<?php
require_once 'auth.php';
verificarSesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - Administración Los Ríos</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="admin-header">
                <div class="header-search">
                    <input type="text" placeholder="Buscar...">
                </div>
                
                <div class="header-user">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['admin_nombre']; ?></div>
                        <div class="user-role"><?php echo ucfirst($_SESSION['admin_rol']); ?></div>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_nombre'], 0, 2)); ?>
                    </div>
                </div>
            </header>
            
            <div class="content-area">
