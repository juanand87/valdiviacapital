-- ================================================
-- SOLUCIÓN RÁPIDA: Agregar solo columnas faltantes
-- ================================================
-- Ejecuta SOLO las líneas de las columnas que te faltan

USE losrios;

-- SI TE FALTA LA COLUMNA 'color', ejecuta esta línea:
-- ALTER TABLE categorias ADD COLUMN color VARCHAR(7) DEFAULT '#2563eb' AFTER descripcion;

-- SI TE FALTA LA COLUMNA 'icono', ejecuta esta línea:
-- ALTER TABLE categorias ADD COLUMN icono VARCHAR(50) AFTER color;

-- La columna 'orden' YA EXISTE, así que no ejecutes esta línea

-- ==================================================
-- ACTUALIZAR COLORES DE CATEGORÍAS
-- ==================================================
-- Ejecuta estas líneas para establecer los colores

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

-- ==================================================
-- ACTUALIZAR ICONOS DE CATEGORÍAS
-- ==================================================
-- Ejecuta estas líneas para establecer los iconos

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

-- Verificar las columnas
SELECT 'Actualización completada' as mensaje;
