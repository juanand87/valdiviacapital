<?php
$page_title = 'Hacer Scraping';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();
$medio_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener información del medio
$stmt = $db->prepare("
    SELECT m.*, d.*
    FROM medios_conectados m
    LEFT JOIN medios_diarios_config d ON m.id = d.medio_id
    WHERE m.id = :id AND m.tipo = 'diario_online'
");
$stmt->execute([':id' => $medio_id]);
$medio = $stmt->fetch();

if (!$medio) {
    echo '<div class="alert alert-error">Medio no encontrado</div>';
    include 'includes/footer.php';
    exit;
}

$resultados = [];
$error = null;

// Función para limpiar texto
function limpiarTexto($texto) {
    // Eliminar scripts, estilos y comentarios
    $texto = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $texto);
    $texto = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/i', '', $texto);
    $texto = preg_replace('/<!--.*?-->/s', '', $texto);
    
    // Reemplazar etiquetas de bloque con saltos de línea
    $texto = preg_replace('/<\/(p|div|li|h[1-6]|blockquote|article|section)>/i', "\n", $texto);
    $texto = preg_replace('/<br\s*\/?>/i', "\n", $texto);
    
    // Eliminar etiquetas restantes
    $texto = strip_tags($texto);
    
    // Limpiar espacios múltiples pero preservar saltos de línea
    $texto = preg_replace('/[ \t]+/', ' ', $texto);
    $texto = preg_replace('/\n{3,}/', "\n\n", $texto);
    $texto = trim($texto);
    
    return $texto;
}

// Función para hacer scraping de 2 niveles
function hacerScraping($url, $selectores, $db = null, $medio_id = null, &$diagnostico = []) {
    $resultados = [];
    $diagnostico = [
        'links_encontrados'  => 0,
        'links_visitados'    => 0,
        'duplicados_bd'      => 0,
        'duplicados_titulo'  => 0,
        'titulo_vacio'       => 0,
        'titulo_invalido'    => 0,
        'guardadas'          => 0,
    ];
    
    try {
        // Configurar contexto para obtener el HTML
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        
        // NIVEL 1: Obtener la página principal
        $html = @file_get_contents($url, false, $context);
        
        if ($html === false) {
            throw new Exception("No se pudo acceder a la URL");
        }
        
        // Cargar el HTML en DOMDocument
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        
        // Extraer links de noticias desde la portada
        $links = [];
        if ($selectores['link']) {
            $selectorLink = convertirCSSaXPath($selectores['link']);
            $elementosLink = @$xpath->query($selectorLink);
            
            if ($elementosLink === false || $elementosLink->length == 0) {
                throw new Exception("No se encontraron links con el selector: {$selectores['link']}");
            }
            
            // Recopilar más links de los necesarios (el doble) para tener margen con duplicados
            $cantidadDeseada = isset($selectores['cantidad_noticias']) ? (int)$selectores['cantidad_noticias'] : 10;
            $maxLinksAExtraer = min($cantidadDeseada * 2, 100); // Máximo 100 links
            
            $count = 0;
            foreach ($elementosLink as $elemento) {
                if ($count >= $maxLinksAExtraer) break;
                
                $href = $elemento->getAttribute('href');
                if (!empty($href)) {
                    // Convertir URL relativa a absoluta
                    if (strpos($href, 'http') !== 0) {
                        $urlParts = parse_url($url);
                        $baseUrl = $urlParts['scheme'] . '://' . $urlParts['host'];
                        $href = (strpos($href, '/') === 0) ? $baseUrl . $href : $baseUrl . '/' . $href;
                    }
                    $links[] = $href;
                    $count++;
                }
            }
            $diagnostico['links_encontrados'] = count($links);
        }
        
        if (empty($links)) {
            throw new Exception("No se encontraron links de noticias en la portada");
        }
        
        // Array para rastrear títulos ya procesados y evitar duplicados
        $titulosVistos = [];
        $urlsVistos = [];
        $cantidadDeseada = isset($selectores['cantidad_noticias']) ? (int)$selectores['cantidad_noticias'] : 10;
        
        // NIVEL 2: Visitar cada link y extraer contenido completo
        foreach ($links as $linkNoticia) {
            // Detener si ya tenemos la cantidad deseada
            if (count($resultados) >= $cantidadDeseada) {
                break;
            }
            
            try {
                // Evitar URLs duplicadas en este scraping
                if (in_array($linkNoticia, $urlsVistos)) {
                    continue;
                }
                
                // Verificar si esta URL ya fue scrapeada anteriormente
                if ($db && $medio_id && urlYaEscaneada($db, $medio_id, $linkNoticia)) {
                    $diagnostico['duplicados_bd']++;
                    continue; // Saltar esta URL, ya existe en la BD
                }
                
                $urlsVistos[] = $linkNoticia;
                $diagnostico['links_visitados']++;
                
                $htmlNoticia = @file_get_contents($linkNoticia, false, $context);
                
                if ($htmlNoticia === false) {
                    continue; // Saltar esta noticia si no se puede acceder
                }
                
                $domNoticia = new DOMDocument();
                libxml_use_internal_errors(true);
                $domNoticia->loadHTML(mb_convert_encoding($htmlNoticia, 'HTML-ENTITIES', 'UTF-8'));
                libxml_clear_errors();
                $xpathNoticia = new DOMXPath($domNoticia);
                
                $resultado = [
                    'titulo' => '',
                    'contenido' => '',
                    'imagen' => '',
                    'fecha' => '',
                    'autor' => '',
                    'categoria' => '',
                    'url' => $linkNoticia
                ];
                
                // Extraer título
                if ($selectores['titulo']) {
                    $selectorTitulo = convertirCSSaXPath($selectores['titulo']);
                    $titulos = @$xpathNoticia->query($selectorTitulo);
                    if ($titulos && $titulos->length > 0) {
                        $tituloTexto = limpiarTexto($titulos->item(0)->textContent);
                        if (strlen($tituloTexto) >= 10 && strlen($tituloTexto) <= 200) {
                            // Verificar si el título ya fue procesado
                            if (in_array($tituloTexto, $titulosVistos)) {
                                $diagnostico['duplicados_titulo']++;
                                continue; // Saltar esta noticia duplicada
                            }
                            $resultado['titulo'] = $tituloTexto;
                            $titulosVistos[] = $tituloTexto;
                        } else {
                            $diagnostico['titulo_invalido']++;
                        }
                    } else {
                        $diagnostico['titulo_vacio']++;
                    }
                }
                
                // Si no se extrajo título, saltar esta noticia
                if (empty($resultado['titulo'])) {
                    continue;
                }
                
                // Extraer contenido completo
                if ($selectores['contenido']) {
                    $selectorContenido = convertirCSSaXPath($selectores['contenido']);
                    $contenidos = @$xpathNoticia->query($selectorContenido);
                    if ($contenidos && $contenidos->length > 0) {
                        $nodoContenido = $contenidos->item(0);
                        // Obtener HTML interno para preservar estructura de párrafos
                        $htmlContenido = $domNoticia->saveHTML($nodoContenido);
                        $textoContenido = limpiarTexto($htmlContenido);
                        if (strlen($textoContenido) > 50) {
                            $resultado['contenido'] = $textoContenido;
                        }
                    }
                }
                
                // Extraer imagen
                if ($selectores['imagen']) {
                    $selectorImagen = convertirCSSaXPath($selectores['imagen']);
                    $imagenes = @$xpathNoticia->query($selectorImagen);
                    if ($imagenes && $imagenes->length > 0) {
                        $img = $imagenes->item(0);
                        $src = $img->getAttribute('src');
                        if (!empty($src)) {
                            // Convertir URL relativa a absoluta
                            if (strpos($src, 'http') !== 0) {
                                $urlParts = parse_url($linkNoticia);
                                $baseUrl = $urlParts['scheme'] . '://' . $urlParts['host'];
                                $src = (strpos($src, '/') === 0) ? $baseUrl . $src : $baseUrl . '/' . $src;
                            }
                            $resultado['imagen'] = $src;
                        }
                    }
                }
                
                // Extraer fecha
                if ($selectores['fecha']) {
                    $selectorFecha = convertirCSSaXPath($selectores['fecha']);
                    $fechas = @$xpathNoticia->query($selectorFecha);
                    if ($fechas && $fechas->length > 0) {
                        $textoFecha = limpiarTexto($fechas->item(0)->textContent);
                        if (strlen($textoFecha) < 50) {
                            $resultado['fecha'] = $textoFecha;
                        }
                    }
                }
                
                // Extraer autor
                if ($selectores['autor']) {
                    $selectorAutor = convertirCSSaXPath($selectores['autor']);
                    $autores = @$xpathNoticia->query($selectorAutor);
                    if ($autores && $autores->length > 0) {
                        $textoAutor = limpiarTexto($autores->item(0)->textContent);
                        if (strlen($textoAutor) < 100) {
                            $resultado['autor'] = $textoAutor;
                        }
                    }
                }
                
                // Extraer categoría
                if ($selectores['categoria']) {
                    $selectorCategoria = convertirCSSaXPath($selectores['categoria']);
                    $categorias = @$xpathNoticia->query($selectorCategoria);
                    if ($categorias && $categorias->length > 0) {
                        $textoCategoria = limpiarTexto($categorias->item(0)->textContent);
                        if (strlen($textoCategoria) < 50) {
                            $resultado['categoria'] = $textoCategoria;
                        }
                    }
                }
                
                // Agregar resultado (ya validamos que tenga título)
                $resultados[] = $resultado;
                $diagnostico['guardadas']++;
                
            } catch (Exception $e) {
                // Continuar con el siguiente link si hay error
                continue;
            }
        }
        
    } catch (Exception $e) {
        throw $e;
    }
    
    return $resultados;
}

// Función mejorada para convertir selectores CSS a XPath
function convertirCSSaXPath($selector) {
    if (empty($selector)) {
        return '//*';
    }
    
    $selector = trim($selector);
    
    // Selectores con clase (.clase)
    if (preg_match('/^\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
        return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$matches[1]} ')]";
    }
    
    // Selectores con ID (#id)
    if (preg_match('/^#([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
        return "//*[@id='{$matches[1]}']";
    }
    
    // Etiqueta con clase (div.clase, h1.clase)
    if (preg_match('/^([a-zA-Z0-9]+)\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
        return "//{$matches[1]}[contains(concat(' ', normalize-space(@class), ' '), ' {$matches[2]} ')]";
    }
    
    // Etiqueta con ID (div#id)
    if (preg_match('/^([a-zA-Z0-9]+)#([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
        return "//{$matches[1]}[@id='{$matches[2]}']";
    }
    
    // Selector descendente (div .clase, .padre .hijo, h3.clase a)
    if (strpos($selector, ' ') !== false) {
        $parts = explode(' ', $selector);
        $xpath = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            if (strpos($part, '.') === 0) {
                // .clase
                $clases = explode('.', substr($part, 1));
                $conds = array_map(fn($c) => "contains(concat(' ', normalize-space(@class), ' '), ' {$c} ')", $clases);
                $xpath .= "//*[" . implode(' and ', $conds) . "]";
            } elseif (strpos($part, '#') === 0) {
                // #id
                $id = substr($part, 1);
                $xpath .= "//*[@id='{$id}']";
            } elseif (strpos($part, '.') !== false) {
                // tag.clase o tag.clase1.clase2
                $dotPos = strpos($part, '.');
                $tag = substr($part, 0, $dotPos);
                $clases = explode('.', substr($part, $dotPos + 1));
                $conds = array_map(fn($c) => "contains(concat(' ', normalize-space(@class), ' '), ' {$c} ')", $clases);
                $xpath .= "//{$tag}[" . implode(' and ', $conds) . "]";
            } elseif (strpos($part, '#') !== false) {
                // tag#id
                list($tag, $id) = explode('#', $part, 2);
                $xpath .= "//{$tag}[@id='{$id}']";
            } else {
                // etiqueta simple
                $xpath .= "//{$part}";
            }
        }
        return $xpath;
    }
    
    // Selector de etiqueta simple (h1, div, p)
    if (preg_match('/^[a-zA-Z0-9]+$/', $selector)) {
        return "//{$selector}";
    }
    
    // Si no se reconoce, intentar como etiqueta
    return '//*';
}

// Función para verificar si una URL ya fue scrapeada
function urlYaEscaneada($db, $medio_id, $url) {
    $stmt = $db->prepare("
        SELECT id FROM medios_contenido_sincronizado 
        WHERE medio_id = :medio_id AND url_original = :url
    ");
    $stmt->execute([':medio_id' => $medio_id, ':url' => $url]);
    return $stmt->fetch() !== false;
}

// Función para guardar noticia scrapeada en la BD
function guardarNoticiaScrapeada($db, $medio_id, $noticia) {
    // Crear hash del contenido para detectar duplicados
    $hash = md5($noticia['titulo'] . $noticia['url']);
    
    // Verificar si ya existe por hash
    $stmt = $db->prepare("
        SELECT id FROM medios_contenido_sincronizado 
        WHERE medio_id = :medio_id AND hash_contenido = :hash
    ");
    $stmt->execute([':medio_id' => $medio_id, ':hash' => $hash]);
    
    if ($stmt->fetch()) {
        return false; // Ya existe
    }
    
    // Insertar nueva noticia
    $stmt = $db->prepare("
        INSERT INTO medios_contenido_sincronizado (
            medio_id, titulo, contenido, imagen_url, url_original, 
            fecha_publicacion, autor, categoria, hash_contenido, estado
        ) VALUES (
            :medio_id, :titulo, :contenido, :imagen_url, :url_original,
            :fecha_publicacion, :autor, :categoria, :hash_contenido, 'pendiente'
        )
    ");
    
    return $stmt->execute([
        ':medio_id' => $medio_id,
        ':titulo' => $noticia['titulo'],
        ':contenido' => $noticia['contenido'],
        ':imagen_url' => $noticia['imagen'] ?? null,
        ':url_original' => $noticia['url'],
        ':fecha_publicacion' => $noticia['fecha'] ? date('Y-m-d H:i:s') : null,
        ':autor' => $noticia['autor'] ?? null,
        ':categoria' => $noticia['categoria'] ?? null,
        ':hash_contenido' => $hash
    ]);
}

// Ejecutar scraping si se solicitó
if (isset($_GET['ejecutar']) && $_GET['ejecutar'] == '1') {
    $guardadas = 0;
    $duplicadas = 0;
    
    try {
        $selectores = [
            'link' => $medio['selector_link'],
            'titulo' => $medio['selector_titulo'],
            'contenido' => $medio['selector_contenido'],
            'imagen' => $medio['selector_imagen'],
            'fecha' => $medio['selector_fecha'],
            'autor' => $medio['selector_autor'],
            'categoria' => $medio['selector_categoria'],
            'cantidad_noticias' => $medio['cantidad_noticias'] ?? 10
        ];
        
        $diagnostico = [];
        $resultados = hacerScraping($medio['url'], $selectores, $db, $medio_id, $diagnostico);
        
        if (empty($resultados)) {
            $partes = [];
            if ($diagnostico['links_encontrados'] === 0) {
                $partes[] = "el selector de links (<strong>{$selectores['link']}</strong>) no encontró ningún enlace en la portada";
            } else {
                $partes[] = "se encontraron <strong>{$diagnostico['links_encontrados']}</strong> links en portada";
                if ($diagnostico['duplicados_bd'] > 0)
                    $partes[] = "<strong>{$diagnostico['duplicados_bd']}</strong> ya existían en BD";
                if ($diagnostico['links_visitados'] === 0) {
                    $partes[] = "ningún link fue visitado (todos eran duplicados en BD)";
                } else {
                    $partes[] = "se visitaron <strong>{$diagnostico['links_visitados']}</strong>";
                    if ($diagnostico['titulo_vacio'] > 0)
                        $partes[] = "en <strong>{$diagnostico['titulo_vacio']}</strong> el selector de título (<strong>{$selectores['titulo']}</strong>) no encontró nada";
                    if ($diagnostico['titulo_invalido'] > 0)
                        $partes[] = "en <strong>{$diagnostico['titulo_invalido']}</strong> el título tenía longitud inválida (debe tener entre 10 y 200 caracteres)";
                    if ($diagnostico['duplicados_titulo'] > 0)
                        $partes[] = "<strong>{$diagnostico['duplicados_titulo']}</strong> títulos duplicados descartados";
                }
            }
            $error = "No se encontraron noticias. " . implode('; ', $partes) . ".";
        } else {
            // Guardar noticias en la base de datos
            foreach ($resultados as $noticia) {
                if (guardarNoticiaScrapeada($db, $medio_id, $noticia)) {
                    $guardadas++;
                } else {
                    $duplicadas++;
                }
            }
            
            // Actualizar última sincronización
            $stmt = $db->prepare("UPDATE medios_conectados SET ultima_sincronizacion = NOW() WHERE id = :id");
            $stmt->execute([':id' => $medio_id]);
            
            $mensaje_exito = "Scraping completado: {$guardadas} noticias guardadas, {$duplicadas} ya existían.";
        }
    } catch (Exception $e) {
        $error = "Error al hacer scraping: " . $e->getMessage();
    }
}
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-sync-alt"></i> Hacer Scraping</h1>
        <p>Prueba de extracción de noticias desde: <strong><?php echo htmlspecialchars($medio['nombre']); ?></strong></p>
    </div>
    <a href="medios-diarios.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (isset($mensaje_exito)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
        <a href="noticias-escaneadas.php?medio_id=<?php echo $medio_id; ?>" class="btn btn-sm" style="margin-left: 10px; background: #27ae60; color: white;">
            Ver Noticias Guardadas
        </a>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Configuración del Medio</h2>
    </div>
    <div class="card-body">
        <div class="info-grid">
            <div class="info-item">
                <label>URL:</label>
                <a href="<?php echo htmlspecialchars($medio['url']); ?>" target="_blank">
                    <?php echo htmlspecialchars($medio['url']); ?>
                </a>
            </div>
            <div class="info-item">
                <label>Método:</label>
                <span class="badge <?php echo $medio['usa_api'] ? 'badge-info' : 'badge-secondary'; ?>">
                    <?php echo $medio['usa_api'] ? 'API' : 'Scrapping'; ?>
                </span>
            </div>
            <div class="info-item">
                <label>Cantidad de Noticias:</label>
                <span style="font-weight: 600; color: #2c3e50;">
                    <?php echo $medio['cantidad_noticias'] ?? 10; ?> noticias por scraping
                </span>
            </div>
        </div>
        
        <?php if (!$medio['usa_api']): ?>
            <h3 style="margin-top: 20px;">Selectores CSS Configurados</h3>
            
            <h4 style="color: #3498db; margin: 15px 0 10px;"><i class="fas fa-home"></i> Nivel 1: Portada</h4>
            <div class="selectores-grid">
                <div class="selector-item">
                    <label>Selector de Link:</label>
                    <code><?php echo htmlspecialchars($medio['selector_link'] ?: 'No configurado'); ?></code>
                    <?php if ($medio['selector_link']): ?>
                        <small style="display:block; margin-top: 5px; color: #7f8c8d;">
                            XPath: <?php echo htmlspecialchars(convertirCSSaXPath($medio['selector_link'])); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <h4 style="color: #e74c3c; margin: 15px 0 10px;"><i class="fas fa-newspaper"></i> Nivel 2: Noticia Individual</h4>
            <div class="selectores-grid">
                <div class="selector-item">
                    <label>Título:</label>
                    <code><?php echo htmlspecialchars($medio['selector_titulo'] ?: 'No configurado'); ?></code>
                    <?php if ($medio['selector_titulo']): ?>
                        <small style="display:block; margin-top: 5px; color: #7f8c8d;">
                            XPath: <?php echo htmlspecialchars(convertirCSSaXPath($medio['selector_titulo'])); ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="selector-item">
                    <label>Contenido:</label>
                    <code><?php echo htmlspecialchars($medio['selector_contenido'] ?: 'No configurado'); ?></code>
                    <?php if ($medio['selector_contenido']): ?>
                        <small style="display:block; margin-top: 5px; color: #7f8c8d;">
                            XPath: <?php echo htmlspecialchars(convertirCSSaXPath($medio['selector_contenido'])); ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="selector-item">
                    <label>Imagen:</label>
                    <code><?php echo htmlspecialchars($medio['selector_imagen'] ?: 'No configurado'); ?></code>
                    <?php if ($medio['selector_imagen']): ?>
                        <small style="display:block; margin-top: 5px; color: #7f8c8d;">
                            XPath: <?php echo htmlspecialchars(convertirCSSaXPath($medio['selector_imagen'])); ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="selector-item">
                    <label>Fecha:</label>
                    <code><?php echo htmlspecialchars($medio['selector_fecha'] ?: 'No configurado'); ?></code>
                    <?php if ($medio['selector_fecha']): ?>
                        <small style="display:block; margin-top: 5px; color: #7f8c8d;">
                            XPath: <?php echo htmlspecialchars(convertirCSSaXPath($medio['selector_fecha'])); ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="selector-item">
                    <label>Autor:</label>
                    <code><?php echo htmlspecialchars($medio['selector_autor'] ?: 'No configurado'); ?></code>
                    <?php if ($medio['selector_autor']): ?>
                        <small style="display:block; margin-top: 5px; color: #7f8c8d;">
                            XPath: <?php echo htmlspecialchars(convertirCSSaXPath($medio['selector_autor'])); ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="selector-item">
                    <label>Categoría:</label>
                    <code><?php echo htmlspecialchars($medio['selector_categoria'] ?: 'No configurado'); ?></code>
                    <?php if ($medio['selector_categoria']): ?>
                        <small style="display:block; margin-top: 5px; color: #7f8c8d;">
                            XPath: <?php echo htmlspecialchars(convertirCSSaXPath($medio['selector_categoria'])); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="margin-top: 15px; padding: 12px; background: #ffe8e8; border-left: 4px solid #e74c3c; border-radius: 4px;">
                <strong><i class="fas fa-exclamation-triangle"></i> ¡IMPORTANTE!</strong>
                <p style="margin: 8px 0 0 0; font-size: 14px;">
                    Los selectores de <strong>Nivel 1 (Portada)</strong> y <strong>Nivel 2 (Noticia)</strong> deben ser diferentes:
                </p>
                <ul style="margin: 8px 0 0 20px; font-size: 13px;">
                    <li><strong>Nivel 1:</strong> Extrae los <u>links</u> desde la página principal (portada)</li>
                    <li><strong>Nivel 2:</strong> Extrae el <u>contenido completo</u> desde cada noticia individual</li>
                </ul>
                <p style="margin: 8px 0 0 0; font-size: 13px; color: #c0392b;">
                    <strong>Ejemplo:</strong> Si el selector de título del Nivel 1 es <code>h2.entry-title</code>, 
                    el del Nivel 2 podría ser <code>h1.entry-title</code> o <code>.single-title</code> 
                    porque la estructura de la página individual es diferente.
                </p>
            </div>
            
            <div style="margin-top: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <strong><i class="fas fa-lightbulb"></i> Consejos:</strong>
                <ul style="margin: 10px 0 0 20px; font-size: 14px;">
                    <li>Usa selectores simples como: <code>h2</code>, <code>.titulo</code>, <code>#contenido</code></li>
                    <li>Para clases: <code>.nombre-clase</code> o <code>div.nombre-clase</code></li>
                    <li>Para IDs: <code>#id-elemento</code> o <code>div#id-elemento</code></li>
                    <li>Descendientes: <code>.contenedor .titulo</code></li>
                    <li>Inspecciona el sitio web (F12) para ver la estructura HTML</li>
                    <li><strong>Abre una noticia individual</strong> en tu navegador para ver los selectores del Nivel 2</li>
                </ul>
                
                <div style="margin-top: 12px; padding: 10px; background: #e3f2fd; border-radius: 4px;">
                    <strong><i class="fas fa-info-circle"></i> Ejemplo para periodicolosrios.cl:</strong>
                    
                    <p style="margin: 8px 0 5px 0; font-weight: 600; color: #3498db;">
                        <i class="fas fa-home"></i> Nivel 1 (Portada):
                    </p>
                    <ul style="margin: 5px 0 0 20px; font-size: 13px;">
                        <li><strong>Selector de Link:</strong> <code>.entry-title a</code> o <code>h2.entry-title a</code></li>
                    </ul>
                    
                    <p style="margin: 15px 0 5px 0; font-weight: 600; color: #e74c3c;">
                        <i class="fas fa-newspaper"></i> Nivel 2 (Noticia Individual):
                    </p>
                    <ul style="margin: 5px 0 0 20px; font-size: 13px;">
                        <li><strong>Título:</strong> <code>h1.entry-title</code> o <code>.single-title</code></li>
                        <li><strong>Contenido:</strong> <code>.entry-content</code> o <code>.post-content</code></li>
                        <li><strong>Imagen:</strong> <code>.wp-post-image</code> o <code>.featured-image img</code></li>
                        <li><strong>Fecha:</strong> <code>.entry-date</code> o <code>time.published</code></li>
                        <li><strong>Autor:</strong> <code>.author-name</code> o <code>.byline a</code></li>
                    </ul>
                    
                    <small style="display:block; margin-top:8px; color:#666;">
                        <strong>Nota:</strong> Abre la portada y una noticia individual en tu navegador, 
                        presiona F12 e inspecciona los elementos para ver la estructura HTML real de cada página.
                    </small>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <a href="?id=<?php echo $medio_id; ?>&ejecutar=1" class="btn btn-primary">
                <i class="fas fa-play"></i> Ejecutar Scraping Ahora
            </a>
        </div>
    </div>
</div>

<?php if (!empty($resultados)): ?>
    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h2>
                <i class="fas fa-check-circle"></i> Resultados del Scraping 
                (<?php echo count($resultados); ?> de <?php echo $medio['cantidad_noticias'] ?? 10; ?> noticias solicitadas)
            </h2>
        </div>
        <div class="card-body">
            <div class="noticias-grid">
                <?php foreach ($resultados as $index => $noticia): ?>
                    <div class="noticia-card">
                        <div class="noticia-numero">#<?php echo $index + 1; ?></div>
                        
                        <?php if ($noticia['imagen']): ?>
                            <div class="noticia-imagen">
                                <img src="<?php echo htmlspecialchars($noticia['imagen']); ?>" 
                                     alt="<?php echo htmlspecialchars($noticia['titulo']); ?>"
                                     onerror="this.style.display='none'">
                            </div>
                        <?php else: ?>
                            <div class="noticia-imagen" style="background: #ecf0f1; display: flex; align-items: center; justify-content: center; min-height: 150px;">
                                <span style="color: #95a5a6; font-style: italic;">
                                    <i class="fas fa-image"></i> Sin imagen
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="noticia-contenido">
                            <h3><?php echo htmlspecialchars($noticia['titulo']); ?></h3>
                            
                            <!-- Información de debug -->
                            <div style="background: #fff3cd; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 12px;">
                                <strong><i class="fas fa-bug"></i> Debug:</strong><br>
                                <strong>URL visitada:</strong> <a href="<?php echo htmlspecialchars($noticia['url']); ?>" target="_blank" style="font-size: 11px; word-break: break-all;">
                                    <?php echo htmlspecialchars($noticia['url']); ?>
                                </a><br>
                                <strong>Selector usado:</strong> <code style="background: #f8f9fa; padding: 2px 5px; border-radius: 3px;"><?php echo htmlspecialchars($medio['selector_titulo']); ?></code>
                            </div>
                            
                            <p class="noticia-meta">
                                <i class="fas fa-user"></i> 
                                <?php if ($noticia['autor']): ?>
                                    <?php echo htmlspecialchars($noticia['autor']); ?>
                                <?php else: ?>
                                    <span style="color: #95a5a6; font-style: italic;">No encontrado</span>
                                <?php endif; ?>
                            </p>
                            
                            <p class="noticia-meta">
                                <i class="fas fa-calendar"></i> 
                                <?php if ($noticia['fecha']): ?>
                                    <?php echo htmlspecialchars($noticia['fecha']); ?>
                                <?php else: ?>
                                    <span style="color: #95a5a6; font-style: italic;">No encontrado</span>
                                <?php endif; ?>
                            </p>
                            
                            <?php if (!empty($noticia['categoria'])): ?>
                                <p class="noticia-meta">
                                    <i class="fas fa-folder"></i> <?php echo htmlspecialchars($noticia['categoria']); ?>
                                </p>
                            <?php else: ?>
                                <p class="noticia-meta">
                                    <i class="fas fa-folder"></i> <span style="color: #95a5a6; font-style: italic;">No encontrado</span>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($noticia['url']): ?>
                                <p class="noticia-meta">
                                    <i class="fas fa-external-link-alt"></i> 
                                    <a href="<?php echo htmlspecialchars($noticia['url']); ?>" target="_blank" style="font-size: 12px;">
                                        Abrir en nueva pestaña
                                    </a>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($noticia['contenido']): ?>
                                <p class="noticia-texto"><?php echo htmlspecialchars($noticia['contenido']); ?></p>
                            <?php else: ?>
                                <p class="noticia-texto" style="color: #95a5a6; font-style: italic;">Sin contenido</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 8px;">
                <p style="margin: 0 0 10px 0; color: #2e7d32;">
                    <i class="fas fa-check-circle"></i> 
                    <strong>Scraping completado:</strong> Se extrajeron <?php echo count($resultados); ?> de <?php echo $medio['cantidad_noticias'] ?? 10; ?> noticias solicitadas.
                </p>
                <?php if (count($resultados) < ($medio['cantidad_noticias'] ?? 10)): ?>
                    <p style="margin: 10px 0 0 0; color: #f57c00; font-size: 13px;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Se obtuvieron menos noticias de las solicitadas. Esto puede deberse a noticias duplicadas o links que no pudieron ser procesados.
                        Puedes aumentar la cantidad en la configuración o verificar los selectores.
                    </p>
                <?php endif; ?>
                <p style="margin: 10px 0 0 0; color: #7f8c8d; font-size: 13px;">
                    <i class="fas fa-info-circle"></i> 
                    Estos son resultados de prueba. Para guardarlas como noticias reales, implementa la funcionalidad de importación automática.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.info-item {
    border-left: 3px solid #3498db;
    padding-left: 12px;
}

.info-item label {
    display: block;
    font-weight: 600;
    color: #7f8c8d;
    font-size: 12px;
    margin-bottom: 5px;
}

.selectores-grid {
    display: grid;
    gap: 12px;
    margin-top: 15px;
}

.selector-item {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
}

.selector-item label {
    display: block;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
    font-size: 13px;
}

.selector-item code {
    background: white;
    padding: 8px 12px;
    border-radius: 4px;
    display: block;
    border: 1px solid #e0e0e0;
    color: #e74c3c;
    font-family: 'Courier New', monospace;
}

.noticias-grid {
    display: grid;
    gap: 20px;
}

.noticia-card {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    background: white;
    position: relative;
}

.noticia-numero {
    position: absolute;
    top: -12px;
    left: 20px;
    background: #3498db;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
}

.noticia-imagen {
    margin-bottom: 15px;
    border-radius: 8px;
    overflow: hidden;
    max-height: 300px;
}

.noticia-imagen img {
    width: 100%;
    height: auto;
    display: block;
}

.noticia-contenido h3 {
    color: #2c3e50;
    margin-bottom: 12px;
    font-size: 20px;
}

.noticia-meta {
    color: #7f8c8d;
    font-size: 14px;
    margin: 8px 0;
}

.noticia-meta i {
    margin-right: 5px;
}

.noticia-texto {
    color: #34495e;
    line-height: 1.6;
    margin-top: 12px;
}

.badge-info {
    background: #3498db;
}

.badge-secondary {
    background: #95a5a6;
}
</style>

<?php include 'includes/footer.php'; ?>
