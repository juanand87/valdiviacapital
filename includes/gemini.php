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
    $maxTokens = (int)($config['gemini_max_tokens'] ?? 8192);
    
    $payload = json_encode([
        'contents' => [[
            'parts' => [[
                'text' => $prompt
            ]]
        ]],
        'generationConfig' => [
            'temperature' => $temperatura,
            'maxOutputTokens' => $maxTokens
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
    
    // Llamar a la API de Gemini (v1beta)
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$config['gemini_api_key']}";
    
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
    
    if (empty($texto)) {
        return ['error' => 'Gemini no generó contenido. Intenta de nuevo.'];
    }
    
    // Limpiar posibles bloques de código markdown que Gemini a veces agrega
    $texto = preg_replace('/^```json\s*/i', '', trim($texto));
    $texto = preg_replace('/\s*```$/', '', $texto);
    
    // Intentar parsear como JSON con titulo y contenido
    $decoded = json_decode(trim($texto), true);
    if ($decoded && isset($decoded['titulo']) && isset($decoded['contenido'])) {
        return ['titulo' => $decoded['titulo'], 'texto' => $decoded['contenido']];
    }
    
    // Fallback: devolver como texto plano sin titulo separado
    return ['titulo' => '', 'texto' => $texto];
}
