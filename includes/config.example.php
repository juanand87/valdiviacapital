<?php
// ========================================
// CONFIGURACIÓN DE BASE DE DATOS
// Copiar este archivo como config.php y ajustar los valores
// ========================================

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'bd_valdivia');        // Nombre de la BD en producción
define('DB_USER', 'cpses_k007nefycb');  // Usuario del hosting
define('DB_PASS', 'TU_PASSWORD_AQUI');
define('DB_CHARSET', 'utf8mb4');

// Configuración del sitio
define('SITE_URL', 'https://tudominio.cl');
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
