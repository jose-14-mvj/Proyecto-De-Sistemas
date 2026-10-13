-- =====================================================================
-- MigraSense · usuario de la aplicación en Aiven (mínimos privilegios)

-- Ejecutar con:
--   php artisan migrasense:importar-sql database/aiven/usuario_app_aiven.sql
-- =====================================================================
CREATE USER IF NOT EXISTS 'migrasense_app'@'%' IDENTIFIED BY 'ProyectoDeSistemaSSSSSSSs123#';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON migrasense.* TO 'migrasense_app'@'%';
