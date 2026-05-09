<?php
require_once '../../includes/config.php';
header('Content-Type: application/json');

session_start();
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT ruta FROM medios WHERE id = ?");
$stmt->execute([$id]);
$medio = $stmt->fetch();

if (!$medio) {
    echo json_encode(['error' => 'Archivo no encontrado']);
    exit;
}

// Eliminar archivo físico
$rutaFisica = __DIR__ . '/../../' . $medio['ruta'];
if (file_exists($rutaFisica)) {
    @unlink($rutaFisica);
}

$db->prepare("DELETE FROM medios WHERE id = ?")->execute([$id]);

echo json_encode(['ok' => true]);
