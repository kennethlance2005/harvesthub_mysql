<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'Gardener Dashboard';
$firstName = explode(' ', trim($user['name']))[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=36">
</head>
<body class="account-page">

<!-- The new Flexbox layout wrapper -->
<div class="app-layout">

  <!-- Inject the sidebar on the left -->
  <?php include __DIR__ . '/customer_sidebar.php'; ?>

  <!-- Scrollable main workspace on the right -->
  <div class="main-content">

    <main class="wrap gardener-page customer-dashboard-page" id="top">

      <!-- Welcome Banner -->
      <header class="page-head">
        <div>
          <p class="eyebrow">Gardener dashboard</p>
          <h1>Welcome back, <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>.</h1>
          <p class="text-muted">Here is your overview of the garden today.</p>
        </div>
      </header>

      <section class="panel dash-notice" id="archive-notice-panel" hidden aria-live="polite">
        <h2>Important account notice</h2>
        <p>Your account will be archived for this reason unless the issue is resolved:</p>
        <p><strong id="archive-notice-reason"></strong></p>
        <p id="archive-notice-details" class="dash-notice-details"></p>
        <p>To resolve this, please return borrowed items and request unassignment of any plots assigned to you.</p>
        <p class="dash-notice-links">
          <a href="customer_inventory.php">Go to Resource Inventory</a>
          <span aria-hidden="true"> · </span>
          <a href="customer_plots.php">Go to My Plots</a>
        </p>
      </section>

      <!-- Top Row: KPI Cards -->
      <div class="dash-kpis">
        <a class="panel dash-kpi" href="customer_plots.php">
          <span class="dash-kpi-label">Active plots</span>
          <span class="dash-kpi-value" id="kpi-plots">–</span>
          <span class="dash-kpi-link">View my plots <span aria-hidden="true">→</span></span>
        </a>
        <a class="panel dash-kpi" href="customer_inventory.php">
          <span class="dash-kpi-label">Pending resource requests</span>
          <span class="dash-kpi-value" id="kpi-resources">–</span>
          <span class="dash-kpi-link">Go to inventory <span aria-hidden="true">→</span></span>
        </a>
        <a class="panel dash-kpi" href="customer_exchange.php">
          <span class="dash-kpi-label">My exchange listings</span>
          <span class="dash-kpi-value" id="kpi-listings">–</span>
          <span class="dash-kpi-link">Open exchange board <span aria-hidden="true">→</span></span>
        </a>
      </div>

      <!-- Middle Row: Activity Summaries -->
      <div class="dash-activity">
        <section class="panel">
          <div class="dash-panel-head">
            <h2 class="panel-title">Recent maintenance</h2>
            <a class="dash-panel-link" href="customer_crops.php">View all</a>
          </div>
          <div id="recent-logs-list" class="dash-list scroll-y" aria-live="polite">
            <p class="dash-empty">Loading recent logs...</p>
          </div>
        </section>

        <section class="panel">
          <div class="dash-panel-head">
            <h2 class="panel-title">New on the exchange</h2>
            <a class="dash-panel-link" href="customer_exchange.php">Browse</a>
          </div>
          <div id="recent-exchange-list" class="dash-list scroll-y" aria-live="polite">
            <p class="dash-empty">Loading latest produce...</p>
          </div>
        </section>
      </div>

      <?php if (hasRole('staff')): ?>
      <section class="panel dash-coordinator">
        <div class="dash-coordinator-intro">
          <h2 class="panel-title">Coordinator workspace</h2>
          <p class="text-muted">You have coordinator access as well as your gardener account.</p>
        </div>
        <a class="btn btn-accent btn-sm" href="staff_dashboard.php">Open coordinator dashboard</a>
      </section>
      <?php else: ?>
      <section class="panel dash-coordinator" id="coordinator-application-panel" hidden>
        <div class="dash-coordinator-intro">
          <h2 class="panel-title">Become a garden coordinator</h2>
          <p class="text-muted">Coordinators review plot and resource requests and help keep the garden running. An administrator will review your application.</p>
        </div>
        <div class="dash-coordinator-body">
          <div id="coordinator-application-status" aria-live="polite"></div>
          <form id="coordinator-application-form" class="dash-coordinator-form">
            <div class="field">
              <label for="coordinator-shift">Preferred shift <span class="required">*</span></label>
              <select id="coordinator-shift" name="shift" required>
                <option value="Morning">Morning</option>
                <option value="Afternoon">Afternoon</option>
                <option value="Evening">Evening</option>
              </select>
            </div>
            <div class="field">
              <label for="coordinator-motivation">Why would you like to coordinate? <span class="required">*</span></label>
              <textarea id="coordinator-motivation" name="motivation" minlength="20" maxlength="1000" rows="4" required placeholder="Tell us about your gardening experience and how you'd like to help."></textarea>
              <p class="field-hint">At least 20 characters.</p>
            </div>
            <button class="btn btn-accent" type="submit">Submit application</button>
          </form>
        </div>
      </section>
      <?php endif; ?>

    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/app.js"></script>
<script src="assets/customer.js?v=13"></script>
</body>
</html>
