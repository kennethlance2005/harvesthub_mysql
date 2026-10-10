// Admin Roles and Permissions page (admin_roles.php). Relies on escapeHtml and
// showToast from admin.js, and hhConfirm / hhAlert / hhForm / hhDetails from
// dialog.js.
document.addEventListener('DOMContentLoaded', () => {
  const rolesList = document.getElementById('roles-list');
  if (!rolesList) return;

  const els = {
    newRole: document.getElementById('role-new'),
    matrix: document.getElementById('permission-matrix'),
    matrixStatus: document.getElementById('matrix-status'),
    matrixUndo: document.getElementById('matrix-undo'),
    matrixSave: document.getElementById('matrix-save'),
    members: document.getElementById('role-members'),
    membersTitle: document.getElementById('role-members-title'),
    membersHelp: document.getElementById('role-members-help'),
    membersTable: document.getElementById('role-members-table'),
    membersClose: document.getElementById('role-members-close'),
    addPerson: document.getElementById('role-add-person'),
    personSearch: document.getElementById('role-person-search'),
    candidates: document.getElementById('role-candidates'),
  };

  // What built-in roles do on top of their ticks, in plain words.
  const BUILT_IN_NOTES = {
    admin: 'Administrators can do everything in HarvestHub.',
    coordinator: 'Coordinators also see the coordinator dashboard and records.',
    gardener: 'Gardeners can always look after their own things: request plots, log crops, borrow and donate resources, and trade on the exchange board. The ticks below are for extra tools.',
  };
  const ACCOUNT_LABELS = { admin: 'Administrator', coordinator: 'Coordinator', gardener: 'Gardener' };

  let roles = [];
  let permissions = [];
  let draft = {};          // roleId -> Set of permission codes, as ticked on screen
  let openRoleId = null;   // role whose members are showing

  const roleById = id => roles.find(role => role.id === Number(id));
  const permissionName = code => (permissions.find(p => p.code === code) || {}).name || code;
  const modules = () => [...new Set(permissions.map(p => p.module))];

  async function api(params, method = 'GET') {
    const options = method === 'POST'
      ? { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params }
      : undefined;
    const url = method === 'POST' ? 'api.php' : `api.php?${params}`;
    try {
      const res = await fetch(url, options);
      return await res.json();
    } catch (e) {
      return { ok: false, error: 'Could not reach HarvestHub. Check your connection and try again.' };
    }
  }

  // ---------- Loading ----------

  async function load() {
    const data = await api(new URLSearchParams({ action: 'roles_overview' }));
    if (!data.ok) {
      rolesList.innerHTML = `<p class="text-muted">${escapeHtml(data.error || 'Could not load roles.')}</p>`;
      return;
    }
    // Keep unsaved ticks for roles whose saved permissions didn't change
    // (e.g. after giving someone a role), so they aren't lost on reload.
    const before = Object.fromEntries(roles.map(role => [role.id, role.permissions.join()]));
    const oldDraft = draft;
    roles = data.roles;
    permissions = data.permissions;
    draft = Object.fromEntries(roles.map(role => [role.id,
      oldDraft[role.id] && before[role.id] === role.permissions.join() ? oldDraft[role.id] : new Set(role.permissions)]));
    renderRoles();
    renderMatrix();
    if (openRoleId && roleById(openRoleId)) showMembers(openRoleId);
    else closeMembers();
  }

  // ---------- Role cards ----------

  function renderRoles() {
    rolesList.innerHTML = roles.map(role => {
      const count = role.members === 1 ? '1 person' : `${role.members} people`;
      const can = role.locked ? 'Every permission' : `${role.permissions.length} of ${permissions.length} permissions`;
      return `
        <article class="role-card${role.built_in ? '' : ' role-card-custom'}">
          <div class="role-card-head">
            <h3>${escapeHtml(role.name)}</h3>
            <span class="badge ${role.built_in ? 'badge-neutral' : 'badge-brown'}">${role.built_in ? 'Built-in' : 'Custom'}</span>
          </div>
          <p class="role-card-desc">${escapeHtml(role.description || 'No description yet.')}</p>
          <p class="role-card-meta"><strong>${count}</strong> · ${can}</p>
          <div class="role-card-actions">
            <button type="button" class="btn btn-ghost btn-sm" data-role-preview="${role.id}">What can it do?</button>
            <button type="button" class="btn btn-ghost btn-sm" data-role-members="${role.id}">Members</button>
            ${role.built_in ? '' : `
              <button type="button" class="btn btn-ghost btn-sm" data-role-edit="${role.id}">Edit</button>
              <button type="button" class="btn btn-ghost btn-sm role-delete-btn" data-role-delete="${role.id}">Delete</button>`}
          </div>
        </article>`;
    }).join('');
  }

  rolesList.addEventListener('click', event => {
    const btn = event.target.closest('button');
    if (!btn) return;
    if (btn.dataset.rolePreview) previewRole(roleById(btn.dataset.rolePreview));
    if (btn.dataset.roleMembers) showMembers(Number(btn.dataset.roleMembers), true);
    if (btn.dataset.roleEdit) editRole(roleById(btn.dataset.roleEdit));
    if (btn.dataset.roleDelete) deleteRole(roleById(btn.dataset.roleDelete));
  });

  // ---------- "What can this role do?" ----------

  function canList(codes) {
    return modules().map(module => {
      const items = permissions.filter(p => p.module === module && codes.has(p.code));
      if (!items.length) return '';
      return `<li><strong>${escapeHtml(module)}:</strong> ${items.map(p => escapeHtml(p.name.toLowerCase())).join(', ')}</li>`;
    }).join('');
  }

  function previewRole(role) {
    const codes = role.locked ? new Set(permissions.map(p => p.code)) : new Set(role.permissions);
    const cannot = new Set(permissions.map(p => p.code).filter(code => !codes.has(code)));
    const note = BUILT_IN_NOTES[role.code];
    hhDetails({
      title: `What can the ${role.name} role do?`,
      bodyHtml: `
        ${note ? `<p class="review-muted role-preview-note">${escapeHtml(note)}</p>` : ''}
        <h4 class="role-preview-heading">Can</h4>
        ${codes.size ? `<ul class="role-preview-list role-preview-can">${canList(codes)}</ul>` : '<p class="review-muted">Nothing extra yet. Tick permissions for this role to give it tools.</p>'}
        ${cannot.size ? `<h4 class="role-preview-heading">Can't</h4><ul class="role-preview-list role-preview-cannot">${canList(cannot)}</ul>` : ''}`,
    });
  }

  // ---------- Create / edit / delete ----------

  function permissionCheckboxes(selected) {
    return modules().map(module => `
      <fieldset class="role-form-group">
        <legend>${escapeHtml(module)}</legend>
        ${permissions.filter(p => p.module === module).map(p => `
          <label class="role-form-check">
            <input type="checkbox" name="permissions" value="${escapeHtml(p.code)}" ${selected.has(p.code) ? 'checked' : ''}>
            <span><strong>${escapeHtml(p.name)}</strong><small>${escapeHtml(p.description)}</small></span>
          </label>`).join('')}
      </fieldset>`).join('');
  }

  async function editRole(role = null) {
    const result = await hhForm({
      title: role ? `Edit the ${role.name} role` : 'Create a new role',
      bodyHtml: `
        <div class="field">
          <label for="role-name">Role name <span class="required">*</span></label>
          <input type="text" id="role-name" name="name" maxlength="60" aria-required="true" placeholder="For example: Inventory Clerk" value="${role ? escapeHtml(role.name) : ''}">
        </div>
        <div class="field">
          <label for="role-description">What is this role for?</label>
          <textarea id="role-description" name="description" rows="2" maxlength="255" placeholder="For example: Helps keep the shared tool shed stocked.">${role ? escapeHtml(role.description || '') : ''}</textarea>
        </div>
        <p class="review-muted">Choose what people with this role are allowed to do:</p>
        ${permissionCheckboxes(new Set(role ? role.permissions : []))}`,
      confirmText: role ? 'Save role' : 'Create role',
      isValid: form => form.elements.name.value.trim().length >= 2,
      collect: form => ({
        name: form.elements.name.value.trim(),
        description: form.elements.description.value.trim(),
        permissions: [...form.querySelectorAll('input[name="permissions"]:checked')].map(input => input.value),
      }),
    });
    if (!result) return;

    const params = new URLSearchParams({ action: 'role_save', name: result.name, description: result.description });
    if (role) params.append('role_id', role.id);
    result.permissions.forEach(code => params.append('permissions[]', code));
    const data = await api(params, 'POST');
    if (!data.ok) {
      showToast(data.error || 'Could not save the role.', 'danger');
      return;
    }
    showToast(role ? `${result.name} saved.` : `${result.name} created. Use Members to give it to people.`);
    await load();
  }

  async function deleteRole(role) {
    if (role.members > 0) {
      await hhAlert({
        title: `${role.name} still has members`,
        message: `${role.members === 1 ? '1 person has' : `${role.members} people have`} this role. Open Members and remove it from them first, then you can delete the role.`,
      });
      return;
    }
    const confirmed = await hhConfirm({
      title: `Delete the ${role.name} role?`,
      message: 'Nobody has this role, so no one loses access. The role and its permissions are removed; its history stays in the audit log.',
      confirmText: 'Delete role',
      tone: 'danger',
    });
    if (!confirmed) return;
    const data = await api(new URLSearchParams({ action: 'role_delete', role_id: role.id }), 'POST');
    if (!data.ok) {
      showToast(data.error || 'Could not delete the role.', 'danger');
      return;
    }
    if (openRoleId === role.id) openRoleId = null;
    showToast(`${role.name} deleted.`);
    await load();
  }

  els.newRole.addEventListener('click', () => editRole());

  // ---------- Permission matrix ----------

  function renderMatrix() {
    const head = `<thead><tr><th scope="col" class="matrix-permission-col">Permission</th>${roles.map(role => `
      <th scope="col" class="matrix-role-col">${escapeHtml(role.name)}${role.built_in ? '' : '<small>Custom</small>'}</th>`).join('')}</tr></thead>`;
    const body = modules().map(module => `
      <tbody>
        <tr class="matrix-group"><th scope="colgroup" colspan="${roles.length + 1}">${escapeHtml(module)}</th></tr>
        ${permissions.filter(p => p.module === module).map(p => `
          <tr>
            <th scope="row" class="matrix-permission-col"><strong>${escapeHtml(p.name)}</strong><small>${escapeHtml(p.description)}</small></th>
            ${roles.map(role => `
              <td class="matrix-cell">
                <input type="checkbox" data-role="${role.id}" data-permission="${escapeHtml(p.code)}"
                  aria-label="${escapeHtml(`${role.name}: ${p.name}`)}"
                  ${role.locked || draft[role.id].has(p.code) ? 'checked' : ''} ${role.locked ? 'disabled title="Administrators always have every permission."' : ''}>
              </td>`).join('')}
          </tr>`).join('')}
      </tbody>`).join('');
    els.matrix.innerHTML = head + body;
    updateMatrixStatus();
  }

  // Roles whose ticks differ from what is saved: [{ role, added, removed }]
  function pendingChanges() {
    return roles.filter(role => !role.locked).map(role => {
      const saved = new Set(role.permissions);
      const now = draft[role.id];
      return {
        role,
        added: [...now].filter(code => !saved.has(code)),
        removed: [...saved].filter(code => !now.has(code)),
      };
    }).filter(change => change.added.length || change.removed.length);
  }

  function updateMatrixStatus() {
    const changes = pendingChanges();
    const count = changes.reduce((sum, c) => sum + c.added.length + c.removed.length, 0);
    els.matrixStatus.textContent = count === 0 ? 'No unsaved changes.' : `${count} unsaved ${count === 1 ? 'change' : 'changes'}.`;
    els.matrixStatus.classList.toggle('matrix-status-dirty', count > 0);
    els.matrixSave.disabled = count === 0;
    els.matrixUndo.disabled = count === 0;
    els.matrix.querySelectorAll('input[data-role]').forEach(input => {
      const role = roleById(input.dataset.role);
      input.closest('td').classList.toggle('matrix-cell-changed', !role.locked && role.permissions.includes(input.dataset.permission) !== input.checked);
    });
  }

  els.matrix.addEventListener('change', event => {
    const input = event.target.closest('input[data-role]');
    if (!input) return;
    const set = draft[input.dataset.role];
    if (input.checked) set.add(input.dataset.permission); else set.delete(input.dataset.permission);
    updateMatrixStatus();
  });

  els.matrixUndo.addEventListener('click', () => {
    draft = Object.fromEntries(roles.map(role => [role.id, new Set(role.permissions)]));
    renderMatrix();
  });

  els.matrixSave.addEventListener('click', async () => {
    const changes = pendingChanges();
    if (!changes.length) return;
    const list = changes.map(({ role, added, removed }) => `
      <li><strong>${escapeHtml(role.name)}</strong> (${role.members === 1 ? '1 person' : `${role.members} people`})
        ${added.length ? `<br><span class="matrix-change-add">Can now: ${added.map(code => escapeHtml(permissionName(code))).join(', ')}</span>` : ''}
        ${removed.length ? `<br><span class="matrix-change-remove">Can no longer: ${removed.map(code => escapeHtml(permissionName(code))).join(', ')}</span>` : ''}
      </li>`).join('');
    const choice = await hhDetails({
      title: 'Save these permission changes?',
      bodyHtml: `<ul class="role-preview-list">${list}</ul>
        <p class="review-muted">Everyone with these roles is affected straight away. Each change is saved in the audit log.</p>`,
      actions: [{ label: 'Save changes', value: 'save' }],
      closeText: 'Keep editing',
    });
    if (choice !== 'save') return;

    els.matrixSave.disabled = true;
    let failed = 0;
    for (const { role } of changes) {
      const params = new URLSearchParams({ action: 'role_save', role_id: role.id, name: role.name, description: role.description || '' });
      [...draft[role.id]].forEach(code => params.append('permissions[]', code));
      const data = await api(params, 'POST');
      if (!data.ok) {
        failed++;
        showToast(`${role.name}: ${data.error || 'could not save.'}`, 'danger');
      }
    }
    if (!failed) showToast('Permissions saved.');
    await load();
  });

  // ---------- Members ----------

  function closeMembers() {
    openRoleId = null;
    els.members.hidden = true;
  }

  async function showMembers(roleId, scroll = false) {
    const role = roleById(roleId);
    if (!role) return;
    openRoleId = role.id;
    els.members.hidden = false;
    els.membersTitle.textContent = `People with the ${role.name} role`;
    els.membersHelp.textContent = role.built_in
      ? `Everyone with a ${role.name.toLowerCase()} account has this role. Add or remove these people from the Manage Accounts pages.`
      : 'Give this role to gardeners or coordinators. A person can have several roles.';
    els.addPerson.hidden = role.built_in;
    els.personSearch.value = '';
    els.candidates.innerHTML = '';
    els.membersTable.innerHTML = '<tr class="admin-empty-row"><td colspan="5" class="text-muted">Loading...</td></tr>';
    if (scroll) els.members.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const data = await api(new URLSearchParams({ action: 'role_members', role_id: role.id }));
    if (openRoleId !== role.id) return;
    if (!data.ok) {
      els.membersTable.innerHTML = `<tr class="admin-empty-row"><td colspan="5" class="text-muted">${escapeHtml(data.error || 'Could not load members.')}</td></tr>`;
      return;
    }
    els.membersTable.innerHTML = data.members.map(m => `
      <tr>
        <td data-label="Name">${escapeHtml(m.Name)}</td>
        <td data-label="Email">${escapeHtml(m.Email)}</td>
        <td data-label="Account">${escapeHtml(ACCOUNT_LABELS[m.type] || m.type)}</td>
        <td data-label="Status">${accountStatusBadge(m.Status === 'Disabled' ? 'Disabled' : 'Active')}${m.Status !== 'Active' && m.Status !== 'Disabled' ? ` <span class="badge badge-neutral">${escapeHtml(m.Status)}</span>` : ''}</td>
        <td data-label="Actions">${role.built_in ? '<span class="text-muted">From account type</span>' : `
          <button type="button" class="btn btn-ghost btn-sm" data-remove-type="${escapeHtml(m.type)}" data-remove-id="${m.id}" data-remove-name="${escapeHtml(m.Name)}">Remove role</button>`}</td>
      </tr>`).join('') || `<tr class="admin-empty-row"><td colspan="5" class="text-muted">${role.built_in ? 'No accounts of this type yet.' : 'Nobody has this role yet. Search above to give it to someone.'}</td></tr>`;
  }

  els.membersClose.addEventListener('click', closeMembers);

  els.membersTable.addEventListener('click', async event => {
    const btn = event.target.closest('button[data-remove-id]');
    if (!btn) return;
    const role = roleById(openRoleId);
    const result = await hhForm({
      title: `Remove the ${role.name} role from ${btn.dataset.removeName}?`,
      bodyHtml: `
        <p class="review-muted">They lose what this role lets them do straight away. Their account and anything else they can do stay the same.</p>
        <div class="field">
          <label for="role-remove-reason">Reason (optional)</label>
          <textarea id="role-remove-reason" name="reason" rows="3" maxlength="1000" placeholder="For example: No longer helping with the tool shed."></textarea>
        </div>`,
      confirmText: 'Remove role',
      tone: 'danger',
      collect: form => ({ reason: form.elements.reason.value.trim() }),
    });
    if (!result) return;
    const data = await api(new URLSearchParams({
      action: 'role_unassign', role_id: role.id, account_type: btn.dataset.removeType, account_id: btn.dataset.removeId, reason: result.reason,
    }), 'POST');
    if (!data.ok) {
      showToast(data.error || 'Could not remove the role.', 'danger');
      return;
    }
    showToast(`${role.name} removed from ${btn.dataset.removeName}.`);
    await load();
  });

  // Search for someone to give the open role to
  let searchTimer = null;
  let searchNumber = 0;
  els.personSearch.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(searchPeople, 250);
  });

  async function searchPeople() {
    const q = els.personSearch.value.trim();
    if (q === '') {
      els.candidates.innerHTML = '';
      return;
    }
    const thisSearch = ++searchNumber;
    const data = await api(new URLSearchParams({ action: 'role_candidates', role_id: openRoleId, q }));
    if (thisSearch !== searchNumber) return;
    if (!data.ok) {
      els.candidates.innerHTML = `<li class="text-muted">${escapeHtml(data.error || 'Could not search.')}</li>`;
      return;
    }
    els.candidates.innerHTML = data.people.map(p => `
      <li>
        <span><strong>${escapeHtml(p.Name)}</strong> <small>${escapeHtml(p.Email)} · ${escapeHtml(ACCOUNT_LABELS[p.type] || p.type)}</small></span>
        <button type="button" class="btn btn-accent btn-sm" data-give-type="${escapeHtml(p.type)}" data-give-id="${p.id}" data-give-name="${escapeHtml(p.Name)}">Give role</button>
      </li>`).join('') || `<li class="text-muted">No one else matches "${escapeHtml(q)}". Only active gardeners and coordinators can be given roles.</li>`;
  }

  els.candidates.addEventListener('click', async event => {
    const btn = event.target.closest('button[data-give-id]');
    if (!btn) return;
    const role = roleById(openRoleId);
    btn.disabled = true;
    const data = await api(new URLSearchParams({
      action: 'role_assign', role_id: role.id, account_type: btn.dataset.giveType, account_id: btn.dataset.giveId,
    }), 'POST');
    if (!data.ok) {
      btn.disabled = false;
      showToast(data.error || 'Could not give the role.', 'danger');
      return;
    }
    showToast(`${btn.dataset.giveName} now has the ${role.name} role.`);
    await load();
  });

  load();
});
