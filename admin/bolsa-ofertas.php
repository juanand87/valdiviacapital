<?php
$page_title = 'Bolsa de trabajo';
require_once '../includes/config.php';
require_once '../includes/bolsa.php';
require_once 'includes/auth.php';
verificarPermiso('editor');
include 'includes/header.php';

$db = getDB();
$db->query("UPDATE bolsa_ofertas SET estado = 'vencido' WHERE estado = 'publicado' AND fecha_cierre < CURDATE()");

$estado = $_GET['estado'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$q = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];

if (in_array($estado, ['borrador', 'pendiente', 'publicado', 'rechazado', 'vencido'], true)) {
    $where[] = 'o.estado = ?';
    $params[] = $estado;
}

if (in_array($tipo, ['oferta', 'concurso_publico'], true)) {
    $where[] = 'o.tipo = ?';
    $params[] = $tipo;
}

if ($q !== '') {
    $where[] = '(o.titulo LIKE ? OR o.empresa_institucion LIKE ? OR p.nombre LIKE ? OR p.email LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT o.*, c.nombre AS comuna_nombre, p.nombre AS publicador_nombre, p.email AS publicador_email,
    (SELECT COUNT(*) FROM bolsa_postulaciones bp WHERE bp.oferta_id = o.id) AS total_postulaciones
    FROM bolsa_ofertas o
    LEFT JOIN comunas c ON c.id = o.comuna_id
    LEFT JOIN bolsa_publicadores p ON p.id = o.publicador_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY FIELD(o.estado, 'pendiente', 'publicado', 'vencido', 'rechazado', 'borrador'), o.updated_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$ofertas = $stmt->fetchAll();

$stats = $db->query("SELECT
    COUNT(*) AS total,
    SUM(estado='pendiente') AS pendientes,
    SUM(estado='publicado') AS publicadas,
    SUM(estado='vencido') AS vencidas
    FROM bolsa_ofertas")->fetch();
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-briefcase"></i> Bolsa de trabajo</h1>
        <p>Moderación y control de ofertas laborales</p>
    </div>
    <a href="bolsa-config.php" class="btn btn-primary"><i class="fas fa-sliders-h"></i> Configuración</a>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-title">Total</div><div class="stat-value"><?php echo (int)$stats['total']; ?></div></div>
    <div class="stat-card"><div class="stat-title">Pendientes</div><div class="stat-value"><?php echo (int)$stats['pendientes']; ?></div></div>
    <div class="stat-card"><div class="stat-title">Publicadas</div><div class="stat-value"><?php echo (int)$stats['publicadas']; ?></div></div>
    <div class="stat-card"><div class="stat-title">Vencidas</div><div class="stat-value"><?php echo (int)$stats['vencidas']; ?></div></div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end;">
            <div>
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Título, empresa o publicador">
            </div>
            <div>
                <label class="form-label">Estado</label>
                <select class="form-control" name="estado">
                    <option value="">Todos</option>
                    <option value="pendiente" <?php echo $estado==='pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="publicado" <?php echo $estado==='publicado' ? 'selected' : ''; ?>>Publicado</option>
                    <option value="rechazado" <?php echo $estado==='rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                    <option value="vencido" <?php echo $estado==='vencido' ? 'selected' : ''; ?>>Vencido</option>
                    <option value="borrador" <?php echo $estado==='borrador' ? 'selected' : ''; ?>>Borrador</option>
                </select>
            </div>
            <div>
                <label class="form-label">Tipo</label>
                <select class="form-control" name="tipo">
                    <option value="">Todos</option>
                    <option value="oferta" <?php echo $tipo==='oferta' ? 'selected' : ''; ?>>Oferta</option>
                    <option value="concurso_publico" <?php echo $tipo==='concurso_publico' ? 'selected' : ''; ?>>Concurso público</option>
                </select>
            </div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Ofertas</h2></div>
    <div class="card-body table-responsive">
        <?php if (!$ofertas): ?>
            <p>No hay registros para los filtros seleccionados.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Publicador</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Cierre</th>
                        <th>Postulaciones</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ofertas as $o): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($o['titulo']); ?></strong>
                            <div style="font-size:12px;color:#777;"><?php echo htmlspecialchars($o['empresa_institucion']); ?> · <?php echo htmlspecialchars($o['comuna_nombre'] ?? 'Sin comuna'); ?></div>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($o['publicador_nombre'] ?? ''); ?>
                            <div style="font-size:12px;color:#777;"><?php echo htmlspecialchars($o['publicador_email'] ?? ''); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars(bolsaTipoLabel($o['tipo'])); ?></td>
                        <td><?php echo htmlspecialchars(bolsaEstadoLabel($o['estado'])); ?></td>
                        <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($o['fecha_cierre']))); ?></td>
                        <td><?php echo (int)$o['total_postulaciones']; ?></td>
                        <td><a class="btn btn-sm btn-primary" href="revisar-bolsa.php?id=<?php echo (int)$o['id']; ?>">Revisar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
