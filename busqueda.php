<?php
require_once 'includes/config.php';

// Obtener término de búsqueda
$query = $_GET['q'] ?? '';
$query = trim($query);

$resultados = [];
$total_resultados = 0;

if (!empty($query)) {
    $db = getDB();
    
    // Buscar en noticias
    $stmt = $db->prepare("
        SELECT n.*, c.nombre as categoria_nombre, c.slug as categoria_slug, c.color as categoria_color,
               u.nombre as autor_nombre
        FROM noticias n
        INNER JOIN categorias c ON n.categoria_id = c.id
        INNER JOIN usuarios u ON n.autor_id = u.id
        WHERE (n.titulo LIKE ? OR n.bajada LIKE ? OR n.contenido LIKE ?)
        AND n.publicado = 1
        ORDER BY n.fecha_publicacion DESC
        LIMIT 20
    ");
    
    $search_term = "%$query%";
    $stmt->execute([$search_term, $search_term, $search_term]);
    $resultados = $stmt->fetchAll();
    $total_resultados = count($resultados);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar: <?php echo clean($query); ?> - Diario Los Ríos</title>
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
                        <input type="text" name="q" placeholder="Buscar noticias..." value="<?php echo clean($query); ?>" required>
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
            </div>
        </div>
    </nav>

    <!-- Resultados de Búsqueda -->
    <div class="container" style="margin: 60px auto;">
        <?php if (!empty($query)): ?>
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 15px;">
                Resultados de búsqueda para: "<?php echo clean($query); ?>"
            </h1>
            <p style="color: var(--color-gray); margin-bottom: 40px; font-size: 1.1rem;">
                Se encontraron <strong><?php echo $total_resultados; ?></strong> resultado<?php echo $total_resultados != 1 ? 's' : ''; ?>
            </p>

            <?php if ($total_resultados > 0): ?>
                <div class="news-grid">
                    <?php foreach ($resultados as $noticia): ?>
                    <article class="news-card">
                        <a href="noticia.php?id=<?php echo $noticia['id']; ?>">
                            <div class="news-image">
                                <?php if ($noticia['imagen_principal']): ?>
                                    <img src="<?php echo clean($noticia['imagen_principal']); ?>" alt="<?php echo clean($noticia['titulo']); ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/600x400/2563eb/ffffff?text=Noticia" alt="<?php echo clean($noticia['titulo']); ?>">
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
