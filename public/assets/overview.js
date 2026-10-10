// Garden Overview (admin_overview.php): read-only tabs for plots, resources,
// requests, exchange listings and registrations. Relies on escapeHtml,
// formatAdminDate, formatAdminDateTime, timeAgo, profileLink, outcomeBadge,
// requestRows and REQUEST_KIND_LABELS from admin.js, and hhDetails from dialog.js.
document.addEventListener('DOMContentLoaded', () => {
  const tabs = [...document.querySelectorAll('[data-overview-tab]')];
  if (!tabs.length) return;

  const loaded = {};
  const data = {};
  const matches = (query, ...values) => !query || values.some(v => String(v ?? '').toLowerCase().includes(query));
  const searchValue = name => (document.querySelector(`[data-search="${name}"]`)?.value || '').trim().toLowerCase();
  const empty = (cols, text) => `<tr class="admin-empty-row"><td colspan="${cols}" class="text-muted">${escapeHtml(text)}</td></tr>`;
  const dateCell = (value, label = '') => value
    ? `<span class="audit-when">${escapeHtml(formatAdminDate(value))}</span><span class="audit-ago">${escapeHtml(label || timeAgo(value))}</span>`
    : '<span class="text-muted">—</span>';
  const days = n => (n === 1 ? '1 day' : `${n} days`);

  async function getJson(params) {
    try {
      const res = await fetch(`api.php?${new URLSearchParams(params)}`);
      return await res.json();
    } catch (e) {
      return { ok: false, error: 'Could not reach HarvestHub. Check your connection and try again.' };
    }
  }

  // ---------- Plots ----------

  function renderPlots() {
    const q = searchValue('plots');
    const rows = data.plots.filter(p => matches(q, p.Label, p.GardenerName, p.Location));
    document.getElementById('overview-plots').innerHTML = rows.map(p => `
      <tr>
        <td data-label="Plot"><strong>${escapeHtml(p.Label)}</strong><span class="audit-ago">${escapeHtml(p.Location)}${p.AreaSqM ? ` · ${escapeHtml(String(p.AreaSqM))} m²` : ''}</span></td>
        <td data-label="Status"><span class="badge ${p.Status === 'Available' ? 'badge-green' : 'badge-brown'}">${escapeHtml(p.Status)}</span></td>
        <td data-label="Held by">${p.GardenerName ? profileLink('gardener', p.GardenerID, p.GardenerName) : '<span class="text-muted">Nobody</span>'}</td>
        <td data-label="Held since">${p.GardenerName ? dateCell(p.HeldSince) : '<span class="text-muted">—</span>'}</td>
        <td data-label="Requests">${p.TotalRequests}${p.PendingRequests > 0 ? ` <span class="badge badge-brown">${p.PendingRequests} waiting</span>` : ''}</td>
        <td data-label="History"><button type="button" class="btn btn-ghost btn-sm" data-plot-history="${p.PltID}">View history</button></td>
      </tr>`).join('') || empty(6, 'No plots match your search.');
  }

  async function showPlotHistory(plotId) {
    const plot = data.plots.find(p => String(p.PltID) === String(plotId));
    const history = await getJson({ action: 'overview_plot_history', plot_id: plotId });
    if (!history.ok) {
      showToast(history.error || 'Could not load the plot history.', 'danger');
      return;
    }
    const events = history.events.map(e => `
      <li><strong>${escapeHtml(e.EventType)}</strong> · ${escapeHtml(formatAdminDateTime(e.OccurredAt))}
        ${e.ActorName ? `<br><span class="text-muted">By ${escapeHtml(e.ActorName)}${e.GardenerName && e.GardenerName !== e.ActorName ? ` · gardener: ${escapeHtml(e.GardenerName)}` : ''}</span>` : ''}
        ${e.RejectionReason ? `<br>Reason: ${escapeHtml(e.RejectionReason)}` : e.RequestReason ? `<br>Note: ${escapeHtml(e.RequestReason)}` : ''}</li>`).join('');
    const requests = history.requests.map(r => `
      <li><strong>${escapeHtml({ Apply: 'Asked for the plot', Unassign: 'Asked to give it up', Return: 'Asked to return it' }[r.RequestType] || r.RequestType)}: ${escapeHtml(r.Status)}</strong>
        · ${escapeHtml(r.GardenerName || 'Unknown')} · ${escapeHtml(formatAdminDate(r.AppliedAt))}
        ${r.CoordinatorName ? `<br><span class="text-muted">Decided by ${escapeHtml(r.CoordinatorName)}${r.ProcessedAt ? ` on ${escapeHtml(formatAdminDate(r.ProcessedAt))}` : ''}</span>` : ''}
        ${r.RejectionReason ? `<br>Reason: ${escapeHtml(r.RejectionReason)}` : ''}</li>`).join('');
    hhDetails({
      title: `${plot ? plot.Label : 'Plot'} history`,
      bodyHtml: `
        <div class="review-section"><h4>Timeline</h4>${events ? `<ul class="review-history">${events}</ul>` : '<p class="review-muted">Nothing recorded yet. Plot events are kept from October 7, 2026.</p>'}</div>
        <div class="review-section"><h4>All requests for this plot</h4>${requests ? `<ul class="review-history">${requests}</ul>` : '<p class="review-muted">No requests yet.</p>'}</div>`,
    });
  }

  // ---------- Resources ----------

  function renderResources() {
    const q = searchValue('resources');
    const overdueOnly = document.getElementById('overview-overdue-only').checked;
    const rows = [];
    data.resources.resources.forEach(r => {
      const holders = r.Holders.filter(h => (!overdueOnly || h.Overdue) && matches(q, r.Name, h.GardenerName, h.PlotLabel));
      const showEmpty = !overdueOnly && !r.Holders.length && matches(q, r.Name);
      holders.forEach((h, i) => rows.push(`
        <tr class="${h.Overdue ? 'overview-overdue' : ''}">
          <td data-label="Resource">${i === 0 ? `<strong>${escapeHtml(r.Name)}</strong><span class="audit-ago">${r.AvailableQty} of ${r.TotalQty} on the shelf</span>` : `<span class="text-muted">${escapeHtml(r.Name)}</span>`}</td>
          <td data-label="Gardener">${profileLink('gardener', h.GardenerID, h.GardenerName)}${h.PlotLabel ? `<span class="audit-ago">${escapeHtml(h.PlotLabel)}</span>` : ''}</td>
          <td data-label="Quantity">${h.Qty}</td>
          <td data-label="Held for">${h.DaysHeld === null ? '—' : days(Number(h.DaysHeld))}<span class="audit-ago">since ${escapeHtml(formatAdminDate(h.ApprovedAt))}</span></td>
          <td data-label="Return">${h.Status === 'Return Requested'
            ? `<span class="badge ${h.Overdue ? 'badge-danger' : 'badge-brown'}">${h.Overdue ? 'Overdue' : 'Return requested'}</span><span class="audit-ago">asked ${escapeHtml(timeAgo(h.ReturnRequestedAt))}</span>`
            : '<span class="text-muted">Not asked yet</span>'}</td>
        </tr>`));
      if (showEmpty) rows.push(`
        <tr>
          <td data-label="Resource"><strong>${escapeHtml(r.Name)}</strong><span class="audit-ago">${r.AvailableQty} of ${r.TotalQty} on the shelf</span></td>
          <td data-label="Gardener" colspan="4"><span class="text-muted">Nobody is borrowing this</span></td>
        </tr>`);
    });
    document.getElementById('overview-resources').innerHTML = rows.join('')
      || empty(5, overdueOnly ? 'No overdue returns.' : 'No resources match your search.');
  }

  // ---------- Requests ----------

  let showRequests = null;
  function renderRequests() {
    const body = document.getElementById('overview-requests');
    showRequests ??= pagedRows(body.closest('.table-wrap'), 50, rows => {
      body.innerHTML = requestRows(rows, true) || empty(6, 'No requests match these filters.');
    });
    const q = searchValue('requests');
    const kind = document.getElementById('overview-request-kind').value;
    const outcome = document.getElementById('overview-request-outcome').value;
    const rows = data.requests.filter(r => (!kind || r.Kind === kind) && (!outcome || r.Outcome === outcome)
      && matches(q, r.Who, r.What, r.Reason, r.Notes, r.DecidedBy));
    document.getElementById('overview-requests-count').textContent =
      `${rows.length} ${rows.length === 1 ? 'request' : 'requests'}${data.requests.length >= 1000 ? ' (the 1,000 most recent)' : ''}`;
    showRequests(rows);
  }

  // ---------- Exchange ----------

  function renderExchange() {
    const q = searchValue('exchange');
    const rows = data.exchange.filter(l => matches(q, l.ProduceName, l.GardenerName, l.Description, ...l.Claims.map(c => c.RequesterName)));
    document.getElementById('overview-exchange').innerHTML = rows.map(l => `
      <tr>
        <td data-label="Listing"><span class="audit-action-label">${escapeHtml(l.Type)}</span><strong>${escapeHtml(l.ProduceName)}</strong> · ${escapeHtml(l.Qty)}
          ${l.Description ? `<span class="audit-ago">${escapeHtml(l.Description)}</span>` : ''}</td>
        <td data-label="Posted by">${profileLink('gardener', l.GardenerID, l.GardenerName)}<span class="audit-ago">${escapeHtml(formatAdminDate(l.CreatedAt))}</span></td>
        <td data-label="Status"><span class="badge ${l.Status === 'Active' ? 'badge-green' : 'badge-neutral'}">${escapeHtml(l.Status)}</span></td>
        <td data-label="Claims">${l.Claims.length ? `<ul class="overview-claims">${l.Claims.map(c => `
          <li>${profileLink('gardener', c.RequesterID, c.RequesterName)} wants ${escapeHtml(c.QtyWanted)} <span class="badge ${{ Pending: 'badge-brown', Accepted: 'badge-green', Rejected: 'badge-danger' }[c.Status] || 'badge-neutral'}">${escapeHtml(c.Status)}</span>
            <span class="audit-ago">${escapeHtml(formatAdminDate(c.CreatedAt))}${c.PickupDetails ? ` · ${escapeHtml(c.PickupDetails)}` : ''}</span></li>`).join('')}</ul>`
          : '<span class="text-muted">No claims yet</span>'}</td>
      </tr>`).join('') || empty(4, data.exchange.length ? 'No listings match your search.' : 'Nothing has been posted on the exchange board yet.');
  }

  // ---------- Registrations ----------

  function renderRegistrations() {
    const q = searchValue('registrations');
    const status = document.getElementById('overview-registration-status').value;
    const rows = data.registrations.filter(r => (!status || r.Status === status) && matches(q, r.Name, r.Email, r.Location, r.RejectionReason));
    const tone = { Pending: 'badge-brown', Approved: 'badge-green', Rejected: 'badge-danger' };
    document.getElementById('overview-registrations').innerHTML = rows.map(r => `
      <tr>
        <td data-label="Applicant"><strong>${escapeHtml(r.Name)}</strong><span class="audit-ago">${escapeHtml(r.Email)} · ${escapeHtml(r.Location || 'No location')}${r.Age ? ` · age ${escapeHtml(String(r.Age))}` : ''}</span></td>
        <td data-label="Signed up">${dateCell(r.RequestedAt)}</td>
        <td data-label="Status"><span class="badge ${tone[r.Status] || 'badge-neutral'}">${escapeHtml(r.Status)}</span></td>
        <td data-label="Reviewed">${r.ReviewedAt ? `${escapeHtml(formatAdminDate(r.ReviewedAt))}<span class="audit-ago">${r.ReviewedBy ? `by ${escapeHtml(r.ReviewedBy)}` : 'reviewer not recorded'}</span>` : '<span class="text-muted">Not yet</span>'}</td>
        <td data-label="Reason">${r.RejectionReason ? escapeHtml(r.RejectionReason) : '<span class="text-muted">—</span>'}</td>
      </tr>`).join('') || empty(5, 'No registrations match these filters.');
  }

  // ---------- Tabs ----------

  const sections = {
    plots: { load: async () => { const d = await getJson({ action: 'overview', section: 'plots' }); data.plots = d.plots; return d; }, render: renderPlots, body: 'overview-plots', cols: 6 },
    resources: {
      load: async () => {
        const d = await getJson({ action: 'overview', section: 'resources' });
        if (d.ok) {
          data.resources = d;
          const overdue = d.resources.reduce((n, r) => n + r.Holders.filter(h => h.Overdue).length, 0);
          document.getElementById('overview-resources-help').textContent =
            `Who is holding what right now. A return is overdue when it was asked for ${d.overdue_days} or more days ago.${overdue ? ` ${overdue} ${overdue === 1 ? 'return is' : 'returns are'} overdue.` : ''}`;
        }
        return d;
      },
      render: renderResources, body: 'overview-resources', cols: 5,
    },
    requests: { load: async () => { const d = await getJson({ action: 'overview', section: 'requests' }); data.requests = d.requests; return d; }, render: renderRequests, body: 'overview-requests', cols: 6 },
    exchange: { load: async () => { const d = await getJson({ action: 'overview', section: 'exchange' }); data.exchange = d.listings; return d; }, render: renderExchange, body: 'overview-exchange', cols: 4 },
    registrations: { load: async () => { const d = await getJson({ action: 'overview', section: 'registrations' }); data.registrations = d.registrations; return d; }, render: renderRegistrations, body: 'overview-registrations', cols: 5 },
  };

  async function openTab(name) {
    tabs.forEach(tab => {
      const active = tab.dataset.overviewTab === name;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', String(active));
      document.getElementById(`overview-panel-${tab.dataset.overviewTab}`).hidden = !active;
    });
    try { history.replaceState(null, '', `#${name}`); } catch (e) { /* ignore */ }
    if (loaded[name]) return;
    loaded[name] = true;
    const section = sections[name];
    const result = await section.load();
    if (!result.ok) {
      loaded[name] = false;
      document.getElementById(section.body).innerHTML = empty(section.cols, result.error || 'Could not load this section.');
      return;
    }
    section.render();
  }

  tabs.forEach(tab => tab.addEventListener('click', () => openTab(tab.dataset.overviewTab)));
  document.querySelectorAll('.overview-search').forEach(input => input.addEventListener('input', () => {
    if (loaded[input.dataset.search] && data[input.dataset.search]) sections[input.dataset.search].render();
  }));
  [['overview-overdue-only', 'resources'], ['overview-request-kind', 'requests'], ['overview-request-outcome', 'requests'], ['overview-registration-status', 'registrations']]
    .forEach(([id, name]) => document.getElementById(id)?.addEventListener('change', () => { if (loaded[name]) sections[name].render(); }));
  document.getElementById('overview-plots')?.addEventListener('click', event => {
    const btn = event.target.closest('[data-plot-history]');
    if (btn) showPlotHistory(btn.dataset.plotHistory);
  });

  // Open the tab named in the address (e.g. admin_overview.php#requests), else the first one
  const wanted = location.hash.slice(1);
  openTab(tabs.some(t => t.dataset.overviewTab === wanted) ? wanted : tabs[0].dataset.overviewTab);
});
