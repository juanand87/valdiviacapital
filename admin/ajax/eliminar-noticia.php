<?php
require_once '../includes/auth.php';
verificarSesion();
require_once '../../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id = (int)$_POST['id'];

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

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
    
    $db->commit();
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
