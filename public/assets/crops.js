document.addEventListener('DOMContentLoaded', () => {
  const list = document.getElementById('plots-list');
  const addDialog = document.getElementById('add-crop-dialog');
  const addForm = document.getElementById('add-plot-form');
  if (!list || !addDialog || !addForm) return;

  let plots = [];
  let logs = [];
  let expandedPlotId = null;

  const localDateValue = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  const dateLabel = value => {
    if (!value) return '';
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
    return Number.isNaN(date.getTime())
      ? String(value)
      : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  };

  const dateTimeLabel = value => {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime())
      ? String(value)
      : date.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
  };

  const statusBadge = status => {
    const classes = { Planted: 'badge-green', Growing: 'badge-green', Harvested: 'badge-brown', Failed: 'badge-neutral' };
    return `<span class="badge ${classes[status] || 'badge-neutral'}">${escapeHtml(status)}</span>`;
  };

  async function post(action, values = {}) {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action, ...values }),
    });
    const result = await response.json();
    if (!result.ok) throw new Error(result.error || 'The request could not be completed.');
    return result;
  }

  function renderHistory(plotId) {
    const entries = logs.filter(log => Number(log.GardenPlotID) === Number(plotId));
    if (!entries.length) return '<p class="crop-history-empty">No care or harvest updates recorded yet.</p>';

    return `<div class="crop-history">
      ${entries.map(entry => `
        <article class="crop-history-entry">
          <div class="crop-history-entry-head">
            <strong>${entry.HarvestYield ? 'Harvest recorded' : 'Care recorded'}</strong>
            <time datetime="${escapeHtml(String(entry.LoggedAt || '').replace(' ', 'T'))}">${escapeHtml(dateTimeLabel(entry.LoggedAt))}</time>
          </div>
          ${entry.MaintenanceNotes ? `<p>${escapeHtml(entry.MaintenanceNotes)}</p>` : ''}
          ${entry.HarvestYield ? `<p class="crop-history-yield">Yield: ${escapeHtml(entry.HarvestYield)}</p>` : ''}
        </article>
      `).join('')}
    </div>`;
  }

  function render() {
    const statusFilter = document.getElementById('plots-category-filter')?.value || 'All';
    const searchTerm = (document.getElementById('search-plots')?.value || '').trim().toLowerCase();
    const visiblePlots = plots.filter(plot => {
      const matchesStatus = statusFilter === 'All' || plot.Status === statusFilter;
      const matchesSearch = String(plot.CropName || '').toLowerCase().includes(searchTerm);
      return matchesStatus && matchesSearch;
    });

    if (!plots.length) {
      list.innerHTML = '<p class="crops-empty">You have not planted any crops yet. Choose <strong>+ Plant a Crop</strong> to start your garden journal.</p>';
      return;
    }
    if (!visiblePlots.length) {
      list.innerHTML = '<p class="crops-empty">No crops match your search or status filter.</p>';
      return;
    }

    list.innerHTML = visiblePlots.map(plot => {
      const plotId = Number(plot.PlotID);
      const open = plotId === Number(expandedPlotId);
      const panelId = `crop-panel-${plotId}`;
      return `
        <article class="crop-accordion" data-open="${open}">
          <button type="button" class="crop-accordion-toggle" aria-expanded="${open}" aria-controls="${panelId}" data-crop-toggle="${plotId}">
            <span class="crop-accordion-info">
              <span class="crop-accordion-title">
                <strong>${escapeHtml(plot.CropName)}</strong>
                ${statusBadge(plot.Status)}
              </span>
              <span class="crop-row-meta">Planted ${escapeHtml(dateLabel(plot.PlantedDate))}</span>
            </span>
            <span class="crop-accordion-chevron" aria-hidden="true">⌄</span>
          </button>
          <div class="crop-accordion-panel" id="${panelId}" role="region" aria-label="${escapeHtml(plot.CropName)} details" aria-hidden="${!open}" ${open ? '' : 'inert'}>
            <div class="crop-accordion-panel-inner">
              <div class="crop-expanded-content">
                <form class="crop-update-form" data-crop-form="${plotId}">
                  <div class="field">
                    <label for="crop-status-${plotId}">Status</label>
                    <select id="crop-status-${plotId}" name="status" required>
                      <option value="Planted" ${plot.Status === 'Planted' ? 'selected' : ''}>Planted</option>
                      <option value="Growing" ${plot.Status === 'Growing' ? 'selected' : ''}>Growing</option>
                      <option value="Harvested" ${plot.Status === 'Harvested' ? 'selected' : ''}>Harvested</option>
                      <option value="Failed" ${plot.Status === 'Failed' ? 'selected' : ''}>Failed</option>
                    </select>
                  </div>
                  <div class="field">
                    <label for="crop-date-${plotId}">Update date</label>
                    <input id="crop-date-${plotId}" type="date" name="logged_date" value="${localDateValue()}" required>
                  </div>
                  <div class="field crop-update-notes">
                    <label for="crop-notes-${plotId}">Maintenance notes</label>
                    <textarea id="crop-notes-${plotId}" name="notes" maxlength="1000" rows="1" placeholder="Watered, pruned, treated for pests..."></textarea>
                  </div>
                  <div class="field">
                    <label for="crop-yield-${plotId}">Yield <span class="field-optional">(optional)</span></label>
                    <input id="crop-yield-${plotId}" type="text" name="yield" maxlength="60" placeholder="e.g., 2 kg">
                  </div>
                  <button type="submit" class="btn btn-accent btn-sm">Save Update</button>
                </form>
                ${plot.Notes ? `<p class="crop-details-notes"><strong>Initial notes:</strong> ${escapeHtml(plot.Notes)}</p>` : ''}
                <h3 class="crop-history-heading">Maintenance history</h3>
                ${renderHistory(plotId)}
              </div>
            </div>
          </div>
        </article>
      `;
    }).join('');
  }

  async function loadJournal() {
    list.innerHTML = '<p class="crops-empty">Loading your garden journal...</p>';
    try {
      const [plotData, logData] = await Promise.all([
        post('get_my_plots'),
        post('my_croplog'),
      ]);
      plots = Array.isArray(plotData.plots) ? plotData.plots : [];
      logs = Array.isArray(logData.logs) ? logData.logs : [];
      render();
    } catch (error) {
      console.error('Could not load garden journal:', error);
      list.innerHTML = `<p class="crops-empty crops-error">${escapeHtml(error.message || 'Could not load your garden journal. Please try again.')}</p>`;
    }
  }

  document.getElementById('open-add-crop').addEventListener('click', () => {
    document.getElementById('plot-planted-date').value = localDateValue();
    addDialog.showModal();
    document.getElementById('plot-crop-name').focus();
  });
  document.getElementById('cancel-add-crop').addEventListener('click', () => addDialog.close());

  addForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (!addForm.reportValidity()) return;
    const button = addForm.querySelector('[type="submit"]');
    button.disabled = true;
    button.textContent = 'Planting...';
    try {
      await post('add_crop_log', {
        crop_name: document.getElementById('plot-crop-name').value.trim(),
        planted_date: document.getElementById('plot-planted-date').value,
        notes: document.getElementById('plot-notes').value.trim(),
      });
      addForm.reset();
      addDialog.close();
      if (typeof showToast === 'function') showToast('Crop added to your garden journal.', 'success');
      await loadJournal();
    } catch (error) {
      if (typeof showToast === 'function') showToast(error.message || 'Could not add the crop.', 'danger');
    } finally {
      button.disabled = false;
      button.textContent = 'Plant crop';
    }
  });

  list.addEventListener('click', event => {
    const toggle = event.target.closest('[data-crop-toggle]');
    if (!toggle) return;
    const selectedId = Number(toggle.dataset.cropToggle);
    expandedPlotId = expandedPlotId === selectedId ? null : selectedId;
    render();
  });

  list.addEventListener('submit', async event => {
    const form = event.target.closest('[data-crop-form]');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('[type="submit"]');
    const fields = new FormData(form);
    const values = {
      plot_id: form.dataset.cropForm,
      status: String(fields.get('status') || ''),
      logged_date: String(fields.get('logged_date') || ''),
      notes: String(fields.get('notes') || '').trim(),
      yield: String(fields.get('yield') || '').trim(),
    };
    button.disabled = true;
    button.textContent = 'Saving...';
    try {
      await post('garden_journal_update', values);
      if (typeof showToast === 'function') showToast('Garden journal updated.', 'success');
      await loadJournal();
    } catch (error) {
      if (typeof showToast === 'function') showToast(error.message || 'Could not save this update.', 'danger');
    } finally {
      button.disabled = false;
      button.textContent = 'Save Update';
    }
  });

  document.getElementById('plots-category-filter').addEventListener('change', render);
  document.getElementById('search-plots').addEventListener('input', render);

  loadJournal();
});
