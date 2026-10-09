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

    // 1. Load the Interactive Catalog
    async function loadResources() {
        try {
            const res = await fetch('api.php?action=resources');
            const data = await res.json();
            
            if (!data.ok) return;

            const listEl = document.getElementById('inventory-list');

            if (data.resources.length === 0) {
                listEl.innerHTML = '<p class="inv-empty">No resources in the catalog yet.</p>';
                return;
            }

            listEl.innerHTML = data.resources.map(r => {
                const availableQty = Number(r.AvailableQty) || 0;
                const isAvailable = availableQty > 0;
                const myPendingQty = Number(r.MyPendingQty) || 0;

                let action;
                if (myPendingQty > 0) {
                    action = `<span class="badge badge-brown" title="Cancel it under My requests to change the quantity.">Requested (${myPendingQty})</span>`;
                } else if (isAvailable) {
                    action = `
                    <form class="inline-request-form inv-request-form" data-id="${r.ResourceID}" novalidate>
                        <label class="inv-qty">
                            <span>Qty <span class="required">*</span></span>
                            <input type="number" name="qty" min="1" max="${availableQty}" value="1" required>
                        </label>
                        <button type="submit" class="btn btn-accent btn-sm inv-btn">Request</button>
                    </form>`;
                } else {
                    action = '<span class="badge badge-neutral">Out of stock</span>';
                }

                return `
                <div class="inv-row catalog-item" data-search="${escapeHtml(r.Name).toLowerCase()}">
                    <div class="inv-row-main">
                        <strong class="inv-row-title">${escapeHtml(r.Name)}</strong>
                        <span class="inv-row-meta ${isAvailable ? 'inv-stock-ok' : 'inv-stock-out'}">${availableQty} of ${escapeHtml(String(r.TotalQty))} available</span>
                    </div>
                    <div class="inv-row-actions">${action}</div>
                </div>
                `;
            }).join('');

            document.querySelectorAll('.inline-request-form').forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn.disabled) return;
                    const resourceId = form.getAttribute('data-id');
                    const qty = form.querySelector('input[name="qty"]').value;

                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Requesting…';

                    try {
                        const res = await fetch('api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'resource_request', resource_id: resourceId, qty: qty })
                        });
                        const result = await res.json();

                        if (result.ok) {
                            if (typeof showToast === 'function') showToast('Resource requested!', 'success');
                            loadResources();
                            loadMyRequests();
                            return;
                        }
                        if (typeof showToast === 'function') showToast(result.error || 'Could not submit request.', 'danger');
                        if (res.status === 409) loadResources();
                    } catch (err) {
                        console.error('Network error:', err);
                        if (typeof showToast === 'function') showToast('Network error. Please try again.', 'danger');
                    }
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Request';
                });
            });
            
            // Re-apply search filter if user is actively searching during a refresh
            triggerSearch('search-catalog', '.catalog-item');
            
        } catch (err) {
            console.error("Error loading resources:", err);
            document.getElementById('inventory-list').innerHTML = '<p class="inv-empty inv-error">Failed to load the catalog.</p>';
        }
    }

    // 2. Load the Request Tracker & Combined Inventory
    async function loadMyRequests() {
        try {
            const [resReq, resPers] = await Promise.all([
                fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'my_resource_requests' }) }),
                fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'get_personal_inventory' }) })
            ]);
            
            const dataReq = await resReq.json();
            const dataPers = await resPers.json();

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
                    if (!window.confirm(`Cancel your pending request for "${btn.dataset.name}"?`)) return;
                    btn.disabled = true;
                    btn.textContent = 'Cancelling…';
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
                    loadResources(); loadMyRequests();
                });
            });

            // Attach Return Listeners
            document.querySelectorAll('.return-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    btn.disabled = true; 
                    const txnId = btn.dataset.txn;
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
                    }
                });
            });

            // Attach Remove Listeners
            document.querySelectorAll('.remove-personal-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    btn.disabled = true; 
                    const itemId = btn.dataset.id;
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
});