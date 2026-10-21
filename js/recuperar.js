/* ===================================================================
   MigraSense — recuperar.js
   "¿Olvidaste tu contraseña?" en 2 pasos:
     1) POST /clave/olvide       {email}  → el servidor envía un código de 6 números
     2) POST /clave/restablecer  {email, codigo, password, password_confirmation}
   La respuesta del paso 1 es la misma exista o no la cuenta (no revela
   quién está registrado). Al cambiar la clave se cierran todas las sesiones.
   =================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  const $ = id => document.getElementById(id);

  const paso1 = $('paso1'), paso2 = $('paso2');
  const pedirForm = $('pedirForm'), cambiarForm = $('cambiarForm');
  const emailInput = $('email'), codigoInput = $('codigo');
  const passInput = $('password'), pass2Input = $('password2');
  const reenviarBtn = $('reenviarBtn'), otroCorreoBtn = $('otroCorreoBtn');

  const ESPERA_REENVIO = 60;   // igual que el servidor: 1 código por minuto
  let correo = '';
  let cuentaAtras = null;

  /* ---------- Utilidades ---------- */
  const showError = (fieldId, show, message) => {
    const field = $(fieldId);
    field.classList.toggle('has-error', show);
    if (show && message) {
      const el = field.querySelector('.field-error');
      if (el) el.textContent = message;
    }
  };

  const ocupado = (btn, si, texto = 'Enviando…') => {
    if (si) { btn.dataset.texto = btn.textContent; btn.textContent = texto; btn.setAttribute('aria-busy', 'true'); btn.disabled = true; }
    else { btn.textContent = btn.dataset.texto || btn.textContent; btn.removeAttribute('aria-busy'); btn.disabled = false; }
  };

  const validarCorreo = v => {
    if (!v) return 'Ingresa tu correo electrónico.';
    if (v.length > 60) return 'Máximo 60 caracteres.';
    if (/\s/.test(v) || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Ingresa un correo válido.';
    return '';
  };

  // Misma regla que el registro y el servidor
  const validarClave = v => {
    if (v.length < 10) return 'Mínimo 10 caracteres.';
    if (v.length > 128) return 'Máximo 128 caracteres.';
    if (/\s/.test(v)) return 'La contraseña no puede tener espacios.';
    if (!/[a-z]/.test(v) || !/[A-Z]/.test(v)) return 'Usa al menos una mayúscula y una minúscula.';
    if (!/\d/.test(v)) return 'Incluye al menos un número.';
    return '';
  };

  function fuerza(pw) {
    let s = 0;
    if (pw.length >= 8) s++;
    if (pw.length >= 12) s++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++;
    if (/\d/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return s <= 1 ? ['weak', 'Fácil'] : s <= 3 ? ['medium', 'Medio'] : ['strong', 'Difícil'];
  }
  function actualizarFuerza() {
    const wrap = $('pwStrength'), field = $('fieldPass');
    wrap.classList.remove('weak', 'medium', 'strong');
    if (!passInput.value) { field.classList.remove('show-strength'); return; }
    const [nivel, etiqueta] = fuerza(passInput.value);
    field.classList.add('show-strength');
    wrap.classList.add(nivel);
    $('pwStrengthLabel').textContent = etiqueta;
  }

  function iniciarCuentaAtras() {
    clearInterval(cuentaAtras);
    let s = ESPERA_REENVIO;
    reenviarBtn.disabled = true;
    reenviarBtn.textContent = `Reenviar código (${s}s)`;
    cuentaAtras = setInterval(() => {
      s--;
      if (s <= 0) {
        clearInterval(cuentaAtras);
        reenviarBtn.disabled = false;
        reenviarBtn.textContent = 'Reenviar código';
      } else {
        reenviarBtn.textContent = `Reenviar código (${s}s)`;
      }
    }, 1000);
  }

  function irPaso2() {
    $('correoMostrado').textContent = correo;
    paso1.hidden = true;
    paso2.hidden = false;
    iniciarCuentaAtras();
    codigoInput.focus();
  }

  function irPaso1() {
    clearInterval(cuentaAtras);
    cambiarForm.reset();
    actualizarFuerza();
    ['fieldCodigo', 'fieldPass', 'fieldPass2'].forEach(f => showError(f, false));
    paso2.hidden = true;
    paso1.hidden = false;
    emailInput.focus();
  }

  /* ---------- Mostrar / ocultar contraseña ---------- */
  document.querySelectorAll('.toggle-eye').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = $(btn.dataset.target);
      input.type = input.type === 'password' ? 'text' : 'password';
    });
  });

  // Sin espacios en correo y contraseñas
  [emailInput, passInput, pass2Input].forEach(input => {
    input.addEventListener('keydown', e => { if (e.key === ' ') e.preventDefault(); });
    input.addEventListener('input', () => { if (/\s/.test(input.value)) input.value = input.value.replace(/\s/g, ''); });
  });
  // El código: solo números, máximo 6 (también al pegar "123 456")
  codigoInput.addEventListener('input', () => {
    const limpio = codigoInput.value.replace(/\D/g, '').slice(0, 6);
    if (codigoInput.value !== limpio) codigoInput.value = limpio;
    if (limpio.length === 6) showError('fieldCodigo', false);
  });
  passInput.addEventListener('input', actualizarFuerza);
  passInput.addEventListener('blur', () => { const m = validarClave(passInput.value); showError('fieldPass', !!m, m); });
  pass2Input.addEventListener('blur', () => {
    const m = pass2Input.value !== passInput.value ? 'Las contraseñas no coinciden.' : '';
    showError('fieldPass2', !!m, m);
  });

  // Si viene desde el login con el correo ya escrito
  try {
    const previo = (sessionStorage.getItem('ms_recuperar_email') || '').trim();
    sessionStorage.removeItem('ms_recuperar_email');
    if (previo && !validarCorreo(previo)) emailInput.value = previo.toLowerCase();
  } catch (e) { /* nada */ }

  /* ---------- PASO 1: pedir el código ---------- */
  async function pedirCodigo(btn) {
    ocupado(btn, true);
    try {
      await MSApi.req('POST', '/clave/olvide', { email: correo });
      return true;
    } catch (err) {
      if (err.errors && err.errors.email) showError('fieldEmail', true, err.errors.email[0]);
      msToast(err.message, '⚠️');
      return false;
    } finally {
      ocupado(btn, false);
    }
  }

  pedirForm.addEventListener('submit', async e => {
    e.preventDefault();
    const v = emailInput.value.trim().toLowerCase();
    const m = validarCorreo(v);
    showError('fieldEmail', !!m, m);
    if (m) return;
    correo = v;
    if (await pedirCodigo(pedirForm.querySelector('button[type="submit"]'))) {
      msToast('Revisa tu correo', '📧');
      irPaso2();
    }
  });

  reenviarBtn.addEventListener('click', async () => {
    if (reenviarBtn.disabled) return;
    if (await pedirCodigo(reenviarBtn)) {
      msToast('Te enviamos un código nuevo. El anterior ya no sirve.', '📧');
      codigoInput.value = '';
      iniciarCuentaAtras();
    }
  });

  otroCorreoBtn.addEventListener('click', irPaso1);

  /* ---------- PASO 2: código + contraseña nueva ---------- */
  cambiarForm.addEventListener('submit', async e => {
    e.preventDefault();
    const codigo = codigoInput.value.replace(/\D/g, '');
    const pw = passInput.value, pw2 = pass2Input.value;

    let ok = true;
    const mc = /^\d{6}$/.test(codigo) ? '' : 'Escribe los 6 números del correo.';
    showError('fieldCodigo', !!mc, mc); if (mc) ok = false;
    const mp = validarClave(pw);
    showError('fieldPass', !!mp, mp); if (mp) ok = false;
    const m2 = !pw2 ? 'Confirma tu contraseña.' : pw2 !== pw ? 'Las contraseñas no coinciden.' : '';
    showError('fieldPass2', !!m2, m2); if (m2) ok = false;
    if (!ok) return;

    const btn = cambiarForm.querySelector('button[type="submit"]');
    ocupado(btn, true, 'Guardando…');
    try {
      await MSApi.req('POST', '/clave/restablecer', { email: correo, codigo, password: pw, password_confirmation: pw2 });
      clearInterval(cuentaAtras);
      msToast('¡Listo! Inicia sesión con tu nueva contraseña', '🔐');
      setTimeout(() => { location.href = 'login.html'; }, 1200);
    } catch (err) {
      ocupado(btn, false);
      const campos = { codigo: 'fieldCodigo', password: 'fieldPass', email: 'fieldCodigo' };
      let marcado = false;
      Object.entries(err.errors || {}).forEach(([k, msgs]) => {
        if (campos[k]) { showError(campos[k], true, msgs[0]); marcado = true; }
      });
      if (err.errors && err.errors.codigo) codigoInput.select();
      msToast(marcado ? 'Revisa los datos marcados' : err.message, '⚠️');
    }
  });
});
