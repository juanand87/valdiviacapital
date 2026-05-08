<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
if (isMaintenance()) { include 'mantenimiento.php'; exit; }

// Obtener noticia por slug (o por id como fallback)
$slug = $_GET['slug'] ?? '';
$id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$db = getDB();
if ($slug !== '') {
    $stmt = $db->prepare("
        SELECT n.*, c.nombre as categoria_nombre, c.slug as categoria_slug, c.color as categoria_color,
               u.nombre as autor_nombre
        FROM noticias n
        INNER JOIN categorias c ON n.categoria_id = c.id
        INNER JOIN usuarios u ON n.autor_id = u.id
        WHERE n.slug = ? AND n.publicado = 1
    ");
    $stmt->execute([$slug]);
} elseif ($id) {
    $stmt = $db->prepare("
        SELECT n.*, c.nombre as categoria_nombre, c.slug as categoria_slug, c.color as categoria_color,
               u.nombre as autor_nombre
        FROM noticias n
        INNER JOIN categorias c ON n.categoria_id = c.id
        INNER JOIN usuarios u ON n.autor_id = u.id
        WHERE n.id = ? AND n.publicado = 1
    ");
    $stmt->execute([$id]);
} else {
    header('Location: index.php');
    exit;
}

$noticia = $stmt->fetch();

if (!$noticia) {
    header('Location: index.php');
    exit;
}

// Incrementar vistas
$db->prepare("UPDATE noticias SET vistas = vistas + 1 WHERE id = ?")->execute([$noticia['id']]);

// Tiempo de lectura estimado
$palabras = str_word_count(strip_tags($noticia['contenido']));
$tiempoLectura = max(1, round($palabras / 200));

// Noticias relacionadas
$stmtRel = $db->prepare("
    SELECT n.*, c.color as categoria_color
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    WHERE n.categoria_id = ? AND n.id != ? AND n.publicado = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT 3
");
$stmtRel->execute([$noticia['categoria_id'], $noticia['id']]);
$relacionadas = $stmtRel->fetchAll();

// Comentarios
$stmtCom = $db->prepare("
    SELECT * FROM comentarios
    WHERE noticia_id = ? AND aprobado = 1
    ORDER BY created_at DESC
");
$stmtCom->execute([$noticia['id']]);
$comentarios = $stmtCom->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($noticia['titulo']); ?> - Valdivia Capital</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <meta name="description" content="<?php echo clean(truncate(strip_tags($noticia['bajada']), 160)); ?>">
    <meta property="og:title" content="<?php echo clean($noticia['titulo']); ?>">
    <meta property="og:description" content="<?php echo clean(truncate(strip_tags($noticia['bajada']), 200)); ?>">
    <?php if ($noticia['imagen_principal']): ?>
    <meta property="og:image" content="<?php echo clean($noticia['imagen_principal']); ?>">
    <?php endif; ?>
    <meta property="og:type" content="article">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
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
        /* Breadcrumb */
        .breadcrumb-bar {
            background: var(--color-light);
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--color-gray);
            flex-wrap: wrap;
        }
        .breadcrumb-nav a {
            color: var(--color-primary);
            font-weight: 500;
        }
        .breadcrumb-nav a:hover { text-decoration: underline; }
        .breadcrumb-nav .sep { color: #bbb; }
        .breadcrumb-nav .current { color: var(--color-dark); font-weight: 500; }
        /* Barra de progreso de lectura */
        #reading-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: var(--color-primary);
            width: 0%;
            z-index: 10000;
            transition: width 0.1s linear;
        }
    </style>
</head>
<body>
    <!-- Barra de progreso de lectura -->
    <div id="reading-progress"></div>

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
                <a href="seccion.php?cat=regional">Regional</a>
                <a href="seccion.php?cat=politica">Política</a>
                <a href="seccion.php?cat=economia">Economía</a>
                <a href="seccion.php?cat=deportes">Deportes</a>
                <a href="seccion.php?cat=cultura">Cultura</a>
                <a href="seccion.php?cat=turismo">Turismo</a>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="breadcrumb-bar">
        <div class="container">
            <nav class="breadcrumb-nav" aria-label="Ruta de navegación">
                <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
                <span class="sep">/</span>
                <a href="seccion.php?cat=<?php echo clean($noticia['categoria_slug']); ?>">
                    <?php echo clean($noticia['categoria_nombre']); ?>
                </a>
                <span class="sep">/</span>
                <span class="current"><?php echo clean(truncate($noticia['titulo'], 65)); ?></span>
            </nav>
        </div>
    </div>

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
                    <span><i class="fas fa-book-open"></i> <?php echo $tiempoLectura; ?> min de lectura</span>
                </div>
            </div>

            <?php if ($noticia['imagen_principal']): ?>
            <div class="article-image-main">
                <img src="<?php echo clean($noticia['imagen_principal']); ?>" alt="<?php echo clean($noticia['titulo']); ?>">
                <?php if (!empty($noticia['imagen_caption'])): ?>
                <p class="image-caption"><i class="fas fa-camera"></i> <?php echo clean($noticia['imagen_caption']); ?></p>
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
                <a href="noticia.php?slug=<?php echo clean($relacionada['slug']); ?>" class="related-item">
                    <img src="<?php echo $relacionada['imagen_principal'] ? clean($relacionada['imagen_principal']) : 'https://picsum.photos/seed/' . $relacionada['id'] . '/120/80'; ?>" alt="<?php echo clean($relacionada['titulo']); ?>">
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
        // Barra de progreso de lectura
        $(window).on('scroll', function () {
            var docHeight = $(document).height() - $(window).height();
            var scrolled = $(window).scrollTop();
            var progress = docHeight > 0 ? (scrolled / docHeight) * 100 : 0;
            $('#reading-progress').css('width', Math.min(100, progress) + '%');
        });
    </script>
</body>
</html>
