-- ============================================================
-- Ticker Breaking News — Valdivia Capital
-- Ejecutar en phpMyAdmin o MySQL CLI sobre la BD valdiviacapital
-- ============================================================

-- Mensajes personalizados del ticker
CREATE TABLE IF NOT EXISTS ticker_mensajes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mensaje    VARCHAR(400) NOT NULL,
    url        VARCHAR(500) NULL COMMENT 'Enlace opcional al hacer clic en el item',
    tipo       ENUM('normal','urgente','flash') NOT NULL DEFAULT 'normal',
    activo     TINYINT(1) NOT NULL DEFAULT 1,
    orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticker_activo_orden (activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configuración global del ticker
CREATE TABLE IF NOT EXISTS ticker_config (
    nombre VARCHAR(60) NOT NULL PRIMARY KEY,
    valor  TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores por defecto (INSERT IGNORE no sobrescribe si ya existen)
INSERT IGNORE INTO ticker_config (nombre, valor) VALUES
    ('activo',            '1'),
    ('etiqueta',          'Último momento'),
    ('velocidad',         '35'),
    ('fuente',            'noticias'),
    ('cantidad_noticias', '8');
