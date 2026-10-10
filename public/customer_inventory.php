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
<link rel="stylesheet" href="assets/style.css?v=39">
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

            <!-- Add Personal Item Form -->
            <form id="add-personal-form" class="inv-add-form" novalidate>
              <div class="inv-add-field inv-add-name">
                <label for="personal-item-name">Add your own item <span class="required">*</span></label>
                <input type="text" id="personal-item-name" placeholder="E.g., Pruning Shears" maxlength="100" required>
              </div>
              <div class="inv-add-field inv-add-qty">
                <label for="personal-item-qty">Qty <span class="required">*</span></label>
                <input type="number" id="personal-item-qty" min="1" value="1" required>
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

<script src="assets/app.js"></script>
<script src="assets/inventory.js?v=13"></script>
</body>
</html>
