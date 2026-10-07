<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'Resource Inventory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Resource Inventory</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=24">
</head>
<body class="account-page">

<div class="app-layout">
  
  <!-- The Sidebar -->
  <?php include __DIR__ . '/customer_sidebar.php'; ?>

  <!-- Main Workspace -->
  <div class="main-content">
    <main class="wrap customer-inventory-page" id="top" style="max-width: 1200px; padding-top: 32px;">

      <div class="inventory-layout">
        
        <!-- WIDE LEFT COLUMN: Catalog & Inventory (flex: 3) -->
        <div class="inventory-primary">
          
          <!-- Top Left: Resource Catalog -->
          <div class="board-panel">
            <!-- Padding comes from .inventory-head-aligned so the header controls line up with the list buttons -->
            <div class="board-head inventory-head-aligned" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
              <div>
                <h2 style="margin-bottom: 8px;">Resource Catalog</h2>
                <p class="text-muted" style="margin: 0;">Request community tools or garden materials directly from this list.</p>
              </div>
              
              <!-- Search Bar: Catalog (Automatically pushed right by space-between) -->
              <input type="search" id="search-catalog" placeholder="Search catalog..." style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 4px; width: 220px;">
            </div>

            <div class="panel" style="padding: 0 24px;">
              <div id="inventory-list" class="scroll-y" aria-live="polite" style="max-height: 400px;">
                  <p class="empty-state">Loading inventory data...</p>
              </div>
            </div>
          </div>

          <!-- Bottom Left: My Inventory -->
          <div class="board-panel">
            <!-- Padding comes from .inventory-head-aligned so the header controls line up with the list buttons -->
            <div class="board-head inventory-head-aligned" style="margin-bottom: 24px;">
              <h2 style="margin-bottom: 16px;">My Inventory</h2>
              
              <!-- Toolbar Row: Search & Add Item -->
              <div class="inventory-toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                
                <!-- Search Bar: Inventory -->
                <input type="search" id="search-inventory" placeholder="Search my items..." style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 4px; width: 220px;">
                
                <!-- Add Personal Item Form -->
                <form id="add-personal-form" style="display: flex; gap: 8px; align-items: center;" novalidate>
                  <input type="text" id="personal-item-name" aria-label="Item name" placeholder="E.g., Pruning Shears" style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 4px; width: 200px;" required>
                  <span class="required" aria-hidden="true">*</span>
                  <div style="display: flex; align-items: center; gap: 6px;">
                      <input type="number" id="personal-item-qty" aria-label="Quantity" min="1" value="1" style="width: 55px; padding: 6px; border: 1px solid #e2e8f0; border-radius: 4px;" required>
                      <span class="required" aria-hidden="true">*</span>
                      <span style="font-weight: 600; color: #64748b; font-size: 0.9rem;">x</span>
                  </div>
                  <button type="submit" class="btn btn-accent btn-sm inventory-action-btn">Add Item</button>
                </form>

              </div>
            </div>

            <div class="panel" style="padding: 0 24px;">
              <div id="my-inventory-list" class="scroll-y" aria-live="polite" style="max-height: 300px;">
                  <p class="empty-state">Loading your inventory...</p>
              </div>
            </div>
          </div>

        </div>

        <!-- NARROW RIGHT COLUMN: My Requests (flex: 1) -->
        <div class="post-panel inventory-requests">
          <h2 style="font-size: 1.25rem; margin-bottom: 8px;">My Requests</h2>
          <p class="panel-hint" style="margin-bottom: 16px;">Track your ongoing approvals.</p>
          
          <div id="my-requests-list" class="scroll-y" style="max-height: 600px; padding-right: 8px;" aria-live="polite">
            <p class="empty-state">Loading your requests...</p>
          </div>
        </div>

      </div>

    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>

<script src="assets/app.js"></script>
<!-- Bumped version number to guarantee the new layout scripts load -->
<script src="assets/inventory.js?v=7"></script>
</body>
</html>