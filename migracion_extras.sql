-- Migración: Tablas para reacciones y vistas diarias
-- Ejecutar en producción (bd_valdivia)

CREATE TABLE IF NOT EXISTS reacciones (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    noticia_id INT UNSIGNED NOT NULL,
    tipo       ENUM('me_gusta','me_encanta','sorpresa') NOT NULL,
    ip_hash    VARCHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_noticia_ip (noticia_id, ip_hash),
    INDEX idx_noticia (noticia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vistas_diarias (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    noticia_id INT UNSIGNED NOT NULL,
    fecha      DATE NOT NULL,
    vistas     INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uk_noticia_fecha (noticia_id, fecha),
    INDEX idx_noticia (noticia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS banners (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo      VARCHAR(100) NOT NULL,
    imagen_url  VARCHAR(500) NOT NULL,
    url_destino VARCHAR(500) NOT NULL,
    posicion    ENUM('leaderboard','billboard','sidebar','in_article') NOT NULL,
    activo      TINYINT(1) NOT NULL DEFAULT 1,
    fecha_inicio DATE NULL,
    fecha_fin    DATE NULL,
    orden       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    clics       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_posicion_activo (posicion, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS medios (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_original  VARCHAR(255) NOT NULL,
    nombre_archivo   VARCHAR(255) NOT NULL,
    ruta             VARCHAR(500) NOT NULL,
    tipo_mime        VARCHAR(100) NOT NULL,
    tamano           INT UNSIGNED NOT NULL DEFAULT 0,
    ancho            SMALLINT UNSIGNED NULL,
    alto             SMALLINT UNSIGNED NULL,
    autor_id         INT UNSIGNED NULL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo_mime),
    INDEX idx_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Las 12 comunas de la Región de Los Ríos
CREATE TABLE IF NOT EXISTS comunas (
    id     TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    slug   VARCHAR(60) NOT NULL,
    UNIQUE KEY uk_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO comunas (id, nombre, slug) VALUES
(1,  'Valdivia',    'valdivia'),
(2,  'Corral',      'corral'),
(3,  'Futrono',     'futrono'),
(4,  'La Unión',    'la-union'),
(5,  'Lago Ranco',  'lago-ranco'),
(6,  'Lanco',       'lanco'),
(7,  'Los Lagos',   'los-lagos'),
(8,  'Máfil',       'mafil'),
(9,  'Mariquina',   'mariquina'),
(10, 'Paillaco',    'paillaco'),
(11, 'Panguipulli', 'panguipulli'),
(12, 'Río Bueno',   'rio-bueno');

-- Relación muchos-a-muchos: noticias ↔ comunas
CREATE TABLE IF NOT EXISTS noticias_comunas (
    noticia_id INT UNSIGNED NOT NULL,
    comuna_id  TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (noticia_id, comuna_id),
    INDEX idx_comuna (comuna_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sección Multimedia: videos de YouTube y Facebook
CREATE TABLE IF NOT EXISTS videos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo       VARCHAR(255) NOT NULL,
    url          VARCHAR(500) NOT NULL,
    tipo         ENUM('youtube','facebook') NOT NULL DEFAULT 'youtube',
    es_reel      TINYINT(1) NOT NULL DEFAULT 0,
    descripcion  TEXT NULL,
    categoria_id INT UNSIGNED NULL,
    activo       TINYINT(1) NOT NULL DEFAULT 1,
    orden        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activo_orden (activo, orden),
    INDEX idx_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relación muchos-a-muchos: videos ↔ comunas
CREATE TABLE IF NOT EXISTS videos_comunas (
    video_id  INT UNSIGNED NOT NULL,
    comuna_id TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (video_id, comuna_id),
    INDEX idx_comuna (comuna_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Galerías de video (agrupadores)
CREATE TABLE IF NOT EXISTS galerias_video (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo         VARCHAR(255) NOT NULL,
    slug           VARCHAR(255) NOT NULL,
    descripcion    TEXT NULL,
    imagen_portada VARCHAR(500) NULL,
    categoria_id   INT UNSIGNED NULL,
    activo         TINYINT(1) NOT NULL DEFAULT 1,
    destacada      TINYINT(1) NOT NULL DEFAULT 0,
    orden          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug),
    INDEX idx_activo_destacada (activo, destacada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Videos dentro de cada galería (con orden propio)
CREATE TABLE IF NOT EXISTS galerias_video_items (
    galeria_id INT UNSIGNED NOT NULL,
    video_id   INT UNSIGNED NOT NULL,
    orden      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (galeria_id, video_id),
    INDEX idx_video (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categorias adicionales por noticia (N:M)
CREATE TABLE IF NOT EXISTS noticias_categorias (
    noticia_id   INT UNSIGNED NOT NULL,
    categoria_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (noticia_id, categoria_id),
    INDEX idx_ncat_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Push Notifications (Phase 19)
-- ============================================================

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint   TEXT NOT NULL,
    p256dh     TEXT NOT NULL,
    auth       TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_endpoint (endpoint(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS push_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo     VARCHAR(255) NOT NULL,
    mensaje    VARCHAR(500) NOT NULL,
    url        VARCHAR(500) NOT NULL DEFAULT '/',
    enviado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Eventos (Phase 22)
-- ============================================================

CREATE TABLE IF NOT EXISTS eventos (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo        VARCHAR(255) NOT NULL,
    slug          VARCHAR(255) NOT NULL,
    descripcion   TEXT NULL,
    fecha_inicio  DATETIME NOT NULL,
    fecha_fin     DATETIME NULL,
    lugar         VARCHAR(255) NOT NULL,
    direccion     VARCHAR(300) NULL,
    comuna_id     TINYINT UNSIGNED NULL,
    categoria     VARCHAR(80) NOT NULL DEFAULT 'General',
    imagen_url    VARCHAR(500) NULL,
    url_externo   VARCHAR(500) NULL,
    organizador   VARCHAR(150) NULL,
    gratuito      TINYINT(1) NOT NULL DEFAULT 1,
    precio        VARCHAR(100) NULL,
    destacado     TINYINT(1) NOT NULL DEFAULT 0,
    activo        TINYINT(1) NOT NULL DEFAULT 1,
    autor_id      INT UNSIGNED NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_evento_slug (slug),
    INDEX idx_evento_fecha_activo (activo, fecha_inicio),
    INDEX idx_evento_comuna (comuna_id),
    INDEX idx_evento_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
