<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Inventory</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=24">
</head>
<body class="account-page">
<div class="app-layout">
  <?php include __DIR__ . '/coordinator_sidebar.php'; ?>
  <div class="main-content coordinator-main">
    <main class="coordinator-page" id="top">
      <header class="coordinator-page-head">
        <div><p class="eyebrow">Garden operations</p><h1>Inventory</h1><p class="text-muted">Review resource requests and keep track of shared stock.</p></div>
      </header>

      <section class="coordinator-section" aria-labelledby="resource-requests-heading">
        <div class="section-heading"><div><p class="eyebrow">Needs review</p><h2 id="resource-requests-heading">Resource requests</h2></div>
          <form class="table-search" id="resource-search-form">
            <label class="sr-only" for="resource-search">Search resource requests</label>
            <input id="resource-search" type="search" placeholder="Search gardener or resource">
          </form>
        </div>
        <div class="pending-request-list coordinator-request-list" id="resource-txns-list"></div>
        <p class="text-muted" id="resource-txns-empty" hidden>No pending resource requests.</p>
      </section>

      <section class="coordinator-section" aria-labelledby="resource-stock-heading">
        <div class="section-heading"><div><p class="eyebrow">Shared supplies</p><h2 id="resource-stock-heading">Resource inventory</h2></div>
          <form class="table-search" id="all-resources-search-form">
            <label class="sr-only" for="all-resources-search">Search resources or borrowers</label>
            <input id="all-resources-search" type="search" placeholder="Search resource or borrower">
          </form>
        </div>
        <form class="inventory-add-form" id="add-resource-form">
          <label for="resource-name">Add an item <span class="required">*</span></label>
          <input id="resource-name" name="name" type="text" maxlength="80" placeholder="Resource name" required>
          <label for="resource-qty">Quantity <span class="required">*</span></label>
          <input id="resource-qty" name="qty" type="number" min="1" max="100000" value="1" required>
          <button class="btn btn-accent btn-sm" type="submit">Add item</button>
        </form>
        <div class="table-wrap">
          <table class="data-table resource-inventory-table">
            <thead><tr><th>Resource</th><th>Total</th><th>Available</th><th>Borrower assignments</th></tr></thead>
            <tbody id="resources-table"></tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</div>
<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/staff.js?v=13"></script>
</body>
</html>