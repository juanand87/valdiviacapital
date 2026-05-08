<?php
// Imagen placeholder dinámica
header('Content-Type: image/svg+xml');

$width = $_GET['w'] ?? 600;
$height = $_GET['h'] ?? 400;
$text = $_GET['text'] ?? 'Imagen';
$bg = $_GET['bg'] ?? '2563eb';
$color = $_GET['color'] ?? 'ffffff';

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?php echo $width; ?>" height="<?php echo $height; ?>" viewBox="0 0 <?php echo $width; ?> <?php echo $height; ?>">
    <rect width="100%" height="100%" fill="#<?php echo $bg; ?>"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="24" fill="#<?php echo $color; ?>" text-anchor="middle" dominant-baseline="middle">
        <?php echo htmlspecialchars($text); ?>
    </text>
</svg>
