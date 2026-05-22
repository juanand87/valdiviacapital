-- Tabla para configuración de IA
CREATE TABLE IF NOT EXISTS configuracion_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuraciones por defecto (idempotente)
INSERT IGNORE INTO configuracion_ia (nombre, valor, descripcion) VALUES
('gemini_api_key', '', 'API Key de Google Gemini para redacción con IA'),
('redaccion_provider', 'gemini', 'Proveedor para redacción IA: gemini | jina | copilot'),
('jina_redaccion_modelo', 'jina-deepsearch-v1', 'Modelo para redacción con Jina'),
('jina_redaccion_api_url', 'https://api.jina.ai/v1/chat/completions', 'Endpoint API para redacción con Jina'),
('copilot_api_key', '', 'API Key para GitHub Copilot (chat completions)'),
('copilot_modelo', 'auto', 'Modelo para GitHub Copilot (auto o claude-sonnet-4-5)'),
('copilot_api_url', 'https://models.inference.ai.azure.com/chat/completions', 'Endpoint API para GitHub Copilot (GitHub Models)'),
('jina_api_key', '', 'API Key opcional de Jina AI Reader (r.jina.ai)'),
('gemini_prompt_base', 'Eres un periodista profesional. A partir de la siguiente información extraída de una noticia, escribe un artículo periodístico completo, profesional y bien redactado. Mantén la objetividad y el estilo periodístico. La información es:\n\nTítulo: {titulo}\nAutor: {autor}\nCategoría: {categoria}\nContenido original: {contenido}\n\nEscribe el artículo completo con introducción, desarrollo y conclusión. Mantén un tono profesional y objetivo.', 'Prompt base para generar redacciones con IA. Usa {titulo}, {autor}, {categoria}, {contenido} como variables'),
('gemini_modelo', 'gemini-1.5-flash', 'Modelo de Gemini a utilizar (gemini-1.5-flash o gemini-1.5-pro)'),
('gemini_temperatura', '0.7', 'Temperatura para la generación (0.0 a 1.0). Mayor = más creativo'),
('gemini_max_tokens', '2000', 'Máximo de tokens a generar en la respuesta'),
('scraping_provider_diarios', 'direct', 'Proveedor de extracción para diarios: direct | jina | gemini'),
('scraping_provider_facebook', 'direct', 'Proveedor de extracción para Facebook: direct | jina | gemini');
