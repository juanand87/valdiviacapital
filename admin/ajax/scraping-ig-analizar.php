<?php
/**
 * AJAX: Scraping de Instagram vía visores públicos de terceros.
 * Instagram no permite acceso directo sin login, así que usamos visores
 * como imginn.com y picuki.com que cachean contenido público.
 */
require_once '../../includes/config.php';
require_once '../../admin/includes/auth.php';
verificarSesion();

header('Content-Type: application/json; charset=utf-8');

$db       = getDB();
$perfilId = (int)($_POST['perfil_id'] ?? 0);

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

// ─────────────────────────────────────────────────────────────────────────────
// Helpers HTTP
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Descarga una URL con cURL (o file_get_contents como fallback).
 * Permite pasar headers personalizados como Referer.
 */
function igFetch(string $url, array $extraHeaders = []): string|false {
    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: es-CL,es;q=0.9,en-US;q=0.8',
        'Cache-Control: no-cache',
    ];
    $headers = array_merge($defaultHeaders, $extraHeaders);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',          // acepta gzip automáticamente
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($html !== false && $code >= 200 && $code < 400) ? $html : false;
    }

    // Fallback sin cURL
    $headerStr = implode("\r\n", $headers);
    $opts = [
        'http' => ['method' => 'GET', 'header' => $headerStr, 'timeout' => 20],
        'ssl'  => ['verify_peer' => false],
    ];
    return @file_get_contents($url, false, stream_context_create($opts));
}

/**
 * Parsea un HTML con DOMDocument y devuelve [DOMDocument, DOMXPath].
 */
function igParseHtml(string $html): array {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    return [$dom, new DOMXPath($dom)];
}

// ─────────────────────────────────────────────────────────────────────────────
// Método 1 – imginn.com  (usa shortcodes reales de Instagram en sus URLs)
// URL perfil: https://imginn.com/{username}/
// ─────────────────────────────────────────────────────────────────────────────

function scrapeViaImginn(string $username): array {
    $profileUrl = "https://imginn.com/{$username}/";
    $html = igFetch($profileUrl);
    if (!$html) return [];

    // Si pide captcha / login propio, descartar
    if (stripos($html, 'not found') !== false && stripos($html, '/p/') === false) {
        return [];
    }

    [, $xpath] = igParseHtml($html);

    $posts = [];
    $seen  = [];

    // Los posts en imginn tienen links /p/{shortcode}/
    $links = $xpath->query('//a[contains(@href,"/p/")]');
    foreach ($links as $link) {
        if (count($posts) >= 2) break;
        $href = $link->getAttribute('href');
        if (!preg_match('#/p/([A-Za-z0-9_\-]{10,15})#', $href, $m)) continue;
        $shortcode = $m[1];
        if (isset($seen[$shortcode])) continue;
        $seen[$shortcode] = true;

        // Imagen dentro del mismo elemento padre
        $img    = $xpath->query('.//img', $link)->item(0);
        $imgSrc = null;
        if ($img) {
            // Imginn usa data-src para lazy-load
            $imgSrc = $img->getAttribute('data-src') ?: $img->getAttribute('src');
            $imgSrc = $imgSrc ?: null;
        }

        // Caption: buscar en elementos adyacentes o en data-caption / alt
        $caption = null;
        if ($img) {
            $caption = trim($img->getAttribute('alt')) ?: null;
        }
        // Buscar .post-desc o .caption próximo
        $parent  = $link->parentNode;
        if ($parent) {
            foreach (['post-desc', 'post-caption', 'caption', 'desc'] as $cls) {
                $descNode = $xpath->query('.//*[contains(@class,"' . $cls . '")]', $parent)->item(0);
                if ($descNode) {
                    $txt = trim(strip_tags($descNode->textContent));
                    if ($txt !== '') { $caption = $txt; break; }
                }
            }
        }

        // Fecha
        $fechaPost = null;
        $timeNode  = $xpath->query('.//time', $link->parentNode ?? $link)->item(0);
        if ($timeNode) {
            $dt = $timeNode->getAttribute('datetime');
            if ($dt) $fechaPost = date('Y-m-d H:i:s', strtotime($dt));
        }

        $posts[] = [
            'shortcode'   => $shortcode,
            'tipo'        => 'image',
            'url_post'    => "https://www.instagram.com/p/{$shortcode}/",
            'url_visor'   => "https://imginn.com/p/{$shortcode}/",
            'imagen_url'  => $imgSrc,
            'caption'     => $caption,
            'likes'       => 0,
            'comentarios' => 0,
            'fecha_post'  => $fechaPost,
        ];
    }

    // Si obtuvimos shortcodes pero sin imagen, intentar enriquecer con la página del post en imginn
    foreach ($posts as &$post) {
        if ($post['imagen_url']) continue;
        $postHtml = igFetch($post['url_visor'], ['Referer: https://imginn.com/']);
        if (!$postHtml) continue;
        [, $xp] = igParseHtml($postHtml);

        // Imagen principal
        $imgNode = $xp->query('//div[contains(@class,"post-img")]//img')->item(0)
            ?? $xp->query('//article//img')->item(0)
            ?? $xp->query('//img[contains(@class,"post")]')->item(0);
        if ($imgNode) {
            $post['imagen_url'] = $imgNode->getAttribute('data-src') ?: $imgNode->getAttribute('src') ?: null;
        }
        // Caption
        if (!$post['caption']) {
            $capNode = $xp->query('//*[contains(@class,"post-desc") or contains(@class,"caption")]')->item(0);
            if ($capNode) $post['caption'] = trim(strip_tags($capNode->textContent)) ?: null;
        }
        // Fecha
        if (!$post['fecha_post']) {
            $timeNode = $xp->query('//time')->item(0);
            if ($timeNode) {
                $dt = $timeNode->getAttribute('datetime');
                if ($dt) $post['fecha_post'] = date('Y-m-d H:i:s', strtotime($dt));
            }
        }
    }
    unset($post);

    return $posts;
}

// ─────────────────────────────────────────────────────────────────────────────
// Método 2 – picuki.com   (visor alternativo muy usado)
// URL perfil: https://www.picuki.com/profile/{username}
// ─────────────────────────────────────────────────────────────────────────────

function scrapeViaPicuki(string $username): array {
    $profileUrl = "https://www.picuki.com/profile/{$username}";
    $html = igFetch($profileUrl, ['Referer: https://www.picuki.com/']);
    if (!$html) return [];
    if (stripos($html, 'profile not found') !== false || stripos($html, '/p/') === false) return [];

    [, $xpath] = igParseHtml($html);
    $posts = [];
    $seen  = [];

    // Picuki usa /media/{id} pero también hay links /p/{shortcode}
    // Primero intentamos los que tienen shortcode directo
    $links = $xpath->query('//a[contains(@href,"/p/") or contains(@href,"/media/")]');
    foreach ($links as $link) {
        if (count($posts) >= 2) break;
        $href = $link->getAttribute('href');

        $shortcode = null;
        if (preg_match('#/p/([A-Za-z0-9_\-]{10,15})#', $href, $m)) {
            $shortcode = $m[1];
        }
        if (!$shortcode) continue;
        if (isset($seen[$shortcode])) continue;
        $seen[$shortcode] = true;

        $img    = $xpath->query('.//img', $link)->item(0);
        $imgSrc = null;
        if ($img) {
            $imgSrc = $img->getAttribute('src') ?: $img->getAttribute('data-src') ?: null;
            // Picuki pone thumbnails en atributo src que puede venir de CDN
        }

        $caption = trim($link->getAttribute('title')) ?: null;
        if (!$caption && $img) $caption = trim($img->getAttribute('alt')) ?: null;

        $posts[] = [
            'shortcode'   => $shortcode,
            'tipo'        => 'image',
            'url_post'    => "https://www.instagram.com/p/{$shortcode}/",
            'url_visor'   => "https://www.picuki.com/media/{$shortcode}",
            'imagen_url'  => $imgSrc,
            'caption'     => $caption,
            'likes'       => 0,
            'comentarios' => 0,
            'fecha_post'  => null,
        ];
    }

    return $posts;
}

// ─────────────────────────────────────────────────────────────────────────────
// Método 3 – instastories.io   (otro visor público)
// URL: https://instastories.watch/profile/{username}
// ─────────────────────────────────────────────────────────────────────────────

function scrapeViaInstastories(string $username): array {
    $profileUrl = "https://instastories.watch/profile/{$username}";
    $html = igFetch($profileUrl);
    if (!$html) return [];

    $posts = [];
    $seen  = [];
    if (preg_match_all('#/p/([A-Za-z0-9_\-]{10,15})#', $html, $m)) {
        $shortcodes = array_unique($m[1]);
        [, $xpath] = igParseHtml($html);
        foreach ($shortcodes as $sc) {
            if (count($posts) >= 2) break;
            if (isset($seen[$sc])) continue;
            $seen[$sc] = true;

            $imgSrc  = null;
            $caption = null;

            // Buscar img cercana al link que contiene este shortcode
            $link = $xpath->query('//a[contains(@href,"' . $sc . '")]')->item(0);
            if ($link) {
                $img = $xpath->query('.//img', $link->parentNode ?? $link)->item(0);
                if ($img) $imgSrc = $img->getAttribute('src') ?: $img->getAttribute('data-src') ?: null;
            }

            $posts[] = [
                'shortcode'   => $sc,
                'tipo'        => 'image',
                'url_post'    => "https://www.instagram.com/p/{$sc}/",
                'url_visor'   => $profileUrl,
                'imagen_url'  => $imgSrc,
                'caption'     => $caption,
                'likes'       => 0,
                'comentarios' => 0,
                'fecha_post'  => null,
            ];
        }
    }
    return $posts;
}

// ─────────────────────────────────────────────────────────────────────────────
// Ejecutar con fallback entre visores
// ─────────────────────────────────────────────────────────────────────────────

$posts      = scrapeViaImginn($username);
$fuenteUsada = 'imginn.com';

if (empty($posts)) {
    $posts       = scrapeViaPicuki($username);
    $fuenteUsada = 'picuki.com';
}

if (empty($posts)) {
    $posts       = scrapeViaInstastories($username);
    $fuenteUsada = 'instastories.watch';
}

if (empty($posts)) {
    echo json_encode([
        'error' => "No se encontraron publicaciones de @{$username}. "
            . "Es posible que el perfil sea privado, no tenga publicaciones, "
            . "o los visores públicos usados (imginn.com, picuki.com) no tengan datos de este perfil aún."
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Guardar en BD
// ─────────────────────────────────────────────────────────────────────────────

$savedPosts = [];
foreach ($posts as $post) {
    try {
        $stmtIns = $db->prepare("INSERT INTO ig_scraping_posts
            (perfil_id, shortcode, tipo, url_post, imagen_url, caption, likes, comentarios, fecha_post)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                imagen_url = COALESCE(VALUES(imagen_url), imagen_url),
                caption    = COALESCE(VALUES(caption), caption),
                likes      = VALUES(likes),
                fecha_post = COALESCE(VALUES(fecha_post), fecha_post)");
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
        // Ignorar duplicados inesperados
    }

    $savedPosts[] = [
        'shortcode'  => $post['shortcode'],
        'url_post'   => $post['url_post'],
        'imagen_url' => $post['imagen_url'],
        'caption'    => $post['caption'],
        'likes'      => (int)$post['likes'],
        'fecha_post' => $post['fecha_post'] ? date('d-m-Y', strtotime($post['fecha_post'])) : null,
    ];
}

// Actualizar última revisión
$db->prepare("UPDATE ig_scraping_perfiles SET ultima_revision = NOW() WHERE id = ?")->execute([$perfilId]);

echo json_encode([
    'posts'          => $savedPosts,
    'fuente'         => $fuenteUsada,
    'fecha_revision' => date('d-m-Y H:i'),
]);

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
