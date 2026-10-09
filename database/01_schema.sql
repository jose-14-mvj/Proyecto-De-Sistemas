-- =====================================================================
-- MigraSense · 01_schema.sql  (MariaDB 10.4 / XAMPP)
-- ATENCIÓN: borra la base migrasense anterior y todo su contenido.
-- =====================================================================
DROP DATABASE IF EXISTS migrasense;
CREATE DATABASE migrasense CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE migrasense;

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