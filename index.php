<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/cache.php';
require_once 'includes/banners.php';
require_once 'includes/galerias.php';
// Cargar claves VAPID si están configuradas (para el widget de push)
if (!defined('VAPID_PUBLIC_KEY')) {
    $__vc = __DIR__ . '/includes/vapid_config.php';
    if (file_exists($__vc)) require_once $__vc;
    unset($__vc);
}
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

// Trending (sidebar) — Calcula engagement score: vistas + comentarios + reacciones — caché 10 minutos
$trending = cacheGet('homepage_trending', 600);
if ($trending === false) {
    $trending = $db->query("
        SELECT 
          n.id, n.titulo, n.slug, n.vistas,
          COALESCE(COUNT(DISTINCT cm.id), 0) as comentarios_recientes,
          COALESCE(COUNT(DISTINCT r.id), 0) as reacciones_recientes,
          (n.vistas * 0.4 + COALESCE(COUNT(DISTINCT cm.id), 0) * 2 + COALESCE(COUNT(DISTINCT r.id), 0) * 1.5) as trending_score
        FROM noticias n
        LEFT JOIN comentarios cm ON n.id = cm.noticia_id 
          AND cm.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND cm.aprobado = 1
        LEFT JOIN reacciones r ON n.id = r.noticia_id 
          AND r.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        WHERE n.publicado = 1 AND n.fecha_publicacion > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY n.id
        ORDER BY trending_score DESC
        LIMIT 7
    ")->fetchAll();
    cacheSet('homepage_trending', $trending);
}

// Ticker: configuración dinámica + mensajes personalizados
$tickerConfig = ['activo'=>'1','etiqueta'=>'Último momento','velocidad'=>'35','fuente'=>'noticias','cantidad_noticias'=>'8'];
$tickerItems  = []; // cada item: ['texto'=>'...','url'=>'...','tipo'=>'normal']
try {
    $tcRows = $db->query("SELECT nombre, valor FROM ticker_config")->fetchAll();
    foreach ($tcRows as $r) $tickerConfig[$r['nombre']] = $r['valor'];

    if ($tickerConfig['activo'] != '0') {
        $fuente = $tickerConfig['fuente'] ?? 'noticias';

        // Mensajes personalizados
        if ($fuente === 'personalizado' || $fuente === 'ambos') {
            $rows = $db->query("SELECT mensaje, url, tipo FROM ticker_mensajes WHERE activo=1 ORDER BY orden ASC, id ASC")->fetchAll();
            foreach ($rows as $r) {
                $tickerItems[] = ['texto' => $r['mensaje'], 'url' => $r['url'], 'tipo' => $r['tipo']];
            }
        }

        // Noticias recientes
        if ($fuente === 'noticias' || $fuente === 'ambos' || empty($tickerItems)) {
            $qty  = max(1, min(20, (int)($tickerConfig['cantidad_noticias'] ?? 8)));
            $stmt = $db->prepare("SELECT titulo, slug FROM noticias WHERE publicado=1 ORDER BY fecha_publicacion DESC LIMIT ?");
            $stmt->execute([$qty]);
            foreach ($stmt->fetchAll() as $n) {
                $tickerItems[] = ['texto' => $n['titulo'], 'url' => 'noticia.php?slug=' . rawurlencode($n['slug']), 'tipo' => 'normal'];
            }
        }
    }
} catch (\PDOException $e) {
    // Fallback: solo últimas noticias
    try {
        $rows = $db->query("SELECT titulo, slug FROM noticias WHERE publicado=1 ORDER BY fecha_publicacion DESC LIMIT 8")->fetchAll();
        foreach ($rows as $n) {
            $tickerItems[] = ['texto' => $n['titulo'], 'url' => 'noticia.php?slug=' . rawurlencode($n['slug']), 'tipo' => 'normal'];
        }
    } catch (\Exception $e2) {}
}

$comunasPortada = cacheGet('homepage_comunas', 900);
if ($comunasPortada === false) {
    $comunasPortada = $db->query("
        SELECT *
        FROM (
            SELECT
                com.id AS comuna_id,
                com.nombre AS comuna_nombre,
                com.slug AS comuna_slug,
                n.id,
                n.titulo,
                n.slug,
                n.bajada,
                n.imagen_principal,
                n.fecha_publicacion,
                n.vistas,
                c.nombre AS cat_nombre,
                c.color AS cat_color,
                ROW_NUMBER() OVER (PARTITION BY com.id ORDER BY n.fecha_publicacion DESC, n.id DESC) AS rn
            FROM comunas com
            INNER JOIN noticias_comunas nc ON nc.comuna_id = com.id
            INNER JOIN noticias n ON n.id = nc.noticia_id
            INNER JOIN categorias c ON c.id = n.categoria_id
            WHERE n.publicado = 1
        ) ranked
        WHERE rn = 1
        ORDER BY fecha_publicacion DESC
    ")->fetchAll();
    cacheSet('homepage_comunas', $comunasPortada);
}

$comunasPortadaVisibles = 6;

// Sección Multimedia: galería destacada (Opción A) o fallback a videos individuales
$multimediaVideos = [];
$multimediaTitulo = '';
try {
    $galeriaDesk = $db->query("SELECT * FROM galerias_video WHERE destacada=1 AND activo=1 LIMIT 1")->fetch();
    if ($galeriaDesk) {
        $multimediaVideos = mm_galeria_videos((int)$galeriaDesk['id'], $db);
        $multimediaTitulo = $galeriaDesk['titulo'];
    }
} catch (\Exception $e) { /* tabla aún no creada en este entorno */ }
if (empty($multimediaVideos)) {
    $multimediaVideos = $db->query("
        SELECT v.*, c.nombre AS cat_nombre, c.color AS cat_color
        FROM videos v
        LEFT JOIN categorias c ON c.id = v.categoria_id
        WHERE v.activo = 1
        ORDER BY v.orden ASC, v.created_at DESC
        LIMIT 9
    ")->fetchAll();
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
                <a href="eventos.php"><i class="fas fa-calendar-alt"></i> Eventos</a>
                <a href="bolsa-trabajo.php"><i class="fas fa-briefcase"></i> Empleos</a>
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
    <?php if ($tickerConfig['activo'] != '0' && !empty($tickerItems)): ?>
    <div class="breaking-news">
        <div class="container" style="display:flex;align-items:center;width:100%;overflow:hidden;">
            <span class="breaking-label"><i class="fas fa-bolt"></i> <?= clean($tickerConfig['etiqueta'] ?? 'Último momento') ?></span>
            <div class="ticker-wrap">
                <div class="ticker-text" style="animation-duration:<?= (int)($tickerConfig['velocidad'] ?? 35) ?>s;">
                    <?php foreach ($tickerItems as $item): ?>
                        <?php
                            $cls = $item['tipo'] === 'urgente' ? ' ticker-item-urgente' : ($item['tipo'] === 'flash' ? ' ticker-item-flash' : '');
                            $prefix = $item['tipo'] === 'urgente' ? '<strong>URGENTE: </strong>' : ($item['tipo'] === 'flash' ? '<strong>FLASH: </strong>' : '');
                        ?>
                        <?php if ($item['url']): ?>
                            <a href="<?= clean($item['url']) ?>" class="ticker-link<?= $cls ?>"><?= $prefix . clean($item['texto']) ?></a>
                        <?php else: ?>
                            <span class="<?= ltrim($cls) ?>"><?= $prefix . clean($item['texto']) ?></span>
                        <?php endif; ?>
                        <span class="sep">&bull;</span>
                    <?php endforeach; ?>
                    <?php foreach ($tickerItems as $item): ?>
                        <?php
                            $cls = $item['tipo'] === 'urgente' ? ' ticker-item-urgente' : ($item['tipo'] === 'flash' ? ' ticker-item-flash' : '');
                            $prefix = $item['tipo'] === 'urgente' ? '<strong>URGENTE: </strong>' : ($item['tipo'] === 'flash' ? '<strong>FLASH: </strong>' : '');
                        ?>
                        <?php if ($item['url']): ?>
                            <a href="<?= clean($item['url']) ?>" class="ticker-link<?= $cls ?>"><?= $prefix . clean($item['texto']) ?></a>
                        <?php else: ?>
                            <span class="<?= ltrim($cls) ?>"><?= $prefix . clean($item['texto']) ?></span>
                        <?php endif; ?>
                        <span class="sep">&bull;</span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

    <!-- ======== SECCIÓN MULTIMEDIA ======== -->
    <?php if (!empty($multimediaVideos)): ?>
    <section class="multimedia-section">
        <div class="container">
            <h2 class="multimedia-heading">
                <span class="mm-icon"><i class="fas fa-play"></i></span>
                Multimedia
                <?php if ($multimediaTitulo): ?>
                    <span class="mm-galeria-label"><?= htmlspecialchars($multimediaTitulo) ?></span>
                <?php endif; ?>
            </h2>
            <?= mm_render_videos($multimediaVideos, true) ?>
        </div>
    </section>
    <?php endif; ?>

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

                <!-- Trending (Vistas + Comentarios + Reacciones) -->
                <div class="widget trending-widget">
                    <h3 class="widget-title"><i class="fas fa-fire" style="color: #ff6b35; margin-right: 8px;"></i>Trending Ahora</h3>
                    <div class="trending-list">
                        <?php foreach ($trending as $i => $t): ?>
                        <div class="trending-item">
                            <div class=\"trending-rank\"><?= $i + 1 ?></div>
                            <div class="trending-content">
                                <a href="noticia.php?slug=<?= clean($t['slug']) ?>" class="trending-title">
                                    <?= clean(truncate($t['titulo'], 55)) ?>
                                </a>
                                <div class="trending-stats">
                                    <span class="stat-views" title="Vistas en últimas 24h">
                                        <i class="far fa-eye"></i> <?= number_format((int)($t['vistas'] ?? 0)) ?>
                                    </span>
                                    <span class="stat-comments" title="Comentarios en últimas 24h">
                                        <i class="far fa-comment"></i> <?= (int)($t['comentarios_recientes'] ?? 0) ?>
                                    </span>
                                    <span class="stat-reactions" title="Reacciones en últimas 24h">
                                        <i class="fas fa-fire"></i> <?= (int)($t['reacciones_recientes'] ?? 0) ?>
                                    </span>
                                </div>
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

                <!-- Historial de lectura (renderizado por JS desde localStorage) -->
                <div id="widget-historial" class="widget" style="display:none;">
                    <h3 class="widget-title">
                        <i class="fas fa-history" style="color:var(--color-primary);margin-right:6px;"></i>Continúa leyendo
                    </h3>
                    <div id="historial-list"></div>
                </div>

                <!-- Clima Regional -->
                <div class="widget">                    <h3 class="widget-title"><i class="fas fa-cloud-sun" style="color:var(--color-primary);margin-right:6px;"></i>Clima Regional</h3>
                    <div id="clima-body">
                        <div class="weather-item skel-row"><div class="skel skel-text" style="width:55%"></div><div class="skel skel-temp"></div></div>
                        <div class="weather-item skel-row"><div class="skel skel-text" style="width:45%"></div><div class="skel skel-temp"></div></div>
                        <div class="weather-item skel-row"><div class="skel skel-text" style="width:50%"></div><div class="skel skel-temp"></div></div>
                    </div>
                </div>

                <!-- Indicadores Financieros -->
                <div class="widget">
                    <h3 class="widget-title"><i class="fas fa-chart-line" style="color:var(--color-primary);margin-right:6px;"></i>Indicadores Financieros</h3>
                    <div id="indicadores-body">
                        <div class="ind-item skel-row"><div class="skel skel-text" style="width:60%"></div><div class="skel skel-ind"></div></div>
                        <div class="ind-item skel-row"><div class="skel skel-text" style="width:55%"></div><div class="skel skel-ind"></div></div>
                        <div class="ind-item skel-row"><div class="skel skel-text" style="width:40%"></div><div class="skel skel-ind"></div></div>
                        <div class="ind-item skel-row"><div class="skel skel-text" style="width:35%"></div><div class="skel skel-ind"></div></div>
                    </div>
                    <div id="indicadores-fecha" class="ind-fecha"></div>
                </div>

            </aside>
        </div>
    </div>

    <?php if (!empty($comunasPortada)): ?>
    <section class="comunas-home-section">
        <div class="container">
            <div class="comunas-home-header">
                <div>
                    <span class="comunas-home-kicker">Cobertura local</span>
                    <h2 class="section-title comunas-home-title"><i class="fas fa-map-marked-alt"></i> Comunas</h2>
                    <p class="comunas-home-intro">Una noticia destacada por comuna para recorrer la regi&oacute;n desde la portada, con acceso directo a cada cobertura local.</p>
                </div>
            </div>

            <div class="comunas-home-grid" id="comunas-home-grid">
                <?php foreach ($comunasPortada as $index => $comunaItem): ?>
                <article class="comuna-home-card fade-in <?php echo $index === 0 ? 'comuna-home-card-featured' : ''; ?> <?php echo $index >= $comunasPortadaVisibles ? 'is-hidden' : ''; ?>">
                    <a href="noticia.php?slug=<?= clean($comunaItem['slug']) ?>" class="comuna-home-link">
                        <div class="comuna-home-image">
                            <?php if ($comunaItem['imagen_principal']): ?>
                                <img src="<?= clean($comunaItem['imagen_principal']) ?>" alt="<?= clean($comunaItem['titulo']) ?>" loading="lazy">
                            <?php else: ?>
                                <img src="https://picsum.photos/seed/comuna<?= (int)$comunaItem['id'] ?>/720/480" alt="<?= clean($comunaItem['titulo']) ?>" loading="lazy">
                            <?php endif; ?>
                            <span class="comuna-home-badge"><?= clean($comunaItem['comuna_nombre']) ?></span>
                        </div>
                        <div class="comuna-home-body">
                            <div class="comuna-home-meta-top">
                                <span class="comuna-home-category" style="color:<?= clean($comunaItem['cat_color']) ?>;"><?= clean($comunaItem['cat_nombre']) ?></span>
                                <span class="comuna-home-separator">|</span>
                                <span class="comuna-home-meta-time"><?= timeAgo($comunaItem['fecha_publicacion']) ?></span>
                            </div>
                            <h3 class="comuna-home-title-card"><?= clean($comunaItem['titulo']) ?></h3>
                            <?php if (!empty($comunaItem['bajada'])): ?>
                                <p class="comuna-home-excerpt"><?= clean(truncate($comunaItem['bajada'], $index === 0 ? 180 : 120)) ?></p>
                            <?php endif; ?>
                            <div class="comuna-home-footer">
                                <span class="comuna-home-views"><i class="fas fa-eye"></i> <?= number_format((int)$comunaItem['vistas'], 0, ',', '.') ?></span>
                                <span class="comuna-home-cta">Ver comuna <i class="fas fa-arrow-right"></i></span>
                            </div>
                        </div>
                    </a>
                    <a href="comuna.php?comuna=<?= clean($comunaItem['comuna_slug']) ?>" class="comuna-home-local-link">
                        <i class="fas fa-map-pin"></i> Ir a <?= clean($comunaItem['comuna_nombre']) ?>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>

            <?php if (count($comunasPortada) > $comunasPortadaVisibles): ?>
            <div class="load-more-wrap comunas-load-more-wrap" id="comunas-load-more-wrap">
                <button id="btn-cargar-mas-comunas" class="btn-load-more">
                    <span class="label"><i class="fas fa-map"></i> Mostrar m&aacute;s comunas</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

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
                        <li><a href="eventos.php">Eventos</a></li>
                        <li><a href="ser-reportero.php">Ser reportero VC</a></li>
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

        $('#btn-cargar-mas-comunas').on('click', function () {
            var $btn = $(this);
            var $hidden = $('#comunas-home-grid .comuna-home-card.is-hidden').slice(0, 4);

            if (!$hidden.length) {
                $('#comunas-load-more-wrap').fadeOut(300);
                return;
            }

            $hidden.removeClass('is-hidden');
            setTimeout(function () {
                $hidden.css({ opacity: '', transform: '' }).addClass('visible');
            }, 30);

            if (!$('#comunas-home-grid .comuna-home-card.is-hidden').length) {
                $('#comunas-load-more-wrap').fadeOut(300);
            }
        });
    })(jQuery);
    </script>

    <!-- ======== Widgets dinámicos: clima e indicadores ======== -->
    <script>
    (function () {
        function escHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function renderWidgets() {
            fetch('ajax/widgets.php')
                .then(function(r){ return r.json(); })
                .then(function(data) {

                    // ── Clima ──────────────────────────────────────────────
                    if (data.clima && data.clima.length) {
                        var html = '';
                        data.clima.forEach(function(c) {
                            html += '<div class="weather-item">'
                                  + '<div style="display:flex;align-items:center;gap:10px;flex:1;">'
                                  + '<i class="fas ' + escHtml(c.icono) + ' weather-wi"></i>'
                                  + '<div>'
                                  + '<div class="weather-city">' + escHtml(c.ciudad) + '</div>'
                                  + '<div class="weather-desc">' + escHtml(c.desc) + '</div>'
                                  + '<div class="weather-minmax">'
                                  + '<i class="fas fa-arrow-up" style="color:#ef4444;font-size:9px;"></i> ' + escHtml(c.maxima)
                                  + ' &nbsp;<i class="fas fa-arrow-down" style="color:#3b82f6;font-size:9px;"></i> ' + escHtml(c.minima)
                                  + '</div>'
                                  + '</div>'
                                  + '</div>'
                                  + '<div class="weather-right">'
                                  + '<div class="weather-temp">' + escHtml(c.temp) + '°C</div>'
                                  + '<div class="weather-detail"><i class="fas fa-droplet weather-di"></i>' + escHtml(c.humedad) + '</div>'
                                  + '<div class="weather-detail"><i class="fas fa-wind weather-di"></i>' + escHtml(c.viento) + '</div>'
                                  + '</div>'
                                  + '</div>';
                        });
                        document.getElementById('clima-body').innerHTML = html;
                    }

                    // ── Indicadores ────────────────────────────────────────
                    if (data.indicadores && data.indicadores.items) {
                        var html2 = '';
                        data.indicadores.items.forEach(function(ind) {
                            html2 += '<div class="ind-item">'
                                   + '<div class="ind-nombre"><i class="fas ' + escHtml(ind.icono) + ' ind-icon"></i>' + escHtml(ind.nombre) + '</div>'
                                   + '<div class="ind-valor">' + escHtml(ind.valor) + '</div>'
                                   + '</div>';
                        });
                        document.getElementById('indicadores-body').innerHTML = html2;
                        if (data.indicadores.fecha) {
                            document.getElementById('indicadores-fecha').textContent =
                                'Datos al ' + data.indicadores.fecha;
                        }
                    }
                })
                .catch(function(){});
        }

        renderWidgets();
        // Actualizar automáticamente cada 30 minutos
        setInterval(renderWidgets, 1800000);
    })();
    </script>

    <!-- ======== Multimedia: reproducir + carrusel ======== -->
    <script>
    (function () {
        // Reproducir cualquier tarjeta de video al hacer clic
        function initVideoCard(card) {
            card.addEventListener('click', function () {
                var embed = this.getAttribute('data-embed');
                if (!embed) return;
                var thumb = this.querySelector('.mm-thumb');
                var iframeWrap = this.querySelector('.mm-iframe-wrap');
                var iframe = iframeWrap.querySelector('iframe');
                if (thumb) thumb.style.display = 'none';
                iframe.src = embed;
                iframeWrap.style.display = '';
            });
        }

        document.querySelectorAll('.mm-featured, .mm-small-card, .mm-carousel-item').forEach(initVideoCard);

        // Carrusel
        var carousel = document.getElementById('mm-carousel');
        if (!carousel) return;

        document.querySelector('.mm-prev').addEventListener('click', function () {
            carousel.scrollBy({ left: -220, behavior: 'smooth' });
        });
        document.querySelector('.mm-next').addEventListener('click', function () {
            carousel.scrollBy({ left: 220, behavior: 'smooth' });
        });
    })();
    </script>
    <script>
    // ── Historial de lectura (widget "Continúa leyendo") ─────────
    (function () {
        var list   = document.getElementById('historial-list');
        var widget = document.getElementById('widget-historial');
        if (!list || !widget) return;

        var hist;
        try { hist = JSON.parse(localStorage.getItem('vc_history') || '[]'); }
        catch (e) { return; }
        if (!hist.length) return;

        var html = '';
        hist.slice(0, 5).forEach(function (n) {
            var imgStyle = n.imagen
                ? 'background-image:url("' + n.imagen.replace(/"/g, '%22') + '")'
                : '';
            html += '<a href="noticia.php?slug=' + encodeURIComponent(n.slug) + '" class="historial-item">'
                + '<div class="historial-thumb"' + (imgStyle ? ' style="' + imgStyle + '"' : '') + '>'
                + (!n.imagen ? '<i class="fas fa-newspaper" style="color:#bbb;font-size:18px;"></i>' : '')
                + '</div>'
                + '<div class="historial-info">'
                + '<h4>' + n.titulo.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</h4>'
                + '<span class="historial-cat" style="background:' + n.color + '22;color:' + n.color + '">'
                + n.cat + '</span>'
                + '</div>'
                + '</a>';
        });

        list.innerHTML = html;
        widget.style.display = '';
    }());
    </script>
    <!-- Push Notifications -->
    <div id="push-toast" class="push-toast" role="dialog" aria-live="polite">
        <div class="push-toast-icon"><i class="fas fa-bell"></i></div>
        <div class="push-toast-text">
            <strong>¿Quieres recibir alertas?</strong>
            <span>Entérate de lo último al instante</span>
        </div>
        <button id="push-allow" class="push-allow-btn">Activar</button>
        <button id="push-dismiss" class="push-dismiss-btn" title="Cerrar"><i class="fas fa-times"></i></button>
    </div>
    <script>window.VAPID_PUBLIC_KEY = '<?php echo defined('VAPID_PUBLIC_KEY') ? addslashes(VAPID_PUBLIC_KEY) : ''; ?>';</script>
    <script src="assets/js/push.js" defer></script>
</body>
</html>
