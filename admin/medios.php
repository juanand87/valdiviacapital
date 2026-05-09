<?php
$page_title = 'Medios';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Paginación
$porPagina = 30;
$pagina    = max(1, (int)($_GET['p'] ?? 1));
$offset    = ($pagina - 1) * $porPagina;

// Filtro por tipo
$filtroTipo = $_GET['tipo'] ?? 'all';
$whereClause = '';
$params = [];
if ($filtroTipo === 'image') {
    $whereClause = "WHERE tipo_mime LIKE 'image/%'";
} elseif ($filtroTipo === 'document') {
    $whereClause = "WHERE tipo_mime NOT LIKE 'image/%'";
}

$total   = (int)$db->query("SELECT COUNT(*) FROM medios $whereClause")->fetchColumn();
$medios  = $db->query("SELECT * FROM medios $whereClause ORDER BY created_at DESC LIMIT $porPagina OFFSET $offset")->fetchAll();
$totalPags = (int)ceil($total / $porPagina);

function formatBytes(int $bytes): string {
    if ($bytes < 1024)     return $bytes . ' B';
    if ($bytes < 1048576)  return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}

function iconoMime(string $mime): string {
    if (str_starts_with($mime, 'image/')) return 'fa-file-image';
    if ($mime === 'application/pdf')       return 'fa-file-pdf';
    if (str_contains($mime, 'word'))       return 'fa-file-word';
    if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return 'fa-file-excel';
    if (str_contains($mime, 'powerpoint') || str_contains($mime, 'presentation')) return 'fa-file-powerpoint';
    return 'fa-file';
}
?>

<style>
.medios-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.medios-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; }
.medio-card {
    background:white; border:2px solid #e2e8f0; border-radius:10px;
    overflow:hidden; cursor:pointer; transition:all .18s; position:relative;
}
.medio-card:hover { border-color:#c8102e; box-shadow:0 4px 14px rgba(200,16,46,.15); transform:translateY(-2px); }
.medio-card.selected { border-color:#c8102e; box-shadow:0 0 0 3px rgba(200,16,46,.25); }
.medio-thumb {
    height:120px; display:flex; align-items:center; justify-content:center;
    background:#f7fafc; overflow:hidden;
}
.medio-thumb img { width:100%; height:100%; object-fit:cover; }
.medio-thumb .medio-icon { font-size:42px; color:#a0aec0; }
.medio-info { padding:8px 10px; }
.medio-nombre { font-size:11px; font-weight:600; color:#2d3748; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.medio-meta { font-size:10px; color:#a0aec0; margin-top:2px; }
.medio-delete { position:absolute; top:6px; right:6px; background:rgba(255,255,255,.9); border:none; border-radius:50%; width:26px; height:26px; display:none; align-items:center; justify-content:center; cursor:pointer; color:#c53030; font-size:12px; }
.medio-card:hover .medio-delete { display:flex; }

/* Upload zone */
.upload-zone {
    border:2px dashed #cbd5e0; border-radius:10px; padding:30px 20px;
    text-align:center; cursor:pointer; transition:all .2s; background:#fafafa; margin-bottom:20px;
}
.upload-zone:hover, .upload-zone.drag-over { border-color:#c8102e; background:#fff5f5; }
.upload-zone i { font-size:36px; color:#c8102e; margin-bottom:10px; display:block; }
.upload-zone p { color:#718096; font-size:14px; }
.upload-zone small { color:#a0aec0; font-size:12px; }
.upload-progress { display:none; margin-top:12px; }
.progress-bar-wrap { height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden; }
.progress-bar-fill { height:100%; background:#c8102e; width:0; transition:width .3s; border-radius:3px; }

/* Detail panel */
#detail-panel { display:none; position:fixed; right:0; top:0; height:100vh; width:300px; background:white; box-shadow:-4px 0 20px rgba(0,0,0,.12); z-index:1000; padding:24px; overflow-y:auto; }
#detail-panel .detail-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
#detail-panel .detail-img { width:100%; border-radius:8px; margin-bottom:14px; max-height:200px; object-fit:contain; background:#f7fafc; padding:8px; }
#detail-panel .detail-icon { font-size:64px; color:#a0aec0; text-align:center; padding:24px; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-photo-video"></i> Medios</h1>
        <p class="page-subtitle">Biblioteca de imágenes y documentos</p>
    </div>
</div>

<!-- Upload zone -->
<div class="upload-zone" id="upload-zone">
    <i class="fas fa-cloud-upload-alt"></i>
    <p><strong>Arrastra archivos aquí</strong> o haz clic para seleccionar</p>
    <small>Imágenes (JPG, PNG, GIF, WebP) · Documentos (PDF, Word, Excel, PowerPoint) · Máx. 10 MB</small>
    <input type="file" id="file-input" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" style="display:none;">
    <div class="upload-progress" id="upload-progress">
        <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progress-fill"></div></div>
        <p id="progress-text" style="margin-top:8px;font-size:13px;color:#718096;"></p>
    </div>
</div>

<!-- Toolbar -->
<div class="medios-toolbar">
    <div style="display:flex;gap:6px;">
        <a href="medios.php?tipo=all<?php echo $filtroTipo==='all'?'':'' ?>" class="btn <?php echo $filtroTipo==='all'?'btn-primary':'' ?>" style="<?php echo $filtroTipo==='all'?'':'background:#e2e8f0;color:#4a5568;'; ?>padding:7px 16px;font-size:13px;">
            Todos (<?php echo $total; ?>)
        </a>
        <a href="medios.php?tipo=image" class="btn" style="<?php echo $filtroTipo==='image'?'background:#c8102e;color:white;':'background:#e2e8f0;color:#4a5568;'; ?>padding:7px 16px;font-size:13px;">
            <i class="fas fa-images"></i> Imágenes
        </a>
        <a href="medios.php?tipo=document" class="btn" style="<?php echo $filtroTipo==='document'?'background:#c8102e;color:white;':'background:#e2e8f0;color:#4a5568;'; ?>padding:7px 16px;font-size:13px;">
            <i class="fas fa-file-alt"></i> Documentos
        </a>
    </div>
    <span style="margin-left:auto;font-size:13px;color:#718096;"><?php echo $total; ?> archivo<?php echo $total!=1?'s':''; ?></span>
</div>

<!-- Grid -->
<?php if (empty($medios)): ?>
<div style="text-align:center;padding:60px 20px;color:#a0aec0;">
    <i class="fas fa-photo-video" style="font-size:56px;display:block;margin-bottom:14px;opacity:.3;"></i>
    <p>No hay archivos todavía. ¡Sube el primero!</p>
</div>
<?php else: ?>
<div class="medios-grid" id="medios-grid">
    <?php foreach ($medios as $m): ?>
    <div class="medio-card" data-id="<?php echo $m['id']; ?>"
         data-url="<?php echo htmlspecialchars(SITE_URL . '/' . $m['ruta']); ?>"
         data-nombre="<?php echo htmlspecialchars($m['nombre_original']); ?>"
         data-mime="<?php echo htmlspecialchars($m['tipo_mime']); ?>"
         data-ancho="<?php echo $m['ancho'] ?? ''; ?>"
         data-alto="<?php echo $m['alto'] ?? ''; ?>"
         data-tamano="<?php echo $m['tamano']; ?>"
         data-fecha="<?php echo $m['created_at']; ?>">
        <div class="medio-thumb">
            <?php if (str_starts_with($m['tipo_mime'], 'image/')): ?>
                <img src="<?php echo htmlspecialchars(SITE_URL . '/' . $m['ruta']); ?>" alt="<?php echo htmlspecialchars($m['nombre_original']); ?>" loading="lazy">
            <?php else: ?>
                <i class="fas <?php echo iconoMime($m['tipo_mime']); ?> medio-icon"></i>
            <?php endif; ?>
        </div>
        <div class="medio-info">
            <div class="medio-nombre" title="<?php echo htmlspecialchars($m['nombre_original']); ?>"><?php echo htmlspecialchars($m['nombre_original']); ?></div>
            <div class="medio-meta"><?php echo formatBytes($m['tamano']); ?><?php echo $m['ancho'] ? " · {$m['ancho']}×{$m['alto']}" : ''; ?></div>
        </div>
        <button class="medio-delete" data-id="<?php echo $m['id']; ?>" title="Eliminar"><i class="fas fa-trash"></i></button>
    </div>
    <?php endforeach; ?>
</div>

<!-- Paginación -->
<?php if ($totalPags > 1): ?>
<nav style="display:flex;justify-content:center;gap:8px;margin:30px 0;">
    <?php if ($pagina > 1): ?>
        <a href="medios.php?tipo=<?php echo $filtroTipo; ?>&p=<?php echo $pagina-1; ?>" style="padding:7px 16px;border:2px solid #c8102e;border-radius:6px;color:#c8102e;font-weight:600;">&laquo;</a>
    <?php endif; ?>
    <?php for ($i=max(1,$pagina-3); $i<=min($totalPags,$pagina+3); $i++): ?>
        <a href="medios.php?tipo=<?php echo $filtroTipo; ?>&p=<?php echo $i; ?>" style="padding:7px 14px;border-radius:6px;font-weight:600;<?php echo $i===$pagina?'background:#c8102e;color:white;':'border:2px solid #e2e8f0;color:#4a5568;'; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
    <?php if ($pagina < $totalPags): ?>
        <a href="medios.php?tipo=<?php echo $filtroTipo; ?>&p=<?php echo $pagina+1; ?>" style="padding:7px 16px;border:2px solid #c8102e;border-radius:6px;color:#c8102e;font-weight:600;">&raquo;</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<!-- Panel de detalle -->
<div id="detail-panel">
    <div class="detail-header">
        <strong style="font-size:15px;">Detalles</strong>
        <button onclick="cerrarDetalle()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#718096;">&times;</button>
    </div>
    <div id="detail-preview"></div>
    <div id="detail-info"></div>
    <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
        <button id="btn-copiar-url" class="btn btn-primary" style="width:100%;" onclick="copiarUrl()">
            <i class="fas fa-copy"></i> Copiar URL
        </button>
        <button id="btn-eliminar-det" class="btn" style="width:100%;background:#fff5f5;color:#c53030;border:1px solid #fed7d7;" onclick="eliminarSeleccionado()">
            <i class="fas fa-trash"></i> Eliminar archivo
        </button>
    </div>
    <div style="margin-top:16px;">
        <label style="font-size:12px;color:#718096;display:block;margin-bottom:4px;">URL del archivo</label>
        <input id="detail-url-input" type="text" style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;" readonly onclick="this.select()">
    </div>
</div>

<script>
const siteUrl   = <?php echo json_encode(SITE_URL); ?>;
let selectedCard = null;
let selectedData = null;

// ── Upload ──────────────────────────────────────────────────────────
const zone    = document.getElementById('upload-zone');
const input   = document.getElementById('file-input');
const progress = document.getElementById('upload-progress');
const fill    = document.getElementById('progress-fill');
const txt     = document.getElementById('progress-text');

zone.addEventListener('click', () => input.click());
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('drag-over'); subirArchivos(e.dataTransfer.files); });
input.addEventListener('change', () => subirArchivos(input.files));

function subirArchivos(files) {
    if (!files.length) return;
    const arr = Array.from(files);
    let done = 0;
    progress.style.display = 'block';

    function subirUno(file) {
        const fd = new FormData();
        fd.append('archivo', file);
        txt.textContent = 'Subiendo ' + (done+1) + '/' + arr.length + ': ' + file.name;
        fill.style.width = Math.round((done/arr.length)*100) + '%';

        fetch('ajax/subir-medio.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(res => {
                done++;
                if (res.ok) {
                    agregarMedioAlGrid(res);
                } else {
                    alert('Error al subir ' + file.name + ': ' + (res.error || 'desconocido'));
                }
                if (done < arr.length) subirUno(arr[done]);
                else {
                    fill.style.width = '100%';
                    txt.textContent = done + ' archivo(s) subido(s) correctamente';
                    setTimeout(() => { progress.style.display='none'; fill.style.width='0'; }, 2500);
                }
            })
            .catch(() => {
                done++;
                alert('Error de red al subir ' + file.name);
                if (done < arr.length) subirUno(arr[done]);
            });
    }
    subirUno(arr[0]);
    input.value = '';
}

function agregarMedioAlGrid(data) {
    const grid = document.getElementById('medios-grid');
    if (!grid) { location.reload(); return; }
    const div = document.createElement('div');
    div.className = 'medio-card';
    div.dataset.id     = data.id;
    div.dataset.url    = data.url;
    div.dataset.nombre = data.nombre;
    div.dataset.mime   = data.mime;
    div.dataset.ancho  = data.ancho || '';
    div.dataset.alto   = data.alto  || '';
    div.dataset.tamano = data.tamano;
    div.dataset.fecha  = new Date().toLocaleString();
    const isImg = data.mime.startsWith('image/');
    div.innerHTML = `
        <div class="medio-thumb">
            ${isImg ? `<img src="${data.url}" alt="${data.nombre}" loading="lazy">` : '<i class="fas fa-file medio-icon"></i>'}
        </div>
        <div class="medio-info">
            <div class="medio-nombre" title="${data.nombre}">${data.nombre}</div>
            <div class="medio-meta">${formatBytes(data.tamano)}${data.ancho ? ' · '+data.ancho+'×'+data.alto : ''}</div>
        </div>
        <button class="medio-delete" data-id="${data.id}" title="Eliminar"><i class="fas fa-trash"></i></button>`;
    grid.prepend(div);
    inicializarCard(div);
}

function formatBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(2) + ' MB';
}

// ── Cards ────────────────────────────────────────────────────────────
document.querySelectorAll('.medio-card').forEach(card => inicializarCard(card));

function inicializarCard(card) {
    card.addEventListener('click', function(e) {
        if (e.target.closest('.medio-delete')) return;
        document.querySelectorAll('.medio-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        selectedCard = card;
        selectedData = card.dataset;
        abrirDetalle(card.dataset);
    });
    const btn = card.querySelector('.medio-delete');
    if (btn) btn.addEventListener('click', e => { e.stopPropagation(); eliminarMedio(btn.dataset.id, card); });
}

// ── Detalle ──────────────────────────────────────────────────────────
function abrirDetalle(d) {
    const panel = document.getElementById('detail-panel');
    const isImg = d.mime && d.mime.startsWith('image/');
    document.getElementById('detail-preview').innerHTML = isImg
        ? `<img src="${d.url}" class="detail-img" alt="${d.nombre}">`
        : `<div class="detail-icon"><i class="fas fa-file-alt"></i></div>`;
    document.getElementById('detail-info').innerHTML = `
        <table style="font-size:12px;width:100%;border-collapse:collapse;">
            <tr><td style="color:#718096;padding:4px 0;">Nombre</td><td style="padding:4px 0;word-break:break-all;">${d.nombre}</td></tr>
            ${d.ancho ? `<tr><td style="color:#718096;padding:4px 0;">Dimensiones</td><td style="padding:4px 0;">${d.ancho}×${d.alto} px</td></tr>` : ''}
            <tr><td style="color:#718096;padding:4px 0;">Tipo</td><td style="padding:4px 0;">${d.mime}</td></tr>
            <tr><td style="color:#718096;padding:4px 0;">Tamaño</td><td style="padding:4px 0;">${formatBytes(parseInt(d.tamano))}</td></tr>
        </table>`;
    document.getElementById('detail-url-input').value = d.url;
    panel.style.display = 'block';
}

function cerrarDetalle() {
    document.getElementById('detail-panel').style.display = 'none';
    document.querySelectorAll('.medio-card').forEach(c => c.classList.remove('selected'));
    selectedCard = null; selectedData = null;
}

function copiarUrl() {
    const inp = document.getElementById('detail-url-input');
    inp.select();
    navigator.clipboard.writeText(inp.value).then(() => {
        const btn = document.getElementById('btn-copiar-url');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
        btn.style.background = '#38a169';
        setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; }, 2000);
    });
}

function eliminarSeleccionado() {
    if (!selectedCard) return;
    eliminarMedio(selectedData.id, selectedCard);
}

function eliminarMedio(id, card) {
    if (!confirm('¿Eliminar este archivo? Esta acción no se puede deshacer.')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('ajax/eliminar-medio.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                card.remove();
                cerrarDetalle();
            } else {
                alert('Error al eliminar: ' + (res.error || 'desconocido'));
            }
        });
}
</script>

<?php include 'includes/footer.php'; ?>
