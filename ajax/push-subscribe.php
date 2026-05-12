<?php
/**
 * ajax/push-subscribe.php — Guardar o eliminar una suscripción Web Push
 *
 * Acepta: POST JSON { action: "subscribe"|"unsubscribe", subscription: PushSubscription }
 * Responde: JSON { ok: true|false [, error: string] }
 */

require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

/* Solo POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

/* Leer y validar el cuerpo */
$raw = (string) file_get_contents('php://input');
if (strlen($raw) > 8192) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Payload demasiado grande']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$action       = (string)($data['action'] ?? 'subscribe');
$subscription = (array)($data['subscription'] ?? []);
$endpoint     = (string)($subscription['endpoint'] ?? '');
$keys         = (array)($subscription['keys'] ?? []);
$p256dh       = (string)($keys['p256dh'] ?? '');
$auth         = (string)($keys['auth'] ?? '');

/* Validar endpoint */
if (strlen($endpoint) < 20 || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Endpoint inválido']);
    exit;
}

try {
    $db = getDB();

    if ($action === 'unsubscribe') {
        $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")
           ->execute([$endpoint]);
        echo json_encode(['ok' => true]);
        exit;
    }

    /* subscribe — también valida claves */
    if (strlen($p256dh) < 10 || strlen($auth) < 5) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Claves de suscripción ausentes']);
        exit;
    }

    $db->prepare(
        "INSERT INTO push_subscriptions (endpoint, p256dh, auth)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth)"
    )->execute([$endpoint, $p256dh, $auth]);

    echo json_encode(['ok' => true]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos']);
}
