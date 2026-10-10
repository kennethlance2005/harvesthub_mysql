<?php
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
$navTitle = 'Roles and Permissions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Roles and Permissions</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=48">
</head>
<body class="account-page">

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap gardener-page roles-page" id="top">

      <header class="page-head">
        <div>
          <p class="eyebrow">Access</p>
          <h1>Roles and Permissions</h1>
          <p class="text-muted">A role is a set of things someone is allowed to do. Everyone gets a role from their account type, and you can create extra roles (for example "Inventory Clerk") and give them to gardeners or coordinators.</p>
        </div>
        <button type="button" class="btn btn-accent" id="role-new">+ New role</button>
      </header>

      <!-- Roles -->
      <section class="panel roles-panel" aria-labelledby="roles-title">
        <h2 class="panel-title" id="roles-title">Roles</h2>
        <div class="roles-grid" id="roles-list">
          <p class="text-muted">Loading roles...</p>
        </div>
      </section>

      <!-- Members of the chosen role -->
      <section class="panel roles-panel" id="role-members" aria-labelledby="role-members-title" hidden>
        <div class="roles-section-head">
          <div>
            <h2 class="panel-title" id="role-members-title">Members</h2>
            <p class="text-muted roles-help" id="role-members-help"></p>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" id="role-members-close">Close</button>
        </div>
        <div class="role-add-person" id="role-add-person" hidden>
          <div class="field">
            <label for="role-person-search">Give this role to someone</label>
            <input type="search" id="role-person-search" placeholder="Search gardeners and coordinators by name or email..." autocomplete="off">
          </div>
          <ul class="role-candidates" id="role-candidates" aria-live="polite"></ul>
        </div>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table role-members-table">
            <thead><tr><th>Name</th><th>Email</th><th>Account</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="role-members-table"></tbody>
          </table>
        </div>
      </section>

      <!-- Permission matrix -->
      <section class="panel roles-panel" aria-labelledby="matrix-title">
        <div class="roles-section-head">
          <div>
            <h2 class="panel-title" id="matrix-title">What each role can do</h2>
            <p class="text-muted roles-help">Tick a box to allow a role to do something. Nothing changes until you press <strong>Save changes</strong>. Administrators always have every permission, so their column can't be changed.</p>
          </div>
        </div>
        <p class="matrix-swipe-hint">Swipe the table sideways to see every role.</p>
        <div class="table-wrap permission-matrix-wrap">
          <table class="permission-matrix" id="permission-matrix">
            <tbody><tr><td class="text-muted">Loading permissions...</td></tr></tbody>
          </table>
        </div>
        <div class="permission-matrix-actions">
          <p class="text-muted" id="matrix-status" aria-live="polite">No unsaved changes.</p>
          <button type="button" class="btn btn-ghost btn-sm" id="matrix-undo" disabled>Undo changes</button>
          <button type="button" class="btn btn-accent btn-sm" id="matrix-save" disabled>Save changes</button>
        </div>
      </section>

    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js?v=20"></script>
<script src="assets/roles.js?v=1"></script>
</body>
</html>
