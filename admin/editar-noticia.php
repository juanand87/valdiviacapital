<?php
$page_title = 'Editor de Noticia';
require_once '../includes/config.php';
require_once 'includes/auth.php';
verificarSesion();

$db = getDB();
$editando = false;
$noticia = null;

// Si hay ID, cargar noticia para editar
if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM noticias WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $noticia = $stmt->fetch();
    
    if ($noticia) {
        $editando = true;
    }
}

// Guardar noticia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $titulo = clean($_POST['titulo']);
    $slug = clean($_POST['slug']);
    $bajada = clean($_POST['bajada']);
    $contenido = $_POST['contenido']; // No limpiar completamente porque tiene HTML
    $categoria_id = (int)$_POST['categoria_id'];
    $autor_id = $_SESSION['admin_id'];
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $publicado = isset($_POST['publicado']) ? 1 : 0;
    $imagen_principal = $_POST['imagen_principal'] ?? '';
    
    try {
        if ($id) {
            // Actualizar
            $stmt = $db->prepare("
                UPDATE noticias 
                SET titulo = ?, slug = ?, bajada = ?, contenido = ?, 
                    categoria_id = ?, destacado = ?, publicado = ?, 
                    imagen_principal = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$titulo, $slug, $bajada, $contenido, $categoria_id, $destacado, $publicado, $imagen_principal, $id]);
            $mensaje = 'Noticia actualizada correctamente';
            $noticia_id = $id;
        } else {
            // Crear nueva
            $stmt = $db->prepare("
                INSERT INTO noticias (titulo, slug, bajada, contenido, categoria_id, autor_id, destacado, publicado, imagen_principal)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$titulo, $slug, $bajada, $contenido, $categoria_id, $autor_id, $destacado, $publicado, $imagen_principal]);
            $noticia_id = $db->lastInsertId();
            $mensaje = 'Noticia creada correctamente';
        }
        
        // Guardar comunas asociadas
        $db->prepare("DELETE FROM noticias_comunas WHERE noticia_id = ?")->execute([$noticia_id]);
        $comunasPost = array_filter(array_map('intval', $_POST['comunas'] ?? []));
        if ($comunasPost) {
            $stmtCom = $db->prepare("INSERT IGNORE INTO noticias_comunas (noticia_id, comuna_id) VALUES (?, ?)");
            foreach ($comunasPost as $cid) {
                $stmtCom->execute([$noticia_id, $cid]);
            }
        }

        // Redirigir a editar la noticia creada/actualizada
        header("Location: editar-noticia.php?id=$noticia_id&success=1");
        exit;
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

include 'includes/header.php';

// Obtener categorías
$categorias = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Obtener comunas y las ya asignadas a esta noticia
$comunas = $db->query("SELECT * FROM comunas ORDER BY nombre")->fetchAll();
$comunas_seleccionadas = [];
if ($editando) {
    $stmtCsel = $db->prepare("SELECT comuna_id FROM noticias_comunas WHERE noticia_id = ?");
    $stmtCsel->execute([$noticia['id']]);
    $comunas_seleccionadas = $stmtCsel->fetchAll(PDO::FETCH_COLUMN);
}
?>

<style>
.editor-container {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 20px;
}

@media (max-width: 1024px) {
    .editor-container {
        grid-template-columns: 1fr;
    }
}

.ql-editor {
    min-height: 400px;
    font-size: 16px;
}
</style>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $editando ? 'Editar Noticia' : 'Nueva Noticia'; ?></h1>
        <p class="page-subtitle"><?php echo $editando ? 'Modifica la información de la noticia' : 'Crea una nueva noticia'; ?></p>
    </div>
    <a href="noticias.php" class="btn" style="background: #718096; color: white;">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-check-circle"></i> Noticia guardada correctamente
        <?php
            $slugGuardado = $db->query("SELECT slug FROM noticias WHERE id = " . (int)$_GET['id'])->fetchColumn();
        ?>
        <a href="../noticia.php?slug=<?php echo htmlspecialchars($slugGuardado); ?>" target="_blank" style="margin-left: 15px;">
            <i class="fas fa-external-link-alt"></i> Ver en el sitio
        </a>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div style="background: #fee; color: #c53030; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<form method="POST" id="formNoticia">
    <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?php echo $noticia['id']; ?>">
    <?php endif; ?>
    
    <div class="editor-container">
        <!-- Columna Principal -->
        <div>
            <div class="card">
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" class="form-control" required 
                               style="font-size: 20px; font-weight: 600;"
                               value="<?php echo $editando ? htmlspecialchars($noticia['titulo']) : ''; ?>"
                               onkeyup="generarSlug(this.value)">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Slug (URL) *</label>
                        <input type="text" name="slug" id="slug" class="form-control" required 
                               value="<?php echo $editando ? htmlspecialchars($noticia['slug']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Bajada / Resumen</label>
                        <textarea name="bajada" class="form-control" rows="3" 
                                  placeholder="Resumen breve de la noticia que aparece en las tarjetas..."><?php echo $editando ? htmlspecialchars($noticia['bajada']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Contenido *</label>
                        <div id="editor" style="background: white;"><?php echo $editando ? $noticia['contenido'] : ''; ?></div>
                        <textarea name="contenido" id="contenido" style="display: none;"></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar de Opciones -->
        <div>
            <!-- Publicar -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #f7fafc;">
                    <h3 class="card-title" style="font-size: 15px;">Publicar</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="publicado" value="1" 
                                   <?php echo ($editando && $noticia['publicado']) || !$editando ? 'checked' : ''; ?>>
                            <span>Publicar inmediatamente</span>
                        </label>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="destacado" value="1" 
                                   <?php echo $editando && $noticia['destacado'] ? 'checked' : ''; ?>>
                            <span><i class="fas fa-star" style="color: #f59e0b;"></i> Noticia destacada</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> <?php echo $editando ? 'Actualizar' : 'Publicar'; ?>
                    </button>

                    <button type="button" onclick="abrirVistaPrevia()" class="btn" style="width:100%;margin-top:10px;background:#6366f1;color:white;">
                        <i class="fas fa-eye"></i> Vista previa
                    </button>

                    <?php if ($editando): ?>
                        <a href="../noticia.php?slug=<?php echo htmlspecialchars($noticia['slug']); ?>" target="_blank" 
                           class="btn" style="width: 100%; margin-top: 10px; background: #4299e1; color: white;">
                            <i class="fas fa-external-link-alt"></i> Ver noticia publicada
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Categoría -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #f7fafc;">
                    <h3 class="card-title" style="font-size: 15px;">Categoría</h3>
                </div>
                <div class="card-body">
                    <select name="categoria_id" class="form-control" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo $editando && $noticia['categoria_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Comunas -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header" style="background: #f7fafc;">
                    <h3 class="card-title" style="font-size: 15px;"><i class="fas fa-map-marker-alt"></i> Comuna relacionada</h3>
                </div>
                <div class="card-body" style="padding: 12px 16px;">
                    <select id="select-comunas" name="comunas[]" multiple placeholder="Escribe para buscar...">
                        <?php foreach ($comunas as $c): ?>
                        <option value="<?php echo $c['id']; ?>"
                                <?php echo in_array($c['id'], $comunas_seleccionadas) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Imagen Destacada -->
            <div class="card">
                <div class="card-header" style="background: #f7fafc;">
                    <h3 class="card-title" style="font-size: 15px;">Imagen Destacada</h3>
                </div>
                <div class="card-body">
                    <div id="imagen-preview" style="margin-bottom: 15px;">
                        <?php if ($editando && $noticia['imagen_principal']): ?>
                            <img src="<?php echo htmlspecialchars($noticia['imagen_principal']); ?>" 
                                 style="width: 100%; border-radius: 8px;" id="preview-img">
                        <?php else: ?>
                            <div id="preview-placeholder" style="background: #e2e8f0; height: 150px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #718096;">
                                <i class="fas fa-image" style="font-size: 48px;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="imagen_principal" id="imagen_principal" class="form-control" 
                               placeholder="URL de la imagen o selecciona de Medios"
                               value="<?php echo $editando ? htmlspecialchars($noticia['imagen_principal']) : ''; ?>"
                               onchange="actualizarPreview(this.value)">
                        <button type="button" onclick="abrirMediaPicker('imagen_principal','image')" class="btn btn-primary" style="white-space:nowrap;padding:0 14px;" title="Seleccionar de la biblioteca de medios">
                            <i class="fas fa-photo-video"></i>
                        </button>
                    </div>
                    <small style="color: #718096; font-size: 12px; display: block; margin-top: 8px;">
                        Selecciona de la biblioteca de Medios o pega una URL (Unsplash, Pexels, etc.)
                    </small>
                </div>
            </div>
            
            <?php if ($editando): ?>
            <!-- Estadísticas -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-header" style="background: #f7fafc;">
                    <h3 class="card-title" style="font-size: 15px;">Estadísticas</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 10px;">
                        <i class="fas fa-eye"></i> <strong><?php echo number_format($noticia['vistas']); ?></strong> vistas
                    </div>
                    <div style="font-size: 13px; color: #718096;">
                        Publicado: <?php echo formatDate($noticia['fecha_publicacion']); ?>
                    </div>
                    <?php if ($noticia['updated_at'] !== $noticia['created_at']): ?>
                        <div style="font-size: 13px; color: #718096;">
                            Actualizado: <?php echo timeAgo($noticia['updated_at']); ?>
                        </div>
                    <?php endif; ?>
                    <canvas id="chart-vistas" height="110" style="margin-top:18px;display:block;width:100%;"></canvas>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* Tom Select — ajuste visual al admin */
#select-comunas + .ts-wrapper { margin-top: 0; }
.ts-wrapper { font-size: 13px; }
.ts-control { border: 1px solid #e2e8f0 !important; border-radius: 8px !important; padding: 6px 8px !important; min-height: 42px; gap: 4px; }
.ts-control input { font-size: 13px !important; }
.ts-control .item {
    background: #edf2ff !important; color: #3c4cad !important;
    border: 1px solid #c3d0f8 !important; border-radius: 20px !important;
    padding: 2px 10px 2px 8px !important; font-weight: 600; font-size: 12px;
    display: flex; align-items: center; gap: 4px;
}
.ts-control .item .remove { color: #3c4cad !important; font-size: 14px !important; margin-left: 4px; opacity: .7; }
.ts-control .item .remove:hover { opacity: 1; }
.ts-dropdown { border: 1px solid #e2e8f0 !important; border-radius: 8px !important; box-shadow: 0 4px 16px rgba(0,0,0,.1) !important; font-size: 13px; }
.ts-dropdown .option { padding: 9px 14px !important; }
.ts-dropdown .option:hover, .ts-dropdown .option.active { background: #edf2ff !important; color: #3c4cad !important; }
.ts-dropdown .highlight { background: #c3d0f8 !important; border-radius: 3px; }
</style>

<!-- Modal Vista Previa -->
<div id="modal-preview" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;overflow-y:auto;padding:30px 16px;">
    <div style="max-width:820px;margin:0 auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="background:#c8102e;color:white;padding:14px 24px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:700;font-size:15px;"><i class="fas fa-eye"></i> Vista Previa</span>
            <button onclick="cerrarVistaPrevia()" style="background:rgba(255,255,255,0.2);border:none;color:white;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:16px;">&times;</button>
        </div>
        <div style="padding:32px;">
            <div id="preview-cat-badge" style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:white;background:#c8102e;margin-bottom:16px;"></div>
            <h1 id="preview-titulo" style="font-size:2rem;font-weight:800;line-height:1.3;margin-bottom:16px;color:#222;"></h1>
            <p id="preview-bajada" style="font-size:1.15rem;color:#666;line-height:1.7;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #eee;"></p>
            <div style="display:flex;gap:20px;font-size:13px;color:#888;margin-bottom:24px;flex-wrap:wrap;">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['admin_nombre'] ?? 'Redacción'); ?></span>
                <span><i class="far fa-clock"></i> Ahora</span>
                <span><i class="fas fa-book-open"></i> <span id="preview-tiempo">1</span> min de lectura</span>
            </div>
            <div id="preview-imagen-wrap" style="margin-bottom:24px;display:none;">
                <img id="preview-imagen" src="" alt="" style="width:100%;border-radius:8px;">
            </div>
            <div id="preview-contenido" style="font-size:1.05rem;line-height:1.8;color:#333;"></div>
        </div>
    </div>
</div>
<script>
// Tom Select — tag input para comunas
new TomSelect('#select-comunas', {
    plugins: ['remove_button'],
    maxOptions: 12,
    placeholder: 'Escribe para buscar una comuna…',
    render: {
        item: function(data) {
            return '<div><i class="fas fa-map-marker-alt" style="font-size:9px;margin-right:4px;opacity:.7;"></i>' + data.text + '</div>';
        }
    }
});

// Inicializar Quill
var quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            [{ 'header': [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'color': [] }, { 'background': [] }],
            ['link', 'image', 'video'],
            ['clean']
        ]
    },
    placeholder: 'Escribe el contenido de la noticia aquí...'
});

// Guardar contenido en textarea oculto antes de enviar
document.getElementById('formNoticia').onsubmit = function() {
    document.getElementById('contenido').value = quill.root.innerHTML;
    return true;
};

// Generar slug
function generarSlug(texto) {
    const slug = texto.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    document.getElementById('slug').value = slug;
}

// Preview de imagen
function actualizarPreview(url) {
    if (!url) return;
    
    const placeholder = document.getElementById('preview-placeholder');
    let img = document.getElementById('preview-img');
    
    if (!img) {
        img = document.createElement('img');
        img.id = 'preview-img';
        img.style.cssText = 'width: 100%; border-radius: 8px;';
        document.getElementById('imagen-preview').innerHTML = '';
        document.getElementById('imagen-preview').appendChild(img);
    }
    
    img.src = url;
}

// Vista previa
function abrirVistaPrevia() {
    const titulo   = document.querySelector('[name=titulo]').value || 'Sin título';
    const bajada   = document.querySelector('[name=bajada]').value || '';
    const imagen   = document.getElementById('imagen_principal').value;
    const catSel   = document.querySelector('[name=categoria_id]');
    const catNombre = catSel && catSel.selectedIndex > 0 ? catSel.options[catSel.selectedIndex].text : '';
    const contenidoHTML = quill.root.innerHTML;
    const palabras = quill.getText().trim().split(/\s+/).length;
    const tLect   = Math.max(1, Math.round(palabras / 200));

    document.getElementById('preview-titulo').textContent   = titulo;
    document.getElementById('preview-bajada').textContent   = bajada;
    document.getElementById('preview-cat-badge').textContent = catNombre;
    document.getElementById('preview-contenido').innerHTML  = contenidoHTML;
    document.getElementById('preview-tiempo').textContent   = tLect;

    const imgWrap = document.getElementById('preview-imagen-wrap');
    if (imagen) {
        document.getElementById('preview-imagen').src = imagen;
        imgWrap.style.display = 'block';
    } else {
        imgWrap.style.display = 'none';
    }

    document.getElementById('modal-preview').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function cerrarVistaPrevia() {
    document.getElementById('modal-preview').style.display = 'none';
    document.body.style.overflow = '';
}

// Cerrar modal al click fuera del contenido
document.getElementById('modal-preview').addEventListener('click', function(e) {
    if (e.target === this) cerrarVistaPrevia();
});

<?php if ($editando): ?>
// Gráfico de vistas diarias
fetch('ajax/vistas-chart.php?id=<?php echo (int)$noticia['id']; ?>')
    .then(r => r.json())
    .then(function(res) {
        if (!res.labels) return;
        const canvas = document.getElementById('chart-vistas');
        if (!canvas) return;
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: res.labels,
                datasets: [{
                    label: 'Vistas',
                    data: res.data,
                    borderColor: '#c8102e',
                    backgroundColor: 'rgba(200,16,46,0.08)',
                    borderWidth: 2,
                    pointRadius: 3,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } }, grid: { color: '#f0f0f0' } },
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    })
    .catch(function() {});
<?php endif; ?>
</script>

<?php include 'includes/media-picker.php'; ?>
<?php include 'includes/footer.php'; ?>
