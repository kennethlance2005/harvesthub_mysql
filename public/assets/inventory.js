document.addEventListener('DOMContentLoaded', () => {

    // Past (non-pending) requests stay collapsed unless the gardener opens them.
    let showRequestHistory = false;

    // "2026-09-27 18:37:39" -> "Sep 27, 2026"
    function formatInvDate(value) {
        if (!value) return '';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return escapeHtml(String(value));
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    // Live refresh state. Background refreshes skip redrawing when nothing
    // changed, and never redraw while one of the gardener's own actions is
    // still being sent (busyCount > 0).
    const REFRESH_INTERVAL_MS = 10000;
    let busyCount = 0;
    let lastResourcesSig = null;
    let lastRequestsSig = null;
    let lastAvailability = null;
    let pendingLimit = { count: 0, max: 5 };

    async function whileBusy(task) {
        busyCount++;
        try {
            return await task();
        } finally {
            busyCount--;
        }
    }

    function updateLimitText() {
        const sub = document.getElementById('my-requests-sub');
        if (sub) sub.textContent = `${pendingLimit.count} of ${pendingLimit.max} pending requests are waiting for a coordinator.`;

        const note = document.getElementById('inv-limit-note');
        if (note) {
            const atLimit = pendingLimit.count >= pendingLimit.max;
            note.hidden = !atLimit;
            note.textContent = atLimit
                ? `You have reached the limit of ${pendingLimit.max} pending requests. Cancel one or wait for a coordinator to review them before requesting more.`
                : '';
        }
    }

    // 1. Load the Interactive Catalog
    async function loadResources({ background = false } = {}) {
        try {
            const res = await fetch('api.php?action=resources');
            const data = await res.json();

            if (!data.ok) return;
            if (background && busyCount > 0) return;

            const sig = JSON.stringify(data);
            if (background && sig === lastResourcesSig) return;
            lastResourcesSig = sig;

            pendingLimit = { count: Number(data.my_pending_count) || 0, max: Number(data.max_pending) || 5 };
            updateLimitText();
            const atLimit = pendingLimit.count >= pendingLimit.max;

            const listEl = document.getElementById('inventory-list');

            if (data.resources.length === 0) {
                listEl.innerHTML = '<p class="inv-empty">No resources in the catalog yet.</p>';
                lastAvailability = {};
                return;
            }

            // Remember what the gardener typed and where the cursor was, so a
            // refresh doesn't wipe a quantity they're in the middle of entering.
            const typedQty = {};
            listEl.querySelectorAll('.inline-request-form').forEach(form => {
                typedQty[form.dataset.id] = form.querySelector('input[name="qty"]').value;
            });
            const focusedForm = document.activeElement && document.activeElement.closest
                ? document.activeElement.closest('.inline-request-form')
                : null;
            const focusedId = focusedForm ? focusedForm.dataset.id : null;

            const previousAvailability = lastAvailability;
            lastAvailability = {};

            listEl.innerHTML = data.resources.map(r => {
                const availableQty = Number(r.AvailableQty) || 0;
                const isAvailable = availableQty > 0;
                const myPendingQty = Number(r.MyPendingQty) || 0;
                lastAvailability[r.ResourceID] = availableQty;
                const changed = previousAvailability !== null
                    && previousAvailability[r.ResourceID] !== undefined
                    && previousAvailability[r.ResourceID] !== availableQty;

                let action;
                if (myPendingQty > 0) {
                    action = `<span class="badge badge-brown" title="Cancel it under My requests to change the quantity.">Requested (${myPendingQty})</span>`;
                } else if (isAvailable) {
                    const typed = Number(typedQty[r.ResourceID]);
                    const qtyValue = Number.isInteger(typed) && typed >= 1 ? Math.min(typed, availableQty) : 1;
                    action = `
                    <form class="inline-request-form inv-request-form" data-id="${r.ResourceID}" novalidate>
                        <label class="inv-qty">
                            <span>Qty <span class="required">*</span></span>
                            <input type="number" name="qty" min="1" max="${availableQty}" value="${qtyValue}" required>
                        </label>
                        <button type="submit" class="btn btn-accent btn-sm inv-btn" ${atLimit ? 'disabled title="You have reached the pending request limit."' : ''}>Request</button>
                    </form>`;
                } else {
                    action = '<span class="badge badge-neutral">Out of stock</span>';
                }

                return `
                <div class="inv-row catalog-item" data-search="${escapeHtml(r.Name).toLowerCase()}">
                    <div class="inv-row-main">
                        <strong class="inv-row-title">${escapeHtml(r.Name)}</strong>
                        <span class="inv-row-meta ${isAvailable ? 'inv-stock-ok' : 'inv-stock-out'}${changed ? ' inv-stock-changed' : ''}">${availableQty} of ${escapeHtml(String(r.TotalQty))} available</span>
                    </div>
                    <div class="inv-row-actions">${action}</div>
                </div>
                `;
            }).join('');

            if (focusedId) {
                listEl.querySelector(`.inline-request-form[data-id="${CSS.escape(focusedId)}"] input[name="qty"]`)?.focus();
            }

            document.querySelectorAll('.inline-request-form').forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn.disabled) return;
                    const resourceId = form.getAttribute('data-id');
                    const qty = form.querySelector('input[name="qty"]').value;

                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Requesting…';

                    await whileBusy(async () => {
                        try {
                            const res = await fetch('api.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ action: 'resource_request', resource_id: resourceId, qty: qty })
                            });
                            const result = await res.json();

                            if (result.ok) {
                                if (typeof showToast === 'function') showToast('Resource requested!', 'success');
                                return;
                            }
                            if (typeof showToast === 'function') showToast(result.error || 'Could not submit request.', 'danger');
                        } catch (err) {
                            console.error('Network error:', err);
                            if (typeof showToast === 'function') showToast('Network error. Please try again.', 'danger');
                        }
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Request';
                    });
                    // Redraw both lists so the badge, counts and limit note are current.
                    loadResources();
                    loadMyRequests();
                });
            });

            // Re-apply search filter if user is actively searching during a refresh
            triggerSearch('search-catalog', '.catalog-item');

        } catch (err) {
            console.error("Error loading resources:", err);
            if (!background) {
                document.getElementById('inventory-list').innerHTML = '<p class="inv-empty inv-error">Failed to load the catalog.</p>';
            }
        }
    }

    // 2. Load the Request Tracker & Combined Inventory
    async function loadMyRequests({ background = false } = {}) {
        try {
            const [resReq, resPers] = await Promise.all([
                fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'my_resource_requests' }) }),
                fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'get_personal_inventory' }) })
            ]);

            const dataReq = await resReq.json();
            const dataPers = await resPers.json();

            if (background) {
                if (busyCount > 0 || !dataReq.ok || !dataPers.ok) return;
                const sig = JSON.stringify([dataReq, dataPers]);
                if (sig === lastRequestsSig) return;
                lastRequestsSig = sig;
            } else {
                lastRequestsSig = JSON.stringify([dataReq, dataPers]);
            }

            if (!dataReq.ok || !dataPers.ok) {
                document.getElementById('my-requests-list').innerHTML = '<p class="inv-empty inv-error">Error loading requests.</p>';
                document.getElementById('my-inventory-list').innerHTML = '<p class="inv-empty inv-error">Error loading inventory.</p>';
                return;
            }

            const reqList = document.getElementById('my-requests-list');
            const invList = document.getElementById('my-inventory-list');

            const activeRequests = dataReq.requests.filter(r => r.Status === 'Requested');
            const historyRequests = dataReq.requests.filter(r => ['Rejected', 'Approved', 'Return Requested'].includes(r.Status));
            const borrowedItems = dataReq.requests.filter(r => ['Approved', 'Return Requested'].includes(r.Status));
            const personalItems = dataPers.items;

            pendingLimit.count = activeRequests.length;
            updateLimitText();

            // Render Request Tracker: pending requests first, older decisions behind a toggle
            const badgeClass = { Requested: 'badge-brown', Rejected: 'badge-neutral', Approved: 'badge-green', 'Return Requested': 'badge-brown' };
            const badgeLabel = { Requested: 'Pending' };
            const renderRequest = r => {
                // The badge already names the status, so the date stands on its own.
                const dateLabel = r.Status === 'Approved' || r.Status === 'Return Requested'
                    ? formatInvDate(r.ApprovedAt || r.RequestedAt)
                    : formatInvDate(r.RequestedAt);
                return `
                <div class="inv-req">
                    <div class="inv-req-main">
                        <strong class="inv-row-title">${escapeHtml(String(r.Qty))}× ${escapeHtml(r.Name)}</strong>
                        <div class="inv-req-meta">
                            <span class="badge ${badgeClass[r.Status] || 'badge-neutral'}">${escapeHtml(badgeLabel[r.Status] || r.Status)}</span>
                            <span>${dateLabel}</span>
                        </div>
                        ${r.Status === 'Rejected' && r.RejectionReason ? `<p class="inv-req-reason">Reason: ${escapeHtml(r.RejectionReason)}</p>` : ''}
                    </div>
                    ${r.Status === 'Requested' ? `<button type="button" class="btn btn-ghost btn-sm cancel-request-btn" data-txn="${r.TxnID}" data-name="${escapeHtml(r.Name)}">Cancel</button>` : ''}
                </div>`;
            };

            let requestsHTML = activeRequests.length === 0
                ? '<p class="inv-empty">No pending requests. Request an item from the catalog to get started.</p>'
                : activeRequests.map(renderRequest).join('');
            if (historyRequests.length > 0) {
                requestsHTML += `
                <button type="button" class="inv-history-toggle" id="request-history-toggle" aria-expanded="${showRequestHistory}" aria-controls="request-history">
                    ${showRequestHistory ? 'Hide' : 'Show'} past requests (${historyRequests.length})
                </button>
                <div id="request-history" class="inv-history" ${showRequestHistory ? '' : 'hidden'}>
                    ${historyRequests.map(renderRequest).join('')}
                </div>`;
            }
            reqList.innerHTML = requestsHTML;

            const historyToggle = document.getElementById('request-history-toggle');
            if (historyToggle) {
                historyToggle.addEventListener('click', () => {
                    showRequestHistory = !showRequestHistory;
                    document.getElementById('request-history').hidden = !showRequestHistory;
                    historyToggle.setAttribute('aria-expanded', String(showRequestHistory));
                    historyToggle.textContent = `${showRequestHistory ? 'Hide' : 'Show'} past requests (${historyRequests.length})`;
                });
            }

            // Render Combined Inventory
            let inventoryHTML = '';
            
            if (borrowedItems.length > 0) {
                inventoryHTML += borrowedItems.map(r => `
                    <div class="inv-row inventory-item" data-search="${escapeHtml(r.Name).toLowerCase()}">
                        <div class="inv-row-main">
                            <div class="inv-row-title-line">
                                <strong class="inv-row-title">${escapeHtml(String(r.Qty))}× ${escapeHtml(r.Name)}</strong>
                                <span class="badge ${r.Status === 'Return Requested' ? 'badge-brown' : 'badge-green'}">${r.Status === 'Return Requested' ? 'Return requested' : 'Borrowed'}</span>
                            </div>
                            <span class="inv-row-meta">${r.Status === 'Return Requested' ? 'The coordinator has asked for this item back.' : `Approved ${formatInvDate(r.ApprovedAt || r.RequestedAt)}`}</span>
                        </div>
                        <div class="inv-row-actions">
                            <button class="btn btn-accent btn-sm inv-btn return-btn" data-txn="${r.TxnID}">Return item</button>
                        </div>
                    </div>
                `).join('');
            }

            if (personalItems.length > 0) {
                inventoryHTML += personalItems.map(p => `
                    <div class="inv-row inventory-item" data-search="${escapeHtml(p.ItemName).toLowerCase()}">
                        <div class="inv-row-main">
                            <div class="inv-row-title-line">
                                <strong class="inv-row-title">${escapeHtml(String(p.Qty))}× ${escapeHtml(p.ItemName)}</strong>
                                <span class="badge badge-neutral">Personal</span>
                            </div>
                            <span class="inv-row-meta">Added ${formatInvDate(p.AddedAt)}</span>
                        </div>
                        <div class="inv-row-actions">
                            <button class="btn btn-ghost btn-sm inv-btn remove-personal-btn" data-id="${p.ItemID}">Remove</button>
                        </div>
                    </div>
                `).join('');
            }

            invList.innerHTML = inventoryHTML === '' ? '<p class="inv-empty">Your inventory is empty. Borrowed items and your own tools will show up here.</p>' : inventoryHTML;

            // Attach Cancel Listeners (pending requests only)
            document.querySelectorAll('.cancel-request-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (btn.disabled) return;
                    if (!await hhConfirm({ title: 'Cancel this request?', message: `Your pending request for ${btn.dataset.name} will be withdrawn. You can request it again later.`, confirmText: 'Cancel request', cancelText: 'Keep request', tone: 'danger' })) return;
                    btn.disabled = true;
                    btn.textContent = 'Cancelling…';
                    busyCount++;
                    try {
                        const res = await fetch('api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'cancel_resource_request', txn_id: btn.dataset.txn })
                        });
                        const result = await res.json();
                        if (result.ok) {
                            if (typeof showToast === 'function') showToast('Request cancelled.', 'success');
                        } else {
                            if (typeof showToast === 'function') showToast(result.error || 'Could not cancel request.', 'danger');
                        }
                    } catch (err) {
                        if (typeof showToast === 'function') showToast('Network error. Please try again.', 'danger');
                    }
                    busyCount--;
                    loadResources(); loadMyRequests();
                });
            });

            // Attach Return Listeners
            document.querySelectorAll('.return-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    btn.disabled = true; 
                    const txnId = btn.dataset.txn;
                    busyCount++;
                    try {
                        const res = await fetch('api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'return_resource', txn_id: txnId })
                        });
                        const result = await res.json();
                        if (result.ok) {
                            if (typeof showToast === 'function') showToast('Item returned successfully!', 'success');
                            loadResources(); loadMyRequests(); 
                        } else {
                            if (typeof showToast === 'function') showToast(result.error || 'Failed to return item.', 'danger');
                            btn.disabled = false;
                        }
                    } catch (err) {
                        btn.disabled = false;
                    } finally {
                        busyCount--;
                    }
                });
            });

            // Attach Remove Listeners
            document.querySelectorAll('.remove-personal-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    btn.disabled = true; 
                    const itemId = btn.dataset.id;
                    busyCount++;
                    try {
                        const res = await fetch('api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'remove_personal_item', item_id: itemId })
                        });
                        const result = await res.json();
                        if (result.ok) {
                            if (typeof showToast === 'function') showToast('Personal item removed.', 'success');
                            loadMyRequests(); 
                        } else {
                            if (typeof showToast === 'function') showToast(result.error || 'Failed to remove item.', 'danger');
                            btn.disabled = false;
                        }
                    } catch (err) {
                        btn.disabled = false;
                    } finally {
                        busyCount--;
                    }
                });
            });
            
            // Re-apply search filter if user is actively searching during a refresh
            triggerSearch('search-inventory', '.inventory-item');
            
        } catch (err) {
            console.error("Error loading requests:", err);
            document.getElementById('my-requests-list').innerHTML = '<p class="inv-empty inv-error">Failed to load your requests.</p>';
        }
    }

    // 3. Handle Add Personal Item Form Submission
    const addPersonalForm = document.getElementById('add-personal-form');
    if (addPersonalForm) {
        addPersonalForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = addPersonalForm.querySelector('button[type="submit"]');
            btn.disabled = true;

            const itemName = document.getElementById('personal-item-name').value;
            const itemQty = document.getElementById('personal-item-qty').value;

            busyCount++;
            try {
                const res = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'add_personal_item', item_name: itemName, qty: itemQty })
                });
                const result = await res.json();
                
                if (result.ok) {
                    if (typeof showToast === 'function') showToast('Personal item added!', 'success');
                    addPersonalForm.reset();
                    loadMyRequests();
                } else {
                    if (typeof showToast === 'function') showToast(result.error || 'Failed to add item.', 'danger');
                }
            } catch (err) {
                console.error("Error adding personal item:", err);
            } finally {
                btn.disabled = false;
                busyCount--;
            }
        });
    }

    // 4. FRONTEND SEARCH LOGIC
    function setupSearch(inputId, itemClass) {
        const inputEl = document.getElementById(inputId);
        if (inputEl) {
            inputEl.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll(itemClass).forEach(item => {
                    const itemName = item.getAttribute('data-search');
                    // Hide the item if it doesn't match the search term
                    item.hidden = !itemName.includes(term);
                });
            });
        }
    }

    // Helper function to maintain search states after form submissions
    function triggerSearch(inputId, itemClass) {
        const inputEl = document.getElementById(inputId);
        if (inputEl && inputEl.value !== '') {
            const event = new Event('input');
            inputEl.dispatchEvent(event);
        }
    }

    setupSearch('search-catalog', '.catalog-item');
    setupSearch('search-inventory', '.inventory-item');

    // Initialize the data fetches immediately when the page loads
    loadResources();
    loadMyRequests();

    // Live refresh: keep availability and request statuses current while the
    // page is open, and catch up as soon as the gardener returns to the tab.
    function refreshInBackground() {
        if (document.hidden) return;
        loadResources({ background: true });
        loadMyRequests({ background: true });
    }
    window.setInterval(refreshInBackground, REFRESH_INTERVAL_MS);
    document.addEventListener('visibilitychange', refreshInBackground);
});