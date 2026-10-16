-- =====================================================================
-- MigraSense · esquema SOLO PARA PRUEBAS (SQLite en memoria)
-- Copia simplificada de database/01_schema.sql y 03_vistas.sql.
-- Los procedimientos almacenados no existen en SQLite: las pruebas los
-- reemplazan con tests/Support/ServiciosFalsos.php.
-- ahora() y hoy() son funciones que registra tests/TestCase.php con la
-- hora de Bolivia (como hace la conexión MySQL con time_zone -04:00).
-- =====================================================================

CREATE TABLE usuario (
  id_usuario     INTEGER PRIMARY KEY AUTOINCREMENT,
  nombres        VARCHAR(60)  NOT NULL,
  paterno        VARCHAR(40)  NULL,
  materno        VARCHAR(40)  NULL,
  correo         VARCHAR(60)  NOT NULL UNIQUE,
  contrasena     VARCHAR(255) NOT NULL,
  fecha_nac      DATE         NULL,
  foto           TEXT         NULL,
  activo         TINYINT      NOT NULL DEFAULT 1,
  fecha_registro DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso  DATETIME     NULL,
  alerta_riesgo  TINYINT      NOT NULL DEFAULT 1
);

CREATE TABLE condicion_medica (
  id_condicion     INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo           VARCHAR(30) NOT NULL UNIQUE,
  condicion_medica VARCHAR(80) NOT NULL
);

CREATE TABLE patron_aparicion (
  id_patron        INTEGER PRIMARY KEY AUTOINCREMENT,
  patron_aparicion VARCHAR(2)  NOT NULL,
  orden_aparicion  VARCHAR(12) NULL UNIQUE
);

CREATE TABLE consumo_cafeina (
  id_consumo_cafeina INTEGER PRIMARY KEY AUTOINCREMENT,
  consume_cafeina    VARCHAR(2) NOT NULL,
  cantidad_cafeina   VARCHAR(5) NULL UNIQUE
);

CREATE TABLE perfil (
  id_perfil              INTEGER PRIMARY KEY AUTOINCREMENT,
  id_usuario             INTEGER NOT NULL UNIQUE REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  genero                 VARCHAR(15) NOT NULL,
  ocupacion              VARCHAR(20) NOT NULL,
  antiguedad             VARCHAR(15) NOT NULL,
  frecuencia             VARCHAR(15) NOT NULL,
  inicio_momento_dia     VARCHAR(12) NOT NULL,
  duracion               VARCHAR(10) NOT NULL,
  intensidad             VARCHAR(12) NOT NULL,
  conoce_fase_prodromica VARCHAR(10) NOT NULL,
  horas_sueno            VARCHAR(10) NOT NULL,
  nivel_estres           VARCHAR(10) NOT NULL,
  id_patron              INTEGER NOT NULL REFERENCES patron_aparicion (id_patron),
  id_consumo_cafeina     INTEGER NOT NULL REFERENCES consumo_cafeina (id_consumo_cafeina),
  version_cuestionario   TINYINT  NOT NULL DEFAULT 2,
  fecha_completado       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE perfil_condicion (
  id_perfil    INTEGER NOT NULL REFERENCES perfil (id_perfil) ON DELETE CASCADE,
  id_condicion INTEGER NOT NULL REFERENCES condicion_medica (id_condicion),
  PRIMARY KEY (id_perfil, id_condicion)
);

CREATE TABLE sintoma_prodromico (
  id_sintoma     INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo         VARCHAR(30)  NOT NULL UNIQUE,
  nombre_sintoma VARCHAR(80)  NOT NULL,
  descripcion    VARCHAR(255) NULL,
  activo         TINYINT      NOT NULL DEFAULT 1
);

CREATE TABLE desencadenante (
  id_desencadenante INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo            VARCHAR(30) NOT NULL UNIQUE,
  nombre            VARCHAR(80) NOT NULL,
  activo            TINYINT     NOT NULL DEFAULT 1
);

CREATE TABLE registro_sintoma (
  id_registro    INTEGER PRIMARY KEY AUTOINCREMENT,
  id_usuario     INTEGER NOT NULL REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  fecha          DATE    NOT NULL,
  hora           TIME    NOT NULL,
  intensidad     TINYINT NOT NULL CHECK (intensidad BETWEEN 1 AND 10),
  observacion    VARCHAR(500) NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE detalle_registro_sintoma (
  id_registro INTEGER NOT NULL REFERENCES registro_sintoma (id_registro) ON DELETE CASCADE,
  id_sintoma  INTEGER NOT NULL REFERENCES sintoma_prodromico (id_sintoma),
  PRIMARY KEY (id_registro, id_sintoma)
);

CREATE TABLE episodio_migrana (
  id_episodio    INTEGER PRIMARY KEY AUTOINCREMENT,
  id_usuario     INTEGER NOT NULL REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  fecha_inicio   DATE    NOT NULL,
  hora_inicio    TIME    NOT NULL,
  duracion       VARCHAR(10) NOT NULL,
  nivel_dolor    TINYINT NOT NULL CHECK (nivel_dolor BETWEEN 1 AND 10),
  notas          VARCHAR(500) NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE episodio_medicacion (
  id_episodio INTEGER PRIMARY KEY REFERENCES episodio_migrana (id_episodio) ON DELETE CASCADE,
  medicamento VARCHAR(80) NULL
);

CREATE TABLE episodio_sintoma (
  id_episodio INTEGER NOT NULL REFERENCES episodio_migrana (id_episodio) ON DELETE CASCADE,
  id_sintoma  INTEGER NOT NULL REFERENCES sintoma_prodromico (id_sintoma),
  PRIMARY KEY (id_episodio, id_sintoma)
);

CREATE TABLE episodio_desencadenante (
  id_episodio       INTEGER NOT NULL REFERENCES episodio_migrana (id_episodio) ON DELETE CASCADE,
  id_desencadenante INTEGER NOT NULL REFERENCES desencadenante (id_desencadenante),
  PRIMARY KEY (id_episodio, id_desencadenante)
);

CREATE TABLE recordatorio (
  id_recordatorio     INTEGER PRIMARY KEY AUTOINCREMENT,
  id_usuario          INTEGER NOT NULL REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  frecuencia          VARCHAR(8) NOT NULL,
  dia_semana          TINYINT NULL,
  hora_configurada    TIME    NOT NULL,
  activo              TINYINT NOT NULL DEFAULT 1,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (id_usuario, frecuencia)
);

CREATE TABLE notificacion (
  id_notificacion INTEGER PRIMARY KEY AUTOINCREMENT,
  id_usuario      INTEGER NOT NULL REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  id_recordatorio INTEGER NULL REFERENCES recordatorio (id_recordatorio) ON DELETE SET NULL,
  tipo            VARCHAR(25)  NOT NULL,
  mensaje         VARCHAR(255) NOT NULL,
  estado          VARCHAR(12)  NOT NULL DEFAULT 'pendiente',
  fecha_hora      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reporte (
  id_reporte       INTEGER PRIMARY KEY AUTOINCREMENT,
  id_usuario       INTEGER NOT NULL REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  formato          VARCHAR(3)  NOT NULL,
  periodo_inicio   DATE        NOT NULL,
  periodo_fin      DATE        NOT NULL,
  etiqueta         VARCHAR(30) NULL,
  fecha_generacion DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reporte_registro (
  id_reporte  INTEGER NOT NULL REFERENCES reporte (id_reporte) ON DELETE CASCADE,
  id_registro INTEGER NOT NULL REFERENCES registro_sintoma (id_registro) ON DELETE CASCADE,
  PRIMARY KEY (id_reporte, id_registro)
);

CREATE TABLE reporte_episodio (
  id_reporte  INTEGER NOT NULL REFERENCES reporte (id_reporte) ON DELETE CASCADE,
  id_episodio INTEGER NOT NULL REFERENCES episodio_migrana (id_episodio) ON DELETE CASCADE,
  PRIMARY KEY (id_reporte, id_episodio)
);

-- Tabla de tokens de Sanctum
CREATE TABLE personal_access_tokens (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  tokenable_type VARCHAR(255) NOT NULL,
  tokenable_id   INTEGER NOT NULL,
  name           VARCHAR(255) NOT NULL,
  token          VARCHAR(64)  NOT NULL UNIQUE,
  abilities      TEXT NULL,
  last_used_at   DATETIME NULL,
  expires_at     DATETIME NULL,
  created_at     DATETIME NULL,
  updated_at     DATETIME NULL
);

-- ---------------------------------------------------------------------
-- Vistas (misma salida que las de MySQL)
-- ---------------------------------------------------------------------
CREATE VIEW v_historial AS
SELECT r.id_usuario,
       'prodromico'              AS tipo,
       r.id_registro             AS id,
       'Síntomas prodrómicos'    AS etiqueta,
       r.fecha || ' ' || r.hora  AS fecha_hora,
       NULL                      AS inicio,
       r.intensidad              AS intensidad,
       NULL                      AS duracion,
       NULL                      AS medicacion,
       NULL                      AS medicamento,
       r.observacion             AS notas,
       (SELECT group_concat(n, ', ') FROM (
          SELECT s.nombre_sintoma AS n FROM detalle_registro_sintoma d
            JOIN sintoma_prodromico s ON s.id_sintoma = d.id_sintoma
           WHERE d.id_registro = r.id_registro ORDER BY s.id_sintoma)) AS sintomas,
       NULL                      AS desencadenantes
  FROM registro_sintoma r
UNION ALL
SELECT e.id_usuario, 'episodio', e.id_episodio, 'Episodio de migraña',
       e.fecha_inicio || ' ' || e.hora_inicio,
       e.fecha_inicio || ' ' || e.hora_inicio,
       e.nivel_dolor, e.duracion,
       (SELECT COUNT(*) FROM episodio_medicacion m WHERE m.id_episodio = e.id_episodio),
       (SELECT m.medicamento FROM episodio_medicacion m WHERE m.id_episodio = e.id_episodio),
       e.notas,
       (SELECT group_concat(n, ', ') FROM (
          SELECT s.nombre_sintoma AS n FROM episodio_sintoma x
            JOIN sintoma_prodromico s ON s.id_sintoma = x.id_sintoma
           WHERE x.id_episodio = e.id_episodio ORDER BY s.id_sintoma)),
       (SELECT group_concat(n, ', ') FROM (
          SELECT t.nombre AS n FROM episodio_desencadenante y
            JOIN desencadenante t ON t.id_desencadenante = y.id_desencadenante
           WHERE y.id_episodio = e.id_episodio ORDER BY t.id_desencadenante))
  FROM episodio_migrana e;

CREATE VIEW v_resumen_30dias AS
SELECT u.id_usuario,
       (SELECT COUNT(*) FROM episodio_migrana e
         WHERE e.id_usuario = u.id_usuario AND e.fecha_inicio >= date(hoy(), '-30 days')) AS episodios_30d,
       (SELECT COUNT(*) FROM registro_sintoma r
         WHERE r.id_usuario = u.id_usuario AND r.fecha >= date(hoy(), '-30 days'))        AS prodromicos_30d,
       (SELECT ROUND(AVG(h.intensidad), 1) FROM v_historial h
         WHERE h.id_usuario = u.id_usuario AND h.fecha_hora >= datetime(ahora(), '-30 days')) AS intensidad_media_30d,
       ((SELECT COUNT(*) FROM episodio_migrana e2 WHERE e2.id_usuario = u.id_usuario) +
        (SELECT COUNT(*) FROM registro_sintoma r2 WHERE r2.id_usuario = u.id_usuario))     AS registros_totales
  FROM usuario u;

CREATE VIEW v_episodios_semana AS
SELECT id_usuario,
       date(fecha_inicio, '-' || ((CAST(strftime('%w', fecha_inicio) AS INTEGER) + 6) % 7) || ' days') AS semana_inicio,
       COUNT(*)                   AS episodios,
       ROUND(AVG(nivel_dolor), 1) AS dolor_medio
  FROM episodio_migrana
 GROUP BY id_usuario, semana_inicio;

CREATE VIEW v_desencadenantes_frecuentes AS
SELECT e.id_usuario, t.codigo, t.nombre, COUNT(*) AS veces
  FROM episodio_migrana e
  JOIN episodio_desencadenante x ON x.id_episodio = e.id_episodio
  JOIN desencadenante t ON t.id_desencadenante = x.id_desencadenante
 GROUP BY e.id_usuario, t.codigo, t.nombre;

CREATE VIEW v_sintomas_frecuentes AS
SELECT q.id_usuario, s.codigo, s.nombre_sintoma, COUNT(*) AS veces
  FROM (SELECT r.id_usuario, d.id_sintoma FROM registro_sintoma r
          JOIN detalle_registro_sintoma d ON d.id_registro = r.id_registro
        UNION ALL
        SELECT e.id_usuario, es.id_sintoma FROM episodio_migrana e
          JOIN episodio_sintoma es ON es.id_episodio = e.id_episodio) q
  JOIN sintoma_prodromico s ON s.id_sintoma = q.id_sintoma
 GROUP BY q.id_usuario, s.codigo, s.nombre_sintoma;

CREATE VIEW v_perfil_completo AS
SELECT u.id_usuario, u.nombres, u.paterno, u.materno, u.correo, u.foto, u.fecha_nac,
       p.id_perfil, p.genero, p.ocupacion, p.antiguedad, p.frecuencia,
       pa.patron_aparicion, pa.orden_aparicion,
       p.inicio_momento_dia, p.duracion, p.intensidad, p.conoce_fase_prodromica,
       p.horas_sueno, p.nivel_estres,
       cc.consume_cafeina, cc.cantidad_cafeina,
       p.fecha_completado,
       (SELECT group_concat(c.codigo, ',') FROM perfil_condicion pc
          JOIN condicion_medica c ON c.id_condicion = pc.id_condicion
         WHERE pc.id_perfil = p.id_perfil) AS condiciones
  FROM usuario u
  LEFT JOIN perfil p            ON p.id_usuario = u.id_usuario
  LEFT JOIN patron_aparicion pa ON pa.id_patron = p.id_patron
  LEFT JOIN consumo_cafeina cc  ON cc.id_consumo_cafeina = p.id_consumo_cafeina;

-- ---------------------------------------------------------------------
-- Catálogos mínimos (códigos reales del frontend, js/app.js)
-- ---------------------------------------------------------------------
INSERT INTO sintoma_prodromico (codigo, nombre_sintoma, activo) VALUES
 ('luz', 'Sensibilidad a la luz', 1),
 ('ruido', 'Sensibilidad al ruido', 1),
 ('nauseas', 'Náuseas', 1),
 ('bostezos_repetidos', 'Bostezos repetidos', 1),
 ('cervical', 'Dolor o rigidez cervical', 1),
 ('sonido', 'Sensibilidad al sonido (antiguo)', 0);

INSERT INTO desencadenante (codigo, nombre, activo) VALUES
 ('estres', 'Estrés', 1),
 ('sueno', 'Falta de sueño', 1);

-- Memoria del aprendizaje del ML (ver migración 2026_10_14_000001_create_modelo_ml_table)
CREATE TABLE modelo_ml (
  id_modelo INTEGER PRIMARY KEY AUTOINCREMENT,
  version VARCHAR(40) NULL,
  estado VARCHAR(15) NOT NULL DEFAULT 'en_curso',
  auc DECIMAL(5,3) NULL,
  auc_anterior DECIMAL(5,3) NULL,
  pacientes_reales INTEGER NULL,
  filas_reales INTEGER NULL,
  registros_usados INTEGER NOT NULL DEFAULT 0,
  motivo VARCHAR(255) NULL,
  sha256 CHAR(64) NULL,
  tamano_bytes INTEGER NULL,
  archivo TEXT NULL,
  reporte TEXT NULL,
  fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_fin DATETIME NULL
);

-- Códigos de recuperación de contraseña (ver migración 2026_10_15_000001_create_recuperacion_clave_table)
CREATE TABLE recuperacion_clave (
  id_recuperacion INTEGER PRIMARY KEY AUTOINCREMENT,
  id_usuario INTEGER NOT NULL REFERENCES usuario(id_usuario) ON DELETE CASCADE,
  codigo_hash CHAR(64) NOT NULL,
  intentos INTEGER NOT NULL DEFAULT 0,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_expira DATETIME NOT NULL,
  fecha_uso DATETIME NULL,
  ip VARCHAR(45) NULL
);
