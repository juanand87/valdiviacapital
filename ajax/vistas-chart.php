<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

// Solo administradores pueden ver los datos de estadísticas
session_start();
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$noticiaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$noticiaId || $noticiaId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$db = getDB();

// Últimos 7 días (rellenando los días sin datos con 0)
$stmt = $db->prepare("
    SELECT fecha, vistas
    FROM vistas_diarias
    WHERE noticia_id = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ORDER BY fecha ASC
");
$stmt->execute([$noticiaId]);
$rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Generar los 7 días y rellenar huecos
$labels = [];
$data   = [];
for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($fecha));
    $data[]   = isset($rows[$fecha]) ? (int)$rows[$fecha] : 0;
}

echo json_encode(['labels' => $labels, 'data' => $data]);
