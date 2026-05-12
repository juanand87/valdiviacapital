<?php
$page_title = 'Configurar VAPID';
require_once '../includes/config.php';
require_once '../includes/webpush.php';
include 'includes/header.php';

$msg     = '';
$msgType = '';
$configFile = __DIR__ . '/../includes/vapid_config.php';
$exists     = file_exists($configFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirmar'] ?? '') === '1') {
    $forzar = !empty($_POST['forzar']);

    if ($exists && !$forzar) {
        $msg     = 'El archivo <code>includes/vapid_config.php</code> ya existe. Marca "Forzar regeneración" para sobreescribir.';
        $msgType = 'warning';
    } else {
        try {
            $keys    = vapid_generate();
            $subject = 'mailto:contacto@valdiviacapital.cl';
            $date    = date('Y-m-d H:i:s');

            $content = <<<PHP
<?php
/**
 * Claves VAPID — generadas automáticamente por admin/setup-vapid.php
 * Fecha: {$date}
 *
 * NO editar manualmente.
 * IMPORTANTE: si regeneras las claves, los suscriptores actuales necesitarán
 * re-suscribirse (la clave pública cambia y los endpoints quedan inválidos).
 */
define('VAPID_PUBLIC_KEY',  '{$keys['public']}');
define('VAPID_PRIVATE_KEY', '{$keys['private']}');
define('VAPID_SUBJECT',     '{$subject}');
PHP;

            if (file_put_contents($configFile, $content) === false) {
                throw new \RuntimeException(
                    'No se pudo escribir el archivo. Verifica permisos en includes/'
                );
            }

            $msg     = 'Claves VAPID generadas y guardadas en <code>includes/vapid_config.php</code>.';
            $msgType = 'success';
            $exists  = true;
        } catch (\Throwable $e) {
            $msg     = 'Error: ' . htmlspecialchars($e->getMessage());
            $msgType = 'danger';
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-key"></i> Configurar VAPID</h1>
        <p class="page-subtitle">Genera el par de claves para Web Push Notifications</p>
    </div>
    <a href="push.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver a Push
    </a>
</div>

<?php if ($msg): ?>
<div style="padding:14px 18px;border-radius:8px;margin-bottom:20px;
    <?php echo $msgType === 'success'
        ? 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;'
        : ($msgType === 'warning'
            ? 'background:#fef3c7;color:#92400e;border:1px solid #fcd34d;'
            : 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;'); ?>">
    <?php echo $msg; ?>
    <?php if ($msgType === 'success'): ?>
    &nbsp;<a href="push.php" style="font-weight:700;text-decoration:underline;">Ir al panel de Push →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($exists && !$msg): ?>
<div style="padding:14px 18px;border-radius:8px;margin-bottom:20px;background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;">
    <i class="fas fa-check-circle"></i>
    Las claves VAPID ya están configuradas en <code>includes/vapid_config.php</code>.
    Puedes regenerarlas si es necesario.
</div>
<?php endif; ?>

<div class="card" style="max-width:680px;">
    <div class="card-body" style="padding:30px;">
        <h3 style="margin-bottom:14px;">¿Qué son las claves VAPID?</h3>
        <p style="color:#64748b;margin-bottom:16px;">
            VAPID (<em>Voluntary Application Server Identification</em>) es el estándar que permite
            al servidor identificarse ante el servicio de push del navegador (FCM de Google, Mozilla Push…).
            Se genera un par de claves asimétricas <strong>EC P-256</strong>: la clave pública se comparte
            con el navegador y la clave privada firma los tokens JWT de autorización.
        </p>

        <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:14px;margin-bottom:24px;font-size:13px;color:#92400e;">
            <strong>⚠ Atención:</strong> Regenerar las claves invalida todas las suscripciones existentes.
            Los usuarios necesitarán activar las notificaciones de nuevo.
        </div>

        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:14px;margin-bottom:24px;font-size:13px;color:#0369a1;">
            <strong>ℹ Nota sobre HTTPS:</strong> Las notificaciones push solo funcionan en sitios servidos
            por <strong>HTTPS</strong>. En local (XAMPP con HTTP) el registro del Service Worker puede
            fallar en algunos navegadores, pero el código es correcto para producción.
        </div>

        <form method="POST">
            <input type="hidden" name="confirmar" value="1">
            <div style="margin-bottom:18px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="forzar" value="1" style="width:16px;height:16px;">
                    <span style="font-size:14px;">Forzar regeneración <?php echo $exists ? '(sobrescribir clave actual)' : ''; ?></span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="padding:12px 28px;font-size:1rem;">
                <i class="fas fa-cog"></i>
                <?php echo $exists ? 'Regenerar Claves VAPID' : 'Generar Claves VAPID'; ?>
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
