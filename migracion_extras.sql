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
