<?php
/**
 * AJAX: Scraping de página de Facebook (sin API, via JSON embebido en HTML)
 */

// Capturar TODOS los errores/warnings antes de que corrompan el JSON
ob_start();
$logFile = dirname(dirname(dirname(__FILE__))) . '/cache/logs/scraping.log';
@mkdir(dirname($logFile), 0755, true);
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logFile) {
    error_log("[$errno] $errstr in $errfile:$errline", 3, $logFile);
});

require_once '../../includes/config.php';
require_once '../../includes/scraping_ai.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    ob_end_clean();
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$pagina_id = (int)($_POST['pagina_id'] ?? 0);
if ($pagina_id <= 0) {
    ob_end_clean();
    echo json_encode(['error' => 'ID de página inválido']);
    exit;
}

$db = getDB();
$providerCfg = getScrapingProviderConfig($db);

try {
    // Obtener datos de la página
    $stmt = $db->prepare("SELECT * FROM medios_conectados WHERE id = :id AND tipo = 'facebook_scraping'");
    $stmt->execute([':id' => $pagina_id]);
    $pagina = $stmt->fetch();

    if (!$pagina) {
        ob_end_clean();
        echo json_encode(['error' => 'Página no encontrada']);
        exit;
    }

    // Extraer slug desde la URL almacenada
    $parsed = parse_url($pagina['url']);
    $slug = trim($parsed['path'] ?? '', '/');

    if (empty($slug)) {
        ob_end_clean();
        echo json_encode(['error' => 'URL de página inválida: ' . htmlspecialchars($pagina['url'])]);
        exit;
    }

    // --- Scraping ---------------------------------------------------------------
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://www.facebook.com/' . $slug,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => 'gzip, deflate',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: es-CL,es;q=0.9,en;q=0.8',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
        ],
    ]);

    $html      = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        ob_end_clean();
        echo json_encode(['error' => 'Error de red: ' . $curl_err]);
        exit;
    }

    if ($http_code !== 200) {
        ob_end_clean();
        echo json_encode(['error' => "Facebook devolvió HTTP $http_code"]);
        exit;
    }

    // --- Extraer posts según proveedor configurado
    $postsExtraidos = extractFacebookPostsByProvider($providerCfg, 'https://www.facebook.com/' . $slug, $html);
} catch (Exception $e) {
    ob_end_clean();
    error_log("Scraping error: " . $e->getMessage(), 3, $logFile);
    echo json_encode(['error' => 'Error durante el scraping: ' . $e->getMessage()]);
    exit;
}

$guardadas  = 0;
$duplicadas = 0;
$errores    = 0;
$posts_info = [];
$vistos     = [];

foreach ($postsExtraidos as $post) {
    $texto = trim((string)($post['texto'] ?? ''));
    if (mb_strlen($texto) < 10) continue;

    $hash = md5($texto);
    if (isset($vistos[$hash])) continue; // dedup en memoria
    $vistos[$hash] = true;

    $timestamp = (int)($post['timestamp'] ?? 0);
    if ($timestamp <= 0) {
        $timestamp = time();
    }

    // Título: primera línea (máx 500 chars, sin cortar palabras al final)
    $lineas = explode("\n", $texto, 2);
    $tituloRaw = trim((string)($lineas[0] ?? ''));
    if ($tituloRaw === '') {
        $tituloRaw = trim($texto);
    }
    $tituloRaw = preg_replace('/\s+/u', ' ', $tituloRaw);
    $titulo = $tituloRaw;
    if (mb_strlen($titulo) > 500) {
        $titulo = mb_substr($titulo, 0, 500);
        $ultimoEspacio = mb_strrpos($titulo, ' ');
        if ($ultimoEspacio !== false && $ultimoEspacio > 350) {
            $titulo = mb_substr($titulo, 0, $ultimoEspacio);
        }
    }
    if ($titulo === '') {
        $titulo = 'Publicación de Facebook';
    }

    $fecha = date('Y-m-d H:i:s', $timestamp);
    $url_post = 'https://www.facebook.com/' . $slug;

    // Verificar si ya existe en la BD (dedup persistente)
    $chk = $db->prepare("SELECT id FROM medios_contenido_sincronizado WHERE hash_contenido = :hash");
    $chk->execute([':hash' => $hash]);
    if ($chk->fetch()) {
        $duplicadas++;
        continue;
    }

    try {
        $ins = $db->prepare("
            INSERT INTO medios_contenido_sincronizado
                (medio_id, titulo, contenido, imagen_url, url_original, hash_contenido,
                 fecha_publicacion, autor, categoria, estado)
            VALUES
                (:medio_id, :titulo, :contenido, NULL, :url_original, :hash,
                 :fecha, :autor, NULL, 'pendiente')
        ");
        $ins->execute([
            ':medio_id'    => $pagina_id,
            ':titulo'      => $titulo,
            ':contenido'   => $texto,
            ':url_original'=> $url_post,
            ':hash'        => $hash,
            ':fecha'       => $fecha,
            ':autor'       => $pagina['nombre'],
        ]);

        $guardadas++;
        $posts_info[] = [
            'titulo' => mb_substr($titulo, 0, 80),
            'fecha'  => $fecha,
        ];
    } catch (PDOException $e) {
        $errores++;
    }
}

// Actualizar última sincronización
try {
    $db->prepare("UPDATE medios_conectados SET ultima_sincronizacion = NOW() WHERE id = :id")
       ->execute([':id' => $pagina_id]);
} catch (Exception $e) {
    error_log("Update timestamp error: " . $e->getMessage(), 3, $logFile);
}

// Limpiar output buffer para asegurar JSON válido
$warnings = ob_get_clean();
if (!empty(trim($warnings))) {
    error_log("Output buffer warnings: " . substr($warnings, 0, 300), 3, $logFile);
}

echo json_encode([
    'ok'         => true,
    'guardadas'  => $guardadas,
    'duplicadas' => $duplicadas,
    'errores'    => $errores,
    'total_html' => count($postsExtraidos),
    'provider'   => $providerCfg['provider_facebook'] ?? 'direct',
    'posts'      => $posts_info,
    'pagina'     => $pagina['nombre'],
]);
