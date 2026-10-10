// Admin Audit Log page (admin_audit_log.php). Relies on escapeHtml, showToast,
// formatAdminDateTime, timeAgo and reviewField from admin.js, and hhDetails
// from dialog.js.
document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('audit-table');
  if (!table) return;

  // Plain-English names for what is stored in the database.
  const AREA_LABELS = {
    accounts: 'Accounts', roles: 'Roles', plots: 'Plots', resources: 'Resources',
    exchange: 'Exchange', crops: 'Crops', admin: 'Admin tools',
  };
  const ACTOR_LABELS = {
    admin: 'Administrator', coordinator: 'Coordinator', gardener: 'Gardener',
    guest: 'Visitor', system: 'Automatic',
  };
  const ACTION_LABELS = {
    login: 'Logged in',
    logout: 'Logged out',
    login_failed: 'Failed login',
    account_locked: 'Account locked',
    account_enabled: 'Account unlocked',
    password_reset_requested: 'Password reset requested',
    password_reset: 'Password changed',
    registration_submitted: 'Registration submitted',
    registration_approved: 'Registration approved',
    registration_rejected: 'Registration rejected',
    account_archived: 'Account archived',
    account_unarchived: 'Account restored',
    archive_notice_sent: 'Archive warning sent',
    admin_created: 'Administrator created',
    coordinator_application_submitted: 'Applied to be coordinator',
    coordinator_application_approved: 'Coordinator approved',
    coordinator_application_rejected: 'Coordinator application rejected',
    audit_exported: 'Audit log downloaded',
  };
  // Who did it, in words: visitors and automatic actions have no account name.
  const actorName = e => e.ActorName
    || { guest: 'Someone not logged in', system: 'HarvestHub' }[e.ActorType]
    || 'Unknown';

  // Anything not listed above: "plot_request_rejected" -> "Plot request rejected"
  const actionLabel = action => ACTION_LABELS[action]
    || action.replace(/_/g, ' ').replace(/^./, c => c.toUpperCase());

  const els = {
    form: document.getElementById('audit-filter-form'),
    search: document.getElementById('audit-search'),
    module: document.getElementById('audit-module'),
    actorType: document.getElementById('audit-actor-type'),
    action: document.getElementById('audit-action'),
    range: document.getElementById('audit-range'),
    from: document.getElementById('audit-from'),
    to: document.getElementById('audit-to'),
    customDates: [document.getElementById('audit-custom-dates'), document.getElementById('audit-custom-dates-to')],
    count: document.getElementById('audit-count'),
    prev: document.getElementById('audit-prev'),
    next: document.getElementById('audit-next'),
    pageLabel: document.getElementById('audit-page-label'),
  };

  let page = 1;
  let entriesById = {};
  let requestNumber = 0;

  // yyyy-mm-dd in the viewer's own time zone
  const isoDate = date => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');

  function dateRange() {
    const today = new Date();
    switch (els.range.value) {
      case 'today': return { from: isoDate(today), to: isoDate(today) };
      case '7':
      case '30': {
        const start = new Date();
        start.setDate(start.getDate() - (Number(els.range.value) - 1));
        return { from: isoDate(start), to: isoDate(today) };
      }
      case 'custom': return { from: els.from.value, to: els.to.value };
      default: return { from: '', to: '' };
    }
  }

  function filterParams() {
    const { from, to } = dateRange();
    const params = new URLSearchParams();
    if (els.search.value.trim()) params.set('q', els.search.value.trim());
    if (els.module.value) params.set('module', els.module.value);
    if (els.actorType.value) params.set('actor_type', els.actorType.value);
    if (els.action.value) params.set('action_type', els.action.value);
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    return params;
  }

  function fillActionFilter(actions) {
    const selected = els.action.value;
    const byArea = {};
    actions.forEach(({ Module, Action }) => { (byArea[Module] = byArea[Module] || []).push(Action); });
    els.action.innerHTML = '<option value="">All actions</option>' + Object.keys(byArea).map(area => `
      <optgroup label="${escapeHtml(AREA_LABELS[area] || area)}">
        ${byArea[area].map(a => `<option value="${escapeHtml(a)}">${escapeHtml(actionLabel(a))}</option>`).join('')}
      </optgroup>`).join('');
    els.action.value = selected;
  }

  function renderRows(entries) {
    entriesById = {};
    if (entries.length === 0) {
      const filtered = filterParams().toString() !== '';
      table.innerHTML = `<tr class="admin-empty-row"><td colspan="4" class="text-muted">${filtered
        ? 'No activity matches these filters. Try a wider date range or clear the filters.'
        : 'Nothing has been recorded yet. Activity will appear here as people use HarvestHub.'}</td></tr>`;
      return;
    }
    table.innerHTML = entries.map(e => {
      entriesById[e.LogID] = e;
      return `
      <tr class="audit-row" data-id="${e.LogID}" tabindex="0" title="Show full details">
        <td data-label="When">
          <span class="audit-when">${escapeHtml(formatAdminDateTime(e.OccurredAt))}</span>
          <span class="audit-ago">${escapeHtml(timeAgo(e.OccurredAt))}</span>
        </td>
        <td data-label="Who">
          <span class="audit-who">${escapeHtml(actorName(e))}</span>
          <span class="badge ${e.ActorType === 'admin' ? 'badge-brown' : e.ActorType === 'gardener' || e.ActorType === 'coordinator' ? 'badge-green' : 'badge-neutral'}">${escapeHtml(ACTOR_LABELS[e.ActorType] || e.ActorType || 'Unknown')}</span>
        </td>
        <td data-label="What happened">
          <span class="audit-action-label">${escapeHtml(AREA_LABELS[e.Module] || e.Module)} · ${escapeHtml(actionLabel(e.Action))}</span>
          <span class="audit-summary">${escapeHtml(e.Summary)}</span>
        </td>
        <td data-label="Reason">${e.Reason ? `<span class="audit-reason">${escapeHtml(e.Reason)}</span>` : '<span class="text-muted">—</span>'}</td>
      </tr>`;
    }).join('');
  }

  async function load() {
    const thisRequest = ++requestNumber;
    const params = filterParams();
    params.set('action', 'audit_log');
    params.set('page', page);
    els.count.textContent = 'Loading...';
    try {
      const res = await fetch(`api.php?${params}`);
      const data = await res.json();
      if (thisRequest !== requestNumber) return; // a newer search already started
      if (!data.ok) throw new Error(data.error);

      fillActionFilter(data.actions);
      renderRows(data.entries);

      const first = data.total === 0 ? 0 : (data.page - 1) * data.per_page + 1;
      const last = Math.min(data.page * data.per_page, data.total);
      const pages = Math.max(1, Math.ceil(data.total / data.per_page));
      els.count.textContent = data.total === 0
        ? 'No entries'
        : `Showing ${first}–${last} of ${data.total} ${data.total === 1 ? 'entry' : 'entries'}`;
      els.pageLabel.textContent = `Page ${data.page} of ${pages}`;
      els.prev.disabled = data.page <= 1;
      els.next.disabled = data.page >= pages;
    } catch (error) {
      if (thisRequest !== requestNumber) return;
      els.count.textContent = '';
      table.innerHTML = '<tr class="admin-empty-row"><td colspan="4" class="text-muted">Could not load the audit log. Please refresh the page.</td></tr>';
    }
  }

  // The before/after values are stored as JSON text; show them side by side.
  function changesTable(beforeText, afterText) {
    const parse = text => { try { return text ? JSON.parse(text) : {}; } catch { return {}; } };
    const before = parse(beforeText);
    const after = parse(afterText);
    const keys = [...new Set([...Object.keys(before), ...Object.keys(after)])];
    if (keys.length === 0) return '';
    const show = value => value === undefined ? '—' : Array.isArray(value) ? value.join(', ') : String(value);
    return `
      <section class="review-section">
        <h4>Changes</h4>
        <table class="audit-changes">
          <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
          <tbody>${keys.map(k => `<tr><td>${escapeHtml(k)}</td><td>${escapeHtml(show(before[k]))}</td><td>${escapeHtml(show(after[k]))}</td></tr>`).join('')}</tbody>
        </table>
      </section>`;
  }

  function openDetails(entry) {
    hhDetails({
      title: actionLabel(entry.Action),
      bodyHtml: `
        <section class="review-section">
          <h4>What happened</h4>
          <p class="review-quote">${escapeHtml(entry.Summary)}</p>
        </section>
        ${entry.Reason ? `<section class="review-section"><h4>Reason given</h4><p class="review-quote">${escapeHtml(entry.Reason)}</p></section>` : ''}
        <section class="review-section">
          <h4>Details</h4>
          <dl class="review-grid">
            ${reviewField('When', `${formatAdminDateTime(entry.OccurredAt)} (${timeAgo(entry.OccurredAt)})`)}
            ${reviewField('Done by', `${actorName(entry)} (${ACTOR_LABELS[entry.ActorType] || entry.ActorType || 'unknown'})`)}
            ${reviewField('Area', AREA_LABELS[entry.Module] || entry.Module)}
            ${entry.TargetName ? reviewField('About', entry.TargetName) : ''}
            ${entry.IpAddress ? reviewField('Network address (IP)', entry.IpAddress) : ''}
          </dl>
        </section>
        ${changesTable(entry.BeforeData, entry.AfterData)}`,
    });
  }

  // --- wiring ---
  let searchTimer;
  const reload = () => { page = 1; load(); };
  els.search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(reload, 300); });
  [els.module, els.actorType, els.action, els.from, els.to].forEach(el => el.addEventListener('change', reload));
  els.range.addEventListener('change', () => {
    els.customDates.forEach(el => { el.hidden = els.range.value !== 'custom'; });
    reload();
  });
  els.form.addEventListener('submit', event => event.preventDefault());

  document.getElementById('audit-reset').addEventListener('click', () => {
    els.form.reset();
    els.customDates.forEach(el => { el.hidden = true; });
    reload();
  });

  document.getElementById('audit-export').addEventListener('click', () => {
    const params = filterParams();
    params.set('action', 'audit_log_export');
    window.location.href = `api.php?${params}`;
    showToast('Preparing your download...', 'success');
  });

  els.prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
  els.next.addEventListener('click', () => { page++; load(); });

  const openRow = target => {
    const row = target.closest('.audit-row');
    if (row && entriesById[row.dataset.id]) openDetails(entriesById[row.dataset.id]);
  };
  table.addEventListener('click', event => openRow(event.target));
  table.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openRow(event.target); }
  });

  load();
});
