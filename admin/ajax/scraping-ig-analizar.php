<?php
/**
 * AJAX: Scraping de Instagram vía visores públicos de terceros.
 * Instagram bloquea el acceso directo sin sesión, así que usamos visores
 * de terceros que cachean perfiles públicos: imginn.com, picuki.com.
 */

// Capturar errores PHP y devolverlos como JSON
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Error PHP: ' . $err['message'] . ' (línea ' . $err['line'] . ')']);
    }
});

require_once '../../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Sesión expirada. Recarga la página.']);
    exit;
}

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
// Helper: descarga una URL
// ─────────────────────────────────────────────────────────────────────────────
function igFetch($url, $extraHeaders = []) {
    $headers = array_merge([
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: es-CL,es;q=0.9,en-US;q=0.8',
        'Cache-Control: no-cache',
    ], $extraHeaders);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($html !== false && $code >= 200 && $code < 400) ? $html : false;
    }

    $opts = [
        'http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 20],
        'ssl'  => ['verify_peer' => false],
    ];
    return @file_get_contents($url, false, stream_context_create($opts));
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: parsea HTML con DOMXPath
// ─────────────────────────────────────────────────────────────────────────────
function igParseHtml($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    return new DOMXPath($dom);
}

// ─────────────────────────────────────────────────────────────────────────────
// Método 1 – imginn.com
// ─────────────────────────────────────────────────────────────────────────────
function scrapeViaImginn($username) {
    $html = igFetch("https://imginn.com/{$username}/");
    if (!$html) return [];
    if (stripos($html, '/p/') === false) return [];

    $xpath = igParseHtml($html);
    $posts = [];
    $seen  = [];

    $links = $xpath->query('//a[contains(@href,"/p/")]');
    foreach ($links as $link) {
        if (count($posts) >= 2) break;
        $href = $link->getAttribute('href');
        if (!preg_match('#/p/([A-Za-z0-9_\-]{10,15})#', $href, $m)) continue;
        $sc = $m[1];
        if (isset($seen[$sc])) continue;
        $seen[$sc] = true;

        $imgSrc    = null;
        $caption   = null;
        $fechaPost = null;

        $img = $xpath->query('.//img', $link)->item(0);
        if ($img) {
            $imgSrc  = $img->getAttribute('data-src') ?: $img->getAttribute('src') ?: null;
            $caption = trim($img->getAttribute('alt')) ?: null;
        }

        $parent = $link->parentNode;
        if ($parent) {
            foreach (['post-desc', 'post-caption', 'caption', 'desc'] as $cls) {
                $node = $xpath->query('.//*[contains(@class,"' . $cls . '")]', $parent)->item(0);
                if ($node) {
                    $txt = trim(strip_tags($node->textContent));
                    if ($txt !== '') { $caption = $txt; break; }
                }
            }
            $timeNode = $xpath->query('.//time', $parent)->item(0);
            if ($timeNode) {
                $dt = $timeNode->getAttribute('datetime');
                if ($dt) $fechaPost = date('Y-m-d H:i:s', strtotime($dt));
            }
        }

        // Sin imagen: intentar página individual del post
        if (!$imgSrc) {
            $postHtml = igFetch("https://imginn.com/p/{$sc}/", ['Referer: https://imginn.com/']);
            if ($postHtml) {
                $xp2    = igParseHtml($postHtml);
                $imgNode = $xp2->query('//div[contains(@class,"post-img")]//img')->item(0)
                         ?? $xp2->query('//article//img')->item(0)
                         ?? $xp2->query('//img')->item(0);
                if ($imgNode) {
                    $imgSrc = $imgNode->getAttribute('data-src') ?: $imgNode->getAttribute('src') ?: null;
                }
                if (!$caption) {
                    $capNode = $xp2->query('//*[contains(@class,"post-desc") or contains(@class,"caption")]')->item(0);
                    if ($capNode) $caption = trim(strip_tags($capNode->textContent)) ?: null;
                }
                if (!$fechaPost) {
                    $tn = $xp2->query('//time')->item(0);
                    if ($tn) {
                        $dt = $tn->getAttribute('datetime');
                        if ($dt) $fechaPost = date('Y-m-d H:i:s', strtotime($dt));
                    }
                }
            }
        }

        $posts[] = [
            'shortcode'   => $sc,
            'tipo'        => 'image',
            'url_post'    => "https://www.instagram.com/p/{$sc}/",
            'imagen_url'  => $imgSrc,
            'caption'     => $caption,
            'likes'       => 0,
            'comentarios' => 0,
            'fecha_post'  => $fechaPost,
        ];
    }

    return $posts;
}

// ─────────────────────────────────────────────────────────────────────────────
// Método 2 – picuki.com
// ─────────────────────────────────────────────────────────────────────────────
function scrapeViaPicuki($username) {
    $html = igFetch("https://www.picuki.com/profile/{$username}", ['Referer: https://www.picuki.com/']);
    if (!$html) return [];
    if (stripos($html, '/p/') === false && stripos($html, 'media') === false) return [];

    $xpath = igParseHtml($html);
    $posts = [];
    $seen  = [];

    $links = $xpath->query('//a[contains(@href,"/p/") or contains(@href,"/media/")]');
    foreach ($links as $link) {
        if (count($posts) >= 2) break;
        $href = $link->getAttribute('href');
        if (!preg_match('#/p/([A-Za-z0-9_\-]{10,15})#', $href, $m)) continue;
        $sc = $m[1];
        if (isset($seen[$sc])) continue;
        $seen[$sc] = true;

        $img     = $xpath->query('.//img', $link)->item(0);
        $imgSrc  = $img ? ($img->getAttribute('src') ?: $img->getAttribute('data-src') ?: null) : null;
        $caption = trim($link->getAttribute('title')) ?: ($img ? trim($img->getAttribute('alt')) : null) ?: null;

        $posts[] = [
            'shortcode'   => $sc,
            'tipo'        => 'image',
            'url_post'    => "https://www.instagram.com/p/{$sc}/",
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
// Ejecutar con fallback
// ─────────────────────────────────────────────────────────────────────────────
$posts       = scrapeViaImginn($username);
$fuenteUsada = 'imginn.com';

if (empty($posts)) {
    $posts       = scrapeViaPicuki($username);
    $fuenteUsada = 'picuki.com';
}

if (empty($posts)) {
    echo json_encode([
        'error' => "No se encontraron publicaciones de @{$username}. "
            . "El perfil podría ser privado o los visores (imginn.com, picuki.com) "
            . "aún no tienen datos de este perfil.",
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Guardar en BD
// ─────────────────────────────────────────────────────────────────────────────
$savedPosts = [];
foreach ($posts as $post) {
    try {
        $ins = $db->prepare("INSERT INTO ig_scraping_posts
            (perfil_id, shortcode, tipo, url_post, imagen_url, caption, likes, comentarios, fecha_post)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                imagen_url = COALESCE(VALUES(imagen_url), imagen_url),
                caption    = COALESCE(VALUES(caption), caption),
                likes      = VALUES(likes),
                fecha_post = COALESCE(VALUES(fecha_post), fecha_post)");
        $ins->execute([
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
        // shortcode duplicado: ignorar
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

$db->prepare("UPDATE ig_scraping_perfiles SET ultima_revision = NOW() WHERE id = ?")->execute([$perfilId]);

echo json_encode([
    'posts'          => $savedPosts,
    'fuente'         => $fuenteUsada,
    'fecha_revision' => date('d-m-Y H:i'),
]);
