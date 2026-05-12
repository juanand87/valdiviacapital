<?php
$page_title = 'Notificaciones Push';
require_once '../includes/config.php';
require_once '../includes/webpush.php';
include 'includes/header.php';

$db     = getDB();
$vapidOk = defined('VAPID_PUBLIC_KEY') && strlen(VAPID_PUBLIC_KEY) > 20;
$msg     = '';
$msgType = '';

/* ─── Procesar acciones POST ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'enviar' && $vapidOk) {
        $titulo  = trim(strip_tags($_POST['titulo']  ?? ''));
        $mensaje = trim(strip_tags($_POST['mensaje'] ?? ''));
        $url     = trim($_POST['url'] ?? SITE_URL . '/');

        if ($titulo && $mensaje) {
            /* Guardar el mensaje en el historial */
            $db->prepare("INSERT INTO push_messages (titulo, mensaje, url) VALUES (?, ?, ?)")
               ->execute([$titulo, $mensaje, $url]);

            /* Enviar a todos los suscriptores */
            $subs    = $db->query("SELECT endpoint, p256dh, auth FROM push_subscriptions")->fetchAll();
            $sent = $expired = $failed = 0;

            foreach ($subs as $sub) {
                $code = vapid_send($sub, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT);
                if ($code >= 200 && $code < 300) {
                    $sent++;
                } elseif ($code === 410 || $code === 404) {
                    /* Suscripción expirada — limpiar */
                    $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")
                       ->execute([$sub['endpoint']]);
                    $expired++;
                } else {
                    $failed++;
                }
            }

            $msg     = "Push enviado a <strong>{$sent}</strong> dispositivo(s). "
                     . "Expirados eliminados: {$expired}. Fallos: {$failed}.";
            $msgType = ($sent > 0) ? 'success' : 'warning';

        } else {
            $msg     = 'El título y el mensaje son requeridos.';
            $msgType = 'danger';
        }
    }

    if ($accion === 'eliminar_sub') {
        $id = (int)($_POST['sub_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$id]);
            $msg     = 'Suscripción eliminada.';
            $msgType = 'info';
        }
    }
}

/* ─── Estadísticas ────────────────────────────────────────────── */
try {
    $totalSubs  = (int)$db->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn();
    $totalPush  = (int)$db->query("SELECT COUNT(*) FROM push_messages")->fetchColumn();
    $ultimoPush = $db->query("SELECT * FROM push_messages ORDER BY id DESC LIMIT 1")->fetch();
    $historial  = $db->query("SELECT * FROM push_messages ORDER BY id DESC LIMIT 10")->fetchAll();
    $subs       = $db->query(
        "SELECT id, LEFT(endpoint, 60) AS ep_short, created_at FROM push_subscriptions ORDER BY created_at DESC LIMIT 25"
    )->fetchAll();
} catch (\PDOException $e) {
    $totalSubs = $totalPush = 0;
    $ultimoPush = null; $historial = []; $subs = [];
    if (!$msg) {
        $msg     = 'Las tablas de push no existen. Ejecuta el archivo <code>migracion_push.sql</code> en tu base de datos.';
        $msgType = 'warning';
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-bell"></i> Notificaciones Push</h1>
        <p class="page-subtitle">Envía alertas de última hora directamente a los dispositivos suscritos</p>
    </div>
</div>

<?php if ($msg): ?>
<div style="padding:14px 18px;border-radius:8px;margin-bottom:20px;
    <?php echo $msgType === 'success'
        ? 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;'
        : ($msgType === 'danger'
            ? 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;'
            : ($msgType === 'warning'
                ? 'background:#fef3c7;color:#92400e;border:1px solid #fcd34d;'
                : 'background:#e0f2fe;color:#075985;border:1px solid #7dd3fc;')); ?>">
    <?php echo $msg; ?>
</div>
<?php endif; ?>

<?php if (!$vapidOk): ?>
<!-- ── VAPID no configurado ─────────────────────────────── -->
<div class="card" style="border-left:4px solid #f59e0b;">
    <div class="card-body" style="padding:40px;text-align:center;">
        <div style="font-size:52px;margin-bottom:16px;">🔑</div>
        <h2 style="margin-bottom:10px;">VAPID no configurado</h2>
        <p style="color:#64748b;max-width:520px;margin:0 auto 24px;">
            Para enviar notificaciones push necesitas generar un par de claves VAPID (EC P-256).
            El proceso es automático, gratuito y se realiza una sola vez.
        </p>
        <a href="setup-vapid.php" class="btn btn-primary" style="padding:12px 30px;font-size:1rem;">
            <i class="fas fa-key"></i> Generar Claves VAPID
        </a>
        <p style="margin-top:16px;font-size:13px;color:#94a3b8;">
            Las claves se guardarán en <code>includes/vapid_config.php</code>
        </p>
    </div>
</div>

<?php else: ?>
<!-- ── VAPID configurado ─────────────────────────────────── -->

<!-- Estadísticas -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-title">Dispositivos Suscritos</div>
        <div class="stat-value" style="color:var(--color-primary);"><?php echo $totalSubs; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title">Pushes Enviados</div>
        <div class="stat-value"><?php echo $totalPush; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title">Último Push</div>
        <div class="stat-value" style="font-size:1rem;">
            <?php echo $ultimoPush ? date('d/m/Y H:i', strtotime($ultimoPush['enviado_en'])) : '—'; ?>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

    <!-- Formulario envío -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-paper-plane"></i> Enviar Push</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="accion" value="enviar">

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">
                        Título <span style="color:#ef4444">*</span>
                    </label>
                    <input type="text" name="titulo" maxlength="80" required
                           placeholder="Ej: Última hora — incendio en el centro"
                           value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>"
                           style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">
                        Mensaje <span style="color:#ef4444">*</span>
                        <span id="char-count" style="font-weight:400;color:#94a3b8;font-size:12px;">(200 restantes)</span>
                    </label>
                    <textarea name="mensaje" maxlength="200" required rows="4"
                              placeholder="Texto que verá el usuario en la notificación (máx. 200 caracteres)"
                              style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;resize:vertical;"><?php echo htmlspecialchars($_POST['mensaje'] ?? ''); ?></textarea>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px;">
                        URL al hacer clic en la notificación
                    </label>
                    <input type="url" name="url"
                           value="<?php echo htmlspecialchars($_POST['url'] ?? SITE_URL . '/'); ?>"
                           style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;">
                </div>

                <?php if ($totalSubs === 0): ?>
                <div style="background:#fef3c7;border:1px solid #fcd34d;padding:12px;border-radius:6px;margin-bottom:16px;font-size:13px;color:#92400e;">
                    <i class="fas fa-exclamation-triangle"></i>
                    No hay dispositivos suscritos. Los usuarios deben activar notificaciones en el sitio público.
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary" style="width:100%;"
                    <?php echo ($totalSubs === 0) ? 'disabled' : ''; ?>>
                    <i class="fas fa-bell"></i>
                    Enviar a <?php echo $totalSubs; ?> dispositivo(s)
                </button>
            </form>

            <!-- Info clave pública -->
            <details style="margin-top:20px;">
                <summary style="cursor:pointer;font-size:13px;color:#64748b;font-weight:600;">
                    Ver clave pública VAPID
                </summary>
                <div style="margin-top:10px;padding:12px;background:#f8fafc;border-radius:6px;font-size:11px;word-break:break-all;font-family:monospace;color:#475569;">
                    <?php echo htmlspecialchars(VAPID_PUBLIC_KEY); ?>
                </div>
                <p style="font-size:12px;color:#94a3b8;margin-top:6px;">
                    Esta clave debe coincidir con la usada en el frontend (se inyecta automáticamente).
                    <a href="setup-vapid.php" style="color:var(--color-primary);">Regenerar</a>
                </p>
            </details>
        </div>
    </div>

    <!-- Historial -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history"></i> Historial de Pushes</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($historial)): ?>
            <p style="padding:20px;color:#94a3b8;text-align:center;">Aún no se han enviado pushes.</p>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                            <th style="padding:10px 14px;text-align:left;font-weight:600;">Título / Mensaje</th>
                            <th style="padding:10px 14px;text-align:left;font-weight:600;white-space:nowrap;">Enviado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $h): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px 14px;">
                                <div style="font-weight:600;margin-bottom:2px;"><?php echo htmlspecialchars($h['titulo']); ?></div>
                                <div style="color:#64748b;font-size:12px;"><?php echo htmlspecialchars(mb_strimwidth($h['mensaje'], 0, 70, '…')); ?></div>
                            </td>
                            <td style="padding:10px 14px;white-space:nowrap;color:#64748b;">
                                <?php echo date('d/m H:i', strtotime($h['enviado_en'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Lista de suscriptores -->
<?php if (!empty($subs)): ?>
<div class="card" style="margin-top:24px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3 class="card-title"><i class="fas fa-mobile-alt"></i> Dispositivos Suscritos (últimos 25)</h3>
        <span style="font-size:13px;color:#94a3b8;"><?php echo $totalSubs; ?> total</span>
    </div>
    <div class="card-body" style="padding:0;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                        <th style="padding:10px 14px;text-align:left;">#</th>
                        <th style="padding:10px 14px;text-align:left;">Endpoint (parcial)</th>
                        <th style="padding:10px 14px;text-align:left;white-space:nowrap;">Suscrito</th>
                        <th style="padding:10px 14px;text-align:left;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subs as $i => $sub): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 14px;color:#94a3b8;"><?php echo $i + 1; ?></td>
                        <td style="padding:10px 14px;font-family:monospace;font-size:11px;color:#64748b;">
                            <?php echo htmlspecialchars($sub['ep_short']); ?>…
                        </td>
                        <td style="padding:10px 14px;white-space:nowrap;color:#64748b;">
                            <?php echo date('d/m/Y H:i', strtotime($sub['created_at'])); ?>
                        </td>
                        <td style="padding:10px 14px;">
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('¿Eliminar esta suscripción?')">
                                <input type="hidden" name="accion" value="eliminar_sub">
                                <input type="hidden" name="sub_id" value="<?php echo (int)$sub['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        style="padding:4px 10px;font-size:12px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
(function () {
    var ta      = document.querySelector('textarea[name="mensaje"]');
    var counter = document.getElementById('char-count');
    if (!ta || !counter) return;
    function update() {
        var rem = 200 - ta.value.length;
        counter.textContent = '(' + Math.max(0, rem) + ' restantes)';
        counter.style.color = rem < 20 ? '#ef4444' : '#94a3b8';
    }
    ta.addEventListener('input', update);
    update();
}());
</script>

<?php include 'includes/footer.php'; ?>
