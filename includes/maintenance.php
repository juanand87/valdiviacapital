<?php
// Función separada en archivo propio para que quede en git
// (includes/config.php está en .gitignore)
if (!function_exists('isMaintenance')) {
    function isMaintenance() {
        return file_exists(__DIR__ . '/../cache/maintenance.flag');
    }
}
