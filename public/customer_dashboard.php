<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'Gardener Dashboard';
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
<link rel="stylesheet" href="assets/style.css?v=5">
<style>
  .kpi-value { font-size: 2.5rem; font-weight: 700; color: var(--accent); margin: 8px 0; font-family: 'Fraunces', serif; }
  .kpi-link { font-size: 0.85rem; font-weight: 600; text-decoration: none; color: var(--text); transition: color 0.2s ease; }
  .kpi-link:hover { color: var(--accent); }
  .panel-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 16px; }
</style>
</head>
<body>

<!-- The new Flexbox layout wrapper -->
<div class="app-layout">
  
  <!-- Inject the sidebar on the left -->
  <?php include __DIR__ . '/customer_sidebar.php'; ?>

  <!-- Scrollable main workspace on the right -->
  <div class="main-content">
    
    <main class="wrap" id="top" style="max-width: 1100px; padding-top: 32px;">
      
      <!-- Welcome Banner -->
      <div style="margin-bottom: 32px;">
        <h1>Welcome back to the garden.</h1>
        <p class="text-muted">Here is your high-level overview for today.</p>
      </div>

      <!-- Top Row: KPI Cards -->
      <div class="grid grid-3" style="margin-bottom: 32px;">
        <div class="panel">
          <p class="panel-title">Active Plots</p>
          <p class="kpi-value" id="kpi-plots">-</p>
          <a href="customer_plots.php" class="kpi-link">Manage your plots &rarr;</a>
        </div>
        
        <div class="panel">
          <p class="panel-title">Pending Resources</p>
          <p class="kpi-value" id="kpi-resources">-</p>
          <a href="customer_inventory.php" class="kpi-link">View inventory tracker &rarr;</a>
        </div>

        <div class="panel">
          <p class="panel-title">My Exchange Listings</p>
          <p class="kpi-value" id="kpi-listings">-</p>
          <a href="customer_exchange.php" class="kpi-link">Go to the board &rarr;</a>
        </div>
      </div>

      <!-- Middle Row: Activity Summaries -->
      <div class="grid grid-2">
        <div class="panel">
          <div class="panel-header">
            <p class="panel-title" style="margin: 0;">Recent Maintenance</p>
            <a href="customer_plots.php" class="kpi-link" style="font-weight: 400;">Log new entry</a>
          </div>
          <div id="recent-logs-list" class="scroll-y" style="max-height: 220px;" aria-live="polite">
            <p class="empty-state">Loading recent logs...</p>
          </div>
        </div>

        <div class="panel">
          <div class="panel-header">
            <p class="panel-title" style="margin: 0;">New on the Exchange</p>
            <a href="customer_exchange.php" class="kpi-link" style="font-weight: 400;">Browse all</a>
          </div>
          <div id="recent-exchange-list" class="scroll-y" style="max-height: 220px;" aria-live="polite">
            <p class="empty-state">Loading latest produce...</p>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/app.js"></script>
<script src="assets/customer.js?v=4"></script>
</body>
</html>