<?php
require_once '../includes/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: ../index.php');
    exit;
}

$db = getDB();

// Incrementar clics y obtener URL en una sola consulta
$db->prepare("UPDATE banners SET clics = clics + 1 WHERE id = ? AND activo = 1")->execute([$id]);

$stmt = $db->prepare("SELECT url_destino FROM banners WHERE id = ?");
$stmt->execute([$id]);
$url = $stmt->fetchColumn();

// Validate URL before redirecting to prevent open redirect attacks
if ($url && filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url)) {
    header('Location: ' . $url);
} else {
    header('Location: ../index.php');
}
exit;
