document.addEventListener('DOMContentLoaded', () => {

    // 1. Load the Interactive Catalog
    async function loadResources() {
        try {
            const res = await fetch('api.php?action=resources');
            const data = await res.json();
            
            if (!data.ok) return;

            const listEl = document.getElementById('inventory-list');

            if (data.resources.length === 0) {
                listEl.innerHTML = '<p class="empty-state">No resources currently available.</p>';
                return;
            }

            // ADDED: class="catalog-item" and data-search attribute for filtering
            listEl.innerHTML = data.resources.map(r => {
                const isAvailable = r.AvailableQty > 0;
                
                return `
                <div class="catalog-item" data-search="${escapeHtml(r.Name).toLowerCase()}" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid #e2e8f0;">
                    <div>
                        <strong>${escapeHtml(r.Name)}</strong><br>
                        <span style="font-size: 0.85em; color: ${isAvailable ? 'var(--accent)' : '#d9534f'}">
                            ${r.AvailableQty} / ${r.TotalQty} available
                        </span>
                    </div>
                    
                    ${isAvailable ? `
                    <form class="inline-request-form" data-id="${r.ResourceID}" style="display: flex; gap: 12px; align-items: center;" novalidate>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <input type="number" name="qty" aria-label="Quantity" min="1" max="${r.AvailableQty}" value="1" style="width: 55px; padding: 6px; border: 1px solid #e2e8f0; border-radius: 4px;" required>
                            <span class="required" aria-hidden="true">*</span>
                            <span style="font-weight: 600; color: #64748b; font-size: 0.9rem;">x</span>
                        </div>
                        <button type="submit" class="btn btn-accent btn-sm inventory-action-btn">Request</button>
                    </form>
                    ` : `
                    <span class="badge badge-neutral">Out of stock</span>
                    `}
                </div>
                `;
            }).join('');

            document.querySelectorAll('.inline-request-form').forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const resourceId = form.getAttribute('data-id');
                    const qty = form.querySelector('input[name="qty"]').value;

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
                        } else {
                            if (typeof showToast === 'function') showToast(result.error || 'Could not submit request.', 'error');
                        }
                    } catch (err) {
                        console.error('Network error:', err);
                    }
                });
            });
            
            // Re-apply search filter if user is actively searching during a refresh
            triggerSearch('search-catalog', '.catalog-item');
            
        } catch (err) {
            console.error("Error loading resources:", err);
            document.getElementById('inventory-list').innerHTML = '<p class="empty-state" style="color: #d9534f;">Failed to load inventory.</p>';
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
                document.getElementById('my-requests-list').innerHTML = `<p class="empty-state" style="color: #d9534f;">Error loading requests.</p>`;
                document.getElementById('my-inventory-list').innerHTML = `<p class="empty-state" style="color: #d9534f;">Error loading inventory.</p>`;
                return;
            }

            const reqList = document.getElementById('my-requests-list');
            const invList = document.getElementById('my-inventory-list');

            const pendingRequests = dataReq.requests.filter(r => ['Requested', 'Rejected', 'Approved', 'Return Requested'].includes(r.Status));
            const borrowedItems = dataReq.requests.filter(r => ['Approved', 'Return Requested'].includes(r.Status));
            const personalItems = dataPers.items;

            // Render Request Tracker (Requested, Rejected, and Approved)
            if (pendingRequests.length === 0) {
                reqList.innerHTML = '<p class="text-muted" style="font-size: 0.85rem;">No pending requests.</p>';
            } else {
                const badgeClass = { Requested: 'badge-brown', Rejected: 'badge-neutral', Approved: 'badge-green', 'Return Requested': 'badge-brown' };
                reqList.innerHTML = pendingRequests.map(r => {
                    const dateLabel = r.Status === 'Approved' || r.Status === 'Return Requested'
                        ? `Approved ${new Date(r.ApprovedAt || r.RequestedAt).toLocaleDateString()}`
                        : `Requested ${new Date(r.RequestedAt).toLocaleDateString()}`;
                    return `
                    <div style="display:flex; justify-content: space-between; align-items:center; border-bottom: 1px solid #e2e8f0; padding: 12px 0;">
                        <div>
                            <strong>${escapeHtml(String(r.Qty))}x ${escapeHtml(r.Name)}</strong><br>
                            <span class="text-muted" style="font-size: 0.8em;">${dateLabel}</span>
                        </div>
                        <span class="badge ${badgeClass[r.Status] || 'badge-neutral'}">${escapeHtml(r.Status)}</span>
                    </div>
                `;
                }).join('');
            }

            // Render Combined Inventory
            let inventoryHTML = '';
            
            // ADDED: class="inventory-item" and data-search attribute
            if (borrowedItems.length > 0) {
                inventoryHTML += borrowedItems.map(r => `
                    <div class="inventory-item" data-search="${escapeHtml(r.Name).toLowerCase()}" style="display:flex; justify-content: space-between; align-items:center; border-bottom: 1px solid #e2e8f0; padding: 12px 0;">
                        <div>
                            <strong>${escapeHtml(String(r.Qty))}x ${escapeHtml(r.Name)}</strong>
                            <span class="badge ${r.Status === 'Return Requested' ? 'badge-brown' : 'badge-green'}" style="margin-left: 8px; font-size: 0.7rem;">${r.Status === 'Return Requested' ? 'Return requested' : 'Borrowed'}</span><br>
                            <span class="text-muted" style="font-size: 0.8em;">${r.Status === 'Return Requested' ? 'The coordinator has requested this item back.' : `Approved ${new Date(r.ApprovedAt || r.RequestedAt).toLocaleDateString()}`}</span>
                        </div>
                        <button class="btn btn-accent btn-sm inventory-action-btn return-btn" data-txn="${r.TxnID}">Return item</button>
                    </div>
                `).join('');
            }

            if (personalItems.length > 0) {
                inventoryHTML += personalItems.map(p => `
                    <div class="inventory-item" data-search="${escapeHtml(p.ItemName).toLowerCase()}" style="display:flex; justify-content: space-between; align-items:center; border-bottom: 1px solid #e2e8f0; padding: 12px 0;">
                        <div>
                            <strong>${escapeHtml(String(p.Qty))}x ${escapeHtml(p.ItemName)}</strong>
                            <span class="badge badge-neutral" style="margin-left: 8px; font-size: 0.7rem;">Personal</span><br>
                            <span class="text-muted" style="font-size: 0.8em;">Added ${new Date(p.AddedAt).toLocaleDateString()}</span>
                        </div>
                        <button class="btn btn-accent btn-sm inventory-action-btn remove-personal-btn" data-id="${p.ItemID}">Remove</button>
                    </div>
                `).join('');
            }

            invList.innerHTML = inventoryHTML === '' ? '<p class="text-muted" style="font-size: 0.85rem;">Your inventory is empty.</p>' : inventoryHTML;

            // Attach Return Listeners
            document.querySelectorAll('.return-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    btn.disabled = true; 
                    const txnId = e.target.getAttribute('data-txn');
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
                            if (typeof showToast === 'function') showToast(result.error || 'Failed to return item.', 'error');
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
                    const itemId = e.target.getAttribute('data-id');
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
                            if (typeof showToast === 'function') showToast(result.error || 'Failed to remove item.', 'error');
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
            document.getElementById('my-requests-list').innerHTML = '<p class="empty-state" style="color: #d9534f;">Failed to load data.</p>';
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
                    if (typeof showToast === 'function') showToast(result.error || 'Failed to add item.', 'error');
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
                    item.style.display = itemName.includes(term) ? 'flex' : 'none';
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