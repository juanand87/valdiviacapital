<?php
/**
 * Diagnostico basico de scraping Facebook por proveedor.
 *
 * Uso:
 *   php verificar_scraping_facebook.php --url=https://www.facebook.com/informaalminuto
 *   php verificar_scraping_facebook.php --html=cache/fb_source.html --url=https://www.facebook.com/informaalminuto
 *
 * Opciones:
 *   --url=...      URL de pagina Facebook (requerida si no hay --html)
 *   --html=...     Ruta local a snapshot HTML para pruebas sin red
 *   --modes=...    Lista separada por coma (direct,jina,gemini,copilot)
 *   --max=...      Cantidad de ejemplos por modo (default: 2)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scraping_ai.php';

function argValue(array $argv, string $name): ?string {
    foreach ($argv as $arg) {
        if (strpos($arg, '--' . $name . '=') === 0) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return null;
}

function printLine(string $text = ''): void {
    echo $text . PHP_EOL;
}

function isLikelyTruncated(string $text): bool {
    $trimmed = trim($text);
    if ($trimmed === '') {
        return false;
    }
    if (preg_match('/\.\.\.$/u', $trimmed)) {
        return true;
    }
    if (preg_match('/\b(ver\s+menos|see\s+more)\s*$/iu', $trimmed)) {
        return true;
    }
    return false;
}

function summarizePosts(array $posts): array {
    $count = count($posts);
    if ($count === 0) {
        return [
            'count' => 0,
            'avg_len' => 0,
            'max_len' => 0,
            'min_len' => 0,
            'truncated_pct' => 0.0,
        ];
    }

    $lengths = [];
    $truncated = 0;

    foreach ($posts as $post) {
        $text = trim((string)($post['texto'] ?? ''));
        $len = mb_strlen($text);
        $lengths[] = $len;
        if (isLikelyTruncated($text)) {
            $truncated++;
        }
    }

    $avg = array_sum($lengths) / max(1, count($lengths));

    return [
        'count' => $count,
        'avg_len' => (int)round($avg),
        'max_len' => (int)max($lengths),
        'min_len' => (int)min($lengths),
        'truncated_pct' => round(($truncated / $count) * 100, 1),
    ];
}

$inputUrl = argValue($argv, 'url');
$htmlFile = argValue($argv, 'html');
$modesArg = argValue($argv, 'modes');
$maxExamples = (int)(argValue($argv, 'max') ?? '2');
if ($maxExamples < 1) {
    $maxExamples = 1;
}

if ($htmlFile === null && $inputUrl === null) {
    printLine('ERROR: Debes indicar --url=... o --html=...');
    printLine('Ejemplo: php verificar_scraping_facebook.php --url=https://www.facebook.com/informaalminuto');
    exit(1);
}

if ($inputUrl === null) {
    $inputUrl = 'https://www.facebook.com/';
}

$allowedModes = ['direct', 'jina', 'gemini', 'copilot'];
$selectedModes = $allowedModes;
if ($modesArg !== null && trim($modesArg) !== '') {
    $selectedModes = [];
    foreach (explode(',', $modesArg) as $mode) {
        $m = strtolower(trim($mode));
        if (in_array($m, $allowedModes, true)) {
            $selectedModes[] = $m;
        }
    }
    $selectedModes = array_values(array_unique($selectedModes));
    if (empty($selectedModes)) {
        printLine('ERROR: --modes invalido. Usa combinacion de: direct,jina,gemini,copilot');
        exit(1);
    }
}

$cfg = [
    'provider_facebook' => 'direct',
    'jina_api_key' => '',
    'gemini_api_key' => '',
    'gemini_modelo' => 'gemini-2.5-flash',
    'gemini_temperatura' => 0.3,
    'copilot_api_key' => '',
    'copilot_modelo' => 'auto',
    'copilot_api_url' => 'https://models.inference.ai.azure.com/chat/completions',
];

try {
    $db = getDB();
    $cfg = array_merge($cfg, getScrapingProviderConfig($db));
} catch (Throwable $e) {
    printLine('[WARN] No se pudo leer configuracion desde BD, se usan defaults: ' . $e->getMessage());
}

$html = '';
if ($htmlFile !== null) {
    if (!is_file($htmlFile)) {
        printLine('ERROR: No existe archivo HTML: ' . $htmlFile);
        exit(1);
    }
    $html = (string)file_get_contents($htmlFile);
    printLine('Fuente HTML: archivo local ' . $htmlFile);
    printLine('HTML bytes: ' . strlen($html));
} else {
    $fetch = scrapingHttpGet($inputUrl, 45, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: es-CL,es;q=0.9,en;q=0.8',
    ]);

    printLine('Fuente HTML: URL remota ' . $inputUrl);
    printLine('HTTP: ' . (string)($fetch['http'] ?? 0) . ' OK: ' . (!empty($fetch['ok']) ? '1' : '0'));
    if (!empty($fetch['error'])) {
        printLine('cURL error: ' . (string)$fetch['error']);
    }

    $html = (string)($fetch['body'] ?? '');
    printLine('HTML bytes: ' . strlen($html));
}

printLine(str_repeat('-', 80));
printLine('Diagnostico de proveedores: ' . implode(', ', $selectedModes));
printLine(str_repeat('-', 80));

foreach ($selectedModes as $mode) {
    $modeCfg = $cfg;
    $modeCfg['provider_facebook'] = $mode;

    $start = microtime(true);
    $posts = extractFacebookPostsByProvider($modeCfg, $inputUrl, $html);
    $elapsed = round((microtime(true) - $start) * 1000);

    $stats = summarizePosts($posts);

    printLine('[' . strtoupper($mode) . ']');
    printLine('  tiempo_ms: ' . $elapsed);
    printLine('  posts: ' . $stats['count']);
    printLine('  largo_promedio: ' . $stats['avg_len']);
    printLine('  largo_min: ' . $stats['min_len'] . ' | largo_max: ' . $stats['max_len']);
    printLine('  truncados_pct: ' . $stats['truncated_pct'] . '%');

    $examples = array_slice($posts, 0, $maxExamples);
    foreach ($examples as $idx => $post) {
        $text = trim((string)($post['texto'] ?? ''));
        $preview = preg_replace('/\s+/u', ' ', $text);
        $preview = mb_substr($preview, 0, 220);
        printLine('  ejemplo_' . ($idx + 1) . '_len: ' . mb_strlen($text));
        printLine('  ejemplo_' . ($idx + 1) . ': ' . $preview);
    }

    if (empty($examples)) {
        printLine('  ejemplo_1: (sin resultados)');
    }

    printLine('');
}

printLine('Sugerencia: si direct da largos bajos y truncados altos, prueba con --modes=copilot,gemini usando claves activas.');
