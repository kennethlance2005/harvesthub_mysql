<?php
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
$navTitle = 'Manage Gardeners';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Manage Gardeners</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=48">
</head>
<body class="account-page">
<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">
      
      <?php if (can('registrations.review')): ?>
      <!-- Pending Gardener Requests -->
      <div class="panel" style="margin-bottom: 24px;">
        <p class="panel-title">Pending Gardener Requests</p>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table">
            <thead><tr><th>Name</th><th>Email</th><th>Age</th><th>Location</th><th>Actions</th></tr></thead>
            <tbody id="pending-gardeners-table"></tbody>
          </table>
        </div>
        <p class="text-muted" id="pending-gardeners-empty" hidden style="margin-top: 12px;">No pending gardener requests.</p>
      </div>

      <?php endif; ?>

      <!-- Active Gardeners -->
      <?php if (can('accounts.view')): ?>
      <div class="panel">
          <div class="admin-table-heading" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <p class="panel-title" style="margin: 0;">Community Gardeners</p>
          <div class="admin-table-controls">
            <input type="search" id="search-gardeners" data-table-search="gardeners-table" placeholder="Search gardeners...">
            <select class="admin-status-filter" data-status-filter="gardeners-table" aria-label="Filter by status">
              <option value="">All statuses</option>
              <option value="Active">Active</option>
              <option value="Disabled">Disabled</option>
            </select>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table">
            <thead><tr><th>Name</th><th>Email</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="gardeners-table"></tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </main>
  </div>
</div>
<?php include __DIR__ . '/admin_modal_archive.php'; ?>
<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container"></div>
<script src="assets/admin.js?v=20"></script>
</body>
</html>
