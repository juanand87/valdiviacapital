<?php
/**
 * ajax/ultima-hora.php — Devuelve el último mensaje push al Service Worker
 *
 * Llamado por sw.js cuando recibe un push sin payload.
 * El SW muestra la notificación con el título y texto retornados aquí.
 *
 * Responde: JSON { titulo, bajada, url }
 */

require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

try {
    $db = getDB();

    /* 1) Último mensaje enviado por el admin desde el panel de push */
    $msg = $db->query(
        "SELECT titulo, mensaje, url FROM push_messages ORDER BY id DESC LIMIT 1"
    )->fetch();

    if ($msg) {
        echo json_encode([
            'titulo' => $msg['titulo'],
            'bajada' => $msg['mensaje'],
            'url'    => $msg['url'] ?: SITE_URL . '/',
        ]);
        exit;
    }

    /* 2) Fallback: última noticia publicada */
    $noticia = $db->query(
        "SELECT titulo, bajada, slug FROM noticias WHERE publicado = 1 ORDER BY fecha_publicacion DESC LIMIT 1"
    )->fetch();

    if ($noticia) {
        $resumen = mb_strimwidth(strip_tags((string)($noticia['bajada'] ?? '')), 0, 120, '…');
        echo json_encode([
            'titulo' => $noticia['titulo'],
            'bajada' => $resumen,
            'url'    => SITE_URL . '/noticia.php?slug=' . urlencode($noticia['slug']),
        ]);
        exit;
    }

} catch (\Throwable $e) {
    /* ignorar error de BD — responder con valores por defecto */
}

echo json_encode([
    'titulo' => defined('SITE_NAME') ? SITE_NAME : 'Valdivia Capital',
    'bajada' => 'Nuevas noticias disponibles',
    'url'    => defined('SITE_URL') ? SITE_URL . '/' : '/',
]);
