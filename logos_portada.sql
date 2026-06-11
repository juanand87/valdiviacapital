-- ================================================
-- TABLA PARA LOGOS DE PORTADA (CARRUSEL)
-- ================================================

CREATE TABLE IF NOT EXISTS logos_portada (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    imagen_url VARCHAR(500) NOT NULL,
    url_destino VARCHAR(500) DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    orden INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_activo (activo),
    INDEX idx_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar logos de ejemplo
INSERT INTO logos_portada (nombre, imagen_url, url_destino, activo, orden) VALUES
('Logo Ejemplo 1', 'https://via.placeholder.com/200x130/3182ce/ffffff?text=Logo+1', 'https://ejemplo1.cl', 1, 1),
('Logo Ejemplo 2', 'https://via.placeholder.com/200x130/805ad5/ffffff?text=Logo+2', 'https://ejemplo2.cl', 1, 2),
('Logo Ejemplo 3', 'https://via.placeholder.com/200x130/38a169/ffffff?text=Logo+3', 'https://ejemplo3.cl', 1, 3),
('Logo Ejemplo 4', 'https://via.placeholder.com/200x130/dd6b20/ffffff?text=Logo+4', 'https://ejemplo4.cl', 1, 4),
('Logo Ejemplo 5', 'https://via.placeholder.com/200x130/e53e3e/ffffff?text=Logo+5', 'https://ejemplo5.cl', 1, 5),
('Logo Ejemplo 6', 'https://via.placeholder.com/200x130/319795/ffffff?text=Logo+6', 'https://ejemplo6.cl', 1, 6);
