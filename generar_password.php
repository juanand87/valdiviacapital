<?php
// Generar hash de contraseña
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Contraseña: $password\n";
echo "Hash: $hash\n";
?>
