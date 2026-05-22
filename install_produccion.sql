-- ============================================================
-- bd_valdivia - SQL INSTALACIÓN PRODUCCIÓN
-- Instalación limpia y completa. Ejecutar UNA sola vez.
-- ============================================================

USE bd_valdivia;

-- ============================================================
-- TABLAS BASE
-- ============================================================

CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    color VARCHAR(7) DEFAULT '#2563eb',
    icono VARCHAR(50),
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'editor', 'periodista') DEFAULT 'periodista',
    avatar VARCHAR(255),
    biografia TEXT,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS noticias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    bajada TEXT,
    contenido LONGTEXT NOT NULL,
    imagen_principal VARCHAR(255),
    imagen_caption VARCHAR(255),
    categoria_id INT NOT NULL,
    autor_id INT NOT NULL,
    destacado TINYINT(1) DEFAULT 0,
    vistas INT DEFAULT 0,
    publicado TINYINT(1) DEFAULT 1,
    fecha_publicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    FOREIGN KEY (autor_id) REFERENCES usuarios(id),
    INDEX idx_categoria (categoria_id),
    INDEX idx_publicado (publicado),
    INDEX idx_destacado (destacado),
    INDEX idx_fecha (fecha_publicacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    noticia_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    comentario TEXT NOT NULL,
    aprobado TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE,
    INDEX idx_noticia (noticia_id),
    INDEX idx_aprobado (aprobado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS noticias_tags (
    noticia_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (noticia_id, tag_id),
    FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLAS MEDIOS CONECTADOS
-- ============================================================

CREATE TABLE IF NOT EXISTS medios_conectados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    tipo ENUM('diario_online', 'facebook', 'instagram', 'facebook_scraping') NOT NULL,
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

CREATE TABLE IF NOT EXISTS medios_diarios_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medio_id INT NOT NULL,
    selector_titulo VARCHAR(255),
    selector_link VARCHAR(255),
    selector_contenido VARCHAR(255),
    selector_imagen VARCHAR(255),
    selector_fecha VARCHAR(255),
    selector_autor VARCHAR(255),
    selector_categoria VARCHAR(255),
    usa_api TINYINT(1) DEFAULT 0,
    api_endpoint VARCHAR(500),
    api_key VARCHAR(255),
    frecuencia_sincronizacion INT DEFAULT 60 COMMENT 'Minutos entre sincronizaciones',
    cantidad_noticias INT DEFAULT 10,
    FOREIGN KEY (medio_id) REFERENCES medios_conectados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS medios_contenido_sincronizado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medio_id INT NOT NULL,
    contenido_id_externo VARCHAR(255),
    noticia_id INT,
    titulo VARCHAR(500),
    contenido LONGTEXT,
    imagen_url VARCHAR(500),
    url_original VARCHAR(500),
    hash_contenido VARCHAR(32),
    fecha_publicacion DATETIME,
    autor VARCHAR(255),
    categoria VARCHAR(255),
    estado ENUM('pendiente', 'procesado', 'publicado', 'error') DEFAULT 'pendiente',
    error_mensaje TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medio_id) REFERENCES medios_conectados(id) ON DELETE CASCADE,
    FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE SET NULL,
    INDEX idx_medio_estado (medio_id, estado),
    INDEX idx_contenido_externo (contenido_id_externo),
    INDEX idx_hash (hash_contenido),
    INDEX idx_url (url_original)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA CONFIGURACION IA
-- ============================================================

CREATE TABLE IF NOT EXISTS configuracion_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATOS INICIALES
-- ============================================================

INSERT IGNORE INTO categorias (nombre, slug, descripcion, color, icono, orden) VALUES
('Regional',       'regional',       'Noticias de la región',          '#059669', 'fa-map-marked-alt',  1),
('Política',       'politica',       'Política local y nacional',       '#2563eb', 'fa-landmark',        2),
('Economía',       'economia',       'Economía y negocios regionales',  '#f59e0b', 'fa-chart-line',      3),
('Deportes',       'deportes',       'Deportes locales y nacionales',   '#dc2626', 'fa-futbol',          4),
('Cultura',        'cultura',        'Cultura, arte y espectáculos',    '#8b5cf6', 'fa-palette',         5),
('Salud',          'salud',          'Salud y bienestar',               '#06b6d4', 'fa-heartbeat',       6),
('Educación',      'educacion',      'Educación y universidad',         '#ec4899', 'fa-graduation-cap',  7),
('Turismo',        'turismo',        'Turismo y viajes en la región',   '#10b981', 'fa-suitcase',        8),
('Medio Ambiente', 'medio-ambiente', 'Medio ambiente y sostenibilidad', '#10b981', 'fa-leaf',            9),
('Tecnología',     'tecnologia',     'Tecnología e innovación',         '#6366f1', 'fa-microchip',      10);

-- Usuario admin
INSERT IGNORE INTO usuarios (nombre, email, password, rol, activo) VALUES
('Juan Fica', 'juanand87@gmail.com', '$2y$10$uvMjuTAsaYAQCpxFNPgyEunppkZz..Pg066mTZmMohZlkUxWG2GIK', 'admin', 1);

-- Configuración IA por defecto
INSERT IGNORE INTO configuracion_ia (nombre, valor, descripcion) VALUES
('gemini_api_key',      '', 'API Key de Google Gemini para redacción con IA'),
('jina_api_key',        '', 'API Key opcional de Jina AI Reader (r.jina.ai)'),
('gemini_modelo',       'gemini-1.5-flash', 'Modelo de Gemini a utilizar'),
('gemini_temperatura',  '0.7', 'Temperatura para la generación (0.0 a 1.0)'),
('gemini_max_tokens',   '2000', 'Máximo de tokens a generar en la respuesta'),
('scraping_provider_diarios',  'direct', 'Proveedor de extracción para diarios: direct | jina | gemini'),
('scraping_provider_facebook', 'direct', 'Proveedor de extracción para Facebook: direct | jina | gemini'),
('gemini_prompt_base',  'Eres un periodista profesional. A partir de la siguiente información extraída de una noticia, escribe un artículo periodístico completo, profesional y bien redactado. Mantén la objetividad y el estilo periodístico. La información es:\n\nTítulo: {titulo}\nAutor: {autor}\nCategoría: {categoria}\nContenido original: {contenido}\n\nEscribe el artículo completo con introducción, desarrollo y conclusión. Mantén un tono profesional y objetivo.', 'Prompt base para generar redacciones con IA');

SELECT 'Instalación completada exitosamente.' AS resultado;

