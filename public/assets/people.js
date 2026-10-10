// User Activity list (admin_activity.php) and profile pages (admin_user.php).
// Relies on escapeHtml, formatAdminDate, formatAdminDateTime, timeAgo,
// reviewField, profileLink, requestRows and accountStatusBadge from admin.js.
(function () {
  const DAY = 86400000;

  function statusBadge(status) {
    if (status === 'Archived') return '<span class="badge badge-neutral">Archived</span>';
    if (status === 'Demoted') return '<span class="badge badge-neutral">Role removed</span>';
    return accountStatusBadge(status);
  }

  function roleBadges(roles) {
    return roles.map(role => `<span class="badge ${['Gardener', 'Coordinator', 'Administrator'].includes(role) ? 'badge-neutral' : 'badge-brown'} activity-role">${escapeHtml(role)}</span>`).join(' ');
  }

  function lastSignIn(value) {
    if (!value) return '<span class="text-muted">None recorded</span>';
    return `<span class="audit-when">${escapeHtml(formatAdminDateTime(value))}</span><span class="audit-ago">${escapeHtml(timeAgo(value))}</span>`;
  }

  function trackingNote(since) {
    return since
      ? `Sign-ins are counted from ${formatAdminDate(since)}, when HarvestHub started keeping its audit log. Earlier sign-ins aren't included.`
      : 'No sign-ins have been recorded yet.';
  }

  async function getJson(params) {
    try {
      const res = await fetch(`api.php?${new URLSearchParams(params)}`);
      return await res.json();
    } catch (e) {
      return { ok: false, error: 'Could not reach HarvestHub. Check your connection and try again.' };
    }
  }

  // ---------- User Activity list ----------

  async function initActivityList() {
    const table = document.getElementById('activity-table');
    if (!table) return;
    const els = {
      search: document.getElementById('activity-search'),
      type: document.getElementById('activity-type'),
      inactive: document.getElementById('activity-inactive'),
      status: document.getElementById('activity-status'),
      count: document.getElementById('activity-count'),
      stats: document.getElementById('activity-stats'),
      note: document.getElementById('activity-tracking-note'),
    };

    const data = await getJson({ action: 'user_activity' });
    if (!data.ok) {
      table.innerHTML = `<tr class="admin-empty-row"><td colspan="6" class="text-muted">${escapeHtml(data.error || 'Could not load people.')}</td></tr>`;
      els.count.textContent = '';
      return;
    }
    const people = data.people;
    els.note.textContent = trackingNote(data.tracking_since);

    // Summary cards (current accounts only)
    const current = people.filter(p => p.status === 'Active' || p.status === 'Disabled');
    const since = days => current.filter(p => p.last_login && Date.now() - new Date(p.last_login.replace(' ', 'T')).getTime() < days * DAY).length;
    const cards = [
      [since(1), 'Signed in today'],
      [since(7), 'Signed in this week'],
      [current.filter(p => !p.last_login || Date.now() - new Date(p.last_login.replace(' ', 'T')).getTime() >= 30 * DAY).length, 'Inactive 30+ days'],
      [current.filter(p => p.status === 'Disabled').length, 'Locked accounts'],
    ];
    els.stats.innerHTML = cards.map(([value, label]) => `<div class="stat-card"><div class="stat-value">${value}</div><div class="stat-label">${escapeHtml(label)}</div></div>`).join('');

    function render() {
      const q = els.search.value.trim().toLowerCase();
      const now = Date.now();
      const rows = people.filter(p => {
        if (q && ![p.name, p.email, p.location].some(v => String(v || '').toLowerCase().includes(q))) return false;
        if (els.type.value && !p.roles.includes(els.type.value)) return false;
        if (els.status.value === 'current' && !['Active', 'Disabled'].includes(p.status)) return false;
        if (els.status.value && els.status.value !== 'current' && p.status !== els.status.value) return false;
        if (els.inactive.value === 'never') return !p.last_login;
        if (els.inactive.value) {
          return !p.last_login || now - new Date(p.last_login.replace(' ', 'T')).getTime() >= Number(els.inactive.value) * DAY;
        }
        return true;
      });
      els.count.textContent = `${rows.length} ${rows.length === 1 ? 'person' : 'people'}`;
      table.innerHTML = rows.map(p => `
        <tr>
          <td data-label="Name">${profileLink(p.type, p.id, p.name)}<span class="audit-ago">${escapeHtml(p.email)}</span></td>
          <td data-label="Roles">${roleBadges(p.roles)}</td>
          <td data-label="Status">${statusBadge(p.status)}</td>
          <td data-label="Last signed in">${lastSignIn(p.last_login)}</td>
          <td data-label="Sign-ins">${p.login_count}</td>
          <td data-label="Failed sign-ins">${p.failed_total}${p.failed_now ? ` <span class="badge badge-danger" title="Wrong passwords since their last successful sign-in. 3 locks the account.">${p.failed_now} in a row</span>` : ''}</td>
        </tr>`).join('') || '<tr class="admin-empty-row"><td colspan="6" class="text-muted">No one matches these filters.</td></tr>';
    }

    [els.search, els.type, els.inactive, els.status].forEach(el => el.addEventListener('input', render));
    document.getElementById('activity-reset').addEventListener('click', () => {
      els.search.value = '';
      els.type.value = '';
      els.inactive.value = '';
      els.status.value = 'current';
      render();
    });
    // Allow links such as admin_activity.php?inactive=30
    const preset = new URLSearchParams(location.search).get('inactive');
    if (preset && [...els.inactive.options].some(o => o.value === preset)) els.inactive.value = preset;
    render();
  }

  // ---------- Profile page ----------

  const TIMELINE_AREAS = { accounts: 'Account', roles: 'Roles', plots: 'Plot', resources: 'Resource', crops: 'Crop', exchange: 'Exchange', admin: 'Admin' };
  const CROP_BADGES = { Planted: 'badge-green', Growing: 'badge-green', Harvested: 'badge-brown', Failed: 'badge-danger' };
  const ROLE_CHANGES = { granted: 'Given', removed: 'Taken away', demoted: 'Removed' };

  async function initProfile() {
    const main = document.querySelector('.profile-page');
    if (!main) return;
    const nameEl = document.getElementById('profile-name');
    const data = await getJson({ action: 'user_profile', type: main.dataset.profileType, id: main.dataset.profileId });
    if (!data.ok) {
      nameEl.textContent = 'Profile not found';
      document.getElementById('profile-subtitle').textContent = data.error || 'This account could not be found.';
      document.querySelectorAll('.profile-panel, #profile-stats').forEach(el => { el.hidden = true; });
      return;
    }
    const p = data.person;
    const s = data.summary;
    document.title = `HarvestHub — ${p.name}`;
    nameEl.textContent = p.name;
    document.getElementById('profile-subtitle').innerHTML = `${roleBadges(p.roles)} ${statusBadge(p.status)}`;

    // Summary cards
    const cards = [];
    if (p.gardener_id) {
      cards.push([s.plots.length, s.plots.length === 1 ? 'Plot held' : 'Plots held', s.plots.join(', ')]);
      cards.push([s.crops_growing, 'Crops growing', `${s.crops_total} logged in total`]);
      cards.push([s.maintenance_entries, 'Maintenance entries', '']);
      cards.push([s.items_borrowed, 'Items borrowed now', '']);
      cards.push([s.listings_active, 'Exchange listings', `${s.listings_total} posted, ${s.claims_made} claims made`]);
      cards.push([data.requests.length, 'Requests made', `${data.requests.filter(r => r.Outcome === 'Pending').length} pending`]);
    }
    if (p.coord_id) {
      cards.push([s.plot_requests_decided, 'Plot requests decided', 'as coordinator']);
      cards.push([s.resource_requests_decided, 'Resource requests decided', 'as coordinator']);
    }
    document.getElementById('profile-stats').innerHTML = cards.map(([value, label, hint]) => `
      <div class="stat-card"><div class="stat-value">${escapeHtml(String(value))}</div><div class="stat-label">${escapeHtml(label)}</div>${hint ? `<div class="stat-hint">${escapeHtml(hint)}</div>` : ''}</div>`).join('');

    // Details
    const accountLabel = { gardener: 'Gardener', coordinator: 'Coordinator', admin: 'Administrator' }[p.type];
    document.getElementById('profile-details').innerHTML = [
      reviewField('Email', p.email),
      reviewField('Location', p.location),
      reviewField('Age', data.details.age ?? 'Not provided'),
      reviewField('Account type', accountLabel + (p.type === 'gardener' && p.coord_id ? ' (also a coordinator)' : '')),
      reviewField('Member since', data.details.member_since ? formatAdminDate(data.details.member_since) : 'Not recorded'),
      data.details.coordinator ? reviewField('Coordinator shift', `${data.details.coordinator.Shift} (${data.details.coordinator.Status === 'Demoted' ? 'role removed' : data.details.coordinator.Status.toLowerCase()})`) : '',
    ].join('');

    // Sign-in
    document.getElementById('profile-signin').innerHTML = [
      `<div><dt>Last signed in</dt><dd>${p.last_login ? `${escapeHtml(formatAdminDateTime(p.last_login))} <span class="audit-ago">${escapeHtml(timeAgo(p.last_login))}</span>` : 'None recorded'}</dd></div>`,
      reviewField('Times signed in', p.login_count),
      reviewField('Failed sign-ins', p.failed_total + (p.last_failed ? ` (last ${formatAdminDate(p.last_failed)})` : '')),
      reviewField('Wrong passwords in a row', p.failed_now ? `${p.failed_now} of 3${p.status === 'Disabled' ? ' (locked)' : ''}` : 'None'),
    ].join('');
    document.getElementById('profile-tracking-note').textContent = trackingNote(data.tracking_since);

    // Roles and their history
    document.getElementById('profile-roles').innerHTML = `
      <p class="profile-role-list">${roleBadges(p.roles)}</p>
      ${data.role_history.length ? `<ul class="review-history">${data.role_history.map(h => `
        <li><strong>${escapeHtml(h.RoleName)}: ${escapeHtml(ROLE_CHANGES[h.ChangeType] || h.ChangeType)}</strong>
          · ${escapeHtml(formatAdminDate(h.ChangedAt))}${h.ChangedByName ? ` by ${escapeHtml(h.ChangedByName)}` : ''}
          ${h.ReasonCategory || h.ReasonDetails ? `<br><span class="text-muted">${escapeHtml([h.ReasonCategory, h.ReasonDetails].filter(Boolean).join(': '))}</span>` : ''}</li>`).join('')}</ul>`
        : '<p class="review-muted">No role changes recorded.</p>'}`;

    // Crops and maintenance (gardeners)
    if (p.gardener_id) {
      document.getElementById('profile-crops-section').hidden = false;
      document.getElementById('profile-crops').innerHTML = `
        <h3 class="profile-subheading">Crops</h3>
        ${data.crops.length ? `<div class="table-wrap"><table class="data-table admin-responsive-table">
          <thead><tr><th>Crop</th><th>Planted</th><th>Expected harvest</th><th>Status</th><th>Notes</th></tr></thead>
          <tbody>${data.crops.map(c => `<tr>
            <td data-label="Crop">${escapeHtml(c.CropName)}</td>
            <td data-label="Planted">${escapeHtml(formatAdminDate(c.PlantedDate) || '—')}</td>
            <td data-label="Expected harvest">${escapeHtml(formatAdminDate(c.EstHarvestDate) || '—')}</td>
            <td data-label="Status"><span class="badge ${CROP_BADGES[c.Status] || 'badge-neutral'}">${escapeHtml(c.Status)}</span></td>
            <td data-label="Notes">${escapeHtml(c.Notes || '—')}</td></tr>`).join('')}</tbody></table></div>`
          : '<p class="review-muted">No crops logged yet.</p>'}
        <h3 class="profile-subheading">Maintenance history</h3>
        ${data.maintenance.length ? `<ul class="review-history">${data.maintenance.map(m => `
          <li><strong>${escapeHtml(m.CropName)}</strong> · ${escapeHtml(formatAdminDateTime(m.LoggedAt))}${m.PlotLabel ? ` · ${escapeHtml(m.PlotLabel)}` : ''}
            ${m.MaintenanceNotes ? `<br>${escapeHtml(m.MaintenanceNotes)}` : ''}${m.HarvestYield ? `<br><span class="text-muted">Yield: ${escapeHtml(m.HarvestYield)}</span>` : ''}</li>`).join('')}</ul>`
          : '<p class="review-muted">No maintenance logged yet.</p>'}`;

      // Requests
      document.getElementById('profile-requests-section').hidden = false;
      const requestsBody = document.getElementById('profile-requests');
      const filter = document.getElementById('profile-requests-filter');
      const showRows = pagedRows(requestsBody.closest('.table-wrap'), 10, rows => {
        requestsBody.innerHTML = requestRows(rows, false)
          || '<tr class="admin-empty-row"><td colspan="5" class="text-muted">No requests here.</td></tr>';
      });
      const renderRequests = () => showRows(data.requests.filter(r => !filter.value || r.Outcome === filter.value));
      filter.addEventListener('change', renderRequests);
      renderRequests();
    }

    // Timeline
    const timelineEl = document.getElementById('profile-timeline');
    pagedRows(timelineEl, 25, items => { timelineEl.innerHTML = items.map(t => `
      <li class="profile-timeline-item">
        <time datetime="${escapeHtml(String(t.OccurredAt).replace(' ', 'T'))}">${escapeHtml(formatAdminDateTime(t.OccurredAt))}</time>
        <span class="badge badge-neutral">${escapeHtml(TIMELINE_AREAS[t.Area] || t.Area)}</span>
        <p>${escapeHtml(t.Text)}${t.Detail ? `<span class="text-muted"> — ${escapeHtml(t.Detail)}</span>` : ''}</p>
      </li>`).join('') || '<li class="review-muted">Nothing recorded yet.</li>'; })(data.timeline);
  }

  document.addEventListener('DOMContentLoaded', () => {
    initActivityList();
    initProfile();
  });
})();
