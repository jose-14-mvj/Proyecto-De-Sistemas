/* ===================================================================
   MigraSense — dashboard.js
   El riesgo viene del modelo de machine learning (GET /api/prediccion).
   Mientras responde (o si no hay conexión) se muestra la estimación por
   reglas de js/analisis.js, que luego se reemplaza por la del modelo.
   =================================================================== */

msAlCargar(() => {
  if (!MS.requireAuth()) return;

  const user = MS.getUser();
  const onboarding = MS.get(MS_KEYS.ONBOARDING, {});
  const records = MS.get(MS_KEYS.RECORDS, []);

  document.getElementById('greetName').textContent = user ? user.name.split(' ')[0] : 'Invitado/a';
  const avatarImg = document.getElementById('avatarImg');   // la foto ahora la pone la barra común (app.js)
  if (avatarImg) avatarImg.src = MS.avatarSrc(user);

  /* ---------- Migraña en curso: "La migraña terminó" ---------- */
  const ecCard = document.getElementById('enCursoCard');
  const ecHoraWrap = document.getElementById('enCursoHoraWrap');
  const ecFin = document.getElementById('enCursoFin');
  const pad2 = n => String(n).padStart(2, '0');
  const aLocalInput = d => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}T${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
  const LIMITE_HORAS = 72;   // pasado esto, se pregunta a qué hora terminó en realidad

  const enCurso = () => MS.get(MS_KEYS.RECORDS, []).find(r => r.type === 'episodio' && r.enCurso);

  function textoTranscurrido(min) {
    if (min < 1) return 'hace un momento';
    const d = Math.floor(min / 1440), h = Math.floor(min % 1440 / 60), m = min % 60;
    if (d) return `hace ${d} día${d > 1 ? 's' : ''}${h ? ` y ${h} h` : ''}`;
    return `hace ${h ? h + ' h ' : ''}${m} min`;
  }

  function pintarEnCurso() {
    const ep = enCurso();
    ecCard.hidden = !ep;
    if (!ep) return;
    const inicio = new Date(ep.inicio || ep.date);
    const min = Math.max(0, Math.round((Date.now() - inicio) / 60000));
    const larga = min >= LIMITE_HORAS * 60;
    ecCard.classList.toggle('es-larga', larga);
    const hora = inicio.toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' });
    const dias = Math.round((new Date().setHours(0, 0, 0, 0) - new Date(inicio).setHours(0, 0, 0, 0)) / 86400000);
    const cuando = dias === 0 ? 'hoy' : dias === 1 ? 'ayer' : 'el ' + inicio.toLocaleDateString('es-BO', { day: 'numeric', month: 'long' });
    document.getElementById('enCursoTitulo').textContent = larga ? '¿Ya terminó tu migraña?' : 'Tienes una migraña en curso';
    document.getElementById('enCursoTexto').textContent = larga
      ? `La marcaste como «en curso» ${textoTranscurrido(min)} (${cuando} a las ${hora}). Si ya se te pasó, indica a qué hora terminó para que tu historial quede bien.`
      : `Empezó ${cuando} a las ${hora} (${textoTranscurrido(min)}). Cuando se te pase, tócalo aquí y guardaremos la hora.`;
    document.getElementById('enCursoOtraHora').textContent = larga ? 'Indicar a qué hora terminó' : 'Terminó antes · indicar la hora';
    ecFin.min = aLocalInput(inicio);
    ecFin.max = aLocalInput(new Date());
    if (larga && ecHoraWrap.hidden && !ecFin.value) {   // después de 72 h se ofrece directamente la hora
      ecHoraWrap.hidden = false;
      ecFin.value = aLocalInput(new Date(Math.min(Date.now(), inicio.getTime() + 6 * 3600000)));
    }
  }

  async function terminar(btn, fin) {
    const ep = enCurso();
    if (!ep || btn.getAttribute('aria-busy') === 'true') return;
    const texto = btn.textContent;
    btn.setAttribute('aria-busy', 'true'); btn.textContent = 'Guardando…';
    try {
      const act = await MSApi.terminarMigrana(ep, fin);
      const m = act.duracionMinutos || 0;
      msToast(`Listo. La migraña duró ${m >= 60 ? Math.floor(m / 60) + ' h ' : ''}${m % 60} min. ¡Que te mejores!`, '💙');
      ecHoraWrap.hidden = true; ecFin.value = '';
      pintarEnCurso();
      records.splice(0, records.length, ...MS.get(MS_KEYS.RECORDS, []));
    } catch (err) {
      msToast(err.message, '⚠️');
    } finally {
      btn.removeAttribute('aria-busy'); btn.textContent = texto;
    }
  }

  document.getElementById('enCursoTerminar').addEventListener('click', e => terminar(e.currentTarget));
  document.getElementById('enCursoOtraHora').addEventListener('click', () => {
    ecHoraWrap.hidden = !ecHoraWrap.hidden;
    if (!ecHoraWrap.hidden) {
      if (!ecFin.value) ecFin.value = aLocalInput(new Date());
      ecFin.focus();
    }
  });
  document.getElementById('enCursoGuardarHora').addEventListener('click', e => {
    const ep = enCurso();
    if (!ep) return;
    const fin = ecFin.value ? new Date(ecFin.value) : null;
    if (!fin || Number.isNaN(fin.getTime())) { msToast('Indica la fecha y hora en que terminó', '⚠️'); return; }
    if (fin < new Date(ep.inicio || ep.date)) { msToast('No puede terminar antes de haber empezado', '⚠️'); return; }
    if (fin.getTime() > Date.now() + 60000) { msToast('La hora de fin no puede estar en el futuro', '⚠️'); return; }
    terminar(e.currentTarget, fin);
  });
  pintarEnCurso();
  setInterval(pintarEnCurso, 60000);

  /* ---------- Riesgo estimado: modelo de ML (o reglas si no hay conexión) ---------- */
  let risk = MSAnalisis.riesgo(records, onboarding);
  const pat = MSAnalisis.patrones(records);
  const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function pintarRiesgo(extra = '') {
    document.getElementById('riskTitle').textContent = risk.titulo;
    document.getElementById('riskDesc').textContent = risk.descripcion + extra;
    const bar = document.getElementById('riskBar');
    bar.style.width = Math.max(risk.score, 4) + '%';
    bar.dataset.level = risk.nivel;
    document.getElementById('riskPct').textContent = risk.score + '%';

    /* Posible episodio de migraña (probabilidad muy alta) */
    const alerta = MSAnalisis.alertaEpisodio(records, onboarding);
    document.getElementById('episodeAlert').hidden = !alerta.activa;
    if (alerta.activa) document.getElementById('episodeAlertText').textContent = alerta.texto;
  }
  pintarRiesgo(risk.fuente === 'ml' ? '' : ' (Calculando con el modelo de predicción…)');

  if (risk.fuente !== 'ml') {
    MSApi.prediccion()
      .then(() => {
        risk = MSAnalisis.riesgo(records, onboarding);
        pintarRiesgo();
        if (typeof MSRec !== 'undefined') MSRec.comprobar();   // la alerta usa la nueva predicción
      })
      .catch(err => {
        risk = MSAnalisis.riesgoReglas(records, onboarding);
        pintarRiesgo(' (Estimación local: el modelo de predicción no respondió. Recarga en un minuto.)');
        console.warn('Predicción ML:', err.message);
      });
  }

  /* ---------- Síntomas recientes ---------- */
  const list = document.getElementById('recentSymptoms');
  const dotColor = { alto: 'var(--danger)', medio: 'var(--orange)', bajo: 'var(--success)' };

  if (records.length === 0) {
    list.innerHTML = `<li style="border:none; color:var(--text-faint);">Aún no tienes registros</li>`;
  } else {
    [...records].sort((a, b) => new Date(b.date) - new Date(a.date)).slice(0, 4).forEach(r => {
      const li = document.createElement('li');
      const color = r.intensidad >= 7 ? dotColor.alto : r.intensidad >= 4 ? dotColor.medio : dotColor.bajo;
      li.innerHTML = `<span><span class="mini-dot" style="background:${color}"></span>${r.label}</span><span>${msFormatDate(r.date)}</span>`;
      list.appendChild(li);
    });
  }

  /* ---------- Recordatorios (lógica real en js/recordatorios.js) ---------- */
  const remDaily = document.getElementById('remDaily');
  const remWeekly = document.getElementById('remWeekly');
  const remAlerta = document.getElementById('remAlerta');
  const DIA_TXT = ['domingos', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábados'];

  function pintarRecordatorios() {
    const s = MSRec.get();
    remDaily.checked = s.daily.on;
    remWeekly.checked = s.weekly.on;
    remAlerta.checked = s.alerta.on;
    document.getElementById('remDailyWhen').textContent = s.daily.on ? `Todos los días · ${s.daily.time}` : 'Desactivado';
    document.getElementById('remWeeklyWhen').textContent = s.weekly.on ? `${DIA_TXT[s.weekly.day].replace(/^./, c => c.toUpperCase())} · ${s.weekly.time}` : 'Desactivado';
  }
  pintarRecordatorios();

  async function cambiarRecordatorio(clave, activo, nombre) {
    MSRec.update(s => { s[clave].on = activo; });
    pintarRecordatorios();
    msToast(`${nombre} ${activo ? 'activado' : 'desactivado'}`, '🔔');
    if (activo && MSRec.permiso() === 'default') {
      const p = await MSRec.pedirPermiso();
      if (p === 'granted') msToast('Avisos del dispositivo activados', '✅');
    }
    MSRec.comprobar();
  }
  remDaily.addEventListener('change', e => cambiarRecordatorio('daily', e.target.checked, 'Recordatorio diario'));
  remWeekly.addEventListener('change', e => cambiarRecordatorio('weekly', e.target.checked, 'Resumen semanal'));
  remAlerta.addEventListener('change', e => cambiarRecordatorio('alerta', e.target.checked, 'Aviso de posible migraña'));

  /* ---------- Detectar señales: riesgo explicado + patrones reales ---------- */
  const DIAS_CORTO = ['D', 'L', 'M', 'X', 'J', 'V', 'S'];

  function detectHTML() {
    let h = `<div class="risk-summary" data-level="${risk.nivel}"><strong>${risk.score}%</strong><span>${esc(risk.titulo)}</span></div>`;

    h += '<h4 class="dm-h">Por qué este resultado</h4>';
    h += risk.factores.length
      ? '<ul class="factor-list">' + risk.factores.map(f => `<li><span class="pts">${f.pts > 0 ? '+' : ''}${f.pts}</span>${esc(f.texto)}</li>`).join('') + '</ul>'
      : '<p class="small muted">Todavía no hay factores que sumen riesgo.</p>';

    h += '<h4 class="dm-h">Tus patrones</h4>';
    if (!pat.suficiente) {
      h += '<p class="small muted">Registra al menos 3 veces (síntomas o episodios) para que podamos detectar patrones confiables.</p>';
    } else {
      h += `<div class="pat-grid">
        <div><strong>${pat.episodios}</strong><span>Episodios</span></div>
        <div><strong>${pat.intensidadMedia == null ? '–' : pat.intensidadMedia}</strong><span>Intensidad media</span></div>
        <div><strong>${pat.duracionFrecuente ? esc(pat.duracionFrecuente) : '–'}</strong><span>Duración habitual</span></div>
        <div><strong>${pat.intervaloMedio ? Math.round(pat.intervaloMedio) + ' d' : '–'}</strong><span>Entre episodios</span></div>
      </div>`;

      if (pat.episodios) {
        const max = Math.max(1, ...pat.porDia);
        const orden = [1, 2, 3, 4, 5, 6, 0];   // lunes a domingo
        h += '<p class="small muted" style="margin:12px 0 4px;">Episodios por día de la semana</p><div class="dow-bars">' +
          orden.map(i => `<div class="dow"><i style="height:${Math.round(6 + 44 * pat.porDia[i] / max)}px" class="${pat.porDia[i] === max && max > 1 ? 'top' : ''}"></i><b>${pat.porDia[i] || ''}</b><span>${DIAS_CORTO[i]}</span></div>`).join('') + '</div>';
      }
      if (pat.desencadenantes.length) {
        h += '<p class="small muted" style="margin:12px 0 6px;">Desencadenantes más frecuentes</p><div class="chip-row">' +
          pat.desencadenantes.map(t => `<span class="mini-chip">${esc(t.nombre)} · ${t.n}</span>`).join('') + '</div>';
      }
      if (pat.hallazgos.length) {
        h += '<ul class="hallazgos">' + pat.hallazgos.map(t => `<li>${esc(t)}</li>`).join('') + '</ul>';
      }
    }
    h += risk.fuente === 'ml'
      ? `<p class="small muted" style="margin-top:14px;">Predicción del modelo de machine learning de MigraSense (v${esc(risk.version)}) para las próximas ${risk.ventana} horas, calculada con tus registros y tu perfil. Los puntos indican cuánto sube (+) o baja (−) cada factor tu probabilidad. Confianza: ${esc(risk.confianza)}. No es un diagnóstico médico.</p>`
      : '<p class="small muted" style="margin-top:14px;">Estimación orientativa basada en reglas simples y en tus propios registros. No es un diagnóstico médico.</p>';
    return h;
  }

  function openDetect() {
    document.getElementById('detectTitle').textContent = 'Análisis de tus señales';
    document.getElementById('detectBody').innerHTML = detectHTML();
    document.getElementById('detectModal').classList.add('show');
  }
  document.getElementById('detectBtn').addEventListener('click', openDetect);
  document.getElementById('whyBtn').addEventListener('click', openDetect);
  document.getElementById('detectModal').addEventListener('click', (e) => {
    if (e.target.id === 'detectModal') e.target.classList.remove('show');
  });
});
