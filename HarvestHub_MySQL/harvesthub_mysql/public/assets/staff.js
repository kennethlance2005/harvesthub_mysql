// staff.js — Garden Coordinator dashboard logic

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function showToast(message, type = 'success') {
  const toastEl = document.createElement('div');
  toastEl.className = `toast${type === 'danger' ? ' toast-danger' : ''}`;
  toastEl.textContent = message;
  document.getElementById('toast-container').appendChild(toastEl);
  setTimeout(() => toastEl.remove(), 3500);
}

let applications = [];
let resourceTransactions = [];
let plots = [];
let resources = [];

function matchesSearch(value, query) {
  return String(value || '').toLowerCase().includes(query);
}

function renderApplications() {
  const listEl = document.getElementById('applications-list');
  const emptyEl = document.getElementById('applications-empty');
  if (!listEl || !emptyEl) return;
  const query = document.getElementById('applications-search').value.trim().toLowerCase();
  const filtered = applications.filter(app =>
    matchesSearch(app.GardenerName, query) || matchesSearch(app.Label, query));

  listEl.innerHTML = filtered.map(app => `
    <div class="action-row">
      <div>
        <div class="action-row-title">${escapeHtml(app.GardenerName)}</div>
        <div class="action-row-sub">${app.RequestType === 'Unassign'
          ? `Requesting for plot "${escapeHtml(app.Label)}" to be unassigned`
          : `Requesting ${escapeHtml(app.Label)}`}
          <span class="text-muted"> • ${escapeHtml(app.PlotStatus || 'Pending')}</span>
        </div>
      </div>
      <div class="action-row-actions">
        <button class="btn btn-accent btn-sm approve-app" data-id="${app.AppID}">Approve</button>
        <button class="btn btn-ghost btn-sm reject-app" data-id="${app.AppID}">Reject</button>
      </div>
    </div>
  `).join('');
  emptyEl.textContent = applications.length && !filtered.length
    ? 'No applications match your search.'
    : 'No pending applications.';
  emptyEl.hidden = filtered.length > 0;

  listEl.querySelectorAll('.approve-app').forEach(btn =>
    btn.addEventListener('click', () => processApplication(btn.dataset.id, 'approve')));
  listEl.querySelectorAll('.reject-app').forEach(btn =>
    btn.addEventListener('click', () => processApplication(btn.dataset.id, 'reject')));
}

function renderResourceTransactions() {
  const listEl = document.getElementById('resource-txns-list');
  const emptyEl = document.getElementById('resource-txns-empty');
  if (!listEl || !emptyEl) return;
  const query = document.getElementById('resource-search').value.trim().toLowerCase();
  const filtered = resourceTransactions.filter(txn =>
    matchesSearch(txn.GardenerName, query) || matchesSearch(txn.ResourceName, query));

  listEl.innerHTML = filtered.map(txn => `
    <div class="action-row">
      <div>
        <div class="action-row-title">${escapeHtml(txn.GardenerName)}</div>
        <div class="action-row-sub">${escapeHtml(String(txn.Qty))}x ${escapeHtml(txn.ResourceName)}</div>
      </div>
      <div class="action-row-actions">
        <button class="btn btn-accent btn-sm approve-txn" data-id="${txn.TxnID}">Approve</button>
        <button class="btn btn-ghost btn-sm reject-txn" data-id="${txn.TxnID}">Reject</button>
      </div>
    </div>
  `).join('');
  emptyEl.textContent = resourceTransactions.length && !filtered.length
    ? 'No resource requests match your search.'
    : 'No pending resource requests.';
  emptyEl.hidden = filtered.length > 0;

  listEl.querySelectorAll('.approve-txn').forEach(btn =>
    btn.addEventListener('click', () => processResourceTxn(btn.dataset.id, 'approve')));
  listEl.querySelectorAll('.reject-txn').forEach(btn =>
    btn.addEventListener('click', () => processResourceTxn(btn.dataset.id, 'reject')));
}

async function postAction(action, params) {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action, ...params }),
  });
  return res.json();
}

async function loadApplications() {
  const res = await fetch('api.php?action=pending_applications');
  const data = await res.json();
  if (!data.ok) return;
  applications = data.applications;
  renderApplications();
  renderCoordinatorOverview();
}

async function processApplication(appId, decision) {
  const data = await postAction('process_application', { app_id: appId, decision });
  if (data.ok) {
    showToast(`Application ${decision === 'approve' ? 'approved' : 'rejected'}.`, 'success');
    loadApplications();
    loadPlots();
  } else {
    showToast(data.error || 'Could not process application.', 'danger');
  }
}

async function loadResourceTxns() {
  const res = await fetch('api.php?action=pending_resource_txns');
  const data = await res.json();
  if (!data.ok) return;
  resourceTransactions = data.transactions;
  renderResourceTransactions();
  renderCoordinatorOverview();
}

async function processResourceTxn(txnId, decision) {
  const data = await postAction('process_resource_txn', { txn_id: txnId, decision });
  if (data.ok) {
    showToast(`Request ${decision === 'approve' ? 'approved' : 'rejected'}.`, 'success');
    loadResourceTxns();
    loadResources();
  } else {
    showToast(data.error || 'Could not process request.', 'danger');
  }
}

async function loadPlots() {
  const res = await fetch('api.php?action=all_plots');
  const data = await res.json();
  if (!data.ok) return;

  plots = data.plots;
  renderPlots();
  renderCoordinatorOverview();
}

function renderPlots(selectedFilter) {
  const mapEl = document.getElementById('plot-map');
  if (!mapEl) return;
  const filter = selectedFilter || document.getElementById('plot-status-filter').value;
  const filtered = plots.filter(plot => {
    const status = String(plot.Status || '').trim().toLowerCase();
    if (filter === 'available') return status === 'available';
    if (filter === 'unavailable') return status === 'occupied';
    return true;
  });

  mapEl.innerHTML = filtered.map(plot => {
    const available = String(plot.Status || '').trim().toLowerCase() === 'available';
    return `
      <article class="plot-tile ${available ? 'plot-tile-available' : 'plot-tile-occupied'}">
        <div class="plot-tile-top"><span class="plot-tile-label">${escapeHtml(plot.Label)}</span><span class="plot-status">${escapeHtml(plot.Status)}</span></div>
        <p class="plot-tile-gardener">${plot.GardenerName ? escapeHtml(plot.GardenerName) : 'Unassigned'}</p>
        ${available && !plot.GardenerName
          ? `<button class="btn btn-ghost btn-sm delete-plot" data-id="${plot.PltID}" data-label="${escapeHtml(plot.Label)}" type="button">Delete plot</button>`
          : ''}
      </article>
    `;
  }).join('') || '<p class="text-muted plot-map-empty">No plots match this filter.</p>';

  mapEl.querySelectorAll('.delete-plot').forEach(button => {
    button.addEventListener('click', () => deletePlot(button.dataset.id, button.dataset.label));
  });
}

async function createPlot(label) {
  const data = await postAction('create_plot', { label });
  if (data.ok) {
    showToast('Plot added.', 'success');
    document.getElementById('new-plot-label').value = '';
    loadPlots();
  } else {
    showToast(data.error || 'Could not add plot.', 'danger');
  }
}

async function deletePlot(plotId, label) {
  if (!window.confirm(`Delete plot "${label}"? This cannot be undone.`)) return;
  const data = await postAction('delete_plot', { plot_id: plotId });
  if (data.ok) {
    showToast('Plot deleted.', 'success');
    loadPlots();
  } else {
    showToast(data.error || 'Could not delete plot.', 'danger');
  }
}

async function loadResources() {
  const res = await fetch('api.php?action=all_resources');
  const data = await res.json();
  if (!data.ok) return;

  resources = data.resources;
  renderResources();
  renderCoordinatorOverview();
}

async function loadResourceRecords() {
  const listEl = document.getElementById('resource-records-list');
  if (!listEl) return;
  const res = await fetch('api.php?action=resource_records');
  const data = await res.json();
  if (!data.ok) {
    listEl.innerHTML = '<p class="text-muted">Could not load inventory records.</p>';
    return;
  }
  renderResourceRecords(data.records);
}

async function addResource(name, qty) {
  const data = await postAction('add_resource', { name, qty });
  if (!data.ok) {
    showToast(data.error || 'Could not add item.', 'danger');
    return;
  }
  document.getElementById('resource-name').value = '';
  document.getElementById('resource-qty').value = '1';
  showToast('Item added to inventory.', 'success');
  loadResources();
}

async function requestResourceReturn(txnId) {
  const data = await postAction('request_resource_return', { txn_id: txnId });
  if (!data.ok) {
    showToast(data.error || 'Could not request this return.', 'danger');
    return;
  }
  showToast('Return request sent to the borrower.', 'success');
  loadResources();
  loadResourceRecords();
}

function renderResources() {
  const tableEl = document.getElementById('resources-table');
  if (!tableEl) return;
  const query = document.getElementById('all-resources-search').value.trim().toLowerCase();
  const filtered = resources.filter(resource =>
    matchesSearch(resource.Name, query) || resource.Borrowers.some(borrower =>
      matchesSearch(borrower.Name, query) || matchesSearch(borrower.PlotLabel, query)));

  const rows = filtered.map(resource => `
    <tr>
      <td>${escapeHtml(resource.Name)}</td>
      <td>${escapeHtml(String(resource.TotalQty))}</td>
      <td>${escapeHtml(String(resource.AvailableQty))}</td>
      <td><div class="borrower-assignments">${resource.Borrowers.length ? resource.Borrowers.map(borrower => `
        <div class="borrower-assignment">
          <div><strong>${escapeHtml(borrower.Name)}</strong> <span class="text-muted">· ${escapeHtml(String(borrower.Qty))}x · ${escapeHtml(borrower.PlotLabel || 'No plot assigned')}</span>
            ${borrower.Status === 'Return Requested' ? '<span class="badge badge-brown">Return requested</span>' : ''}
          </div>
          ${borrower.Status === 'Approved'
            ? `<button class="btn btn-ghost btn-sm request-return-btn" type="button" data-id="${borrower.TxnID}">Request return</button>`
            : ''}
        </div>
      `).join('') : '<span class="text-muted">No current borrowers</span>'}</div></td>
    </tr>
  `).join('');
  tableEl.innerHTML = rows || '<tr><td colspan="4" class="text-muted">No resources match your search.</td></tr>';
  tableEl.querySelectorAll('.request-return-btn').forEach(button => {
    button.addEventListener('click', () => requestResourceReturn(button.dataset.id));
  });
}

function renderResourceRecords(records) {
  const listEl = document.getElementById('resource-records-list');
  if (!listEl) return;
  listEl.innerHTML = records.length ? records.map(record => `
    <article class="record-entry ${record.Action === 'Returned' ? 'record-returned' : 'record-borrowed'}">
      <div class="record-entry-marker" aria-hidden="true"></div>
      <div class="record-entry-content">
        <div class="record-entry-head">
          <strong>${escapeHtml(record.ResourceName)}</strong>
          <span class="badge ${record.Action === 'Returned' ? 'badge-green' : 'badge-brown'}">${escapeHtml(record.Action)}</span>
        </div>
        <p>${escapeHtml(record.GardenerName)} <span class="text-muted">· ${escapeHtml(record.PlotLabel || 'No plot assigned')}</span></p>
        <time datetime="${escapeHtml(String(record.OccurredAt).replace(' ', 'T'))}">${escapeHtml(formatRecordDate(record.OccurredAt))}</time>
      </div>
    </article>
  `).join('') : '<p class="text-muted">No inventory transactions have been recorded.</p>';
}

function formatRecordDate(value) {
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}

function renderCoordinatorOverview() {
  const statsEl = document.getElementById('coordinator-stats');
  if (!statsEl) return;
  const availablePlots = plots.filter(plot => String(plot.Status || '').trim().toLowerCase() === 'available').length;
  const stats = [
    [applications.length, 'Pending plot requests'],
    [resourceTransactions.length, 'Pending resource requests'],
    [availablePlots, 'Available plots'],
    [resources.length, 'Resource types'],
  ];
  statsEl.innerHTML = stats.map(([value, label]) => `
    <div class="stat-card"><div class="stat-value">${escapeHtml(String(value))}</div><div class="stat-label">${escapeHtml(label)}</div></div>
  `).join('');
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('create-plot-form')?.addEventListener('submit', event => {
    event.preventDefault();
    createPlot(document.getElementById('new-plot-label').value.trim());
  });
  document.getElementById('applications-search-form')?.addEventListener('submit', event => {
    event.preventDefault();
    renderApplications();
  });
  document.getElementById('resource-search-form')?.addEventListener('submit', event => {
    event.preventDefault();
    renderResourceTransactions();
  });
  document.getElementById('all-resources-search-form')?.addEventListener('submit', event => {
    event.preventDefault();
    renderResources();
  });
  document.getElementById('add-resource-form')?.addEventListener('submit', event => {
    event.preventDefault();
    addResource(
      document.getElementById('resource-name').value.trim(),
      document.getElementById('resource-qty').value
    );
  });
  document.getElementById('plot-status-filter')?.addEventListener('change', event => {
    renderPlots(event.target.value);
  });
  const isDashboard = Boolean(document.getElementById('coordinator-stats'));
  if (isDashboard || document.getElementById('applications-list')) loadApplications();
  if (isDashboard || document.getElementById('resource-txns-list')) loadResourceTxns();
  if (isDashboard || document.getElementById('plot-map')) loadPlots();
  if (isDashboard || document.getElementById('resources-table')) loadResources();
  if (document.getElementById('resource-records-list')) loadResourceRecords();
});
