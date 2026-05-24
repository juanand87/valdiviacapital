<?php
$page_title = 'Gestión de Videos';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();
$tieneEsReel = false;
try {
    $tieneEsReel = (bool)$db->query("SHOW COLUMNS FROM videos LIKE 'es_reel'")->fetch();
} catch (\Exception $e) {
    $tieneEsReel = false;
}

// ── Helpers ────────────────────────────────────────────────────────────────
function extractYoutubeId(string $url): ?string {
    preg_match('/(?:v=|youtu\.be\/|embed\/|shorts\/)([a-zA-Z0-9_\-]{11})/', $url, $m);
    return $m[1] ?? null;
}

function buildEmbedUrl(string $url, string $tipo): string {
    if ($tipo === 'youtube') {
        $id = extractYoutubeId($url);
        return $id ? "https://www.youtube.com/embed/{$id}?rel=0" : $url;
    }
    // Facebook
    return "https://www.facebook.com/plugins/video.php?href=" . urlencode($url) . "&show_text=false&width=640";
}

function buildThumbnail(string $url, string $tipo): string {
    if ($tipo === 'youtube') {
        $id = extractYoutubeId($url);
        return $id ? "https://img.youtube.com/vi/{$id}/hqdefault.jpg" : '';
    }
    return ''; // Facebook no tiene thumbnail fiable via URL
}

// ── Acciones AJAX / POST ───────────────────────────────────────────────────
$accion = $_POST['accion'] ?? '';

if ($accion === 'guardar') {
    $id          = (int)($_POST['id'] ?? 0);
    $titulo      = htmlspecialchars(trim($_POST['titulo'] ?? ''), ENT_QUOTES, 'UTF-8');
    $url         = trim($_POST['url'] ?? '');
    $tipo        = in_array($_POST['tipo'] ?? '', ['youtube','facebook']) ? $_POST['tipo'] : 'youtube';
    $descripcion = htmlspecialchars(trim($_POST['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
    $categoria_id= (int)($_POST['categoria_id'] ?? 0) ?: null;
    $activo      = isset($_POST['activo']) ? 1 : 0;
    $orden       = (int)($_POST['orden'] ?? 0);
    $es_reel     = isset($_POST['es_reel']) ? 1 : 0;

    if (!$titulo || !$url) {
        $error = 'Título y URL son obligatorios.';
    } else {
        if ($id) {
            if ($tieneEsReel) {
                $stmt = $db->prepare("UPDATE videos SET titulo=?,url=?,tipo=?,es_reel=?,descripcion=?,categoria_id=?,activo=?,orden=? WHERE id=?");
                $stmt->execute([$titulo, $url, $tipo, $es_reel, $descripcion, $categoria_id, $activo, $orden, $id]);
            } else {
                $stmt = $db->prepare("UPDATE videos SET titulo=?,url=?,tipo=?,descripcion=?,categoria_id=?,activo=?,orden=? WHERE id=?");
                $stmt->execute([$titulo, $url, $tipo, $descripcion, $categoria_id, $activo, $orden, $id]);
            }
        } else {
            if ($tieneEsReel) {
                $stmt = $db->prepare("INSERT INTO videos (titulo,url,tipo,es_reel,descripcion,categoria_id,activo,orden) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$titulo, $url, $tipo, $es_reel, $descripcion, $categoria_id, $activo, $orden]);
            } else {
                $stmt = $db->prepare("INSERT INTO videos (titulo,url,tipo,descripcion,categoria_id,activo,orden) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$titulo, $url, $tipo, $descripcion, $categoria_id, $activo, $orden]);
            }
            $id = $db->lastInsertId();
        }

        // Comunas
        $db->prepare("DELETE FROM videos_comunas WHERE video_id=?")->execute([$id]);
        $comunasPost = array_filter(array_map('intval', $_POST['comunas'] ?? []));
        if ($comunasPost) {
            $stmtC = $db->prepare("INSERT IGNORE INTO videos_comunas (video_id, comuna_id) VALUES (?,?)");
            foreach ($comunasPost as $cid) $stmtC->execute([$id, $cid]);
        }

        $exito = 'Video guardado correctamente.';
    }
}

if ($accion === 'eliminar' && ($del_id = (int)($_POST['id'] ?? 0))) {
    $db->prepare("DELETE FROM videos_comunas WHERE video_id=?")->execute([$del_id]);
    $db->prepare("DELETE FROM videos WHERE id=?")->execute([$del_id]);
    $exito = 'Video eliminado.';
}

// ── Cargar datos ───────────────────────────────────────────────────────────
$videos    = $db->query("
    SELECT v.*, c.nombre AS cat_nombre
    FROM videos v
    LEFT JOIN categorias c ON c.id = v.categoria_id
    ORDER BY v.orden ASC, v.created_at DESC
")->fetchAll();

// Comunas por video
$vcMap = [];
if ($videos) {
    $vids = implode(',', array_column($videos, 'id'));
    $rows = $db->query("SELECT vc.video_id, com.nombre FROM videos_comunas vc INNER JOIN comunas com ON com.id = vc.comuna_id WHERE vc.video_id IN ($vids)")->fetchAll();
    foreach ($rows as $r) $vcMap[$r['video_id']][] = $r['nombre'];
}

$categorias = $db->query("SELECT * FROM categorias WHERE activo=1 ORDER BY nombre")->fetchAll();
$comunas    = $db->query("SELECT * FROM comunas ORDER BY nombre")->fetchAll();

// Video a editar
$editando = null;
$editComunas = [];
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM videos WHERE id=?");
    $stmt->execute([(int)$_GET['editar']]);
    $editando = $stmt->fetch();
    if ($editando) {
        $stmtC = $db->prepare("SELECT comuna_id FROM videos_comunas WHERE video_id=?");
        $stmtC->execute([$editando['id']]);
        $editComunas = $stmtC->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-film"></i> Gestión de Videos</h1>
        <p class="page-subtitle">Administra los videos multimedia del portal</p>
    </div>
</div>

<?php if (!empty($exito)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($exito) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 400px;gap:24px;align-items:start;">

    <!-- Lista de videos -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-list"></i> Videos registrados</h3></div>
        <div class="card-body" style="padding:0;">
            <?php if ($videos): ?>
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th style="width:64px;">Preview</th>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Formato</th>
                        <th>Categoría</th>
                        <th>Comunas</th>
                        <th>Orden</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($videos as $v): ?>
                    <?php $thumb = buildThumbnail($v['url'], $v['tipo']); ?>
                    <tr>
                        <td>
                            <?php if ($thumb): ?>
                                <img src="<?= htmlspecialchars($thumb) ?>" alt="" style="width:56px;height:36px;object-fit:cover;border-radius:4px;">
                            <?php else: ?>
                                <div style="width:56px;height:36px;background:#1877f2;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                                    <i class="fab fa-facebook" style="color:#fff;font-size:18px;"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:200px;">
                            <strong><?= htmlspecialchars($v['titulo']) ?></strong>
                        </td>
                        <td>
                            <?php if ($v['tipo'] === 'youtube'): ?>
                                <span style="background:#ff0000;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;"><i class="fab fa-youtube"></i> YouTube</span>
                            <?php else: ?>
                                <span style="background:#1877f2;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;"><i class="fab fa-facebook"></i> Facebook</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($v['es_reel'])): ?>
                                <span style="background:#f97316;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;letter-spacing:.4px;">REEL</span>
                            <?php else: ?>
                                <span style="background:#64748b;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;letter-spacing:.4px;">VIDEO</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($v['cat_nombre'] ?? '—') ?></td>
                        <td style="font-size:12px;color:var(--color-gray);">
                            <?= htmlspecialchars(implode(', ', $vcMap[$v['id']] ?? [])) ?: '—' ?>
                        </td>
                        <td style="text-align:center;"><?= (int)$v['orden'] ?></td>
                        <td>
                            <?php if ($v['activo']): ?>
                                <span class="badge badge-success">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="videos.php?editar=<?= $v['id'] ?>" class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este video?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:24px;color:var(--color-gray);text-align:center;"><i class="fas fa-film" style="font-size:32px;display:block;margin-bottom:8px;"></i>No hay videos registrados.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulario -->
    <div class="card" style="position:sticky;top:24px;">
        <div class="card-header">
            <h3 class="card-title">
                <?= $editando ? '<i class="fas fa-edit"></i> Editar video' : '<i class="fas fa-plus"></i> Nuevo video' ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="videos.php">
                <input type="hidden" name="accion" value="guardar">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Título *</label>
                    <input type="text" name="titulo" class="form-control" required
                           value="<?= htmlspecialchars($editando['titulo'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Plataforma *</label>
                    <select name="tipo" class="form-control" id="sel-tipo">
                        <option value="youtube" <?= ($editando['tipo'] ?? '') === 'youtube' ? 'selected' : '' ?>>YouTube</option>
                        <option value="facebook" <?= ($editando['tipo'] ?? '') === 'facebook' ? 'selected' : '' ?>>Facebook</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">URL del video *</label>
                    <input type="url" name="url" class="form-control" required
                           id="inp-url"
                           placeholder="https://www.youtube.com/watch?v=..."
                           value="<?= htmlspecialchars($editando['url'] ?? '') ?>">
                    <small id="thumb-preview-wrap" style="display:block;margin-top:8px;"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2"><?= htmlspecialchars($editando['descripcion'] ?? '') ?></textarea>
                </div>

                <?php if ($tieneEsReel): ?>
                <div class="form-group">
                    <label class="form-label">Formato</label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="es_reel" <?= !empty($editando['es_reel']) ? 'checked' : '' ?>>
                        <span>Marcar como Reel</span>
                    </label>
                    <small style="color:var(--color-gray);">El tercer destacado en portada prioriza videos marcados como Reel.</small>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Categoría</label>
                    <select name="categoria_id" class="form-control">
                        <option value="">— Sin categoría —</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($editando['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Comunas relacionadas</label>
                    <div style="max-height:130px;overflow-y:auto;border:1px solid var(--border-color);border-radius:6px;padding:8px;">
                        <?php foreach ($comunas as $com): ?>
                            <label style="display:flex;align-items:center;gap:8px;padding:3px 0;font-size:13px;cursor:pointer;">
                                <input type="checkbox" name="comunas[]" value="<?= $com['id'] ?>"
                                       <?= in_array($com['id'], $editComunas) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($com['nombre']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Orden <small>(menor = primero)</small></label>
                        <input type="number" name="orden" class="form-control" min="0" max="99"
                               value="<?= (int)($editando['orden'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <div style="padding-top:10px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="activo" <?= ($editando['activo'] ?? 1) ? 'checked' : '' ?>>
                                <span>Activo</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:4px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">
                        <i class="fas fa-save"></i>
                        <?= $editando ? 'Actualizar' : 'Guardar video' ?>
                    </button>
                    <?php if ($editando): ?>
                        <a href="videos.php" class="btn btn-secondary"><i class="fas fa-plus"></i> Nuevo</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div><!-- /formulario -->

</div>

<script>
(function () {
    var inpUrl   = document.getElementById('inp-url');
    var selTipo  = document.getElementById('sel-tipo');
    var preview  = document.getElementById('thumb-preview-wrap');

    function updatePreview() {
        var url  = inpUrl.value.trim();
        var tipo = selTipo.value;
        if (tipo === 'youtube' && url) {
            var m = url.match(/(?:v=|youtu\.be\/|embed\/|shorts\/)([a-zA-Z0-9_\-]{11})/);
            if (m) {
                preview.innerHTML = '<img src="https://img.youtube.com/vi/' + m[1] + '/hqdefault.jpg" style="width:100%;max-width:280px;border-radius:6px;margin-top:4px;">';
                return;
            }
        }
        preview.innerHTML = '';
    }

    inpUrl.addEventListener('input', updatePreview);
    selTipo.addEventListener('change', function () {
        inpUrl.placeholder = this.value === 'youtube'
            ? 'https://www.youtube.com/watch?v=...'
            : 'https://www.facebook.com/watch/?v=...';
        updatePreview();
    });

    updatePreview(); // init on edit
})();
</script>
