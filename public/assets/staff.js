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
let currentGardenerId = null;
let plots = [];
let resources = [];
let activeRecordsTimeline = 'inventory';

function matchesSearch(value, query) {
  const normalizedQuery = String(query || '').trim().toLowerCase();
  return String(value || '').toLowerCase().includes(normalizedQuery);
}

function bindLiveSearch(inputId, render) {
  const input = document.getElementById(inputId);
  if (!input) return;

  const filter = () => render();
  input.addEventListener('input', filter);
  input.addEventListener('search', filter);
  input.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      input.value = '';
      filter();
    }
  });
}

function renderApplications() {
  const assignmentListEl = document.getElementById('assignment-applications-list');
  const unassignmentListEl = document.getElementById('unassignment-applications-list');
  const assignmentEmptyEl = document.getElementById('assignment-applications-empty');
  const unassignmentEmptyEl = document.getElementById('unassignment-applications-empty');
  if (!assignmentListEl || !unassignmentListEl || !assignmentEmptyEl || !unassignmentEmptyEl) return;
  const query = document.getElementById('applications-search')?.value.trim() || '';
  const assignmentRequests = applications.filter(app => app.RequestType !== 'Unassign');
  const unassignmentRequests = applications.filter(app => app.RequestType === 'Unassign');
  const filterRequests = requests => requests.filter(app =>
    matchesSearch(app.GardenerName, query) || matchesSearch(app.Label, query));
  const renderRows = (requests, type) => requests.map(app => {
    const isSelfRequest = currentGardenerId !== null && String(app.GardenerID) === String(currentGardenerId);
    return `
      <div class="action-row">
        <div class="action-row-details">
          <div class="action-row-title">${escapeHtml(app.GardenerName)}</div>
          <div class="action-row-sub">${type === 'unassignment'
            ? `Requesting to give up plot "${escapeHtml(app.Label)}"`
            : `Requesting ${escapeHtml(app.Label)}`}
            <span class="text-muted"> • ${escapeHtml(app.PlotStatus || 'Pending')}</span>
          </div>
          <time class="action-row-time" datetime="${escapeHtml(String(app.AppliedAt || '').replace(' ', 'T'))}">Requested ${escapeHtml(formatRecordDate(app.AppliedAt))}</time>
        </div>
        <div class="action-row-actions">
          ${isSelfRequest
            ? '<span class="badge badge-neutral" title="Coordinators cannot review their own requests.">Cannot self-review</span>'
            : `<button class="btn btn-accent btn-sm approve-app" data-id="${app.AppID}">${type === 'unassignment' ? 'Approve unassignment' : 'Approve assignment'}</button>
              <button class="btn btn-ghost btn-sm reject-app" data-id="${app.AppID}">Reject</button>`}
        </div>
      </div>
    `;
  }).join('');

  const filteredAssignments = filterRequests(assignmentRequests);
  const filteredUnassignments = filterRequests(unassignmentRequests);
  assignmentListEl.innerHTML = renderRows(filteredAssignments, 'assignment');
  unassignmentListEl.innerHTML = renderRows(filteredUnassignments, 'unassignment');

  assignmentEmptyEl.textContent = assignmentRequests.length && !filteredAssignments.length
    ? 'No assignment requests match your search.'
    : 'No pending assignment requests.';
  assignmentEmptyEl.hidden = filteredAssignments.length > 0;
  unassignmentEmptyEl.textContent = unassignmentRequests.length && !filteredUnassignments.length
    ? 'No unassignment requests match your search.'
    : 'No pending unassignment requests.';
  unassignmentEmptyEl.hidden = filteredUnassignments.length > 0;

  document.querySelectorAll('#assignment-applications-list .approve-app, #unassignment-applications-list .approve-app').forEach(btn =>
    btn.addEventListener('click', () => processApplication(btn.dataset.id, 'approve')));
  document.querySelectorAll('#assignment-applications-list .reject-app, #unassignment-applications-list .reject-app').forEach(btn =>
    btn.addEventListener('click', () => processApplication(btn.dataset.id, 'reject')));
}

function renderResourceTransactions() {
  const listEl = document.getElementById('resource-txns-list');
  const emptyEl = document.getElementById('resource-txns-empty');
  if (!listEl || !emptyEl) return;
  const query = document.getElementById('resource-search').value.trim();
  const filtered = resourceTransactions.filter(txn =>
    matchesSearch(txn.GardenerName, query) || matchesSearch(txn.ResourceName, query) || matchesSearch(txn.RequestNotes, query));

  listEl.innerHTML = filtered.map(txn => {
    const isSelfRequest = currentGardenerId !== null && String(txn.GardenerID) === String(currentGardenerId);
    return `
    <div class="action-row">
      <div>
        <div class="action-row-title">${escapeHtml(txn.GardenerName)}</div>
        <div class="action-row-sub">${txn.RequestType === 'Donation' ? 'Donation · ' : ''}${escapeHtml(String(txn.Qty))}x ${escapeHtml(txn.ResourceName)}</div>
        ${txn.RequestType === 'Donation' && txn.RequestNotes ? `<div class="action-row-sub donation-request-notes">Notes: ${escapeHtml(txn.RequestNotes)}</div>` : ''}
      </div>
      <div class="action-row-actions">
        ${isSelfRequest
          ? '<span class="badge badge-neutral" title="Coordinators cannot review their own requests.">Cannot self-review</span>'
          : `<label class="sr-only" for="approve-qty-${txn.TxnID}">Quantity to ${txn.RequestType === 'Donation' ? 'accept' : 'approve'} or reject</label>
            <input type="number" class="qty-choice-input" id="approve-qty-${txn.TxnID}" min="1" max="${txn.Qty}" value="${txn.Qty}" title="Quantity for this decision">
            <button class="btn btn-accent btn-sm approve-txn" data-id="${txn.TxnID}">${txn.RequestType === 'Donation' ? 'Accept donation' : 'Approve'}</button>
            <button class="btn btn-ghost btn-sm reject-txn" data-id="${txn.TxnID}">${txn.RequestType === 'Donation' ? 'Reject donation' : 'Reject'}</button>`}
      </div>
    </div>
  `;
  }).join('');
  emptyEl.textContent = resourceTransactions.length && !filtered.length
    ? 'No resource requests match your search.'
    : 'No pending resource requests.';
  emptyEl.hidden = filtered.length > 0;

  listEl.querySelectorAll('.approve-txn').forEach(btn =>
    btn.addEventListener('click', () => {
      const input = document.getElementById(`approve-qty-${btn.dataset.id}`);
      processResourceTxn(btn.dataset.id, 'approve', input ? input.value : undefined);
    }));
  listEl.querySelectorAll('.reject-txn').forEach(btn =>
    btn.addEventListener('click', () => {
      const input = document.getElementById(`approve-qty-${btn.dataset.id}`);
      processResourceTxn(btn.dataset.id, 'reject', input ? input.value : undefined);
    }));
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
  currentGardenerId = data.current_gardener_id === null || data.current_gardener_id === undefined
    ? null
    : String(data.current_gardener_id);
  renderApplications();
  renderCoordinatorOverview();
}

async function processApplication(appId, decision) {
  let reason = '';
  if (decision === 'reject') {
    reason = await hhPrompt({ title: 'Decline this plot request?', message: 'The gardener will see this reason.', label: 'Reason for declining', placeholder: 'e.g., This plot is reserved for the school program.', confirmText: 'Decline request', tone: 'danger' });
    if (!reason) return;
  }
  const data = await postAction('process_application', { app_id: appId, decision, reason });
  if (data.ok) {
    const resultMessage = decision === 'approve' && data.auto_rejected
      ? `Request accepted. ${data.auto_rejected} competing request${data.auto_rejected === 1 ? '' : 's'} rejected.`
      : `Request ${decision === 'approve' ? 'accepted' : 'rejected'}.`;
    showToast(resultMessage, 'success');
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
  currentGardenerId = data.current_gardener_id === null || data.current_gardener_id === undefined
    ? null
    : String(data.current_gardener_id);
  renderResourceTransactions();
  renderCoordinatorOverview();
}

async function processResourceTxn(txnId, decision, qty) {
  const params = { txn_id: txnId, decision };
  const request = resourceTransactions.find(txn => String(txn.TxnID) === String(txnId));
  if (qty !== undefined && (!/^\d+$/.test(String(qty)) || Number(qty) < 1 || Number(qty) > Number(request?.Qty))) {
    showToast(request
      ? `Choose a quantity between 1 and ${request.Qty}.`
      : 'Enter a valid quantity.', 'danger');
    return;
  }
  const chosenQty = Number(qty === undefined ? request?.Qty : qty);
  if (decision === 'approve' && request && chosenQty < Number(request.Qty)) {
    params.reason = await hhPrompt({
      title: 'Reason for partial approval',
      message: `You are approving ${chosenQty} of ${request.Qty} requested units. The remaining ${Number(request.Qty) - chosenQty} units will be marked rejected.`,
      label: 'Reason for Partial Approval',
      placeholder: 'Explain why the full quantity cannot be approved.',
      confirmText: 'Approve quantity',
      tone: 'default',
    });
    if (!params.reason) return;
  }
  if (decision === 'reject') {
    const donation = request?.RequestType === 'Donation';
    params.reason = await hhPrompt({ title: donation ? 'Reject this donation?' : 'Decline this resource request?', message: 'The gardener will see this reason.', label: donation ? 'Reason for rejecting donation' : 'Reason for declining', placeholder: donation ? 'Explain why this donation cannot be accepted.' : 'e.g., Not enough stock this week.', confirmText: donation ? 'Reject donation' : 'Decline request', tone: 'danger' });
    if (!params.reason) return;
  }
  if (qty !== undefined) params.qty = qty;
  const data = await postAction('process_resource_txn', params);
  if (data.ok) {
    const donation = request?.RequestType === 'Donation';
    showToast(decision === 'approve' && request && chosenQty < Number(request.Qty)
      ? `${donation ? 'Donation' : 'Request'} partially approved. The remaining ${Number(request.Qty) - chosenQty} units were rejected.`
      : donation
        ? `Donation ${decision === 'approve' ? 'accepted' : 'rejected'}.`
        : `Request ${decision === 'approve' ? 'approved' : 'rejected'}.`, 'success');
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
  if (!await hhConfirm({ title: `Delete plot ${label}?`, message: 'This removes the plot from the garden map. This cannot be undone.', confirmText: 'Delete plot', tone: 'danger' })) return;
  const data = await postAction('delete_plot', { plot_id: plotId });
  if (data.ok) {
    showToast('Plot deleted.', 'success');
    loadPlots();
  } else {
    showToast(data.error || 'Could not delete plot.', 'danger');
  }
}

async function loadResources() {
  try {
    const res = await fetch('api.php?action=all_resources');
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'Could not load resource inventory.');

    resources = data.resources;
    renderResources();
    renderCoordinatorOverview();
    return true;
  } catch (error) {
    const tableEl = document.getElementById('resources-table');
    if (tableEl) tableEl.innerHTML = '<tr><td colspan="5" class="text-muted">Could not load resource inventory.</td></tr>';
    return false;
  }
}

async function loadResourceRecords(dateValue) {
  const listEl = document.getElementById('resource-records-list');
  if (!listEl) return;
  const dateFilter = document.getElementById('records-date-filter-input');
  const selectedDate = dateValue || dateFilter?.value;
  if (!selectedDate) return;
  const params = new URLSearchParams({ action: 'resource_records', date: selectedDate });
  const res = await fetch(`api.php?${params.toString()}`);
  const data = await res.json();
  if (!data.ok) {
    listEl.innerHTML = '<p class="text-muted">Could not load inventory records.</p>';
    return;
  }
  renderTimelineRecords('resource-records-list', data.records, renderResourceRecord, 'No inventory events have been recorded for this date.');
}

async function loadPlotRecords(dateValue) {
  const listEl = document.getElementById('plot-records-list');
  const dateFilter = document.getElementById('records-date-filter-input');
  const selectedDate = dateValue || dateFilter?.value;
  if (!listEl || !selectedDate) return;
  const params = new URLSearchParams({ action: 'plot_records', date: selectedDate });
  const res = await fetch(`api.php?${params.toString()}`);
  const data = await res.json();
  if (!data.ok) {
    listEl.innerHTML = '<p class="text-muted">Could not load plot records.</p>';
    return;
  }
  renderTimelineRecords('plot-records-list', data.records, renderPlotRecord, 'No plot events have been recorded for this date.');
}

function loadActiveRecords(dateValue) {
  if (activeRecordsTimeline === 'plots') {
    loadPlotRecords(dateValue);
  } else {
    loadResourceRecords(dateValue);
  }
}

function switchRecordsTimeline(type) {
  const inventoryTab = document.getElementById('records-inventory-tab');
  const plotsTab = document.getElementById('records-plots-tab');
  const inventoryPanel = document.getElementById('resource-records-panel');
  const plotsPanel = document.getElementById('plot-records-panel');
  const heading = document.getElementById('records-heading');
  const dateFilter = document.querySelector('.records-date-filter');
  if (!inventoryTab || !plotsTab || !inventoryPanel || !plotsPanel) return;

  activeRecordsTimeline = type;
  const showInventory = type === 'inventory';
  inventoryTab.classList.toggle('is-active', showInventory);
  inventoryTab.setAttribute('aria-selected', String(showInventory));
  plotsTab.classList.toggle('is-active', !showInventory);
  plotsTab.setAttribute('aria-selected', String(!showInventory));
  inventoryPanel.hidden = !showInventory;
  plotsPanel.hidden = showInventory;
  if (heading) heading.textContent = showInventory ? 'Inventory Timeline' : 'Plots Timeline';
  dateFilter?.setAttribute('aria-label', `Filter ${showInventory ? 'inventory' : 'plot'} records by date`);
  loadActiveRecords();
}

function shiftResourceRecordDate(dayOffset) {
  const dateFilter = document.getElementById('records-date-filter-input');
  if (!dateFilter?.value) return;
  const selectedDateParts = dateFilter.value.split('-').map(Number);
  const selectedDate = new Date(selectedDateParts[0], selectedDateParts[1] - 1, selectedDateParts[2], 12);
  selectedDate.setDate(selectedDate.getDate() + dayOffset);
  dateFilter.value = formatRecordInputDate(selectedDate);
  loadActiveRecords(dateFilter.value);
}

function formatRecordInputDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

async function addResource(name, qty) {
  const data = await postAction('add_resource', { name, qty });
  if (!data.ok) {
    showToast(data.error || 'Could not add item.', 'danger');
    return;
  }
  document.getElementById('resource-name').value = '';
  document.getElementById('resource-qty').value = '1';
  const loaded = await loadResources();
  if (loaded) {
    showToast('Item added to inventory.', 'success');
  } else {
    showToast('Item was added, but the inventory could not be refreshed.', 'danger');
  }
}

async function requestResourceReturn(txnId, qty, resourceName, gardenerName) {
  const reason = await hhPrompt({
    title: 'Reason for return request',
    message: `Explain why ${gardenerName} needs to return ${qty || 'all'} ${resourceName}.`,
    label: 'Reason for Return Request',
    placeholder: 'Enter the reason for requesting this return.',
    confirmText: 'Send return request',
  });
  if (!reason) return;

  const params = { txn_id: txnId };
  if (qty) params.qty = qty;
  params.reason = reason;
  const data = await postAction('request_resource_return', params);
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
  const query = document.getElementById('all-resources-search').value.trim();
  const filtered = resources.map(resource => {
    const matchingBorrowers = resource.Borrowers.filter(borrower =>
      matchesSearch(borrower.Name, query) || matchesSearch(borrower.PlotLabel, query));
    return {
      ...resource,
      matchingBorrowers,
      matchesQuery: matchesSearch(resource.Name, query) || matchingBorrowers.length > 0,
    };
  }).filter(resource => resource.matchesQuery);

  const rows = filtered.map(resource => `
    <tr class="resource-inventory-row">
      <td data-label="Resource">
        <span class="resource-name-cell" title="${escapeHtml(resource.Name)}">${escapeHtml(resource.Name)}</span>
      </td>
      <td data-label="Total">${escapeHtml(String(resource.TotalQty))}</td>
      <td data-label="Available">${escapeHtml(String(resource.AvailableQty))}</td>
      <td data-label="Borrower assignments"><div class="borrower-assignments">${resource.matchingBorrowers.length ? resource.matchingBorrowers.map(borrower => `
        <div class="borrower-assignment">
          <div class="borrower-assignment-info">
            <strong title="${escapeHtml(borrower.Name)}">${escapeHtml(borrower.Name)}</strong>
            <span class="borrower-assignment-meta" title="${escapeHtml(`${borrower.Qty}x · ${borrower.PlotLabel || 'No plot assigned'}`)}">${escapeHtml(String(borrower.Qty))}x · ${escapeHtml(borrower.PlotLabel || 'No plot assigned')}</span>
            ${borrower.Status === 'Return Requested' ? '<span class="borrower-return-status">Return requested</span>' : ''}
          </div>
          ${document.body.dataset.inventoryRole !== 'admin' && borrower.Status === 'Approved'
            ? `<div class="return-request-control">
                <label class="sr-only" for="return-qty-${borrower.TxnID}">Quantity to request back</label>
                <input type="number" class="qty-choice-input" id="return-qty-${borrower.TxnID}" min="1" max="${borrower.Qty}" value="${borrower.Qty}" title="Quantity to request back">
                <button class="btn btn-sm btn-return-request request-return-btn" type="button" data-id="${borrower.TxnID}" data-resource="${escapeHtml(resource.Name)}" data-gardener="${escapeHtml(borrower.Name)}">Request return</button>
              </div>`
            : ''}
        </div>
      `).join('') : resource.Borrowers.length ? '<span class="borrower-empty-state">No borrower assignments match this search.</span>' : '<span class="borrower-empty-state">No current borrowers</span>'}</div></td>
      <td data-label="Actions"><button class="btn btn-ghost btn-sm edit-resource-btn" type="button" data-id="${resource.ResourceID}" data-name="${escapeHtml(resource.Name)}" data-total="${resource.TotalQty}">Edit</button></td>
    </tr>
  `).join('');
  tableEl.innerHTML = rows || '<tr class="resource-empty-row"><td colspan="5" class="text-muted">No resources match your search.</td></tr>';
  tableEl.querySelectorAll('.request-return-btn').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(`return-qty-${button.dataset.id}`);
      requestResourceReturn(button.dataset.id, input ? input.value : undefined, button.dataset.resource, button.dataset.gardener);
    });
  });
  tableEl.querySelectorAll('.edit-resource-btn').forEach(button => {
    button.addEventListener('click', () => openResourceEdit(button.dataset.id, button.dataset.name, button.dataset.total));
  });
}

function openResourceEdit(resourceId, resourceName, totalQty) {
  const dialog = document.getElementById('resource-edit-dialog');
  const nameEl = document.getElementById('resource-edit-name');
  const idEl = document.getElementById('resource-edit-id');
  const qtyEl = document.getElementById('resource-edit-total');
  if (!dialog || !nameEl || !idEl || !qtyEl) return;
  nameEl.textContent = resourceName;
  idEl.value = resourceId;
  qtyEl.value = totalQty;
  qtyEl.dispatchEvent(new Event('input', { bubbles: true }));
  dialog.showModal();
  qtyEl.focus();
  qtyEl.select();
}

async function updateResourceTotal(resourceId, totalQty) {
  const res = await postAction('update_resource_total', { resource_id: resourceId, total_qty: totalQty });
  if (!res.ok) {
    showToast(res.error || 'Could not update resource quantity.', 'danger');
    return;
  }
  document.getElementById('resource-edit-dialog')?.close();
  const loaded = await loadResources();
  showToast(loaded ? 'Resource quantity updated.' : 'Quantity updated, but inventory could not be refreshed.', loaded ? 'success' : 'danger');
}

function renderTimelineRecords(listId, records, renderRecord, emptyMessage) {
  const listEl = document.getElementById(listId);
  if (!listEl) return;
  const groups = new Map();
  records.forEach(record => {
    const date = parseRecordDate(record.OccurredAt);
    const key = Number.isNaN(date.getTime())
      ? 'unknown-date'
      : `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
    if (!groups.has(key)) {
      groups.set(key, {
        label: Number.isNaN(date.getTime()) ? 'Date unavailable' : formatRecordGroupDate(date),
        records: [],
      });
    }
    groups.get(key).records.push(record);
  });

  listEl.innerHTML = groups.size ? Array.from(groups.values()).map(group => `
    <section class="record-day-group">
      <h3 class="record-day-heading">${escapeHtml(group.label)}</h3>
      <div class="record-day-events">${group.records.map(renderRecord).join('')}</div>
    </section>
  `).join('') : `<p class="text-muted">${escapeHtml(emptyMessage)}</p>`;
}

function renderResourceRecord(record) {
  const quantity = `${escapeHtml(String(record.Qty))}x`;
  const plot = escapeHtml(record.PlotLabel || 'No plot assigned');
  let details;
  let entryClass = 'record-borrowed';
  let badgeClass = 'badge-brown';

  if (record.Action === 'Added') {
    details = `${quantity} added by ${escapeHtml(record.ActorName)}`;
    entryClass = 'record-added';
  } else if (record.Action === 'Borrowed') {
    details = `${quantity} borrowed by ${escapeHtml(record.GardenerName)} · ${plot}`;
  } else if (record.Action === 'Returned') {
    details = `${quantity} returned by ${escapeHtml(record.ActorName)} · ${plot}`;
    entryClass = 'record-returned';
    badgeClass = 'badge-green';
  } else {
    details = `${quantity} return requested by ${escapeHtml(record.ActorName)} for ${escapeHtml(record.GardenerName)} · ${plot}`;
    entryClass = 'record-return-requested';
  }

  return `
    <article class="record-entry ${entryClass}">
      <time class="record-entry-time" datetime="${escapeHtml(String(record.OccurredAt).replace(' ', 'T'))}">${escapeHtml(formatRecordTime(record.OccurredAt))}</time>
      <div class="record-entry-marker" aria-hidden="true"></div>
      <div class="record-entry-content">
        <div class="record-entry-head">
          <strong>${escapeHtml(record.ResourceName)}</strong>
          <span class="badge ${badgeClass}">${escapeHtml(record.Action)}</span>
        </div>
        <p>${details}</p>
      </div>
    </article>
  `;
}

function renderPlotRecord(record) {
  const plotLabel = escapeHtml(record.PlotLabel);
  const gardenerName = escapeHtml(record.GardenerName || 'A gardener');
  const actorName = escapeHtml(record.ActorName || 'A coordinator');
  let details;
  let badgeLabel;
  let badgeClass;
  let entryClass;

  switch (record.Action) {
    case 'Plot Added':
      details = `Added by ${actorName}`;
      badgeLabel = 'Added';
      badgeClass = 'badge-green';
      entryClass = 'record-plot-added';
      break;
    case 'Request Assignment':
      details = `${gardenerName} requested assignment to ${plotLabel}`;
      badgeLabel = 'Pending';
      badgeClass = 'badge-brown';
      entryClass = 'record-plot-pending';
      break;
    case 'Request Unassign':
      details = `${gardenerName} requested unassignment of ${plotLabel}`;
      badgeLabel = 'Pending';
      badgeClass = 'badge-brown';
      entryClass = 'record-plot-pending';
      break;
    case 'Request Accepted':
      details = `${actorName} accepted ${gardenerName}'s request for ${plotLabel}`;
      badgeLabel = 'Accepted';
      badgeClass = 'badge-green';
      entryClass = 'record-plot-accepted';
      break;
    case 'Plot Unassigned':
      details = `${actorName} unassigned ${gardenerName} from ${plotLabel}`;
      badgeLabel = 'Unassigned';
      badgeClass = 'badge-neutral';
      entryClass = 'record-plot-unassigned';
      break;
    default:
      details = `${actorName} rejected ${gardenerName}'s request for ${plotLabel}`;
      badgeLabel = 'Rejected';
      badgeClass = 'badge-neutral';
      entryClass = 'record-plot-rejected';
  }

  return `
    <article class="record-entry ${entryClass}">
      <time class="record-entry-time" datetime="${escapeHtml(String(record.OccurredAt).replace(' ', 'T'))}">${escapeHtml(formatRecordTime(record.OccurredAt))}</time>
      <div class="record-entry-marker" aria-hidden="true"></div>
      <div class="record-entry-content">
        <div class="record-entry-head">
          <strong>${plotLabel}</strong>
          <span class="badge ${badgeClass}">${badgeLabel}</span>
        </div>
        <p>${details}</p>
      </div>
    </article>
  `;
}

function parseRecordDate(value) {
  return new Date(String(value).replace(' ', 'T'));
}

function formatRecordGroupDate(date) {
  return date.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

function formatRecordTime(value) {
  const date = parseRecordDate(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
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
  [
    ['applications-search-form', 'applications-search', renderApplications],
    ['resource-search-form', 'resource-search', renderResourceTransactions],
    ['all-resources-search-form', 'all-resources-search', renderResources],
  ].forEach(([formId, inputId, render]) => {
    const form = document.getElementById(formId);
    form?.addEventListener('submit', event => {
      event.preventDefault();
      render();
    });
    bindLiveSearch(inputId, render);
  });
  document.getElementById('add-resource-form')?.addEventListener('submit', event => {
    event.preventDefault();
    addResource(
      document.getElementById('resource-name').value.trim(),
      document.getElementById('resource-qty').value
    );
  });
  document.getElementById('resource-edit-form')?.addEventListener('submit', event => {
    event.preventDefault();
    updateResourceTotal(
      document.getElementById('resource-edit-id').value,
      document.getElementById('resource-edit-total').value
    );
  });
  document.getElementById('resource-edit-cancel')?.addEventListener('click', () => {
    document.getElementById('resource-edit-dialog')?.close();
  });
  document.getElementById('plot-status-filter')?.addEventListener('change', event => {
    renderPlots(event.target.value);
  });
  const recordsDateFilter = document.getElementById('records-date-filter-input');
  if (recordsDateFilter) {
    recordsDateFilter.value = formatRecordInputDate(new Date());
    recordsDateFilter.addEventListener('change', event => {
      if (event.target.value) loadActiveRecords(event.target.value);
    });
  }
  document.getElementById('records-previous-day')?.addEventListener('click', () => shiftResourceRecordDate(-1));
  document.getElementById('records-next-day')?.addEventListener('click', () => shiftResourceRecordDate(1));
  document.getElementById('records-inventory-tab')?.addEventListener('click', () => switchRecordsTimeline('inventory'));
  document.getElementById('records-plots-tab')?.addEventListener('click', () => switchRecordsTimeline('plots'));
  const isDashboard = Boolean(document.getElementById('coordinator-stats'));
  if (document.getElementById('applications-list')) loadApplications();
  if (isDashboard || document.getElementById('resource-txns-list')) loadResourceTxns();
  if (isDashboard || document.getElementById('plot-map')) loadPlots();
  if (isDashboard || document.getElementById('resources-table')) loadResources();
  if (document.getElementById('resource-records-list')) loadResourceRecords();
});
