<?php
$page_title = 'Diarios Online';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['accion'])) {
            switch ($_POST['accion']) {
                case 'agregar':
                    // Insertar medio
                    $stmt = $db->prepare("
                        INSERT INTO medios_conectados (nombre, tipo, url, descripcion, activo)
                        VALUES (:nombre, 'diario_online', :url, :descripcion, :activo)
                    ");
                    $stmt->execute([
                        ':nombre' => $_POST['nombre'],
                        ':url' => $_POST['url'],
                        ':descripcion' => $_POST['descripcion'],
                        ':activo' => isset($_POST['activo']) ? 1 : 0
                    ]);
                    $medio_id = $db->lastInsertId();
                    
                    // Insertar configuración
                    $stmt = $db->prepare("
                        INSERT INTO medios_diarios_config (
                            medio_id, selector_link, selector_titulo, selector_contenido, selector_imagen,
                            selector_fecha, selector_autor, selector_categoria,
                            usa_api, api_endpoint, api_key, frecuencia_sincronizacion, cantidad_noticias
                        ) VALUES (
                            :medio_id, :selector_link, :selector_titulo, :selector_contenido, :selector_imagen,
                            :selector_fecha, :selector_autor, :selector_categoria,
                            :usa_api, :api_endpoint, :api_key, :frecuencia, :cantidad_noticias
                        )
                    ");
                    $stmt->execute([
                        ':medio_id' => $medio_id,
                        ':selector_link' => $_POST['selector_link'] ?? '',
                        ':selector_titulo' => $_POST['selector_titulo'] ?? '',
                        ':selector_contenido' => $_POST['selector_contenido'] ?? '',
                        ':selector_imagen' => $_POST['selector_imagen'] ?? '',
                        ':selector_fecha' => $_POST['selector_fecha'] ?? '',
                        ':selector_autor' => $_POST['selector_autor'] ?? '',
                        ':selector_categoria' => $_POST['selector_categoria'] ?? '',
                        ':usa_api' => isset($_POST['usa_api']) ? 1 : 0,
                        ':api_endpoint' => $_POST['api_endpoint'] ?? '',
                        ':api_key' => $_POST['api_key'] ?? '',
                        ':frecuencia' => $_POST['frecuencia'] ?? 60,
                        ':cantidad_noticias' => $_POST['cantidad_noticias'] ?? 10
                    ]);
                    
                    $mensaje_exito = "Diario agregado correctamente";
                    break;
                    
                case 'editar':
                    // Actualizar medio
                    $stmt = $db->prepare("
                        UPDATE medios_conectados 
                        SET nombre = :nombre, url = :url, descripcion = :descripcion, activo = :activo
                        WHERE id = :id AND tipo = 'diario_online'
                    ");
                    $stmt->execute([
                        ':id' => $_POST['id'],
                        ':nombre' => $_POST['nombre'],
                        ':url' => $_POST['url'],
                        ':descripcion' => $_POST['descripcion'],
                        ':activo' => isset($_POST['activo']) ? 1 : 0
                    ]);
                    
                    // Actualizar configuración
                    $stmt = $db->prepare("
                        UPDATE medios_diarios_config 
                        SET selector_link = :selector_link,
                            selector_titulo = :selector_titulo,
                            selector_contenido = :selector_contenido,
                            selector_imagen = :selector_imagen,
                            selector_fecha = :selector_fecha,
                            selector_autor = :selector_autor,
                            selector_categoria = :selector_categoria,
                            usa_api = :usa_api,
                            api_endpoint = :api_endpoint,
                            api_key = :api_key,
                            frecuencia_sincronizacion = :frecuencia,
                            cantidad_noticias = :cantidad_noticias
                        WHERE medio_id = :medio_id
                    ");
                    $stmt->execute([
                        ':medio_id' => $_POST['id'],
                        ':selector_link' => $_POST['selector_link'] ?? '',
                        ':selector_titulo' => $_POST['selector_titulo'] ?? '',
                        ':selector_contenido' => $_POST['selector_contenido'] ?? '',
                        ':selector_imagen' => $_POST['selector_imagen'] ?? '',
                        ':selector_fecha' => $_POST['selector_fecha'] ?? '',
                        ':selector_autor' => $_POST['selector_autor'] ?? '',
                        ':selector_categoria' => $_POST['selector_categoria'] ?? '',
                        ':usa_api' => isset($_POST['usa_api']) ? 1 : 0,
                        ':api_endpoint' => $_POST['api_endpoint'] ?? '',
                        ':api_key' => $_POST['api_key'] ?? '',
                        ':frecuencia' => $_POST['frecuencia'] ?? 60,
                        ':cantidad_noticias' => $_POST['cantidad_noticias'] ?? 10
                    ]);
                    
                    $mensaje_exito = "Diario actualizado correctamente";
                    break;
                    
                case 'eliminar':
                    $stmt = $db->prepare("DELETE FROM medios_conectados WHERE id = :id AND tipo = 'diario_online'");
                    $stmt->execute([':id' => $_POST['id']]);
                    $mensaje_exito = "Diario eliminado correctamente";
                    break;
            }
        }
    } catch (PDOException $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// Obtener lista de diarios
$stmt = $db->query("
    SELECT m.*, d.selector_titulo, d.frecuencia_sincronizacion, d.usa_api,
           (SELECT COUNT(*) FROM medios_contenido_sincronizado WHERE medio_id = m.id) as total_contenido
    FROM medios_conectados m
    LEFT JOIN medios_diarios_config d ON m.id = d.medio_id
    WHERE m.tipo = 'diario_online'
    ORDER BY m.created_at DESC
");
$diarios = $stmt->fetchAll();

// Si se está editando, obtener datos
$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("
        SELECT m.*, d.*
        FROM medios_conectados m
        LEFT JOIN medios_diarios_config d ON m.id = d.medio_id
        WHERE m.id = :id AND m.tipo = 'diario_online'
    ");
    $stmt->execute([':id' => $_GET['editar']]);
    $editando = $stmt->fetch();
}
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-newspaper"></i> Diarios Online</h1>
        <p>Configura scrapping de noticias desde portales web</p>
    </div>
    <a href="medios-conectados.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<?php if (isset($mensaje_exito)): ?>
    <div class="alert alert-success"><?php echo $mensaje_exito; ?></div>
<?php endif; ?>

<?php if (isset($mensaje_error)): ?>
    <div class="alert alert-error"><?php echo $mensaje_error; ?></div>
<?php endif; ?>

<div class="content-grid">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h2>Diarios Configurados</h2>
            </div>
            <div class="card-body">
                <?php if (empty($diarios)): ?>
                    <p class="text-muted">No hay diarios configurados. Agrega tu primer diario online.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>URL</th>
                                    <th>Método</th>
                                    <th>Frecuencia</th>
                                    <th>Contenido</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($diarios as $diario): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($diario['nombre']); ?></strong></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($diario['url']); ?>" target="_blank" class="text-link">
                                                <?php echo substr($diario['url'], 0, 30); ?>...
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $diario['usa_api'] ? 'badge-info' : 'badge-secondary'; ?>">
                                                <?php echo $diario['usa_api'] ? 'API' : 'Scrapping'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $diario['frecuencia_sincronizacion']; ?> min</td>
                                        <td><?php echo $diario['total_contenido']; ?> items</td>
                                        <td>
                                            <span class="badge <?php echo $diario['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $diario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="medios-scraping.php?id=<?php echo $diario['id']; ?>" class="btn btn-sm btn-success" title="Hacer Scraping">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                            <a href="?editar=<?php echo $diario['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este diario?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $diario['id']; ?>">
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
    </div>
    
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h2><?php echo $editando ? 'Editar' : 'Agregar'; ?> Diario</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'agregar'; ?>">
                    <?php if ($editando): ?>
                        <input type="hidden" name="id" value="<?php echo $editando['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="nombre">Nombre del Diario *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" 
                               value="<?php echo $editando['nombre'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="url">URL del Sitio *</label>
                        <input type="url" id="url" name="url" class="form-control" 
                               value="<?php echo $editando['url'] ?? ''; ?>" required>
                        <small class="form-text">URL principal del diario</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3"><?php echo $editando['descripcion'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="usa_api" value="1" <?php echo ($editando['usa_api'] ?? 0) ? 'checked' : ''; ?>>
                            Usar API en lugar de scrapping
                        </label>
                    </div>
                    
                    <div id="scrapping-config" style="<?php echo ($editando['usa_api'] ?? 0) ? 'display:none;' : ''; ?>">
                        <h3 style="color: #3498db; margin-bottom: 10px;"><i class="fas fa-home"></i> Nivel 1: Portada</h3>
                        <p style="font-size: 13px; color: #7f8c8d; margin-bottom: 15px;">Configura cómo extraer los links de las noticias desde la página principal</p>
                        
                        <div class="form-group">
                            <label for="selector_link">Selector de Link de Noticia *</label>
                            <input type="text" id="selector_link" name="selector_link" class="form-control" 
                                   value="<?php echo $editando['selector_link'] ?? ''; ?>" placeholder=".entry-title a">
                            <small class="form-text">Ej: .entry-title a, a.post-link, .noticia-link</small>
                        </div>
                        
                        <hr style="margin: 20px 0; border-color: #e0e0e0;">
                        
                        <h3 style="color: #e74c3c; margin-bottom: 10px;"><i class="fas fa-newspaper"></i> Nivel 2: Noticia Individual</h3>
                        <p style="font-size: 13px; color: #7f8c8d; margin-bottom: 15px;">Configura cómo extraer el contenido desde cada página de noticia</p>
                        
                        <div class="form-group">
                            <label for="selector_titulo">Selector de Título</label>
                            <input type="text" id="selector_titulo" name="selector_titulo" class="form-control" 
                                   value="<?php echo $editando['selector_titulo'] ?? ''; ?>" placeholder="h1.entry-title">
                            <small class="form-text">Ej: h1.entry-title, .post-title</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="selector_contenido">Selector de Contenido</label>
                            <input type="text" id="selector_contenido" name="selector_contenido" class="form-control" 
                                   value="<?php echo $editando['selector_contenido'] ?? ''; ?>" placeholder=".entry-content">
                            <small class="form-text">Ej: .entry-content, .post-body, article</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="selector_imagen">Selector de Imagen</label>
                            <input type="text" id="selector_imagen" name="selector_imagen" class="form-control" 
                                   value="<?php echo $editando['selector_imagen'] ?? ''; ?>" placeholder=".post-thumbnail img">
                            <small class="form-text">Ej: .post-thumbnail img, .featured-image img</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="selector_fecha">Selector de Fecha</label>
                            <input type="text" id="selector_fecha" name="selector_fecha" class="form-control" 
                                   value="<?php echo $editando['selector_fecha'] ?? ''; ?>" placeholder="time.entry-date">
                            <small class="form-text">Ej: time.entry-date, .post-date</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="selector_autor">Selector de Autor</label>
                            <input type="text" id="selector_autor" name="selector_autor" class="form-control" 
                                   value="<?php echo $editando['selector_autor'] ?? ''; ?>" placeholder=".author-name">
                            <small class="form-text">Ej: .author-name, .entry-author</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="selector_categoria">Selector de Categoría</label>
                            <input type="text" id="selector_categoria" name="selector_categoria" class="form-control" 
                                   value="<?php echo $editando['selector_categoria'] ?? ''; ?>" placeholder=".entry-category">
                            <small class="form-text">Ej: .entry-category, .post-category</small>
                        </div>
                    </div>
                    
                    <div id="api-config" style="<?php echo ($editando['usa_api'] ?? 0) ? '' : 'display:none;'; ?>">
                        <h3>Configuración API</h3>
                        
                        <div class="form-group">
                            <label for="api_endpoint">Endpoint API</label>
                            <input type="url" id="api_endpoint" name="api_endpoint" class="form-control" 
                                   value="<?php echo $editando['api_endpoint'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="api_key">API Key</label>
                            <input type="text" id="api_key" name="api_key" class="form-control" 
                                   value="<?php echo $editando['api_key'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="frecuencia">Frecuencia de Sincronización (minutos)</label>
                        <input type="number" id="frecuencia" name="frecuencia" class="form-control" 
                               value="<?php echo $editando['frecuencia_sincronizacion'] ?? 60; ?>" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="cantidad_noticias">Cantidad de Noticias a Scrapear</label>
                        <input type="number" id="cantidad_noticias" name="cantidad_noticias" class="form-control" 
                               value="<?php echo $editando['cantidad_noticias'] ?? 10; ?>" min="1" max="50">
                        <small class="form-text">Número máximo de noticias a extraer por scraping (1-50)</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="activo" value="1" <?php echo ($editando['activo'] ?? 1) ? 'checked' : ''; ?>>
                            Activo
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $editando ? 'Actualizar' : 'Agregar'; ?>
                        </button>
                        <?php if ($editando): ?>
                            <a href="medios-diarios.php" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelector('input[name="usa_api"]').addEventListener('change', function() {
    document.getElementById('scrapping-config').style.display = this.checked ? 'none' : 'block';
    document.getElementById('api-config').style.display = this.checked ? 'block' : 'none';
});
</script>

<style>
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

@media (max-width: 992px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #7f8c8d;
}

.badge-info {
    background: #3498db;
}

.badge-secondary {
    background: #95a5a6;
}

.text-link {
    color: #3498db;
    text-decoration: none;
}

.text-link:hover {
    text-decoration: underline;
}
</style>

<?php include 'includes/footer.php'; ?>
