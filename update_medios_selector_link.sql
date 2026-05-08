-- Agregar campo para selector de link en la tabla medios_diarios_config
ALTER TABLE medios_diarios_config 
ADD COLUMN selector_link VARCHAR(255) AFTER selector_titulo;
