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

function normalizeFacebookPosts(array $posts) {
    $out = [];
    $seen = [];

    foreach ($posts as $p) {
        $texto = trim((string)($p['texto'] ?? ''));
        if (isFacebookNoiseText($texto)) {
            continue;
        }

        $hash = md5(mb_strtolower($texto));
        if (isset($seen[$hash])) {
            continue;
        }
        $seen[$hash] = true;

        $out[] = [
            'texto' => $texto,
            'timestamp' => (int)($p['timestamp'] ?? 0),
        ];
    }

    return $out;
}

function cleanFacebookPostText($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }

    // Remover coletillas típicas de UI al final del post.
    $text = preg_replace('/\s*(ver\s+menos|see\s+more)\s*$/iu', '', $text);

    // Normalizar espacios manteniendo saltos de línea.
    $text = preg_replace('/[ \t]{2,}/u', ' ', $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);

    return trim($text);
}

function extractFacebookDirectPosts($html) {
    preg_match_all('#"message":\{"text":"((?:[^"\\\\]|\\\\.)*)"\}#', (string)$html, $msgMatches);
    preg_match_all('#"creation_time":(\d{10})#', (string)$html, $timeMatches);

    $texts = $msgMatches[1] ?? [];
    $times = $timeMatches[1] ?? [];
    $posts = [];

    foreach ($texts as $i => $raw) {
        $texto = @json_decode('"' . $raw . '"');
        if (!is_string($texto)) {
            $texto = $raw;
        }

        $texto = cleanFacebookPostText($texto);
        if (mb_strlen($texto) < 10) {
            continue;
        }

        $posts[] = [
            'texto' => $texto,
            'timestamp' => isset($times[$i]) ? (int)$times[$i] : 0,
        ];
    }

    return normalizeFacebookPosts($posts);
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
            $text = cleanFacebookPostText((string)$jina['body']);
            if ($text !== '' && mb_strlen($text) > 30) {
                return normalizeFacebookPosts([[
                    'texto' => $text,
                    'timestamp' => time(),
                ]]);
            }
        }
        return [];
    }

    if ($mode === 'gemini') {
        // Usar más HTML para que Gemini pueda ver más contenido
        $sample = mb_substr((string)$html, 0, 30000);
        $prompt = "Desde el siguiente HTML de una página de Facebook pública, extrae hasta 10 posts recientes completos (no resumidos). " .
                  "Devuelve JSON válido sin texto adicional con esta forma exacta: " .
                  "{\"posts\":[{\"texto\":\"contenido completo del post\",\"timestamp\":1234567890}]}. " .
                  "IMPORTANTE: cada post debe tener el contenido COMPLETO, sin '...' ni truncamiento. " .
                  "Si no hay timestamp, usa 0. HTML:\n{$sample}";

        $res = geminiStructuredExtract($providerCfg, $prompt, 2500);
        if (!empty($res['ok']) && !empty($res['data']['posts']) && is_array($res['data']['posts'])) {
            $out = [];
            foreach ($res['data']['posts'] as $p) {
                $texto = cleanFacebookPostText((string)($p['texto'] ?? ''));
                if (mb_strlen($texto) < 10) continue;
                $out[] = [
                    'texto' => $texto,
                    'timestamp' => (int)($p['timestamp'] ?? 0),
                ];
            }
            return normalizeFacebookPosts($out);
        }
        return [];
    }

    if ($mode === 'copilot') {
        // Prioridad INVERTIDA: LLM primero, porque el HTML tiene contenido que regex no puede captar
        $sample = mb_substr((string)$html, 0, 35000);
        $prompt = "Eres un extractor de datos de páginas públicas de Facebook. " .
                  "Analiza el siguiente HTML e identifica hasta 10 publicaciones (posts) recientes. " .
                  "Devuelve ÚNICAMENTE un JSON válido sin texto adicional, con esta estructura exacta: " .
                  '{"posts":[{"texto":"contenido completo sin resumir","timestamp":1234567890}]}. ' .
                  "CRÍTICO: cada 'texto' debe ser COMPLETO, sin '...', no resumido, incluyendo todo el párrafo. " .
                  "Si no encuentras timestamp Unix de 10 dígitos exactos, usa 0. " .
                  "HTML:\n{$sample}";

        $res = copilotStructuredExtract($providerCfg, $prompt, 2500);
        if (!empty($res['ok']) && !empty($res['data']['posts']) && is_array($res['data']['posts'])) {
            $out = [];
            foreach ($res['data']['posts'] as $p) {
                $texto = cleanFacebookPostText((string)($p['texto'] ?? ''));
                if (mb_strlen($texto) < 10) continue;
                $out[] = [
                    'texto'     => $texto,
                    'timestamp' => (int)($p['timestamp'] ?? 0),
                ];
            }
            $out = normalizeFacebookPosts($out);
            if (!empty($out)) return $out;
        }

        // Fallback: extracción directa por regex solo si Copilot falla
        return extractFacebookDirectPosts($html);
    }

    // direct (JSON embebido)
    return extractFacebookDirectPosts($html);
}
