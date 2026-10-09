<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('admin');
$navTitle = 'Manage Coordinators';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Manage Coordinators</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=29">
</head>
<body class="account-page">
<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">
      
      <!-- Pending Coordinator Applications -->
      <div class="panel" style="margin-bottom: 24px;">
        <p class="panel-title">Pending Coordinator Applications</p>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table">
            <thead><tr><th>Name</th><th>Email</th><th>Location</th><th>Shift</th><th>Why they want to coordinate</th><th>Actions</th></tr></thead>
            <tbody id="pending-coordinator-applications-table"></tbody>
          </table>
        </div>
        <p class="text-muted" id="pending-coordinator-applications-empty" hidden style="margin-top: 12px;">No pending coordinator applications.</p>
      </div>

      <!-- Active Coordinators -->
      <div class="panel">
          <div class="admin-table-heading" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <p class="panel-title" style="margin: 0;">Garden Coordinators</p>
          <input type="search" id="search-coordinators" data-table-search="coordinators-table" placeholder="Search coordinators...">
        </div>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table">
            <thead><tr><th>Name</th><th>Email</th><th>Shift</th><th>Location</th><th>Actions</th></tr></thead>
            <tbody id="coordinators-table"></tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>
<?php include __DIR__ . '/admin_modal_archive.php'; ?>
<?php include __DIR__ . '/admin_modal_reason.php'; ?>
<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container"></div>
<script src="assets/admin.js?v=11"></script>
</body>
</html>
