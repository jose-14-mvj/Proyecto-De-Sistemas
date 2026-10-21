/* ===================================================================
   MigraSense — perfil.js
   Datos de la cuenta (PUT /api/cuenta), contraseña, eliminar cuenta,
   cuestionario y recordatorios. Todo se guarda en el servidor.
   =================================================================== */

msAlCargar(() => {
  if (!MS.requireAuth({ requireOnboarding: false })) return;

  const user = MS.getUser() || { name: 'Usuario', email: '' };
  const onboarding = MS.get(MS_KEYS.ONBOARDING, {});

  document.getElementById('profileName').textContent = user.name;
  document.getElementById('profileEmail').textContent = user.email;
  document.getElementById('pName').value = user.name;
  document.getElementById('pEmail').value = user.email;

  /* ---------- Foto de perfil (predeterminada o elegida) ---------- */
  const avatarImg = document.getElementById('profileAvatarImg');
  const photoInput = document.getElementById('photoInput');
  const changeBtn = document.getElementById('changePhotoBtn');
  const removeBtn = document.getElementById('removePhotoBtn');

  function paintPhoto(u) {
    avatarImg.src = MS.avatarSrc(u);
    removeBtn.style.display = u && u.photo ? '' : 'none';
    if (typeof msPintarCuenta === 'function') msPintarCuenta();   // también la barra y el menú de cuenta
  }
  paintPhoto(user);

  changeBtn.addEventListener('click', () => photoInput.click());

  photoInput.addEventListener('change', () => {
    const file = photoInput.files[0];
    if (!file) return;
    if (!/^image\/(png|jpe?g|webp)$/.test(file.type)) {
      msToast('Elige una imagen PNG, JPG o WEBP', '⚠️');
      photoInput.value = '';
      return;
    }
    if (file.size > 8 * 1024 * 1024) {
      msToast('La imagen pesa más de 8 MB', '⚠️');
      photoInput.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      const img = new Image();
      img.onload = () => {
        // Se recorta al centro (cuadrada) y se reduce a 256px (el servidor acepta hasta 300 KB)
        const SIZE = 256;
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = SIZE;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, SIZE, SIZE);
        const side = Math.min(img.width, img.height);
        ctx.drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, SIZE, SIZE);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        const current = MS.getUser() || user;
        MSApi.actualizarCuenta({ name: current.name, photo: dataUrl })
          .then(u => { paintPhoto(u); msToast('Foto de perfil actualizada', '📷'); })
          .catch(err => msToast(err.message, '⚠️'));
        photoInput.value = '';
      };
      img.onerror = () => { msToast('No se pudo leer la imagen', '⚠️'); photoInput.value = ''; };
      img.src = reader.result;
    };
    reader.onerror = () => msToast('No se pudo leer la imagen', '⚠️');
    reader.readAsDataURL(file);
  });

  removeBtn.addEventListener('click', () => {
    const current = MS.getUser() || user;
    MSApi.actualizarCuenta({ name: current.name, photo: null })
      .then(u => { paintPhoto(u); msToast('Volviste a la foto predeterminada', '✅'); })
      .catch(err => msToast(err.message, '⚠️'));
  });

  /* ---------- Resumen del perfil de migraña (respuestas del cuestionario) ---------- */
  const labels = onboarding.labels || {};
  const INFO_ROWS = [
    ['Género', 'genero'],
    ['Ocupación', 'ocupacion'],
    ['Tiempo con migrañas', 'tiempoMigrana'],
    ['Frecuencia', 'frecuencia'],
    ['¿Tienen orden de aparición?', 'tieneOrden'],
    ['Orden de aparición', 'ordenAparicion', true],
    ['Momento del día en que comienza', 'momentoDia'],
    ['Duración habitual', 'duracion'],
    ['Intensidad del dolor', 'intensidad'],
    ['Conoce la fase prodrómica', 'conocesProdromica'],
    ['Horas de sueño', 'sueno'],
    ['Nivel de estrés', 'estres'],
    ['Consume cafeína', 'cafeina'],
    ['Porciones de cafeína al día', 'porcionesCafeina', true],
    ['Condiciones', 'condiciones']
  ];
  const infoBox = document.getElementById('migraineInfo');
  INFO_ROWS.forEach(([title, key, condicional]) => {
    const value = labels[key];
    if (condicional && value === undefined) return;   // pregunta que no aplicó según tus respuestas
    const text = Array.isArray(value) ? value.join(', ') : value;
    const row = document.createElement('div');
    row.className = 'settings-row';
    const label = document.createElement('div');
    label.className = 'settings-label';
    const inner = document.createElement('div');
    inner.textContent = title;
    const sub = document.createElement('div');
    sub.className = 'settings-sub';
    sub.textContent = text || 'No respondido aún';
    inner.appendChild(sub);
    label.appendChild(inner);
    row.appendChild(label);
    infoBox.appendChild(row);
  });

  /* ---------- Guardar datos personales ---------- */
  const pEmail = document.getElementById('pEmail');
  const fieldPassActual = document.getElementById('fieldPassActual');
  const pPassActual = document.getElementById('pPassActual');
  // Cambiar el correo pide la contraseña actual (lo exige el servidor)
  pEmail.addEventListener('input', () => {
    fieldPassActual.hidden = pEmail.value.trim().toLowerCase() === String((MS.getUser() || user).email).toLowerCase();
  });

  const ocupado = (btn, si) => { if (si) btn.setAttribute('aria-busy', 'true'); else btn.removeAttribute('aria-busy'); };

  document.getElementById('profileForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const name = document.getElementById('pName').value.trim();
    const email = pEmail.value.trim();
    if (!name || !email) { msToast('Completa nombre y correo', '⚠️'); return; }
    const datos = { name, email };
    if (!fieldPassActual.hidden) {
      if (!pPassActual.value) { msToast('Escribe tu contraseña actual para cambiar el correo', '⚠️'); pPassActual.focus(); return; }
      datos.current_password = pPassActual.value;
    }
    ocupado(btn, true);
    MSApi.actualizarCuenta(datos)
      .then(u => {
        document.getElementById('profileName').textContent = u.name; if (typeof msPintarCuenta === 'function') msPintarCuenta();
        document.getElementById('profileEmail').textContent = u.email;
        document.getElementById('pName').value = u.name;
        pEmail.value = u.email;
        pPassActual.value = ''; fieldPassActual.hidden = true;
        msToast('Perfil actualizado', '✅');
      })
      .catch(err => msToast(err.message, '⚠️'))
      .finally(() => ocupado(btn, false));
  });

  /* ---------- Cambiar contraseña ---------- */
  document.getElementById('claveForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const actual = document.getElementById('cActual').value;
    const nueva = document.getElementById('cNueva').value;
    const nueva2 = document.getElementById('cNueva2').value;
    if (!actual) { msToast('Escribe tu contraseña actual', '⚠️'); return; }
    if (nueva.length < 10 || !/[a-z]/.test(nueva) || !/[A-Z]/.test(nueva) || !/\d/.test(nueva)) {
      msToast('La nueva contraseña necesita 10 caracteres, mayúscula, minúscula y número', '⚠️'); return;
    }
    if (nueva !== nueva2) { msToast('Las contraseñas nuevas no coinciden', '⚠️'); return; }
    ocupado(btn, true);
    MSApi.req('PUT', '/cuenta/clave', { current_password: actual, password: nueva })
      .then(() => { e.target.reset(); msToast('Contraseña actualizada. Se cerró la sesión en tus otros dispositivos.', '🔒'); })
      .catch(err => msToast(err.message, '⚠️'))
      .finally(() => ocupado(btn, false));
  });

  /* ---------- Eliminar cuenta ---------- */
  document.getElementById('borrarCuentaBtn').addEventListener('click', (e) => {
    const clave = document.getElementById('bClave').value;
    if (!clave) { msToast('Escribe tu contraseña para confirmar', '⚠️'); return; }
    if (!confirm('¿Seguro? Tu cuenta y todos tus registros se borrarán para siempre.')) return;
    ocupado(e.target, true);
    MSApi.req('DELETE', '/cuenta', { password: clave })
      .then(() => {
        MSApi.limpiarLocal();
        msToast('Tu cuenta fue eliminada', '👋');
        setTimeout(() => { window.location.href = 'register.html'; }, 1200);
      })
      .catch(err => { ocupado(e.target, false); msToast(err.message, '⚠️'); });
  });

  /* ---------- Recordatorios (lógica real en js/recordatorios.js) ---------- */
  const $ = id => document.getElementById(id);
  const remStatus = $('remStatus'), remNext = $('remNext'), remEnable = $('remEnableBtn'), remTest = $('remTestBtn');

  const ESTADOS = {
    granted: ['ok', 'Avisos activados en este dispositivo: te llegarán a la hora elegida, aunque MigraSense esté cerrada.'],
    'granted-local': ['warn', 'Los avisos están permitidos, pero este dispositivo todavía no quedó registrado en el servidor. Toca «Enviar prueba» para registrarlo.'],
    denied: ['bad', 'Los avisos están bloqueados en este navegador. Habilítalos desde el candado junto a la dirección web. Mientras tanto verás los recordatorios dentro de la app.'],
    default: ['warn', 'Los avisos del dispositivo aún no están activados. Verás los recordatorios dentro de la app.'],
    'no-soportado': ['warn', 'Este navegador no admite avisos del dispositivo (en iPhone, instala MigraSense en la pantalla de inicio). Verás los avisos dentro de la app.']
  };

  function pintarRecordatorios() {
    const s = MSRec.get();
    $('pRemDaily').checked = s.daily.on;
    $('pDailyTime').value = s.daily.time;
    $('pRemWeekly').checked = s.weekly.on;
    $('pRemAlerta').checked = s.alerta.on;
    $('pWeeklyDay').value = String(s.weekly.day);
    $('pWeeklyTime').value = s.weekly.time;
    const p = MSRec.permiso();
    const clave = p === 'granted' && MSRec.soportaPush() && !MSRec.pushActivo() ? 'granted-local' : p;
    const [estado, texto] = ESTADOS[clave] || ESTADOS.default;
    remStatus.dataset.estado = estado;
    remStatus.textContent = texto;
    remEnable.style.display = p === 'default' ? '' : 'none';
    remNext.textContent = MSRec.textoProximo(s);
  }
  pintarRecordatorios();

  async function activarSiHaceFalta() {
    if (MSRec.permiso() === 'default') await MSRec.pedirPermiso();
    if (MSRec.permiso() === 'granted') await MSRec.suscribirPush();
    pintarRecordatorios();
  }
  const HORA_OK = v => /^([01]\d|2[0-3]):[0-5]\d$/.test(v);

  $('pRemDaily').addEventListener('change', async e => {
    MSRec.update(s => { s.daily.on = e.target.checked; });
    msToast(e.target.checked ? 'Recordatorio diario activado' : 'Recordatorio diario desactivado', '🔔');
    if (e.target.checked) await activarSiHaceFalta();
    pintarRecordatorios(); MSRec.comprobar();
  });
  $('pRemWeekly').addEventListener('change', async e => {
    MSRec.update(s => { s.weekly.on = e.target.checked; });
    msToast(e.target.checked ? 'Resumen semanal activado' : 'Resumen semanal desactivado', '🔔');
    if (e.target.checked) await activarSiHaceFalta();
    pintarRecordatorios(); MSRec.comprobar();
  });
  $('pRemAlerta').addEventListener('change', async e => {
    MSRec.update(s => { s.alerta.on = e.target.checked; });
    msToast(e.target.checked ? 'Aviso de posible episodio activado' : 'Aviso de posible episodio desactivado', '⚠️');
    if (e.target.checked) await activarSiHaceFalta();
    pintarRecordatorios(); MSRec.comprobar();
  });
  $('pDailyTime').addEventListener('change', e => {
    if (!HORA_OK(e.target.value)) { pintarRecordatorios(); return; }
    MSRec.update(s => { s.daily.time = e.target.value; s.dismissedDaily = ''; s.notifiedDaily = ''; s.snoozeUntil = 0; });
    pintarRecordatorios(); msToast(`Recordatorio diario a las ${e.target.value}`, '⏰'); MSRec.comprobar();
  });
  $('pWeeklyTime').addEventListener('change', e => {
    if (!HORA_OK(e.target.value)) { pintarRecordatorios(); return; }
    MSRec.update(s => { s.weekly.time = e.target.value; s.dismissedWeekly = ''; s.notifiedWeekly = ''; });
    pintarRecordatorios(); msToast(`Resumen semanal a las ${e.target.value}`, '⏰'); MSRec.comprobar();
  });
  $('pWeeklyDay').addEventListener('change', e => {
    MSRec.update(s => { s.weekly.day = Number(e.target.value); s.dismissedWeekly = ''; s.notifiedWeekly = ''; });
    pintarRecordatorios(); MSRec.comprobar();
  });

  remEnable.addEventListener('click', async () => {
    const p = await MSRec.pedirPermiso();
    const r = p === 'granted' ? await MSRec.suscribirPush() : p;
    pintarRecordatorios();
    if (r === 'ok') msToast('Avisos activados: llegarán aunque la app esté cerrada', '✅');
    else if (p === 'granted') msToast('Avisos permitidos; no se pudo registrar el dispositivo en el servidor', '⚠️');
  });
  // Al entrar al perfil con permiso ya dado, se registra el dispositivo si faltaba
  if (MSRec.permiso() === 'granted' && MSRec.soportaPush() && !MSRec.pushActivo()) MSRec.suscribirPush().then(pintarRecordatorios);
  remTest.addEventListener('click', async () => {
    const r = await MSRec.probar();
    pintarRecordatorios();
    if (r === 'ok') msToast('Aviso de prueba enviado: debería aparecer en unos segundos', '🔔');
    else if (r === 'denied') msToast('Las notificaciones están bloqueadas en este navegador', '⚠️');
    else if (r === 'no-soportado') msToast('Este navegador no admite notificaciones del dispositivo', '⚠️');
    else msToast('No se pudo enviar la notificación', '⚠️');
  });
  $('remTestAlertBtn').addEventListener('click', async () => {
    const r = await MSRec.probarAlerta();
    pintarRecordatorios();
    if (r === 'ok') msToast('Aviso de prueba enviado', '⚠️');
    else if (r === 'denied') msToast('Las notificaciones están bloqueadas en este navegador', '⚠️');
    else if (r === 'no-soportado') msToast('Este navegador no admite notificaciones del dispositivo', '⚠️');
    else msToast('No se pudo enviar la notificación', '⚠️');
  });
  if (location.hash === '#recordatorios') setTimeout(() => $('recordatorios').scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);

  /* ---------- Cerrar sesión ---------- */
  document.getElementById('logoutBtn').addEventListener('click', () => {
    msToast('Cerrando sesión…', '👋');
    setTimeout(() => MS.logout(), 500);
  });
});