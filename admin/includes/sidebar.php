<?php require_once __DIR__ . '/../../includes/maintenance.php'; ?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-newspaper"></i> Los Ríos</h2>
    </div>
    
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="noticias.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'noticias.php' ? 'active' : ''; ?>">
            <i class="fas fa-newspaper"></i>
            <span>Noticias</span>
        </a>
        
        <a href="categorias.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'categorias.php' ? 'active' : ''; ?>">
            <i class="fas fa-folder"></i>
            <span>Categorías</span>
        </a>
        
        <a href="comentarios.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'comentarios.php' ? 'active' : ''; ?>">
            <i class="fas fa-comments"></i>
            <span>Comentarios</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="medios-conectados.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['medios-conectados.php', 'medios-diarios.php', 'medios-facebook.php', 'medios-instagram.php', 'medios-scraping.php', 'noticias-escaneadas.php', 'configuracion-ia.php']) ? 'active' : ''; ?>">
            <i class="fas fa-broadcast-tower"></i>
            <span>Medios Conectados</span>
        </a>
        
        <!-- Submenú de Medios Conectados -->
        <?php if (in_array(basename($_SERVER['PHP_SELF']), ['medios-conectados.php', 'medios-diarios.php', 'medios-facebook.php', 'medios-instagram.php', 'medios-scraping.php', 'noticias-escaneadas.php', 'configuracion-ia.php'])): ?>
            <div style="padding-left: 20px; background: rgba(0,0,0,0.05);">
                <a href="noticias-escaneadas.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'noticias-escaneadas.php' ? 'active' : ''; ?>" style="font-size: 14px; padding: 10px 15px;">
                    <i class="fas fa-list"></i>
                    <span>Noticias Escaneadas</span>
                </a>
                <a href="configuracion-ia.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'configuracion-ia.php' ? 'active' : ''; ?>" style="font-size: 14px; padding: 10px 15px;">
                    <i class="fas fa-robot"></i>
                    <span>Configuración IA</span>
                </a>
            </div>
        <?php endif; ?>
        
        <div class="nav-divider"></div>
        
        <a href="usuarios.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Usuarios</span>
        </a>
        
        <a href="newsletter.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'newsletter.php' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i>
            <span>Newsletter</span>
        </a>
        
        <a href="banners.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'banners.php' ? 'active' : ''; ?>">
            <i class="fas fa-ad"></i>
            <span>Banners</span>
        </a>
        
        <a href="medios.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'medios.php' ? 'active' : ''; ?>">
            <i class="fas fa-photo-video"></i>
            <span>Medios</span>
        </a>

        <a href="videos.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'videos.php' ? 'active' : ''; ?>">
            <i class="fas fa-film"></i>
            <span>Videos</span>
        </a>

        <a href="galerias-video.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'galerias-video.php' ? 'active' : ''; ?>">
            <i class="fas fa-layer-group"></i>
            <span>Galerías Video</span>
        </a>

        <a href="eventos.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'eventos.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Eventos</span>
        </a>

        <a href="reporteros.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['reporteros.php', 'revisar-reportero.php']) ? 'active' : ''; ?>">
            <i class="fas fa-microphone-lines"></i>
            <span>Reporteros VC</span>
        </a>

        <a href="bolsa-ofertas.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['bolsa-ofertas.php', 'revisar-bolsa.php', 'bolsa-config.php']) ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i>
            <span>Bolsa Trabajo</span>
        </a>

        <a href="ticker.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'ticker.php' ? 'active' : ''; ?>">
            <i class="fas fa-scroll"></i>
            <span>Ticker</span>
        </a>

        <a href="push.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'push.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <span>Push Notifications</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="../index.php" class="nav-item" target="_blank">
            <i class="fas fa-external-link-alt"></i>
            <span>Ver Sitio</span>
        </a>
        
        <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </nav>

    <!-- Toggle Mantenimiento -->
    <div class="sidebar-maintenance">
        <label class="maintenance-label" title="Activar/desactivar modo mantenimiento">
            <div class="maintenance-toggle-wrap">
                <input type="checkbox" id="chk-mantenimiento" <?php echo isMaintenance() ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </div>
            <span class="maintenance-text">
                <i class="fas fa-tools"></i>
                <span>Mantenimiento</span>
            </span>
        </label>
        <div id="maint-status" class="maint-status" style="display:none"></div>
    </div>

    <script>
    (function () {
        var chk = document.getElementById('chk-mantenimiento');
        if (!chk) return;
        chk.addEventListener('change', function () {
            var estado = this.checked ? '1' : '0';
            var status = document.getElementById('maint-status');
            status.style.display = 'none';
            fetch('ajax/toggle-mantenimiento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'estado=' + estado
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    status.textContent = data.mantenimiento ? '¡Activo!' : 'Desactivado';
                    status.className = 'maint-status ' + (data.mantenimiento ? 'maint-on' : 'maint-off');
                    status.style.display = 'block';
                    setTimeout(function () { status.style.display = 'none'; }, 2500);
                }
            });
        });
    })();
    </script>
</aside>
