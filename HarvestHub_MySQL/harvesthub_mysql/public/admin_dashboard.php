<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('admin');
$navTitle = 'System Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=8">
<!-- Load Chart.js for the graph -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">
      
      <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
          <div>
              <h2 style="margin: 0; font-family: var(--font-display); color: var(--green-900); font-size: 1.8rem;">System Overview</h2>
              
          </div>
          <div style="display: flex; gap: 12px;">
              <button type="button" class="btn btn-accent" id="export-report-btn">Export CSV</button>
              <button type="button" class="btn btn-accent" id="export-pdf-btn">Save as PDF</button>
          </div>
      </div>

      <div class="stat-grid" id="stats-row" style="margin-bottom: 28px;"></div>

      <div class="panel">
        <p class="panel-title">Platform Activity Graph</p>
        <div style="position: relative; height: 350px; width: 100%;">
          <canvas id="activityChart"></canvas>
        </div>
      </div>

    </main>
  </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js"></script>
</body>
</html>