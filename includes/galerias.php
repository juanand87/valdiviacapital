<?php
/**
 * Helpers compartidos para Multimedia / Galerías de Video.
 * Incluir en páginas públicas que necesiten renderizar galerías.
 */

// ── Utilidades de URL ──────────────────────────────────────────────────────

function mm_yt_id(string $url): ?string {
    preg_match('/(?:v=|youtu\.be\/|embed\/|shorts\/)([a-zA-Z0-9_\-]{11})/', $url, $m);
    return $m[1] ?? null;
}

function mm_embed_url(string $url, string $tipo): string {
    if ($tipo === 'youtube') {
        $id = mm_yt_id($url);
        return $id ? "https://www.youtube.com/embed/{$id}?rel=0&autoplay=1" : $url;
    }
    return "https://www.facebook.com/plugins/video.php?href=" . urlencode($url) . "&show_text=false&width=640&autoplay=true";
}

function mm_thumb_url(string $url, string $tipo): string {
    if ($tipo === 'youtube') {
        $id = mm_yt_id($url);
        return $id ? "https://img.youtube.com/vi/{$id}/hqdefault.jpg" : '';
    }
    return ''; // Facebook no expone thumbnail fiable por URL
}

function mm_is_reel(array $video): bool {
    return !empty($video['es_reel']);
}

/**
 * Fuerza que el tercer destacado (último de la columna derecha) sea Reel cuando exista uno disponible.
 */
function mm_prioritize_rightmost_reel(array $videos): array {
    if (count($videos) < 3) {
        return $videos;
    }

    $reordered = array_values($videos);
    if (mm_is_reel($reordered[2])) {
        return $reordered;
    }

    for ($i = 3; $i < count($reordered); $i++) {
        if (mm_is_reel($reordered[$i])) {
            $tmp = $reordered[2];
            $reordered[2] = $reordered[$i];
            $reordered[$i] = $tmp;
            break;
        }
    }

    return $reordered;
}

// ── DB ─────────────────────────────────────────────────────────────────────

/**
 * Retorna los videos de una galería respetando el orden definido en galerias_video_items.
 */
function mm_galeria_videos(int $galeriaId, PDO $db): array {
    $stmt = $db->prepare("
        SELECT v.*, c.nombre AS cat_nombre, c.color AS cat_color
        FROM galerias_video_items gi
        INNER JOIN videos v ON v.id = gi.video_id
        LEFT JOIN categorias c ON c.id = v.categoria_id
        WHERE gi.galeria_id = ? AND v.activo = 1
        ORDER BY gi.orden ASC, v.orden ASC
    ");
    $stmt->execute([$galeriaId]);
    return $stmt->fetchAll();
}

// ── Render ─────────────────────────────────────────────────────────────────

/**
 * Renderiza el grid de videos + carrusel.
 * No incluye el wrapper <section> externo — el caller lo provee.
 *
 * @param array $videos  Filas de la tabla `videos` (necesita: titulo, url, tipo, cat_nombre, cat_color)
 */
function mm_render_videos(array $videos, bool $useReelSpotlight = false): string {
    if (empty($videos)) return '';

    $videos         = mm_prioritize_rightmost_reel($videos);
    $featured       = $videos[0];
    $secondary      = array_slice($videos, 1, 2);
    $carousel       = $videos;
    $uid            = 'mmcar' . mt_rand(10000, 99999); // id único para múltiples galerías en la misma página
    $reelSpotlight  = $useReelSpotlight && isset($secondary[1]) && mm_is_reel($secondary[1]) ? $secondary[1] : null;

    ob_start(); ?>
            <?php if ($reelSpotlight): ?>
            <div class="mm-showcase mm-showcase--reel">
                <div class="mm-featured" data-embed="<?= htmlspecialchars(mm_embed_url($featured['url'], $featured['tipo'])) ?>">
                    <?php $ft = mm_thumb_url($featured['url'], $featured['tipo']); ?>
                    <div class="mm-thumb">
                        <?php if ($ft): ?>
                            <img src="<?= htmlspecialchars($ft) ?>" alt="<?= htmlspecialchars($featured['titulo']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="mm-fb-thumb"><i class="fab fa-facebook"></i></div>
                        <?php endif; ?>
                        <div class="mm-play-btn"><i class="fas fa-play"></i></div>
                        <?php if (!empty($featured['cat_nombre'])): ?>
                            <span class="mm-cat-badge" style="background:<?= htmlspecialchars($featured['cat_color'] ?? 'var(--color-primary)') ?>;">
                                <?= htmlspecialchars($featured['cat_nombre']) ?>
                            </span>
                        <?php endif; ?>
                        <span class="mm-format-badge <?= mm_is_reel($featured) ? 'is-reel' : 'is-video' ?>">
                            <?= mm_is_reel($featured) ? 'Reel' : 'Video' ?>
                        </span>
                    </div>
                    <div class="mm-iframe-wrap" style="display:none;">
                        <iframe src="" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" loading="lazy"></iframe>
                    </div>
                    <p class="mm-featured-title"><?= htmlspecialchars($featured['titulo']) ?></p>
                </div>

                <div class="mm-reel-spotlight mm-small-card mm-small-card--reel" data-embed="<?= htmlspecialchars(mm_embed_url($reelSpotlight['url'], $reelSpotlight['tipo'])) ?>">
                    <?php $rt = mm_thumb_url($reelSpotlight['url'], $reelSpotlight['tipo']); ?>
                    <div class="mm-thumb">
                        <?php if ($rt): ?>
                            <img src="<?= htmlspecialchars($rt) ?>" alt="<?= htmlspecialchars($reelSpotlight['titulo']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="mm-fb-thumb"><i class="fab fa-facebook"></i></div>
                        <?php endif; ?>
                        <div class="mm-play-btn"><i class="fas fa-play"></i></div>
                        <span class="mm-format-badge is-reel">Reel</span>
                    </div>
                    <div class="mm-iframe-wrap" style="display:none;">
                        <iframe src="" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" loading="lazy"></iframe>
                    </div>
                    <p class="mm-small-title"><?= htmlspecialchars($reelSpotlight['titulo']) ?></p>
                </div>

                <?php if (count($carousel) > 1): ?>
                <div class="mm-carousel-wrap">
            <?php else: ?>
                <div></div>
            <?php endif; ?>
            <?php else: ?>
            <!-- Grid principal: 1 grande + 2 pequeños -->
            <div class="mm-grid">

                <!-- Video destacado -->
                <div class="mm-featured" data-embed="<?= htmlspecialchars(mm_embed_url($featured['url'], $featured['tipo'])) ?>">
                    <?php $ft = mm_thumb_url($featured['url'], $featured['tipo']); ?>
                    <div class="mm-thumb">
                        <?php if ($ft): ?>
                            <img src="<?= htmlspecialchars($ft) ?>" alt="<?= htmlspecialchars($featured['titulo']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="mm-fb-thumb"><i class="fab fa-facebook"></i></div>
                        <?php endif; ?>
                        <div class="mm-play-btn"><i class="fas fa-play"></i></div>
                        <?php if (!empty($featured['cat_nombre'])): ?>
                            <span class="mm-cat-badge" style="background:<?= htmlspecialchars($featured['cat_color'] ?? 'var(--color-primary)') ?>;">
                                <?= htmlspecialchars($featured['cat_nombre']) ?>
                            </span>
                        <?php endif; ?>
                        <span class="mm-format-badge <?= mm_is_reel($featured) ? 'is-reel' : 'is-video' ?>">
                            <?= mm_is_reel($featured) ? 'Reel' : 'Video' ?>
                        </span>
                    </div>
                    <div class="mm-iframe-wrap" style="display:none;">
                        <iframe src="" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" loading="lazy"></iframe>
                    </div>
                    <p class="mm-featured-title"><?= htmlspecialchars($featured['titulo']) ?></p>
                </div>

                <!-- 2 videos secundarios -->
                <?php if ($secondary): ?>
                <div class="mm-secondary">
                    <?php foreach ($secondary as $sv): ?>
                    <div class="mm-small-card <?= mm_is_reel($sv) ? 'mm-small-card--reel' : '' ?>" data-embed="<?= htmlspecialchars(mm_embed_url($sv['url'], $sv['tipo'])) ?>">
                        <?php $st = mm_thumb_url($sv['url'], $sv['tipo']); ?>
                        <div class="mm-thumb">
                            <?php if ($st): ?>
                                <img src="<?= htmlspecialchars($st) ?>" alt="<?= htmlspecialchars($sv['titulo']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="mm-fb-thumb"><i class="fab fa-facebook"></i></div>
                            <?php endif; ?>
                            <div class="mm-play-btn"><i class="fas fa-play"></i></div>
                            <span class="mm-format-badge <?= mm_is_reel($sv) ? 'is-reel' : 'is-video' ?>">
                                <?= mm_is_reel($sv) ? 'Reel' : 'Video' ?>
                            </span>
                        </div>
                        <div class="mm-iframe-wrap" style="display:none;">
                            <iframe src="" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" loading="lazy"></iframe>
                        </div>
                        <p class="mm-small-title"><?= htmlspecialchars($sv['titulo']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div><!-- /mm-grid -->

            <!-- Carrusel inferior -->
            <?php if (count($carousel) > 1): ?>
            <div class="mm-carousel-wrap">
            <?php endif; ?>
            <?php endif; ?>

            <?php if (count($carousel) > 1): ?>
                <button class="mm-carousel-btn mm-prev" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button>
                <div class="mm-carousel" id="<?= $uid ?>">
                    <?php foreach ($carousel as $cv): ?>
                    <?php $ct = mm_thumb_url($cv['url'], $cv['tipo']); ?>
                    <div class="mm-carousel-item" data-embed="<?= htmlspecialchars(mm_embed_url($cv['url'], $cv['tipo'])) ?>">
                        <div class="mm-thumb">
                            <?php if ($ct): ?>
                                <img src="<?= htmlspecialchars($ct) ?>" alt="<?= htmlspecialchars($cv['titulo']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="mm-fb-thumb mm-fb-thumb--sm"><i class="fab fa-facebook"></i></div>
                            <?php endif; ?>
                            <div class="mm-play-btn mm-play-btn--sm"><i class="fas fa-play"></i></div>
                            <span class="mm-format-badge mm-format-badge--sm <?= mm_is_reel($cv) ? 'is-reel' : 'is-video' ?>">
                                <?= mm_is_reel($cv) ? 'Reel' : 'Video' ?>
                            </span>
                            <div class="mm-carousel-overlay">
                                <p><?= htmlspecialchars($cv['titulo']) ?></p>
                            </div>
                        </div>
                        <div class="mm-iframe-wrap" style="display:none;">
                            <iframe src="" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" loading="lazy"></iframe>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="mm-carousel-btn mm-next" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></button>
            </div>
            <?php endif; ?>
            <?php if ($reelSpotlight): ?>
            </div>
            <?php endif; ?>
    <?php
    return ob_get_clean();
}

// ── Shortcodes ─────────────────────────────────────────────────────────────

/**
 * Parsea shortcodes [galeria slug="xxx"] en el contenido de una noticia
 * y los reemplaza con el bloque de video correspondiente.
 *
 * Uso en el editor: [galeria slug="nombre-de-la-galeria"]
 */
function parseGaleriaShortcodes(string $content, PDO $db): string {
    return preg_replace_callback(
        '/\[galeria\s+slug=["\']([a-z0-9\-_]+)["\']\s*\/?\]/i',
        function (array $m) use ($db) {
            $slug = $m[1];
            try {
                $stmt = $db->prepare("SELECT * FROM galerias_video WHERE slug = ? AND activo = 1");
                $stmt->execute([$slug]);
                $galeria = $stmt->fetch();
                if (!$galeria) return '';
                $videos = mm_galeria_videos((int)$galeria['id'], $db);
                if (empty($videos)) return '';
                return '<div class="galeria-shortcode">'
                    . '<p class="galeria-shortcode-titulo"><i class="fas fa-film"></i> '
                    . htmlspecialchars($galeria['titulo']) . '</p>'
                    . mm_render_videos($videos)
                    . '</div>';
            } catch (\Exception $e) {
                return '';
            }
        },
        $content
    );
}
