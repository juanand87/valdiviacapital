<?php
$page_title = 'Configuración IA';
require_once '../includes/config.php';
require_once '../includes/gemini.php';

// AJAX: test de conexión con Gemini
if (isset($_POST['action']) && $_POST['action'] === 'test_gemini') {
    header('Content-Type: application/json');
    $db = getDB();
    $config = getConfigIA($db);
    if (empty($config['gemini_api_key'])) {
        echo json_encode(['ok' => false, 'msg' => 'No hay API Key configurada.']);
        exit;
    }
    $modelo = $config['gemini_modelo'] ?? 'gemini-1.5-flash';
    $payload = json_encode(['contents' => [['parts' => [['text' => 'Responde solo: OK']]]]]);
    $opts = ['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 10,
        'ignore_errors' => true
    ]];
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$config['gemini_api_key']}";
    $response = @file_get_contents($url, false, stream_context_create($opts));
    if ($response === false) {
        echo json_encode(['ok' => false, 'msg' => 'No se pudo conectar con la API de Gemini.']);
    } else {
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            echo json_encode(['ok' => false, 'msg' => $data['error']['message'] ?? 'Error desconocido']);
        } else {
            echo json_encode(['ok' => true, 'msg' => 'Conexión exitosa con Gemini ✓']);
        }
    }
    exit;
}

include 'includes/header.php';

$db = getDB();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST['config'] as $nombre => $valor) {
            $stmt = $db->prepare("
                UPDATE configuracion_ia 
                SET valor = :valor 
                WHERE nombre = :nombre
            ");
            $stmt->execute([
                ':valor' => $valor,
                ':nombre' => $nombre
            ]);
        }
        $mensaje_exito = "Configuración guardada correctamente";
    } catch (PDOException $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// Obtener configuración actual
$stmt = $db->query("SELECT * FROM configuracion_ia ORDER BY id");
$config = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-robot"></i> Configuración de IA</h1>
        <p>Configura la integración con Google Gemini para redacción automática</p>
    </div>
    <a href="medios-conectados.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

<?php if (isset($mensaje_exito)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
    </div>
<?php endif; ?>

<?php if (isset($mensaje_error)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($mensaje_error); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Configuración de Google Gemini</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php foreach ($config as $item): ?>
                <div class="form-group">
                    <label for="config_<?php echo $item['nombre']; ?>">
                        <?php 
                        $labels = [
                            'gemini_api_key' => 'API Key de Google Gemini',
                            'gemini_prompt_base' => 'Prompt Base',
                            'gemini_modelo' => 'Modelo',
                            'gemini_temperatura' => 'Temperatura',
                            'gemini_max_tokens' => 'Máximo de Tokens'
                        ];
                        echo $labels[$item['nombre']] ?? $item['nombre'];
                        ?>
                    </label>
                    
                    <?php if ($item['nombre'] === 'gemini_prompt_base'): ?>
                        <textarea 
                            id="config_<?php echo $item['nombre']; ?>" 
                            name="config[<?php echo $item['nombre']; ?>]" 
                            class="form-control" 
                            rows="10"
                            style="font-family: monospace; font-size: 13px;"
                        ><?php echo htmlspecialchars($item['valor']); ?></textarea>
                    <?php elseif ($item['nombre'] === 'gemini_modelo'): ?>
                        <select 
                            id="config_<?php echo $item['nombre']; ?>" 
                            name="config[<?php echo $item['nombre']; ?>]" 
                            class="form-control"
                        >
                            <option value="gemini-2.5-flash" <?php echo $item['valor'] === 'gemini-2.5-flash' ? 'selected' : ''; ?>>
                                Gemini 2.5 Flash (Recomendado)
                            </option>
                            <option value="gemini-2.5-pro" <?php echo $item['valor'] === 'gemini-2.5-pro' ? 'selected' : ''; ?>>
                                Gemini 2.5 Pro (Mayor calidad)
                            </option>
                            <option value="gemini-2.0-flash" <?php echo $item['valor'] === 'gemini-2.0-flash' ? 'selected' : ''; ?>>
                                Gemini 2.0 Flash
                            </option>
                            <option value="gemini-1.5-flash" <?php echo $item['valor'] === 'gemini-1.5-flash' ? 'selected' : ''; ?>>
                                Gemini 1.5 Flash
                            </option>
                        </select>
                    <?php elseif ($item['nombre'] === 'gemini_api_key'): ?>
                        <input 
                            type="password" 
                            id="config_<?php echo $item['nombre']; ?>" 
                            name="config[<?php echo $item['nombre']; ?>]" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($item['valor']); ?>"
                        >
                    <?php else: ?>
                        <input 
                            type="text" 
                            id="config_<?php echo $item['nombre']; ?>" 
                            name="config[<?php echo $item['nombre']; ?>]" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($item['valor']); ?>"
                        >
                    <?php endif; ?>
                    
                    <small class="form-text"><?php echo htmlspecialchars($item['descripcion']); ?></small>
                </div>
            <?php endforeach; ?>
            
            <div class="form-actions" style="display:flex; gap:12px; align-items:center;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Configuración
                </button>
                <button type="button" class="btn btn-secondary" id="btn-test-gemini" onclick="testGemini()">
                    <i class="fas fa-plug"></i> Probar conexión
                </button>
                <span id="test-gemini-result" style="font-weight:600;"></span>
            </div>
        </form>

<script>
function testGemini() {
    const btn = document.getElementById('btn-test-gemini');
    const result = document.getElementById('test-gemini-result');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probando...';
    result.textContent = '';
    result.style.color = '';
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=test_gemini'
    })
    .then(r => r.json())
    .then(data => {
        result.textContent = data.msg;
        result.style.color = data.ok ? '#16a34a' : '#dc2626';
    })
    .catch(() => {
        result.textContent = 'Error al contactar el servidor.';
        result.style.color = '#dc2626';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug"></i> Probar conexión';
    });
}
</script>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2><i class="fas fa-info-circle"></i> Instrucciones</h2>
    </div>
    <div class="card-body">
        <h3 style="margin-bottom: 15px;">Cómo obtener tu API Key de Google Gemini:</h3>
        <ol style="line-height: 2;">
            <li>Ve a <a href="https://makersuite.google.com/app/apikey" target="_blank" style="color: #3498db;">Google AI Studio</a></li>
            <li>Inicia sesión con tu cuenta de Google</li>
            <li>Haz clic en "Get API key" o "Crear clave de API"</li>
            <li>Copia la API Key generada</li>
            <li>Pégala en el campo "API Key" arriba</li>
        </ol>
        
        <h3 style="margin: 25px 0 15px;">Variables disponibles en el Prompt:</h3>
        <ul style="line-height: 2;">
            <li><code>{titulo}</code> - Título de la noticia</li>
            <li><code>{autor}</code> - Autor de la noticia</li>
            <li><code>{categoria}</code> - Categoría de la noticia</li>
            <li><code>{contenido}</code> - Contenido extraído de la noticia</li>
        </ul>
        
        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
            <strong><i class="fas fa-lightbulb"></i> Consejo:</strong>
            <p style="margin: 8px 0 0 0;">
                Ajusta la <strong>temperatura</strong> para controlar la creatividad: 
                <br>• 0.0-0.3: Muy conservador y objetivo
                <br>• 0.4-0.7: Equilibrado (recomendado para periodismo)
                <br>• 0.8-1.0: Más creativo y variado
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
