<?php
$page_title = 'Gestion de Eventos';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

$categoriasEvento = ['General', 'Cultura', 'Musica', 'Deporte', 'Municipal', 'Familiar', 'Feria', 'Educacion'];

function toDatetimeLocal(?string $value): string {
    if (!$value) return '';
    $ts = strtotime($value);
    if (!$ts) return '';
    return date('Y-m-d\\TH:i', $ts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'guardar';

    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare('DELETE FROM eventos WHERE id = ?')->execute([$id]);
            $mensaje = 'Evento eliminado correctamente';
        }
    } else {
        $id          = (int)($_POST['id'] ?? 0);
        $titulo      = trim($_POST['titulo'] ?? '');
        $slugInput   = trim($_POST['slug'] ?? '');
        $slug        = $slugInput !== '' ? generateSlug($slugInput) : generateSlug($titulo);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
        $fechaFin    = trim($_POST['fecha_fin'] ?? '');
        $lugar       = trim($_POST['lugar'] ?? '');
        $direccion   = trim($_POST['direccion'] ?? '');
        $comunaId    = (int)($_POST['comuna_id'] ?? 0);
        $categoria   = trim($_POST['categoria'] ?? 'General');
        $imagenUrl   = trim($_POST['imagen_url'] ?? '');
        $urlExterno  = trim($_POST['url_externo'] ?? '');
        $organizador = trim($_POST['organizador'] ?? '');
        $gratuito    = isset($_POST['gratuito']) ? 1 : 0;
        $precio      = trim($_POST['precio'] ?? '');
        $destacado   = isset($_POST['destacado']) ? 1 : 0;
        $activo      = isset($_POST['activo']) ? 1 : 0;
        $autorId     = (int)($_SESSION['admin_id'] ?? 0) ?: null;

        if ($titulo === '' || $slug === '' || $fechaInicio === '' || $lugar === '') {
            $error = 'Completa los campos obligatorios: titulo, slug, fecha inicio y lugar.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $db->prepare(
                        'UPDATE eventos
                         SET titulo = ?, slug = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, lugar = ?, direccion = ?,
                             comuna_id = ?, categoria = ?, imagen_url = ?, url_externo = ?, organizador = ?, gratuito = ?,
                             precio = ?, destacado = ?, activo = ?
                         WHERE id = ?'
                    );
                    $stmt->execute([
                        $titulo,
                        $slug,
                        $descripcion !== '' ? $descripcion : null,
                        $fechaInicio,
                        $fechaFin !== '' ? $fechaFin : null,
                        $lugar,
                        $direccion !== '' ? $direccion : null,
                        $comunaId > 0 ? $comunaId : null,
                        $categoria,
                        $imagenUrl !== '' ? $imagenUrl : null,
                        $urlExterno !== '' ? $urlExterno : null,
                        $organizador !== '' ? $organizador : null,
                        $gratuito,
                        $precio !== '' ? $precio : null,
                        $destacado,
                        $activo,
                        $id,
                    ]);
                    $mensaje = 'Evento actualizado correctamente';
                } else {
                    $stmt = $db->prepare(
                        'INSERT INTO eventos
                         (titulo, slug, descripcion, fecha_inicio, fecha_fin, lugar, direccion, comuna_id, categoria, imagen_url,
                          url_externo, organizador, gratuito, precio, destacado, activo, autor_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $titulo,
                        $slug,
                        $descripcion !== '' ? $descripcion : null,
                        $fechaInicio,
                        $fechaFin !== '' ? $fechaFin : null,
                        $lugar,
                        $direccion !== '' ? $direccion : null,
                        $comunaId > 0 ? $comunaId : null,
                        $categoria,
                        $imagenUrl !== '' ? $imagenUrl : null,
                        $urlExterno !== '' ? $urlExterno : null,
                        $organizador !== '' ? $organizador : null,
                        $gratuito,
                        $precio !== '' ? $precio : null,
                        $destacado,
                        $activo,
                        $autorId,
                    ]);
                    $mensaje = 'Evento creado correctamente';
                }
            } catch (PDOException $e) {
                $error = 'Error al guardar evento: ' . $e->getMessage();
            }
        }
    }
}

$comunas = $db->query('SELECT id, nombre FROM comunas ORDER BY nombre')->fetchAll();
$eventos = $db->query(
    'SELECT e.*, c.nombre as comuna_nombre
     FROM eventos e
     LEFT JOIN comunas c ON c.id = e.comuna_id
     ORDER BY e.fecha_inicio DESC'
)->fetchAll();

$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare('SELECT * FROM eventos WHERE id = ?');
    $stmt->execute([(int)$_GET['editar']]);
    $editando = $stmt->fetch();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestion de Eventos</h1>
        <p class="page-subtitle">Crea, edita y publica la agenda del diario</p>
    </div>
</div>

<?php if (isset($mensaje)): ?>
<div style="background:#d1fae5;color:#065f46;padding:15px;border-radius:8px;margin-bottom:20px;">
    <i class="fas fa-check-circle"></i> <?php echo clean($mensaje); ?>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div style="background:#fee;color:#c53030;padding:15px;border-radius:8px;margin-bottom:20px;">
    <i class="fas fa-exclamation-circle"></i> <?php echo clean($error); ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1.15fr 1fr;gap:20px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo $editando ? 'Editar Evento' : 'Nuevo Evento'; ?></h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?php echo (int)$editando['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Titulo *</label>
                    <input type="text" class="form-control" name="titulo" required
                           value="<?php echo $editando ? clean($editando['titulo']) : ''; ?>"
                           onkeyup="generarSlug(this.value)">
                </div>

                <div class="form-group">
                    <label class="form-label">Slug *</label>
                    <input type="text" class="form-control" name="slug" id="slug" required
                           value="<?php echo $editando ? clean($editando['slug']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Descripcion</label>
                    <textarea class="form-control" name="descripcion" rows="4"><?php echo $editando ? clean($editando['descripcion']) : ''; ?></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="form-group">
                        <label class="form-label">Fecha inicio *</label>
                        <input type="datetime-local" class="form-control" name="fecha_inicio" required
                               value="<?php echo $editando ? toDatetimeLocal($editando['fecha_inicio']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha fin</label>
                        <input type="datetime-local" class="form-control" name="fecha_fin"
                               value="<?php echo $editando ? toDatetimeLocal($editando['fecha_fin']) : ''; ?>">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="form-group">
                        <label class="form-label">Lugar *</label>
                        <input type="text" class="form-control" name="lugar" required
                               value="<?php echo $editando ? clean($editando['lugar']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Comuna</label>
                        <select class="form-control" name="comuna_id">
                            <option value="">Seleccionar comuna</option>
                            <?php foreach ($comunas as $com): ?>
                            <option value="<?php echo (int)$com['id']; ?>"
                                <?php echo $editando && (int)$editando['comuna_id'] === (int)$com['id'] ? 'selected' : ''; ?>>
                                <?php echo clean($com['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <select class="form-control" name="categoria">
                            <?php foreach ($categoriasEvento as $cat): ?>
                            <option value="<?php echo clean($cat); ?>"
                                <?php echo $editando && $editando['categoria'] === $cat ? 'selected' : ''; ?>>
                                <?php echo clean($cat); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Organizador</label>
                        <input type="text" class="form-control" name="organizador"
                               value="<?php echo $editando ? clean($editando['organizador']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Direccion</label>
                    <input type="text" class="form-control" name="direccion"
                           value="<?php echo $editando ? clean($editando['direccion']) : ''; ?>">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="form-group">
                        <label class="form-label">Imagen URL</label>
                        <input type="url" class="form-control" name="imagen_url"
                               placeholder="https://..."
                               value="<?php echo $editando ? clean($editando['imagen_url']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Enlace externo</label>
                        <input type="url" class="form-control" name="url_externo"
                               placeholder="https://..."
                               value="<?php echo $editando ? clean($editando['url_externo']) : ''; ?>">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="form-group">
                        <label class="form-label">Precio</label>
                        <input type="text" class="form-control" name="precio"
                               placeholder="$5.000 o Entrada liberada"
                               value="<?php echo $editando ? clean($editando['precio']) : ''; ?>">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;gap:16px;">
                        <label><input type="checkbox" name="gratuito" <?php echo (!$editando || (int)$editando['gratuito'] === 1) ? 'checked' : ''; ?>> Gratuito</label>
                        <label><input type="checkbox" name="destacado" <?php echo $editando && (int)$editando['destacado'] === 1 ? 'checked' : ''; ?>> Destacado</label>
                        <label><input type="checkbox" name="activo" <?php echo (!$editando || (int)$editando['activo'] === 1) ? 'checked' : ''; ?>> Activo</label>
                    </div>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $editando ? 'Actualizar' : 'Crear'; ?></button>
                    <?php if ($editando): ?>
                    <a href="eventos.php" class="btn" style="background:#718096;color:#fff;"><i class="fas fa-times"></i> Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Eventos Creados</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Titulo</th>
                            <th style="width:120px;">Fecha</th>
                            <th style="width:90px;">Estado</th>
                            <th style="width:120px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$eventos): ?>
                        <tr><td colspan="4" style="text-align:center;padding:24px;color:#718096;">No hay eventos</td></tr>
                        <?php else: ?>
                            <?php foreach ($eventos as $ev): ?>
                            <tr>
                                <td>
                                    <strong><?php echo clean(truncate($ev['titulo'], 46)); ?></strong>
                                    <br>
                                    <small style="color:#718096;"><?php echo clean($ev['categoria']); ?><?php if ($ev['comuna_nombre']): ?> · <?php echo clean($ev['comuna_nombre']); ?><?php endif; ?></small>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($ev['fecha_inicio'])); ?></td>
                                <td>
                                    <?php if ((int)$ev['activo'] === 1): ?>
                                    <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                    <span class="badge badge-warning">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn btn-sm" style="background:#4299e1;color:#fff;" target="_blank" href="../evento.php?slug=<?php echo clean($ev['slug']); ?>"><i class="fas fa-eye"></i></a>
                                    <a class="btn btn-sm btn-primary" href="eventos.php?editar=<?php echo (int)$ev['id']; ?>"><i class="fas fa-edit"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este evento?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>">
                                        <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
    const field = document.getElementById('slug');
    if (field) field.value = slug;
}
</script>

<?php include 'includes/footer.php'; ?>
