<?php
require_once '../includes/auth.php';
verificarSesion();
require_once '../../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$flagFile = __DIR__ . '/../../cache/maintenance.flag';
$estado = $_POST['estado'] ?? '0';

if ($estado === '1') {
    file_put_contents($flagFile, '1');
    $activo = true;
} else {
    if (file_exists($flagFile)) {
        unlink($flagFile);
    }
    $activo = false;
}

echo json_encode(['ok' => true, 'mantenimiento' => $activo]);
