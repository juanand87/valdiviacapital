<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diario Los Ríos - Noticias de la Región</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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

    <!-- Hero - Noticia Principal -->
    <section class="hero-section">
        <div class="container">
            <a href="noticia.php?id=1" class="hero-card">
                <div class="hero-image">
                    <img src="https://images.unsplash.com/photo-1580048915913-4f8f5cb481c4?w=800" alt="Volcán Osorno">
                    <span class="category-badge">DESTACADO</span>
                </div>
                <div class="hero-content">
                    <h2 class="hero-title">Volcán Osorno registra actividad inusual: Expertos monitorean la situación</h2>
                    <p class="hero-excerpt">Autoridades de SERNAGEOMIN mantienen alerta amarilla en la zona tras detectar movimientos sísmicos de baja intensidad. Especialistas señalan que la actividad es normal pero requiere supervisión constante.</p>
                    <div class="hero-meta">
                        <span><i class="far fa-clock"></i> Hace 2 horas</span>
                        <span><i class="far fa-user"></i> Por Daniela Montecinos</span>
                        <span><i class="fas fa-eye"></i> 15,432 vistas</span>
                    </div>
                    <button class="btn-primary">Leer noticia completa <i class="fas fa-arrow-right"></i></button>
                </div>
            </a>
        </div>
    </section>

    <!-- Contenido Principal con Sidebar -->
    <div class="container">
        <div class="content-layout">
            <!-- Columna Principal -->
            <main>
                <!-- Sección de Noticias Regionales -->
                <section class="news-section">
                    <h2 class="section-title">Últimas Noticias Regionales</h2>
                    <div class="news-grid">
                        <!-- Noticia 1 -->
                        <article class="news-card">
                            <a href="noticia.php?id=2">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1542744173-8e7e53415bb0?w=600" alt="Desarrollo urbano">
                                    <span class="category-badge" style="background: #059669;">REGIONAL</span>
                                </div>
                                <div class="news-body">
                                    <h3 class="news-title">Municipio de Valdivia aprueba nuevo plan regulador para 2026</h3>
                                    <p class="news-excerpt">El Concejo Municipal aprobó por unanimidad el nuevo plan que contempla zonas de expansión urbana y áreas verdes protegidas...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 4 horas</span>
                                        <span><i class="fas fa-eye"></i> 8,234 vistas</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <!-- Noticia 2 -->
                        <article class="news-card">
                            <a href="noticia.php?id=3">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=600" alt="Deportes">
                                    <span class="category-badge" style="background: #dc2626;">DEPORTES</span>
                                </div>
                                <div class="news-body">
                                    <h3 class="news-title">Deportes Valdivia clasifica a semifinales del torneo regional</h3>
                                    <p class="news-excerpt">El equipo valdiviano venció 3-1 a su rival en un emocionante partido disputado en el estadio municipal...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 5 horas</span>
                                        <span><i class="fas fa-eye"></i> 12,543 vistas</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <!-- Noticia 3 -->
                        <article class="news-card">
                            <a href="noticia.php?id=4">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=600" alt="Economía">
                                    <span class="category-badge" style="background: #f59e0b;">ECONOMÍA</span>
                                </div>
                                <div class="news-body">
                                    <h3 class="news-title">Turismo en Los Ríos crece 35% en temporada de verano</h3>
                                    <p class="news-excerpt">Hoteles y servicios turísticos reportan excelentes cifras, superando las expectativas del sector para esta temporada...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 6 horas</span>
                                        <span><i class="fas fa-eye"></i> 9,876 vistas</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <!-- Noticia 4 -->
                        <article class="news-card">
                            <a href="noticia.php?id=5">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?w=600" alt="Cultura">
                                    <span class="category-badge" style="background: #8b5cf6;">CULTURA</span>
                                </div>
                                <div class="news-body">
                                    <h3 class="news-title">Festival de música tradicional mapuche reúne a miles de personas</h3>
                                    <p class="news-excerpt">El evento cultural destacó la riqueza ancestral de la región con presentaciones de reconocidos artistas locales...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 8 horas</span>
                                        <span><i class="fas fa-eye"></i> 7,432 vistas</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <!-- Noticia 5 -->
                        <article class="news-card">
                            <a href="noticia.php?id=6">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=600" alt="Salud">
                                    <span class="category-badge" style="background: #06b6d4;">SALUD</span>
                                </div>
                                <div class="news-body">
                                    <h3 class="news-title">Hospital Base de Valdivia implementa nuevo sistema de atención</h3>
                                    <p class="news-excerpt">Centro asistencial moderniza sus procesos para reducir tiempos de espera y mejorar la calidad de atención...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 10 horas</span>
                                        <span><i class="fas fa-eye"></i> 5,621 vistas</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <!-- Noticia 6 -->
                        <article class="news-card">
                            <a href="noticia.php?id=7">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1449034446853-66c86144b0ad?w=600" alt="Medio Ambiente">
                                    <span class="category-badge" style="background: #10b981;">MEDIO AMBIENTE</span>
                                </div>
                                <div class="news-body">
                                    <h3 class="news-title">Proyecto de conservación protegerá bosques nativos del sur</h3>
                                    <p class="news-excerpt">Iniciativa público-privada busca preservar más de 15,000 hectáreas de bosque nativo en la región de Los Ríos...</p>
                                    <div class="news-meta">
                                        <span><i class="far fa-clock"></i> Hace 12 horas</span>
                                        <span><i class="fas fa-eye"></i> 6,234 vistas</span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    </div>
                </section>
            </main>

            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Widget de Tendencias -->
                <div class="widget">
                    <h3 class="widget-title">Lo Más Leído</h3>
                    <div class="trending-list">
                        <div class="trending-item">
                            <div class="trending-number">1</div>
                            <div class="trending-info">
                                <h4>Volcán Osorno registra actividad inusual</h4>
                                <span>15,432 vistas</span>
                            </div>
                        </div>
                        <div class="trending-item">
                            <div class="trending-number">2</div>
                            <div class="trending-info">
                                <h4>Deportes Valdivia clasifica a semifinales</h4>
                                <span>12,543 vistas</span>
                            </div>
                        </div>
                        <div class="trending-item">
                            <div class="trending-number">3</div>
                            <div class="trending-info">
                                <h4>Turismo crece 35% en temporada de verano</h4>
                                <span>9,876 vistas</span>
                            </div>
                        </div>
                        <div class="trending-item">
                            <div class="trending-number">4</div>
                            <div class="trending-info">
                                <h4>Nuevo plan regulador para Valdivia</h4>
                                <span>8,234 vistas</span>
                            </div>
                        </div>
                        <div class="trending-item">
                            <div class="trending-number">5</div>
                            <div class="trending-info">
                                <h4>Festival de música mapuche exitoso</h4>
                                <span>7,432 vistas</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Widget Newsletter -->
                <div class="widget newsletter-widget">
                    <h3 class="widget-title">Boletín Informativo</h3>
                    <p>Recibe las noticias más importantes directamente en tu correo</p>
                    <form class="newsletter-form" id="newsletter-form">
                        <input type="email" placeholder="Tu correo electrónico" required>
                        <button type="submit">Suscribirme</button>
                    </form>
                </div>

                <!-- Widget Clima -->
                <div class="widget">
                    <h3 class="widget-title">Clima Regional</h3>
                    <div style="padding: 15px 0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--color-light);">
                            <div>
                                <strong>Valdivia</strong><br>
                                <span style="color: var(--color-gray);">Parcialmente nublado</span>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--color-primary);">15°C</div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--color-light);">
                            <div>
                                <strong>Osorno</strong><br>
                                <span style="color: var(--color-gray);">Lluvias ligeras</span>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--color-primary);">12°C</div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <div>
                                <strong>La Unión</strong><br>
                                <span style="color: var(--color-gray);">Despejado</span>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--color-primary);">18°C</div>
                            </div>
                        </div>
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
                    <h3>Sobre Nosotros</h3>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.7;">
                        Diario Los Ríos es el principal medio de comunicación digital de la región, 
                        comprometido con la información veraz y oportuna para nuestra comunidad.
                    </p>
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
                        <li><i class="fas fa-envelope"></i> contacto@diariolosrios.cl</li>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
