/* ===================================================================
   MigraSense — recordatorios.js
   Recordatorios que SÍ se disparan (antes eran solo interruptores):

   1. Programación con hora propia: diario (registrar síntomas) y semanal
      (revisar patrones, con día y hora elegidos por la persona).
   2. Notificación del dispositivo (Notifications API + Service Worker)
      cuando MigraSense está abierta o instalada como app.
   3. Aviso dentro de la app (banner) que se queda visible hasta que la
      persona registra, lo pospone o lo descarta. También sirve cuando el
      navegador no permite notificaciones (p. ej. Safari sin instalar).
   4. ALERTA DE POSIBLE EPISODIO DE MIGRAÑA (la más importante): aparece
      cuando la probabilidad es muy alta e indica en cuántas horas podría
      empezar el episodio (MSAnalisis.alertaEpisodio).
   5. Mensajes contextuales según el riesgo y los registros recientes.

   Limitación honesta: sin servidor, ningún navegador puede despertar una
   página cerrada. Para avisos 100 % en segundo plano hace falta Web Push
   con backend (el sw.js ya incluye el manejador 'push' para cuando exista).
   =================================================================== */
const MSRec = (() => {
  const DAY = 86400000;
  const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  const KEY = (typeof MS_KEYS !== 'undefined' && MS_KEYS.REMINDERS) || 'ms_reminders';
  const HORA_RE = /^([01]\d|2[0-3]):[0-5]\d$/;

  const pad = n => String(n).padStart(2, '0');
  const dayKey = d => { d = new Date(d); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; };
  const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const plural = (n, s, p) => `${n} ${n === 1 ? s : p}`;

  /* ---------- Ajustes (con migración del formato antiguo {daily:bool, weekly:bool}) ---------- */
  const porDefecto = () => ({
    daily: { on: true, time: '20:00' },
    weekly: { on: true, day: 0, time: '19:00' },
    alerta: { on: true },
    snoozeUntil: 0, notifiedDaily: '', notifiedWeekly: '', dismissedDaily: '', dismissedWeekly: '',
    dismissedAlerta: '', notifiedAlerta: ''
  });

  function normalizar(raw) {
    const s = porDefecto();
    if (!raw || typeof raw !== 'object') return s;
    const d = raw.daily, w = raw.weekly;
    if (typeof d === 'boolean') s.daily.on = d;
    else if (d && typeof d === 'object') { s.daily.on = d.on !== false; if (HORA_RE.test(d.time)) s.daily.time = d.time; }
    if (typeof w === 'boolean') s.weekly.on = w;
    else if (w && typeof w === 'object') {
      s.weekly.on = w.on !== false;
      if (HORA_RE.test(w.time)) s.weekly.time = w.time;
      if (Number.isInteger(w.day) && w.day >= 0 && w.day <= 6) s.weekly.day = w.day;
    }
    if (raw.alerta && typeof raw.alerta === 'object') s.alerta.on = raw.alerta.on !== false;
    ['notifiedDaily', 'notifiedWeekly', 'dismissedDaily', 'dismissedWeekly', 'dismissedAlerta', 'notifiedAlerta'].forEach(k => { if (typeof raw[k] === 'string') s[k] = raw[k]; });
    if (Number.isFinite(raw.snoozeUntil)) s.snoozeUntil = raw.snoozeUntil;
    return s;
  }

  const get = () => normalizar(MS.get(KEY, null));
  const save = s => { MS.set(KEY, s); return s; };
  function update(fn) { const s = get(); fn(s); return save(normalizar(s)); }

  /* ---------- Lógica de horarios ---------- */
  function aHora(fecha, hhmm) {
    const d = new Date(fecha); const [h, m] = hhmm.split(':').map(Number);
    d.setHours(h, m, 0, 0); return d;
  }
  const registroHoy = (records, ahora) => (records || []).some(r => r && r.date && dayKey(r.date) === dayKey(ahora));

  /** ¿Qué recordatorios están vencidos ahora mismo? */
  function vencidos(s, records, ahora = Date.now()) {
    const hoy = dayKey(ahora), now = new Date(ahora);
    const daily = s.daily.on && ahora >= aHora(now, s.daily.time).getTime()
      && !registroHoy(records, ahora) && s.dismissedDaily !== hoy && ahora >= s.snoozeUntil;
    const weekly = s.weekly.on && now.getDay() === s.weekly.day
      && ahora >= aHora(now, s.weekly.time).getTime() && s.dismissedWeekly !== hoy;
    return { daily, weekly };
  }

  /** Próxima ocurrencia programada → { tipo, fecha } o null */
  function proximo(s, ahora = Date.now()) {
    const c = [];
    if (s.daily.on) {
      let f = aHora(ahora, s.daily.time);
      if (f.getTime() <= ahora) f = new Date(f.getTime() + DAY);
      c.push({ tipo: 'daily', fecha: f });
    }
    if (s.weekly.on) {
      let f = aHora(ahora, s.weekly.time);
      const falta = (s.weekly.day - f.getDay() + 7) % 7;
      f.setDate(f.getDate() + falta);
      if (f.getTime() <= ahora) f.setDate(f.getDate() + 7);
      c.push({ tipo: 'weekly', fecha: f });
    }
    c.sort((a, b) => a.fecha - b.fecha);
    return c[0] || null;
  }

  function textoProximo(s, ahora = Date.now()) {
    const p = proximo(s, ahora);
    if (!p) return 'Sin recordatorios activos';
    const dif = Math.round((new Date(dayKey(p.fecha)) - new Date(dayKey(ahora))) / DAY);
    const cuando = dif === 0 ? 'hoy' : dif === 1 ? 'mañana' : `el ${DIAS[p.fecha.getDay()]}`;
    return `Próximo aviso: ${p.tipo === 'daily' ? 'registrar síntomas' : 'resumen semanal'}, ${cuando} a las ${pad(p.fecha.getHours())}:${pad(p.fecha.getMinutes())}`;
  }

  /* ---------- Mensajes contextuales ---------- */
  function mensajeDiario(records, onb, ahora = Date.now()) {
    const recs = records || [];
    if (!recs.length) return { titulo: 'Empieza tu diario de migraña', cuerpo: 'Registra cómo te sientes hoy; toma menos de un minuto.' };
    const r = (typeof MSAnalisis !== 'undefined') ? MSAnalisis.riesgo(recs, onb || {}, ahora) : { nivel: 'bajo' };
    if (r.nivel === 'alto') return { titulo: 'Tu riesgo estimado hoy es alto', cuerpo: 'Descansa, hidrátate y registra cómo te sientes para afinar tu seguimiento.' };
    if (r.nivel === 'moderado') return { titulo: 'Hay señales a tener en cuenta', cuerpo: '¿Cómo te sientes hoy? Registrar ayuda a detectar a tiempo un episodio.' };
    return { titulo: 'Hora de tu registro diario', cuerpo: '¿Cómo te sientes hoy? Registrarlo toma menos de un minuto.' };
  }

  function mensajeSemanal(records, ahora = Date.now()) {
    const eps = (records || []).filter(r => r.type === 'episodio');
    const en = (d0, d1) => eps.filter(r => { const d = (ahora - new Date(r.date).getTime()) / DAY; return d >= d0 && d < d1; }).length;
    const esta = en(0, 7), previa = en(7, 14);
    const cuerpo = esta === 0
      ? 'Esta semana no registraste episodios. Revisa tus patrones y mantén tus hábitos.'
      : `Esta semana registraste ${plural(esta, 'episodio', 'episodios')} (${previa} la semana anterior). Revisa tus patrones.`;
    return { titulo: 'Tu resumen semanal está listo', cuerpo };
  }

  /* ---------- Notificaciones del dispositivo ---------- */
  const soporta = () => typeof window !== 'undefined' && 'Notification' in window;
  const permiso = () => soporta() ? Notification.permission : 'no-soportado';

  async function pedirPermiso() {
    if (!soporta()) return 'no-soportado';
    if (Notification.permission !== 'default') return Notification.permission;
    try { return await Notification.requestPermission(); } catch (e) { return Notification.permission; }
  }

  async function notificar({ titulo, cuerpo, url = 'dashboard.html', tag = 'migrasense' }) {
    if (permiso() !== 'granted') return false;
    const opts = { body: cuerpo, icon: 'assets/img/logo-256.png', tag, lang: 'es', data: { url } };
    try {
      const reg = navigator.serviceWorker && await navigator.serviceWorker.getRegistration();
      if (reg && reg.showNotification) { await reg.showNotification(titulo, opts); return true; }
    } catch (e) { /* cae al método clásico */ }
    try {
      const n = new Notification(titulo, opts);
      n.onclick = () => { window.focus(); window.location.href = url; n.close(); };
      return true;
    } catch (e) { return false; }
  }

  function registrarSW() {
    if (!('serviceWorker' in navigator)) return Promise.resolve(null);
    const ok = location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname);
    if (!ok) return Promise.resolve(null);
    return navigator.serviceWorker.register('sw.js').catch(() => null);
  }

  /* ---------- Aviso dentro de la app ---------- */
  function quitarBanner() { const el = document.getElementById('remBanner'); if (el) el.remove(); }

  function mostrarBanner(tipo, msg, extra = {}) {
    const host = document.querySelector('.screen');
    if (!host) return;
    const previo = document.getElementById('remBanner');
    if (previo && previo.dataset.tipo === tipo && previo.dataset.clave === (extra.clave || '')) return;
    if (previo) previo.remove();
    const el = document.createElement('div');
    const alerta = tipo === 'alerta';
    const diario = tipo === 'daily';
    el.id = 'remBanner'; el.className = 'rem-banner' + (alerta ? ' rem-alerta' : ''); el.dataset.tipo = tipo; el.dataset.clave = extra.clave || '';
    el.setAttribute('role', alerta ? 'alert' : 'status');
    const acciones = alerta
      ? `<a class="btn btn-primary btn-sm" href="registro.html?tab=prodromico">Registrar síntomas</a>
         <a class="btn btn-outline btn-sm" href="consejos.html">Ver consejos</a>`
      : `<a class="btn btn-primary btn-sm" href="${diario ? 'registro.html' : 'historial.html'}">${diario ? 'Registrar ahora' : 'Ver historial'}</a>
         ${diario ? '<button type="button" class="btn btn-outline btn-sm" data-rem="snooze">En 1 hora</button>' : ''}`;
    el.innerHTML = `
      <div class="rem-banner-icon" aria-hidden="true">${alerta ? '⚠️' : '🔔'}</div>
      <div class="rem-banner-body"><strong>${esc(msg.titulo)}</strong><span>${esc(msg.cuerpo)}</span></div>
      <div class="rem-banner-actions">
        ${acciones}
        <button type="button" class="rem-x" data-rem="dismiss" aria-label="Descartar aviso">×</button>
      </div>`;
    const topbar = host.querySelector('.topbar');
    host.insertBefore(el, topbar ? topbar.nextSibling : host.firstChild);

    el.addEventListener('click', e => {
      const b = e.target.closest('[data-rem]');
      if (!b) return;
      const hoy = dayKey(Date.now());
      if (b.dataset.rem === 'snooze') {
        update(s => { s.snoozeUntil = Date.now() + 3600000; });
        if (typeof msToast === 'function') msToast('Te lo recordamos en 1 hora', '⏰');
      } else if (alerta) {
        update(s => { s.dismissedAlerta = extra.clave || hoy; });
      } else {
        update(s => { if (diario) s.dismissedDaily = hoy; else s.dismissedWeekly = hoy; });
      }
      quitarBanner();
    });
  }

  /* ---------- Comprobación periódica ---------- */
  let intervalo = null;
  const paginaActual = () => (location.pathname.split('/').pop() || 'dashboard.html');
  const sinBanner = () => ['registro.html', 'perfil.html'].includes(paginaActual());

  async function comprobar() {
    if (!MS.isLoggedIn() || !MS.hasOnboarding()) return;
    const ahora = Date.now(), hoy = dayKey(ahora);
    const s = get(), records = MS.get(MS_KEYS.RECORDS, []), onb = MS.get(MS_KEYS.ONBOARDING, {});
    const v = vencidos(s, records, ahora);
    const visible = document.visibilityState === 'visible';

    /* 1) Alerta de posible episodio: máxima prioridad */
    const al = (s.alerta.on && typeof MSAnalisis !== 'undefined') ? MSAnalisis.alertaEpisodio(records, onb, ahora) : { activa: false };
    const alertaVigente = al.activa && s.dismissedAlerta !== al.clave;
    if (!alertaVigente) { const b = document.getElementById('remBanner'); if (b && b.dataset.tipo === 'alerta') quitarBanner(); }
    if (alertaVigente && !visible && s.notifiedAlerta !== al.clave) {
      const ok = await notificar({ titulo: '⚠️ ' + al.titulo, cuerpo: al.cuerpo, url: 'registro.html?tab=prodromico', tag: 'migrasense-alerta' });
      if (ok) update(x => { x.notifiedAlerta = al.clave; });
    }
    if (alertaVigente && visible && !['registro.html'].includes(paginaActual())) {
      mostrarBanner('alerta', al, { clave: al.clave });
      return;
    }

    /* 2) Recordatorios diario y semanal */
    if (registroHoy(records, ahora) || !v.daily) { const b = document.getElementById('remBanner'); if (b && b.dataset.tipo === 'daily') quitarBanner(); }

    const items = [];
    if (v.daily) items.push({ tipo: 'daily', msg: mensajeDiario(records, onb, ahora), url: 'registro.html', notif: 'notifiedDaily' });
    if (v.weekly) items.push({ tipo: 'weekly', msg: mensajeSemanal(records, ahora), url: 'historial.html', notif: 'notifiedWeekly' });

    for (const it of items) {
      // Notificación del dispositivo: una sola vez por día y solo si la pestaña no está a la vista
      if (!visible && s[it.notif] !== hoy) {
        const ok = await notificar({ titulo: it.msg.titulo, cuerpo: it.msg.cuerpo, url: it.url, tag: 'migrasense-' + it.tipo });
        if (ok) update(x => { x[it.notif] = hoy; });
      }
    }
    // Aviso visible en la app (el diario tiene prioridad sobre el semanal)
    if (visible && !sinBanner() && items.length) mostrarBanner(items[0].tipo, items[0].msg);
  }

  function iniciar() {
    if (!MS.isLoggedIn()) return;
    registrarSW();
    comprobar();
    clearInterval(intervalo);
    intervalo = setInterval(comprobar, 30000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') comprobar(); });
    window.addEventListener('storage', e => { if (e.key === KEY || e.key === MS_KEYS.RECORDS) comprobar(); });
  }

  async function probar() {
    const p = await pedirPermiso();
    const msg = { titulo: 'Así se verán tus recordatorios', cuerpo: 'Si ves este aviso, las notificaciones de MigraSense funcionan.' };
    if (p === 'granted') { const ok = await notificar({ ...msg, tag: 'migrasense-prueba' }); return ok ? 'ok' : 'fallo'; }
    return p;
  }

  /** Notificación de ejemplo de "posible episodio" (para que la persona vea cómo se verá) */
  async function probarAlerta() {
    const p = await pedirPermiso();
    const msg = { titulo: '⚠️ Posible episodio de migraña', cuerpo: 'En 5 a 10 horas puede que tengas un episodio de migraña. Descansa, hidrátate y evita tus desencadenantes.' };
    if (p === 'granted') { const ok = await notificar({ ...msg, url: 'registro.html?tab=prodromico', tag: 'migrasense-alerta-prueba' }); return ok ? 'ok' : 'fallo'; }
    return p;
  }

  if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', iniciar);

  return {
    get, save, update, normalizar, vencidos, proximo, textoProximo, probarAlerta,
    mensajeDiario, mensajeSemanal, permiso, pedirPermiso, notificar, probar, comprobar,
    DIAS
  };
})();
