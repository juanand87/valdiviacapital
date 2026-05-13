-- ============================================================
-- Reporteros VC
-- ============================================================

CREATE TABLE IF NOT EXISTS reporteros (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombres           VARCHAR(120) NOT NULL,
    apellidos         VARCHAR(120) NOT NULL,
    email             VARCHAR(180) NOT NULL,
    password          VARCHAR(255) NOT NULL,
    direccion         VARCHAR(255) NOT NULL,
    telefono          VARCHAR(40) NOT NULL,
    rut               VARCHAR(20) NOT NULL,
    activo            TINYINT(1) NOT NULL DEFAULT 1,
    email_verificado  TINYINT(1) NOT NULL DEFAULT 0,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login        DATETIME NULL,
    UNIQUE KEY uk_reportero_email (email),
    UNIQUE KEY uk_reportero_rut (rut),
    INDEX idx_reportero_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reportero_noticias (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reportero_id         INT UNSIGNED NOT NULL,
    titulo               VARCHAR(255) NOT NULL,
    slug_sugerido        VARCHAR(255) NULL,
    bajada               TEXT NULL,
    contenido            LONGTEXT NOT NULL,
    imagen_principal     VARCHAR(500) NULL,
    admin_notas          TEXT NULL,
    motivo_rechazo       TEXT NULL,
    estado               ENUM('borrador','pendiente','en_revision','requiere_correccion','aprobado','rechazado') NOT NULL DEFAULT 'borrador',
    noticia_publicada_id INT UNSIGNED NULL,
    revisado_por         INT UNSIGNED NULL,
    fecha_envio          DATETIME NULL,
    fecha_revision       DATETIME NULL,
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reportero_estado (reportero_id, estado),
    INDEX idx_estado_revision (estado, fecha_envio),
    INDEX idx_publicada (noticia_publicada_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reportero_noticias_comunas (
    reportero_noticia_id INT UNSIGNED NOT NULL,
    comuna_id            TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (reportero_noticia_id, comuna_id),
    INDEX idx_reportero_noticia_comuna (comuna_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;