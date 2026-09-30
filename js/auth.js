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

  const showError = (fieldId, show, message) => {
    const field = document.getElementById(fieldId);
    field.classList.toggle('has-error', show);
    if (show && message) {
      const errorEl = field.querySelector('.field-error');
      if (errorEl) errorEl.textContent = message;
    }
  };

  const NAME_EMAIL_MAX_LEN = 60;
  const PASSWORD_MAX_LEN = 25;

  /* ---------- Reglas de validación reutilizables ---------- */
  const validators = {
    // Nombre: 2 a 60 caracteres, solo letras (con acentos/ñ) y espacios,
    // sin espacios al inicio/final ni espacios dobles.
    name(value) {
      const v = value;
      if (v.trim().length < 2) return 'Ingresa tu nombre (mínimo 2 letras).';
      if (v.length > NAME_EMAIL_MAX_LEN) return `Máximo ${NAME_EMAIL_MAX_LEN} caracteres.`;
      if (v !== v.trim()) return 'No dejes espacios al inicio ni al final.';
      if (/\s{2,}/.test(v)) return 'No uses varios espacios seguidos.';
      if (!/^[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]+$/.test(v)) return 'El nombre solo puede tener letras.';
      return '';
    },
    // Correo: formato válido, sin espacios, máximo 60 caracteres.
    email(value) {
      const v = value;
      if (v.length === 0) return 'Ingresa tu correo electrónico.';
      if (v.length > NAME_EMAIL_MAX_LEN) return `Máximo ${NAME_EMAIL_MAX_LEN} caracteres.`;
      if (/\s/.test(v)) return 'El correo no puede tener espacios.';
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Ingresa un correo válido.';
      return '';
    },
    // Contraseña: 6 a 25 caracteres, sin espacios.
    password(value) {
      const v = value;
      if (v.length < 6) return 'Mínimo 6 caracteres.';
      if (v.length > PASSWORD_MAX_LEN) return `Máximo ${PASSWORD_MAX_LEN} caracteres.`;
      if (/\s/.test(v)) return 'La contraseña no puede tener espacios.';
      return '';
    },
    password2(value, password) {
      if (value === '') return 'Confirma tu contraseña.';
      if (value !== password) return 'Las contraseñas no coinciden.';
      return '';
    }
  };

  /* ---------- Medidor de fuerza de contraseña ---------- */
  function passwordStrength(pw) {
    if (!pw) return null;
    let score = 0;
    if (pw.length >= 8) score++;
    if (pw.length >= 12) score++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
    if (/\d/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    if (score <= 1) return { level: 'weak', label: 'Fácil' };
    if (score <= 3) return { level: 'medium', label: 'Medio' };
    return { level: 'strong', label: 'Difícil' };
  }

  function updateStrengthMeter() {
    const pwInput = document.getElementById('password');
    if (!pwInput) return;
    const wrap = document.getElementById('pwStrength');
    const label = document.getElementById('pwStrengthLabel');
    const fieldPass = document.getElementById('fieldPass');
    const result = passwordStrength(pwInput.value);

    wrap.classList.remove('weak', 'medium', 'strong');
    if (!result) {
      fieldPass.classList.remove('show-strength');
      return;
    }
    fieldPass.classList.add('show-strength');
    wrap.classList.add(result.level);
    label.textContent = result.label;
  }

  /* ---------- Formulario de REGISTRO ---------- */
  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const password2Input = document.getElementById('password2');

    // No permitir escribir espacios dentro de la contraseña ni del correo.
    [passwordInput, password2Input, emailInput].forEach(input => {
      input.addEventListener('keydown', (e) => {
        if (e.key === ' ') e.preventDefault();
      });
      input.addEventListener('input', () => {
        if (input.value.includes(' ')) input.value = input.value.replace(/\s/g, '');
      });
    });

    passwordInput.addEventListener('input', updateStrengthMeter);

    // Validación en tiempo real al salir de cada campo.
    nameInput.addEventListener('blur', () => {
      const msg = validators.name(nameInput.value);
      showError('fieldName', !!msg, msg);
    });
    emailInput.addEventListener('blur', () => {
      const msg = validators.email(emailInput.value.trim());
      showError('fieldEmail', !!msg, msg);
    });
    passwordInput.addEventListener('blur', () => {
      const msg = validators.password(passwordInput.value);
      showError('fieldPass', !!msg, msg);
    });
    password2Input.addEventListener('blur', () => {
      const msg = validators.password2(password2Input.value, passwordInput.value);
      showError('fieldPass2', !!msg, msg);
    });

    registerForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const name = nameInput.value;
      const email = emailInput.value.trim();
      const password = passwordInput.value;
      const password2 = password2Input.value;
      const terms = document.getElementById('terms').checked;

      let valid = true;

      const nameMsg = validators.name(name);
      showError('fieldName', !!nameMsg, nameMsg); if (nameMsg) valid = false;

      const emailMsg = validators.email(email);
      showError('fieldEmail', !!emailMsg, emailMsg); if (emailMsg) valid = false;

      const passMsg = validators.password(password);
      showError('fieldPass', !!passMsg, passMsg); if (passMsg) valid = false;

      const pass2Msg = validators.password2(password2, password);
      showError('fieldPass2', !!pass2Msg, pass2Msg); if (pass2Msg) valid = false;

      const termsField = document.getElementById('fieldTerms');
      if (!terms) { termsField.style.outline = '2px solid var(--danger)'; termsField.style.borderRadius = '10px'; valid = false; }
      else { termsField.style.outline = 'none'; }

      if (!valid) return;

      const trimmedName = name.trim();

      // Cuenta nueva = cuestionario nuevo (evita heredar respuestas de otra persona)
      MS.remove(MS_KEYS.ONBOARDING);
      MS.set(MS_KEYS.USER, { name: trimmedName, email, createdAt: new Date().toISOString() });
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
        MS.remove(MS_KEYS.ONBOARDING);
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

  const forgotLink = document.getElementById('forgotLink');
  if (forgotLink) {
    forgotLink.addEventListener('click', (e) => {
      e.preventDefault();
      msToast('Función disponible próximamente', 'ℹ️');
    });
  }
});