<?php
/**
 * AJAX: Scraping de página de Facebook (sin API, via JSON embebido en HTML)
 */
require_once '../../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$pagina_id = (int)($_POST['pagina_id'] ?? 0);
if ($pagina_id <= 0) {
    echo json_encode(['error' => 'ID de página inválido']);
    exit;
}

$db = getDB();

// Obtener datos de la página
$stmt = $db->prepare("SELECT * FROM medios_conectados WHERE id = :id AND tipo = 'facebook_scraping'");
$stmt->execute([':id' => $pagina_id]);
$pagina = $stmt->fetch();

if (!$pagina) {
    echo json_encode(['error' => 'Página no encontrada']);
    exit;
}

// Extraer slug desde la URL almacenada
$parsed = parse_url($pagina['url']);
$slug = trim($parsed['path'] ?? '', '/');

if (empty($slug)) {
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
    echo json_encode(['error' => 'Error de red: ' . $curl_err]);
    exit;
}

if ($http_code !== 200) {
    echo json_encode(['error' => "Facebook devolvió HTTP $http_code para /$slug"]);
    exit;
}

// --- Extraer posts del JSON embebido ----------------------------------------
preg_match_all('#"message":\{"text":"((?:[^"\\\\]|\\\\.)*)"\}#', $html, $msg_matches);
preg_match_all('#"creation_time":(\d{10})#', $html, $time_matches);

$textos     = $msg_matches[1]  ?? [];
$timestamps = $time_matches[1] ?? [];

$guardadas  = 0;
$duplicadas = 0;
$errores    = 0;
$posts_info = [];
$vistos     = [];

foreach ($textos as $i => $raw) {
    // Decodificar escapes Unicode/JSON
    $texto = @json_decode('"' . $raw . '"');
    if (!is_string($texto)) {
        $texto = $raw; // fallback: usar crudo
    }
    $texto = trim($texto);
    if (mb_strlen($texto) < 10) continue;

    $hash = md5($texto);
    if (isset($vistos[$hash])) continue; // dedup en memoria
    $vistos[$hash] = true;

    $timestamp = isset($timestamps[$i]) ? (int)$timestamps[$i] : time();

    // Título: primera línea (máx 200 chars)
    $lineas = explode("\n", $texto, 2);
    $titulo = mb_substr(trim($lineas[0]), 0, 200);
    if (empty($titulo)) {
        $titulo = mb_substr($texto, 0, 200);
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
$db->prepare("UPDATE medios_conectados SET ultima_sincronizacion = NOW() WHERE id = :id")
   ->execute([':id' => $pagina_id]);

echo json_encode([
    'ok'         => true,
    'guardadas'  => $guardadas,
    'duplicadas' => $duplicadas,
    'errores'    => $errores,
    'total_html' => count($textos),
    'posts'      => $posts_info,
    'pagina'     => $pagina['nombre'],
]);
