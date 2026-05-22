<?php
session_start();
$page_title = 'Scraping Facebook';
require_once '../includes/config.php';
require_once '../includes/scraping_ai.php';

$db = getDB();
$providerCfg = getScrapingProviderConfig($db);

// Procesar cambio de proveedor via AJAX
if ($_POST['action'] ?? '' === 'change_provider' && isset($_POST['provider'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
    
    $provider = $_POST['provider'];
    if (!in_array($provider, ['direct', 'jina', 'gemini', 'copilot'], true)) {
        echo json_encode(['error' => 'Proveedor inválido']);
        exit;
    }
    
    try {
        $db->prepare("UPDATE configuracion_ia SET valor = :valor WHERE nombre = 'scraping_provider_facebook'")
           ->execute([':valor' => $provider]);
        echo json_encode(['ok' => true, 'provider' => $provider]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $redirect_msg = '';
    $redirect_err = '';

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
                $redirect_msg = urlencode("Página \"$nombre\" agregada correctamente.");
                break;

            case 'toggle_activo':
                $id     = (int)($_POST['id'] ?? 0);
                $activo = (int)($_POST['activo'] ?? 0);
                $db->prepare("UPDATE medios_conectados SET activo = :activo WHERE id = :id AND tipo = 'facebook_scraping'")
                   ->execute([':activo' => $activo, ':id' => $id]);
                $redirect_msg = urlencode($activo ? 'Página activada.' : 'Página desactivada.');
                break;

            case 'eliminar':
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare("DELETE FROM medios_conectados WHERE id = :id AND tipo = 'facebook_scraping'")
                   ->execute([':id' => $id]);
                $redirect_msg = urlencode('Página eliminada.');
                break;
        }
    } catch (Exception $e) {
        $redirect_err = urlencode($e->getMessage());
    }

    // PRG: redirigir siempre tras POST para evitar reenvío al recargar
    $qs = $redirect_err ? '?error=' . $redirect_err : ($redirect_msg ? '?ok=' . $redirect_msg : '');
    header('Location: medios-facebook-scraping.php' . $qs);
    exit;
}

// Leer mensajes de la redirección
$mensaje_exito = isset($_GET['ok'])    ? htmlspecialchars(urldecode($_GET['ok']))    : null;
$mensaje_error = isset($_GET['error']) ? htmlspecialchars(urldecode($_GET['error'])) : null;

include 'includes/header.php';

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
        <div style="margin-top:12px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <label for="provider-selector" style="margin:0; color:#666; font-size:13px;">Proveedor de extracción:</label>
            <select id="provider-selector" class="form-control" style="flex:0 0 auto; width:200px; max-width:100%;" onchange="cambiarProveedor()">
                <option value="direct" <?php echo ($providerCfg['provider_facebook'] ?? 'direct') === 'direct' ? 'selected' : ''; ?>>
                    Directo (HTML/Regex)
                </option>
                <option value="jina" <?php echo ($providerCfg['provider_facebook'] ?? 'direct') === 'jina' ? 'selected' : ''; ?>>
                    Jina AI Reader
                </option>
                <option value="gemini" <?php echo ($providerCfg['provider_facebook'] ?? 'direct') === 'gemini' ? 'selected' : ''; ?>>
                    Gemini
                </option>
                <option value="copilot" <?php echo ($providerCfg['provider_facebook'] ?? 'direct') === 'copilot' ? 'selected' : ''; ?>>
                    GitHub Copilot
                </option>
            </select>
            <span id="provider-status" style="color:#666; font-size:12px;"></span>
        </div>
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
                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; width:100%;">
                    <h2 style="margin:0;">Páginas Configuradas</h2>
                    <button type="button" class="btn btn-primary" onclick="analizarTodasPaginas()" <?php echo empty($paginas) ? 'disabled' : ''; ?>>
                        <i class="fas fa-layer-group"></i> Analizar todas (activas)
                    </button>
                </div>
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
                            <tr id="fila-<?php echo $p['id']; ?>"
                                data-pagina-id="<?php echo (int)$p['id']; ?>"
                                data-nombre="<?php echo htmlspecialchars($p['nombre'], ENT_QUOTES); ?>"
                                data-activo="<?php echo (int)$p['activo']; ?>">
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
function ejecutarScrapingPagina(id) {
    var data = new FormData();
    data.append('pagina_id', id);

    return fetch('ajax/scraping-fb-analizar.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    }).then(function(r) { return r.json(); });
}

function analizarPagina(id, nombre) {
    var modal = document.getElementById('modal-analisis');
    var titulo = document.getElementById('modal-titulo');
    var body   = document.getElementById('modal-body');

    titulo.textContent = 'Analizando: ' + nombre;
    body.innerHTML = '<p style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Scrapeando Facebook...</p>';
    modal.style.display = 'flex';

    ejecutarScrapingPagina(id)
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
        html += '<p style="margin:0 0 14px 0; color:#666; font-size:13px;">Proveedor usado: <strong>' + escapeHtml(res.provider || 'direct') + '</strong></p>';

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
    })
    .catch(function(err) {
        body.innerHTML = '<div class="alert alert-error" style="margin:0;">Error de red: ' + escapeHtml(err.message) + '</div>';
    });
}

async function analizarTodasPaginas() {
    var modal = document.getElementById('modal-analisis');
    var titulo = document.getElementById('modal-titulo');
    var body   = document.getElementById('modal-body');

    var filas = Array.from(document.querySelectorAll('tr[data-pagina-id][data-activo="1"]'));
    if (filas.length === 0) {
        titulo.textContent = 'Analizar todas';
        body.innerHTML = '<div class="alert alert-error" style="margin:0;">No hay páginas activas para analizar.</div>';
        modal.style.display = 'flex';
        return;
    }

    titulo.textContent = 'Analizando páginas activas de Facebook';
    modal.style.display = 'flex';

    var total = filas.length;
    var procesadas = 0;
    var totGuardadas = 0;
    var totDuplicadas = 0;
    var totErrores = 0;
    var log = [];

    function renderProgreso(actualNombre) {
        var pct = Math.round((procesadas / total) * 100);
        body.innerHTML =
            '<p style="margin:0 0 10px 0;"><strong>Procesando:</strong> ' + escapeHtml(actualNombre || '...') + '</p>' +
            '<div style="width:100%; background:#eef2f7; border-radius:999px; height:14px; overflow:hidden; margin-bottom:10px;">' +
                '<div style="width:' + pct + '%; height:100%; background:#27ae60; transition:width .2s;"></div>' +
            '</div>' +
            '<p style="margin:0 0 12px 0; color:#666; font-size:13px;">' + procesadas + ' de ' + total + ' (' + pct + '%)</p>' +
            '<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:12px;">' +
                '<div style="text-align:center; background:#e8f8f5; border-radius:6px; padding:10px;"><strong style="font-size:20px; color:#27ae60;">' + totGuardadas + '</strong><div style="font-size:12px; color:#555;">Guardadas</div></div>' +
                '<div style="text-align:center; background:#fef9e7; border-radius:6px; padding:10px;"><strong style="font-size:20px; color:#f39c12;">' + totDuplicadas + '</strong><div style="font-size:12px; color:#555;">Duplicadas</div></div>' +
                '<div style="text-align:center; background:#fdecea; border-radius:6px; padding:10px;"><strong style="font-size:20px; color:#e74c3c;">' + totErrores + '</strong><div style="font-size:12px; color:#555;">Errores</div></div>' +
            '</div>' +
            '<div style="max-height:220px; overflow:auto; border:1px solid #edf2f7; border-radius:8px; padding:10px; background:#fafbfc;">' +
                (log.length ? log.join('') : '<small style="color:#999;">Iniciando...</small>') +
            '</div>';
    }

    renderProgreso('Iniciando...');

    for (var i = 0; i < filas.length; i++) {
        var fila = filas[i];
        var id = parseInt(fila.getAttribute('data-pagina-id'), 10);
        var nombre = fila.getAttribute('data-nombre') || ('ID ' + id);

        renderProgreso(nombre);

        try {
            var res = await ejecutarScrapingPagina(id);
            if (res && !res.error) {
                totGuardadas += (parseInt(res.guardadas, 10) || 0);
                totDuplicadas += (parseInt(res.duplicadas, 10) || 0);
                totErrores += (parseInt(res.errores, 10) || 0);
                log.unshift('<div style="padding:6px 0; border-bottom:1px solid #f0f2f5;"><strong>' + escapeHtml(nombre) + '</strong><br><small style="color:#666;">+' + (parseInt(res.guardadas, 10) || 0) + ' nuevas, ' + (parseInt(res.duplicadas, 10) || 0) + ' duplicadas</small></div>');
            } else {
                totErrores++;
                log.unshift('<div style="padding:6px 0; border-bottom:1px solid #f0f2f5;"><strong>' + escapeHtml(nombre) + '</strong><br><small style="color:#c0392b;">Error: ' + escapeHtml((res && res.error) ? res.error : 'desconocido') + '</small></div>');
            }
        } catch (e) {
            totErrores++;
            log.unshift('<div style="padding:6px 0; border-bottom:1px solid #f0f2f5;"><strong>' + escapeHtml(nombre) + '</strong><br><small style="color:#c0392b;">Error de red: ' + escapeHtml(e.message) + '</small></div>');
        }

        procesadas++;
        renderProgreso(nombre);
    }

    var resumen = '<div style="margin-top:14px; display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">';
    resumen += '<a href="noticias-escaneadas.php" class="btn btn-primary"><i class="fas fa-list"></i> Ver Noticias Escaneadas</a>';
    resumen += '<button onclick="location.reload()" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> Recargar tabla</button>';
    resumen += '</div>';
    body.innerHTML += resumen;
}

function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

function cambiarProveedor() {
    var selector = document.getElementById('provider-selector');
    var status = document.getElementById('provider-status');
    var nuevoProvider = selector.value;
    
    status.textContent = '';
    status.style.color = '#666';
    selector.disabled = true;
    
    var formData = new FormData();
    formData.append('action', 'change_provider');
    formData.append('provider', nuevoProvider);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.ok) {
            status.textContent = '✓ Proveedor actualizado';
            status.style.color = '#27ae60';
            setTimeout(function() {
                status.textContent = '';
            }, 3000);
        } else {
            status.textContent = '✗ ' + (data.error || 'Error desconocido');
            status.style.color = '#e74c3c';
            selector.value = selector.options[0].value; // Revertir
        }
    })
    .catch(function(err) {
        status.textContent = '✗ Error de red';
        status.style.color = '#e74c3c';
        selector.value = selector.options[0].value; // Revertir
    })
    .finally(function() {
        selector.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>
