<?php
/**
 * AJAX: Scraping de páginas públicas de Facebook.
 *
 * Estrategia:
 *   1. mbasic.facebook.com  – versión ultra-reducida, sin JS, más accesible
 *   2. m.facebook.com       – versión móvil como fallback
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
// Extraer el slug/path de la URL de Facebook
// ej: https://www.facebook.com/municipalidadvaldivia → municipalidadvaldivia
// ─────────────────────────────────────────────────────────────────────────────
function fbSlug($url) {
    $slug = preg_replace('#^https?://(www\.|m\.|mbasic\.)?facebook\.com/#i', '', $url);
    $slug = rtrim($slug, '/');
    $slug = preg_replace('/[?#].*/', '', $slug);
    return $slug;
}

// ─────────────────────────────────────────────────────────────────────────────
// Descarga una URL con cURL; devuelve array con html, code, curl_error, final_url
// ─────────────────────────────────────────────────────────────────────────────
function fbFetch($url) {
    if (!function_exists('curl_init')) {
        return ['html' => false, 'code' => 0, 'curl_error' => 'cURL no disponible', 'final_url' => $url];
    }

    $cookieFile = tempnam(sys_get_temp_dir(), 'fb_cookie_');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 8,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => 'gzip, deflate', // NO brotli: libcurl de XAMPP no lo soporta
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_HTTPHEADER     => [
            // User-Agent escritorio moderno — mbasic responde igual
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: es-CL,es;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
        ],
    ]);

    $html      = curl_exec($ch);
    $code      = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $curlError = curl_error($ch);
    curl_close($ch);

    @unlink($cookieFile);

    return [
        'html'       => ($html !== false && strlen($html) > 100) ? $html : false,
        'code'       => $code,
        'curl_error' => $curlError,
        'final_url'  => $finalUrl,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Detectar muro de login en el HTML
// ─────────────────────────────────────────────────────────────────────────────
function fbIsLoginWall($html, $finalUrl) {
    if (preg_match('#facebook\.com/login#i', $finalUrl)) return true;
    if (preg_match('#facebook\.com/checkpoint#i', $finalUrl)) return true;
    if (stripos($html, 'id="login_form"') !== false) return true;
    if (stripos($html, 'name="login"') !== false && stripos($html, 'name="pass"') !== false) return true;
    if (stripos($html, 'signup_wall') !== false) return true;
    if (stripos($html, 'You must log in') !== false) return true;
    if (stripos($html, 'log in to continue') !== false) return true;
    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Parsear HTML con DOMXPath
// ─────────────────────────────────────────────────────────────────────────────
function fbParseHtml($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    return new DOMXPath($dom);
}

// ─────────────────────────────────────────────────────────────────────────────
// Extraer posts del HTML recibido de mbasic/m.facebook.com
// ─────────────────────────────────────────────────────────────────────────────
function fbExtractPosts($html, $baseUrl) {
    $xpath = fbParseHtml($html);
    $posts = [];
    $seen  = [];

    // mbasic.facebook.com estructura: cada post está en un <div> con id numérico
    // Los posts tienen anclas del tipo /story.php?story_fbid=XXX o /permalink/XXX

    // ── Estrategia A: contenedores de mbasic (div > div > article, o divs con links a story) ──
    $storyNodes = $xpath->query(
        '//*[contains(@class,"story_body_container")]' .
        ' | //*[contains(@class,"userContentWrapper")]' .
        ' | //*[contains(@class,"_5rgt _5nk5")]' .
        ' | //article'
    );

    foreach ($storyNodes as $node) {
        if (count($posts) >= 2) break;
        list($post, $skip) = fbNodeToPost($xpath, $node, $baseUrl, $seen);
        if ($skip) continue;
        $seen[$post['post_id']] = true;
        $posts[] = $post;
    }

    // ── Estrategia B (mbasic específico): buscar todos los divs hijos de #root o #m_story_permalink_view ──
    if (empty($posts)) {
        // En mbasic los posts están dentro de div#root > div > div > div (cada hijo es un post)
        $rootDivs = $xpath->query('//div[@id="root"]/div/div/div | //div[@id="timelineBody"]/div/div');
        foreach ($rootDivs as $node) {
            if (count($posts) >= 2) break;
            list($post, $skip) = fbNodeToPost($xpath, $node, $baseUrl, $seen);
            if ($skip) continue;
            $seen[$post['post_id']] = true;
            $posts[] = $post;
        }
    }

    // ── Estrategia C: cualquier enlace a story/posts/permalink con texto padre ──
    if (empty($posts)) {
        $links = $xpath->query(
            '//a[contains(@href,"story.php") or contains(@href,"/posts/") or contains(@href,"/permalink/")]'
        );
        foreach ($links as $link) {
            if (count($posts) >= 2) break;
            $href = $link->getAttribute('href');
            if (strpos($href, 'http') !== 0) {
                $href = rtrim($baseUrl, '/') . $href;
            }
            $postId = fbExtractPostId($href);
            if (!$postId) $postId = md5($href);
            if (isset($seen[$postId])) continue;
            $seen[$postId] = true;

            $parent = $link->parentNode;
            $texto  = $parent ? trim(preg_replace('/\s+/', ' ', strip_tags($parent->textContent))) : '';
            if (strlen($texto) < 10) continue;

            $posts[] = [
                'post_id'    => $postId,
                'url_post'   => $href,
                'imagen_url' => null,
                'texto'      => substr($texto, 0, 600),
                'fecha_post' => null,
            ];
        }
    }

    return $posts;
}

// ─────────────────────────────────────────────────────────────────────────────
// Extraer ID de post desde URL
// ─────────────────────────────────────────────────────────────────────────────
function fbExtractPostId($href) {
    if (preg_match('#story_fbid=(\d+)#', $href, $m)) return $m[1];
    if (preg_match('#/posts/(\d+)#', $href, $m))      return $m[1];
    if (preg_match('#/permalink/(\d+)#', $href, $m))  return $m[1];
    if (preg_match('#[?&]id=(\d+)#', $href, $m))      return $m[1];
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Convertir nodo DOM en datos de post
// Devuelve [$postData, $skip]
// ─────────────────────────────────────────────────────────────────────────────
function fbNodeToPost($xpath, $node, $baseUrl, $seen) {
    $texto = trim(preg_replace('/\s{2,}/', ' ', strip_tags($node->textContent)));
    if (strlen($texto) < 10) return [null, true];

    // Buscar URL del post
    $postUrl = null;
    $postId  = null;
    $linkNodes = $xpath->query(
        './/a[contains(@href,"story.php") or contains(@href,"/posts/") or contains(@href,"/permalink/")]',
        $node
    );
    foreach ($linkNodes as $ln) {
        $href = $ln->getAttribute('href');
        if (strpos($href, 'http') !== 0) {
            $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
        }
        $id = fbExtractPostId($href);
        if ($id) {
            $postId  = $id;
            $postUrl = $href;
            break;
        }
    }
    if (!$postId) $postId = md5($texto);
    if (isset($seen[$postId])) return [null, true];

    // Imagen
    $imgUrl   = null;
    $imgNodes = $xpath->query('.//img', $node);
    foreach ($imgNodes as $imgN) {
        $src = $imgN->getAttribute('src');
        if (!$src || stripos($src, 'data:') === 0) continue;
        $w = (int)$imgN->getAttribute('width');
        $h = (int)$imgN->getAttribute('height');
        if ($w > 0 && $w < 40) continue;
        if ($h > 0 && $h < 40) continue;
        $imgUrl = $src;
        break;
    }

    // Fecha
    $fechaPost = null;
    $timeNodes = $xpath->query('.//abbr[@data-store] | .//abbr[@title] | .//time', $node);
    foreach ($timeNodes as $tn) {
        $ts = $tn->getAttribute('data-store');
        if ($ts) {
            $data = @json_decode($ts, true);
            if (isset($data['time'])) { $fechaPost = date('Y-m-d H:i:s', (int)$data['time']); break; }
        }
        $dt = $tn->getAttribute('datetime');
        if ($dt) { $fechaPost = date('Y-m-d H:i:s', strtotime($dt)); break; }
    }

    // Limpiar texto
    $texto = preg_replace('/\b(Like|Comment|Share|Me gusta|Comentar|Compartir|Ver más|Traducir|Seguir|Más)\b/u', '', $texto);
    $texto = trim(preg_replace('/\s{2,}/', ' ', $texto));
    if (strlen($texto) < 5) return [null, true];
    $texto = substr($texto, 0, 800);

    return [[
        'post_id'    => $postId,
        'url_post'   => $postUrl ?? $baseUrl,
        'imagen_url' => $imgUrl,
        'texto'      => $texto,
        'fecha_post' => $fechaPost,
    ], false];
}

// ─────────────────────────────────────────────────────────────────────────────
// Orquestador: intenta mbasic → m.facebook como fallback
// ─────────────────────────────────────────────────────────────────────────────
function scrapeFacebook($pageUrl) {
    $slug = fbSlug($pageUrl);

    $candidatos = [
        'https://mbasic.facebook.com/' . $slug,
        'https://m.facebook.com/'      . $slug,
    ];

    $lastError = 'No se pudo conectar con Facebook.';

    foreach ($candidatos as $url) {
        $res = fbFetch($url);

        if (!$res['html']) {
            $lastError = sprintf(
                'Facebook bloqueó el acceso a %s (HTTP %d%s).',
                parse_url($url, PHP_URL_HOST),
                $res['code'],
                $res['curl_error'] ? ' — ' . $res['curl_error'] : ''
            );
            continue;
        }

        if (fbIsLoginWall($res['html'], $res['final_url'])) {
            $lastError = 'Facebook exige inicio de sesión para ver esta página (' . parse_url($url, PHP_URL_HOST) . '). La página puede tener restricciones de privacidad.';
            continue;
        }

        $posts = fbExtractPosts($res['html'], $url);

        if (empty($posts)) {
            $lastError = 'Se accedió a la página en ' . parse_url($url, PHP_URL_HOST) . ' pero no se encontraron publicaciones visibles. La página podría no tener posts públicos o Facebook cambió su estructura HTML.';
            continue;
        }

        return ['posts' => $posts, 'fuente' => parse_url($url, PHP_URL_HOST)];
    }

    return ['error' => $lastError];
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

// Guardar en BD
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
    'fuente'         => $resultado['fuente'],
    'fecha_revision' => date('d-m-Y H:i'),
]);
