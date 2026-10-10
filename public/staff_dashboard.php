<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('staff');
$navTitle = 'Coordinator Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Coordinator Dashboard</title>
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
        <div>
          <p class="eyebrow">Garden operations</p>
          <h1>Dashboard</h1>
          <p class="text-muted">A current snapshot of plots, requests, and shared resources.</p>
        </div>
        <span class="coordinator-greeting">Hi, <?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
      </header>

      <section class="coordinator-stat-grid" id="coordinator-stats" aria-label="Garden overview">
        <div class="stat-card"><div class="stat-value">—</div><div class="stat-label">Pending resource requests</div></div>
        <div class="stat-card"><div class="stat-value">—</div><div class="stat-label">Available plots</div></div>
        <div class="stat-card"><div class="stat-value">—</div><div class="stat-label">Resource types</div></div>
      </section>

      <section class="coordinator-shortcuts" aria-label="Coordinator work areas">
        <a class="coordinator-shortcut" href="staff_plots.php">
          <span class="shortcut-index">01</span>
          <span><strong>Plots</strong><small>Review applications and manage the plot map</small></span>
          <span class="shortcut-arrow" aria-hidden="true">→</span>
        </a>
        <a class="coordinator-shortcut" href="staff_inventory.php">
          <span class="shortcut-index">02</span>
          <span><strong>Inventory</strong><small>Approve resource requests and check stock</small></span>
          <span class="shortcut-arrow" aria-hidden="true">→</span>
        </a>
      </section>
    </main>
  </div>
</div>
<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/staff.js?v=19"></script>
</body>
</html>
