<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'admin') {
    header('Location: login.php');
    exit;
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h1 class="h2">📊 Estadísticas del Portal</h1>
            </div>

            <!-- Tarjetas de Resumen -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Visitas Totales (Hoy)</h5>
                            <h2 id="visitas-hoy">Cargando...</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Noticia Más Leída</h5>
                            <p id="noticia-top" class="mb-0">Cargando...</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Categoría Top</h5>
                            <p id="categoria-top" class="mb-0">Cargando...</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Promedio Diario</h5>
                            <h2 id="promedio-diario">Cargando...</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">📈 Noticias Más Leídas (Últimos 7 días)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="grafico-noticias"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">🍕 Visitas por Categoría</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="grafico-categorias"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Tendencias -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">🔥 Tendencias Actuales</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabla-tendencias">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Noticia</th>
                                    <th>Categoría</th>
                                    <th>Visitas (7 días)</th>
                                    <th>Tendencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="5" class="text-center">Cargando datos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cargar datos de la API
    fetch('api_estadisticas.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error cargando estadísticas: ' + data.error);
                return;
            }

            // Actualizar tarjetas
            document.getElementById('visitas-hoy').textContent = data.resumen.visitasHoy.toLocaleString();
            document.getElementById('noticia-top').textContent = data.resumen.noticiaTop.titulo || 'Sin datos';
            document.getElementById('categoria-top').textContent = data.resumen.categoriaTop.nombre || 'Sin datos';
            document.getElementById('promedio-diario').textContent = Math.round(data.resumen.promedioDiario).toLocaleString();

            // Gráfico de Barras - Noticias Más Leídas
            const ctxNoticias = document.getElementById('grafico-noticias').getContext('2d');
            new Chart(ctxNoticias, {
                type: 'bar',
                data: {
                    labels: data.graficos.noticias.map(n => n.titulo.substring(0, 20) + '...'),
                    datasets: [{
                        label: 'Visitas',
                        data: data.graficos.noticias.map(n => n.visitas),
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Gráfico de Dona - Categorías
            const ctxCategorias = document.getElementById('grafico-categorias').getContext('2d');
            new Chart(ctxCategorias, {
                type: 'doughnut',
                data: {
                    labels: data.graficos.categorias.map(c => c.nombre),
                    datasets: [{
                        data: data.graficos.categorias.map(c => c.visitas),
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });

            // Tabla de Tendencias
            const tbody = document.querySelector('#tabla-tendencias tbody');
            tbody.innerHTML = '';
            
            if (data.tendencias.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No hay datos disponibles</td></tr>';
            } else {
                data.tendencias.forEach((item, index) => {
                    const tendenciaIcon = item.tendencia > 0 ? '⬆️' : (item.tendencia < 0 ? '⬇️' : '➡️');
                    const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${item.titulo}</td>
                            <td>${item.categoria}</td>
                            <td>${item.visitas.toLocaleString()}</td>
                            <td>${tendenciaIcon} ${item.tendencia > 0 ? '+' : ''}${item.tendencia}%</td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar las estadísticas. Verifica la consola para más detalles.');
        });
});
</script>

<?php include 'includes/footer.php'; ?>
