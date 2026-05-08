<?php
// ajax/newsletter.php
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Verificar si ya existe
        $stmt = $db->prepare("SELECT id FROM newsletter WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Este email ya está suscrito']);
            exit;
        }
        
        // Insertar nuevo suscriptor
        $stmt = $db->prepare("INSERT INTO newsletter (email) VALUES (?)");
        $stmt->execute([$email]);
        
        echo json_encode(['success' => true, 'message' => 'Suscripción exitosa']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
