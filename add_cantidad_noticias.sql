-- Agregar campo para cantidad de noticias a scrapear
ALTER TABLE medios_diarios_config 
ADD COLUMN cantidad_noticias INT DEFAULT 10 AFTER frecuencia_sincronizacion;
