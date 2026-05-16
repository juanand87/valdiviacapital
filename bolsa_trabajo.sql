-- ============================================================
-- Bolsa de Trabajo VC (MVP)
-- ============================================================

CREATE TABLE IF NOT EXISTS bolsa_publicadores (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(140) NOT NULL,
    email          VARCHAR(180) NOT NULL,
    password       VARCHAR(255) NOT NULL,
    telefono       VARCHAR(40) NULL,
    empresa_nombre VARCHAR(180) NULL,
    activo         TINYINT(1) NOT NULL DEFAULT 1,
    last_login     DATETIME NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bolsa_publicador_email (email),
    INDEX idx_bolsa_publicador_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bolsa_ofertas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    publicador_id       INT UNSIGNED NOT NULL,
    tipo                ENUM('oferta','concurso_publico') NOT NULL DEFAULT 'oferta',
    titulo              VARCHAR(220) NOT NULL,
    slug                VARCHAR(255) NOT NULL,
    empresa_institucion VARCHAR(180) NOT NULL,
    cargo               VARCHAR(180) NOT NULL,
    rubro               VARCHAR(120) NOT NULL,
    comuna_id           TINYINT UNSIGNED NOT NULL,
    ubicacion_texto     VARCHAR(180) NULL,
    modalidad           ENUM('presencial','remoto','hibrido') NOT NULL DEFAULT 'presencial',
    jornada             ENUM('full_time','part_time','honorarios','practica','otro') NOT NULL DEFAULT 'full_time',
    descripcion         LONGTEXT NOT NULL,
    requisitos          LONGTEXT NULL,
    salario_texto       VARCHAR(120) NULL,
    email_contacto      VARCHAR(180) NOT NULL,
    telefono_contacto   VARCHAR(40) NULL,
    fecha_cierre        DATE NOT NULL,
    estado              ENUM('borrador','pendiente','publicado','rechazado','vencido') NOT NULL DEFAULT 'borrador',
    destacado           TINYINT(1) NOT NULL DEFAULT 0,
    motivo_rechazo      TEXT NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at        DATETIME NULL,
    revisado_por        INT UNSIGNED NULL,
    revisado_at         DATETIME NULL,
    UNIQUE KEY uk_bolsa_oferta_slug (slug),
    INDEX idx_bolsa_publicador_estado (publicador_id, estado),
    INDEX idx_bolsa_estado_fecha (estado, fecha_cierre),
    INDEX idx_bolsa_comuna (comuna_id),
    INDEX idx_bolsa_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bolsa_postulaciones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    oferta_id    INT UNSIGNED NOT NULL,
    nombre       VARCHAR(140) NOT NULL,
    email        VARCHAR(180) NOT NULL,
    telefono     VARCHAR(40) NOT NULL,
    mensaje      TEXT NOT NULL,
    cv_archivo   VARCHAR(500) NOT NULL,
    estado       ENUM('nueva','revisada','contactado','descartada') NOT NULL DEFAULT 'nueva',
    ip           VARCHAR(45) NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bolsa_post_oferta_estado (oferta_id, estado),
    INDEX idx_bolsa_post_fecha (created_at),
    INDEX idx_bolsa_post_email (email),
    INDEX idx_bolsa_post_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bolsa_config_smtp (
    id          TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    host        VARCHAR(180) NULL,
    puerto      SMALLINT UNSIGNED NULL,
    usuario     VARCHAR(180) NULL,
    password    VARCHAR(255) NULL,
    cifrado     ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
    from_email  VARCHAR(180) NULL,
    from_name   VARCHAR(140) NULL,
    activo      TINYINT(1) NOT NULL DEFAULT 0,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bolsa_config (
    nombre VARCHAR(80) NOT NULL PRIMARY KEY,
    valor  TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO bolsa_config_smtp (id, activo, cifrado) VALUES (1, 0, 'tls');

INSERT IGNORE INTO bolsa_config (nombre, valor) VALUES
('max_postulaciones_diarias', '2'),
('max_cv_mb', '5'),
('cv_ext_permitidas', 'pdf,doc,docx'),
('recaptcha_site_key', ''),
('recaptcha_secret_key', '');
