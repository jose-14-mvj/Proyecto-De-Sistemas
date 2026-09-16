/* ===================================================================
   MigraSense — auth.js
   Validación y manejo de los formularios de login / registro.
   Simulado 100% en frontend con localStorage (sin backend).
   =================================================================== */

document.addEventListener('DOMContentLoaded', () => {

  /* ---------- Mostrar / ocultar contraseña ---------- */
  document.querySelectorAll('.toggle-eye').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      input.type = input.type === 'password' ? 'text' : 'password';
    });
  });

  const showError = (fieldId, show) => {
    const field = document.getElementById(fieldId);
    field.classList.toggle('has-error', show);
  };

  /* ---------- Formulario de REGISTRO ---------- */
  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const name = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const password2 = document.getElementById('password2').value;
      const terms = document.getElementById('terms').checked;

      let valid = true;
      showError('fieldName', name.length < 2); if (name.length < 2) valid = false;
      const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      showError('fieldEmail', !emailOk); if (!emailOk) valid = false;
      showError('fieldPass', password.length < 6); if (password.length < 6) valid = false;
      showError('fieldPass2', password2 !== password || password2 === ''); if (password2 !== password || password2 === '') valid = false;

      const termsField = document.getElementById('fieldTerms');
      if (!terms) { termsField.style.outline = '2px solid var(--danger)'; termsField.style.borderRadius = '10px'; valid = false; }
      else { termsField.style.outline = 'none'; }

      if (!valid) return;

      MS.set(MS_KEYS.USER, { name, email, createdAt: new Date().toISOString() });
      MS.set(MS_KEYS.SESSION, { email, since: new Date().toISOString() });

      msToast('Cuenta creada. ¡Bienvenido/a!', '🎉');
      setTimeout(() => { window.location.href = 'onboarding.html'; }, 700);
    });
  }

  /* ---------- Formulario de LOGIN ---------- */
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;

      let valid = true;
      const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      showError('fieldEmail', !emailOk); if (!emailOk) valid = false;
      showError('fieldPass', password.length < 1); if (password.length < 1) valid = false;
      if (!valid) return;

      // Si no existe un usuario registrado todavía, se crea uno de demostración
      let user = MS.getUser();
      if (!user || user.email !== email) {
        user = { name: email.split('@')[0], email, createdAt: new Date().toISOString() };
        MS.set(MS_KEYS.USER, user);
      }
      MS.set(MS_KEYS.SESSION, { email, since: new Date().toISOString() });

      msToast('Sesión iniciada', '👋');
      setTimeout(() => {
        window.location.href = MS.hasOnboarding() ? 'dashboard.html' : 'onboarding.html';
      }, 500);
    });
  }

  const guestBtn = document.getElementById('guestBtn');
  if (guestBtn) {
    guestBtn.addEventListener('click', () => {
      MS.set(MS_KEYS.USER, { name: 'Invitado/a', email: 'invitado@migrasense.app', createdAt: new Date().toISOString() });
      MS.set(MS_KEYS.SESSION, { email: 'invitado@migrasense.app', since: new Date().toISOString() });
      window.location.href = 'onboarding.html';
    });
  }

  const forgotLink = document.getElementById('forgotLink');
  if (forgotLink) {
    forgotLink.addEventListener('click', (e) => {
      e.preventDefault();
      msToast('Función disponible próximamente', 'ℹ️');
    });
  }
});
