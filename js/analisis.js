/* ===================================================================
   MigraSense — analisis.js
   Módulo compartido de cálculo (sin dependencias, sin acceso al DOM).
     MSAnalisis.riesgo(registros, onboarding)  -> riesgo estimado 0-100
     MSAnalisis.patrones(registros)            -> estadísticas y hallazgos
   El riesgo es un sistema de PUNTOS por reglas simples y explicables:
   cada regla que se cumple suma puntos y queda listada como "factor".
   Es una estimación orientativa, no un diagnóstico médico.
   =================================================================== */
const MSAnalisis = (() => {
  const DAY = 86400000;
  const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  const MOMENTOS = { madrugada: 'la madrugada', manana: 'la mañana', tarde: 'la tarde', noche: 'la noche' };

  const count = arr => arr.reduce((m, x) => { m[x] = (m[x] || 0) + 1; return m; }, {});
  const top = (obj, n = 3) => Object.entries(obj).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0])).slice(0, n);
  const ts = r => new Date(r.date).getTime();
  const plural = (n, s, p) => `${n} ${n === 1 ? s : p}`;
  const plDia = d => (d.endsWith('o') ? d + 's' : d);   // sábado→sábados, lunes→lunes
  const humanize = s => String(s || '').replace(/_/g, ' ');
  const promedio = a => a.length ? a.reduce((s, x) => s + x, 0) / a.length : 0;

  /* Intervalo medio (en días) entre episodios consecutivos */
  function intervaloMedio(episodios) {
    if (episodios.length < 3) return null;
    const t = episodios.map(ts).sort((a, b) => a - b);
    const gaps = t.slice(1).map((x, i) => (x - t[i]) / DAY).filter(g => g >= 0.5);
    return gaps.length ? promedio(gaps) : null;
  }

  /* ------------------------------------------------------------------
     RIESGO
     Perfil (cuestionario)  : hasta 35 puntos (solo con perfil, el riesgo nunca pasa de 44)
     Registros recientes    : el resto (prodrómicos, episodios, ritmo, desencadenantes)
     Nivel: < 25 bajo · 25-54 moderado · >= 55 alto
     ------------------------------------------------------------------ */
  function riesgo(registros, onb, ahora = Date.now()) {
    onb = onb || {};
    const recs = registros || [];
    const factores = [];
    const add = (grupo, texto, pts) => { if (pts > 0) factores.push({ grupo, texto, pts: Math.round(pts) }); };

    /* --- Perfil --- */
    add('perfil', 'Tu frecuencia habitual de migraña (perfil)',
      { menos_1_mes: 0, '1_3_mes': 4, '1_2_semana': 8, '3_mas_semana': 12, casi_diario: 15 }[onb.frecuencia] || 0);
    add('perfil', 'Tus episodios suelen ser intensos (perfil)', { intensa: 3, muy_intensa: 5 }[onb.intensidad] || 0);
    add('perfil', 'Duermes pocas horas por noche (perfil)', { menos_5: 7, '5_6': 3 }[onb.sueno] || 0);
    add('perfil', 'Nivel de estrés habitual alto (perfil)', { alto: 3, muy_alto: 6 }[onb.estres] || 0);
    if (onb.cafeina === 'si') add('perfil', 'Consumo alto de cafeína (perfil)', { '4_mas': 2 }[onb.porcionesCafeina] || 0);

    /* --- Registros recientes --- */
    const hace = r => (ahora - ts(r)) / DAY;
    const prod = recs.filter(r => r.type === 'prodromico');
    const eps = recs.filter(r => r.type === 'episodio');

    const p72 = prod.filter(r => hace(r) >= 0 && hace(r) <= 3);
    add('reciente', `${plural(p72.length, 'registro de síntomas prodrómicos', 'registros de síntomas prodrómicos')} en las últimas 72 horas`,
      Math.min(45, p72.reduce((s, r) => s + 20 + 1.2 * (Number(r.intensidad) || 0), 0)));

    const p7 = prod.filter(r => hace(r) > 3 && hace(r) <= 7);
    add('reciente', `${plural(p7.length, 'registro prodrómico', 'registros prodrómicos')} entre 3 y 7 días atrás`, Math.min(6, p7.length * 3));

    if (eps.some(r => hace(r) >= 0 && hace(r) <= 2)) add('reciente', 'Tuviste un episodio en las últimas 48 horas (puede repetirse)', 8);
    const e7 = eps.filter(r => hace(r) >= 0 && hace(r) <= 7);
    add('reciente', `${plural(e7.length, 'episodio', 'episodios')} en los últimos 7 días`, Math.min(9, e7.length * 3));

    const G = intervaloMedio(eps);
    if (G) {
      const ultimo = Math.min(...eps.filter(r => hace(r) >= 0).map(hace));
      if (isFinite(ultimo) && ultimo >= 0.8 * G && ultimo <= 1.5 * G)
        add('ritmo', `Según tu ritmo (un episodio cada ~${Math.round(G)} días), ya podría tocar uno`, 16);
    }

    const recientes30 = eps.filter(r => hace(r) >= 0 && hace(r) <= 30);
    const trig30 = top(count(recientes30.flatMap(r => r.disparadores || [])), 1)[0];
    if (trig30 && trig30[1] >= 2 && e7.some(r => (r.disparadores || []).includes(trig30[0])))
      add('reciente', `Desencadenante recurrente presente: ${trig30[0]}`, 6);

    let score = Math.min(100, factores.reduce((s, f) => s + f.pts, 0));
    const sinRegistros = recs.length === 0;
    if (sinRegistros) score = Math.min(score, 44);   // solo perfil: nunca alto

    const nivel = score >= 55 ? 'alto' : score >= 25 ? 'moderado' : 'bajo';
    const descripcion = sinRegistros
      ? (score ? 'Estimación basada solo en tu perfil. Registra tus síntomas para afinarla.' : 'Aún no hay registros. Registra tus síntomas para calcular tu riesgo.')
      : nivel === 'alto' ? 'Se combinan varias señales de alerta. Descansa, hidrátate y anota cómo te sientes.'
      : nivel === 'moderado' ? 'Hay algunas señales a tener en cuenta. Sigue registrando tus síntomas.'
      : 'No se detectan señales de alerta en tus registros recientes.';

    factores.sort((a, b) => b.pts - a.pts);
    return { score, nivel, titulo: `Riesgo ${nivel} de migraña`, descripcion, factores, sinRegistros };
  }

  /* ------------------------------------------------------------------
     PATRONES
     ------------------------------------------------------------------ */
  function momentoDe(r) {
    if (MOMENTOS[r.inicio]) return r.inicio;
    const h = new Date(r.date).getHours();
    return h < 6 ? 'madrugada' : h < 12 ? 'manana' : h < 18 ? 'tarde' : 'noche';
  }

  function patrones(registros, ahora = Date.now()) {
    const recs = registros || [];
    const eps = recs.filter(r => r.type === 'episodio');
    const out = {
      total: recs.length, episodios: eps.length, suficiente: recs.length >= 3,
      intensidadMedia: null, duracionFrecuente: null, intervaloMedio: null,
      porDia: [0, 0, 0, 0, 0, 0, 0], diaTop: null, momentoTop: null,
      desencadenantes: [], sintomas: [], medicacionPct: null, tendencia: null, hallazgos: []
    };
    if (!recs.length) return out;

    const ints = eps.map(r => Number(r.intensidad)).filter(Boolean);
    if (ints.length) out.intensidadMedia = Math.round(promedio(ints) * 10) / 10;

    const dur = top(count(eps.map(r => r.duracion).filter(Boolean)), 1)[0];
    if (dur) out.duracionFrecuente = humanize(dur[0]);

    const G = intervaloMedio(eps);
    if (G) out.intervaloMedio = Math.round(G * 10) / 10;

    eps.forEach(r => { out.porDia[new Date(r.date).getDay()]++; });
    const maxDia = Math.max(...out.porDia);
    if (eps.length >= 3 && maxDia >= 2) out.diaTop = { indice: out.porDia.indexOf(maxDia), nombre: DIAS[out.porDia.indexOf(maxDia)], n: maxDia };

    const mom = top(count(eps.map(momentoDe)), 1)[0];
    if (eps.length >= 3 && mom && mom[1] >= 2) out.momentoTop = { clave: mom[0], texto: MOMENTOS[mom[0]], n: mom[1] };

    out.desencadenantes = top(count(eps.flatMap(r => r.disparadores || [])), 3).map(([nombre, n]) => ({ nombre, n }));
    out.sintomas = top(count(recs.flatMap(r => r.sintomas || [])), 3).map(([nombre, n]) => ({ nombre, n }));
    if (eps.length) out.medicacionPct = Math.round(100 * eps.filter(r => r.medicacion).length / eps.length);

    const en = (d0, d1) => eps.filter(r => { const d = (ahora - ts(r)) / DAY; return d >= d0 && d < d1; }).length;
    const act = en(0, 30), prev = en(30, 60);
    if (act + prev >= 2) out.tendencia = { actual: act, previo: prev, direccion: act > prev ? 'aumentó' : act < prev ? 'disminuyó' : 'igual' };

    /* --- Hallazgos en lenguaje natural --- */
    const H = out.hallazgos;
    if (out.desencadenantes[0]) H.push(`Tu desencadenante más frecuente es «${out.desencadenantes[0].nombre}» (${plural(out.desencadenantes[0].n, 'episodio', 'episodios')}).`);
    if (out.diaTop) H.push(`Tus episodios se concentran los ${plDia(out.diaTop.nombre)} (${out.diaTop.n} de ${eps.length}).`);
    if (out.momentoTop) H.push(`Suelen comenzar en ${out.momentoTop.texto}.`);
    if (out.intervaloMedio) H.push(`En promedio pasan ${Math.round(out.intervaloMedio)} días entre un episodio y otro.`);
    if (out.tendencia && out.tendencia.direccion !== 'igual')
      H.push(`En los últimos 30 días tuviste ${out.tendencia.actual} episodio(s) frente a ${out.tendencia.previo} en los 30 días anteriores: ${out.tendencia.direccion === 'aumentó' ? 'van en aumento' : 'van disminuyendo'}.`);
    if (out.medicacionPct !== null && eps.length >= 3) H.push(`Tomaste medicación en el ${out.medicacionPct}% de tus episodios.`);
    return out;
  }

  /* ------------------------------------------------------------------
     ALERTA DE POSIBLE EPISODIO
     Se activa solo cuando la probabilidad es MUY ALTA (score >= UMBRAL_ALERTA)
     y hay señales recientes de que el episodio está por empezar.
     Calcula en cuántas horas podría aparecer la migraña usando tu propio
     historial (tiempo entre un síntoma prodrómico y el episodio siguiente).
     Este es el punto de conexión para el modelo de machine learning real:
     basta con reemplazar el cálculo de `score` y de la ventana de horas por
     la respuesta del modelo, manteniendo el mismo objeto de salida.
     ------------------------------------------------------------------ */
  const UMBRAL_ALERTA = 55;

  const mediana = a => { const s = [...a].sort((x, y) => x - y), m = Math.floor(s.length / 2); return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2; };

  /** Horas típicas entre un síntoma prodrómico y el episodio que le siguió (máx. 72 h) */
  function horasHastaEpisodio(recs) {
    const prod = recs.filter(r => r.type === 'prodromico'), eps = recs.filter(r => r.type === 'episodio');
    const lapsos = [];
    prod.forEach(p => {
      const sig = eps.map(e => (ts(e) - ts(p)) / 3600000).filter(h => h > 0 && h <= 72).sort((a, b) => a - b)[0];
      if (sig !== undefined) lapsos.push(sig);
    });
    return lapsos.length >= 2 ? mediana(lapsos) : null;
  }

  function alertaEpisodio(registros, onb, ahora = Date.now()) {
    const recs = registros || [];
    const r = riesgo(recs, onb, ahora);
    const horasDesde = x => (ahora - ts(x)) / 3600000;
    const prodRecientes = recs.filter(x => x.type === 'prodromico' && horasDesde(x) >= 0 && horasDesde(x) <= 72)
      .sort((a, b) => ts(b) - ts(a));
    const hayRitmo = r.factores.some(f => f.grupo === 'ritmo');
    const base = { activa: false, score: r.score, nivel: r.nivel, motivos: r.factores.slice(0, 3).map(f => f.texto) };

    if (r.score < UMBRAL_ALERTA || (!prodRecientes.length && !hayRitmo)) return base;

    // Ventana estimada (en horas desde ahora)
    let desde, hasta;
    const tipico = horasHastaEpisodio(recs);          // p. ej. 8 h entre el aviso y el dolor
    if (prodRecientes.length) {
      const transcurridas = horasDesde(prodRecientes[0]);
      const centro = tipico !== null ? tipico : 12;    // sin historial suficiente: 12 h de referencia
      const margen = Math.max(2, centro * 0.4);
      desde = centro - margen - transcurridas;
      hasta = centro + margen - transcurridas;
    } else {                                           // solo por el ritmo de tus episodios
      desde = 6; hasta = 24;
    }
    desde = Math.max(0, Math.round(desde));
    hasta = Math.max(desde + 1, Math.round(hasta));
    if (desde === 0 && hasta <= 1) hasta = 2;
    const texto = desde === 0
      ? `En las próximas ${hasta} horas puede que tengas un episodio de migraña.`
      : `En ${desde} a ${hasta} horas puede que tengas un episodio de migraña.`;
    const clave = prodRecientes.length ? 'p' + prodRecientes[0].id : 'r' + new Date(ahora).toISOString().slice(0, 10);

    return { ...base, activa: true, desde, hasta, texto, clave,
      titulo: 'Posible episodio de migraña',
      cuerpo: texto + ' Descansa, hidrátate y evita tus desencadenantes.' };
  }

  return { riesgo, patrones, alertaEpisodio, UMBRAL_ALERTA };
})();
