CREATE USER IF NOT EXISTS 'migrasense_app'@'localhost' IDENTIFIED BY 'migra2026';
CREATE USER IF NOT EXISTS 'migrasense_app'@'127.0.0.1' IDENTIFIED BY 'migra2026';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON migrasense.* TO 'migrasense_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON migrasense.* TO 'migrasense_app'@'127.0.0.1';
FLUSH PRIVILEGES;