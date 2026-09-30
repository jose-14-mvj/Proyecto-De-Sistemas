/* ===================================================================
   MigraSense — historial.js
   Historial con resumen, gráficos (SVG), calendario mensual y
   detalle de cada registro con opción de editar y eliminar.
   =================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  if (!MS.requireAuth()) return;

  const list = document.getElementById('recordsList');
  const emptyState = document.getElementById('emptyState');
  const statsBox = document.getElementById('statsBox');
  const calBox = document.getElementById('calBox');
  const dayPill = document.getElementById('dayPill');
  const detailModal = document.getElementById('detailModal');
  const detailSheet = document.getElementById('detailSheet');

  let currentFilter = 'todos';
  let dayFilter = null;                 // 'YYYY-MM-DD' o null
  let view = 'lista';                   // 'lista' | 'calendario'
  let calMonth = new Date(); calMonth.setDate(1);
  let records = [];

  const ICONS = {
    prodromico: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1"/></svg>`,
    episodio: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 3c-3 4-7 8-7 12a7 7 0 0 0 14 0c0-4-4-8-7-12Z"/></svg>`
  };

  /* ---------- Utilidades ---------- */
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const pad = n => String(n).padStart(2, '0');
  const dayKey = d => { d = new Date(d); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; };
  const humanize = s => String(s || '').replace(/_/g, ' ');
  const fmtLong = iso => new Date(iso).toLocaleString('es-BO', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

  function load() {
    records = MS.get(MS_KEYS.RECORDS, []).sort((a, b) => new Date(b.date) - new Date(a.date));
  }
  function updateStore(mutator) {
    MS.set(MS_KEYS.RECORDS, mutator(MS.get(MS_KEYS.RECORDS, [])));
    load();
    render();
  }

  function tagFor(intensidad) {
    if (intensidad >= 7) return '<span class="tag tag-high">Intenso</span>';
    if (intensidad >= 4) return '<span class="tag tag-mid">Moderado</span>';
    return '<span class="tag tag-low">Leve</span>';
  }

  /* ---------- Resumen y gráficos ---------- */
  function weekStart(d) {
    const x = new Date(d); x.setHours(0, 0, 0, 0);
    x.setDate(x.getDate() - ((x.getDay() + 6) % 7));   // lunes
    return x;
  }

  function weeklyChart() {
    const W = 8, start = weekStart(new Date());
    const buckets = Array.from({ length: W }, (_, i) => {
      const d = new Date(start); d.setDate(d.getDate() - 7 * (W - 1 - i));
      return { d, n: 0 };
    });
    records.filter(r => r.type === 'episodio').forEach(r => {
      const ws = weekStart(r.date).getTime();
      const b = buckets.find(x => x.d.getTime() === ws);
      if (b) b.n++;
    });
    const max = Math.max(1, ...buckets.map(b => b.n));
    const bw = 28, gap = 12, h = 80;
    const bars = buckets.map((b, i) => {
      const x = 10 + i * (bw + gap), bh = Math.round((b.n / max) * h);
      return `<rect x="${x}" y="${100 - bh}" width="${bw}" height="${Math.max(bh, 2)}" rx="6" fill="${b.n ? 'var(--accent-strong)' : 'var(--border)'}"/>
        ${b.n ? `<text x="${x + bw / 2}" y="${94 - bh}" text-anchor="middle" font-size="11" font-weight="700" fill="var(--text)">${b.n}</text>` : ''}
        <text x="${x + bw / 2}" y="118" text-anchor="middle" font-size="9.5" fill="var(--text-muted)">${b.d.getDate()}/${b.d.getMonth() + 1}</text>`;
    }).join('');
    return `<svg viewBox="0 0 340 126" role="img" aria-label="Episodios por semana">${bars}</svg>`;
  }

  function intensityChart() {
    const pts = records.filter(r => r.intensidad).slice(0, 12).reverse();
    if (pts.length < 2) return `<p class="small muted chart-empty">Registra al menos 2 veces para ver la evolución.</p>`;
    const x0 = 26, x1 = 326, y0 = 100, y1 = 12;
    const X = i => x0 + (i * (x1 - x0)) / (pts.length - 1);
    const Y = v => y0 - ((v - 1) / 9) * (y0 - y1);
    const line = pts.map((r, i) => `${X(i)},${Y(r.intensidad)}`).join(' ');
    const grid = [1, 5, 10].map(v => `<line x1="${x0}" x2="${x1}" y1="${Y(v)}" y2="${Y(v)}" stroke="var(--border)" stroke-dasharray="3 4"/><text x="18" y="${Y(v) + 3}" text-anchor="end" font-size="9.5" fill="var(--text-muted)">${v}</text>`).join('');
    const dots = pts.map((r, i) => `<circle cx="${X(i)}" cy="${Y(r.intensidad)}" r="4.5" fill="${r.type === 'episodio' ? 'var(--orange)' : 'var(--accent-strong)'}" stroke="var(--card)" stroke-width="2"/>`).join('');
    return `<svg viewBox="0 0 340 118" role="img" aria-label="Intensidad en el tiempo">${grid}<polyline points="${line}" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linejoin="round"/>${dots}</svg>
      <div class="legend"><span><i style="background:var(--orange)"></i>Episodio</span><span><i style="background:var(--accent-strong)"></i>Prodrómico</span></div>`;
  }

  function renderStats() {
    if (!records.length) { statsBox.innerHTML = ''; return; }
    const since = Date.now() - 30 * 86400000;
    const rec30 = records.filter(r => new Date(r.date).getTime() >= since);
    const eps30 = rec30.filter(r => r.type === 'episodio').length;
    const withInt = rec30.filter(r => r.intensidad);
    const avg = withInt.length ? (withInt.reduce((s, r) => s + Number(r.intensidad), 0) / withInt.length).toFixed(1) : '–';
    statsBox.innerHTML = `
      <div class="stats-grid">
        <div class="stat"><strong>${eps30}</strong><span>Episodios (30 días)</span></div>
        <div class="stat"><strong>${avg}</strong><span>Intensidad media</span></div>
        <div class="stat"><strong>${records.length}</strong><span>Registros totales</span></div>
      </div>
      <div class="card chart-card"><h4>Episodios por semana</h4>${weeklyChart()}</div>
      <div class="card chart-card"><h4>Intensidad en el tiempo</h4>${intensityChart()}</div>`;
  }

  /* ---------- Calendario ---------- */
  function renderCalendar() {
    calBox.style.display = view === 'calendario' ? 'block' : 'none';
    if (view !== 'calendario') return;
    const y = calMonth.getFullYear(), m = calMonth.getMonth();
    const offset = (new Date(y, m, 1).getDay() + 6) % 7;
    const days = new Date(y, m + 1, 0).getDate();
    const byDay = {};
    records.forEach(r => { (byDay[dayKey(r.date)] = byDay[dayKey(r.date)] || []).push(r); });
    const today = dayKey(new Date());
    let cells = '';
    for (let i = 0; i < offset; i++) cells += '<span class="cal-day empty"></span>';
    for (let d = 1; d <= days; d++) {
      const k = `${y}-${pad(m + 1)}-${pad(d)}`, rs = byDay[k] || [];
      const cls = ['cal-day', rs.some(r => r.type === 'episodio') ? 'has-ep' : (rs.length ? 'has-pro' : ''), k === today ? 'today' : '', k === dayFilter ? 'selected' : ''].join(' ');
      cells += `<button type="button" class="${cls}" data-day="${k}" ${rs.length ? '' : 'disabled'} aria-label="${d}: ${rs.length} registro(s)">${d}</button>`;
    }
    const t0 = calMonth.toLocaleDateString('es-BO', { month: 'long', year: 'numeric' });
    const title = t0.charAt(0).toUpperCase() + t0.slice(1);
    calBox.innerHTML = `
      <div class="card">
        <div class="cal-head">
          <button type="button" class="cal-nav" data-nav="-1" aria-label="Mes anterior">‹</button>
          <strong>${title}</strong>
          <button type="button" class="cal-nav" data-nav="1" aria-label="Mes siguiente">›</button>
        </div>
        <div class="cal-grid cal-dow">${['L', 'M', 'X', 'J', 'V', 'S', 'D'].map(x => `<span>${x}</span>`).join('')}</div>
        <div class="cal-grid">${cells}</div>
        <div class="legend"><span><i style="background:var(--orange)"></i>Episodio</span><span><i style="background:var(--accent-strong)"></i>Prodrómico</span></div>
      </div>`;
  }

  calBox.addEventListener('click', e => {
    const nav = e.target.closest('[data-nav]');
    if (nav) { calMonth.setMonth(calMonth.getMonth() + Number(nav.dataset.nav)); renderCalendar(); return; }
    const day = e.target.closest('[data-day]');
    if (day && !day.disabled) { dayFilter = dayFilter === day.dataset.day ? null : day.dataset.day; render(); }
  });

  /* ---------- Lista ---------- */
  function render() {
    renderStats();
    renderCalendar();

    dayPill.style.display = dayFilter ? 'flex' : 'none';
    if (dayFilter) {
      const [y, m, d] = dayFilter.split('-').map(Number);
      dayPill.querySelector('span').textContent = 'Mostrando: ' + new Date(y, m - 1, d).toLocaleDateString('es-BO', { day: 'numeric', month: 'long' });
    }

    const filtered = records.filter(r =>
      (currentFilter === 'todos' || r.type === currentFilter) && (!dayFilter || dayKey(r.date) === dayFilter));
    list.innerHTML = '';
    emptyState.style.display = filtered.length === 0 ? 'block' : 'none';

    filtered.forEach(r => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'record-item record-btn';
      item.dataset.id = r.id;
      const bg = r.type === 'episodio' ? 'linear-gradient(135deg,#f2994a,#f2b26a)' : 'linear-gradient(135deg,#60a5fa,#2563eb)';
      const detalle = r.type === 'episodio'
        ? `${esc((r.sintomas || []).slice(0, 2).join(', ') || 'Sin síntomas detallados')}${r.duracion ? ' · ' + esc(humanize(r.duracion)) : ''}`
        : esc((r.sintomas || []).slice(0, 3).join(', ') || 'Sin detalle');

      item.innerHTML = `
        <div class="record-icon" style="background:${bg};">${ICONS[r.type] || ''}</div>
        <div class="record-body">
          <div class="record-top">
            <span class="record-title">${esc(r.label)}</span>
            <span class="record-date">${msFormatDate(r.date)}</span>
          </div>
          <p class="record-desc">${detalle}</p>
          ${tagFor(r.intensidad || 0)}
        </div>`;
      list.appendChild(item);
    });
  }

  list.addEventListener('click', e => {
    const item = e.target.closest('[data-id]');
    if (item) openDetail(item.dataset.id, 'ver');
  });

  document.querySelectorAll('.filter-chip[data-filter]').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.filter-chip[data-filter]').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      currentFilter = chip.dataset.filter;
      render();
    });
  });
  document.querySelectorAll('.filter-chip[data-view]').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.filter-chip[data-view]').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      view = chip.dataset.view;
      if (view === 'lista') dayFilter = null;
      render();
    });
  });
  dayPill.querySelector('button').addEventListener('click', () => { dayFilter = null; render(); });

  /* ---------- Detalle / editar / eliminar ---------- */
  function row(label, value) {
    return value ? `<div class="d-row"><span>${label}</span><strong>${value}</strong></div>` : '';
  }

  function openDetail(id, mode) {
    const r = records.find(x => String(x.id) === String(id));
    if (!r) return;
    const ep = r.type === 'episodio';
    let html = `<div class="modal-handle"></div><h3 style="margin-bottom:2px;">${esc(r.label)}</h3><p class="small muted" style="margin-bottom:10px;">${fmtLong(r.date)}</p>`;

    if (mode === 'ver') {
      html += `${tagFor(r.intensidad || 0)} <span class="small muted">Intensidad ${r.intensidad || '–'}/10</span>
        <div class="d-list">
          ${row('Síntomas', esc((r.sintomas || []).join(', ')))}
          ${row('Posibles desencadenantes', esc((r.disparadores || []).join(', ')))}
          ${row('Duración', esc(humanize(r.duracion)))}
          ${row('Inicio', esc(humanize(r.inicio)))}
          ${ep ? row('Medicación', r.medicacion ? esc(r.medicamento || 'Sí') : 'No tomó') : ''}
          ${row('Notas', esc(r.notas))}
        </div>
        <div class="btn-row mt-16">
          <button class="btn btn-outline" data-act="close">Cerrar</button>
          <button class="btn btn-outline" data-act="edit">Editar</button>
          <button class="btn btn-danger" data-act="ask-delete">Eliminar</button>
        </div>`;
    } else if (mode === 'editar') {
      html += `
        <label class="f-label" for="edInt">Intensidad: <strong id="edIntVal">${r.intensidad || 5}</strong>/10</label>
        <input type="range" id="edInt" min="1" max="10" value="${r.intensidad || 5}" style="width:100%;accent-color:var(--accent-strong);">
        ${ep ? `<label class="f-label" for="edMed">Medicamento (déjalo vacío si no tomaste)</label>
        <input class="f-input" id="edMed" value="${esc(r.medicamento || '')}" maxlength="80">` : ''}
        <label class="f-label" for="edNotas">Notas</label>
        <textarea class="f-input" id="edNotas" rows="3" maxlength="500">${esc(r.notas || '')}</textarea>
        <div class="btn-row mt-16">
          <button class="btn btn-outline" data-act="cancel">Cancelar</button>
          <button class="btn btn-primary" data-act="save">Guardar cambios</button>
        </div>`;
    } else if (mode === 'borrar') {
      html += `<div class="g-note g-bad" style="margin-top:12px;"><strong>¿Eliminar este registro?</strong><br>Esta acción no se puede deshacer y afectará tus estadísticas.</div>
        <div class="btn-row mt-16">
          <button class="btn btn-outline" data-act="cancel">Cancelar</button>
          <button class="btn btn-danger" data-act="delete">Sí, eliminar</button>
        </div>`;
    }

    detailSheet.innerHTML = html;
    detailSheet.dataset.id = r.id;
    detailModal.classList.add('show');

    const range = document.getElementById('edInt');
    if (range) range.addEventListener('input', () => { document.getElementById('edIntVal').textContent = range.value; });
  }

  detailSheet.addEventListener('click', e => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const id = detailSheet.dataset.id;
    switch (btn.dataset.act) {
      case 'close': detailModal.classList.remove('show'); break;
      case 'edit': openDetail(id, 'editar'); break;
      case 'cancel': openDetail(id, 'ver'); break;
      case 'ask-delete': openDetail(id, 'borrar'); break;
      case 'save': {
        const intensidad = Number(document.getElementById('edInt').value);
        const notas = document.getElementById('edNotas').value.trim();
        const medEl = document.getElementById('edMed');
        updateStore(all => all.map(r => {
          if (String(r.id) !== String(id)) return r;
          const upd = { ...r, intensidad, notas };
          if (medEl) { upd.medicamento = medEl.value.trim(); upd.medicacion = !!upd.medicamento; }
          return upd;
        }));
        detailModal.classList.remove('show');
        msToast('Registro actualizado', '✓');
        break;
      }
      case 'delete':
        updateStore(all => all.filter(r => String(r.id) !== String(id)));
        detailModal.classList.remove('show');
        msToast('Registro eliminado', '✓');
        break;
    }
  });
  detailModal.addEventListener('click', e => { if (e.target === detailModal) detailModal.classList.remove('show'); });

  load();
  render();

  /* ---------- Generar reporte de texto ---------- */
  function buildReportText() {
    const user = MS.getUser();
    let txt = `REPORTE DE SÍNTOMAS — MigraSense\n`;
    txt += `Paciente: ${user ? user.name : 'Invitado/a'}\n`;
    txt += `Generado: ${new Date().toLocaleString('es-BO')}\n`;
    txt += `Total de registros: ${records.length}\n`;
    txt += `----------------------------------------\n\n`;

    if (records.length === 0) {
      txt += 'Aún no hay registros guardados.\n';
      return txt;
    }

    records.forEach(r => {
      txt += `• ${r.label} — ${new Date(r.date).toLocaleString('es-BO')}\n`;
      txt += `  Intensidad: ${r.intensidad}/10\n`;
      if (r.sintomas && r.sintomas.length) txt += `  Síntomas: ${r.sintomas.join(', ')}\n`;
      if (r.disparadores && r.disparadores.length) txt += `  Posibles desencadenantes: ${r.disparadores.join(', ')}\n`;
      if (r.duracion) txt += `  Duración: ${humanize(r.duracion)}\n`;
      if (r.medicacion) txt += `  Medicación tomada: ${r.medicamento || 'Sí'}\n`;
      if (r.notas) txt += `  Notas: ${r.notas}\n`;
      txt += `\n`;
    });

    txt += `----------------------------------------\n`;
    txt += `Este reporte es generado automáticamente y no reemplaza una evaluación médica profesional.\n`;
    return txt;
  }

  document.getElementById('reportBtn').addEventListener('click', () => {
    document.getElementById('reportText').value = buildReportText();
    document.getElementById('reportModal').classList.add('show');
  });
  document.getElementById('reportModal').addEventListener('click', (e) => {
    if (e.target.id === 'reportModal') e.target.classList.remove('show');
  });
  document.getElementById('downloadReportBtn').addEventListener('click', () => {
    const blob = new Blob([buildReportText()], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `reporte-migrasense-${new Date().toISOString().slice(0, 10)}.txt`;
    a.click();
    URL.revokeObjectURL(url);
    msToast('Reporte descargado', '⬇️');
  });
});
