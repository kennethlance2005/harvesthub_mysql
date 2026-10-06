document.addEventListener('DOMContentLoaded', () => {
    let activePlotCategory = 'All';

    async function loadPlots() {
        const listEl = document.getElementById('plots-list');
        if (listEl) {
            listEl.innerHTML = '<p class="empty-state">Loading your garden plots...</p>';
        }

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_my_plots' })
            });
            const data = await res.json();
            
            if (!data.ok) return;

            if (data.plots.length === 0) {
                listEl.innerHTML = '<p class="empty-state">You have not logged any crops yet.</p>';
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
                listEl.innerHTML = '<p class="empty-state">No crops in this category yet.</p>';
                return;
            }

            listEl.innerHTML = filteredPlots.map(p => {
                let badgeClass = 'badge-neutral';
                if (p.Status === 'Harvested') badgeClass = 'badge-brown';

                return `
                <div class="plot-item" data-search="${escapeHtml(p.CropName).toLowerCase()}" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid #e2e8f0;">
                    <div style="flex: 1; padding-right: 16px;">
                        <div style="margin-bottom: 4px;">
                            <strong style="font-size: 1.1rem;">${escapeHtml(p.CropName)}</strong>
                            <span class="badge ${badgeClass}" style="margin-left: 8px; font-size: 0.75rem;">${escapeHtml(p.Status)}</span>
                        </div>
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 8px;">
                            <strong>Planted:</strong> ${new Date(p.PlantedDate).toLocaleDateString()}
                        </div>
                        ${p.Notes ? `<p style="margin: 0; font-size: 0.85rem; color: #64748b; font-style: italic;">"${escapeHtml(p.Notes)}"</p>` : ''}
                    </div>
                    <div>
                        <select class="status-dropdown" data-id="${p.PlotID}" style="padding: 6px; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff;">
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
                            loadPlots(); 
                        } else {
                            if (typeof showToast === 'function') showToast(result.error || 'Failed to update status.', 'error');
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
            document.getElementById('plots-list').innerHTML = '<p class="empty-state" style="color: #d9534f;">Failed to load crops.</p>';
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
                    showToast('Crop name and planted date are required.', 'error');
                }
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Logging...';

            const listEl = document.getElementById('plots-list');
            if (listEl) {
                listEl.innerHTML = '<p class="empty-state">Saving crop...</p>';
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
                    if (typeof showToast === 'function') showToast(result.error || 'Failed to log crop.', 'error');
                }
            } catch (err) {
                console.error("Error logging crop:", err);
                if (typeof showToast === 'function') showToast('Network error while saving crop.', 'error');
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
                item.style.display = itemName.includes(term) ? 'flex' : 'none';
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

            gridEl.innerHTML = '';

            if (!Array.isArray(data.plots) || data.plots.length === 0) {
                gridEl.innerHTML = '<p class="text-muted" style="grid-column: span 4; text-align: center;">No community plots available right now.</p>';
                if (assignedListEl) assignedListEl.innerHTML = '<p class="text-muted">You do not have any assigned plots.</p>';
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
                `).join('') : '<p class="text-muted">You do not have any assigned plots.</p>';

                assignedListEl.querySelectorAll('.request-unassignment-btn:not(:disabled)').forEach(button => {
                    button.addEventListener('click', () => requestPlotUnassignment(button.dataset.plotId, button));
                });
            }

            gridEl.innerHTML = data.plots.map(plot => {
                const isMine = Number(plot.IsMine) === 1;
                const statusClass = isMine ? 'assigned' : plot.Status === 'Available' ? 'available' : plot.Status === 'Pending Approval' ? 'pending' : 'occupied';
                const accessibleName = `${plot.PlotName ?? 'Plot'}, ${isMine ? 'assigned to you' : plot.Status}`;
                const isAvailable = plot.Status === 'Available';

                return `
                    <button type="button" class="garden-map-plot ${statusClass}" aria-label="${escapeHtml(accessibleName)}" data-plot-id="${Number(plot.PlotID)}" data-plot-name="${escapeHtml(plot.PlotName ?? 'Plot')}" ${isAvailable ? '' : 'disabled'}>
                        <span>${escapeHtml(plot.PlotName ?? 'Plot')}</span>
                        ${isMine ? '<small>Yours</small>' : ''}
                    </button>
                `;
            }).join('');

            gridEl.querySelectorAll('.garden-map-plot.available').forEach(button => {
                button.addEventListener('click', () => window.openPlotModal(button.dataset.plotId, button.dataset.plotName));
            });

        } catch (err) {
            console.error("Error loading map:", err);
            const gridEl = document.getElementById('garden-map-grid');
            if (gridEl) {
                gridEl.innerHTML = '<p class="text-muted" style="grid-column: span 4; text-align: center;">Failed to load community map.</p>';
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
                if (typeof showToast === 'function') showToast(result.error || 'Failed to request plot.', 'error');
            }
        } catch (err) {
            console.error("Error requesting plot:", err);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Send Request';
        }
    });

    // Helper for fetch body
    function newSearchParams(params) {
        return new URLSearchParams(params);
    }

    // Initialize
    if (document.getElementById('plots-list')) loadPlots();
    if (document.getElementById('garden-map-grid')) loadMap();
});