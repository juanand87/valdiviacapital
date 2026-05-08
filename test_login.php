<?php
require_once 'includes/config.php';

$email = 'admin@losrios.cl';
$password = 'admin123';

$db = getDB();
$stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if ($usuario) {
    echo "Usuario encontrado: " . $usuario['nombre'] . "\n";
    echo "Email: " . $usuario['email'] . "\n";
    echo "Rol: " . $usuario['rol'] . "\n";
    echo "Activo: " . $usuario['activo'] . "\n";
    echo "Hash en DB: " . substr($usuario['password'], 0, 30) . "...\n\n";
    
    if (password_verify($password, $usuario['password'])) {
        echo "✅ ¡Login exitoso! La contraseña es correcta.\n";
    } else {
        echo "❌ La contraseña NO coincide.\n";
    }
} else {
    echo "❌ Usuario no encontrado.\n";
}
?>
