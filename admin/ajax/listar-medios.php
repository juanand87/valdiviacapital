<?php
require_once '../../includes/config.php';
session_start();
if (empty($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); exit; }

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$medios = $db->query(
    "SELECT id, nombre_original, nombre_archivo, ruta, tipo_mime, tamano, ancho, alto, created_at
     FROM medios ORDER BY created_at DESC LIMIT 500"
)->fetchAll();

$resultado = array_map(function($m) {
    $m['url'] = SITE_URL . '/' . $m['ruta'];
    return $m;
}, $medios);

echo json_encode(['medios' => $resultado]);
