/* ===================================================================
   MigraSense — api.js
   Conexión del frontend con la API de Laravel (Render + Aiven).

   - Guarda el token de sesión (Sanctum) y lo envía en cada petición.
   - Los datos de la persona (usuario, cuestionario, registros y
     recordatorios) viven en la BASE DE DATOS. En el navegador solo se
     guarda una COPIA (caché) para que las páginas se dibujen rápido y
     sigan mostrándose si se corta el internet.
   - msAlCargar(fn): espera a que la página cargue Y a que se sincronicen
     los datos con el servidor, y recién ahí ejecuta el código de la página.

   Para probar con Laravel en tu computadora, en la consola del navegador:
     localStorage.setItem('ms_api_url', 'http://127.0.0.1:8000/api')
   (y para volver a la nube: localStorage.removeItem('ms_api_url'))
   =================================================================== */

const MS_API_URL_NUBE = 'https://migrasence-api.onrender.com/api';

const MSApi = (() => {
  const TOKEN = 'ms_token';
  const PRED = 'ms_prediccion';
  const DATOS_PERSONA = [MS_KEYS.USER, MS_KEYS.ONBOARDING, MS_KEYS.SESSION, MS_KEYS.RECORDS, MS_KEYS.REMINDERS, TOKEN, PRED];

  const base = () => {
    let u = null;
    try { u = localStorage.getItem('ms_api_url'); } catch (e) { /* sin almacenamiento */ }
    return (u || MS_API_URL_NUBE).replace(/\/+$/, '');
  };
  const token = () => { try { return localStorage.getItem(TOKEN); } catch (e) { return null; } };
  const espera = ms => new Promise(r => setTimeout(r, ms));

  class ApiError extends Error {
    constructor(status, data) {
      const primero = data && data.errors ? Object.values(data.errors).flat()[0] : null;
      super(primero || (data && (data.error || data.message)) || 'Ocurrió un error. Intenta de nuevo.');
      this.status = status; this.errors = (data && data.errors) || {}; this.data = data || {};
    }
  }

  /* ---------- Aviso "despertando el servidor" (Render gratis duerme tras 15 min) ---------- */
  let avisoTimer = null, pendientes = 0;
  function avisoInicio() {
    pendientes++;
    if (avisoTimer) return;
    avisoTimer = setTimeout(() => {
      if (pendientes > 0 && typeof msToast === 'function') msToast('Conectando con el servidor… la primera vez puede tardar hasta 1 minuto', '⏳');
    }, 4000);
  }
  function avisoFin() {
    pendientes = Math.max(0, pendientes - 1);
    if (!pendientes) { clearTimeout(avisoTimer); avisoTimer = null; }
  }

  /** Borra todo lo de la persona en este navegador (no toca el tema claro/oscuro) */
  function limpiarLocal() { DATOS_PERSONA.forEach(k => { try { localStorage.removeItem(k); } catch (e) { /* nada */ } }); }

  /**
   * Petición a la API. Devuelve el JSON o lanza ApiError (con .status y .errors).
   * status 0 = no hubo conexión.
   */
  async function req(metodo, ruta, cuerpo, { reintentos = 2 } = {}) {
    const headers = { Accept: 'application/json' };
    if (cuerpo !== undefined) headers['Content-Type'] = 'application/json';
    const t = token();
    if (t) headers.Authorization = 'Bearer ' + t;

    avisoInicio();
    try {
      for (let intento = 0; ; intento++) {
        let r;
        try {
          r = await fetch(base() + ruta, { method: metodo, headers, body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo), cache: 'no-store' });
        } catch (e) {
          // Sin internet o el servidor está despertando: se reintenta con pausa
          if (intento < reintentos) { await espera(5000 * (intento + 1)); continue; }
          throw new ApiError(0, { error: 'No se pudo conectar con el servidor. Revisa tu internet e intenta de nuevo.' });
        }
        // Render responde 502/503/504 mientras arranca (salvo el 503 propio de /prediccion, que trae JSON)
        const esJson = (r.headers.get('content-type') || '').includes('application/json');
        if ([502, 503, 504].includes(r.status) && !esJson && intento < reintentos) { await espera(5000 * (intento + 1)); continue; }

        const data = esJson ? await r.json().catch(() => ({})) : {};
        if (r.ok) return data;

        if (r.status === 401 && t && ruta !== '/login') {
          // Token vencido o cerrado en otro lado: volver a iniciar sesión
          limpiarLocal();
          if (!/login\.html$/.test(location.pathname)) {
            if (typeof msToast === 'function') msToast('Tu sesión expiró. Inicia sesión de nuevo.', '🔒');
            setTimeout(() => { location.href = 'login.html'; }, 900);
          }
        }
        if (r.status === 429) throw new ApiError(429, { error: 'Demasiados intentos. Espera un minuto y vuelve a probar.' });
        throw new ApiError(r.status, data);
      }
    } finally { avisoFin(); }
  }

  /* ---------- Conversión servidor → formato que ya usaban las páginas ---------- */
  // El servidor devuelve la hora de Bolivia sin zona ("2026-10-12T18:30:00"): se marca -04:00
  const conZona = f => (f && !/[zZ]|[+-]\d{2}:?\d{2}$/.test(f)) ? f + '-04:00' : f;

  function registroLocal(r) {
    const local = { ...r, apiId: r.id, id: r.type.charAt(0) + r.id, inicio: conZona(r.inicio), date: conZona(r.date) };
    if (r.fin) local.fin = conZona(r.fin);
    return local;
  }

  function perfilLocal(perfil) {
    if (!perfil) return null;
    return { ...perfil, labels: msEtiquetasPerfil(perfil), version: 2 };
  }

  function guardarUsuario(usuario) {
    if (!usuario) return;
    const u = { ...usuario };
    if (!u.photo) delete u.photo;
    MS.set(MS_KEYS.USER, u);
  }

  function guardarPerfil(perfil) {
    const p = perfilLocal(perfil);
    if (p) MS.set(MS_KEYS.ONBOARDING, p); else MS.remove(MS_KEYS.ONBOARDING);
  }

  function guardarRecordatorios(rec) {
    if (!rec) return;
    const actual = MS.get(MS_KEYS.REMINDERS, {}) || {};
    // daily/weekly/alerta vienen del servidor; "descartado/pospuesto hoy" es solo de este dispositivo
    MS.set(MS_KEYS.REMINDERS, { ...actual, daily: rec.daily, weekly: rec.weekly, alerta: rec.alerta });
  }

  /** Inicio de sesión / registro: guarda token y datos */
  function abrirSesion(data) {
    limpiarLocal();
    localStorage.setItem(TOKEN, data.token);
    MS.set(MS_KEYS.SESSION, { email: data.usuario.email, since: new Date().toISOString() });
    guardarUsuario(data.usuario);
    guardarPerfil(data.perfil);
    MS.set(MS_KEYS.RECORDS, []);
  }

  /** Trae del servidor usuario, cuestionario, registros y recordatorios */
  async function sincronizar() {
    const [ses, regs, rec] = await Promise.all([
      req('GET', '/sesion'),
      req('GET', '/registros?limite=1000'),
      req('GET', '/recordatorios')
    ]);
    guardarUsuario(ses.usuario);
    guardarPerfil(ses.perfil);
    MS.set(MS_KEYS.RECORDS, (regs.registros || []).map(registroLocal));
    guardarRecordatorios(rec.recordatorios);
  }

  /* ---------- Acciones ---------- */
  // "Auras" ya no es un síntoma: ahora es la pregunta "¿Es una migraña con aura?" (campo aura)
  const codigoSintoma = t => { const s = MS_PRODROMICOS.find(p => p.label === t || p.id === t); return s ? s.id : t; };
  const codigoDisparador = t => { const s = MS_TRIGGERS.find(p => p.label === t || p.id === t); return s ? s.id : t; };

  // Códigos del catálogo; "Ningún síntoma" no se envía (= sin síntomas)
  function cuerpoRegistro(datos) {
    const cuerpo = { ...datos };
    if (cuerpo.sintomas) cuerpo.sintomas = cuerpo.sintomas.filter(s => s !== 'ninguno').map(codigoSintoma);
    if (cuerpo.disparadores) cuerpo.disparadores = cuerpo.disparadores.map(codigoDisparador);
    return cuerpo;
  }

  async function crearRegistro(tipo, datos) {
    const cuerpo = cuerpoRegistro(datos);
    const r = await req('POST', '/registros/' + tipo, cuerpo);
    const nuevo = registroLocal(r.registro);
    MS.set(MS_KEYS.RECORDS, [nuevo, ...MS.get(MS_KEYS.RECORDS, []).filter(x => x.id !== nuevo.id)]);
    MS.remove(PRED);
    return nuevo;
  }

  async function editarRegistro(local, datos) {
    const r = await req('PUT', `/registros/${local.type}/${local.apiId}`, cuerpoRegistro(datos));
    const act = registroLocal(r.registro);
    MS.set(MS_KEYS.RECORDS, MS.get(MS_KEYS.RECORDS, []).map(x => x.id === act.id ? act : x));
    MS.remove(PRED);   // la predicción depende de los registros
    return act;
  }

  /** "La migraña terminó": fin = ahora (o la fecha indicada) */
  async function terminarMigrana(local, fin) {
    const r = await req('POST', `/registros/episodio/${local.apiId}/terminar`, fin ? { fin: fin.toISOString() } : {});
    const act = registroLocal(r.registro);
    MS.set(MS_KEYS.RECORDS, MS.get(MS_KEYS.RECORDS, []).map(x => x.id === act.id ? act : x));
    MS.remove(PRED);
    return act;
  }

  async function eliminarRegistro(local) {
    await req('DELETE', `/registros/${local.type}/${local.apiId}`);
    MS.set(MS_KEYS.RECORDS, MS.get(MS_KEYS.RECORDS, []).filter(x => x.id !== local.id));
    MS.remove(PRED);
  }

  async function guardarCuestionario(respuestas) {
    const cuerpo = {};
    MS_PREGUNTAS.forEach(q => { if (respuestas[q.key] !== undefined) cuerpo[q.key] = respuestas[q.key]; });
    const r = await req('PUT', '/perfil', cuerpo);
    guardarPerfil(r.perfil);
    MS.remove(PRED);
    return r.perfil;
  }

  async function actualizarCuenta(datos) {
    const r = await req('PUT', '/cuenta', datos);
    guardarUsuario(r.usuario);
    return r.usuario;
  }

  // Recordatorios: se envían agrupados (si la persona toca varios interruptores seguidos)
  let recTimer = null;
  let recVersion = 0;
  let recQueue = Promise.resolve();
  function enviarRecordatorios(s) {
    clearTimeout(recTimer);
    const version = ++recVersion;
    recTimer = setTimeout(() => {
      const datos = JSON.parse(JSON.stringify({ daily: s.daily, weekly: s.weekly, alerta: s.alerta }));
      recQueue = recQueue.catch(() => {}).then(() => req('PUT', '/recordatorios', datos))
        .then(r => { if (version === recVersion) guardarRecordatorios(r.recordatorios); })
        .catch(e => { if (version === recVersion && typeof msToast === 'function') msToast('No se pudo guardar el recordatorio: ' + e.message, '⚠️'); });
    }, 600);
  }

  /** Predicción del modelo de ML (vía Laravel). Se guarda junto a una "huella" de los registros. */
  const huella = () => { const r = MS.get(MS_KEYS.RECORDS, []); return r.length + ':' + r.map(x => x.id).sort().join(','); };
  async function prediccion() {
    const r = await req('GET', '/prediccion', undefined, { reintentos: 1 });
    MS.set(PRED, { ...r.prediccion, huella: huella(), obtenida: Date.now() });
    return r.prediccion;
  }
  /** Última predicción guardada, solo si sigue vigente (mismos registros y menos de 1 hora) */
  function prediccionGuardada() {
    const p = MS.get(PRED, null);
    if (!p || p.huella !== huella() || Date.now() - p.obtenida > 3600000) return null;
    return p;
  }

  async function cerrarSesion() {
    try { await req('POST', '/logout', undefined, { reintentos: 0 }); } catch (e) { /* igual se cierra aquí */ }
    limpiarLocal();
  }

  return {
    req, ApiError, sincronizar, abrirSesion, limpiarLocal, crearRegistro, editarRegistro, eliminarRegistro, terminarMigrana,
    guardarCuestionario, actualizarCuenta, enviarRecordatorios, prediccion, prediccionGuardada, cerrarSesion,
    tieneToken: () => !!token(), base
  };
})();

/* Cerrar sesión ahora también avisa al servidor */
MS.logout = async function () {
  if (typeof MSRec !== 'undefined' && MSRec.quitarPush) {
    await Promise.race([MSRec.quitarPush(), new Promise(r => setTimeout(r, 2500))]);
  }
  await MSApi.cerrarSesion();
  window.location.href = 'login.html';
};
/* Sesión = hay token guardado */
MS.isLoggedIn = function () { return MSApi.tieneToken() && !!MS.get(MS_KEYS.SESSION); };

/**
 * Ejecuta fn cuando la página cargó y los datos ya se sincronizaron con el servidor.
 * Si no hay internet, usa la copia guardada y lo avisa.
 */
const msDatosListos = (() => {
  if (!MS.isLoggedIn()) return Promise.resolve(false);
  return MSApi.sincronizar().then(() => true).catch(e => {
    if (e.status === 401) return false;
    window.addEventListener('DOMContentLoaded', () => msToast('Sin conexión con el servidor: mostrando tus datos guardados', '📴'));
    return false;
  });
})();

function msAlCargar(fn) {
  const dom = document.readyState === 'loading'
    ? new Promise(r => document.addEventListener('DOMContentLoaded', r, { once: true }))
    : Promise.resolve();
  let cargando = null;
  const t = setTimeout(() => {
    cargando = document.createElement('div');
    cargando.className = 'ms-cargando';
    cargando.innerHTML = '<div class="ms-spinner"></div><p>Cargando tus datos…</p>';
    document.body.appendChild(cargando);
  }, 500);
  Promise.all([dom, msDatosListos]).then(() => {
    clearTimeout(t);
    if (cargando) cargando.remove();
    fn();
  });
}
