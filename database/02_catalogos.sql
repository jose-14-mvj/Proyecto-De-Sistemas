USE migrasense;

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
 ('bostezos','Bostezos excesivos','Bostezos repetidos sin sueño');

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