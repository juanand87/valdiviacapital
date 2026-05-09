<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

$tiposValidos = ['me_gusta', 'me_encanta', 'sorpresa'];
$noticiaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
          ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$noticiaId || $noticiaId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    if (!in_array($tipo, $tiposValidos, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo inválido']);
        exit;
    }

    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

    // Verificar si ya existe una reacción de esta IP
    $stmt = $db->prepare("SELECT id, tipo FROM reacciones WHERE noticia_id = ? AND ip_hash = ?");
    $stmt->execute([$noticiaId, $ipHash]);
    $existente = $stmt->fetch();

    if ($existente) {
        if ($existente['tipo'] === $tipo) {
            // Toggle off: eliminar la reacción
            $db->prepare("DELETE FROM reacciones WHERE id = ?")->execute([$existente['id']]);
        } else {
            // Cambiar tipo
            $db->prepare("UPDATE reacciones SET tipo = ?, created_at = NOW() WHERE id = ?")
               ->execute([$tipo, $existente['id']]);
        }
    } else {
        // Nueva reacción
        $db->prepare("INSERT INTO reacciones (noticia_id, tipo, ip_hash) VALUES (?, ?, ?)")
           ->execute([$noticiaId, $tipo, $ipHash]);
    }
}

// Devolver conteos actualizados
$stmt = $db->prepare("SELECT tipo, COUNT(*) as total FROM reacciones WHERE noticia_id = ? GROUP BY tipo");
$stmt->execute([$noticiaId]);
$counts = ['me_gusta' => 0, 'me_encanta' => 0, 'sorpresa' => 0];
foreach ($stmt->fetchAll() as $row) {
    if (isset($counts[$row['tipo']])) {
        $counts[$row['tipo']] = (int)$row['total'];
    }
}

// Reacción del usuario actual
$ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
$stmt2 = $db->prepare("SELECT tipo FROM reacciones WHERE noticia_id = ? AND ip_hash = ?");
$stmt2->execute([$noticiaId, $ipHash]);
$miReaccion = $stmt2->fetchColumn();

echo json_encode([
    'counts' => $counts,
    'mi_reaccion' => $miReaccion ?: null,
]);
