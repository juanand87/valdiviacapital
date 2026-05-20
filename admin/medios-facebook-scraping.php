<?php
$page_title = 'Scraping Facebook';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();
$mensaje_exito = null;
$mensaje_error = null;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        switch ($accion) {
            case 'agregar':
                $nombre = trim($_POST['nombre'] ?? '');
                $url    = trim($_POST['url'] ?? '');
                $desc   = trim($_POST['descripcion'] ?? '');

                if (empty($nombre) || empty($url)) {
                    throw new Exception('Nombre y URL son obligatorios.');
                }

                // Normalizar URL: asegurar https://www.facebook.com/slug
                if (!preg_match('#^https?://#i', $url)) {
                    $url = 'https://www.facebook.com/' . ltrim($url, '/');
                }
                $parsed = parse_url($url);
                $slug   = trim($parsed['path'] ?? '', '/');
                if (empty($slug)) {
                    throw new Exception('No se pudo extraer el nombre de la página desde la URL.');
                }
                $url_normalizada = 'https://www.facebook.com/' . $slug;

                $stmt = $db->prepare("
                    INSERT INTO medios_conectados (nombre, tipo, url, descripcion, activo)
                    VALUES (:nombre, 'facebook_scraping', :url, :descripcion, 1)
                ");
                $stmt->execute([
                    ':nombre'      => $nombre,
                    ':url'         => $url_normalizada,
                    ':descripcion' => $desc,
                ]);
                $mensaje_exito = "Página \"$nombre\" agregada correctamente.";
                break;

            case 'toggle_activo':
                $id     = (int)($_POST['id'] ?? 0);
                $activo = (int)($_POST['activo'] ?? 0);
                $db->prepare("UPDATE medios_conectados SET activo = :activo WHERE id = :id AND tipo = 'facebook_scraping'")
                   ->execute([':activo' => $activo, ':id' => $id]);
                $mensaje_exito = $activo ? 'Página activada.' : 'Página desactivada.';
                break;

            case 'eliminar':
                $id = (int)($_POST['id'] ?? 0);
                // El CASCADE borra también medios_contenido_sincronizado
                $db->prepare("DELETE FROM medios_conectados WHERE id = :id AND tipo = 'facebook_scraping'")
                   ->execute([':id' => $id]);
                $mensaje_exito = 'Página eliminada.';
                break;
        }
    } catch (Exception $e) {
        $mensaje_error = $e->getMessage();
    }
}

// Obtener páginas configuradas
$stmt = $db->query("
    SELECT m.*,
           (SELECT COUNT(*) FROM medios_contenido_sincronizado WHERE medio_id = m.id) as total_posts,
           (SELECT COUNT(*) FROM medios_contenido_sincronizado WHERE medio_id = m.id AND estado = 'pendiente') as pendientes
    FROM medios_conectados m
    WHERE m.tipo = 'facebook_scraping'
    ORDER BY m.created_at DESC
");
$paginas = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="fab fa-facebook"></i> Scraping Facebook</h1>
        <p>Extrae publicaciones de páginas públicas de Facebook sin necesidad de API</p>
    </div>
    <a href="medios-conectados.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<?php if ($mensaje_exito): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
<?php endif; ?>
<?php if ($mensaje_error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
<?php endif; ?>

<div class="content-grid">
    <!-- Lista de páginas -->
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h2>Páginas Configuradas</h2>
            </div>
            <div class="card-body">
                <?php if (empty($paginas)): ?>
                    <p class="text-muted">No hay páginas configuradas. Agrega la primera página de Facebook a la derecha.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Página</th>
                                <th>URL</th>
                                <th>Posts</th>
                                <th>Última sync</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginas as $p): ?>
                            <tr id="fila-<?php echo $p['id']; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                    <?php if ($p['descripcion']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($p['descripcion']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($p['url']); ?>" target="_blank" rel="noopener noreferrer" class="text-link">
                                        <?php echo htmlspecialchars(substr($p['url'], 8, 35)); ?>...
                                    </a>
                                </td>
                                <td>
                                    <?php echo (int)$p['total_posts']; ?> total
                                    <?php if ($p['pendientes'] > 0): ?>
                                        <br><span class="badge badge-warning"><?php echo (int)$p['pendientes']; ?> pendientes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['ultima_sincronizacion']): ?>
                                        <small><?php echo date('d/m/Y H:i', strtotime($p['ultima_sincronizacion'])); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Nunca</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $p['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $p['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick="analizarPagina(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['nombre'], ENT_QUOTES); ?>')" title="Analizar ahora">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <a href="noticias-escaneadas.php?medio_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary" title="Ver posts guardados">
                                        <i class="fas fa-list"></i>
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar esta página y todos sus posts guardados?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info técnica -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h2><i class="fas fa-info-circle"></i> Cómo funciona</h2>
            </div>
            <div class="card-body">
                <ul style="line-height: 1.8; color: #555;">
                    <li>Se accede a la página pública de Facebook y se extrae el JSON embebido en el HTML.</li>
                    <li>Se obtiene el texto de cada publicación y su fecha de creación.</li>
                    <li>Cada post se guarda en "Noticias Escaneadas" con estado <em>pendiente</em>.</li>
                    <li>Se evitan duplicados automáticamente mediante hash del contenido.</li>
                    <li>El cron se ejecuta cada 6 horas y acumula el historial de posts.</li>
                    <li><strong>Limitación:</strong> solo se obtienen los posts visibles en la carga inicial de la página (generalmente 1–3 posts recientes). Las imágenes no están disponibles.</li>
                </ul>
                <div class="alert" style="background:#e8f4fd; border-left:4px solid #3498db; padding:12px; margin-top:10px; border-radius:4px;">
                    <strong>Cron GoDaddy cPanel:</strong><br>
                    <code>0 */6 * * * /usr/local/bin/php /home/USUARIO/public_html/cron/sincronizar-facebook.php</code>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario agregar -->
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h2>Agregar Página</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar">

                    <div class="form-group">
                        <label for="nombre">Nombre <span style="color:red">*</span></label>
                        <input type="text" id="nombre" name="nombre" class="form-control"
                               placeholder="Ej: Fiscalía de Los Ríos" required>
                    </div>

                    <div class="form-group">
                        <label for="url">URL de Facebook <span style="color:red">*</span></label>
                        <input type="text" id="url" name="url" class="form-control"
                               placeholder="https://www.facebook.com/NombrePagina" required>
                        <small class="form-text">Puedes pegar solo el nombre de la página o la URL completa.</small>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3"
                                  placeholder="Descripción opcional"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        <i class="fas fa-plus"></i> Agregar Página
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal resultados de análisis -->
<div id="modal-analisis" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:8px; padding:30px; max-width:520px; width:90%; max-height:80vh; overflow-y:auto; position:relative;">
        <button onclick="document.getElementById('modal-analisis').style.display='none'"
                style="position:absolute; top:12px; right:16px; background:none; border:none; font-size:22px; cursor:pointer; color:#666;">&times;</button>
        <h3 id="modal-titulo" style="margin-bottom:20px;"></h3>
        <div id="modal-body"></div>
    </div>
</div>

<script>
function analizarPagina(id, nombre) {
    var modal = document.getElementById('modal-analisis');
    var titulo = document.getElementById('modal-titulo');
    var body   = document.getElementById('modal-body');

    titulo.textContent = 'Analizando: ' + nombre;
    body.innerHTML = '<p style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Scrapeando Facebook...</p>';
    modal.style.display = 'flex';

    var data = new FormData();
    data.append('pagina_id', id);

    fetch('ajax/scraping-fb-analizar.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.error) {
            body.innerHTML = '<div class="alert alert-error" style="margin:0;">' + escapeHtml(res.error) + '</div>';
            return;
        }

        var html = '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:20px;">';
        html += '<div style="text-align:center; padding:15px; background:#e8f8f5; border-radius:6px;"><div style="font-size:28px; font-weight:bold; color:#27ae60;">' + res.guardadas + '</div><div style="color:#555; font-size:13px;">Guardadas</div></div>';
        html += '<div style="text-align:center; padding:15px; background:#fef9e7; border-radius:6px;"><div style="font-size:28px; font-weight:bold; color:#f39c12;">' + res.duplicadas + '</div><div style="color:#555; font-size:13px;">Duplicadas</div></div>';
        html += '<div style="text-align:center; padding:15px; background:#f4f4f4; border-radius:6px;"><div style="font-size:28px; font-weight:bold; color:#7f8c8d;">' + res.total_html + '</div><div style="color:#555; font-size:13px;">Encontradas</div></div>';
        html += '</div>';

        if (res.posts && res.posts.length > 0) {
            html += '<h4 style="margin-bottom:10px;">Posts nuevos guardados:</h4><ul style="padding-left:20px; line-height:1.8;">';
            res.posts.forEach(function(p) {
                html += '<li><strong>' + escapeHtml(p.titulo) + '</strong><br><small style="color:#888;">' + p.fecha + '</small></li>';
            });
            html += '</ul>';
        } else {
            html += '<p style="color:#7f8c8d; text-align:center; padding:10px;">' +
                    (res.guardadas === 0 && res.duplicadas === 0
                        ? 'No se encontraron publicaciones en la página.'
                        : 'Todos los posts ya estaban guardados anteriormente.') +
                    '</p>';
        }

        if (res.errores > 0) {
            html += '<p style="color:#e74c3c; margin-top:10px;"><i class="fas fa-exclamation-triangle"></i> ' + res.errores + ' error(es) al guardar.</p>';
        }

        html += '<div style="margin-top:20px; text-align:right;">';
        if (res.guardadas > 0) {
            html += '<a href="noticias-escaneadas.php" class="btn btn-primary" style="margin-right:8px;"><i class="fas fa-list"></i> Ver Noticias Escaneadas</a>';
        }
        html += '<button onclick="document.getElementById(\'modal-analisis\').style.display=\'none\'" class="btn btn-secondary">Cerrar</button>';
        html += '</div>';

        body.innerHTML = html;

        // Actualizar contador en la tabla
        location.reload();
    })
    .catch(function(err) {
        body.innerHTML = '<div class="alert alert-error" style="margin:0;">Error de red: ' + escapeHtml(err.message) + '</div>';
    });
}

function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}
</script>

<?php include 'includes/footer.php'; ?>
