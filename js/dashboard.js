/* ===================================================================
   MigraSense — dashboard.js
   =================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  if (!MS.requireAuth()) return;

  const user = MS.getUser();
  const onboarding = MS.get(MS_KEYS.ONBOARDING, {});
  const records = MS.get(MS_KEYS.RECORDS, []);

  document.getElementById('greetName').textContent = user ? user.name.split(' ')[0] : 'Invitado/a';
  document.getElementById('avatarBtn').textContent = MS.initials(user ? user.name : 'MS');

  /* ---------- Riesgo estimado (demostrativo, basado en registros recientes) ---------- */
  const last7 = records.filter(r => (Date.now() - new Date(r.date).getTime()) < 7 * 86400000);
  let riskLevel = 'bajo', riskPct = 20, riskText = 'Riesgo bajo de migraña';
  let riskDesc = 'No se detectan patrones de alerta en tus últimos registros.';

  if (last7.length >= 3) {
    riskLevel = 'alto'; riskPct = 82; riskText = 'Riesgo alto de migraña';
    riskDesc = 'Se detectaron varios síntomas prodrómicos esta semana.';
  } else if (last7.length >= 1) {
    riskLevel = 'moderado'; riskPct = 52; riskText = 'Riesgo moderado de migraña';
    riskDesc = 'Se detectaron algunos patrones que podrían indicar un episodio.';
  } else if (onboarding.intensidad >= 7) {
    riskLevel = 'moderado'; riskPct = 45; riskText = 'Riesgo moderado de migraña';
    riskDesc = 'Según tu perfil, tus episodios suelen ser intensos. Registra a tiempo.';
  }

  document.getElementById('riskTitle').textContent = riskText;
  document.getElementById('riskDesc').textContent = riskDesc;
  document.getElementById('riskBar').style.width = riskPct + '%';

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

  /* ---------- Detectar señales (mock de análisis) ---------- */
  document.getElementById('detectBtn').addEventListener('click', () => {
    const modal = document.getElementById('detectModal');
    const title = document.getElementById('detectTitle');
    const body = document.getElementById('detectBody');

    if (records.length === 0) {
      title.textContent = 'Aún no hay suficientes datos';
      body.textContent = 'Registra tus síntomas prodrómicos durante algunos días para que podamos detectar patrones confiables.';
    } else {
      title.textContent = riskLevel === 'alto' ? '⚠️ Posible episodio cercano' : riskLevel === 'moderado' ? '🟠 Mantente alerta' : '🟢 Sin señales de alerta';
      body.textContent = riskDesc + ' Sigue registrando tus síntomas a diario para mejorar la precisión.';
    }
    modal.classList.add('show');
  });
  document.getElementById('detectModal').addEventListener('click', (e) => {
    if (e.target.id === 'detectModal') e.target.classList.remove('show');
  });
});
