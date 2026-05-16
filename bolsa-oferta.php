<?php
require_once 'includes/config.php';
require_once 'includes/maintenance.php';
require_once 'includes/bolsa.php';

if (isMaintenance()) { include 'mantenimiento.php'; exit; }
bolsaPublicadorRequerirLogin();

$db = getDB();
$publicador = bolsaPublicadorActual();
if (!$publicador) {
    bolsaPublicadorLogout();
    header('Location: bolsa-login.php');
    exit;
}

$comunas = $db->query('SELECT id, nombre FROM comunas ORDER BY nombre')->fetchAll();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$esEdicion = $id > 0;
$errores = [];
$ok = '';

$form = [
    'tipo' => 'oferta',
    'titulo' => '',
    'empresa_institucion' => $publicador['empresa_nombre'] ?: $publicador['nombre'],
    'cargo' => '',
    'rubro' => '',
    'comuna_id' => '',
    'ubicacion_texto' => '',
    'modalidad' => 'presencial',
    'jornada' => 'full_time',
    'descripcion' => '',
    'requisitos' => '',
    'salario_texto' => '',
    'email_contacto' => $publicador['email'],
    'telefono_contacto' => $publicador['telefono'] ?? '',
    'fecha_cierre' => date('Y-m-d', strtotime('+30 days')),
    'estado' => 'borrador',
];

if ($esEdicion) {
    $stmt = $db->prepare('SELECT * FROM bolsa_ofertas WHERE id = ? AND publicador_id = ? LIMIT 1');
    $stmt->execute([$id, $publicador['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: bolsa-panel.php');
        exit;
    }
    $form = array_merge($form, $row);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'guardar';

    $form['tipo'] = ($_POST['tipo'] ?? 'oferta') === 'concurso_publico' ? 'concurso_publico' : 'oferta';
    $form['titulo'] = trim($_POST['titulo'] ?? '');
    $form['empresa_institucion'] = trim($_POST['empresa_institucion'] ?? '');
    $form['cargo'] = trim($_POST['cargo'] ?? '');
    $form['rubro'] = trim($_POST['rubro'] ?? '');
    $form['comuna_id'] = (int)($_POST['comuna_id'] ?? 0);
    $form['ubicacion_texto'] = trim($_POST['ubicacion_texto'] ?? '');
    $form['modalidad'] = in_array($_POST['modalidad'] ?? '', ['presencial', 'remoto', 'hibrido'], true) ? $_POST['modalidad'] : 'presencial';
    $form['jornada'] = in_array($_POST['jornada'] ?? '', ['full_time', 'part_time', 'honorarios', 'practica', 'otro'], true) ? $_POST['jornada'] : 'full_time';
    $form['descripcion'] = trim($_POST['descripcion'] ?? '');
    $form['requisitos'] = trim($_POST['requisitos'] ?? '');
    $form['salario_texto'] = trim($_POST['salario_texto'] ?? '');
    $form['email_contacto'] = trim($_POST['email_contacto'] ?? '');
    $form['telefono_contacto'] = trim($_POST['telefono_contacto'] ?? '');
    $form['fecha_cierre'] = trim($_POST['fecha_cierre'] ?? '');

    if ($form['titulo'] === '') $errores[] = 'El título es obligatorio.';
    if ($form['empresa_institucion'] === '') $errores[] = 'Empresa o institución es obligatoria.';
    if ($form['cargo'] === '') $errores[] = 'El cargo es obligatorio.';
    if ($form['rubro'] === '') $errores[] = 'El rubro es obligatorio.';
    if ((int)$form['comuna_id'] <= 0) $errores[] = 'Debes seleccionar comuna.';
    if ($form['descripcion'] === '') $errores[] = 'La descripción es obligatoria.';
    if (!filter_var($form['email_contacto'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Correo de contacto inválido.';

    $tsCierre = strtotime($form['fecha_cierre']);
    if (!$tsCierre) {
        $errores[] = 'Fecha de cierre inválida.';
    } elseif (date('Y-m-d', $tsCierre) < date('Y-m-d')) {
        $errores[] = 'La fecha de cierre debe ser hoy o futura.';
    }

    if (!$errores) {
        $slug = bolsaGenerarSlugUnico($db, $form['titulo'], $esEdicion ? $id : null);
        $estadoDestino = $accion === 'enviar' ? 'pendiente' : 'borrador';

        if ($esEdicion) {
            $stmt = $db->prepare("UPDATE bolsa_ofertas SET
                tipo = ?, titulo = ?, slug = ?, empresa_institucion = ?, cargo = ?, rubro = ?, comuna_id = ?, ubicacion_texto = ?,
                modalidad = ?, jornada = ?, descripcion = ?, requisitos = ?, salario_texto = ?, email_contacto = ?, telefono_contacto = ?,
                fecha_cierre = ?, estado = ?, updated_at = NOW()
                WHERE id = ? AND publicador_id = ?");
            $stmt->execute([
                $form['tipo'], $form['titulo'], $slug, $form['empresa_institucion'], $form['cargo'], $form['rubro'], $form['comuna_id'], $form['ubicacion_texto'],
                $form['modalidad'], $form['jornada'], $form['descripcion'], $form['requisitos'], $form['salario_texto'], $form['email_contacto'],
                $form['telefono_contacto'], $form['fecha_cierre'], $estadoDestino, $id, $publicador['id']
            ]);
            $ok = $accion === 'enviar' ? 'Oferta enviada a revisión.' : 'Borrador actualizado.';
        } else {
            $stmt = $db->prepare("INSERT INTO bolsa_ofertas
                (publicador_id, tipo, titulo, slug, empresa_institucion, cargo, rubro, comuna_id, ubicacion_texto, modalidad, jornada,
                 descripcion, requisitos, salario_texto, email_contacto, telefono_contacto, fecha_cierre, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $publicador['id'], $form['tipo'], $form['titulo'], $slug, $form['empresa_institucion'], $form['cargo'], $form['rubro'],
                $form['comuna_id'], $form['ubicacion_texto'], $form['modalidad'], $form['jornada'], $form['descripcion'], $form['requisitos'],
                $form['salario_texto'], $form['email_contacto'], $form['telefono_contacto'], $form['fecha_cierre'], $estadoDestino
            ]);
            $id = (int)$db->lastInsertId();
            $esEdicion = true;
            $ok = $accion === 'enviar' ? 'Oferta creada y enviada a revisión.' : 'Oferta guardada en borrador.';
        }

        $stmt = $db->prepare('SELECT * FROM bolsa_ofertas WHERE id = ? AND publicador_id = ? LIMIT 1');
        $stmt->execute([$id, $publicador['id']]);
        $row = $stmt->fetch();
        if ($row) {
            $form = array_merge($form, $row);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $esEdicion ? 'Editar oferta' : 'Nueva oferta'; ?> - Bolsa de trabajo</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');</script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<section class="reportero-dashboard-shell">
    <div class="container">
        <div class="reportero-dashboard-header">
            <div>
                <span class="reportero-kicker">Bolsa de trabajo</span>
                <h1><?php echo $esEdicion ? 'Editar oferta' : 'Nueva oferta'; ?></h1>
                <p>Completa los datos del aviso. Puedes guardar borrador o enviarlo a revisión.</p>
            </div>
            <div class="reportero-dashboard-actions">
                <a href="bolsa-panel.php" class="reportero-btn secondary"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php if ($ok): ?><div class="reportero-alert success"><?php echo htmlspecialchars($ok); ?></div><?php endif; ?>
        <?php if ($errores): ?>
            <div class="reportero-alert error">
                <?php foreach ($errores as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="reportero-card">
            <form method="POST" class="reportero-form-grid">
                <div class="reportero-form-row">
                    <div>
                        <label>Tipo de aviso</label>
                        <select name="tipo">
                            <option value="oferta" <?php echo $form['tipo'] === 'oferta' ? 'selected' : ''; ?>>Oferta de trabajo</option>
                            <option value="concurso_publico" <?php echo $form['tipo'] === 'concurso_publico' ? 'selected' : ''; ?>>Concurso público</option>
                        </select>
                    </div>
                    <div>
                        <label>Fecha de cierre</label>
                        <input type="date" name="fecha_cierre" value="<?php echo htmlspecialchars($form['fecha_cierre']); ?>" required>
                    </div>
                </div>

                <div>
                    <label>Título</label>
                    <input type="text" name="titulo" value="<?php echo htmlspecialchars($form['titulo']); ?>" required>
                </div>

                <div class="reportero-form-row">
                    <div>
                        <label>Empresa o institución</label>
                        <input type="text" name="empresa_institucion" value="<?php echo htmlspecialchars($form['empresa_institucion']); ?>" required>
                    </div>
                    <div>
                        <label>Cargo</label>
                        <input type="text" name="cargo" value="<?php echo htmlspecialchars($form['cargo']); ?>" required>
                    </div>
                </div>

                <div class="reportero-form-row">
                    <div>
                        <label>Rubro</label>
                        <input type="text" name="rubro" value="<?php echo htmlspecialchars($form['rubro']); ?>" required>
                    </div>
                    <div>
                        <label>Comuna</label>
                        <select name="comuna_id" required>
                            <option value="">Seleccionar comuna</option>
                            <?php foreach ($comunas as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$form['comuna_id'] === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="reportero-form-row">
                    <div>
                        <label>Modalidad</label>
                        <select name="modalidad">
                            <option value="presencial" <?php echo $form['modalidad'] === 'presencial' ? 'selected' : ''; ?>>Presencial</option>
                            <option value="remoto" <?php echo $form['modalidad'] === 'remoto' ? 'selected' : ''; ?>>Remoto</option>
                            <option value="hibrido" <?php echo $form['modalidad'] === 'hibrido' ? 'selected' : ''; ?>>Híbrido</option>
                        </select>
                    </div>
                    <div>
                        <label>Jornada</label>
                        <select name="jornada">
                            <option value="full_time" <?php echo $form['jornada'] === 'full_time' ? 'selected' : ''; ?>>Full time</option>
                            <option value="part_time" <?php echo $form['jornada'] === 'part_time' ? 'selected' : ''; ?>>Part time</option>
                            <option value="honorarios" <?php echo $form['jornada'] === 'honorarios' ? 'selected' : ''; ?>>Honorarios</option>
                            <option value="practica" <?php echo $form['jornada'] === 'practica' ? 'selected' : ''; ?>>Práctica</option>
                            <option value="otro" <?php echo $form['jornada'] === 'otro' ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                </div>

                <div class="reportero-form-row">
                    <div>
                        <label>Ubicación (texto)</label>
                        <input type="text" name="ubicacion_texto" value="<?php echo htmlspecialchars($form['ubicacion_texto']); ?>" placeholder="Ej: Av. Picarte 1234, Valdivia">
                    </div>
                    <div>
                        <label>Renta referencial (opcional)</label>
                        <input type="text" name="salario_texto" value="<?php echo htmlspecialchars($form['salario_texto']); ?>" placeholder="Ej: $900.000 líquidos">
                    </div>
                </div>

                <div>
                    <label>Descripción de la oferta</label>
                    <textarea name="descripcion" rows="8" required><?php echo htmlspecialchars($form['descripcion']); ?></textarea>
                </div>

                <div>
                    <label>Requisitos</label>
                    <textarea name="requisitos" rows="6"><?php echo htmlspecialchars($form['requisitos']); ?></textarea>
                </div>

                <div class="reportero-form-row">
                    <div>
                        <label>Correo de contacto</label>
                        <input type="email" name="email_contacto" value="<?php echo htmlspecialchars($form['email_contacto']); ?>" required>
                    </div>
                    <div>
                        <label>Teléfono de contacto</label>
                        <input type="text" name="telefono_contacto" value="<?php echo htmlspecialchars($form['telefono_contacto']); ?>">
                    </div>
                </div>

                <div class="reportero-editor-actions">
                    <button type="submit" name="accion" value="guardar" class="reportero-btn secondary"><i class="fas fa-save"></i> Guardar borrador</button>
                    <button type="submit" name="accion" value="enviar" class="reportero-btn primary"><i class="fas fa-paper-plane"></i> Enviar a revisión</button>
                </div>
            </form>
        </div>
    </div>
</section>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
