<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('admin');
$navTitle = 'Archived Accounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Archived Accounts</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=8">
</head>
<body>

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">
      <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
          <div>
            <h2 style="margin-bottom: 4px;">Archived Accounts</h2>
            <p class="text-muted" style="margin: 0;">These accounts are disabled and cannot log in. Unarchive them to restore access.</p>
          </div>
          <input type="search" id="search-archived" data-table-search="archived-table" placeholder="Search by name or email..." style="border-radius: 10px;">
        </div>
        
        <div class="table-wrap">
          <table class="data-table" id="archived-data-table">
            <thead>
              <tr>
                <th style="cursor: pointer;" onclick="sortTable(0)">Name <span id="sort-icon-0"></span></th>
                <th style="cursor: pointer;" onclick="sortTable(1)">Email <span id="sort-icon-1"></span></th>
                <th style="cursor: pointer;" onclick="sortTable(2)">Role <span id="sort-icon-2"></span></th>
                <th style="cursor: pointer;" onclick="sortTable(3)">Location <span id="sort-icon-3"></span></th>
                <th style="cursor: pointer;" onclick="sortTable(4)">Shift <span id="sort-icon-4"></span></th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="archived-table">
               <tr><td colspan="6" class="text-muted">Loading archives...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js"></script>
</body>
</html>