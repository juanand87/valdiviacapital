<?php
/**
 * Componente reutilizable: Selector de medios (modal)
 *
 * Uso:
 *   1. Incluir este archivo en la página admin: <?php include 'includes/media-picker.php'; ?>
 *   2. Llamar desde JS: abrirMediaPicker('idDelInput', 'image')
 *      - Primer param:  id del <input> que recibirá la URL seleccionada
 *      - Segundo param: 'image' para filtrar solo imágenes, '' para todos (opcional)
 */
?>

<!-- ────────────────────────────────────────────────────────────────────
     MODAL SELECTOR DE MEDIOS
──────────────────────────────────────────────────────────────────────── -->
<div id="modal-media-picker" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(2px);">
    <div style="display:flex;align-items:center;justify-content:center;height:100%;padding:20px;">
        <div style="background:#fff;border-radius:14px;width:min(900px,100%);max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;">

            <!-- Cabecera -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-bottom:1px solid #e2e8f0;flex-shrink:0;">
                <div style="display:flex;gap:8px;align-items:center;">
                    <i class="fas fa-photo-video" style="color:#c8102e;font-size:18px;"></i>
                    <strong style="font-size:16px;">Seleccionar archivo</strong>
                </div>
                <button onclick="cerrarMediaPicker()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#718096;line-height:1;">&times;</button>
            </div>

            <!-- Pestañas -->
            <div style="display:flex;gap:0;border-bottom:1px solid #e2e8f0;flex-shrink:0;">
                <button class="mp-tab active" data-tab="biblioteca" onclick="mpCambiarTab('biblioteca')" style="padding:12px 22px;border:none;background:none;cursor:pointer;font-size:14px;font-weight:600;color:#c8102e;border-bottom:2px solid #c8102e;margin-bottom:-1px;">
                    <i class="fas fa-images"></i> Biblioteca
                </button>
                <button class="mp-tab" data-tab="subir" onclick="mpCambiarTab('subir')" style="padding:12px 22px;border:none;background:none;cursor:pointer;font-size:14px;font-weight:600;color:#718096;border-bottom:2px solid transparent;margin-bottom:-1px;">
                    <i class="fas fa-cloud-upload-alt"></i> Subir nuevo
                </button>
            </div>

            <!-- Barra herramientas biblioteca -->
            <div id="mp-tab-biblioteca" style="display:flex;gap:8px;padding:12px 18px;border-bottom:1px solid #e2e8f0;flex-shrink:0;flex-wrap:wrap;align-items:center;">
                <input id="mp-buscar" type="search" placeholder="Buscar…" oninput="mpFiltrar()" style="padding:7px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;width:200px;">
                <div style="display:flex;gap:4px;">
                    <button class="mp-filtro active" data-mime="" onclick="mpSetMine(this, '')" style="padding:6px 12px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-weight:600;background:#c8102e;color:white;">Todos</button>
                    <button class="mp-filtro" data-mime="image" onclick="mpSetMine(this, 'image')" style="padding:6px 12px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-weight:600;background:#e2e8f0;color:#4a5568;">Imágenes</button>
                    <button class="mp-filtro" data-mime="document" onclick="mpSetMine(this, 'document')" style="padding:6px 12px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-weight:600;background:#e2e8f0;color:#4a5568;">Docs</button>
                </div>
                <span id="mp-count" style="margin-left:auto;font-size:12px;color:#a0aec0;"></span>
            </div>

            <!-- Grid biblioteca -->
            <div id="mp-grid-wrap" style="flex:1;overflow-y:auto;padding:16px;">
                <div id="mp-loading" style="text-align:center;padding:40px;color:#a0aec0;">
                    <i class="fas fa-spinner fa-spin" style="font-size:28px;"></i><br>Cargando biblioteca…
                </div>
                <div id="mp-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;display:none;"></div>
                <div id="mp-empty" style="display:none;text-align:center;padding:50px 20px;color:#a0aec0;">
                    <i class="fas fa-photo-video" style="font-size:48px;opacity:.3;display:block;margin-bottom:12px;"></i>
                    No hay archivos en la biblioteca.
                </div>
            </div>

            <!-- Tab: Subir -->
            <div id="mp-tab-subir" style="display:none;flex:1;overflow-y:auto;padding:24px;">
                <div id="mp-dropzone" style="border:2px dashed #cbd5e0;border-radius:10px;padding:40px 20px;text-align:center;cursor:pointer;transition:all .2s;background:#fafafa;">
                    <i class="fas fa-cloud-upload-alt" style="font-size:44px;color:#c8102e;display:block;margin-bottom:12px;"></i>
                    <p style="color:#4a5568;font-weight:600;margin-bottom:6px;">Arrastra archivos aquí o haz clic para seleccionar</p>
                    <small style="color:#a0aec0;">JPG, PNG, GIF, WebP, PDF, Word, Excel, PowerPoint · Máx. 10 MB</small>
                    <input type="file" id="mp-file-input" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" style="display:none;">
                </div>
                <div id="mp-upload-progress" style="display:none;margin-top:16px;">
                    <div style="height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                        <div id="mp-prog-fill" style="height:100%;background:#c8102e;width:0;transition:width .3s;border-radius:4px;"></div>
                    </div>
                    <p id="mp-prog-text" style="margin-top:8px;font-size:13px;color:#718096;text-align:center;"></p>
                </div>
                <div id="mp-upload-result" style="margin-top:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;"></div>
            </div>

            <!-- Pie -->
            <div style="padding:14px 22px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;background:#f7fafc;">
                <span id="mp-sel-label" style="font-size:13px;color:#718096;">Haz clic en una imagen para seleccionarla</span>
                <div style="display:flex;gap:8px;">
                    <button onclick="cerrarMediaPicker()" style="padding:9px 20px;border:1px solid #e2e8f0;border-radius:7px;background:white;cursor:pointer;font-size:14px;color:#4a5568;">Cancelar</button>
                    <button id="mp-btn-insertar" disabled onclick="mpInsertar()" style="padding:9px 22px;border:none;border-radius:7px;background:#c8102e;color:white;cursor:pointer;font-size:14px;font-weight:600;opacity:.5;transition:opacity .2s;">
                        <i class="fas fa-check"></i> Insertar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ── Estado del picker ────────────────────────────────────────────────
const _mp = {
    targetId: null,         // id del input a rellenar
    mimeFilter: '',         // '' | 'image' forzado desde código llamante
    mimeActual: '',         // mime activo en el filtro UI
    medios: [],             // caché de todos los medios
    seleccionado: null,     // objeto medio seleccionado (single)
    seleccionados: [],      // array de objetos medios (multi)
    multi: false,          // modo selección múltiple
    cargado: false
};

// Estilos dinámicos para la mini-card del picker
const _mpStyle = document.createElement('style');
_mpStyle.textContent = `
.mp-card { border:2px solid #e2e8f0;border-radius:8px;overflow:hidden;cursor:pointer;transition:all .16s; }
.mp-card:hover { border-color:#c8102e;transform:translateY(-2px);box-shadow:0 4px 12px rgba(200,16,46,.15); }
.mp-card.mp-sel { border-color:#c8102e;box-shadow:0 0 0 3px rgba(200,16,46,.25); }
.mp-card .mp-thumb { height:90px;display:flex;align-items:center;justify-content:center;background:#f7fafc;overflow:hidden; }
.mp-card .mp-thumb img { width:100%;height:100%;object-fit:cover; }
.mp-card .mp-info { padding:5px 7px;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#4a5568;font-weight:600; }
.mp-tab { transition:color .15s; }
.mp-filtro { transition:all .15s; }
#mp-dropzone.mp-drag { border-color:#c8102e;background:#fff5f5; }
`;
document.head.appendChild(_mpStyle);

// ── API pública ──────────────────────────────────────────────────────

/**
 * Abre el modal.
 * @param {string} targetId  - id del <input> que recibirá la URL
 * @param {string} [filter]  - '' (todos) | 'image' (solo imágenes)
 */
function abrirMediaPicker(targetId, filter, allowMultiple) {
    _mp.targetId   = targetId;
    _mp.mimeFilter = filter || '';
    _mp.seleccionado = null;
    _mp.seleccionados = [];
    _mp.multi = !!allowMultiple;

    // Si se fuerza filtro imagen, ocultamos botón Docs
    document.querySelectorAll('.mp-filtro[data-mime="document"]').forEach(b => {
        b.style.display = _mp.mimeFilter === 'image' ? 'none' : '';
    });

    // Aplicar filtro inicial
    if (_mp.mimeFilter) {
        document.querySelectorAll('.mp-filtro').forEach(b => {
            const activo = b.dataset.mime === _mp.mimeFilter;
            b.style.background = activo ? '#c8102e' : '#e2e8f0';
            b.style.color      = activo ? 'white'   : '#4a5568';
            b.classList.toggle('active', activo);
        });
        _mp.mimeActual = _mp.mimeFilter;
    } else {
        mpSetMine(document.querySelector('.mp-filtro[data-mime=""]'), '');
    }

    document.getElementById('modal-media-picker').style.display = 'block';
    document.body.style.overflow = 'hidden';
    mpCambiarTab('biblioteca');

    if (!_mp.cargado) mpCargarMedios();
    else mpRenderGrid();
}

function cerrarMediaPicker() {
    document.getElementById('modal-media-picker').style.display = 'none';
    document.body.style.overflow = '';
    _mp.seleccionado = null;
    _mpActualizarPie();
}

// Cerrar con Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarMediaPicker();
});

// ── Tabs ─────────────────────────────────────────────────────────────
function mpCambiarTab(tab) {
    ['biblioteca','subir'].forEach(t => {
        const isA = t === tab;
        // panel de contenido
        if (t === 'biblioteca') {
            document.getElementById('mp-tab-biblioteca').style.display = isA ? 'flex' : 'none';
            document.getElementById('mp-grid-wrap').style.display       = isA ? 'block' : 'none';
        } else {
            document.getElementById('mp-tab-subir').style.display = isA ? 'block' : 'none';
        }
        // botón tab
        const btn = document.querySelector(`.mp-tab[data-tab="${t}"]`);
        if (btn) {
            btn.style.color       = isA ? '#c8102e' : '#718096';
            btn.style.borderBottom = isA ? '2px solid #c8102e' : '2px solid transparent';
            btn.classList.toggle('active', isA);
        }
    });
}

// ── Filtros ──────────────────────────────────────────────────────────
function mpSetMine(btn, mime) {
    _mp.mimeActual = mime;
    document.querySelectorAll('.mp-filtro').forEach(b => {
        const activo = b === btn;
        b.style.background = activo ? '#c8102e' : '#e2e8f0';
        b.style.color      = activo ? 'white'   : '#4a5568';
        b.classList.toggle('active', activo);
    });
    mpRenderGrid();
}

function mpFiltrar() { mpRenderGrid(); }

// ── Cargar medios (AJAX) ─────────────────────────────────────────────
function mpCargarMedios() {
    fetch('ajax/listar-medios.php')
        .then(r => r.json())
        .then(data => {
            _mp.medios  = data.medios || [];
            _mp.cargado = true;
            mpRenderGrid();
        })
        .catch(() => {
            document.getElementById('mp-loading').innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#c53030;"></i> Error al cargar biblioteca';
        });
}

function mpRenderGrid() {
    const grid    = document.getElementById('mp-grid');
    const loading = document.getElementById('mp-loading');
    const empty   = document.getElementById('mp-empty');
    const bus     = (document.getElementById('mp-buscar')?.value || '').toLowerCase();

    let items = _mp.medios;

    // Filtro MIME
    if (_mp.mimeActual === 'image')    items = items.filter(m => m.tipo_mime.startsWith('image/'));
    if (_mp.mimeActual === 'document') items = items.filter(m => !m.tipo_mime.startsWith('image/'));
    // Búsqueda
    if (bus) items = items.filter(m => m.nombre_original.toLowerCase().includes(bus));

    loading.style.display = 'none';
    document.getElementById('mp-count').textContent = items.length + ' archivo(s)';

    if (!items.length) {
        grid.style.display  = 'none';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    grid.style.display  = 'grid';
    grid.innerHTML = items.map(m => {
        const isImg = m.tipo_mime.startsWith('image/');
        const icon  = mpIcono(m.tipo_mime);
        return `<div class="mp-card" data-id="${m.id}" data-url="${_escHtml(m.url)}" data-nombre="${_escHtml(m.nombre_original)}"
                     onclick="mpSeleccionar(this, ${JSON.stringify(m)})">
            <div class="mp-thumb">
                ${isImg ? `<img src="${_escHtml(m.url)}" alt="" loading="lazy">` : `<i class="fas ${icon}" style="font-size:36px;color:#a0aec0;"></i>`}
            </div>
            <div class="mp-info" title="${_escHtml(m.nombre_original)}">${_escHtml(m.nombre_original)}</div>
        </div>`;
    }).join('');
}

function _escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function mpIcono(mime) {
    if (mime === 'application/pdf')         return 'fa-file-pdf';
    if (mime.includes('word'))              return 'fa-file-word';
    if (mime.includes('excel') || mime.includes('spreadsheet')) return 'fa-file-excel';
    if (mime.includes('powerpoint') || mime.includes('presentation')) return 'fa-file-powerpoint';
    return 'fa-file';
}

function mpSeleccionar(card, medio) {
    if (_mp.multi) {
        // Toggle selection on the card
        const was = card.classList.contains('mp-sel');
        if (was) {
            card.classList.remove('mp-sel');
            _mp.seleccionados = _mp.seleccionados.filter(s => s.url !== medio.url);
        } else {
            card.classList.add('mp-sel');
            _mp.seleccionados.push(medio);
        }
        _mpActualizarPie();
        return;
    }

    // Single selection behavior
    document.querySelectorAll('#mp-grid .mp-card').forEach(c => c.classList.remove('mp-sel'));
    card.classList.add('mp-sel');
    _mp.seleccionado = medio;
    _mpActualizarPie();
}

function _mpActualizarPie() {
    const label = document.getElementById('mp-sel-label');
    const btn   = document.getElementById('mp-btn-insertar');
    if (_mp.multi) {
        const n = _mp.seleccionados.length;
        if (n > 0) {
            label.textContent = '✔ ' + n + ' archivo(s) seleccionado(s)';
            btn.disabled = false;
            btn.style.opacity = '1';
        } else {
            label.textContent = 'Haz clic en las imágenes para seleccionarlas';
            btn.disabled = true;
            btn.style.opacity = '.5';
        }
        return;
    }

    if (_mp.seleccionado) {
        label.textContent = '✔ ' + _mp.seleccionado.nombre_original;
        btn.disabled      = false;
        btn.style.opacity = '1';
    } else {
        label.textContent = 'Haz clic en una imagen para seleccionarla';
        btn.disabled      = true;
        btn.style.opacity = '.5';
    }
}

function mpInsertar() {
    if (!_mp.targetId) return;
    const input = document.getElementById(_mp.targetId);
    if (!_mp.multi) {
        if (!_mp.seleccionado || !input) return;
        input.value = _mp.seleccionado.url;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.dispatchEvent(new Event('input', { bubbles: true }));
        cerrarMediaPicker();
        return;
    }

    // Multi-insert: append selected URLs to textarea-like input
    if (_mp.multi && input) {
        const urls = _mp.seleccionados.map(s => s.url);
        const existing = (input.value || '').split(/\r\n|\r|\n/).map(s => s.trim()).filter(Boolean);
        const merged = Array.from(new Set(existing.concat(urls))).slice(0, 20);
        input.value = merged.join('\n');
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    cerrarMediaPicker();
}

// ── Upload desde el picker ───────────────────────────────────────────
(function() {
    function init() {
        const dz  = document.getElementById('mp-dropzone');
        const inp = document.getElementById('mp-file-input');
        if (!dz || !inp) return;
        dz.addEventListener('click', () => inp.click());
        dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('mp-drag'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('mp-drag'));
        dz.addEventListener('drop', e => { e.preventDefault(); dz.classList.remove('mp-drag'); mpSubirArchivos(e.dataTransfer.files); });
        inp.addEventListener('change', () => { mpSubirArchivos(inp.files); inp.value = ''; });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

function mpSubirArchivos(files) {
    if (!files.length) return;
    const arr  = Array.from(files);
    const prog = document.getElementById('mp-upload-progress');
    const fill = document.getElementById('mp-prog-fill');
    const txt  = document.getElementById('mp-prog-text');
    const res  = document.getElementById('mp-upload-result');
    prog.style.display = 'block';
    let done = 0;

    function subirUno(file) {
        txt.textContent = 'Subiendo ' + (done+1) + '/' + arr.length + ': ' + file.name;
        fill.style.width = Math.round((done/arr.length)*100) + '%';
        const fd = new FormData();
        fd.append('archivo', file);
        fetch('ajax/subir-medio.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                done++;
                if (data.ok) {
                    // Añadir a caché y mostrar resultado
                    _mp.medios.unshift(data);
                    const isImg = data.mime.startsWith('image/');
                    const div = document.createElement('div');
                    div.className = 'mp-card mp-sel';
                    div.dataset.id  = data.id;
                    div.dataset.url = data.url;
                    div.innerHTML = `<div class="mp-thumb">${isImg ? `<img src="${data.url}" loading="lazy">` : '<i class="fas fa-file" style="font-size:36px;color:#a0aec0;"></i>'}</div><div class="mp-info">${data.nombre}</div>`;
                    div.addEventListener('click', () => {
                        document.querySelectorAll('#mp-upload-result .mp-card, #mp-grid .mp-card').forEach(c => c.classList.remove('mp-sel'));
                        div.classList.add('mp-sel');
                        _mp.seleccionado = data;
                        _mp.seleccionado.url  = data.url;
                        _mp.seleccionado.nombre_original = data.nombre;
                        _mpActualizarPie();
                    });
                    res.prepend(div);
                    // Autoseleccionar último subido
                    _mp.seleccionado = { url: data.url, nombre_original: data.nombre };
                    _mpActualizarPie();
                } else {
                    alert('Error: ' + (data.error || 'desconocido'));
                }
                if (done < arr.length) subirUno(arr[done]);
                else {
                    fill.style.width = '100%';
                    txt.textContent  = done + ' archivo(s) subidos. Haz clic en "Insertar" para usar el último.';
                }
            })
            .catch(() => { done++; alert('Error de red'); if (done < arr.length) subirUno(arr[done]); });
    }
    subirUno(arr[0]);
}
</script>
