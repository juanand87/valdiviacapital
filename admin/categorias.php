<?php
$page_title = 'Gestión de Categorías';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Guardar o actualizar categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nombre = clean($_POST['nombre']);
    $slug = clean($_POST['slug']);
    $descripcion = clean($_POST['descripcion']);
    $color = clean($_POST['color']);
    $icono = clean($_POST['icono']);
    $orden = (int)$_POST['orden'];
    
    try {
        if ($id) {
            // Actualizar
            $stmt = $db->prepare("
                UPDATE categorias 
                SET nombre = ?, slug = ?, descripcion = ?, color = ?, icono = ?, orden = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $slug, $descripcion, $color, $icono, $orden, $id]);
            $mensaje = 'Categoría actualizada correctamente';
        } else {
            // Crear nueva
            $stmt = $db->prepare("
                INSERT INTO categorias (nombre, slug, descripcion, color, icono, orden) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nombre, $slug, $descripcion, $color, $icono, $orden]);
            $mensaje = 'Categoría creada correctamente';
        }
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Obtener categorías
$categorias = $db->query("SELECT * FROM categorias ORDER BY orden ASC, nombre ASC")->fetchAll();

// Si hay ID en GET, obtener datos para editar
$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$_GET['editar']]);
    $editando = $stmt->fetch();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de Categorías</h1>
        <p class="page-subtitle">Organiza las secciones del sitio</p>
    </div>
</div>

<?php if (isset($mensaje)): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div style="background: #fee; color: #c53030; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Formulario -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php echo $editando ? 'Editar Categoría' : 'Nueva Categoría'; ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?php echo $editando['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" required 
                           value="<?php echo $editando ? htmlspecialchars($editando['nombre']) : ''; ?>"
                           onkeyup="generarSlug(this.value)">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Slug (URL) *</label>
                    <input type="text" name="slug" id="slug" class="form-control" required 
                           value="<?php echo $editando ? htmlspecialchars($editando['slug']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3"><?php echo $editando ? htmlspecialchars($editando['descripcion']) : ''; ?></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Color *</label>
                        <input type="color" name="color" class="form-control" required 
                               value="<?php echo $editando ? $editando['color'] : '#2563eb'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Orden</label>
                        <input type="number" name="orden" class="form-control" 
                               value="<?php echo $editando ? $editando['orden'] : 0; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ícono (Font Awesome)</label>
                    <input type="text" name="icono" class="form-control" 
                           placeholder="fa-newspaper" 
                           value="<?php echo $editando ? htmlspecialchars($editando['icono']) : ''; ?>">
                    <small style="color: #718096; font-size: 12px;">
                        Busca íconos en <a href="https://fontawesome.com/icons" target="_blank">fontawesome.com/icons</a>
                    </small>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $editando ? 'Actualizar' : 'Crear'; ?>
                    </button>
                    
                    <?php if ($editando): ?>
                        <a href="categorias.php" class="btn" style="background: #718096; color: white;">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Lista de Categorías -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Categorías Existentes</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th style="width: 80px;">Color</th>
                            <th style="width: 120px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $cat): ?>
                            <tr>
                                <td>
                                    <?php if ($cat['icono']): ?>
                                        <i class="fas <?php echo $cat['icono']; ?>" style="color: <?php echo $cat['color']; ?>;"></i>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($cat['nombre']); ?></strong>
                                    <br>
                                    <small style="color: #718096;">/<?php echo $cat['slug']; ?></small>
                                </td>
                                <td>
                                    <div style="width: 30px; height: 30px; background: <?php echo $cat['color']; ?>; border-radius: 6px;"></div>
                                </td>
                                <td>
                                    <a href="?editar=<?php echo $cat['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="eliminarCategoria(<?php echo $cat['id']; ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function generarSlug(texto) {
    const slug = texto.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    document.getElementById('slug').value = slug;
}

function eliminarCategoria(id) {
    if (!confirm('¿Eliminar esta categoría? Las noticias asociadas quedarán sin categoría.')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/eliminar-categoria.php',
        method: 'POST',
        data: { id: id },
        success: function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                alert('Categoría eliminada');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
