<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/bolsa.php';

if (isMaintenance()) { include 'mantenimiento.php'; exit; }
bolsaPublicadorRequerirLogin();

$db = getDB();
$publicador = bolsaPublicadorActual();
if (!$publicador) {
    bolsaPublicadorLogout();
    header('Location: bolsa-login.php');
    exit;
}

$db->query("UPDATE bolsa_ofertas SET estado = 'vencido' WHERE estado = 'publicado' AND fecha_cierre < CURDATE()");

$stmtStats = $db->prepare("SELECT
    COUNT(*) AS total,
    SUM(estado = 'borrador') AS borradores,
    SUM(estado = 'pendiente') AS pendientes,
    SUM(estado = 'publicado') AS publicadas,
    SUM(estado = 'vencido') AS vencidas
    FROM bolsa_ofertas WHERE publicador_id = ?");
$stmtStats->execute([$publicador['id']]);
$stats = $stmtStats->fetch() ?: ['total'=>0,'borradores'=>0,'pendientes'=>0,'publicadas'=>0,'vencidas'=>0];

$stmt = $db->prepare("SELECT o.*, c.nombre AS comuna_nombre,
    (SELECT COUNT(*) FROM bolsa_postulaciones p WHERE p.oferta_id = o.id) AS total_postulaciones,
    (SELECT COUNT(*) FROM bolsa_postulaciones p WHERE p.oferta_id = o.id AND p.estado = 'nueva') AS nuevas_postulaciones
    FROM bolsa_ofertas o
    LEFT JOIN comunas c ON c.id = o.comuna_id
    WHERE o.publicador_id = ?
    ORDER BY o.updated_at DESC, o.id DESC");
$stmt->execute([$publicador['id']]);
$ofertas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi panel de empleo - Valdivia Capital</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<section class="reportero-dashboard-shell">
    <div class="container">
        <div class="reportero-dashboard-header">
            <div>
                <span class="reportero-kicker">Bolsa de trabajo</span>
                <h1>Hola, <?php echo htmlspecialchars($publicador['nombre']); ?></h1>
                <p>Gestiona tus avisos y revisa postulaciones desde tu panel.</p>
            </div>
            <div class="reportero-dashboard-actions">
                <a href="bolsa-oferta.php" class="reportero-btn primary"><i class="fas fa-plus"></i> Nueva oferta</a>
                <a href="bolsa-logout.php" class="reportero-btn secondary"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
            </div>
        </div>

        <?php if (isset($_GET['welcome'])): ?>
        <div class="reportero-alert success">Tu cuenta fue creada correctamente. Ya puedes publicar ofertas.</div>
        <?php endif; ?>

        <?php if (isset($_GET['nueva'])): ?>
        <div class="reportero-alert success" style="display:flex;flex-direction:column;gap:12px;">
            <div>
                <strong>¡Oferta creada con éxito!</strong><br>
                <?php if ($_GET['nueva'] === 'enviada'): ?>
                    Tu oferta fue enviada a revisión. Será publicada una vez aprobada por nuestro equipo.
                <?php else: ?>
                    Tu borrador fue guardado. Puedes editarlo y enviarlo a revisión cuando quieras.
                <?php endif; ?>
            </div>
            <div>
                <a href="bolsa-panel.php" class="reportero-btn primary"><i class="fas fa-list"></i> Aceptar e ir al listado</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="reportero-stats-grid">
            <div class="reportero-stat-card"><span>Total</span><strong><?php echo (int)$stats['total']; ?></strong></div>
            <div class="reportero-stat-card"><span>Borradores</span><strong><?php echo (int)$stats['borradores']; ?></strong></div>
            <div class="reportero-stat-card"><span>Pendientes</span><strong><?php echo (int)$stats['pendientes']; ?></strong></div>
            <div class="reportero-stat-card"><span>Publicadas</span><strong><?php echo (int)$stats['publicadas']; ?></strong></div>
            <div class="reportero-stat-card"><span>Vencidas</span><strong><?php echo (int)$stats['vencidas']; ?></strong></div>
        </div>

        <div class="reportero-card">
            <div class="reportero-card-header">
                <h2>Mis ofertas</h2>
                <span><?php echo count($ofertas); ?> registro(s)</span>
            </div>

            <?php if (!$ofertas): ?>
                <p class="reportero-empty">Aún no has publicado ofertas. Crea tu primer aviso laboral.</p>
            <?php else: ?>
                <div class="reportero-table-wrap">
                    <table class="reportero-table">
                        <thead>
                        <tr>
                            <th>Aviso</th>
                            <th>Estado</th>
                            <th>Vigencia</th>
                            <th>Postulaciones</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ofertas as $o): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($o['titulo']); ?></strong>
                                    <div style="font-size:12px;color:#888;">
                                        <?php echo htmlspecialchars(bolsaTipoLabel($o['tipo'])); ?> · <?php echo htmlspecialchars($o['comuna_nombre'] ?? 'Sin comuna'); ?>
                                    </div>
                                </td>
                                <td><span class="reportero-status <?php echo $o['estado'] === 'publicado' ? 'status-approved' : ($o['estado'] === 'rechazado' ? 'status-rejected' : ($o['estado'] === 'vencido' ? 'status-review' : 'status-pending')); ?>"><?php echo htmlspecialchars(bolsaEstadoLabel($o['estado'])); ?></span></td>
                                <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($o['fecha_cierre']))); ?></td>
                                <td>
                                    <strong><?php echo (int)$o['total_postulaciones']; ?></strong>
                                    <?php if ((int)$o['nuevas_postulaciones'] > 0): ?>
                                        <span class="reportero-status status-pending"><?php echo (int)$o['nuevas_postulaciones']; ?> nuevas</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="reportero-actions-inline">
                                        <a href="bolsa-oferta.php?id=<?php echo (int)$o['id']; ?>" class="reportero-mini-link">Editar</a>
                                        <a href="bolsa-postulaciones.php?id=<?php echo (int)$o['id']; ?>" class="reportero-mini-link">Postulaciones</a>
                                        <?php if ($o['estado'] === 'publicado'): ?>
                                            <a href="bolsa-detalle.php?slug=<?php echo urlencode($o['slug']); ?>" target="_blank" class="reportero-mini-link">Ver público</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
