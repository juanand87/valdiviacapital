<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function reporteroNormalizarRut($rut) {
    $rut = strtoupper((string)$rut);
    $rut = preg_replace('/[^0-9K]/', '', $rut);

    if (strlen($rut) < 2) {
        return '';
    }

    $cuerpo = substr($rut, 0, -1);
    $dv = substr($rut, -1);
    $cuerpo = ltrim($cuerpo, '0');
    if ($cuerpo === '') {
        $cuerpo = '0';
    }

    return $cuerpo . '-' . $dv;
}

function reporteroValidarRut($rut) {
    $rut = reporteroNormalizarRut($rut);
    if (!$rut || strpos($rut, '-') === false) {
        return false;
    }

    [$cuerpo, $dv] = explode('-', $rut, 2);
    if (!ctype_digit($cuerpo)) {
        return false;
    }

    $suma = 0;
    $multiplo = 2;
    for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
        $suma += (int)$cuerpo[$i] * $multiplo;
        $multiplo = $multiplo === 7 ? 2 : $multiplo + 1;
    }

    $resto = 11 - ($suma % 11);
    if ($resto === 11) {
        $esperado = '0';
    } elseif ($resto === 10) {
        $esperado = 'K';
    } else {
        $esperado = (string)$resto;
    }

    return strtoupper($dv) === $esperado;
}

function reporteroSlug($texto) {
    $texto = trim((string)$texto);
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = strtolower((string)$texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    $texto = preg_replace('/-+/', '-', $texto);
    return trim((string)$texto, '-');
}

function reporteroEstadoLabel($estado) {
    $map = [
        'borrador' => 'Borrador',
        'pendiente' => 'Pendiente',
        'en_revision' => 'En revisión',
        'requiere_correccion' => 'Requiere corrección',
        'aprobado' => 'Aprobado',
        'rechazado' => 'Rechazado',
    ];

    return $map[$estado] ?? ucfirst(str_replace('_', ' ', (string)$estado));
}

function reporteroEstadoBadgeClass($estado) {
    $map = [
        'borrador' => 'status-draft',
        'pendiente' => 'status-pending',
        'en_revision' => 'status-review',
        'requiere_correccion' => 'status-fix',
        'aprobado' => 'status-approved',
        'rechazado' => 'status-rejected',
    ];

    return $map[$estado] ?? 'status-pending';
}

function reporteroPuedeEditarEnvio($estado) {
    return in_array($estado, ['borrador', 'requiere_correccion'], true);
}

function reporteroLogin($reportero) {
    $_SESSION['reportero_id'] = (int)$reportero['id'];
    $_SESSION['reportero_nombre'] = trim($reportero['nombres'] . ' ' . $reportero['apellidos']);
    $_SESSION['reportero_email'] = $reportero['email'];
}

function reporteroLogout() {
    unset($_SESSION['reportero_id'], $_SESSION['reportero_nombre'], $_SESSION['reportero_email']);
}

function reporteroEstaAutenticado() {
    return !empty($_SESSION['reportero_id']);
}

function reporteroRequerirLogin() {
    if (!reporteroEstaAutenticado()) {
        header('Location: ser-reportero.php?login=1');
        exit;
    }
}

function reporteroActual() {
    if (!reporteroEstaAutenticado()) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM reporteros WHERE id = ? AND activo = 1');
    $stmt->execute([$_SESSION['reportero_id']]);
    return $stmt->fetch();
}

function reporteroSubirImagen($fieldName, $existingPath = null) {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => $existingPath];
    }

    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'No se pudo subir la imagen.'];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'La imagen supera el límite de 5 MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extMap[$mime])) {
        return ['ok' => false, 'error' => 'Formato de imagen no permitido. Usa JPG, PNG o WEBP.'];
    }

    $dir = __DIR__ . '/../uploads/noticias';
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'No fue posible preparar el directorio de imágenes.'];
    }

    $filename = 'reportero_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extMap[$mime];
    $relativePath = 'uploads/noticias/' . $filename;
    $absolutePath = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        return ['ok' => false, 'error' => 'No fue posible guardar la imagen en el servidor.'];
    }

    return ['ok' => true, 'path' => $relativePath];
}

function reporteroGenerarSlugUnico(PDO $db, $titulo, $excludeNoticiaId = null) {
    $base = reporteroSlug($titulo);
    if ($base === '') {
        $base = 'noticia-reportero';
    }

    $slug = $base;
    $n = 2;
    while (true) {
        if ($excludeNoticiaId) {
            $stmt = $db->prepare('SELECT id FROM noticias WHERE slug = ? AND id != ? LIMIT 1');
            $stmt->execute([$slug, $excludeNoticiaId]);
        } else {
            $stmt = $db->prepare('SELECT id FROM noticias WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
        }
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $n;
        $n++;
    }
}
