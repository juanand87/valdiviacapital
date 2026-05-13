<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/reporteros.php';

if (isMaintenance()) { include 'mantenimiento.php'; exit; }
reporteroRequerirLogin();

$db = getDB();
$reportero = reporteroActual();
if (!$reportero) {
    reporteroLogout();
    header('Location: ser-reportero.php?login=1');
    exit;
}

$stmtStats = $db->prepare("SELECT
    COUNT(*) AS total,
    SUM(estado = 'borrador') AS borradores,
    SUM(estado = 'pendiente') AS pendientes,
    SUM(estado = 'requiere_correccion') AS correcciones,
    SUM(estado = 'aprobado') AS aprobadas
    FROM reportero_noticias
    WHERE reportero_id = ?");
$stmtStats->execute([$reportero['id']]);
$stats = $stmtStats->fetch() ?: ['total' => 0, 'borradores' => 0, 'pendientes' => 0, 'correcciones' => 0, 'aprobadas' => 0];

$stmt = $db->prepare("SELECT rn.*, n.slug AS noticia_slug
    FROM reportero_noticias rn
    LEFT JOIN noticias n ON n.id = rn.noticia_publicada_id
    WHERE rn.reportero_id = ?
    ORDER BY rn.updated_at DESC, rn.id DESC");
$stmt->execute([$reportero['id']]);
$envios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi panel de reportero - Valdivia Capital</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="top-header">
        <div class="container">
            <div class="top-header-content">
                <div class="date"><i class="far fa-calendar-alt"></i><span id="current-date"></span></div>
                <div class="social-links">
                    <button id="btn-dark-mode" title="Cambiar tema"><i class="fas fa-moon"></i> <span class="dm-label">Oscuro</span></button>
                </div>
            </div>
        </div>
    </div>

    <section class="reportero-dashboard-shell">
        <div class="container">
            <div class="reportero-dashboard-header">
                <div>
                    <span class="reportero-kicker">Panel privado</span>
                    <h1>Hola, <?php echo htmlspecialchars($reportero['nombres']); ?></h1>
                    <p>Desde aquí puedes crear envíos, guardar borradores y seguir cada cambio editorial.</p>
                </div>
                <div class="reportero-dashboard-actions">
                    <a href="reportero-noticia.php" class="reportero-btn primary"><i class="fas fa-pen"></i> Nuevo envío</a>
                    <a href="reportero-logout.php" class="reportero-btn secondary"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
                </div>
            </div>

            <?php if (isset($_GET['welcome'])): ?>
            <div class="reportero-alert success">Tu cuenta fue creada correctamente. Ya puedes comenzar a enviar noticias.</div>
            <?php endif; ?>

            <div class="reportero-stats-grid">
                <div class="reportero-stat-card"><span>Total</span><strong><?php echo (int)$stats['total']; ?></strong></div>
                <div class="reportero-stat-card"><span>Borradores</span><strong><?php echo (int)$stats['borradores']; ?></strong></div>
                <div class="reportero-stat-card"><span>Pendientes</span><strong><?php echo (int)$stats['pendientes']; ?></strong></div>
                <div class="reportero-stat-card"><span>Correcciones</span><strong><?php echo (int)$stats['correcciones']; ?></strong></div>
                <div class="reportero-stat-card"><span>Aprobadas</span><strong><?php echo (int)$stats['aprobadas']; ?></strong></div>
            </div>

            <div class="reportero-card">
                <div class="reportero-card-header">
                    <h2>Mis envíos</h2>
                    <span><?php echo count($envios); ?> registro(s)</span>
                </div>

                <?php if (!$envios): ?>
                    <p class="reportero-empty">Aún no tienes noticias cargadas. Crea tu primer borrador y envíalo a revisión cuando esté listo.</p>
                <?php else: ?>
                    <div class="reportero-table-wrap">
                        <table class="reportero-table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Estado</th>
                                    <th>Actualizado</th>
                                    <th>Observaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($envios as $envio): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($envio['titulo']); ?></strong>
                                        <?php if ($envio['noticia_publicada_id'] && $envio['noticia_slug']): ?>
                                            <div><a href="noticia.php?slug=<?php echo htmlspecialchars($envio['noticia_slug']); ?>" target="_blank">Ver noticia publicada</a></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="reportero-status <?php echo reporteroEstadoBadgeClass($envio['estado']); ?>"><?php echo htmlspecialchars(reporteroEstadoLabel($envio['estado'])); ?></span></td>
                                    <td><?php echo htmlspecialchars(timeAgo($envio['updated_at'])); ?></td>
                                    <td>
                                        <?php
                                        $nota = $envio['estado'] === 'rechazado' ? ($envio['motivo_rechazo'] ?: 'Sin detalle.') : ($envio['admin_notas'] ?: 'Sin observaciones.');
                                        echo htmlspecialchars(truncate($nota, 90));
                                        ?>
                                    </td>
                                    <td>
                                        <div class="reportero-actions-inline">
                                            <a href="reportero-noticia.php?id=<?php echo (int)$envio['id']; ?>" class="reportero-mini-link"><?php echo reporteroPuedeEditarEnvio($envio['estado']) ? 'Editar' : 'Ver'; ?></a>
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