document.addEventListener('DOMContentLoaded', () => {
    let activePlotCategory = 'All';

    async function loadPlots() {
        const listEl = document.getElementById('plots-list');
        if (listEl) {
            listEl.innerHTML = '<p class="crops-empty">Loading your garden log...</p>';
        }

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_my_plots' })
            });
            const data = await res.json();
            
            if (!data.ok) return;

            const cropOptions = document.getElementById('maintenance-crop-options');
            const maintenanceCropInput = document.getElementById('crop-name');
            const maintenanceCropHint = document.getElementById('maintenance-crop-hint');
            if (cropOptions) {
                const cropChoices = data.plots.filter(plot => plot.CropName && !['Harvested', 'Failed'].includes(plot.Status)).map(plot => {
                    const plantedDate = new Date(`${plot.PlantedDate}T00:00:00`).toLocaleDateString();
                    return {
                        plotId: plot.PlotID,
                        label: `${plot.CropName} · planted ${plantedDate}`
                    };
                });
                cropOptions.innerHTML = cropChoices.map(choice => `<option value="${escapeHtml(choice.label)}" data-plot-id="${Number(choice.plotId)}"></option>`).join('');
                if (maintenanceCropInput) maintenanceCropInput.disabled = cropChoices.length === 0;
                if (maintenanceCropHint && cropChoices.length === 0) {
                    maintenanceCropHint.textContent = 'Add an active crop to your garden log before recording maintenance.';
                }
            }

            if (data.plots.length === 0) {
                listEl.innerHTML = '<p class="crops-empty">You have not logged any crops yet. Use <strong>Log a crop</strong> to add your first planting.</p>';
                return;
            }

            const categoryFilterEl = document.getElementById('plots-category-filter');
            if (categoryFilterEl && !categoryFilterEl.value) {
                categoryFilterEl.value = activePlotCategory;
            }

            const filteredPlots = data.plots.filter(plot => {
                return activePlotCategory === 'All' || plot.Status === activePlotCategory;
            });

            if (filteredPlots.length === 0) {
                listEl.innerHTML = '<p class="crops-empty">No crops in this category yet.</p>';
                return;
            }

            listEl.innerHTML = filteredPlots.map(p => {
                const badgeClass = { Planted: 'badge-green', Harvested: 'badge-brown' }[p.Status] || 'badge-neutral';

                return `
                <div class="plot-item crop-row" data-search="${escapeHtml(p.CropName).toLowerCase()}">
                    <div class="crop-row-main">
                        <div class="crop-row-title">
                            <strong>${escapeHtml(p.CropName)}</strong>
                            <span class="badge ${badgeClass}">${escapeHtml(p.Status)}</span>
                        </div>
                        <span class="crop-row-meta">Planted ${escapeHtml(formatShortDate(p.PlantedDate))}</span>
                        ${p.Notes ? `<p class="crop-row-notes">${escapeHtml(p.Notes)}</p>` : ''}
                    </div>
                    <div class="crop-row-actions">
                        <label class="sr-only" for="crop-status-${Number(p.PlotID)}">Status of ${escapeHtml(p.CropName)}</label>
                        <select class="status-dropdown" id="crop-status-${Number(p.PlotID)}" data-id="${p.PlotID}">
                            <option value="Planted" ${p.Status === 'Planted' ? 'selected' : ''}>Planted</option>
                            <option value="Harvested" ${p.Status === 'Harvested' ? 'selected' : ''}>Harvested</option>
                            <option value="Failed" ${p.Status === 'Failed' ? 'selected' : ''}>Failed</option>
                        </select>
                    </div>
                </div>
                `;
            }).join('');

            // Attach listeners to status dropdowns
            document.querySelectorAll('.status-dropdown').forEach(select => {
                select.addEventListener('change', async (e) => {
                    const plotId = e.target.getAttribute('data-id');
                    const newStatus = e.target.value;
                    
                    try {
                        const res = await fetch('api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'update_crop_status', plot_id: plotId, status: newStatus })
                        });
                        const result = await res.json();
                        
                        if (result.ok) {
                            if (typeof showToast === 'function') showToast(`Status updated to ${newStatus}`, 'success');
                            if (typeof loadCropLog === 'function') loadCropLog();
                            loadPlots(); 
                        } else {
                            if (typeof showToast === 'function') showToast(result.error || 'Failed to update status.', 'danger');
                        }
                    } catch (err) {
                        console.error('Error updating status:', err);
                    }
                });
            });

            // Re-apply search filter
            const searchEl = document.getElementById('search-plots');
            if (searchEl && searchEl.value !== '') {
                const event = new Event('input');
                searchEl.dispatchEvent(event);
            }
            
        } catch (err) {
            console.error("Error loading plots:", err);
            document.getElementById('plots-list').innerHTML = '<p class="crops-empty crops-error">Failed to load crops.</p>';
        }
    }

    // Handle Add Crop Form Submission
    const addForm = document.getElementById('add-plot-form');
    const cropInput = document.getElementById('plot-crop-name');

    if (cropInput) {
        cropInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^A-Za-z\s]/g, '');
        });
    }

    if (addForm) {
        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = addForm.querySelector('button[type="submit"]');
            const cropName = document.getElementById('plot-crop-name').value.trim();
            const plantedDate = document.getElementById('plot-planted-date').value;
            const notes = document.getElementById('plot-notes').value.trim();

            if (!cropName || !plantedDate) {
                if (typeof showToast === 'function') {
                    showToast('Crop name and planted date are required.', 'danger');
                }
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Logging...';

            const listEl = document.getElementById('plots-list');
            if (listEl) {
                listEl.innerHTML = '<p class="crops-empty">Saving crop...</p>';
            }

            try {
                const res = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 
                        action: 'add_crop_log', 
                        crop_name: cropName, 
                        planted_date: plantedDate, 
                        notes: notes 
                    })
                });
                const result = await res.json();
                
                if (result.ok) {
                    if (typeof showToast === 'function') showToast('Crop logged successfully!', 'success');
                    addForm.reset();
                    await loadPlots();
                } else {
                    if (typeof showToast === 'function') showToast(result.error || 'Failed to log crop.', 'danger');
                }
            } catch (err) {
                console.error("Error logging crop:", err);
                if (typeof showToast === 'function') showToast('Network error while saving crop.', 'danger');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Log Crop';
            }
        });
    }

    // Search Filter Logic
    const searchEl = document.getElementById('search-plots');
    if (searchEl) {
        searchEl.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.plot-item').forEach(item => {
                const itemName = item.getAttribute('data-search');
                item.hidden = !itemName.includes(term);
            });
        });
    }

    const categoryFilterEl = document.getElementById('plots-category-filter');
    if (categoryFilterEl) {
        categoryFilterEl.addEventListener('change', (e) => {
            activePlotCategory = e.target.value;
            loadPlots();
        });
    }

    // -----------------------------------------
    // COMMUNITY MAP LOGIC
    // -----------------------------------------

    const seenPlotRejections = new Set();

    function notifyRejectedApplications(applications) {
        if (!Array.isArray(applications) || typeof showToast !== 'function') return;

        applications.forEach(application => {
            const appId = String(application.AppID);
            const storageKey = `harvesthub:plot-rejection:${appId}`;
            if (seenPlotRejections.has(appId)) return;

            try {
                if (localStorage.getItem(storageKey)) {
                    seenPlotRejections.add(appId);
                    return;
                }
                localStorage.setItem(storageKey, 'shown');
            } catch (error) {
                console.warn('Could not save plot rejection notification state:', error);
            }

            seenPlotRejections.add(appId);
            const reason = application.RejectionReason ? ` Reason: ${application.RejectionReason}` : '';
            showToast(`Your request for ${application.PlotName} was declined.${reason} You can request another available plot.`, 'danger');
        });
    }
    
    async function loadMap() {
        const gridEl = document.getElementById('garden-map-grid');
        if (!gridEl) return;
        const assignedListEl = document.getElementById('my-assigned-plots');

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_community_map' })
            });
            const data = await res.json();

            if (!data.ok) return;
            notifyRejectedApplications(data.rejected_applications);

            gridEl.innerHTML = '';

            if (!Array.isArray(data.plots) || data.plots.length === 0) {
                gridEl.innerHTML = '<p class="plt-empty">No community plots available right now.</p>';
                if (assignedListEl) assignedListEl.innerHTML = '<p class="plt-empty">You do not have a plot yet. Pick an available plot on the map to request one.</p>';
                return;
            }

            const assignedPlots = data.plots.filter(plot => Number(plot.IsMine) === 1);
            if (assignedListEl) {
                assignedListEl.innerHTML = assignedPlots.length ? assignedPlots.map(plot => `
                    <article class="assigned-plot-item">
                        <div class="assigned-plot-info">
                            <strong>${escapeHtml(plot.PlotName ?? 'Plot')}</strong>
                            <span>${plot.UnassignmentPending ? 'Unassignment request pending' : 'Assigned to you'}</span>
                        </div>
                        <button type="button" class="btn btn-sm request-unassignment-btn" data-plot-id="${Number(plot.PlotID)}" ${plot.UnassignmentPending ? 'disabled' : ''}>
                            ${plot.UnassignmentPending ? 'Request pending' : 'Request unassignment'}
                        </button>
                    </article>
                `).join('') : '<p class="plt-empty">You do not have a plot yet. Pick an available plot on the map to request one.</p>';

                assignedListEl.querySelectorAll('.request-unassignment-btn:not(:disabled)').forEach(button => {
                    button.addEventListener('click', () => requestPlotUnassignment(button.dataset.plotId, button));
                });
            }

            gridEl.innerHTML = data.plots.map(plot => {
                const isMine = Number(plot.IsMine) === 1;
                const hasRequested = Number(plot.ApplicationPending) === 1;
                const statusClass = isMine ? 'assigned' : hasRequested ? 'pending' : ['Available', 'Pending Approval'].includes(plot.Status) ? 'available' : 'occupied';
                const canRequest = !isMine && !Number(plot.ApplicationPending) && ['Available', 'Pending Approval'].includes(plot.Status);
                const accessibleStatus = isMine
                    ? 'assigned to you'
                    : hasRequested
                        ? 'your request is pending'
                        : canRequest
                            ? 'available to request'
                            : plot.Status;
                const accessibleName = `${plot.PlotName ?? 'Plot'}, ${accessibleStatus}`;

                return `
                    <button type="button" class="garden-map-plot ${statusClass}" aria-label="${escapeHtml(accessibleName)}" data-plot-id="${Number(plot.PlotID)}" data-plot-name="${escapeHtml(plot.PlotName ?? 'Plot')}" ${canRequest ? '' : 'disabled'}>
                        <span>${escapeHtml(plot.PlotName ?? 'Plot')}</span>
                        ${isMine ? '<small>Yours</small>' : ''}
                        ${hasRequested ? '<small>Your request pending</small>' : ''}
                    </button>
                `;
            }).join('');

            gridEl.querySelectorAll('.garden-map-plot.available:not(:disabled), .garden-map-plot.pending:not(:disabled)').forEach(button => {
                button.addEventListener('click', () => window.openPlotModal(button.dataset.plotId, button.dataset.plotName));
            });

        } catch (err) {
            console.error("Error loading map:", err);
            const gridEl = document.getElementById('garden-map-grid');
            if (gridEl) {
                gridEl.innerHTML = '<p class="plt-empty">Failed to load community map.</p>';
            }
        }
    }

    async function requestPlotUnassignment(plotId, button) {
        if (!window.confirm('Send an unassignment request to the coordinator? The plot stays assigned to you until it is approved.')) return;
        button.disabled = true;
        button.textContent = 'Sending...';

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'request_plot_unassignment', plt_id: plotId })
            });
            const result = await res.json();
            if (result.ok) {
                if (typeof showToast === 'function') showToast('Unassignment request sent to the coordinator.', 'success');
                await loadMap();
            } else {
                if (typeof showToast === 'function') showToast(result.error || 'Could not request unassignment.', 'danger');
                button.disabled = false;
                button.textContent = 'Request unassignment';
            }
        } catch (err) {
            console.error('Error requesting plot unassignment:', err);
            if (typeof showToast === 'function') showToast('Could not send the unassignment request.', 'danger');
            button.disabled = false;
            button.textContent = 'Request unassignment';
        }
    }

    // Modal Logic
    const plotModal = document.getElementById('plot-modal');
    
    // Attach function to window so the inline onclick="" can find it
    window.openPlotModal = function(id, name) {
        document.getElementById('modal-plot-id').value = id;
        document.getElementById('modal-plot-name').textContent = name;
        plotModal.style.display = 'flex';
    };

    document.getElementById('cancel-plot-btn')?.addEventListener('click', () => {
        plotModal.style.display = 'none';
    });

    document.getElementById('confirm-plot-btn')?.addEventListener('click', async (e) => {
        const btn = e.target;
        btn.disabled = true;
        btn.textContent = 'Sending...';

        const plotId = document.getElementById('modal-plot-id').value;

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'request_garden_plot', plot_id: plotId })
            });
            const result = await res.json();
            
            if (result.ok) {
                if (typeof showToast === 'function') showToast('Plot request sent to coordinator!', 'success');
                plotModal.style.display = 'none';
                loadMap(); // Refresh map to show it turn yellow (Pending)
            } else {
                if (typeof showToast === 'function') showToast(result.error || 'Failed to request plot.', 'danger');
            }
        } catch (err) {
            console.error("Error requesting plot:", err);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Send request';
        }
    });

    // Helper for fetch body
    function newSearchParams(params) {
        return new URLSearchParams(params);
    }

    // Initialize
    if (document.getElementById('plots-list')) loadPlots();
    if (document.getElementById('garden-map-grid')) {
        loadMap();
        window.setInterval(() => {
            if (!document.hidden) loadMap();
        }, 15000);
    }
});