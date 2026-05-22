<?php
$page_title = 'Hacer Scraping';
require_once '../includes/config.php';
require_once '../includes/scraping_ai.php';
include 'includes/header.php';

$db = getDB();
$providerCfgVista = getScrapingProviderConfig($db);
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

                $providerMode = $selectores['provider_diarios'] ?? 'direct';
                $extraidoIA = null;
                if ($providerMode !== 'direct' && !empty($selectores['provider_cfg'])) {
                    $extraidoIA = extractDiarioArticleByProvider($db, $linkNoticia, $htmlNoticia, $selectores['provider_cfg']);
                }

                $usarExtraccionDirecta = $extraidoIA === null;

                if (!$usarExtraccionDirecta) {
                    $resultado['titulo'] = trim((string)($extraidoIA['titulo'] ?? ''));
                    $resultado['contenido'] = trim((string)($extraidoIA['contenido'] ?? ''));
                    $resultado['autor'] = $extraidoIA['autor'] ?? '';
                    $resultado['categoria'] = $extraidoIA['categoria'] ?? '';
                    $resultado['fecha'] = $extraidoIA['fecha'] ?? '';
                }
                
                // Extraer título
                if ($usarExtraccionDirecta && $selectores['titulo']) {
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
                if ($usarExtraccionDirecta && $selectores['contenido']) {
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
                if ($usarExtraccionDirecta && $selectores['fecha']) {
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
                if ($usarExtraccionDirecta && $selectores['autor']) {
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
                if ($usarExtraccionDirecta && $selectores['categoria']) {
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
    
    $result = $stmt->execute([
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
    
    // Si se insertó, retornar el ID de la noticia
    if ($result) {
        return $db->lastInsertId();
    }
    
    return false;
}

// Ejecutar scraping si se solicitó
if (isset($_GET['ejecutar']) && $_GET['ejecutar'] == '1') {
    $guardadas = 0;
    $duplicadas = 0;
    
    try {
        $providerCfg = getScrapingProviderConfig($db);
        $selectores = [
            'link' => $medio['selector_link'],
            'titulo' => $medio['selector_titulo'],
            'contenido' => $medio['selector_contenido'],
            'imagen' => $medio['selector_imagen'],
            'fecha' => $medio['selector_fecha'],
            'autor' => $medio['selector_autor'],
            'categoria' => $medio['selector_categoria'],
            'cantidad_noticias' => $medio['cantidad_noticias'] ?? 10,
            'provider_cfg' => $providerCfg,
            'provider_diarios' => ($providerCfg['provider_diarios'] ?? 'direct')
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
            $resultados_con_ids = [];
            foreach ($resultados as $noticia) {
                $noticia_id = guardarNoticiaScrapeada($db, $medio_id, $noticia);
                if ($noticia_id) {
                    $guardadas++;
                    $noticia['id'] = $noticia_id; // Agregar ID a la noticia
                    $resultados_con_ids[] = $noticia;
                } else {
                    $duplicadas++;
                }
            }
            
            // Reemplazar resultados con los que tienen IDs
            $resultados = $resultados_con_ids;
            
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
            <div class="info-item">
                <label>Proveedor de extracción:</label>
                <span class="badge badge-info"><?php echo htmlspecialchars($providerCfgVista['provider_diarios'] ?? 'direct'); ?></span>
                <small style="display:block; margin-top:4px; color:#7f8c8d;">Configurable en Configuración IA</small>
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
                            
                            <!-- Botones de acción -->
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0; display: flex; gap: 8px; flex-wrap: wrap;">
                                <button 
                                    class="btn btn-sm"
                                    style="background: #8e44ad; color: white;"
                                    onclick="redactarIAMedios(<?php echo isset($noticia['id']) ? $noticia['id'] : 0; ?>)">
                                    <i class="fas fa-robot"></i> Redacción IA
                                </button>
                                <a href="<?php echo htmlspecialchars($noticia['url']); ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-secondary">
                                    <i class="fas fa-external-link-alt"></i> Abrir Original
                                </a>
                            </div>
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

<!-- Datos de noticias para JavaScript -->
<script>
const noticiasScrapeo = <?php echo json_encode(array_values($resultados), JSON_UNESCAPED_UNICODE); ?>;
</script>

<!-- Cargar categorías y comunas para el formulario -->
<script>
const categoriasDisponibles = <?php 
    $categorias = $db->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();
    echo json_encode($categorias, JSON_UNESCAPED_UNICODE);
?>;

const comunasDisponibles = <?php 
    $comunas = $db->query("SELECT id, nombre FROM comunas ORDER BY nombre")->fetchAll();
    echo json_encode($comunas, JSON_UNESCAPED_UNICODE);
?>;
</script>

<!-- Modal Ver Noticia / Redacción IA -->
<div id="modal-noticia-scrapeo" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; overflow-y:auto;">
    <div style="background:white; max-width:850px; margin:40px auto; border-radius:12px; overflow:hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        
        <!-- Header del modal -->
        <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="color: white; margin: 0; font-size: 20px;">
                <i class="fas fa-newspaper"></i> <span id="modal-scrapeo-titulo-cabecera">Noticia</span>
            </h2>
            <button onclick="cerrarModalScrapeo()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 18px;">&times;</button>
        </div>
        
        <!-- Tabs -->
        <div style="display: flex; border-bottom: 2px solid #e0e0e0; background: #f8f9fa;">
            <button id="tab-noticia-scrapeo" onclick="mostrarTabScrapeo('noticia')" 
                style="padding: 15px 25px; border: none; background: white; border-bottom: 3px solid #667eea; cursor: pointer; font-weight: 600; color: #667eea; font-size: 15px;">
                <i class="fas fa-file-alt"></i> Noticia Original
            </button>
            <button id="tab-ia-scrapeo" onclick="mostrarTabScrapeo('ia')" 
                style="padding: 15px 25px; border: none; background: transparent; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; color: #7f8c8d;">
                <i class="fas fa-robot"></i> Redacción IA
            </button>
        </div>
        
        <!-- Tab: Noticia Original -->
        <div id="panel-noticia-scrapeo" style="padding: 25px;">
            <div id="modal-scrapeo-imagen" style="margin-bottom: 20px;"></div>
            
            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
                <div id="modal-scrapeo-medio" style="font-size: 13px; color: #7f8c8d;"></div>
                <div id="modal-scrapeo-autor" style="font-size: 13px; color: #7f8c8d;"></div>
                <div id="modal-scrapeo-categoria" style="font-size: 13px; color: #7f8c8d;"></div>
                <div id="modal-scrapeo-fecha" style="font-size: 13px; color: #7f8c8d;"></div>
            </div>
            
            <h1 id="modal-scrapeo-titulo" style="font-size: 24px; margin-bottom: 20px; color: #1a202c; line-height: 1.4;"></h1>
            
            <div id="modal-scrapeo-contenido" style="line-height: 1.8; color: #2d3748; font-size: 15px; white-space: pre-wrap;"></div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <a id="modal-scrapeo-url" href="#" target="_blank" class="btn btn-secondary btn-sm">
                    <i class="fas fa-external-link-alt"></i> Ver noticia original
                </a>
            </div>
        </div>
        
        <!-- Tab: Redacción IA -->
        <div id="panel-ia-scrapeo" style="padding: 25px; display: none;">
            <div id="ia-sin-generar-scrapeo">
                <div style="text-align: center; padding: 30px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-robot" style="font-size: 48px; color: #8e44ad; margin-bottom: 15px;"></i>
                    <p style="color: #555; font-size: 16px; margin: 0;">La IA redactará un artículo periodístico profesional basado en la información de la noticia original.</p>
                </div>
                <div style="text-align: center;">
                    <button id="btn-generar-scrapeo" onclick="generarRedaccionIAScrapeo()" 
                        class="btn btn-primary"
                        style="background: #8e44ad; padding: 12px 30px; font-size: 16px;">
                        <i class="fas fa-magic"></i> Generar Redacción con IA
                    </button>
                </div>
            </div>
            
            <div id="ia-loading-scrapeo" style="display: none; text-align: center; padding: 50px;">
                <div style="display: inline-block; width: 50px; height: 50px; border: 4px solid #e0e0e0; border-top-color: #8e44ad; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                <p style="margin-top: 20px; color: #7f8c8d; font-size: 16px;">La IA está redactando el artículo...</p>
            </div>
            
            <div id="ia-resultado-scrapeo" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: #27ae60;"><i class="fas fa-check-circle"></i> Redacción completada</h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button onclick="copiarRedaccionScrapeo()" class="btn btn-sm btn-secondary">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                        <button onclick="generarRedaccionIAScrapeo()" class="btn btn-sm" style="background: #8e44ad; color: white;">
                            <i class="fas fa-redo"></i> Regenerar
                        </button>
                        <button onclick="mostrarFormPublicarScrapeo()" class="btn btn-sm" style="background: #27ae60; color: white;">
                            <i class="fas fa-paper-plane"></i> Publicar
                        </button>
                    </div>
                </div>

                <!-- Campo título generado por IA -->
                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; display: block;">Título del artículo</label>
                    <input type="text" id="ia-titulo-value-scrapeo"
                        style="width: 100%; padding: 10px 14px; border: 2px solid #8e44ad; border-radius: 6px; font-size: 15px; font-weight: 600; box-sizing: border-box;">
                </div>

                <div id="ia-texto-scrapeo" style="line-height: 1.9; color: #2d3748; font-size: 15px; background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #8e44ad; white-space: pre-wrap;"></div>

                <!-- Formulario de publicación -->
                <div id="form-publicar-scrapeo" style="display: none; margin-top: 20px; padding: 20px; background: #f0fff4; border: 2px solid #27ae60; border-radius: 8px;">
                    <h4 style="margin: 0 0 15px 0; color: #27ae60;"><i class="fas fa-paper-plane"></i> Publicar en el sitio</h4>

                    <div class="form-group">
                        <label style="font-weight: 600;">Categoría <span style="color:red">*</span></label>
                        <select id="pub-categoria-scrapeo" class="form-control">
                            <option value="">-- Seleccionar categoría --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600;">Comunas <span style="color:red">*</span></label>
                        <div id="pub-comunas-container-scrapeo" style="border: 1px solid #ddd; border-radius: 6px; padding: 10px; background: white; min-height: 40px; max-height: 200px; overflow-y: auto;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px;"></div>
                        </div>
                        <div style="margin-top: 8px; font-size: 12px; color: #666;">
                            <strong>Seleccionadas:</strong> <span id="comunas-selected-scrapeo">Ninguna</span>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button onclick="publicarRedaccionScrapeo()" class="btn btn-primary" style="background: #27ae60;">
                            <i class="fas fa-check"></i> Confirmar publicación
                        </button>
                        <button onclick="mostrarFormPublicarScrapeo(false)" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<script>
let noticia_id_actual_scrapeo = 0;

function redactarIAMedios(noticia_id) {
    noticia_id_actual_scrapeo = noticia_id;
    const noticia = noticiasScrapeo.find(n => n.id == noticia_id);
    
    if (!noticia) {
        alert('Noticia no encontrada');
        return;
    }
    
    // Mostrar modal
    document.getElementById('modal-noticia-scrapeo').style.display = 'block';
    
    // Llenar datos de la noticia
    document.getElementById('modal-scrapeo-titulo-cabecera').textContent = noticia.titulo.substring(0, 50);
    document.getElementById('modal-scrapeo-titulo').textContent = noticia.titulo;
    document.getElementById('modal-scrapeo-contenido').textContent = noticia.contenido || 'Sin contenido';
    document.getElementById('modal-scrapeo-url').href = noticia.url;
    document.getElementById('modal-scrapeo-url').textContent = noticia.url;
    
    // Meta información
    const metaElements = [];
    if (noticia.medio_nombre) metaElements.push(`<strong><i class="fas fa-newspaper"></i> ${htmlEscape(noticia.medio_nombre)}</strong>`);
    if (noticia.autor) metaElements.push(`<strong><i class="fas fa-user"></i> ${htmlEscape(noticia.autor)}</strong>`);
    if (noticia.categoria) metaElements.push(`<strong><i class="fas fa-folder"></i> ${htmlEscape(noticia.categoria)}</strong>`);
    if (noticia.fecha) metaElements.push(`<strong><i class="fas fa-calendar"></i> ${htmlEscape(noticia.fecha)}</strong>`);
    
    document.getElementById('modal-scrapeo-medio').innerHTML = metaElements.join(' | ') || '<span style="color: #95a5a6;">Sin información</span>';
    
    // Imagen
    const imagenContainer = document.getElementById('modal-scrapeo-imagen');
    if (noticia.imagen) {
        imagenContainer.innerHTML = '<img src="' + htmlEscape(noticia.imagen) + '" alt="Imagen" style="max-width: 100%; max-height: 300px; border-radius: 8px;" onerror="this.style.display=\'none\'">';
    } else {
        imagenContainer.innerHTML = '';
    }
    
    // Limpiar pestaña de IA
    document.getElementById('ia-sin-generar-scrapeo').style.display = 'block';
    document.getElementById('ia-loading-scrapeo').style.display = 'none';
    document.getElementById('ia-resultado-scrapeo').style.display = 'none';
    document.getElementById('form-publicar-scrapeo').style.display = 'none';
    
    // Mostrar primera pestaña
    mostrarTabScrapeo('noticia');
}

function cerrarModalScrapeo() {
    document.getElementById('modal-noticia-scrapeo').style.display = 'none';
}

function mostrarTabScrapeo(tab) {
    const noticiaPanelVisible = document.getElementById('panel-noticia-scrapeo').style.display !== 'none';
    const iaPanelVisible = document.getElementById('panel-ia-scrapeo').style.display !== 'none';
    
    if (tab === 'noticia') {
        document.getElementById('panel-noticia-scrapeo').style.display = 'block';
        document.getElementById('panel-ia-scrapeo').style.display = 'none';
        document.getElementById('tab-noticia-scrapeo').style.borderBottomColor = '#667eea';
        document.getElementById('tab-noticia-scrapeo').style.color = '#667eea';
        document.getElementById('tab-ia-scrapeo').style.borderBottomColor = 'transparent';
        document.getElementById('tab-ia-scrapeo').style.color = '#7f8c8d';
    } else if (tab === 'ia') {
        document.getElementById('panel-noticia-scrapeo').style.display = 'none';
        document.getElementById('panel-ia-scrapeo').style.display = 'block';
        document.getElementById('tab-noticia-scrapeo').style.borderBottomColor = 'transparent';
        document.getElementById('tab-noticia-scrapeo').style.color = '#7f8c8d';
        document.getElementById('tab-ia-scrapeo').style.borderBottomColor = '#667eea';
        document.getElementById('tab-ia-scrapeo').style.color = '#667eea';
    }
}

function generarRedaccionIAScrapeo() {
    if (!noticia_id_actual_scrapeo) return;
    
    const noticia = noticiasScrapeo.find(n => n.id == noticia_id_actual_scrapeo);
    if (!noticia) return;
    
    document.getElementById('ia-sin-generar-scrapeo').style.display = 'none';
    document.getElementById('ia-loading-scrapeo').style.display = 'block';
    document.getElementById('ia-resultado-scrapeo').style.display = 'none';
    
    // Llamar AJAX a redactar-ia.php
    fetch('ajax/redactar-ia.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            noticia_id: noticia_id_actual_scrapeo,
            titulo: noticia.titulo,
            contenido: noticia.contenido
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('ia-loading-scrapeo').style.display = 'none';
        
        if (data.success) {
            document.getElementById('ia-titulo-value-scrapeo').value = data.titulo;
            document.getElementById('ia-texto-scrapeo').textContent = data.contenido;
            document.getElementById('ia-resultado-scrapeo').style.display = 'block';
        } else {
            alert('Error al generar redacción: ' + (data.error || 'Error desconocido'));
            document.getElementById('ia-sin-generar-scrapeo').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('ia-loading-scrapeo').style.display = 'none';
        document.getElementById('ia-sin-generar-scrapeo').style.display = 'block';
        alert('Error al generar redacción');
    });
}

function copiarRedaccionScrapeo() {
    const titulo = document.getElementById('ia-titulo-value-scrapeo').value;
    const contenido = document.getElementById('ia-texto-scrapeo').textContent;
    const texto = titulo + '\n\n' + contenido;
    
    navigator.clipboard.writeText(texto).then(() => {
        alert('Redacción copiada al portapapeles');
    });
}

function mostrarFormPublicarScrapeo(mostrar = true) {
    if (mostrar) {
        document.getElementById('form-publicar-scrapeo').style.display = 'block';
        cargarCategoriasYComunasScrapeo();
    } else {
        document.getElementById('form-publicar-scrapeo').style.display = 'none';
    }
}

function cargarCategoriasYComunasScrapeo() {
    // Categorías
    const selectCategoria = document.getElementById('pub-categoria-scrapeo');
    selectCategoria.innerHTML = '<option value="">-- Seleccionar categoría --</option>';
    categoriasDisponibles.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.id;
        option.textContent = cat.nombre;
        selectCategoria.appendChild(option);
    });
    
    // Comunas
    const comunasContainer = document.querySelector('#pub-comunas-container-scrapeo > div');
    comunasContainer.innerHTML = '';
    comunasDisponibles.forEach(com => {
        const label = document.createElement('label');
        label.style.cssText = 'display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px; border-radius: 4px; transition: background 0.2s;';
        label.innerHTML = `
            <input type="checkbox" value="${com.id}" class="pub-comuna-checkbox-scrapeo" data-nombre="${htmlEscape(com.nombre)}" style="cursor: pointer;">
            <span style="font-size: 14px;">${htmlEscape(com.nombre)}</span>
        `;
        label.addEventListener('mouseover', () => label.style.background = '#f0f0f0');
        label.addEventListener('mouseout', () => label.style.background = '');
        
        // Agregar evento para actualizar lista de seleccionadas
        label.querySelector('input').addEventListener('change', actualizarComunasSeleccionadasScrapeo);
        
        comunasContainer.appendChild(label);
    });
}

function actualizarComunasSeleccionadasScrapeo() {
    const checkboxes = document.querySelectorAll('.pub-comuna-checkbox-scrapeo:checked');
    const nombres = Array.from(checkboxes).map(cb => cb.dataset.nombre);
    document.getElementById('comunas-selected-scrapeo').textContent = nombres.length > 0 ? nombres.join(', ') : 'Ninguna';
}

function publicarRedaccionScrapeo() {
    const titulo = document.getElementById('ia-titulo-value-scrapeo').value.trim();
    const contenido = document.getElementById('ia-texto-scrapeo').textContent.trim();
    const categoria_id = document.getElementById('pub-categoria-scrapeo').value;
    
    // Obtener comunas seleccionadas
    const comunasCheckboxes = document.querySelectorAll('.pub-comuna-checkbox-scrapeo:checked');
    const comunas_ids = Array.from(comunasCheckboxes).map(cb => cb.value);
    
    // Validar
    if (!titulo) {
        alert('Por favor ingresa un título');
        return;
    }
    if (!contenido) {
        alert('Por favor ingresa el contenido');
        return;
    }
    if (!categoria_id) {
        alert('Por favor selecciona una categoría');
        return;
    }
    if (comunas_ids.length === 0) {
        alert('Por favor selecciona al menos una comuna');
        return;
    }
    
    // Enviar a publicar-noticia-ia.php
    fetch('ajax/publicar-noticia-ia.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            titulo: titulo,
            contenido: contenido,
            category_id: categoria_id,
            communes_ids: comunas_ids,
            medios_contenido_id: noticia_id_actual_scrapeo
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('¡Noticia publicada exitosamente!');
            cerrarModalScrapeo();
            // Recargar la lista o hacer algo
        } else {
            alert('Error al publicar: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al publicar');
    });
}

function htmlEscape(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function(event) {
    const modal = document.getElementById('modal-noticia-scrapeo');
    if (event.target === modal) {
        cerrarModalScrapeo();
    }
});

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModalScrapeo();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
