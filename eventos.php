<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/banners.php';
if (isMaintenance()) { include 'mantenimiento.php'; exit; }

$db = getDB();

$filtroComuna    = (int)($_GET['comuna'] ?? 0);
$filtroCategoria = trim($_GET['categoria'] ?? '');
$filtroQ         = trim($_GET['q'] ?? '');
$filtroMes       = trim($_GET['mes'] ?? '');

$where = ['e.activo = 1'];
$params = [];

if ($filtroComuna > 0) {
    $where[] = 'e.comuna_id = ?';
    $params[] = $filtroComuna;
}
if ($filtroCategoria !== '') {
    $where[] = 'e.categoria = ?';
    $params[] = $filtroCategoria;
}
if ($filtroQ !== '') {
    $where[] = '(e.titulo LIKE ? OR e.descripcion LIKE ? OR e.lugar LIKE ?)';
    $params[] = '%' . $filtroQ . '%';
    $params[] = '%' . $filtroQ . '%';
    $params[] = '%' . $filtroQ . '%';
}
if ($filtroMes !== '' && preg_match('/^\\d{4}-\\d{2}$/', $filtroMes)) {
    $where[] = "DATE_FORMAT(e.fecha_inicio, '%Y-%m') = ?";
    $params[] = $filtroMes;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $db->prepare(
    "SELECT e.*, c.nombre as comuna_nombre, c.slug as comuna_slug
     FROM eventos e
     LEFT JOIN comunas c ON c.id = e.comuna_id
     $whereSql
     ORDER BY e.destacado DESC, e.fecha_inicio ASC"
);
$stmt->execute($params);
$eventos = $stmt->fetchAll();

$categorias = $db->query('SELECT DISTINCT categoria FROM eventos WHERE activo = 1 ORDER BY categoria')->fetchAll(PDO::FETCH_COLUMN);
$comunas = $db->query('SELECT id, nombre FROM comunas ORDER BY nombre')->fetchAll();
$todasComunas = $db->query('SELECT id, nombre, slug FROM comunas ORDER BY nombre')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - Valdivia Capital</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
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

    <nav class="main-nav">
        <div class="container">
            <div class="nav-content">
                <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
                <a href="seccion.php?cat=regional">Regional</a>
                <a href="seccion.php?cat=politica">Politica</a>
                <a href="seccion.php?cat=economia">Economia</a>
                <a href="seccion.php?cat=deportes">Deportes</a>
                <a href="seccion.php?cat=cultura">Cultura</a>
                <a href="seccion.php?cat=turismo">Turismo</a>
                <a href="eventos.php" class="active"><i class="fas fa-calendar-alt"></i> Eventos</a>
                <div class="nav-dropdown">
                    <a href="#" class="nav-dropdown-toggle">
                        <i class="fas fa-map-marker-alt"></i> Region <i class="fas fa-chevron-down" style="font-size:10px;"></i>
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

    <section class="eventos-hero">
        <div class="container">
            <h1><i class="fas fa-calendar-alt"></i> Agenda de Eventos</h1>
            <p>Descubre actividades culturales, deportivas y panoramas en la Region de Los Rios.</p>
        </div>
    </section>

    <div class="container" style="margin-top: 28px;">
        <?php renderBanner('leaderboard'); ?>

        <form method="GET" class="eventos-filtros">
            <input type="text" name="q" placeholder="Buscar evento o lugar" value="<?php echo clean($filtroQ); ?>">

            <select name="categoria">
                <option value="">Todas las categorias</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?php echo clean($cat); ?>" <?php echo $filtroCategoria === $cat ? 'selected' : ''; ?>>
                    <?php echo clean($cat); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="comuna">
                <option value="">Todas las comunas</option>
                <?php foreach ($comunas as $com): ?>
                <option value="<?php echo (int)$com['id']; ?>" <?php echo $filtroComuna === (int)$com['id'] ? 'selected' : ''; ?>>
                    <?php echo clean($com['nombre']); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <input type="month" name="mes" value="<?php echo clean($filtroMes); ?>">

            <button type="submit"><i class="fas fa-filter"></i> Filtrar</button>
            <a href="eventos.php" class="clear-btn">Limpiar</a>
        </form>

        <?php if (!$eventos): ?>
        <div class="eventos-empty">
            <i class="fas fa-calendar-times"></i>
            <h3>No hay eventos para este filtro</h3>
            <p>Prueba con otra categoria, comuna o mes.</p>
        </div>
        <?php else: ?>
        <div class="eventos-grid">
            <?php foreach ($eventos as $ev): ?>
            <article class="evento-card <?php echo (int)$ev['destacado'] === 1 ? 'destacado' : ''; ?>">
                <a href="evento.php?slug=<?php echo clean($ev['slug']); ?>" class="evento-link">
                    <div class="evento-thumb" style="background-image:url('<?php echo clean($ev['imagen_url'] ?: 'https://picsum.photos/seed/evento' . (int)$ev['id'] . '/700/420'); ?>');">
                        <?php if ((int)$ev['destacado'] === 1): ?>
                        <span class="evento-chip chip-destacado"><i class="fas fa-star"></i> Destacado</span>
                        <?php endif; ?>
                        <span class="evento-chip chip-cat"><?php echo clean($ev['categoria']); ?></span>
                    </div>
                    <div class="evento-body">
                        <h3><?php echo clean($ev['titulo']); ?></h3>
                        <p><?php echo clean(truncate(strip_tags((string)$ev['descripcion']), 120)); ?></p>
                        <div class="evento-meta">
                            <span><i class="far fa-calendar"></i> <?php echo formatDate($ev['fecha_inicio']); ?></span>
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo clean($ev['lugar']); ?><?php if ($ev['comuna_nombre']): ?> · <?php echo clean($ev['comuna_nombre']); ?><?php endif; ?></span>
                            <span>
                                <i class="fas fa-ticket"></i>
                                <?php echo (int)$ev['gratuito'] === 1 ? 'Gratis' : clean($ev['precio'] ?: 'Con costo'); ?>
                            </span>
                        </div>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <span class="footer-logo-text">VALDIVIA CAPITAL</span>
                    <p style="color:rgba(255,255,255,0.7);margin-top:10px;">El principal medio de comunicacion digital de la region, comprometido con la informacion veraz y oportuna.</p>
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
                        <li><a href="seccion.php?cat=politica">Politica</a></li>
                        <li><a href="seccion.php?cat=economia">Economia</a></li>
                        <li><a href="seccion.php?cat=deportes">Deportes</a></li>
                        <li><a href="seccion.php?cat=cultura">Cultura</a></li>
                        <li><a href="seccion.php?cat=turismo">Turismo</a></li>
                        <li><a href="eventos.php">Eventos</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contactanos</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> Valdivia, Los Rios, Chile</li>
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
</body>
</html>
