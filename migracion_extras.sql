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
