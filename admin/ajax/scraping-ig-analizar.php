<?php
/**
 * AJAX: Scraping de Instagram
 * Extrae las últimas 2 publicaciones de un perfil público.
 */
require_once '../../includes/config.php';
require_once '../../admin/includes/auth.php';
verificarSesion();

header('Content-Type: application/json; charset=utf-8');

$db        = getDB();
$perfilId  = (int)($_POST['perfil_id'] ?? 0);

if ($perfilId <= 0) {
    echo json_encode(['error' => 'Perfil no válido.']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM ig_scraping_perfiles WHERE id = ? LIMIT 1");
$stmt->execute([$perfilId]);
$perfil = $stmt->fetch();

if (!$perfil) {
    echo json_encode(['error' => 'Perfil no encontrado.']);
    exit;
}

$username = $perfil['username'];

// ── Función de scraping ───────────────────────────────────────────────────────
function igFetchPage(string $url): string|false {
    if (!function_exists('curl_init')) {
        // Fallback con file_get_contents
        $opts = [
            'http' => [
                'method'     => 'GET',
                'header'     => implode("\r\n", [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: es-ES,es;q=0.8',
                ]),
                'timeout'    => 15,
            ],
            'ssl' => ['verify_peer' => false],
        ];
        return @file_get_contents($url, false, stream_context_create($opts));
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.8,en-US;q=0.5',
            'Accept-Encoding: gzip, deflate, br',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Cache-Control: max-age=0',
        ],
    ]);
    $html     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($html !== false && $httpCode === 200) ? $html : false;
}

/**
 * Intenta parsear el JSON embebido en la página de Instagram.
 * Instagram lo coloca en varios scripts; buscamos los más comunes.
 */
function igExtraerPosts(string $html, string $username): array {
    $posts = [];

    // ── Método 1: buscar shortcodes de posts /p/{shortcode}/ en el HTML ───────
    // La URL canónica de cada post siempre sigue este patrón.
    $shortcodes = [];
    if (preg_match_all('#/p/([A-Za-z0-9_\-]{10,15})/#', $html, $m)) {
        $shortcodes = array_unique(array_slice($m[1], 0, 6));
    }

    // ── Método 2: buscar en <script type="application/json"> ─────────────────
    // Instagram puede incluir data estructurada en script tags JSON
    if (preg_match_all('/<script[^>]+type=["\']application\/json["\'][^>]*>(.*?)<\/script>/si', $html, $scriptMatches)) {
        foreach ($scriptMatches[1] as $jsonStr) {
            $data = @json_decode($jsonStr, true);
            if (!$data) continue;
            // Buscar shortcodes dentro de la estructura JSON (recursiva simple)
            $jsonText = $jsonStr;
            if (preg_match_all('"shortcode":"([A-Za-z0-9_\-]{10,15})"', $jsonText, $sm)) {
                $shortcodes = array_unique(array_merge($shortcodes, $sm[1]));
            }
        }
    }

    // ── Método 3: buscar en scripts inline (window._sharedData o similar) ────
    if (preg_match_all('/"shortcode"\s*:\s*"([A-Za-z0-9_\-]{10,15})"/', $html, $m3)) {
        $shortcodes = array_unique(array_merge($shortcodes, $m3[1]));
    }

    if (empty($shortcodes)) {
        return [];
    }

    // Tomamos solo los primeros 2 shortcodes únicos
    $shortcodes = array_values(array_unique($shortcodes));
    $shortcodes = array_slice($shortcodes, 0, 2);

    foreach ($shortcodes as $sc) {
        $postUrl  = "https://www.instagram.com/p/{$sc}/";
        $embedUrl = "https://www.instagram.com/p/{$sc}/embed/";

        // Intentar obtener datos del embed (más ligero y suele funcionar)
        $embedHtml = igFetchPage($embedUrl);

        $imagenUrl = null;
        $caption   = null;
        $likes     = 0;
        $tipo      = 'image';
        $fechaPost = null;

        if ($embedHtml) {
            // Imagen
            if (preg_match('/<img[^>]+class="[^"]*EmbeddedMediaImage[^"]*"[^>]+src="([^"]+)"/i', $embedHtml, $imgM)) {
                $imagenUrl = html_entity_decode($imgM[1]);
            } elseif (preg_match('/<img[^>]+src="([^"]+)"[^>]+class="[^"]*EmbeddedMediaImage[^"]*"/i', $embedHtml, $imgM)) {
                $imagenUrl = html_entity_decode($imgM[1]);
            } elseif (preg_match('/<img[^>]+src="(https:\/\/[^"]+(?:\.jpg|\.jpeg|\.png|\.webp)[^"]*)"[^>]*>/i', $embedHtml, $imgM)) {
                $imagenUrl = html_entity_decode($imgM[1]);
            }
            // Caption
            if (preg_match('/<div[^>]+class="[^"]*Caption[^"]*"[^>]*>(.*?)<\/div>/si', $embedHtml, $capM)) {
                $caption = trim(strip_tags($capM[1]));
            }
            // Fecha
            if (preg_match('/<time[^>]+datetime="([^"]+)"/i', $embedHtml, $dateM)) {
                $fechaPost = date('Y-m-d H:i:s', strtotime($dateM[1]));
            }
            // Tipo: video
            if (stripos($embedHtml, '<video') !== false) {
                $tipo = 'video';
            }
        }

        $posts[] = [
            'shortcode'  => $sc,
            'tipo'       => $tipo,
            'url_post'   => $postUrl,
            'imagen_url' => $imagenUrl,
            'caption'    => $caption,
            'likes'      => $likes,
            'comentarios'=> 0,
            'fecha_post' => $fechaPost,
        ];
    }

    return $posts;
}

// ── Ejecutar scraping ─────────────────────────────────────────────────────────
$profileUrl = "https://www.instagram.com/{$username}/";
$html = igFetchPage($profileUrl);

if ($html === false) {
    echo json_encode(['error' => "No se pudo acceder al perfil @{$username}. Instagram puede haber bloqueado la petición o el perfil es privado."]);
    exit;
}

// Detectar si Instagram pide login
if (
    stripos($html, 'login_required') !== false ||
    stripos($html, '"loginRequired"') !== false ||
    (stripos($html, 'Log in') !== false && stripos($html, 'shortcode') === false && stripos($html, '/p/') === false)
) {
    echo json_encode(['error' => "Instagram requiere inicio de sesión para ver este perfil. Asegúrate de que el perfil sea público."]);
    exit;
}

$posts = igExtraerPosts($html, $username);

if (empty($posts)) {
    echo json_encode(['error' => "No se encontraron publicaciones. El perfil podría ser privado, no tener posts, o Instagram está bloqueando el acceso."]);
    exit;
}

// ── Guardar en BD ─────────────────────────────────────────────────────────────
$savedPosts = [];
foreach ($posts as $post) {
    try {
        $stmtIns = $db->prepare("INSERT INTO ig_scraping_posts
            (perfil_id, shortcode, tipo, url_post, imagen_url, caption, likes, comentarios, fecha_post)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                imagen_url = VALUES(imagen_url),
                caption    = VALUES(caption),
                likes      = VALUES(likes),
                fecha_post = VALUES(fecha_post)");
        $stmtIns->execute([
            $perfilId,
            $post['shortcode'],
            $post['tipo'],
            $post['url_post'],
            $post['imagen_url'],
            $post['caption'],
            $post['likes'],
            $post['comentarios'],
            $post['fecha_post'],
        ]);
    } catch (PDOException $e) {
        // Ignorar duplicados
    }

    $savedPosts[] = [
        'shortcode'  => $post['shortcode'],
        'url_post'   => $post['url_post'],
        'imagen_url' => $post['imagen_url'],
        'caption'    => $post['caption'],
        'likes'      => $post['likes'],
        'fecha_post' => $post['fecha_post'] ? date('d-m-Y', strtotime($post['fecha_post'])) : null,
    ];
}

// Actualizar última revisión
$db->prepare("UPDATE ig_scraping_perfiles SET ultima_revision = NOW() WHERE id = ?")->execute([$perfilId]);

$fechaRevision = date('d-m-Y H:i');

echo json_encode([
    'posts'          => $savedPosts,
    'fecha_revision' => $fechaRevision,
]);
