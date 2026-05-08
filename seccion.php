<?php
require_once 'includes/config.php';

// Obtener categoría de la URL
$categoria_slug = $_GET['cat'] ?? 'regional';

// Obtener información de la categoría
$db = getDB();
$stmt = $db->prepare("SELECT * FROM categorias WHERE slug = ? AND activo = 1");
$stmt->execute([$categoria_slug]);
$categoria = $stmt->fetch();

if (!$categoria) {
    header('Location: index.php');
    exit;
}

// Obtener noticias de esta categoría
$stmt = $db->prepare("
    SELECT n.*, c.nombre as categoria_nombre, c.slug as categoria_slug, c.color as categoria_color,
           u.nombre as autor_nombre
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    INNER JOIN usuarios u ON n.autor_id = u.id
    WHERE n.categoria_id = ? AND n.publicado = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT 12
");
$stmt->execute([$categoria['id']]);
$noticias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($categoria['nombre']); ?> - Diario Los Ríos</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header Superior -->
    <div class="top-header">
        <div class="container">
            <div class="top-header-content">
                <div class="date">
                    <i class="far fa-calendar"></i>
                    <span>Viernes, 21 de Febrero de 2026</span>
                </div>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Header Principal -->
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php">
                        <h1>DIARIO LOS RÍOS</h1>
                        <p class="tagline">La voz de la región • Valdivia, Chile</p>
                    </a>
                </div>
                <div class="header-search">
                    <form class="search-form" action="busqueda.php" method="GET">
                        <input type="text" name="q" placeholder="Buscar noticias..." required>
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
            </div>
        </div>
    </nav>

    <!-- Título de Sección -->
    <section style="background: linear-gradient(135deg, <?php echo $categoria['color']; ?>, <?php echo $categoria['color']; ?>dd); padding: 40px 0; margin-bottom: 40px;">
        <div class="container">
            <h1 style="color: white; font-size: 2.5rem; font-weight: 800; margin: 0;">
                <i class="<?php echo $categoria['icono']; ?>"></i>
                <?php echo clean($categoria['nombre']); ?>
            </h1>
            <p style="color: rgba(255,255,255,0.9); margin-top: 10px;">
                <?php echo clean($categoria['descripcion']); ?>
            </p>
        </div>
    </section>

    <!-- Noticias de la Categoría -->
    <div class="container">
        <div class="news-grid" style="margin-bottom: 60px;">
            <?php foreach ($noticias as $noticia): ?>
            <article class="news-card">
                <a href="noticia.php?id=<?php echo $noticia['id']; ?>">
                    <div class="news-image">
                        <?php if ($noticia['imagen_principal']): ?>
                            <img src="<?php echo clean($noticia['imagen_principal']); ?>" alt="<?php echo clean($noticia['titulo']); ?>">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/600x400/2563eb/ffffff?text=<?php echo urlencode($categoria['nombre']); ?>" alt="<?php echo clean($noticia['titulo']); ?>">
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
            <a href="index.php" class="btn-primary" style="margin-top: 20px; display: inline-block;">
                Volver al inicio
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>Sobre Nosotros</h3>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.7;">
                        Diario Los Ríos es el principal medio de comunicación digital de la región.
                    </p>
                </div>
                <div class="footer-column">
                    <h3>Secciones</h3>
                    <ul>
                        <li><a href="seccion.php?cat=regional">Regional</a></li>
                        <li><a href="seccion.php?cat=politica">Política</a></li>
                        <li><a href="seccion.php?cat=economia">Economía</a></li>
                        <li><a href="seccion.php?cat=deportes">Deportes</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contáctanos</h3>
                    <ul>
                        <li>Valdivia, Los Ríos, Chile</li>
                        <li>+56 9 8765 4321</li>
                        <li>contacto@diariolosrios.cl</li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Síguenos</h3>
                    <div class="social-links" style="font-size: 1.5rem; gap: 20px;">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 Diario Los Ríos. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
