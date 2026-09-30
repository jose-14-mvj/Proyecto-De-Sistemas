/* ===================================================================
   MigraSense — reporte-pdf.js
   Genera el reporte clínico en PDF directamente en el navegador
   (sin servidor). Usa jsPDF + jsPDF-AutoTable (js/vendor/).

     MSReportePDF.filtrar(registros, dias, ahora)  -> registros del periodo
     MSReportePDF.generar({ jsPDF, user, onboarding, records, dias, etiqueta,
                            logo, ahora })         -> instancia jsPDF

   El reporte está pensado para llevarlo a consulta: resumen del periodo,
   gráficos, patrones detectados, perfil de migraña y tabla completa.
   =================================================================== */
const MSReportePDF = (() => {
  const DAY = 86400000;
  const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  const DIAS_CORTO = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
  const P = { w: 210, h: 297, m: 16 };                 // A4 en mm, margen
  const CW = P.w - 2 * P.m;                            // ancho útil
  const C = {                                          // paleta (igual que la app)
    primary: [74, 92, 130], dark: [50, 62, 92], accent: [108, 127, 232],
    soft: [238, 240, 255], orange: [242, 153, 74], orangeSoft: [255, 241, 227],
    text: [46, 58, 89], muted: [124, 136, 163], border: [228, 232, 247],
    card: [245, 247, 254], danger: [239, 111, 108], success: [79, 183, 135], white: [255, 255, 255]
  };

  /* ---------- Utilidades ---------- */
  const pad = n => String(n).padStart(2, '0');
  const ts = r => new Date(r.date).getTime();
  const humanize = s => String(s || '').replace(/_/g, ' ');
  const num = n => String(n).replace('.', ',');

  // Las fuentes estándar del PDF solo dibujan Latin-1: se quitan emojis y símbolos raros
  const MAPA = { '–': '-', '—': '-', '‘': "'", '’': "'", '“': '"', '”': '"', '…': '...', '•': '-', '≥': '>=', '≤': '<=' };
  const limpiar = s => String(s == null ? '' : s)
    .replace(/[–—‘’“”…•≥≤]/g, c => MAPA[c])
    .replace(/[^\n\u0020-\u007E\u00A0-\u00FF]/g, '')
    .replace(/[ \t]+/g, ' ').trim();

  const fmtFecha = d => { d = new Date(d); return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`; };
  const fmtHora = d => { d = new Date(d); return `${pad(d.getHours())}:${pad(d.getMinutes())}`; };
  const fmtLarga = d => { d = new Date(d); return `${d.getDate()} de ${MESES[d.getMonth()]} de ${d.getFullYear()}`; };
  const fmtCorta = d => { d = new Date(d); return `${d.getDate()} ${MESES[d.getMonth()].slice(0, 3)}`; };
  const plural = (n, s, p) => `${n} ${n === 1 ? s : p}`;
  const promedio = a => a.length ? a.reduce((s, x) => s + x, 0) / a.length : 0;
  const count = arr => arr.reduce((m, x) => { m[x] = (m[x] || 0) + 1; return m; }, {});
  const top = (obj, n) => Object.entries(obj).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0])).slice(0, n);
  const inicioDia = d => { const x = new Date(d); x.setHours(0, 0, 0, 0); return x; };
  const diaClave = d => { d = new Date(d); return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`; };

  /** Registros dentro del periodo (dias = null → todos), del más antiguo al más reciente */
  function filtrar(registros, dias, ahora = Date.now()) {
    const desde = dias ? ahora - dias * DAY : -Infinity;
    return (registros || [])
      .filter(r => r && r.date && ts(r) >= desde && ts(r) <= ahora + DAY)
      .sort((a, b) => ts(a) - ts(b));
  }

  /* ---------- Primitivas de dibujo ---------- */
  const fill = (doc, c) => doc.setFillColor(c[0], c[1], c[2]);
  const stroke = (doc, c) => doc.setDrawColor(c[0], c[1], c[2]);
  const color = (doc, c) => doc.setTextColor(c[0], c[1], c[2]);
  function texto(doc, s, x, y, { size = 9, bold = false, c = C.text, align = 'left', maxW } = {}) {
    doc.setFont('helvetica', bold ? 'bold' : 'normal');
    doc.setFontSize(size); color(doc, c);
    doc.text(limpiar(s), x, y, { align, ...(maxW ? { maxWidth: maxW } : {}) });
  }
  function tarjeta(doc, x, y, w, h, { bg = C.card, borde = C.border, r = 3 } = {}) {
    fill(doc, bg); stroke(doc, borde); doc.setLineWidth(0.25);
    doc.roundedRect(x, y, w, h, r, r, 'FD');
  }
  function titulo(doc, s, x, y) {
    texto(doc, s, x, y, { size: 11, bold: true, c: C.primary });
    stroke(doc, C.accent); doc.setLineWidth(0.6);
    doc.line(x, y + 1.8, x + 8, y + 1.8);
    return y + 6;
  }

  /* ---------- Gráficos ---------- */
  function barras(doc, x, y, w, h, etiquetas, valores, col) {
    const max = Math.max(1, ...valores);
    const base = y + h - 6;                              // línea base (deja espacio para etiquetas)
    const alto = h - 12;
    stroke(doc, C.border); doc.setLineWidth(0.25); doc.line(x, base, x + w, base);
    const n = valores.length, paso = w / n, bw = Math.min(paso * 0.62, 9);
    valores.forEach((v, i) => {
      const bh = v ? Math.max(1.2, (v / max) * alto) : 0;
      const bx = x + i * paso + (paso - bw) / 2;
      if (v) { fill(doc, col); doc.roundedRect(bx, base - bh, bw, bh, 0.8, 0.8, 'F'); texto(doc, String(v), bx + bw / 2, base - bh - 1.2, { size: 7, bold: true, align: 'center' }); }
      else { fill(doc, C.border); doc.rect(bx, base - 0.5, bw, 0.5, 'F'); }
      texto(doc, etiquetas[i], bx + bw / 2, base + 3.6, { size: 5.8, c: C.muted, align: 'center' });
    });
  }

  function lineaIntensidad(doc, x, y, w, h, recs, t0, t1) {
    const pts = recs.filter(r => Number(r.intensidad) > 0);
    const gx0 = x + 6, gx1 = x + w - 2, gy0 = y + h - 7, gy1 = y + 3;
    const Y = v => gy0 - ((v - 1) / 9) * (gy0 - gy1);
    [1, 5, 10].forEach(v => {
      stroke(doc, C.border); doc.setLineWidth(0.2); doc.setLineDashPattern([0.8, 1], 0);
      doc.line(gx0, Y(v), gx1, Y(v)); doc.setLineDashPattern([], 0);
      texto(doc, String(v), gx0 - 1.5, Y(v) + 1, { size: 6, c: C.muted, align: 'right' });
    });
    if (pts.length < 2) {
      texto(doc, 'Se necesitan al menos 2 registros', x + w / 2, y + h / 2, { size: 8, c: C.muted, align: 'center' });
      return;
    }
    const span = Math.max(1, t1 - t0);
    const X = r => gx0 + ((ts(r) - t0) / span) * (gx1 - gx0);
    stroke(doc, C.accent); doc.setLineWidth(0.5);
    pts.forEach((r, i) => { if (i) doc.line(X(pts[i - 1]), Y(pts[i - 1].intensidad), X(r), Y(r.intensidad)); });
    pts.forEach(r => {
      fill(doc, r.type === 'episodio' ? C.orange : C.accent);
      stroke(doc, C.white); doc.setLineWidth(0.4);
      doc.circle(X(r), Y(r.intensidad), 1.3, 'FD');
    });
    texto(doc, fmtCorta(t0), gx0, gy0 + 5, { size: 6, c: C.muted });
    texto(doc, fmtCorta(t1), gx1, gy0 + 5, { size: 6, c: C.muted, align: 'right' });
  }

  function barrasHorizontales(doc, x, y, w, items, col) {
    if (!items.length) { texto(doc, 'Sin datos en este periodo', x, y + 3, { size: 8, c: C.muted }); return y + 6; }
    const max = Math.max(...items.map(i => i[1]));
    const lw = w * 0.5, bwMax = w - lw - 8;
    items.forEach(([nombre, n], i) => {
      const yy = y + i * 6.2;
      texto(doc, nombre, x, yy + 3, { size: 8, maxW: lw - 2 });
      fill(doc, C.border); doc.roundedRect(x + lw, yy + 0.6, bwMax, 3.4, 1.2, 1.2, 'F');
      fill(doc, col); doc.roundedRect(x + lw, yy + 0.6, Math.max(2.4, (n / max) * bwMax), 3.4, 1.2, 1.2, 'F');
      texto(doc, String(n), x + w, yy + 3.2, { size: 8, bold: true, align: 'right' });
    });
    return y + items.length * 6.2;
  }

  /* ---------- Datos derivados ---------- */
  function agruparEpisodios(eps, t0, t1) {
    const largo = (t1 - t0) / DAY;
    const porMes = largo > 100;
    const cubos = [];
    if (porMes) {
      const d = new Date(t0); d.setDate(1); d.setHours(0, 0, 0, 0);
      while (d.getTime() <= t1 && cubos.length < 13) {
        cubos.push({ ini: d.getTime(), fin: new Date(d.getFullYear(), d.getMonth() + 1, 1).getTime(), n: 0, et: MESES[d.getMonth()].slice(0, 3) });
        d.setMonth(d.getMonth() + 1);
      }
    } else {
      const d = inicioDia(t1); d.setDate(d.getDate() - ((d.getDay() + 6) % 7));    // lunes de la semana actual
      const semanas = Math.min(13, Math.max(4, Math.ceil(largo / 7) + 1));
      for (let i = semanas - 1; i >= 0; i--) {
        const ini = new Date(d); ini.setDate(ini.getDate() - 7 * i);
        const fin = new Date(ini); fin.setDate(fin.getDate() + 7);
        cubos.push({ ini: ini.getTime(), fin: fin.getTime(), n: 0, et: `${ini.getDate()}/${ini.getMonth() + 1}` });
      }
    }
    eps.forEach(r => { const c = cubos.find(b => ts(r) >= b.ini && ts(r) < b.fin); if (c) c.n++; });
    return { cubos, porMes };
  }

  function resumen(recs, eps, prods, dias, t0, t1) {
    const ints = eps.map(r => Number(r.intensidad)).filter(Boolean);
    const conMed = eps.filter(r => r.medicacion);
    const diasMed = new Set(conMed.map(r => diaClave(r.date))).size;
    const duraciones = top(count(eps.map(r => r.duracion).filter(Boolean)), 1)[0];
    const gaps = [];
    const tE = eps.map(ts).sort((a, b) => a - b);
    for (let i = 1; i < tE.length; i++) { const g = (tE[i] - tE[i - 1]) / DAY; if (g >= 0.5) gaps.push(g); }
    const meses = Math.max(1, (t1 - t0) / DAY / 30);
    return {
      episodios: eps.length, prodromicos: prods.length,
      intMedia: ints.length ? Math.round(promedio(ints) * 10) / 10 : null,
      intMax: ints.length ? Math.max(...ints) : null,
      medPct: eps.length ? Math.round(100 * conMed.length / eps.length) : null,
      diasMed, duracion: duraciones ? humanize(duraciones[0]) : null,
      intervalo: gaps.length ? Math.round(promedio(gaps)) : null,
      porMes: eps.length ? Math.round((eps.length / meses) * 10) / 10 : 0,
      diasConRegistro: new Set(eps.concat(prods).map(r => diaClave(r.date))).size
    };
  }

  /* ---------- Documento ---------- */
  function generar({ jsPDF, user, onboarding, records, dias = 30, etiqueta, logo, ahora = Date.now(), patronesFn }) {
    const doc = new jsPDF({ unit: 'mm', format: 'a4', compress: true });
    const recs = filtrar(records, dias, ahora);
    const eps = recs.filter(r => r.type === 'episodio');
    const prods = recs.filter(r => r.type === 'prodromico');
    const t1 = ahora;
    const t0 = dias ? ahora - dias * DAY : (recs.length ? ts(recs[0]) : ahora - 30 * DAY);
    const S = resumen(recs, eps, prods, dias, t0, t1);
    const pat = (patronesFn || (typeof MSAnalisis !== 'undefined' ? MSAnalisis.patrones : null));
    const patrones = pat ? pat(recs, ahora) : null;
    const labels = (onboarding && onboarding.labels) || {};
    const nombre = limpiar((user && user.name) || 'Paciente');
    const conAnio = d => `${fmtCorta(d)} ${new Date(d).getFullYear()}`;
    const periodoTxt = `${etiqueta || (dias ? `Últimos ${dias} días` : 'Todo el historial')}: ${conAnio(t0)} - ${conAnio(t1)}`;

    doc.setProperties({
      title: `Reporte de síntomas - ${nombre}`, subject: 'Reporte de migraña generado con MigraSense',
      author: 'MigraSense', creator: 'MigraSense'
    });

    let y = 0;
    const limite = P.h - 18;
    const asegurar = h => { if (y + h > limite) { doc.addPage(); y = cabeceraInterna(); } };

    function cabeceraInterna() {
      fill(doc, C.primary); doc.rect(0, 0, P.w, 11, 'F');
      texto(doc, 'MigraSense · Reporte de síntomas', P.m, 7, { size: 8.5, bold: true, c: C.white });
      texto(doc, nombre, P.w - P.m, 7, { size: 8.5, c: C.white, align: 'right' });
      return 20;
    }

    /* ----- Portada / cabecera ----- */
    fill(doc, C.primary); doc.rect(0, 0, P.w, 34, 'F');
    fill(doc, C.accent); doc.rect(0, 34, P.w, 1.2, 'F');
    if (logo) {
      try { fill(doc, C.white); doc.roundedRect(P.m, 7, 20, 20, 4, 4, 'F'); doc.addImage(logo, 'PNG', P.m + 2, 9, 16, 16); } catch (e) { /* sin logo */ }
    }
    const tx = logo ? P.m + 25 : P.m;
    texto(doc, 'MigraSense', tx, 16.5, { size: 20, bold: true, c: C.white });
    texto(doc, 'Reporte de síntomas de migraña', tx, 23.5, { size: 10.5, c: [220, 226, 250] });
    texto(doc, 'Generado el', P.w - P.m, 14, { size: 7.5, c: [200, 208, 240], align: 'right' });
    texto(doc, fmtLarga(ahora), P.w - P.m, 19.5, { size: 9.5, bold: true, c: C.white, align: 'right' });
    texto(doc, fmtHora(ahora) + ' h', P.w - P.m, 24.5, { size: 8, c: [200, 208, 240], align: 'right' });

    /* ----- Datos del paciente ----- */
    y = 44;
    tarjeta(doc, P.m, y, CW, 27);
    const col2 = P.m + CW / 2 + 2;
    const dato = (et, val, x, yy) => { texto(doc, et, x, yy, { size: 7, c: C.muted }); texto(doc, val || '-', x, yy + 4.4, { size: 9.5, bold: true, maxW: CW / 2 - 12 }); };
    dato('Paciente', nombre, P.m + 5, y + 6.5);
    dato('Correo', (user && user.email) || '-', col2, y + 6.5);
    dato('Periodo del reporte', periodoTxt, P.m + 5, y + 17.5);
    dato('Registros incluidos', `${recs.length} (${plural(S.episodios, 'episodio', 'episodios')} · ${plural(S.prodromicos, 'prodrómico', 'prodrómicos')})`, col2, y + 17.5);
    y += 27 + 8;

    /* ----- Sin datos ----- */
    if (!recs.length) {
      tarjeta(doc, P.m, y, CW, 30, { bg: C.soft });
      texto(doc, 'No hay registros en este periodo', P.w / 2, y + 13, { size: 12, bold: true, c: C.primary, align: 'center' });
      texto(doc, 'Registra tus síntomas en MigraSense o elige un periodo más amplio para generar el reporte.', P.w / 2, y + 20, { size: 9, c: C.muted, align: 'center' });
      y += 38;
    } else {
      /* ----- Resumen del periodo (tarjetas) ----- */
      y = titulo(doc, 'Resumen del periodo', P.m, y);
      const kpis = [
        [String(S.episodios), 'Episodios de migraña', C.orange],
        [S.intMedia == null ? '-' : `${num(S.intMedia)}/10`, S.intMax ? `Intensidad media (máx. ${S.intMax})` : 'Intensidad media', C.accent],
        [S.medPct == null ? '-' : `${S.medPct}%`, S.diasMed ? `Con medicación (${plural(S.diasMed, 'día', 'días')})` : 'Episodios con medicación', C.success],
        [String(S.diasConRegistro), 'Días con registro', C.primary]
      ];
      const kw = (CW - 3 * 4) / 4;
      kpis.forEach(([v, l, c], i) => {
        const kx = P.m + i * (kw + 4);
        tarjeta(doc, kx, y, kw, 21, { bg: C.white });
        fill(doc, c); doc.roundedRect(kx, y, 1.6, 21, 0.8, 0.8, 'F');
        texto(doc, v, kx + 5, y + 10, { size: 15, bold: true, c: C.dark });
        texto(doc, l, kx + 5, y + 16, { size: 6.8, c: C.muted, maxW: kw - 7 });
      });
      y += 21 + 6;

      // Texto de resumen
      const frases = [];
      if (S.episodios) {
        frases.push(`En el periodo se registraron ${plural(S.episodios, 'episodio', 'episodios')} de migraña (aprox. ${num(S.porMes)} por mes)${S.intMedia ? `, con una intensidad media de ${num(S.intMedia)}/10` : ''}.`);
        if (S.duracion) frases.push(`La duración más frecuente fue: ${S.duracion}.`);
        if (S.intervalo) frases.push(`Entre un episodio y otro pasan en promedio ${S.intervalo} días.`);
        if (S.medPct !== null) frases.push(`Se tomó medicación en ${S.medPct}% de los episodios (${plural(S.diasMed, 'día', 'días')} distintos).`);
      } else frases.push('En el periodo no se registraron episodios completos; solo síntomas prodrómicos.');
      if (S.prodromicos) frases.push(`Además hay ${plural(S.prodromicos, 'registro', 'registros')} de síntomas prodrómicos (señales previas).`);
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9.2);
      const lineas = doc.splitTextToSize(limpiar(frases.join(' ')), CW);
      color(doc, C.text); doc.text(lineas, P.m, y + 3, { lineHeightFactor: 1.45 });
      y += lineas.length * 4.9 + 6;

      /* ----- Gráficos ----- */
      asegurar(62);
      y = titulo(doc, 'Evolución', P.m, y);
      const gw = (CW - 6) / 2, gh = 46;
      const { cubos, porMes } = agruparEpisodios(eps, t0, t1);
      tarjeta(doc, P.m, y, gw, gh + 8, { bg: C.white });
      texto(doc, porMes ? 'Episodios por mes' : 'Episodios por semana (inicio lunes)', P.m + 4, y + 6, { size: 8.5, bold: true });
      barras(doc, P.m + 4, y + 9, gw - 8, gh - 2, cubos.map(c => c.et), cubos.map(c => c.n), C.orange);
      const gx2 = P.m + gw + 6;
      tarjeta(doc, gx2, y, gw, gh + 8, { bg: C.white });
      texto(doc, 'Intensidad del dolor (1-10)', gx2 + 4, y + 6, { size: 8.5, bold: true });
      lineaIntensidad(doc, gx2 + 4, y + 9, gw - 8, gh - 2, recs, t0, t1);
      // leyenda
      fill(doc, C.orange); doc.circle(gx2 + gw - 33, y + 5.2, 1, 'F'); texto(doc, 'Episodio', gx2 + gw - 31, y + 6.2, { size: 6.5, c: C.muted });
      fill(doc, C.accent); doc.circle(gx2 + gw - 17, y + 5.2, 1, 'F'); texto(doc, 'Prodrómico', gx2 + gw - 15, y + 6.2, { size: 6.5, c: C.muted });
      y += gh + 8 + 8;

      /* ----- Patrones ----- */
      asegurar(70);
      y = titulo(doc, 'Patrones detectados', P.m, y);
      const hall = (patrones && patrones.suficiente) ? patrones.hallazgos : [];
      if (hall.length) {
        hall.forEach(h => {
          const ls = doc.splitTextToSize(limpiar(h), CW - 8);
          asegurar(ls.length * 4.6 + 2);
          fill(doc, C.accent); doc.circle(P.m + 1.4, y + 2.2, 0.9, 'F');
          doc.setFont('helvetica', 'normal'); doc.setFontSize(9); color(doc, C.text);
          doc.text(ls, P.m + 5, y + 3, { lineHeightFactor: 1.35 });
          y += ls.length * 4.6 + 1.6;
        });
      } else {
        texto(doc, 'Aún no hay suficientes registros para detectar patrones confiables (se necesitan al menos 3).', P.m, y + 3, { size: 8.8, c: C.muted });
        y += 7;
      }
      y += 4;

      const trig = top(count(eps.flatMap(r => r.disparadores || []).map(limpiar)), 5);
      const sint = top(count(recs.flatMap(r => r.sintomas || []).map(limpiar)), 5);
      const cw2 = (CW - 8) / 2;
      const h0 = Math.max(trig.length, sint.length, 1) * 6.2 + 14;
      asegurar(h0 + 2);
      tarjeta(doc, P.m, y, cw2, h0, { bg: C.white });
      texto(doc, 'Desencadenantes más frecuentes', P.m + 4, y + 6.5, { size: 8.5, bold: true });
      barrasHorizontales(doc, P.m + 4, y + 10, cw2 - 8, trig, C.orange);
      tarjeta(doc, P.m + cw2 + 8, y, cw2, h0, { bg: C.white });
      texto(doc, 'Síntomas más frecuentes', P.m + cw2 + 12, y + 6.5, { size: 8.5, bold: true });
      barrasHorizontales(doc, P.m + cw2 + 12, y + 10, cw2 - 8, sint, C.accent);
      y += h0 + 6;

      // Episodios por día de la semana
      if (eps.length) {
        asegurar(40);
        const dow = [0, 0, 0, 0, 0, 0, 0];
        eps.forEach(r => { dow[(new Date(r.date).getDay() + 6) % 7]++; });
        tarjeta(doc, P.m, y, CW, 34, { bg: C.white });
        texto(doc, 'Episodios por día de la semana', P.m + 4, y + 6.5, { size: 8.5, bold: true });
        barras(doc, P.m + 8, y + 8, CW - 16, 25, DIAS_CORTO, dow, C.accent);
        y += 34 + 8;
      }
    }

    /* ----- Perfil de migraña (cuestionario) ----- */
    const FILAS = [['Género', 'genero'], ['Ocupación', 'ocupacion'], ['Tiempo con migrañas', 'tiempoMigrana'], ['Frecuencia habitual', 'frecuencia'],
      ['Duración habitual', 'duracion'], ['Intensidad habitual', 'intensidad'], ['Momento del día', 'momentoDia'], ['Horas de sueño', 'sueno'],
      ['Nivel de estrés', 'estres'], ['Consumo de cafeína', 'cafeina'], ['Condiciones', 'condiciones']];
    const perfil = FILAS.map(([et, k]) => { const v = labels[k]; return [et, Array.isArray(v) ? v.join(', ') : v]; }).filter(f => f[1]).map(f => [limpiar(f[0]), limpiar(f[1])]);
    if (perfil.length) {
      asegurar(30);
      y = titulo(doc, 'Perfil de migraña (cuestionario inicial)', P.m, y);
      const mitad = Math.ceil(perfil.length / 2);
      const filas = [];
      for (let i = 0; i < mitad; i++) filas.push([...(perfil[i] || ['', '']), ...(perfil[i + mitad] || ['', ''])]);
      doc.autoTable({
        startY: y, margin: { left: P.m, right: P.m, top: 20 }, body: filas, theme: 'plain',
        styles: { font: 'helvetica', fontSize: 8.3, cellPadding: { top: 1.9, bottom: 1.9, left: 2.5, right: 2.5 }, textColor: C.text, lineColor: C.border, lineWidth: { bottom: 0.2 }, valign: 'top' },
        columnStyles: { 0: { fontStyle: 'bold', textColor: C.muted, cellWidth: 36 }, 1: { cellWidth: 52 }, 2: { fontStyle: 'bold', textColor: C.muted, cellWidth: 36 }, 3: { cellWidth: 54 } },
        didDrawPage: d => { if (d.pageNumber > 1) cabeceraInterna(); }
      });
      y = doc.lastAutoTable.finalY + 9;
    }

    /* ----- Detalle de registros ----- */
    if (recs.length) {
      asegurar(30);
      y = titulo(doc, `Detalle de registros (${recs.length})`, P.m, y);
      const cuerpo = recs.slice().reverse().map(r => {
        const ep = r.type === 'episodio';
        return [
          `${fmtFecha(r.date)}\n${fmtHora(r.date)} h`,
          ep ? 'Episodio' : 'Prodrómico',
          r.intensidad ? `${r.intensidad}` : '-',
          ep ? humanize(r.duracion) || '-' : '-',
          (r.sintomas || []).join(', ') || '-',
          (r.disparadores || []).join(', ') || '-',
          ep ? (r.medicacion ? (r.medicamento || 'Sí') : 'No') : '-',
          r.notas || ''
        ].map(limpiar);
      });
      doc.autoTable({
        startY: y, margin: { left: P.m, right: P.m, top: 20, bottom: 18 },
        head: [['Fecha', 'Tipo', 'Int.', 'Duración', 'Síntomas', 'Desencadenantes', 'Medicación', 'Notas']],
        body: cuerpo, theme: 'grid', rowPageBreak: 'avoid',
        styles: { font: 'helvetica', fontSize: 7.4, cellPadding: 1.8, textColor: C.text, lineColor: C.border, lineWidth: 0.2, valign: 'top', overflow: 'linebreak' },
        headStyles: { fillColor: C.primary, textColor: C.white, fontStyle: 'bold', fontSize: 7.6, halign: 'left' },
        alternateRowStyles: { fillColor: C.card },
        columnStyles: { 0: { cellWidth: 21 }, 1: { cellWidth: 18 }, 2: { cellWidth: 9, halign: 'center', fontStyle: 'bold' }, 3: { cellWidth: 17 }, 4: { cellWidth: 33 }, 5: { cellWidth: 30 }, 6: { cellWidth: 21 }, 7: { cellWidth: 'auto' } },
        didParseCell: d => {
          if (d.section !== 'body') return;
          const r = recs[recs.length - 1 - d.row.index];
          if (d.column.index === 1) d.cell.styles.textColor = r.type === 'episodio' ? [190, 105, 25] : C.accent, d.cell.styles.fontStyle = 'bold';
          if (d.column.index === 2) {
            const v = Number(r.intensidad) || 0;
            d.cell.styles.textColor = v >= 7 ? C.danger : v >= 4 ? [190, 105, 25] : C.success;
          }
        },
        didDrawPage: d => { if (d.pageNumber > 1) cabeceraInterna(); }
      });
      y = doc.lastAutoTable.finalY + 9;
    }

    /* ----- Observaciones del profesional + aviso ----- */
    asegurar(46);
    texto(doc, 'Observaciones del profesional de salud', P.m, y, { size: 10, bold: true, c: C.primary });
    stroke(doc, C.border); doc.setLineWidth(0.3);
    for (let i = 1; i <= 3; i++) doc.line(P.m, y + i * 7, P.m + CW, y + i * 7);
    y += 3 * 7 + 8;
    tarjeta(doc, P.m, y, CW, 15, { bg: C.orangeSoft, borde: [248, 214, 176] });
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7.6); color(doc, [140, 84, 20]);
    doc.text(doc.splitTextToSize('Este reporte se genera automáticamente con la información que la persona registró en MigraSense. Es un apoyo para la consulta y no reemplaza una evaluación ni un diagnóstico médico.', CW - 8), P.m + 4, y + 5.6, { lineHeightFactor: 1.4 });

    /* ----- Pie de página en todas las páginas ----- */
    const total = doc.getNumberOfPages();
    for (let i = 1; i <= total; i++) {
      doc.setPage(i);
      stroke(doc, C.border); doc.setLineWidth(0.25); doc.line(P.m, P.h - 12, P.w - P.m, P.h - 12);
      texto(doc, `MigraSense · ${nombre} · ${fmtFecha(ahora)}`, P.m, P.h - 7.5, { size: 7.2, c: C.muted });
      texto(doc, `Página ${i} de ${total}`, P.w - P.m, P.h - 7.5, { size: 7.2, c: C.muted, align: 'right' });
    }
    return doc;
  }

  /** Nombre de archivo sugerido */
  function nombreArchivo(user, ahora = new Date()) {
    const base = limpiar((user && user.name) || 'paciente').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'paciente';
    return `reporte-migrasense-${base}-${ahora.getFullYear()}-${pad(ahora.getMonth() + 1)}-${pad(ahora.getDate())}.pdf`;
  }

  return { generar, filtrar, nombreArchivo, limpiar };
})();
