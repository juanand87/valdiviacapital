<?php
// ========================================
// CONFIGURACIÓN DE BASE DE DATOS
// Copiar este archivo como config.php y ajustar los valores
// ========================================

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'nombre_base_de_datos');
define('DB_USER', 'usuario_db');
define('DB_PASS', 'password_db');
define('DB_CHARSET', 'utf8mb4');

// Configuración del sitio
define('SITE_URL', 'http://localhost/valdiviacapital');
define('SITE_NAME', 'Nombre del Sitio');
define('SITE_DESCRIPTION', 'Descripción del sitio');

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
