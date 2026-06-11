<?php
$page_title = 'Logos Portada';
require_once '../includes/config.php';
include 'includes/header.php';

$db    = getDB();

// ── Eliminar ──────────────────────────────────────────────────────────────
if (isset($_GET['eliminar'])) {
    $lid = (int)$_GET['eliminar'];
    if ($lid > 0) {
        $db->prepare("DELETE FROM logos_portada WHERE id = ?")->execute([$lid]);
        $mensaje = 'Logo eliminado correctamente';
    }
}

// ── Guardar / Actualizar ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lid         = (int)($_POST['id'] ?? 0);
    $nombre      = trim(clean($_POST['nombre']      ?? ''));
    $imagen_url  = trim(clean($_POST['imagen_url']  ?? ''));
    $url_destino = trim(clean($_POST['url_destino'] ?? ''));
    $activo      = isset($_POST['activo']) ? 1 : 0;
    $orden       = max(0, (int)($_POST['orden'] ?? 0));

    if (!$nombre || !$imagen_url) {
        $error = 'Completa los campos obligatorios (Nombre e Imagen).';
    } else {
        try {
            if ($lid) {
                $db->prepare("
                    UPDATE logos_portada
                    SET nombre = ?, imagen_url = ?, url_destino = ?, activo = ?, orden = ?
                    WHERE id = ?
                ")->execute([$nombre, $imagen_url, $url_destino, $activo, $orden, $lid]);
                $mensaje = 'Logo actualizado correctamente';
            } else {
                $db->prepare("
                    INSERT INTO logos_portada (nombre, imagen_url, url_destino, activo, orden)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$nombre, $imagen_url, $url_destino, $activo, $orden]);
                $mensaje = 'Logo creado correctamente';
            }
            $lid = 0; // Limpiar formulario
        } catch (PDOException $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

// Cargar para editar
$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM logos_portada WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editando = $stmt->fetch();
}

// Lista completa
$logos = $db->query("SELECT * FROM logos_portada ORDER BY orden ASC, id DESC")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Logos Portada</h1>
        <p class="page-subtitle">Gestiona los logos del carrusel en la portada (sobre "Comunas, Cobertura local")</p>
    </div>
</div>

<?php if (isset($mensaje)): ?>
<div style="background:#d1fae5;color:#065f46;padding:15px;border-radius:8px;margin-bottom:20px;">
    <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
</div>
<?php endif; ?>
<?php if (isset($error)): ?>
<div style="background:#fee;color:#c53030;padding:15px;border-radius:8px;margin-bottom:20px;">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:380px 1fr;gap:24px;align-items:start;">

    <!-- Formulario -->
    <div class="card">
        <div class="card-header" style="background:#f7fafc;">
            <h3 class="card-title" style="font-size:15px;">
                <i class="fas fa-<?php echo $editando ? 'edit' : 'plus-circle'; ?>"></i>
                <?php echo $editando ? 'Editar Logo' : 'Nuevo Logo'; ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$editando['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Nombre interno *</label>
                    <input type="text" name="nombre" class="form-control" required maxlength="150"
                           value="<?php echo $editando ? htmlspecialchars($editando['nombre']) : ''; ?>"
                           placeholder="Ej: Empresa XYZ">
                </div>

                <div class="form-group">
                    <label class="form-label">Imagen del logo *</label>
                    <div style="display:flex;gap:8px;margin-bottom:8px;">
                        <input type="url" name="imagen_url" id="imagen_url" class="form-control" required
                               value="<?php echo $editando ? htmlspecialchars($editando['imagen_url']) : ''; ?>"
                               placeholder="https://ejemplo.com/logo.png o selecciona de Medios"
                               onchange="document.getElementById('img-preview').src=this.value"
                               oninput="document.getElementById('img-preview').src=this.value">
                        <button type="button" onclick="abrirMediaPicker('imagen_url','image')" class="btn btn-primary" style="white-space:nowrap;padding:0 14px;" title="Seleccionar de la biblioteca de medios">
                            <i class="fas fa-photo-video"></i>
                        </button>
                    </div>
                    <div style="height:130px;background:#f5f5f5;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                        <img id="img-preview"
                             src="<?php echo $editando ? htmlspecialchars($editando['imagen_url']) : ''; ?>"
                             alt="Preview"
                             style="max-height:130px;max-width:100%;object-fit:contain;"
                             onerror="this.style.display='none'" onload="this.style.display='block'">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">URL de destino (clic opcional)</label>
                    <input type="url" name="url_destino" class="form-control"
                           value="<?php echo $editando ? htmlspecialchars($editando['url_destino'] ?? '') : ''; ?>"
                           placeholder="https://sitio-del-anunciante.cl">
                    <small style="color:#718096;font-size:12px;">Deja vacío si el logo no es clicable</small>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:center;">
                    <div class="form-group">
                        <label class="form-label">Orden (prioridad)</label>
                        <input type="number" name="orden" class="form-control" min="0" max="99"
                               value="<?php echo $editando ? (int)$editando['orden'] : 0; ?>">
                    </div>
                    <div class="form-group" style="padding-top:22px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;">
                            <input type="checkbox" name="activo" value="1"
                                   <?php echo (!$editando || $editando['activo']) ? 'checked' : ''; ?>>
                            Activo
                        </label>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:6px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $editando ? 'Actualizar' : 'Crear Logo'; ?>
                    </button>
                    <?php if ($editando): ?>
                    <a href="logos-portada.php" class="btn" style="background:#718096;color:white;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de logos -->
    <div class="card">
        <div class="card-header" style="background:#f7fafc;">
            <h3 class="card-title" style="font-size:15px;">
                <i class="fas fa-list"></i> Logos (<?php echo count($logos); ?>)
            </h3>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($logos)): ?>
            <div style="padding:40px;text-align:center;color:#718096;">
                <i class="fas fa-images" style="font-size:48px;opacity:0.3;display:block;margin-bottom:12px;"></i>
                No hay logos creados todavía
            </div>
            <?php else: ?>
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>URL Destino</th>
                        <th style="text-align:center;">Orden</th>
                        <th style="text-align:center;">Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logos as $l): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <img src="<?php echo htmlspecialchars($l['imagen_url']); ?>"
                                 style="width:100px;height:65px;object-fit:contain;border-radius:4px;background:#eee;"
                                 onerror="this.style.opacity='.2'">
                            <div>
                                <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($l['nombre']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;color:#718096;">
                        <?php if (!empty($l['url_destino'])): ?>
                            <a href="<?php echo htmlspecialchars($l['url_destino']); ?>" target="_blank" style="color:#3182ce;">
                                <?php echo htmlspecialchars(substr($l['url_destino'], 0, 40)) . (strlen($l['url_destino']) > 40 ? '...' : ''); ?>
                            </a>
                        <?php else: ?>
                            <span style="opacity:.5;">Sin enlace</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;font-weight:700;color:#3182ce;">
                        <?php echo (int)$l['orden']; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($l['activo']): ?>
                            <span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;">Activo</span>
                        <?php else: ?>
                            <span style="background:#fee;color:#c53030;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;">Oculto</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a href="logos-portada.php?editar=<?php echo (int)$l['id']; ?>"
                           style="background:#ebf8ff;color:#2b6cb0;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:600;display:inline-block;margin-right:4px;">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="logos-portada.php?eliminar=<?php echo (int)$l['id']; ?>"
                           onclick="return confirm('¿Eliminar este logo?')"
                           style="background:#fff5f5;color:#c53030;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:600;display:inline-block;">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/media-picker.php'; ?>
<?php include 'includes/footer.php'; ?>
