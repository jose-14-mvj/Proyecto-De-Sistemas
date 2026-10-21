/* ===================================================================
   MigraSense — onboarding.js
   Cuestionario de personalización OBLIGATORIO (no se puede saltar).
   - Las preguntas se definen en el arreglo QUESTIONS (abajo).
   - Las preguntas con "showIf" solo aparecen si se respondió algo concreto.
   - Al terminar, guarda las respuestas en el servidor (PUT /api/perfil).
   =================================================================== */

msAlCargar(function () {
  // Login obligatorio: sin sesión no se puede responder el cuestionario
  if (!MS.requireAuth({ requireOnboarding: false })) return;

  const QUESTIONS = MS_PREGUNTAS;   // definidas en js/preguntas.js

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
    const respuestas = {};
    list.forEach(q => { respuestas[q.key] = answers[q.key]; });
    const btn = nextBtn;
    btn.disabled = true;
    MSApi.guardarCuestionario(respuestas)
      .then(() => {
        msToast('¡Todo listo! Personalizando tu experiencia…', '✨');
        setTimeout(() => { window.location.href = 'dashboard.html'; }, 700);
      })
      .catch(err => { btn.disabled = false; msToast(err.message, '⚠️'); });
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
      const respuestas = {};
      list.forEach(q => { respuestas[q.key] = answers[q.key]; });
      saveBtn.disabled = true;
      MSApi.guardarCuestionario(respuestas)
        .then(() => {
          msToast('Cuestionario actualizado', '✅');
          setTimeout(() => { window.location.href = 'perfil.html'; }, 600);
        })
        .catch(err => { saveBtn.disabled = false; msToast(err.message, '⚠️'); });
    });
    pintar();
  }

  const saved = MS.get(MS_KEYS.ONBOARDING, null);
  if (new URLSearchParams(location.search).get('edit') === '1' && MS.hasOnboarding()) iniciarEdicion(saved);
  else render();
});
