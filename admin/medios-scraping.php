<?php
$page_title = 'Hacer Scraping';
require_once '../includes/config.php';
require_once '../includes/scraping_ai.php';
include 'includes/header.php';

$db = getDB();
$providerCfgVista = getScrapingProviderConfig($db);
$categorias = $db->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();
$comunas    = $db->query("SELECT id, nombre FROM comunas ORDER BY nombre")->fetchAll();
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
    // Decodificar entidades para evitar guardar etiquetas escapadas como texto
    $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
                        $fragmentos = [];
                        foreach ($contenidos as $nodoContenido) {
                            // Si es selector por párrafos (.news-body p), priorizar texto directo del nodo
                            $textoNodo = limpiarTexto($nodoContenido->textContent ?? '');
                            if ($textoNodo !== '' && strlen($textoNodo) > 10) {
                                $fragmentos[] = $textoNodo;
                                continue;
                            }

                            // Fallback: limpiar HTML del nodo si no hubo texto util
                            $htmlNodo = $domNoticia->saveHTML($nodoContenido);
                            $textoDesdeHtml = limpiarTexto($htmlNodo);
                            if ($textoDesdeHtml !== '' && strlen($textoDesdeHtml) > 10) {
                                $fragmentos[] = $textoDesdeHtml;
                            }
                        }

                        // Unir fragmentos y evitar duplicados contiguos
                        $fragmentos = array_values(array_filter($fragmentos));
                        $fragmentosUnicos = [];
                        foreach ($fragmentos as $frag) {
                            $last = end($fragmentosUnicos);
                            if ($last !== $frag) {
                                $fragmentosUnicos[] = $frag;
                            }
                        }

                        $textoContenido = trim(implode("\n\n", $fragmentosUnicos));
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
    
    // Selectores con múltiples clases (.clase1.clase2)
    if (preg_match('/^\.([a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)+)$/', $selector, $matches)) {
        $clases = explode('.', $matches[1]);
        $conds = array_map(fn($c) => "contains(concat(' ', normalize-space(@class), ' '), ' {$c} ')", $clases);
        return "//*[" . implode(' and ', $conds) . "]";
    }

    // Selectores con clase (.clase)
    if (preg_match('/^\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
        return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$matches[1]} ')]";
    }
    
    // Selectores con ID (#id)
    if (preg_match('/^#([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
        return "//*[@id='{$matches[1]}']";
    }
    
    // Etiqueta con múltiples clases (div.clase1.clase2)
    if (preg_match('/^([a-zA-Z0-9]+)\.([a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)+)$/', $selector, $matches)) {
        $tag = $matches[1];
        $clases = explode('.', $matches[2]);
        $conds = array_map(fn($c) => "contains(concat(' ', normalize-space(@class), ' '), ' {$c} ')", $clases);
        return "//{$tag}[" . implode(' and ', $conds) . "]";
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
            if ($part === '>') continue; // Tratar child combinator como descendiente simple

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
            foreach ($resultados as $noticia) {
                if (guardarNoticiaScrapeada($db, $medio_id, $noticia)) {
                    $guardadas++;
                } else {
                    $duplicadas++;
                }
            }
            
            // Actualizar última sincronización
                // Recuperar IDs de las noticias guardadas (necesario para botón IA)
                $urlsScrapeadas = array_column($resultados, 'url');
                if (!empty($urlsScrapeadas)) {
                    $placeholdersIds = implode(',', array_fill(0, count($urlsScrapeadas), '?'));
                    $stmtIds = $db->prepare(
                        "SELECT id, url_original FROM medios_contenido_sincronizado
                         WHERE medio_id = ? AND url_original IN ($placeholdersIds)"
                    );
                    $stmtIds->execute(array_merge([$medio_id], $urlsScrapeadas));
                    $idsPorUrl = array_column($stmtIds->fetchAll(), 'id', 'url_original');
                    foreach ($resultados as &$r) {
                        $r['id'] = $idsPorUrl[$r['url']] ?? null;
                    }
                    unset($r);
                }

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
                    <div class="noticia-card" <?php if (!empty($noticia['id'])): ?>data-noticia-id="<?php echo (int)$noticia['id']; ?>"<?php endif; ?>>
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

                                <?php if (!empty($noticia['id'])): ?>
                                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e0e0e0;">
                                    <button class="btn btn-sm"
                                            style="background: #8e44ad; color: white;"
                                            onclick="redactarIA(<?php echo (int)$noticia['id']; ?>)">
                                        <i class="fas fa-robot"></i> Redacción IA
                                    </button>
                                </div>
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
</style>

<?php if (!empty($resultados)): ?>
<?php
$noticiasParaJs = array_values(array_filter($resultados, fn($r) => !empty($r['id'])));
$noticiasParaJs = array_map(function($r) use ($medio) {
    return [
        'id'          => (int)$r['id'],
        'titulo'      => $r['titulo'],
        'contenido'   => $r['contenido'],
        'imagen_url'  => $r['imagen'],
        'url_original'=> $r['url'],
        'autor'       => $r['autor'] ?? '',
        'categoria'   => $r['categoria'] ?? '',
        'medio_nombre'=> $medio['nombre'],
        'created_at'  => date('Y-m-d H:i:s'),
    ];
}, $noticiasParaJs);
?>
<script>
const noticias = <?php echo json_encode(array_values($noticiasParaJs), JSON_UNESCAPED_UNICODE); ?>;
</script>

<!-- Modal Redacción IA -->
<div id="modal-noticia" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; overflow-y:auto;">
    <div style="background:white; max-width:850px; margin:40px auto; border-radius:12px; overflow:hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">

        <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="color: white; margin: 0; font-size: 20px;">
                <i class="fas fa-newspaper"></i> <span id="modal-titulo-cabecera">Noticia</span>
            </h2>
            <button onclick="cerrarModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 18px;">&times;</button>
        </div>

        <div style="display: flex; border-bottom: 2px solid #e0e0e0; background: #f8f9fa;">
            <button id="tab-noticia" onclick="mostrarTab('noticia')"
                style="padding: 15px 25px; border: none; background: white; border-bottom: 3px solid #667eea; cursor: pointer; font-weight: 600; color: #667eea; font-size: 15px;">
                <i class="fas fa-file-alt"></i> Noticia Original
            </button>
            <button id="tab-ia" onclick="mostrarTab('ia')"
                style="padding: 15px 25px; border: none; background: transparent; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; color: #7f8c8d;">
                <i class="fas fa-robot"></i> Redacción IA
            </button>
        </div>

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

        <div id="panel-ia" style="padding: 25px; display: none;">
            <div id="ia-sin-generar">
                <div style="text-align: center; padding: 30px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-robot" style="font-size: 48px; color: #8e44ad; margin-bottom: 15px;"></i>
                    <p style="color: #555; font-size: 16px; margin: 0;">La IA redactará un artículo periodístico profesional basado en la información de la noticia original.</p>
                </div>

                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 10px; color: #333;">
                            <i class="fas fa-plus-circle" style="color: #8e44ad;"></i> Información complementaria (opcional)
                        </label>
                        <textarea id="ia-info-complementaria"
                            placeholder="Pega información adicional que la IA debe considerar para la redacción. Por ejemplo: comunicados, datos estatísticos, contexto histórico, etc."
                            style="width: 100%; min-height: 120px; padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; font-family: Arial, sans-serif; box-sizing: border-box; resize: vertical;"
                        ></textarea>
                        <small style="display: block; margin-top: 6px; color: #7f8c8d;">
                            💡 Tip: Incluye información adicional para mejorar la calidad de la redacción IA.
                        </small>
                    </div>

                <div style="text-align: center;">
                    <button id="btn-generar" onclick="generarRedaccionIA()"
                        class="btn btn-primary"
                        style="background: #8e44ad; padding: 12px 30px; font-size: 16px;">
                        <i class="fas fa-magic"></i> Generar Redacción con IA
                    </button>
                </div>
            </div>

            <div id="ia-loading" style="display: none; text-align: center; padding: 50px;">
                <div style="display: inline-block; width: 50px; height: 50px; border: 4px solid #e0e0e0; border-top-color: #8e44ad; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                <p style="margin-top: 20px; color: #7f8c8d; font-size: 16px;">La IA está redactando el artículo...</p>
            </div>

            <div id="ia-resultado" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: #27ae60;"><i class="fas fa-check-circle"></i> Redacción completada</h3>
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

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; display: block;">Título del artículo</label>
                    <input type="text" id="ia-titulo-value"
                        style="width: 100%; padding: 10px 14px; border: 2px solid #8e44ad; border-radius: 6px; font-size: 15px; font-weight: 600; box-sizing: border-box;">
                </div>

                <div id="ia-texto" style="line-height: 1.9; color: #2d3748; font-size: 15px; background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #8e44ad; white-space: pre-wrap;"></div>

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
                            <i class="fas fa-check"></i> Confirmar publicación
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
                        <i class="fas fa-cog"></i> Ir a Configuración IA
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

    document.getElementById('modal-medio').innerHTML    = noticia.medio_nombre ? `<i class="fas fa-newspaper"></i> ${noticia.medio_nombre}` : '';
    document.getElementById('modal-autor').innerHTML    = noticia.autor       ? `<i class="fas fa-user"></i> ${noticia.autor}` : '';
    document.getElementById('modal-categoria').innerHTML = noticia.categoria  ? `<i class="fas fa-folder"></i> ${noticia.categoria}` : '';
    document.getElementById('modal-fecha').innerHTML    = noticia.created_at  ? `<i class="fas fa-clock"></i> ${noticia.created_at}` : '';

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
    document.getElementById('panel-ia').style.display      = tab === 'ia'      ? 'block' : 'none';

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
    document.getElementById('ia-sin-generar').style.display = 'block';
    document.getElementById('ia-loading').style.display = 'none';
    document.getElementById('ia-resultado').style.display = 'none';
    document.getElementById('ia-error').style.display = 'none';
    document.getElementById('form-publicar').style.display = 'none';
    document.getElementById('ia-titulo-value').value = '';
    document.getElementById('ia-texto').textContent = '';
        document.getElementById('ia-info-complementaria').value = '';
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
    btnFinal.innerHTML = '<i class="fas fa-check"></i> Confirmar publicación';
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
        body: formData,
        credentials: 'include'
    })
        const infoComplementaria = document.getElementById('ia-info-complementaria').value.trim();
        if (infoComplementaria) {
            formData.append('info_complementaria', infoComplementaria);
        }

        fetch('ajax/redactar-ia.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        })
        .then(async (r) => {
        const raw = await r.text();
        let data;
        try { data = JSON.parse(raw); } catch(e) {
            throw new Error(raw ? ('Respuesta inválida del servidor: ' + raw.substring(0, 220)) : 'Respuesta vacía del servidor');
        }
        if (!r.ok) throw new Error((data.error || ('Error HTTP ' + r.status)) + (data.debug ? (' :: ' + data.debug) : ''));
        return data;
    })
    .then(data => {
        document.getElementById('ia-loading').style.display = 'none';
        if (data.error) {
            document.getElementById('ia-error-msg').textContent = data.error + (data.debug ? ' :: ' + data.debug : '');
            document.getElementById('ia-error').style.display = 'block';
        } else {
            document.getElementById('ia-titulo-value').value = data.titulo || noticiaActual.titulo;
            document.getElementById('ia-texto').textContent = data.texto;
            document.getElementById('form-publicar').style.display = 'none';
            document.getElementById('ia-resultado').style.display = 'block';
        }
    })
    .catch(err => {
        document.getElementById('ia-loading').style.display = 'none';
        document.getElementById('ia-error-msg').textContent = (err && err.message) ? err.message : 'Error de conexión. Intenta de nuevo.';
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
        btn.style.background = '#27ae60'; btn.style.color = 'white';
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
            this.style.background   = active ? '#16a34a' : '#f8fafc';
            this.style.color        = active ? '#ffffff' : '#334155';
            this.style.borderColor  = active ? '#15803d' : '#cbd5e1';
        });
    });
}

function mostrarFormPublicar() {
    const fp = document.getElementById('form-publicar');
    const visible = fp.style.display !== 'none';
    fp.style.display = visible ? 'none' : 'block';
    if (!visible) {
        initComunaTags();
        fp.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function publicarNoticia() {
    const titulo    = document.getElementById('ia-titulo-value').value.trim();
    const contenido = document.getElementById('ia-texto').textContent.trim();
    const categoriaId = document.getElementById('pub-categoria').value;
    const imagenFile  = document.getElementById('pub-imagen-file').files[0];
    const comunasSeleccionadas = Array.from(document.querySelectorAll('#pub-comunas-tags .comuna-tag.selected')).map(el => el.dataset.id);

    if (!titulo)      { mostrarMsgPublicar('El título es obligatorio', 'error'); return; }
    if (!categoriaId) { mostrarMsgPublicar('Debes seleccionar una categoría', 'error'); return; }
    if (!contenido)   { mostrarMsgPublicar('No hay contenido generado', 'error'); return; }

    const btn = document.getElementById('btn-publicar-final');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publicando...';

    const formData = new FormData();
    formData.append('titulo', titulo);
    formData.append('contenido', contenido);
    formData.append('categoria_id', categoriaId);
    formData.append('noticia_id', noticiaActual.id);
    comunasSeleccionadas.forEach(id => formData.append('comunas[]', id));
    if (imagenFile) formData.append('imagen', imagenFile);

    fetch('ajax/publicar-noticia-ia.php', {
        method: 'POST',
        body: formData,
        credentials: 'include'
    })
    .then(async (r) => {
        const raw = await r.text();
        let data;
        try { data = JSON.parse(raw); } catch(e) {
            throw new Error(raw ? ('Respuesta inválida del servidor: ' + raw.substring(0, 220)) : 'Respuesta vacía del servidor');
        }
        if (!r.ok) {
            const det = [];
            if (data.debug) det.push(data.debug);
            if (data.file)  det.push(data.file + (data.line ? ':' + data.line : ''));
            throw new Error((data.error || ('Error HTTP ' + r.status)) + (det.length ? ' :: ' + det.join(' | ') : ''));
        }
        return data;
    })
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmar publicación';
        if (data.error) {
            const det = [];
            if (data.debug) det.push(data.debug);
            if (data.file)  det.push(data.file + (data.line ? ':' + data.line : ''));
            mostrarMsgPublicar(data.error + (det.length ? ' :: ' + det.join(' | ') : ''), 'error');
        } else {
            mostrarMsgPublicar('¡Noticia publicada correctamente! <a href="noticias.php" style="color:white;font-weight:bold;">Ver en noticias →</a>', 'success');
            btn.style.display = 'none';
            document.querySelectorAll('[data-noticia-id="' + noticiaActual.id + '"]').forEach(card => {
                const badge = card.querySelector('.noticia-numero');
                if (badge) badge.style.background = '#27ae60';
            });
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmar publicación';
        mostrarMsgPublicar((err && err.message) ? err.message : 'Error de conexión. Intenta de nuevo.', 'error');
    });
}

function mostrarMsgPublicar(msg, tipo) {
    const div = document.getElementById('pub-msg');
    div.style.cssText = `display:block; padding:10px 15px; border-radius:6px; margin-bottom:10px; ${tipo === 'error' ? 'background:#fde8e8;color:#c0392b;border:1px solid #e74c3c;' : 'background:#e8f8f0;color:#1e8449;border:1px solid #27ae60;'}`;
    div.innerHTML = msg;
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });
document.getElementById('modal-noticia').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});
</script>
<?php endif; ?>

<style>
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
