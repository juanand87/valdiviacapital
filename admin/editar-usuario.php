<?php
$page_title = 'Editar Usuario';
require_once '../includes/config.php';
include 'includes/header.php';
verificarPermiso('admin');

$db = getDB();
$usuario = null;
$mensaje_exito = '';
$mensaje_error = '';

// Obtener usuario si se pasa id
if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        $mensaje_error = "Usuario no encontrado";
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = trim($_POST['rol'] ?? 'periodista');
        $activo = isset($_POST['activo']) ? 1 : 0;
        $password = trim($_POST['password'] ?? '');
        
        // Validar
        if (empty($nombre)) {
            throw new Exception("El nombre es requerido");
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido");
        }
        if (!in_array($rol, ['admin', 'editor', 'periodista'])) {
            throw new Exception("Rol inválido");
        }
        
        if ($usuario) {
            // Editar usuario
            $sql = "UPDATE usuarios SET nombre = ?, email = ?, rol = ?, activo = ? WHERE id = ?";
            $params = [$nombre, $email, $rol, $activo, $usuario['id']];
            
            // Actualizar password si se proporciona
            if (!empty($password)) {
                $sql = "UPDATE usuarios SET nombre = ?, email = ?, rol = ?, activo = ?, password = ? WHERE id = ?";
                $params = [$nombre, $email, $rol, $activo, password_hash($password, PASSWORD_DEFAULT), $usuario['id']];
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } else {
            // Crear usuario
            if (empty($password)) {
                throw new Exception("La contraseña es requerida para nuevos usuarios");
            }
            
            // Verificar que el email no exista
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception("El email ya existe");
            }
            
            $stmt = $db->prepare("
                INSERT INTO usuarios (nombre, email, rol, password, activo, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$nombre, $email, $rol, password_hash($password, PASSWORD_DEFAULT), $activo]);
            $usuario_id = $db->lastInsertId();
            
            // Redirigir a editar
            header("Location: editar-usuario.php?id=" . $usuario_id . "&exito=1");
            exit;
        }
        
        $mensaje_exito = "Usuario guardado correctamente";
        
        // Recargar datos
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute($usuario ? [$usuario['id']] : [$usuario_id]);
        $usuario = $stmt->fetch();
        
    } catch (Exception $e) {
        $mensaje_error = $e->getMessage();
    }
}

// Mensaje de éxito en URL
if (isset($_GET['exito'])) {
    $mensaje_exito = "Usuario creado correctamente";
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $usuario ? 'Editar Usuario' : 'Nuevo Usuario'; ?></h1>
        <p class="page-subtitle"><?php echo $usuario ? htmlspecialchars($usuario['nombre']) : 'Crear un nuevo usuario'; ?></p>
    </div>
    <a href="usuarios.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<?php if ($mensaje_exito): ?>
    <div class="alert alert-success" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
    </div>
<?php endif; ?>

<?php if ($mensaje_error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($mensaje_error); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" class="form">
            <div class="form-group">
                <label for="nombre">Nombre Completo *</label>
                <input 
                    type="text" 
                    id="nombre" 
                    name="nombre" 
                    class="form-control" 
                    value="<?php echo $usuario ? htmlspecialchars($usuario['nombre']) : ''; ?>"
                    required
                />
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    value="<?php echo $usuario ? htmlspecialchars($usuario['email']) : ''; ?>"
                    required
                />
            </div>

            <div class="form-group">
                <label for="rol">Rol *</label>
                <select id="rol" name="rol" class="form-control" required>
                    <option value="">Selecciona un rol</option>
                    <option value="periodista" <?php echo ($usuario && $usuario['rol'] === 'periodista') ? 'selected' : ''; ?>>
                        Periodista
                    </option>
                    <option value="editor" <?php echo ($usuario && $usuario['rol'] === 'editor') ? 'selected' : ''; ?>>
                        Editor
                    </option>
                    <option value="admin" <?php echo ($usuario && $usuario['rol'] === 'admin') ? 'selected' : ''; ?>>
                        Administrador
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">
                    Contraseña <?php echo !$usuario ? '*' : ''; ?>
                    <?php if ($usuario): ?>
                        <small class="text-muted">(Dejá en blanco para no cambiar)</small>
                    <?php endif; ?>
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control"
                    <?php echo !$usuario ? 'required' : ''; ?>
                />
            </div>

            <div class="form-group">
                <label for="activo" class="checkbox">
                    <input 
                        type="checkbox" 
                        id="activo" 
                        name="activo" 
                        value="1"
                        <?php echo (!$usuario || $usuario['activo']) ? 'checked' : ''; ?>
                    />
                    Usuario Activo
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar
                </button>
                <a href="usuarios.php" class="btn btn-secondary">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($usuario): ?>
    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h3>Información Adicional</h3>
        </div>
        <div class="card-body">
            <dl>
                <dt>ID</dt>
                <dd><?php echo htmlspecialchars($usuario['id']); ?></dd>
                
                <dt>Creado</dt>
                <dd><?php echo date('d/m/Y H:i', strtotime($usuario['created_at'])); ?></dd>
                
                <dt>Último Login</dt>
                <dd>
                    <?php 
                    if ($usuario['last_login']) {
                        echo date('d/m/Y H:i', strtotime($usuario['last_login']));
                    } else {
                        echo 'Nunca ha iniciado sesión';
                    }
                    ?>
                </dd>
            </dl>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
