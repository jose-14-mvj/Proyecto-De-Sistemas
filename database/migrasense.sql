-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 09-10-2026 a las 16:28:59
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `migrasense`
--

DELIMITER $$
--
-- Procedimientos
--
DROP PROCEDURE IF EXISTS `sp_crear_notificacion`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_notificacion` (IN `p_id_usuario` INT UNSIGNED, IN `p_tipo` VARCHAR(25), IN `p_mensaje` VARCHAR(255), IN `p_id_recordatorio` INT UNSIGNED, OUT `p_id_notificacion` BIGINT UNSIGNED)   BEGIN
  INSERT INTO notificacion (id_usuario, id_recordatorio, tipo, mensaje)
  VALUES (p_id_usuario, p_id_recordatorio, p_tipo, p_mensaje);
  SET p_id_notificacion = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS `sp_editar_registro`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_editar_registro` (IN `p_id_usuario` INT UNSIGNED, IN `p_tipo` VARCHAR(12), IN `p_id` BIGINT UNSIGNED, IN `p_intensidad` TINYINT UNSIGNED, IN `p_notas` VARCHAR(500), IN `p_medicamento` VARCHAR(80))   BEGIN
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

DROP PROCEDURE IF EXISTS `sp_eliminar_registro`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_eliminar_registro` (IN `p_id_usuario` INT UNSIGNED, IN `p_tipo` VARCHAR(12), IN `p_id` BIGINT UNSIGNED)   BEGIN
  IF p_tipo = 'prodromico' THEN
    DELETE FROM registro_sintoma WHERE id_registro = p_id AND id_usuario = p_id_usuario;
  ELSEIF p_tipo = 'episodio' THEN
    DELETE FROM episodio_migrana WHERE id_episodio = p_id AND id_usuario = p_id_usuario;
  ELSE
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tipo de registro inválido';
  END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_generar_reporte`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generar_reporte` (IN `p_id_usuario` INT UNSIGNED, IN `p_dias` INT, IN `p_formato` VARCHAR(3), OUT `p_id_reporte` BIGINT UNSIGNED)   BEGIN
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

DROP PROCEDURE IF EXISTS `sp_guardar_perfil`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_guardar_perfil` (IN `p_id_usuario` INT UNSIGNED, IN `p_genero` VARCHAR(15), IN `p_ocupacion` VARCHAR(20), IN `p_antiguedad` VARCHAR(15), IN `p_frecuencia` VARCHAR(15), IN `p_patron_aparicion` VARCHAR(2), IN `p_orden_aparicion` VARCHAR(12), IN `p_inicio_momento_dia` VARCHAR(12), IN `p_duracion` VARCHAR(10), IN `p_intensidad` VARCHAR(12), IN `p_conoce_prodromica` VARCHAR(10), IN `p_horas_sueno` VARCHAR(10), IN `p_nivel_estres` VARCHAR(10), IN `p_consume_cafeina` VARCHAR(2), IN `p_cantidad_cafeina` VARCHAR(6), IN `p_condiciones` TEXT)   BEGIN
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

DROP PROCEDURE IF EXISTS `sp_guardar_recordatorio`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_guardar_recordatorio` (IN `p_id_usuario` INT UNSIGNED, IN `p_frecuencia` VARCHAR(8), IN `p_activo` TINYINT, IN `p_hora` TIME, IN `p_dia_semana` TINYINT UNSIGNED)   BEGIN
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

DROP PROCEDURE IF EXISTS `sp_historial_usuario`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_historial_usuario` (IN `p_id_usuario` INT UNSIGNED, IN `p_tipo` VARCHAR(12), IN `p_dias` INT)   BEGIN
  SELECT *
    FROM v_historial h
   WHERE h.id_usuario = p_id_usuario
     AND (p_tipo IS NULL OR p_tipo = 'todos' OR h.tipo = p_tipo)
     AND (p_dias IS NULL OR p_dias = 0 OR h.fecha_hora >= NOW() - INTERVAL p_dias DAY)
   ORDER BY h.fecha_hora DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_marcar_notificacion`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_marcar_notificacion` (IN `p_id_usuario` INT UNSIGNED, IN `p_id_notificacion` BIGINT UNSIGNED, IN `p_estado` VARCHAR(12))   BEGIN
  IF p_estado NOT IN ('enviada','leida','descartada') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Estado inválido';
  END IF;
  UPDATE notificacion SET estado = p_estado
   WHERE id_notificacion = p_id_notificacion AND id_usuario = p_id_usuario;
END$$

DROP PROCEDURE IF EXISTS `sp_registrar_episodio`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_episodio` (IN `p_id_usuario` INT UNSIGNED, IN `p_nivel_dolor` TINYINT UNSIGNED, IN `p_duracion` VARCHAR(10), IN `p_inicio` DATETIME, IN `p_medicacion` TINYINT, IN `p_medicamento` VARCHAR(80), IN `p_notas` VARCHAR(500), IN `p_sintomas` TEXT, IN `p_desencadenantes` TEXT, OUT `p_id_episodio` BIGINT UNSIGNED)   BEGIN
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

DROP PROCEDURE IF EXISTS `sp_registrar_prodromico`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_prodromico` (IN `p_id_usuario` INT UNSIGNED, IN `p_intensidad` TINYINT UNSIGNED, IN `p_notas` VARCHAR(500), IN `p_sintomas` TEXT, IN `p_fecha_hora` DATETIME, OUT `p_id_registro` BIGINT UNSIGNED)   BEGIN
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

DROP PROCEDURE IF EXISTS `sp_registrar_usuario`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_usuario` (IN `p_nombres` VARCHAR(60), IN `p_correo` VARCHAR(60), IN `p_contrasena_hash` VARCHAR(255), OUT `p_id_usuario` INT UNSIGNED)   BEGIN
  IF EXISTS (SELECT 1 FROM usuario WHERE correo = LOWER(TRIM(p_correo))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El correo ya está registrado';
  END IF;
  INSERT INTO usuario (nombres, correo, contrasena)
  VALUES (p_nombres, p_correo, p_contrasena_hash);
  SET p_id_usuario = LAST_INSERT_ID();
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `condicion_medica`
--

DROP TABLE IF EXISTS `condicion_medica`;
CREATE TABLE `condicion_medica` (
  `id_condicion` tinyint(3) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `condicion_medica` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `condicion_medica`
--

INSERT INTO `condicion_medica` (`id_condicion`, `codigo`, `condicion_medica`) VALUES
(1, 'ansiedad_depresion', 'Ansiedad o depresión'),
(2, 'hipertension', 'Hipertensión'),
(3, 'trastornos_sueno', 'Trastornos del sueño'),
(4, 'hormonal', 'Alteraciones hormonales o ciclo irregular'),
(5, 'alergias_sinusitis', 'Alergias o sinusitis crónica'),
(6, 'ninguna', 'Ninguna'),
(7, 'otra', 'Otra');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consumo_cafeina`
--

DROP TABLE IF EXISTS `consumo_cafeina`;
CREATE TABLE `consumo_cafeina` (
  `id_consumo_cafeina` tinyint(3) UNSIGNED NOT NULL,
  `consume_cafeina` enum('si','no') NOT NULL,
  `cantidad_cafeina` enum('1','2_3','4_mas') DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `consumo_cafeina`
--

INSERT INTO `consumo_cafeina` (`id_consumo_cafeina`, `consume_cafeina`, `cantidad_cafeina`) VALUES
(1, 'no', NULL),
(2, 'si', '1'),
(3, 'si', '2_3'),
(4, 'si', '4_mas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `desencadenante`
--

DROP TABLE IF EXISTS `desencadenante`;
CREATE TABLE `desencadenante` (
  `id_desencadenante` tinyint(3) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `desencadenante`
--

INSERT INTO `desencadenante` (`id_desencadenante`, `codigo`, `nombre`, `activo`) VALUES
(1, 'estres', 'Estrés', 1),
(2, 'sueno', 'Falta de sueño', 1),
(3, 'alimentos', 'Ciertos alimentos', 1),
(4, 'hormonal', 'Cambios hormonales', 1),
(5, 'deshidratacion', 'Deshidratación', 1),
(6, 'clima', 'Cambios de clima/presión', 1),
(7, 'pantallas', 'Luces o pantallas', 1),
(8, 'ayuno', 'Ayuno prolongado', 1),
(9, 'alcohol', 'Alcohol', 1),
(10, 'olores', 'Olores fuertes', 1),
(11, 'ejercicio', 'Ejercicio intenso', 1),
(12, 'ruido', 'Ruido excesivo', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_registro_sintoma`
--

DROP TABLE IF EXISTS `detalle_registro_sintoma`;
CREATE TABLE `detalle_registro_sintoma` (
  `id_registro` bigint(20) UNSIGNED NOT NULL,
  `id_sintoma` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `episodio_desencadenante`
--

DROP TABLE IF EXISTS `episodio_desencadenante`;
CREATE TABLE `episodio_desencadenante` (
  `id_episodio` bigint(20) UNSIGNED NOT NULL,
  `id_desencadenante` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `episodio_medicacion`
--

DROP TABLE IF EXISTS `episodio_medicacion`;
CREATE TABLE `episodio_medicacion` (
  `id_episodio` bigint(20) UNSIGNED NOT NULL,
  `medicamento` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `episodio_migrana`
--

DROP TABLE IF EXISTS `episodio_migrana`;
CREATE TABLE `episodio_migrana` (
  `id_episodio` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `fecha_inicio` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `duracion` enum('menos_4h','4_24h','1_3d','mas_3d') NOT NULL,
  `nivel_dolor` tinyint(3) UNSIGNED NOT NULL,
  `notas` varchar(500) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Disparadores `episodio_migrana`
--
DROP TRIGGER IF EXISTS `trg_episodio_bi`;
DELIMITER $$
CREATE TRIGGER `trg_episodio_bi` BEFORE INSERT ON `episodio_migrana` FOR EACH ROW BEGIN
  IF TIMESTAMP(NEW.fecha_inicio, NEW.hora_inicio) > NOW() + INTERVAL 1 DAY THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fecha de inicio no puede estar en el futuro';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `episodio_sintoma`
--

DROP TABLE IF EXISTS `episodio_sintoma`;
CREATE TABLE `episodio_sintoma` (
  `id_episodio` bigint(20) UNSIGNED NOT NULL,
  `id_sintoma` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificacion`
--

DROP TABLE IF EXISTS `notificacion`;
CREATE TABLE `notificacion` (
  `id_notificacion` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_recordatorio` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('recordatorio_diario','resumen_semanal','alerta_riesgo','sistema') NOT NULL,
  `mensaje` varchar(255) NOT NULL,
  `estado` enum('pendiente','enviada','leida','descartada') NOT NULL DEFAULT 'pendiente',
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `patron_aparicion`
--

DROP TABLE IF EXISTS `patron_aparicion`;
CREATE TABLE `patron_aparicion` (
  `id_patron` tinyint(3) UNSIGNED NOT NULL,
  `patron_aparicion` enum('si','no') NOT NULL,
  `orden_aparicion` enum('2_3_meses','4_5_meses','6_7_meses','8_9_meses','9_10_meses') DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `patron_aparicion`
--

INSERT INTO `patron_aparicion` (`id_patron`, `patron_aparicion`, `orden_aparicion`) VALUES
(1, 'no', NULL),
(2, 'si', '2_3_meses'),
(3, 'si', '4_5_meses'),
(4, 'si', '6_7_meses'),
(5, 'si', '8_9_meses'),
(6, 'si', '9_10_meses');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `perfil`
--

DROP TABLE IF EXISTS `perfil`;
CREATE TABLE `perfil` (
  `id_perfil` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `genero` enum('femenino','masculino','otro') NOT NULL,
  `ocupacion` enum('estudiante','oficina_virtual','fisico_campo','turnos_nocturno','hogar','sin_ocupacion') NOT NULL,
  `antiguedad` enum('menos_1_anio','1_3_anios','4_10_anios','mas_10_anios') NOT NULL,
  `frecuencia` enum('menos_1_mes','1_3_mes','1_2_semana','3_mas_semana','casi_diario') NOT NULL,
  `inicio_momento_dia` enum('madrugada','manana','tarde','noche','sin_horario') NOT NULL,
  `duracion` enum('menos_4h','4_12h','12_24h','1_3d','mas_3d') NOT NULL,
  `intensidad` enum('leve','moderada','intensa','muy_intensa') NOT NULL,
  `conoce_fase_prodromica` enum('si','no','no_seguro') NOT NULL,
  `horas_sueno` enum('menos_5','5_6','7_8','mas_8') NOT NULL,
  `nivel_estres` enum('bajo','moderado','alto','muy_alto') NOT NULL,
  `id_patron` tinyint(3) UNSIGNED NOT NULL,
  `id_consumo_cafeina` tinyint(3) UNSIGNED NOT NULL,
  `version_cuestionario` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `fecha_completado` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `perfil_condicion`
--

DROP TABLE IF EXISTS `perfil_condicion`;
CREATE TABLE `perfil_condicion` (
  `id_perfil` int(10) UNSIGNED NOT NULL,
  `id_condicion` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recordatorio`
--

DROP TABLE IF EXISTS `recordatorio`;
CREATE TABLE `recordatorio` (
  `id_recordatorio` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `frecuencia` enum('diaria','semanal') NOT NULL,
  `dia_semana` tinyint(3) UNSIGNED DEFAULT NULL,
  `hora_configurada` time NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registro_sintoma`
--

DROP TABLE IF EXISTS `registro_sintoma`;
CREATE TABLE `registro_sintoma` (
  `id_registro` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `intensidad` tinyint(3) UNSIGNED NOT NULL,
  `observacion` varchar(500) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Disparadores `registro_sintoma`
--
DROP TRIGGER IF EXISTS `trg_registro_sintoma_bi`;
DELIMITER $$
CREATE TRIGGER `trg_registro_sintoma_bi` BEFORE INSERT ON `registro_sintoma` FOR EACH ROW BEGIN
  IF TIMESTAMP(NEW.fecha, NEW.hora) > NOW() + INTERVAL 1 DAY THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fecha del registro no puede estar en el futuro';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reporte`
--

DROP TABLE IF EXISTS `reporte`;
CREATE TABLE `reporte` (
  `id_reporte` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `formato` enum('pdf','txt') NOT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_fin` date NOT NULL,
  `etiqueta` varchar(30) DEFAULT NULL,
  `fecha_generacion` datetime NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reporte_episodio`
--

DROP TABLE IF EXISTS `reporte_episodio`;
CREATE TABLE `reporte_episodio` (
  `id_reporte` bigint(20) UNSIGNED NOT NULL,
  `id_episodio` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reporte_registro`
--

DROP TABLE IF EXISTS `reporte_registro`;
CREATE TABLE `reporte_registro` (
  `id_reporte` bigint(20) UNSIGNED NOT NULL,
  `id_registro` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sintoma_prodromico`
--

DROP TABLE IF EXISTS `sintoma_prodromico`;
CREATE TABLE `sintoma_prodromico` (
  `id_sintoma` tinyint(3) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre_sintoma` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sintoma_prodromico`
--

INSERT INTO `sintoma_prodromico` (`id_sintoma`, `codigo`, `nombre_sintoma`, `descripcion`, `activo`) VALUES
(1, 'luz', 'Sensibilidad a la luz', 'La luz normal resulta molesta', 1),
(2, 'sonido', 'Sensibilidad al sonido', 'Los ruidos habituales resultan molestos', 1),
(3, 'fatiga', 'Fatiga o bostezos frecuentes', 'Cansancio inusual antes del dolor', 1),
(4, 'cuello', 'Dolor o rigidez de cuello', 'Tensión en cuello u hombros', 1),
(5, 'antojos', 'Antojos de comida', 'Ganas intensas de ciertos alimentos', 1),
(6, 'humor', 'Cambios de humor / irritabilidad', 'Variaciones del estado de ánimo', 1),
(7, 'concentracion', 'Dificultad para concentrarse', 'Cuesta mantener la atención', 1),
(8, 'nauseas', 'Náuseas', 'Malestar estomacal', 1),
(9, 'vision', 'Destellos o visión borrosa (aura)', 'Alteraciones visuales previas al dolor', 1),
(10, 'sed', 'Sed excesiva', 'Sed inusual', 1),
(11, 'retencion', 'Retención de líquidos', 'Hinchazón o sensación de retención', 1),
(12, 'bostezos', 'Bostezos excesivos', 'Bostezos repetidos sin sueño', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

DROP TABLE IF EXISTS `usuario`;
CREATE TABLE `usuario` (
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `nombres` varchar(60) NOT NULL,
  `paterno` varchar(40) DEFAULT NULL,
  `materno` varchar(40) DEFAULT NULL,
  `correo` varchar(60) NOT NULL,
  `contrasena` varchar(255) NOT NULL COMMENT 'HASH bcrypt, nunca texto plano',
  `fecha_nac` date DEFAULT NULL,
  `foto` mediumtext DEFAULT NULL COMMENT 'data URL JPEG 256px',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL
) ;

--
-- Disparadores `usuario`
--
DROP TRIGGER IF EXISTS `trg_usuario_ai`;
DELIMITER $$
CREATE TRIGGER `trg_usuario_ai` AFTER INSERT ON `usuario` FOR EACH ROW BEGIN
  INSERT INTO recordatorio (id_usuario, frecuencia, dia_semana, hora_configurada, activo)
  VALUES (NEW.id_usuario, 'diaria',  NULL, '20:00:00', 1),
         (NEW.id_usuario, 'semanal', 0,    '19:00:00', 1);
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_usuario_bi`;
DELIMITER $$
CREATE TRIGGER `trg_usuario_bi` BEFORE INSERT ON `usuario` FOR EACH ROW BEGIN
  SET NEW.correo  = LOWER(TRIM(NEW.correo));
  SET NEW.nombres = TRIM(NEW.nombres);
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_usuario_bu`;
DELIMITER $$
CREATE TRIGGER `trg_usuario_bu` BEFORE UPDATE ON `usuario` FOR EACH ROW BEGIN
  SET NEW.correo  = LOWER(TRIM(NEW.correo));
  SET NEW.nombres = TRIM(NEW.nombres);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_desencadenantes_frecuentes`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_desencadenantes_frecuentes`;
CREATE TABLE `v_desencadenantes_frecuentes` (
`id_usuario` int(10) unsigned
,`codigo` varchar(30)
,`nombre` varchar(80)
,`veces` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_episodios_semana`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_episodios_semana`;
CREATE TABLE `v_episodios_semana` (
`id_usuario` int(10) unsigned
,`semana_inicio` date
,`episodios` bigint(21)
,`dolor_medio` decimal(5,1)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_historial`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_historial`;
CREATE TABLE `v_historial` (
`id_usuario` int(10) unsigned
,`tipo` varchar(10)
,`id` bigint(20) unsigned
,`etiqueta` varchar(20)
,`fecha_hora` datetime
,`inicio` datetime
,`intensidad` tinyint(3) unsigned
,`duracion` enum('menos_4h','4_24h','1_3d','mas_3d')
,`medicacion` bigint(21)
,`medicamento` varchar(80)
,`notas` varchar(500)
,`sintomas` mediumtext
,`desencadenantes` mediumtext
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_perfil_completo`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_perfil_completo`;
CREATE TABLE `v_perfil_completo` (
`id_usuario` int(10) unsigned
,`nombres` varchar(60)
,`paterno` varchar(40)
,`materno` varchar(40)
,`correo` varchar(60)
,`foto` mediumtext
,`fecha_nac` date
,`id_perfil` int(10) unsigned
,`genero` enum('femenino','masculino','otro')
,`ocupacion` enum('estudiante','oficina_virtual','fisico_campo','turnos_nocturno','hogar','sin_ocupacion')
,`antiguedad` enum('menos_1_anio','1_3_anios','4_10_anios','mas_10_anios')
,`frecuencia` enum('menos_1_mes','1_3_mes','1_2_semana','3_mas_semana','casi_diario')
,`patron_aparicion` enum('si','no')
,`orden_aparicion` enum('2_3_meses','4_5_meses','6_7_meses','8_9_meses','9_10_meses')
,`inicio_momento_dia` enum('madrugada','manana','tarde','noche','sin_horario')
,`duracion` enum('menos_4h','4_12h','12_24h','1_3d','mas_3d')
,`intensidad` enum('leve','moderada','intensa','muy_intensa')
,`conoce_fase_prodromica` enum('si','no','no_seguro')
,`horas_sueno` enum('menos_5','5_6','7_8','mas_8')
,`nivel_estres` enum('bajo','moderado','alto','muy_alto')
,`consume_cafeina` enum('si','no')
,`cantidad_cafeina` enum('1','2_3','4_mas')
,`fecha_completado` datetime
,`condiciones` mediumtext
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_resumen_30dias`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_resumen_30dias`;
CREATE TABLE `v_resumen_30dias` (
`id_usuario` int(10) unsigned
,`episodios_30d` bigint(21)
,`prodromicos_30d` bigint(21)
,`intensidad_media_30d` decimal(5,1)
,`registros_totales` bigint(22)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_sintomas_frecuentes`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_sintomas_frecuentes`;
CREATE TABLE `v_sintomas_frecuentes` (
`id_usuario` int(10) unsigned
,`codigo` varchar(30)
,`nombre_sintoma` varchar(80)
,`veces` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_desencadenantes_frecuentes`
--
DROP TABLE IF EXISTS `v_desencadenantes_frecuentes`;

DROP VIEW IF EXISTS `v_desencadenantes_frecuentes`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_desencadenantes_frecuentes`  AS SELECT `e`.`id_usuario` AS `id_usuario`, `t`.`codigo` AS `codigo`, `t`.`nombre` AS `nombre`, count(0) AS `veces` FROM ((`episodio_migrana` `e` join `episodio_desencadenante` `x` on(`x`.`id_episodio` = `e`.`id_episodio`)) join `desencadenante` `t` on(`t`.`id_desencadenante` = `x`.`id_desencadenante`)) GROUP BY `e`.`id_usuario`, `t`.`codigo`, `t`.`nombre` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_episodios_semana`
--
DROP TABLE IF EXISTS `v_episodios_semana`;

DROP VIEW IF EXISTS `v_episodios_semana`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_episodios_semana`  AS SELECT `episodio_migrana`.`id_usuario` AS `id_usuario`, `episodio_migrana`.`fecha_inicio`- interval weekday(`episodio_migrana`.`fecha_inicio`) day AS `semana_inicio`, count(0) AS `episodios`, round(avg(`episodio_migrana`.`nivel_dolor`),1) AS `dolor_medio` FROM `episodio_migrana` GROUP BY `episodio_migrana`.`id_usuario`, `episodio_migrana`.`fecha_inicio`- interval weekday(`episodio_migrana`.`fecha_inicio`) AS `day` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_historial`
--
DROP TABLE IF EXISTS `v_historial`;

DROP VIEW IF EXISTS `v_historial`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_historial`  AS SELECT `r`.`id_usuario` AS `id_usuario`, 'prodromico' AS `tipo`, `r`.`id_registro` AS `id`, 'Síntomas prodrómicos' AS `etiqueta`, timestamp(`r`.`fecha`,`r`.`hora`) AS `fecha_hora`, NULL AS `inicio`, `r`.`intensidad` AS `intensidad`, NULL AS `duracion`, NULL AS `medicacion`, NULL AS `medicamento`, `r`.`observacion` AS `notas`, (select group_concat(`s`.`nombre_sintoma` order by `s`.`id_sintoma` ASC separator ', ') from (`detalle_registro_sintoma` `d` join `sintoma_prodromico` `s` on(`s`.`id_sintoma` = `d`.`id_sintoma`)) where `d`.`id_registro` = `r`.`id_registro`) AS `sintomas`, NULL AS `desencadenantes` FROM `registro_sintoma` AS `r`union all select `e`.`id_usuario` AS `id_usuario`,'episodio' AS `episodio`,`e`.`id_episodio` AS `id_episodio`,'Episodio de migraña' AS `Episodio de migraña`,timestamp(`e`.`fecha_inicio`,`e`.`hora_inicio`) AS `TIMESTAMP(e.fecha_inicio, e.hora_inicio)`,timestamp(`e`.`fecha_inicio`,`e`.`hora_inicio`) AS `TIMESTAMP(e.fecha_inicio, e.hora_inicio)`,`e`.`nivel_dolor` AS `nivel_dolor`,`e`.`duracion` AS `duracion`,(select count(0) from `episodio_medicacion` `m` where `m`.`id_episodio` = `e`.`id_episodio`) AS `Name_exp_9`,(select `m`.`medicamento` from `episodio_medicacion` `m` where `m`.`id_episodio` = `e`.`id_episodio`) AS `Name_exp_10`,`e`.`notas` AS `notas`,(select group_concat(`s`.`nombre_sintoma` order by `s`.`id_sintoma` ASC separator ', ') from (`episodio_sintoma` `x` join `sintoma_prodromico` `s` on(`s`.`id_sintoma` = `x`.`id_sintoma`)) where `x`.`id_episodio` = `e`.`id_episodio`) AS `Name_exp_12`,(select group_concat(`t`.`nombre` order by `t`.`id_desencadenante` ASC separator ', ') from (`episodio_desencadenante` `y` join `desencadenante` `t` on(`t`.`id_desencadenante` = `y`.`id_desencadenante`)) where `y`.`id_episodio` = `e`.`id_episodio`) AS `Name_exp_13` from `episodio_migrana` `e`  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_perfil_completo`
--
DROP TABLE IF EXISTS `v_perfil_completo`;

DROP VIEW IF EXISTS `v_perfil_completo`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_perfil_completo`  AS SELECT `u`.`id_usuario` AS `id_usuario`, `u`.`nombres` AS `nombres`, `u`.`paterno` AS `paterno`, `u`.`materno` AS `materno`, `u`.`correo` AS `correo`, `u`.`foto` AS `foto`, `u`.`fecha_nac` AS `fecha_nac`, `p`.`id_perfil` AS `id_perfil`, `p`.`genero` AS `genero`, `p`.`ocupacion` AS `ocupacion`, `p`.`antiguedad` AS `antiguedad`, `p`.`frecuencia` AS `frecuencia`, `pa`.`patron_aparicion` AS `patron_aparicion`, `pa`.`orden_aparicion` AS `orden_aparicion`, `p`.`inicio_momento_dia` AS `inicio_momento_dia`, `p`.`duracion` AS `duracion`, `p`.`intensidad` AS `intensidad`, `p`.`conoce_fase_prodromica` AS `conoce_fase_prodromica`, `p`.`horas_sueno` AS `horas_sueno`, `p`.`nivel_estres` AS `nivel_estres`, `cc`.`consume_cafeina` AS `consume_cafeina`, `cc`.`cantidad_cafeina` AS `cantidad_cafeina`, `p`.`fecha_completado` AS `fecha_completado`, (select group_concat(`c`.`codigo` order by `c`.`id_condicion` ASC separator ',') from (`perfil_condicion` `pc` join `condicion_medica` `c` on(`c`.`id_condicion` = `pc`.`id_condicion`)) where `pc`.`id_perfil` = `p`.`id_perfil`) AS `condiciones` FROM (((`usuario` `u` left join `perfil` `p` on(`p`.`id_usuario` = `u`.`id_usuario`)) left join `patron_aparicion` `pa` on(`pa`.`id_patron` = `p`.`id_patron`)) left join `consumo_cafeina` `cc` on(`cc`.`id_consumo_cafeina` = `p`.`id_consumo_cafeina`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_resumen_30dias`
--
DROP TABLE IF EXISTS `v_resumen_30dias`;

DROP VIEW IF EXISTS `v_resumen_30dias`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_resumen_30dias`  AS SELECT `u`.`id_usuario` AS `id_usuario`, (select count(0) from `episodio_migrana` `e` where `e`.`id_usuario` = `u`.`id_usuario` and `e`.`fecha_inicio` >= curdate() - interval 30 day) AS `episodios_30d`, (select count(0) from `registro_sintoma` `r` where `r`.`id_usuario` = `u`.`id_usuario` and `r`.`fecha` >= curdate() - interval 30 day) AS `prodromicos_30d`, (select round(avg(`h`.`intensidad`),1) from `v_historial` `h` where `h`.`id_usuario` = `u`.`id_usuario` and `h`.`fecha_hora` >= current_timestamp() - interval 30 day) AS `intensidad_media_30d`, (select count(0) from `episodio_migrana` `e2` where `e2`.`id_usuario` = `u`.`id_usuario`) + (select count(0) from `registro_sintoma` `r2` where `r2`.`id_usuario` = `u`.`id_usuario`) AS `registros_totales` FROM `usuario` AS `u` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_sintomas_frecuentes`
--
DROP TABLE IF EXISTS `v_sintomas_frecuentes`;

DROP VIEW IF EXISTS `v_sintomas_frecuentes`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_sintomas_frecuentes`  AS SELECT `q`.`id_usuario` AS `id_usuario`, `s`.`codigo` AS `codigo`, `s`.`nombre_sintoma` AS `nombre_sintoma`, count(0) AS `veces` FROM ((select `r`.`id_usuario` AS `id_usuario`,`d`.`id_sintoma` AS `id_sintoma` from (`registro_sintoma` `r` join `detalle_registro_sintoma` `d` on(`d`.`id_registro` = `r`.`id_registro`)) union all select `e`.`id_usuario` AS `id_usuario`,`es`.`id_sintoma` AS `id_sintoma` from (`episodio_migrana` `e` join `episodio_sintoma` `es` on(`es`.`id_episodio` = `e`.`id_episodio`))) `q` join `sintoma_prodromico` `s` on(`s`.`id_sintoma` = `q`.`id_sintoma`)) GROUP BY `q`.`id_usuario`, `s`.`codigo`, `s`.`nombre_sintoma` ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `condicion_medica`
--
ALTER TABLE `condicion_medica`
  ADD PRIMARY KEY (`id_condicion`),
  ADD UNIQUE KEY `uq_condicion_codigo` (`codigo`);

--
-- Indices de la tabla `consumo_cafeina`
--
ALTER TABLE `consumo_cafeina`
  ADD PRIMARY KEY (`id_consumo_cafeina`),
  ADD UNIQUE KEY `uq_cafeina_cantidad` (`cantidad_cafeina`);

--
-- Indices de la tabla `desencadenante`
--
ALTER TABLE `desencadenante`
  ADD PRIMARY KEY (`id_desencadenante`),
  ADD UNIQUE KEY `uq_desencadenante_codigo` (`codigo`);

--
-- Indices de la tabla `detalle_registro_sintoma`
--
ALTER TABLE `detalle_registro_sintoma`
  ADD PRIMARY KEY (`id_registro`,`id_sintoma`),
  ADD KEY `fk_drs_sintoma` (`id_sintoma`);

--
-- Indices de la tabla `episodio_desencadenante`
--
ALTER TABLE `episodio_desencadenante`
  ADD PRIMARY KEY (`id_episodio`,`id_desencadenante`),
  ADD KEY `fk_ed_desenc` (`id_desencadenante`);

--
-- Indices de la tabla `episodio_medicacion`
--
ALTER TABLE `episodio_medicacion`
  ADD PRIMARY KEY (`id_episodio`);

--
-- Indices de la tabla `episodio_migrana`
--
ALTER TABLE `episodio_migrana`
  ADD PRIMARY KEY (`id_episodio`),
  ADD KEY `idx_ep_usuario_fecha` (`id_usuario`,`fecha_inicio`,`hora_inicio`);

--
-- Indices de la tabla `episodio_sintoma`
--
ALTER TABLE `episodio_sintoma`
  ADD PRIMARY KEY (`id_episodio`,`id_sintoma`),
  ADD KEY `fk_es_sintoma` (`id_sintoma`);

--
-- Indices de la tabla `notificacion`
--
ALTER TABLE `notificacion`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_notif_usuario` (`id_usuario`,`estado`,`fecha_hora`),
  ADD KEY `fk_notif_rec` (`id_recordatorio`);

--
-- Indices de la tabla `patron_aparicion`
--
ALTER TABLE `patron_aparicion`
  ADD PRIMARY KEY (`id_patron`),
  ADD UNIQUE KEY `uq_patron_orden` (`orden_aparicion`);

--
-- Indices de la tabla `perfil`
--
ALTER TABLE `perfil`
  ADD PRIMARY KEY (`id_perfil`),
  ADD UNIQUE KEY `uq_perfil_usuario` (`id_usuario`),
  ADD KEY `fk_perfil_patron` (`id_patron`),
  ADD KEY `fk_perfil_cafeina` (`id_consumo_cafeina`);

--
-- Indices de la tabla `perfil_condicion`
--
ALTER TABLE `perfil_condicion`
  ADD PRIMARY KEY (`id_perfil`,`id_condicion`),
  ADD KEY `fk_pc_condicion` (`id_condicion`);

--
-- Indices de la tabla `recordatorio`
--
ALTER TABLE `recordatorio`
  ADD PRIMARY KEY (`id_recordatorio`),
  ADD UNIQUE KEY `uq_recordatorio_usuario_frec` (`id_usuario`,`frecuencia`);

--
-- Indices de la tabla `registro_sintoma`
--
ALTER TABLE `registro_sintoma`
  ADD PRIMARY KEY (`id_registro`),
  ADD KEY `idx_reg_usuario_fecha` (`id_usuario`,`fecha`,`hora`);

--
-- Indices de la tabla `reporte`
--
ALTER TABLE `reporte`
  ADD PRIMARY KEY (`id_reporte`),
  ADD KEY `idx_reporte_usuario` (`id_usuario`,`fecha_generacion`);

--
-- Indices de la tabla `reporte_episodio`
--
ALTER TABLE `reporte_episodio`
  ADD PRIMARY KEY (`id_reporte`,`id_episodio`),
  ADD KEY `fk_re_episodio` (`id_episodio`);

--
-- Indices de la tabla `reporte_registro`
--
ALTER TABLE `reporte_registro`
  ADD PRIMARY KEY (`id_reporte`,`id_registro`),
  ADD KEY `fk_rr_registro` (`id_registro`);

--
-- Indices de la tabla `sintoma_prodromico`
--
ALTER TABLE `sintoma_prodromico`
  ADD PRIMARY KEY (`id_sintoma`),
  ADD UNIQUE KEY `uq_sintoma_codigo` (`codigo`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuario_correo` (`correo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `condicion_medica`
--
ALTER TABLE `condicion_medica`
  MODIFY `id_condicion` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `consumo_cafeina`
--
ALTER TABLE `consumo_cafeina`
  MODIFY `id_consumo_cafeina` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `desencadenante`
--
ALTER TABLE `desencadenante`
  MODIFY `id_desencadenante` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `episodio_migrana`
--
ALTER TABLE `episodio_migrana`
  MODIFY `id_episodio` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificacion`
--
ALTER TABLE `notificacion`
  MODIFY `id_notificacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `patron_aparicion`
--
ALTER TABLE `patron_aparicion`
  MODIFY `id_patron` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `perfil`
--
ALTER TABLE `perfil`
  MODIFY `id_perfil` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `recordatorio`
--
ALTER TABLE `recordatorio`
  MODIFY `id_recordatorio` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `registro_sintoma`
--
ALTER TABLE `registro_sintoma`
  MODIFY `id_registro` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reporte`
--
ALTER TABLE `reporte`
  MODIFY `id_reporte` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sintoma_prodromico`
--
ALTER TABLE `sintoma_prodromico`
  MODIFY `id_sintoma` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_registro_sintoma`
--
ALTER TABLE `detalle_registro_sintoma`
  ADD CONSTRAINT `fk_drs_registro` FOREIGN KEY (`id_registro`) REFERENCES `registro_sintoma` (`id_registro`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_drs_sintoma` FOREIGN KEY (`id_sintoma`) REFERENCES `sintoma_prodromico` (`id_sintoma`);

--
-- Filtros para la tabla `episodio_desencadenante`
--
ALTER TABLE `episodio_desencadenante`
  ADD CONSTRAINT `fk_ed_desenc` FOREIGN KEY (`id_desencadenante`) REFERENCES `desencadenante` (`id_desencadenante`),
  ADD CONSTRAINT `fk_ed_episodio` FOREIGN KEY (`id_episodio`) REFERENCES `episodio_migrana` (`id_episodio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `episodio_medicacion`
--
ALTER TABLE `episodio_medicacion`
  ADD CONSTRAINT `fk_em_episodio` FOREIGN KEY (`id_episodio`) REFERENCES `episodio_migrana` (`id_episodio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `episodio_migrana`
--
ALTER TABLE `episodio_migrana`
  ADD CONSTRAINT `fk_ep_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `episodio_sintoma`
--
ALTER TABLE `episodio_sintoma`
  ADD CONSTRAINT `fk_es_episodio` FOREIGN KEY (`id_episodio`) REFERENCES `episodio_migrana` (`id_episodio`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_es_sintoma` FOREIGN KEY (`id_sintoma`) REFERENCES `sintoma_prodromico` (`id_sintoma`);

--
-- Filtros para la tabla `notificacion`
--
ALTER TABLE `notificacion`
  ADD CONSTRAINT `fk_notif_rec` FOREIGN KEY (`id_recordatorio`) REFERENCES `recordatorio` (`id_recordatorio`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `perfil`
--
ALTER TABLE `perfil`
  ADD CONSTRAINT `fk_perfil_cafeina` FOREIGN KEY (`id_consumo_cafeina`) REFERENCES `consumo_cafeina` (`id_consumo_cafeina`),
  ADD CONSTRAINT `fk_perfil_patron` FOREIGN KEY (`id_patron`) REFERENCES `patron_aparicion` (`id_patron`),
  ADD CONSTRAINT `fk_perfil_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `perfil_condicion`
--
ALTER TABLE `perfil_condicion`
  ADD CONSTRAINT `fk_pc_condicion` FOREIGN KEY (`id_condicion`) REFERENCES `condicion_medica` (`id_condicion`),
  ADD CONSTRAINT `fk_pc_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id_perfil`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recordatorio`
--
ALTER TABLE `recordatorio`
  ADD CONSTRAINT `fk_rec_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `registro_sintoma`
--
ALTER TABLE `registro_sintoma`
  ADD CONSTRAINT `fk_reg_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reporte`
--
ALTER TABLE `reporte`
  ADD CONSTRAINT `fk_reporte_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reporte_episodio`
--
ALTER TABLE `reporte_episodio`
  ADD CONSTRAINT `fk_re_episodio` FOREIGN KEY (`id_episodio`) REFERENCES `episodio_migrana` (`id_episodio`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_re_reporte` FOREIGN KEY (`id_reporte`) REFERENCES `reporte` (`id_reporte`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reporte_registro`
--
ALTER TABLE `reporte_registro`
  ADD CONSTRAINT `fk_rr_registro` FOREIGN KEY (`id_registro`) REFERENCES `registro_sintoma` (`id_registro`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rr_reporte` FOREIGN KEY (`id_reporte`) REFERENCES `reporte` (`id_reporte`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
