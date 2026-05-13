<?php
$page_title = 'Revisión Reportero VC';
require_once '../includes/config.php';
require_once '../includes/reporteros.php';
require_once 'includes/auth.php';
verificarPermiso('editor');

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT rn.*, r.nombres, r.apellidos, r.email, r.telefono, r.rut
    FROM reportero_noticias rn
    INNER JOIN reporteros r ON r.id = rn.reportero_id
    WHERE rn.id = ? LIMIT 1");
$stmt->execute([$id]);
$envio = $stmt->fetch();

if (!$envio) {
    header('Location: reporteros.php');
    exit;
}

$categorias = $db->query('SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre')->fetchAll();
$comunas = $db->query('SELECT * FROM comunas ORDER BY nombre')->fetchAll();
$selectedComunas = [];
$stmtC = $db->prepare('SELECT comuna_id FROM reportero_noticias_comunas WHERE reportero_noticia_id = ?');
$stmtC->execute([$envio['id']]);
$selectedComunas = $stmtC->fetchAll(PDO::FETCH_COLUMN);

$errores = [];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'guardar';
    $titulo = trim($_POST['titulo'] ?? '');
    $bajada = trim($_POST['bajada'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $imagenPrincipal = trim($_POST['imagen_principal'] ?? '');
    $adminNotas = trim($_POST['admin_notas'] ?? '');
    $motivoRechazo = trim($_POST['motivo_rechazo'] ?? '');
    $categoriasPost = array_filter(array_map('intval', $_POST['categorias'] ?? []));
    $comunasPost = array_filter(array_map('intval', $_POST['comunas'] ?? []));
    $publicado = isset($_POST['publicado']) ? 1 : 0;
    $destacado = isset($_POST['destacado']) ? 1 : 0;

    if ($titulo === '') $errores[] = 'Debes ingresar un título.';
    if ($contenido === '') $errores[] = 'Debes ingresar contenido.';
    if ($accion === 'aprobar' && !$categoriasPost) $errores[] = 'Debes seleccionar al menos una categoría para aprobar.';
    if ($accion === 'correccion' && $adminNotas === '') $errores[] = 'Debes indicar la corrección solicitada.';
    if ($accion === 'rechazar' && $motivoRechazo === '') $errores[] = 'Debes indicar el motivo del rechazo.';

    if (!$errores) {
        try {
            $db->beginTransaction();

            $estadoDestino = $envio['estado'];
            if ($accion === 'guardar' && !in_array($envio['estado'], ['aprobado', 'rechazado'], true)) {
                $estadoDestino = 'en_revision';
            }
            if ($accion === 'correccion') {
                $estadoDestino = 'requiere_correccion';
            }
            if ($accion === 'rechazar') {
                $estadoDestino = 'rechazado';
            }

            $stmtUpdate = $db->prepare('UPDATE reportero_noticias SET titulo = ?, slug_sugerido = ?, bajada = ?, contenido = ?, imagen_principal = ?, admin_notas = ?, motivo_rechazo = ?, estado = ?, revisado_por = ?, fecha_revision = NOW(), updated_at = NOW() WHERE id = ?');
            $stmtUpdate->execute([
                $titulo,
                reporteroSlug($titulo),
                $bajada,
                $contenido,
                $imagenPrincipal,
                $adminNotas ?: null,
                $motivoRechazo ?: null,
                $estadoDestino,
                $_SESSION['admin_id'],
                $envio['id']
            ]);

            $db->prepare('DELETE FROM reportero_noticias_comunas WHERE reportero_noticia_id = ?')->execute([$envio['id']]);
            if ($comunasPost) {
                $stmtInsertCom = $db->prepare('INSERT INTO reportero_noticias_comunas (reportero_noticia_id, comuna_id) VALUES (?, ?)');
                foreach ($comunasPost as $cid) {
                    $stmtInsertCom->execute([$envio['id'], $cid]);
                }
            }

            if ($accion === 'aprobar') {
                $slug = reporteroGenerarSlugUnico($db, $titulo);
                $categoriaPrincipal = reset($categoriasPost);
                $stmtNoticia = $db->prepare('INSERT INTO noticias (titulo, slug, bajada, contenido, imagen_principal, categoria_id, autor_id, destacado, publicado, fecha_publicacion, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())');
                $stmtNoticia->execute([$titulo, $slug, $bajada, $contenido, $imagenPrincipal ?: null, $categoriaPrincipal, $_SESSION['admin_id'], $destacado, $publicado]);
                $noticiaId = (int)$db->lastInsertId();

                $stmtCat = $db->prepare('INSERT IGNORE INTO noticias_categorias (noticia_id, categoria_id) VALUES (?, ?)');
                foreach ($categoriasPost as $catId) {
                    $stmtCat->execute([$noticiaId, $catId]);
                }

                if ($comunasPost) {
                    $stmtNc = $db->prepare('INSERT IGNORE INTO noticias_comunas (noticia_id, comuna_id) VALUES (?, ?)');
                    foreach ($comunasPost as $cid) {
                        $stmtNc->execute([$noticiaId, $cid]);
                    }
                }

                $stmtAprob = $db->prepare("UPDATE reportero_noticias SET estado = 'aprobado', noticia_publicada_id = ?, revisado_por = ?, fecha_revision = NOW(), updated_at = NOW() WHERE id = ?");
                $stmtAprob->execute([$noticiaId, $_SESSION['admin_id'], $envio['id']]);
            }

            $db->commit();
            header('Location: revisar-reportero.php?id=' . $envio['id'] . '&ok=' . $accion);
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errores[] = 'No fue posible guardar la revisión: ' . $e->getMessage();
        }
    }
}

$stmt->execute([$id]);
$envio = $stmt->fetch();
$stmtC->execute([$envio['id']]);
$selectedComunas = $stmtC->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<style>
.review-grid { display:grid; grid-template-columns:1fr 320px; gap:20px; }
@media (max-width: 1024px) { .review-grid { grid-template-columns:1fr; } }
.ql-editor { min-height: 360px; font-size: 16px; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Revisión de envío</h1>
        <p class="page-subtitle">Define si el envío se publica, requiere corrección o se rechaza.</p>
    </div>
    <a href="reporteros.php" class="btn" style="background:#718096;color:white;"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Acción ejecutada correctamente.</div>
<?php endif; ?>

<?php if ($errores): ?>
    <div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?php echo htmlspecialchars(implode(' ', $errores)); ?></div>
<?php endif; ?>

<form method="POST" id="formRevision">
    <div class="review-grid">
        <div>
            <div class="card">
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($envio['titulo']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bajada</label>
                        <textarea name="bajada" class="form-control" rows="3"><?php echo htmlspecialchars($envio['bajada']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contenido</label>
                        <div id="editor-admin" style="background:white;"><?php echo $envio['contenido']; ?></div>
                        <textarea name="contenido" id="contenido" style="display:none;"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notas para el reportero</label>
                        <textarea name="admin_notas" class="form-control" rows="4" placeholder="Observaciones para corrección o contexto editorial"><?php echo htmlspecialchars($envio['admin_notas'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Motivo de rechazo</label>
                        <textarea name="motivo_rechazo" class="form-control" rows="3" placeholder="Solo si rechazas el envío"><?php echo htmlspecialchars($envio['motivo_rechazo'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h3 class="card-title" style="font-size:15px;">Datos del reportero</h3></div>
                <div class="card-body" style="font-size:14px;line-height:1.7;">
                    <strong><?php echo htmlspecialchars(trim($envio['nombres'] . ' ' . $envio['apellidos'])); ?></strong><br>
                    <?php echo htmlspecialchars($envio['email']); ?><br>
                    <?php echo htmlspecialchars($envio['telefono']); ?><br>
                    RUT: <?php echo htmlspecialchars($envio['rut']); ?><br>
                    Estado: <span class="badge <?php echo $envio['estado'] === 'aprobado' ? 'badge-success' : ($envio['estado'] === 'rechazado' ? 'badge-danger' : 'badge-warning'); ?>"><?php echo htmlspecialchars(reporteroEstadoLabel($envio['estado'])); ?></span>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h3 class="card-title" style="font-size:15px;">Publicación</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Imagen principal</label>
                        <input type="text" name="imagen_principal" class="form-control" value="<?php echo htmlspecialchars($envio['imagen_principal'] ?? ''); ?>">
                        <?php if (!empty($envio['imagen_principal'])): ?>
                            <img src="<?php echo htmlspecialchars($envio['imagen_principal']); ?>" alt="Imagen" style="width:100%;margin-top:10px;border-radius:8px;">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categorías</label>
                        <div style="max-height:160px;overflow:auto;border:1px solid #e2e8f0;border-radius:8px;padding:8px;">
                            <?php foreach ($categorias as $cat): ?>
                                <label style="display:flex;gap:8px;align-items:center;padding:4px 0;font-size:13px;">
                                    <input type="checkbox" name="categorias[]" value="<?php echo (int)$cat['id']; ?>">
                                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?php echo htmlspecialchars($cat['color']); ?>;"></span>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Comunas</label>
                        <div style="max-height:160px;overflow:auto;border:1px solid #e2e8f0;border-radius:8px;padding:8px;">
                            <?php foreach ($comunas as $comuna): ?>
                                <label style="display:flex;gap:8px;align-items:center;padding:4px 0;font-size:13px;">
                                    <input type="checkbox" name="comunas[]" value="<?php echo (int)$comuna['id']; ?>" <?php echo in_array($comuna['id'], $selectedComunas, false) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($comuna['nombre']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" name="publicado" value="1" checked> Publicar inmediatamente</label>
                    </div>
                    <div style="margin-bottom:18px;">
                        <label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" name="destacado" value="1"> Marcar como destacada</label>
                    </div>
                    <button type="submit" name="accion" value="guardar" class="btn btn-primary" style="width:100%;margin-bottom:10px;"><i class="fas fa-save"></i> Guardar revisión</button>
                    <button type="submit" name="accion" value="correccion" class="btn" style="width:100%;margin-bottom:10px;background:#ed8936;color:white;"><i class="fas fa-rotate-left"></i> Solicitar corrección</button>
                    <button type="submit" name="accion" value="rechazar" class="btn btn-danger" style="width:100%;margin-bottom:10px;"><i class="fas fa-ban"></i> Rechazar</button>
                    <?php if ((int)$envio['noticia_publicada_id'] === 0): ?>
                        <button type="submit" name="accion" value="aprobar" class="btn btn-success" style="width:100%;"><i class="fas fa-check"></i> Aprobar y publicar</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
var quillAdmin = new Quill('#editor-admin', {
    theme: 'snow',
    modules: {
        toolbar: [
            [{ header: [2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link', 'blockquote'],
            ['clean']
        ]
    }
});

document.getElementById('formRevision').addEventListener('submit', function () {
    document.getElementById('contenido').value = quillAdmin.root.innerHTML;
});
</script>

<?php include 'includes/footer.php'; ?>