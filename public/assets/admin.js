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

function filterTableByName(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  const query = input.value.trim().toLowerCase();

  table.querySelectorAll('tr[data-name]').forEach(row => {
    // Name is stored in the data attribute
    const name = row.dataset.name ? row.dataset.name.toLowerCase() : '';
    
    // Email is uniformly located in the second column (td:nth-child(2)) across all our tables
    const emailCell = row.querySelector('td:nth-child(2)');
    const email = emailCell ? emailCell.textContent.toLowerCase() : '';
    
    // Hide row if the query is not empty AND it matches neither Name nor Email
    row.hidden = query !== '' && !name.includes(query) && !email.includes(query);
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
      <tr data-name="${escapeHtml(g.Name)}" data-location="${escapeHtml(g.Location || '')}">
        <td>${escapeHtml(g.Name)}</td>
        <td>${escapeHtml(g.Email)}</td>
        <td>${escapeHtml(g.Location || 'Not provided')}</td>
        <td>
          <button type="button" class="btn btn-ghost btn-sm delete-btn" data-table="gardener" data-id="${g.id}" data-name="${escapeHtml(g.Name)}">Archive</button>
        </td>
      </tr>
    `).join('') || '<tr><td colspan="4" class="text-muted">No gardeners yet.</td></tr>';
  }

  // Render Coordinators if table exists
  const coordsTable = document.getElementById('coordinators-table');
  if (coordsTable) {
    coordsTable.innerHTML = data.coordinators.map(c => `
      <tr data-name="${escapeHtml(c.Name)}" data-location="${escapeHtml(c.Location || '')}">
        <td>${escapeHtml(c.Name)}</td>
        <td>${escapeHtml(c.Email)}</td>
        <td>${escapeHtml(c.Shift)}</td>
        <td>${escapeHtml(c.Location || 'Not provided')}</td>
        <td>
          <button type="button" class="btn btn-ghost btn-sm delete-btn" data-table="coordinator" data-id="${c.id}" data-name="${escapeHtml(c.Name)}">Archive</button>
        </td>
      </tr>
    `).join('') || '<tr><td colspan="5" class="text-muted">No coordinators yet.</td></tr>';
  }

  // Render Admins if table exists
  const adminsTable = document.getElementById('admins-table');
  if (adminsTable) {
    adminsTable.innerHTML = data.admins.map(a => `
      <tr data-name="${escapeHtml(a.Name)}" data-location="${escapeHtml(a.Location || '')}">
        <td>${escapeHtml(a.Name)}</td>
        <td>${escapeHtml(a.Email)}</td>
        <td>${escapeHtml(a.Location || 'Not provided')}</td>
        <td>
          <button type="button" class="btn btn-ghost btn-sm delete-btn" data-table="admin" data-id="${a.id}" data-name="${escapeHtml(a.Name)}" ${a.id === data.current_user_id ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>Archive</button>
        </td>
      </tr>
    `).join('') || '<tr><td colspan="4" class="text-muted">No administrators yet.</td></tr>';
  }

  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.onclick = () => {
      openDeleteModal(btn.dataset.table, btn.dataset.id, btn.dataset.name);
    };
  });
}

async function loadArchivedAccounts() {
  const table = document.getElementById('archived-table');
  if (!table) return; // Only run on the archived page

  const res = await fetch('api.php?action=archived_accounts');
  const data = await res.json();
  
  if (!data.ok || data.accounts.length === 0) {
    table.innerHTML = '<tr><td colspan="6" class="text-muted">No archived accounts found.</td></tr>';
    return;
  }

  table.innerHTML = data.accounts.map(a => {
    const displayRole = a.Role === 'Customer' ? 'Gardener' : a.Role;
    return `
      <tr data-name="${escapeHtml(a.Name)}">
        <td>${escapeHtml(a.Name)}</td>
        <td>${escapeHtml(a.Email)}</td>
        <td>${escapeHtml(displayRole)}</td>
        <td>${escapeHtml(a.Location)}</td>
        <td>${escapeHtml(a.Shift)}</td>
        <td>
          <button type="button" class="btn btn-accent btn-sm unarchive-btn" data-role="${a.Role}" data-id="${a.id}">Unarchive</button>
        </td>
      </tr>
    `;
  }).join('');

  // Re-apply any active search filter immediately after table loads
  const searchInput = document.getElementById('search-archived');
  if (searchInput && searchInput.value) {
    filterTableByName('search-archived', 'archived-table');
  }

  document.querySelectorAll('.unarchive-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
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

async function loadSignupRequests() {
  const gardenersTable = document.getElementById('pending-gardeners-table');
  const coordsTable = document.getElementById('pending-coordinators-table');
  
  // If neither table is on the page, don't fetch data
  if (!gardenersTable && !coordsTable) return;

  const res = await fetch('api.php?action=pending_signups');
  const data = await res.json();
  if (!data.ok) return;

  const renderRow = (r) => `
    <tr data-name="${escapeHtml(r.FirstName + ' ' + r.LastName)}" data-location="${escapeHtml(r.Location || '')}">
      <td>${escapeHtml(r.FirstName + ' ' + r.LastName)}</td>
      <td>${escapeHtml(r.Email)}</td>
      <td>${escapeHtml(String(r.Age))}</td>
      <td>${escapeHtml(r.Location)}</td>
      ${r.Role === 'staff' ? `<td>${escapeHtml(r.Shift || 'Morning')}</td>` : ''}
      <td class="text-right" style="white-space: nowrap;">
        <button class="btn btn-sm approve-signup" style="background: var(--green-700); color: var(--white);" data-id="${r.RequestID}">Approve</button>
        <button class="btn btn-sm reject-signup" style="background: var(--danger); color: var(--white);" data-id="${r.RequestID}">Reject</button>
      </td>
    </tr>
  `;

  if (gardenersTable) {
    const gardeners = data.requests.filter(r => r.Role !== 'staff');
    const emptyEl = document.getElementById('pending-gardeners-empty');
    if (gardeners.length === 0) {
      gardenersTable.innerHTML = '';
      if (emptyEl) emptyEl.hidden = false;
    } else {
      if (emptyEl) emptyEl.hidden = true;
      gardenersTable.innerHTML = gardeners.map(renderRow).join('');
    }
  }

  if (coordsTable) {
    const coords = data.requests.filter(r => r.Role === 'staff');
    const emptyEl = document.getElementById('pending-coordinators-empty');
    if (coords.length === 0) {
      coordsTable.innerHTML = '';
      if (emptyEl) emptyEl.hidden = false;
    } else {
      if (emptyEl) emptyEl.hidden = true;
      coordsTable.innerHTML = coords.map(renderRow).join('');
    }
  }

  document.querySelectorAll('.approve-signup').forEach(btn => {
    btn.addEventListener('click', () => processSignup(btn.dataset.id, 'approve'));
  });
  document.querySelectorAll('.reject-signup').forEach(btn => {
    btn.addEventListener('click', () => processSignup(btn.dataset.id, 'reject'));
  });
}

async function processSignup(requestId, decision) {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'process_signup', request_id: requestId, decision }),
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

// ---------- Delete confirmation modal ----------

const deleteModal = document.getElementById('delete-modal');
const deleteModalBody = document.getElementById('delete-modal-body');
const deleteModalTitle = document.getElementById('delete-modal-title');
const deleteCancelBtn = document.getElementById('delete-cancel');
const deleteConfirmBtn = document.getElementById('delete-confirm');
let pendingDelete = null;

async function openDeleteModal(table, id, name) {
  if (!deleteModal) return; 
  pendingDelete = { table, id };
  
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
            <strong>Cannot Archive:</strong> This gardener currently possesses unreturned tools. They must return these items before archiving is permitted:
            <ul style="margin: 8px 0 0; padding-left: 20px;">
               ${d.borrowed.map(i => `<li>${escapeHtml(i.Name)} (Qty:${i.Qty})</li>`).join('')}
            </ul>
         </div>`;
     }

     // Warning: Active Plots
     if (d.plots && d.plots.length > 0) {
         html += `
         <div style="margin-bottom: 14px;">
            <strong style="color: var(--danger);">Active Plots (Will be unassigned):</strong>
            <ul style="margin: 4px 0 0; padding-left: 20px; font-size: 0.92rem;">
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

  // Final permissive text if they pass the guardrails
  if (canArchive) {
     html += `<p style="margin: 0; font-size: 0.95rem; color: var(--ink-600);">They will lose login access, but their past records will remain intact.</p>`;
     
     // Restore full brown button appearance
     if (deleteConfirmBtn) {
         deleteConfirmBtn.disabled = false;
         deleteConfirmBtn.style.opacity = '1'; 
         deleteConfirmBtn.style.cursor = 'pointer';
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
      closeDeleteModal();

      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'archive_account', table, id }),
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

// ---------- Archived Accounts Logic ----------
async function loadArchivedAccounts() {
  const table = document.getElementById('archived-table');
  if (!table) return; // Only run on the archived page

  const res = await fetch('api.php?action=archived_accounts');
  const data = await res.json();
  
  if (!data.ok || data.accounts.length === 0) {
    table.innerHTML = '<tr><td colspan="6" class="text-muted">No archived accounts found.</td></tr>';
    return;
  }

  table.innerHTML = data.accounts.map(a => {
    const displayRole = a.Role === 'Customer' ? 'Gardener' : a.Role;
    return `
      <tr data-name="${escapeHtml(a.Name)}">
        <td>${escapeHtml(a.Name)}</td>
        <td>${escapeHtml(a.Email)}</td>
        <td>${escapeHtml(displayRole)}</td>
        <td>${escapeHtml(a.Location)}</td>
        <td>${escapeHtml(a.Shift)}</td>
        <td>
          <button type="button" class="btn btn-accent btn-sm unarchive-btn" data-role="${a.Role}" data-id="${a.id}">Unarchive</button>
        </td>
      </tr>
    `;
  }).join('');

  // Re-apply any active search filter immediately after table loads
  const searchInput = document.getElementById('search-archived');
  if (searchInput && searchInput.value) {
    filterTableByName('search-archived', 'archived-table');
  }

  document.querySelectorAll('.unarchive-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
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
      submitBtn.disabled = true;

      const res = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'create_admin',
          name: document.getElementById('new-admin-name').value,
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
      submitBtn.disabled = false;
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