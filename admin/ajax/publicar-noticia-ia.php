<?php
/**
 * AJAX: Publicar noticia generada por IA en la tabla noticias
 */
require_once '../../includes/config.php';
require_once '../../includes/cache.php';
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

// Soportar tanto form data como JSON
$input = $_POST;
if (empty($input) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

$titulo      = trim($input['titulo']      ?? '');
$contenido   = trim($input['contenido']   ?? '');
$categoria_id = (int)($input['category_id'] ?? $input['categoria_id'] ?? 0);
$noticia_id  = (int)($input['noticia_id'] ?? $input['medios_contenido_id'] ?? 0);
$autor_id    = (int)$_SESSION['admin_id'];

// Manejar comunas: puede venir como array directo o como JSON string
$comunas_ids = [];
if (isset($input['comunas_ids'])) {
    // Caso 1: Array directo desde JSON fetch
    if (is_array($input['comunas_ids'])) {
        $comunas_ids = array_map('intval', $input['comunas_ids']);
    } else {
        // Caso 2: String JSON desde form data
        $comunas_ids = json_decode($input['comunas_ids'], true) ?? [];
        $comunas_ids = array_map('intval', $comunas_ids);
    }
} else if (isset($input['comunas'])) {
    // Caso 3: Parámetro antiguo 'comunas'
    $comunas_json = trim($input['comunas'] ?? '[]');
    $comunas_ids = json_decode($comunas_json, true) ?? [];
    $comunas_ids = array_map('intval', $comunas_ids);
}

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
if (empty($comunas_ids)) {
    echo json_encode(['error' => 'Debes seleccionar al menos una comuna']);
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

// Manejar imagen principal (usar la de la noticia escaneada)
$imagen_principal = '';

// Insertar en tabla noticias
try {
    $stmt = $db->prepare("
        INSERT INTO noticias (titulo, slug, contenido, imagen_principal, categoria_id, autor_id, publicado, fecha_publicacion, created_at, updated_at)
        VALUES (:titulo, :slug, :contenido, :imagen_principal, :categoria_id, :autor_id, 1, NOW(), NOW(), NOW())
    ");
    $stmt->execute([
        ':titulo'           => $titulo,
        ':slug'             => $slug,
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

// Insertar comunas en noticias_comunas
foreach ($comunas_ids as $comuna_id) {
    try {
        $stmt = $db->prepare("INSERT INTO noticias_comunas (noticia_id, comuna_id) VALUES (:noticia_id, :comuna_id)");
        $stmt->execute([':noticia_id' => $nueva_id, ':comuna_id' => $comuna_id]);
    } catch (PDOException $e) {
        // Ignorar duplicados
    }
}

// Marcar la noticia escaneada como publicada
if ($noticia_id > 0) {
    $stmt = $db->prepare("UPDATE medios_contenido_sincronizado SET estado = 'publicado' WHERE id = :id");
    $stmt->execute([':id' => $noticia_id]);
}

cacheInvalidateHomepage();

echo json_encode(['success' => true, 'id' => $nueva_id, 'slug' => $slug]);
