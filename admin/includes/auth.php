<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está autenticado
function verificarSesion() {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_nombre'])) {
        header('Location: ../admin/login.php');
        exit;
    }
}

// Verificar si es admin o editor
function verificarPermiso($rol_requerido = 'periodista') {
    verificarSesion();
    
    $jerarquia = ['admin' => 3, 'editor' => 2, 'periodista' => 1];
    $rol_usuario = $_SESSION['admin_rol'] ?? 'periodista';
    
    if ($jerarquia[$rol_usuario] < $jerarquia[$rol_requerido]) {
        header('Location: index.php');
        exit;
    }
}

// Cerrar sesión
function cerrarSesion() {
    session_destroy();
    header('Location: login.php');
    exit;
}
