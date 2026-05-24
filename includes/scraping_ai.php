<?php
/**
 * Helpers para extracción de contenido con proveedores:
 * - direct: selectores / regex locales
 * - jina: r.jina.ai
 * - gemini: parseo estructurado por LLM
 */

function getScrapingProviderConfig($db) {
    $rows = $db->query("SELECT nombre, valor FROM configuracion_ia")->fetchAll(PDO::FETCH_ASSOC);
    $cfg = [];
    foreach ($rows as $row) {
        $cfg[$row['nombre']] = $row['valor'];
    }

    return [
        'provider_diarios'   => $cfg['scraping_provider_diarios'] ?? 'direct',
        'provider_facebook'  => $cfg['scraping_provider_facebook'] ?? 'direct',
        'jina_api_key'       => $cfg['jina_api_key'] ?? '',
        'gemini_api_key'     => $cfg['gemini_api_key'] ?? '',
        'gemini_modelo'      => $cfg['gemini_modelo'] ?? 'gemini-2.5-flash',
        'gemini_temperatura' => (float)($cfg['gemini_temperatura'] ?? 0.3),
        'copilot_api_key'    => $cfg['copilot_api_key'] ?? '',
        'copilot_modelo'     => $cfg['copilot_modelo'] ?? 'auto',
        'copilot_api_url'    => $cfg['copilot_api_url'] ?? 'https://models.inference.ai.azure.com/chat/completions',
    ];
}

function scrapingHttpGet($url, $timeout = 30, $headers = []) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => 'gzip, deflate',
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return ['ok' => $err === '' && $http >= 200 && $http < 300, 'body' => $body, 'http' => $http, 'error' => $err];
}

function getJinaReaderContent($targetUrl, $jinaApiKey = '') {
    $jinaUrl = 'https://r.jina.ai/' . $targetUrl;
    $headers = ['Accept: text/plain'];
    if (!empty($jinaApiKey)) {
        $headers[] = 'Authorization: Bearer ' . $jinaApiKey;
    }
    return scrapingHttpGet($jinaUrl, 45, $headers);
}

function parseJinaMarkdownArticle($markdown, $fallbackUrl = '') {
    $markdown = trim((string)$markdown);
    if ($markdown === '') {
        return null;
    }

    $lines = preg_split('/\r\n|\r|\n/', $markdown);
    $titulo = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (strpos($line, '#') === 0) {
            $titulo = trim(ltrim($line, '# '));
            break;
        }
        if (mb_strlen($line) > 20) {
            $titulo = mb_substr($line, 0, 200);
            break;
        }
    }

    if ($titulo === '') {
        $titulo = 'Sin título';
    }

    return [
        'titulo'    => mb_substr($titulo, 0, 200),
        'contenido' => trim($markdown),
        'autor'     => null,
        'categoria' => null,
        'fecha'     => null,
        'url'       => $fallbackUrl,
    ];
}

function isFacebookNoiseText($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return true;
    }

    $lower = mb_strtolower($text);

    // Frases típicas de páginas de login/landing que no son publicaciones.
    $noisePhrases = [
        'log into facebook',
        'forgot password',
        'create new account',
        'explore the things you love',
        'join facebook',
        'sign up for facebook',
        'inicia sesión en facebook',
        'crear cuenta nueva',
        '¿olvidaste tu contraseña?',
        'olvidaste tu contraseña',
        'meta ©',
        'meta © 202',
        'privacy',
        'cookies',
        'terms',
    ];

    foreach ($noisePhrases as $phrase) {
        if (mb_strpos($lower, $phrase) !== false) {
            return true;
        }
    }

    // Textos demasiado cortos rara vez corresponden a publicaciones reales.
    if (mb_strlen($text) < 25) {
        return true;
    }

    // Exigir al menos 4 palabras para reducir títulos/menus sueltos.
    $wordCount = preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);
    if ($wordCount < 4) {
        return true;
    }

    return false;
}

function normalizeFacebookPermalinkUrl($url, $fallbackPageUrl = '') {
    $u = trim((string)$url);
    if ($u === '') {
        return '';
    }

    $u = str_replace('\\/', '/', $u);
    $u = preg_replace('/\\u0025([0-9a-fA-F]{2})/', '%$1', $u);
    $u = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (strpos($u, '//') === 0) {
        $u = 'https:' . $u;
    } elseif (strpos($u, '/') === 0 && $fallbackPageUrl !== '') {
        $parts = parse_url($fallbackPageUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'www.facebook.com';
        $u = $scheme . '://' . $host . $u;
    }

    if (!preg_match('#^https?://#i', $u)) {
        return '';
    }

    $u = preg_replace('#^https?://(?:m|mbasic)\.facebook\.com#i', 'https://www.facebook.com', $u);

    $parts = parse_url($u);
    if (empty($parts['host'])) {
        return $u;
    }

    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'];
    $path = $parts['path'] ?? '';
    $query = '';

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $queryParts);
        $keep = [];
        foreach (['story_fbid', 'fbid', 'id', 'post_id', 'comment_id'] as $key) {
            if (isset($queryParts[$key]) && $queryParts[$key] !== '') {
                $keep[$key] = $queryParts[$key];
            }
        }
        if (!empty($keep)) {
            $query = '?' . http_build_query($keep);
        }
    }

    return $scheme . '://' . $host . $path . $query;
}

function facebookPostExternalId(array $post, $pageUrl = '') {
    $url = normalizeFacebookPermalinkUrl((string)($post['url_original'] ?? $post['url_candidata'] ?? ''), $pageUrl);
    if ($url !== '') {
        return 'fburl:' . md5($url);
    }

    $timestamp = (int)($post['timestamp'] ?? 0);
    $texto = mb_strtolower(trim((string)($post['texto'] ?? '')));
    $base = trim((string)$pageUrl);
    return 'fbtxt:' . md5($base . '|' . $timestamp . '|' . $texto);
}

function normalizeFacebookPosts(array $posts, $pageUrl = '') {
    $out = [];
    $seen = [];

    foreach ($posts as $p) {
        $texto = trim((string)($p['texto'] ?? ''));
        $externalId = trim((string)($p['contenido_id_externo'] ?? ''));
        if ($texto === '' && $externalId === '') {
            continue;
        }
        if ($externalId === '' && isFacebookNoiseText($texto)) {
            continue;
        }
        if ($externalId === '' && mb_strlen($texto) < 10) {
            continue;
        }

        $urlOriginal = normalizeFacebookPermalinkUrl((string)($p['url_original'] ?? $p['url_candidata'] ?? ''), $pageUrl);
        $externalId = $externalId !== '' ? $externalId : facebookPostExternalId($p, $pageUrl);
        $identity = $externalId !== '' ? $externalId : ('fbtxt:' . md5(mb_strtolower($texto)));

        if (isset($seen[$identity])) {
            continue;
        }
        $seen[$identity] = true;

        $out[] = [
            'texto' => $texto,
            'timestamp' => (int)($p['timestamp'] ?? 0),
            'url_original' => $urlOriginal,
            'contenido_id_externo' => $identity,
            'hash_contenido' => md5(mb_strtolower($texto)),
        ];
    }

    return $out;
}

function cleanFacebookPostText($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }

    // Quitar coletillas visuales típicas de Facebook.
    $text = preg_replace('/\s*(ver\s+menos|see\s+more)\s*$/iu', '', $text);
    $text = preg_replace('/\s{2,}/u', ' ', $text);

    return trim($text);
}

function isLikelyTruncatedFacebookPost($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return false;
    }

    if (preg_match('/(\.\.\.|…)\s*$/u', $text)) {
        return true;
    }

    if (preg_match('/\b(ver\s+menos|see\s+more)\s*$/iu', $text)) {
        return true;
    }

    return false;
}

function extractFacebookPermalinkUrls($html, $fallbackPageUrl = '') {
    $html = (string)$html;
    $urls = [];

    // URLs serializadas en JSON embebido.
    preg_match_all('#"wwwURL":"([^"]+)"#', $html, $m1);
    preg_match_all('#"story":\{"url":"([^"]+)"#', $html, $m2);

    $candidates = array_merge($m1[1] ?? [], $m2[1] ?? []);
    foreach ($candidates as $raw) {
        $u = str_replace('\\/', '/', (string)$raw);
        $u = preg_replace('/\\\\u0025([0-9a-fA-F]{2})/', '%$1', $u);
        $u = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (strpos($u, '//') === 0) {
            $u = 'https:' . $u;
        } elseif (strpos($u, '/') === 0 && $fallbackPageUrl !== '') {
            $parts = parse_url($fallbackPageUrl);
            $scheme = $parts['scheme'] ?? 'https';
            $host = $parts['host'] ?? 'www.facebook.com';
            $u = $scheme . '://' . $host . $u;
        }

        if (!preg_match('#^https?://#i', $u)) {
            continue;
        }

        // Normalizar host para evitar variantes m./mbasic./www.
        $u = preg_replace('#^https?://(?:m|mbasic)\.facebook\.com#i', 'https://www.facebook.com', $u);
        $urls[] = $u;
    }

    return array_values(array_unique($urls));
}

function extractFacebookDirectPostsRaw($html, $pageUrl = '') {
    preg_match_all('#"message":\{"text":"((?:[^"\\]|\\.)*)"\}#', (string)$html, $msgMatches);
    preg_match_all('#"creation_time":(\d{10})#', (string)$html, $timeMatches);

    $texts = $msgMatches[1] ?? [];
    $times = $timeMatches[1] ?? [];
    $urls = extractFacebookPermalinkUrls($html, $pageUrl);

    $posts = [];
    foreach ($texts as $i => $raw) {
        $texto = @json_decode('"' . $raw . '"');
        if (!is_string($texto)) {
            $texto = $raw;
        }

        $texto = cleanFacebookPostText($texto);
        if (mb_strlen($texto) < 10) continue;

        $posts[] = [
            'texto' => $texto,
            'timestamp' => isset($times[$i]) ? (int)$times[$i] : 0,
            'url_candidata' => $urls[$i] ?? null,
            'url_original' => $urls[$i] ?? null,
        ];
    }

    return $posts;
}

function tryExpandFacebookPostFromUrl($url, $currentText) {
    $url = trim((string)$url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return null;
    }

    $res = scrapingHttpGet($url, 20, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: es-CL,es;q=0.9,en;q=0.8',
    ]);

    if (empty($res['ok']) || empty($res['body'])) {
        return null;
    }

    $html = (string)$res['body'];
    $best = trim((string)$currentText);

    // En páginas de detalle suele venir una descripción más extensa.
    if (preg_match('/<meta\s+property="og:description"\s+content="([^"]+)"/iu', $html, $m)) {
        $og = html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $og = cleanFacebookPostText($og);
        if (!isFacebookNoiseText($og) && mb_strlen($og) > mb_strlen($best)) {
            $best = $og;
        }
    }

    // Reusar extracción directa sobre el detalle y escoger el texto más largo.
    $candidates = extractFacebookDirectPostsRaw($html, $url);
    foreach ($candidates as $c) {
        $txt = cleanFacebookPostText((string)($c['texto'] ?? ''));
        if (!isFacebookNoiseText($txt) && mb_strlen($txt) > mb_strlen($best)) {
            $best = $txt;
        }
    }

    if (mb_strlen($best) >= mb_strlen((string)$currentText) + 40) {
        return $best;
    }

    return null;
}

function expandTruncatedFacebookPosts(array $posts, $pageUrl = '') {
    if (empty($posts)) {
        return $posts;
    }

    $expanded = $posts;
    $expandedCount = 0;

    foreach ($expanded as $i => $p) {
        $texto = (string)($p['texto'] ?? '');
        $url = (string)($p['url_candidata'] ?? '');

        if (!isLikelyTruncatedFacebookPost($texto)) {
            continue;
        }
        if ($url === '') {
            continue;
        }

        $full = tryExpandFacebookPostFromUrl($url, $texto);
        if (is_string($full) && $full !== '') {
            $expanded[$i]['texto'] = $full;
            $expandedCount++;
        }

        // Limitar requests extra por corrida para no ralentizar/saturar.
        if ($expandedCount >= 4) {
            break;
        }
    }

    return $expanded;
}

function finalizeFacebookDirectPosts(array $rawPosts, $pageUrl = '') {
    $rawPosts = expandTruncatedFacebookPosts($rawPosts, $pageUrl);

    $plain = [];
    foreach ($rawPosts as $p) {
        $urlOriginal = normalizeFacebookPermalinkUrl((string)($p['url_original'] ?? $p['url_candidata'] ?? ''), $pageUrl);
        $plain[] = [
            'texto' => cleanFacebookPostText((string)($p['texto'] ?? '')),
            'timestamp' => (int)($p['timestamp'] ?? 0),
            'url_original' => $urlOriginal,
            'contenido_id_externo' => $urlOriginal !== '' ? ('fburl:' . md5($urlOriginal)) : facebookPostExternalId($p, $pageUrl),
        ];
    }

    return normalizeFacebookPosts($plain, $pageUrl);
}

function geminiStructuredExtract($cfg, $prompt, $maxTokens = 2048) {
    if (empty($cfg['gemini_api_key'])) {
        return ['error' => 'No hay gemini_api_key configurada'];
    }

    $modelo = $cfg['gemini_modelo'] ?? 'gemini-2.5-flash';
    $payload = json_encode([
        'contents' => [[
            'parts' => [[ 'text' => $prompt ]]
        ]],
        'generationConfig' => [
            'temperature' => (float)($cfg['gemini_temperatura'] ?? 0.3),
            'maxOutputTokens' => (int)$maxTokens
        ]
    ]);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $payload,
            'timeout' => 60,
            'ignore_errors' => true
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$cfg['gemini_api_key']}";
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    if ($resp === false) {
        return ['error' => 'No se pudo conectar con Gemini'];
    }

    $data = json_decode($resp, true);
    if (isset($data['error'])) {
        return ['error' => $data['error']['message'] ?? 'Error de Gemini'];
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = preg_replace('/^```json\s*/i', '', trim($text));
    $text = preg_replace('/\s*```$/', '', $text);

    $json = json_decode(trim($text), true);
    if (!is_array($json)) {
        return ['error' => 'Gemini devolvió respuesta no JSON'];
    }

    return ['ok' => true, 'data' => $json];
}

function copilotStructuredExtract($cfg, $prompt, $maxTokens = 2048) {
    if (empty($cfg['copilot_api_key'])) {
        return ['error' => 'No hay copilot_api_key configurada'];
    }

    $apiUrl = trim((string)($cfg['copilot_api_url'] ?? 'https://models.inference.ai.azure.com/chat/completions'));
    if ($apiUrl === '') {
        $apiUrl = 'https://models.inference.ai.azure.com/chat/completions';
    }

    $modelo = trim((string)($cfg['copilot_modelo'] ?? 'auto'));
    if ($modelo === '') {
        $modelo = 'auto';
    }

    $payload = json_encode([
        'model' => $modelo,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.3,
        'max_tokens' => (int)$maxTokens
    ]);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$cfg['copilot_api_key']}\r\nAccept: application/json\r\n",
            'content' => $payload,
            'timeout' => 60,
            'ignore_errors' => true
        ]
    ];

    $response = @file_get_contents($apiUrl, false, stream_context_create($opts));
    if ($response === false) {
        return ['error' => 'No se pudo conectar con GitHub Copilot'];
    }

    $data = json_decode($response, true);
    if (isset($data['error'])) {
        $msg = is_array($data['error']) ? ($data['error']['message'] ?? 'Error desconocido') : (string)$data['error'];
        return ['error' => $msg];
    }

    $text = $data['choices'][0]['message']['content'] ?? '';
    $text = preg_replace('/^```json\s*/i', '', trim($text));
    $text = preg_replace('/\s*```$/', '', $text);

    $json = json_decode(trim($text), true);
    if (!is_array($json)) {
        return ['error' => 'GitHub Copilot devolvió respuesta no JSON'];
    }

    return ['ok' => true, 'data' => $json];
}

function extractDiarioArticleByProvider($db, $url, $html, $providerCfg) {
    $mode = $providerCfg['provider_diarios'] ?? 'direct';

    if ($mode === 'jina') {
        $jina = getJinaReaderContent($url, $providerCfg['jina_api_key'] ?? '');
        if ($jina['ok']) {
            return parseJinaMarkdownArticle($jina['body'], $url);
        }
        return null;
    }

    if ($mode === 'gemini') {
        $sample = mb_substr(strip_tags((string)$html), 0, 14000);
        $prompt = "Extrae una noticia en JSON válido con esta forma exacta: " .
                  "{\"titulo\":\"...\",\"contenido\":\"...\",\"autor\":\"...\",\"categoria\":\"...\",\"fecha\":\"...\"}. " .
                  "No incluyas texto adicional. URL: {$url}\n\nContenido:\n{$sample}";

        $res = geminiStructuredExtract($providerCfg, $prompt, 1800);
        if (!empty($res['ok']) && !empty($res['data']['contenido'])) {
            $d = $res['data'];
            return [
                'titulo'    => mb_substr(trim((string)($d['titulo'] ?? 'Sin título')), 0, 200),
                'contenido' => trim((string)$d['contenido']),
                'autor'     => trim((string)($d['autor'] ?? '')) ?: null,
                'categoria' => trim((string)($d['categoria'] ?? '')) ?: null,
                'fecha'     => trim((string)($d['fecha'] ?? '')) ?: null,
                'url'       => $url,
            ];
        }
    }

    return null;
}

function extractFacebookPostsByProvider($providerCfg, $pageUrl, $html) {
    $mode = $providerCfg['provider_facebook'] ?? 'direct';

    if ($mode === 'jina') {
        $jina = getJinaReaderContent($pageUrl, $providerCfg['jina_api_key'] ?? '');
        if ($jina['ok']) {
            $text = trim((string)$jina['body']);
            if ($text !== '' && mb_strlen($text) > 30) {
                return normalizeFacebookPosts([[
                    'texto' => $text,
                    'timestamp' => time(),
                ]], $pageUrl);
            }
        }
        return [];
    }

    if ($mode === 'gemini') {
        $sample = mb_substr((string)$html, 0, 18000);
        $prompt = "Desde el siguiente HTML de una página de Facebook pública, extrae hasta 5 posts recientes y devuelve JSON válido sin texto adicional con esta forma exacta: " .
                  "{\"posts\":[{\"texto\":\"...\",\"timestamp\":1234567890}]}. " .
                  "Si no hay timestamp, usa 0. HTML:\n{$sample}";

        $res = geminiStructuredExtract($providerCfg, $prompt, 1800);
        if (!empty($res['ok']) && !empty($res['data']['posts']) && is_array($res['data']['posts'])) {
            $out = [];
            foreach ($res['data']['posts'] as $p) {
                $texto = trim((string)($p['texto'] ?? ''));
                if (mb_strlen($texto) < 10) continue;
                $out[] = [
                    'texto' => $texto,
                    'timestamp' => (int)($p['timestamp'] ?? 0),
                ];
            }
            return normalizeFacebookPosts($out, $pageUrl);
        }
        return [];
    }

    if ($mode === 'copilot') {
        // Extraer texto plano del HTML para no saturar el contexto del LLM
        $textoPlano = '';

        // Intentar primero con Jina Reader para obtener texto limpio
        $jina = getJinaReaderContent($pageUrl, $providerCfg['jina_api_key'] ?? '');
        if ($jina['ok'] && mb_strlen(trim((string)$jina['body'])) > 50) {
            $textoPlano = mb_substr(trim((string)$jina['body']), 0, 12000);
        } else {
            // Fallback: strip_tags sobre el HTML para extraer solo texto
            $stripped = strip_tags(preg_replace('#<script[^>]*>.*?</script>#si', '', (string)$html));
            $stripped = preg_replace('/\s{2,}/', ' ', $stripped);
            $textoPlano = mb_substr(trim($stripped), 0, 12000);
        }

        if (mb_strlen($textoPlano) < 30) {
            return [];
        }

        $prompt = "Eres un extractor de datos. Analiza el siguiente texto extraído de una página pública de Facebook " .
                  "e identifica hasta 5 publicaciones (posts) recientes. " .
                  "Devuelve ÚNICAMENTE un JSON válido sin texto adicional ni bloques de código, con esta estructura exacta: " .
                  '{"posts":[{"texto":"contenido del post","timestamp":1234567890}]}. ' .
                  "Si no encuentras timestamp Unix de 10 dígitos, usa 0. " .
                  "Texto:\n{$textoPlano}";

        $res = copilotStructuredExtract($providerCfg, $prompt, 2000);
        if (!empty($res['ok']) && !empty($res['data']['posts']) && is_array($res['data']['posts'])) {
            $out = [];
            foreach ($res['data']['posts'] as $p) {
                $texto = trim((string)($p['texto'] ?? ''));
                if (mb_strlen($texto) < 10) continue;
                $out[] = [
                    'texto'     => $texto,
                    'timestamp' => (int)($p['timestamp'] ?? 0),
                ];
            }
            $out = normalizeFacebookPosts($out, $pageUrl);
            if (!empty($out)) return $out;
        }

        // Fallback: extracción directa + intento de expansión por permalink.
        $fallbackRaw = extractFacebookDirectPostsRaw($html, $pageUrl);
        return finalizeFacebookDirectPosts($fallbackRaw, $pageUrl);
    }

    // direct (JSON embebido) + expansión por permalink si viene truncado.
    $directRaw = extractFacebookDirectPostsRaw($html, $pageUrl);
    return finalizeFacebookDirectPosts($directRaw, $pageUrl);
}
