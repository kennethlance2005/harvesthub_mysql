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
<link rel="stylesheet" href="assets/style.css?v=25">
</head>
<body class="account-page">

<div class="app-layout">
  
  <?php include __DIR__ . '/customer_sidebar.php'; ?>

  <div class="main-content">
    <main class="wrap customer-plots-page" id="top" style="max-width: 1000px; padding-top: 32px;">
      <header class="page-head">
        <div>
          <p class="eyebrow">Space & community</p>
          <h1>My Plots</h1>
          <p class="text-muted">Explore available garden plots and request a space.</p>
        </div>
      </header>
      <section class="assigned-plots-section" aria-labelledby="assigned-plots-title">
        <div class="section-heading">
          <div>
            <p class="eyebrow">Your garden space</p>
            <h2 id="assigned-plots-title">Plots assigned to me</h2>
          </div>
        </div>
        <div id="my-assigned-plots" class="assigned-plots-list" aria-live="polite">
          <p class="text-muted">Loading your assigned plots...</p>
        </div>
      </section>
      <section class="board-panel garden-map-panel" aria-labelledby="garden-map-title">
        <div style="margin-bottom: 24px;">
          <h2 id="garden-map-title" style="font-size: 1.25rem; margin-bottom: 8px;">Community Garden Map</h2>
          <p class="text-muted" style="margin: 0;">Select an available plot to request it from the coordinator.</p>
        </div>
        <div id="garden-map-grid" class="customer-garden-map" aria-live="polite">
          <p class="text-muted" style="grid-column: span 4; text-align: center;">Loading map...</p>
        </div>
        <div class="garden-map-legend">
          <div><span class="garden-map-swatch available"></span>Available</div>
          <div><span class="garden-map-swatch pending"></span>My request pending</div>
          <div><span class="garden-map-swatch occupied"></span>Occupied</div>
          <div><span class="garden-map-swatch assigned"></span>Assigned to me</div>
        </div>
      </section>
    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<!-- Plot Request Modal Overlay -->
<div id="plot-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 16px;">
  <div class="board-panel" style="padding: 24px; width: 100%; max-width: 400px; background: #fff; border-radius: 8px; text-align: center;">
    <h3 style="margin-bottom: 12px; font-size: 1.25rem;">Request Plot</h3>
    <p class="text-muted" style="margin-bottom: 24px;">Do you want to send a request to the coordinator to claim <strong id="modal-plot-name" style="color: var(--accent);">--</strong>?</p>
    
    <input type="hidden" id="modal-plot-id">
    
    <div style="display: flex; justify-content: center; gap: 12px;">
      <button type="button" class="btn btn-ghost" id="cancel-plot-btn" style="border: 1px solid #cbd5e1; width: 100px;">Cancel</button>
      <button type="button" class="btn btn-accent" id="confirm-plot-btn" style="width: 120px;">Send Request</button>
    </div>
  </div>
</div>
<script src="assets/app.js"></script>
<script src="assets/plots.js?v=6"></script>
</body>
</html>