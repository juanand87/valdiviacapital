<?php
require_once 'includes/config.php';

// Obtener ID de la noticia
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

// Obtener noticia
$db = getDB();
$stmt = $db->prepare("
    SELECT n.*, c.nombre as categoria_nombre, c.slug as categoria_slug, c.color as categoria_color,
           u.nombre as autor_nombre, u.biografia as autor_bio
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    INNER JOIN usuarios u ON n.autor_id = u.id
    WHERE n.id = ? AND n.publicado = 1
");
$stmt->execute([$id]);
$noticia = $stmt->fetch();

if (!$noticia) {
    header('Location: index.php');
    exit;
}

// Obtener noticias relacionadas
$stmt = $db->prepare("
    SELECT n.*, c.color as categoria_color
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    WHERE n.categoria_id = ? AND n.id != ? AND n.publicado = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT 3
");
$stmt->execute([$noticia['categoria_id'], $id]);
$relacionadas = $stmt->fetchAll();

// Obtener comentarios
$stmt = $db->prepare("
    SELECT * FROM comentarios 
    WHERE noticia_id = ? AND aprobado = 1
    ORDER BY created_at DESC
");
$stmt->execute([$id]);
$comentarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($noticia['titulo']); ?> - Diario Los Ríos</title>
    <meta name="description" content="<?php echo clean(truncate(strip_tags($noticia['bajada']), 160)); ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .article-full {
            background: white;
            border-radius: var(--radius);
            padding: 40px;
            margin: 40px 0;
            box-shadow: var(--shadow-lg);
        }
        .article-header-info {
            margin-bottom: 30px;
        }
        .article-full h1 {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 20px;
        }
        .article-bajada {
            font-size: 1.2rem;
            color: var(--color-gray);
            line-height: 1.7;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--color-light);
        }
        .article-meta-info {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 14px;
            color: var(--color-gray);
            margin-bottom: 25px;
        }
        .article-image-main {
            margin: 30px 0;
            border-radius: var(--radius);
            overflow: hidden;
        }
        .article-image-main img {
            width: 100%;
            height: auto;
        }
        .image-caption {
            font-size: 14px;
            color: var(--color-gray);
            font-style: italic;
            margin-top: 10px;
        }
        .article-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--color-dark);
        }
        .article-content p {
            margin-bottom: 20px;
        }
        .article-content h2 {
            font-size: 1.7rem;
            font-weight: 700;
            margin: 35px 0 15px 0;
            color: var(--color-dark);
            border-left: 4px solid var(--color-primary);
            padding-left: 12px;
        }
        .article-content h3 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 28px 0 12px 0;
            color: var(--color-dark);
        }
        .article-content h4 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 20px 0 10px 0;
        }
        .article-content strong {
            font-weight: 700;
        }
        .article-content em {
            font-style: italic;
        }
        .article-content ul,
        .article-content ol {
            margin: 0 0 20px 25px;
            padding: 0;
        }
        .article-content ul li,
        .article-content ol li {
            margin-bottom: 8px;
            line-height: 1.7;
        }
        .article-content blockquote {
            border-left: 4px solid var(--color-primary);
            margin: 25px 0;
            padding: 15px 20px;
            background: #f8f9fa;
            font-style: italic;
            border-radius: 0 6px 6px 0;
        }
        .share-buttons {
            display: flex;
            gap: 10px;
            margin: 30px 0;
            padding: 20px 0;
            border-top: 1px solid var(--color-light);
            border-bottom: 1px solid var(--color-light);
        }
        .share-btn {
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            transition: var(--transition);
        }
        .share-btn:hover {
            transform: translateY(-2px);
        }
        .share-btn.facebook { background: #3b5998; }
        .share-btn.twitter { background: #1da1f2; }
        .share-btn.whatsapp { background: #25d366; }
        .related-news {
            margin-top: 50px;
        }
        .related-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--color-light);
        }
        .related-item img {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        .related-item h4 {
            font-size: 1rem;
            line-height: 1.4;
            margin-bottom: 5px;
        }
        .comments-section {
            background: var(--color-light);
            padding: 40px;
            border-radius: var(--radius);
            margin-top: 40px;
        }
        .comment {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .comment-author {
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: 5px;
        }
        .comment-date {
            font-size: 12px;
            color: var(--color-gray);
            margin-bottom: 10px;
        }
    </style>
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
                <a href="seccion.php?cat=regional">Regional</a>
                <a href="seccion.php?cat=politica">Política</a>
                <a href="seccion.php?cat=economia">Economía</a>
                <a href="seccion.php?cat=deportes">Deportes</a>
                <a href="seccion.php?cat=cultura">Cultura</a>
                <a href="seccion.php?cat=turismo">Turismo</a>
            </div>
        </div>
    </nav>

    <!-- Contenido de la Noticia -->
    <div class="container">
        <article class="article-full">
            <div class="article-header-info">
                <span class="category-badge" style="background: <?php echo $noticia['categoria_color']; ?>; position: static; display: inline-block; margin-bottom: 15px;">
                    <?php echo strtoupper($noticia['categoria_nombre']); ?>
                </span>
                
                <h1><?php echo clean($noticia['titulo']); ?></h1>
                
                <?php if ($noticia['bajada']): ?>
                <p class="article-bajada"><?php echo clean($noticia['bajada']); ?></p>
                <?php endif; ?>
                
                <div class="article-meta-info">
                    <span><i class="far fa-user"></i> Por <?php echo clean($noticia['autor_nombre']); ?></span>
                    <span><i class="far fa-clock"></i> <?php echo formatDate($noticia['fecha_publicacion']); ?></span>
                    <span><i class="fas fa-eye"></i> <?php echo number_format($noticia['vistas']); ?> vistas</span>
                </div>
            </div>

            <?php if ($noticia['imagen_principal']): ?>
            <div class="article-image-main">
                <img src="<?php echo clean($noticia['imagen_principal']); ?>" alt="<?php echo clean($noticia['titulo']); ?>">
                <?php if ($noticia['imagen_caption']): ?>
                <p class="image-caption"><?php echo clean($noticia['imagen_caption']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="article-content">
                <?php echo $noticia['contenido']; ?>
            </div>

            <!-- Botones de compartir -->
            <div class="share-buttons">
                <span style="font-weight: 600; margin-right: 10px; display: flex; align-items: center;">Compartir:</span>
                <a href="#" class="share-btn facebook" onclick="compartirNoticia('facebook', '<?php echo addslashes($noticia['titulo']); ?>', window.location.href); return false;">
                    <i class="fab fa-facebook-f"></i> Facebook
                </a>
                <a href="#" class="share-btn twitter" onclick="compartirNoticia('twitter', '<?php echo addslashes($noticia['titulo']); ?>', window.location.href); return false;">
                    <i class="fab fa-twitter"></i> Twitter
                </a>
                <a href="#" class="share-btn whatsapp" onclick="compartirNoticia('whatsapp', '<?php echo addslashes($noticia['titulo']); ?>', window.location.href); return false;">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
            </div>

            <!-- Noticias Relacionadas -->
            <?php if (!empty($relacionadas)): ?>
            <div class="related-news">
                <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 20px;">Noticias Relacionadas</h3>
                <?php foreach ($relacionadas as $relacionada): ?>
                <a href="noticia.php?id=<?php echo $relacionada['id']; ?>" class="related-item">
                    <?php if ($relacionada['imagen_principal']): ?>
                        <img src="<?php echo clean($relacionada['imagen_principal']); ?>" alt="<?php echo clean($relacionada['titulo']); ?>">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/120x80/2563eb/ffffff" alt="<?php echo clean($relacionada['titulo']); ?>">
                    <?php endif; ?>
                    <div>
                        <h4><?php echo clean($relacionada['titulo']); ?></h4>
                        <span style="font-size: 12px; color: var(--color-gray);">
                            <i class="far fa-clock"></i> <?php echo timeAgo($relacionada['fecha_publicacion']); ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </article>

        <!-- Sección de Comentarios -->
        <?php if (!empty($comentarios)): ?>
        <section class="comments-section">
            <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 25px;">
                <i class="far fa-comments"></i> Comentarios (<?php echo count($comentarios); ?>)
            </h3>
            
            <?php foreach ($comentarios as $comentario): ?>
            <div class="comment">
                <div class="comment-author"><?php echo clean($comentario['nombre']); ?></div>
                <div class="comment-date"><?php echo timeAgo($comentario['created_at']); ?></div>
                <p><?php echo clean($comentario['comentario']); ?></p>
            </div>
            <?php endforeach; ?>
        </section>
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
