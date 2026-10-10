// customer.js — Community Gardener dashboard logic

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

async function postAction(action, params) {
  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action, ...params }),
    });
    return await res.json();
  } catch (error) {
    console.error(`Request failed (${action}):`, error);
    return { ok: false, error: 'Network error. Please try again.' };
  }
}

// ---------- Plot ----------

async function loadPlot() {
  const res = await fetch('api.php?action=my_plot');
  const data = await res.json();
  const el = document.getElementById('plot-status');
  if (!el || !data.ok) return;

  if (data.plots.length > 0) {
    const pendingUnassignment = data.pending_application?.RequestType === 'Unassign';
    el.innerHTML = `
      <p class="text-muted" style="font-size: 0.85rem; margin: 0 0 8px;">Your currently assigned plots:</p>
      <ul style="margin: 0; padding-left: 18px;">
        ${data.plots.map(plot => `
          <li style="margin-bottom: 10px;">
            <strong>${escapeHtml(plot.Label)}</strong>
            <button class="btn btn-ghost btn-sm unassign-plot-btn" data-id="${plot.PltID}" data-label="${escapeHtml(plot.Label)}" type="button" style="margin-left: 8px;" ${pendingUnassignment ? 'disabled' : ''}>${pendingUnassignment ? 'Unassignment pending' : 'Request unassignment'}</button>
          </li>
        `).join('')}
      </ul>
      <p class="text-muted" style="font-size: 0.85rem;">Log your crops and resource needs using the panels alongside this one.</p>
    `;
    if (data.pending_application?.RequestType === 'Unassign') {
      el.insertAdjacentHTML('beforeend', `<p class="form-alert" style="display:block; background:#f3e9d6; color:#8a5a1e;">Your request to unassign <strong>${escapeHtml(data.pending_application.Label)}</strong> is pending Coordinator approval.</p>`);
    }
    document.querySelectorAll('.unassign-plot-btn').forEach(button => {
      button.addEventListener('click', async () => {
        if (!await hhConfirm({
          title: `Request unassignment of ${button.dataset.label}?`,
          message: 'Are you sure? Please ensure you have no actively planted crops or unreturned resources/equipment tied to this plot before unassigning. The coordinator will review your request; the plot stays assigned to you until it is approved.',
          confirmText: 'Send request',
          tone: 'danger',
        })) return;
        button.disabled = true;
        const result = await postAction('request_plot_unassignment', { plt_id: button.dataset.id });
        if (result.ok) {
          showToast('Unassignment request submitted.', 'success');
          loadPlot();
        } else {
          showToast(result.error || 'Could not submit unassignment request.', 'danger');
          button.disabled = false;
        }
      });
    });
    return;
  }

  if (data.pending_application) {
    el.innerHTML = `
      <p class="form-alert" style="display:block; background:#f3e9d6; color:#8a5a1e;">
        Application for <strong>${escapeHtml(data.pending_application.Label)}</strong> is pending Coordinator approval.
      </p>
    `;
    return;
  }

  if (data.available_plots.length === 0) {
    el.innerHTML = `<p class="text-muted" style="font-size: 0.9rem;">No plots available right now. Check back later.</p>`;
    return;
  }

  el.innerHTML = `
    <p class="text-muted" style="font-size: 0.9rem;">You don't have a plot yet. Apply for one below:</p>
    <div class="inline-form">
      <select id="plot-select" style="flex: 1;">
        ${data.available_plots.map(p => `<option value="${p.PltID}">${escapeHtml(p.Label)} · ${escapeHtml(p.Location || 'Location not set')} · ${p.AreaSqM ? `${escapeHtml(String(p.AreaSqM))} m²` : 'Area not set'}</option>`).join('')}
      </select>
      <button class="btn btn-accent btn-sm" id="apply-plot-btn">Apply</button>
    </div>
  `;

  const applyPlotBtn = document.getElementById('apply-plot-btn');
  if (applyPlotBtn) {
    applyPlotBtn.addEventListener('click', async () => {
      applyPlotBtn.disabled = true;
      const pltId = document.getElementById('plot-select').value;
      const result = await postAction('apply_plot', { plt_id: pltId });
      if (result.ok) {
        showToast('Application submitted!', 'success');
        loadPlot();
      } else {
        showToast(result.error || 'Could not submit application.', 'danger');
        applyPlotBtn.disabled = false;
      }
    });
  }
}

// ---------- Crop Log ----------

async function loadCropLog() {
  const res = await fetch('api.php?action=my_croplog');
  const data = await res.json();
  const el = document.getElementById('croplog-list');
  if (!el || !data.ok) return;

  if (data.logs.length === 0) {
    el.innerHTML = '<p class="crops-empty">No maintenance entries yet. Use <strong>Log maintenance</strong> to record care or a harvest.</p>';
    return;
  }

  el.innerHTML = data.logs.map(log => {
    const details = [
      log.Label ? escapeHtml(log.Label) : '',
      log.GardenPlantedDate ? `Planted ${escapeHtml(formatShortDate(log.GardenPlantedDate))}` : '',
    ].filter(Boolean).join(' · ');
    return `
    <article class="maintenance-log-entry">
      <div class="maintenance-log-entry-head">
        <strong>${escapeHtml(log.CropName)}</strong>
        <time class="text-muted" datetime="${escapeHtml(log.LoggedAt)}">${escapeHtml(formatShortDateTime(log.LoggedAt))}</time>
      </div>
      ${details ? `<p class="maintenance-log-plot">${details}</p>` : ''}
      ${log.MaintenanceNotes ? `<p class="maintenance-log-notes">${escapeHtml(log.MaintenanceNotes)}</p>` : ''}
      ${log.HarvestYield ? `<p class="maintenance-log-yield"><strong>Yield:</strong> ${escapeHtml(log.HarvestYield)}</p>` : ''}
    </article>`;
  }).join('');
}

const croplogForm = document.getElementById('croplog-form');
if (croplogForm) {
  croplogForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertEl = document.getElementById('croplog-alert');
    alertEl.hidden = true;
    const submitButton = croplogForm.querySelector('button[type="submit"]');

    const cropSelection = document.getElementById('crop-name').value.trim();
    if (cropSelection === '') {
      alertEl.textContent = 'Select a crop from your garden log.';
      alertEl.hidden = false;
      document.getElementById('crop-name').focus();
      return;
    }

    const cropOptions = document.getElementById('maintenance-crop-options');
    const selectedCrop = cropOptions && Array.from(cropOptions.options).find(option => option.value.toLowerCase() === cropSelection.toLowerCase());
    if (!selectedCrop) {
      alertEl.textContent = 'Choose a crop and planted date from your garden log.';
      alertEl.hidden = false;
      document.getElementById('crop-name').focus();
      return;
    }

    submitButton.disabled = true;
    try {
      const result = await postAction('croplog_create', {
        garden_plot_id: selectedCrop.dataset.plotId,
        notes: document.getElementById('crop-notes').value.trim(),
        yield: document.getElementById('crop-yield').value.trim(),
      });

      if (result.ok) {
        if (typeof showToast === 'function') showToast('Maintenance entry saved.', 'success');
        e.target.reset();
        await loadCropLog();
      } else {
        alertEl.textContent = result.error || 'Could not save entry.';
        alertEl.hidden = false;
      }
    } catch (error) {
      alertEl.textContent = 'Network error. Please try again.';
      alertEl.hidden = false;
    } finally {
      submitButton.disabled = false;
    }
  });
}

const plotBtn = document.getElementById('request-plot-btn');
const plotAlert = document.getElementById('plot-request-alert');
const plotSuccess = document.getElementById('plot-request-success');
const availablePlotsEl = document.getElementById('available-plots');

function renderAvailablePlots(plots) {
  if (!availablePlotsEl) return;
  if (plots.length === 0) {
    availablePlotsEl.innerHTML = '<p class="text-muted" style="font-size: 0.85rem;">No plots are available right now. Check back later.</p>';
    return;
  }

  availablePlotsEl.innerHTML = `
    <p class="text-muted" style="font-size: 0.85rem; margin: 0 0 8px;">Choose an available plot to request:</p>
    <div class="inline-form">
      <select id="more-plot-select" class="field-select" style="flex: 1;">
        ${plots.map(plot => `<option value="${plot.PltID}">${escapeHtml(plot.Label)} · ${escapeHtml(plot.Location || 'Location not set')} · ${plot.AreaSqM ? `${escapeHtml(String(plot.AreaSqM))} m²` : 'Area not set'}</option>`).join('')}
      </select>
      <button type="button" class="btn btn-accent btn-sm" id="more-plot-apply">Request</button>
    </div>
  `;

  const morePlotApply = document.getElementById('more-plot-apply');
  if (morePlotApply) {
    morePlotApply.addEventListener('click', async () => {
      morePlotApply.disabled = true;
      if (plotAlert) plotAlert.hidden = true;
      if (plotSuccess) plotSuccess.hidden = true;

      const result = await postAction('apply_plot', {
        plt_id: document.getElementById('more-plot-select').value,
      });

      if (result.ok) {
        if (plotSuccess) {
          plotSuccess.textContent = 'Plot request submitted for Coordinator approval.';
          plotSuccess.hidden = false;
        }
        availablePlotsEl.hidden = true;
        if (plotBtn) plotBtn.textContent = 'Request for more plots';
      } else {
        if (plotAlert) {
          plotAlert.textContent = result.error || 'Could not submit plot request.';
          plotAlert.hidden = false;
        }
        morePlotApply.disabled = false;
      }
    });
  }
}

if (plotBtn) {
  plotBtn.addEventListener('click', async () => {
    if (availablePlotsEl && !availablePlotsEl.hidden) {
      availablePlotsEl.hidden = true;
      plotBtn.textContent = 'Request for more plots';
      return;
    }

    if (plotAlert) plotAlert.hidden = true;
    if (plotSuccess) plotSuccess.hidden = true;
    plotBtn.disabled = true;

    try {
      const res = await fetch('api.php?action=my_plot');
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Could not load available plots.');

      renderAvailablePlots(data.available_plots);
      if (availablePlotsEl) availablePlotsEl.hidden = false;
      plotBtn.textContent = 'Hide available plots';
    } catch (err) {
      if (plotAlert) {
        plotAlert.textContent = err.message;
        plotAlert.hidden = false;
      }
    } finally {
      plotBtn.disabled = false;
    }
  });
}

// ---------- Customer Dashboard Overview Loader ----------

// "2026-09-27 18:37:39" or "2026-09-27" -> "Sep 27, 2026"
function formatShortDate(value) {
  if (!value) return '';
  const date = new Date(String(value).includes(' ') ? value.replace(' ', 'T') : `${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

// "2026-09-27 18:37:39" -> "Sep 27, 2026 · 6:37 PM"
function formatShortDateTime(value) {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return value;
  return `${formatShortDate(value)} · ${date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`;
}

async function loadCustomerDashboard() {
  const kpiPlots = document.getElementById('kpi-plots');
  if (!kpiPlots) return; // Exit if not on customer_dashboard.php

  try {
    const res = await fetch('api.php?action=customer_dashboard_overview');
    const data = await res.json();
    if (!data.ok) return;

    const noticePanel = document.getElementById('archive-notice-panel');
    if (noticePanel && data.archive_notice) {
      document.getElementById('archive-notice-reason').textContent = data.archive_notice.Reason;
      document.getElementById('archive-notice-details').textContent = data.archive_notice.Details;
      noticePanel.hidden = false;
    }

    // 1. Update KPI numbers
    kpiPlots.textContent = data.stats.active_plots;
    document.getElementById('kpi-resources').textContent = data.stats.pending_resources;
    document.getElementById('kpi-listings').textContent = data.stats.my_listings;

    // 2. Update Recent Maintenance
    const logsContainer = document.getElementById('recent-logs-list');
    if (logsContainer) {
      if (data.recent_logs.length === 0) {
        logsContainer.innerHTML = '<p class="dash-empty">No maintenance logged yet. <a href="customer_crops.php">Log your first crop</a>.</p>';
      } else {
        logsContainer.innerHTML = data.recent_logs.map(log => `
          <div class="dash-list-item">
            <div class="dash-list-row">
              <strong>${escapeHtml(log.CropName)}</strong>
              <span class="dash-list-date">${escapeHtml(formatShortDate(log.LoggedAt))}</span>
            </div>
            ${log.GardenPlantedDate ? `<div class="dash-list-meta">Planted ${escapeHtml(formatShortDate(log.GardenPlantedDate))}</div>` : ''}
            ${log.MaintenanceNotes ? `<div class="dash-list-meta">${escapeHtml(log.MaintenanceNotes)}</div>` : ''}
          </div>
        `).join('');
      }
    }

    // 3. Update New on the Exchange
    const exchangeContainer = document.getElementById('recent-exchange-list');
    if (exchangeContainer) {
      if (data.recent_exchange.length === 0) {
        exchangeContainer.innerHTML = '<p class="dash-empty">No active exchange listings right now.</p>';
      } else {
        exchangeContainer.innerHTML = data.recent_exchange.map(item => `
          <div class="dash-list-item">
            <div class="dash-list-row">
              <strong>${escapeHtml(item.ProduceName)}</strong>
              <span class="badge badge-neutral">${escapeHtml(item.Qty)}</span>
            </div>
            ${item.Description ? `<div class="dash-list-meta">${escapeHtml(item.Description)}</div>` : ''}
          </div>
        `).join('');
      }
    }
  } catch (err) {
    console.error('Failed to load dashboard overview:', err);
  }
}

async function loadCoordinatorApplication() {
  const panel = document.getElementById('coordinator-application-panel');
  if (!panel) return;
  const status = document.getElementById('coordinator-application-status');
  const form = document.getElementById('coordinator-application-form');
  panel.hidden = false;
  if (form.dataset.bound !== 'true') {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const submit = form.querySelector('button[type="submit"]');
      const availabilityError = document.getElementById('coordinator-availability-error');
      const availabilityDays = Array.from(form.querySelectorAll('[name="availability-day"]:checked'), input => input.value);
      if (availabilityError) availabilityError.hidden = availabilityDays.length > 0;
      if (!form.reportValidity()) return;
      if (!availabilityDays.length) {
        form.querySelector('[name="availability-day"]')?.focus();
        return;
      }

      submit.disabled = true;
      try {
        const result = await postAction('apply_coordinator', {
          shift: document.getElementById('coordinator-shift').value,
          availability_days: availabilityDays.join(','),
          motivation: document.getElementById('coordinator-motivation').value.trim(),
          gardening_experience: document.getElementById('coordinator-experience').value,
          leadership_experience: document.getElementById('coordinator-leadership').value.trim(),
          agree_duties: document.getElementById('coordinator-agree-duties').checked ? '1' : '0',
          agree_rules: document.getElementById('coordinator-agree-rules').checked ? '1' : '0',
        });
        if (!result.ok) {
          showToast(result.error || 'Could not submit your application.', 'danger');
        } else {
          showToast('Coordinator application submitted for review.', 'success');
          form.reset();
          if (availabilityError) availabilityError.hidden = true;
          await loadCoordinatorApplication();
        }
      } catch (error) {
        showToast('Could not submit your application. Please try again.', 'danger');
      } finally {
        submit.disabled = false;
      }
    });
    form.dataset.bound = 'true';
  }

  try {
    const data = await postAction('my_coordinator_application', {});
    if (!data.ok) throw new Error(data.error || 'Could not load your coordinator application.');
    if (data.approved) {
      status.innerHTML = '<p>You already have coordinator access. <a href="staff_dashboard.php">Open your coordinator workspace</a>.</p>';
      form.hidden = true;
    } else if (data.has_coordinator) {
      status.innerHTML = '<p>Your coordinator access is inactive. Please contact an administrator for assistance.</p>';
      form.hidden = true;
    } else if (data.application?.Status === 'Pending') {
      status.innerHTML = '<p>Your coordinator application is awaiting administrator review.</p>';
      form.hidden = true;
    } else {
      if (data.application?.Status === 'Rejected') {
        status.innerHTML = `<p class="form-alert dash-status-alert" style="display:block;">Your previous application was declined. Reason: ${escapeHtml(data.application.RejectionReason || 'No reason was provided.')}</p><p>You may submit a new application below.</p>`;
      }
      form.hidden = false;
    }
  } catch (error) {
    status.textContent = error.message;
    form.hidden = true;
  }
}

// Unified DOM Initializer
document.addEventListener('DOMContentLoaded', () => {
  loadCustomerDashboard();
  loadCoordinatorApplication();
  if (typeof loadPlot === 'function') loadPlot();
  if (typeof loadCropLog === 'function') loadCropLog();
  if (document.getElementById('plot-status')) {
    setInterval(loadPlot, 5000);
  }
});