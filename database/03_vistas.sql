USE migrasense;

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