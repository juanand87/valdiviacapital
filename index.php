<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valdivia Capital - Noticias de la Región</title>
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
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Twitter / X"><i class="fab fa-x-twitter"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="YouTube"><i class="fab fa-youtube"></i></a>
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
                        <h1>VALDIVIA CAPITAL</h1>
                        <p class="tagline">La voz de la región &bull; Valdivia, Chile</p>
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
                <a href="index.php" class="active"><i class="fas fa-home"></i> Inicio</a>
                <a href="seccion.php?cat=regional">Regional</a>
                <a href="seccion.php?cat=politica">Política</a>
                <a href="seccion.php?cat=economia">Economía</a>
                <a href="seccion.php?cat=deportes">Deportes</a>
                <a href="seccion.php?cat=cultura">Cultura</a>
                <a href="seccion.php?cat=turismo">Turismo</a>
            </div>
        </div>
    </nav>

    <!-- Ticker de noticias de último momento -->
    <div class="breaking-news">
        <div class="container" style="display:flex; align-items:center; width:100%; overflow:hidden;">
            <span class="breaking-label"><i class="fas fa-bolt"></i> Último momento</span>
            <div class="ticker-wrap">
                <div class="ticker-text" id="ticker-text">
                    <span>Municipio de Valdivia aprueba nuevo plan regulador para 2026</span>
                    <span class="sep">&bull;</span>
                    <span>Deportes Valdivia clasifica a semifinales del torneo regional</span>
                    <span class="sep">&bull;</span>
                    <span>Turismo en Los Ríos crece 35% en temporada de verano</span>
                    <span class="sep">&bull;</span>
                    <span>Hospital Base moderniza sistema de atención con nueva tecnología</span>
                    <span class="sep">&bull;</span>
                    <span>Festival de música mapuche reúne a miles en la región</span>
                    <span class="sep">&bull;</span>
                    <!-- duplicado para loop continuo -->
                    <span>Municipio de Valdivia aprueba nuevo plan regulador para 2026</span>
                    <span class="sep">&bull;</span>
                    <span>Deportes Valdivia clasifica a semifinales del torneo regional</span>
                    <span class="sep">&bull;</span>
                    <span>Turismo en Los Ríos crece 35% en temporada de verano</span>
                    <span class="sep">&bull;</span>
                    <span>Hospital Base moderniza sistema de atención con nueva tecnología</span>
                    <span class="sep">&bull;</span>
                    <span>Festival de música mapuche reúne a miles en la región</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ======== PORTADA / HERO ======== -->
    <section class="hero-section">
        <div class="container">

            <!-- Noticia destacada principal — imagen contenida, no pantalla completa -->
            <a href="noticia.php?id=1" class="hero-featured">
                <div class="hero-image">
                    <img src="https://images.unsplash.com/photo-1580048915913-4f8f5cb481c4?w=800&q=80" alt="Volcán Osorno">
                    <span class="hero-badge"><i class="fas fa-star"></i> Destacado</span>
                </div>
                <div class="hero-content">
                    <div class="hero-category"><i class="fas fa-map-marked-alt"></i> &nbsp;Regional</div>
                    <h2 class="hero-title">Volcán Osorno registra actividad inusual: Expertos monitorean la situación</h2>
                    <p class="hero-excerpt">Autoridades de SERNAGEOMIN mantienen alerta amarilla en la zona tras detectar movimientos sísmicos de baja intensidad que requieren supervisión.</p>
                    <div class="hero-meta">
                        <span><i class="far fa-clock"></i> Hace 2 horas</span>
                        <span><i class="far fa-user"></i> Daniela Montecinos</span>
                        <span><i class="fas fa-eye"></i> 15.432 vistas</span>
                    </div>
                    <span class="btn-primary">Leer noticia <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <!-- Grid secundario: 3 noticias de portada -->
            <div class="hero-grid">

                <a href="noticia.php?id=2" class="hero-grid-card">
                    <div class="hero-grid-img">
                        <img src="https://images.unsplash.com/photo-1542744173-8e7e53415bb0?w=600&q=80" alt="Desarrollo urbano">
                        <span class="category-badge">Regional</span>
                    </div>
                    <div class="hero-grid-body">
                        <div class="hero-grid-cat">Regional</div>
                        <h3 class="hero-grid-title">Municipio aprueba nuevo plan regulador urbano para 2026</h3>
                    </div>
                    <div class="hero-grid-meta">
                        <span><i class="far fa-clock"></i> Hace 4h</span>
                        <span><i class="fas fa-eye"></i> 8.234</span>
                    </div>
                </a>

                <a href="noticia.php?id=3" class="hero-grid-card">
                    <div class="hero-grid-img">
                        <img src="https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=600&q=80" alt="Deportes">
                        <span class="category-badge">Deportes</span>
                    </div>
                    <div class="hero-grid-body">
                        <div class="hero-grid-cat">Deportes</div>
                        <h3 class="hero-grid-title">Deportes Valdivia clasifica a semifinales del torneo regional</h3>
                    </div>
                    <div class="hero-grid-meta">
                        <span><i class="far fa-clock"></i> Hace 5h</span>
                        <span><i class="fas fa-eye"></i> 12.543</span>
                    </div>
                </a>

                <a href="noticia.php?id=4" class="hero-grid-card">
                    <div class="hero-grid-img">
                        <img src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=600&q=80" alt="Economía">
                        <span class="category-badge" style="background:#d97706;">Economía</span>
                    </div>
                    <div class="hero-grid-body">
                        <div class="hero-grid-cat">Economía</div>
                        <h3 class="hero-grid-title">Turismo en Los Ríos crece 35% durante temporada de verano</h3>
                    </div>
                    <div class="hero-grid-meta">
                        <span><i class="far fa-clock"></i> Hace 6h</span>
                        <span><i class="fas fa-eye"></i> 9.876</span>
                    </div>
                </a>

            </div><!-- /hero-grid -->
        </div>
    </section>

    <!-- ======== CONTENIDO PRINCIPAL + SIDEBAR ======== -->
    <div class="container">
        <div class="content-layout">

            <!-- Columna principal -->
            <main>
                <section class="news-section">
                    <h2 class="section-title"><i class="fas fa-newspaper"></i> Últimas Noticias</h2>
                    <div class="news-grid" id="news-grid">

                        <article class="news-card fade-in">
                            <a href="noticia.php?id=2">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1542744173-8e7e53415bb0?w=600&q=80" alt="Desarrollo urbano">
                                    <span class="category-badge">Regional</span>
                                </div>
                                <div class="news-body">
                                    <div class="news-cat-label">Regional</div>
                                    <h3 class="news-title">Municipio de Valdivia aprueba nuevo plan regulador para 2026</h3>
                                    <p class="news-excerpt">El Concejo Municipal aprobó por unanimidad el nuevo plan que contempla zonas de expansión urbana y áreas verdes protegidas...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 4 horas</span>
                                        <span><i class="fas fa-eye"></i> 8.234</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <article class="news-card fade-in">
                            <a href="noticia.php?id=3">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=600&q=80" alt="Deportes">
                                    <span class="category-badge">Deportes</span>
                                </div>
                                <div class="news-body">
                                    <div class="news-cat-label">Deportes</div>
                                    <h3 class="news-title">Deportes Valdivia clasifica a semifinales del torneo regional</h3>
                                    <p class="news-excerpt">El equipo valdiviano venció 3-1 a su rival en un emocionante partido disputado en el estadio municipal...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 5 horas</span>
                                        <span><i class="fas fa-eye"></i> 12.543</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <article class="news-card fade-in">
                            <a href="noticia.php?id=4">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=600&q=80" alt="Economía">
                                    <span class="category-badge" style="background:#d97706;">Economía</span>
                                </div>
                                <div class="news-body">
                                    <div class="news-cat-label">Economía</div>
                                    <h3 class="news-title">Turismo en Los Ríos crece 35% en temporada de verano</h3>
                                    <p class="news-excerpt">Hoteles y servicios turísticos reportan excelentes cifras, superando las expectativas del sector para esta temporada...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 6 horas</span>
                                        <span><i class="fas fa-eye"></i> 9.876</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <article class="news-card fade-in">
                            <a href="noticia.php?id=5">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?w=600&q=80" alt="Cultura">
                                    <span class="category-badge" style="background:#7c3aed;">Cultura</span>
                                </div>
                                <div class="news-body">
                                    <div class="news-cat-label">Cultura</div>
                                    <h3 class="news-title">Festival de música tradicional mapuche reúne a miles de personas</h3>
                                    <p class="news-excerpt">El evento cultural destacó la riqueza ancestral de la región con presentaciones de reconocidos artistas locales...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 8 horas</span>
                                        <span><i class="fas fa-eye"></i> 7.432</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <article class="news-card fade-in">
                            <a href="noticia.php?id=6">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=600&q=80" alt="Salud">
                                    <span class="category-badge" style="background:#0891b2;">Salud</span>
                                </div>
                                <div class="news-body">
                                    <div class="news-cat-label">Salud</div>
                                    <h3 class="news-title">Hospital Base de Valdivia implementa nuevo sistema de atención</h3>
                                    <p class="news-excerpt">Centro asistencial moderniza sus procesos para reducir tiempos de espera y mejorar la calidad de atención...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 10 horas</span>
                                        <span><i class="fas fa-eye"></i> 5.621</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <article class="news-card fade-in">
                            <a href="noticia.php?id=7">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1449034446853-66c86144b0ad?w=600&q=80" alt="Medio Ambiente">
                                    <span class="category-badge" style="background:#059669;">Medio Ambiente</span>
                                </div>
                                <div class="news-body">
                                    <div class="news-cat-label">Medio Ambiente</div>
                                    <h3 class="news-title">Proyecto protegerá más de 15.000 hectáreas de bosque nativo</h3>
                                    <p class="news-excerpt">Iniciativa público-privada busca preservar bosque nativo en la región de Los Ríos con fondos internacionales...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 12 horas</span>
                                        <span><i class="fas fa-eye"></i> 6.234</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                    </div><!-- /news-grid -->
                </section>
            </main>

            <!-- Sidebar -->
            <aside class="sidebar">

                <!-- Lo más leído -->
                <div class="widget">
                    <h3 class="widget-title"><i class="fas fa-fire" style="color:var(--color-primary);margin-right:6px;"></i>Lo Más Leído</h3>
                    <div class="trending-list">
                        <div class="trending-item">
                            <div class="trending-number">1</div>
                            <div class="trending-info">
                                <h4><a href="noticia.php?id=1">Volcán Osorno registra actividad inusual</a></h4>
                                <span>15.432 vistas</span>
                            </div>
                        </div>
                        <div class="trending-item">
                            <div class="trending-number">2</div>
                            <div class="trending-info">
                                <h4><a href="noticia.php?id=3">Deportes Valdivia clasifica a semifinales</a></h4>
                                <span>12.543 vistas</span>
                            </div>
                        </div>
                        <div class="trending-item">
                            <div class="trending-number">3</div>
                            <div class="trending-info">
                                <h4><a href="noticia.php?id=4">Turismo crece 35% en verano</a></h4>
                                <span>9.876 vistas</span>
                            </div>
                        </div>
                        <div class="trending-item">
                            <div class="trending-number">4</div>
                            <div class="trending-info">
                                <h4><a href="noticia.php?id=2">Nuevo plan regulador para Valdivia</a></h4>
                                <span>8.234 vistas</span>
                            </div>
                        </div>
                        <div class="trending-item">
                            <div class="trending-number">5</div>
                            <div class="trending-info">
                                <h4><a href="noticia.php?id=5">Festival de música mapuche exitoso</a></h4>
                                <span>7.432 vistas</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Newsletter -->
                <div class="widget newsletter-widget">
                    <h3 class="widget-title">Boletín Informativo</h3>
                    <p>Recibe las noticias más importantes directamente en tu correo.</p>
                    <form class="newsletter-form" id="newsletter-form">
                        <input type="email" placeholder="Tu correo electrónico" required>
                        <button type="submit"><i class="fas fa-paper-plane"></i> Suscribirme</button>
                    </form>
                </div>

                <!-- Clima -->
                <div class="widget">
                    <h3 class="widget-title"><i class="fas fa-cloud-sun" style="color:var(--color-primary);margin-right:6px;"></i>Clima Regional</h3>
                    <div class="weather-item">
                        <div>
                            <div class="weather-city">Valdivia</div>
                            <div class="weather-desc">Parcialmente nublado</div>
                        </div>
                        <div class="weather-temp">15°C</div>
                    </div>
                    <div class="weather-item">
                        <div>
                            <div class="weather-city">Osorno</div>
                            <div class="weather-desc">Lluvias ligeras</div>
                        </div>
                        <div class="weather-temp">12°C</div>
                    </div>
                    <div class="weather-item">
                        <div>
                            <div class="weather-city">La Unión</div>
                            <div class="weather-desc">Despejado</div>
                        </div>
                        <div class="weather-temp">18°C</div>
                    </div>
                </div>

            </aside>
        </div>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <span class="footer-logo-text">VALDIVIA CAPITAL</span>
                    <h3 style="display:none;"></h3>
                    <p style="color:rgba(255,255,255,0.7);">El principal medio de comunicación digital de la región, comprometido con la información veraz y oportuna.</p>
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

</body>
</html>
