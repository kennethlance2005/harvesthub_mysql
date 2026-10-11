// Admin dashboard (admin_dashboard.php): one time-period filter drives every
// chart. Each chart has a plain-English title, a one-line explanation and a
// "Show as table" view. Relies on escapeHtml, showToast and logReportExport
// from admin.js, and Chart.js.
//
// Colors were checked with the dataviz palette validator on the white panel
// (#ffffff): the three series colors pass every colorblind-safety check for up
// to three series together, and the green ramp passes as an ordered scale.
document.addEventListener('DOMContentLoaded', () => {
  if (!document.querySelector('.dash-page') || typeof Chart === 'undefined') return;

  const COLORS = {
    series: ['#2f855a', '#c2571a', '#2b6cb0'], // green, brown-orange, blue: never more than three per chart
    ramp: ['#78bb8e', '#4f9d6e', '#2f855a', '#1f5e3f'], // light -> dark, for ordered groups
    surface: '#ffffff',
    grid: '#ece8de',
    axis: '#c9c3b4',
    ink: '#201f1b',
    muted: '#6b6a63',
  };
  Chart.defaults.font.family = 'Inter, system-ui, -apple-system, "Segoe UI", sans-serif';
  Chart.defaults.font.size = 12;
  Chart.defaults.color = COLORS.muted;
  Chart.defaults.animation.duration = 250;

  const els = {
    rangeBtns: [...document.querySelectorAll('.dash-range-btn')],
    custom: document.getElementById('dash-custom'),
    from: document.getElementById('dash-from'),
    to: document.getElementById('dash-to'),
    label: document.getElementById('dash-range-label'),
    kpis: document.getElementById('dash-kpis'),
    attention: document.getElementById('dash-attention'),
  };
  const charts = {};      // id -> Chart instance
  const tables = {};      // id -> { columns, rows } for the table view and CSV
  let data = null;
  let requestNumber = 0;

  // ---------- Dates ----------
  const parseDay = key => { const [y, m, d] = key.split('-').map(Number); return new Date(y, m - 1, d); };
  const fmtDay = (key, opts = { month: 'short', day: 'numeric' }) => parseDay(key).toLocaleDateString(undefined, opts);
  const fmtLong = key => fmtDay(key, { month: 'short', day: 'numeric', year: 'numeric' });
  const isoDay = date => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
  const bucketLabel = key => (data.range.bucket === 'week' ? `Week of ${fmtDay(key)}` : fmtDay(key));
  const bucketLabels = () => data.range.keys.map(bucketLabel);
  const hoursText = hours => {
    if (hours === null || hours === undefined) return 'not recorded';
    if (hours < 1) return 'under an hour';
    if (hours < 48) return `about ${Math.round(hours)} ${Math.round(hours) === 1 ? 'hour' : 'hours'}`;
    return `about ${Math.round(hours / 24)} days`;
  };
  const pct = (part, whole) => (whole ? `${Math.round((part / whole) * 100)}%` : '—');

  // ---------- Chart building ----------
  const baseScales = (horizontal, stacked) => {
    const valueAxis = {
      beginAtZero: true, stacked,
      grid: { color: COLORS.grid, drawTicks: false }, border: { display: false },
      ticks: { precision: 0, padding: 6 },
    };
    const categoryAxis = {
      stacked,
      grid: { display: false }, border: { color: COLORS.axis },
      ticks: { padding: 6, autoSkip: true, maxRotation: 0, autoSkipPadding: 12 },
    };
    return horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis };
  };

  function barDataset(label, values, color, stacked) {
    return {
      label, data: values, backgroundColor: color,
      borderColor: COLORS.surface, borderWidth: stacked ? 2 : 0, // a surface-colored gap between stacked pieces
      borderRadius: stacked ? 2 : 4, borderSkipped: stacked ? false : 'start',
      maxBarThickness: 30, categoryPercentage: 0.8, barPercentage: 0.9,
    };
  }

  function lineDataset(label, values, color) {
    return {
      label, data: values, borderColor: color, backgroundColor: color,
      borderWidth: 2, pointRadius: values.length === 1 ? 4 : 0, pointHoverRadius: 4,
      pointHoverBorderColor: COLORS.surface, pointHoverBorderWidth: 2, tension: 0,
    };
  }

  // Draws (or redraws) one chart. spec: { type: 'bar'|'line', horizontal, stacked,
  // labels, datasets, columns, rows, empty, note, height, tooltip }
  function draw(id, spec) {
    const box = document.querySelector(`[data-chart="${id}"]`);
    const canvas = box.querySelector('canvas');
    const emptyEl = box.querySelector('.dash-empty');
    const noteEl = document.querySelector(`[data-note="${id}"]`);
    tables[id] = { columns: spec.columns, rows: spec.rows };

    const total = spec.datasets.reduce((sum, ds) => sum + ds.data.reduce((a, v) => a + (Number(v) || 0), 0), 0);
    const isEmpty = total === 0;
    emptyEl.hidden = !isEmpty;
    emptyEl.textContent = spec.empty || 'Nothing to show for this period.';
    canvas.hidden = isEmpty;
    box.style.height = isEmpty ? 'auto' : `${spec.height || 260}px`;
    noteEl.hidden = !spec.note;
    noteEl.innerHTML = spec.note || '';
    renderTable(id);

    if (charts[id]) { charts[id].destroy(); delete charts[id]; }
    if (isEmpty) return;

    const multi = spec.datasets.length > 1;
    charts[id] = new Chart(canvas, {
      type: spec.type,
      data: { labels: spec.labels, datasets: spec.datasets },
      options: {
        indexAxis: spec.horizontal ? 'y' : 'x',
        maintainAspectRatio: false,
        interaction: spec.type === 'line' || spec.stacked ? { mode: 'index', intersect: false } : { mode: 'nearest', intersect: false, axis: spec.horizontal ? 'y' : 'x' },
        scales: baseScales(spec.horizontal, spec.stacked),
        plugins: {
          legend: {
            display: multi, position: 'top', align: 'start',
            labels: { color: COLORS.ink, boxWidth: 12, boxHeight: 12, useBorderRadius: true, borderRadius: 2, padding: 14 },
          },
          tooltip: {
            backgroundColor: COLORS.ink, titleColor: '#fff', bodyColor: '#fff', padding: 10, cornerRadius: 6,
            boxPadding: 4, usePointStyle: false,
            callbacks: spec.tooltip || {},
          },
        },
      },
    });
  }

  function renderTable(id) {
    const holder = document.querySelector(`[data-table="${id}"]`);
    const { columns, rows } = tables[id];
    holder.innerHTML = rows.length ? `
      <div class="table-wrap"><table class="data-table dash-data-table">
        <thead><tr>${columns.map(c => `<th scope="col">${escapeHtml(c)}</th>`).join('')}</tr></thead>
        <tbody>${rows.map(r => `<tr>${r.map((v, i) => `<td${i ? ' class="dash-num"' : ''}>${escapeHtml(String(v))}</td>`).join('')}</tr>`).join('')}</tbody>
      </table></div>` : '<p class="text-muted">Nothing to show for this period.</p>';
  }

  // Table rows for a time series: one row per period, skipping empty ones in long ranges
  function timeRows(seriesList) {
    return data.range.keys.map((key, i) => [bucketLabel(key), ...seriesList.map(s => s[i])])
      .filter(row => data.range.keys.length <= 31 || row.slice(1).some(v => v > 0));
  }

  // ---------- Each chart ----------
  function drawAll() {
    const d = data;
    const labels = bucketLabels();
    const per = d.range.bucket === 'week' ? 'week' : 'day';

    // The garden
    const plotSince = d.tracking.plot_history_since;
    draw('occupancy', {
      type: 'line', labels,
      datasets: [lineDataset('Plots in use', d.plots.occupancy.in_use, COLORS.series[0]), lineDataset('All plots', d.plots.occupancy.total, COLORS.series[1])],
      columns: ['Period', 'Plots in use', 'All plots'],
      rows: timeRows([d.plots.occupancy.in_use, d.plots.occupancy.total]),
      note: `Right now <strong>${d.kpis.plots_in_use} of ${d.kpis.plots_total}</strong> plots are in use (${pct(d.kpis.plots_in_use, d.kpis.plots_total)}).`
        + (plotSince ? ` Plot history is recorded from ${escapeHtml(fmtLong(plotSince.slice(0, 10)))}, so earlier days repeat that day's numbers.` : ''),
      height: 240,
    });
    const cropLabels = Object.keys(d.crops.by_status);
    draw('crops', {
      type: 'bar', labels: cropLabels,
      datasets: [barDataset('Crops', Object.values(d.crops.by_status), COLORS.series[0])],
      columns: ['Status', 'Crops'], rows: cropLabels.map(k => [k, d.crops.by_status[k]]),
      empty: 'No crops have been logged yet.',
    });
    draw('harvests', {
      type: 'bar', labels,
      datasets: [barDataset('Harvests', d.crops.harvests, COLORS.series[0])],
      columns: ['Period', 'Harvests logged'], rows: timeRows([d.crops.harvests]),
      empty: 'No harvests were logged in this period.',
    });

    // Shared resources
    const levels = d.resources.levels;
    draw('levels', {
      type: 'bar', horizontal: true, stacked: true, labels: levels.map(l => l.name),
      datasets: [
        barDataset('On the shelf', levels.map(l => l.on_shelf), COLORS.series[0], true),
        barDataset('Borrowed', levels.map(l => l.borrowed), COLORS.series[1], true),
      ],
      columns: ['Item', 'On the shelf', 'Borrowed', 'Total'], rows: levels.map(l => [l.name, l.on_shelf, l.borrowed, l.on_shelf + l.borrowed]),
      empty: 'There are no shared resources yet.',
      height: Math.max(160, levels.length * 34 + 60),
    });
    const mb = d.resources.most_borrowed;
    draw('borrowed', {
      type: 'bar', horizontal: true, labels: mb.map(m => m.name),
      datasets: [barDataset('Lent out', mb.map(m => m.qty), COLORS.series[0])],
      columns: ['Item', 'Quantity lent'], rows: mb.map(m => [m.name, m.qty]),
      empty: 'Nothing was lent out in this period.',
      height: Math.max(160, mb.length * 34 + 40),
    });
    const heldLabels = ['Up to a week', '8 to 30 days', '31 to 60 days', 'Over 60 days'];
    draw('held', {
      type: 'bar', labels: heldLabels,
      datasets: [{ ...barDataset('Items out', d.resources.held_buckets, COLORS.ramp[0]), backgroundColor: COLORS.ramp }],
      columns: ['Time held', 'Items'], rows: heldLabels.map((l, i) => [l, d.resources.held_buckets[i]]),
      empty: 'Nothing is borrowed right now.',
      note: d.resources.overdue
        ? `<span class="dash-flag dash-flag-serious" aria-hidden="true">!</span> <strong>${d.resources.overdue}</strong> ${d.resources.overdue === 1 ? 'return is' : 'returns are'} overdue (asked for ${d.resources.overdue_days}+ days ago). <a href="admin_overview.php#resources">See who has them</a>`
        : 'No overdue returns.',
    });

    // Requests and decisions
    const rq = d.requests.over_time;
    draw('requests', {
      type: 'bar', stacked: true, labels,
      datasets: [
        barDataset('Plot requests', rq.plot, COLORS.series[0], true),
        barDataset('Resource requests', rq.resource, COLORS.series[1], true),
        barDataset('Coordinator applications', rq.coordinator, COLORS.series[2], true),
      ],
      columns: ['Period', 'Plot requests', 'Resource requests', 'Coordinator applications'],
      rows: timeRows([rq.plot, rq.resource, rq.coordinator]),
      empty: 'No requests were made in this period.',
      note: 'Fully approved resource requests are counted on the day they were lent out.',
    });
    const oc = d.requests.outcomes;
    const ocTypes = Object.keys(oc);
    draw('outcomes', {
      type: 'bar', horizontal: true, stacked: true, labels: ocTypes,
      datasets: [
        barDataset('Approved', ocTypes.map(t => oc[t].approved), COLORS.series[0], true),
        barDataset('Declined', ocTypes.map(t => oc[t].rejected), COLORS.series[1], true),
        barDataset('Cancelled by the gardener', ocTypes.map(t => oc[t].cancelled), COLORS.series[2], true),
      ],
      columns: ['Type', 'Approved', 'Declined', 'Cancelled', 'Approval rate', 'Typical time to decide'],
      rows: ocTypes.map(t => {
        const o = oc[t];
        return [t, o.approved, o.rejected, o.cancelled, pct(o.approved, o.approved + o.rejected), hoursText(o.avg_hours)];
      }),
      empty: 'No decisions were made in this period.',
      height: 220,
      tooltip: {
        footer: items => {
          const o = oc[ocTypes[items[0].dataIndex]];
          return `Approval rate: ${pct(o.approved, o.approved + o.rejected)}`;
        },
      },
      note: ocTypes.filter(t => oc[t].approved + oc[t].rejected > 0).map(t =>
        `<span class="dash-note-item"><strong>${escapeHtml(t)}:</strong> ${pct(oc[t].approved, oc[t].approved + oc[t].rejected)} approved${oc[t].avg_hours !== null ? `, decided in ${hoursText(oc[t].avg_hours)} on average` : ''}</span>`).join(''),
    });
    const reasons = d.requests.top_reasons;
    const shortReason = text => (text.length > 38 ? `${text.slice(0, 36)}…` : text);
    draw('reasons', {
      type: 'bar', horizontal: true, labels: reasons.map(x => shortReason(x.reason)),
      datasets: [barDataset('Times used', reasons.map(x => x.count), COLORS.series[0])],
      columns: ['Reason', 'Times used'], rows: reasons.map(x => [x.reason, x.count]),
      empty: 'Nothing was declined in this period.',
      height: Math.max(160, reasons.length * 34 + 40),
      tooltip: { title: items => reasons[items[0].dataIndex].reason },
    });
    const wl = d.requests.workload;
    draw('workload', {
      type: 'bar', horizontal: true, stacked: true, labels: wl.map(w => w.name),
      datasets: [
        barDataset('Plot decisions', wl.map(w => w.plots), COLORS.series[0], true),
        barDataset('Resource decisions', wl.map(w => w.resources), COLORS.series[1], true),
        barDataset('Crop catalog reviews', wl.map(w => w.crops), COLORS.series[2], true),
      ],
      columns: ['Coordinator', 'Plot decisions', 'Resource decisions', 'Crop catalog reviews', 'Total'],
      rows: wl.map(w => [w.name, w.plots, w.resources, w.crops, w.plots + w.resources + w.crops]),
      empty: 'No coordinator made a decision in this period.',
      height: Math.max(150, wl.length * 38 + 70),
      note: 'Decisions made by administrators or by people with extra roles are not counted here; the audit log shows them.',
    });

    // People
    draw('newusers', {
      type: 'bar', labels,
      datasets: [barDataset('New accounts', d.people.new_users, COLORS.series[0])],
      columns: ['Period', 'New accounts'], rows: timeRows([d.people.new_users]),
      empty: 'No new accounts in this period.',
    });
    const reg = d.people.registrations;
    draw('registrations', {
      type: 'bar', horizontal: true, labels: ['Waiting', 'Approved', 'Declined'],
      datasets: [barDataset('Sign-ups', [reg.Pending, reg.Approved, reg.Rejected], COLORS.series[0])],
      columns: ['Status', 'Sign-ups'], rows: [['Waiting', reg.Pending], ['Approved', reg.Approved], ['Declined', reg.Rejected]],
      empty: 'Nobody signed up in this period.',
      height: 170,
    });
    const auditSince = d.tracking.audit_since;
    draw('signins', {
      type: 'line', labels,
      datasets: [lineDataset('People active', d.people.active_users, COLORS.series[0]), lineDataset('Wrong-password attempts', d.people.failed_logins, COLORS.series[1])],
      columns: ['Period', 'People active', 'Wrong-password attempts'],
      rows: timeRows([d.people.active_users, d.people.failed_logins]),
      empty: 'Nobody used HarvestHub in this period.',
      note: `${d.people.locked ? `<span class="dash-flag dash-flag-serious" aria-hidden="true">!</span> <strong>${d.people.locked}</strong> ${d.people.locked === 1 ? 'account is' : 'accounts are'} locked right now. ` : 'No accounts are locked. '}`
        + (auditSince ? `Activity is counted from ${escapeHtml(fmtLong(auditSince.slice(0, 10)))}.` : ''),
    });
    const loc = d.people.by_location;
    draw('locations', {
      type: 'bar', horizontal: true, labels: loc.map(l => l.location),
      datasets: [barDataset('Gardeners', loc.map(l => l.count), COLORS.series[0])],
      columns: ['Location', 'Gardeners'], rows: loc.map(l => [l.location, l.count]),
      empty: 'No gardeners yet.',
      height: Math.max(150, loc.length * 32 + 40),
    });
  }

  // ---------- Headline numbers and attention list ----------
  function drawKpis() {
    const k = data.kpis;
    const tiles = [
      [k.gardeners, 'Active gardeners', ''],
      [k.coordinators, 'Coordinators', ''],
      [`${k.plots_in_use} of ${k.plots_total}`, 'Plots in use', pct(k.plots_in_use, k.plots_total)],
      [k.items_on_loan, 'Items out on loan', ''],
      [k.active_listings, 'Exchange listings open', ''],
      [k.trades_in_range, 'Trades completed', 'in this period'],
    ];
    els.kpis.innerHTML = tiles.map(([value, label, hint]) => `
      <div class="stat-card"><div class="stat-value">${escapeHtml(String(value))}</div><div class="stat-label">${escapeHtml(label)}</div>${hint ? `<div class="stat-hint">${escapeHtml(hint)}</div>` : ''}</div>`).join('');
  }

  function drawAttention() {
    const waiting = data.attention.filter(a => a.count > 0);
    els.attention.innerHTML = waiting.length
      ? waiting.map(a => `
          <li class="dash-attention-item dash-attention-${a.level}">
            <span class="dash-flag dash-flag-${a.level}" aria-hidden="true">${a.level === 'serious' ? '!' : '•'}</span>
            <span class="dash-attention-count">${a.count}</span>
            <span class="dash-attention-label">${escapeHtml(a.label)}<span class="sr-only"> (${a.level === 'serious' ? 'urgent' : 'waiting'})</span></span>
            <a class="btn btn-ghost btn-sm" href="${escapeHtml(a.link)}">Open</a>
          </li>`).join('')
      : '<li class="dash-attention-item dash-attention-good"><span class="dash-flag dash-flag-good" aria-hidden="true">✓</span><span class="dash-attention-label">Nothing is waiting. All requests have been handled.</span></li>';
  }

  // ---------- Loading ----------
  async function load(params) {
    const thisRequest = ++requestNumber;
    document.querySelector('.dash-page').classList.add('is-loading');
    let result;
    try {
      const res = await fetch(`api.php?${new URLSearchParams({ action: 'dashboard', ...params })}`);
      result = await res.json();
    } catch (e) {
      result = { ok: false, error: 'Could not reach HarvestHub. Check your connection and try again.' };
    }
    if (thisRequest !== requestNumber) return;
    document.querySelector('.dash-page').classList.remove('is-loading');
    if (!result.ok) {
      els.label.textContent = result.error || 'Could not load the dashboard.';
      return;
    }
    data = result;
    const r = data.range;
    els.label.textContent = r.from === r.to
      ? `Showing ${fmtLong(r.from)}`
      : `Showing ${fmtLong(r.from)} to ${fmtLong(r.to)} (${r.days} days${r.bucket === 'week' ? ', grouped by week' : ''})`;
    els.from.value = r.from;
    els.to.value = r.to;
    drawKpis();
    drawAttention();
    drawAll();
  }

  function rangeParams(range) {
    const today = new Date();
    if (range === 'today') return { from: isoDay(today), to: isoDay(today) };
    const days = Number(range);
    const start = new Date(today);
    start.setDate(start.getDate() - (days - 1));
    return { from: isoDay(start), to: isoDay(today) };
  }

  function selectRange(range) {
    els.rangeBtns.forEach(btn => {
      const active = btn.dataset.range === range;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', String(active));
    });
    els.custom.hidden = range !== 'custom';
    if (range === 'custom') { els.from.focus(); return; }
    try { history.replaceState(null, '', `?range=${range}`); } catch (e) { /* ignore */ }
    load(rangeParams(range));
  }

  els.rangeBtns.forEach(btn => btn.addEventListener('click', () => selectRange(btn.dataset.range)));
  els.custom.addEventListener('submit', event => {
    event.preventDefault();
    if (!els.from.value || !els.to.value) { showToast('Choose both dates.', 'danger'); return; }
    try { history.replaceState(null, '', `?range=custom&from=${els.from.value}&to=${els.to.value}`); } catch (e) { /* ignore */ }
    load({ from: els.from.value, to: els.to.value });
  });

  // Table view toggle
  document.querySelectorAll('.dash-table-toggle').forEach(btn => btn.addEventListener('click', () => {
    const id = btn.dataset.card;
    const showTable = btn.getAttribute('aria-pressed') !== 'true';
    btn.setAttribute('aria-pressed', String(showTable));
    btn.textContent = showTable ? 'Show as chart' : 'Show as table';
    document.querySelector(`[data-table="${id}"]`).hidden = !showTable;
    document.querySelector(`[data-chart="${id}"]`).hidden = showTable;
  }));

  // ---------- Export ----------
  document.getElementById('dash-export-csv')?.addEventListener('click', () => {
    if (!data) return;
    const csvCell = value => {
      let text = String(value ?? '');
      if (/^[=+\-@\t\r]/.test(text)) text = `'${text}`; // keep spreadsheets from running it as a formula
      return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
    };
    const lines = [['HarvestHub dashboard'], [els.label.textContent], []];
    lines.push(['Headline numbers']);
    [...els.kpis.querySelectorAll('.stat-card')].forEach(card => lines.push([card.querySelector('.stat-label').textContent, card.querySelector('.stat-value').textContent]));
    lines.push([], ['Needs attention']);
    data.attention.forEach(a => lines.push([a.label, a.count]));
    document.querySelectorAll('.dash-card').forEach(card => {
      const id = card.id.replace('card-', '');
      if (!tables[id]) return;
      lines.push([], [card.querySelector('h3').textContent], tables[id].columns, ...tables[id].rows);
    });
    const csv = '﻿' + lines.map(row => row.map(csvCell).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = Object.assign(document.createElement('a'), { href: url, download: `harvesthub-dashboard-${data.range.from}-to-${data.range.to}.csv` });
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    logReportExport('dashboard', 'csv');
  });

  document.getElementById('dash-export-pdf')?.addEventListener('click', () => {
    logReportExport('dashboard', 'pdf');
    setTimeout(() => window.print(), 100);
  });
  // Charts redraw at print size so nothing is cut off
  window.addEventListener('beforeprint', () => Object.values(charts).forEach(c => c.resize()));
  window.addEventListener('afterprint', () => Object.values(charts).forEach(c => c.resize()));

  // ---------- Start ----------
  const query = new URLSearchParams(location.search);
  const startRange = query.get('range');
  if (startRange === 'custom' && query.get('from') && query.get('to')) {
    els.rangeBtns.forEach(btn => { btn.classList.toggle('is-active', btn.dataset.range === 'custom'); btn.setAttribute('aria-pressed', String(btn.dataset.range === 'custom')); });
    els.custom.hidden = false;
    load({ from: query.get('from'), to: query.get('to') });
  } else {
    selectRange(['today', '7', '30', '90', '365'].includes(startRange) ? startRange : '30');
  }
});
