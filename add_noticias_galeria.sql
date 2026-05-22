-- Crear tabla de galería de imágenes para noticias
CREATE TABLE IF NOT EXISTS noticias_galeria (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    noticia_id INT NOT NULL,
    imagen_url VARCHAR(700) NOT NULL,
    titulo VARCHAR(255) NULL,
    orden INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_noticia_orden (noticia_id, orden),
    CONSTRAINT fk_noticias_galeria_noticia
        FOREIGN KEY (noticia_id) REFERENCES noticias(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
