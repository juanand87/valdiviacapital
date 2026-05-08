<?php
$page_title = 'Dashboard';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Obtener estadísticas
$stats = [];

// Total noticias
$stmt = $db->query("SELECT COUNT(*) as total FROM noticias");
$stats['noticias'] = $stmt->fetch()['total'];

// Total categorías
$stmt = $db->query("SELECT COUNT(*) as total FROM categorias WHERE activo = 1");
$stats['categorias'] = $stmt->fetch()['total'];

// Total comentarios
$stmt = $db->query("SELECT COUNT(*) as total FROM comentarios");
$stats['comentarios'] = $stmt->fetch()['total'];

// Total suscriptores
$stmt = $db->query("SELECT COUNT(*) as total FROM newsletter WHERE activo = 1");
$stats['newsletter'] = $stmt->fetch()['total'];

// Noticias recientes
$stmt = $db->query("
    SELECT n.*, c.nombre as categoria_nombre, u.nombre as autor_nombre
    FROM noticias n
    INNER JOIN categorias c ON n.categoria_id = c.id
    INNER JOIN usuarios u ON n.autor_id = u.id
    ORDER BY n.fecha_publicacion DESC
    LIMIT 5
");
$noticias_recientes = $stmt->fetchAll();

// Comentarios pendientes
$stmt = $db->query("
    SELECT cm.*, n.titulo as noticia_titulo
    FROM comentarios cm
    INNER JOIN noticias n ON cm.noticia_id = n.id
    WHERE cm.aprobado = 0
    ORDER BY cm.created_at DESC
    LIMIT 5
");
$comentarios_pendientes = $stmt->fetchAll();

// ---- Datos para gráficos ----

// Noticias por categoría
$chartCats = $db->query("
    SELECT c.nombre, COUNT(n.id) as total, c.color
    FROM categorias c
    LEFT JOIN noticias n ON n.categoria_id = c.id AND n.publicado = 1
    WHERE c.activo = 1
    GROUP BY c.id, c.nombre, c.color
    ORDER BY total DESC
    LIMIT 8
")->fetchAll();
$chartCatLabels  = json_encode(array_column($chartCats, 'nombre'));
$chartCatValues  = json_encode(array_map('intval', array_column($chartCats, 'total')));
$chartCatColors  = json_encode(array_column($chartCats, 'color'));

// Vistas por día últimos 7 días
$chartDays = $db->query("
    SELECT DATE(fecha_publicacion) as dia,
           SUM(vistas) as vistas_total,
           COUNT(id) as noticias_count
    FROM noticias
    WHERE publicado = 1 AND fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha_publicacion)
    ORDER BY dia ASC
")->fetchAll();
// Rellenar días faltantes
$diasMap = [];
for ($i = 6; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime("-$i days"));
    $diasMap[$key] = ['vistas' => 0, 'label' => date('d/m', strtotime("-$i days"))];
}
foreach ($chartDays as $row) {
    if (isset($diasMap[$row['dia']])) {
        $diasMap[$row['dia']]['vistas'] = (int)$row['vistas_total'];
    }
}
$chartDayLabels = json_encode(array_column($diasMap, 'label'));
$chartDayValues = json_encode(array_column($diasMap, 'vistas'));

// Top 5 más leídas
$topNoticias = $db->query("
    SELECT titulo, vistas FROM noticias
    WHERE publicado = 1
    ORDER BY vistas DESC
    LIMIT 5
")->fetchAll();
$topLabels = json_encode(array_map(fn($r) => mb_substr($r['titulo'], 0, 40) . '...', $topNoticias));
$topValues = json_encode(array_map('intval', array_column($topNoticias, 'vistas')));
?>

<div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Bienvenido, <?php echo $_SESSION['admin_nombre']; ?></p>
</div>

<!-- Estadísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Total Noticias</span>
            <div class="stat-icon primary">
                <i class="fas fa-newspaper"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $stats['noticias']; ?></div>
        <div class="stat-footer">
            <a href="noticias.php">Ver todas →</a>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Categorías</span>
            <div class="stat-icon success">
                <i class="fas fa-folder"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $stats['categorias']; ?></div>
        <div class="stat-footer">
            <a href="categorias.php">Administrar →</a>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Comentarios</span>
            <div class="stat-icon warning">
                <i class="fas fa-comments"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $stats['comentarios']; ?></div>
        <div class="stat-footer">
            <a href="comentarios.php">Moderar →</a>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Suscriptores</span>
            <div class="stat-icon info">
                <i class="fas fa-envelope"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $stats['newsletter']; ?></div>
        <div class="stat-footer">
            <a href="newsletter.php">Ver lista →</a>
        </div>
    </div>
</div>

<!-- Noticias Recientes -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Noticias Recientes</h3>
        <a href="noticias.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Nueva Noticia
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Autor</th>
                        <th>Vistas</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($noticias_recientes)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #718096;">
                                No hay noticias todavía
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($noticias_recientes as $noticia): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($noticia['titulo']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-success"><?php echo htmlspecialchars($noticia['categoria_nombre']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($noticia['autor_nombre']); ?></td>
                                <td><?php echo number_format($noticia['vistas']); ?></td>
                                <td><?php echo formatDate($noticia['fecha_publicacion']); ?></td>
                                <td>
                                    <a href="editar-noticia.php?id=<?php echo $noticia['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Comentarios Pendientes -->
<?php if (!empty($comentarios_pendientes)): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Comentarios Pendientes de Aprobación</h3>
        <a href="comentarios.php" class="btn btn-warning btn-sm">
            Ver Todos
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Autor</th>
                        <th>Noticia</th>
                        <th>Comentario</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comentarios_pendientes as $comentario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($comentario['nombre']); ?></td>
                            <td><?php echo htmlspecialchars(truncate($comentario['noticia_titulo'], 40)); ?></td>
                            <td><?php echo htmlspecialchars(truncate($comentario['comentario'], 60)); ?></td>
                            <td><?php echo timeAgo($comentario['created_at']); ?></td>
                            <td>
                                <a href="comentarios.php" class="btn btn-sm btn-success">
                                    <i class="fas fa-check"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== GRÁFICOS CHART.JS ===== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">

    <!-- Distribución por categoría -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-pie" style="color:#c8102e;margin-right:6px;"></i>Noticias por Categoría</h3>
        </div>
        <div class="card-body" style="display:flex;align-items:center;justify-content:center;">
            <canvas id="chartCategorias" style="max-height:260px;"></canvas>
        </div>
    </div>

    <!-- Top 5 más leídas -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-fire" style="color:#c8102e;margin-right:6px;"></i>Top 5 Más Leídas</h3>
        </div>
        <div class="card-body">
            <canvas id="chartTop" style="max-height:260px;"></canvas>
        </div>
    </div>

</div>

<!-- Vistas últimos 7 días (ancho completo) -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-line" style="color:#c8102e;margin-right:6px;"></i>Vistas publicadas — Últimos 7 días</h3>
    </div>
    <div class="card-body">
        <canvas id="chartVistas" style="max-height:220px;"></canvas>
    </div>
</div>

<script>
(function () {
    // --- Donut: Noticias por categoría ---
    new Chart(document.getElementById('chartCategorias'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $chartCatLabels; ?>,
            datasets: [{
                data: <?php echo $chartCatValues; ?>,
                backgroundColor: <?php echo $chartCatColors; ?>,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right', labels: { font: { size: 12 }, boxWidth: 14 } }
            }
        }
    });

    // --- Bar: Top 5 noticias ---
    new Chart(document.getElementById('chartTop'), {
        type: 'bar',
        data: {
            labels: <?php echo $topLabels; ?>,
            datasets: [{
                label: 'Vistas',
                data: <?php echo $topValues; ?>,
                backgroundColor: 'rgba(200,16,46,0.75)',
                borderColor: '#c8102e',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { font: { size: 11 } } }, y: { ticks: { font: { size: 11 } } } }
        }
    });

    // --- Line: Vistas 7 días ---
    new Chart(document.getElementById('chartVistas'), {
        type: 'line',
        data: {
            labels: <?php echo $chartDayLabels; ?>,
            datasets: [{
                label: 'Vistas acumuladas',
                data: <?php echo $chartDayValues; ?>,
                borderColor: '#c8102e',
                backgroundColor: 'rgba(200,16,46,0.08)',
                borderWidth: 2,
                pointRadius: 4,
                pointBackgroundColor: '#c8102e',
                tension: 0.35,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
