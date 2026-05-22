<?php
// Handler AJAX para redacción con IA
require_once '../../includes/config.php';
require_once '../../includes/gemini.php';
session_start();

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);
ob_start();

function responderJson(array $payload, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = '{"error":"No se pudo serializar la respuesta JSON."}';
    }
    echo $json;
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'error' => 'Error fatal al generar la redacción IA.',
            'debug' => $error['message'] ?? 'error desconocido'
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
});

try {
    if (!isset($_SESSION['admin_id'])) {
        responderJson(['error' => 'No autorizado'], 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responderJson(['error' => 'Método no permitido'], 405);
    }

    $input = $_POST;
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (empty($input) && strpos($contentType, 'application/json') !== false) {
        $decoded = json_decode(file_get_contents('php://input'), true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    $noticia_id = (int)($input['noticia_id'] ?? 0);
    if ($noticia_id <= 0) {
        responderJson(['error' => 'ID de noticia inválido'], 422);
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM medios_contenido_sincronizado WHERE id = :id');
    $stmt->execute([':id' => $noticia_id]);
    $noticia = $stmt->fetch();

    if (!$noticia) {
        responderJson(['error' => 'Noticia no encontrada'], 404);
    }

    $resultado = redactarConIA($db, $noticia);
    if (!is_array($resultado)) {
        responderJson(['error' => 'Respuesta inválida del motor de IA'], 500);
    }

    responderJson($resultado, 200);
} catch (Throwable $e) {
    error_log('redactar-ia.php: ' . $e->getMessage());
    responderJson(['error' => 'Error interno al generar redacción IA: ' . $e->getMessage()], 500);
}
