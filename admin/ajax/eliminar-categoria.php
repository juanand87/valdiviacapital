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
    
    // Verificar si tiene noticias asociadas
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM noticias WHERE categoria_id = ?");
    $stmt->execute([$id]);
    $total = $stmt->fetch()['total'];
    
    if ($total > 0) {
        echo json_encode(['success' => false, 'message' => "Esta categoría tiene $total noticias asociadas"]);
        exit;
    }
    
    // Eliminar
    $stmt = $db->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
