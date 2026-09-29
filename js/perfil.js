/* ===================================================================
   MigraSense — perfil.js
   =================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  if (!MS.requireAuth({ requireOnboarding: false })) return;

  const user = MS.getUser() || { name: 'Invitado/a', email: '' };
  const onboarding = MS.get(MS_KEYS.ONBOARDING, {});

  document.getElementById('profileAvatar').textContent = MS.initials(user.name);
  document.getElementById('profileName').textContent = user.name;
  document.getElementById('profileEmail').textContent = user.email;
  document.getElementById('pName').value = user.name;
  document.getElementById('pEmail').value = user.email;

  const diagnosticoMap = { si: 'Sí, tiene diagnóstico', no: 'No tiene diagnóstico', no_seguro: 'No está seguro/a' };
  const frecuenciaMap = { rara_vez: 'Rara vez (menos de 1 al mes)', '1_3_mes': '1 a 3 veces al mes', '1_2_semana': '1 a 2 veces por semana', casi_diario: 'Casi a diario' };

  document.getElementById('infoDiagnostico').textContent = diagnosticoMap[onboarding.diagnostico] || 'No respondido aún';
  document.getElementById('infoFrecuencia').textContent = frecuenciaMap[onboarding.frecuencia] || 'No respondido aún';
  document.getElementById('infoIntensidad').textContent = onboarding.intensidad ? `${onboarding.intensidad}/10` : 'No respondido aún';

  /* ---------- Guardar datos personales ---------- */
  document.getElementById('profileForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const name = document.getElementById('pName').value.trim();
    const email = document.getElementById('pEmail').value.trim();
    if (!name || !email) { msToast('Completa nombre y correo', '⚠️'); return; }
    MS.set(MS_KEYS.USER, { ...user, name, email });
    document.getElementById('profileAvatar').textContent = MS.initials(name);
    document.getElementById('profileName').textContent = name;
    document.getElementById('profileEmail').textContent = email;
    msToast('Perfil actualizado', '✅');
  });

  /* ---------- Recordatorios ---------- */
  const reminders = MS.get(MS_KEYS.REMINDERS, { daily: true, weekly: true });
  document.getElementById('pRemDaily').checked = reminders.daily;
  document.getElementById('pRemWeekly').checked = reminders.weekly;
  document.getElementById('pRemDaily').addEventListener('change', (e) => {
    reminders.daily = e.target.checked; MS.set(MS_KEYS.REMINDERS, reminders);
  });
  document.getElementById('pRemWeekly').addEventListener('change', (e) => {
    reminders.weekly = e.target.checked; MS.set(MS_KEYS.REMINDERS, reminders);
  });

  /* ---------- Cerrar sesión ---------- */
  document.getElementById('logoutBtn').addEventListener('click', () => {
    msToast('Cerrando sesión…', '👋');
    setTimeout(() => MS.logout(), 500);
  });
});
