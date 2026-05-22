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

// AJAX: test de conexión con GitHub Copilot
if (isset($_POST['action']) && $_POST['action'] === 'test_copilot') {
    header('Content-Type: application/json');
    $db = getDB();
    $config = getConfigIA($db);

    if (empty($config['copilot_api_key'])) {
        echo json_encode(['ok' => false, 'msg' => 'No hay API Key de GitHub Copilot configurada.']);
        exit;
    }

    $apiUrl = trim((string)($config['copilot_api_url'] ?? 'https://models.inference.ai.azure.com/chat/completions'));
    if ($apiUrl === '') {
        $apiUrl = 'https://models.inference.ai.azure.com/chat/completions';
    }

    $modelo = trim((string)($config['copilot_modelo'] ?? 'auto'));
    if ($modelo === '') {
        $modelo = 'auto';
    }
    if (in_array(strtolower($modelo), ['claude-sonnet-4.6', 'claude-sonnet-4-6', 'claude-sonnet-4-5', 'claude-sonnet-4.5'], true)) {
        $modelo = 'auto';
    }

    $payload = json_encode([
        'model' => $modelo,
        'messages' => [
            ['role' => 'user', 'content' => 'Responde solo: OK']
        ],
        'temperature' => 0,
        'max_tokens' => 16
    ]);

    $opts = ['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$config['copilot_api_key']}\r\nAccept: application/json\r\n",
        'content' => $payload,
        'timeout' => 12,
        'ignore_errors' => true
    ]];

    $response = @file_get_contents($apiUrl, false, stream_context_create($opts));
    if ($response === false) {
        echo json_encode(['ok' => false, 'msg' => 'No se pudo conectar con la API de GitHub Copilot.']);
    } else {
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            $msg = is_array($data['error']) ? ($data['error']['message'] ?? 'Error desconocido') : (string)$data['error'];
            echo json_encode(['ok' => false, 'msg' => $msg]);
        } else {
            echo json_encode(['ok' => true, 'msg' => 'Conexión exitosa con GitHub Copilot ✓']);
        }
    }
    exit;
}

// AJAX: test de conexión con Jina (redacción)
if (isset($_POST['action']) && $_POST['action'] === 'test_jina') {
    header('Content-Type: application/json');
    $db = getDB();
    $config = getConfigIA($db);

    if (empty($config['jina_api_key'])) {
        echo json_encode(['ok' => false, 'msg' => 'No hay API Key de Jina configurada.']);
        exit;
    }

    $apiUrl = trim((string)($config['jina_redaccion_api_url'] ?? 'https://api.jina.ai/v1/chat/completions'));
    if ($apiUrl === '') {
        $apiUrl = 'https://api.jina.ai/v1/chat/completions';
    }

    $modelo = trim((string)($config['jina_redaccion_modelo'] ?? 'jina-deepsearch-v1'));
    if ($modelo === '') {
        $modelo = 'jina-deepsearch-v1';
    }

    $payload = json_encode([
        'model' => $modelo,
        'messages' => [
            ['role' => 'user', 'content' => 'Responde solo: OK']
        ],
        'temperature' => 0,
        'max_tokens' => 16
    ]);

    $opts = ['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$config['jina_api_key']}\r\nAccept: application/json\r\n",
        'content' => $payload,
        'timeout' => 12,
        'ignore_errors' => true
    ]];

    $response = @file_get_contents($apiUrl, false, stream_context_create($opts));
    if ($response === false) {
        echo json_encode(['ok' => false, 'msg' => 'No se pudo conectar con la API de Jina.']);
    } else {
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            $msg = is_array($data['error']) ? ($data['error']['message'] ?? 'Error desconocido') : (string)$data['error'];
            echo json_encode(['ok' => false, 'msg' => $msg]);
        } else {
            echo json_encode(['ok' => true, 'msg' => 'Conexión exitosa con Jina ✓']);
        }
    }
    exit;
}

include 'includes/header.php';

$db = getDB();

// Asegurar nuevas claves de configuración para scraping híbrido
$db->exec("INSERT IGNORE INTO configuracion_ia (nombre, valor, descripcion) VALUES
    ('jina_api_key', '', 'API Key opcional de Jina AI Reader (r.jina.ai)'),
    ('jina_redaccion_modelo', 'jina-deepsearch-v1', 'Modelo para redacción con Jina'),
    ('jina_redaccion_api_url', 'https://api.jina.ai/v1/chat/completions', 'Endpoint API para redacción con Jina'),
    ('redaccion_provider', 'gemini', 'Proveedor para redacción IA: gemini | jina | copilot'),
    ('copilot_api_key', '', 'API Key para GitHub Copilot (chat completions)'),
    ('copilot_modelo', 'auto', 'Modelo para GitHub Copilot (auto o gpt-4o-mini)'),
    ('copilot_api_url', 'https://models.inference.ai.azure.com/chat/completions', 'Endpoint API para GitHub Copilot (GitHub Models)'),
    ('scraping_provider_diarios', 'direct', 'Proveedor de extracción para diarios: direct | jina | gemini'),
    ('scraping_provider_facebook', 'direct', 'Proveedor de extracción para Facebook: direct | jina | gemini')
");

// Migrar URL antigua de GitHub Copilot si aún existe
$db->exec("UPDATE configuracion_ia SET valor = 'https://models.inference.ai.azure.com/chat/completions' WHERE nombre = 'copilot_api_url' AND valor = 'https://api.githubcopilot.com/chat/completions'");

// Migrar modelos antiguos de GitHub Copilot si aún existen
$db->exec("UPDATE configuracion_ia SET valor = 'auto' WHERE nombre = 'copilot_modelo' AND LOWER(TRIM(valor)) IN ('claude-sonnet-4.6', 'claude-sonnet-4-6', 'claude-sonnet-4-5', 'claude-sonnet-4.5')");

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
        <p>Configura la integración de redacción automática con Gemini, Jina o GitHub Copilot</p>
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
                            'redaccion_provider' => 'Proveedor de redacción IA',
                            'jina_redaccion_modelo' => 'Modelo de Jina (redacción)',
                            'jina_redaccion_api_url' => 'URL API de Jina (redacción)',
                            'copilot_api_key' => 'API Key de GitHub Copilot',
                            'copilot_modelo' => 'Modelo de GitHub Copilot',
                            'copilot_api_url' => 'URL API de GitHub Copilot',
                            'jina_api_key' => 'API Key de Jina AI (opcional)',
                            'gemini_prompt_base' => 'Prompt Base',
                            'gemini_modelo' => 'Modelo',
                            'gemini_temperatura' => 'Temperatura',
                            'gemini_max_tokens' => 'Máximo de Tokens',
                            'scraping_provider_diarios' => 'Proveedor de Scraping para Diarios',
                            'scraping_provider_facebook' => 'Proveedor de Scraping para Facebook'
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
                    <?php elseif ($item['nombre'] === 'redaccion_provider'): ?>
                        <select 
                            id="config_<?php echo $item['nombre']; ?>" 
                            name="config[<?php echo $item['nombre']; ?>]" 
                            class="form-control"
                        >
                            <option value="gemini" <?php echo $item['valor'] === 'gemini' ? 'selected' : ''; ?>>
                                Google Gemini
                            </option>
                            <option value="jina" <?php echo $item['valor'] === 'jina' ? 'selected' : ''; ?>>
                                Jina
                            </option>
                            <option value="copilot" <?php echo $item['valor'] === 'copilot' ? 'selected' : ''; ?>>
                                GitHub Copilot
                            </option>
                        </select>
                    <?php elseif ($item['nombre'] === 'jina_redaccion_modelo'): ?>
                        <select 
                            id="config_<?php echo $item['nombre']; ?>" 
                            name="config[<?php echo $item['nombre']; ?>]" 
                            class="form-control"
                        >
                            <option value="jina-deepsearch-v1" <?php echo $item['valor'] === 'jina-deepsearch-v1' ? 'selected' : ''; ?>>
                                Jina DeepSearch v1 (Recomendado)
                            </option>
                            <option value="auto" <?php echo $item['valor'] === 'auto' ? 'selected' : ''; ?>>
                                Auto (si tu cuenta lo soporta)
                            </option>
                        </select>
                    <?php elseif ($item['nombre'] === 'copilot_modelo'): ?>
                        <select 
                            id="config_<?php echo $item['nombre']; ?>" 
                            name="config[<?php echo $item['nombre']; ?>]" 
                            class="form-control"
                        >
                            <option value="auto" <?php echo $item['valor'] === 'auto' ? 'selected' : ''; ?>>
                                Auto (Recomendado)
                            </option>
                            <option value="gpt-4o-mini" <?php echo $item['valor'] === 'gpt-4o-mini' ? 'selected' : ''; ?>>
                                GPT-4o mini
                            </option>
                        </select>
                    <?php elseif ($item['nombre'] === 'scraping_provider_diarios' || $item['nombre'] === 'scraping_provider_facebook'): ?>
                        <select 
                            id="config_<?php echo $item['nombre']; ?>" 
                            name="config[<?php echo $item['nombre']; ?>]" 
                            class="form-control"
                        >
                            <option value="direct" <?php echo $item['valor'] === 'direct' ? 'selected' : ''; ?>>
                                Directo (HTML local / regex)
                            </option>
                            <option value="jina" <?php echo $item['valor'] === 'jina' ? 'selected' : ''; ?>>
                                Jina AI Reader (r.jina.ai)
                            </option>
                            <option value="gemini" <?php echo $item['valor'] === 'gemini' ? 'selected' : ''; ?>>
                                Gemini (extracción estructurada por IA)
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
                    <?php elseif ($item['nombre'] === 'copilot_api_key'): ?>
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
                <button type="button" class="btn btn-secondary" id="btn-test-copilot" onclick="testCopilot()">
                    <i class="fas fa-plug"></i> Probar GitHub Copilot
                </button>
                <button type="button" class="btn btn-secondary" id="btn-test-jina" onclick="testJina()">
                    <i class="fas fa-plug"></i> Probar Jina
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

function testCopilot() {
    const btn = document.getElementById('btn-test-copilot');
    const result = document.getElementById('test-gemini-result');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probando...';
    result.textContent = '';
    result.style.color = '';
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=test_copilot'
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
        btn.innerHTML = '<i class="fas fa-plug"></i> Probar GitHub Copilot';
    });
}

function testJina() {
    const btn = document.getElementById('btn-test-jina');
    const result = document.getElementById('test-gemini-result');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probando...';
    result.textContent = '';
    result.style.color = '';
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=test_jina'
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
        btn.innerHTML = '<i class="fas fa-plug"></i> Probar Jina';
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

        <h3 style="margin: 25px 0 15px;">Configuración de GitHub Copilot:</h3>
        <ol style="line-height: 2;">
            <li>Selecciona <strong>Proveedor de redacción IA = GitHub Copilot</strong></li>
            <li>Agrega tu API Key en <strong>API Key de GitHub Copilot</strong></li>
            <li>Selecciona el modelo en <strong>Modelo de GitHub Copilot</strong>: <code>auto</code> o <code>gpt-4o-mini</code></li>
            <li>Si usas otro gateway, ajusta la <strong>URL API de GitHub Copilot</strong></li>
            <li>Usa el botón <strong>Probar GitHub Copilot</strong> para validar conexión</li>
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

        <div style="margin-top: 20px; padding: 15px; background: #e8f4fd; border-left: 4px solid #3498db; border-radius: 4px;">
            <strong><i class="fas fa-random"></i> Switch de Scraping</strong>
            <p style="margin: 8px 0 0 0;">
                Puedes elegir proveedor distinto para <strong>Diarios</strong> y <strong>Facebook</strong>:
                <br>• <strong>direct</strong>: más rápido, sin dependencias externas
                <br>• <strong>jina</strong>: extrae contenido limpio vía r.jina.ai
                <br>• <strong>gemini</strong>: extrae campos estructurados con IA
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
