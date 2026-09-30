/* ===================================================================
   MigraSense — dashboard.js
   =================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  if (!MS.requireAuth()) return;

  const user = MS.getUser();
  const onboarding = MS.get(MS_KEYS.ONBOARDING, {});
  const records = MS.get(MS_KEYS.RECORDS, []);

  document.getElementById('greetName').textContent = user ? user.name.split(' ')[0] : 'Invitado/a';
  document.getElementById('avatarImg').src = MS.avatarSrc(user);

  /* ---------- Riesgo estimado (reglas de puntos en js/analisis.js) ---------- */
  const risk = MSAnalisis.riesgo(records, onboarding);
  const pat = MSAnalisis.patrones(records);
  const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  document.getElementById('riskTitle').textContent = risk.titulo;
  document.getElementById('riskDesc').textContent = risk.descripcion;
  const bar = document.getElementById('riskBar');
  bar.style.width = Math.max(risk.score, 4) + '%';
  bar.dataset.level = risk.nivel;
  document.getElementById('riskPct').textContent = risk.score + '%';

  /* ---------- Síntomas recientes ---------- */
  const list = document.getElementById('recentSymptoms');
  const dotColor = { alto: 'var(--danger)', medio: 'var(--orange)', bajo: 'var(--success)' };

  if (records.length === 0) {
    list.innerHTML = `<li style="border:none; color:var(--text-faint);">Aún no tienes registros</li>`;
  } else {
    records.slice(-4).reverse().forEach(r => {
      const li = document.createElement('li');
      const color = r.intensidad >= 7 ? dotColor.alto : r.intensidad >= 4 ? dotColor.medio : dotColor.bajo;
      li.innerHTML = `<span><span class="mini-dot" style="background:${color}"></span>${r.label}</span><span>${msFormatDate(r.date)}</span>`;
      list.appendChild(li);
    });
  }

  /* ---------- Recordatorios ---------- */
  const reminders = MS.get(MS_KEYS.REMINDERS, { daily: true, weekly: true });
  document.getElementById('remDaily').checked = reminders.daily;
  document.getElementById('remWeekly').checked = reminders.weekly;
  document.getElementById('remDaily').addEventListener('change', (e) => {
    reminders.daily = e.target.checked; MS.set(MS_KEYS.REMINDERS, reminders);
    msToast(reminders.daily ? 'Recordatorio diario activado' : 'Recordatorio diario desactivado', '🔔');
  });
  document.getElementById('remWeekly').addEventListener('change', (e) => {
    reminders.weekly = e.target.checked; MS.set(MS_KEYS.REMINDERS, reminders);
  });

  /* ---------- Detectar señales: riesgo explicado + patrones reales ---------- */
  const DIAS_CORTO = ['D', 'L', 'M', 'X', 'J', 'V', 'S'];

  function detectHTML() {
    let h = `<div class="risk-summary" data-level="${risk.nivel}"><strong>${risk.score}%</strong><span>${esc(risk.titulo)}</span></div>`;

    h += '<h4 class="dm-h">Por qué este resultado</h4>';
    h += risk.factores.length
      ? '<ul class="factor-list">' + risk.factores.map(f => `<li><span class="pts">+${f.pts}</span>${esc(f.texto)}</li>`).join('') + '</ul>'
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
    h += '<p class="small muted" style="margin-top:14px;">Estimación orientativa basada en reglas simples y en tus propios registros. No es un diagnóstico médico.</p>';
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
