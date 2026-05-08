<?php
// ajax/incrementar_vista.php
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    
    if (!$id) {
        echo json_encode(['success' => false]);
        exit;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE noticias SET vistas = vistas + 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>
