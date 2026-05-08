<?php
$page_title = 'Usuarios';
require_once '../includes/config.php';
include 'includes/header.php';
verificarPermiso('admin');

$db = getDB();

// Obtener usuarios
$usuarios = $db->query("
    SELECT u.*, COUNT(n.id) as total_noticias 
    FROM usuarios u
    LEFT JOIN noticias n ON u.id = n.autor_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de Usuarios</h1>
        <p class="page-subtitle">Administra periodistas y editores</p>
    </div>
    <a href="editar-usuario.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Nuevo Usuario
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Noticias</th>
                        <th>Último Login</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($usuario['nombre']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td>
                                <?php
                                $roles_badges = [
                                    'admin' => '<span class="badge badge-danger">Admin</span>',
                                    'editor' => '<span class="badge badge-warning">Editor</span>',
                                    'periodista' => '<span class="badge badge-success">Periodista</span>'
                                ];
                                echo $roles_badges[$usuario['rol']];
                                ?>
                            </td>
                            <td><?php echo $usuario['total_noticias']; ?></td>
                            <td>
                                <?php echo $usuario['last_login'] ? timeAgo($usuario['last_login']) : 'Nunca'; ?>
                            </td>
                            <td>
                                <?php if ($usuario['activo']): ?>
                                    <span class="badge badge-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="editar-usuario.php?id=<?php echo $usuario['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
