<?php
require_once '../../includes/config.php';
header('Content-Type: application/json');

// Solo administradores
session_start();
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['archivo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibió ningún archivo']);
    exit;
}

$file = $_FILES['archivo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Error al subir el archivo (código ' . $file['error'] . ')']);
    exit;
}

// Tamaño máximo: 10 MB
$maxBytes = 10 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    echo json_encode(['error' => 'El archivo supera el límite de 10 MB']);
    exit;
}

// Verificar MIME real (no confiar en la extensión ni en $_FILES['type'])
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeReal = $finfo->file($file['tmp_name']);

$mimePermitidos = [
    // Imágenes
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    // Documentos
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];

if (!in_array($mimeReal, $mimePermitidos, true)) {
    echo json_encode(['error' => 'Tipo de archivo no permitido: ' . htmlspecialchars($mimeReal)]);
    exit;
}

// Extensión segura basada en MIME
$extMap = [
    'image/jpeg'    => 'jpg',
    'image/png'     => 'png',
    'image/gif'     => 'gif',
    'image/webp'    => 'webp',
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
];
$ext = $extMap[$mimeReal];

// Nombre único: timestamp + random + ext
$nombreArchivo = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

// Subdirectorio por año/mes
$subdir = date('Y/m');
$dirFisico = __DIR__ . '/../../uploads/medios/' . $subdir;
if (!is_dir($dirFisico)) {
    mkdir($dirFisico, 0755, true);
}

$rutaFisica = $dirFisico . '/' . $nombreArchivo;
$rutaRelativa = 'uploads/medios/' . $subdir . '/' . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
    echo json_encode(['error' => 'Error al guardar el archivo en el servidor']);
    exit;
}

// Dimensiones para imágenes
$ancho = null;
$alto  = null;
if (str_starts_with($mimeReal, 'image/')) {
    $size = @getimagesize($rutaFisica);
    if ($size) {
        $ancho = $size[0];
        $alto  = $size[1];
    }
}

// Guardar en BD
try {
    $db = getDB();
    $db->prepare("
        INSERT INTO medios (nombre_original, nombre_archivo, ruta, tipo_mime, tamano, ancho, alto, autor_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        basename($file['name']),
        $nombreArchivo,
        $rutaRelativa,
        $mimeReal,
        $file['size'],
        $ancho,
        $alto,
        $_SESSION['admin_id'],
    ]);
    $id = $db->lastInsertId();
} catch (PDOException $e) {
    // Revertir el archivo si falla la BD
    @unlink($rutaFisica);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'ok'     => true,
    'id'     => (int)$id,
    'url'    => SITE_URL . '/' . $rutaRelativa,
    'ruta'   => $rutaRelativa,
    'nombre' => basename($file['name']),
    'mime'   => $mimeReal,
    'ancho'  => $ancho,
    'alto'   => $alto,
    'tamano' => $file['size'],
]);
