<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function bolsaSlug($texto) {
    $texto = trim((string)$texto);
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = strtolower((string)$texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    $texto = preg_replace('/-+/', '-', $texto);
    return trim((string)$texto, '-');
}

function bolsaTipoLabel($tipo) {
    return $tipo === 'concurso_publico' ? 'Concurso público' : 'Oferta de trabajo';
}

function bolsaEstadoLabel($estado) {
    $map = [
        'borrador' => 'Borrador',
        'pendiente' => 'Pendiente',
        'publicado' => 'Publicado',
        'rechazado' => 'Rechazado',
        'vencido' => 'Vencido',
        'nueva' => 'Nueva',
        'revisada' => 'Revisada',
        'contactado' => 'Contactado',
        'descartada' => 'Descartada',
    ];
    return $map[$estado] ?? ucfirst((string)$estado);
}

function bolsaPublicadorLogin($publicador) {
    $_SESSION['bolsa_publicador_id'] = (int)$publicador['id'];
    $_SESSION['bolsa_publicador_nombre'] = $publicador['nombre'];
    $_SESSION['bolsa_publicador_email'] = $publicador['email'];
}

function bolsaPublicadorLogout() {
    unset($_SESSION['bolsa_publicador_id'], $_SESSION['bolsa_publicador_nombre'], $_SESSION['bolsa_publicador_email']);
}

function bolsaPublicadorEstaAutenticado() {
    return !empty($_SESSION['bolsa_publicador_id']);
}

function bolsaPublicadorRequerirLogin() {
    if (!bolsaPublicadorEstaAutenticado()) {
        header('Location: bolsa-login.php');
        exit;
    }
}

function bolsaPublicadorActual() {
    if (!bolsaPublicadorEstaAutenticado()) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM bolsa_publicadores WHERE id = ? AND activo = 1 LIMIT 1');
    $stmt->execute([$_SESSION['bolsa_publicador_id']]);
    return $stmt->fetch();
}

function bolsaSetConfig(PDO $db, $nombre, $valor) {
    $stmt = $db->prepare('INSERT INTO bolsa_config (nombre, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
    $stmt->execute([$nombre, (string)$valor]);
}

function bolsaGetConfig(PDO $db, $nombre, $default = '') {
    $stmt = $db->prepare('SELECT valor FROM bolsa_config WHERE nombre = ? LIMIT 1');
    $stmt->execute([$nombre]);
    $v = $stmt->fetchColumn();
    return $v === false ? $default : $v;
}

function bolsaGenerarSlugUnico(PDO $db, $titulo, $excludeId = null) {
    $base = bolsaSlug($titulo);
    if ($base === '') {
        $base = 'oferta';
    }

    $slug = $base;
    $n = 2;

    while (true) {
        if ($excludeId) {
            $stmt = $db->prepare('SELECT id FROM bolsa_ofertas WHERE slug = ? AND id != ? LIMIT 1');
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $db->prepare('SELECT id FROM bolsa_ofertas WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
        }

        if (!$stmt->fetchColumn()) {
            return $slug;
        }

        $slug = $base . '-' . $n;
        $n++;
    }
}

function bolsaUploadCv($fieldName, $maxMb = 5, $allowedExt = ['pdf', 'doc', 'docx']) {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Debes adjuntar tu CV.'];
    }

    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'No fue posible subir el CV.'];
    }

    if ($file['size'] > ($maxMb * 1024 * 1024)) {
        return ['ok' => false, 'error' => 'El CV supera el tamaño permitido de ' . (int)$maxMb . ' MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'error' => 'Formato de CV no permitido.'];
    }

    $dir = __DIR__ . '/../uploads/cv';
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'No fue posible preparar el directorio de CV.'];
    }

    $filename = 'cv_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $relative = 'uploads/cv/' . $filename;
    $absolute = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absolute)) {
        return ['ok' => false, 'error' => 'No fue posible guardar el CV.'];
    }

    return ['ok' => true, 'path' => $relative];
}

function bolsaVerifyRecaptcha(PDO $db, $token) {
    $secret = trim((string)bolsaGetConfig($db, 'recaptcha_secret_key', ''));

    if ($secret === '') {
        return ['ok' => false, 'error' => 'Recaptcha no configurado.'];
    }

    $token = trim((string)$token);
    if ($token === '') {
        return ['ok' => false, 'error' => 'Debes completar el recaptcha.'];
    }

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $payload,
        'timeout' => 15,
    ]]);

    $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    if ($raw === false) {
        return ['ok' => false, 'error' => 'No fue posible validar recaptcha.'];
    }

    $data = json_decode($raw, true);
    if (empty($data['success'])) {
        return ['ok' => false, 'error' => 'Recaptcha inválido.'];
    }

    return ['ok' => true];
}

function bolsaPostulacionesDisponiblesHoy(PDO $db, $email, $ip) {
    $max = (int)bolsaGetConfig($db, 'max_postulaciones_diarias', '2');
    if ($max <= 0) {
        $max = 2;
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM bolsa_postulaciones WHERE DATE(created_at) = CURDATE() AND (email = ? OR ip = ?)');
    $stmt->execute([$email, $ip]);
    $count = (int)$stmt->fetchColumn();

    return ['ok' => $count < $max, 'count' => $count, 'max' => $max];
}

function bolsaGetSmtpConfig(PDO $db) {
    $stmt = $db->query('SELECT * FROM bolsa_config_smtp WHERE id = 1 LIMIT 1');
    $cfg = $stmt->fetch();
    if (!$cfg) {
        return null;
    }
    return $cfg;
}

function bolsaSendEmail(PDO $db, $to, $subject, $htmlBody, $textBody = '') {
    $to = trim((string)$to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Correo de destino inválido.'];
    }

    $cfg = bolsaGetSmtpConfig($db);
    if (!$cfg || (int)$cfg['activo'] !== 1) {
        // Fallback si SMTP está inactivo.
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= 'From: Valdivia Capital <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
        $ok = @mail($to, $subject, $htmlBody, $headers);
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo enviar el correo (mail() falló).'];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL no disponible para envío SMTP.'];
    }

    $host = trim((string)$cfg['host']);
    $port = (int)$cfg['puerto'];
    $user = trim((string)$cfg['usuario']);
    $pass = (string)$cfg['password'];
    $fromEmail = trim((string)$cfg['from_email']);
    $fromName = trim((string)$cfg['from_name']);
    $secure = (string)$cfg['cifrado'];

    if (!$host || !$port || !$fromEmail) {
        return ['ok' => false, 'error' => 'Configuración SMTP incompleta.'];
    }

    $eol = "\r\n";
    $boundary = 'bnd_' . bin2hex(random_bytes(8));
    $fromDisplay = ($fromName !== '' ? $fromName : 'Valdivia Capital');

    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'From: ' . $fromDisplay . ' <' . $fromEmail . '>';
    $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $message = implode($eol, $headers) . $eol . $eol;
    $message .= '--' . $boundary . $eol;
    $message .= 'Content-Type: text/plain; charset=UTF-8' . $eol . $eol;
    $message .= ($textBody !== '' ? $textBody : strip_tags($htmlBody)) . $eol . $eol;
    $message .= '--' . $boundary . $eol;
    $message .= 'Content-Type: text/html; charset=UTF-8' . $eol . $eol;
    $message .= $htmlBody . $eol . $eol;
    $message .= '--' . $boundary . '--' . $eol;

    $smtpUrl = ($secure === 'ssl' ? 'smtps://' : 'smtp://') . $host . ':' . $port;

    $ch = curl_init();
    $payload = $message;
    $offset = 0;

    curl_setopt_array($ch, [
        CURLOPT_URL => $smtpUrl,
        CURLOPT_MAIL_FROM => '<' . $fromEmail . '>',
        CURLOPT_MAIL_RCPT => ['<' . $to . '>'],
        CURLOPT_UPLOAD => true,
        CURLOPT_READFUNCTION => function($curl, $fd, $length) use (&$payload, &$offset) {
            $chunk = substr($payload, $offset, $length);
            $offset += strlen($chunk);
            return $chunk;
        },
        CURLOPT_INFILESIZE => strlen($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    if ($user !== '') {
        curl_setopt($ch, CURLOPT_USERNAME, $user);
        curl_setopt($ch, CURLOPT_PASSWORD, $pass);
    }

    if ($secure === 'tls') {
        curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
    }

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($resp === false || $err) {
        return ['ok' => false, 'error' => 'SMTP error: ' . $err];
    }

    if ($code >= 400) {
        return ['ok' => false, 'error' => 'SMTP rechazó el correo (código ' . $code . ').'];
    }

    return ['ok' => true];
}
