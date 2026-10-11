// Reports page (admin_reports.php): pick a report and a time period; the
// report is shown as summary numbers and tables, and can be downloaded as CSV
// or printed as a PDF. Relies on escapeHtml, showToast and pagedRows from admin.js.
document.addEventListener('DOMContentLoaded', () => {
  const page = document.querySelector('.reports-page');
  if (!page) return;

  const els = {
    select: document.getElementById('report-select'),
    items: [...document.querySelectorAll('.reports-menu-item')],
    rangeBtns: [...document.querySelectorAll('.reports-filters .dash-range-btn')],
    custom: document.getElementById('report-custom'),
    from: document.getElementById('report-from'),
    to: document.getElementById('report-to'),
    personWrap: document.getElementById('report-person-wrap'),
    person: document.getElementById('report-person'),
    moduleWrap: document.getElementById('report-module-wrap'),
    module: document.getElementById('report-module'),
    title: document.getElementById('report-title'),
    description: document.getElementById('report-description'),
    period: document.getElementById('report-period'),
    summary: document.getElementById('report-summary'),
    tables: document.getElementById('report-tables'),
  };
  if (!els.items.length) {
    els.title.textContent = 'No reports available';
    els.description.textContent = 'Your roles do not include any reports. Ask an administrator if you need one.';
    return;
  }

  const isoDay = date => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
  // person: the chosen person's key, kept here because the list only arrives with the first report
  const state = { report: els.items[0].dataset.report, range: '30', from: '', to: '', person: new URLSearchParams(location.search).get('person') || '' };
  let requestNumber = 0;

  function dates() {
    if (state.range === 'custom') return { from: state.from, to: state.to };
    const today = new Date();
    if (state.range === 'today') return { from: isoDay(today), to: isoDay(today) };
    const start = new Date(today);
    start.setDate(start.getDate() - (Number(state.range) - 1));
    return { from: isoDay(start), to: isoDay(today) };
  }

  function params(extra = {}) {
    const p = new URLSearchParams({ report: state.report, ...dates(), ...extra });
    if (state.report === 'user_activity' && state.person) p.set('person', state.person);
    if (state.report === 'audit' && els.module.value) p.set('module', els.module.value);
    return p;
  }

  function remember() {
    const p = new URLSearchParams({ report: state.report, range: state.range });
    if (state.range === 'custom') { p.set('from', state.from); p.set('to', state.to); }
    if (state.report === 'user_activity' && state.person) p.set('person', state.person);
    try { history.replaceState(null, '', `?${p}`); } catch (e) { /* ignore */ }
  }

  function selectReport(key) {
    state.report = key;
    els.select.value = key;
    els.items.forEach(item => {
      const active = item.dataset.report === key;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-current', active ? 'true' : 'false');
    });
    els.personWrap.hidden = key !== 'user_activity';
    els.moduleWrap.hidden = key !== 'audit';
    load();
  }

  async function load() {
    remember();
    const thisRequest = ++requestNumber;
    page.classList.add('is-loading');
    let data;
    try {
      const res = await fetch(`api.php?${params({ action: 'report' })}`);
      data = await res.json();
    } catch (e) {
      data = { ok: false, error: 'Could not reach HarvestHub. Check your connection and try again.' };
    }
    if (thisRequest !== requestNumber) return;
    page.classList.remove('is-loading');
    if (!data.ok) {
      els.title.textContent = 'This report could not be shown';
      els.description.textContent = data.error || 'Please try again.';
      els.summary.innerHTML = '';
      els.tables.innerHTML = '';
      return;
    }
    render(data.report);
  }

  function render(r) {
    els.title.textContent = r.title;
    els.description.textContent = r.description;
    els.period.textContent = `Period: ${r.period}`;
    if (state.range === 'custom' || !els.from.value) { els.from.value = r.range.from; els.to.value = r.range.to; }

    if (r.people && els.person.options.length <= 1) {
      els.person.innerHTML = '<option value="">Everyone</option>' + r.people.map(p => `<option value="${escapeHtml(p.key)}">${escapeHtml(p.name)}</option>`).join('');
    }
    if (r.people) els.person.value = state.person;

    els.summary.innerHTML = r.summary.map(s => `
      <div class="stat-card"><div class="stat-value">${escapeHtml(String(s.value))}</div><div class="stat-label">${escapeHtml(s.label)}</div></div>`).join('');

    els.tables.innerHTML = r.tables.map((t, i) => `
      <section class="report-table" aria-labelledby="report-table-${i}">
        <h3 id="report-table-${i}">${escapeHtml(t.title)} <span class="report-count">${t.rows.length} ${t.rows.length === 1 ? 'row' : 'rows'}</span></h3>
        ${t.note ? `<p class="dash-card-help">${escapeHtml(t.note)}</p>` : ''}
        <div class="table-wrap"><table class="data-table admin-responsive-table report-data-table">
          <thead><tr>${t.columns.map(c => `<th scope="col">${escapeHtml(c)}</th>`).join('')}</tr></thead>
          <tbody data-table-index="${i}"></tbody>
        </table></div>
      </section>`).join('');

    r.tables.forEach((t, i) => {
      const body = els.tables.querySelector(`tbody[data-table-index="${i}"]`);
      const show = pagedRows(body.closest('.table-wrap'), 50, rows => {
        body.innerHTML = rows.map(row => `<tr>${row.map((cell, c) => `<td data-label="${escapeHtml(t.columns[c])}"${typeof cell === 'number' ? ' class="dash-num"' : ''}>${escapeHtml(String(cell))}</td>`).join('')}</tr>`).join('')
          || `<tr class="admin-empty-row"><td colspan="${t.columns.length}" class="text-muted">Nothing in this period.</td></tr>`;
      });
      show(t.rows);
    });
  }

  // ---------- Controls ----------
  els.items.forEach(item => item.addEventListener('click', () => selectReport(item.dataset.report)));
  els.select.addEventListener('change', () => selectReport(els.select.value));
  els.person.addEventListener('change', () => { state.person = els.person.value; load(); });
  els.module.addEventListener('change', load);

  els.rangeBtns.forEach(btn => btn.addEventListener('click', () => {
    els.rangeBtns.forEach(b => {
      const active = b === btn;
      b.classList.toggle('is-active', active);
      b.setAttribute('aria-pressed', String(active));
    });
    els.custom.hidden = btn.dataset.range !== 'custom';
    if (btn.dataset.range === 'custom') { els.from.focus(); return; }
    state.range = btn.dataset.range;
    load();
  }));
  els.custom.addEventListener('submit', event => {
    event.preventDefault();
    if (!els.from.value || !els.to.value) { showToast('Choose both dates.', 'danger'); return; }
    state.range = 'custom';
    state.from = els.from.value;
    state.to = els.to.value;
    load();
  });

  document.getElementById('report-csv')?.addEventListener('click', () => {
    window.location.href = `api.php?${params({ action: 'report_export' })}`;
    showToast('Preparing your download...');
  });
  document.getElementById('report-print')?.addEventListener('click', () => {
    window.open(`admin_report_print.php?${params()}`, '_blank', 'noopener');
  });

  // ---------- Start (links like admin_reports.php?report=plots&range=90 open that report) ----------
  const query = new URLSearchParams(location.search);
  if (['today', '7', '30', '90', '365', 'custom'].includes(query.get('range'))) {
    state.range = query.get('range');
    if (state.range === 'custom') {
      state.from = query.get('from') || '';
      state.to = query.get('to') || '';
      els.custom.hidden = false;
    }
    els.rangeBtns.forEach(b => {
      const active = b.dataset.range === state.range;
      b.classList.toggle('is-active', active);
      b.setAttribute('aria-pressed', String(active));
    });
  }
  const wantedReport = query.get('report');
  selectReport(els.items.some(i => i.dataset.report === wantedReport) ? wantedReport : state.report);
});
