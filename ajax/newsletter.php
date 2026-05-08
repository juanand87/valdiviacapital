<?php
// ajax/newsletter.php
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

try {
    $db = getDB();

    // Verificar si ya existe
    $stmt = $db->prepare("SELECT id, confirmado FROM newsletter WHERE email = ?");
    $stmt->execute([$email]);
    $existente = $stmt->fetch();

    if ($existente) {
        if ($existente['confirmado']) {
            echo json_encode(['success' => false, 'message' => 'Este email ya está suscrito y confirmado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Revisa tu correo — ya enviamos un enlace de confirmación']);
        }
        exit;
    }

    // Generar token seguro
    $token = bin2hex(random_bytes(32));

    $stmt = $db->prepare("INSERT INTO newsletter (email, token, confirmado, activo) VALUES (?, ?, 0, 0)");
    $stmt->execute([$email, $token]);

    // Construir enlace de confirmación
    $confirmarUrl = rtrim(SITE_URL, '/') . '/confirmar-newsletter.php?token=' . $token;

    // Intentar enviar email (si hay servidor de correo configurado)
    $asunto  = 'Confirma tu suscripción – Valdivia Capital';
    $cuerpo  = "Hola,\n\nGracias por suscribirte a Valdivia Capital.\n\n";
    $cuerpo .= "Confirma tu suscripción haciendo clic en el siguiente enlace:\n$confirmarUrl\n\n";
    $cuerpo .= "Si no solicitaste esta suscripción, puedes ignorar este mensaje.\n\n";
    $cuerpo .= "Valdivia Capital – contacto@valdiviacapital.cl";
    $headers = "From: Valdivia Capital <no-reply@valdiviacapital.cl>\r\nContent-Type: text/plain; charset=UTF-8";

    @mail($email, $asunto, $cuerpo, $headers);

    echo json_encode([
        'success' => true,
        'message' => '¡Casi listo! Revisa tu correo y confirma tu suscripción.',
        'debug_url' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $confirmarUrl : null,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
}
?>
