<?php
$page_title = 'Revisar oferta';
require_once '../includes/config.php';
require_once '../includes/bolsa.php';
require_once 'includes/auth.php';
verificarPermiso('editor');
include 'includes/header.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: bolsa-ofertas.php');
    exit;
}

$stmt = $db->prepare("SELECT o.*, c.nombre AS comuna_nombre, p.nombre AS publicador_nombre, p.email AS publicador_email
    FROM bolsa_ofertas o
    LEFT JOIN comunas c ON c.id = o.comuna_id
    LEFT JOIN bolsa_publicadores p ON p.id = o.publicador_id
    WHERE o.id = ? LIMIT 1");
$stmt->execute([$id]);
$oferta = $stmt->fetch();

if (!$oferta) {
    header('Location: bolsa-ofertas.php');
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'aprobar') {
        $stmt = $db->prepare("UPDATE bolsa_ofertas SET estado='publicado', motivo_rechazo=NULL, revisado_por=?, revisado_at=NOW(), published_at=IFNULL(published_at, NOW()) WHERE id=?");
        $stmt->execute([$_SESSION['admin_id'], $id]);
        $msg = 'Oferta aprobada y publicada.';
    }

    if ($accion === 'rechazar') {
        $motivo = trim($_POST['motivo_rechazo'] ?? '');
        $stmt = $db->prepare("UPDATE bolsa_ofertas SET estado='rechazado', motivo_rechazo=?, revisado_por=?, revisado_at=NOW() WHERE id=?");
        $stmt->execute([$motivo, $_SESSION['admin_id'], $id]);
        $msg = 'Oferta rechazada.';
    }

    if ($accion === 'destacar') {
        $stmt = $db->prepare("UPDATE bolsa_ofertas SET destacado = 1 - destacado WHERE id=?");
        $stmt->execute([$id]);
        $msg = 'Estado destacado actualizado.';
    }

    if ($accion === 'extender') {
        $nueva = trim($_POST['fecha_cierre'] ?? '');
        if (strtotime($nueva)) {
            $stmt = $db->prepare("UPDATE bolsa_ofertas SET fecha_cierre=?, estado=IF(estado='vencido','publicado',estado), updated_at=NOW() WHERE id=?");
            $stmt->execute([$nueva, $id]);
            $msg = 'Fecha de cierre actualizada.';
        }
    }

    $stmtReload = $db->prepare("SELECT o.*, c.nombre AS comuna_nombre, p.nombre AS publicador_nombre, p.email AS publicador_email
        FROM bolsa_ofertas o
        LEFT JOIN comunas c ON c.id = o.comuna_id
        LEFT JOIN bolsa_publicadores p ON p.id = o.publicador_id
        WHERE o.id = ? LIMIT 1");
    $stmtReload->execute([$id]);
    $oferta = $stmtReload->fetch();
}

$stmtPost = $db->prepare('SELECT * FROM bolsa_postulaciones WHERE oferta_id = ? ORDER BY created_at DESC LIMIT 20');
$stmtPost->execute([$id]);
$postulaciones = $stmtPost->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-file-signature"></i> Revisar oferta</h1>
        <p><?php echo htmlspecialchars($oferta['titulo']); ?></p>
    </div>
    <a href="bolsa-ofertas.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2 class="card-title">Datos del aviso</h2></div>
    <div class="card-body">
        <p><strong>Tipo:</strong> <?php echo htmlspecialchars(bolsaTipoLabel($oferta['tipo'])); ?></p>
        <p><strong>Empresa:</strong> <?php echo htmlspecialchars($oferta['empresa_institucion']); ?></p>
        <p><strong>Cargo:</strong> <?php echo htmlspecialchars($oferta['cargo']); ?></p>
        <p><strong>Rubro:</strong> <?php echo htmlspecialchars($oferta['rubro']); ?></p>
        <p><strong>Comuna:</strong> <?php echo htmlspecialchars($oferta['comuna_nombre'] ?? 'Sin comuna'); ?></p>
        <p><strong>Contacto:</strong> <?php echo htmlspecialchars($oferta['email_contacto']); ?><?php echo $oferta['telefono_contacto'] ? ' · ' . htmlspecialchars($oferta['telefono_contacto']) : ''; ?></p>
        <p><strong>Publicador:</strong> <?php echo htmlspecialchars($oferta['publicador_nombre'] ?? ''); ?> (<?php echo htmlspecialchars($oferta['publicador_email'] ?? ''); ?>)</p>
        <p><strong>Estado:</strong> <?php echo htmlspecialchars(bolsaEstadoLabel($oferta['estado'])); ?><?php echo (int)$oferta['destacado'] === 1 ? ' · Destacado' : ''; ?></p>
        <p><strong>Cierre:</strong> <?php echo htmlspecialchars(date('d-m-Y', strtotime($oferta['fecha_cierre']))); ?></p>

        <h3 style="margin-top:15px;">Descripción</h3>
        <div style="white-space:pre-wrap;line-height:1.6;"><?php echo htmlspecialchars($oferta['descripcion']); ?></div>

        <?php if ($oferta['requisitos']): ?>
            <h3 style="margin-top:15px;">Requisitos</h3>
            <div style="white-space:pre-wrap;line-height:1.6;"><?php echo htmlspecialchars($oferta['requisitos']); ?></div>
        <?php endif; ?>

        <?php if ($oferta['motivo_rechazo']): ?>
            <div class="alert alert-warning" style="margin-top:15px;"><strong>Motivo rechazo:</strong> <?php echo htmlspecialchars($oferta['motivo_rechazo']); ?></div>
        <?php endif; ?>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:18px;">
            <form method="POST"><input type="hidden" name="accion" value="aprobar"><button class="btn btn-success"><i class="fas fa-check"></i> Aprobar</button></form>
            <form method="POST"><input type="hidden" name="accion" value="destacar"><button class="btn btn-primary"><i class="fas fa-star"></i> Destacar/Quitar</button></form>
            <form method="POST" style="display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="accion" value="extender">
                <input type="date" name="fecha_cierre" class="form-control" value="<?php echo htmlspecialchars($oferta['fecha_cierre']); ?>" style="width:190px;">
                <button class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Extender</button>
            </form>
        </div>

        <form method="POST" style="margin-top:14px;">
            <input type="hidden" name="accion" value="rechazar">
            <label class="form-label">Motivo de rechazo</label>
            <textarea class="form-control" name="motivo_rechazo" rows="4" placeholder="Detalle para el publicador..."></textarea>
            <button class="btn btn-danger" style="margin-top:8px;"><i class="fas fa-ban"></i> Rechazar</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Últimas postulaciones</h2></div>
    <div class="card-body table-responsive">
        <?php if (!$postulaciones): ?>
            <p>Sin postulaciones todavía.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email/Teléfono</th>
                    <th>Estado</th>
                    <th>CV</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($postulaciones as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($p['email']); ?><br><?php echo htmlspecialchars($p['telefono']); ?></td>
                    <td><?php echo htmlspecialchars(bolsaEstadoLabel($p['estado'])); ?></td>
                    <td><a href="../<?php echo htmlspecialchars($p['cv_archivo']); ?>" target="_blank">Descargar</a></td>
                    <td><?php echo htmlspecialchars(formatDate($p['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
