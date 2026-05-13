<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/banners.php';
if (isMaintenance()) { include 'mantenimiento.php'; exit; }

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: eventos.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare(
    "SELECT e.*, c.nombre as comuna_nombre, c.slug as comuna_slug
     FROM eventos e
     LEFT JOIN comunas c ON c.id = e.comuna_id
     WHERE e.slug = ? AND e.activo = 1
     LIMIT 1"
);
$stmt->execute([$slug]);
$evento = $stmt->fetch();

if (!$evento) {
    header('Location: eventos.php');
    exit;
}

$stmtRel = $db->prepare(
    "SELECT id, titulo, slug, fecha_inicio, lugar, imagen_url, categoria
     FROM eventos
     WHERE activo = 1 AND id != ?
       AND (comuna_id <=> ? OR categoria = ?)
     ORDER BY fecha_inicio ASC
     LIMIT 4"
);
$stmtRel->execute([(int)$evento['id'], $evento['comuna_id'], $evento['categoria']]);
$relacionados = $stmtRel->fetchAll();

$todasComunas = $db->query('SELECT id, nombre, slug FROM comunas ORDER BY nombre')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($evento['titulo']); ?> - Eventos</title>
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
                <div class="date"><i class="far fa-calendar-alt"></i><span id="current-date"></span></div>
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
                    <a href="index.php"><img src="https://valdiviacapital.cl/logovc.png" alt="Valdivia Capital" class="site-logo"></a>
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
                        <li><a href="comuna.php?comuna=<?php echo $c['slug']; ?>"><i class="fas fa-map-pin"></i> <?php echo clean($c['nombre']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <section class="container" style="margin-top:28px;">
        <nav class="breadcrumb-nav" style="margin-bottom:18px;">
            <a href="index.php">Inicio</a>
            <span class="sep">/</span>
            <a href="eventos.php">Eventos</a>
            <span class="sep">/</span>
            <span class="current"><?php echo clean(truncate($evento['titulo'], 60)); ?></span>
        </nav>

        <article class="evento-detalle">
            <div class="evento-detalle-hero" style="background-image:url('<?php echo clean($evento['imagen_url'] ?: 'https://picsum.photos/seed/eventodet' . (int)$evento['id'] . '/1400/700'); ?>');">
                <span class="evento-chip chip-cat"><?php echo clean($evento['categoria']); ?></span>
            </div>

            <div class="evento-detalle-body">
                <h1><?php echo clean($evento['titulo']); ?></h1>

                <div class="evento-detalle-meta">
                    <span><i class="far fa-calendar"></i> <?php echo formatDate($evento['fecha_inicio']); ?></span>
                    <?php if (!empty($evento['fecha_fin'])): ?>
                    <span><i class="far fa-clock"></i> Hasta <?php echo formatDate($evento['fecha_fin']); ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo clean($evento['lugar']); ?><?php if ($evento['comuna_nombre']): ?> · <?php echo clean($evento['comuna_nombre']); ?><?php endif; ?></span>
                    <span><i class="fas fa-ticket"></i> <?php echo (int)$evento['gratuito'] === 1 ? 'Entrada gratuita' : clean($evento['precio'] ?: 'Con costo'); ?></span>
                </div>

                <?php if (!empty($evento['descripcion'])): ?>
                <div class="evento-detalle-texto">
                    <?php echo nl2br(clean($evento['descripcion'])); ?>
                </div>
                <?php endif; ?>

                <div class="evento-detalle-extra">
                    <?php if (!empty($evento['organizador'])): ?>
                    <p><strong>Organiza:</strong> <?php echo clean($evento['organizador']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($evento['direccion'])): ?>
                    <p><strong>Direccion:</strong> <?php echo clean($evento['direccion']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($evento['url_externo'])): ?>
                    <p><a class="btn-primary" href="<?php echo clean($evento['url_externo']); ?>" target="_blank" rel="noopener noreferrer">Mas informacion</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </article>

        <?php if ($relacionados): ?>
        <section style="margin:40px 0 10px;">
            <h3 style="font-size:1.35rem;font-weight:700;margin-bottom:16px;"><i class="fas fa-calendar-week"></i> Otros eventos que te pueden interesar</h3>
            <div class="eventos-grid">
                <?php foreach ($relacionados as $ev): ?>
                <article class="evento-card">
                    <a href="evento.php?slug=<?php echo clean($ev['slug']); ?>" class="evento-link">
                        <div class="evento-thumb" style="background-image:url('<?php echo clean($ev['imagen_url'] ?: 'https://picsum.photos/seed/rel' . (int)$ev['id'] . '/700/420'); ?>');">
                            <span class="evento-chip chip-cat"><?php echo clean($ev['categoria']); ?></span>
                        </div>
                        <div class="evento-body">
                            <h3><?php echo clean($ev['titulo']); ?></h3>
                            <div class="evento-meta">
                                <span><i class="far fa-calendar"></i> <?php echo formatDate($ev['fecha_inicio']); ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo clean($ev['lugar']); ?></span>
                            </div>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </section>

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
