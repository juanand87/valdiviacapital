-- Evita truncamiento de contenido largo en scraping
ALTER TABLE medios_contenido_sincronizado
MODIFY COLUMN contenido LONGTEXT;
