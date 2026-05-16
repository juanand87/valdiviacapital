<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/bolsa.php';

if (isMaintenance()) { include 'mantenimiento.php'; exit; }

$db = getDB();
$db->query("UPDATE bolsa_ofertas SET estado = 'vencido' WHERE estado = 'publicado' AND fecha_cierre < CURDATE()");

$todasComunas = $db->query('SELECT id, nombre, slug FROM comunas ORDER BY nombre')->fetchAll();
$comunaId = (int)($_GET['comuna_id'] ?? 0);
$tipo = $_GET['tipo'] ?? '';
$q = trim($_GET['q'] ?? '');

$where = ["o.estado = 'publicado'", 'o.fecha_cierre >= CURDATE()'];
$params = [];

if ($comunaId > 0) {
    $where[] = 'o.comuna_id = ?';
    $params[] = $comunaId;
}

if (in_array($tipo, ['oferta', 'concurso_publico'], true)) {
    $where[] = 'o.tipo = ?';
    $params[] = $tipo;
}

if ($q !== '') {
    $where[] = '(o.titulo LIKE ? OR o.cargo LIKE ? OR o.empresa_institucion LIKE ? OR o.rubro LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT o.*, c.nombre AS comuna_nombre
    FROM bolsa_ofertas o
    LEFT JOIN comunas c ON c.id = o.comuna_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY o.destacado DESC, o.published_at DESC, o.id DESC
    LIMIT 50";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$ofertas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bolsa de trabajo - Valdivia Capital</title>
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
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-x-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <button id="btn-dark-mode" title="Cambiar tema"><i class="fas fa-moon"></i> <span class="dm-label">Oscuro</span></button>
                </div>
            </div>
        </div>
    </div>

    <section class="reportero-hero">
        <div class="container reportero-hero-grid">
            <div>
                <span class="reportero-kicker">Bolsa de trabajo VC</span>
                <h1>Ofertas laborales y concursos públicos</h1>
                <p>Encuentra oportunidades laborales en la región y postula en línea con CV adjunto.</p>
            </div>
            <div class="reportero-dashboard-actions" style="justify-content:flex-end;align-items:center;">
                <a href="bolsa-login.php" class="reportero-btn primary"><i class="fas fa-plus"></i> Publicar oferta</a>
            </div>
        </div>
    </section>

    <section class="reportero-auth-section" style="padding-top:0;">
        <div class="container">
            <div class="reportero-card" style="margin-bottom:20px;">
                <form method="GET" class="reportero-form-grid" style="grid-template-columns:2fr 1fr 1fr auto;align-items:end;">
                    <div>
                        <label>Buscar</label>
                        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Cargo, empresa, rubro...">
                    </div>
                    <div>
                        <label>Comuna</label>
                        <select name="comuna_id">
                            <option value="0">Todas</option>
                            <?php foreach ($todasComunas as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>" <?php echo $comunaId === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="">Todos</option>
                            <option value="oferta" <?php echo $tipo === 'oferta' ? 'selected' : ''; ?>>Oferta</option>
                            <option value="concurso_publico" <?php echo $tipo === 'concurso_publico' ? 'selected' : ''; ?>>Concurso público</option>
                        </select>
                    </div>
                    <button type="submit" class="reportero-btn dark"><i class="fas fa-filter"></i> Filtrar</button>
                </form>
            </div>

            <?php if (!$ofertas): ?>
                <div class="reportero-card"><p class="reportero-empty">No hay ofertas disponibles con esos filtros.</p></div>
            <?php else: ?>
                <div class="bolsa-grid">
                    <?php foreach ($ofertas as $o): ?>
                        <article class="reportero-card bolsa-card <?php echo (int)$o['destacado'] === 1 ? 'bolsa-card-destacado' : ''; ?>">
                            <div class="bolsa-head">
                                <span class="reportero-status <?php echo $o['tipo'] === 'concurso_publico' ? 'status-review' : 'status-approved'; ?>"><?php echo htmlspecialchars(bolsaTipoLabel($o['tipo'])); ?></span>
                                <?php if ((int)$o['destacado'] === 1): ?><span class="reportero-status status-fix">Destacado</span><?php endif; ?>
                            </div>
                            <h3><?php echo htmlspecialchars($o['titulo']); ?></h3>
                            <p class="bolsa-meta"><i class="fas fa-building"></i> <?php echo htmlspecialchars($o['empresa_institucion']); ?></p>
                            <p class="bolsa-meta"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($o['cargo']); ?> · <?php echo htmlspecialchars($o['rubro']); ?></p>
                            <p class="bolsa-meta"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($o['comuna_nombre'] ?? 'Sin comuna'); ?></p>
                            <p class="bolsa-meta"><i class="fas fa-calendar-times"></i> Cierra: <?php echo htmlspecialchars(date('d-m-Y', strtotime($o['fecha_cierre']))); ?></p>
                            <a href="bolsa-detalle.php?slug=<?php echo urlencode($o['slug']); ?>" class="reportero-btn primary" style="margin-top:8px;"><i class="fas fa-paper-plane"></i> Ver y postular</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
