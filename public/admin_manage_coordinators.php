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
<link rel="stylesheet" href="assets/style.css?v=8">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">
      
      <!-- Pending Coordinator Requests -->
      <div class="panel" style="margin-bottom: 24px;">
        <p class="panel-title">Pending Coordinator Requests</p>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Age</th><th>Location</th><th>Shift</th><th>Actions</th></tr></thead>
            <tbody id="pending-coordinators-table"></tbody>
          </table>
        </div>
        <p class="text-muted" id="pending-coordinators-empty" hidden style="margin-top: 12px;">No pending coordinator requests.</p>
      </div>

      <!-- Active Coordinators -->
      <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <p class="panel-title" style="margin: 0;">Active Garden Coordinators</p>
          <input type="search" id="search-coordinators" data-table-search="coordinators-table" placeholder="Search coordinators...">
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Shift</th><th>Location</th><th>Actions</th></tr></thead>
            <tbody id="coordinators-table"></tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>
<?php include __DIR__ . '/admin_modal_archive.php'; ?>
<div class="toast-container" id="toast-container"></div>
<script src="assets/admin.js"></script>
</body>
</html>