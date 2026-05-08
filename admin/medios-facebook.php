<?php
$page_title = 'Medios de Facebook';
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
                        VALUES (:nombre, 'facebook', :url, :descripcion, :activo)
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
                        INSERT INTO medios_facebook_config (
                            medio_id, page_id, access_token, app_id, app_secret,
                            sincronizar_posts, sincronizar_comentarios, frecuencia_sincronizacion
                        ) VALUES (
                            :medio_id, :page_id, :access_token, :app_id, :app_secret,
                            :sincronizar_posts, :sincronizar_comentarios, :frecuencia
                        )
                    ");
                    $stmt->execute([
                        ':medio_id' => $medio_id,
                        ':page_id' => $_POST['page_id'] ?? '',
                        ':access_token' => $_POST['access_token'] ?? '',
                        ':app_id' => $_POST['app_id'] ?? '',
                        ':app_secret' => $_POST['app_secret'] ?? '',
                        ':sincronizar_posts' => isset($_POST['sincronizar_posts']) ? 1 : 0,
                        ':sincronizar_comentarios' => isset($_POST['sincronizar_comentarios']) ? 1 : 0,
                        ':frecuencia' => $_POST['frecuencia'] ?? 30
                    ]);
                    
                    $mensaje_exito = "Medio de Facebook agregado correctamente";
                    break;
                    
                case 'editar':
                    // Actualizar medio
                    $stmt = $db->prepare("
                        UPDATE medios_conectados 
                        SET nombre = :nombre, url = :url, descripcion = :descripcion, activo = :activo
                        WHERE id = :id AND tipo = 'facebook'
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
                        UPDATE medios_facebook_config 
                        SET page_id = :page_id,
                            access_token = :access_token,
                            app_id = :app_id,
                            app_secret = :app_secret,
                            sincronizar_posts = :sincronizar_posts,
                            sincronizar_comentarios = :sincronizar_comentarios,
                            frecuencia_sincronizacion = :frecuencia
                        WHERE medio_id = :medio_id
                    ");
                    $stmt->execute([
                        ':medio_id' => $_POST['id'],
                        ':page_id' => $_POST['page_id'] ?? '',
                        ':access_token' => $_POST['access_token'] ?? '',
                        ':app_id' => $_POST['app_id'] ?? '',
                        ':app_secret' => $_POST['app_secret'] ?? '',
                        ':sincronizar_posts' => isset($_POST['sincronizar_posts']) ? 1 : 0,
                        ':sincronizar_comentarios' => isset($_POST['sincronizar_comentarios']) ? 1 : 0,
                        ':frecuencia' => $_POST['frecuencia'] ?? 30
                    ]);
                    
                    $mensaje_exito = "Medio de Facebook actualizado correctamente";
                    break;
                    
                case 'eliminar':
                    $stmt = $db->prepare("DELETE FROM medios_conectados WHERE id = :id AND tipo = 'facebook'");
                    $stmt->execute([':id' => $_POST['id']]);
                    $mensaje_exito = "Medio de Facebook eliminado correctamente";
                    break;
            }
        }
    } catch (PDOException $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// Obtener lista de medios
$stmt = $db->query("
    SELECT m.*, f.page_id, f.sincronizar_posts, f.sincronizar_comentarios, f.frecuencia_sincronizacion,
           (SELECT COUNT(*) FROM medios_contenido_sincronizado WHERE medio_id = m.id) as total_contenido
    FROM medios_conectados m
    LEFT JOIN medios_facebook_config f ON m.id = f.medio_id
    WHERE m.tipo = 'facebook'
    ORDER BY m.created_at DESC
");
$medios = $stmt->fetchAll();

// Si se está editando, obtener datos
$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("
        SELECT m.*, f.*
        FROM medios_conectados m
        LEFT JOIN medios_facebook_config f ON m.id = f.medio_id
        WHERE m.id = :id AND m.tipo = 'facebook'
    ");
    $stmt->execute([':id' => $_GET['editar']]);
    $editando = $stmt->fetch();
}
?>

<div class="page-header">
    <div>
        <h1><i class="fab fa-facebook"></i> Medios de Facebook</h1>
        <p>Sincroniza publicaciones desde páginas de Facebook</p>
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

<div class="card info-card">
    <div class="card-body">
        <h3><i class="fas fa-info-circle"></i> Cómo configurar un medio de Facebook</h3>
        <ol>
            <li>Ve a <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a></li>
            <li>Crea una aplicación o usa una existente</li>
            <li>Obtén el <strong>App ID</strong> y <strong>App Secret</strong></li>
            <li>Genera un <strong>Access Token</strong> con permisos de lectura de páginas</li>
            <li>Obtén el <strong>Page ID</strong> de la página que deseas sincronizar</li>
            <li>Completa el formulario con estos datos</li>
        </ol>
    </div>
</div>

<div class="content-grid">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h2>Medios Configurados</h2>
            </div>
            <div class="card-body">
                <?php if (empty($medios)): ?>
                    <p class="text-muted">No hay medios de Facebook configurados. Agrega tu primera página.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Page ID</th>
                                    <th>Sincronización</th>
                                    <th>Frecuencia</th>
                                    <th>Contenido</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medios as $medio): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($medio['nombre']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($medio['page_id']); ?></td>
                                        <td>
                                            <?php if ($medio['sincronizar_posts']): ?>
                                                <span class="badge badge-info">Posts</span>
                                            <?php endif; ?>
                                            <?php if ($medio['sincronizar_comentarios']): ?>
                                                <span class="badge badge-info">Comentarios</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $medio['frecuencia_sincronizacion']; ?> min</td>
                                        <td><?php echo $medio['total_contenido']; ?> items</td>
                                        <td>
                                            <span class="badge <?php echo $medio['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $medio['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?editar=<?php echo $medio['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este medio?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $medio['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
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
                <h2><?php echo $editando ? 'Editar' : 'Agregar'; ?> Medio</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'agregar'; ?>">
                    <?php if ($editando): ?>
                        <input type="hidden" name="id" value="<?php echo $editando['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="nombre">Nombre de la Página *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" 
                               value="<?php echo $editando['nombre'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="url">URL de la Página *</label>
                        <input type="url" id="url" name="url" class="form-control" 
                               value="<?php echo $editando['url'] ?? ''; ?>" required>
                        <small class="form-text">URL de la página de Facebook</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3"><?php echo $editando['descripcion'] ?? ''; ?></textarea>
                    </div>
                    
                    <h3>Configuración de Facebook</h3>
                    
                    <div class="form-group">
                        <label for="page_id">Page ID *</label>
                        <input type="text" id="page_id" name="page_id" class="form-control" 
                               value="<?php echo $editando['page_id'] ?? ''; ?>" required>
                        <small class="form-text">ID de la página de Facebook</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="app_id">App ID *</label>
                        <input type="text" id="app_id" name="app_id" class="form-control" 
                               value="<?php echo $editando['app_id'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="app_secret">App Secret *</label>
                        <input type="text" id="app_secret" name="app_secret" class="form-control" 
                               value="<?php echo $editando['app_secret'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="access_token">Access Token *</label>
                        <textarea id="access_token" name="access_token" class="form-control" rows="3" required><?php echo $editando['access_token'] ?? ''; ?></textarea>
                        <small class="form-text">Token de acceso de larga duración</small>
                    </div>
                    
                    <h3>Opciones de Sincronización</h3>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="sincronizar_posts" value="1" <?php echo ($editando['sincronizar_posts'] ?? 1) ? 'checked' : ''; ?>>
                            Sincronizar publicaciones
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="sincronizar_comentarios" value="1" <?php echo ($editando['sincronizar_comentarios'] ?? 1) ? 'checked' : ''; ?>>
                            Sincronizar comentarios
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="frecuencia">Frecuencia de Sincronización (minutos)</label>
                        <input type="number" id="frecuencia" name="frecuencia" class="form-control" 
                               value="<?php echo $editando['frecuencia_sincronizacion'] ?? 30; ?>" min="1">
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
                            <a href="medios-facebook.php" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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

.info-card {
    background: #e8f4f8;
    border-left: 4px solid #1877f2;
    margin-bottom: 20px;
}

.info-card h3 {
    color: #1877f2;
    margin-bottom: 15px;
}

.info-card ol {
    margin-left: 20px;
}

.info-card li {
    margin-bottom: 8px;
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #7f8c8d;
}

.badge-info {
    background: #3498db;
    margin-right: 5px;
}
</style>

<?php include 'includes/footer.php'; ?>
