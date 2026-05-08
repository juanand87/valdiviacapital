<?php
// Handler AJAX para redacción con IA
require_once '../../includes/config.php';
require_once '../../includes/gemini.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$noticia_id = (int)($_POST['noticia_id'] ?? 0);

if (!$noticia_id) {
    echo json_encode(['error' => 'ID de noticia inválido']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("
    SELECT * FROM medios_contenido_sincronizado WHERE id = :id
");
$stmt->execute([':id' => $noticia_id]);
$noticia = $stmt->fetch();

if (!$noticia) {
    echo json_encode(['error' => 'Noticia no encontrada']);
    exit;
}

$resultado = redactarConIA($db, $noticia);

echo json_encode($resultado);
