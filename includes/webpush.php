<?php
/**
 * includes/webpush.php — Funciones VAPID para Web Push Notifications
 *
 * Estrategia: "payload-less push" (evita el cifrado RFC 8291).
 *   - El servidor envía un POST vacío con cabeceras VAPID firmadas.
 *   - El Service Worker recibe el push y llama a /ajax/ultima-hora.php.
 *
 * Requiere: PHP >= 8.0, extensiones openssl y curl.
 * Config:   includes/vapid_config.php (generado por admin/setup-vapid.php)
 */

if (!defined('VAPID_PUBLIC_KEY')) {
    $__vc = __DIR__ . '/vapid_config.php';
    if (file_exists($__vc)) require_once $__vc;
    unset($__vc);
}

/* ── Helpers base64url (RFC 4648 §5, sin relleno) ──────────── */

function vapid_b64u(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function vapid_b64u_dec(string $b64u): string
{
    $pad = strlen($b64u) % 4;
    if ($pad) $b64u .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($b64u, '-_', '+/'));
}

/* ── Generación de claves ───────────────────────────────────── */

/**
 * Genera un nuevo par de claves VAPID (EC P-256).
 *
 * @return array{ public: string, private: string }  — ambos base64url
 * @throws RuntimeException si openssl no puede generar la clave
 */
function vapid_generate(): array
{
    $key = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if (!$key) {
        throw new \RuntimeException('openssl_pkey_new falló: ' . openssl_error_string());
    }
    $d = openssl_pkey_get_details($key);
    return [
        // Clave pública sin comprimir: 0x04 || x || y  (65 bytes)
        'public'  => vapid_b64u("\x04" . $d['ec']['x'] . $d['ec']['y']),
        // Escalar privado d (32 bytes)
        'private' => vapid_b64u($d['ec']['d']),
    ];
}

/* ── PEM de la clave privada EC ─────────────────────────────── */

/**
 * Construye el PEM de una clave privada EC desde el escalar d (32 bytes, base64url).
 *
 * Estructura DER mínima (51 bytes) para prime256v1:
 *   30 31          SEQUENCE, 49 bytes
 *     02 01 01     version = 1
 *     04 20 [d]    OCTET STRING, 32 bytes  ← la clave privada
 *     a0 0a        [0] EXPLICIT
 *       06 08      OID, 8 bytes
 *         2a 86 48 ce 3d 03 01 07  (prime256v1 = 1.2.840.10045.3.1.7)
 */
function vapid_priv_pem(string $privB64u): string
{
    $raw = vapid_b64u_dec($privB64u);
    if (strlen($raw) !== 32) {
        throw new \RuntimeException('La clave privada VAPID debe tener exactamente 32 bytes (obtenidos: ' . strlen($raw) . ')');
    }
    $inner = "\x02\x01\x01\x04\x20" . $raw
           . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    $der   = "\x30" . chr(strlen($inner)) . $inner;

    return "-----BEGIN EC PRIVATE KEY-----\n"
         . chunk_split(base64_encode($der), 64, "\n")
         . "-----END EC PRIVATE KEY-----\n";
}

/* ── Conversión de firma DER → raw r||s (IEEE P1363) ─────────── */

/**
 * Convierte una firma ECDSA en formato DER (producida por openssl_sign)
 * al formato raw IEEE P1363: r||s (exactamente 64 bytes = 32 + 32).
 *
 * DER: SEQUENCE { INTEGER r, INTEGER s }
 * Cada INTEGER puede tener un 0x00 inicial si el bit más alto es 1.
 */
function vapid_der_to_raw(string $der): string
{
    $i    = 2;                            // saltar SEQUENCE tag + longitud
    $i++;                                 // INTEGER tag (r)
    $rLen = ord($der[$i++]);
    $r    = substr($der, $i, $rLen); $i += $rLen;
    $i++;                                 // INTEGER tag (s)
    $sLen = ord($der[$i++]);
    $s    = substr($der, $i, $sLen);

    // Eliminar relleno 0x00 inicial y luego ajustar a 32 bytes con ceros a la izquierda
    return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT)
         . str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
}

/* ── JWT VAPID (ES256) ─────────────────────────────────────── */

/**
 * Crea y firma un JWT VAPID para el endpoint de push dado.
 *
 * @param  string $endpoint  URL del push endpoint del suscriptor
 * @param  string $privB64u  Clave privada VAPID (base64url, 32 bytes raw)
 * @param  string $subject   Contacto del servidor (mailto: o https:)
 * @return string            JWT firmado: header.payload.signature
 */
function vapid_jwt(string $endpoint, string $privB64u, string $subject): string
{
    $aud     = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
    $header  = vapid_b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims  = vapid_b64u(json_encode([
        'aud' => $aud,
        'exp' => time() + 43200,   // expira en 12 horas
        'sub' => $subject,
    ]));
    $toSign  = $header . '.' . $claims;

    $pem    = vapid_priv_pem($privB64u);
    $privKey = openssl_pkey_get_private($pem);
    openssl_sign($toSign, $derSig, $privKey, OPENSSL_ALGO_SHA256);

    return $toSign . '.' . vapid_b64u(vapid_der_to_raw($derSig));
}

/* ── Envío de push ─────────────────────────────────────────── */

/**
 * Envía un push payload-less (cuerpo vacío) con cabeceras VAPID.
 * El Service Worker (sw.js) obtendrá el contenido via /ajax/ultima-hora.php.
 *
 * @param  array  $sub   Fila BD: { endpoint, p256dh, auth }
 * @param  string $pub   Clave pública VAPID (base64url)
 * @param  string $priv  Clave privada VAPID (base64url)
 * @param  string $subj  VAPID subject (mailto: o https:)
 * @return array  ['code' => int, 'body' => string]  — code 201=ok, 410=expirado
 */
function vapid_send(array $sub, string $pub, string $priv, string $subj): array
{
    $jwt = vapid_jwt($sub['endpoint'], $priv, $subj);

    $ch = curl_init($sub['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',  // evita que cURL añada Content-Type automático
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Authorization: vapid t=' . $jwt . ', k=' . $pub,
            'TTL: 86400',
            'Content-Length: 0',
            'Content-Type:',   // fuerza header vacío → ningún Content-Type en la petición
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => $body];
}
