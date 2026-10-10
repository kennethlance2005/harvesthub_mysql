function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// Whether the logged-in person's roles allow something (window.hhCan comes from
// the sidebar). This only hides buttons; the server checks every action too.
function adminCan(permission) {
  return !permission || (typeof window.hhCan === 'function' && window.hhCan(permission));
}

// Account-table buttons and the permission each one needs
const ACCOUNT_BUTTON_PERMISSIONS = {
  '.account-roles-btn': 'roles.manage',
  '.delete-btn': 'accounts.archive',
  '.unarchive-btn': 'accounts.archive',
  '.enable-account-btn': 'accounts.enable',
  '.demote-coordinator-btn': 'accounts.demote',
};

// Removes the buttons this person's roles don't allow, leaving a note when a row has none.
function removeDisallowedButtons(root = document) {
  Object.entries(ACCOUNT_BUTTON_PERMISSIONS).forEach(([selector, permission]) => {
    if (!adminCan(permission)) root.querySelectorAll(selector).forEach(btn => btn.remove());
  });
  root.querySelectorAll('.account-action-buttons').forEach(group => {
    if (!group.querySelector('button')) group.innerHTML = '<span class="text-muted">View only</span>';
  });
}

function showToast(message, type = 'success') {
  const toastEl = document.createElement('div');
  toastEl.className = `toast${type === 'danger' ? ' toast-danger' : ''}`;
  toastEl.textContent = message;
  document.getElementById('toast-container').appendChild(toastEl);
  setTimeout(() => toastEl.remove(), 3500);
}

function filterTableByName(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  const query = input.value.trim().toLowerCase();
  // Optional status dropdown next to the search box (account tables only)
  const statusSelect = document.querySelector(`[data-status-filter="${tableId}"]`);
  const status = statusSelect ? statusSelect.value : '';

  let visibleRows = 0;
  table.querySelectorAll('tr[data-name]').forEach(row => {
    // Name is stored in the data attribute
    const name = row.dataset.name ? row.dataset.name.toLowerCase() : '';

    // Email is uniformly located in the second column (td:nth-child(2)) across all our tables
    const emailCell = row.querySelector('td:nth-child(2)');
    const email = emailCell ? emailCell.textContent.toLowerCase() : '';
    const location = row.dataset.location ? row.dataset.location.toLowerCase() : '';

    // Hide row if the query is not empty AND it matches neither Name nor Email
    const matchesQuery = query === '' || name.includes(query) || email.includes(query) || location.includes(query);
    const matchesStatus = status === '' || row.dataset.status === status;
    row.hidden = !(matchesQuery && matchesStatus);
    if (!row.hidden) visibleRows++;
  });

  // Tell the admin when a search/filter hides every row
  table.querySelector('tr.admin-filter-empty')?.remove();
  const hasRows = table.querySelector('tr[data-name]') !== null;
  if (hasRows && visibleRows === 0) {
    const columns = table.closest('table')?.querySelectorAll('thead th').length || 1;
    const message = query === ''
      ? `No ${status.toLowerCase()} accounts.`
      : `No ${status ? status.toLowerCase() + ' ' : ''}accounts match "${escapeHtml(input.value.trim())}".`;
    table.insertAdjacentHTML('beforeend', `<tr class="admin-filter-empty"><td colspan="${columns}" class="text-muted">${message}</td></tr>`);
  }
}

// Status pill for account tables: Active, or Disabled (locked after failed logins)
function accountStatusBadge(status) {
  if (status === 'Disabled') {
    return '<span class="badge badge-danger" title="Locked after 3 failed login attempts. Use Enable Account to unlock it.">Disabled</span>';
  }
  return '<span class="badge badge-green">Active</span>';
}

// Re-apply each account table's search box and status filter after a reload
function reapplyAccountFilters() {
  document.querySelectorAll('[data-table-search]').forEach(input => {
    filterTableByName(input.id, input.dataset.tableSearch);
  });
}

async function loadStats() {
  const statsRow = document.getElementById('stats-row');
  if (!statsRow) return; 

  const res = await fetch('api.php?action=stats');
  const data = await res.json();
  if (!data.ok) return;

  const cards = [
    ['Administrators', data.stats.admins],
    ['Gardeners', data.stats.gardeners],
    ['Coordinators', data.stats.coordinators],
    ['Plots Occupied', data.stats.plots_occupied],
    ['Plots Available', data.stats.plots_available],
    ['Pending Applications', data.stats.pending_applications],
    ['Pending Resource Requests', data.stats.pending_resource_txns],
    ['Pending Signups', data.stats.pending_signups || 0],
    ['Active Listings', data.stats.active_listings],
    ['Completed Trades', data.stats.completed_trades],
  ];

  statsRow.innerHTML = cards.map(([label, value]) => `
    <div class="stat-card">
      <div class="stat-value">${value}</div>
      <div class="stat-label">${label}</div>
    </div>
  `).join('');
}

async function loadAccounts() {
  const res = await fetch('api.php?action=accounts');
  const data = await res.json();
  if (!data.ok) return;

  // Render Gardeners if table exists
  const gardenersTable = document.getElementById('gardeners-table');
  if (gardenersTable) {
    gardenersTable.innerHTML = data.gardeners.map(g => `
      <tr data-name="${escapeHtml(g.Name)}" data-location="${escapeHtml(g.Location || '')}" data-status="${escapeHtml(g.Status)}">
        <td data-label="Name">${escapeHtml(g.Name)}${customRoleBadges(g.custom_roles)}</td>
        <td data-label="Email">${escapeHtml(g.Email)}</td>
        <td data-label="Location">${escapeHtml(g.Location || 'Not provided')}</td>
        <td data-label="Status">${accountStatusBadge(g.Status)}</td>
        <td data-label="Actions">
          <div class="account-action-buttons"><button type="button" class="btn btn-ghost btn-sm account-roles-btn" data-type="gardener" data-id="${g.id}" data-name="${escapeHtml(g.Name)}">Roles</button>
          <button type="button" class="btn btn-ghost btn-sm delete-btn" data-table="gardener" data-id="${g.id}" data-name="${escapeHtml(g.Name)}">Archive</button>
          <button type="button" class="btn btn-accent btn-sm enable-account-btn" data-table="gardener" data-id="${g.id}" data-name="${escapeHtml(g.Name)}" ${g.Status !== 'Disabled' ? 'disabled' : ''}>Enable Account</button></div>
        </td>
      </tr>
    `).join('') || '<tr class="admin-empty-row"><td colspan="5" class="text-muted">No gardeners yet.</td></tr>';
  }

  // Render Coordinators if table exists
  const coordsTable = document.getElementById('coordinators-table');
  if (coordsTable) {
    coordsTable.innerHTML = data.coordinators.map(c => `
      <tr data-name="${escapeHtml(c.Name)}" data-location="${escapeHtml(c.Location || '')}" data-status="${escapeHtml(c.Status)}">
        <td data-label="Name">${escapeHtml(c.Name)}${c.GardenerID ? ' <span class="badge badge-neutral admin-you-badge" title="This person also has a gardener account, which they keep if the coordinator role is removed.">Also a gardener</span>' : ''}${customRoleBadges(c.custom_roles)}</td>
        <td data-label="Email">${escapeHtml(c.Email)}</td>
        <td data-label="Shift">${escapeHtml(c.Shift)}</td>
        <td data-label="Location">${escapeHtml(c.Location || 'Not provided')}</td>
        <td data-label="Status">${accountStatusBadge(c.Status)}</td>
        <td data-label="Actions">
          <div class="account-action-buttons"><button type="button" class="btn btn-ghost btn-sm account-roles-btn" data-type="coordinator" data-id="${c.id}" data-name="${escapeHtml(c.Name)}">Roles</button>
          <button type="button" class="btn btn-ghost btn-sm demote-coordinator-btn" data-id="${c.id}" data-name="${escapeHtml(c.Name)}">Remove role</button>
          <button type="button" class="btn btn-ghost btn-sm delete-btn" data-table="coordinator" data-id="${c.id}" data-name="${escapeHtml(c.Name)}">Archive</button>
          <button type="button" class="btn btn-accent btn-sm enable-account-btn" data-table="coordinator" data-id="${c.id}" data-name="${escapeHtml(c.Name)}" ${c.Status !== 'Disabled' ? 'disabled' : ''}>Enable Account</button></div>
        </td>
      </tr>
    `).join('') || '<tr class="admin-empty-row"><td colspan="6" class="text-muted">No coordinators yet.</td></tr>';
  }

  // Render Admins if table exists
  const adminsTable = document.getElementById('admins-table');
  if (adminsTable) {
    adminsTable.innerHTML = data.admins.map(a => `
      <tr data-name="${escapeHtml(a.Name)}" data-location="${escapeHtml(a.Location || '')}" data-status="${escapeHtml(a.Status)}">
        <td data-label="Name">${escapeHtml(a.Name)}${a.id === data.current_user_id ? ' <span class="badge badge-neutral admin-you-badge">You</span>' : ''}</td>
        <td data-label="Email">${escapeHtml(a.Email)}</td>
        <td data-label="Location">${escapeHtml(a.Location || 'Not provided')}</td>
        <td data-label="Status">${accountStatusBadge(a.Status)}</td>
        <td data-label="Actions">
          <div class="account-action-buttons"><button type="button" class="btn btn-ghost btn-sm delete-btn" data-table="admin" data-id="${a.id}" data-name="${escapeHtml(a.Name)}" ${a.id === data.current_user_id ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>Archive</button>
          <button type="button" class="btn btn-accent btn-sm enable-account-btn" data-table="admin" data-id="${a.id}" data-name="${escapeHtml(a.Name)}" ${a.Status !== 'Disabled' || a.id === data.current_user_id ? 'disabled' : ''}>Enable Account</button></div>
        </td>
      </tr>
    `).join('') || '<tr class="admin-empty-row"><td colspan="5" class="text-muted">No administrators yet.</td></tr>';
  }

  document.querySelectorAll('.account-roles-btn').forEach(btn => {
    btn.onclick = () => manageAccountRoles(btn.dataset.type, btn.dataset.id, btn.dataset.name);
  });

  document.querySelectorAll('.demote-coordinator-btn').forEach(btn => {
    btn.onclick = () => removeCoordinatorRole(btn.dataset.id, btn);
  });

  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.onclick = () => {
      openDeleteModal(btn.dataset.table, btn.dataset.id, btn.dataset.name);
    };
  });

  document.querySelectorAll('.enable-account-btn').forEach(btn => {
    btn.onclick = async () => {
      const confirmed = await hhConfirm({
        title: `Enable ${btn.dataset.name}'s account?`,
        message: 'This unlocks the account and resets its failed login attempts, so they can log in again.',
        confirmText: 'Enable account',
      });
      if (!confirmed) return;
      btn.disabled = true;
      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'enable_account', table: btn.dataset.table, id: btn.dataset.id })
      });
      const result = await res.json();
      if (result.ok) {
        showToast('Account enabled and login attempts reset.', 'success');
        loadAccounts();
      } else {
        showToast(result.error || 'Could not enable account.', 'danger');
        btn.disabled = false;
      }
    };
  });

  removeDisallowedButtons();
  reapplyAccountFilters();
}

// ---------- Custom roles on an account ----------

// Small badges after a name for each extra role, e.g. "Inventory Clerk"
function customRoleBadges(names) {
  return (names || []).map(name => ` <span class="badge badge-brown account-role-badge">${escapeHtml(name)}</span>`).join('');
}

async function manageAccountRoles(type, id, name) {
  const res = await fetch(`api.php?${new URLSearchParams({ action: 'account_roles', account_type: type, account_id: id })}`);
  const data = await res.json();
  if (!data.ok) {
    showToast(data.error || 'Could not load roles.', 'danger');
    return;
  }
  const builtIn = `<p class="review-muted">From their account: <strong>${data.built_in.map(escapeHtml).join(' and ')}</strong>. These come with the account and are changed elsewhere (for example, Remove role on a coordinator).</p>`;
  if (!data.custom_roles.length) {
    await hhDetails({
      title: `${name}'s roles`,
      bodyHtml: `${builtIn}<p class="review-muted">There are no extra roles to give yet. Create one on the <a href="admin_roles.php">Roles and Permissions</a> page, for example "Inventory Clerk".</p>`,
    });
    return;
  }
  const held = new Set(data.custom_roles.filter(r => r.held).map(r => r.id));
  const result = await hhForm({
    title: `${name}'s roles`,
    bodyHtml: `${builtIn}
      <fieldset class="role-form-group">
        <legend>Extra roles</legend>
        ${data.custom_roles.map(r => `
          <label class="role-form-check">
            <input type="checkbox" name="role_ids" value="${r.id}" ${r.held ? 'checked' : ''}>
            <span><strong>${escapeHtml(r.name)}</strong><small>${escapeHtml(r.description || 'No description.')}</small></span>
          </label>`).join('')}
      </fieldset>
      <div class="field">
        <label for="account-roles-reason">Reason (optional)</label>
        <textarea id="account-roles-reason" name="reason" rows="2" maxlength="1000" placeholder="Why are their roles changing?"></textarea>
      </div>
      <p class="review-muted">Changes apply straight away and are saved in their role history and the audit log.</p>`,
    confirmText: 'Save roles',
    // Only allow saving once a box was actually changed
    isValid: form => {
      const ticked = [...form.querySelectorAll('input[name="role_ids"]:checked')].map(i => Number(i.value));
      return ticked.length !== held.size || ticked.some(roleId => !held.has(roleId));
    },
    collect: form => ({
      roleIds: [...form.querySelectorAll('input[name="role_ids"]:checked')].map(i => i.value),
      reason: form.elements.reason.value.trim(),
    }),
  });
  if (!result) return;

  const params = new URLSearchParams({ action: 'account_roles_save', account_type: type, account_id: id, reason: result.reason });
  result.roleIds.forEach(roleId => params.append('role_ids[]', roleId));
  const saveRes = await fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params });
  const saved = await saveRes.json();
  if (!saved.ok) {
    showToast(saved.error || 'Could not save roles.', 'danger');
    return;
  }
  showToast(`${name}'s roles updated.`);
  loadAccounts();
}

// ---------- Remove a coordinator role (demote) ----------

async function removeCoordinatorRole(coordId, button) {
  button.disabled = true;
  const label = button.textContent;
  button.textContent = 'Loading…';
  let preview;
  try {
    preview = await (await fetch(`api.php?action=coordinator_demotion_preview&coord_id=${encodeURIComponent(coordId)}`)).json();
  } catch (error) {
    preview = { ok: false };
  }
  button.disabled = false;
  button.textContent = label;
  if (!preview.ok) {
    showToast(preview.error || 'Could not load this coordinator.', 'danger');
    return;
  }

  const c = preview.coordinator;
  const name = escapeHtml(c.name);
  const queued = preview.queue.plot_requests + preview.queue.resource_requests;
  const warnings = [];
  if (preview.other_active_coordinators === 0) {
    warnings.push(`<p class="review-alert review-alert-danger">${name} is the <strong>only active coordinator</strong>. Until another coordinator is approved, nobody will be able to review plot and resource requests${queued ? ` (${queued} waiting right now)` : ''}.</p>`);
  }
  if (preview.open_return_requests > 0) {
    warnings.push(`<p class="review-alert review-alert-warn">${name} asked for ${preview.open_return_requests} ${preview.open_return_requests === 1 ? 'item or plot' : 'items or plots'} to be returned. Those requests stay open so another coordinator can follow them up.</p>`);
  }

  const effect = c.has_gardener_account
    ? `<p class="hh-dialog-message"><strong>${name} stays a gardener</strong>: their plots, crops, borrowed items and exchange listings don't change. They lose coordinator tools straight away, even if they are logged in right now.</p>`
    : `<p class="hh-dialog-message">${name} only has a coordinator account, with no gardener account. Choose what happens to it:</p>
       <fieldset class="demote-outcome">
         <legend class="sr-only">What happens to the account</legend>
         <label class="demote-choice"><input type="radio" name="outcome" value="gardener">
           <span><strong>Convert to a gardener account</strong><small>They keep logging in with the same email and password, as a gardener.</small></span></label>
         <label class="demote-choice"><input type="radio" name="outcome" value="archive">
           <span><strong>Archive the account</strong><small>They can no longer log in. You can restore it later from Archived Accounts.</small></span></label>
       </fieldset>`;

  const result = await hhForm({
    title: `Remove ${c.name}'s coordinator role?`,
    tone: 'danger',
    confirmText: 'Remove coordinator role',
    bodyHtml: `
      ${effect}
      ${warnings.join('')}
      <div class="field">
        <label for="demote-reason">Reason <span class="required">*</span></label>
        <select id="demote-reason" name="reason" aria-required="true">
          <option value="">Choose a reason</option>
          ${preview.reasons.map(r => `<option value="${escapeHtml(r)}">${escapeHtml(r)}</option>`).join('')}
        </select>
      </div>
      <div class="field">
        <label for="demote-details">Details <span class="required">*</span></label>
        <textarea id="demote-details" name="details" rows="3" maxlength="1000" aria-required="true" placeholder="What happened? ${name} will see this on their dashboard."></textarea>
      </div>
      <p class="review-muted">They can apply to be a coordinator again after ${preview.reapply_wait_days} days. This is saved in their role history and the audit log.</p>`,
    isValid: form => form.reason.value !== ''
      && form.details.value.trim() !== ''
      && (c.has_gardener_account || form.querySelector('input[name="outcome"]:checked') !== null),
    collect: form => ({
      reason: form.reason.value,
      details: form.details.value.trim(),
      outcome: form.querySelector('input[name="outcome"]:checked')?.value || '',
    }),
  });
  if (!result) return;

  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'demote_coordinator', coord_id: coordId, ...result }),
  });
  const data = await res.json();
  if (data.ok) {
    showToast(`${c.name} is no longer a coordinator. ${data.outcome}`, 'success');
    loadAccounts();
    loadStats();
  } else {
    showToast(data.error || 'Could not remove the coordinator role.', 'danger');
  }
}

async function loadArchivedAccounts() {
  const table = document.getElementById('archived-table');
  if (!table) return; // Only run on the archived page

  const res = await fetch('api.php?action=archived_accounts');
  const data = await res.json();
  
  if (!data.ok || data.accounts.length === 0) {
    table.innerHTML = '<tr class="archived-empty-row"><td colspan="7" class="text-muted">No archived accounts found.</td></tr>';
    return;
  }

  table.innerHTML = data.accounts.map(a => {
    const displayRole = a.Role === 'Customer' ? 'Gardener' : a.Role;
    return `
      <tr data-name="${escapeHtml(a.Name)}">
        <td data-label="Name">${escapeHtml(a.Name)}</td>
        <td data-label="Email">${escapeHtml(a.Email)}</td>
        <td data-label="Role">${escapeHtml(displayRole)}</td>
        <td data-label="Location">${escapeHtml(a.Location)}</td>
        <td data-label="Shift">${escapeHtml(a.Shift)}</td>
        <td data-label="Archive reason" class="archive-reason-cell">${a.ArchiveReason
          ? `<strong class="archive-reason-title">${escapeHtml(a.ArchiveReason)}</strong><span class="archive-reason-details">${escapeHtml(a.ArchiveDetails || '')}</span>`
          : '<span class="text-muted">Not recorded</span>'}</td>
        <td data-label="Actions">
          <button type="button" class="btn btn-accent btn-sm unarchive-btn" data-role="${a.Role}" data-id="${a.id}" data-name="${escapeHtml(a.Name)}" data-role-label="${escapeHtml(displayRole)}">Unarchive</button>
        </td>
      </tr>
    `;
  }).join('');

  if (!adminCan('accounts.archive')) table.querySelectorAll('.unarchive-btn').forEach(btn => btn.replaceWith(Object.assign(document.createElement('span'), { className: 'text-muted', textContent: 'View only' })));

  // Re-apply any active search filter immediately after table loads
  const searchInput = document.getElementById('search-archived');
  if (searchInput && searchInput.value) {
    filterTableByName('search-archived', 'archived-table');
  }

  document.querySelectorAll('.unarchive-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const confirmed = await hhConfirm({
        title: `Restore ${btn.dataset.name}'s account?`,
        message: `This ${btn.dataset.roleLabel.toLowerCase()} account becomes active again and can log in.`,
        confirmText: 'Unarchive',
      });
      if (!confirmed) return;
      btn.disabled = true;
      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'unarchive_account', role: btn.dataset.role, id: btn.dataset.id })
      });
      const result = await res.json();
      if (result.ok) {
        showToast('Account successfully unarchived and restored.', 'success');
        loadArchivedAccounts();
      } else {
        showToast(result.error || 'Failed to unarchive.', 'danger');
        btn.disabled = false;
      }
    });
  });
}

// ---------- Pending Account Requests ----------

function requestAdminRejectionReason(title) {
  return hhPrompt({
    title,
    message: 'The applicant will see this reason.',
    label: 'Reason for declining',
    confirmText: 'Decline',
    tone: 'danger',
  });
}

// ---------- Review step for pending registrations and coordinator applications ----------

// "2026-10-07 12:30:35" -> "Oct 7, 2026"
function formatAdminDate(value) {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatAdminDateTime(value) {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

// "2026-10-07 12:30:35" -> "3 days ago"
function timeAgo(value) {
  const date = new Date(String(value || '').replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return '';
  const days = Math.floor((Date.now() - date.getTime()) / 86400000);
  if (days <= 0) return 'today';
  if (days === 1) return 'yesterday';
  return `${days} days ago`;
}

function reviewField(label, value) {
  return `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(String(value ?? ''))}</dd></div>`;
}

function reviewHistory(items, describe) {
  if (!items.length) return '<p class="review-muted">None.</p>';
  return `<ul class="review-history">${items.map(item => `
    <li>
      <strong>${escapeHtml(item.Status)}</strong> · requested ${escapeHtml(formatAdminDate(item.RequestedAt))}${describe ? escapeHtml(describe(item)) : ''}
      ${item.RejectionReason ? `<span class="review-reason">Reason: ${escapeHtml(item.RejectionReason)}</span>` : ''}
    </li>`).join('')}</ul>`;
}

function renderGardenerActivity(items) {
  if (!items.length) return '<p class="review-muted">No crop or resource activity has been recorded.</p>';
  return `<ol class="review-activity-list">${items.map(item => `
    <li class="review-activity-item">
      <div class="review-activity-head">
        <strong>${escapeHtml(item.Subject)}</strong>
        <span class="badge ${item.ActivityType === 'Crop' ? 'badge-green' : 'badge-brown'}">${escapeHtml(item.Action)}</span>
      </div>
      <time class="review-activity-time" datetime="${escapeHtml(String(item.OccurredAt).replace(' ', 'T'))}">${escapeHtml(formatAdminDateTime(item.OccurredAt))}</time>
      ${item.PlotLabel ? `<span class="review-activity-detail">${escapeHtml(item.PlotLabel)}</span>` : ''}
      ${item.Details ? `<p class="review-activity-detail">${escapeHtml(item.Details)}</p>` : ''}
    </li>`).join('')}</ol>`;
}

// Shows the review dialog; a cancelled rejection reason returns to the review.
// approvePermission / rejectPermission: each button only appears for people whose roles allow it.
async function runReview({ title, bodyHtml, approveDisabledReason, rejectTitle, onDecision, approvePermission, rejectPermission }) {
  while (true) {
    const actions = [];
    if (adminCan(rejectPermission)) actions.push({ label: 'Reject', value: 'reject', tone: 'danger' });
    if (adminCan(approvePermission)) actions.push({ label: 'Approve', value: 'approve', disabled: Boolean(approveDisabledReason), title: approveDisabledReason || '' });
    const choice = await hhDetails({ title, bodyHtml, actions });
    if (choice === 'approve') return onDecision('approve');
    if (choice !== 'reject') return;
    const reason = await requestAdminRejectionReason(rejectTitle);
    if (reason) return onDecision('reject', reason);
  }
}

async function reviewSignupRequest(requestId, button) {
  button.disabled = true;
  button.textContent = 'Loading…';
  let data;
  try {
    data = await (await fetch(`api.php?action=signup_request_details&request_id=${encodeURIComponent(requestId)}`)).json();
  } catch (error) {
    data = { ok: false };
  }
  button.disabled = false;
  button.textContent = 'Review';
  if (!data.ok) {
    showToast(data.error || 'Could not load this registration.', 'danger');
    return;
  }

  const r = data.request;
  const fullName = `${r.FirstName} ${r.LastName}`;
  const alerts = [];
  if (data.email_in_use) {
    alerts.push(`<p class="review-alert review-alert-danger">This email already belongs to ${data.email_in_use.status === 'Archived' ? 'an archived' : 'an existing'} ${escapeHtml(data.email_in_use.role)} account, so it can't be approved. Reject it, or unarchive the existing account instead.</p>`);
  }
  if (data.previous_requests.some(p => p.Status === 'Rejected')) {
    alerts.push('<p class="review-alert review-alert-warn">This email was declined before. Check the earlier reason below.</p>');
  }

  const bodyHtml = `
    ${alerts.join('')}
    <section class="review-section">
      <h4>Applicant</h4>
      <dl class="review-grid">
        ${reviewField('Name', fullName)}
        ${reviewField('Email', r.Email)}
        ${reviewField('Age', r.Age)}
        ${reviewField('Location', r.Location)}
        ${reviewField('Requested', `${formatAdminDate(r.RequestedAt)} (${timeAgo(r.RequestedAt)})`)}
        ${reviewField('Account type', 'Gardener')}
      </dl>
    </section>
    <section class="review-section">
      <h4>Earlier requests from this email</h4>
      ${reviewHistory(data.previous_requests)}
    </section>`;

  await runReview({
    title: `Review registration: ${fullName}`,
    bodyHtml,
    approveDisabledReason: data.email_in_use ? 'This email is already in use.' : '',
    rejectTitle: 'Why is this registration being declined?',
    approvePermission: 'registrations.approve',
    rejectPermission: 'registrations.reject',
    onDecision: (decision, reason) => processSignup(requestId, decision, reason),
  });
}

async function reviewCoordinatorApplication(applicationId, button) {
  button.disabled = true;
  button.textContent = 'Loading…';
  let data;
  try {
    data = await (await fetch(`api.php?action=coordinator_application_details&application_id=${encodeURIComponent(applicationId)}`)).json();
  } catch (error) {
    data = { ok: false };
  }
  button.disabled = false;
  button.textContent = 'Review';
  if (!data.ok) {
    showToast(data.error || 'Could not load this application.', 'danger');
    return;
  }

  const app = data.application;
  const a = data.activity;
  const alerts = [];
  if (app.AccountStatus !== 'Active') {
    alerts.push(`<p class="review-alert review-alert-danger">This gardener's account is currently ${escapeHtml(app.AccountStatus.toLowerCase())}.</p>`);
  }
  if (data.previous_applications.some(p => p.Status === 'Rejected')) {
    alerts.push('<p class="review-alert review-alert-warn">This gardener applied before and was declined. Check the earlier reason below.</p>');
  }

  const bodyHtml = `
    ${alerts.join('')}
    <section class="review-section">
      <h4>Applicant</h4>
      <dl class="review-grid">
        ${reviewField('Name', app.Name)}
        ${reviewField('Email', app.Email)}
        ${reviewField('Location', app.Location)}
        ${reviewField('Age', app.Age ?? 'Not provided')}
        ${reviewField('Preferred shift', app.Shift)}
        ${reviewField('Availability', app.AvailabilityDays ? app.AvailabilityDays.split(',').join(', ') : 'Not provided')}
        ${reviewField('Gardening experience', app.GardeningExperience)}
        ${reviewField('Applied', `${formatAdminDate(app.RequestedAt)} (${timeAgo(app.RequestedAt)})`)}
        ${data.member_since ? reviewField('Member since', formatAdminDate(data.member_since)) : ''}
      </dl>
    </section>
    <section class="review-section">
      <h4>Why they want to coordinate</h4>
      <p class="review-quote">${escapeHtml(app.Motivation)}</p>
    </section>
    <section class="review-section">
      <h4>Leadership or volunteer experience</h4>
      <p class="review-quote">${escapeHtml(app.LeadershipExperience || 'Not provided.')}</p>
    </section>
    <section class="review-section">
      <h4>Agreements</h4>
      <dl class="review-grid">
        ${reviewField('Coordinator duties', Number(app.AgreedToDuties) === 1 ? 'Agreed' : 'Not recorded')}
        ${reviewField('Garden rules and fair, impartial conduct', Number(app.AgreedToRules) === 1 ? 'Agreed' : 'Not recorded')}
      </dl>
    </section>
    <section class="review-section">
      <h4>Garden activity</h4>
      <dl class="review-grid">
        ${reviewField('Assigned plots', a.plots.length ? a.plots.join(', ') : 'None')}
        ${reviewField('Crops logged', a.crops_logged)}
        ${reviewField('Maintenance entries', a.maintenance_entries)}
        ${reviewField('Items borrowed now', a.items_borrowed)}
        ${reviewField('Active exchange listings', a.active_listings)}
      </dl>
      <details class="review-activity">
        <summary>View activity log (${a.history.length} entries)</summary>
        ${renderGardenerActivity(a.history)}
      </details>
    </section>
    <section class="review-section">
      <h4>Earlier coordinator applications</h4>
      ${reviewHistory(data.previous_applications, item => ` · ${item.Shift} shift`)}
    </section>`;

  await runReview({
    title: `Review application: ${app.Name}`,
    bodyHtml,
    approveDisabledReason: app.AccountStatus !== 'Active' ? 'The gardener account is not active.' : '',
    rejectTitle: 'Why is this coordinator application being declined?',
    approvePermission: 'coordinator_applications.review',
    rejectPermission: 'coordinator_applications.review',
    onDecision: (decision, reason) => processCoordinatorApplication(applicationId, decision, reason),
  });
}

async function loadSignupRequests() {
  const gardenersTable = document.getElementById('pending-gardeners-table');

  if (!gardenersTable) return;

  const res = await fetch('api.php?action=pending_signups');
  const data = await res.json();
  if (!data.ok) return;

  const renderRow = (r) => `
    <tr data-name="${escapeHtml(r.FirstName + ' ' + r.LastName)}" data-location="${escapeHtml(r.Location || '')}">
      <td data-label="Name">${escapeHtml(r.FirstName + ' ' + r.LastName)}</td>
      <td data-label="Email">${escapeHtml(r.Email)}</td>
      <td data-label="Age">${escapeHtml(String(r.Age))}</td>
      <td data-label="Location">${escapeHtml(r.Location)}</td>
      <td data-label="Actions" class="text-right" style="white-space: nowrap;">
        <button type="button" class="btn btn-accent btn-sm review-signup" data-id="${r.RequestID}">Review</button>
      </td>
    </tr>
  `;

  const emptyEl = document.getElementById('pending-gardeners-empty');
  if (data.requests.length === 0) {
    gardenersTable.innerHTML = '';
    if (emptyEl) emptyEl.hidden = false;
  } else {
    if (emptyEl) emptyEl.hidden = true;
    gardenersTable.innerHTML = data.requests.map(renderRow).join('');
  }

  gardenersTable.querySelectorAll('.review-signup').forEach(btn => {
    btn.addEventListener('click', () => reviewSignupRequest(btn.dataset.id, btn));
  });
}

async function processSignup(requestId, decision, reason = '') {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'process_signup', request_id: requestId, decision, reason }),
  });
  const data = await res.json();
  if (data.ok) {
    showToast(`Account request ${decision === 'approve' ? 'approved & created' : 'rejected'}.`, 'success');
    loadSignupRequests();
    loadAccounts();
    loadStats();
  } else {
    showToast(data.error || 'Could not process request.', 'danger');
  }
}

async function loadCoordinatorApplications() {
  const table = document.getElementById('pending-coordinator-applications-table');
  if (!table) return;
  const res = await fetch('api.php?action=pending_coordinator_applications');
  const data = await res.json();
  if (!data.ok) {
    showToast(data.error || 'Could not load coordinator applications.', 'danger');
    return;
  }
  const empty = document.getElementById('pending-coordinator-applications-empty');
  table.innerHTML = data.applications.map(app => `
    <tr>
      <td data-label="Name">${escapeHtml(app.Name)}</td>
      <td data-label="Email">${escapeHtml(app.Email)}</td>
      <td data-label="Location">${escapeHtml(app.Location || 'Not provided')}</td>
      <td data-label="Shift">${escapeHtml(app.Shift)}</td>
      <td data-label="Motivation"><span class="motivation-preview" title="${escapeHtml(app.Motivation)}">${escapeHtml(app.Motivation)}</span></td>
      <td data-label="Actions" class="text-right" style="white-space: nowrap;">
        <button type="button" class="btn btn-accent btn-sm review-coordinator-application" data-id="${app.ApplicationID}">Review</button>
      </td>
    </tr>
  `).join('');
  if (empty) empty.hidden = data.applications.length > 0;
  table.querySelectorAll('.review-coordinator-application').forEach(btn =>
    btn.addEventListener('click', () => reviewCoordinatorApplication(btn.dataset.id, btn)));
}

async function processCoordinatorApplication(applicationId, decision, reason = '') {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'process_coordinator_application', application_id: applicationId, decision, reason }),
  });
  const data = await res.json();
  if (data.ok) {
    showToast(`Coordinator application ${decision === 'approve' ? 'approved' : 'declined'}.`, 'success');
    loadCoordinatorApplications();
    loadAccounts();
    loadStats();
  } else {
    showToast(data.error || 'Could not process coordinator application.', 'danger');
  }
}

// ---------- Delete confirmation modal ----------

const deleteModal = document.getElementById('delete-modal');
const deleteModalBody = document.getElementById('delete-modal-body');
const deleteModalTitle = document.getElementById('delete-modal-title');
const deleteCancelBtn = document.getElementById('delete-cancel');
const deleteConfirmBtn = document.getElementById('delete-confirm');
const deleteNoticeBtn = document.getElementById('delete-notice');
let pendingDelete = null;

async function openDeleteModal(table, id, name) {
  if (!deleteModal) return; 
  pendingDelete = { table, id };
  if (deleteConfirmBtn) deleteConfirmBtn.hidden = false;
  if (deleteNoticeBtn) deleteNoticeBtn.hidden = true;
  
  // Reset modal state to loading
  if (deleteModalTitle) deleteModalTitle.textContent = `Archive ${name}?`;
  if (deleteModalBody) deleteModalBody.innerHTML = `<p class="text-muted">Loading account details...</p>`;
  
  // Set button to faded brown while loading
  if (deleteConfirmBtn) {
      deleteConfirmBtn.disabled = true;
      deleteConfirmBtn.style.opacity = '0.5';
      deleteConfirmBtn.style.cursor = 'not-allowed';
  }
  
  deleteModal.hidden = false;

  // Fetch the user's active assets and profile from the backend
  const res = await fetch(`api.php?action=user_archive_details&table=${table}&id=${id}`);
  const data = await res.json();
  
  if (!data.ok) {
     if (deleteModalBody) deleteModalBody.innerHTML = `<p class="text-danger">Failed to load account details.</p>`;
     return;
  }

  const d = data.details;
  let html = ``;
  let canArchive = true;
  const archiveNotice = d.archiveNotice || null;

  // 1. Basic Information Block (Shown for everyone)
  if (d.profile) {
      html += `
      <div style="background: var(--cream-100); padding: 14px; border-radius: var(--radius); border: 1px solid var(--line); margin-bottom: 18px; font-size: 0.9rem; color: var(--ink-900);">
          <div style="margin-bottom: 6px;"><strong>Email:</strong> ${escapeHtml(d.profile.Email)}</div>
          <div style="margin-bottom: 6px;"><strong>Location:</strong> ${escapeHtml(d.profile.Location || 'Not provided')}</div>`;
      
      if (d.profile.Age) {
          html += `<div style="margin-bottom: 6px;"><strong>Age:</strong> ${escapeHtml(String(d.profile.Age))}</div>`;
      }
      if (d.profile.Shift) {
          html += `<div style="margin-bottom: 0;"><strong>Shift:</strong> ${escapeHtml(d.profile.Shift)}</div>`;
      }
      html += `</div>`;
  }

  // 2. Gardener-Specific Checks
  if (table === 'gardener') {
     
     // Hard Block: Unreturned Borrowed Items
     if (d.borrowed && d.borrowed.length > 0) {
         canArchive = false;
         html += `
         <div class="form-alert" style="margin-top: 0; margin-bottom: 18px; padding: 14px;">
            <strong>Cannot Archive:</strong> This gardener currently possesses unreturned tools. Ask them to return these items:
            <ul style="margin: 8px 0 0; padding-left: 20px;">
               ${d.borrowed.map(i => `<li>${escapeHtml(i.Name)} (Qty:${i.Qty})</li>`).join('')}
            </ul>
         </div>`;
     }

     // Hard Block: Active Plots
     if (d.plots && d.plots.length > 0) {
         canArchive = false;
         html += `
         <div class="form-alert" style="margin-top: 0; margin-bottom: 18px; padding: 14px;">
            <strong>Cannot Archive:</strong> This user owns active plots. Ask them to request plot unassignment:
            <ul style="margin: 8px 0 0; padding-left: 20px;">
               ${d.plots.map(p => `<li>${escapeHtml(p)}</li>`).join('')}
            </ul>
         </div>`;
     }

     // Warning: Active Listings
     if (d.listings && d.listings.length > 0) {
         html += `
         <div style="margin-bottom: 14px;">
            <strong>Exchange Listings (Will be orphaned):</strong>
            <ul style="margin: 4px 0 0; padding-left: 20px; font-size: 0.92rem;">
               ${d.listings.map(l => `<li>${escapeHtml(l)}</li>`).join('')}
            </ul>
         </div>`;
     }
  }

  if (table === 'gardener') {
     const reasons = ['Inactive account', 'Spam or abuse', 'Policy violation', 'Other'];
     html += `
       <div class="archive-notice-form">
         <label for="archive-notice-reason"><strong>Reason for proposed archive</strong></label>
         <select id="archive-notice-reason" required>
           <option value="">Choose a reason</option>
           ${reasons.map(reason => `<option value="${reason}" ${archiveNotice && archiveNotice.Reason === reason ? 'selected' : ''}>${reason}</option>`).join('')}
         </select>
         <label for="archive-notice-details"><strong>Explain why</strong></label>
         <textarea id="archive-notice-details" rows="3" maxlength="1000" required
           placeholder="Explain the reason and what the gardener should do next."
           >${archiveNotice ? escapeHtml(archiveNotice.Details) : ''}</textarea>
         <p class="text-muted archive-notice-hint">
           ${canArchive
             ? 'This reason will be recorded with the archived account.'
             : 'The gardener will be asked to return borrowed items and request plot unassignment.'}
         </p>
       </div>`;
  }

  // Final permissive text if they pass the guardrails
  if (canArchive) {
     html += `<p style="margin: 0; font-size: 0.95rem; color: var(--ink-600);">They will lose login access, but their past records will remain intact.</p>`;
     
     // Restore full brown button appearance
     if (deleteConfirmBtn) {
         deleteConfirmBtn.disabled = false;
         deleteConfirmBtn.style.opacity = '1'; 
         deleteConfirmBtn.style.cursor = 'pointer';
     }
  } else {
     if (deleteConfirmBtn) deleteConfirmBtn.hidden = true;
     if (deleteNoticeBtn && table === 'gardener') {
         deleteNoticeBtn.hidden = false;
     }
  }

  // Inject content and focus
  if (deleteModalBody) deleteModalBody.innerHTML = html;
  if (canArchive && deleteConfirmBtn) {
      deleteConfirmBtn.focus();
  }
}

function closeDeleteModal() {
  if (!deleteModal) return; 
  deleteModal.hidden = true;
  pendingDelete = null;
}

if (deleteCancelBtn) deleteCancelBtn.addEventListener('click', closeDeleteModal);
if (deleteModal) {
    deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) closeDeleteModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !deleteModal.hidden) closeDeleteModal(); });
}

if (deleteConfirmBtn) {
    deleteConfirmBtn.addEventListener('click', async () => {
      if (!pendingDelete) return;
      const { table, id } = pendingDelete;
      const params = { action: 'archive_account', table, id };
      if (table === 'gardener') {
        const reasonInput = document.getElementById('archive-notice-reason');
        const detailsInput = document.getElementById('archive-notice-details');
        params.reason = reasonInput ? reasonInput.value : '';
        params.details = detailsInput ? detailsInput.value.trim() : '';
        if (!params.reason || !params.details) {
          showToast('Choose a reason and explain why this account should be archived.', 'danger');
          return;
        }
      }
      closeDeleteModal();

      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(params),
      });
      const data = await res.json();
      if (data.ok) {
        showToast('Account archived.', 'success');
        loadAccounts();
      } else {
        showToast(data.error || 'Could not archive account.', 'danger');
      }
    });
}

if (deleteNoticeBtn) {
    deleteNoticeBtn.addEventListener('click', async () => {
      if (!pendingDelete || pendingDelete.table !== 'gardener') return;
      const reasonInput = document.getElementById('archive-notice-reason');
      const detailsInput = document.getElementById('archive-notice-details');
      const reason = reasonInput ? reasonInput.value : '';
      const details = detailsInput ? detailsInput.value.trim() : '';
      if (!reason || !details) {
        showToast('Choose a reason and explain the notice.', 'danger');
        return;
      }

      const { id } = pendingDelete;
      deleteNoticeBtn.disabled = true;

      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'send_archive_notice', id, reason, details }),
        });
        const data = await res.json();
        if (data.ok) {
          showToast('Account archive notice sent to the gardener.', 'success');
          closeDeleteModal();
        } else {
          showToast(data.error || 'Could not send archive notice.', 'danger');
        }
      } catch (error) {
        showToast('Could not send archive notice. Please try again.', 'danger');
      } finally {
        deleteNoticeBtn.disabled = false;
      }
    });
}

// Clean table sorting helper using direct element reference
function sortTable(columnIndex, headerEl) {
  const table = document.getElementById("archived-data-table");
  if (!table) return;
  
  const tbody = document.getElementById("archived-table");
  if (!tbody) return;

  const headers = table.querySelectorAll("th");
  const cleanTexts = ["Name", "Email", "Role", "Location", "Shift"];
  
  // Reset all headers to clean text without arrows
  headers.forEach((th, idx) => {
    if (idx < 5) {
      th.innerHTML = `${cleanTexts[idx]} <span id="sort-icon-${idx}"></span>`;
    }
  });

  // Determine sort direction (toggle if clicking the same column, default to asc)
  let currentDir = table.dataset.sortDir === "asc" && table.dataset.sortCol == columnIndex ? "desc" : "asc";
  table.dataset.sortDir = currentDir;
  table.dataset.sortCol = columnIndex;

  // Render the arrow inside the specific column's span instantly
  const activeIcon = document.getElementById(`sort-icon-${columnIndex}`);
  if (activeIcon) {
    activeIcon.textContent = currentDir === "asc" ? "▴" : "▾";
  }

  // Grab all table rows (excluding header)
  const rowsArray = Array.from(tbody.querySelectorAll("tr"));

  // Check if we have valid data rows (ignore empty/loading state rows)
  if (rowsArray.length <= 1 && rowsArray[0]?.querySelector('.text-muted')) return;

  // Sort rows cleanly using modern array sorting
  rowsArray.sort((rowA, rowB) => {
    const cellA = rowA.getElementsByTagName("TD")[columnIndex]?.textContent.trim().toLowerCase() || "";
    const cellB = rowB.getElementsByTagName("TD")[columnIndex]?.textContent.trim().toLowerCase() || "";

    if (cellA < cellB) return currentDir === "asc" ? -1 : 1;
    if (cellA > cellB) return currentDir === "asc" ? 1 : -1;
    return 0;
  });

  // Re-append sorted rows to the table body in one smooth operation
  rowsArray.forEach(row => tbody.appendChild(row));
}

// ---------- Chart.js Graph & Report Export Logic ----------
async function renderActivityGraph() {
  const plotCtx = document.getElementById('plotChart');
  const exchangeCtx = document.getElementById('exchangeChart');
  const resourceCtx = document.getElementById('resourceChart');
  
  if (!plotCtx || !exchangeCtx || !resourceCtx) return; 

  const res = await fetch('api.php?action=dashboard_charts');
  const data = await res.json();
  if (!data.ok) return;

  // Orangey-Brown Palette (Based on original graph)
  const brownMain = '#a8562e';   // Original graph color (Primary)
  const brownLight = '#d69777';  // Soft terracotta (Secondary)
  const brownPale = '#f3e3d8';   // Pale peach from your CSS badges (Tertiary)

  // 1. Plot Utilization (Doughnut)
  new Chart(plotCtx, {
    type: 'doughnut',
    data: {
      labels: ['Occupied', 'Available', 'Pending Applications'],
      datasets: [{
        data: [data.plots.Occupied, data.plots.Available, data.plots.Pending],
        backgroundColor: [brownMain, brownLight, brownPale],
        borderWidth: 1,
        borderColor: '#ffffff'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false }
  });

  // 2. Exchange Market (Doughnut)
  new Chart(exchangeCtx, {
    type: 'doughnut',
    data: {
      labels: ['Active Listings', 'Completed Trades'],
      datasets: [{
        data: [data.exchange.Active, data.exchange.Completed],
        backgroundColor: [brownMain, brownLight],
        borderWidth: 1,
        borderColor: '#ffffff'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false }
  });

  // 3. Resource Inventory (Grouped Bar)
  new Chart(resourceCtx, {
    type: 'bar',
    data: {
      labels: data.resources.labels,
      datasets: [
        {
          label: 'Available',
          data: data.resources.available,
          backgroundColor: brownLight,
          borderRadius: 4
        },
        {
          label: 'Currently Borrowed',
          data: data.resources.borrowed,
          backgroundColor: brownMain,
          borderRadius: 4
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
}

// ---------- SINGLE INITIALIZATION BLOCK ----------
document.addEventListener('DOMContentLoaded', () => {
  
  // 1. Fire all loaders. The safety checks (!table, !ctx, etc.) will prevent them from crashing on the wrong pages.
  loadStats();
  loadAccounts();
  loadSignupRequests();
  loadCoordinatorApplications();
  loadArchivedAccounts();
  renderActivityGraph();

  // 2. Account search
  document.querySelectorAll('[data-table-search]').forEach(input => {
    const filter = () => filterTableByName(input.id, input.dataset.tableSearch);
    input.addEventListener('input', filter);
    input.addEventListener('search', filter);
    input.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        input.value = '';
        filter();
      }
    });
    document.querySelector(`[data-status-filter="${input.dataset.tableSearch}"]`)?.addEventListener('change', filter);
  });

  // 3. Create Admin Form Logic
  const createAdminForm = document.getElementById('create-admin-form');
  const adminPasswordInput = document.getElementById('new-admin-pass');
  const adminPasswordReqs = document.getElementById('admin-password-reqs');
  const adminPasswordRules = [
    ['length', value => value.length >= 8],
    ['upper', value => /[A-Z]/.test(value)],
    ['lower', value => /[a-z]/.test(value)],
    ['number', value => /\d/.test(value)],
    ['special', value => /[\W_]/.test(value)],
  ];

  function updateAdminPasswordRequirements() {
    if (!adminPasswordInput || !adminPasswordReqs) return;

    adminPasswordRules.forEach(([name, test]) => {
      const item = adminPasswordReqs.querySelector(`[data-requirement="${name}"]`);
      const valid = test(adminPasswordInput.value);
      item.classList.toggle('valid', valid);
      item.classList.toggle('invalid', !valid);
    });
  }

  if (adminPasswordInput && adminPasswordReqs) {
    adminPasswordInput.addEventListener('focus', () => adminPasswordReqs.classList.add('active'));
    adminPasswordInput.addEventListener('input', updateAdminPasswordRequirements);
    adminPasswordInput.addEventListener('blur', () => {
      const isValid = adminPasswordRules.every(([, test]) => test(adminPasswordInput.value));
      if (adminPasswordInput.value === '' || isValid) adminPasswordReqs.classList.remove('active');
    });
  }

  if (createAdminForm) {
    createAdminForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const requiredFields = [
        document.getElementById('new-admin-first-name'),
        document.getElementById('new-admin-last-name'),
        document.getElementById('new-admin-location'),
        document.getElementById('new-admin-age'),
        document.getElementById('new-admin-email'),
        adminPasswordInput,
      ];
      const missingField = requiredFields.find(field => !field || !field.value.trim());
      if (missingField) {
        showToast('Please fill in all required fields.', 'danger');
        missingField.focus();
        return;
      }

      const password = adminPasswordInput.value;
      const isPasswordValid = adminPasswordRules.every(([, test]) => test(password));
      if (!isPasswordValid) {
        updateAdminPasswordRequirements();
        if (adminPasswordReqs) adminPasswordReqs.classList.add('active');
        if (adminPasswordInput) adminPasswordInput.focus();
        showToast('Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.', 'danger');
        return;
      }

      const submitBtn = createAdminForm.querySelector('button[type="submit"]');
      if (!createAdminForm.reportValidity()) return;
      submitBtn.disabled = true;

      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'create_admin',
            first_name: document.getElementById('new-admin-first-name').value,
            last_name: document.getElementById('new-admin-last-name').value,
            email: document.getElementById('new-admin-email').value,
            age: document.getElementById('new-admin-age').value,
            location: document.getElementById('new-admin-location').value,
            password: password
          })
        });
        const data = await res.json();
        if (data.ok) {
          showToast('Administrator account created successfully!', 'success');
          createAdminForm.reset();
          updateAdminPasswordRequirements();
          if (adminPasswordReqs) adminPasswordReqs.classList.remove('active');
          loadAccounts();
        } else {
          showToast(data.error || 'Failed to create account.', 'danger');
        }
      } catch (error) {
        console.error('Error creating administrator:', error);
        showToast('Network error. Please try again.', 'danger');
      } finally {
        submitBtn.disabled = false;
      }
    });
  }

  // 4. Export Report Button (CSV)
  const exportBtn = document.getElementById('export-report-btn');
  if (exportBtn) {
    exportBtn.addEventListener('click', async () => {
      exportBtn.disabled = true;
      exportBtn.textContent = 'Exporting...';

      try {
        const [statsRes, chartsRes] = await Promise.all([
          fetch('api.php?action=stats'),
          fetch('api.php?action=dashboard_charts')
        ]);
        const statsData = await statsRes.json();
        const chartsData = await chartsRes.json();

        if (!statsData.ok || !chartsData.ok) throw new Error();

        // Build a highly readable, sectioned CSV array
        let csv = [];
        
        // --- 1. Top KPI Stats ---
        csv.push("--- SYSTEM OVERVIEW ---,");
        csv.push("Metric,Value");
        for (const [key, value] of Object.entries(statsData.stats)) {
          csv.push(`"${key.replace(/_/g, ' ').toUpperCase()}",${value}`);
        }
        csv.push(","); // Blank row for spacing

        // --- 2. Plot Utilization ---
        csv.push("--- PLOT UTILIZATION ---,");
        csv.push("Status,Count");
        for (const [key, value] of Object.entries(chartsData.plots)) {
           csv.push(`"${key.toUpperCase()}",${value}`);
        }
        csv.push(",");

        // --- 3. Exchange Market ---
        csv.push("--- EXCHANGE MARKET ---,");
        csv.push("Status,Count");
        for (const [key, value] of Object.entries(chartsData.exchange)) {
           csv.push(`"${key.toUpperCase()}",${value}`);
        }
        csv.push(",");

        // --- 4. Resource Inventory ---
        csv.push("--- RESOURCE INVENTORY ---,,");
        csv.push("Resource,Available in Shed,Currently Borrowed");
        const r = chartsData.resources;
        for (let i = 0; i < r.labels.length; i++) {
           // Groups the tool name, available count, and borrowed count nicely into 3 columns
           csv.push(`"${r.labels[i]}",${r.available[i]},${r.borrowed[i]}`);
        }

        // Generate a Blob with a UTF-8 BOM (\uFEFF) to force Excel to format it cleanly
        const blob = new Blob(["\uFEFF" + csv.join("\n")], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        
        const link = document.createElement("a");
        link.href = url;
        link.download = `HarvestHub_Analytics_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(link);
        link.click();
        
        // Clean up memory
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
      } catch (e) {
        showToast('Failed to export data.', 'danger');
      } finally {
        exportBtn.disabled = false;
        exportBtn.textContent = 'Export CSV';
      }
    });
  }

  // 5. Export PDF Button
  const exportPdfBtn = document.getElementById('export-pdf-btn');
  if (exportPdfBtn) {
    exportPdfBtn.addEventListener('click', () => {
      // Small timeout ensures any active tooltips close before capturing
      setTimeout(() => window.print(), 100); 
    });
  }
});
