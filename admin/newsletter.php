<?php
$page_title = 'Newsletter';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Obtener suscriptores
$stmt = $db->query("SELECT * FROM newsletter ORDER BY fecha_suscripcion DESC");
$suscriptores = $stmt->fetchAll();

// Estadísticas
$total = count($suscriptores);
$activos = count(array_filter($suscriptores, fn($s) => $s['activo'] == 1));
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Newsletter</h1>
        <p class="page-subtitle">Gestiona los suscriptores</p>
    </div>
</div>

<!-- Estadísticas -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="stat-card">
        <div class="stat-title">Total Suscriptores</div>
        <div class="stat-value"><?php echo $total; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title">Activos</div>
        <div class="stat-value" style="color: var(--color-success);"><?php echo $activos; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title">Inactivos</div>
        <div class="stat-value" style="color: var(--color-danger);"><?php echo $total - $activos; ?></div>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Lista de Suscriptores</h3>
        <button onclick="exportarCSV()" class="btn btn-success btn-sm">
            <i class="fas fa-file-csv"></i> Exportar CSV
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tablaSuscriptores">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Fecha Suscripción</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suscriptores as $sub): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sub['email']); ?></td>
                            <td><?php echo formatDate($sub['fecha_suscripcion']); ?></td>
                            <td>
                                <?php if ($sub['activo']): ?>
                                    <span class="badge badge-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactivo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportarCSV() {
    const tabla = document.getElementById('tablaSuscriptores');
    let csv = 'Email,Fecha Suscripción,Estado\n';
    
    const filas = tabla.querySelectorAll('tbody tr');
    filas.forEach(fila => {
        const cols = fila.querySelectorAll('td');
        csv += `"${cols[0].textContent}","${cols[1].textContent}","${cols[2].textContent}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'suscriptores_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
}
</script>

<?php include 'includes/footer.php'; ?>
