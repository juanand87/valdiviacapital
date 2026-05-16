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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmtOferta = $db->prepare('SELECT o.*, c.nombre AS comuna_nombre FROM bolsa_ofertas o LEFT JOIN comunas c ON c.id = o.comuna_id WHERE o.id = ? AND o.publicador_id = ? LIMIT 1');
$stmtOferta->execute([$id, $publicador['id']]);
$oferta = $stmtOferta->fetch();

if (!$oferta) {
    header('Location: bolsa-panel.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = (int)($_POST['postulacion_id'] ?? 0);
    $estado = $_POST['estado'] ?? 'revisada';
    if (in_array($estado, ['nueva', 'revisada', 'contactado', 'descartada'], true) && $postId > 0) {
        $stmt = $db->prepare('UPDATE bolsa_postulaciones p JOIN bolsa_ofertas o ON o.id = p.oferta_id SET p.estado = ?, p.updated_at = NOW() WHERE p.id = ? AND o.publicador_id = ?');
        $stmt->execute([$estado, $postId, $publicador['id']]);
    }
    header('Location: bolsa-postulaciones.php?id=' . $id);
    exit;
}

$stmt = $db->prepare('SELECT * FROM bolsa_postulaciones WHERE oferta_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([$id]);
$postulaciones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postulaciones - <?php echo htmlspecialchars($oferta['titulo']); ?></title>
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
                <span class="reportero-kicker">Postulaciones</span>
                <h1><?php echo htmlspecialchars($oferta['titulo']); ?></h1>
                <p><?php echo htmlspecialchars($oferta['comuna_nombre'] ?? ''); ?> · Cierre: <?php echo htmlspecialchars(date('d-m-Y', strtotime($oferta['fecha_cierre']))); ?></p>
            </div>
            <div class="reportero-dashboard-actions">
                <a href="bolsa-panel.php" class="reportero-btn secondary"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
        </div>

        <div class="reportero-card">
            <?php if (!$postulaciones): ?>
                <p class="reportero-empty">Aún no hay postulaciones para esta oferta.</p>
            <?php else: ?>
                <div class="reportero-table-wrap">
                    <table class="reportero-table">
                        <thead>
                            <tr>
                                <th>Postulante</th>
                                <th>Mensaje</th>
                                <th>CV</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($postulaciones as $p): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                    <div style="font-size:12px;color:#777;"><?php echo htmlspecialchars($p['email']); ?> · <?php echo htmlspecialchars($p['telefono']); ?></div>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars(truncate($p['mensaje'], 140))); ?></td>
                                <td><a href="<?php echo htmlspecialchars($p['cv_archivo']); ?>" target="_blank" class="reportero-mini-link">Descargar CV</a></td>
                                <td><span class="reportero-status <?php echo $p['estado'] === 'contactado' ? 'status-approved' : ($p['estado'] === 'descartada' ? 'status-rejected' : 'status-pending'); ?>"><?php echo htmlspecialchars(bolsaEstadoLabel($p['estado'])); ?></span></td>
                                <td><?php echo htmlspecialchars(formatDate($p['created_at'])); ?></td>
                                <td>
                                    <form method="POST" style="display:flex;gap:8px;">
                                        <input type="hidden" name="postulacion_id" value="<?php echo (int)$p['id']; ?>">
                                        <select name="estado" style="padding:6px 8px;border:1px solid #ddd;border-radius:6px;">
                                            <option value="nueva" <?php echo $p['estado']==='nueva' ? 'selected' : ''; ?>>Nueva</option>
                                            <option value="revisada" <?php echo $p['estado']==='revisada' ? 'selected' : ''; ?>>Revisada</option>
                                            <option value="contactado" <?php echo $p['estado']==='contactado' ? 'selected' : ''; ?>>Contactado</option>
                                            <option value="descartada" <?php echo $p['estado']==='descartada' ? 'selected' : ''; ?>>Descartada</option>
                                        </select>
                                        <button type="submit" class="reportero-btn secondary" style="padding:6px 10px;">Guardar</button>
                                    </form>
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
