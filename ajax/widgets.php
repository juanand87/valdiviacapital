<?php
/**
 * ajax/widgets.php
 * Devuelve JSON con datos reales de clima e indicadores financieros.
 *
 * Fuentes:
 *   Clima      → Open-Meteo API  (gratuito, sin clave)
 *   Indicadores→ mindicador.cl   (gratuito, sin clave, datos Banco Central Chile)
 *
 * Cache en /cache/ : clima 30 min · indicadores 4 horas
 */

require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'clima'       => getClima(),
    'indicadores' => getIndicadores(),
]);

// ─── CLIMA ───────────────────────────────────────────────────────────────────
function getClima(): array
{
    $cacheFile = __DIR__ . '/../cache/clima.json';
    $ttl       = 1800; // 30 minutos

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        return json_decode(file_get_contents($cacheFile), true) ?: [];
    }

    $ciudades = [
        ['nombre' => 'Valdivia', 'lat' => -39.8271, 'lon' => -73.2451],
        ['nombre' => 'Osorno',   'lat' => -40.5728, 'lon' => -73.1346],
        ['nombre' => 'La Unión', 'lat' => -40.2942, 'lon' => -73.0862],
    ];

    $resultado = [];
    foreach ($ciudades as $c) {
        $url  = 'https://api.open-meteo.com/v1/forecast'
              . "?latitude={$c['lat']}&longitude={$c['lon']}"
              . '&current=temperature_2m,apparent_temperature,weather_code,relative_humidity_2m,wind_speed_10m'
              . '&daily=temperature_2m_max,temperature_2m_min'
              . '&timezone=America%2FSantiago&forecast_days=1';

        $raw  = fetchUrl($url);
        if ($raw) {
            $d    = json_decode($raw, true);
            $cur  = $d['current'] ?? [];
            $day  = $d['daily']   ?? [];
            $code = $cur['weather_code'] ?? 0;
            $resultado[] = [
                'ciudad'   => $c['nombre'],
                'temp'     => isset($cur['temperature_2m'])       ? (int) round($cur['temperature_2m'])       : '--',
                'sensacion'=> isset($cur['apparent_temperature'])  ? (int) round($cur['apparent_temperature']) . '°' : '--',
                'humedad'  => isset($cur['relative_humidity_2m'])  ? (int) $cur['relative_humidity_2m'] . '%'  : '--',
                'viento'   => isset($cur['wind_speed_10m'])        ? round($cur['wind_speed_10m']) . ' km/h'   : '--',
                'maxima'   => isset($day['temperature_2m_max'][0]) ? (int) round($day['temperature_2m_max'][0]) . '°' : '--',
                'minima'   => isset($day['temperature_2m_min'][0]) ? (int) round($day['temperature_2m_min'][0]) . '°' : '--',
                'desc'     => wmoDesc($code),
                'icono'    => wmoIcon($code),
            ];
        } else {
            $resultado[] = [
                'ciudad'   => $c['nombre'],
                'temp'     => '--',
                'sensacion'=> '--',
                'humedad'  => '--',
                'viento'   => '--',
                'maxima'   => '--',
                'minima'   => '--',
                'desc'     => 'Sin datos',
                'icono'    => 'fa-cloud',
            ];
        }
    }

    @file_put_contents($cacheFile, json_encode($resultado));
    return $resultado;
}

/** Descripción en español según código WMO */
function wmoDesc(int $code): string
{
    if ($code === 0)            return 'Despejado';
    if ($code <= 2)             return 'Parcialmente nublado';
    if ($code === 3)            return 'Nublado';
    if ($code <= 48)            return 'Neblina';
    if ($code <= 55)            return 'Llovizna';
    if ($code <= 65)            return 'Lluvias';
    if ($code <= 75)            return 'Nevada';
    if ($code <= 82)            return 'Chubascos';
    if ($code <= 86)            return 'Nevada intensa';
    return 'Tormenta';
}

/** Icono FontAwesome según código WMO */
function wmoIcon(int $code): string
{
    if ($code === 0)  return 'fa-sun';
    if ($code <= 2)   return 'fa-cloud-sun';
    if ($code === 3)  return 'fa-cloud';
    if ($code <= 48)  return 'fa-smog';
    if ($code <= 55)  return 'fa-cloud-rain';
    if ($code <= 65)  return 'fa-cloud-showers-heavy';
    if ($code <= 75)  return 'fa-snowflake';
    if ($code <= 82)  return 'fa-cloud-showers-heavy';
    if ($code <= 86)  return 'fa-snowflake';
    return 'fa-bolt';
}

// ─── INDICADORES FINANCIEROS ─────────────────────────────────────────────────
function getIndicadores(): ?array
{
    $cacheFile = __DIR__ . '/../cache/indicadores.json';
    $ttl       = 14400; // 4 horas

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    $raw = fetchUrl('https://mindicador.cl/api');
    if (!$raw) {
        // Devolver caché viejo si existe antes de retornar null
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        return null;
    }

    $d = json_decode($raw, true);
    if (!$d) return null;

    $fecha = '';
    if (!empty($d['uf']['fecha'])) {
        $fecha = date('d/m/Y', strtotime($d['uf']['fecha']));
    }

    $resultado = [
        'fecha'  => $fecha,
        'items'  => [
            [
                'nombre' => 'UF',
                'icono'  => 'fa-coins',
                'valor'  => fmtVal($d['uf']['valor']   ?? null),
            ],
            [
                'nombre' => 'Dólar observado',
                'icono'  => 'fa-dollar-sign',
                'valor'  => fmtVal($d['dolar']['valor'] ?? null),
            ],
            [
                'nombre' => 'Euro',
                'icono'  => 'fa-euro-sign',
                'valor'  => fmtVal($d['euro']['valor']  ?? null),
            ],
            [
                'nombre' => 'Yen (100 JPY)',
                'icono'  => 'fa-yen-sign',
                'valor'  => getYenCLP($d['dolar']['valor'] ?? null),
            ],
        ],
    ];

    @file_put_contents($cacheFile, json_encode($resultado));
    return $resultado;
}

/** Formatea valor numérico en pesos chilenos */
function fmtVal($val, int $dec = 2): string
{
    if ($val === null || $val === '') return 'N/D';
    return '$ ' . number_format((float)$val, $dec, ',', '.');
}

/**
 * Calcula cuántos CLP valen 100 JPY usando open.er-api.com (gratuito, sin key).
 * Si falla la request, deriva la cifra desde el tipo de cambio USD/CLP.
 */
function getYenCLP($clpPerUsd): string
{
    // Intento 1: tasa directa JPY→CLP desde open.er-api.com
    $raw = fetchUrl('https://open.er-api.com/v6/latest/JPY');
    if ($raw) {
        $er = json_decode($raw, true);
        if (!empty($er['rates']['CLP'])) {
            $clpPer1Jpy = (float)$er['rates']['CLP'];
            return '$ ' . number_format($clpPer1Jpy * 100, 2, ',', '.');
        }
    }
    // Intento 2: derivar desde USD/CLP + USD/JPY via er-api
    if ($clpPerUsd) {
        $raw2 = fetchUrl('https://open.er-api.com/v6/latest/USD');
        if ($raw2) {
            $er2 = json_decode($raw2, true);
            if (!empty($er2['rates']['JPY'])) {
                $jpyPerUsd = (float)$er2['rates']['JPY'];
                $clpPer100Jpy = (float)$clpPerUsd / $jpyPerUsd * 100;
                return '$ ' . number_format($clpPer100Jpy, 2, ',', '.');
            }
        }
    }
    return 'N/D';
}

// ─── HTTP helper ─────────────────────────────────────────────────────────────
function fetchUrl(string $url): string|false
{
    $ctx = stream_context_create(['http' => [
        'timeout'     => 8,
        'user_agent'  => 'ValdiviaCapital/1.0',
        'ignore_errors' => true,
    ]]);
    return @file_get_contents($url, false, $ctx);
}
