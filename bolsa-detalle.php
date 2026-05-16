<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/bolsa.php';

if (isMaintenance()) { include 'mantenimiento.php'; exit; }

$db = getDB();
$db->query("UPDATE bolsa_ofertas SET estado = 'vencido' WHERE estado = 'publicado' AND fecha_cierre < CURDATE()");

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: bolsa-trabajo.php');
    exit;
}

$stmt = $db->prepare("SELECT o.*, c.nombre AS comuna_nombre
    FROM bolsa_ofertas o
    LEFT JOIN comunas c ON c.id = o.comuna_id
    WHERE o.slug = ? AND o.estado = 'publicado' AND o.fecha_cierre >= CURDATE()
    LIMIT 1");
$stmt->execute([$slug]);
$oferta = $stmt->fetch();

if (!$oferta) {
    header('Location: bolsa-trabajo.php');
    exit;
}

$recaptchaSiteKey = trim((string)bolsaGetConfig($db, 'recaptcha_site_key', ''));
$maxCvMb = max(1, (int)bolsaGetConfig($db, 'max_cv_mb', '5'));
$extPermitidas = array_filter(array_map('trim', explode(',', strtolower((string)bolsaGetConfig($db, 'cv_ext_permitidas', 'pdf,doc,docx')))));
if (!$extPermitidas) {
    $extPermitidas = ['pdf', 'doc', 'docx'];
}

$ok = '';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($nombre === '') $errores[] = 'Debes ingresar tu nombre.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Debes ingresar un correo válido.';
    if ($telefono === '') $errores[] = 'Debes ingresar tu teléfono.';
    if ($mensaje === '') $errores[] = 'Debes ingresar un mensaje.';

    if (!$errores) {
        $rate = bolsaPostulacionesDisponiblesHoy($db, $email, $ip);
        if (!$rate['ok']) {
            $errores[] = 'Límite diario alcanzado. Máximo ' . (int)$rate['max'] . ' postulaciones por día.';
        }
    }

    if (!$errores) {
        $recaptcha = bolsaVerifyRecaptcha($db, $_POST['g-recaptcha-response'] ?? '');
        if (!$recaptcha['ok']) {
            $errores[] = $recaptcha['error'];
        }
    }

    if (!$errores) {
        $upload = bolsaUploadCv('cv', $maxCvMb, $extPermitidas);
        if (!$upload['ok']) {
            $errores[] = $upload['error'];
        }
    }

    if (!$errores) {
        $stmtIns = $db->prepare('INSERT INTO bolsa_postulaciones (oferta_id, nombre, email, telefono, mensaje, cv_archivo, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmtIns->execute([
            $oferta['id'],
            $nombre,
            $email,
            $telefono,
            $mensaje,
            $upload['path'],
            $ip,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
        ]);

        $subject = 'Nueva postulación: ' . $oferta['titulo'];
        $body = '<h2>Nueva postulación recibida</h2>'
            . '<p><strong>Oferta:</strong> ' . htmlspecialchars($oferta['titulo']) . '</p>'
            . '<p><strong>Nombre:</strong> ' . htmlspecialchars($nombre) . '</p>'
            . '<p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>'
            . '<p><strong>Teléfono:</strong> ' . htmlspecialchars($telefono) . '</p>'
            . '<p><strong>Mensaje:</strong><br>' . nl2br(htmlspecialchars($mensaje)) . '</p>'
            . '<p><strong>CV:</strong> ' . htmlspecialchars(baseUrl($upload['path'])) . '</p>'
            . '<p><a href="' . htmlspecialchars(baseUrl('bolsa-login.php')) . '">Entrar al panel de bolsa</a></p>';

        bolsaSendEmail($db, $oferta['email_contacto'], $subject, $body, 'Nueva postulación a ' . $oferta['titulo']);

        $ok = 'Tu postulación fue enviada correctamente.';
        $_POST = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($oferta['titulo']); ?> - Bolsa de trabajo</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<section class="reportero-auth-section" style="padding-top:30px;">
    <div class="container reportero-editor-grid">
        <div class="reportero-card">
            <div class="bolsa-head">
                <span class="reportero-status <?php echo $oferta['tipo'] === 'concurso_publico' ? 'status-review' : 'status-approved'; ?>"><?php echo htmlspecialchars(bolsaTipoLabel($oferta['tipo'])); ?></span>
                <?php if ((int)$oferta['destacado'] === 1): ?><span class="reportero-status status-fix">Destacado</span><?php endif; ?>
            </div>
            <h1 style="margin-top:10px;"><?php echo htmlspecialchars($oferta['titulo']); ?></h1>
            <p class="bolsa-meta"><i class="fas fa-building"></i> <?php echo htmlspecialchars($oferta['empresa_institucion']); ?></p>
            <p class="bolsa-meta"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($oferta['cargo']); ?> · <?php echo htmlspecialchars($oferta['rubro']); ?></p>
            <p class="bolsa-meta"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($oferta['comuna_nombre'] ?? 'Sin comuna'); ?><?php echo $oferta['ubicacion_texto'] ? ' · ' . htmlspecialchars($oferta['ubicacion_texto']) : ''; ?></p>
            <p class="bolsa-meta"><i class="fas fa-clock"></i> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $oferta['jornada']))); ?> · <?php echo htmlspecialchars(ucfirst($oferta['modalidad'])); ?></p>
            <?php if ($oferta['salario_texto']): ?><p class="bolsa-meta"><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($oferta['salario_texto']); ?></p><?php endif; ?>
            <p class="bolsa-meta"><i class="fas fa-calendar-times"></i> Cierre: <?php echo htmlspecialchars(date('d-m-Y', strtotime($oferta['fecha_cierre']))); ?></p>

            <hr style="margin:18px 0;border:none;border-top:1px solid #ececec;">
            <h3>Descripción</h3>
            <div style="line-height:1.6;white-space:pre-wrap;"><?php echo htmlspecialchars($oferta['descripcion']); ?></div>

            <?php if ($oferta['requisitos']): ?>
            <h3 style="margin-top:18px;">Requisitos</h3>
            <div style="line-height:1.6;white-space:pre-wrap;"><?php echo htmlspecialchars($oferta['requisitos']); ?></div>
            <?php endif; ?>
        </div>

        <div class="reportero-card">
            <h2><i class="fas fa-paper-plane"></i> Postular a esta oferta</h2>
            <?php if ($ok): ?><div class="reportero-alert success"><?php echo htmlspecialchars($ok); ?></div><?php endif; ?>
            <?php if ($errores): ?>
                <div class="reportero-alert error">
                    <?php foreach ($errores as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="reportero-form-grid">
                <div>
                    <label>Nombre completo</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
                </div>
                <div class="reportero-form-row">
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label>Teléfono</label>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>" required>
                    </div>
                </div>
                <div>
                    <label>Mensaje</label>
                    <textarea name="mensaje" rows="5" required><?php echo htmlspecialchars($_POST['mensaje'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label>Adjuntar CV (<?php echo htmlspecialchars(strtoupper(implode(', ', $extPermitidas))); ?>)</label>
                    <input type="file" name="cv" accept=".pdf,.doc,.docx" required>
                    <p class="reportero-note">Tamaño máximo: <?php echo (int)$maxCvMb; ?> MB</p>
                </div>

                <?php if ($recaptchaSiteKey !== ''): ?>
                    <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey); ?>"></div>
                <?php else: ?>
                    <div class="reportero-alert warning">Recaptcha no está configurado. Contacta al administrador.</div>
                <?php endif; ?>

                <button type="submit" class="reportero-btn primary" <?php echo $recaptchaSiteKey === '' ? 'disabled' : ''; ?>><i class="fas fa-paper-plane"></i> Enviar postulación</button>
            </form>
        </div>
    </div>
</section>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
