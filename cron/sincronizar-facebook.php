<?php
/**
 * Cron: Sincronizar páginas de Facebook via scraping JSON
 * 
 * GoDaddy cPanel cron:  0 * /6 * * *  (cada 6 horas)
 * Comando: /usr/local/bin/php /home/usuario/public_html/cron/sincronizar-facebook.php
 */

// Evitar ejecución desde navegador en producción
if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_CRON')) {
    http_response_code(403);
    exit('Solo se puede ejecutar desde CLI');
}

define('CRON_RUN', true);

require_once __DIR__ . '/../includes/config.php';

$db    = getDB();
$inicio = microtime(true);

echo "[" . date('Y-m-d H:i:s') . "] === Inicio sincronización Facebook Scraping ===\n";

// Obtener todas las páginas activas
$stmt = $db->query("
    SELECT * FROM medios_conectados
    WHERE tipo = 'facebook_scraping' AND activo = 1
    ORDER BY ultima_sincronizacion ASC
");
$paginas = $stmt->fetchAll();

if (empty($paginas)) {
    echo "[" . date('Y-m-d H:i:s') . "] No hay páginas de Facebook configuradas.\n";
    exit(0);
}

$total_guardadas  = 0;
$total_duplicadas = 0;
$total_errores    = 0;

foreach ($paginas as $pagina) {
    echo "[" . date('Y-m-d H:i:s') . "] Procesando: {$pagina['nombre']} ({$pagina['url']})\n";

    // Extraer slug
    $parsed = parse_url($pagina['url']);
    $slug   = trim($parsed['path'] ?? '', '/');

    if (empty($slug)) {
        echo "  [ERROR] URL inválida, saltando.\n";
        $total_errores++;
        continue;
    }

    // --- Scraping ---
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://www.facebook.com/' . $slug,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => 'gzip, deflate',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: es-CL,es;q=0.9,en;q=0.8',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
        ],
    ]);

    $html      = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        echo "  [ERROR] cURL: $curl_err\n";
        $total_errores++;
        continue;
    }

    if ($http_code !== 200) {
        echo "  [ERROR] HTTP $http_code\n";
        $total_errores++;
        continue;
    }

    // --- Extraer posts ---
    preg_match_all('#"message":\{"text":"((?:[^"\\\\]|\\\\.)*)"\}#', $html, $msg_matches);
    preg_match_all('#"creation_time":(\d{10})#', $html, $time_matches);

    $textos     = $msg_matches[1]  ?? [];
    $timestamps = $time_matches[1] ?? [];

    $guardadas  = 0;
    $duplicadas = 0;
    $vistos     = [];

    foreach ($textos as $i => $raw) {
        $texto = @json_decode('"' . $raw . '"');
        if (!is_string($texto)) $texto = $raw;
        $texto = trim($texto);
        if (mb_strlen($texto) < 10) continue;

        $hash = md5($texto);
        if (isset($vistos[$hash])) continue;
        $vistos[$hash] = true;

        $timestamp = isset($timestamps[$i]) ? (int)$timestamps[$i] : time();

        $lineas = explode("\n", $texto, 2);
        $titulo = mb_substr(trim($lineas[0]), 0, 200);
        if (empty($titulo)) $titulo = mb_substr($texto, 0, 200);

        $fecha    = date('Y-m-d H:i:s', $timestamp);
        $url_post = 'https://www.facebook.com/' . $slug;

        // Dedup persistente
        $chk = $db->prepare("SELECT id FROM medios_contenido_sincronizado WHERE hash_contenido = :hash");
        $chk->execute([':hash' => $hash]);
        if ($chk->fetch()) {
            $duplicadas++;
            continue;
        }

        try {
            $ins = $db->prepare("
                INSERT INTO medios_contenido_sincronizado
                    (medio_id, titulo, contenido, imagen_url, url_original, hash_contenido,
                     fecha_publicacion, autor, categoria, estado)
                VALUES
                    (:medio_id, :titulo, :contenido, NULL, :url_original, :hash,
                     :fecha, :autor, NULL, 'pendiente')
            ");
            $ins->execute([
                ':medio_id'     => $pagina['id'],
                ':titulo'       => $titulo,
                ':contenido'    => $texto,
                ':url_original' => $url_post,
                ':hash'         => $hash,
                ':fecha'        => $fecha,
                ':autor'        => $pagina['nombre'],
            ]);
            $guardadas++;
        } catch (PDOException $e) {
            echo "  [DB ERROR] " . $e->getMessage() . "\n";
            $total_errores++;
        }
    }

    // Actualizar última sincronización
    $db->prepare("UPDATE medios_conectados SET ultima_sincronizacion = NOW() WHERE id = :id")
       ->execute([':id' => $pagina['id']]);

    echo "  Encontrados: " . count($textos) . " | Guardados: $guardadas | Duplicados: $duplicadas\n";

    $total_guardadas  += $guardadas;
    $total_duplicadas += $duplicadas;

    // Pausa entre requests para no saturar
    sleep(2);
}

$duracion = round(microtime(true) - $inicio, 2);
echo "[" . date('Y-m-d H:i:s') . "] === Fin: {$total_guardadas} guardadas, {$total_duplicadas} duplicadas, {$total_errores} errores ({$duracion}s) ===\n";
exit(0);
