<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/bolsa.php';

if (isMaintenance()) { include 'mantenimiento.php'; exit; }

$db = getDB();
$erroresRegistro = [];
$errorLogin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registro') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $empresa = trim($_POST['empresa_nombre'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password_confirm'] ?? '';

        if ($nombre === '') $erroresRegistro[] = 'Debes ingresar tu nombre.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erroresRegistro[] = 'Debes ingresar un correo válido.';
        if (strlen($password) < 8) $erroresRegistro[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($password !== $password2) $erroresRegistro[] = 'Las contraseñas no coinciden.';

        if (!$erroresRegistro) {
            $stmt = $db->prepare('SELECT id FROM bolsa_publicadores WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                $erroresRegistro[] = 'Ya existe una cuenta registrada con ese correo.';
            } else {
                $stmt = $db->prepare('INSERT INTO bolsa_publicadores (nombre, email, telefono, empresa_nombre, password) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$nombre, $email, $telefono ?: null, $empresa ?: null, password_hash($password, PASSWORD_DEFAULT)]);
                $id = (int)$db->lastInsertId();
                $stmt = $db->prepare('SELECT * FROM bolsa_publicadores WHERE id = ?');
                $stmt->execute([$id]);
                $pub = $stmt->fetch();
                bolsaPublicadorLogin($pub);
                $db->prepare('UPDATE bolsa_publicadores SET last_login = NOW() WHERE id = ?')->execute([$id]);
                header('Location: bolsa-panel.php?welcome=1');
                exit;
            }
        }
    }

    if ($accion === 'login') {
        $email = trim($_POST['email_login'] ?? '');
        $password = $_POST['password_login'] ?? '';

        $stmt = $db->prepare('SELECT * FROM bolsa_publicadores WHERE email = ? AND activo = 1 LIMIT 1');
        $stmt->execute([$email]);
        $pub = $stmt->fetch();

        if (!$pub || !password_verify($password, $pub['password'])) {
            $errorLogin = 'Correo o contraseña incorrectos.';
        } else {
            bolsaPublicadorLogin($pub);
            $db->prepare('UPDATE bolsa_publicadores SET last_login = NOW() WHERE id = ?')->execute([$pub['id']]);
            header('Location: bolsa-panel.php');
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
    <title>Bolsa de trabajo - Acceso publicadores</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <section class="reportero-hero">
        <div class="container reportero-hero-grid">
            <div>
                <span class="reportero-kicker">Bolsa de trabajo VC</span>
                <h1>Publica ofertas laborales</h1>
                <p>Regístrate como publicador, crea ofertas y revisa postulaciones desde tu panel privado.</p>
                <ul class="reportero-benefits">
                    <li><i class="fas fa-check-circle"></i> Registro inmediato con correo y contraseña</li>
                    <li><i class="fas fa-check-circle"></i> Moderación editorial para cada oferta</li>
                    <li><i class="fas fa-check-circle"></i> Postulaciones en panel + envío por correo</li>
                </ul>
            </div>
            <div class="reportero-hero-card">
                <div class="reportero-hero-stat"><strong>1 módulo</strong><span>ofertas y concursos públicos en el mismo flujo</span></div>
                <div class="reportero-hero-stat"><strong>2 postulaciones/día</strong><span>control anti-spam por email e IP</span></div>
                <div class="reportero-hero-stat"><strong>CV adjunto</strong><span>soporte PDF, DOC y DOCX</span></div>
            </div>
        </div>
    </section>

    <section class="reportero-auth-section">
        <div class="container reportero-auth-grid">
            <div class="reportero-card">
                <h2><i class="fas fa-user-plus"></i> Crear cuenta de publicador</h2>
                <?php if ($erroresRegistro): ?>
                    <div class="reportero-alert error">
                        <?php foreach ($erroresRegistro as $err): ?>
                            <div><?php echo htmlspecialchars($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="reportero-form-grid">
                    <input type="hidden" name="accion" value="registro">
                    <div>
                        <label>Nombre o razón social</label>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label>E-mail</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="reportero-form-row">
                        <div>
                            <label>Teléfono</label>
                            <input type="text" name="telefono" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Empresa (opcional)</label>
                            <input type="text" name="empresa_nombre" value="<?php echo htmlspecialchars($_POST['empresa_nombre'] ?? ''); ?>">
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
                    <button type="submit" class="reportero-btn primary"><i class="fas fa-id-card"></i> Crear cuenta</button>
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
                    <button type="submit" class="reportero-btn dark"><i class="fas fa-arrow-right"></i> Entrar al panel</button>
                </form>
                <div class="reportero-auth-extra">
                    <a href="bolsa-trabajo.php" class="reportero-mini-link"><i class="fas fa-briefcase"></i> Ver bolsa pública</a>
                </div>
            </div>
        </div>
    </section>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
