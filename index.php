<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/cache.php';
require_once 'includes/banners.php';
if (isMaintenance()) { include 'mantenimiento.php'; exit; }

$db = getDB();

// Noticia destacada (hero principal) — con caché de 5 minutos
$cached = cacheGet('homepage_main');
if ($cached) {
    ['hero' => $hero, 'heroGrid' => $heroGrid, 'noticias' => $noticias, 'shownIds' => $shownIds] = $cached;
} else {
    $hero = $db->query("
        SELECT n.*, c.nombre AS cat_nombre, c.color AS cat_color, u.nombre AS autor_nombre
        FROM noticias n
        JOIN categorias c ON c.id = n.categoria_id
        JOIN usuarios u ON u.id = n.autor_id
        WHERE n.publicado = 1 AND n.destacado = 1
        ORDER BY n.fecha_publicacion DESC
        LIMIT 1
    ")->fetch();

    // Si no hay destacada, tomar la más reciente
    if (!$hero) {
        $hero = $db->query("
            SELECT n.*, c.nombre AS cat_nombre, c.color AS cat_color, u.nombre AS autor_nombre
            FROM noticias n
            JOIN categorias c ON c.id = n.categoria_id
            JOIN usuarios u ON u.id = n.autor_id
            WHERE n.publicado = 1
            ORDER BY n.fecha_publicacion DESC
            LIMIT 1
        ")->fetch();
    }

    // 3 noticias secundarias del hero (excluir la principal)
    $heroId = $hero ? $hero['id'] : 0;
    $stmtGrid = $db->prepare("
        SELECT n.*, c.nombre AS cat_nombre, c.color AS cat_color
        FROM noticias n
        JOIN categorias c ON c.id = n.categoria_id
        WHERE n.publicado = 1 AND n.id != :id
        ORDER BY n.fecha_publicacion DESC
        LIMIT 3
    ");
    $stmtGrid->execute([':id' => $heroId]);
    $heroGrid = $stmtGrid->fetchAll();

    // Últimas noticias (sin las del hero)
    $heroIds = array_merge([$heroId], array_column($heroGrid, 'id'));
    $placeholders = implode(',', array_fill(0, count($heroIds), '?'));
    $stmtNews = $db->prepare("
        SELECT n.*, c.nombre AS cat_nombre, c.color AS cat_color
        FROM noticias n
        JOIN categorias c ON c.id = n.categoria_id
        WHERE n.publicado = 1 AND n.id NOT IN ($placeholders)
        ORDER BY n.fecha_publicacion DESC
        LIMIT 12
    ");
    $stmtNews->execute($heroIds);
    $noticias = $stmtNews->fetchAll();

    // IDs ya mostrados (para el cargar más)
    $shownIds = array_merge($heroIds, array_column($noticias, 'id'));
    $shownIds = array_map('intval', array_unique($shownIds));

    cacheSet('homepage_main', compact('hero', 'heroGrid', 'noticias', 'shownIds'));
}

// Lo más leído (sidebar) — caché 10 minutos (vistas cambian con más frecuencia)
$trending = cacheGet('homepage_trending', 600);
if ($trending === false) {
    $trending = $db->query("
        SELECT n.id, n.titulo, n.slug, n.vistas
        FROM noticias n
        WHERE n.publicado = 1
        ORDER BY n.vistas DESC
        LIMIT 5
    ")->fetchAll();
    cacheSet('homepage_trending', $trending);
}

// Ticker: últimos títulos
$tickerNoticias = cacheGet('homepage_ticker');
if ($tickerNoticias === false) {
    $tickerNoticias = $db->query("
        SELECT titulo FROM noticias WHERE publicado = 1 ORDER BY fecha_publicacion DESC LIMIT 8
    ")->fetchAll(PDO::FETCH_COLUMN);
    cacheSet('homepage_ticker', $tickerNoticias);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valdivia Capital - Noticias de la Región</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- Barra superior -->
    <div class="top-header">
        <div class="container">
            <div class="top-header-content">
                <div class="date">
                    <i class="far fa-calendar-alt"></i>
                    <span id="current-date"></span>
                </div>
                <div class="social-links">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Twitter / X"><i class="fab fa-x-twitter"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="YouTube"><i class="fab fa-youtube"></i></a>
                    <button id="btn-dark-mode" title="Cambiar tema"><i class="fas fa-moon"></i> <span class="dm-label">Oscuro</span></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Header principal -->
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php">
                        <img src="https://valdiviacapital.cl/logovc.png" alt="Valdivia Capital" class="site-logo">
                    </a>
                </div>
                <div class="header-search">
                    <form class="search-form" action="busqueda.php" method="GET">
                        <input type="text" name="q" placeholder="Buscar noticias...">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Navegación -->
    <nav class="main-nav">
        <div class="container">
            <div class="nav-content">
                <a href="index.php" class="active"><i class="fas fa-home"></i> Inicio</a>
                <a href="seccion.php?cat=regional">Regional</a>
                <a href="seccion.php?cat=politica">Política</a>
                <a href="seccion.php?cat=economia">Economía</a>
                <a href="seccion.php?cat=deportes">Deportes</a>
                <a href="seccion.php?cat=cultura">Cultura</a>
                <a href="seccion.php?cat=turismo">Turismo</a>
                <?php
                $todasComunas = $db->query("SELECT id, nombre, slug FROM comunas ORDER BY nombre")->fetchAll();
                ?>
                <div class="nav-dropdown">
                    <a href="#" class="nav-dropdown-toggle">
                        <i class="fas fa-map-marker-alt"></i> Regi&oacute;n <i class="fas fa-chevron-down" style="font-size:10px;"></i>
                    </a>
                    <ul class="nav-dropdown-menu">
                        <?php foreach ($todasComunas as $c): ?>
                        <li><a href="comuna.php?comuna=<?php echo $c['slug']; ?>"><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($c['nombre']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <?php renderBanner('leaderboard'); ?>

    <!-- Ticker dinámico -->
    <div class="breaking-news">
        <div class="container" style="display:flex;align-items:center;width:100%;overflow:hidden;">
            <span class="breaking-label"><i class="fas fa-bolt"></i> Último momento</span>
            <div class="ticker-wrap">
                <div class="ticker-text">
                    <?php foreach ($tickerNoticias as $t): ?>
                        <span><?= clean($t) ?></span>
                        <span class="sep">&bull;</span>
                    <?php endforeach; ?>
                    <?php foreach ($tickerNoticias as $t): ?>
                        <span><?= clean($t) ?></span>
                        <span class="sep">&bull;</span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ======== PORTADA / HERO ======== -->
    <section class="hero-section">
        <div class="container">

            <?php if ($hero): ?>
            <!-- Noticia destacada principal -->
            <a href="noticia.php?slug=<?= clean($hero['slug']) ?>" class="hero-featured">
                <div class="hero-image">
                    <?php if ($hero['imagen_principal']): ?>
                        <img src="<?= clean($hero['imagen_principal']) ?>" alt="<?= clean($hero['titulo']) ?>" loading="lazy">
                    <?php else: ?>
                        <img src="https://picsum.photos/seed/<?= $hero['id'] ?>/800/500" alt="<?= clean($hero['titulo']) ?>" loading="lazy">
                    <?php endif; ?>
                    <?php if (strtotime($hero['fecha_publicacion']) > strtotime('-2 hours')): ?>
                        <span class="badge-ultima-hora"><i class="fas fa-bolt"></i> Última hora</span>
                    <?php else: ?>
                        <span class="hero-badge"><i class="fas fa-star"></i> Destacado</span>
                    <?php endif; ?>
                </div>
                <div class="hero-content">
                    <div class="hero-category"><?= clean($hero['cat_nombre']) ?></div>
                    <h2 class="hero-title"><?= clean($hero['titulo']) ?></h2>
                    <?php if ($hero['bajada']): ?>
                        <p class="hero-excerpt"><?= clean($hero['bajada']) ?></p>
                    <?php endif; ?>
                    <div class="hero-meta">
                        <span><i class="far fa-clock"></i> <?= timeAgo($hero['fecha_publicacion']) ?></span>
                        <span><i class="far fa-user"></i> <?= clean($hero['autor_nombre']) ?></span>
                        <span><i class="fas fa-eye"></i> <?= number_format($hero['vistas'], 0, ',', '.') ?> vistas</span>
                    </div>
                    <span class="btn-primary">Leer noticia <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>
            <?php endif; ?>

            <!-- Grid secundario: 3 noticias -->
            <?php if ($heroGrid): ?>
            <div class="hero-grid">
                <?php foreach ($heroGrid as $g): ?>
                <a href="noticia.php?slug=<?= clean($g['slug']) ?>" class="hero-grid-card">
                    <div class="hero-grid-img">
                        <?php if ($g['imagen_principal']): ?>
                            <img src="<?= clean($g['imagen_principal']) ?>" alt="<?= clean($g['titulo']) ?>" loading="lazy">
                        <?php else: ?>
                            <img src="https://picsum.photos/seed/<?= $g['id'] ?>grid/600/400" alt="<?= clean($g['titulo']) ?>" loading="lazy">
                        <?php endif; ?>
                        <span class="category-badge" style="background:<?= clean($g['cat_color']) ?>;"><?= clean($g['cat_nombre']) ?></span>
                    </div>
                    <div class="hero-grid-body">
                        <div class="hero-grid-cat"><?= clean($g['cat_nombre']) ?></div>
                        <h3 class="hero-grid-title"><?= clean($g['titulo']) ?></h3>
                    </div>
                    <div class="hero-grid-meta">
                        <span><i class="far fa-clock"></i> <?= timeAgo($g['fecha_publicacion']) ?></span>
                        <span><i class="fas fa-eye"></i> <?= number_format($g['vistas'], 0, ',', '.') ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </section>

    <!-- Banner Billboard -->
    <?php renderBanner('billboard'); ?>

    <!-- ======== CONTENIDO PRINCIPAL + SIDEBAR ======== -->
    <div class="container">
        <div class="content-layout">

            <!-- Columna principal -->
            <main>
                <section class="news-section">
                    <h2 class="section-title"><i class="fas fa-newspaper"></i> Últimas Noticias</h2>
                    <div class="news-grid" id="news-grid">

                        <?php if ($noticias): ?>
                            <?php foreach ($noticias as $n): ?>
                            <article class="news-card fade-in">
                                <a href="noticia.php?slug=<?= clean($n['slug']) ?>">
                                    <div class="news-image">
                                        <?php if ($n['imagen_principal']): ?>
                                            <img src="<?= clean($n['imagen_principal']) ?>" alt="<?= clean($n['titulo']) ?>" loading="lazy">
                                        <?php else: ?>
                                            <img src="https://picsum.photos/seed/<?= $n['id'] ?>news/600/400" alt="<?= clean($n['titulo']) ?>" loading="lazy">
                                        <?php endif; ?>
                                        <span class="category-badge" style="background:<?= clean($n['cat_color']) ?>;"><?= clean($n['cat_nombre']) ?></span>
                                        <?php if (strtotime($n['fecha_publicacion']) > strtotime('-2 hours')): ?>
                                        <span class="badge-ultima-hora"><i class="fas fa-bolt"></i> Última hora</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="news-body">
                                        <div class="news-cat-label"><?= clean($n['cat_nombre']) ?></div>
                                        <h3 class="news-title"><?= clean($n['titulo']) ?></h3>
                                        <?php if ($n['bajada']): ?>
                                            <p class="news-excerpt"><?= clean($n['bajada']) ?></p>
                                        <?php endif; ?>
                                        <div class="news-meta">
                                            <span><i class="far fa-clock"></i> <?= timeAgo($n['fecha_publicacion']) ?></span>
                                            <span><i class="fas fa-eye"></i> <?= number_format($n['vistas'], 0, ',', '.') ?></span>
                                        </div>
                                    </div>
                                </a>
                            </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:var(--color-gray);padding:20px 0;">No hay noticias disponibles.</p>
                        <?php endif; ?>

                    </div><!-- /news-grid -->

                    <!-- Botón cargar más -->
                    <div class="load-more-wrap" id="load-more-wrap">
                        <button id="btn-cargar-mas" class="btn-load-more">
                            <i class="fas fa-sync-alt spinner"></i>
                            <span class="label"><i class="fas fa-plus-circle"></i> Cargar más noticias</span>
                        </button>
                    </div>
                </section>
            </main>

            <!-- Sidebar -->
            <aside class="sidebar">

                <!-- Lo más leído -->
                <div class="widget">
                    <h3 class="widget-title"><i class="fas fa-fire" style="color:var(--color-primary);margin-right:6px;"></i>Lo Más Leído</h3>
                    <div class="trending-list">
                        <?php foreach ($trending as $i => $t): ?>
                        <div class="trending-item">
                            <div class="trending-number"><?= $i + 1 ?></div>
                            <div class="trending-info">
                                <h4><a href="noticia.php?slug=<?= clean($t['slug']) ?>"><?= clean($t['titulo']) ?></a></h4>
                                <span><?= number_format($t['vistas'], 0, ',', '.') ?> vistas</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Widget sidebar banner -->
                <?php if (($bSide = getBanner('sidebar'))): ?>
                <div class="widget ad-sidebar" style="padding:14px;">
                    <span class="ad-label">Publicidad</span>
                    <a href="ajax/banner-click.php?id=<?php echo (int)$bSide['id']; ?>" target="_blank" rel="noopener nofollow sponsored">
                        <img src="<?php echo htmlspecialchars($bSide['imagen_url']); ?>" alt="<?php echo htmlspecialchars($bSide['titulo']); ?>" loading="lazy" style="width:100%;border-radius:6px;">
                    </a>
                </div>
                <?php endif; ?>

                <!-- Newsletter -->
                <div class="widget newsletter-widget">
                    <h3 class="widget-title">Boletín Informativo</h3>
                    <p>Recibe las noticias más importantes directamente en tu correo.</p>
                    <form class="newsletter-form" id="newsletter-form">
                        <input type="email" placeholder="Tu correo electrónico" required>
                        <button type="submit"><i class="fas fa-paper-plane"></i> Suscribirme</button>
                    </form>
                </div>

                <!-- Clima -->
                <div class="widget">
                    <h3 class="widget-title"><i class="fas fa-cloud-sun" style="color:var(--color-primary);margin-right:6px;"></i>Clima Regional</h3>
                    <div class="weather-item">
                        <div><div class="weather-city">Valdivia</div><div class="weather-desc">Parcialmente nublado</div></div>
                        <div class="weather-temp">15°C</div>
                    </div>
                    <div class="weather-item">
                        <div><div class="weather-city">Osorno</div><div class="weather-desc">Lluvias ligeras</div></div>
                        <div class="weather-temp">12°C</div>
                    </div>
                    <div class="weather-item">
                        <div><div class="weather-city">La Unión</div><div class="weather-desc">Despejado</div></div>
                        <div class="weather-temp">18°C</div>
                    </div>
                </div>

            </aside>
        </div>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <span class="footer-logo-text">VALDIVIA CAPITAL</span>
                    <p style="color:rgba(255,255,255,0.7);margin-top:10px;">El principal medio de comunicación digital de la región, comprometido con la información veraz y oportuna.</p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-x-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Secciones</h3>
                    <ul>
                        <li><a href="seccion.php?cat=regional">Regional</a></li>
                        <li><a href="seccion.php?cat=politica">Política</a></li>
                        <li><a href="seccion.php?cat=economia">Economía</a></li>
                        <li><a href="seccion.php?cat=deportes">Deportes</a></li>
                        <li><a href="seccion.php?cat=cultura">Cultura</a></li>
                        <li><a href="seccion.php?cat=turismo">Turismo</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contáctanos</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> Valdivia, Los Ríos, Chile</li>
                        <li><i class="fas fa-phone"></i> +56 9 8765 4321</li>
                        <li><i class="fas fa-envelope"></i> contacto@valdiviacapital.cl</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Valdivia Capital. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    (function ($) {
        var excludeIds = <?php echo json_encode($shownIds); ?>;

        $('#btn-cargar-mas').on('click', function () {
            var $btn = $(this);
            $btn.addClass('loading').prop('disabled', true);

            $.getJSON('ajax/cargar_noticias.php', { exclude: excludeIds.join(','), limite: 6 })
                .done(function (data) {
                    if (data.html) {
                        var $cards = $(data.html);
                        $('#news-grid').append($cards);
                        // Activar fade-in en las nuevas tarjetas
                        setTimeout(function () {
                            $cards.filter('.news-card').css({ opacity: '', transform: '' }).addClass('visible');
                        }, 50);
                        excludeIds = excludeIds.concat(data.newIds);
                    }
                    if (!data.hasMore) {
                        $('#load-more-wrap').fadeOut(300);
                    } else {
                        $btn.removeClass('loading').prop('disabled', false);
                    }
                })
                .fail(function () {
                    $btn.removeClass('loading').prop('disabled', false);
                });
        });
    })(jQuery);
    </script>
</body>
</html>
