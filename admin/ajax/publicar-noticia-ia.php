<?php
/**
 * AJAX: Publicar noticia generada por IA en la tabla noticias
 */
require_once '../../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$titulo      = trim($_POST['titulo']      ?? '');
$contenido   = trim($_POST['contenido']   ?? '');
$bajada      = trim($_POST['bajada']      ?? '');
$categoria_id = (int)($_POST['categoria_id'] ?? 0);
$noticia_id  = (int)($_POST['noticia_id'] ?? 0);
$imagen_url  = trim($_POST['imagen_url']  ?? '');
$autor_id    = (int)$_SESSION['admin_id'];

// Convertir Markdown a HTML limpio
function markdownToHTML($md) {
    $lines   = explode("\n", $md);
    $html    = '';
    $inList  = false;

    foreach ($lines as $line) {
        // Headings
        if (preg_match('/^#{4}\s+(.+)/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h4>' . $m[1] . '</h4>' . "\n";
        } elseif (preg_match('/^#{3}\s+(.+)/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h3>' . $m[1] . '</h3>' . "\n";
        } elseif (preg_match('/^#{2}\s+(.+)/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h2>' . $m[1] . '</h2>' . "\n";
        } elseif (preg_match('/^#{1}\s+(.+)/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h2>' . $m[1] . '</h2>' . "\n";
        // Listas (- item o * item)
        } elseif (preg_match('/^[\*\-]\s+(.+)/', $line, $m)) {
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $html .= '<li>' . $m[1] . '</li>' . "\n";
        // Línea vacía → cierra lista o párrafo
        } elseif (trim($line) === '') {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= "\n";
        // Párrafo normal
        } else {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<p>' . trim($line) . '</p>' . "\n";
        }
    }
    if ($inList) $html .= '</ul>';

    // Inline: **negrita**, *cursiva*, __negrita__, _cursiva_
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/__(.+?)__/s',     '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/s',     '<em>$1</em>',         $html);
    $html = preg_replace('/_(.+?)_/s',       '<em>$1</em>',         $html);

    // Limpiar párrafos vacíos
    $html = preg_replace('/<p>\s*<\/p>/', '', $html);

    return trim($html);
}

$contenido = markdownToHTML($contenido);

if (!$titulo) {
    echo json_encode(['error' => 'El título es obligatorio']);
    exit;
}
if (!$contenido) {
    echo json_encode(['error' => 'El contenido no puede estar vacío']);
    exit;
}
if (!$categoria_id) {
    echo json_encode(['error' => 'Debes seleccionar una categoría']);
    exit;
}

$db = getDB();

// Verificar que la categoría existe
$stmt = $db->prepare("SELECT id FROM categorias WHERE id = :id");
$stmt->execute([':id' => $categoria_id]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Categoría no válida']);
    exit;
}

// Generar slug único
function generarSlug($texto) {
    $texto = mb_strtolower($texto, 'UTF-8');
    $from  = ['á','é','í','ó','ú','ü','ñ','à','â','ê','î','ô','û','ä','ë','ï','ö'];
    $to    = ['a','e','i','o','u','u','n','a','a','e','i','o','u','a','e','i','o'];
    $texto = str_replace($from, $to, $texto);
    $texto = preg_replace('/[^a-z0-9\s-]/', '', $texto);
    $texto = preg_replace('/[\s-]+/', '-', trim($texto));
    return substr($texto, 0, 200);
}

$slugBase = generarSlug($titulo);
$slug = $slugBase;
$i = 1;
while (true) {
    $stmt = $db->prepare("SELECT id FROM noticias WHERE slug = :slug");
    $stmt->execute([':slug' => $slug]);
    if (!$stmt->fetch()) break;
    $slug = $slugBase . '-' . $i;
    $i++;
}

// Manejar imagen principal
$imagen_principal = '';

if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../uploads/noticias/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowedExt)) {
        echo json_encode(['error' => 'Formato de imagen no permitido. Usa JPG, PNG, GIF o WEBP.']);
        exit;
    }
    $filename = 'ia_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $filename)) {
        $imagen_principal = 'uploads/noticias/' . $filename;
    }
} elseif (!empty($imagen_url)) {
    $imagen_principal = $imagen_url;
}

// Insertar en tabla noticias
try {
    $stmt = $db->prepare("
        INSERT INTO noticias (titulo, slug, bajada, contenido, imagen_principal, categoria_id, autor_id, publicado, fecha_publicacion, created_at, updated_at)
        VALUES (:titulo, :slug, :bajada, :contenido, :imagen_principal, :categoria_id, :autor_id, 1, NOW(), NOW(), NOW())
    ");
    $stmt->execute([
        ':titulo'           => $titulo,
        ':slug'             => $slug,
        ':bajada'           => $bajada ?: null,
        ':contenido'        => $contenido,
        ':imagen_principal' => $imagen_principal ?: null,
        ':categoria_id'     => $categoria_id,
        ':autor_id'         => $autor_id,
    ]);
    $nueva_id = $db->lastInsertId();
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error al guardar la noticia: ' . $e->getMessage()]);
    exit;
}

// Marcar la noticia escaneada como publicada
if ($noticia_id > 0) {
    $stmt = $db->prepare("UPDATE medios_contenido_sincronizado SET estado = 'publicado' WHERE id = :id");
    $stmt->execute([':id' => $noticia_id]);
}

echo json_encode(['success' => true, 'id' => $nueva_id, 'slug' => $slug]);
