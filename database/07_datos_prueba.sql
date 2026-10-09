USE migrasense;

CALL sp_registrar_usuario('Usuario Demo', 'demo@migrasense.com', '$2y$10$HASH_DE_EJEMPLO_NO_VALIDO', @uid);

CALL sp_guardar_perfil(@uid, 'femenino','estudiante','1_3_anios','1_3_mes','si','2_3_meses',
     'tarde','4_12h','intensa','si','5_6','alto','si','2_3','hormonal,ansiedad_depresion');

CALL sp_registrar_prodromico(@uid, 5, 'Luz molesta tras estudiar', 'luz,cuello', NOW() - INTERVAL 3 DAY, @r1);
CALL sp_registrar_prodromico(@uid, 4, NULL, 'fatiga,humor', NOW() - INTERVAL 1 DAY, @r2);

CALL sp_registrar_episodio(@uid, 7, '4_24h', NOW() - INTERVAL 2 DAY, 1, 'Ibuprofeno 400mg',
     'Después de una noche corta', 'luz,nauseas', 'estres,sueno', @e1);
CALL sp_registrar_episodio(@uid, 8, '1_3d', NOW() - INTERVAL 20 DAY, 0, NULL,
     NULL, 'sonido', 'pantallas', @e2);

CALL sp_generar_reporte(@uid, 90, 'pdf', @rep);