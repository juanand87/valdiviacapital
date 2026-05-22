-- Migracion: agregar configuracion de GitHub Copilot para redaccion IA

INSERT IGNORE INTO configuracion_ia (nombre, valor, descripcion) VALUES
('redaccion_provider', 'gemini', 'Proveedor para redaccion IA: gemini | jina | copilot'),
('jina_redaccion_modelo', 'jina-deepsearch-v1', 'Modelo para redaccion con Jina'),
('jina_redaccion_api_url', 'https://api.jina.ai/v1/chat/completions', 'Endpoint API para redaccion con Jina'),
('copilot_api_key', '', 'API Key para GitHub Copilot (chat completions)'),
('copilot_modelo', 'auto', 'Modelo para GitHub Copilot (auto o claude-sonnet-4-5)'),
('copilot_api_url', 'https://models.inference.ai.azure.com/chat/completions', 'Endpoint API para GitHub Copilot (GitHub Models).');

-- Corregir URL antigua si fue guardada con el valor incorrecto
UPDATE configuracion_ia SET valor = 'https://models.inference.ai.azure.com/chat/completions' WHERE nombre = 'copilot_api_url' AND valor = 'https://api.githubcopilot.com/chat/completions';

-- Corregir modelo antiguo si fue guardado con el valor incorrecto
UPDATE configuracion_ia SET valor = 'auto' WHERE nombre = 'copilot_modelo' AND LOWER(TRIM(valor)) IN ('claude-sonnet-4.6', 'claude-sonnet-4-6');
