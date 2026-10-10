<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'My Plots';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — My Plots</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=48">
</head>
<body class="account-page">

<div class="app-layout">

  <?php include __DIR__ . '/customer_sidebar.php'; ?>

  <div class="main-content">
    <main class="wrap gardener-page plots-page" id="top">
      <header class="page-head">
        <div>
          <p class="eyebrow">Space &amp; community</p>
          <h1>My Plots</h1>
          <p class="text-muted">Explore available garden plots and request a space.</p>
        </div>
      </header>

      <div class="plt-layout">
        <!-- WIDE LEFT COLUMN: the community map -->
        <section class="panel plt-card" aria-labelledby="garden-map-title">
          <h2 class="panel-title" id="garden-map-title">Community garden map</h2>
          <p class="plt-card-sub">Review each plot's city and area before requesting it from the coordinator.</p>
          <div id="garden-map-grid" class="customer-garden-map" aria-live="polite">
            <p class="plt-empty">Loading map...</p>
          </div>
          <div class="garden-map-legend" aria-label="Map legend">
            <div><span class="garden-map-swatch available"></span>Available</div>
            <div><span class="garden-map-swatch pending"></span>My request pending</div>
            <div><span class="garden-map-swatch assigned"></span>Assigned to me</div>
            <div><span class="garden-map-swatch occupied"></span>Occupied</div>
          </div>
        </section>

        <!-- NARROW RIGHT COLUMN: the gardener's own plots -->
        <aside class="panel plt-card plt-mine" aria-labelledby="assigned-plots-title">
          <h2 class="panel-title" id="assigned-plots-title">Plots assigned to me</h2>
          <p class="plt-card-sub">Your garden space. Ask the coordinator to release a plot you no longer need.</p>
          <div id="my-assigned-plots" class="assigned-plots-list" aria-live="polite">
            <p class="plt-empty">Loading your assigned plots...</p>
          </div>
        </aside>
      </div>
    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<!-- Plot Request Modal Overlay (shown by plots.js) -->
<div id="plot-modal" class="gardener-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="plot-modal-title">
  <div class="gardener-modal-box">
    <h3 id="plot-modal-title">Request plot</h3>
    <p class="text-muted">Send a request to the coordinator to claim <strong id="modal-plot-name">--</strong>?</p>
    <p class="plot-request-details" id="modal-plot-details"></p>

    <input type="hidden" id="modal-plot-id">

    <div class="gardener-modal-actions">
      <button type="button" class="btn btn-ghost" id="cancel-plot-btn">Cancel</button>
      <button type="button" class="btn btn-accent" id="confirm-plot-btn">Send request</button>
    </div>
  </div>
</div>
<script src="assets/app.js"></script>
<script src="assets/plots.js?v=14"></script>
</body>
</html>
