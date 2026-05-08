<?php
$page_title = 'Medios de Instagram';
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
                        VALUES (:nombre, 'instagram', :url, :descripcion, :activo)
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
                        INSERT INTO medios_instagram_config (
                            medio_id, username, user_id, access_token,
                            sincronizar_posts, sincronizar_stories, frecuencia_sincronizacion
                        ) VALUES (
                            :medio_id, :username, :user_id, :access_token,
                            :sincronizar_posts, :sincronizar_stories, :frecuencia
                        )
                    ");
                    $stmt->execute([
                        ':medio_id' => $medio_id,
                        ':username' => $_POST['username'] ?? '',
                        ':user_id' => $_POST['user_id'] ?? '',
                        ':access_token' => $_POST['access_token'] ?? '',
                        ':sincronizar_posts' => isset($_POST['sincronizar_posts']) ? 1 : 0,
                        ':sincronizar_stories' => isset($_POST['sincronizar_stories']) ? 1 : 0,
                        ':frecuencia' => $_POST['frecuencia'] ?? 30
                    ]);
                    
                    $mensaje_exito = "Medio de Instagram agregado correctamente";
                    break;
                    
                case 'editar':
                    // Actualizar medio
                    $stmt = $db->prepare("
                        UPDATE medios_conectados 
                        SET nombre = :nombre, url = :url, descripcion = :descripcion, activo = :activo
                        WHERE id = :id AND tipo = 'instagram'
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
                        UPDATE medios_instagram_config 
                        SET username = :username,
                            user_id = :user_id,
                            access_token = :access_token,
                            sincronizar_posts = :sincronizar_posts,
                            sincronizar_stories = :sincronizar_stories,
                            frecuencia_sincronizacion = :frecuencia
                        WHERE medio_id = :medio_id
                    ");
                    $stmt->execute([
                        ':medio_id' => $_POST['id'],
                        ':username' => $_POST['username'] ?? '',
                        ':user_id' => $_POST['user_id'] ?? '',
                        ':access_token' => $_POST['access_token'] ?? '',
                        ':sincronizar_posts' => isset($_POST['sincronizar_posts']) ? 1 : 0,
                        ':sincronizar_stories' => isset($_POST['sincronizar_stories']) ? 1 : 0,
                        ':frecuencia' => $_POST['frecuencia'] ?? 30
                    ]);
                    
                    $mensaje_exito = "Medio de Instagram actualizado correctamente";
                    break;
                    
                case 'eliminar':
                    $stmt = $db->prepare("DELETE FROM medios_conectados WHERE id = :id AND tipo = 'instagram'");
                    $stmt->execute([':id' => $_POST['id']]);
                    $mensaje_exito = "Medio de Instagram eliminado correctamente";
                    break;
            }
        }
    } catch (PDOException $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// Obtener lista de medios
$stmt = $db->query("
    SELECT m.*, i.username, i.sincronizar_posts, i.sincronizar_stories, i.frecuencia_sincronizacion,
           (SELECT COUNT(*) FROM medios_contenido_sincronizado WHERE medio_id = m.id) as total_contenido
    FROM medios_conectados m
    LEFT JOIN medios_instagram_config i ON m.id = i.medio_id
    WHERE m.tipo = 'instagram'
    ORDER BY m.created_at DESC
");
$medios = $stmt->fetchAll();

// Si se está editando, obtener datos
$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("
        SELECT m.*, i.*
        FROM medios_conectados m
        LEFT JOIN medios_instagram_config i ON m.id = i.medio_id
        WHERE m.id = :id AND m.tipo = 'instagram'
    ");
    $stmt->execute([':id' => $_GET['editar']]);
    $editando = $stmt->fetch();
}
?>

<div class="page-header">
    <div>
        <h1><i class="fab fa-instagram"></i> Medios de Instagram</h1>
        <p>Obtén contenido desde perfiles de Instagram</p>
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
        <h3><i class="fas fa-info-circle"></i> Cómo configurar un medio de Instagram</h3>
        <ol>
            <li>Ve a <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a> (Instagram usa la API de Facebook)</li>
            <li>Crea una aplicación con Instagram Basic Display o Instagram Graph API</li>
            <li>Conecta tu cuenta de Instagram Business o Creator</li>
            <li>Genera un <strong>Access Token</strong> de larga duración</li>
            <li>Obtén el <strong>User ID</strong> de Instagram</li>
            <li>Completa el formulario con estos datos</li>
        </ol>
        <p><strong>Nota:</strong> Solo funciona con cuentas de Instagram Business o Creator conectadas a una página de Facebook.</p>
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
                    <p class="text-muted">No hay medios de Instagram configurados. Agrega tu primer perfil.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Username</th>
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
                                        <td>@<?php echo htmlspecialchars($medio['username']); ?></td>
                                        <td>
                                            <?php if ($medio['sincronizar_posts']): ?>
                                                <span class="badge badge-info">Posts</span>
                                            <?php endif; ?>
                                            <?php if ($medio['sincronizar_stories']): ?>
                                                <span class="badge badge-warning">Stories</span>
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
                        <label for="nombre">Nombre del Perfil *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" 
                               value="<?php echo $editando['nombre'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="url">URL del Perfil *</label>
                        <input type="url" id="url" name="url" class="form-control" 
                               value="<?php echo $editando['url'] ?? ''; ?>" required>
                        <small class="form-text">URL del perfil de Instagram</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3"><?php echo $editando['descripcion'] ?? ''; ?></textarea>
                    </div>
                    
                    <h3>Configuración de Instagram</h3>
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <div class="input-group">
                            <span class="input-prefix">@</span>
                            <input type="text" id="username" name="username" class="form-control" 
                                   value="<?php echo $editando['username'] ?? ''; ?>" required>
                        </div>
                        <small class="form-text">Nombre de usuario de Instagram (sin @)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_id">User ID *</label>
                        <input type="text" id="user_id" name="user_id" class="form-control" 
                               value="<?php echo $editando['user_id'] ?? ''; ?>" required>
                        <small class="form-text">ID numérico del usuario de Instagram</small>
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
                            <input type="checkbox" name="sincronizar_stories" value="1" <?php echo ($editando['sincronizar_stories'] ?? 0) ? 'checked' : ''; ?>>
                            Sincronizar historias (stories)
                        </label>
                        <small class="form-text">Las stories tienen duración limitada de 24 horas</small>
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
                            <a href="medios-instagram.php" class="btn btn-secondary">Cancelar</a>
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
    background: #fce4ec;
    border-left: 4px solid #e4405f;
    margin-bottom: 20px;
}

.info-card h3 {
    color: #e4405f;
    margin-bottom: 15px;
}

.info-card ol {
    margin-left: 20px;
}

.info-card li {
    margin-bottom: 8px;
}

.info-card p {
    margin-top: 15px;
    color: #c2185b;
    font-weight: 500;
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

.badge-warning {
    background: #f39c12;
    margin-right: 5px;
}

.input-group {
    display: flex;
    align-items: center;
}

.input-prefix {
    background: #ecf0f1;
    padding: 8px 12px;
    border: 1px solid #bdc3c7;
    border-right: none;
    border-radius: 4px 0 0 4px;
    color: #7f8c8d;
}

.input-group .form-control {
    border-radius: 0 4px 4px 0;
}
</style>

<?php include 'includes/footer.php'; ?>
