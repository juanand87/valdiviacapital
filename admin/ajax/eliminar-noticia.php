<?php
// Impide que avisos o espacios de archivos incluidos contaminen la respuesta JSON.
ob_start();
require_once '../includes/auth.php';
verificarSesion();
require_once '../../includes/config.php';
require_once '../../includes/cache.php';

function responderJson(array $respuesta, int $estado = 200): void {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['success' => false, 'message' => 'Método no permitido'], 405);
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    responderJson(['success' => false, 'message' => 'ID inválido'], 400);
}

$db = null;
try {
    $db = getDB();
    
    // Eliminar noticia y sus relaciones
    $db->beginTransaction();
    
    // Eliminar tags asociados
    $stmt = $db->prepare("DELETE FROM noticias_tags WHERE noticia_id = ?");
    $stmt->execute([$id]);
    
    // Eliminar comentarios
    $stmt = $db->prepare("DELETE FROM comentarios WHERE noticia_id = ?");
    $stmt->execute([$id]);
    
    // Eliminar noticia
    $stmt = $db->prepare("DELETE FROM noticias WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() !== 1) {
        $db->rollBack();
        responderJson(['success' => false, 'message' => 'La noticia no existe o ya fue eliminada'], 404);
    }
    
    $db->commit();

    cacheInvalidateHomepage();
    
    responderJson(['success' => true]);
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error al eliminar noticia #' . $id . ': ' . $e->getMessage());
    responderJson(['success' => false, 'message' => 'No se pudo eliminar la noticia'], 500);
}
