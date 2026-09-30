/* ===================================================================
   MigraSense — consejos.js
   Guías desplegables (alimentación, ejercicios, cuándo consultar).
   El contenido está en datos: para agregar un alimento o ejercicio
   basta con añadir un elemento a los arreglos de abajo.
   =================================================================== */
(function () {
  const IMG = 'assets/img/consejos/';
  const F = (id, label) => ({ id, label });

  const BUENOS = [
    F('espinaca','Espinaca'), F('aguacate','Aguacate'), F('platano','Plátano'), F('almendras','Almendras'),
    F('nueces','Nueces'), F('salmon','Salmón'), F('sardinas','Sardinas'), F('avena','Avena'),
    F('arroz','Arroz integral'), F('huevo','Huevo'), F('yogur','Yogur natural'), F('brocoli','Brócoli'),
    F('zanahoria','Zanahoria'), F('camote','Camote'), F('lentejas','Lentejas'), F('linaza','Semillas de linaza'),
    F('aceite','Aceite de oliva'), F('frutos-rojos','Frutos rojos'), F('sandia','Sandía'), F('naranja','Naranja'),
    F('jengibre','Jengibre'), F('agua','Agua')
  ];
  const VIGILAR = [
    F('cafe','Café'), F('chocolate','Chocolate'), F('quesos','Quesos curados'), F('embutidos','Embutidos'),
    F('alcohol','Alcohol'), F('ultraprocesada','Comida ultraprocesada'), F('sopas','Sopas instantáneas'), F('snacks','Snacks salados')
  ];
  const PRECAUCIONES = [
    { t: 'Enfermedad renal', d: 'Evitar o limitar en exceso:', l: ['Plátano','Aguacate','Frutos secos','Semillas','Espinaca','Lentejas','Camote'] },
    { t: 'Hipertensión', d: 'Limitar especialmente:', l: ['Embutidos','Comida muy salada','Sopas instantáneas','Snacks salados','Alimentos ultraprocesados'] },
    { t: 'Diabetes', d: 'Controlar la cantidad y combinación con proteína/fibra. Evitar:', l: ['Azúcar en exceso','Preparaciones muy dulces'] },
    { t: 'Enfermedad cardíaca o arritmias', d: 'Tener cuidado con:', l: ['Cafeína en exceso','Cambios bruscos en la dieta'] },
    { t: 'Embarazo', d: 'No iniciar suplementos o hierbas por cuenta propia (magnesio, vitamina B2, CoQ10, etc.).', l: [] }
  ];
  const EJERCICIOS = [
    { img: 'ej-respiracion', t: 'Respiración diafragmática', d: 'Relaja el cuerpo y la mente.',
      p: ['Inhala por la nariz durante 4 segundos.', 'Mantén 1–2 segundos.', 'Exhala lentamente por la boca durante 6 segundos.'], n: 'Repite 5–10 minutos.' },
    { img: 'ej-cuello', t: 'Estiramiento del cuello', d: 'Reduce la tensión en cuello y cabeza.',
      p: ['Inclina la cabeza hacia el hombro derecho. Mantén 15–20 seg.', 'Cambia al lado izquierdo. Mantén 15–20 seg.', 'Lleva la barbilla hacia el pecho. Mantén 15–20 seg.'],
      n: 'Repite 2–3 veces por lado. No hagas movimientos bruscos ni círculos completos con el cuello.' },
    { img: 'ej-hombros', t: 'Estiramiento de hombros', d: 'Libera la tensión de la parte superior del cuerpo.',
      p: ['Sube ambos hombros hacia las orejas. Mantén 3–5 seg.', 'Relájalos lentamente. Repite 10 veces.', 'Después, haz círculos pequeños hacia atrás con los hombros.'] },
    { img: 'ej-espalda', t: 'Estiramiento de la espalda', d: 'Mejora la postura y alivia la tensión.',
      p: ['Junta las manos delante del cuerpo y empuja suavemente hacia adelante, separando los omóplatos.', 'Mantén 15–30 segundos.'], n: 'Repite 2–3 veces.' },
    { t: 'Relajación muscular progresiva', d: 'Libera la tensión acumulada.',
      p: ['Manos, hombros, cuello, mandíbula y piernas: en cada zona aprieta 5 seg. y relaja.'], n: 'Hazlo durante 5–10 minutos.' }
  ];
  const AEROBICO = [
    F('ej-caminar','Caminar'), F('ej-bicicleta','Bicicleta'), F('ej-natacion','Natación'), F('ej-yoga','Yoga'), F('ej-trote','Trote suave (si lo toleras)')
  ];
  const DURANTE = ['Respiración lenta y profunda.', 'Estiramientos muy suaves.', 'Relajación muscular.', 'Caminar lentamente si no empeora el dolor.', 'Descansar en un lugar oscuro y tranquilo.', 'Hidratarte.'];
  const EVITAR = ['Correr intensamente.', 'Levantar mucho peso.', 'HIIT (entrenamiento de alta intensidad).', 'Saltos.', 'Ejercicios que aumenten la presión o el esfuerzo.', 'Entrenar con calor intenso o deshidratado.'];
  const ALARMA = ['Es el peor dolor de cabeza de tu vida.', 'Viene con desmayo o confusión.', 'Hay debilidad en un lado del cuerpo.', 'Dificultad para hablar.', 'Fiebre o rigidez importante del cuello.'];

  /* ---------- Helpers de HTML ---------- */
  const ul = (arr, cls = '') => `<ul class="g-list ${cls}">${arr.map(x => `<li>${x}</li>`).join('')}</ul>`;
  const grid = arr => `<div class="food-grid">${arr.map(f =>
    `<figure class="food"><img src="${IMG}${f.id}.jpg" alt="${f.label}" loading="lazy"><figcaption>${f.label}</figcaption></figure>`).join('')}</div>`;
  const h = txt => `<h5 class="g-h">${txt}</h5>`;
  const note = (txt, kind = 'info') => `<div class="g-note g-${kind}">${txt}</div>`;

  const alimentacion =
    h('Alimentos recomendados') + `<p class="small muted">Pueden ayudar a reducir la frecuencia o intensidad de las migrañas.</p>` +
    grid(BUENOS) + note('<strong>La hidratación es clave.</strong> Beber agua regularmente puede ayudar a prevenir las migrañas.') +
    h('Alimentos que conviene vigilar') + `<p class="small muted">Pueden desencadenar migrañas en algunas personas.</p>` +
    grid(VIGILAR, 'warn').replace('food-grid', 'food-grid warn') +
    h('Precauciones según la enfermedad o situación') +
    `<div class="prec-grid">${PRECAUCIONES.map(p => `<div class="prec"><strong>${p.t}</strong><p class="small muted">${p.d}</p>${p.l.length ? ul(p.l) : ''}</div>`).join('')}</div>` +
    h('Consejos generales') +
    ul(['No te saltes comidas.', 'Mantén horarios regulares.', 'Varía tu alimentación.', 'Lleva un diario de migraña para identificar tus desencadenantes.', 'Reduce alimentos ultraprocesados.', 'Si las migrañas son frecuentes o empeoran, consulta a un médico.']) +
    note('Cada persona es diferente: lo que funciona para uno puede no funcionar para otro. Ante cualquier duda, consulta a un profesional de la salud.', 'warn');

  const ejercicios =
    `<p class="small muted">No todos los ejercicios funcionan igual en todas las personas. Realízalos de forma regular y con calma.</p>` +
    EJERCICIOS.map((e, i) => `
      <div class="ex-item">
        ${e.img ? `<img src="${IMG}${e.img}.jpg" alt="${e.t}" loading="lazy">` : `<span class="ex-num">${i + 1}</span>`}
        <div><strong>${i + 1}. ${e.t}</strong><p class="small muted">${e.d}</p>${ul(e.p)}${e.n ? `<p class="small"><em>${e.n}</em></p>` : ''}</div>
      </div>`).join('') +
    h('Ejercicio aeróbico (para prevenir migrañas)') +
    `<p class="small muted">Cuando no estés teniendo una crisis, el ejercicio moderado puede ayudar a reducir la frecuencia. Recomendado: 20–30 minutos, 3–5 días por semana, aumentando progresivamente.</p>` +
    `<div class="food-grid aero">${AEROBICO.map(f => `<figure class="food"><img src="${IMG}${f.id}.jpg" alt="${f.label}" loading="lazy"><figcaption>${f.label}</figcaption></figure>`).join('')}</div>` +
    ul(['Mantén una buena hidratación.', 'Usa ropa cómoda.', 'Elige un ambiente agradable.', 'Escucha a tu cuerpo.']) +
    `<div class="two-col"><div class="g-note g-ok"><strong>Durante una migraña puedes probar:</strong>${ul(DURANTE)}</div>
     <div class="g-note g-bad"><strong>Evita durante la crisis:</strong>${ul(EVITAR)}<p class="small">Si el ejercicio aumenta claramente el dolor, detente.</p></div></div>` +
    h('Consejos extra') +
    ul(['Mantén horarios regulares de sueño.', 'No te saltes comidas.', 'Mantente bien hidratado/a.', 'Reduce el estrés (meditación, respiración, actividades que disfrutes).', 'Evita la sobreexposición a pantallas.', 'Mantén una buena postura.']) +
    note('La constancia es clave. Los resultados no son inmediatos, pero pueden ser muy positivos.');

  const medico =
    note('<strong>Consulta a un médico si…</strong> una migraña aparece por primera vez durante o inmediatamente después de hacer ejercicio, especialmente si es un dolor muy intenso y repentino.', 'bad') +
    `<p class="small" style="margin-top:12px;"><strong>Busca atención urgente también si el dolor:</strong></p>` + ul(ALARMA) +
    note('No ignores los síntomas de alarma. Estos consejos pueden ayudar a muchas personas, pero no son un tratamiento único ni garantizan que desaparezcan las migrañas.', 'warn');

  const SECCIONES = [
    { id: 'alimentos', t: 'Alimentación y migraña', s: 'Qué comer, qué vigilar y precauciones', color: 'linear-gradient(135deg,#4fb787,#7fd1a8)',
      icon: '<path d="M6 3v7a2 2 0 0 0 4 0V3M8 10v11M17 3c-2 1-3 3-3 6s1 5 3 6v6"/>', body: alimentacion },
    { id: 'ejercicios', t: 'Ejercicios para aliviar la migraña', s: 'Estiramientos, respiración y ejercicio aeróbico', color: 'linear-gradient(135deg,#60a5fa,#2563eb)',
      icon: '<path d="M3 12h3l2-6 4 12 3-9 2 3h4"/>', body: ejercicios },
    { id: 'medico', t: 'Cuándo consultar a un médico', s: 'Señales de alarma que no debes ignorar', color: 'linear-gradient(135deg,#ef6f6c,#f2994a)',
      icon: '<path d="M12 3 2 21h20L12 3ZM12 10v4M12 17.5v.5"/>', body: medico }
  ];

  const root = document.getElementById('guias');
  if (!root) return;

  root.innerHTML = SECCIONES.map(s => `
    <section class="card acc" id="acc-${s.id}">
      <button type="button" class="acc-btn" aria-expanded="false" aria-controls="panel-${s.id}">
        <span class="tip-icon" style="background:${s.color};margin:0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${s.icon}</svg>
        </span>
        <span class="acc-title"><strong>${s.t}</strong><small>${s.s}</small></span>
        <svg class="acc-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m6 9 6 6 6-6"/></svg>
      </button>
      <div class="acc-panel" id="panel-${s.id}" role="region"><div class="acc-inner">${s.body}</div></div>
    </section>`).join('');

  root.addEventListener('click', ev => {
    const btn = ev.target.closest('.acc-btn');
    if (!btn) return;
    const card = btn.parentElement;
    const open = !card.classList.contains('open');
    card.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open);
  });
})();
