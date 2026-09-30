document.addEventListener('DOMContentLoaded', () => {

    // 1. Load the Community Exchange Feed
    async function loadExchangeFeed() {
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'exchange_feed' })
            });
            const data = await res.json();
            
            if (!data.ok) return;

            const feedEl = document.getElementById('exchange-feed-list');
            const currentUserId = data.current_user_id; 

            if (data.posts.length === 0) {
                feedEl.innerHTML = '<p class="empty-state">The exchange board is currently empty. Be the first to post!</p>';
                return;
            }

            // Generate the feed
            feedEl.innerHTML = data.posts.map(p => {
                const isMine = p.GardenerID === currentUserId; 

                return `
                <div class="exchange-item" data-search="${escapeHtml(p.ProduceName).toLowerCase()}" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid #e2e8f0;">
                    
                    <!-- Left Side: Post Details -->
                    <div style="flex: 1; padding-right: 16px;">
                        <div style="margin-bottom: 4px;">
                            <strong style="font-size: 1.1rem;">${escapeHtml(p.ProduceName)}</strong>
                            <span class="badge badge-brown" style="margin-left: 8px; font-size: 0.75rem;">${escapeHtml(p.Qty)}</span>
                        </div>
                        ${p.Description ? `<p style="margin: 0 0 8px 0; font-size: 0.9rem; color: #475569;">${escapeHtml(p.Description)}</p>` : ''}
                        <span class="text-muted" style="font-size: 0.8em;">Posted ${new Date(p.CreatedAt).toLocaleDateString()}</span>
                    </div>
                    
                    <!-- Right Side: Conditional Standalone Button or Badge -->
                    ${isMine ? `
                        <span class="badge badge-neutral" style="font-size: 0.75rem; padding: 6px 10px;">Your Listing</span>
                    ` : `
                        <!-- Removed inline input, kept uniform 85px button -->
                        <button class="btn btn-accent btn-sm open-claim-modal-btn" data-id="${p.PostID}" style="width: 85px;">Claim</button>
                    `}
                </div>
                `;
            }).join('');

            // Attach listeners to open the Claim popup
            document.querySelectorAll('.open-claim-modal-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const postId = e.target.getAttribute('data-id');
                    
                    // Reset and open the modal
                    document.getElementById('claim-post-id').value = postId;
                    document.getElementById('claim-qty').value = '';
                    document.getElementById('claim-pickup').value = '';
                    document.getElementById('claim-modal').style.display = 'flex';
                });
            });

            triggerSearch('search-exchange', '.exchange-item');
            
        } catch (err) {
            console.error("Error loading feed:", err);
            document.getElementById('exchange-feed-list').innerHTML = '<p class="empty-state" style="color: #d9534f;">Failed to load feed.</p>';
        }
    }


    // -------------------------------------------------------------
    // PASTE THIS AT THE BOTTOM OF exchange.js (Above initializers)
    // -------------------------------------------------------------
    
    // 5. Popup Modal Logic
    const claimModal = document.getElementById('claim-modal');
    const cancelClaimBtn = document.getElementById('cancel-claim-btn');
    const submitClaimForm = document.getElementById('submit-claim-form');

    if (cancelClaimBtn) {
        cancelClaimBtn.addEventListener('click', () => {
            claimModal.style.display = 'none'; // Hide modal on cancel
        });
    }

    if (submitClaimForm) {
        submitClaimForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = submitClaimForm.querySelector('button[type="submit"]');
            btn.disabled = true;

            const postId = document.getElementById('claim-post-id').value;
            const qty = document.getElementById('claim-qty').value;
            const pickup = document.getElementById('claim-pickup').value;

            // Note: Eventually we will send this data to a new 'send_claim_request' endpoint in api.php
            console.log(`Sending claim request for Post ${postId}. Qty: ${qty}, Pickup: ${pickup}`);

            if (typeof showToast === 'function') {
                showToast('Claim request sent to the gardener!', 'success');
            }

            // Close the modal and re-enable the button
            claimModal.style.display = 'none';
            btn.disabled = false;
        });
    }if (submitClaimForm) {
        submitClaimForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = submitClaimForm.querySelector('button[type="submit"]');
            btn.disabled = true;

            const postId = document.getElementById('claim-post-id').value;
            const qty = document.getElementById('claim-qty').value;
            const pickup = document.getElementById('claim-pickup').value;

            try {
                // Actually send the claim to the backend
                const res = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'send_claim_request', post_id: postId, qty: qty, pickup: pickup })
                });
                const result = await res.json();

                if (result.ok) {
                    if (typeof showToast === 'function') showToast('Claim request sent to the gardener!', 'success');
                    claimModal.style.display = 'none';
                } else {
                    if (typeof showToast === 'function') showToast(result.error || 'Failed to send request.', 'error');
                }
            } catch (err) {
                console.error("Error sending claim:", err);
            } finally {
                btn.disabled = false;
            }
        });
    }

    // 2. Load My Active Listings
    async function loadMyListings() {
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'my_exchange_listings' })
            });
            const data = await res.json();
            
            if (!data.ok) return;

            const myEl = document.getElementById('my-exchange-list');

            if (data.posts.length === 0) {
                myEl.innerHTML = '<p class="text-muted" style="font-size: 0.85rem;">You have no active listings.</p>';
                return;
            }

            myEl.innerHTML = data.posts.map(p => `
                <div style="display:flex; justify-content: space-between; align-items:center; border-bottom: 1px solid #e2e8f0; padding: 12px 0;">
                    <div>
                        <strong>${escapeHtml(p.ProduceName)}</strong> (${escapeHtml(p.Qty)})<br>
                        <span class="text-muted" style="font-size: 0.8em;">Posted ${new Date(p.CreatedAt).toLocaleDateString()}</span>
                    </div>
                    <button class="btn btn-accent btn-sm close-post-btn" data-id="${p.PostID}">Close</button>
                </div>
            `).join('');

            // Attach Close Listeners
            document.querySelectorAll('.close-post-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    btn.disabled = true; 
                    const postId = e.target.getAttribute('data-id');
                    try {
                        const res = await fetch('api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'close_exchange_post', post_id: postId })
                        });
                        const result = await res.json();
                        
                       if (result.ok) {
                            if (typeof showToast === 'function') showToast(`Request ${status.toLowerCase()}!`, 'success');
                            loadPendingRequests(); 
                            
                            // Add these two lines to refresh the feeds with the new quantities
                            loadExchangeFeed(); 
                            loadMyListings(); 
                        } else {
                            if (typeof showToast === 'function') showToast(result.error || 'Action failed.', 'error');
                            e.target.disabled = false;
                        }
                    } catch (err) {
                        btn.disabled = false;
                    }
                });
            });
            
        } catch (err) {
            console.error("Error loading my listings:", err);
            document.getElementById('my-exchange-list').innerHTML = '<p class="empty-state" style="color: #d9534f;">Failed to load listings.</p>';
        }
    }

    // 3. Handle Add Post Form Submission
    const addForm = document.getElementById('add-exchange-form');
    const itemInput = document.getElementById('exchange-item');

    // Force real-time character-only input for the Item name
    if (itemInput) {
        itemInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^A-Za-z\s]/g, '');
        });
    }

    if (addForm) {
        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = addForm.querySelector('button[type="submit"]');
            btn.disabled = true;

            const item = document.getElementById('exchange-item').value;
            const qtyNum = document.getElementById('exchange-qty-num').value;
            const qtyUnit = document.getElementById('exchange-qty-unit').value;
            const desc = document.getElementById('exchange-desc').value;

            // Combine the float and the unit into a single string for the backend (e.g., "2.5 kg")
            const combinedQty = `${qtyNum} ${qtyUnit}`;

            try {
                const res = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'add_exchange_post', item: item, qty: combinedQty, desc: desc })
                });
                const result = await res.json();
                
                if (result.ok) {
                    if (typeof showToast === 'function') showToast('Successfully posted to the board!', 'success');
                    addForm.reset();
                    loadExchangeFeed();
                    loadMyListings();
                } else {
                    if (typeof showToast === 'function') showToast(result.error || 'Failed to create post.', 'error');
                }
            } catch (err) {
                console.error("Error creating post:", err);
            } finally {
                btn.disabled = false;
            }
        });
    }
    
    // 4. Search Filter Logic
    const searchEl = document.getElementById('search-exchange');
    if (searchEl) {
        searchEl.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.exchange-item').forEach(item => {
                const itemName = item.getAttribute('data-search');
                item.style.display = itemName.includes(term) ? 'block' : 'none';
            });
        });
    }

    function triggerSearch(inputId, itemClass) {
        const inputEl = document.getElementById(inputId);
        if (inputEl && inputEl.value !== '') {
            const event = new Event('input');
            inputEl.dispatchEvent(event);
        }
    }

    // Load Pending Claims on User's Posts
    async function loadPendingRequests() {
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'my_pending_claims' })
            });
            const data = await res.json();
            
            if (!data.ok) return;

            const pendingEl = document.getElementById('pending-claims-list');

            if (data.claims.length === 0) {
                pendingEl.innerHTML = '<p class="text-muted" style="font-size: 0.85rem;">No pending requests.</p>';
                return;
            }

            pendingEl.innerHTML = data.claims.map(c => `
                <div style="border-bottom: 1px solid #e2e8f0; padding: 12px 0;">
                    <div style="margin-bottom: 8px;">
                        <strong>Request for: ${escapeHtml(c.ProduceName)}</strong><br>
                        <span class="badge badge-brown" style="font-size: 0.75rem; margin-top: 4px; display: inline-block;">Wants: ${escapeHtml(c.QtyWanted)}</span>
                    </div>
                    <p style="margin: 0 0 12px 0; font-size: 0.85rem; color: #475569; line-height: 1.4;">
                        <strong>Pickup:</strong> ${escapeHtml(c.PickupDetails)}
                    </p>
                    <div style="display: flex; gap: 8px; justify-content: space-between;">
                        <button class="btn btn-sm btn-accent handle-claim-btn" data-id="${c.ClaimID}" data-status="Accepted" style="flex: 1;">Accept</button>
                        <button class="btn btn-sm btn-ghost handle-claim-btn" data-id="${c.ClaimID}" data-status="Rejected" style="flex: 1; border: 1px solid #d9534f; color: #d9534f;">Reject</button>
                    </div>
                </div>
            `).join('');

            // Attach listeners to Accept/Reject buttons
            document.querySelectorAll('.handle-claim-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const claimId = e.target.getAttribute('data-id');
                    const status = e.target.getAttribute('data-status');
                    e.target.disabled = true;

                    try {
                        const res = await fetch('api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'handle_claim_request', claim_id: claimId, status: status })
                        });
                        const result = await res.json();
                        
                        if (result.ok) {
                            if (typeof showToast === 'function') showToast(`Request ${status.toLowerCase()}!`, 'success');
                            loadPendingRequests(); 
                        } else {
                            if (typeof showToast === 'function') showToast(result.error || 'Action failed.', 'error');
                            e.target.disabled = false;
                        }
                    } catch (err) {
                        e.target.disabled = false;
                    }
                });
            });
            
        } catch (err) {
            console.error("Error loading pending requests:", err);
            document.getElementById('pending-claims-list').innerHTML = '<p class="empty-state" style="color: #d9534f;">Failed to load requests.</p>';
        }
    }

    // Initialize the page
    loadExchangeFeed();
    loadMyListings();
    loadPendingRequests();
});