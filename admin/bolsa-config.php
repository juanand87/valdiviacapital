<?php
$page_title = 'Configuración bolsa trabajo';
require_once '../includes/config.php';
require_once '../includes/bolsa.php';
require_once 'includes/auth.php';
verificarPermiso('admin');
include 'includes/header.php';

$db = getDB();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $host = trim($_POST['smtp_host'] ?? '');
        $puerto = (int)($_POST['smtp_puerto'] ?? 587);
        $usuario = trim($_POST['smtp_usuario'] ?? '');
        $password = trim($_POST['smtp_password'] ?? '');
        $cifrado = in_array($_POST['smtp_cifrado'] ?? '', ['none', 'tls', 'ssl'], true) ? $_POST['smtp_cifrado'] : 'tls';
        $fromEmail = trim($_POST['smtp_from_email'] ?? '');
        $fromName = trim($_POST['smtp_from_name'] ?? 'Valdivia Capital');
        $activo = isset($_POST['smtp_activo']) ? 1 : 0;

        $actual = bolsaGetSmtpConfig($db);
        if ($password === '' && $actual) {
            $password = $actual['password'];
        }

        $stmt = $db->prepare('UPDATE bolsa_config_smtp SET host=?, puerto=?, usuario=?, password=?, cifrado=?, from_email=?, from_name=?, activo=?, updated_at=NOW() WHERE id=1');
        $stmt->execute([$host, $puerto, $usuario, $password, $cifrado, $fromEmail, $fromName, $activo]);

        bolsaSetConfig($db, 'recaptcha_site_key', trim($_POST['recaptcha_site_key'] ?? ''));
        bolsaSetConfig($db, 'recaptcha_secret_key', trim($_POST['recaptcha_secret_key'] ?? ''));
        bolsaSetConfig($db, 'max_postulaciones_diarias', max(1, (int)($_POST['max_postulaciones_diarias'] ?? 2)));
        bolsaSetConfig($db, 'max_cv_mb', max(1, (int)($_POST['max_cv_mb'] ?? 5)));

        $ext = trim($_POST['cv_ext_permitidas'] ?? 'pdf,doc,docx');
        bolsaSetConfig($db, 'cv_ext_permitidas', strtolower($ext));

        $msg = 'Configuración guardada.';
    }

    if ($accion === 'probar') {
        $to = trim($_POST['test_email'] ?? '');
        $result = bolsaSendEmail($db, $to, 'Prueba SMTP Bolsa Trabajo', '<p>Prueba de correo exitosa.</p>', 'Prueba SMTP');
        if ($result['ok']) {
            $msg = 'Correo de prueba enviado correctamente.';
        } else {
            $err = $result['error'] ?? 'No se pudo enviar el correo de prueba.';
        }
    }
}

$smtp = bolsaGetSmtpConfig($db);
$cfg = [
    'recaptcha_site_key' => bolsaGetConfig($db, 'recaptcha_site_key', ''),
    'recaptcha_secret_key' => bolsaGetConfig($db, 'recaptcha_secret_key', ''),
    'max_postulaciones_diarias' => bolsaGetConfig($db, 'max_postulaciones_diarias', '2'),
    'max_cv_mb' => bolsaGetConfig($db, 'max_cv_mb', '5'),
    'cv_ext_permitidas' => bolsaGetConfig($db, 'cv_ext_permitidas', 'pdf,doc,docx'),
];
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-sliders-h"></i> Configuración Bolsa Trabajo</h1>
        <p>SMTP, recaptcha y límites de postulación</p>
    </div>
    <a href="bolsa-ofertas.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2 class="card-title">SMTP</h2></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="accion" value="guardar">

            <div class="form-group">
                <label class="form-label"><input type="checkbox" name="smtp_activo" value="1" <?php echo ((int)($smtp['activo'] ?? 0) === 1) ? 'checked' : ''; ?>> Activar SMTP</label>
            </div>

            <div style="display:grid;grid-template-columns:1fr 120px 140px;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Host</label>
                    <input class="form-control" type="text" name="smtp_host" value="<?php echo htmlspecialchars($smtp['host'] ?? ''); ?>" placeholder="smtp.tudominio.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Puerto</label>
                    <input class="form-control" type="number" name="smtp_puerto" value="<?php echo (int)($smtp['puerto'] ?? 587); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Cifrado</label>
                    <select class="form-control" name="smtp_cifrado">
                        <option value="none" <?php echo ($smtp['cifrado'] ?? '') === 'none' ? 'selected' : ''; ?>>Sin cifrado</option>
                        <option value="tls" <?php echo ($smtp['cifrado'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?php echo ($smtp['cifrado'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Usuario SMTP</label>
                    <input class="form-control" type="text" name="smtp_usuario" value="<?php echo htmlspecialchars($smtp['usuario'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Password SMTP (dejar vacío para mantener)</label>
                    <input class="form-control" type="password" name="smtp_password" value="">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">From email</label>
                    <input class="form-control" type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($smtp['from_email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">From name</label>
                    <input class="form-control" type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($smtp['from_name'] ?? 'Valdivia Capital'); ?>">
                </div>
            </div>

            <hr style="margin:16px 0;border:none;border-top:1px solid #eee;">

            <h3 style="margin-bottom:10px;">Recaptcha</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Site key</label>
                    <input class="form-control" type="text" name="recaptcha_site_key" value="<?php echo htmlspecialchars($cfg['recaptcha_site_key']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Secret key</label>
                    <input class="form-control" type="text" name="recaptcha_secret_key" value="<?php echo htmlspecialchars($cfg['recaptcha_secret_key']); ?>">
                </div>
            </div>

            <h3 style="margin:6px 0 10px;">Límites</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Máx postulaciones diarias</label>
                    <input class="form-control" type="number" min="1" name="max_postulaciones_diarias" value="<?php echo (int)$cfg['max_postulaciones_diarias']; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Máx CV (MB)</label>
                    <input class="form-control" type="number" min="1" name="max_cv_mb" value="<?php echo (int)$cfg['max_cv_mb']; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Extensiones CV</label>
                    <input class="form-control" type="text" name="cv_ext_permitidas" value="<?php echo htmlspecialchars($cfg['cv_ext_permitidas']); ?>" placeholder="pdf,doc,docx">
                </div>
            </div>

            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Guardar configuración</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Probar envío</h2></div>
    <div class="card-body">
        <form method="POST" style="display:flex;gap:10px;align-items:flex-end;">
            <input type="hidden" name="accion" value="probar">
            <div class="form-group" style="margin:0;flex:1;">
                <label class="form-label">Correo destino</label>
                <input class="form-control" type="email" name="test_email" required placeholder="correo@dominio.com">
            </div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> Enviar prueba</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
