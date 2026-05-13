<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/banners.php';
if (isMaintenance()) { include 'mantenimiento.php'; exit; }

// Obtener término de búsqueda
$query = $_GET['q'] ?? '';
$query = trim($query);

$resultados = [];
$total_resultados = 0;
$per_page = 12;
$hayMas   = false;

if (!empty($query)) {
    $db          = getDB();
    $search_term = "%$query%";

    // Total de resultados
    $stmtT = $db->prepare("SELECT COUNT(*) FROM noticias WHERE (titulo LIKE ? OR bajada LIKE ? OR contenido LIKE ?) AND publicado = 1");
    $stmtT->execute([$search_term, $search_term, $search_term]);
    $total_resultados = (int)$stmtT->fetchColumn();
    $hayMas = $total_resultados > $per_page;

    // Primera página
    $stmt = $db->prepare("
        SELECT n.*, c.nombre as categoria_nombre, c.slug as categoria_slug, c.color as categoria_color,
               u.nombre as autor_nombre
        FROM noticias n
        INNER JOIN categorias c ON n.categoria_id = c.id
        INNER JOIN usuarios u ON n.autor_id = u.id
        WHERE (n.titulo LIKE ? OR n.bajada LIKE ? OR n.contenido LIKE ?)
        AND n.publicado = 1
        ORDER BY n.fecha_publicacion DESC
        LIMIT $per_page
    ");
    $stmt->execute([$search_term, $search_term, $search_term]);
    $resultados = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar: <?php echo clean($query); ?> - Valdivia Capital</title>
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
                        <input type="text" name="q" placeholder="Buscar noticias..." value="<?php echo clean($query); ?>">
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

    <!-- Resultados de Búsqueda -->
    <div class="container" style="margin: 60px auto;">
        <?php renderBanner('leaderboard'); ?>
        <?php if (!empty($query)): ?>
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 15px;">
                Resultados de búsqueda para: "<?php echo clean($query); ?>"
            </h1>
            <p style="color: var(--color-gray); margin-bottom: 40px; font-size: 1.1rem;">
                Se encontraron <strong><?php echo $total_resultados; ?></strong> resultado<?php echo $total_resultados != 1 ? 's' : ''; ?>
            </p>

            <?php if ($total_resultados > 0): ?>
                <div class="news-grid" id="busqueda-grid">
                    <?php foreach ($resultados as $noticia): ?>
                    <article class="news-card">
                        <a href="noticia.php?slug=<?php echo clean($noticia['slug']); ?>">
                            <div class="news-image">
                                <?php if ($noticia['imagen_principal']): ?>
                                    <img src="<?php echo clean($noticia['imagen_principal']); ?>" alt="<?php echo clean($noticia['titulo']); ?>" loading="lazy">
                                <?php else: ?>
                                    <img src="https://picsum.photos/seed/<?php echo $noticia['id']; ?>bsq/600/400" alt="<?php echo clean($noticia['titulo']); ?>" loading="lazy">
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
                <div id="load-sentinel-bsq"></div>
                <div id="scroll-loader-bsq" class="scroll-loader">
                    <div class="scroll-spinner"></div><span>Cargando más resultados...</span>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 80px 20px; background: white; border-radius: var(--radius); box-shadow: var(--shadow-md);">
                    <i class="fas fa-search" style="font-size: 4rem; color: var(--color-light); margin-bottom: 20px;"></i>
                    <h2 style="font-size: 1.5rem; color: var(--color-dark); margin-bottom: 10px;">
                        No se encontraron resultados
                    </h2>
                    <p style="color: var(--color-gray); margin-bottom: 30px;">
                        Intenta con otras palabras clave o explora nuestras secciones
                    </p>
                    <a href="index.php" class="btn-primary">
                        Volver al inicio
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 80px 20px; background: white; border-radius: var(--radius); box-shadow: var(--shadow-md);">
                <i class="fas fa-search" style="font-size: 4rem; color: var(--color-primary); margin-bottom: 20px;"></i>
                <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 15px;">Buscar Noticias</h1>
                <p style="color: var(--color-gray); margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto;">
                    Ingresa una palabra clave o frase para buscar noticias en nuestro archivo
                </p>
                <form action="busqueda.php" method="GET" style="max-width: 500px; margin: 0 auto;">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="q" placeholder="¿Qué estás buscando?" required 
                               style="flex: 1; padding: 15px; border: 2px solid var(--color-light); border-radius: var(--radius); font-size: 1rem;">
                        <button type="submit" class="btn-primary" style="padding: 15px 30px;">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
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
    (function () {
        var page     = 1;
        var loading  = false;
        var hasMore  = <?php echo !empty($query) && $hayMas ? 'true' : 'false'; ?>;
        var q        = <?php echo json_encode($query ?? ''); ?>;
        var grid     = document.getElementById('busqueda-grid');
        var loader   = document.getElementById('scroll-loader-bsq');
        var sentinel = document.getElementById('load-sentinel-bsq');
        if (!hasMore || !sentinel) return;

        function loadMore() {
            if (loading || !hasMore) return;
            loading = true; page++;
            loader.style.display = 'flex';
            fetch('ajax/cargar_busqueda.php?q=' + encodeURIComponent(q) + '&p=' + page)
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
