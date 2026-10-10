<?php
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
$navTitle = 'User Activity';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — User Activity</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=50">
</head>
<body class="account-page">

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap gardener-page activity-page" id="top">

      <header class="page-head">
        <div>
          <p class="eyebrow">People</p>
          <h1>User Activity</h1>
          <p class="text-muted">When each person last signed in, how often they use HarvestHub, and failed sign-in attempts. Open someone to see their full profile and history.</p>
        </div>
      </header>

      <section class="activity-stats" id="activity-stats" aria-label="Summary"></section>

      <section class="panel audit-filters" aria-label="Filter people">
        <form id="activity-filter-form" class="audit-filter-grid activity-filter-grid" novalidate>
          <div class="field audit-filter-search">
            <label for="activity-search">Search</label>
            <input type="search" id="activity-search" placeholder="Name, email or location...">
          </div>
          <div class="field">
            <label for="activity-type">Account</label>
            <select id="activity-type">
              <option value="">Everyone</option>
              <option value="Gardener">Gardeners</option>
              <option value="Coordinator">Coordinators</option>
              <option value="Administrator">Administrators</option>
            </select>
          </div>
          <div class="field">
            <label for="activity-inactive">Last signed in</label>
            <select id="activity-inactive">
              <option value="">Any time</option>
              <option value="30">Not in the last 30 days</option>
              <option value="60">Not in the last 60 days</option>
              <option value="90">Not in the last 90 days</option>
              <option value="never">No sign-in recorded</option>
            </select>
          </div>
          <div class="field">
            <label for="activity-status">Status</label>
            <select id="activity-status">
              <option value="current">Active and locked</option>
              <option value="">All, including archived</option>
              <option value="Disabled">Locked only</option>
              <option value="Archived">Archived only</option>
            </select>
          </div>
          <div class="audit-filter-actions">
            <button type="button" class="btn btn-ghost btn-sm" id="activity-reset">Clear filters</button>
          </div>
        </form>
      </section>

      <section class="panel audit-results" aria-labelledby="activity-results-title">
        <div class="audit-results-head">
          <h2 class="panel-title" id="activity-results-title">People</h2>
          <p class="text-muted" id="activity-count" aria-live="polite">Loading...</p>
        </div>
        <p class="text-muted activity-note" id="activity-tracking-note"></p>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table activity-table">
            <thead><tr><th>Name</th><th>Roles</th><th>Status</th><th>Last signed in</th><th>Sign-ins</th><th>Failed sign-ins</th></tr></thead>
            <tbody id="activity-table">
              <tr class="admin-empty-row"><td colspan="6" class="text-muted">Loading people...</td></tr>
            </tbody>
          </table>
        </div>
      </section>

    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js?v=22"></script>
<script src="assets/people.js?v=2"></script>
</body>
</html>
