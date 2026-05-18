<?php
$page_title = 'Medios Conectados';
require_once '../includes/config.php';
include 'includes/header.php';

$db = getDB();

// Obtener estadísticas de medios conectados
$stats = [];

// Total medios por tipo
$stmt = $db->query("SELECT tipo, COUNT(*) as total FROM medios_conectados WHERE activo = 1 GROUP BY tipo");
$medios_por_tipo = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stats['diarios'] = $medios_por_tipo['diario_online'] ?? 0;
$stats['facebook'] = $medios_por_tipo['facebook'] ?? 0;
$stats['instagram'] = $medios_por_tipo['instagram'] ?? 0;
$stats['total'] = array_sum($medios_por_tipo);

// Últimas sincronizaciones
$stmt = $db->query("
    SELECT m.*, 
           CASE 
               WHEN m.ultima_sincronizacion IS NULL THEN 'Nunca'
               WHEN TIMESTAMPDIFF(MINUTE, m.ultima_sincronizacion, NOW()) < 60 
                   THEN CONCAT(TIMESTAMPDIFF(MINUTE, m.ultima_sincronizacion, NOW()), ' min')
               WHEN TIMESTAMPDIFF(HOUR, m.ultima_sincronizacion, NOW()) < 24 
                   THEN CONCAT(TIMESTAMPDIFF(HOUR, m.ultima_sincronizacion, NOW()), ' hrs')
               ELSE CONCAT(TIMESTAMPDIFF(DAY, m.ultima_sincronizacion, NOW()), ' días')
           END as tiempo_transcurrido
    FROM medios_conectados m
    WHERE m.activo = 1
    ORDER BY m.ultima_sincronizacion DESC
    LIMIT 10
");
$ultimas_sincronizaciones = $stmt->fetchAll();

// Contenido pendiente de procesar
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM medios_contenido_sincronizado 
    WHERE estado = 'pendiente'
");
$contenido_pendiente = $stmt->fetch()['total'];

?>

<div class="page-header">
    <h1><i class="fas fa-broadcast-tower"></i> Medios Conectados</h1>
    <p>Gestiona la sincronización de contenido desde diferentes medios digitales</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #3498db;">
            <i class="fas fa-newspaper"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['diarios']; ?></h3>
            <p>Diarios Online</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #1877f2;">
            <i class="fab fa-facebook"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['facebook']; ?></h3>
            <p>Medios Facebook</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #e4405f;">
            <i class="fab fa-instagram"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['instagram']; ?></h3>
            <p>Medios Instagram</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #f39c12;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $contenido_pendiente; ?></h3>
            <p>Contenido Pendiente</p>
        </div>
    </div>
</div>

<div class="content-grid">
    <div class="col-8">
        <div class="card">
            <div class="card-header">
                <h2>Tipos de Medios</h2>
            </div>
            <div class="card-body">
                <div class="medios-tipos-grid">
                    <a href="medios-diarios.php" class="medio-tipo-card">
                        <div class="medio-icon" style="background: #3498db;">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <h3>Diarios Online</h3>
                        <p>Configura scrapping de noticias desde portales web</p>
                        <span class="badge"><?php echo $stats['diarios']; ?> activos</span>
                    </a>
                    
                    <a href="medios-facebook.php" class="medio-tipo-card">
                        <div class="medio-icon" style="background: #1877f2;">
                            <i class="fab fa-facebook"></i>
                        </div>
                        <h3>Medios de Facebook</h3>
                        <p>Sincroniza publicaciones desde páginas de Facebook</p>
                        <span class="badge"><?php echo $stats['facebook']; ?> activos</span>
                    </a>
                    
                    <a href="medios-instagram.php" class="medio-tipo-card">
                        <div class="medio-icon" style="background: #e4405f;">
                            <i class="fab fa-instagram"></i>
                        </div>
                        <h3>Medios de Instagram</h3>
                        <p>Obtén contenido desde perfiles de Instagram</p>
                        <span class="badge"><?php echo $stats['instagram']; ?> activos</span>
                    </a>

                </div>
            </div>
        </div>
    </div>
    
    <div class="col-4">
        <div class="card">
            <div class="card-header">
                <h2>Últimas Sincronizaciones</h2>
            </div>
            <div class="card-body">
                <?php if (empty($ultimas_sincronizaciones)): ?>
                    <p class="text-muted">No hay sincronizaciones registradas</p>
                <?php else: ?>
                    <div class="sync-list">
                        <?php foreach ($ultimas_sincronizaciones as $sync): ?>
                            <div class="sync-item">
                                <div class="sync-icon">
                                    <?php if ($sync['tipo'] == 'diario_online'): ?>
                                        <i class="fas fa-newspaper"></i>
                                    <?php elseif ($sync['tipo'] == 'facebook'): ?>
                                        <i class="fab fa-facebook"></i>
                                    <?php else: ?>
                                        <i class="fab fa-instagram"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="sync-info">
                                    <strong><?php echo htmlspecialchars($sync['nombre']); ?></strong>
                                    <small>hace <?php echo $sync['tiempo_transcurrido']; ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.medios-tipos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.medio-tipo-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    display: block;
}

.medio-tipo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    border-color: #3498db;
}

.medio-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    color: white;
    font-size: 36px;
}

.medio-tipo-card h3 {
    margin: 15px 0 10px;
    color: #2c3e50;
    font-size: 18px;
}

.medio-tipo-card p {
    color: #7f8c8d;
    font-size: 14px;
    margin-bottom: 15px;
}

.medio-tipo-card .badge {
    display: inline-block;
    background: #3498db;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.sync-list {
    max-height: 400px;
    overflow-y: auto;
}

.sync-item {
    display: flex;
    align-items: center;
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
}

.sync-item:last-child {
    border-bottom: none;
}

.sync-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #ecf0f1;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    color: #7f8c8d;
}

.sync-info {
    flex: 1;
}

.sync-info strong {
    display: block;
    color: #2c3e50;
    font-size: 14px;
}

.sync-info small {
    color: #95a5a6;
    font-size: 12px;
}

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

@media (max-width: 992px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
