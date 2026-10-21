/* ===================================================================
   MigraSense — preguntas.js
   Preguntas del cuestionario de personalización (compartidas):
   - onboarding.js las muestra.
   - api.js las usa para convertir los códigos que guarda el servidor
     (p. ej. "1_3_mes") en texto legible ("1 a 3 veces al mes").
   type: 'single' (una opción) | 'multi' (varias). options: [valor, texto]
   =================================================================== */
const MS_PREGUNTAS = [
  { key: 'genero', icon: 'user', type: 'single',
    title: '¿Con qué género te identificas?',
    options: [['femenino', 'Femenino'], ['masculino', 'Masculino'], ['otro', 'Otro']] },

  { key: 'ocupacion', icon: 'briefcase', type: 'single',
    title: '¿Cuál es tu ocupación principal?',
    options: [
      ['estudiante', 'Estudiante'],
      ['oficina_virtual', 'Trabajo de oficina o virtual'],
      ['fisico_campo', 'Trabajo físico o de campo'],
      ['turnos_nocturno', 'Trabajo por turnos o nocturno'],
      ['hogar', 'Labores del hogar'],
      ['sin_ocupacion', 'Sin ocupación actual']] },

  { key: 'tiempoMigrana', icon: 'clock', type: 'single',
    title: '¿Hace cuánto tiempo sufres migrañas?',
    options: [['menos_1_anio', 'Menos de 1 año'], ['1_3_anios', '1 a 3 años'], ['4_10_anios', '4 a 10 años'], ['mas_10_anios', 'Más de 10 años']] },

  { key: 'frecuencia', icon: 'calendar', type: 'single',
    title: '¿Cada cuánto te da migraña?',
    options: [
      ['menos_1_mes', 'Menos de 1 vez al mes'],
      ['1_3_mes', '1 a 3 veces al mes'],
      ['1_2_semana', '1 a 2 veces por semana'],
      ['3_mas_semana', '3 o más veces por semana'],
      ['casi_diario', 'Casi todos los días']] },

  { key: 'tieneOrden', icon: 'cycle', type: 'single',
    title: '¿Tus migrañas tienen un orden de aparición?',
    help: 'Es decir, ¿aparecen cada cierto número de meses o en ciertos meses?',
    options: [['si', 'Sí'], ['no', 'No']] },

  { key: 'ordenAparicion', icon: 'cycle', type: 'single',
    showIf: { key: 'tieneOrden', value: 'si' },
    title: '¿Cuál es el orden de aparición?',
    options: [
      ['2_3_meses', 'Cada 2 o 3 meses'],
      ['4_5_meses', 'Cada 4 o 5 meses'],
      ['6_7_meses', 'Cada 6 o 7 meses'],
      ['8_9_meses', 'Cada 8 o 9 meses'],
      ['9_10_meses', 'Cada 9 o 10 meses']] },

  { key: 'momentoDia', icon: 'sun', type: 'single',
    title: '¿En qué momentos del día suele comenzar?',
    options: [
      ['madrugada', 'Madrugada (00-06)'],
      ['manana', 'Mañana (06-12)'],
      ['tarde', 'Tarde (12-18)'],
      ['noche', 'Noche (18-24)'],
      ['sin_horario', 'Sin horario fijo']] },

  { key: 'duracion', icon: 'stopwatch', type: 'single',
    title: '¿Cuánto suele durar un episodio?',
    options: [
      ['menos_4h', 'Menos de 4 horas'],
      ['4_12h', '4 a 12 horas'],
      ['12_24h', '12 a 24 horas'],
      ['1_3d', '1 a 3 días'],
      ['mas_3d', 'Más de 3 días']] },

  { key: 'intensidad', icon: 'gauge', type: 'single',
    title: '¿Qué tan fuerte suele ser el dolor?',
    options: [
      ['leve', 'Leve (1-3)'],
      ['moderada', 'Moderada (4-6)'],
      ['intensa', 'Intensa (7-8)'],
      ['muy_intensa', 'Muy intensa o incapacitante (9-10)']] },

  { key: 'conocesProdromica', icon: 'bulb', type: 'single',
    title: '¿Habías escuchado antes de la fase prodrómica?',
    help: 'Son las señales de aviso que aparecen antes del dolor.',
    options: [['si', 'Sí'], ['no', 'No'], ['no_seguro', 'No estoy seguro']] },

  { key: 'sueno', icon: 'moon', type: 'single',
    title: '¿Cuántas horas duermes en promedio por noche?',
    options: [['menos_5', 'Menos de 5'], ['5_6', '5 a 6'], ['7_8', '7 a 8'], ['mas_8', 'Más de 8']] },

  { key: 'estres', icon: 'bolt', type: 'single',
    title: '¿Cómo describirías tu nivel de estrés habitual?',
    options: [['bajo', 'Bajo'], ['moderado', 'Moderado'], ['alto', 'Alto'], ['muy_alto', 'Muy alto']] },

  { key: 'cafeina', icon: 'cup', type: 'single',
    title: '¿Consumes cafeína?',
    help: 'Café, bebidas energéticas, gaseosas.',
    options: [['si', 'Sí'], ['no', 'No']] },

  { key: 'porcionesCafeina', icon: 'cup', type: 'single',
    showIf: { key: 'cafeina', value: 'si' },
    title: '¿Cuántas porciones al día?',
    options: [['1', '1'], ['2_3', '2 a 3'], ['4_mas', '4 o más']] },

  { key: 'condiciones', icon: 'heart', type: 'multi', exclusive: 'ninguna',
    title: '¿Tienes alguna de estas condiciones?',
    help: 'Puedes seleccionar más de una.',
    options: [
      ['ansiedad_depresion', 'Ansiedad o depresión'],
      ['hipertension', 'Hipertensión'],
      ['trastornos_sueno', 'Trastornos del sueño'],
      ['hormonal', 'Alteraciones hormonales o ciclo irregular'],
      ['alergias_sinusitis', 'Alergias o sinusitis crónica'],
      ['ninguna', 'Ninguna'],
      ['otra', 'Otra']] }
];

/** Texto legible de cada respuesta → { frecuencia: '1 a 3 veces al mes', condiciones: ['Hipertensión'], ... } */
function msEtiquetasPerfil(respuestas) {
  const labels = {};
  MS_PREGUNTAS.forEach(q => {
    const a = respuestas ? respuestas[q.key] : undefined;
    if (a === undefined || a === null) return;
    const textOf = v => (q.options.find(o => o[0] === v) || [v, v])[1];
    labels[q.key] = Array.isArray(a) ? a.map(textOf) : textOf(a);
  });
  return labels;
}
