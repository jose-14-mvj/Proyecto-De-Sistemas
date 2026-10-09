USE migrasense;
DELIMITER $$

-- Registro de usuario (PHP manda la contraseña YA hasheada)
DROP PROCEDURE IF EXISTS sp_registrar_usuario$$
CREATE PROCEDURE sp_registrar_usuario(
  IN  p_nombres VARCHAR(60), IN p_correo VARCHAR(60),
  IN  p_contrasena_hash VARCHAR(255), OUT p_id_usuario INT UNSIGNED)
BEGIN
  IF EXISTS (SELECT 1 FROM usuario WHERE correo = LOWER(TRIM(p_correo))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El correo ya está registrado';
  END IF;
  INSERT INTO usuario (nombres, correo, contrasena)
  VALUES (p_nombres, p_correo, p_contrasena_hash);
  SET p_id_usuario = LAST_INSERT_ID();
END$$

-- Guardar / rehacer cuestionario. p_condiciones: 'hormonal,ansiedad_depresion'
DROP PROCEDURE IF EXISTS sp_guardar_perfil$$
CREATE PROCEDURE sp_guardar_perfil(
  IN p_id_usuario INT UNSIGNED,
  IN p_genero VARCHAR(15), IN p_ocupacion VARCHAR(20), IN p_antiguedad VARCHAR(15),
  IN p_frecuencia VARCHAR(15), IN p_patron_aparicion VARCHAR(2), IN p_orden_aparicion VARCHAR(12),
  IN p_inicio_momento_dia VARCHAR(12), IN p_duracion VARCHAR(10), IN p_intensidad VARCHAR(12),
  IN p_conoce_prodromica VARCHAR(10), IN p_horas_sueno VARCHAR(10), IN p_nivel_estres VARCHAR(10),
  IN p_consume_cafeina VARCHAR(2), IN p_cantidad_cafeina VARCHAR(6), IN p_condiciones TEXT)
BEGIN
  DECLARE v_id_perfil  INT UNSIGNED DEFAULT NULL;
  DECLARE v_id_patron  TINYINT UNSIGNED DEFAULT NULL;
  DECLARE v_id_cafeina TINYINT UNSIGNED DEFAULT NULL;
  DECLARE v_pedidas INT DEFAULT 0;
  DECLARE v_validas INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  -- Condiciones médicas
  SET p_condiciones = REPLACE(TRIM(COALESCE(p_condiciones, '')), ' ', '');
  IF p_condiciones = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Selecciona al menos una condición (o "ninguna")';
  END IF;
  SET v_pedidas = LENGTH(p_condiciones) - LENGTH(REPLACE(p_condiciones, ',', '')) + 1;
  SELECT COUNT(*) INTO v_validas FROM condicion_medica WHERE FIND_IN_SET(codigo, p_condiciones) > 0;
  IF v_pedidas <> v_validas THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Alguna condición no existe en el catálogo (o está repetida)';
  END IF;

  -- Patrón de aparición y consumo de cafeína (se buscan en sus catálogos)
  SELECT id_patron INTO v_id_patron FROM patron_aparicion
   WHERE patron_aparicion = p_patron_aparicion
     AND orden_aparicion <=> NULLIF(p_orden_aparicion, '') LIMIT 1;
  IF v_id_patron IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Patrón de aparición inválido';
  END IF;

  SELECT id_consumo_cafeina INTO v_id_cafeina FROM consumo_cafeina
   WHERE consume_cafeina = p_consume_cafeina
     AND cantidad_cafeina <=> NULLIF(p_cantidad_cafeina, '') LIMIT 1;
  IF v_id_cafeina IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Consumo de cafeína inválido';
  END IF;

  START TRANSACTION;
  SELECT id_perfil INTO v_id_perfil FROM perfil WHERE id_usuario = p_id_usuario LIMIT 1;

  IF v_id_perfil IS NULL THEN
    INSERT INTO perfil (id_usuario, genero, ocupacion, antiguedad, frecuencia,
        inicio_momento_dia, duracion, intensidad, conoce_fase_prodromica,
        horas_sueno, nivel_estres, id_patron, id_consumo_cafeina)
    VALUES (p_id_usuario, p_genero, p_ocupacion, p_antiguedad, p_frecuencia,
        p_inicio_momento_dia, p_duracion, p_intensidad, p_conoce_prodromica,
        p_horas_sueno, p_nivel_estres, v_id_patron, v_id_cafeina);
    SET v_id_perfil = LAST_INSERT_ID();
  ELSE
    UPDATE perfil SET genero = p_genero, ocupacion = p_ocupacion, antiguedad = p_antiguedad,
        frecuencia = p_frecuencia, inicio_momento_dia = p_inicio_momento_dia,
        duracion = p_duracion, intensidad = p_intensidad,
        conoce_fase_prodromica = p_conoce_prodromica, horas_sueno = p_horas_sueno,
        nivel_estres = p_nivel_estres, id_patron = v_id_patron,
        id_consumo_cafeina = v_id_cafeina, version_cuestionario = 2,
        fecha_completado = NOW()
     WHERE id_perfil = v_id_perfil;
    DELETE FROM perfil_condicion WHERE id_perfil = v_id_perfil;
  END IF;

  INSERT INTO perfil_condicion (id_perfil, id_condicion)
  SELECT v_id_perfil, id_condicion FROM condicion_medica
   WHERE FIND_IN_SET(codigo, p_condiciones) > 0;
  COMMIT;
END$$

-- Registrar síntomas prodrómicos. p_sintomas: 'luz,cuello'. p_fecha_hora NULL = ahora
DROP PROCEDURE IF EXISTS sp_registrar_prodromico$$
CREATE PROCEDURE sp_registrar_prodromico(
  IN p_id_usuario INT UNSIGNED, IN p_intensidad TINYINT UNSIGNED, IN p_notas VARCHAR(500),
  IN p_sintomas TEXT, IN p_fecha_hora DATETIME, OUT p_id_registro BIGINT UNSIGNED)
BEGIN
  DECLARE v_ts DATETIME;
  DECLARE v_pedidos INT DEFAULT 0;
  DECLARE v_validos INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  SET v_ts = COALESCE(p_fecha_hora, NOW());
  SET p_sintomas = REPLACE(TRIM(COALESCE(p_sintomas, '')), ' ', '');
  IF p_sintomas = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Selecciona al menos un síntoma';
  END IF;
  SET v_pedidos = LENGTH(p_sintomas) - LENGTH(REPLACE(p_sintomas, ',', '')) + 1;
  SELECT COUNT(*) INTO v_validos FROM sintoma_prodromico
   WHERE FIND_IN_SET(codigo, p_sintomas) > 0 AND activo = 1;
  IF v_pedidos <> v_validos THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Algún síntoma no existe en el catálogo (o está repetido)';
  END IF;

  START TRANSACTION;
  INSERT INTO registro_sintoma (id_usuario, fecha, hora, intensidad, observacion)
  VALUES (p_id_usuario, DATE(v_ts), TIME(v_ts), p_intensidad, NULLIF(TRIM(p_notas), ''));
  SET p_id_registro = LAST_INSERT_ID();

  INSERT INTO detalle_registro_sintoma (id_registro, id_sintoma)
  SELECT p_id_registro, id_sintoma FROM sintoma_prodromico
   WHERE FIND_IN_SET(codigo, p_sintomas) > 0;
  COMMIT;
END$$

-- Registrar episodio. p_sintomas y p_desencadenantes: listas con comas (pueden ir vacías)
DROP PROCEDURE IF EXISTS sp_registrar_episodio$$
CREATE PROCEDURE sp_registrar_episodio(
  IN p_id_usuario INT UNSIGNED, IN p_nivel_dolor TINYINT UNSIGNED, IN p_duracion VARCHAR(10),
  IN p_inicio DATETIME, IN p_medicacion TINYINT, IN p_medicamento VARCHAR(80),
  IN p_notas VARCHAR(500), IN p_sintomas TEXT, IN p_desencadenantes TEXT,
  OUT p_id_episodio BIGINT UNSIGNED)
BEGIN
  DECLARE v_ts DATETIME;
  DECLARE v_pedidos INT DEFAULT 0;
  DECLARE v_validos INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  SET v_ts = COALESCE(p_inicio, NOW());
  SET p_sintomas = REPLACE(TRIM(COALESCE(p_sintomas, '')), ' ', '');
  SET p_desencadenantes = REPLACE(TRIM(COALESCE(p_desencadenantes, '')), ' ', '');
  IF p_duracion IS NULL OR p_duracion = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Selecciona la duración estimada';
  END IF;

  IF p_sintomas <> '' THEN
    SET v_pedidos = LENGTH(p_sintomas) - LENGTH(REPLACE(p_sintomas, ',', '')) + 1;
    SELECT COUNT(*) INTO v_validos FROM sintoma_prodromico
     WHERE FIND_IN_SET(codigo, p_sintomas) > 0 AND activo = 1;
    IF v_pedidos <> v_validos THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Algún síntoma no existe en el catálogo (o está repetido)';
    END IF;
  END IF;

  IF p_desencadenantes <> '' THEN
    SET v_pedidos = LENGTH(p_desencadenantes) - LENGTH(REPLACE(p_desencadenantes, ',', '')) + 1;
    SELECT COUNT(*) INTO v_validos FROM desencadenante
     WHERE FIND_IN_SET(codigo, p_desencadenantes) > 0 AND activo = 1;
    IF v_pedidos <> v_validos THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Algún desencadenante no existe en el catálogo (o está repetido)';
    END IF;
  END IF;

  START TRANSACTION;
  INSERT INTO episodio_migrana (id_usuario, fecha_inicio, hora_inicio, duracion, nivel_dolor, notas)
  VALUES (p_id_usuario, DATE(v_ts), TIME(v_ts), p_duracion, p_nivel_dolor,
          NULLIF(TRIM(p_notas), ''));
  SET p_id_episodio = LAST_INSERT_ID();

  IF p_medicacion = 1 THEN
    INSERT INTO episodio_medicacion (id_episodio, medicamento)
    VALUES (p_id_episodio, NULLIF(TRIM(p_medicamento), ''));
  END IF;

  IF p_sintomas <> '' THEN
    INSERT INTO episodio_sintoma (id_episodio, id_sintoma)
    SELECT p_id_episodio, id_sintoma FROM sintoma_prodromico WHERE FIND_IN_SET(codigo, p_sintomas) > 0;
  END IF;
  IF p_desencadenantes <> '' THEN
    INSERT INTO episodio_desencadenante (id_episodio, id_desencadenante)
    SELECT p_id_episodio, id_desencadenante FROM desencadenante WHERE FIND_IN_SET(codigo, p_desencadenantes) > 0;
  END IF;
  COMMIT;
END$$

-- Editar registro (intensidad, notas, medicamento). p_tipo: 'prodromico' | 'episodio'
DROP PROCEDURE IF EXISTS sp_editar_registro$$
CREATE PROCEDURE sp_editar_registro(
  IN p_id_usuario INT UNSIGNED, IN p_tipo VARCHAR(12), IN p_id BIGINT UNSIGNED,
  IN p_intensidad TINYINT UNSIGNED, IN p_notas VARCHAR(500), IN p_medicamento VARCHAR(80))
BEGIN
  IF p_tipo = 'prodromico' THEN
    IF NOT EXISTS (SELECT 1 FROM registro_sintoma WHERE id_registro = p_id AND id_usuario = p_id_usuario) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Registro no encontrado';
    END IF;
    UPDATE registro_sintoma
       SET intensidad = p_intensidad, observacion = NULLIF(TRIM(p_notas), '')
     WHERE id_registro = p_id AND id_usuario = p_id_usuario;
  ELSEIF p_tipo = 'episodio' THEN
    IF NOT EXISTS (SELECT 1 FROM episodio_migrana WHERE id_episodio = p_id AND id_usuario = p_id_usuario) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Registro no encontrado';
    END IF;
    UPDATE episodio_migrana
       SET nivel_dolor = p_intensidad, notas = NULLIF(TRIM(p_notas), '')
     WHERE id_episodio = p_id AND id_usuario = p_id_usuario;

    IF NULLIF(TRIM(p_medicamento), '') IS NULL THEN
      DELETE FROM episodio_medicacion WHERE id_episodio = p_id;
    ELSE
      INSERT INTO episodio_medicacion (id_episodio, medicamento)
      VALUES (p_id, TRIM(p_medicamento))
      ON DUPLICATE KEY UPDATE medicamento = VALUES(medicamento);
    END IF;
  ELSE
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tipo de registro inválido';
  END IF;
END$$

-- Eliminar registro (las tablas hijas se borran en cascada)
DROP PROCEDURE IF EXISTS sp_eliminar_registro$$
CREATE PROCEDURE sp_eliminar_registro(
  IN p_id_usuario INT UNSIGNED, IN p_tipo VARCHAR(12), IN p_id BIGINT UNSIGNED)
BEGIN
  IF p_tipo = 'prodromico' THEN
    DELETE FROM registro_sintoma WHERE id_registro = p_id AND id_usuario = p_id_usuario;
  ELSEIF p_tipo = 'episodio' THEN
    DELETE FROM episodio_migrana WHERE id_episodio = p_id AND id_usuario = p_id_usuario;
  ELSE
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tipo de registro inválido';
  END IF;
END$$

-- Historial con filtros: p_tipo 'todos'/'prodromico'/'episodio'; p_dias 0 = todo
DROP PROCEDURE IF EXISTS sp_historial_usuario$$
CREATE PROCEDURE sp_historial_usuario(
  IN p_id_usuario INT UNSIGNED, IN p_tipo VARCHAR(12), IN p_dias INT)
BEGIN
  SELECT *
    FROM v_historial h
   WHERE h.id_usuario = p_id_usuario
     AND (p_tipo IS NULL OR p_tipo = 'todos' OR h.tipo = p_tipo)
     AND (p_dias IS NULL OR p_dias = 0 OR h.fecha_hora >= NOW() - INTERVAL p_dias DAY)
   ORDER BY h.fecha_hora DESC;
END$$

-- Recordatorios: 'diaria' → dia NULL · 'semanal' → dia 0..6 (0 = domingo)
DROP PROCEDURE IF EXISTS sp_guardar_recordatorio$$
CREATE PROCEDURE sp_guardar_recordatorio(
  IN p_id_usuario INT UNSIGNED, IN p_frecuencia VARCHAR(8), IN p_activo TINYINT,
  IN p_hora TIME, IN p_dia_semana TINYINT UNSIGNED)
BEGIN
  DECLARE v_dia TINYINT UNSIGNED;
  IF p_frecuencia NOT IN ('diaria','semanal') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Frecuencia inválida';
  END IF;
  IF p_frecuencia = 'semanal' AND (p_dia_semana IS NULL OR p_dia_semana > 6) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Día de la semana inválido (0-6)';
  END IF;
  SET v_dia = IF(p_frecuencia = 'semanal', p_dia_semana, NULL);

  IF EXISTS (SELECT 1 FROM recordatorio WHERE id_usuario = p_id_usuario AND frecuencia = p_frecuencia) THEN
    UPDATE recordatorio
       SET activo = IF(p_activo = 1, 1, 0), hora_configurada = p_hora, dia_semana = v_dia
     WHERE id_usuario = p_id_usuario AND frecuencia = p_frecuencia;
  ELSE
    INSERT INTO recordatorio (id_usuario, frecuencia, dia_semana, hora_configurada, activo)
    VALUES (p_id_usuario, p_frecuencia, v_dia, p_hora, IF(p_activo = 1, 1, 0));
  END IF;
END$$

-- Notificaciones
DROP PROCEDURE IF EXISTS sp_crear_notificacion$$
CREATE PROCEDURE sp_crear_notificacion(
  IN p_id_usuario INT UNSIGNED, IN p_tipo VARCHAR(25), IN p_mensaje VARCHAR(255),
  IN p_id_recordatorio INT UNSIGNED, OUT p_id_notificacion BIGINT UNSIGNED)
BEGIN
  INSERT INTO notificacion (id_usuario, id_recordatorio, tipo, mensaje)
  VALUES (p_id_usuario, p_id_recordatorio, p_tipo, p_mensaje);
  SET p_id_notificacion = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS sp_marcar_notificacion$$
CREATE PROCEDURE sp_marcar_notificacion(
  IN p_id_usuario INT UNSIGNED, IN p_id_notificacion BIGINT UNSIGNED, IN p_estado VARCHAR(12))
BEGIN
  IF p_estado NOT IN ('enviada','leida','descartada') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Estado inválido';
  END IF;
  UPDATE notificacion SET estado = p_estado
   WHERE id_notificacion = p_id_notificacion AND id_usuario = p_id_usuario;
END$$

-- Reporte: p_dias 30 / 90 / 365 / 0 (todo)
DROP PROCEDURE IF EXISTS sp_generar_reporte$$
CREATE PROCEDURE sp_generar_reporte(
  IN p_id_usuario INT UNSIGNED, IN p_dias INT, IN p_formato VARCHAR(3),
  OUT p_id_reporte BIGINT UNSIGNED)
BEGIN
  DECLARE v_fin DATE DEFAULT CURDATE();
  DECLARE v_ini DATE;
  DECLARE v_et VARCHAR(30);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF p_formato NOT IN ('pdf','txt') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Formato inválido (pdf o txt)';
  END IF;

  IF p_dias IS NULL OR p_dias = 0 THEN
    SELECT LEAST(
             COALESCE((SELECT MIN(fecha) FROM registro_sintoma WHERE id_usuario = p_id_usuario), v_fin),
             COALESCE((SELECT MIN(fecha_inicio) FROM episodio_migrana WHERE id_usuario = p_id_usuario), v_fin))
      INTO v_ini;
    SET v_et = 'Todo el historial';
  ELSE
    SET v_ini = v_fin - INTERVAL p_dias DAY;
    SET v_et = CASE p_dias WHEN 30 THEN 'Últimos 30 días' WHEN 90 THEN 'Últimos 3 meses'
                           WHEN 365 THEN 'Últimos 12 meses'
                           ELSE CONCAT('Últimos ', p_dias, ' días') END;
  END IF;

  START TRANSACTION;
  INSERT INTO reporte (id_usuario, formato, periodo_inicio, periodo_fin, etiqueta)
  VALUES (p_id_usuario, p_formato, v_ini, v_fin, v_et);
  SET p_id_reporte = LAST_INSERT_ID();

  INSERT INTO reporte_registro (id_reporte, id_registro)
  SELECT p_id_reporte, id_registro FROM registro_sintoma
   WHERE id_usuario = p_id_usuario AND fecha BETWEEN v_ini AND v_fin;

  INSERT INTO reporte_episodio (id_reporte, id_episodio)
  SELECT p_id_reporte, id_episodio FROM episodio_migrana
   WHERE id_usuario = p_id_usuario AND fecha_inicio BETWEEN v_ini AND v_fin;
  COMMIT;
END$$

DELIMITER ;