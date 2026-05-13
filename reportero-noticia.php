<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/reporteros.php';

if (isMaintenance()) { include 'mantenimiento.php'; exit; }
reporteroRequerirLogin();

$db = getDB();
$reportero = reporteroActual();
if (!$reportero) {
    reporteroLogout();
    header('Location: ser-reportero.php?login=1');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$envio = null;
$editando = false;

if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM reportero_noticias WHERE id = ? AND reportero_id = ? LIMIT 1');
    $stmt->execute([$id, $reportero['id']]);
    $envio = $stmt->fetch();
    if (!$envio) {
        header('Location: reportero-panel.php');
        exit;
    }
    $editando = true;
}

$comunas = $db->query('SELECT id, nombre FROM comunas ORDER BY nombre')->fetchAll();
$comunasSeleccionadas = [];
if ($editando) {
    $stmtC = $db->prepare('SELECT comuna_id FROM reportero_noticias_comunas WHERE reportero_noticia_id = ?');
    $stmtC->execute([$envio['id']]);
    $comunasSeleccionadas = $stmtC->fetchAll(PDO::FETCH_COLUMN);
}

$errores = [];
$mensaje = '';
$bloqueado = $editando && !reporteroPuedeEditarEnvio($envio['estado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {
    $accion = $_POST['accion'] ?? 'guardar';
    $titulo = trim($_POST['titulo'] ?? '');
    $bajada = trim($_POST['bajada'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $slugSugerido = reporteroSlug($titulo);
    $comunasPost = array_filter(array_map('intval', $_POST['comunas'] ?? []));

    if ($titulo === '') $errores[] = 'Debes ingresar un título.';
    if ($contenido === '') $errores[] = 'Debes ingresar el contenido de la noticia.';

    $upload = reporteroSubirImagen('imagen_principal', $envio['imagen_principal'] ?? null);
    if (!$upload['ok']) {
        $errores[] = $upload['error'];
    }

    if (!$errores) {
        $estado = $accion === 'enviar' ? 'pendiente' : 'borrador';
        $fechaEnvio = $accion === 'enviar' ? date('Y-m-d H:i:s') : null;
        $imagen = $upload['path'] ?? ($envio['imagen_principal'] ?? null);

        if ($editando) {
            $stmt = $db->prepare('UPDATE reportero_noticias SET titulo = ?, slug_sugerido = ?, bajada = ?, contenido = ?, imagen_principal = ?, estado = ?, fecha_envio = ?, updated_at = NOW() WHERE id = ? AND reportero_id = ?');
            $stmt->execute([$titulo, $slugSugerido, $bajada, $contenido, $imagen, $estado, $fechaEnvio, $envio['id'], $reportero['id']]);
            $envioId = $envio['id'];
        } else {
            $stmt = $db->prepare('INSERT INTO reportero_noticias (reportero_id, titulo, slug_sugerido, bajada, contenido, imagen_principal, estado, fecha_envio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$reportero['id'], $titulo, $slugSugerido, $bajada, $contenido, $imagen, $estado, $fechaEnvio]);
            $envioId = (int)$db->lastInsertId();
            $editando = true;
        }

        $db->prepare('DELETE FROM reportero_noticias_comunas WHERE reportero_noticia_id = ?')->execute([$envioId]);
        if ($comunasPost) {
            $stmtCom = $db->prepare('INSERT INTO reportero_noticias_comunas (reportero_noticia_id, comuna_id) VALUES (?, ?)');
            foreach ($comunasPost as $cid) {
                $stmtCom->execute([$envioId, $cid]);
            }
        }

        header('Location: reportero-noticia.php?id=' . $envioId . '&saved=' . ($accion === 'enviar' ? 'sent' : 'draft'));
        exit;
    }
}

if ($editando) {
    $stmt = $db->prepare('SELECT * FROM reportero_noticias WHERE id = ? AND reportero_id = ? LIMIT 1');
    $stmt->execute([$id, $reportero['id']]);
    $envio = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editando ? 'Editar envío' : 'Nuevo envío'; ?> - Reportero VC</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <section class="reportero-editor-shell">
        <div class="container">
            <div class="reportero-dashboard-header">
                <div>
                    <span class="reportero-kicker">Mi redacción</span>
                    <h1><?php echo $editando ? 'Editar envío' : 'Nuevo envío'; ?></h1>
                    <p><?php echo $editando ? 'Ajusta tu noticia antes de enviarla o reenvíala a revisión.' : 'Carga una noticia ciudadana y guárdala como borrador si aún no está lista.'; ?></p>
                </div>
                <div class="reportero-dashboard-actions">
                    <a href="reportero-panel.php" class="reportero-btn secondary"><i class="fas fa-arrow-left"></i> Volver a mi panel</a>
                </div>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="reportero-alert success"><?php echo $_GET['saved'] === 'sent' ? 'Tu noticia fue enviada a revisión.' : 'Borrador guardado correctamente.'; ?></div>
            <?php endif; ?>

            <?php if ($bloqueado): ?>
                <div class="reportero-alert warning">Este envío está en estado <strong><?php echo htmlspecialchars(reporteroEstadoLabel($envio['estado'])); ?></strong> y no puede editarse por ahora.</div>
            <?php endif; ?>

            <?php if ($editando && !empty($envio['admin_notas'])): ?>
                <div class="reportero-alert info"><strong>Observaciones editoriales:</strong> <?php echo nl2br(htmlspecialchars($envio['admin_notas'])); ?></div>
            <?php endif; ?>

            <?php if ($errores): ?>
                <div class="reportero-alert error">
                    <?php foreach ($errores as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="reportero-editor-grid" id="form-reportero">
                <div class="reportero-card">
                    <div class="reportero-form-grid">
                        <div>
                            <label>Título *</label>
                            <input type="text" name="titulo" required value="<?php echo htmlspecialchars($envio['titulo'] ?? ''); ?>" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                        </div>
                        <div>
                            <label>Bajada / resumen</label>
                            <textarea name="bajada" rows="3" <?php echo $bloqueado ? 'disabled' : ''; ?>><?php echo htmlspecialchars($envio['bajada'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label>Contenido *</label>
                            <div id="editor-reportero" class="reportero-quill"><?php echo $editando ? ($envio['contenido'] ?? '') : ''; ?></div>
                            <textarea name="contenido" id="contenido" style="display:none;"></textarea>
                        </div>
                    </div>
                </div>

                <aside class="reportero-card reportero-side-card">
                    <div class="reportero-form-grid">
                        <div>
                            <label>Imagen principal</label>
                            <?php if (!empty($envio['imagen_principal'])): ?>
                                <img src="<?php echo htmlspecialchars($envio['imagen_principal']); ?>" alt="Imagen actual" class="reportero-thumb-preview">
                            <?php endif; ?>
                            <input type="file" name="imagen_principal" accept="image/jpeg,image/png,image/webp" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                            <small>Formatos: JPG, PNG o WEBP. Máximo 5 MB.</small>
                        </div>
                        <div>
                            <label>Comunas relacionadas</label>
                            <div class="reportero-checklist">
                                <?php foreach ($comunas as $comuna): ?>
                                    <label><input type="checkbox" name="comunas[]" value="<?php echo (int)$comuna['id']; ?>" <?php echo in_array($comuna['id'], $comunasSeleccionadas, false) ? 'checked' : ''; ?> <?php echo $bloqueado ? 'disabled' : ''; ?>> <?php echo htmlspecialchars($comuna['nombre']); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if ($editando): ?>
                            <div>
                                <label>Estado actual</label>
                                <div><span class="reportero-status <?php echo reporteroEstadoBadgeClass($envio['estado']); ?>"><?php echo htmlspecialchars(reporteroEstadoLabel($envio['estado'])); ?></span></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!$bloqueado): ?>
                            <button type="submit" name="accion" value="guardar" class="reportero-btn secondary"><i class="fas fa-save"></i> Guardar borrador</button>
                            <button type="submit" name="accion" value="enviar" class="reportero-btn primary"><i class="fas fa-paper-plane"></i> <?php echo ($editando && ($envio['estado'] ?? '') === 'requiere_correccion') ? 'Reenviar a revisión' : 'Enviar a revisión'; ?></button>
                        <?php endif; ?>
                    </div>
                </aside>
            </form>
        </div>
    </section>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    var quill = new Quill('#editor-reportero', {
        theme: 'snow',
        readOnly: <?php echo $bloqueado ? 'true' : 'false'; ?>,
        modules: {
            toolbar: <?php echo $bloqueado ? 'false' : json_encode([
                [['header' => [2, 3, false]]],
                ['bold', 'italic', 'underline'],
                [['list' => 'ordered'], ['list' => 'bullet']],
                ['link', 'blockquote'],
                ['clean']
            ]); ?>
        }
    });

    document.getElementById('form-reportero').addEventListener('submit', function () {
        document.getElementById('contenido').value = quill.root.innerHTML;
    });
    </script>
</body>
</html>