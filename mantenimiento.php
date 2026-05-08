<?php
// Esta página NO verifica isMaintenance() para evitar redirección circular
require_once 'includes/config.php';
// Responder con 503 Service Unavailable
header('HTTP/1.1 503 Service Unavailable');
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>En Mantenimiento - Valdivia Capital</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #0f0f0f;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
        }

        /* Fondo animado */
        .bg-glow {
            position: fixed;
            inset: 0;
            z-index: 0;
            background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(200,16,46,0.18) 0%, transparent 70%),
                        radial-gradient(ellipse 60% 40% at 80% 100%, rgba(200,16,46,0.10) 0%, transparent 60%);
        }

        .particles {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(200,16,46,0.15);
            animation: float linear infinite;
        }

        @keyframes float {
            0%   { transform: translateY(110vh) scale(0); opacity: 0; }
            10%  { opacity: 1; }
            90%  { opacity: 0.6; }
            100% { transform: translateY(-10vh) scale(1.2); opacity: 0; }
        }

        /* Contenido */
        .card {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 60px 50px 50px;
            max-width: 580px;
            width: 90%;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            backdrop-filter: blur(20px);
            box-shadow: 0 32px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(200,16,46,0.1);
            animation: fadeInUp 0.8s ease both;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .logo-wrap {
            margin-bottom: 36px;
        }

        .logo-wrap img {
            max-width: 280px;
            width: 100%;
            height: auto;
            filter: brightness(0) invert(1);
            opacity: 0.9;
        }

        .icon-wrap {
            width: 80px;
            height: 80px;
            margin: 0 auto 28px;
            background: linear-gradient(135deg, #c8102e, #a00d24);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            animation: pulse 2.5s ease-in-out infinite;
            box-shadow: 0 0 0 0 rgba(200,16,46,0.4);
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(200,16,46,0.4); }
            50%       { box-shadow: 0 0 0 18px rgba(200,16,46,0); }
        }

        h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 14px;
            background: linear-gradient(135deg, #fff 40%, rgba(255,255,255,0.6));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            font-size: 1.05rem;
            color: rgba(255,255,255,0.55);
            line-height: 1.7;
            margin-bottom: 36px;
        }

        .divider {
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #c8102e, #a00d24);
            border-radius: 2px;
            margin: 0 auto 36px;
        }

        /* Barra de progreso animada */
        .progress-wrap {
            background: rgba(255,255,255,0.07);
            border-radius: 100px;
            height: 4px;
            overflow: hidden;
            margin-bottom: 32px;
        }

        .progress-bar-anim {
            height: 100%;
            background: linear-gradient(90deg, #c8102e, #ff4d6d, #c8102e);
            background-size: 200% 100%;
            border-radius: 100px;
            animation: shimmer 2s linear infinite;
            width: 60%;
        }

        @keyframes shimmer {
            0%   { background-position: 200% center; }
            100% { background-position: -200% center; }
        }

        .status-text {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.3);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Footer */
        .footer-maint {
            position: relative;
            z-index: 1;
            margin-top: 32px;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.2);
        }

        @media (max-width: 480px) {
            .card { padding: 40px 24px 36px; }
            h1 { font-size: 1.55rem; }
            .logo-wrap img { max-width: 200px; }
        }
    </style>
</head>
<body>

<div class="bg-glow"></div>
<div class="particles" id="particles"></div>

<div class="card">
    <div class="logo-wrap">
        <img src="https://valdiviacapital.cl/logovc.png" alt="Valdivia Capital">
    </div>

    <div class="icon-wrap">
        <i class="fas fa-tools"></i>
    </div>

    <h1>Sitio en Mantenimiento</h1>
    <div class="divider"></div>
    <p class="subtitle">
        Estamos trabajando para mejorar tu experiencia.<br>
        Volveremos muy pronto con novedades.
    </p>

    <div class="progress-wrap">
        <div class="progress-bar-anim"></div>
    </div>

    <p class="status-text">Actualización en progreso&hellip;</p>
</div>

<div class="footer-maint">
    &copy; <?php echo date('Y'); ?> Valdivia Capital &bull; Todos los derechos reservados
</div>

<script>
// Generar partículas flotantes
(function () {
    var container = document.getElementById('particles');
    for (var i = 0; i < 18; i++) {
        var p = document.createElement('div');
        p.className = 'particle';
        var size = Math.random() * 60 + 10;
        p.style.cssText = [
            'width:' + size + 'px',
            'height:' + size + 'px',
            'left:' + Math.random() * 100 + '%',
            'animation-duration:' + (Math.random() * 14 + 8) + 's',
            'animation-delay:' + (Math.random() * 10) + 's'
        ].join(';');
        container.appendChild(p);
    }
})();
</script>
</body>
</html>
