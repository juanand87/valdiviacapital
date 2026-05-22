<?php
/**
 * Simple file-based cache helper.
 * TTL default: 300 seconds (5 minutes).
 */

function cacheGet(string $key, int $ttl = 300) {
    $file = __DIR__ . '/../cache/' . md5($key) . '.cache';
    if (!file_exists($file) || (time() - filemtime($file)) > $ttl) {
        return false;
    }
    $data = file_get_contents($file);
    return $data !== false ? unserialize($data) : false;
}

function cacheSet(string $key, $data): void {
    $file = __DIR__ . '/../cache/' . md5($key) . '.cache';
    file_put_contents($file, serialize($data), LOCK_EX);
}

function cacheDelete(string $key): void {
    $file = __DIR__ . '/../cache/' . md5($key) . '.cache';
    if (file_exists($file)) {
        @unlink($file);
    }
}

function cacheInvalidateHomepage(): void {
    cacheDelete('homepage_main');
    cacheDelete('homepage_trending');
    cacheDelete('homepage_comunas');
}
