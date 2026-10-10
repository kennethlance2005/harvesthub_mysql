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
<link rel="stylesheet" href="assets/style.css?v=48">
</head>
<body class="account-page">

<div class="app-layout">
  
  <!-- The Sidebar -->
  <?php include __DIR__ . '/customer_sidebar.php'; ?>

  <!-- Main Workspace -->
  <div class="main-content">
    <main class="wrap gardener-page inv-page" id="top">

      <header class="page-head">
        <div>
          <p class="eyebrow">Tools &amp; supplies</p>
          <h1>Resource Inventory</h1>
          <p class="text-muted">Borrow community tools and materials, and keep track of what you have.</p>
        </div>
      </header>

      <div class="inv-layout">
        
        <!-- WIDE LEFT COLUMN: Catalog & Inventory -->
        <div class="inv-primary">
          
          <!-- Resource Catalog -->
          <section class="panel inv-card">
            <div class="inv-card-head">
              <div>
                <h2 class="panel-title">Resource catalog</h2>
                <p class="inv-card-sub">Request community tools or garden materials. Availability updates automatically.</p>
              </div>
              <input type="search" id="search-catalog" class="inv-search" placeholder="Search catalog..." aria-label="Search catalog">
            </div>

            <p class="inv-limit-note" id="inv-limit-note" role="status" hidden></p>

            <div id="inventory-list" class="inv-list" aria-live="polite">
              <p class="inv-empty">Loading inventory data...</p>
            </div>
          </section>

          <!-- My Inventory -->
          <section class="panel inv-card">
            <div class="inv-card-head">
              <div>
                <h2 class="panel-title">My inventory</h2>
                <p class="inv-card-sub">Items you borrowed plus your own tools.</p>
              </div>
              <input type="search" id="search-inventory" class="inv-search" placeholder="Search my items..." aria-label="Search my items">
            </div>
            <button type="button" class="btn btn-ghost btn-sm inv-donate-open" id="open-donation-form">Donate an item</button>

            <!-- Add Personal Item Form -->
            <form id="add-personal-form" class="inv-add-form">
              <div class="inv-add-field inv-add-name">
                <label for="personal-item-name">Add your own item <span class="required">*</span></label>
                <input type="text" id="personal-item-name" placeholder="E.g., Pruning Shears" maxlength="100" required>
              </div>
              <div class="inv-add-field inv-add-qty">
                <label for="personal-item-qty">Qty <span class="required">*</span></label>
                <input type="number" id="personal-item-qty" min="1" max="100000" value="1" required>
              </div>
              <button type="submit" class="btn btn-accent btn-sm inv-btn">Add item</button>
            </form>

            <div id="my-inventory-list" class="inv-list" aria-live="polite">
              <p class="inv-empty">Loading your inventory...</p>
            </div>
          </section>

        </div>

        <!-- NARROW RIGHT COLUMN: My Requests -->
        <aside class="panel inv-card inv-requests">
          <h2 class="panel-title">My requests</h2>
          <p class="inv-card-sub" id="my-requests-sub">Requests waiting for a coordinator.</p>
          
          <div id="my-requests-list" aria-live="polite">
            <p class="inv-empty">Loading your requests...</p>
          </div>
        </aside>

      </div>

    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<dialog class="hh-dialog" id="donation-dialog" aria-labelledby="donation-title">
  <form class="hh-dialog-box" id="donation-form">
    <h3 id="donation-title">Donate an item</h3>
    <p class="hh-dialog-message">Your donation will be reviewed by a coordinator before it is added to shared inventory.</p>
    <input type="hidden" id="donation-source-item">
    <div class="field hh-dialog-field">
      <label for="donation-item-name">Item name <span class="required">*</span></label>
      <input type="text" id="donation-item-name" maxlength="100" required>
    </div>
    <div class="field hh-dialog-field">
      <label for="donation-item-qty">Quantity <span class="required">*</span></label>
      <input type="number" id="donation-item-qty" min="1" max="100000" value="1" required>
    </div>
    <div class="field hh-dialog-field">
      <label for="donation-item-notes">Notes <span class="field-optional">(optional)</span></label>
      <textarea id="donation-item-notes" maxlength="1000" rows="3" placeholder="Condition, useful details, etc."></textarea>
    </div>
    <div class="hh-dialog-actions">
      <button type="button" class="btn btn-ghost" id="donation-cancel">Cancel</button>
      <button type="submit" class="btn btn-accent">Send donation request</button>
    </div>
  </form>
</dialog>
<script src="assets/app.js"></script>
<script src="assets/inventory.js?v=15"></script>
</body>
</html>
