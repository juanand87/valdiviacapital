<?php
/**
 * AJAX: Scraping de páginas públicas de Facebook.
 * Usa m.facebook.com (versión móvil) que muestra contenido público sin login.
 */

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
$paginaId = (int)($_POST['pagina_id'] ?? 0);

if ($paginaId <= 0) {
    echo json_encode(['error' => 'Página no válida.']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM fb_scraping_paginas WHERE id = ? LIMIT 1");
$stmt->execute([$paginaId]);
$pagina = $stmt->fetch();

if (!$pagina) {
    echo json_encode(['error' => 'Página no encontrada.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: descarga una URL
// ─────────────────────────────────────────────────────────────────────────────
function fbFetch($url, $extraHeaders = []) {
    $headers = array_merge([
        // User-Agent móvil: hace que Facebook sirva la versión m.facebook.com
        'User-Agent: Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: es-CL,es;q=0.9',
        'Accept-Encoding: gzip, deflate',
        'Cache-Control: no-cache',
        'Connection: keep-alive',
    ], $extraHeaders);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
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
// Convertir URL de página a URL móvil
// Ej: https://www.facebook.com/municipalidadvaldivia
//  -> https://m.facebook.com/municipalidadvaldivia
// ─────────────────────────────────────────────────────────────────────────────
function fbToMobileUrl($url) {
    // Extraer el path de la URL (nombre de usuario o ID)
    $url = preg_replace('#^https?://(www\.)?facebook\.com/#i', '', $url);
    $url = rtrim($url, '/');
    // Limpiar query strings o fragments
    $url = preg_replace('/[?#].*/', '', $url);
    return 'https://m.facebook.com/' . $url;
}

// ─────────────────────────────────────────────────────────────────────────────
// Parsear HTML
// ─────────────────────────────────────────────────────────────────────────────
function fbParseHtml($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    return new DOMXPath($dom);
}

// ─────────────────────────────────────────────────────────────────────────────
// Scraping principal: m.facebook.com
// ─────────────────────────────────────────────────────────────────────────────
function scrapeFacebook($pageUrl) {
    $mobileUrl = fbToMobileUrl($pageUrl);
    $html      = fbFetch($mobileUrl);

    if (!$html) {
        return ['error' => 'No se pudo acceder a la página. Verifica que la URL sea correcta.'];
    }

    // Detectar si Facebook exige login
    if (
        preg_match('#/login[/?]#i', $mobileUrl) === false &&
        (
            stripos($html, 'log in to continue') !== false ||
            stripos($html, 'join facebook') !== false ||
            stripos($html, 'signup_wall') !== false ||
            (stripos($html, 'id="loginform"') !== false && stripos($html, 'story_body') === false)
        )
    ) {
        return ['error' => 'Facebook requiere inicio de sesión para ver esta página. Es posible que la página tenga restricciones o no sea pública.'];
    }

    $xpath = fbParseHtml($html);
    $posts = [];
    $seen  = [];

    // ── Estrategia 1: buscar divs con class que contenga "story_body" o "_5rgt" ─────
    // m.facebook.com envuelve cada post en contenedores reconocibles
    $storyNodes = $xpath->query(
        '//*[contains(@class,"story_body_container") or contains(@class,"_5rgt") or contains(@class,"_4kg") or contains(@class,"userContentWrapper")]'
    );

    foreach ($storyNodes as $node) {
        if (count($posts) >= 2) break;

        // Texto del post
        $texto = trim(strip_tags($node->textContent));
        $texto = preg_replace('/\s{2,}/', ' ', $texto);
        if (strlen($texto) < 5) continue;

        // URL del post: buscar primer enlace /permalink/ o /story.php o /posts/
        $postUrl  = null;
        $postId   = null;
        $linkNodes = $xpath->query('.//a[contains(@href,"/permalink/") or contains(@href,"story.php") or contains(@href,"/posts/")]', $node);
        foreach ($linkNodes as $ln) {
            $href = $ln->getAttribute('href');
            // Limpiar URL relativa
            if (strpos($href, 'http') !== 0) {
                $href = 'https://m.facebook.com' . $href;
            }
            // Extraer ID del post
            if (preg_match('#story_fbid=(\d+)#', $href, $mid)) {
                $postId  = $mid[1];
                $postUrl = $href;
                break;
            }
            if (preg_match('#/posts/(\d+)#', $href, $mid)) {
                $postId  = $mid[1];
                $postUrl = $href;
                break;
            }
            if (preg_match('#/permalink/(\d+)#', $href, $mid)) {
                $postId  = $mid[1];
                $postUrl = $href;
                break;
            }
        }

        if (!$postId) {
            // Generar ID por hash del texto para evitar duplicados
            $postId = md5($texto);
        }

        if (isset($seen[$postId])) continue;
        $seen[$postId] = true;

        // Imagen del post
        $imgUrl = null;
        $imgNodes = $xpath->query('.//img[contains(@src,"fbcdn") or contains(@src,"fbstatic") or contains(@src,"facebook")]', $node);
        foreach ($imgNodes as $imgN) {
            $src = $imgN->getAttribute('src');
            // Ignorar íconos pequeños (avatares, emojis)
            $w = (int)$imgN->getAttribute('width');
            $h = (int)$imgN->getAttribute('height');
            if ($w > 0 && $w < 50) continue;
            if ($h > 0 && $h < 50) continue;
            if ($src && stripos($src, 'data:') === false) {
                $imgUrl = $src;
                break;
            }
        }

        // Fecha
        $fechaPost = null;
        $timeNodes = $xpath->query('.//abbr[@data-store] | .//abbr[contains(@class,"_5ptz")] | .//time', $node);
        foreach ($timeNodes as $tn) {
            $ts = $tn->getAttribute('data-store');
            if ($ts) {
                $data = @json_decode($ts, true);
                if (isset($data['time'])) {
                    $fechaPost = date('Y-m-d H:i:s', (int)$data['time']);
                    break;
                }
            }
            $dt = $tn->getAttribute('datetime');
            if ($dt) {
                $fechaPost = date('Y-m-d H:i:s', strtotime($dt));
                break;
            }
        }

        // Limpiar el texto de basura típica de m.facebook.com
        $texto = trim(preg_replace('/\b(Like|Comment|Share|Ver más|Traducir|Seguir)\b/u', '', $texto));
        $texto = trim(preg_replace('/\s{2,}/', ' ', $texto));

        $posts[] = [
            'post_id'    => $postId,
            'url_post'   => $postUrl ?? $mobileUrl,
            'imagen_url' => $imgUrl,
            'texto'      => $texto,
            'fecha_post' => $fechaPost,
        ];
    }

    // ── Estrategia 2: si no encontramos posts con la estrategia 1,
    //    buscar cualquier bloque con texto largo y link a permalink ───────────
    if (empty($posts)) {
        $allLinks = $xpath->query('//a[contains(@href,"story.php") or contains(@href,"/posts/") or contains(@href,"/permalink/")]');
        foreach ($allLinks as $link) {
            if (count($posts) >= 2) break;
            $href = $link->getAttribute('href');
            if (strpos($href, 'http') !== 0) {
                $href = 'https://m.facebook.com' . $href;
            }

            $postId = md5($href);
            if (isset($seen[$postId])) continue;
            $seen[$postId] = true;

            // Texto del padre más cercano
            $parent = $link->parentNode;
            $texto  = $parent ? trim(preg_replace('/\s+/', ' ', strip_tags($parent->textContent))) : '';
            if (strlen($texto) < 10) continue;

            $posts[] = [
                'post_id'    => $postId,
                'url_post'   => $href,
                'imagen_url' => null,
                'texto'      => $texto,
                'fecha_post' => null,
            ];
        }
    }

    if (empty($posts)) {
        return ['error' => 'No se encontraron publicaciones. La página podría ser privada, no tener posts visibles sin login, o Facebook cambió su estructura.'];
    }

    return ['posts' => $posts];
}

// ─────────────────────────────────────────────────────────────────────────────
// Ejecutar
// ─────────────────────────────────────────────────────────────────────────────
$resultado = scrapeFacebook($pagina['page_url']);

if (isset($resultado['error'])) {
    echo json_encode(['error' => $resultado['error']]);
    exit;
}

$rawPosts = $resultado['posts'];

// ─────────────────────────────────────────────────────────────────────────────
// Guardar en BD
// ─────────────────────────────────────────────────────────────────────────────
$savedPosts = [];
foreach ($rawPosts as $post) {
    try {
        $ins = $db->prepare("INSERT INTO fb_scraping_posts
            (pagina_id, post_id, url_post, imagen_url, texto, fecha_post)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                imagen_url = COALESCE(VALUES(imagen_url), imagen_url),
                texto      = COALESCE(VALUES(texto), texto),
                fecha_post = COALESCE(VALUES(fecha_post), fecha_post)");
        $ins->execute([
            $paginaId,
            $post['post_id'],
            $post['url_post'],
            $post['imagen_url'],
            $post['texto'],
            $post['fecha_post'],
        ]);
    } catch (PDOException $e) {
        // duplicado: ignorar
    }

    $savedPosts[] = [
        'post_id'    => $post['post_id'],
        'url_post'   => $post['url_post'],
        'imagen_url' => $post['imagen_url'],
        'texto'      => $post['texto'],
        'fecha_post' => $post['fecha_post'] ? date('d-m-Y', strtotime($post['fecha_post'])) : null,
    ];
}

$db->prepare("UPDATE fb_scraping_paginas SET ultima_revision = NOW() WHERE id = ?")->execute([$paginaId]);

echo json_encode([
    'posts'          => $savedPosts,
    'fecha_revision' => date('d-m-Y H:i'),
]);
