<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/banners.php';
if (isMaintenance()) { include 'mantenimiento.php'; exit; }

$slug    = $_GET['comuna'] ?? '';
$pagina  = max(1, filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT) ?: 1);
$por_pagina = 12;
$offset  = ($pagina - 1) * $por_pagina;

$db = getDB();

$stmtC = $db->prepare("SELECT * FROM comunas WHERE slug = ?");
$stmtC->execute([$slug]);
$comuna = $stmtC->fetch();

if (!$comuna) {
    header('Location: index.php');
    exit;
}

// Total para paginación
$stmtTotal = $db->prepare("
    SELECT COUNT(*) FROM noticias n
    INNER JOIN noticias_comunas nc ON nc.noticia_id = n.id
    WHERE nc.comuna_id = ? AND n.publicado = 1
");
$stmtTotal->execute([$comuna['id']]);
$total = (int)$stmtTotal->fetchColumn();
$total_paginas = max(1, ceil($total / $por_pagina));

// Noticias de la página
$stmtN = $db->prepare("
    SELECT n.*, c.nombre as categoria_nombre, c.slug as categoria_slug, c.color as categoria_color,
           u.nombre as autor_nombre
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    INNER JOIN usuarios u ON n.autor_id = u.id
    INNER JOIN noticias_comunas nc ON nc.noticia_id = n.id
    WHERE nc.comuna_id = ? AND n.publicado = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT $por_pagina OFFSET $offset
");
$stmtN->execute([$comuna['id']]);
$noticias = $stmtN->fetchAll();

// Noticias de categoría Municipal (id 12) en esta comuna
$stmtMun = $db->prepare("
    SELECT n.*, c.nombre as categoria_nombre, c.color as categoria_color
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    INNER JOIN noticias_comunas nc ON nc.noticia_id = n.id
    WHERE nc.comuna_id = ? AND n.categoria_id = 12 AND n.publicado = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT 5
");
$stmtMun->execute([$comuna['id']]);
$noticiasMunicipal = $stmtMun->fetchAll();

// Noticias de categoría Gobierno (id 13) en esta comuna
$stmtGob = $db->prepare("
    SELECT n.*, c.nombre as categoria_nombre, c.color as categoria_color
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    INNER JOIN noticias_comunas nc ON nc.noticia_id = n.id
    WHERE nc.comuna_id = ? AND n.categoria_id = 13 AND n.publicado = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT 5
");
$stmtGob->execute([$comuna['id']]);
$noticiasGobierno = $stmtGob->fetchAll();

// Todas las comunas para el dropdown del nav
$todasComunas = $db->query("SELECT * FROM comunas ORDER BY nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($comuna['nombre']); ?> - Región de Los Ríos - Valdivia Capital</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <meta name="description" content="Noticias de <?php echo clean($comuna['nombre']); ?> en la Región de Los Ríos">
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
                <a href="seccion.php?cat=regional">Regional</a>
                <a href="seccion.php?cat=politica">Política</a>
                <a href="seccion.php?cat=economia">Economía</a>
                <a href="seccion.php?cat=deportes">Deportes</a>
                <a href="seccion.php?cat=cultura">Cultura</a>
                <a href="seccion.php?cat=turismo">Turismo</a>
                <div class="nav-dropdown">
                    <a href="#" class="nav-dropdown-toggle active">
                        <i class="fas fa-map-marker-alt"></i> Región <i class="fas fa-chevron-down" style="font-size:10px;margin-left:3px;"></i>
                    </a>
                    <ul class="nav-dropdown-menu">
                        <?php foreach ($todasComunas as $c): ?>
                        <li>
                            <a href="comuna.php?comuna=<?php echo $c['slug']; ?>"
                               <?php echo $c['id'] == $comuna['id'] ? 'class="active"' : ''; ?>>
                                <i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($c['nombre']); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Título de Sección -->
    <section style="background: var(--color-primary); padding: 30px 0; margin-bottom: 40px; border-bottom: 4px solid rgba(0,0,0,0.15);">
        <div class="container">
            <nav style="font-size:13px;color:rgba(255,255,255,0.7);margin-bottom:8px;">
                <a href="index.php" style="color:rgba(255,255,255,0.7);">Inicio</a>
                <span style="margin:0 6px;">/</span>
                <span style="color:rgba(255,255,255,0.7);">Región</span>
                <span style="margin:0 6px;">/</span>
                <span style="color:#fff;"><?php echo clean($comuna['nombre']); ?></span>
            </nav>
            <h1 style="color: white; font-size: 2rem; font-weight: 800; margin: 0;">
                <i class="fas fa-map-marker-alt"></i>
                <?php echo clean($comuna['nombre']); ?>
            </h1>
            <p style="color: rgba(255,255,255,0.85); margin-top: 6px; font-size: 14px;">
                <?php echo $total; ?> noticia<?php echo $total != 1 ? 's' : ''; ?> publicada<?php echo $total != 1 ? 's' : ''; ?>
            </p>
        </div>
    </section>

    <!-- Noticias de la comuna con sidebar -->
    <div class="container">
        <?php renderBanner('leaderboard'); ?>

        <div style="display: grid; grid-template-columns: 1fr 320px; gap: 28px; margin-bottom: 60px;">
            <!-- Contenido principal -->
            <div>
                <?php if (empty($noticias)): ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-map-marker-alt" style="font-size:56px;color:#e2e8f0;display:block;margin-bottom:20px;"></i>
                    <h3>No hay noticias para <?php echo clean($comuna['nombre']); ?></h3>
                    <p style="color: var(--color-gray); margin-top: 10px;">Vuelve pronto para ver nuevo contenido</p>
                    <a href="index.php" style="display:inline-block;margin-top:20px;padding:12px 28px;background:var(--color-primary);color:#fff;border-radius:6px;font-weight:600;">Volver al inicio</a>
                </div>
                <?php else: ?>
                <div class="news-grid">
                    <?php foreach ($noticias as $noticia): ?>
                    <article class="news-card">
                        <a href="noticia.php?slug=<?php echo clean($noticia['slug']); ?>">
                            <div class="news-image">
                                <?php if ($noticia['imagen_principal']): ?>
                                    <img src="<?php echo clean($noticia['imagen_principal']); ?>" alt="<?php echo clean($noticia['titulo']); ?>" loading="lazy">
                                <?php else: ?>
                                    <img src="https://picsum.photos/seed/<?php echo $noticia['id']; ?>com/600/400" alt="<?php echo clean($noticia['titulo']); ?>" loading="lazy">
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

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <nav style="display:flex;justify-content:center;gap:8px;padding:40px 0;">
                    <?php if ($pagina > 1): ?>
                        <a href="comuna.php?comuna=<?php echo $slug; ?>&p=<?php echo $pagina - 1; ?>" style="padding:8px 16px;border:2px solid var(--color-primary);border-radius:6px;color:var(--color-primary);font-weight:600;">&laquo; Anterior</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <a href="comuna.php?comuna=<?php echo $slug; ?>&p=<?php echo $i; ?>"
                           style="padding:8px 14px;border-radius:6px;font-weight:600;<?php echo $i === $pagina ? 'background:var(--color-primary);color:#fff;' : 'border:2px solid var(--color-light);color:var(--color-dark);'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($pagina < $total_paginas): ?>
                        <a href="comuna.php?comuna=<?php echo $slug; ?>&p=<?php echo $pagina + 1; ?>" style="padding:8px 16px;border:2px solid var(--color-primary);border-radius:6px;color:var(--color-primary);font-weight:600;">Siguiente &raquo;</a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Sidebar derecho -->
            <aside style="display: flex; flex-direction: column; gap: 24px;">
                <!-- Widget Municipal -->
                <?php if (!empty($noticiasMunicipal)): ?>
                <div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,.08); border-left: 4px solid #e74c3c;">
                    <h3 style="font-size: 15px; font-weight: 700; margin: 0 0 14px; color: var(--color-dark); text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-landmark" style="color: #e74c3c; margin-right: 6px;"></i>Municipal
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($noticiasMunicipal as $n): ?>
                        <a href="noticia.php?slug=<?php echo clean($n['slug']); ?>" 
                           style="display: block; padding: 8px 0; border-bottom: 1px solid #f0f0f0; text-decoration: none; color: var(--color-dark); font-size: 13px; line-height: 1.4; transition: color .2s;">
                            <span style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <strong><?php echo clean($n['titulo']); ?></strong>
                            </span>
                            <div style="font-size: 11px; color: var(--color-gray); margin-top: 4px;">
                                <i class="far fa-clock"></i> <?php echo timeAgo($n['fecha_publicacion']); ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Widget Gobierno -->
                <?php if (!empty($noticiasGobierno)): ?>
                <div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,.08); border-left: 4px solid #3498db;">
                    <h3 style="font-size: 15px; font-weight: 700; margin: 0 0 14px; color: var(--color-dark); text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-gopuram" style="color: #3498db; margin-right: 6px;"></i>Gobierno
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($noticiasGobierno as $n): ?>
                        <a href="noticia.php?slug=<?php echo clean($n['slug']); ?>" 
                           style="display: block; padding: 8px 0; border-bottom: 1px solid #f0f0f0; text-decoration: none; color: var(--color-dark); font-size: 13px; line-height: 1.4; transition: color .2s;">
                            <span style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <strong><?php echo clean($n['titulo']); ?></strong>
                            </span>
                            <div style="font-size: 11px; color: var(--color-gray); margin-top: 4px;">
                                <i class="far fa-clock"></i> <?php echo timeAgo($n['fecha_publicacion']); ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background:#1a1a2e;color:#a0aec0;padding:40px 0 20px;margin-top:60px;">
        <div class="container" style="text-align:center;">
            <p style="color:#e2e8f0;font-weight:700;font-size:18px;margin-bottom:8px;">Valdivia Capital</p>
            <p style="font-size:13px;">Noticias de la Región de Los Ríos</p>
            <p style="font-size:12px;margin-top:20px;opacity:.6;">&copy; <?php echo date('Y'); ?> Valdivia Capital. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
    <script>
    // Fecha actual
    const d = new Date();
    const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const el = document.getElementById('current-date');
    if (el) el.textContent = d.toLocaleDateString('es-CL', opts);
    </script>
</body>
</html>
