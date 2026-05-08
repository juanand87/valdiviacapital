<?php
$page_title = 'Dashboard';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Obtener estadísticas
$stats = [];

// Total noticias
$stmt = $db->query("SELECT COUNT(*) as total FROM noticias");
$stats['noticias'] = $stmt->fetch()['total'];

// Total categorías
$stmt = $db->query("SELECT COUNT(*) as total FROM categorias WHERE activo = 1");
$stats['categorias'] = $stmt->fetch()['total'];

// Total comentarios
$stmt = $db->query("SELECT COUNT(*) as total FROM comentarios");
$stats['comentarios'] = $stmt->fetch()['total'];

// Total suscriptores
$stmt = $db->query("SELECT COUNT(*) as total FROM newsletter WHERE activo = 1");
$stats['newsletter'] = $stmt->fetch()['total'];

// Noticias recientes
$stmt = $db->query("
    SELECT n.*, c.nombre as categoria_nombre, u.nombre as autor_nombre
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    INNER JOIN usuarios u ON n.autor_id = u.id
    ORDER BY n.fecha_publicacion DESC
    LIMIT 5
");
$noticias_recientes = $stmt->fetchAll();

// Comentarios pendientes
$stmt = $db->query("
    SELECT cm.*, n.titulo as noticia_titulo
    FROM comentarios cm
    INNER JOIN noticias n ON cm.noticia_id = n.id
    WHERE cm.aprobado = 0
    ORDER BY cm.created_at DESC
    LIMIT 5
");
$comentarios_pendientes = $stmt->fetchAll();
?>

<div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Bienvenido, <?php echo $_SESSION['admin_nombre']; ?></p>
</div>

<!-- Estadísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Total Noticias</span>
            <div class="stat-icon primary">
                <i class="fas fa-newspaper"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $stats['noticias']; ?></div>
        <div class="stat-footer">
            <a href="noticias.php">Ver todas →</a>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Categorías</span>
            <div class="stat-icon success">
                <i class="fas fa-folder"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $stats['categorias']; ?></div>
        <div class="stat-footer">
            <a href="categorias.php">Administrar →</a>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Comentarios</span>
            <div class="stat-icon warning">
                <i class="fas fa-comments"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $stats['comentarios']; ?></div>
        <div class="stat-footer">
            <a href="comentarios.php">Moderar →</a>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Suscriptores</span>
            <div class="stat-icon info">
                <i class="fas fa-envelope"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $stats['newsletter']; ?></div>
        <div class="stat-footer">
            <a href="newsletter.php">Ver lista →</a>
        </div>
    </div>
</div>

<!-- Noticias Recientes -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Noticias Recientes</h3>
        <a href="noticias.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Nueva Noticia
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Autor</th>
                        <th>Vistas</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($noticias_recientes)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #718096;">
                                No hay noticias todavía
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($noticias_recientes as $noticia): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($noticia['titulo']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-success"><?php echo htmlspecialchars($noticia['categoria_nombre']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($noticia['autor_nombre']); ?></td>
                                <td><?php echo number_format($noticia['vistas']); ?></td>
                                <td><?php echo formatDate($noticia['fecha_publicacion']); ?></td>
                                <td>
                                    <a href="editar-noticia.php?id=<?php echo $noticia['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Comentarios Pendientes -->
<?php if (!empty($comentarios_pendientes)): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Comentarios Pendientes de Aprobación</h3>
        <a href="comentarios.php" class="btn btn-warning btn-sm">
            Ver Todos
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Autor</th>
                        <th>Noticia</th>
                        <th>Comentario</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comentarios_pendientes as $comentario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($comentario['nombre']); ?></td>
                            <td><?php echo htmlspecialchars(truncate($comentario['noticia_titulo'], 40)); ?></td>
                            <td><?php echo htmlspecialchars(truncate($comentario['comentario'], 60)); ?></td>
                            <td><?php echo timeAgo($comentario['created_at']); ?></td>
                            <td>
                                <a href="comentarios.php" class="btn btn-sm btn-success">
                                    <i class="fas fa-check"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
