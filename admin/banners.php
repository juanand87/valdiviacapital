<?php
$page_title = 'Banners Publicitarios';
require_once '../includes/config.php';
include 'includes/header.php';

$db    = getDB();
$posiciones = ['leaderboard', 'billboard', 'sidebar', 'in_article'];

// ── Eliminar ──────────────────────────────────────────────────────────────
if (isset($_GET['eliminar'])) {
    $bid = (int)$_GET['eliminar'];
    if ($bid > 0) {
        $db->prepare("DELETE FROM banners WHERE id = ?")->execute([$bid]);
        $mensaje = 'Banner eliminado correctamente';
    }
}

// ── Guardar / Actualizar ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bid         = (int)($_POST['id'] ?? 0);
    $titulo      = trim(clean($_POST['titulo']      ?? ''));
    $imagen_url  = trim(clean($_POST['imagen_url']  ?? ''));
    $url_destino = trim(clean($_POST['url_destino'] ?? ''));
    $posicion    = in_array($_POST['posicion'] ?? '', $posiciones, true) ? $_POST['posicion'] : 'leaderboard';
    $activo      = isset($_POST['activo']) ? 1 : 0;
    $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
    $fecha_fin    = !empty($_POST['fecha_fin'])    ? $_POST['fecha_fin']    : null;
    $orden        = max(0, (int)($_POST['orden'] ?? 0));

    if (!$titulo || !$imagen_url || !$url_destino) {
        $error = 'Completa los campos obligatorios (Título, Imagen y URL destino).';
    } elseif (!filter_var($url_destino, FILTER_VALIDATE_URL)) {
        $error = 'La URL de destino no es válida. Incluye http:// o https://';
    } else {
        try {
            if ($bid) {
                $db->prepare("
                    UPDATE banners
                    SET titulo = ?, imagen_url = ?, url_destino = ?, posicion = ?,
                        activo = ?, fecha_inicio = ?, fecha_fin = ?, orden = ?
                    WHERE id = ?
                ")->execute([$titulo, $imagen_url, $url_destino, $posicion,
                             $activo, $fecha_inicio, $fecha_fin, $orden, $bid]);
                $mensaje = 'Banner actualizado correctamente';
            } else {
                $db->prepare("
                    INSERT INTO banners (titulo, imagen_url, url_destino, posicion, activo, fecha_inicio, fecha_fin, orden)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$titulo, $imagen_url, $url_destino, $posicion,
                             $activo, $fecha_inicio, $fecha_fin, $orden]);
                $mensaje = 'Banner creado correctamente';
            }
            $bid = 0; // Limpiar formulario
        } catch (PDOException $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

// Cargar para editar
$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM banners WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editando = $stmt->fetch();
}

// Lista completa
$banners = $db->query("SELECT * FROM banners ORDER BY posicion ASC, orden ASC, id DESC")->fetchAll();

$posLabel = [
    'leaderboard' => ['icon' => 'fa-stream',    'color' => '#3182ce', 'label' => 'Leaderboard (728×90)'],
    'billboard'   => ['icon' => 'fa-expand-alt','color' => '#805ad5', 'label' => 'Billboard (970×250)'],
    'sidebar'     => ['icon' => 'fa-columns',   'color' => '#38a169', 'label' => 'Sidebar (300×250)'],
    'in_article'  => ['icon' => 'fa-align-center','color' => '#dd6b20','label' => 'In-article'],
];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Banners Publicitarios</h1>
        <p class="page-subtitle">Gestiona los espacios publicitarios del sitio</p>
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
                <?php echo $editando ? 'Editar Banner' : 'Nuevo Banner'; ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$editando['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Título interno *</label>
                    <input type="text" name="titulo" class="form-control" required maxlength="100"
                           value="<?php echo $editando ? htmlspecialchars($editando['titulo']) : ''; ?>"
                           placeholder="Ej: Inmobiliaria XYZ – Mayo">
                </div>

                <div class="form-group">
                    <label class="form-label">URL de la imagen *</label>
                    <input type="url" name="imagen_url" class="form-control" required
                           value="<?php echo $editando ? htmlspecialchars($editando['imagen_url']) : ''; ?>"
                           placeholder="https://ejemplo.com/banner.jpg"
                           oninput="document.getElementById('img-preview').src=this.value">
                    <div style="margin-top:8px;height:60px;background:#f5f5f5;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                        <img id="img-preview"
                             src="<?php echo $editando ? htmlspecialchars($editando['imagen_url']) : ''; ?>"
                             alt="Preview"
                             style="max-height:60px;max-width:100%;object-fit:contain;"
                             onerror="this.style.display='none'" onload="this.style.display='block'">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">URL de destino (clic) *</label>
                    <input type="url" name="url_destino" class="form-control" required
                           value="<?php echo $editando ? htmlspecialchars($editando['url_destino']) : ''; ?>"
                           placeholder="https://anunciante.cl">
                </div>

                <div class="form-group">
                    <label class="form-label">Posición</label>
                    <select name="posicion" class="form-control">
                        <?php foreach ($posLabel as $val => $info): ?>
                        <option value="<?php echo $val; ?>"
                            <?php echo ($editando && $editando['posicion'] === $val) ? 'selected' : ''; ?>>
                            <?php echo $info['label']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Desde</label>
                        <input type="date" name="fecha_inicio" class="form-control"
                               value="<?php echo $editando ? ($editando['fecha_inicio'] ?? '') : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="fecha_fin" class="form-control"
                               value="<?php echo $editando ? ($editando['fecha_fin'] ?? '') : ''; ?>">
                    </div>
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
                        <i class="fas fa-save"></i> <?php echo $editando ? 'Actualizar' : 'Crear Banner'; ?>
                    </button>
                    <?php if ($editando): ?>
                    <a href="banners.php" class="btn" style="background:#718096;color:white;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de banners -->
    <div class="card">
        <div class="card-header" style="background:#f7fafc;">
            <h3 class="card-title" style="font-size:15px;">
                <i class="fas fa-list"></i> Banners (<?php echo count($banners); ?>)
            </h3>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($banners)): ?>
            <div style="padding:40px;text-align:center;color:#718096;">
                <i class="fas fa-ad" style="font-size:48px;opacity:0.3;display:block;margin-bottom:12px;"></i>
                No hay banners creados todavía
            </div>
            <?php else: ?>
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Banner</th>
                        <th>Posición</th>
                        <th>Fecha</th>
                        <th style="text-align:center;">Clics</th>
                        <th style="text-align:center;">Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($banners as $b): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <img src="<?php echo htmlspecialchars($b['imagen_url']); ?>"
                                 style="width:72px;height:36px;object-fit:cover;border-radius:4px;background:#eee;"
                                 onerror="this.style.opacity='.2'">
                            <div>
                                <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($b['titulo']); ?></div>
                                <div style="font-size:11px;color:#718096;overflow:hidden;text-overflow:ellipsis;max-width:180px;white-space:nowrap;">
                                    <?php echo htmlspecialchars($b['url_destino']); ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php $p = $posLabel[$b['posicion']] ?? ['icon'=>'fa-ad','color'=>'#666','label'=>$b['posicion']]; ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;background:<?php echo $p['color'];?>1a;color:<?php echo $p['color'];?>;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;">
                            <i class="fas <?php echo $p['icon']; ?>"></i> <?php echo $p['label']; ?>
                        </span>
                    </td>
                    <td style="font-size:12px;color:#718096;">
                        <?php if ($b['fecha_inicio'] || $b['fecha_fin']): ?>
                            <?php echo $b['fecha_inicio'] ? date('d/m/y', strtotime($b['fecha_inicio'])) : '∞'; ?>
                            &rarr;
                            <?php echo $b['fecha_fin'] ? date('d/m/y', strtotime($b['fecha_fin'])) : '∞'; ?>
                        <?php else: ?>
                            <span style="opacity:.5;">Sin límite</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;font-weight:700;color:#3182ce;">
                        <?php echo number_format($b['clics']); ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($b['activo']): ?>
                            <span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;">Activo</span>
                        <?php else: ?>
                            <span style="background:#fee;color:#c53030;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;">Pausado</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a href="banners.php?editar=<?php echo (int)$b['id']; ?>"
                           style="background:#ebf8ff;color:#2b6cb0;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:600;display:inline-block;margin-right:4px;">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="banners.php?eliminar=<?php echo (int)$b['id']; ?>"
                           onclick="return confirm('¿Eliminar este banner?')"
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

<?php include 'includes/footer.php'; ?>
