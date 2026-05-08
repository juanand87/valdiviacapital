-- ================================================
-- ACTUALIZACIÓN DE BASE DE DATOS EXISTENTE
-- ================================================
-- Ejecuta este archivo si ya tenías una base de datos
-- y necesitas agregar las columnas nuevas

USE losrios;

-- Agregar columna 'color' a categorias si no existe
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'losrios' 
AND TABLE_NAME = 'categorias' 
AND COLUMN_NAME = 'color';

SET @query = IF(@col_exists = 0, 
    'ALTER TABLE categorias ADD COLUMN color VARCHAR(7) DEFAULT "#2563eb" AFTER descripcion', 
    'SELECT "La columna color ya existe" as mensaje');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar columna 'icono' a categorias si no existe
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'losrios' 
AND TABLE_NAME = 'categorias' 
AND COLUMN_NAME = 'icono';

SET @query = IF(@col_exists = 0, 
    'ALTER TABLE categorias ADD COLUMN icono VARCHAR(50) AFTER color', 
    'SELECT "La columna icono ya existe" as mensaje');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar columna 'orden' a categorias si no existe
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'losrios' 
AND TABLE_NAME = 'categorias' 
AND COLUMN_NAME = 'orden';

SET @query = IF(@col_exists = 0, 
    'ALTER TABLE categorias ADD COLUMN orden INT DEFAULT 0 AFTER icono', 
    'SELECT "La columna orden ya existe" as mensaje');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Actualizar colores de categorías existentes
UPDATE categorias SET color = '#059669' WHERE slug = 'regional';
UPDATE categorias SET color = '#2563eb' WHERE slug = 'politica';
UPDATE categorias SET color = '#f59e0b' WHERE slug = 'economia';
UPDATE categorias SET color = '#dc2626' WHERE slug = 'deportes';
UPDATE categorias SET color = '#8b5cf6' WHERE slug = 'cultura';
UPDATE categorias SET color = '#06b6d4' WHERE slug = 'salud';
UPDATE categorias SET color = '#ec4899' WHERE slug = 'educacion';
UPDATE categorias SET color = '#10b981' WHERE slug = 'turismo';
UPDATE categorias SET color = '#10b981' WHERE slug = 'medio-ambiente';
UPDATE categorias SET color = '#6366f1' WHERE slug = 'tecnologia';

-- Actualizar iconos de categorías existentes
UPDATE categorias SET icono = 'fa-map-marked-alt' WHERE slug = 'regional';
UPDATE categorias SET icono = 'fa-landmark' WHERE slug = 'politica';
UPDATE categorias SET icono = 'fa-chart-line' WHERE slug = 'economia';
UPDATE categorias SET icono = 'fa-futbol' WHERE slug = 'deportes';
UPDATE categorias SET icono = 'fa-palette' WHERE slug = 'cultura';
UPDATE categorias SET icono = 'fa-heartbeat' WHERE slug = 'salud';
UPDATE categorias SET icono = 'fa-graduation-cap' WHERE slug = 'educacion';
UPDATE categorias SET icono = 'fa-suitcase' WHERE slug = 'turismo';
UPDATE categorias SET icono = 'fa-leaf' WHERE slug = 'medio-ambiente';
UPDATE categorias SET icono = 'fa-microchip' WHERE slug = 'tecnologia';

-- Verificación
SELECT 'Actualización completada' as mensaje;
SELECT * FROM categorias;
