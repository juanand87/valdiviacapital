-- Agregar campos adicionales para almacenar toda la información scrapeada
ALTER TABLE medios_contenido_sincronizado 
ADD COLUMN imagen_url VARCHAR(500) AFTER contenido,
ADD COLUMN autor VARCHAR(255) AFTER fecha_publicacion,
ADD COLUMN categoria VARCHAR(255) AFTER autor,
ADD COLUMN hash_contenido VARCHAR(32) AFTER url_original;

-- Índice para búsqueda rápida de duplicados por hash
ALTER TABLE medios_contenido_sincronizado 
ADD INDEX idx_hash (hash_contenido);

-- Índice para búsqueda por URL
ALTER TABLE medios_contenido_sincronizado 
ADD INDEX idx_url (url_original);
