<?php
header('Content-Type: application/json');
session_start();
require_once 'includes/config.php';

// Verificar autenticación (opcional, puede ser llamado por AJAX)
if (!isset($_SESSION['usuario_id'])) {
    // echo json_encode(['error' => 'No autorizado']); exit;
}

try {
    // 1. Visitas de hoy
    $stmt = $db->prepare("SELECT SUM(visitas) as total FROM estadisticas_visitas WHERE fecha = CURDATE()");
    $stmt->execute();
    $visitasHoy = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 2. Noticia más leída (últimos 7 días)
    $stmt = $db->prepare("
        SELECT n.titulo, SUM(e.visitas) as total_visitas 
        FROM estadisticas_visitas e
        JOIN noticias n ON e.noticia_id = n.id
        WHERE e.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY e.noticia_id
        ORDER BY total_visitas DESC
        LIMIT 1
    ");
    $stmt->execute();
    $noticiaTop = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Categoría más visitada
    $stmt = $db->prepare("
        SELECT c.nombre, SUM(e.visitas) as total_visitas 
        FROM estadisticas_visitas e
        JOIN categorias c ON e.categoria_id = c.id
        WHERE e.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY e.categoria_id
        ORDER BY total_visitas DESC
        LIMIT 1
    ");
    $stmt->execute();
    $categoriaTop = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Promedio diario (últimos 7 días)
    $stmt = $db->prepare("
        SELECT AVG(daily_total) as promedio
        FROM (
            SELECT SUM(visitas) as daily_total
            FROM estadisticas_visitas
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY fecha
        ) as subquery
    ");
    $stmt->execute();
    $promedioDiario = $stmt->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0;

    // 5. Datos para gráfico de noticias (Top 10)
    $stmt = $db->prepare("
        SELECT n.titulo, SUM(e.visitas) as visitas
        FROM estadisticas_visitas e
        JOIN noticias n ON e.noticia_id = n.id
        WHERE e.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY e.noticia_id, n.titulo
        ORDER BY visitas DESC
        LIMIT 10
    ");
    $stmt->execute();
    $noticiasGrafico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Datos para gráfico de categorías
    $stmt = $db->prepare("
        SELECT c.nombre, SUM(e.visitas) as visitas
        FROM estadisticas_visitas e
        JOIN categorias c ON e.categoria_id = c.id
        WHERE e.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY e.categoria_id, c.nombre
        ORDER BY visitas DESC
    ");
    $stmt->execute();
    $categoriasGrafico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Tendencias (comparativa semana actual vs anterior)
    $stmt = $db->prepare("
        SELECT 
            n.titulo,
            c.nombre as categoria,
            COALESCE(SUM(CASE WHEN e.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN e.visitas ELSE 0 END), 0) as visitas_actuales,
            COALESCE(SUM(CASE WHEN e.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 8 DAY) THEN e.visitas ELSE 0 END), 0) as visitas_anteriores
        FROM estadisticas_visitas e
        JOIN noticias n ON e.noticia_id = n.id
        JOIN categorias c ON n.categoria_id = c.id
        GROUP BY e.noticia_id, n.titulo, c.nombre
        HAVING visitas_actuales > 0
        ORDER BY visitas_actuales DESC
        LIMIT 10
    ");
    $stmt->execute();
    $tendenciasRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tendencias = [];
    foreach ($tendenciasRaw as $t) {
        $variacion = 0;
        if ($t['visitas_anteriores'] > 0) {
            $variacion = round((($t['visitas_actuales'] - $t['visitas_anteriores']) / $t['visitas_anteriores']) * 100);
        } elseif ($t['visitas_actuales'] > 0) {
            $variacion = 100; // Nueva noticia
        }
        
        $tendencias[] = [
            'titulo' => $t['titulo'],
            'categoria' => $t['categoria'],
            'visitas' => $t['visitas_actuales'],
            'tendencia' => $variacion
        ];
    }

    // Respuesta JSON
    echo json_encode([
        'resumen' => [
            'visitasHoy' => (int)$visitasHoy,
            'noticiaTop' => $noticiaTop ?: ['titulo' => 'Sin datos'],
            'categoriaTop' => $categoriaTop ?: ['nombre' => 'Sin datos'],
            'promedioDiario' => (float)$promedioDiario
        ],
        'graficos' => [
            'noticias' => $noticiasGrafico,
            'categorias' => $categoriasGrafico
        ],
        'tendencias' => $tendencias
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>
