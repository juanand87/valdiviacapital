-- Migración: configuración de proveedor de scraping (direct / jina / gemini)

INSERT IGNORE INTO configuracion_ia (nombre, valor, descripcion) VALUES
('jina_api_key', '', 'API Key opcional de Jina AI Reader (r.jina.ai)'),
('scraping_provider_diarios', 'direct', 'Proveedor de extracción para diarios: direct | jina | gemini'),
('scraping_provider_facebook', 'direct', 'Proveedor de extracción para Facebook: direct | jina | gemini');
