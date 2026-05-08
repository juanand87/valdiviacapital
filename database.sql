-- ================================================
-- BASE DE DATOS DIARIO LOS RÍOS
-- ================================================

CREATE DATABASE IF NOT EXISTS losrios CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE losrios;

-- Eliminar tablas si existen (en orden inverso por dependencias)
DROP TABLE IF EXISTS noticias_tags;
DROP TABLE IF EXISTS comentarios;
DROP TABLE IF EXISTS newsletter;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS noticias;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS categorias;

-- Tabla de Categorías
CREATE TABLE categorias (
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

-- Tabla de Usuarios/Periodistas
CREATE TABLE usuarios (
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

-- Tabla de Noticias
CREATE TABLE noticias (
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

-- Tabla de Comentarios
CREATE TABLE comentarios (
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

-- Tabla de Newsletter
CREATE TABLE newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Tags/Etiquetas
CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla relacional Noticias-Tags
CREATE TABLE noticias_tags (
    noticia_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (noticia_id, tag_id),
    FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- DATOS DE EJEMPLO
-- ================================================

-- Insertar Categorías
INSERT INTO categorias (nombre, slug, descripcion, color, icono, orden) VALUES
('Regional', 'regional', 'Noticias de la región de Los Ríos', '#059669', 'fa-map-marked-alt', 1),
('Política', 'politica', 'Política local y nacional', '#2563eb', 'fa-landmark', 2),
('Economía', 'economia', 'Economía y negocios regionales', '#f59e0b', 'fa-chart-line', 3),
('Deportes', 'deportes', 'Deportes locales y nacionales', '#dc2626', 'fa-futbol', 4),
('Cultura', 'cultura', 'Cultura, arte y espectáculos', '#8b5cf6', 'fa-palette', 5),
('Salud', 'salud', 'Salud y bienestar', '#06b6d4', 'fa-heartbeat', 6),
('Educación', 'educacion', 'Educación y universidad', '#ec4899', 'fa-graduation-cap', 7),
('Turismo', 'turismo', 'Turismo y viajes en la región', '#10b981', 'fa-suitcase', 8),
('Medio Ambiente', 'medio-ambiente', 'Medio ambiente y sostenibilidad', '#10b981', 'fa-leaf', 9),
('Tecnología', 'tecnologia', 'Tecnología e innovación', '#6366f1', 'fa-microchip', 10);

-- Insertar Usuarios/Periodistas
INSERT INTO usuarios (nombre, email, password, rol, biografia) VALUES
('Admin Sistema', 'admin@diariolosrios.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrador del sistema'),
('Daniela Montecinos', 'dmontecinos@diariolosrios.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'editor', 'Editora general con 15 años de experiencia en periodismo regional'),
('Carlos Sepúlveda', 'csepulveda@diariolosrios.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'periodista', 'Periodista especializado en temas regionales y políticos'),
('María José Henríquez', 'mhenriquez@diariolosrios.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'periodista', 'Periodista deportiva con cobertura en eventos regionales'),
('Roberto Contreras', 'rcontreras@diariolosrios.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'periodista', 'Periodista de economía y negocios');

-- Insertar Noticias de Ejemplo
INSERT INTO noticias (titulo, slug, bajada, contenido, imagen_principal, categoria_id, autor_id, destacado, vistas, fecha_publicacion) VALUES

-- Noticia 1 - Destacada
('Volcán Osorno registra actividad inusual: Expertos monitorean la situación', 
'volcan-osorno-actividad-inusual-expertos-monitorean', 
'Autoridades de SERNAGEOMIN mantienen alerta amarilla en la zona tras detectar movimientos sísmicos de baja intensidad. Especialistas señalan que la actividad es normal pero requiere supervisión constante.',
'<p>El Servicio Nacional de Geología y Minería (SERNAGEOMIN) informó esta mañana que el volcán Osorno ha presentado una actividad sísmica inusual durante las últimas 48 horas, registrando movimientos de baja intensidad que han llevado a las autoridades a mantener una alerta amarilla en la zona.</p>

<p>Según indicó el director regional de SERNAGEOMIN, el Dr. Jorge Villalobos, "los registros sísmicos indican una actividad dentro de los parámetros normales del volcán, sin embargo, dada la frecuencia de estos eventos, hemos decidido mantener un monitoreo permanente y mantener la alerta amarilla como medida preventiva".</p>

<h3>Medidas Preventivas</h3>

<p>La Oficina Nacional de Emergencia (ONEMI) regional ha activado sus protocolos de prevención, trabajando en coordinación con los municipios de Osorno, Puerto Octay y Puerto Varas. Se han realizado reuniones informativas con las comunidades aledañas al volcán para mantenerlas al tanto de la situación.</p>

<p>El alcalde de Osorno, Emilio Tapia, señaló que "queremos transmitir tranquilidad a la población. Estamos en constante comunicación con los organismos técnicos y, de momento, no hay razones para alarmarse. La actividad del volcán está siendo monitoreada las 24 horas".</p>

<h3>Turismo en la Zona</h3>

<p>A pesar de la alerta, las autoridades confirmaron que no hay restricciones para el turismo en la zona. El Centro de Ski Volcán Osorno continúa operando con normalidad, al igual que los demás atractivos turísticos del sector.</p>

<p>Los expertos recomiendan a la población mantenerse informada a través de canales oficiales y seguir las indicaciones de las autoridades en caso de que la situación requiera medidas adicionales.</p>',
'https://images.unsplash.com/photo-1580048915913-4f8f5cb481c4?w=800',
1, 2, 1, 15432, '2026-02-21 08:30:00'),

-- Noticia 2
('Municipio de Valdivia aprueba nuevo plan regulador para 2026',
'municipio-valdivia-aprueba-nuevo-plan-regulador-2026',
'El Concejo Municipal aprobó por unanimidad el nuevo plan que contempla zonas de expansión urbana y áreas verdes protegidas.',
'<p>En una histórica sesión, el Concejo Municipal de Valdivia aprobó por unanimidad el nuevo Plan Regulador Comunal que regirá el desarrollo urbano de la ciudad durante los próximos años.</p>

<p>El nuevo plan contempla la creación de tres nuevas zonas de expansión urbana en los sectores norte y oriente de la ciudad, así como la protección de importantes áreas verdes y humedales que caracterizan el patrimonio natural valdiviano.</p>

<p>La alcaldesa de Valdivia, Carla Amtmann, destacó que "este es un plan que mira hacia el futuro, que busca un desarrollo armónico de nuestra ciudad, respetando nuestro patrimonio natural y cultural, pero también dando espacio para el crecimiento que Valdivia necesita".</p>

<p>Entre los puntos más destacados del nuevo plan se encuentra la creación de un corredor verde que conectará diversos parques y áreas verdes de la ciudad, incentivos para la construcción sustentable, y restricciones más estrictas para la construcción en zonas de riesgo de inundación.</p>

<p>El plan también contempla la creación de nuevas áreas comerciales y de servicios, con especial énfasis en el desarrollo del sector poniente de la ciudad.</p>',
'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?w=600',
1, 3, 0, 8234, '2026-02-21 10:15:00'),

-- Noticia 3
('Deportes Valdivia clasifica a semifinales del torneo regional',
'deportes-valdivia-clasifica-semifinales-torneo-regional',
'El equipo valdiviano venció 3-1 a su rival en un emocionante partido disputado en el estadio municipal.',
'<p>Deportes Valdivia logró una brillante clasificación a las semifinales del Torneo Regional de Fútbol Amateur tras vencer 3-1 al Club Deportivo Osorno en un partido emocionante disputado en el estadio municipal.</p>

<p>Los goles del equipo local fueron anotados por Cristián Vargas (2) y Matías Soto, consolidando una actuación que dejó conforme al cuerpo técnico y a la hinchada valdiviana que colmó las tribunas del estadio.</p>

<p>El entrenador de Deportes Valdivia, Juan Carlos Muñoz, destacó el esfuerzo del equipo: "Los muchachos hicieron un trabajo excepcional. Sabíamos que enfrentábamos a un rival difícil, pero la preparación y el compromiso de todo el plantel nos permitió lograr esta importante victoria".</p>

<p>Con este triunfo, Deportes Valdivia enfrentará en semifinales al ganador del partido entre Unión La Unión y Río Bueno FC, que se disputará el próximo fin de semana.</p>

<p>La final del torneo está programada para el 15 de marzo en el estadio regional, con la expectativa de contar con una masiva asistencia de público.</p>',
'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=600',
4, 4, 0, 12543, '2026-02-21 11:00:00'),

-- Noticia 4
('Turismo en Los Ríos crece 35% en temporada de verano',
'turismo-los-rios-crece-35-temporada-verano',
'Hoteles y servicios turísticos reportan excelentes cifras, superando las expectativas del sector para esta temporada.',
'<p>La región de Los Ríos experimentó un notable crecimiento del 35% en la llegada de turistas durante la temporada de verano 2026, superando ampliamente las proyecciones del sector turístico regional.</p>

<p>Según datos entregados por SERNATUR Los Ríos, entre diciembre de 2025 y febrero de 2026 la región recibió más de 450.000 visitantes, cifra que representa el mejor resultado de los últimos cinco años.</p>

<p>El director regional de SERNATUR, Felipe Barra, atribuyó estos excelentes números a varios factores: "Hemos trabajado fuerte en la promoción de nuestros destinos, destacando la diversidad de atractivos que ofrece la región, desde termas y volcanes hasta nuestro patrimonio cultural y gastronómico".</p>

<p>Los destinos más visitados fueron Panguipulli, Futrono, Lago Ranco y el circuito de los siete lagos, zona que concentró el 45% de las visitas. Las reservas en hoteles y cabañas alcanzaron niveles de ocupación superiores al 85% durante enero.</p>

<p>El presidente de la Cámara de Turismo regional, destacó que "este crecimiento se traduce en más empleos y mejores oportunidades para nuestra gente. El turismo se consolida como un motor importante para la economía regional".</p>',
'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=600',
3, 5, 0, 9876, '2026-02-21 12:30:00'),

-- Noticia 5
('Festival de música tradicional mapuche reúne a miles de personas',
'festival-musica-tradicional-mapuche-reune-miles-personas',
'El evento cultural destacó la riqueza ancestral de la región con presentaciones de reconocidos artistas locales.',
'<p>El Segundo Festival de Música Tradicional Mapuche "Küme Mongen" congregó a más de 5.000 personas en el gimnasio municipal de Panguipulli, en una jornada que celebró la cultura y tradiciones del pueblo mapuche.</p>

<p>El evento, organizado por la Municipalidad de Panguipulli en conjunto con comunidades mapuche de la zona, contó con la presentación de 15 grupos musicales que deleitaron al público con instrumentos tradicionales como kultrun, trutruka, cascahuilla y trompe.</p>

<p>La lonko María Huenul, una de las organizadoras del festival, señaló que "este espacio es fundamental para mantener vivas nuestras tradiciones y compartirlas con toda la comunidad. La música es parte esencial de nuestra identidad como pueblo".</p>

<p>El festival también incluyó una feria gastronómica con platos típicos de la cocina mapuche, muestras de artesanía tradicional y talleres educativos sobre la cosmovisión mapuche.</p>

<p>El alcalde de Panguipulli destacó el éxito de la actividad y confirmó que el festival se realizará anualmente, consolidándose como uno de los eventos culturales más importantes de la región.</p>',
'https://images.unsplash.com/photo-1509062522246-3755977927d7?w=600',
5, 2, 0, 7432, '2026-02-21 14:00:00'),

-- Noticia 6
('Hospital Base de Valdivia implementa nuevo sistema de atención',
'hospital-base-valdivia-implementa-nuevo-sistema-atencion',
'Centro asistencial moderniza sus procesos para reducir tiempos de espera y mejorar la calidad de atención.',
'<p>El Hospital Base de Valdivia implementó un innovador sistema de gestión de pacientes que permitirá reducir significativamente los tiempos de espera y mejorar la calidad de atención en el principal centro asistencial de la región.</p>

<p>El nuevo sistema, desarrollado en conjunto con la Universidad Austral de Chile, integra tecnología de punta para optimizar la asignación de horas médicas, gestión de exámenes y coordinación entre diferentes especialidades.</p>

<p>El director del Hospital Base, Dr. Patricio Campos, explicó que "este sistema nos permite tener una visión integral del paciente, reduciendo duplicidad de exámenes y mejorando la coordinación entre especialidades. Estimamos que los tiempos de espera se reducirán en un 40% durante el primer año".</p>

<p>La implementación del sistema comenzó en enero en el área de especialidades ambulatorias y gradualmente se extenderá a todas las unidades del hospital durante 2026.</p>

<p>Los pacientes podrán acceder a una plataforma digital donde consultar sus horas médicas, resultados de exámenes y recibir recordatorios automáticos de sus citas.</p>',
'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=600',
6, 2, 0, 5621, '2026-02-21 15:30:00'),

-- Noticia 7
('Proyecto de conservación protegerá bosques nativos del sur',
'proyecto-conservacion-protegera-bosques-nativos-sur',
'Iniciativa público-privada busca preservar más de 15,000 hectáreas de bosque nativo en la región de Los Ríos.',
'<p>Un ambicioso proyecto de conservación busca proteger más de 15.000 hectáreas de bosque nativo en la región de Los Ríos, en una alianza público-privada que involucra a CONAF, organizaciones ambientales y empresas forestales comprometidas con la sustentabilidad.</p>

<p>El proyecto, denominado "Bosques del Sur", contempla la creación de corredores biológicos que conectarán áreas protegidas existentes, facilitando el movimiento de fauna nativa y fortaleciendo la biodiversidad regional.</p>

<p>El director regional de CONAF, Rodrigo Saavedra, destacó que "este proyecto es fundamental para preservar nuestro patrimonio natural. Los bosques nativos de la región albergan especies únicas y cumplen un rol crucial en la regulación del clima y la protección de cuencas hidrográficas".</p>

<p>La iniciativa incluye además un programa de educación ambiental dirigido a comunidades locales y establecimientos educacionales, promoviendo la importancia de la conservación.</p>

<p>Se espera que el proyecto esté completamente implementado en un plazo de tres años, con una inversión estimada de 2.500 millones de pesos.</p>',
'https://images.unsplash.com/photo-1449034446853-66c86144b0ad?w=600',
9, 3, 0, 6234, '2026-02-21 16:00:00');

-- Insertar Tags
INSERT INTO tags (nombre, slug) VALUES
('Valdivia', 'valdivia'),
('Osorno', 'osorno'),
('Volcán', 'volcan'),
('Turismo', 'turismo'),
('Cultura Mapuche', 'cultura-mapuche'),
('Deporte', 'deporte'),
('Salud', 'salud'),
('Medio Ambiente', 'medio-ambiente'),
('Desarrollo Urbano', 'desarrollo-urbano'),
('Fútbol', 'futbol');

-- Relacionar noticias con tags
INSERT INTO noticias_tags (noticia_id, tag_id) VALUES
(1, 2), (1, 3),
(2, 1), (2, 9),
(3, 1), (3, 6), (3, 10),
(4, 4),
(5, 5), (5, 1),
(6, 1), (6, 7),
(7, 8);

-- Insertar algunos comentarios de ejemplo
INSERT INTO comentarios (noticia_id, nombre, email, comentario, aprobado) VALUES
(1, 'Pedro González', 'pgonzalez@email.com', 'Excelente cobertura de la situación del volcán. Es importante mantenerse informado.', 1),
(1, 'Ana Martínez', 'amartinez@email.com', 'Gracias por mantener a la comunidad al tanto de esta situación.', 1),
(3, 'Luis Vargas', 'lvargas@email.com', '¡Grande Deportes Valdivia! A seguir ganando en semifinales.', 1),
(4, 'Carolina Silva', 'csilva@email.com', 'Me alegra ver que el turismo está creciendo en nuestra región.', 1);

-- ================================================
-- CONFIGURACIÓN FINAL
-- ================================================

-- Crear usuario para la aplicación (ajustar según necesidades)
-- CREATE USER 'losrios_user'@'localhost' IDENTIFIED BY 'password_seguro_aqui';
-- GRANT ALL PRIVILEGES ON losrios.* TO 'losrios_user'@'localhost';
-- FLUSH PRIVILEGES;
