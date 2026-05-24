<?php
/**
 * Cron: Sincronizar páginas de Facebook via scraping JSON
 * 
 * GoDaddy cPanel cron:  0 * /6 * * *  (cada 6 horas)
 * Comando: /usr/local/bin/php /home/usuario/public_html/cron/sincronizar-facebook.php
 */

if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_CRON')) {
    http_response_code(403);
    exit('Solo se puede ejecutar desde CLI');
}

define('CRON_RUN', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/scraping_ai.php';

$db = getDB();
$providerCfg = getScrapingProviderConfig($db);
$inicio = microtime(true);

echo '[' . date('Y-m-d H:i:s') . "] === Inicio sincronización Facebook Scraping ===\n";

$stmt = $db->query("\n    SELECT * FROM medios_conectados\n    WHERE tipo = 'facebook_scraping' AND activo = 1\n    ORDER BY ultima_sincronizacion ASC\n");
$paginas = $stmt->fetchAll();

if (empty($paginas)) {
    echo '[' . date('Y-m-d H:i:s') . "] No hay páginas de Facebook configuradas.\n";
    exit(0);
}

$total_guardadas = 0;
$total_duplicadas = 0;
$total_errores = 0;

foreach ($paginas as $pagina) {
    echo '[' . date('Y-m-d H:i:s') . "] Procesando: {$pagina['nombre']} ({$pagina['url']})\n";

    $parsed = parse_url($pagina['url']);
    $slug = trim($parsed['path'] ?? '', '/');

    if ($slug === '') {
        echo "  [ERROR] URL inválida, saltando.\n";
        $total_errores++;
        continue;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.facebook.com/' . $slug,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => 'gzip, deflate',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: es-CL,es;q=0.9,en;q=0.8',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
        ],
    ]);

    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
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

    $postsExtraidos = extractFacebookPostsByProvider($providerCfg, 'https://www.facebook.com/' . $slug, $html);

    $guardadas = 0;
    $duplicadas = 0;
    $vistos = [];

    foreach ($postsExtraidos as $post) {
        $texto = trim((string)($post['texto'] ?? ''));
        $externalId = trim((string)($post['contenido_id_externo'] ?? ''));
        $urlPost = trim((string)($post['url_original'] ?? ''));
        $hash = trim((string)($post['hash_contenido'] ?? ''));

        if ($texto === '' && $externalId === '') {
            continue;
        }
        if ($externalId === '' && mb_strlen($texto) < 10) {
            continue;
        }

        if ($externalId === '') {
            $externalId = 'fbtxt:' . md5($pagina['url'] . '|' . mb_strtolower($texto));
        }
        if ($urlPost === '') {
            $urlPost = 'https://www.facebook.com/' . $slug;
        }
        if ($hash === '') {
            $hash = md5($texto);
        }

        if (isset($vistos[$externalId])) {
            continue;
        }
        $vistos[$externalId] = true;

        $timestamp = (int)($post['timestamp'] ?? 0);
        if ($timestamp <= 0) {
            $timestamp = time();
        }

        $lineas = explode("\n", $texto, 2);
        $tituloRaw = trim((string)($lineas[0] ?? ''));
        if ($tituloRaw === '') {
            $tituloRaw = trim($texto);
        }
        $tituloRaw = preg_replace('/\s+/u', ' ', $tituloRaw);
        $titulo = $tituloRaw;
        if (mb_strlen($titulo) > 500) {
            $titulo = mb_substr($titulo, 0, 500);
            $ultimoEspacio = mb_strrpos($titulo, ' ');
            if ($ultimoEspacio !== false && $ultimoEspacio > 350) {
                $titulo = mb_substr($titulo, 0, $ultimoEspacio);
            }
        }
        if ($titulo === '') {
            $titulo = 'Publicación de Facebook';
        }

        $fecha = date('Y-m-d H:i:s', $timestamp);

        $chk = $db->prepare("SELECT id FROM medios_contenido_sincronizado WHERE medio_id = :medio_id AND contenido_id_externo = :contenido_id_externo");
        $chk->execute([
            ':medio_id' => $pagina['id'],
            ':contenido_id_externo' => $externalId,
        ]);
        if ($chk->fetch()) {
            $duplicadas++;
            continue;
        }

        try {
            $ins = $db->prepare("\n                INSERT INTO medios_contenido_sincronizado\n                    (medio_id, contenido_id_externo, titulo, contenido, imagen_url, url_original, hash_contenido,\n                     fecha_publicacion, autor, categoria, estado)\n                VALUES\n                    (:medio_id, :contenido_id_externo, :titulo, :contenido, NULL, :url_original, :hash,\n                     :fecha, :autor, NULL, 'pendiente')\n            ");
            $ins->execute([
                ':medio_id' => $pagina['id'],
                ':contenido_id_externo' => $externalId,
                ':titulo' => $titulo,
                ':contenido' => $texto,
                ':url_original' => $urlPost,
                ':hash' => $hash,
                ':fecha' => $fecha,
                ':autor' => $pagina['nombre'],
            ]);
            $guardadas++;
        } catch (PDOException $e) {
            echo '  [DB ERROR] ' . $e->getMessage() . "\n";
            $total_errores++;
        }
    }

    $db->prepare("UPDATE medios_conectados SET ultima_sincronizacion = NOW() WHERE id = :id")
       ->execute([':id' => $pagina['id']]);

    echo '  Proveedor: ' . ($providerCfg['provider_facebook'] ?? 'direct') . ' | Encontrados: ' . count($postsExtraidos) . ' | Guardados: ' . $guardadas . ' | Duplicados: ' . $duplicadas . "\n";

    $total_guardadas += $guardadas;
    $total_duplicadas += $duplicadas;
    sleep(2);
}

$duracion = round(microtime(true) - $inicio, 2);
echo '[' . date('Y-m-d H:i:s') . "] === Fin: {$total_guardadas} guardadas, {$total_duplicadas} duplicadas, {$total_errores} errores ({$duracion}s) ===\n";
exit(0);
