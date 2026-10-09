USE migrasense;
DELIMITER $$

-- Correo en minúsculas y nombres sin espacios sobrantes
DROP TRIGGER IF EXISTS trg_usuario_bi$$
CREATE TRIGGER trg_usuario_bi BEFORE INSERT ON usuario FOR EACH ROW
BEGIN
  SET NEW.correo  = LOWER(TRIM(NEW.correo));
  SET NEW.nombres = TRIM(NEW.nombres);
END$$

DROP TRIGGER IF EXISTS trg_usuario_bu$$
CREATE TRIGGER trg_usuario_bu BEFORE UPDATE ON usuario FOR EACH ROW
BEGIN
  SET NEW.correo  = LOWER(TRIM(NEW.correo));
  SET NEW.nombres = TRIM(NEW.nombres);
END$$

-- Recordatorios por defecto al crear la cuenta (diario 20:00, semanal domingo 19:00)
DROP TRIGGER IF EXISTS trg_usuario_ai$$
CREATE TRIGGER trg_usuario_ai AFTER INSERT ON usuario FOR EACH ROW
BEGIN
  INSERT INTO recordatorio (id_usuario, frecuencia, dia_semana, hora_configurada, activo)
  VALUES (NEW.id_usuario, 'diaria',  NULL, '20:00:00', 1),
         (NEW.id_usuario, 'semanal', 0,    '19:00:00', 1);
END$$

-- Sin fechas futuras en episodios y registros
DROP TRIGGER IF EXISTS trg_episodio_bi$$
CREATE TRIGGER trg_episodio_bi BEFORE INSERT ON episodio_migrana FOR EACH ROW
BEGIN
  IF TIMESTAMP(NEW.fecha_inicio, NEW.hora_inicio) > NOW() + INTERVAL 1 DAY THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fecha de inicio no puede estar en el futuro';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_registro_sintoma_bi$$
CREATE TRIGGER trg_registro_sintoma_bi BEFORE INSERT ON registro_sintoma FOR EACH ROW
BEGIN
  IF TIMESTAMP(NEW.fecha, NEW.hora) > NOW() + INTERVAL 1 DAY THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fecha del registro no puede estar en el futuro';
  END IF;
END$$

DELIMITER ;