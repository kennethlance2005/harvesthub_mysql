<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'Exchange Board';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Exchange Board</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=6">
</head>
<body>

<div class="app-layout">
  
  <?php include __DIR__ . '/customer_sidebar.php'; ?>

  <div class="main-content">
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">

      <div style="display: flex; gap: 32px; align-items: flex-start; flex-wrap: wrap;">
        
        <!-- WIDE LEFT COLUMN: Community Exchange Feed (flex: 3) -->
        <div style="flex: 3; min-width: 600px; display: flex; flex-direction: column; gap: 32px;">
          
          <div class="board-panel">
            <div class="board-head" style="margin-bottom: 24px; padding: 0 24px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
              <div>
                <h2 style="margin-bottom: 8px;">Community Exchange</h2>
                <p class="text-muted" style="margin: 0;">Trade surplus crops, seeds, or homemade goods with other gardeners.</p>
              </div>
              
              <input type="search" id="search-exchange" placeholder="Search produce..." style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 4px; width: 220px;">
            </div>

            <div class="panel" style="padding: 0 24px;">
              <div id="exchange-feed-list" class="scroll-y" aria-live="polite" style="max-height: 600px;">
                  <p class="empty-state">Loading exchange feed...</p>
              </div>
            </div>
          </div>

        </div>

        <!-- NARROW RIGHT COLUMN: My Listings & Post Form (flex: 1) -->
        <div style="flex: 1; min-width: 280px; display: flex; flex-direction: column; gap: 24px;">
          
          <!-- Create Listing Form -->
          <div class="board-panel" style="padding: 24px;">
            <h2 style="font-size: 1.25rem; margin-bottom: 8px;">Post an Item</h2>
            <p class="text-muted" style="margin-bottom: 16px; font-size: 0.9rem;">Have extra harvest? List it here.</p>
            
            <form id="add-exchange-form" style="display: flex; flex-direction: column; gap: 12px;" novalidate>
              
              <!-- Item input restricted to letters and spaces via pattern -->
              <input type="text" id="exchange-item" placeholder="Item (e.g., Tomatoes)" pattern="[A-Za-z\s]+" title="Letters and spaces only." style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;" required>
              
              <!-- Split Quantity: Full width container with flex spacing -->
              <div style="display: flex; gap: 8px; width: 100%;">
                <input type="number" id="exchange-qty-num" placeholder="Qty (e.g., 2.5)" step="any" min="0.1"
                    style="flex: 1; min-width: 0; box-sizing: border-box; padding: 8px 28px 8px 8px; border: 1px solid #e2e8f0; border-radius: 4px;" required>
                <select id="exchange-qty-unit"
                    style="width: 110px; flex-shrink: 0; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff;" required>
                    <option value="pcs">pcs</option>
                    <option value="kg">kg</option>
                    <option value="g">g</option>
                    <option value="bundles">bundles</option>
                </select>
            </div>

              <textarea id="exchange-desc" placeholder="Details (Optional)" rows="2" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; resize: none;"></textarea>
              <button type="submit" class="btn btn-accent" style="width: 100%; padding: 10px; margin-top: 4px;">Post to Board</button>
            </form>
        </div>

          <!-- My Active Listings -->
          <div class="board-panel" style="padding: 24px;">
            <h2 style="font-size: 1.25rem; margin-bottom: 16px;">My Active Listings</h2>
            
            <div id="my-exchange-list" class="scroll-y" style="max-height: 300px; padding-right: 8px;" aria-live="polite">
              <p class="empty-state">Loading your listings...</p>
            </div>
          </div>

          <!-- Pending Requests (Action Needed) -->
          <div class="board-panel" style="padding: 24px;">
            <h2 style="font-size: 1.25rem; margin-bottom: 16px;">Pending Requests</h2>
            <div id="pending-claims-list" class="scroll-y" style="max-height: 300px; padding-right: 8px;" aria-live="polite">
              <p class="empty-state">Loading requests...</p>
            </div>
          </div>

        </div>

    </main>
  </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>
<!-- Claim Request Modal Overlay -->
<div id="claim-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 16px;">
  <div class="board-panel" style="padding: 24px; width: 100%; max-width: 420px; background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <h3 style="margin-bottom: 16px; font-size: 1.25rem;">Request to Claim</h3>
    
    <form id="submit-claim-form" style="display: flex; flex-direction: column; gap: 16px;" novalidate>
      <!-- Hidden input to remember which post is being claimed -->
      <input type="hidden" id="claim-post-id">
      
      <div>
        <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.9rem; color: #475569;">Quantity Wanted</label>
        <input type="text" id="claim-qty" placeholder="e.g., 2 pcs, 1 kg" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 4px;" required>
      </div>
      
      <div>
        <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.9rem; color: #475569;">Preferred Pickup Details</label>
        <textarea id="claim-pickup" placeholder="e.g., Tomorrow at 10 AM by the main gate" rows="3" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 4px; resize: none;" required></textarea>
      </div>
      
      <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px;">
        <button type="button" class="btn btn-ghost" id="cancel-claim-btn" style="border: 1px solid #cbd5e1;">Cancel</button>
        <button type="submit" class="btn btn-accent">Send Request</button>
      </div>
    </form>
  </div>
</div>
<script src="assets/app.js"></script>
<script src="assets/exchange.js?v=1"></script> 
</body>
</html>