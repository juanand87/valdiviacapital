<?php
$page_title = 'Ticker Breaking News';
require_once '../includes/config.php';
require_once 'includes/auth.php';
verificarPermiso('editor');

$db  = getDB();
$msg = '';
$err = '';

// ── Asegurar que las tablas existen ──────────────────────────────────────────
try {
    $db->query("SELECT 1 FROM ticker_config LIMIT 1");
} catch (\PDOException $e) {
    // Crear tablas on-the-fly si aún no se ejecutó ticker.sql
    $db->exec("
        CREATE TABLE IF NOT EXISTS ticker_mensajes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mensaje VARCHAR(400) NOT NULL,
            url VARCHAR(500) NULL,
            tipo ENUM('normal','urgente','flash') NOT NULL DEFAULT 'normal',
            activo TINYINT(1) NOT NULL DEFAULT 1,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ticker_activo_orden (activo, orden)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS ticker_config (
            nombre VARCHAR(60) NOT NULL PRIMARY KEY,
            valor  TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $db->exec("INSERT IGNORE INTO ticker_config (nombre, valor) VALUES
        ('activo','1'),('etiqueta','Último momento'),
        ('velocidad','35'),('fuente','noticias'),('cantidad_noticias','8')");
}

// ── Leer configuración ────────────────────────────────────────────────────────
$cfgRaw = $db->query("SELECT nombre, valor FROM ticker_config")->fetchAll();
$cfg    = [];
foreach ($cfgRaw as $r) $cfg[$r['nombre']] = $r['valor'];
$cfg += ['activo'=>'1','etiqueta'=>'Último momento','velocidad'=>'35','fuente'=>'noticias','cantidad_noticias'=>'8'];

// ── Acciones POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // Guardar configuración global
    if ($accion === 'guardar_config') {
        $campos = ['activo','etiqueta','velocidad','fuente','cantidad_noticias'];
        foreach ($campos as $campo) {
            $val = $_POST[$campo] ?? '';
            if ($campo === 'activo')            $val = isset($_POST['activo']) ? '1' : '0';
            if ($campo === 'velocidad')         $val = max(10, min(120, (int)$val));
            if ($campo === 'cantidad_noticias') $val = max(1,  min(20,  (int)$val));
            $db->prepare("INSERT INTO ticker_config (nombre,valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")
               ->execute([$campo, $val]);
        }
        $msg = 'Configuración guardada correctamente.';
        // Recargar cfg
        $cfgRaw = $db->query("SELECT nombre, valor FROM ticker_config")->fetchAll();
        $cfg = [];
        foreach ($cfgRaw as $r) $cfg[$r['nombre']] = $r['valor'];

    // Agregar nuevo mensaje
    } elseif ($accion === 'agregar') {
        $mensaje = trim($_POST['mensaje'] ?? '');
        $url     = trim($_POST['url']     ?? '');
        $tipo    = $_POST['tipo'] ?? 'normal';
        if (!in_array($tipo, ['normal','urgente','flash'])) $tipo = 'normal';

        if ($mensaje === '') {
            $err = 'El mensaje no puede estar vacío.';
        } elseif (mb_strlen($mensaje) > 400) {
            $err = 'El mensaje no puede superar 400 caracteres.';
        } else {
            $maxOrden = (int)($db->query("SELECT COALESCE(MAX(orden),0) FROM ticker_mensajes")->fetchColumn());
            $db->prepare("INSERT INTO ticker_mensajes (mensaje, url, tipo, activo, orden) VALUES (?,?,?,1,?)")
               ->execute([$mensaje, $url ?: null, $tipo, $maxOrden + 1]);
            $msg = 'Mensaje agregado.';
        }

    // Toggle activo
    } elseif ($accion === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE ticker_mensajes SET activo = 1 - activo WHERE id = ?")->execute([$id]);
        }
        header('Location: ticker.php'); exit;

    // Subir orden
    } elseif ($accion === 'subir') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $cur = $db->prepare("SELECT orden FROM ticker_mensajes WHERE id=?");
            $cur->execute([$id]);
            $orden = (int)($cur->fetchColumn());
            if ($orden > 0) {
                // Swap con el anterior
                $prev = $db->prepare("SELECT id FROM ticker_mensajes WHERE orden < ? ORDER BY orden DESC LIMIT 1");
                $prev->execute([$orden]);
                $prevId = $prev->fetchColumn();
                if ($prevId) {
                    $prevOrden = (int)($db->prepare("SELECT orden FROM ticker_mensajes WHERE id=?")->execute([$prevId]) && $db->query("SELECT orden FROM ticker_mensajes WHERE id=$prevId")->fetchColumn());
                    $db->prepare("UPDATE ticker_mensajes SET orden=? WHERE id=?")->execute([$orden-1, $id]);
                    $db->prepare("UPDATE ticker_mensajes SET orden=? WHERE id=?")->execute([$orden,   $prevId]);
                }
            }
        }
        header('Location: ticker.php'); exit;

    // Bajar orden
    } elseif ($accion === 'bajar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $cur = $db->prepare("SELECT orden FROM ticker_mensajes WHERE id=?");
            $cur->execute([$id]);
            $orden = (int)($cur->fetchColumn());
            $next = $db->prepare("SELECT id, orden FROM ticker_mensajes WHERE orden > ? ORDER BY orden ASC LIMIT 1");
            $next->execute([$orden]);
            $nextRow = $next->fetch();
            if ($nextRow) {
                $db->prepare("UPDATE ticker_mensajes SET orden=? WHERE id=?")->execute([$nextRow['orden'], $id]);
                $db->prepare("UPDATE ticker_mensajes SET orden=? WHERE id=?")->execute([$orden, $nextRow['id']]);
            }
        }
        header('Location: ticker.php'); exit;

    // Eliminar
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM ticker_mensajes WHERE id=?")->execute([$id]);
            $msg = 'Mensaje eliminado.';
        }
    }
}

// ── Leer mensajes ─────────────────────────────────────────────────────────────
$mensajes = $db->query("SELECT * FROM ticker_mensajes ORDER BY orden ASC, id ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-scroll"></i> Ticker Breaking News</h1>
        <p>Configura la barra de titulares en la portada del sitio</p>
    </div>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<!-- ── CONFIGURACIÓN GLOBAL ─────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h2><i class="fas fa-sliders-h"></i> Configuración global</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="accion" value="guardar_config">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;">

                <div class="form-group" style="margin:0;">
                    <label>Estado del ticker</label>
                    <label class="toggle-switch" style="margin-top:8px;display:flex;align-items:center;gap:12px;cursor:pointer;">
                        <input type="checkbox" name="activo" value="1" <?= $cfg['activo'] == '1' ? 'checked' : '' ?> style="width:auto;">
                        <span>Activo en portada</span>
                    </label>
                </div>

                <div class="form-group" style="margin:0;">
                    <label for="etiqueta">Etiqueta del ticker</label>
                    <input type="text" id="etiqueta" name="etiqueta" class="form-control"
                           value="<?= htmlspecialchars($cfg['etiqueta']) ?>" maxlength="40"
                           placeholder="Ej: Último momento, URGENTE, FLASH">
                    <small class="form-hint">Texto del botón rojo a la izquierda</small>
                </div>

                <div class="form-group" style="margin:0;">
                    <label for="velocidad">Velocidad <span id="vel-label"><?= (int)$cfg['velocidad'] ?>s</span></label>
                    <input type="range" id="velocidad" name="velocidad" min="10" max="90" step="5"
                           value="<?= (int)$cfg['velocidad'] ?>"
                           oninput="document.getElementById('vel-label').textContent=this.value+'s'"
                           style="width:100%;margin-top:8px;">
                    <small class="form-hint">Tiempo para dar una vuelta completa (10s = rápido, 90s = lento)</small>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <div class="form-group" style="margin:0;">
                    <label>Fuente de contenido</label>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;">
                            <input type="radio" name="fuente" value="noticias" <?= $cfg['fuente']==='noticias' ? 'checked' : '' ?>>
                            <span><strong>Noticias recientes</strong> — últimos títulos publicados</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;">
                            <input type="radio" name="fuente" value="personalizado" <?= $cfg['fuente']==='personalizado' ? 'checked' : '' ?>>
                            <span><strong>Mensajes personalizados</strong> — solo los de abajo</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;">
                            <input type="radio" name="fuente" value="ambos" <?= $cfg['fuente']==='ambos' ? 'checked' : '' ?>>
                            <span><strong>Ambos</strong> — personalizados primero, luego noticias</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin:0;" id="wrap-cantidad">
                    <label for="cantidad_noticias">Cantidad de noticias a mostrar</label>
                    <input type="number" id="cantidad_noticias" name="cantidad_noticias" class="form-control"
                           value="<?= (int)$cfg['cantidad_noticias'] ?>" min="1" max="20"
                           style="width:120px;">
                    <small class="form-hint">Solo aplica cuando la fuente incluye noticias recientes</small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar configuración</button>
        </form>
    </div>
</div>

<!-- ── PREVIEW ───────────────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h2><i class="fas fa-eye"></i> Vista previa del ticker</h2>
        <small style="color:var(--text-muted);">Solo muestra mensajes personalizados activos</small>
    </div>
    <div class="card-body" style="padding:0;overflow:hidden;">
        <div style="background:#fff;border:1px solid var(--border-color);display:flex;align-items:center;overflow:hidden;padding:8px 0;">
            <span style="background:var(--color-primary,#e63946);color:#fff;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;padding:5px 14px;border-radius:4px;white-space:nowrap;margin:0 16px;flex-shrink:0;">
                <i class="fas fa-bolt"></i> <?= htmlspecialchars($cfg['etiqueta']) ?>
            </span>
            <div style="flex:1;overflow:hidden;">
                <?php $activos = array_filter($mensajes, fn($m) => $m['activo']); ?>
                <?php if ($activos): ?>
                <div style="display:flex;gap:60px;white-space:nowrap;animation:ticker <?= (int)$cfg['velocidad'] ?>s linear infinite;">
                    <?php foreach ($activos as $m): ?>
                        <span style="font-size:13px;<?= $m['tipo']==='urgente' ? 'color:#dc2626;font-weight:600;' : ($m['tipo']==='flash' ? 'color:#f59e0b;font-weight:600;' : '') ?>">
                            <?php if ($m['tipo'] === 'urgente'): ?><strong style="color:#dc2626;">URGENTE: </strong><?php endif; ?>
                            <?php if ($m['tipo'] === 'flash'):   ?><strong style="color:#f59e0b;">FLASH: </strong><?php endif; ?>
                            <?= htmlspecialchars($m['mensaje']) ?>
                        </span>
                        <span style="color:var(--color-primary,#e63946);font-weight:700;">&bull;</span>
                    <?php endforeach; ?>
                    <?php foreach ($activos as $m): ?>
                        <span style="font-size:13px;<?= $m['tipo']==='urgente' ? 'color:#dc2626;font-weight:600;' : ($m['tipo']==='flash' ? 'color:#f59e0b;font-weight:600;' : '') ?>">
                            <?= htmlspecialchars($m['mensaje']) ?>
                        </span>
                        <span style="color:var(--color-primary,#e63946);font-weight:700;">&bull;</span>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <span style="font-size:13px;color:#888;padding:0 20px;">Sin mensajes personalizados activos — se mostrarán noticias recientes</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── MENSAJES PERSONALIZADOS ──────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-list"></i> Mensajes personalizados (<?= count($mensajes) ?>)</h2>
    </div>
    <div class="card-body">

        <!-- Agregar nuevo -->
        <form method="POST" style="background:var(--bg-secondary,#f8f9fa);padding:20px;border-radius:8px;margin-bottom:24px;border:1px solid var(--border-color);">
            <input type="hidden" name="accion" value="agregar">
            <h3 style="margin:0 0 16px;font-size:15px;"><i class="fas fa-plus-circle"></i> Agregar nuevo mensaje</h3>
            <div style="display:grid;grid-template-columns:1fr auto auto;gap:12px;align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label for="nuevo_mensaje">Mensaje <span style="color:#e63946;">*</span></label>
                    <input type="text" id="nuevo_mensaje" name="mensaje" class="form-control"
                           placeholder="Texto que aparecerá en el ticker..." maxlength="400" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="nuevo_tipo">Tipo</label>
                    <select id="nuevo_tipo" name="tipo" class="form-control">
                        <option value="normal">Normal</option>
                        <option value="urgente">🔴 Urgente</option>
                        <option value="flash">🟡 Flash</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="align-self:flex-end;">
                    <i class="fas fa-plus"></i> Agregar
                </button>
            </div>
            <div class="form-group" style="margin:12px 0 0;">
                <label for="nuevo_url">URL del enlace <small style="font-weight:400;">(opcional — si se agrega, el item será un link)</small></label>
                <input type="url" id="nuevo_url" name="url" class="form-control"
                       placeholder="https://valdiviacapital.cl/noticia.php?slug=..." >
            </div>
        </form>

        <!-- Tabla de mensajes -->
        <?php if ($mensajes): ?>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:50px;">Orden</th>
                        <th style="width:90px;">Tipo</th>
                        <th>Mensaje</th>
                        <th style="width:120px;">URL</th>
                        <th style="width:80px;">Estado</th>
                        <th style="width:160px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mensajes as $m): ?>
                    <tr style="<?= !$m['activo'] ? 'opacity:0.5;' : '' ?>">
                        <td>
                            <div style="display:flex;gap:4px;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accion" value="subir">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding:3px 7px;font-size:11px;" title="Subir">
                                        <i class="fas fa-arrow-up"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accion" value="bajar">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding:3px 7px;font-size:11px;" title="Bajar">
                                        <i class="fas fa-arrow-down"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                        <td>
                            <?php if ($m['tipo'] === 'urgente'): ?>
                                <span class="badge badge-danger" style="background:#dc2626;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;">URGENTE</span>
                            <?php elseif ($m['tipo'] === 'flash'): ?>
                                <span class="badge badge-warning" style="background:#f59e0b;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;">FLASH</span>
                            <?php else: ?>
                                <span class="badge badge-secondary" style="background:#6b7280;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;">Normal</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:320px;">
                            <span title="<?= htmlspecialchars($m['mensaje']) ?>">
                                <?= htmlspecialchars(mb_strlen($m['mensaje']) > 80 ? mb_substr($m['mensaje'],0,80).'…' : $m['mensaje']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($m['url']): ?>
                                <a href="<?= htmlspecialchars($m['url']) ?>" target="_blank" title="<?= htmlspecialchars($m['url']) ?>" style="font-size:12px;">
                                    <i class="fas fa-external-link-alt"></i> Ver
                                </a>
                            <?php else: ?>
                                <span style="color:#aaa;font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="accion" value="toggle">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn <?= $m['activo'] ? 'btn-success' : 'btn-secondary' ?>" style="padding:3px 10px;font-size:12px;">
                                    <?= $m['activo'] ? '<i class="fas fa-eye"></i> Activo' : '<i class="fas fa-eye-slash"></i> Oculto' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este mensaje del ticker?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-danger" style="padding:3px 10px;font-size:12px;">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div style="text-align:center;padding:40px 20px;color:#888;">
                <i class="fas fa-scroll" style="font-size:2rem;margin-bottom:12px;display:block;opacity:0.3;"></i>
                <p>No hay mensajes personalizados aún.</p>
                <p style="font-size:13px;">Agrega un mensaje usando el formulario de arriba.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:20px;border-left:4px solid var(--color-primary,#e63946);">
    <div class="card-body" style="padding:16px 20px;">
        <h4 style="margin:0 0 8px;font-size:14px;"><i class="fas fa-info-circle"></i> Tipos de mensaje</h4>
        <ul style="margin:0;padding-left:20px;font-size:13px;line-height:1.8;color:var(--text-muted);">
            <li><strong>Normal</strong> — título estándar en gris oscuro</li>
            <li><strong style="color:#dc2626;">Urgente</strong> — texto en rojo con prefijo "URGENTE:" para noticias de última hora</li>
            <li><strong style="color:#f59e0b;">Flash</strong> — texto en ámbar con prefijo "FLASH:" para avisos rápidos</li>
        </ul>
    </div>
</div>

<style>
@keyframes ticker { 0% { transform:translateX(0); } 100% { transform:translateX(-50%); } }
</style>

<?php include 'includes/footer.php'; ?>
