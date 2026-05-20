-- Migración: agregar tipo 'facebook_scraping' al ENUM de medios_conectados
-- Ejecutar en producción (GoDaddy) antes de usar el módulo de Scraping Facebook

ALTER TABLE medios_conectados
    MODIFY COLUMN tipo ENUM('diario_online', 'facebook', 'instagram', 'facebook_scraping') NOT NULL;
