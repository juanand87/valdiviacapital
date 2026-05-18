-- ============================================================
-- Scraping Instagram (perfiles públicos)
-- ============================================================

CREATE TABLE IF NOT EXISTS ig_scraping_perfiles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(60) NOT NULL,
    nombre          VARCHAR(140) NULL,
    activo          TINYINT(1) NOT NULL DEFAULT 1,
    ultima_revision DATETIME NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ig_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ig_scraping_posts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    perfil_id   INT UNSIGNED NOT NULL,
    shortcode   VARCHAR(30) NOT NULL,
    tipo        ENUM('image','video','carousel') NOT NULL DEFAULT 'image',
    url_post    VARCHAR(500) NOT NULL,
    imagen_url  TEXT NULL,
    caption     TEXT NULL,
    likes       INT UNSIGNED NOT NULL DEFAULT 0,
    comentarios INT UNSIGNED NOT NULL DEFAULT 0,
    fecha_post  DATETIME NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ig_shortcode (shortcode),
    INDEX idx_ig_perfil_fecha (perfil_id, fecha_post DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
