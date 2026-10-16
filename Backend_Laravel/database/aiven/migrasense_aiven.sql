-- =====================================================================
-- MigraSense · migrasense_aiven.sql  (MySQL 8.4 · Aiven)
-- Generado a partir de database/01..05_*.sql (misma estructura que
-- migrasense_corregido.sql), adaptado a Aiven:
--   * sin DEFINER (los objetos quedan a nombre de avnadmin)
--   * sin DROP DATABASE: crea `migrasense` solo si no existe
--   * sql_mode y collation fijados en la sesión (Aiven usa ANSI por defecto)
-- Importar con:  php artisan migrasense:importar-sql database/aiven/migrasense_aiven.sql
-- Es para una base VACÍA: si ya existen las tablas, se detiene en el primer CREATE TABLE.
-- =====================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
CREATE DATABASE IF NOT EXISTS migrasense CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE migrasense;

-- ===================== 01_schema.sql =====================
-- =====================================================================
-- MigraSense · 01_schema.sql  (MariaDB 10.4 / XAMPP)
-- =====================================================================

-- ---------------------------------------------------------------------
-- USUARIO
-- ---------------------------------------------------------------------
CREATE TABLE usuario (
  id_usuario     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nombres        VARCHAR(60)   NOT NULL,
  paterno        VARCHAR(40)   NULL,
  materno        VARCHAR(40)   NULL,
  correo         VARCHAR(60)   NOT NULL,
  contrasena     VARCHAR(255)  NOT NULL COMMENT 'HASH bcrypt, nunca texto plano',
  fecha_nac      DATE          NULL,
  foto           MEDIUMTEXT    NULL COMMENT 'data URL JPEG 256px',
  activo         TINYINT(1)    NOT NULL DEFAULT 1,
  fecha_registro DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso  DATETIME      NULL,
  PRIMARY KEY (id_usuario),
  CONSTRAINT uq_usuario_correo UNIQUE (correo),
  CONSTRAINT ck_usuario_nombres CHECK (CHAR_LENGTH(TRIM(nombres)) >= 2)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Catálogos que salen de PERFIL (normalización 1FN / 3FN)
-- ---------------------------------------------------------------------
CREATE TABLE condicion_medica (
  id_condicion     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo           VARCHAR(30)      NOT NULL,
  condicion_medica VARCHAR(80)      NOT NULL,
  PRIMARY KEY (id_condicion),
  CONSTRAINT uq_condicion_codigo UNIQUE (codigo)
) ENGINE=InnoDB;

-- orden_aparicion determina patron_aparicion, por eso es clave candidata (UNIQUE)
CREATE TABLE patron_aparicion (
  id_patron        TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patron_aparicion ENUM('si','no') NOT NULL,
  orden_aparicion  ENUM('2_3_meses','4_5_meses','6_7_meses','8_9_meses','9_10_meses') NULL,
  PRIMARY KEY (id_patron),
  CONSTRAINT uq_patron_orden UNIQUE (orden_aparicion),
  CONSTRAINT ck_patron CHECK (
    (patron_aparicion = 'si' AND orden_aparicion IS NOT NULL) OR
    (patron_aparicion = 'no' AND orden_aparicion IS NULL))
) ENGINE=InnoDB;

-- cantidad_cafeina determina consume_cafeina, por eso es clave candidata (UNIQUE)
CREATE TABLE consumo_cafeina (
  id_consumo_cafeina TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  consume_cafeina    ENUM('si','no') NOT NULL,
  cantidad_cafeina   ENUM('1','2_3','4_mas') NULL,
  PRIMARY KEY (id_consumo_cafeina),
  CONSTRAINT uq_cafeina_cantidad UNIQUE (cantidad_cafeina),
  CONSTRAINT ck_cafeina CHECK (
    (consume_cafeina = 'si' AND cantidad_cafeina IS NOT NULL) OR
    (consume_cafeina = 'no' AND cantidad_cafeina IS NULL))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PERFIL (cuestionario) · 1:1 con USUARIO
-- ---------------------------------------------------------------------
CREATE TABLE perfil (
  id_perfil              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  id_usuario             INT UNSIGNED     NOT NULL,
  genero                 ENUM('femenino','masculino','otro') NOT NULL,
  ocupacion              ENUM('estudiante','oficina_virtual','fisico_campo',
                              'turnos_nocturno','hogar','sin_ocupacion') NOT NULL,
  antiguedad             ENUM('menos_1_anio','1_3_anios','4_10_anios','mas_10_anios') NOT NULL,
  frecuencia             ENUM('menos_1_mes','1_3_mes','1_2_semana',
                              '3_mas_semana','casi_diario') NOT NULL,
  inicio_momento_dia     ENUM('madrugada','manana','tarde','noche','sin_horario') NOT NULL,
  duracion               ENUM('menos_4h','4_12h','12_24h','1_3d','mas_3d') NOT NULL,
  intensidad             ENUM('leve','moderada','intensa','muy_intensa') NOT NULL,
  conoce_fase_prodromica ENUM('si','no','no_seguro') NOT NULL,
  horas_sueno            ENUM('menos_5','5_6','7_8','mas_8') NOT NULL,
  nivel_estres           ENUM('bajo','moderado','alto','muy_alto') NOT NULL,
  id_patron              TINYINT UNSIGNED NOT NULL,
  id_consumo_cafeina     TINYINT UNSIGNED NOT NULL,
  version_cuestionario   TINYINT UNSIGNED NOT NULL DEFAULT 2,
  fecha_completado       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_perfil),
  CONSTRAINT uq_perfil_usuario UNIQUE (id_usuario),
  CONSTRAINT fk_perfil_usuario  FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  CONSTRAINT fk_perfil_patron   FOREIGN KEY (id_patron)
    REFERENCES patron_aparicion (id_patron),
  CONSTRAINT fk_perfil_cafeina  FOREIGN KEY (id_consumo_cafeina)
    REFERENCES consumo_cafeina (id_consumo_cafeina)
) ENGINE=InnoDB;

-- PERFIL (M) : (N) CONDICION_MEDICA
CREATE TABLE perfil_condicion (
  id_perfil    INT UNSIGNED     NOT NULL,
  id_condicion TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id_perfil, id_condicion),
  CONSTRAINT fk_pc_perfil    FOREIGN KEY (id_perfil)    REFERENCES perfil (id_perfil) ON DELETE CASCADE,
  CONSTRAINT fk_pc_condicion FOREIGN KEY (id_condicion) REFERENCES condicion_medica (id_condicion)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Catálogos: síntoma prodrómico y desencadenante
-- ---------------------------------------------------------------------
CREATE TABLE sintoma_prodromico (
  id_sintoma     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo         VARCHAR(30)  NOT NULL,
  nombre_sintoma VARCHAR(80)  NOT NULL,
  descripcion    VARCHAR(255) NULL,
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id_sintoma),
  CONSTRAINT uq_sintoma_codigo UNIQUE (codigo)
) ENGINE=InnoDB;

CREATE TABLE desencadenante (
  id_desencadenante TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo            VARCHAR(30) NOT NULL,
  nombre            VARCHAR(80) NOT NULL,
  activo            TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (id_desencadenante),
  CONSTRAINT uq_desencadenante_codigo UNIQUE (codigo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- REGISTRO_SINTOMA  (USUARIO 1 : N REGISTRO)  +  incluye (M:N con SINTOMA)
-- ---------------------------------------------------------------------
CREATE TABLE registro_sintoma (
  id_registro    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_usuario     INT UNSIGNED     NOT NULL,
  fecha          DATE             NOT NULL,
  hora           TIME             NOT NULL,
  intensidad     TINYINT UNSIGNED NOT NULL,
  observacion    VARCHAR(500)     NULL,
  fecha_creacion DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_registro),
  KEY idx_reg_usuario_fecha (id_usuario, fecha, hora),
  CONSTRAINT fk_reg_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  CONSTRAINT ck_reg_intensidad CHECK (intensidad BETWEEN 1 AND 10)
) ENGINE=InnoDB;

CREATE TABLE detalle_registro_sintoma (
  id_registro BIGINT UNSIGNED  NOT NULL,
  id_sintoma  TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id_registro, id_sintoma),
  CONSTRAINT fk_drs_registro FOREIGN KEY (id_registro) REFERENCES registro_sintoma (id_registro) ON DELETE CASCADE,
  CONSTRAINT fk_drs_sintoma  FOREIGN KEY (id_sintoma)  REFERENCES sintoma_prodromico (id_sintoma)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- EPISODIO_MIGRANA  (USUARIO 1 : N EPISODIO, única FK = id_usuario)
-- ---------------------------------------------------------------------
CREATE TABLE episodio_migrana (
  id_episodio    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_usuario     INT UNSIGNED     NOT NULL,
  fecha_inicio   DATE             NOT NULL,
  hora_inicio    TIME             NOT NULL,
  duracion       ENUM('menos_4h','4_24h','1_3d','mas_3d') NOT NULL,
  nivel_dolor    TINYINT UNSIGNED NOT NULL,
  notas          VARCHAR(500)     NULL,
  fecha_creacion DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_episodio),
  KEY idx_ep_usuario_fecha (id_usuario, fecha_inicio, hora_inicio),
  CONSTRAINT fk_ep_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  CONSTRAINT ck_ep_dolor CHECK (nivel_dolor BETWEEN 1 AND 10)
) ENGINE=InnoDB;

-- Si existe la fila, el episodio tuvo medicación (medicamento puede quedar sin nombre)
CREATE TABLE episodio_medicacion (
  id_episodio BIGINT UNSIGNED NOT NULL,
  medicamento VARCHAR(80)     NULL,
  PRIMARY KEY (id_episodio),
  CONSTRAINT fk_em_episodio FOREIGN KEY (id_episodio)
    REFERENCES episodio_migrana (id_episodio) ON DELETE CASCADE
) ENGINE=InnoDB;

-- presenta (M:N)
CREATE TABLE episodio_sintoma (
  id_episodio BIGINT UNSIGNED  NOT NULL,
  id_sintoma  TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id_episodio, id_sintoma),
  CONSTRAINT fk_es_episodio FOREIGN KEY (id_episodio) REFERENCES episodio_migrana (id_episodio) ON DELETE CASCADE,
  CONSTRAINT fk_es_sintoma  FOREIGN KEY (id_sintoma)  REFERENCES sintoma_prodromico (id_sintoma)
) ENGINE=InnoDB;

CREATE TABLE episodio_desencadenante (
  id_episodio       BIGINT UNSIGNED  NOT NULL,
  id_desencadenante TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id_episodio, id_desencadenante),
  CONSTRAINT fk_ed_episodio FOREIGN KEY (id_episodio) REFERENCES episodio_migrana (id_episodio) ON DELETE CASCADE,
  CONSTRAINT fk_ed_desenc   FOREIGN KEY (id_desencadenante) REFERENCES desencadenante (id_desencadenante)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- RECORDATORIO · dia_semana: 0=domingo ... 6=sábado (como Date.getDay())
-- ---------------------------------------------------------------------
CREATE TABLE recordatorio (
  id_recordatorio     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario          INT UNSIGNED NOT NULL,
  frecuencia          ENUM('diaria','semanal') NOT NULL,
  dia_semana          TINYINT UNSIGNED NULL,
  hora_configurada    TIME         NOT NULL,
  activo              TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_actualizacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_recordatorio),
  CONSTRAINT uq_recordatorio_usuario_frec UNIQUE (id_usuario, frecuencia),
  CONSTRAINT fk_rec_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  CONSTRAINT ck_rec_dia CHECK (
    (frecuencia = 'diaria'  AND dia_semana IS NULL) OR
    (frecuencia = 'semanal' AND dia_semana BETWEEN 0 AND 6))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- NOTIFICACION
-- ---------------------------------------------------------------------
CREATE TABLE notificacion (
  id_notificacion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario      INT UNSIGNED    NOT NULL,
  id_recordatorio INT UNSIGNED    NULL,
  tipo            ENUM('recordatorio_diario','resumen_semanal','alerta_riesgo','sistema') NOT NULL,
  mensaje         VARCHAR(255)    NOT NULL,
  estado          ENUM('pendiente','enviada','leida','descartada') NOT NULL DEFAULT 'pendiente',
  fecha_hora      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_notificacion),
  KEY idx_notif_usuario (id_usuario, estado, fecha_hora),
  CONSTRAINT fk_notif_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  CONSTRAINT fk_notif_rec FOREIGN KEY (id_recordatorio) REFERENCES recordatorio (id_recordatorio) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- MODELO_ML · memoria del aprendizaje del modelo de machine learning
-- ---------------------------------------------------------------------
CREATE TABLE modelo_ml (
  id_modelo        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  version          VARCHAR(40)     NULL,
  estado           VARCHAR(15)     NOT NULL DEFAULT 'en_curso',
  auc              DECIMAL(5,3)    NULL,
  auc_anterior     DECIMAL(5,3)    NULL,
  pacientes_reales INT UNSIGNED    NULL,
  filas_reales     INT UNSIGNED    NULL,
  registros_usados INT UNSIGNED    NOT NULL DEFAULT 0,
  motivo           VARCHAR(255)    NULL,
  sha256           CHAR(64)        NULL,
  tamano_bytes     INT UNSIGNED    NULL,
  archivo          LONGTEXT        NULL COMMENT 'modelo (joblib) en base64',
  reporte          LONGTEXT        NULL COMMENT 'lo aprendido en JSON legible (/metricas)',
  fecha_inicio     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_fin        DATETIME        NULL,
  PRIMARY KEY (id_modelo),
  KEY idx_modelo_ml_estado (estado, id_modelo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- RECUPERACION_CLAVE · códigos de "¿Olvidaste tu contraseña?" (solo la huella HMAC)
-- ---------------------------------------------------------------------
CREATE TABLE recuperacion_clave (
  id_recuperacion BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_usuario      INT UNSIGNED     NOT NULL,
  codigo_hash     CHAR(64)         NOT NULL,
  intentos        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fecha_creacion  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_expira    DATETIME         NOT NULL,
  fecha_uso       DATETIME         NULL,
  ip              VARCHAR(45)      NULL,
  PRIMARY KEY (id_recuperacion),
  KEY idx_recuperacion_usuario (id_usuario, id_recuperacion),
  CONSTRAINT fk_recuperacion_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- REPORTE · resume (con registros) y abarca (con episodios)
-- ---------------------------------------------------------------------
CREATE TABLE reporte (
  id_reporte       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario       INT UNSIGNED    NOT NULL,
  formato          ENUM('pdf','txt') NOT NULL,
  periodo_inicio   DATE            NOT NULL,
  periodo_fin      DATE            NOT NULL,
  etiqueta         VARCHAR(30)     NULL,
  fecha_generacion DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_reporte),
  KEY idx_reporte_usuario (id_usuario, fecha_generacion),
  CONSTRAINT fk_reporte_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  CONSTRAINT ck_reporte_periodo CHECK (periodo_fin >= periodo_inicio)
) ENGINE=InnoDB;

CREATE TABLE reporte_registro (
  id_reporte  BIGINT UNSIGNED NOT NULL,
  id_registro BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id_reporte, id_registro),
  CONSTRAINT fk_rr_reporte  FOREIGN KEY (id_reporte)  REFERENCES reporte (id_reporte) ON DELETE CASCADE,
  CONSTRAINT fk_rr_registro FOREIGN KEY (id_registro) REFERENCES registro_sintoma (id_registro) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reporte_episodio (
  id_reporte  BIGINT UNSIGNED NOT NULL,
  id_episodio BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id_reporte, id_episodio),
  CONSTRAINT fk_re_reporte  FOREIGN KEY (id_reporte)  REFERENCES reporte (id_reporte) ON DELETE CASCADE,
  CONSTRAINT fk_re_episodio FOREIGN KEY (id_episodio) REFERENCES episodio_migrana (id_episodio) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== 02_catalogos.sql =====================

INSERT INTO condicion_medica (codigo, condicion_medica) VALUES
 ('ansiedad_depresion','Ansiedad o depresión'),
 ('hipertension','Hipertensión'),
 ('trastornos_sueno','Trastornos del sueño'),
 ('hormonal','Alteraciones hormonales o ciclo irregular'),
 ('alergias_sinusitis','Alergias o sinusitis crónica'),
 ('ninguna','Ninguna'),
 ('otra','Otra');

INSERT INTO patron_aparicion (patron_aparicion, orden_aparicion) VALUES
 ('no', NULL),
 ('si', '2_3_meses'),
 ('si', '4_5_meses'),
 ('si', '6_7_meses'),
 ('si', '8_9_meses'),
 ('si', '9_10_meses');

INSERT INTO consumo_cafeina (consume_cafeina, cantidad_cafeina) VALUES
 ('no', NULL),
 ('si', '1'),
 ('si', '2_3'),
 ('si', '4_mas');

INSERT INTO sintoma_prodromico (codigo, nombre_sintoma, descripcion) VALUES
 ('luz','Sensibilidad a la luz','La luz normal resulta molesta'),
 ('sonido','Sensibilidad al sonido','Los ruidos habituales resultan molestos'),
 ('fatiga','Fatiga o bostezos frecuentes','Cansancio inusual antes del dolor'),
 ('cuello','Dolor o rigidez de cuello','Tensión en cuello u hombros'),
 ('antojos','Antojos de comida','Ganas intensas de ciertos alimentos'),
 ('humor','Cambios de humor / irritabilidad','Variaciones del estado de ánimo'),
 ('concentracion','Dificultad para concentrarse','Cuesta mantener la atención'),
 ('nauseas','Náuseas','Malestar estomacal'),
 ('vision','Destellos o visión borrosa (aura)','Alteraciones visuales previas al dolor'),
 ('sed','Sed excesiva','Sed inusual'),
 ('retencion','Retención de líquidos','Hinchazón o sensación de retención'),
 ('bostezos','Bostezos excesivos','Bostezos repetidos sin sueño'),
 ('auras','Auras','Alteraciones visuales o sensitivas pasajeras (destellos, puntos ciegos, líneas en zigzag, hormigueo) que suelen durar de 5 a 60 minutos, antes o durante el dolor.');

INSERT INTO desencadenante (codigo, nombre) VALUES
 ('estres','Estrés'),
 ('sueno','Falta de sueño'),
 ('alimentos','Ciertos alimentos'),
 ('hormonal','Cambios hormonales'),
 ('deshidratacion','Deshidratación'),
 ('clima','Cambios de clima/presión'),
 ('pantallas','Luces o pantallas'),
 ('ayuno','Ayuno prolongado'),
 ('alcohol','Alcohol'),
 ('olores','Olores fuertes'),
 ('ejercicio','Ejercicio intenso'),
 ('ruido','Ruido excesivo');

-- ===================== 03_vistas.sql =====================

-- Historial unificado (prodrómicos + episodios)
CREATE OR REPLACE VIEW v_historial AS
SELECT r.id_usuario,
       'prodromico'                       AS tipo,
       r.id_registro                      AS id,
       'Síntomas prodrómicos'             AS etiqueta,
       TIMESTAMP(r.fecha, r.hora)         AS fecha_hora,
       NULL                               AS inicio,
       r.intensidad                       AS intensidad,
       NULL                               AS duracion,
       NULL                               AS medicacion,
       NULL                               AS medicamento,
       r.observacion                      AS notas,
       (SELECT GROUP_CONCAT(s.nombre_sintoma ORDER BY s.id_sintoma SEPARATOR ', ')
          FROM detalle_registro_sintoma d
          JOIN sintoma_prodromico s ON s.id_sintoma = d.id_sintoma
         WHERE d.id_registro = r.id_registro) AS sintomas,
       NULL                               AS desencadenantes
  FROM registro_sintoma r
UNION ALL
SELECT e.id_usuario,
       'episodio',
       e.id_episodio,
       'Episodio de migraña',
       TIMESTAMP(e.fecha_inicio, e.hora_inicio),
       TIMESTAMP(e.fecha_inicio, e.hora_inicio),
       e.nivel_dolor,
       e.duracion,
       (SELECT COUNT(*) FROM episodio_medicacion m WHERE m.id_episodio = e.id_episodio),
       (SELECT m.medicamento FROM episodio_medicacion m WHERE m.id_episodio = e.id_episodio),
       e.notas,
       (SELECT GROUP_CONCAT(s.nombre_sintoma ORDER BY s.id_sintoma SEPARATOR ', ')
          FROM episodio_sintoma x
          JOIN sintoma_prodromico s ON s.id_sintoma = x.id_sintoma
         WHERE x.id_episodio = e.id_episodio),
       (SELECT GROUP_CONCAT(t.nombre ORDER BY t.id_desencadenante SEPARATOR ', ')
          FROM episodio_desencadenante y
          JOIN desencadenante t ON t.id_desencadenante = y.id_desencadenante
         WHERE y.id_episodio = e.id_episodio)
  FROM episodio_migrana e;

-- Tarjetas de estadísticas
CREATE OR REPLACE VIEW v_resumen_30dias AS
SELECT u.id_usuario,
       (SELECT COUNT(*) FROM episodio_migrana e
         WHERE e.id_usuario = u.id_usuario
           AND e.fecha_inicio >= CURDATE() - INTERVAL 30 DAY)           AS episodios_30d,
       (SELECT COUNT(*) FROM registro_sintoma r
         WHERE r.id_usuario = u.id_usuario
           AND r.fecha >= CURDATE() - INTERVAL 30 DAY)                  AS prodromicos_30d,
       (SELECT ROUND(AVG(h.intensidad), 1) FROM v_historial h
         WHERE h.id_usuario = u.id_usuario
           AND h.fecha_hora >= NOW() - INTERVAL 30 DAY)                 AS intensidad_media_30d,
       ((SELECT COUNT(*) FROM episodio_migrana e2 WHERE e2.id_usuario = u.id_usuario) +
        (SELECT COUNT(*) FROM registro_sintoma r2 WHERE r2.id_usuario = u.id_usuario)) AS registros_totales
  FROM usuario u;

-- Episodios por semana (la semana inicia el lunes)
CREATE OR REPLACE VIEW v_episodios_semana AS
SELECT id_usuario,
       DATE_SUB(fecha_inicio, INTERVAL WEEKDAY(fecha_inicio) DAY) AS semana_inicio,
       COUNT(*)                   AS episodios,
       ROUND(AVG(nivel_dolor), 1) AS dolor_medio
  FROM episodio_migrana
 GROUP BY id_usuario, DATE_SUB(fecha_inicio, INTERVAL WEEKDAY(fecha_inicio) DAY);

-- Patrones
CREATE OR REPLACE VIEW v_desencadenantes_frecuentes AS
SELECT e.id_usuario, t.codigo, t.nombre, COUNT(*) AS veces
  FROM episodio_migrana e
  JOIN episodio_desencadenante x ON x.id_episodio = e.id_episodio
  JOIN desencadenante t ON t.id_desencadenante = x.id_desencadenante
 GROUP BY e.id_usuario, t.codigo, t.nombre;

CREATE OR REPLACE VIEW v_sintomas_frecuentes AS
SELECT q.id_usuario, s.codigo, s.nombre_sintoma, COUNT(*) AS veces
  FROM (SELECT r.id_usuario, d.id_sintoma
          FROM registro_sintoma r
          JOIN detalle_registro_sintoma d ON d.id_registro = r.id_registro
        UNION ALL
        SELECT e.id_usuario, es.id_sintoma
          FROM episodio_migrana e
          JOIN episodio_sintoma es ON es.id_episodio = e.id_episodio) q
  JOIN sintoma_prodromico s ON s.id_sintoma = q.id_sintoma
 GROUP BY q.id_usuario, s.codigo, s.nombre_sintoma;

-- Perfil completo: reconstruye las columnas que usa api/conexion.php
CREATE OR REPLACE VIEW v_perfil_completo AS
SELECT u.id_usuario, u.nombres, u.paterno, u.materno, u.correo, u.foto, u.fecha_nac,
       p.id_perfil, p.genero, p.ocupacion, p.antiguedad, p.frecuencia,
       pa.patron_aparicion, pa.orden_aparicion,
       p.inicio_momento_dia, p.duracion, p.intensidad, p.conoce_fase_prodromica,
       p.horas_sueno, p.nivel_estres,
       cc.consume_cafeina, cc.cantidad_cafeina,
       p.fecha_completado,
       (SELECT GROUP_CONCAT(c.codigo ORDER BY c.id_condicion SEPARATOR ',')
          FROM perfil_condicion pc
          JOIN condicion_medica c ON c.id_condicion = pc.id_condicion
         WHERE pc.id_perfil = p.id_perfil) AS condiciones
  FROM usuario u
  LEFT JOIN perfil p            ON p.id_usuario = u.id_usuario
  LEFT JOIN patron_aparicion pa ON pa.id_patron = p.id_patron
  LEFT JOIN consumo_cafeina cc  ON cc.id_consumo_cafeina = p.id_consumo_cafeina;

-- ===================== 04_procedimientos.sql =====================
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

-- ===================== 05_triggers.sql =====================
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
