/* ===================================================================
   MigraSense — onboarding.js
   Cuestionario de personalización OBLIGATORIO (no se puede saltar).
   - Las preguntas se definen en el arreglo QUESTIONS (abajo).
   - Las preguntas con "showIf" solo aparecen si se respondió algo concreto.
   - Al terminar, guarda todo en localStorage (ms_onboarding).
   =================================================================== */

(function () {
  // Login obligatorio: sin sesión no se puede responder el cuestionario
  if (!MS.requireAuth({ requireOnboarding: false })) return;

  /* ---------- Preguntas ---------- */
  // type: 'single' (una opción) | 'multi' (varias). options: [valor, texto]
  const QUESTIONS = [
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

  /* ---------- Iconos (24x24) para la ilustración de cada pregunta ---------- */
  const ICONS = {
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
    briefcase: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    cycle: '<path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    stopwatch: '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 2h6"/>',
    gauge: '<path d="M4 17a8 8 0 1 1 16 0"/><path d="M12 17l4-5"/>',
    bulb: '<path d="M12 3a6 6 0 0 0-3 11c1 .7 1 1.4 1 2h4c0-.6 0-1.3 1-2a6 6 0 0 0-3-11Z"/><path d="M10 21h4"/>',
    moon: '<path d="M20 14a8 8 0 1 1-9-10 6.5 6.5 0 0 0 9 10Z"/>',
    bolt: '<path d="M13 3 4 14h6l-1 7 9-11h-6l1-7Z"/>',
    cup: '<path d="M4 8h13v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V8Z"/><path d="M17 10h2a2 2 0 0 1 0 4h-2M7 2v3M11 2v3"/>',
    heart: '<path d="M12 21s-8-5-8-11a4.5 4.5 0 0 1 8-3 4.5 4.5 0 0 1 8 3c0 6-8 11-8 11Z"/>'
  };

  function illustrationFor(icon) {
    return `<svg viewBox="0 0 200 200" aria-hidden="true">
      <circle cx="100" cy="100" r="92" fill="#eaf3ff"/>
      <circle cx="100" cy="100" r="64" fill="#ffffff"/>
      <g transform="translate(52 52) scale(4)" fill="none" stroke="#1d4ed8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">${ICONS[icon] || ''}</g>
    </svg>`;
  }

  /* ---------- Estado ---------- */
  const answers = {};   // { clave: valor | [valores] }
  let idx = 0;          // posición dentro de las preguntas visibles

  const host = document.getElementById('stepsHost');
  const stepLabel = document.getElementById('stepLabel');
  const progressFill = document.getElementById('progressFill');
  const dotsWrap = document.getElementById('dots');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const illustration = document.getElementById('onbIllustration');

  // Preguntas que se deben mostrar según lo respondido hasta ahora
  const visible = () => QUESTIONS.filter(q => !q.showIf || answers[q.showIf.key] === q.showIf.value);

  const isAnswered = q => q.type === 'multi'
    ? Array.isArray(answers[q.key]) && answers[q.key].length > 0
    : answers[q.key] !== undefined;

  // Si cambia una respuesta, se borran las respuestas de preguntas que ya no aplican
  function pruneHiddenAnswers() {
    QUESTIONS.forEach(q => {
      if (q.showIf && answers[q.showIf.key] !== q.showIf.value) delete answers[q.key];
    });
  }

  /* ---------- Dibujar la pregunta actual ---------- */
  function render() {
    const list = visible();
    if (idx > list.length - 1) idx = list.length - 1;
    const q = list[idx];

    host.innerHTML = '';
    const section = document.createElement('section');
    section.className = 'onb-step onb-step-enter';

    const h = document.createElement('h2');
    h.className = 'onb-question';
    h.textContent = q.title;
    section.appendChild(h);

    if (q.help) {
      const p = document.createElement('p');
      p.className = 'onb-help';
      p.textContent = q.help;
      section.appendChild(p);
    }

    const grid = document.createElement('div');
    grid.className = 'chip-grid';
    q.options.forEach(([value, text]) => {
      const label = document.createElement('label');
      label.className = 'chip';
      label.dataset.value = value;
      const input = document.createElement('input');
      input.type = q.type === 'multi' ? 'checkbox' : 'radio';
      input.name = q.key;

      const current = answers[q.key];
      const selected = q.type === 'multi' ? (current || []).includes(value) : current === value;
      if (selected) { input.checked = true; label.classList.add('selected'); }

      // Se escucha 'change' (no 'click') para evitar el doble disparo del <label>
      input.addEventListener('change', () => onPick(q, value, input, grid));
      label.appendChild(input);
      label.appendChild(document.createTextNode(text));
      grid.appendChild(label);
    });
    section.appendChild(grid);
    host.appendChild(section);

    illustration.innerHTML = illustrationFor(q.icon);
    refreshChrome(list);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function onPick(q, value, input, grid) {
    if (q.type === 'multi') {
      let arr = new Set(answers[q.key] || []);
      if (input.checked) {
        if (q.exclusive && value === q.exclusive) arr = new Set([value]);   // "Ninguna" quita el resto
        else if (q.exclusive) arr.delete(q.exclusive);                       // otra opción quita "Ninguna"
        arr.add(value);
      } else {
        arr.delete(value);
      }
      answers[q.key] = Array.from(arr);
      grid.querySelectorAll('.chip').forEach(l => {
        const on = arr.has(l.dataset.value);
        l.classList.toggle('selected', on);
        l.querySelector('input').checked = on;
      });
    } else {
      answers[q.key] = value;
      grid.querySelectorAll('.chip').forEach(l => l.classList.toggle('selected', l.dataset.value === value));
      pruneHiddenAnswers();
    }
    refreshChrome(visible());
  }

  /* ---------- Progreso, botones y puntos ---------- */
  let refreshChrome = function (list) {
    const q = list[idx];
    const total = list.length;
    stepLabel.textContent = `${idx + 1} de ${total}`;
    progressFill.style.width = `${((idx + 1) / total) * 100}%`;
    prevBtn.style.visibility = idx === 0 ? 'hidden' : 'visible';
    nextBtn.textContent = idx === total - 1 ? 'Finalizar' : 'Continuar';
    nextBtn.disabled = !isAnswered(q);   // obligatorio: no avanza sin responder

    dotsWrap.innerHTML = '';
    list.forEach((_, i) => {
      const dot = document.createElement('span');
      if (i === idx) dot.classList.add('on');
      dotsWrap.appendChild(dot);
    });
  };

  /* ---------- Navegación ---------- */
  nextBtn.addEventListener('click', () => {
    const list = visible();
    if (!isAnswered(list[idx])) return;
    if (idx < list.length - 1) { idx++; render(); }
    else finish(list);
  });

  prevBtn.addEventListener('click', () => { if (idx > 0) { idx--; render(); } });

  document.getElementById('backBtn').addEventListener('click', () => {
    if (idx > 0) { idx--; render(); }
  });

  /* ---------- Guardar ---------- */
  function finish(list) {
    // Además del valor, se guarda el texto legible (labels) para mostrarlo en el perfil
    const labels = {};
    list.forEach(q => {
      const textOf = v => (q.options.find(o => o[0] === v) || [v, v])[1];
      const a = answers[q.key];
      labels[q.key] = Array.isArray(a) ? a.map(textOf) : textOf(a);
    });
    const data = { ...answers, labels, version: 2, completedAt: new Date().toISOString() };
    MS.set(MS_KEYS.ONBOARDING, data);
    msToast('¡Todo listo! Personalizando tu experiencia…', '✨');
    setTimeout(() => { window.location.href = 'dashboard.html'; }, 700);
  }

  /* ===================================================================
     MODO EDICIÓN  (onboarding.html?edit=1)
     Muestra TODAS las preguntas con tus respuestas actuales para que
     cambies solo las que quieras, sin volver a responder el cuestionario.
     =================================================================== */
  function iniciarEdicion(saved) {
    QUESTIONS.forEach(q => { if (saved[q.key] !== undefined) answers[q.key] = saved[q.key]; });
    document.body.classList.add('edit-mode');
    document.getElementById('onbHeader').hidden = true;
    document.getElementById('onbBody').hidden = true;
    document.getElementById('editMode').hidden = false;
    const editHost = document.getElementById('editHost');
    const saveBtn = document.getElementById('editSaveBtn');

    function pintar() {
      const y = window.scrollY;
      editHost.innerHTML = '';
      visible().forEach(q => {
        const card = document.createElement('section');
        card.className = 'card edit-q';
        const h = document.createElement('h4');
        h.textContent = q.title;
        card.appendChild(h);
        if (q.help) { const p = document.createElement('p'); p.className = 'muted small'; p.textContent = q.help; card.appendChild(p); }
        const grid = document.createElement('div');
        grid.className = 'chip-grid';
        q.options.forEach(([value, text]) => {
          const label = document.createElement('label');
          label.className = 'chip';
          label.dataset.value = value;
          const input = document.createElement('input');
          input.type = q.type === 'multi' ? 'checkbox' : 'radio';
          input.name = 'e_' + q.key;
          const current = answers[q.key];
          const selected = q.type === 'multi' ? (current || []).includes(value) : current === value;
          if (selected) { input.checked = true; label.classList.add('selected'); }
          input.addEventListener('change', () => {
            const antes = visible().length;
            onPick(q, value, input, grid);          // reutiliza la misma lógica del cuestionario
            if (visible().length !== antes) pintar(); // aparece/desaparece una pregunta dependiente
          });
          label.appendChild(input);
          label.appendChild(document.createTextNode(text));
          grid.appendChild(label);
        });
        card.appendChild(grid);
        editHost.appendChild(card);
      });
      window.scrollTo(0, y);
      refrescarGuardar();
    }
    function refrescarGuardar() { saveBtn.disabled = !visible().every(isAnswered); }

    // onPick llama a refreshChrome (solo del modo paso a paso): en edición se sustituye
    refreshChrome = () => refrescarGuardar();

    saveBtn.addEventListener('click', () => {
      if (saveBtn.disabled) return;
      const list = visible();
      const labels = {};
      list.forEach(q => {
        const textOf = v => (q.options.find(o => o[0] === v) || [v, v])[1];
        const a = answers[q.key];
        labels[q.key] = Array.isArray(a) ? a.map(textOf) : textOf(a);
      });
      const nuevo = { ...saved, labels, version: 2, editedAt: new Date().toISOString() };
      QUESTIONS.forEach(q => { delete nuevo[q.key]; });
      list.forEach(q => { nuevo[q.key] = answers[q.key]; });
      MS.set(MS_KEYS.ONBOARDING, nuevo);
      msToast('Cuestionario actualizado', '✅');
      setTimeout(() => { window.location.href = 'perfil.html'; }, 600);
    });
    pintar();
  }

  const saved = MS.get(MS_KEYS.ONBOARDING, null);
  if (new URLSearchParams(location.search).get('edit') === '1' && MS.hasOnboarding()) iniciarEdicion(saved);
  else render();
})();