/* ===================================================================
   MigraSense — registro.js
   Maneja las dos pestañas de registro: síntomas prodrómicos y
   episodio de migraña completo. Guarda los registros en el servidor
   (POST /api/registros/prodromico y /api/registros/episodio).
   =================================================================== */

msAlCargar(() => {
  if (!MS.requireAuth()) return;

  /* ---------- Tabs ---------- */
  const tabBtns = document.querySelectorAll('.tab-btn');
  const panels = document.querySelectorAll('.tab-panel');
  function setTab(name) {
    tabBtns.forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    panels.forEach(p => p.classList.toggle('active', p.id === `panel-${name}`));
  }
  tabBtns.forEach(b => b.addEventListener('click', () => setTab(b.dataset.tab)));

  const urlTab = new URLSearchParams(window.location.search).get('tab');
  if (urlTab === 'episodio') setTab('episodio');

  /* Edición desde el historial: se reutiliza el formulario original. */
  const editarId = new URLSearchParams(window.location.search).get('editar');
  const registroEditado = editarId ? MS.get(MS_KEYS.RECORDS, []).find(x => String(x.id) === editarId) : null;
  if (editarId && !registroEditado) {
    msToast('No se encontró el registro para editar', '⚠️');
    window.location.href = 'historial.html';
    return;
  }
  if (registroEditado) {
    const titulo = document.getElementById('registroTitulo');
    if (titulo) titulo.textContent = registroEditado.type === 'episodio' ? 'Editar migraña' : 'Editar síntoma prodrómico';
    setTab(registroEditado.type);
    tabBtns.forEach(b => { if (b.dataset.tab !== registroEditado.type) b.style.display = 'none'; });
  }

  /* ---------- Fecha/hora actual por defecto en episodio ---------- */
  const epFecha = document.getElementById('epFecha');
  const epHoraInicio = document.getElementById('epHoraInicio');
  const epHoraFin = document.getElementById('epHoraFin');
  const ahoraLocal = new Date();
  const pad = n => String(n).padStart(2, '0');
  const fechaISO = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  epFecha.value = fechaISO(ahoraLocal);
  epFecha.max = fechaISO(ahoraLocal);
  /* Fin del episodio: null si sigue "Aún en curso" */
  function tiemposEpisodio() {
    if (!epFecha.value || !epHoraInicio.value || !epHoraFin.value) return null;
    const inicio = new Date(`${epFecha.value}T${epHoraInicio.value}:00`);
    const fin = new Date(`${epFecha.value}T${epHoraFin.value}:00`);
    if (Number.isNaN(inicio.getTime()) || Number.isNaN(fin.getTime())) return null;
    if (fin < inicio) fin.setDate(fin.getDate()+1);
    const minutos = Math.round((fin-inicio)/60000);
    return {inicio,fin,minutos};
  }
  function actualizarDuracion() {
    if (typeof epFinModo !== 'undefined' && getSingleValue(epFinModo) === 'curso') return;
    const t = tiemposEpisodio();
    document.getElementById('epDuracionExacta').textContent = t && t.minutos > 0
      ? `Duración exacta: ${Math.floor(t.minutos/1440)} día(s), ${Math.floor(t.minutos%1440/60)} hora(s) y ${t.minutos%60} minuto(s).`
      : 'Completa una hora de inicio y fin diferentes para calcular la duración.';
  }
  [epFecha,epHoraInicio,epHoraFin].forEach(el=>el.addEventListener('change',actualizarDuracion));

  /* ---------- Helpers de selección tipo chip / option-card ----------
     Escuchamos 'change' en el <input>, no 'click' en el <label>: un
     <label> que envuelve un <input> reenvía un click sintético al
     input, que vuelve a burbujear por el label. Si el handler estuviera
     en 'click', se ejecutaría dos veces por cada toque y la selección
     se deshacía sola (el bug de "no deja elegir"). */
  function bindSingle(container) {
    container.querySelectorAll('.chip, .option-card').forEach(el => {
      const input = el.querySelector('input');
      if (!input) return;
      input.addEventListener('change', () => {
        container.querySelectorAll('.chip, .option-card').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');
      });
    });
  }
  function bindMulti(container) {
    container.querySelectorAll('.chip, .option-card').forEach(el => {
      const input = el.querySelector('input');
      if (!input) return;
      input.addEventListener('change', () => {
        el.classList.toggle('selected', input.checked);
      });
    });
  }
  function getSingleValue(container) {
    const sel = container.querySelector('.chip.selected, .option-card.selected');
    return sel ? sel.dataset.value : null;
  }
  function getMultiValues(container) {
    return Array.from(container.querySelectorAll('.chip.selected, .option-card.selected')).map(el => el.dataset.value);
  }
  function getMultiLabels(container) {
    return Array.from(container.querySelectorAll('.chip.selected, .option-card.selected')).map(el => el.textContent.trim());
  }

  /* ---------- Síntomas prodrómicos (pestaña 1): SOLO UNO, con "Más información" ---------- */
  const prodromicoChecklist = document.getElementById('prodromicoChecklist');
  MS_PRODROMICOS.forEach((p, i) => {
    const item = document.createElement('div');
    item.className = 'sym-item';
    item.innerHTML = `
      <label class="option-card" data-value="${p.id}">
        <input type="radio" name="prodsintoma">
        <span class="oc-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/></svg></span>
        <span class="oc-text">${p.label}</span>
        <span class="oc-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg></span>
      </label>
      <button type="button" class="sym-more" aria-expanded="false" aria-controls="symInfo${i}">
        Más información
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="sym-info" id="symInfo${i}" hidden>${p.info}</div>`;
    prodromicoChecklist.appendChild(item);
  });
  bindSingle(prodromicoChecklist);
  prodromicoChecklist.addEventListener('click', (e) => {
    const btn = e.target.closest('.sym-more');
    if (!btn) return;
    const panel = document.getElementById(btn.getAttribute('aria-controls'));
    const open = panel.hidden;
    panel.hidden = !open;
    btn.setAttribute('aria-expanded', String(open));
    btn.closest('.sym-item').classList.toggle('open', open);
  });

  /* ---------- Hora de aparición: "Ahora mismo" u otra hora ---------- */
  const prodHoraModo = document.getElementById('prodHoraModo');
  const prodHoraWrap = document.getElementById('prodHoraWrap');
  const prodHora = document.getElementById('prodHora');
  const prodFecha = document.getElementById('prodFecha');
  const hhmm = d => `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
  prodHora.value = hhmm(new Date());
  prodFecha.value = prodFecha.max = fechaISO(new Date());
  bindSingle(prodHoraModo);
  prodHoraModo.addEventListener('change', () => {
    const modo = getSingleValue(prodHoraModo);
    prodHoraWrap.style.display = modo === 'otra' ? '' : 'none';
    if (modo === 'otra') { const n = new Date(); prodFecha.value = fechaISO(n); prodHora.value = hhmm(n); prodFecha.focus(); }
  });

  /* ---------- Episodio: "Ahora mismo" / "Elegir otra hora" y "Aún en curso" / "Ya terminó" ---------- */
  const epHoraModo = document.getElementById('epHoraModo');
  const epFinModo = document.getElementById('epFinModo');
  const epFinWrap = document.getElementById('epFinWrap');
  const epCursoHint = document.getElementById('epCursoHint');
  const chipAhora = epHoraModo.querySelector('[data-value="ahora"]');
  const chipCurso = epFinModo.querySelector('[data-value="curso"]');
  const chipTermino = epFinModo.querySelector('[data-value="termino"]');
  bindSingle(epHoraModo);
  bindSingle(epFinModo);
  bindSingle(document.getElementById('epAura'));

  /** Marca una opción de un grupo de chips (como si la persona la hubiera tocado) */
  function elegir(contenedor, valor) {
    contenedor.querySelectorAll('[data-value]').forEach(el => {
      const si = el.dataset.value === valor;
      el.classList.toggle('selected', si);
      const input = el.querySelector('input');
      if (input) input.checked = si;
    });
  }

  // Solo puede haber una migraña "en curso": si ya hay otra, esta tiene que ser una que ya terminó
  const otraEnCurso = MS.get(MS_KEYS.RECORDS, []).find(x => x.type === 'episodio' && x.enCurso && (!registroEditado || x.id !== registroEditado.id));
  if (otraEnCurso) {
    document.getElementById('epEnCursoAviso').hidden = false;
    document.getElementById('epEnCursoTexto').textContent = `Empezó ${msFormatDate(otraEnCurso.inicio || otraEnCurso.date)}. Aquí solo puedes anotar una migraña que ya terminó.`;
    chipAhora.classList.add('is-disabled');
    chipCurso.classList.add('is-disabled');
    elegir(epHoraModo, 'otra');
    elegir(epFinModo, 'termino');
  }

  function aplicarModos() {
    const ahora = getSingleValue(epHoraModo) === 'ahora';
    if (ahora) {
      const n = new Date();
      epFecha.value = fechaISO(n);
      epHoraInicio.value = hhmm(n);
      elegir(epFinModo, 'curso');            // si empieza ahora, todavía no terminó
    }
    epFecha.disabled = ahora;
    epHoraInicio.disabled = ahora;
    chipTermino.hidden = ahora;
    const curso = getSingleValue(epFinModo) === 'curso';
    epFinWrap.hidden = curso;
    epCursoHint.hidden = !curso;
    if (!curso && !epHoraFin.value) epHoraFin.value = hhmm(new Date());
    actualizarDuracion();
  }
  epHoraModo.addEventListener('change', aplicarModos);
  epFinModo.addEventListener('change', aplicarModos);

  /* ---------- Construir grid de síntomas (pestaña 2, versión compacta) ---------- */
  const epSintomas = document.getElementById('epSintomas');
  [{id:'ninguno',label:'Ningún síntoma'}, ...MS_PRODROMICOS].forEach(p => {
    const label = document.createElement('label');
    label.className = 'chip';
    label.dataset.value = p.id;
    label.innerHTML = `<input type="checkbox">${p.label}`;
    epSintomas.appendChild(label);
  });
  bindMulti(epSintomas);
  epSintomas.addEventListener('change', e => {
    const input = e.target;
    if (!input.matches('input')) return;
    const ninguno = epSintomas.querySelector('[data-value="ninguno"] input');
    if (input === ninguno && input.checked) {
      epSintomas.querySelectorAll('input').forEach(other => { if(other !== ninguno){other.checked=false;other.closest('.chip').classList.remove('selected');} });
    } else if (input !== ninguno && input.checked) {
      ninguno.checked=false;ninguno.closest('.chip').classList.remove('selected');
    }
  });

  /* ---------- Construir grid de disparadores (pestaña 2) ---------- */
  const epDisparadores = document.getElementById('epDisparadores');
  MS_TRIGGERS.forEach(t => {
    const label = document.createElement('label');
    label.className = 'chip';
    label.dataset.value = t.id;
    label.innerHTML = `<input type="checkbox">${t.label}`;
    epDisparadores.appendChild(label);
  });
  bindMulti(epDisparadores);


  bindSingle(document.getElementById('epMedicacion'));

  /* ---------- Mostrar/ocultar nombre del medicamento ---------- */
  const epMedNombreWrap = document.getElementById('epMedNombreWrap');
  document.getElementById('epMedicacion').addEventListener('change', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    epMedNombreWrap.style.display = chip.dataset.value === 'si' ? '' : 'none';
  });

  /* ---------- Sliders con leyenda ---------- */
  function bindScale(inputId, valueId, captionId) {
    const input = document.getElementById(inputId);
    const value = document.getElementById(valueId);
    const caption = document.getElementById(captionId);
    const captionFor = v => v <= 3 ? 'Leve' : v <= 6 ? 'Moderado' : v <= 8 ? 'Intenso' : 'Incapacitante';
    input.addEventListener('input', () => {
      value.textContent = input.value;
      caption.textContent = captionFor(Number(input.value));
    });
  }
  bindScale('prodIntensidad', 'prodValue', 'prodCaption');
  bindScale('epIntensidad', 'epValue', 'epCaption');

  let horasOriginales = null;

  /* Rellenar todos los campos al editar, manteniendo el diseño existente. */
  if (registroEditado) {
    const r = registroEditado;
    const seleccionar = (contenedor, valores) => {
      const elegidos = (valores || []).map(v => String(v).toLowerCase());
      contenedor.querySelectorAll('[data-value]').forEach(el => {
        const codigo = el.dataset.value.toLowerCase();
        const catalogo = [...MS_PRODROMICOS, ...MS_TRIGGERS].find(x => x.id === codigo);
        const activo = elegidos.includes(codigo) || (catalogo && elegidos.includes(catalogo.label.toLowerCase()));
        const input = el.querySelector('input');
        if (input) input.checked = !!activo;
        el.classList.toggle('selected', !!activo);
      });
    };
    const inicio = new Date(r.inicio || r.date);
    if (r.type === 'episodio') {
      document.getElementById('epIntensidad').value = r.intensidad || 6;
      document.getElementById('epIntensidad').dispatchEvent(new Event('input'));
      // Al editar no hay "Ahora mismo": se muestran la fecha y horas guardadas
      elegir(epHoraModo, 'otra');
      epHoraModo.hidden = true;
      if (!Number.isNaN(inicio.getTime())) {
        epFecha.value = fechaISO(inicio);
        epHoraInicio.value = hhmm(inicio);
        if (r.enCurso) {
          elegir(epFinModo, 'curso');
        } else {
          elegir(epFinModo, 'termino');
          // Registros viejos solo tienen un rango de duración: se usa un valor dentro del rango
          const minutosRango = { menos_4h: 120, '4_24h': 360, '1_3d': 1439, mas_3d: 1439 }[r.duracion] || 60;
          const fin = r.fin ? new Date(conZonaLocal(r.fin)) : new Date(inicio.getTime() + (Number(r.duracionMinutos) || minutosRango)*60000);
          epHoraFin.value = hhmm(fin);
        }
      }
      aplicarModos();
      // Para no cambiar la duración si la persona no toca las horas (p. ej. episodios de más de 1 día)
      horasOriginales = [epFecha.value, epHoraInicio.value, epHoraFin.value, getSingleValue(epFinModo)].join('|');
      seleccionar(document.getElementById('epAura'), r.aura ? [r.aura] : []);
      seleccionar(epSintomas, (r.sintomas || []).length ? r.sintomas : ['ninguno']);
      seleccionar(epDisparadores, r.disparadores);
      seleccionar(document.getElementById('epMedicacion'), [r.medicacion ? 'si' : 'no']);
      epMedNombreWrap.style.display = r.medicacion ? '' : 'none';
      document.getElementById('epMedNombre').value = r.medicamento || '';
      document.getElementById('epNotas').value = r.notas || '';
      document.getElementById('saveEpisodio').textContent = 'Guardar cambios';
    } else {
      seleccionar(prodromicoChecklist, r.sintomas);
      document.getElementById('prodIntensidad').value = r.intensidad || 4;
      document.getElementById('prodIntensidad').dispatchEvent(new Event('input'));
      document.getElementById('prodNotas').value = r.notas || '';
      if (!Number.isNaN(inicio.getTime())) {
        seleccionar(prodHoraModo, ['otra']);
        prodHoraWrap.style.display = '';
        prodFecha.value = fechaISO(inicio);
        prodHora.value = hhmm(inicio);
      }
      document.getElementById('saveProdromico').textContent = 'Guardar cambios';
    }
  }

  /* ---------- Guardar: síntoma prodrómico (uno solo, con hora de aparición) ---------- */
  document.getElementById('saveProdromico').addEventListener('click', () => {
    const sel = prodromicoChecklist.querySelector('.option-card.selected');
    if (!sel) {
      msToast('Selecciona el síntoma que notas', '⚠️');
      return;
    }

    const ahora = new Date();
    let aparicion = ahora;
    const original = registroEditado ? new Date(registroEditado.inicio || registroEditado.date) : null;
    if (getSingleValue(prodHoraModo) === 'otra') {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(prodFecha.value)) { msToast('Indica la fecha de aparición', '⚠️'); return; }
      if (!/^\d{2}:\d{2}$/.test(prodHora.value)) { msToast('Indica la hora de aparición', '⚠️'); return; }
      aparicion = new Date(`${prodFecha.value}T${prodHora.value}:00`);
      if (Number.isNaN(aparicion.getTime())) { msToast('La fecha u hora no es válida', '⚠️'); return; }
      if (aparicion.getTime() > ahora.getTime() + 60000) { msToast('La fecha y hora no pueden estar en el futuro', '⚠️'); return; }
    } else if (original && !Number.isNaN(original.getTime())) {
      aparicion = original;
    }

    guardar(document.getElementById('saveProdromico'), 'prodromico', {
      sintomas: [sel.dataset.value],
      intensidad: Number(document.getElementById('prodIntensidad').value),
      notas: document.getElementById('prodNotas').value.trim(),
      inicio: aparicion.toISOString()             // hora de aparición del síntoma
    }, 'Registro guardado con éxito');
  });

  /* Envía el registro al servidor; si falla, el formulario queda tal cual para reintentar */
  function guardar(btn, tipo, datos, mensaje, destino = 'historial.html') {
    if (btn.getAttribute('aria-busy') === 'true') return;
    const texto = btn.textContent;
    btn.setAttribute('aria-busy', 'true'); btn.textContent = 'Guardando…';
    (registroEditado ? MSApi.editarRegistro(registroEditado, datos) : MSApi.crearRegistro(tipo, datos))
      .then(() => {
        msToast(mensaje, '📝');
        setTimeout(() => { window.location.href = destino; }, 700);
      })
      .catch(err => {
        btn.removeAttribute('aria-busy'); btn.textContent = texto;
        msToast(err.message, '⚠️');
      });
  }

  /* ---------- Guardar: episodio completo ---------- */
  document.getElementById('saveEpisodio').addEventListener('click', () => {
    const enCurso = getSingleValue(epFinModo) === 'curso';
    if (enCurso && otraEnCurso) { msToast('Ya tienes una migraña en curso. Márcala como terminada en Inicio.', '⚠️'); return; }

    // "Ahora mismo": la hora exacta del momento en que guarda
    if (getSingleValue(epHoraModo) === 'ahora' && !registroEditado) {
      const n = new Date(); epFecha.value = fechaISO(n); epHoraInicio.value = hhmm(n);
    }
    if (!epFecha.value || !epHoraInicio.value) { msToast('Indica la fecha y la hora en que comenzó', '⚠️'); return; }
    const inicio = new Date(`${epFecha.value}T${epHoraInicio.value}:00`);
    if (Number.isNaN(inicio.getTime())) { msToast('La fecha u hora de inicio no es válida', '⚠️'); return; }
    if (inicio.getTime() > Date.now() + 60000) { msToast('La migraña no puede comenzar en el futuro', '⚠️'); return; }

    const medicacion = getSingleValue(document.getElementById('epMedicacion')) === 'si';
    const datos = {
      intensidad: Number(document.getElementById('epIntensidad').value),
      inicio: inicio.toISOString(),
      aura: getSingleValue(document.getElementById('epAura')),   // si | no | no_se | null (sin responder)
      sintomas: getMultiValues(epSintomas).filter(s => s !== 'ninguno'),   // códigos (luz, cervical…); "Ningún síntoma" = lista vacía
      disparadores: getMultiValues(epDisparadores),
      medicacion,
      medicamento: medicacion ? document.getElementById('epMedNombre').value.trim() : '',
      notas: document.getElementById('epNotas').value.trim()
    };

    if (enCurso) {
      datos.en_curso = true;
    } else {
      const t = tiemposEpisodio();
      if (!t || t.minutos <= 0) { msToast('Indica a qué hora terminó (distinta de la hora de inicio)', '⚠️'); return; }
      if (t.fin.getTime() > Date.now() + 60000) { msToast('La migraña no puede terminar en el futuro. Si sigue, elige «Aún en curso».', '⚠️'); return; }
      datos.en_curso = false;
      datos.duracion = t.minutos < 240 ? 'menos_4h' : t.minutos <= 1440 ? '4_24h' : t.minutos <= 4320 ? '1_3d' : 'mas_3d';
      const sinCambios = registroEditado && !registroEditado.fin && !registroEditado.enCurso &&
        horasOriginales === [epFecha.value, epHoraInicio.value, epHoraFin.value, 'termino'].join('|');
      if (sinCambios && registroEditado.duracion) {
        datos.duracion = registroEditado.duracion;   // registro viejo sin hora de fin: se conserva su rango
      } else {
        datos.fin = t.fin.toISOString();
      }
    }

    guardar(document.getElementById('saveEpisodio'), 'episodio', datos,
      enCurso ? 'Migraña registrada. Cuando termine, márcalo en Inicio.' : 'Episodio registrado',
      enCurso && !registroEditado ? 'dashboard.html' : 'historial.html');
  });

  /* Estado inicial del formulario de episodio */
  if (!registroEditado) aplicarModos();
});

/* Las fechas del servidor vienen en hora de Bolivia sin zona: se marcan -04:00 */
function conZonaLocal(f) { return (f && !/[zZ]|[+-]\d{2}:?\d{2}$/.test(f)) ? f + '-04:00' : f; }
