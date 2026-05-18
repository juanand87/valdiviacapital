<?php
/**
 * AJAX: Devuelve los posts guardados de un perfil de Instagram.
 */
require_once '../../includes/config.php';
require_once '../../admin/includes/auth.php';
verificarSesion();

header('Content-Type: application/json; charset=utf-8');

$db       = getDB();
$perfilId = (int)($_GET['perfil_id'] ?? 0);

if ($perfilId <= 0) {
    echo json_encode(['posts' => []]);
    exit;
}

$stmt = $db->prepare("SELECT * FROM ig_scraping_posts WHERE perfil_id = ? ORDER BY fecha_post DESC, id DESC LIMIT 2");
$stmt->execute([$perfilId]);
$rows = $stmt->fetchAll();

$posts = [];
foreach ($rows as $r) {
    $posts[] = [
        'shortcode'  => $r['shortcode'],
        'tipo'       => $r['tipo'],
        'url_post'   => $r['url_post'],
        'imagen_url' => $r['imagen_url'],
        'caption'    => $r['caption'],
        'likes'      => (int)$r['likes'],
        'fecha_post' => $r['fecha_post'] ? date('d-m-Y', strtotime($r['fecha_post'])) : null,
    ];
}

echo json_encode(['posts' => $posts]);
