<?php
$page_title = 'Noticias Escaneadas';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Filtro por medio
$medio_id = isset($_GET['medio_id']) ? (int)$_GET['medio_id'] : 0;

// Obtener lista de medios para el filtro (diarios + scraping Facebook)
$stmt = $db->query("
    SELECT id, nombre, tipo 
    FROM medios_conectados 
    WHERE tipo IN ('diario_online', 'facebook_scraping')
    ORDER BY tipo, nombre
");
$medios = $stmt->fetchAll();

// Query base
$where = [];
$params = [];

if ($medio_id > 0) {
    $where[] = "mcs.medio_id = :medio_id";
    $params[':medio_id'] = $medio_id;
}

$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener noticias escaneadas
$stmt = $db->prepare("
    SELECT 
        mcs.*,
        m.nombre as medio_nombre,
        m.url as medio_url
    FROM medios_contenido_sincronizado mcs
    INNER JOIN medios_conectados m ON mcs.medio_id = m.id
    {$whereSQL}
    ORDER BY mcs.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$noticias = $stmt->fetchAll();

// Obtener categorÃ­as para el formulario de publicaciÃ³n
$categorias = $db->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();


// Obtener comunas para selección tipo tag
$comunas = $db->query("SELECT id, nombre FROM comunas ORDER BY nombre")->fetchAll();
// Obtener estadÃ­sticas
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
        COUNT(CASE WHEN estado = 'procesado' THEN 1 END) as procesadas,
        COUNT(CASE WHEN estado = 'publicado' THEN 1 END) as publicadas
    FROM medios_contenido_sincronizado mcs
    {$whereSQL}
");
$stmt->execute($params);
$stats = $stmt->fetch();
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-newspaper"></i> Noticias Escaneadas</h1>
        <p>Noticias extraÃ­das mediante scraping de medios conectados</p>
    </div>
    <a href="medios-conectados.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<!-- EstadÃ­sticas -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #7f8c8d; font-size: 14px; margin-bottom: 5px;">Total</div>
        <div style="font-size: 32px; font-weight: bold; color: #3498db;"><?php echo $stats['total']; ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #7f8c8d; font-size: 14px; margin-bottom: 5px;">Pendientes</div>
        <div style="font-size: 32px; font-weight: bold; color: #f39c12;"><?php echo $stats['pendientes']; ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #7f8c8d; font-size: 14px; margin-bottom: 5px;">Procesadas</div>
        <div style="font-size: 32px; font-weight: bold; color: #9b59b6;"><?php echo $stats['procesadas']; ?></div>
    </div>
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="color: #7f8c8d; font-size: 14px; margin-bottom: 5px;">Publicadas</div>
        <div style="font-size: 32px; font-weight: bold; color: #27ae60;"><?php echo $stats['publicadas']; ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Filtros</h2>
    </div>
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 15px; align-items: end;">
            <div class="form-group" style="flex: 1; margin: 0;">
                <label for="medio_id">Medio</label>
                <select id="medio_id" name="medio_id" class="form-control">
                    <option value="0">Todos los medios</option>
                    <?php foreach ($medios as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $medio_id == $m['id'] ? 'selected' : ''; ?>>
                            <?php
                            $prefix = $m['tipo'] === 'facebook_scraping' ? '[FB] ' : '';
                            echo htmlspecialchars($prefix . $m['nombre']);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="margin: 0;">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <?php if ($medio_id > 0): ?>
                <a href="noticias-escaneadas.php" class="btn btn-secondary" style="margin: 0;">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2>Noticias (<?php echo count($noticias); ?>)</h2>
    </div>
    <div class="card-body">
        <?php if (empty($noticias)): ?>
            <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                <p style="margin: 0;">No hay noticias escaneadas aÃºn</p>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 15px;">
                <?php foreach ($noticias as $noticia): ?>
                    <div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; background: #fafafa;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div style="flex: 1;">
                                <h3 style="margin: 0 0 8px 0; font-size: 18px;">
                                    <a href="<?php echo htmlspecialchars($noticia['url_original']); ?>" target="_blank" style="color: #2c3e50; text-decoration: none;">
                                        <?php echo htmlspecialchars($noticia['titulo']); ?>
                                    </a>
                                </h3>
                                <div style="display: flex; gap: 15px; flex-wrap: wrap; font-size: 13px; color: #7f8c8d;">
                                    <span>
                                        <i class="fas fa-newspaper"></i> 
                                        <?php echo htmlspecialchars($noticia['medio_nombre']); ?>
                                    </span>
                                    <?php if ($noticia['autor']): ?>
                                        <span>
                                            <i class="fas fa-user"></i> 
                                            <?php echo htmlspecialchars($noticia['autor']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($noticia['categoria']): ?>
                                        <span>
                                            <i class="fas fa-folder"></i> 
                                            <?php echo htmlspecialchars($noticia['categoria']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span>
                                        <i class="fas fa-clock"></i> 
                                        <?php echo date('d/m/Y H:i', strtotime($noticia['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div style="margin-left: 15px;">
                                <?php
                                $badge_colors = [
                                    'pendiente' => 'background: #f39c12; color: white;',
                                    'procesado' => 'background: #9b59b6; color: white;',
                                    'publicado' => 'background: #27ae60; color: white;',
                                    'error' => 'background: #e74c3c; color: white;'
                                ];
                                $color = $badge_colors[$noticia['estado']] ?? 'background: #95a5a6; color: white;';
                                ?>
                                <span style="<?php echo $color; ?> padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;"
                                      data-noticia-id="<?php echo $noticia['id']; ?>">
                                    <?php echo ucfirst($noticia['estado']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($noticia['contenido']): ?>
                            <p style="margin: 10px 0; color: #555; font-size: 14px; line-height: 1.6;">
                                <?php echo htmlspecialchars(mb_substr($noticia['contenido'], 0, 200)); ?>...
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($noticia['imagen_url']): ?>
                            <div style="margin-top: 10px;">
                                <img src="<?php echo htmlspecialchars($noticia['imagen_url']); ?>" 
                                     alt="Imagen" 
                                     style="max-width: 200px; max-height: 150px; border-radius: 4px; object-fit: cover;"
                                     onerror="this.style.display='none'">
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e0e0e0; display: flex; gap: 10px; flex-wrap: wrap;">
                            <button 
                                class="btn btn-sm btn-primary"
                                onclick="verNoticia(<?php echo $noticia['id']; ?>)">
                                <i class="fas fa-eye"></i> Ver Noticia
                            </button>
                            <button 
                                class="btn btn-sm"
                                style="background: #8e44ad; color: white;"
                                onclick="redactarIA(<?php echo $noticia['id']; ?>)">
                                <i class="fas fa-robot"></i> RedacciÃ³n IA
                            </button>
                            <a href="<?php echo htmlspecialchars($noticia['url_original']); ?>" 
                               target="_blank" 
                               class="btn btn-sm btn-secondary">
                                <i class="fas fa-external-link-alt"></i> Ver Original
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Datos de noticias para JavaScript -->
<script>
const noticias = <?php echo json_encode(array_values($noticias), JSON_UNESCAPED_UNICODE); ?>;
</script>

<!-- Modal Ver Noticia / RedacciÃ³n IA -->
<div id="modal-noticia" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; overflow-y:auto;">
    <div style="background:white; max-width:850px; margin:40px auto; border-radius:12px; overflow:hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        
        <!-- Header del modal -->
        <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="color: white; margin: 0; font-size: 20px;">
                <i class="fas fa-newspaper"></i> <span id="modal-titulo-cabecera">Noticia</span>
            </h2>
            <button onclick="cerrarModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 18px;">&times;</button>
        </div>
        
        <!-- Tabs -->
        <div style="display: flex; border-bottom: 2px solid #e0e0e0; background: #f8f9fa;">
            <button id="tab-noticia" onclick="mostrarTab('noticia')" 
                style="padding: 15px 25px; border: none; background: white; border-bottom: 3px solid #667eea; cursor: pointer; font-weight: 600; color: #667eea; font-size: 15px;">
                <i class="fas fa-file-alt"></i> Noticia Original
            </button>
            <button id="tab-ia" onclick="mostrarTab('ia')" 
                style="padding: 15px 25px; border: none; background: transparent; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; color: #7f8c8d;">
                <i class="fas fa-robot"></i> RedacciÃ³n IA
            </button>
        </div>
        
        <!-- Tab: Noticia Original -->
        <div id="panel-noticia" style="padding: 25px;">
            <div id="modal-imagen" style="margin-bottom: 20px;"></div>
            
            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
                <div id="modal-medio" style="font-size: 13px; color: #7f8c8d;"></div>
                <div id="modal-autor" style="font-size: 13px; color: #7f8c8d;"></div>
                <div id="modal-categoria" style="font-size: 13px; color: #7f8c8d;"></div>
                <div id="modal-fecha" style="font-size: 13px; color: #7f8c8d;"></div>
            </div>
            
            <h1 id="modal-titulo" style="font-size: 24px; margin-bottom: 20px; color: #1a202c; line-height: 1.4;"></h1>
            
            <div id="modal-contenido" style="line-height: 1.8; color: #2d3748; font-size: 15px; white-space: pre-wrap;"></div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <a id="modal-url" href="#" target="_blank" class="btn btn-secondary btn-sm">
                    <i class="fas fa-external-link-alt"></i> Ver noticia original
                </a>
            </div>
        </div>
        
        <!-- Tab: RedacciÃ³n IA -->
        <div id="panel-ia" style="padding: 25px; display: none;">
            <div id="ia-sin-generar">
                <div style="text-align: center; padding: 30px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-robot" style="font-size: 48px; color: #8e44ad; margin-bottom: 15px;"></i>
                    <p style="color: #555; font-size: 16px; margin: 0;">La IA redactarÃ¡ un artÃ­culo periodÃ­stico profesional basado en la informaciÃ³n de la noticia original.</p>
                </div>
                <div style="text-align: center;">
                    <button id="btn-generar" onclick="generarRedaccionIA()" 
                        class="btn btn-primary"
                        style="background: #8e44ad; padding: 12px 30px; font-size: 16px;">
                        <i class="fas fa-magic"></i> Generar RedacciÃ³n con IA
                    </button>
                </div>
            </div>
            
            <div id="ia-loading" style="display: none; text-align: center; padding: 50px;">
                <div style="display: inline-block; width: 50px; height: 50px; border: 4px solid #e0e0e0; border-top-color: #8e44ad; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                <p style="margin-top: 20px; color: #7f8c8d; font-size: 16px;">La IA estÃ¡ redactando el artÃ­culo...</p>
            </div>
            
            <div id="ia-resultado" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: #27ae60;"><i class="fas fa-check-circle"></i> RedacciÃ³n completada</h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button onclick="copiarRedaccion()" class="btn btn-sm btn-secondary">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                        <button onclick="generarRedaccionIA()" class="btn btn-sm" style="background: #8e44ad; color: white;">
                            <i class="fas fa-redo"></i> Regenerar
                        </button>
                        <button onclick="mostrarFormPublicar()" class="btn btn-sm" style="background: #27ae60; color: white;">
                            <i class="fas fa-paper-plane"></i> Publicar
                        </button>
                    </div>
                </div>

                <!-- Campo tÃ­tulo generado por IA -->
                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; display: block;">TÃ­tulo del artÃ­culo</label>
                    <input type="text" id="ia-titulo-value"
                        style="width: 100%; padding: 10px 14px; border: 2px solid #8e44ad; border-radius: 6px; font-size: 15px; font-weight: 600; box-sizing: border-box;">
                </div>

                <div id="ia-texto" style="line-height: 1.9; color: #2d3748; font-size: 15px; background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #8e44ad; white-space: pre-wrap;"></div>

                <!-- Formulario de publicaciÃ³n -->
                <div id="form-publicar" style="display: none; margin-top: 20px; padding: 20px; background: #f0fff4; border: 2px solid #27ae60; border-radius: 8px;">
                    <h4 style="margin: 0 0 15px 0; color: #27ae60;"><i class="fas fa-paper-plane"></i> Publicar en el sitio</h4>

                    <div class="form-group">
                        <label style="font-weight: 600;">Categoría <span style="color:red">*</span></label>
                        <select id="pub-categoria" class="form-control">
                            <option value="">-- Seleccionar categoría --</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600;">Comunas (una o más)</label>
                        <div id="pub-comunas-tags" style="display:flex; flex-wrap:wrap; gap:8px; padding:10px; border:1px solid #d9e2ec; border-radius:8px; background:#fff; max-height:180px; overflow:auto;">
                            <?php foreach ($comunas as $com): ?>
                                <button type="button" class="comuna-tag" data-id="<?php echo (int)$com['id']; ?>" style="border:1px solid #cbd5e1; background:#f8fafc; color:#334155; border-radius:999px; padding:6px 10px; font-size:12px; cursor:pointer;">
                                    <?php echo htmlspecialchars($com['nombre']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <small style="display:block; margin-top:6px; color:#64748b;">Haz clic para seleccionar una o más comunas.</small>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600;">Imagen principal</label>
                        <input type="file" id="pub-imagen-file" accept="image/*" class="form-control">
                        <small style="display:block; margin-top:6px; color:#64748b;">Opcional. Si no subes imagen, se usará la imagen original si existe.</small>
                    </div>
                    <div id="pub-msg" style="display: none; margin-bottom: 10px;"></div>

                    <div style="display: flex; gap: 10px;">
                        <button onclick="publicarNoticia()" class="btn btn-primary" style="background: #27ae60;" id="btn-publicar-final">
                            <i class="fas fa-check"></i> Confirmar publicaciÃ³n
                        </button>
                        <button onclick="document.getElementById('form-publicar').style.display='none'" class="btn btn-secondary">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="ia-error" style="display: none;">
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="ia-error-msg"></span>
                </div>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="configuracion-ia.php" class="btn btn-primary">
                        <i class="fas fa-cog"></i> Ir a ConfiguraciÃ³n IA
                    </a>
                    <button onclick="generarRedaccionIA()" class="btn btn-secondary" style="margin-left: 10px;">
                        <i class="fas fa-redo"></i> Reintentar
                    </button>
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
let noticiaActual = null;

function verNoticia(id) {
    const noticia = noticias.find(n => n.id == id);
    if (!noticia) return;
    noticiaActual = noticia;
    
    document.getElementById('modal-titulo-cabecera').textContent = noticia.titulo.substring(0, 60) + '...';
    document.getElementById('modal-titulo').textContent = noticia.titulo;
    document.getElementById('modal-contenido').textContent = noticia.contenido || 'Sin contenido';
    document.getElementById('modal-url').href = noticia.url_original;
    
    document.getElementById('modal-medio').innerHTML = noticia.medio_nombre ? `<i class="fas fa-newspaper"></i> ${noticia.medio_nombre}` : '';
    document.getElementById('modal-autor').innerHTML = noticia.autor ? `<i class="fas fa-user"></i> ${noticia.autor}` : '';
    document.getElementById('modal-categoria').innerHTML = noticia.categoria ? `<i class="fas fa-folder"></i> ${noticia.categoria}` : '';
    document.getElementById('modal-fecha').innerHTML = noticia.created_at ? `<i class="fas fa-clock"></i> ${noticia.created_at}` : '';
    
    const imgDiv = document.getElementById('modal-imagen');
    imgDiv.innerHTML = noticia.imagen_url 
        ? `<img src="${noticia.imagen_url}" style="width:100%; max-height:300px; object-fit:cover; border-radius:8px;" onerror="this.style.display='none'">`
        : '';
    
    mostrarTab('noticia');
    document.getElementById('modal-noticia').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function redactarIA(id) {
    verNoticia(id);
    setTimeout(() => mostrarTab('ia'), 50);
}

function mostrarTab(tab) {
    document.getElementById('panel-noticia').style.display = tab === 'noticia' ? 'block' : 'none';
    document.getElementById('panel-ia').style.display = tab === 'ia' ? 'block' : 'none';
    
    document.getElementById('tab-noticia').style.cssText = tab === 'noticia'
        ? 'padding:15px 25px;border:none;background:white;border-bottom:3px solid #667eea;cursor:pointer;font-weight:600;color:#667eea;font-size:15px;'
        : 'padding:15px 25px;border:none;background:transparent;border-bottom:3px solid transparent;cursor:pointer;font-size:15px;color:#7f8c8d;';
    document.getElementById('tab-ia').style.cssText = tab === 'ia'
        ? 'padding:15px 25px;border:none;background:white;border-bottom:3px solid #8e44ad;cursor:pointer;font-weight:600;color:#8e44ad;font-size:15px;'
        : 'padding:15px 25px;border:none;background:transparent;border-bottom:3px solid transparent;cursor:pointer;font-size:15px;color:#7f8c8d;';
}

function cerrarModal() {
    document.getElementById('modal-noticia').style.display = 'none';
    document.body.style.overflow = '';
    // Reset IA
    document.getElementById('ia-sin-generar').style.display = 'block';
    document.getElementById('ia-loading').style.display = 'none';
    document.getElementById('ia-resultado').style.display = 'none';
    document.getElementById('ia-error').style.display = 'none';
    document.getElementById('form-publicar').style.display = 'none';
    document.getElementById('ia-titulo-value').value = '';
    document.getElementById('ia-texto').textContent = '';
    document.getElementById('pub-categoria').value = '';
    document.getElementById('pub-imagen-file').value = '';
    document.querySelectorAll('#pub-comunas-tags .comuna-tag.selected').forEach(tag => {
        tag.classList.remove('selected');
        tag.style.background = '#f8fafc';
        tag.style.color = '#334155';
        tag.style.borderColor = '#cbd5e1';
    });
    document.getElementById('pub-msg').style.display = 'none';
    const btnFinal = document.getElementById('btn-publicar-final');
    btnFinal.style.display = '';
    btnFinal.disabled = false;
    btnFinal.innerHTML = '<i class="fas fa-check"></i> Confirmar publicaciÃ³n';
}

function generarRedaccionIA() {
    if (!noticiaActual) return;
    
    document.getElementById('ia-sin-generar').style.display = 'none';
    document.getElementById('ia-loading').style.display = 'block';
    document.getElementById('ia-resultado').style.display = 'none';
    document.getElementById('ia-error').style.display = 'none';
    
    const formData = new FormData();
    formData.append('noticia_id', noticiaActual.id);
    
    fetch('ajax/redactar-ia.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('ia-loading').style.display = 'none';
        
        if (data.error) {
            document.getElementById('ia-error-msg').textContent = data.error;
            document.getElementById('ia-error').style.display = 'block';
        } else {
            // Poblar tÃ­tulo: usar el generado por IA o el original
            const tituloIA = data.titulo || noticiaActual.titulo;
            document.getElementById('ia-titulo-value').value = tituloIA;
            document.getElementById('ia-texto').textContent = data.texto;
            document.getElementById('form-publicar').style.display = 'none';
            document.getElementById('ia-resultado').style.display = 'block';
        }
    })
    .catch(() => {
        document.getElementById('ia-loading').style.display = 'none';
        document.getElementById('ia-error-msg').textContent = 'Error de conexiÃ³n. Intenta de nuevo.';
        document.getElementById('ia-error').style.display = 'block';
    });
}

function copiarRedaccion() {
    const titulo = document.getElementById('ia-titulo-value').value;
    const contenido = document.getElementById('ia-texto').textContent;
    const texto = titulo ? titulo + '\n\n' + contenido : contenido;
    navigator.clipboard.writeText(texto).then(() => {
        const btn = event.target.closest('button');
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
        btn.style.background = '#27ae60';
        btn.style.color = 'white';
        setTimeout(() => { btn.innerHTML = original; btn.style.background = ''; btn.style.color = ''; }, 2000);
    });
}

function initComunaTags() {
    document.querySelectorAll('#pub-comunas-tags .comuna-tag').forEach(tag => {
        if (tag.dataset.bound === '1') return;
        tag.dataset.bound = '1';
        tag.addEventListener('click', function () {
            this.classList.toggle('selected');
            const active = this.classList.contains('selected');
            this.style.background = active ? '#16a34a' : '#f8fafc';
            this.style.color = active ? '#ffffff' : '#334155';
            this.style.borderColor = active ? '#15803d' : '#cbd5e1';
        });
    });
}

function mostrarFormPublicar() {
    const formPublicar = document.getElementById('form-publicar');
    const visible = formPublicar.style.display !== 'none';
    formPublicar.style.display = visible ? 'none' : 'block';
    if (!visible) {
        initComunaTags();
        formPublicar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function publicarNoticia() {
    const titulo = document.getElementById('ia-titulo-value').value.trim();
    const contenido = document.getElementById('ia-texto').textContent.trim();
    const categoriaId = document.getElementById('pub-categoria').value;
    const imagenFile = document.getElementById('pub-imagen-file').files[0];
    const comunasSeleccionadas = Array.from(document.querySelectorAll('#pub-comunas-tags .comuna-tag.selected')).map(el => el.dataset.id);

    if (!titulo) { mostrarMsgPublicar('El título es obligatorio', 'error'); return; }
    if (!categoriaId) { mostrarMsgPublicar('Debes seleccionar una categoría', 'error'); return; }
    if (!contenido) { mostrarMsgPublicar('No hay contenido generado', 'error'); return; }

    const btn = document.getElementById('btn-publicar-final');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publicando...';

    const formData = new FormData();
    formData.append('titulo', titulo);
    formData.append('contenido', contenido);
    formData.append('categoria_id', categoriaId);
    formData.append('noticia_id', noticiaActual.id);
    comunasSeleccionadas.forEach(id => formData.append('comunas[]', id));
    if (imagenFile) {
        formData.append('imagen', imagenFile);
    }

    fetch('ajax/publicar-noticia-ia.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmar publicación';
        if (data.error) {
            mostrarMsgPublicar(data.error, 'error');
        } else {
            mostrarMsgPublicar('¡Noticia publicada correctamente! <a href="noticias.php" style="color:white;font-weight:bold;">Ver en noticias →</a>', 'success');
            btn.style.display = 'none';
            document.querySelectorAll('[data-noticia-id="' + noticiaActual.id + '"]').forEach(badge => {
                badge.textContent = 'Publicado';
                badge.style.cssText = 'background: #27ae60; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;';
            });
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmar publicación';
        mostrarMsgPublicar('Error de conexión. Intenta de nuevo.', 'error');
    });
}

function mostrarMsgPublicar(msg, tipo) {
    const div = document.getElementById('pub-msg');
    div.style.display = 'block';
    div.style.cssText = `display:block; padding:10px 15px; border-radius:6px; margin-bottom:10px; ${tipo === 'error' ? 'background:#fde8e8;color:#c0392b;border:1px solid #e74c3c;' : 'background:#e8f8f0;color:#1e8449;border:1px solid #27ae60;'}`;
    div.innerHTML = msg;
}

// Cerrar modal con Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });
// Cerrar modal al hacer click fuera
document.getElementById('modal-noticia').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});
</script>

<?php include 'includes/footer.php'; ?>



