<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/banners.php';
if (isMaintenance()) { include 'mantenimiento.php'; exit; }

$categoria_slug = $_GET['cat'] ?? 'regional';
$pagina  = max(1, filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT) ?: 1);
$por_pagina = 12;
$offset  = ($pagina - 1) * $por_pagina;

$db = getDB();
$stmt = $db->prepare("SELECT * FROM categorias WHERE slug = ? AND activo = 1");
$stmt->execute([$categoria_slug]);
$categoria = $stmt->fetch();

if (!$categoria) {
    header('Location: index.php');
    exit;
}

// Total para paginación
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM noticias WHERE categoria_id = ? AND publicado = 1");
$stmtTotal->execute([$categoria['id']]);
$total = $stmtTotal->fetchColumn();
$total_paginas = max(1, ceil($total / $por_pagina));

// Noticias de la página actual
$stmtN = $db->prepare("
    SELECT n.*, c.nombre as categoria_nombre, c.slug as categoria_slug, c.color as categoria_color,
           u.nombre as autor_nombre
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    INNER JOIN usuarios u ON n.autor_id = u.id
    WHERE n.categoria_id = ? AND n.publicado = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT $por_pagina OFFSET $offset
");
$stmtN->execute([$categoria['id']]);
$noticias = $stmtN->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($categoria['nombre']); ?> - Valdivia Capital</title>
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
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-x-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
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
                <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
                <a href="seccion.php?cat=regional" <?php echo $categoria_slug === 'regional' ? 'class="active"' : ''; ?>>Regional</a>
                <a href="seccion.php?cat=politica" <?php echo $categoria_slug === 'politica' ? 'class="active"' : ''; ?>>Política</a>
                <a href="seccion.php?cat=economia" <?php echo $categoria_slug === 'economia' ? 'class="active"' : ''; ?>>Economía</a>
                <a href="seccion.php?cat=deportes" <?php echo $categoria_slug === 'deportes' ? 'class="active"' : ''; ?>>Deportes</a>
                <a href="seccion.php?cat=cultura" <?php echo $categoria_slug === 'cultura' ? 'class="active"' : ''; ?>>Cultura</a>
                <a href="seccion.php?cat=turismo" <?php echo $categoria_slug === 'turismo' ? 'class="active"' : ''; ?>>Turismo</a>
                <a href="eventos.php">Eventos</a>
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

    <section style="background: var(--color-primary); padding: 30px 0; margin-bottom: 40px; border-bottom: 4px solid rgba(0,0,0,0.15);">
        <div class="container">
            <nav style="font-size:13px;color:rgba(255,255,255,0.7);margin-bottom:8px;">
                <a href="index.php" style="color:rgba(255,255,255,0.7);">Inicio</a>
                <span style="margin:0 6px;">/</span>
                <span style="color:#fff;"><?php echo clean($categoria['nombre']); ?></span>
            </nav>
            <h1 style="color: white; font-size: 2rem; font-weight: 800; margin: 0;">
                <i class="<?php echo clean($categoria['icono']); ?>"></i>
                <?php echo clean($categoria['nombre']); ?>
            </h1>
            <p style="color: rgba(255,255,255,0.85); margin-top: 6px; font-size: 14px;">
                <?php echo $total; ?> noticia<?php echo $total != 1 ? 's' : ''; ?> publicada<?php echo $total != 1 ? 's' : ''; ?>
            </p>
        </div>
    </section>

    <!-- Noticias de la Categoría -->
    <div class="container">
        <?php renderBanner('leaderboard'); ?>
        <div class="news-grid" id="seccion-grid" style="margin-bottom: 60px;">
            <?php foreach ($noticias as $noticia): ?>
            <article class="news-card">
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
            <?php endforeach; ?>
        </div>

        <?php if (empty($noticias)): ?>
        <div style="text-align: center; padding: 60px 20px;">
            <h3>No hay noticias disponibles en esta categoría</h3>
            <p style="color: var(--color-gray); margin-top: 10px;">Vuelve pronto para ver nuevo contenido</p>
            <a href="index.php" style="display:inline-block;margin-top:20px;padding:12px 28px;background:var(--color-primary);color:#fff;border-radius:6px;font-weight:600;">Volver al inicio</a>
        </div>
        <?php endif; ?>

        <div id="load-sentinel"></div>
        <div id="scroll-loader" class="scroll-loader">
            <div class="scroll-spinner"></div><span>Cargando más noticias...</span>
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
                        <li><a href="eventos.php">Eventos</a></li>
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
    (function () {
        var page     = 1;
        var loading  = false;
        var hasMore  = <?php echo ($total_paginas > 1) ? 'true' : 'false'; ?>;
        var cat      = <?php echo json_encode($categoria_slug); ?>;
        var grid     = document.getElementById('seccion-grid');
        var loader   = document.getElementById('scroll-loader');
        var sentinel = document.getElementById('load-sentinel');
        if (!hasMore || !sentinel) return;

        function loadMore() {
            if (loading || !hasMore) return;
            loading = true; page++;
            loader.style.display = 'flex';
            fetch('ajax/cargar_seccion.php?cat=' + encodeURIComponent(cat) + '&p=' + page)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.html) {
                        var tmp = document.createElement('div');
                        tmp.innerHTML = data.html;
                        Array.from(tmp.children).forEach(function (el) {
                            grid.appendChild(el);
                            setTimeout(function () { el.classList.add('visible'); }, 30);
                        });
                    }
                    hasMore = !!data.hasMore;
                    if (!hasMore) sentinel.remove();
                    loading = false;
                    loader.style.display = 'none';
                })
                .catch(function () { loading = false; loader.style.display = 'none'; });
        }

        new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting) loadMore();
        }, { rootMargin: '250px' }).observe(sentinel);
    })();
    </script>
</body>
</html>
