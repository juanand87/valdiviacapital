<?php
$page_title = 'Comentarios';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Aprobar/Rechazar comentario
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $accion = $_GET['accion'];
    
    if ($accion === 'aprobar') {
        $stmt = $db->prepare("UPDATE comentarios SET aprobado = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $mensaje = 'Comentario aprobado';
    } elseif ($accion === 'rechazar') {
        $stmt = $db->prepare("DELETE FROM comentarios WHERE id = ?");
        $stmt->execute([$id]);
        $mensaje = 'Comentario eliminado';
    }
}

// Obtener comentarios
$filtro = $_GET['filtro'] ?? 'pendientes';

$where = '';
if ($filtro === 'pendientes') {
    $where = 'WHERE cm.aprobado = 0';
} elseif ($filtro === 'aprobados') {
    $where = 'WHERE cm.aprobado = 1';
}

$stmt = $db->query("
    SELECT cm.*, n.titulo as noticia_titulo
    FROM comentarios cm
    INNER JOIN noticias n ON cm.noticia_id = n.id
    $where
    ORDER BY cm.created_at DESC
");
$comentarios = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de Comentarios</h1>
        <p class="page-subtitle">Modera los comentarios del sitio</p>
    </div>
</div>

<?php if (isset($mensaje)): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
    </div>
<?php endif; ?>

<!-- Filtros -->
<div style="display: flex; gap: 10px; margin-bottom: 20px;">
    <a href="?filtro=pendientes" class="btn <?php echo $filtro === 'pendientes' ? 'btn-primary' : ''; ?>" style="<?php echo $filtro !== 'pendientes' ? 'background: #e2e8f0; color: #4a5568;' : ''; ?>">
        Pendientes
    </a>
    <a href="?filtro=aprobados" class="btn <?php echo $filtro === 'aprobados' ? 'btn-primary' : ''; ?>" style="<?php echo $filtro !== 'aprobados' ? 'background: #e2e8f0; color: #4a5568;' : ''; ?>">
        Aprobados
    </a>
    <a href="?filtro=todos" class="btn <?php echo $filtro === 'todos' ? 'btn-primary' : ''; ?>" style="<?php echo $filtro !== 'todos' ? 'background: #e2e8f0; color: #4a5568;' : ''; ?>">
        Todos
    </a>
</div>

<!-- Lista de Comentarios -->
<div class="card">
    <div class="card-body">
        <?php if (empty($comentarios)): ?>
            <div style="text-align: center; padding: 40px; color: #718096;">
                <i class="fas fa-comments" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                No hay comentarios <?php echo $filtro !== 'todos' ? $filtro : ''; ?>
            </div>
        <?php else: ?>
            <?php foreach ($comentarios as $com): ?>
                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <div>
                            <strong><?php echo htmlspecialchars($com['nombre']); ?></strong>
                            <span style="color: #718096; font-size: 13px;">
                                • <?php echo htmlspecialchars($com['email']); ?>
                            </span>
                        </div>
                        <div>
                            <?php if ($com['aprobado']): ?>
                                <span class="badge badge-success">Aprobado</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pendiente</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="color: #718096; font-size: 13px; margin-bottom: 10px;">
                        En: <a href="../noticia.php?id=<?php echo $com['noticia_id']; ?>" target="_blank">
                            <?php echo htmlspecialchars($com['noticia_titulo']); ?>
                        </a>
                        • <?php echo timeAgo($com['created_at']); ?>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <?php echo nl2br(htmlspecialchars($com['comentario'])); ?>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <?php if (!$com['aprobado']): ?>
                            <a href="?accion=aprobar&id=<?php echo $com['id']; ?>&filtro=<?php echo $filtro; ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-check"></i> Aprobar
                            </a>
                        <?php endif; ?>
                        <a href="?accion=rechazar&id=<?php echo $com['id']; ?>&filtro=<?php echo $filtro; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este comentario?')">
                            <i class="fas fa-trash"></i> Eliminar
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
