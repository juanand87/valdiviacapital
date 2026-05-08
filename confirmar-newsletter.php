<?php
require_once 'includes/config.php';

$token = $_GET['token'] ?? '';
$estado = 'error';
$mensaje = '';

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, confirmado FROM newsletter WHERE token = ?");
    $stmt->execute([$token]);
    $fila = $stmt->fetch();

    if ($fila) {
        if ($fila['confirmado']) {
            $estado  = 'ya_confirmado';
            $mensaje = 'Esta dirección de correo ya estaba confirmada.';
        } else {
            $db->prepare("UPDATE newsletter SET confirmado = 1, activo = 1, token = NULL WHERE id = ?")
               ->execute([$fila['id']]);
            $estado  = 'ok';
            $mensaje = '¡Suscripción confirmada! Ya recibirás las noticias de Valdivia Capital.';
        }
    } else {
        $mensaje = 'El enlace no es válido o ya fue utilizado.';
    }
} else {
    $mensaje = 'Enlace inválido.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar suscripción – Valdivia Capital</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="display:flex;flex-direction:column;min-height:100vh;background:#f5f5f5;">

    <!-- Header mínimo -->
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php">
                        <h1>VALDIVIA CAPITAL</h1>
                        <p class="tagline">La voz de la región &bull; Valdivia, Chile</p>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main style="flex:1;display:flex;align-items:center;justify-content:center;padding:40px 16px;">
        <div style="background:white;border-radius:12px;padding:48px 40px;max-width:480px;width:100%;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,0.1);">

            <?php if ($estado === 'ok'): ?>
                <div style="width:72px;height:72px;background:#d1fae5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                    <i class="fas fa-check" style="font-size:32px;color:#059669;"></i>
                </div>
                <h2 style="font-size:1.6rem;font-weight:800;margin-bottom:12px;color:#222;">¡Bienvenido/a!</h2>
                <p style="color:#666;line-height:1.7;margin-bottom:28px;"><?php echo $mensaje; ?></p>
            <?php elseif ($estado === 'ya_confirmado'): ?>
                <div style="width:72px;height:72px;background:#dbeafe;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                    <i class="fas fa-info-circle" style="font-size:32px;color:#3b82f6;"></i>
                </div>
                <h2 style="font-size:1.6rem;font-weight:800;margin-bottom:12px;color:#222;">Ya estabas suscrito</h2>
                <p style="color:#666;line-height:1.7;margin-bottom:28px;"><?php echo $mensaje; ?></p>
            <?php else: ?>
                <div style="width:72px;height:72px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                    <i class="fas fa-times" style="font-size:32px;color:#dc2626;"></i>
                </div>
                <h2 style="font-size:1.6rem;font-weight:800;margin-bottom:12px;color:#222;">Enlace inválido</h2>
                <p style="color:#666;line-height:1.7;margin-bottom:28px;"><?php echo $mensaje; ?></p>
            <?php endif; ?>

            <a href="index.php" class="btn-primary" style="display:inline-flex;">
                <i class="fas fa-home"></i> Volver al inicio
            </a>
        </div>
    </main>

    <footer class="main-footer" style="margin-top:0;">
        <div class="footer-bottom" style="border-top:none;">
            <p>&copy; <?php echo date('Y'); ?> Valdivia Capital. Todos los derechos reservados.</p>
        </div>
    </footer>

</body>
</html>
