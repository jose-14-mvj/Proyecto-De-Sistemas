/* ===================================================================
   MigraSense — historial.js
   =================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  if (!MS.requireAuth()) return;

  const records = MS.get(MS_KEYS.RECORDS, []).sort((a, b) => new Date(b.date) - new Date(a.date));
  const list = document.getElementById('recordsList');
  const emptyState = document.getElementById('emptyState');
  let currentFilter = 'todos';

  const ICONS = {
    prodromico: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1"/></svg>`,
    episodio: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 3c-3 4-7 8-7 12a7 7 0 0 0 14 0c0-4-4-8-7-12Z"/></svg>`
  };

  function tagFor(intensidad) {
    if (intensidad >= 7) return '<span class="tag tag-high">Intenso</span>';
    if (intensidad >= 4) return '<span class="tag tag-mid">Moderado</span>';
    return '<span class="tag tag-low">Leve</span>';
  }

  function render() {
    const filtered = records.filter(r => currentFilter === 'todos' || r.type === currentFilter);
    list.innerHTML = '';
    emptyState.style.display = filtered.length === 0 ? 'block' : 'none';

    filtered.forEach(r => {
      const item = document.createElement('div');
      item.className = 'record-item';
      const bg = r.type === 'episodio' ? 'linear-gradient(135deg,#f2994a,#f2b26a)' : 'linear-gradient(135deg,#8b9dfb,#6c7fe8)';
      const detalle = r.type === 'episodio'
        ? `${(r.sintomas || []).slice(0, 2).join(', ') || 'Sin síntomas detallados'}${r.duracion ? ' · ' + r.duracion.replace(/_/g, ' ') : ''}`
        : (r.sintomas || []).slice(0, 3).join(', ') || 'Sin detalle';

      item.innerHTML = `
        <div class="record-icon" style="background:${bg};">${ICONS[r.type] || ''}</div>
        <div class="record-body">
          <div class="record-top">
            <span class="record-title">${r.label}</span>
            <span class="record-date">${msFormatDate(r.date)}</span>
          </div>
          <p class="record-desc">${detalle}</p>
          ${tagFor(r.intensidad || 0)}
        </div>`;
      list.appendChild(item);
    });
  }

  document.querySelectorAll('.filter-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      currentFilter = chip.dataset.filter;
      render();
    });
  });

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
      if (r.duracion) txt += `  Duración: ${r.duracion.replace(/_/g, ' ')}\n`;
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
