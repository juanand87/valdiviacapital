<?php
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

// Sanitizar IDs a excluir
$excludeRaw = $_GET['exclude'] ?? '';
$excludeIds = [];
foreach (explode(',', $excludeRaw) as $rawId) {
    $id = (int) trim($rawId);
    if ($id > 0) $excludeIds[] = $id;
}
if (empty($excludeIds)) $excludeIds = [0];

$limite = max(1, min(24, (int) ($_GET['limite'] ?? 6)));

$placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
$db = getDB();
$stmt = $db->prepare("
    SELECT n.id, n.titulo, n.slug, n.bajada, n.imagen_principal, n.vistas, n.fecha_publicacion,
           c.nombre AS cat_nombre, c.color AS cat_color
    FROM noticias n
    JOIN categorias c ON c.id = n.categoria_id
    WHERE n.publicado = 1 AND n.id NOT IN ($placeholders)
    ORDER BY n.fecha_publicacion DESC
    LIMIT $limite
");
$stmt->execute($excludeIds);
$noticias = $stmt->fetchAll();

$hasMore = count($noticias) === $limite;
$newIds  = array_map('intval', array_column($noticias, 'id'));

$html = '';
foreach ($noticias as $n) {
    $img = $n['imagen_principal'] ?: 'https://picsum.photos/seed/' . $n['id'] . 'news/600/400';
    $esUltimaHora = strtotime($n['fecha_publicacion']) > strtotime('-2 hours');
    $badgeUH = $esUltimaHora
        ? '<span class="badge-ultima-hora"><i class="fas fa-bolt"></i> Última hora</span>'
        : '';
    $bajada = $n['bajada']
        ? '<p class="news-excerpt">' . clean(truncate($n['bajada'], 100)) . '</p>'
        : '';

    $html .= '
<article class="news-card fade-in">
    <a href="noticia.php?slug=' . clean($n['slug']) . '">
        <div class="news-image">
            <img src="' . clean($img) . '" alt="' . clean($n['titulo']) . '">
            <span class="category-badge" style="background:' . clean($n['cat_color']) . ';">' . clean($n['cat_nombre']) . '</span>
            ' . $badgeUH . '
        </div>
        <div class="news-body">
            <div class="news-cat-label">' . clean($n['cat_nombre']) . '</div>
            <h3 class="news-title">' . clean($n['titulo']) . '</h3>
            ' . $bajada . '
            <div class="news-meta">
                <span><i class="far fa-clock"></i> ' . timeAgo($n['fecha_publicacion']) . '</span>
                <span><i class="fas fa-eye"></i> ' . number_format($n['vistas'], 0, ',', '.') . '</span>
            </div>
        </div>
    </a>
</article>';
}

echo json_encode([
    'html'    => $html,
    'hasMore' => $hasMore,
    'newIds'  => $newIds,
]);
