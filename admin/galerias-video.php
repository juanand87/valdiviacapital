<?php
$page_title = 'Galerías de Video';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// ── Acciones POST ──────────────────────────────────────────────────────────
$accion = $_POST['accion'] ?? '';

if ($accion === 'guardar') {
    $id          = (int)($_POST['id'] ?? 0);
    $titulo      = htmlspecialchars(trim($_POST['titulo'] ?? ''), ENT_QUOTES, 'UTF-8');
    $slug        = trim($_POST['slug'] ?? '');
    if (!$slug) {
        // auto-generar slug
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $titulo)), '-'));
    }
    $descripcion    = htmlspecialchars(trim($_POST['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
    $imagen_portada = trim($_POST['imagen_portada'] ?? '');
    $categoria_id   = (int)($_POST['categoria_id'] ?? 0) ?: null;
    $activo         = isset($_POST['activo']) ? 1 : 0;
    $orden          = (int)($_POST['orden'] ?? 0);

    if (!$titulo) {
        $error = 'El título es obligatorio.';
    } else {
        if ($id) {
            $stmt = $db->prepare("UPDATE galerias_video SET titulo=?, slug=?, descripcion=?, imagen_portada=?, categoria_id=?, activo=?, orden=? WHERE id=?");
            $stmt->execute([$titulo, $slug, $descripcion, $imagen_portada, $categoria_id, $activo, $orden, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO galerias_video (titulo, slug, descripcion, imagen_portada, categoria_id, activo, orden) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$titulo, $slug, $descripcion, $imagen_portada, $categoria_id, $activo, $orden]);
            $id = $db->lastInsertId();
        }

        // Guardar videos asignados con su orden
        $db->prepare("DELETE FROM galerias_video_items WHERE galeria_id=?")->execute([$id]);
        $videosPost  = $_POST['videos']  ?? [];
        $ordenesPost = $_POST['vorden']  ?? [];
        if ($videosPost) {
            $stmtV = $db->prepare("INSERT IGNORE INTO galerias_video_items (galeria_id, video_id, orden) VALUES (?,?,?)");
            foreach ($videosPost as $vid) {
                $vid  = (int)$vid;
                $vord = (int)($ordenesPost[$vid] ?? 0);
                $stmtV->execute([$id, $vid, $vord]);
            }
        }

        $exito = 'Galería guardada correctamente.';
        // si venimos de editar, refrescar datos
        $editando = $db->prepare("SELECT * FROM galerias_video WHERE id=?");
        $editando->execute([$id]);
        $editando = $editando->fetch();
    }
}

if ($accion === 'destacar' && ($did = (int)($_POST['id'] ?? 0))) {
    $db->query("UPDATE galerias_video SET destacada = 0");
    $db->prepare("UPDATE galerias_video SET destacada = 1 WHERE id=?")->execute([$did]);
    $exito = 'Galería destacada en portada actualizada.';
}

if ($accion === 'quitar_destacada') {
    $db->query("UPDATE galerias_video SET destacada = 0");
    $exito = 'Portada multimedia volvió a usar videos individuales.';
}

if ($accion === 'eliminar' && ($del_id = (int)($_POST['id'] ?? 0))) {
    $db->prepare("DELETE FROM galerias_video_items WHERE galeria_id=?")->execute([$del_id]);
    $db->prepare("DELETE FROM galerias_video WHERE id=?")->execute([$del_id]);
    $exito = 'Galería eliminada.';
}

// ── Datos ──────────────────────────────────────────────────────────────────
$galerias = $db->query("
    SELECT g.*, c.nombre AS cat_nombre,
           (SELECT COUNT(*) FROM galerias_video_items gi WHERE gi.galeria_id = g.id) AS total_videos
    FROM galerias_video g
    LEFT JOIN categorias c ON c.id = g.categoria_id
    ORDER BY g.orden ASC, g.created_at DESC
")->fetchAll();

$todosVideos = $db->query("
    SELECT v.id, v.titulo, v.tipo, v.url, v.orden
    FROM videos v WHERE v.activo = 1 ORDER BY v.orden ASC, v.titulo ASC
")->fetchAll();

$categorias = $db->query("SELECT * FROM categorias WHERE activo=1 ORDER BY nombre")->fetchAll();

// Video en edición
$editando     = null;
$editVideos   = []; // [video_id => orden]
$editVideoIds = [];
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM galerias_video WHERE id=?");
    $stmt->execute([(int)$_GET['editar']]);
    $editando = $stmt->fetch();
    if ($editando) {
        $stmtEV = $db->prepare("SELECT video_id, orden FROM galerias_video_items WHERE galeria_id=? ORDER BY orden ASC");
        $stmtEV->execute([$editando['id']]);
        foreach ($stmtEV->fetchAll() as $row) {
            $editVideos[$row['video_id']] = $row['orden'];
        }
        $editVideoIds = array_keys($editVideos);
    }
}

// Destacada actual
$galeriaDestacada = $db->query("SELECT id FROM galerias_video WHERE destacada=1 LIMIT 1")->fetchColumn();
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-layer-group"></i> Galerías de Video</h1>
        <p class="page-subtitle">Agrupa videos y elige cuál aparece en la portada</p>
    </div>
</div>

<?php if (!empty($exito)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($exito) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Portada indicator -->
<div class="card" style="margin-bottom:20px;border-left:4px solid var(--color-primary);">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <div style="flex:1;">
            <strong><i class="fas fa-star" style="color:var(--color-primary);"></i> Portada Multimedia:</strong>
            <?php if ($galeriaDestacada): ?>
                <?php $gd = $db->query("SELECT titulo FROM galerias_video WHERE id=$galeriaDestacada")->fetch(); ?>
                <span style="color:var(--color-primary);font-weight:600;"><?= htmlspecialchars($gd['titulo']) ?></span>
                <small style="color:var(--color-gray);"> — Los videos de esta galería se muestran en la sección Multimedia del inicio.</small>
            <?php else: ?>
                <span style="color:var(--color-gray);">Videos individuales activos (ninguna galería destacada)</span>
            <?php endif; ?>
        </div>
        <?php if ($galeriaDestacada): ?>
        <form method="POST">
            <input type="hidden" name="accion" value="quitar_destacada">
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Quitar galería de portada</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr <?= $editando ? '420px' : '380px' ?>;gap:24px;align-items:start;">

    <!-- Lista de galerías -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> Galerías</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($galerias): ?>
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Galería</th>
                        <th>Videos</th>
                        <th>Categoría</th>
                        <th>Orden</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($galerias as $g): ?>
                    <tr <?= $g['destacada'] ? 'style="background:rgba(var(--color-primary-rgb,204,0,0),.06);"' : '' ?>>
                        <td>
                            <?php if ($g['imagen_portada']): ?>
                                <img src="<?= htmlspecialchars($g['imagen_portada']) ?>" style="width:48px;height:32px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px;" alt="">
                            <?php endif; ?>
                            <strong><?= htmlspecialchars($g['titulo']) ?></strong>
                            <?php if ($g['destacada']): ?>
                                <span style="margin-left:6px;font-size:11px;background:var(--color-primary);color:#fff;padding:1px 6px;border-radius:10px;">★ Portada</span>
                            <?php endif; ?>
                            <br><small style="color:var(--color-gray);font-size:11px;">/<strong><?= htmlspecialchars($g['slug']) ?></strong></small>
                        </td>
                        <td style="text-align:center;font-weight:600;"><?= (int)$g['total_videos'] ?></td>
                        <td><?= htmlspecialchars($g['cat_nombre'] ?? '—') ?></td>
                        <td style="text-align:center;"><?= (int)$g['orden'] ?></td>
                        <td>
                            <?php if ($g['activo']): ?>
                                <span class="badge badge-success">Activa</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactiva</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <!-- Destacar en portada -->
                            <?php if (!$g['destacada']): ?>
                            <form method="POST" style="display:inline;" title="Destacar en portada">
                                <input type="hidden" name="accion" value="destacar">
                                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="background:#f59e0b;color:#fff;border:none;" title="Poner en portada">
                                    <i class="fas fa-star"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <!-- Editar -->
                            <a href="galerias-video.php?editar=<?= $g['id'] ?>" class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <!-- Eliminar -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar esta galería?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $g['id'] ?>">
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
                <p style="padding:24px;color:var(--color-gray);text-align:center;"><i class="fas fa-layer-group" style="font-size:32px;display:block;margin-bottom:8px;"></i>No hay galerías aún. Crea la primera.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulario galería -->
    <div>
        <div class="card" style="position:sticky;top:24px;">
            <div class="card-header">
                <h3 class="card-title">
                    <?= $editando ? '<i class="fas fa-edit"></i> Editar galería' : '<i class="fas fa-plus"></i> Nueva galería' ?>
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" action="galerias-video.php">
                    <input type="hidden" name="accion" value="guardar">
                    <?php if ($editando): ?>
                        <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" id="inp-titulo" class="form-control" required
                               value="<?= htmlspecialchars($editando['titulo'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Slug
                            <small style="font-weight:400;color:var(--color-gray);">(auto si vacío)</small>
                        </label>
                        <input type="text" name="slug" id="inp-slug" class="form-control"
                               placeholder="mi-galeria-de-videos"
                               value="<?= htmlspecialchars($editando['slug'] ?? '') ?>">
                        <small style="color:var(--color-gray);">Uso en shortcode: <code>[galeria slug="<?= htmlspecialchars($editando['slug'] ?? 'slug-aqui') ?>"]</code></small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2"><?= htmlspecialchars($editando['descripcion'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Imagen de portada (URL)</label>
                        <input type="url" name="imagen_portada" class="form-control" id="inp-portada"
                               placeholder="https://..."
                               value="<?= htmlspecialchars($editando['imagen_portada'] ?? '') ?>">
                        <div id="portada-preview" style="margin-top:6px;"></div>
                    </div>

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
                                    <span>Activa</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Videos incluidos en la galería -->
                    <?php if ($todosVideos): ?>
                    <div class="form-group">
                        <label class="form-label">Videos en esta galería</label>
                        <div style="max-height:220px;overflow-y:auto;border:1px solid var(--border-color);border-radius:6px;padding:6px;">
                            <?php foreach ($todosVideos as $tv): ?>
                            <?php
                                $checked = in_array($tv['id'], $editVideoIds);
                                $vorden  = $editVideos[$tv['id']] ?? 0;
                            ?>
                            <label style="display:grid;grid-template-columns:auto 1fr 52px;align-items:center;gap:8px;padding:4px 2px;font-size:13px;cursor:pointer;border-bottom:1px solid var(--border-color);">
                                <input type="checkbox" name="videos[]" value="<?= $tv['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                                <span>
                                    <?php if ($tv['tipo'] === 'youtube'): ?>
                                        <i class="fab fa-youtube" style="color:#ff0000;"></i>
                                    <?php else: ?>
                                        <i class="fab fa-facebook" style="color:#1877f2;"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($tv['titulo']) ?>
                                </span>
                                <input type="number" name="vorden[<?= $tv['id'] ?>]" value="<?= $vorden ?>"
                                       min="0" max="99" title="Orden"
                                       style="width:48px;padding:2px 4px;font-size:12px;text-align:center;border:1px solid var(--border-color);border-radius:4px;">
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <small style="color:var(--color-gray);">Marca los videos e indica el orden (0 = primero).</small>
                    </div>
                    <?php endif; ?>

                    <div style="display:flex;gap:10px;margin-top:4px;">
                        <button type="submit" class="btn btn-primary" style="flex:1;">
                            <i class="fas fa-save"></i>
                            <?= $editando ? 'Actualizar galería' : 'Guardar galería' ?>
                        </button>
                        <?php if ($editando): ?>
                            <a href="galerias-video.php" class="btn btn-secondary"><i class="fas fa-plus"></i> Nueva</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div><!-- /grid -->

<script>
(function () {
    // Auto-slug desde título (solo si slug está vacío)
    var inp = document.getElementById('inp-titulo');
    var slugInp = document.getElementById('inp-slug');
    if (inp && slugInp) {
        inp.addEventListener('input', function () {
            if (slugInp.value.trim() !== '') return; // no sobreescribir si el usuario ya puso uno
            var s = this.value.toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            slugInp.placeholder = s || 'mi-galeria';
        });
    }

    // Preview imagen portada
    var inpP = document.getElementById('inp-portada');
    var prevP = document.getElementById('portada-preview');
    if (inpP && prevP) {
        function showPreview() {
            var u = inpP.value.trim();
            prevP.innerHTML = u
                ? '<img src="' + u + '" style="max-width:100%;height:60px;object-fit:cover;border-radius:6px;" onerror="this.style.display=\'none\'">'
                : '';
        }
        inpP.addEventListener('input', showPreview);
        showPreview();
    }
})();
</script>
