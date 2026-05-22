-- Tabla para almacenar los medios conectados
CREATE TABLE IF NOT EXISTS medios_conectados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    tipo ENUM('diario_online', 'facebook', 'instagram') NOT NULL,
    url VARCHAR(500) NOT NULL,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    configuracion JSON,
    ultima_sincronizacion DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para configuración de scrapping de diarios online
CREATE TABLE IF NOT EXISTS medios_diarios_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medio_id INT NOT NULL,
    selector_titulo VARCHAR(255),
    selector_contenido VARCHAR(255),
    selector_imagen VARCHAR(255),
    selector_fecha VARCHAR(255),
    selector_autor VARCHAR(255),
    selector_categoria VARCHAR(255),
    usa_api TINYINT(1) DEFAULT 0,
    api_endpoint VARCHAR(500),
    api_key VARCHAR(255),
    frecuencia_sincronizacion INT DEFAULT 60 COMMENT 'Minutos entre sincronizaciones',
    FOREIGN KEY (medio_id) REFERENCES medios_conectados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para configuración de Facebook
CREATE TABLE IF NOT EXISTS medios_facebook_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medio_id INT NOT NULL,
    page_id VARCHAR(255),
    access_token TEXT,
    app_id VARCHAR(255),
    app_secret VARCHAR(255),
    sincronizar_posts TINYINT(1) DEFAULT 1,
    sincronizar_comentarios TINYINT(1) DEFAULT 1,
    frecuencia_sincronizacion INT DEFAULT 30 COMMENT 'Minutos entre sincronizaciones',
    FOREIGN KEY (medio_id) REFERENCES medios_conectados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para configuración de Instagram
CREATE TABLE IF NOT EXISTS medios_instagram_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medio_id INT NOT NULL,
    username VARCHAR(255),
    user_id VARCHAR(255),
    access_token TEXT,
    sincronizar_posts TINYINT(1) DEFAULT 1,
    sincronizar_stories TINYINT(1) DEFAULT 0,
    frecuencia_sincronizacion INT DEFAULT 30 COMMENT 'Minutos entre sincronizaciones',
    FOREIGN KEY (medio_id) REFERENCES medios_conectados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para registro de contenido sincronizado
CREATE TABLE IF NOT EXISTS medios_contenido_sincronizado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medio_id INT NOT NULL,
    contenido_id_externo VARCHAR(255),
    noticia_id INT,
    titulo VARCHAR(500),
    contenido LONGTEXT,
    url_original VARCHAR(500),
    fecha_publicacion DATETIME,
    estado ENUM('pendiente', 'procesado', 'publicado', 'error') DEFAULT 'pendiente',
    error_mensaje TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medio_id) REFERENCES medios_conectados(id) ON DELETE CASCADE,
    FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE SET NULL,
    INDEX idx_medio_estado (medio_id, estado),
    INDEX idx_contenido_externo (contenido_id_externo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
