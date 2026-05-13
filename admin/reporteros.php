<?php
$page_title = 'Reporteros VC';
require_once '../includes/config.php';
require_once '../includes/reporteros.php';
require_once 'includes/auth.php';
verificarPermiso('editor');

$db = getDB();
$filtroEstado = $_GET['estado'] ?? '';
$busqueda = trim($_GET['buscar'] ?? '');

$where = [];
$params = [];

if ($filtroEstado !== '') {
    $where[] = 'rn.estado = ?';
    $params[] = $filtroEstado;
}
if ($busqueda !== '') {
    $where[] = '(rn.titulo LIKE ? OR r.nombres LIKE ? OR r.apellidos LIKE ? OR r.email LIKE ?)';
    $like = '%' . $busqueda . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stats = $db->query("SELECT
    COUNT(*) AS total,
    SUM(estado = 'pendiente') AS pendientes,
    SUM(estado = 'en_revision') AS revision,
    SUM(estado = 'requiere_correccion') AS correccion,
    SUM(estado = 'aprobado') AS aprobados,
    SUM(estado = 'rechazado') AS rechazados
    FROM reportero_noticias")->fetch();

$stmt = $db->prepare("SELECT rn.*, r.nombres, r.apellidos, r.email, n.slug AS noticia_slug
    FROM reportero_noticias rn
    INNER JOIN reporteros r ON r.id = rn.reportero_id
    LEFT JOIN noticias n ON n.id = rn.noticia_publicada_id
    $whereSql
    ORDER BY FIELD(rn.estado, 'pendiente', 'en_revision', 'requiere_correccion', 'borrador', 'aprobado', 'rechazado'), rn.updated_at DESC");
$stmt->execute($params);
$envios = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reporteros VC</h1>
        <p class="page-subtitle">Revisa envíos ciudadanos, solicita correcciones y publícalos como noticias.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-title">Total</div><div class="stat-value"><?php echo (int)($stats['total'] ?? 0); ?></div></div>
    <div class="stat-card"><div class="stat-title">Pendientes</div><div class="stat-value"><?php echo (int)($stats['pendientes'] ?? 0); ?></div></div>
    <div class="stat-card"><div class="stat-title">Corrección</div><div class="stat-value"><?php echo (int)($stats['correccion'] ?? 0); ?></div></div>
    <div class="stat-card"><div class="stat-title">Aprobadas</div><div class="stat-value"><?php echo (int)($stats['aprobados'] ?? 0); ?></div></div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;align-items:end;">
            <div class="form-group" style="margin:0;flex:1;min-width:240px;">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Título, nombre o correo">
            </div>
            <div class="form-group" style="margin:0;min-width:200px;">
                <label class="form-label">Estado</label>
                <select class="form-control" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (['borrador','pendiente','en_revision','requiere_correccion','aprobado','rechazado'] as $estado): ?>
                        <option value="<?php echo $estado; ?>" <?php echo $filtroEstado === $estado ? 'selected' : ''; ?>><?php echo htmlspecialchars(reporteroEstadoLabel($estado)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
            <a href="reporteros.php" class="btn" style="background:#718096;color:white;"><i class="fas fa-times"></i> Limpiar</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Reportero</th>
                        <th>Estado</th>
                        <th>Enviado</th>
                        <th>Publicado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$envios): ?>
                    <tr><td colspan="6">No hay envíos para los filtros aplicados.</td></tr>
                <?php endif; ?>
                <?php foreach ($envios as $envio): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($envio['titulo']); ?></strong>
                            <div style="color:#718096;font-size:12px;margin-top:4px;">Actualizado <?php echo htmlspecialchars(timeAgo($envio['updated_at'])); ?></div>
                        </td>
                        <td>
                            <?php echo htmlspecialchars(trim($envio['nombres'] . ' ' . $envio['apellidos'])); ?><br>
                            <span style="color:#718096;font-size:12px;"><?php echo htmlspecialchars($envio['email']); ?></span>
                        </td>
                        <td><span class="badge <?php echo $envio['estado'] === 'aprobado' ? 'badge-success' : ($envio['estado'] === 'rechazado' ? 'badge-danger' : 'badge-warning'); ?>"><?php echo htmlspecialchars(reporteroEstadoLabel($envio['estado'])); ?></span></td>
                        <td><?php echo $envio['fecha_envio'] ? htmlspecialchars(formatDate($envio['fecha_envio'])) : 'No enviado'; ?></td>
                        <td>
                            <?php if ($envio['noticia_publicada_id'] && $envio['noticia_slug']): ?>
                                <a href="../noticia.php?slug=<?php echo htmlspecialchars($envio['noticia_slug']); ?>" target="_blank">Ver noticia</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="revisar-reportero.php?id=<?php echo (int)$envio['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Revisar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>