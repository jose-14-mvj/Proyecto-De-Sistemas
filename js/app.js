/* ===================================================================
   MigraSense — app.js
   Utilidades compartidas: sesión de usuario, almacenamiento local,
   toasts, iconos y protección de rutas.
   Los datos viven en el servidor (ver js/api.js); localStorage guarda
   solo una copia para dibujar rápido y funcionar sin conexión.
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
  // El cuestionario solo cuenta si es la versión nueva y fue completado (no se puede saltar)
  hasOnboarding() {
    const o = this.get(MS_KEYS.ONBOARDING);
    return !!o && o.version === 2 && !o.skipped;
  },

  logout() {
    MS.remove(MS_KEYS.SESSION);
    window.location.href = 'login.html';
  },

  // Foto de perfil elegida por el usuario, o la imagen predeterminada
  avatarSrc(user) {
    return (user && user.photo) ? user.photo : 'assets/img/avatar-default.png';
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
  document.querySelectorAll('.nav-item, .side-cta').forEach(item => {
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
  { id: 'hiperactividad', label: 'Hiperactividad',
    info: 'Energía inusualmente alta o inquietud: sientes necesidad de moverte o hacer muchas cosas, más de lo normal en ti.' },
  { id: 'hipoactividad', label: 'Hipoactividad',
    info: 'Lentitud o ganas de no moverte: te cuesta arrancar y prefieres quedarte quieto/a más de lo habitual.' },
  { id: 'depresion', label: 'Depresión',
    info: 'Tristeza, desánimo o pérdida de interés que aparece sin una causa clara, horas o días antes del dolor.' },
  { id: 'avidez_alimentos', label: 'Avidez por determinados alimentos',
    info: 'Antojo intenso por comidas concretas (dulces, chocolate, salados). A veces es parte del aviso del cuerpo y no la causa de la migraña.' },
  { id: 'bostezos_repetidos', label: 'Bostezos repetidos',
    info: 'Bostezar muchas veces seguidas sin tener sueño ni aburrimiento.' },
  { id: 'astenia', label: 'Astenia',
    info: 'Debilidad o falta de fuerzas general: el cuerpo se siente pesado aunque no hayas hecho esfuerzo.' },
  { id: 'cervical', label: 'Dolor o rigidez cervical',
    info: 'Tensión, molestia o dificultad para mover el cuello y la nuca.' },
  { id: 'cansancio', label: 'Cansancio',
    info: 'Fatiga o sueño mayor al habitual, incluso después de haber descansado.' },
  { id: 'animo_exaltado', label: 'Ánimo exaltado',
    info: 'Euforia, irritabilidad o humor inusualmente elevado, con cambios bruscos de ánimo.' },
  { id: 'hambre_atipica', label: 'Hambre atípica',
    info: 'Hambre fuera de horario o mucho mayor (o menor) a la que sueles tener.' },
  { id: 'concentracion', label: 'Dificultad para concentrarse',
    info: 'Te cuesta mantener la atención, leer o seguir una conversación; sensación de "mente nublada".' },
  { id: 'luz', label: 'Sensibilidad a la luz',
    info: 'La luz normal (pantallas, focos, sol) te molesta o te incomoda más de lo habitual.' },
  { id: 'ruido', label: 'Sensibilidad al ruido',
    info: 'Los sonidos cotidianos te resultan demasiado fuertes o irritantes.' },
  { id: 'nauseas', label: 'Náuseas',
    info: 'Malestar en el estómago o ganas de vomitar, con o sin vómito.' },
  { id: 'vision', label: 'Visión borrosa',
    info: 'Ves las cosas poco nítidas, con destellos o manchas (puede ser un aviso de aura).' },
  { id: 'bostezos_palidez', label: 'Bostezos o palidez',
    info: 'Bostezos acompañados de una piel más pálida de lo normal en el rostro.' }
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

/* ---------- Modo oscuro ---------- */
const MS_THEME_KEY = 'ms_theme';

function msGetPreferredTheme() {
  const saved = localStorage.getItem(MS_THEME_KEY);
  if (saved === 'dark' || saved === 'light') return saved;
  return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
}

function msApplyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  document.querySelectorAll('.theme-toggle').forEach(input => { input.checked = theme === 'dark'; });
}

function msSetTheme(theme) {
  localStorage.setItem(MS_THEME_KEY, theme);
  msApplyTheme(theme);
}

function msToggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  msSetTheme(current === 'dark' ? 'light' : 'dark');
}

function msInitThemeToggle() {
  msApplyTheme(msGetPreferredTheme());
  document.querySelectorAll('.theme-toggle').forEach(input => {
    input.addEventListener('change', () => msSetTheme(input.checked ? 'dark' : 'light'));
  });
}

document.addEventListener('DOMContentLoaded', msSetActiveNav);
document.addEventListener('DOMContentLoaded', msInitThemeToggle);

/* ---------- Armazón común: cuenta, menú de cuenta y cerrar sesión ----------
   La flecha flotante "hay más abajo" se quitó: tapaba contenido y la barra
   de navegación ya indica dónde estás. */
function msPintarCuenta() {
  const u = MS.getUser();
  const nombre = u && u.name ? u.name : 'Tu cuenta';
  const correo = u && u.email ? u.email : '';
  document.querySelectorAll('[data-user-name]').forEach(el => { el.textContent = nombre; });
  document.querySelectorAll('[data-user-email]').forEach(el => { el.textContent = correo; });
  document.querySelectorAll('img[data-user-avatar]').forEach(img => { img.src = MS.avatarSrc(u); });
}

function msIniciarArmazon() {
  msPintarCuenta();
  // Se vuelve a pintar cuando llegan los datos del servidor (js/api.js)
  if (typeof msDatosListos !== 'undefined') msDatosListos.then(msPintarCuenta).catch(() => {});

  document.querySelectorAll('[data-logout]').forEach(b => b.addEventListener('click', () => {
    b.disabled = true;
    msToast('Cerrando sesión…', '👋');
    setTimeout(() => MS.logout(), 400);
  }));

  // Menú de la cuenta (celular y tablet)
  const btn = document.getElementById('accountBtn');
  const menu = document.getElementById('accountMenu');
  if (!btn || !menu) return;
  const abrir = (si) => {
    menu.hidden = !si;
    btn.setAttribute('aria-expanded', String(si));
    if (si) { const p = menu.querySelector('a, button, input'); if (p) p.focus({ preventScroll: true }); }
  };
  btn.addEventListener('click', (e) => { e.stopPropagation(); abrir(menu.hidden); });
  document.addEventListener('click', (e) => { if (!menu.hidden && !menu.contains(e.target)) abrir(false); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !menu.hidden) { abrir(false); btn.focus(); } });
}
document.addEventListener('DOMContentLoaded', msIniciarArmazon);
