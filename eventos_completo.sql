-- Crear tabla eventos si no existe
CREATE TABLE IF NOT EXISTS `eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL UNIQUE,
  `descripcion` longtext,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime,
  `lugar` varchar(255) NOT NULL,
  `direccion` varchar(255),
  `comuna_id` int(11),
  `categoria` enum('Música','Deporte','Cultura','Educación','Feria','Familiar','Gastronomía','Otro') DEFAULT 'Otro',
  `imagen_url` varchar(500),
  `url_externo` varchar(500),
  `organizador` varchar(255),
  `gratuito` tinyint(1) DEFAULT 1,
  `precio` decimal(10,2),
  `destacado` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `autor_id` int(11),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_evento_slug` (`slug`),
  KEY `idx_evento_fecha_activo` (`fecha_inicio`, `activo`),
  KEY `idx_evento_comuna` (`comuna_id`),
  KEY `idx_evento_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserts para 6 eventos de demostración con imágenes
INSERT INTO `eventos` (`titulo`, `slug`, `descripcion`, `fecha_inicio`, `fecha_fin`, `lugar`, `direccion`, `comuna_id`, `categoria`, `imagen_url`, `url_externo`, `organizador`, `gratuito`, `precio`, `destacado`, `activo`, `autor_id`, `created_at`, `updated_at`) VALUES

('Festival de Jazz del Calle-Calle 2026', 'festival-jazz-calle-calle-2026', 'Disfruta de la mejor música jazz en la costanera de Valdivia. Cuatro noches consecutivas con artistas nacionales e internacionales. Buen clima, buena música y mejor compañía.', '2026-06-21 19:00:00', '2026-06-24 23:00:00', 'Costanera de Valdivia', 'Av. Costanera s/n, Valdivia', 1, 'Música', '/valdiviacapital/assets/images/evento-jazz.jpg', 'https://www.festivaljazz.cl', 'Municipalidad de Valdivia', 0, 8000, 1, 1, 1, NOW(), NOW()),

('Feria de Emprendimiento Local', 'feria-emprendimiento-local', 'Conoce a emprendedores locales de Los Ríos. Exhibición de productos, servicios y negocios innovadores. Entrada libre, actividades para toda la familia.', '2026-06-28 10:00:00', '2026-06-28 18:00:00', 'Parque Saval', 'Calle O''Higgins 567, La Unión', 7, 'Feria', '/valdiviacapital/assets/images/evento-feria.jpg', 'https://www.emprenderios.cl', 'Cámara de Comercio', 1, 0, 0, 1, 1, NOW(), NOW()),

('Corrida Familiar Costanera 5K', 'corrida-familiar-costanera-5k', 'Trotacaminos familiar de 5 km por la costanera. Inscripciones abiertas para todas las edades. Premios y sorpresas para los participantes. ¡Todos pueden correr!', '2026-07-05 08:00:00', '2026-07-05 10:00:00', 'Costanera de Valdivia', 'Salida: Estadio Saval', 1, 'Deporte', '/valdiviacapital/assets/images/evento-corrida.jpg', 'https://www.corridas.cl/valdivia', 'Club de Corredores Valdivia', 1, 0, 1, 1, 1, NOW(), NOW()),

('Cine Bajo las Estrellas', 'cine-bajo-estrellas', 'Películas clásicas en pantalla gigante al aire libre. Trae tu manta y almohada. Entrada gratuita. Venta de snacks en el lugar. Películas a partir de las 20:30 hrs.', '2026-07-12 20:00:00', '2026-07-12 22:30:00', 'Plaza de Panguipulli', 'Plaza Principal, Panguipulli', 4, 'Cultura', '/valdiviacapital/assets/images/evento-cine.jpg', NULL, 'Municipalidad de Panguipulli', 1, 0, 0, 1, 1, NOW(), NOW()),

('Taller de Robótica Escolar', 'taller-robotica-escolar', 'Aprende los fundamentos de la robótica y programación. Dirigido a niños y niñas de 8 a 15 años. Materiales incluidos. Certificado de participación.', '2026-07-19 14:00:00', '2026-07-19 17:00:00', 'Centro Tecnológico Municipal', 'Calle Yungay 890, Los Lagos', 8, 'Educación', '/valdiviacapital/assets/images/evento-robotica.jpg', 'https://www.tecnologialosrios.cl', 'Seremía de Educación', 0, 3000, 0, 1, 1, NOW(), NOW()),

('Fiesta Costumbrista Niebla', 'fiesta-costumbrista-niebla', 'Tradiciones y folklore del sur. Música costumbrista, comidas típicas, danzas regionales. Festival familiar de tradición y cultura. ¡No te lo pierdas!', '2026-07-26 11:00:00', '2026-07-26 20:00:00', 'Plaza Artesanal Niebla', 'Calle San Martín 345, Niebla', 9, 'Familiar', '/valdiviacapital/assets/images/evento-niebla.jpg', NULL, 'Junta de Vecinos Niebla', 1, 0, 1, 1, 1, NOW(), NOW());
