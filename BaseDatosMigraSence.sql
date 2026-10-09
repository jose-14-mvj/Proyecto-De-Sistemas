-- =====================================================
-- BD: migraña_app  |  Motor: MySQL 8 / MariaDB 10.4+
-- Modelo en 3FN. Nombres sin ñ para evitar problemas.
-- =====================================================
CREATE DATABASE IF NOT EXISTS migrana_app
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE migrana_app;

-- ---------- USUARIOS (tabla estándar de Laravel + campos propios) ----------
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombres VARCHAR(100) NOT NULL,
  paterno VARCHAR(60) NOT NULL,
  materno VARCHAR(60) NULL,
  fecha_nac DATE NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,      -- = "correo"
  password VARCHAR(255) NOT NULL,          -- hash (bcrypt), nunca texto plano
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- ---------- CATÁLOGOS ----------
CREATE TABLE patrones_aparicion (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE condiciones_medicas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE sintomas_prodromicos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ---------- PERFIL (1:1 con users) ----------
CREATE TABLE perfiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  patron_aparicion_id BIGINT UNSIGNED NULL,
  genero VARCHAR(30) NULL,
  ocupacion VARCHAR(100) NULL,
  inicio_momento_dia ENUM('madrugada','manana','tarde','noche','variable') NULL,
  antiguedad_anios TINYINT UNSIGNED NULL,          -- hace cuántos años tiene migraña
  frecuencia_inicial VARCHAR(50) NULL,             -- dato declarado al registrarse
  duracion_inicial VARCHAR(50) NULL,
  intensidad_inicial TINYINT UNSIGNED NULL,        -- 1 a 10
  conoce_fase_prodromica BOOLEAN NOT NULL DEFAULT 0,
  horas_sueno DECIMAL(3,1) NULL,
  nivel_estres TINYINT UNSIGNED NULL,              -- 1 a 10
  cantidad_cafeina TINYINT UNSIGNED NOT NULL DEFAULT 0, -- tazas/día (0 = no consume)
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,                       -- reemplaza fecha_actualizacion
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (patron_aparicion_id) REFERENCES patrones_aparicion(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE perfil_condicion (
  perfil_id BIGINT UNSIGNED NOT NULL,
  condicion_medica_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (perfil_id, condicion_medica_id),
  FOREIGN KEY (perfil_id) REFERENCES perfiles(id) ON DELETE CASCADE,
  FOREIGN KEY (condicion_medica_id) REFERENCES condiciones_medicas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- EPISODIOS ----------
CREATE TABLE episodios_migrana (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  fecha_inicio DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  duracion_minutos INT UNSIGNED NULL,
  nivel_dolor TINYINT UNSIGNED NOT NULL,           -- 1 a 10
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (user_id, fecha_inicio)
) ENGINE=InnoDB;

CREATE TABLE episodio_sintoma (
  episodio_id BIGINT UNSIGNED NOT NULL,
  sintoma_id BIGINT UNSIGNED NOT NULL,
  orden_aparicion TINYINT UNSIGNED NULL,
  PRIMARY KEY (episodio_id, sintoma_id),
  FOREIGN KEY (episodio_id) REFERENCES episodios_migrana(id) ON DELETE CASCADE,
  FOREIGN KEY (sintoma_id) REFERENCES sintomas_prodromicos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- REGISTRO DE SÍNTOMAS ----------
CREATE TABLE registros_sintoma (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  episodio_id BIGINT UNSIGNED NULL,                -- opcional: lo liga a un episodio
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  observacion TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (episodio_id) REFERENCES episodios_migrana(id) ON DELETE SET NULL,
  INDEX (user_id, fecha)
) ENGINE=InnoDB;

CREATE TABLE registro_sintoma_detalle (
  registro_id BIGINT UNSIGNED NOT NULL,
  sintoma_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (registro_id, sintoma_id),
  FOREIGN KEY (registro_id) REFERENCES registros_sintoma(id) ON DELETE CASCADE,
  FOREIGN KEY (sintoma_id) REFERENCES sintomas_prodromicos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- REPORTES ----------
CREATE TABLE reportes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  formato ENUM('pdf','excel','csv') NOT NULL DEFAULT 'pdf',
  periodo_inicio DATE NOT NULL,
  periodo_fin DATE NOT NULL,
  fecha_generacion DATETIME NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- RECORDATORIOS Y NOTIFICACIONES ----------
CREATE TABLE recordatorios (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  hora_configurada TIME NOT NULL,
  frecuencia ENUM('diaria','semanal','personalizada') NOT NULL DEFAULT 'diaria',
  activo BOOLEAN NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notificaciones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  recordatorio_id BIGINT UNSIGNED NULL,
  tipo VARCHAR(50) NOT NULL,
  mensaje VARCHAR(255) NOT NULL,
  estado ENUM('pendiente','enviada','leida') NOT NULL DEFAULT 'pendiente',
  fecha_hora DATETIME NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (recordatorio_id) REFERENCES recordatorios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
