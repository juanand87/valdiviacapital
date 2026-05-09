<?php
/**
 * Banner helper: getBanner() and renderBanner().
 * Requires getDB() to be available (included after config.php).
 */

function getBanner(string $posicion): ?array {
    static $cache = [];
    if (array_key_exists($posicion, $cache)) return $cache[$posicion];

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM banners
            WHERE posicion = ? AND activo = 1
              AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
              AND (fecha_fin   IS NULL OR fecha_fin   >= CURDATE())
            ORDER BY orden ASC, RAND()
            LIMIT 1
        ");
        $stmt->execute([$posicion]);
        $cache[$posicion] = $stmt->fetch() ?: null;
        return $cache[$posicion];
    } catch (Exception $e) {
        return null;
    }
}

function renderBanner(string $posicion): void {
    $b = getBanner($posicion);
    if (!$b) return;
    $slug = htmlspecialchars($posicion, ENT_QUOTES);
    $alt  = htmlspecialchars($b['titulo'], ENT_QUOTES);
    $img  = htmlspecialchars($b['imagen_url'], ENT_QUOTES);
    $id   = (int)$b['id'];
    echo "<div class=\"ad-slot ad-{$slug}\">";
    echo '<span class="ad-label">Publicidad</span>';
    echo "<a href=\"ajax/banner-click.php?id={$id}\" target=\"_blank\" rel=\"noopener nofollow sponsored\">";
    echo "<img src=\"{$img}\" alt=\"{$alt}\" loading=\"lazy\">";
    echo '</a></div>';
}
