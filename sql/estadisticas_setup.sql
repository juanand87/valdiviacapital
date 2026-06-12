-- Script de configuración para el módulo de Estadísticas
-- Ejecutar en la base de datos 'valdiviacapital'

-- 1. Crear tabla de estadísticas de visitas si no existe
CREATE TABLE IF NOT EXISTS `estadisticas_visitas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `noticia_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `visitas` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_noticia` (`noticia_id`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Insertar datos de ejemplo para pruebas (últimos 7 días)
-- Nota: Asegúrate de tener noticias y categorías con IDs válidos en tu BD

-- Datos de hoy
INSERT INTO `estadisticas_visitas` (`noticia_id`, `categoria_id`, `fecha`, `visitas`) VALUES
(1, 1, CURDATE(), 150),
(2, 1, CURDATE(), 120),
(3, 2, CURDATE(), 95),
(4, 2, CURDATE(), 80),
(5, 3, CURDATE(), 200),
(6, 3, CURDATE(), 45),
(7, 4, CURDATE(), 60),
(8, 4, CURDATE(), 30);

-- Datos de ayer
INSERT INTO `estadisticas_visitas` (`noticia_id`, `categoria_id`, `fecha`, `visitas`) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 140),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 110),
(5, 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 180),
(3, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 90);

-- Datos de hace 2 días
INSERT INTO `estadisticas_visitas` (`noticia_id`, `categoria_id`, `fecha`, `visitas`) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 130),
(5, 3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 160);
