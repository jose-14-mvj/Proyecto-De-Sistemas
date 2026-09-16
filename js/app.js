/* ===================================================================
   MigraSense — app.js
   Utilidades compartidas: sesión de usuario, almacenamiento local,
   toasts, iconos y protección de rutas.
   Todo se guarda en localStorage; no hay backend real (solo frontend).
   =================================================================== */

const MS_KEYS = {
  USER: 'ms_user',
  ONBOARDING: 'ms_onboarding',
  SESSION: 'ms_session',
  RECORDS: 'ms_records',
  REMINDERS: 'ms_reminders'
};

/* ---------- Helpers de almacenamiento ---------- */
const MS = {
  get(key, fallback = null) {
    try {
      const raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw) : fallback;
    } catch (e) { return fallback; }
  },
  set(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
  },
  remove(key) { localStorage.removeItem(key); },

  getUser() { return this.get(MS_KEYS.USER); },
  isLoggedIn() { return !!this.get(MS_KEYS.SESSION); },
  hasOnboarding() { return !!this.get(MS_KEYS.ONBOARDING); },

  logout() {
    MS.remove(MS_KEYS.SESSION);
    window.location.href = 'login.html';
  },

  initials(name) {
    if (!name) return 'MS';
    const parts = name.trim().split(/\s+/);
    return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
  },

  /* Protege páginas que requieren sesión iniciada + onboarding completo */
  requireAuth({ requireOnboarding = true } = {}) {
    if (!MS.isLoggedIn()) {
      window.location.href = 'login.html';
      return false;
    }
    if (requireOnboarding && !MS.hasOnboarding()) {
      window.location.href = 'onboarding.html';
      return false;
    }
    return true;
  }
};

/* ---------- Toast ---------- */
function msToast(message, icon = '✅') {
  let toast = document.querySelector('.toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast';
    document.body.appendChild(toast);
  }
  toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;
  toast.classList.add('show');
  clearTimeout(toast._t);
  toast._t = setTimeout(() => toast.classList.remove('show'), 2600);
}

/* ---------- Marca la pestaña activa del bottom-nav ---------- */
function msSetActiveNav() {
  const page = window.location.pathname.split('/').pop() || 'dashboard.html';
  document.querySelectorAll('.nav-item').forEach(item => {
    if (item.getAttribute('data-page') === page) {
      item.classList.add('active');
    } else {
      item.classList.remove('active');
    }
  });
}

/* ---------- Datos de ejemplo: catálogos usados en varias vistas ---------- */
const MS_TRIGGERS = [
  { id: 'estres', label: 'Estrés', icon: 'brain' },
  { id: 'sueno', label: 'Falta de sueño', icon: 'moon' },
  { id: 'alimentos', label: 'Ciertos alimentos', icon: 'food' },
  { id: 'hormonal', label: 'Cambios hormonales', icon: 'wave' },
  { id: 'deshidratacion', label: 'Deshidratación', icon: 'drop' },
  { id: 'clima', label: 'Cambios de clima/presión', icon: 'cloud' },
  { id: 'pantallas', label: 'Luces o pantallas', icon: 'screen' },
  { id: 'ayuno', label: 'Ayuno prolongado', icon: 'clock' },
  { id: 'alcohol', label: 'Alcohol', icon: 'drop' },
  { id: 'olores', label: 'Olores fuertes', icon: 'wave' },
  { id: 'ejercicio', label: 'Ejercicio intenso', icon: 'bolt' },
  { id: 'ruido', label: 'Ruido excesivo', icon: 'wave' }
];

const MS_PRODROMICOS = [
  { id: 'luz', label: 'Sensibilidad a la luz' },
  { id: 'sonido', label: 'Sensibilidad al sonido' },
  { id: 'fatiga', label: 'Fatiga o bostezos frecuentes' },
  { id: 'cuello', label: 'Dolor o rigidez de cuello' },
  { id: 'antojos', label: 'Antojos de comida' },
  { id: 'humor', label: 'Cambios de humor / irritabilidad' },
  { id: 'concentracion', label: 'Dificultad para concentrarse' },
  { id: 'nauseas', label: 'Náuseas' },
  { id: 'vision', label: 'Destellos o visión borrosa (aura)' },
  { id: 'sed', label: 'Sed excesiva' },
  { id: 'retencion', label: 'Retención de líquidos' },
  { id: 'bostezos', label: 'Bostezos excesivos' }
];

function msFormatDate(iso) {
  const d = new Date(iso);
  const hoy = new Date();
  const diffDias = Math.floor((hoy - d) / 86400000);
  const hora = d.toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' });
  if (diffDias === 0) return `Hoy, ${hora}`;
  if (diffDias === 1) return `Ayer, ${hora}`;
  if (diffDias < 7) return `Hace ${diffDias} días`;
  return d.toLocaleDateString('es-BO', { day: '2-digit', month: 'short' });
}

document.addEventListener('DOMContentLoaded', msSetActiveNav);
