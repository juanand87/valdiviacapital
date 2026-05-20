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

function redactarConIA($db, $noticia) {
    $config = getConfigIA($db);
    
    if (empty($config['gemini_api_key'])) {
        return ['error' => 'No hay API Key configurada. Ve a Configuración IA para agregarla.'];
    }
    
    // Construir prompt reemplazando variables
    $prompt = $config['gemini_prompt_base'];
    $prompt = str_replace('{titulo}', $noticia['titulo'] ?? '', $prompt);
    $prompt = str_replace('{autor}', $noticia['autor'] ?? 'No especificado', $prompt);
    $prompt = str_replace('{categoria}', $noticia['categoria'] ?? 'No especificada', $prompt);
    $prompt = str_replace('{contenido}', $noticia['contenido'] ?? '', $prompt);
    
    // Forzar respuesta en JSON con titulo y contenido separados
    $prompt .= "\n\nIMPORTANTE: Devuelve ÚNICAMENTE un objeto JSON válido con esta estructura exacta (sin bloques de código, sin texto antes ni después del JSON):\n{\"titulo\": \"El titular del artículo redactado\", \"contenido\": \"El cuerpo completo del artículo\"}";
    
    $modelo = $config['gemini_modelo'] ?? 'gemini-2.5-flash';
    $temperatura = (float)($config['gemini_temperatura'] ?? 0.7);
    // Evitar cortes por configuraciones demasiado bajas
    $maxTokens = max((int)($config['gemini_max_tokens'] ?? 8192), 3072);

    // Primer intento: JSON estructurado
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

    // Segundo intento (fallback): texto plano para evitar JSON truncado
    $promptFallback = $config['gemini_prompt_base'];
    $promptFallback = str_replace('{titulo}', $noticia['titulo'] ?? '', $promptFallback);
    $promptFallback = str_replace('{autor}', $noticia['autor'] ?? 'No especificado', $promptFallback);
    $promptFallback = str_replace('{categoria}', $noticia['categoria'] ?? 'No especificada', $promptFallback);
    $promptFallback = str_replace('{contenido}', $noticia['contenido'] ?? '', $promptFallback);
    $promptFallback .= "\n\nDevuelve SOLO texto del artículo final (sin JSON, sin markdown, sin bloques de código).";

    $llamada2 = llamarGemini($config['gemini_api_key'], $modelo, $temperatura, max($maxTokens, 4096), $promptFallback);
    if (isset($llamada2['error'])) {
        return ['error' => $llamada2['error']];
    }

    $textoPlano = trim((string)$llamada2['texto']);
    $textoPlano = preg_replace('/^```[a-z]*\s*/i', '', $textoPlano);
    $textoPlano = preg_replace('/\s*```$/', '', $textoPlano);

    if ($textoPlano === '') {
        return ['error' => 'No se pudo generar una redacción completa. Aumenta gemini_max_tokens e intenta de nuevo.'];
    }

    return [
        'titulo' => $noticia['titulo'] ?? '',
        'texto' => $textoPlano,
    ];
}
