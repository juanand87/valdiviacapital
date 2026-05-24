-- Agrega indicador de Reel en videos (si no existe)
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'videos'
      AND COLUMN_NAME = 'es_reel'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE videos ADD COLUMN es_reel TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo',
    'SELECT "videos.es_reel ya existe"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
