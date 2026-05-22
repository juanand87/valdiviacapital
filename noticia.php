<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/banners.php';
require_once 'includes/galerias.php';
// Cargar claves VAPID si están configuradas
if (!defined('VAPID_PUBLIC_KEY')) {
    $__vc = __DIR__ . '/includes/vapid_config.php';
    if (file_exists($__vc)) require_once $__vc;
    unset($__vc);
}
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

$galeriaImagenes = [];
try {
    $stmtGal = $db->prepare("SELECT imagen_url, titulo, orden FROM noticias_galeria WHERE noticia_id = ? ORDER BY orden ASC, id ASC");
    $stmtGal->execute([$noticia['id']]);
    $galeriaImagenes = $stmtGal->fetchAll();
} catch (PDOException $e) {
    $galeriaImagenes = [];
}

// Obtener noticias TRENDING (para mostrar en sidebar)
$stmtTrending = $db->prepare("
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
    WHERE n.publicado = 1 
      AND n.fecha_publicacion > DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND n.id != ?
    GROUP BY n.id
    ORDER BY trending_score DESC
    LIMIT 5
");
$stmtTrending->execute([$noticia['id']]);
$trendingWidget = $stmtTrending->fetchAll();

// Incrementar vistas
$db->prepare("UPDATE noticias SET vistas = vistas + 1 WHERE id = ?")->execute([$noticia['id']]);

// Registrar vista diaria para estadísticas
$db->prepare("INSERT INTO vistas_diarias (noticia_id, fecha, vistas) VALUES (?, CURDATE(), 1)
    ON DUPLICATE KEY UPDATE vistas = vistas + 1")->execute([$noticia['id']]);

// Tiempo de lectura estimado
$palabras = str_word_count(strip_tags($noticia['contenido']));
$tiempoLectura = max(1, round($palabras / 200));

// Noticias relacionadas inteligentes: score = categorías_compartidas×2 + comunas_compartidas
$stmtMyCats = $db->prepare("SELECT categoria_id FROM noticias_categorias WHERE noticia_id = ?");
$stmtMyCats->execute([$noticia['id']]);
$myCatIds = $stmtMyCats->fetchAll(PDO::FETCH_COLUMN);
if (empty($myCatIds)) $myCatIds = [$noticia['categoria_id']];

$stmtMyComIds = $db->prepare("SELECT comuna_id FROM noticias_comunas WHERE noticia_id = ?");
$stmtMyComIds->execute([$noticia['id']]);
$myComIds = $stmtMyComIds->fetchAll(PDO::FETCH_COLUMN);

$catIn     = implode(',', array_map('intval', $myCatIds));
$hasComIds = !empty($myComIds);
$comIn     = $hasComIds ? implode(',', array_map('intval', $myComIds)) : '0';

$relSql = "
    SELECT n.id, n.titulo, n.slug, n.imagen_principal, n.fecha_publicacion, n.bajada,
           c.color as categoria_color, c.nombre as categoria_nombre,
           (
               (SELECT COUNT(*) FROM noticias_categorias nc WHERE nc.noticia_id = n.id AND nc.categoria_id IN ($catIn)) * 2
               + " . ($hasComIds ? "(SELECT COUNT(*) FROM noticias_comunas ncom WHERE ncom.noticia_id = n.id AND ncom.comuna_id IN ($comIn))" : "0") . "
           ) AS relevancia
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    WHERE n.id != ? AND n.publicado = 1
    AND (
        n.categoria_id IN ($catIn)
        OR n.id IN (SELECT nc2.noticia_id FROM noticias_categorias nc2 WHERE nc2.categoria_id IN ($catIn))
        " . ($hasComIds ? "OR n.id IN (SELECT ncom2.noticia_id FROM noticias_comunas ncom2 WHERE ncom2.comuna_id IN ($comIn))" : "") . "
    )
    ORDER BY relevancia DESC, n.fecha_publicacion DESC
    LIMIT 4";
$stmtRel = $db->prepare($relSql);
$stmtRel->execute([$noticia['id']]);
$relacionadas = $stmtRel->fetchAll();

// Comentarios
$stmtCom = $db->prepare("
    SELECT * FROM comentarios
    WHERE noticia_id = ? AND aprobado = 1
    ORDER BY created_at DESC
");
$stmtCom->execute([$noticia['id']]);
$comentarios = $stmtCom->fetchAll();

// Reacciones
$stmtReac = $db->prepare("SELECT tipo, COUNT(*) as total FROM reacciones WHERE noticia_id = ? GROUP BY tipo");
$stmtReac->execute([$noticia['id']]);
$reaccionesTotales = ['me_gusta' => 0, 'me_encanta' => 0, 'sorpresa' => 0];
foreach ($stmtReac->fetchAll() as $r) {
    if (isset($reaccionesTotales[$r['tipo']])) {
        $reaccionesTotales[$r['tipo']] = (int)$r['total'];
    }
}
$ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
$stmtMiReac = $db->prepare("SELECT tipo FROM reacciones WHERE noticia_id = ? AND ip_hash = ?");
$stmtMiReac->execute([$noticia['id'], $ipHash]);
$miReaccion = $stmtMiReac->fetchColumn();

// Comunas de la noticia
$stmtComunas = $db->prepare("
    SELECT c.nombre, c.slug FROM comunas c
    INNER JOIN noticias_comunas nc ON c.id = nc.comuna_id
    WHERE nc.noticia_id = ? ORDER BY c.nombre
");
$stmtComunas->execute([$noticia['id']]);
$noticiaComunas = $stmtComunas->fetchAll();

// Lo más leído (para widget)
$masLeidas = $db->query("
    SELECT id, titulo, slug, vistas FROM noticias WHERE publicado = 1 ORDER BY vistas DESC LIMIT 5
")->fetchAll();
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
    <meta property="og:url" content="<?php echo htmlspecialchars(SITE_URL . '/noticia.php?slug=' . urlencode($noticia['slug'])); ?>">
    <meta property="og:site_name" content="Valdivia Capital">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo clean($noticia['titulo']); ?>">
    <meta name="twitter:description" content="<?php echo clean(truncate(strip_tags($noticia['bajada']), 160)); ?>">
    <?php if ($noticia['imagen_principal']): ?>
    <meta name="twitter:image" content="<?php echo clean($noticia['imagen_principal']); ?>">
    <?php endif; ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "NewsArticle",
      "headline": "<?php echo addslashes(clean($noticia['titulo'])); ?>",
      "description": "<?php echo addslashes(clean(truncate(strip_tags($noticia['bajada']), 200))); ?>",
      "datePublished": "<?php echo date('c', strtotime($noticia['fecha_publicacion'])); ?>",
      "dateModified": "<?php echo date('c', strtotime(!empty($noticia['updated_at']) ? $noticia['updated_at'] : $noticia['fecha_publicacion'])); ?>",
      "author": {"@type": "Person", "name": "<?php echo addslashes(clean($noticia['autor_nombre'])); ?>"},
      "publisher": {
        "@type": "Organization",
        "name": "Valdivia Capital",
        "logo": {"@type": "ImageObject", "url": "https://valdiviacapital.cl/logovc.png"}
      }<?php if ($noticia['imagen_principal']): ?>,
      "image": "<?php echo clean($noticia['imagen_principal']); ?>"<?php endif; ?>,
      "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": "<?php echo SITE_URL; ?>/noticia.php?slug=<?php echo urlencode($noticia['slug']); ?>"
      }
    }
    </script>
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
        .article-gallery {
            margin: 28px 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 12px;
        }
        .article-gallery-item {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            background: #fff;
        }
        .article-gallery-item img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            display: block;
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
        /* Reacciones */
        .reactions-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 20px 0;
            padding: 16px 20px;
            background: var(--color-light);
            border-radius: 12px;
        }
        .reactions-bar > span {
            font-weight: 600;
            font-size: 14px;
            color: var(--color-dark);
            margin-right: 4px;
        }
        .react-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border: 2px solid transparent;
            border-radius: 30px;
            background: white;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all .2s;
            color: var(--color-dark);
        }
        .react-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.12); }
        .react-btn.active { border-color: var(--color-primary); background: #fff0f2; }
        .react-count { font-size: 13px; color: var(--color-gray); }
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
        /* ── Botón modo lectura ── */
        .btn-leer {
            margin-left: auto;
            background: none;
            border: 1.5px solid var(--color-border, #e5e7eb);
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 3px 10px;
            color: var(--color-gray);
            cursor: pointer;
            transition: all .2s;
            line-height: 1.4;
        }
        .btn-leer:hover,
        .btn-leer.active { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }
        /* ── Modo lectura ── */
        body.modo-lectura .share-float              { display: none !important; }
        body.modo-lectura .article-full             { max-width: 720px !important; margin-left: auto !important; margin-right: auto !important; }
        body.modo-lectura .article-content          { font-size: 1.22rem !important; line-height: 2.05 !important; }
        body.modo-lectura .related-section,
        body.modo-lectura .comments-section         { display: none !important; }
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
                <a href="eventos.php"><i class="fas fa-calendar-alt"></i> Eventos</a>
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
                <?php foreach ($noticiaComunas as $com): ?>
                <span style="display:inline-flex;align-items:center;gap:5px;background:#edf2ff;color:#3c4cad;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;margin-left:6px;margin-bottom:15px;text-transform:uppercase;letter-spacing:.4px;">
                    <i class="fas fa-map-marker-alt" style="font-size:9px;"></i>
                    <?php echo htmlspecialchars($com['nombre']); ?>
                </span>
                <?php endforeach; ?>
                
                <h1><?php echo clean($noticia['titulo']); ?></h1>
                
                <?php if ($noticia['bajada']): ?>
                <p class="article-bajada"><?php echo clean($noticia['bajada']); ?></p>
                <?php endif; ?>
                
                <div class="article-meta-info">
                    <span><i class="far fa-user"></i> Por <?php echo clean($noticia['autor_nombre']); ?></span>
                    <span><i class="far fa-clock"></i> <?php echo formatDate($noticia['fecha_publicacion']); ?></span>
                    <span><i class="fas fa-eye"></i> <?php echo number_format($noticia['vistas']); ?> vistas</span>
                    <span><i class="fas fa-book-open"></i> <?php echo $tiempoLectura; ?> min de lectura</span>
                    <button id="btn-leer" class="btn-leer" title="Activar modo lectura (Aa)">Aa</button>
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

            <?php if (!empty($galeriaImagenes)): ?>
            <div class="article-gallery">
                <?php foreach ($galeriaImagenes as $gi): ?>
                <a href="<?php echo clean($gi['imagen_url']); ?>" target="_blank" rel="noopener noreferrer" class="article-gallery-item" title="Abrir imagen">
                    <img src="<?php echo clean($gi['imagen_url']); ?>" alt="<?php echo clean($gi['titulo'] ?? $noticia['titulo']); ?>" loading="lazy" onerror="this.parentElement.style.display='none'">
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Banner In-article -->
            <?php renderBanner('in_article'); ?>

            <div class="article-content">
                <?php echo parseGaleriaShortcodes($noticia['contenido'], $db); ?>
            </div>

            <!-- Botones de compartir -->
            <div class="share-buttons">
                <span style="font-weight: 600; margin-right: 10px; display: flex; align-items: center;">Compartir:</span>
                <a href="#" class="share-btn facebook" onclick="compartirNoticia('facebook', '<?php echo addslashes($noticia['titulo']); ?>', window.location.href); return false;">
                    <i class="fab fa-facebook-f"></i> Facebook
                </a>
                <a href="#" class="share-btn twitter" onclick="compartirNoticia('twitter', '<?php echo addslashes($noticia['titulo']); ?>', window.location.href); return false;">
                    <i class="fab fa-x-twitter"></i> X
                </a>
                <a href="#" class="share-btn whatsapp" onclick="compartirNoticia('whatsapp', '<?php echo addslashes($noticia['titulo']); ?>', window.location.href); return false;">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
            </div>

            <!-- Reacciones -->
            <div class="reactions-bar" id="reactions-bar" data-id="<?php echo (int)$noticia['id']; ?>">
                <span>¿Qué te pareció?</span>
                <button class="react-btn<?php echo $miReaccion === 'me_gusta' ? ' active' : ''; ?>" data-tipo="me_gusta">👍 <span class="react-count" id="cnt-me_gusta"><?php echo $reaccionesTotales['me_gusta']; ?></span></button>
                <button class="react-btn<?php echo $miReaccion === 'me_encanta' ? ' active' : ''; ?>" data-tipo="me_encanta">❤️ <span class="react-count" id="cnt-me_encanta"><?php echo $reaccionesTotales['me_encanta']; ?></span></button>
                <button class="react-btn<?php echo $miReaccion === 'sorpresa' ? ' active' : ''; ?>" data-tipo="sorpresa">😮 <span class="react-count" id="cnt-sorpresa"><?php echo $reaccionesTotales['sorpresa']; ?></span></button>
            </div>

            <!-- Noticias Relacionadas -->
            <?php if (!empty($relacionadas)): ?>
            <section class="related-section">
                <h3 class="related-title"><i class="fas fa-newspaper"></i> También te puede interesar</h3>
                <div class="related-grid">
                    <?php foreach ($relacionadas as $r): ?>
                    <a href="noticia.php?slug=<?php echo clean($r['slug']); ?>" class="related-card">
                        <div class="related-card-img">
                            <img src="<?php echo $r['imagen_principal'] ? clean($r['imagen_principal']) : 'https://picsum.photos/seed/' . $r['id'] . 'rel/400/250'; ?>" alt="<?php echo clean($r['titulo']); ?>" loading="lazy">
                            <span class="category-badge" style="background:<?php echo $r['categoria_color']; ?>;position:absolute;bottom:8px;left:8px;font-size:10px;">
                                <?php echo strtoupper($r['categoria_nombre']); ?>
                            </span>
                        </div>
                        <div class="related-card-body">
                            <h4><?php echo clean($r['titulo']); ?></h4>
                            <span class="related-card-date"><i class="far fa-clock"></i> <?php echo timeAgo($r['fecha_publicacion']); ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </article>

        <!-- Widget: Lo más leído -->
        <?php if (!empty($masLeidas)): ?>
        <div class="widget" style="margin: 0 0 30px; padding: 25px; background: white; border-radius: var(--radius); box-shadow: var(--shadow-lg);">
            <h3 class="widget-title" style="font-size: 1.1rem; font-weight: 700; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid var(--color-primary);">
                <i class="fas fa-fire" style="color:var(--color-primary);margin-right:6px;"></i>Lo Más Leído
            </h3>
            <div class="trending-list">
                <?php foreach ($masLeidas as $i => $ml): ?>
                <div class="trending-item" style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--color-light);">
                    <div class="trending-number" style="min-width:28px;height:28px;background:var(--color-primary);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;"><?= $i + 1 ?></div>
                    <div>
                        <h4 style="font-size:14px;line-height:1.4;margin:0 0 3px;"><a href="noticia.php?slug=<?= clean($ml['slug']) ?>" style="color:var(--color-dark);"><?= clean($ml['titulo']) ?></a></h4>
                        <span style="font-size:12px;color:var(--color-gray);"><?= number_format($ml['vistas'], 0, ',', '.') ?> vistas</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

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

    <!-- Barra flotante de compartir -->
    <?php
    $shareSlug  = urlencode($noticia['slug']);
    $shareTitle = rawurlencode($noticia['titulo']);
    $shareUrl   = SITE_URL . '/noticia.php?slug=' . $shareSlug;
    ?>
    <div id="share-float" class="share-float" role="complementary" aria-label="Compartir artículo">
        <span class="sfb-label">Compartir</span>
        <a class="sfb sfb-wa"
           href="https://wa.me/?text=<?php echo $shareTitle . '%20' . rawurlencode($shareUrl); ?>"
           target="_blank" rel="noopener noreferrer" title="WhatsApp">
            <i class="fab fa-whatsapp"></i><span>WhatsApp</span>
        </a>
        <a class="sfb sfb-fb"
           href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($shareUrl); ?>"
           target="_blank" rel="noopener noreferrer" title="Facebook">
            <i class="fab fa-facebook-f"></i><span>Facebook</span>
        </a>
        <a class="sfb sfb-x"
           href="https://twitter.com/intent/tweet?text=<?php echo $shareTitle; ?>&url=<?php echo rawurlencode($shareUrl); ?>"
           target="_blank" rel="noopener noreferrer" title="X / Twitter">
            <i class="fab fa-x-twitter"></i><span>X</span>
        </a>
        <button class="sfb sfb-copy" id="btn-copy-link" title="Copiar enlace">
            <i class="fas fa-link"></i><span>Copiar</span>
        </button>
    </div>

    <!-- Widget Trending (noticias siendo trending ahora) -->
    <?php if (!empty($trendingWidget)): ?>
    <section class="container" style="margin: 60px 0 40px;">
        <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 24px; color: var(--color-dark);">
            <i class="fas fa-fire" style="color: #ff6b35; margin-right: 10px;"></i>También está siendo trending
        </h3>
        <div class="trending-grid">
            <?php foreach ($trendingWidget as $t): ?>
            <div class="trending-card">
                <a href="noticia.php?slug=<?= clean($t['slug']) ?>" class="trending-card-link">
                    <h4 class="trending-card-title"><?= clean(truncate($t['titulo'], 65)) ?></h4>
                    <div class="trending-card-stats">
                        <span class="stat-item">
                            <i class="far fa-eye"></i> <?= number_format((int)($t['vistas'] ?? 0)) ?> vistas
                        </span>
                        <span class="stat-item">
                            <i class="fas fa-fire"></i> <?= round((float)($t['trending_score'] ?? 0)) ?> pts
                        </span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
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
        // Barra de progreso de lectura        $(window).on('scroll', function () {
            var docHeight = $(document).height() - $(window).height();
            var scrolled = $(window).scrollTop();
            var progress = docHeight > 0 ? (scrolled / docHeight) * 100 : 0;
            $('#reading-progress').css('width', Math.min(100, progress) + '%');
        });

        // Reacciones
        (function () {
            const bar = document.getElementById('reactions-bar');
            if (!bar) return;
            const noticiaId = bar.dataset.id;

            bar.querySelectorAll('.react-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const tipo = this.dataset.tipo;
                    fetch('ajax/reacciones.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + encodeURIComponent(noticiaId) + '&tipo=' + encodeURIComponent(tipo)
                    })
                    .then(r => r.json())
                    .then(function (res) {
                        // Actualizar conteos
                        Object.keys(res.counts).forEach(function (t) {
                            const el = document.getElementById('cnt-' + t);
                            if (el) el.textContent = res.counts[t];
                        });
                        // Actualizar clase activa
                        bar.querySelectorAll('.react-btn').forEach(function (b) {
                            b.classList.toggle('active', b.dataset.tipo === res.mi_reaccion);
                        });
                    })
                    .catch(function () {});
                });
            });
        }());
    </script>
    <script>
    // ── Barra flotante de compartir ──────────────────────────────
    (function () {
        var sf = document.getElementById('share-float');
        if (!sf) return;
        window.addEventListener('scroll', function () {
            sf.classList.toggle('visible', window.scrollY > 320);
        }, { passive: true });
        document.getElementById('btn-copy-link').addEventListener('click', function () {
            var btn = this;
            var canonical = <?php echo json_encode(SITE_URL . '/noticia.php?slug=' . $noticia['slug']); ?>;
            navigator.clipboard.writeText(canonical).then(function () {
                var icon = btn.querySelector('i');
                var lbl  = btn.querySelector('span');
                icon.className = 'fas fa-check';
                if (lbl) lbl.textContent = 'Copiado!';
                setTimeout(function () {
                    icon.className = 'fas fa-link';
                    if (lbl) lbl.textContent = 'Copiar';
                }, 2200);
            }).catch(function () {
                // fallback para navegadores sin Clipboard API
                var ta = document.createElement('textarea');
                ta.value = canonical; ta.style.position = 'fixed';
                document.body.appendChild(ta); ta.focus(); ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            });
        });
    })();
    </script>
    <script>
    // ── Modo lectura ─────────────────────────────────────────────
    (function () {
        var btn  = document.getElementById('btn-leer');
        var MODO = 'vc_leer_modo';
        if (!btn) return;

        // Restaurar estado guardado
        if (localStorage.getItem(MODO) === '1') {
            document.body.classList.add('modo-lectura');
            btn.classList.add('active');
        }

        btn.addEventListener('click', function () {
            var active = document.body.classList.toggle('modo-lectura');
            btn.classList.toggle('active', active);
            localStorage.setItem(MODO, active ? '1' : '0');
        });
    }());

    // ── Historial de lectura (localStorage) ──────────────────────
    (function () {
        var art = {
            id:       <?php echo (int)$noticia['id']; ?>,
            titulo:   <?php echo json_encode(clean($noticia['titulo'])); ?>,
            slug:     <?php echo json_encode($noticia['slug']); ?>,
            imagen:   <?php echo json_encode($noticia['imagen_principal'] ?: ''); ?>,
            cat:      <?php echo json_encode($noticia['categoria_nombre']); ?>,
            color:    <?php echo json_encode($noticia['categoria_color']); ?>,
            leido:    Date.now()
        };
        try {
            var hist = JSON.parse(localStorage.getItem('vc_history') || '[]');
            hist = hist.filter(function (n) { return n.id !== art.id; });
            hist.unshift(art);
            localStorage.setItem('vc_history', JSON.stringify(hist.slice(0, 10)));
        } catch (e) {}
    }());
    </script>
    <!-- Push Notifications: VAPID public key + SW client -->
    <script>window.VAPID_PUBLIC_KEY = '<?php echo defined('VAPID_PUBLIC_KEY') ? addslashes(VAPID_PUBLIC_KEY) : ''; ?>';</script>
    <script src="assets/js/push.js" defer></script>
</body>
</html>
