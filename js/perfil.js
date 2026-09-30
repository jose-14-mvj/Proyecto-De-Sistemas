/* ===================================================================
   MigraSense — perfil.js
   =================================================================== */

document.addEventListener('DOMContentLoaded', () => {
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
        // Se recorta al centro (cuadrada) y se reduce a 256px para que quepa en localStorage
        const SIZE = 256;
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = SIZE;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, SIZE, SIZE);
        const side = Math.min(img.width, img.height);
        ctx.drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, SIZE, SIZE);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        try {
          const current = MS.getUser() || user;
          MS.set(MS_KEYS.USER, { ...current, photo: dataUrl });
          paintPhoto(MS.getUser());
          msToast('Foto de perfil actualizada', '📷');
        } catch (e) {
          msToast('No se pudo guardar la foto', '⚠️');
        }
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
    const { photo, ...rest } = current;
    MS.set(MS_KEYS.USER, rest);
    paintPhoto(rest);
    msToast('Volviste a la foto predeterminada', '✅');
  });

  /* ---------- Resumen del perfil de migraña (respuestas del cuestionario) ---------- */
  const labels = onboarding.labels || {};
  const INFO_ROWS = [
    ['Género', 'genero'],
    ['Ocupación', 'ocupacion'],
    ['Tiempo con migrañas', 'tiempoMigrana'],
    ['Frecuencia', 'frecuencia'],
    ['Duración habitual', 'duracion'],
    ['Intensidad del dolor', 'intensidad'],
    ['Nivel de estrés', 'estres'],
    ['Horas de sueño', 'sueno'],
    ['Condiciones', 'condiciones']
  ];
  const infoBox = document.getElementById('migraineInfo');
  INFO_ROWS.forEach(([title, key]) => {
    const value = labels[key];
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
  document.getElementById('profileForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const name = document.getElementById('pName').value.trim();
    const email = document.getElementById('pEmail').value.trim();
    if (!name || !email) { msToast('Completa nombre y correo', '⚠️'); return; }
    const current = MS.getUser() || user;          // relee: conserva la foto guardada
    MS.set(MS_KEYS.USER, { ...current, name, email });
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