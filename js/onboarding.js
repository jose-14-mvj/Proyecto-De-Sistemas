/* ===================================================================
   MigraSense — onboarding.js
   Controla el flujo de preguntas de personalización (9 pasos),
   guarda las respuestas y las persiste en localStorage al finalizar.
   =================================================================== */

(function () {
  const steps = Array.from(document.querySelectorAll('.onb-step'));
  const total = steps.length;
  let current = 1;
  const answers = {};

  const stepLabel = document.getElementById('stepLabel');
  const progressFill = document.getElementById('progressFill');
  const dotsWrap = document.getElementById('dots');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const illustration = document.getElementById('onbIllustration');

  /* ---------- Ilustraciones simples por paso (SVG en línea) ---------- */
  const ILLUSTRATIONS = [
    // 1. Diagnóstico
    `<svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="92" fill="#eef0ff"/>
     <path d="M100 55c-26 0-46 20-46 44 0 18 10 30 20 38v18h52v-18c10-8 20-20 20-38 0-24-20-44-46-44Z" fill="#c9d3fb"/>
     <path d="M78 100h44M100 78v44" stroke="#4a5c82" stroke-width="6" stroke-linecap="round"/>
     <circle cx="100" cy="100" r="10" fill="#8b9dfb"/></svg>`,
    // 2. Datos personales
    `<svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="92" fill="#eef0ff"/>
     <circle cx="100" cy="78" r="26" fill="#c9d3fb"/>
     <path d="M56 152c6-26 24-40 44-40s38 14 44 40" fill="none" stroke="#4a5c82" stroke-width="7" stroke-linecap="round"/></svg>`,
    // 3. Frecuencia
    `<svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="92" fill="#eef0ff"/>
     <rect x="55" y="60" width="90" height="86" rx="14" fill="#fff"/>
     <rect x="55" y="60" width="90" height="26" rx="14" fill="#8b9dfb"/>
     <circle cx="80" cy="110" r="7" fill="#f2994a"/><circle cx="105" cy="110" r="7" fill="#c9d3fb"/><circle cx="130" cy="110" r="7" fill="#c9d3fb"/>
     <circle cx="80" cy="130" r="7" fill="#c9d3fb"/><circle cx="105" cy="130" r="7" fill="#c9d3fb"/></svg>`,
    // 4. Duración
    `<svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="92" fill="#eef0ff"/>
     <circle cx="100" cy="104" r="46" fill="#fff" stroke="#c9d3fb" stroke-width="6"/>
     <path d="M100 104V76M100 104l24 14" stroke="#4a5c82" stroke-width="7" stroke-linecap="round"/>
     <path d="M84 54h32" stroke="#8b9dfb" stroke-width="7" stroke-linecap="round"/></svg>`,
    // 5. Intensidad
    `<svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="92" fill="#eef0ff"/>
     <path d="M56 120a44 44 0 0 1 88 0" fill="none" stroke="#c9d3fb" stroke-width="10" stroke-linecap="round"/>
     <path d="M56 120a44 44 0 0 1 60-40" fill="none" stroke="#f2994a" stroke-width="10" stroke-linecap="round"/>
     <circle cx="100" cy="120" r="7" fill="#4a5c82"/><path d="M100 120 122 96" stroke="#4a5c82" stroke-width="6" stroke-linecap="round"/></svg>`,
    // 6. Disparadores
    `<svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="92" fill="#eef0ff"/>
     <path d="M60 120a24 24 0 0 1 4-47 30 30 0 0 1 58-8 26 26 0 0 1 20 45 20 20 0 0 1-4 40H70a20 20 0 0 1-10-30Z" fill="#fff" stroke="#c9d3fb" stroke-width="5"/>
     <path d="M76 140l-6 16M100 140v18M124 140l6 16" stroke="#8b9dfb" stroke-width="6" stroke-linecap="round"/></svg>`,
    // 7. Prodrómicos
    `<svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="92" fill="#eef0ff"/>
     <path d="M100 55c-26 0-46 20-46 44 0 18 10 30 20 38v18h52v-18c10-8 20-20 20-38 0-24-20-44-46-44Z" fill="#fff" stroke="#c9d3fb" stroke-width="5"/>
     <path d="M78 92c6-8 14-8 20 0M102 92c6-8 14-8 20 0" stroke="#4a5c82" stroke-width="6" stroke-linecap="round"/>
     <path d="M82 112c8 8 28 8 36 0" stroke="#f2994a" stroke-width="6" stroke-linecap="round"/></svg>`,
    // 8. Antecedentes
    `<svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="92" fill="#eef0ff"/>
     <path d="M100 52 150 68v34c0 34-22 54-50 62-28-8-50-28-50-62V68Z" fill="#fff" stroke="#c9d3fb" stroke-width="5"/>
     <path d="M84 100h32M100 84v32" stroke="#4a5c82" stroke-width="7" stroke-linecap="round"/></svg>`,
    // 9. Recordatorios
    `<svg viewBox="0 0 200 200"><circle cx="100" cy="100" r="92" fill="#eef0ff"/>
     <path d="M70 118c0-24 12-40 30-40s30 16 30 40l8 16H62Z" fill="#fff" stroke="#c9d3fb" stroke-width="5"/>
     <path d="M92 142a8 8 0 0 0 16 0" stroke="#4a5c82" stroke-width="6" stroke-linecap="round"/>
     <circle cx="132" cy="70" r="9" fill="#f2994a"/></svg>`
  ];

  /* ---------- Catálogos dinámicos (disparadores y prodrómicos) ---------- */
  const ICONS = {
    brain: '<path d="M9 4a3 3 0 0 0-3 3 3 3 0 0 0-1 5.8 3 3 0 0 0 4 4.2h2V4Z"/><path d="M15 4a3 3 0 0 1 3 3 3 3 0 0 1 1 5.8 3 3 0 0 1-4 4.2h-2V4Z"/>',
    moon: '<path d="M20 14a8 8 0 1 1-9-10 6.5 6.5 0 0 0 9 10Z"/>',
    food: '<path d="M6 3v7a2 2 0 0 0 4 0V3M8 10v11M17 3c-2 1-3 3-3 6s1 5 3 6v6"/>',
    wave: '<path d="M3 12c2-4 4-4 6 0s4 4 6 0 4-4 6 0"/>',
    drop: '<path d="M12 3s7 8 7 13a7 7 0 0 1-14 0c0-5 7-13 7-13Z"/>',
    cloud: '<path d="M7 18a4 4 0 1 1 1-7.9A5 5 0 0 1 18 12a3.5 3.5 0 0 1-1 6.9H7Z"/>',
    screen: '<rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    bolt: '<path d="M13 3 4 14h6l-1 7 9-11h-6l1-7Z"/>'
  };

  const triggerGrid = document.getElementById('triggerGrid');
  if (triggerGrid) {
    MS_TRIGGERS.forEach(t => {
      const label = document.createElement('label');
      label.className = 'chip';
      label.dataset.value = t.id;
      label.innerHTML = `<input type="checkbox">${t.label}`;
      triggerGrid.appendChild(label);
    });
  }

  const prodromicList = document.getElementById('prodromicList');
  if (prodromicList) {
    MS_PRODROMICOS.forEach(p => {
      const label = document.createElement('label');
      label.className = 'option-card multi';
      label.dataset.value = p.id;
      label.innerHTML = `
        <input type="checkbox">
        <span class="oc-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/></svg></span>
        <span class="oc-text">${p.label}</span>
        <span class="oc-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg></span>`;
      prodromicList.appendChild(label);
    });
  }

  /* ---------- Dots ---------- */
  for (let i = 1; i <= total; i++) {
    const dot = document.createElement('span');
    if (i === 1) dot.classList.add('on');
    dotsWrap.appendChild(dot);
  }

  /* ---------- Agrupar claves dentro de un paso ---------- */
  function getGroups(stepEl) {
    const groups = [];
    if (stepEl.dataset.key) groups.push({ el: stepEl, key: stepEl.dataset.key, type: stepEl.dataset.type });
    stepEl.querySelectorAll('[data-key][data-type]').forEach(g => {
      if (g !== stepEl) groups.push({ el: g, key: g.dataset.key, type: g.dataset.type });
    });
    return groups;
  }

  function isStepComplete(stepEl) {
    const groups = getGroups(stepEl);
    if (groups.length === 0) return true; // paso 9 se valida aparte (tiene default)
    return groups.every(g => {
      if (g.type === 'scale') return true;
      if (g.type === 'multi') return Array.isArray(answers[g.key]) && answers[g.key].length > 0;
      return answers[g.key] !== undefined && answers[g.key] !== null;
    });
  }

  /* ---------- Selección: .chip / .option-card ----------
     IMPORTANTE: escuchamos 'change' en el <input> (no 'click' en el
     <label>). Un <label> que envuelve un <input> reenvía un click
     sintético al input al activarse; si escucháramos 'click' en el
     label, ese click sintético volvería a burbujear por el label y
     el handler se ejecutaría DOS veces por cada toque, deshaciendo
     la selección (por eso antes "no dejaba elegir"). 'change' solo
     se dispara una vez por cada cambio real de estado. */
  document.querySelectorAll('.chip, .option-card').forEach(el => {
    // Determina el contenedor (grupo) y su tipo/clave
    const group = el.closest('[data-key][data-type]') || el.closest('.onb-step');
    const key = group ? group.dataset.key : null;
    const type = group ? group.dataset.type : null;
    const input = el.querySelector('input');
    if (!key || !input) return;

    input.addEventListener('change', () => {
      if (type === 'multi') {
        el.classList.toggle('selected', input.checked);
        const arr = new Set(answers[key] || []);
        if (input.checked) arr.add(el.dataset.value);
        else arr.delete(el.dataset.value);
        answers[key] = Array.from(arr);
      } else {
        group.querySelectorAll('.chip, .option-card').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');
        answers[key] = el.dataset.value;
      }
      refreshNextState();
    });
  });

  /* ---------- Slider de intensidad ---------- */
  const scaleInput = document.getElementById('scaleInput');
  if (scaleInput) {
    const scaleValue = document.getElementById('scaleValue');
    const scaleCaption = document.getElementById('scaleCaption');
    const captionFor = v => v <= 3 ? 'Dolor leve' : v <= 6 ? 'Dolor moderado' : v <= 8 ? 'Dolor intenso' : 'Dolor incapacitante';
    answers.intensidad = Number(scaleInput.value);
    scaleInput.addEventListener('input', () => {
      scaleValue.textContent = scaleInput.value;
      scaleCaption.textContent = captionFor(Number(scaleInput.value));
      answers.intensidad = Number(scaleInput.value);
    });
  }

  /* ---------- Recordatorio (paso 9) ---------- */
  const recordatorioToggle = document.getElementById('recordatorioToggle');
  if (recordatorioToggle) {
    answers.recordatorio = recordatorioToggle.checked;
    recordatorioToggle.addEventListener('change', () => { answers.recordatorio = recordatorioToggle.checked; });
  }
  // Valor por defecto pre-seleccionado en el HTML (horario = mañana)
  const preselected = document.querySelector('.chip.selected[data-value]');
  if (preselected) {
    const group = preselected.closest('[data-key][data-type]');
    if (group) answers[group.dataset.key] = preselected.dataset.value;
  }

  /* ---------- Navegación ---------- */
  function refreshNextState() {
    const stepEl = steps[current - 1];
    nextBtn.disabled = !isStepComplete(stepEl);
  }

  function renderStep() {
    steps.forEach(s => s.style.display = 'none');
    const activeStep = steps[current - 1];
    activeStep.style.display = '';
    activeStep.classList.remove('onb-step-enter');
    // Forzar reflow para reiniciar la animación de entrada en cada paso
    void activeStep.offsetWidth;
    activeStep.classList.add('onb-step-enter');
    stepLabel.textContent = `${current} de ${total}`;
    progressFill.style.width = `${(current / total) * 100}%`;
    illustration.innerHTML = ILLUSTRATIONS[current - 1] || '';
    prevBtn.style.visibility = current === 1 ? 'hidden' : 'visible';
    nextBtn.textContent = current === total ? 'Finalizar' : 'Continuar';
    Array.from(dotsWrap.children).forEach((d, i) => d.classList.toggle('on', i === current - 1));
    refreshNextState();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  document.getElementById('nextBtn').addEventListener('click', () => {
    if (nextBtn.disabled) return;
    if (current < total) {
      current++;
      renderStep();
    } else {
      finishOnboarding();
    }
  });

  document.getElementById('prevBtn').addEventListener('click', () => {
    if (current > 1) { current--; renderStep(); }
  });

  document.getElementById('backBtn').addEventListener('click', () => {
    if (current > 1) { current--; renderStep(); }
    else { window.location.href = 'login.html'; }
  });

  document.getElementById('skipLink').addEventListener('click', (e) => {
    e.preventDefault();
    finishOnboarding(true);
  });

  function finishOnboarding(skipped = false) {
    const data = { ...answers, completedAt: new Date().toISOString(), skipped };
    MS.set(MS_KEYS.ONBOARDING, data);
    msToast(skipped ? 'Podrás completarlo luego desde tu perfil' : '¡Todo listo! Personalizando tu experiencia…', '✨');
    setTimeout(() => { window.location.href = 'dashboard.html'; }, 700);
  }

  renderStep();
})();
