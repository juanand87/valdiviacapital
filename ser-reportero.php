<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/reporteros.php';

if (isMaintenance()) { include 'mantenimiento.php'; exit; }

$db = getDB();
$todasComunas = $db->query('SELECT id, nombre, slug FROM comunas ORDER BY nombre')->fetchAll();

$erroresRegistro = [];
$errorLogin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registro') {
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $rut = reporteroNormalizarRut($_POST['rut'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password_confirm'] ?? '';

        if ($nombres === '') $erroresRegistro[] = 'Debes ingresar tus nombres.';
        if ($apellidos === '') $erroresRegistro[] = 'Debes ingresar tus apellidos.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erroresRegistro[] = 'Debes ingresar un correo válido.';
        if ($direccion === '') $erroresRegistro[] = 'Debes ingresar tu dirección.';
        if ($telefono === '') $erroresRegistro[] = 'Debes ingresar tu teléfono.';
        if (!reporteroValidarRut($rut)) $erroresRegistro[] = 'El RUT ingresado no es válido.';
        if (strlen($password) < 8) $erroresRegistro[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($password !== $password2) $erroresRegistro[] = 'Las contraseñas no coinciden.';

        if (!$erroresRegistro) {
            $stmt = $db->prepare('SELECT id FROM reporteros WHERE email = ? OR rut = ? LIMIT 1');
            $stmt->execute([$email, $rut]);
            if ($stmt->fetch()) {
                $erroresRegistro[] = 'Ya existe un reportero registrado con ese correo o RUT.';
            } else {
                $stmt = $db->prepare('INSERT INTO reporteros (nombres, apellidos, email, password, direccion, telefono, rut) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$nombres, $apellidos, $email, password_hash($password, PASSWORD_DEFAULT), $direccion, $telefono, $rut]);
                $reporteroId = (int)$db->lastInsertId();
                $stmt = $db->prepare('SELECT * FROM reporteros WHERE id = ?');
                $stmt->execute([$reporteroId]);
                $reportero = $stmt->fetch();
                reporteroLogin($reportero);
                $db->prepare('UPDATE reporteros SET last_login = NOW() WHERE id = ?')->execute([$reporteroId]);
                header('Location: reportero-panel.php?welcome=1');
                exit;
            }
        }
    }

    if ($accion === 'login') {
        $emailLogin = trim($_POST['email_login'] ?? '');
        $passwordLogin = $_POST['password_login'] ?? '';

        $stmt = $db->prepare('SELECT * FROM reporteros WHERE email = ? AND activo = 1 LIMIT 1');
        $stmt->execute([$emailLogin]);
        $reportero = $stmt->fetch();

        if (!$reportero || !password_verify($passwordLogin, $reportero['password'])) {
            $errorLogin = 'Correo o contraseña incorrectos.';
        } else {
            reporteroLogin($reportero);
            $db->prepare('UPDATE reporteros SET last_login = NOW() WHERE id = ?')->execute([$reportero['id']]);
            header('Location: reportero-panel.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ser reportero VC - Valdivia Capital</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
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
                <a href="seccion.php?cat=politica">Política</a>
                <a href="seccion.php?cat=economia">Economía</a>
                <a href="seccion.php?cat=deportes">Deportes</a>
                <a href="seccion.php?cat=cultura">Cultura</a>
                <a href="eventos.php"><i class="fas fa-calendar-alt"></i> Eventos</a>
                <div class="nav-dropdown">
                    <a href="#" class="nav-dropdown-toggle"><i class="fas fa-map-marker-alt"></i> Región <i class="fas fa-chevron-down" style="font-size:10px;"></i></a>
                    <ul class="nav-dropdown-menu">
                        <?php foreach ($todasComunas as $c): ?>
                        <li><a href="comuna.php?comuna=<?php echo htmlspecialchars($c['slug']); ?>"><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($c['nombre']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <section class="reportero-hero">
        <div class="container reportero-hero-grid">
            <div>
                <span class="reportero-kicker">Participación ciudadana</span>
                <h1>Ser reportero VC</h1>
                <p>Forma parte de la cobertura local. Regístrate, envía noticias con imagen principal y sigue el estado editorial de cada aporte desde tu cuenta.</p>
                <ul class="reportero-benefits">
                    <li><i class="fas fa-check-circle"></i> Cuenta propia con historial de envíos</li>
                    <li><i class="fas fa-check-circle"></i> Borradores, revisión y correcciones</li>
                    <li><i class="fas fa-check-circle"></i> Aprobación editorial y publicación en Noticias</li>
                </ul>
            </div>
            <div class="reportero-hero-card">
                <div class="reportero-hero-stat"><strong>5 estados</strong><span>borrador, pendiente, revisión, corrección y aprobado</span></div>
                <div class="reportero-hero-stat"><strong>1 imagen</strong><span>cada envío puede salir con foto principal desde el formulario</span></div>
                <div class="reportero-hero-stat"><strong>100% trazable</strong><span>cada aporte aprobado queda vinculado a su noticia publicada</span></div>
            </div>
        </div>
    </section>

    <section class="reportero-auth-section">
        <div class="container reportero-auth-grid">
            <div class="reportero-card">
                <h2><i class="fas fa-user-plus"></i> Registro de reportero</h2>
                <?php if ($erroresRegistro): ?>
                    <div class="reportero-alert error">
                        <?php foreach ($erroresRegistro as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="reportero-form-grid">
                    <input type="hidden" name="accion" value="registro">
                    <div class="reportero-form-row">
                        <div>
                            <label>Nombres</label>
                            <input type="text" name="nombres" value="<?php echo htmlspecialchars($_POST['nombres'] ?? ''); ?>" required>
                        </div>
                        <div>
                            <label>Apellidos</label>
                            <input type="text" name="apellidos" value="<?php echo htmlspecialchars($_POST['apellidos'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="reportero-form-row">
                        <div>
                            <label>E-Mail</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        <div>
                            <label>Teléfono</label>
                            <input type="text" name="telefono" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="reportero-form-row">
                        <div>
                            <label>Dirección</label>
                            <input type="text" name="direccion" value="<?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?>" required>
                        </div>
                        <div>
                            <label>RUT</label>
                            <input type="text" name="rut" value="<?php echo htmlspecialchars($_POST['rut'] ?? ''); ?>" placeholder="12.345.678-5" required>
                        </div>
                    </div>
                    <div class="reportero-form-row">
                        <div>
                            <label>Contraseña</label>
                            <input type="password" name="password" required>
                        </div>
                        <div>
                            <label>Confirmar contraseña</label>
                            <input type="password" name="password_confirm" required>
                        </div>
                    </div>
                    <button type="submit" class="reportero-btn primary"><i class="fas fa-id-card"></i> Crear mi cuenta</button>
                    <p class="reportero-note">Más adelante se activará confirmación por correo. Por ahora el registro queda habilitado de inmediato.</p>
                </form>
            </div>

            <div class="reportero-card">
                <h2><i class="fas fa-right-to-bracket"></i> Iniciar sesión</h2>
                <?php if ($errorLogin): ?>
                    <div class="reportero-alert error"><?php echo htmlspecialchars($errorLogin); ?></div>
                <?php endif; ?>
                <form method="POST" class="reportero-form-grid">
                    <input type="hidden" name="accion" value="login">
                    <div>
                        <label>Correo electrónico</label>
                        <input type="email" name="email_login" value="<?php echo htmlspecialchars($_POST['email_login'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label>Contraseña</label>
                        <input type="password" name="password_login" required>
                    </div>
                    <button type="submit" class="reportero-btn dark"><i class="fas fa-arrow-right"></i> Entrar a mi panel</button>
                </form>

                <?php if (reporteroEstaAutenticado()): ?>
                    <div class="reportero-auth-extra">
                        <p>Ya tienes una sesión activa como <strong><?php echo htmlspecialchars($_SESSION['reportero_nombre']); ?></strong>.</p>
                        <a href="reportero-panel.php" class="reportero-btn secondary"><i class="fas fa-gauge"></i> Ir a mi panel</a>
                    </div>
                <?php else: ?>
                    <div class="reportero-auth-extra">
                        <p>Desde tu panel podrás guardar borradores, reenviar noticias corregidas y revisar cada decisión editorial.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <span class="footer-logo-text">VALDIVIA CAPITAL</span>
                    <p style="color:rgba(255,255,255,0.7);margin-top:10px;">Cobertura regional, participación ciudadana y periodismo local conectado con la comunidad.</p>
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
            <div class="footer-bottom"><p>&copy; <?php echo date('Y'); ?> Valdivia Capital. Todos los derechos reservados.</p></div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>