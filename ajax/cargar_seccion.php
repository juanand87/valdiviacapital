<?php
/**
 * ajax/cargar_seccion.php
 * Devuelve JSON {html, hasMore} con tarjetas de noticias para infinite scroll en seccion.php
 */
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$cat    = $_GET['cat'] ?? '';
$page   = max(1, (int)($_GET['p'] ?? 1));
$per    = 12;
$offset = ($page - 1) * $per;

if ($cat === '') {
    echo json_encode(['html' => '', 'hasMore' => false]);
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM categorias WHERE slug = ? AND activo = 1");
$stmt->execute([$cat]);
$categoria = $stmt->fetch();

if (!$categoria) {
    echo json_encode(['html' => '', 'hasMore' => false]);
    exit;
}

$stmtN = $db->prepare("
    SELECT n.*, c.nombre as categoria_nombre, c.slug as categoria_slug,
           c.color as categoria_color, u.nombre as autor_nombre
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    INNER JOIN usuarios u ON n.autor_id = u.id
    WHERE n.categoria_id = ? AND n.publicado = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT $per OFFSET $offset
");
$stmtN->execute([$categoria['id']]);
$noticias = $stmtN->fetchAll();

$stmtT = $db->prepare("SELECT COUNT(*) FROM noticias WHERE categoria_id = ? AND publicado = 1");
$stmtT->execute([$categoria['id']]);
$total = (int)$stmtT->fetchColumn();

ob_start();
foreach ($noticias as $noticia): ?>
<article class="news-card fade-in">
    <a href="noticia.php?slug=<?php echo clean($noticia['slug']); ?>">
        <div class="news-image">
            <?php if ($noticia['imagen_principal']): ?>
                <img src="<?php echo clean($noticia['imagen_principal']); ?>" alt="<?php echo clean($noticia['titulo']); ?>" loading="lazy">
            <?php else: ?>
                <img src="https://picsum.photos/seed/<?php echo $noticia['id']; ?>sec/600/400" alt="<?php echo clean($noticia['titulo']); ?>" loading="lazy">
            <?php endif; ?>
            <span class="category-badge" style="background: <?php echo $noticia['categoria_color']; ?>;">
                <?php echo strtoupper($noticia['categoria_nombre']); ?>
            </span>
        </div>
        <div class="news-body">
            <h3 class="news-title"><?php echo clean($noticia['titulo']); ?></h3>
            <p class="news-excerpt">
                <?php echo clean(truncate(strip_tags($noticia['bajada'] ?? $noticia['contenido']), 120)); ?>
            </p>
            <div class="news-meta">
                <span><i class="far fa-clock"></i> <?php echo timeAgo($noticia['fecha_publicacion']); ?></span>
                <span><i class="fas fa-eye"></i> <?php echo number_format($noticia['vistas']); ?> vistas</span>
            </div>
        </div>
    </a>
</article>
<?php endforeach;
$html = ob_get_clean();

echo json_encode([
    'html'    => $html,
    'hasMore' => ($page * $per) < $total,
]);
