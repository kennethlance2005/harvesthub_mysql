<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Plots</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=44">
</head>
<body class="account-page">
<div class="app-layout">
  <?php include __DIR__ . '/coordinator_sidebar.php'; ?>
  <div class="main-content coordinator-main">
    <main class="coordinator-page" id="top">
      <header class="coordinator-page-head">
        <div><p class="eyebrow">Garden operations</p><h1>Plots</h1><p class="text-muted">Handle plot requests and manage the garden map.</p></div>
      </header>

      <section class="coordinator-section" aria-labelledby="plot-requests-heading">
        <div class="section-heading"><div><p class="eyebrow">Needs review</p><h2 id="plot-requests-heading">Plot requests</h2></div>
          <form class="table-search" id="applications-search-form">
            <label class="sr-only" for="applications-search">Search applications</label>
            <input id="applications-search" type="search" placeholder="Search gardener or plot">
          </form>
        </div>
        <div id="applications-list">
          <section class="plot-request-group" aria-labelledby="assignment-requests-heading">
            <h3 id="assignment-requests-heading">Assignment Requests</h3>
            <div class="pending-request-list coordinator-request-list" id="assignment-applications-list"></div>
            <p class="text-muted" id="assignment-applications-empty" hidden>No pending assignment requests.</p>
          </section>
          <section class="plot-request-group" aria-labelledby="unassignment-requests-heading">
            <h3 id="unassignment-requests-heading">Unassignment Requests</h3>
            <div class="pending-request-list coordinator-request-list" id="unassignment-applications-list"></div>
            <p class="text-muted" id="unassignment-applications-empty" hidden>No pending unassignment requests.</p>
          </section>
        </div>
      </section>

      <section class="coordinator-section" aria-labelledby="plot-map-heading">
        <div class="section-heading"><div><p class="eyebrow">Plot handling</p><h2 id="plot-map-heading">Plot map</h2></div>
          <div class="plot-map-controls">
            <form class="plot-management-form" id="create-plot-form">
              <label class="sr-only" for="new-plot-label">New plot name <span class="required">*</span></label>
              <input id="new-plot-label" type="text" maxlength="80" placeholder="New plot name" required>
              <label class="sr-only" for="new-plot-location">Plot city <span class="required">*</span></label>
              <select id="new-plot-location" required>
                <option value="" selected disabled>Select city</option>
                <option>Caloocan</option><option>Las Piñas</option><option>Makati</option><option>Malabon</option>
                <option>Mandaluyong</option><option>Manila</option><option>Marikina</option><option>Muntinlupa</option>
                <option>Navotas</option><option>Parañaque</option><option>Pasay</option><option>Pasig</option>
                <option>Pateros</option><option>Quezon City</option><option>San Juan</option><option>Taguig</option>
                <option>Valenzuela</option>
              </select>
              <label class="sr-only" for="new-plot-area">Plot area in square meters</label>
              <input id="new-plot-area" type="number" min="0.01" max="100000" step="0.01" placeholder="Area (m²)" required>
              <button class="btn btn-accent btn-sm" type="submit">Add plot</button>
            </form>
            <label class="sr-only" for="plot-status-filter">Filter plots by status</label>
            <select id="plot-status-filter" aria-label="Filter plots by status">
              <option value="all">All plots</option><option value="available">Available</option><option value="unavailable">Occupied</option>
            </select>
          </div>
        </div>
        <div class="plot-map-grid" id="plot-map" aria-live="polite"></div>
      </section>
    </main>
  </div>
</div>
<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<dialog class="hh-dialog" id="edit-plot-dialog" aria-labelledby="edit-plot-title">
  <form class="hh-dialog-box" id="edit-plot-form">
    <h3 id="edit-plot-title">Edit plot details</h3>
    <p class="hh-dialog-message" id="edit-plot-label"></p>
    <input type="hidden" id="edit-plot-id">
    <div class="field hh-dialog-field">
      <label for="edit-plot-location">City <span class="required">*</span></label>
      <select id="edit-plot-location" required>
        <option value="" disabled>Select city</option>
        <option>Caloocan</option><option>Las Piñas</option><option>Makati</option><option>Malabon</option>
        <option>Mandaluyong</option><option>Manila</option><option>Marikina</option><option>Muntinlupa</option>
        <option>Navotas</option><option>Parañaque</option><option>Pasay</option><option>Pasig</option>
        <option>Pateros</option><option>Quezon City</option><option>San Juan</option><option>Taguig</option>
        <option>Valenzuela</option>
      </select>
    </div>
    <div class="field hh-dialog-field">
      <label for="edit-plot-area">Area (m²) <span class="required">*</span></label>
      <input id="edit-plot-area" type="number" min="0.01" max="100000" step="0.01" required>
    </div>
    <div class="hh-dialog-actions">
      <button type="button" class="btn btn-ghost" id="cancel-edit-plot">Cancel</button>
      <button type="submit" class="btn btn-accent">Save details</button>
    </div>
  </form>
</dialog>
<script src="assets/staff.js?v=20"></script>
</body>
</html>