<?php
/**
 * Módulo de integración con Google Gemini API
 */

function getConfigIA($db) {
    $stmt = $db->query("SELECT nombre, valor FROM configuracion_ia");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $config = [];
    foreach ($rows as $row) {
        $config[$row['nombre']] = $row['valor'];
    }
    return $config;
}

function construirPromptRedaccionIA($noticia, $promptBase) {
    $prompt = $promptBase;
    $prompt = str_replace('{titulo}', $noticia['titulo'] ?? '', $prompt);
    $prompt = str_replace('{autor}', $noticia['autor'] ?? 'No especificado', $prompt);
    $prompt = str_replace('{categoria}', $noticia['categoria'] ?? 'No especificada', $prompt);
    $prompt = str_replace('{contenido}', $noticia['contenido'] ?? '', $prompt);
    return $prompt;
}

function parsearRespuestaGeminiArticulo($texto) {
    $texto = trim((string)$texto);
    if ($texto === '') {
        return null;
    }

    // Limpiar posibles fences markdown
    $texto = preg_replace('/^```json\s*/i', '', $texto);
    $texto = preg_replace('/^```\s*/i', '', $texto);
    $texto = preg_replace('/\s*```$/', '', $texto);

    // 1) JSON válido
    $decoded = json_decode(trim($texto), true);
    if (is_array($decoded) && isset($decoded['contenido'])) {
        return [
            'titulo' => trim((string)($decoded['titulo'] ?? '')),
            'texto' => trim((string)($decoded['contenido'] ?? '')),
        ];
    }

    // 2) Intento tolerante: extraer campos aunque el JSON venga truncado
    $titulo = '';
    $contenido = '';

    if (preg_match('/"titulo"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/su', $texto, $mt)) {
        $titulo = json_decode('"' . $mt[1] . '"');
        if (!is_string($titulo)) {
            $titulo = $mt[1];
        }
    }

    if (preg_match('/"contenido"\s*:\s*"((?:[^"\\\\]|\\\\.)*)/su', $texto, $mc)) {
        // En truncados puede faltar la comilla de cierre; decodificar en best-effort
        $contenidoRaw = $mc[1];
        $contenido = json_decode('"' . $contenidoRaw . '"');
        if (!is_string($contenido)) {
            $contenido = str_replace(['\\n', '\\r', '\\t'], ["\n", "", "\t"], $contenidoRaw);
        }
    }

    if ($contenido !== '') {
        return [
            'titulo' => trim((string)$titulo),
            'texto' => trim((string)$contenido),
        ];
    }

    return null;
}

function llamarGemini($apiKey, $modelo, $temperatura, $maxTokens, $prompt) {
    $payload = json_encode([
        'contents' => [[
            'parts' => [[
                'text' => $prompt
            ]]
        ]],
        'generationConfig' => [
            'temperature' => (float)$temperatura,
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

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['error' => 'No se pudo conectar con la API de Gemini. Verifica tu conexión a Internet.'];
    }

    $data = json_decode($response, true);
    if (isset($data['error'])) {
        return ['error' => 'Error de Gemini: ' . ($data['error']['message'] ?? 'Error desconocido')];
    }

    $texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $finishReason = $data['candidates'][0]['finishReason'] ?? '';

    if (empty($texto)) {
        return ['error' => 'Gemini no generó contenido. Intenta de nuevo.'];
    }

    return [
        'ok' => true,
        'texto' => $texto,
        'finishReason' => $finishReason,
    ];
}

function llamarCopilot($apiKey, $apiUrl, $modelo, $temperatura, $maxTokens, $prompt) {
    $payload = json_encode([
        'model' => $modelo,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Eres un editor periodistico profesional. Responde de forma precisa y en espanol.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => (float)$temperatura,
        'max_tokens' => (int)$maxTokens,
    ]);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\nAccept: application/json\r\n",
            'content' => $payload,
            'timeout' => 60,
            'ignore_errors' => true,
        ],
    ];

    $response = @file_get_contents($apiUrl, false, stream_context_create($opts));
    if ($response === false) {
        return ['error' => 'No se pudo conectar con la API de GitHub Copilot. Verifica la URL y tu conexion a Internet.'];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        $preview = mb_substr(trim((string)$response), 0, 300);
        return ['error' => 'Respuesta invalida de GitHub Copilot. Respuesta recibida: ' . $preview];
    }

    if (isset($data['error'])) {
        $msg = is_array($data['error']) ? ($data['error']['message'] ?? 'Error desconocido') : (string)$data['error'];
        return ['error' => 'Error de GitHub Copilot: ' . $msg];
    }

    $texto = '';
    if (isset($data['choices'][0]['message']['content'])) {
        $texto = (string)$data['choices'][0]['message']['content'];
    } elseif (isset($data['choices'][0]['text'])) {
        $texto = (string)$data['choices'][0]['text'];
    }

    if (trim($texto) === '') {
        return ['error' => 'GitHub Copilot no devolvio contenido.'];
    }

    return ['ok' => true, 'texto' => $texto];
}

function llamarJina($apiKey, $apiUrl, $modelo, $temperatura, $maxTokens, $prompt) {
    $payload = json_encode([
        'model' => $modelo,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Eres un editor periodistico profesional. Responde de forma precisa y en espanol.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => (float)$temperatura,
        'max_tokens' => (int)$maxTokens,
    ]);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\nAccept: application/json\r\n",
            'content' => $payload,
            'timeout' => 60,
            'ignore_errors' => true,
        ],
    ];

    $response = @file_get_contents($apiUrl, false, stream_context_create($opts));
    if ($response === false) {
        return ['error' => 'No se pudo conectar con la API de Jina.'];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['error' => 'Respuesta invalida de Jina.'];
    }

    if (isset($data['error'])) {
        $msg = is_array($data['error']) ? ($data['error']['message'] ?? 'Error desconocido') : (string)$data['error'];
        return ['error' => 'Error de Jina: ' . $msg];
    }

    $texto = '';
    if (isset($data['choices'][0]['message']['content'])) {
        $texto = (string)$data['choices'][0]['message']['content'];
    } elseif (isset($data['choices'][0]['text'])) {
        $texto = (string)$data['choices'][0]['text'];
    }

    if (trim($texto) === '') {
        return ['error' => 'Jina no devolvio contenido.'];
    }

    return ['ok' => true, 'texto' => $texto];
}

function redactarConGemini($config, $noticia) {
    if (empty($config['gemini_api_key'])) {
        return ['error' => 'No hay API Key de Gemini configurada. Ve a Configuracion IA para agregarla.'];
    }

    $prompt = construirPromptRedaccionIA($noticia, $config['gemini_prompt_base'] ?? '');
    $prompt .= "\n\nIMPORTANTE: Devuelve UNICAMENTE un objeto JSON valido con esta estructura exacta (sin bloques de codigo, sin texto antes ni despues del JSON):\n{\"titulo\": \"El titular del articulo redactado\", \"contenido\": \"El cuerpo completo del articulo\"}";

    $modelo = $config['gemini_modelo'] ?? 'gemini-2.5-flash';
    $temperatura = (float)($config['gemini_temperatura'] ?? 0.7);
    $maxTokens = max((int)($config['gemini_max_tokens'] ?? 8192), 3072);

    $llamada = llamarGemini($config['gemini_api_key'], $modelo, $temperatura, $maxTokens, $prompt);
    if (isset($llamada['error'])) {
        return ['error' => $llamada['error']];
    }

    $parsed = parsearRespuestaGeminiArticulo($llamada['texto']);
    if ($parsed && !empty($parsed['texto'])) {
        if (empty($parsed['titulo'])) {
            $parsed['titulo'] = $noticia['titulo'] ?? '';
        }
        return $parsed;
    }

    $promptFallback = construirPromptRedaccionIA($noticia, $config['gemini_prompt_base'] ?? '');
    $promptFallback .= "\n\nDevuelve SOLO texto del articulo final (sin JSON, sin markdown, sin bloques de codigo).";

    $llamada2 = llamarGemini($config['gemini_api_key'], $modelo, $temperatura, max($maxTokens, 4096), $promptFallback);
    if (isset($llamada2['error'])) {
        return ['error' => $llamada2['error']];
    }

    $textoPlano = trim((string)$llamada2['texto']);
    $textoPlano = preg_replace('/^```[a-z]*\s*/i', '', $textoPlano);
    $textoPlano = preg_replace('/\s*```$/', '', $textoPlano);

    if ($textoPlano === '') {
        return ['error' => 'No se pudo generar una redaccion completa. Aumenta gemini_max_tokens e intenta de nuevo.'];
    }

    return [
        'titulo' => $noticia['titulo'] ?? '',
        'texto' => $textoPlano,
    ];
}

function redactarConCopilot($config, $noticia) {
    if (empty($config['copilot_api_key'])) {
        return ['error' => 'No hay API Key de GitHub Copilot configurada. Ve a Configuracion IA para agregarla.'];
    }

    $apiUrl = trim((string)($config['copilot_api_url'] ?? 'https://models.inference.ai.azure.com/chat/completions'));
    if ($apiUrl === '') {
        $apiUrl = 'https://models.inference.ai.azure.com/chat/completions';
    }

    $modelo = trim((string)($config['copilot_modelo'] ?? 'claude-sonnet-4.6'));
    if ($modelo === '') {
        $modelo = 'claude-sonnet-4.6';
    }

    $temperatura = (float)($config['gemini_temperatura'] ?? 0.7);
    $maxTokens = max((int)($config['gemini_max_tokens'] ?? 8192), 3072);

    $prompt = construirPromptRedaccionIA($noticia, $config['gemini_prompt_base'] ?? '');
    $prompt .= "\n\nIMPORTANTE: Devuelve UNICAMENTE un objeto JSON valido con esta estructura exacta (sin bloques de codigo, sin texto antes ni despues del JSON):\n{\"titulo\": \"El titular del articulo redactado\", \"contenido\": \"El cuerpo completo del articulo\"}";

    $llamada = llamarCopilot($config['copilot_api_key'], $apiUrl, $modelo, $temperatura, $maxTokens, $prompt);
    if (isset($llamada['error'])) {
        return ['error' => $llamada['error']];
    }

    $parsed = parsearRespuestaGeminiArticulo($llamada['texto']);
    if ($parsed && !empty($parsed['texto'])) {
        if (empty($parsed['titulo'])) {
            $parsed['titulo'] = $noticia['titulo'] ?? '';
        }
        return $parsed;
    }

    $promptFallback = construirPromptRedaccionIA($noticia, $config['gemini_prompt_base'] ?? '');
    $promptFallback .= "\n\nDevuelve SOLO texto del articulo final (sin JSON, sin markdown, sin bloques de codigo).";

    $llamada2 = llamarCopilot($config['copilot_api_key'], $apiUrl, $modelo, $temperatura, max($maxTokens, 4096), $promptFallback);
    if (isset($llamada2['error'])) {
        return ['error' => $llamada2['error']];
    }

    $textoPlano = trim((string)$llamada2['texto']);
    $textoPlano = preg_replace('/^```[a-z]*\s*/i', '', $textoPlano);
    $textoPlano = preg_replace('/\s*```$/', '', $textoPlano);

    if ($textoPlano === '') {
        return ['error' => 'No se pudo generar una redaccion completa con GitHub Copilot.'];
    }

    return [
        'titulo' => $noticia['titulo'] ?? '',
        'texto' => $textoPlano,
    ];
}

function redactarConJina($config, $noticia) {
    if (empty($config['jina_api_key'])) {
        return ['error' => 'No hay API Key de Jina configurada. Ve a Configuracion IA para agregarla.'];
    }

    $apiUrl = trim((string)($config['jina_redaccion_api_url'] ?? 'https://api.jina.ai/v1/chat/completions'));
    if ($apiUrl === '') {
        $apiUrl = 'https://api.jina.ai/v1/chat/completions';
    }

    $modelo = trim((string)($config['jina_redaccion_modelo'] ?? 'jina-deepsearch-v1'));
    if ($modelo === '') {
        $modelo = 'jina-deepsearch-v1';
    }

    $temperatura = (float)($config['gemini_temperatura'] ?? 0.7);
    $maxTokens = max((int)($config['gemini_max_tokens'] ?? 8192), 3072);

    $prompt = construirPromptRedaccionIA($noticia, $config['gemini_prompt_base'] ?? '');
    $prompt .= "\n\nIMPORTANTE: Devuelve UNICAMENTE un objeto JSON valido con esta estructura exacta (sin bloques de codigo, sin texto antes ni despues del JSON):\n{\"titulo\": \"El titular del articulo redactado\", \"contenido\": \"El cuerpo completo del articulo\"}";

    $llamada = llamarJina($config['jina_api_key'], $apiUrl, $modelo, $temperatura, $maxTokens, $prompt);
    if (isset($llamada['error'])) {
        return ['error' => $llamada['error']];
    }

    $parsed = parsearRespuestaGeminiArticulo($llamada['texto']);
    if ($parsed && !empty($parsed['texto'])) {
        if (empty($parsed['titulo'])) {
            $parsed['titulo'] = $noticia['titulo'] ?? '';
        }
        return $parsed;
    }

    $promptFallback = construirPromptRedaccionIA($noticia, $config['gemini_prompt_base'] ?? '');
    $promptFallback .= "\n\nDevuelve SOLO texto del articulo final (sin JSON, sin markdown, sin bloques de codigo).";

    $llamada2 = llamarJina($config['jina_api_key'], $apiUrl, $modelo, $temperatura, max($maxTokens, 4096), $promptFallback);
    if (isset($llamada2['error'])) {
        return ['error' => $llamada2['error']];
    }

    $textoPlano = trim((string)$llamada2['texto']);
    $textoPlano = preg_replace('/^```[a-z]*\s*/i', '', $textoPlano);
    $textoPlano = preg_replace('/\s*```$/', '', $textoPlano);

    if ($textoPlano === '') {
        return ['error' => 'No se pudo generar una redaccion completa con Jina.'];
    }

    return [
        'titulo' => $noticia['titulo'] ?? '',
        'texto' => $textoPlano,
    ];
}

function redactarConIA($db, $noticia) {
    $config = getConfigIA($db);

    $provider = strtolower(trim((string)($config['redaccion_provider'] ?? 'gemini')));
    if ($provider === 'copilot') {
        return redactarConCopilot($config, $noticia);
    }
    if ($provider === 'jina') {
        return redactarConJina($config, $noticia);
    }

    return redactarConGemini($config, $noticia);
}
