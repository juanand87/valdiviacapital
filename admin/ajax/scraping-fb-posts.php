<?php
/**
 * AJAX: Devuelve las publicaciones guardadas de una página de Facebook.
 */

require_once '../../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Sesión expirada.']);
    exit;
}

$db       = getDB();
$paginaId = (int)($_GET['pagina_id'] ?? 0);

if ($paginaId <= 0) {
    echo json_encode(['error' => 'Página no válida.']);
    exit;
}

$stmt = $db->prepare(
    "SELECT post_id, url_post, imagen_url, texto, fecha_post
       FROM fb_scraping_posts
      WHERE pagina_id = ?
      ORDER BY COALESCE(fecha_post, '1970-01-01') DESC, id DESC
      LIMIT 2"
);
$stmt->execute([$paginaId]);
$rows = $stmt->fetchAll();

$posts = array_map(function ($row) {
    return [
        'post_id'    => $row['post_id'],
        'url_post'   => $row['url_post'],
        'imagen_url' => $row['imagen_url'],
        'texto'      => $row['texto'],
        'fecha_post' => $row['fecha_post'] ? date('d-m-Y', strtotime($row['fecha_post'])) : null,
    ];
}, $rows);

echo json_encode(['posts' => $posts]);
