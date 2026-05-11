<?php
$page_title = 'Gestión de Noticias';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Filtros
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_autor = $_GET['autor'] ?? '';
$busqueda = $_GET['buscar'] ?? '';

// Construir query
$where = [];
$params = [];

if ($filtro_categoria) {
    $where[] = "n.categoria_id = ?";
    $params[] = $filtro_categoria;
}

if ($filtro_autor) {
    $where[] = "n.autor_id = ?";
    $params[] = $filtro_autor;
}

if ($busqueda) {
    $where[] = "(n.titulo LIKE ? OR n.contenido LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $db->prepare("
    SELECT n.*, c.nombre as categoria_nombre, c.color as categoria_color, u.nombre as autor_nombre
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    INNER JOIN usuarios u ON n.autor_id = u.id
    $where_sql
    ORDER BY n.fecha_publicacion DESC
");
$stmt->execute($params);
$noticias = $stmt->fetchAll();

// Cargar comunas y categorías adicionales de todas las noticias en una sola query
$noticiaComunasMap    = [];
$noticiaCategoriasMap = [];
if ($noticias) {
    $ids = implode(',', array_column($noticias, 'id'));
    $rows = $db->query("
        SELECT nc.noticia_id, c.nombre FROM noticias_comunas nc
        INNER JOIN comunas c ON c.id = nc.comuna_id
        WHERE nc.noticia_id IN ($ids) ORDER BY c.nombre
    ")->fetchAll();
    foreach ($rows as $r) {
        $noticiaComunasMap[$r['noticia_id']][] = $r['nombre'];
    }
    $catRows = $db->query("
        SELECT nc.noticia_id, c.nombre, c.color FROM noticias_categorias nc
        INNER JOIN categorias c ON c.id = nc.categoria_id
        WHERE nc.noticia_id IN ($ids) ORDER BY c.nombre
    ")->fetchAll();
    foreach ($catRows as $r) {
        $noticiaCategoriasMap[$r['noticia_id']][] = ['nombre' => $r['nombre'], 'color' => $r['color']];
    }
}

// Obtener categorías para filtro
$categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Obtener autores para filtro
$autores = $db->query("SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de Noticias</h1>
        <p class="page-subtitle">Administra todas las noticias publicadas</p>
    </div>
    <a href="editar-noticia.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nueva Noticia
    </a>
</div>

<!-- Filtros -->
<div class="card">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
            <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                <label class="form-label">Buscar</label>
                <input type="text" name="buscar" class="form-control" placeholder="Título o contenido..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Categoría</label>
                <select name="categoria" class="form-control">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $filtro_categoria == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Autor</label>
                <select name="autor" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($autores as $autor): ?>
                        <option value="<?php echo $autor['id']; ?>" <?php echo $filtro_autor == $autor['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($autor['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
            
            <a href="noticias.php" class="btn btn-secondary" style="background: #718096;">
                <i class="fas fa-times"></i> Limpiar
            </a>
        </form>
    </div>
</div>

<!-- Tabla de Noticias -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Título</th>
                        <th style="width: 140px;">Categoría</th>
                        <th style="width: 130px;">Comunas</th>
                        <th style="width: 140px;">Autor</th>
                        <th style="width: 100px;">Vistas</th>
                        <th style="width: 100px;">Estado</th>
                        <th style="width: 150px;">Fecha</th>
                        <th style="width: 160px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($noticias)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #718096;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                No se encontraron noticias
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($noticias as $noticia): ?>
                            <tr>
                                <td><strong>#<?php echo $noticia['id']; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars(truncate($noticia['titulo'], 50)); ?></strong>
                                    <?php if ($noticia['destacado']): ?>
                                        <i class="fas fa-star" style="color: #f59e0b;" title="Destacada"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $catBadges = $noticiaCategoriasMap[$noticia['id']] ?? [];
                                    if ($catBadges):
                                        foreach ($catBadges as $cb):
                                    ?>
                                    <span class="badge" style="background: <?php echo htmlspecialchars($cb['color']); ?>20; color: <?php echo htmlspecialchars($cb['color']); ?>; margin: 1px;">
                                        <?php echo htmlspecialchars($cb['nombre']); ?>
                                    </span>
                                    <?php endforeach; else: ?>
                                    <span class="badge" style="background: <?php echo $noticia['categoria_color']; ?>20; color: <?php echo $noticia['categoria_color']; ?>;">
                                        <?php echo htmlspecialchars($noticia['categoria_nombre']); ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ($noticiaComunasMap[$noticia['id']] ?? [] as $cn): ?>
                                    <span style="display:inline-block;background:#edf2ff;color:#3c4cad;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;margin:1px;"><?php echo htmlspecialchars($cn); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><?php echo htmlspecialchars($noticia['autor_nombre']); ?></td>
                                <td>
                                    <i class="fas fa-eye"></i> <?php echo number_format($noticia['vistas']); ?>
                                </td>
                                <td>
                                    <?php if ($noticia['publicado']): ?>
                                        <span class="badge badge-success">Publicado</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Borrador</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($noticia['fecha_publicacion']); ?></td>
                                <td>
                                    <a href="../noticia.php?id=<?php echo $noticia['id']; ?>" class="btn btn-sm" style="background: #4299e1; color: white;" target="_blank" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="editar-noticia.php?id=<?php echo $noticia['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="eliminarNoticia(<?php echo $noticia['id']; ?>)" class="btn btn-sm btn-danger" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function eliminarNoticia(id) {
    if (!confirm('¿Estás seguro de eliminar esta noticia? Esta acción no se puede deshacer.')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/eliminar-noticia.php',
        method: 'POST',
        data: { id: id },
        success: function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                alert('Noticia eliminada correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
