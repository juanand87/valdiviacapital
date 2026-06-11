<?php
// ========================================
// CONFIGURACIÓN DE BASE DE DATOS
// ========================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'bd_valdivia');
define('DB_USER', 'user_valdivia');
define('DB_PASS', '5802863aA$$$');
define('DB_CHARSET', 'utf8mb4');

// Configuración del sitio
define('SITE_URL', 'http://www.valdiviacapital.cl');
define('SITE_NAME', 'Valdivia Capital');
define('SITE_DESCRIPTION', 'El principal medio de comunicación digital de la región');

// Zona horaria
date_default_timezone_set('America/Santiago');

// Función para obtener conexión PDO
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    return $pdo;
}

function clean($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function generateSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

function formatDate($date) {
    $timestamp = strtotime($date);
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
              'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $dia = date('d', $timestamp);
    $mes = $meses[(int)date('n', $timestamp)];
    $anio = date('Y', $timestamp);
    $hora = date('H:i', $timestamp);
    return "$dia de $mes de $anio, $hora hrs";
}

function timeAgo($date) {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Hace menos de un minuto';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "Hace $minutes " . ($minutes == 1 ? 'minuto' : 'minutos');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "Hace $hours " . ($hours == 1 ? 'hora' : 'horas');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "Hace $days " . ($days == 1 ? 'día' : 'días');
    } else {
        return formatDate($date);
    }
}

function truncate($text, $length = 150) {
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length);
        $text = substr($text, 0, strrpos($text, ' '));
        return $text . '...';
    }
    return $text;
}

function baseUrl($path = '') {
    return SITE_URL . ($path ? '/' . ltrim($path, '/') : '');
}
?>
