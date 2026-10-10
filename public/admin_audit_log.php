<?php
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
$navTitle = 'Audit Log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Audit Log</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=50">
</head>
<body class="account-page">

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap gardener-page audit-page" id="top">

      <header class="page-head">
        <div>
          <p class="eyebrow">Accountability</p>
          <h1>Audit Log</h1>
          <p class="text-muted">Every important action in HarvestHub: who did it, what happened, and when. Entries can't be changed or deleted by anyone.</p>
        </div>
      </header>

      <!-- Filters -->
      <section class="panel audit-filters" aria-label="Filter the audit log">
        <form id="audit-filter-form" class="audit-filter-grid" novalidate>
          <div class="field audit-filter-search">
            <label for="audit-search">Search</label>
            <input type="search" id="audit-search" placeholder="Name, email, plot, reason...">
          </div>
          <div class="field">
            <label for="audit-module">Area</label>
            <select id="audit-module">
              <option value="">All areas</option>
              <option value="accounts">Accounts and sign-in</option>
              <option value="roles">Roles</option>
              <option value="plots">Plots</option>
              <option value="resources">Resources</option>
              <option value="exchange">Exchange</option>
              <option value="crops">Crops</option>
              <option value="admin">Admin tools</option>
            </select>
          </div>
          <div class="field">
            <label for="audit-actor-type">Who</label>
            <select id="audit-actor-type">
              <option value="">Everyone</option>
              <option value="admin">Administrators</option>
              <option value="coordinator">Coordinators</option>
              <option value="gardener">Gardeners</option>
              <option value="guest">Visitors (not logged in)</option>
              <option value="system">HarvestHub (automatic)</option>
            </select>
          </div>
          <div class="field">
            <label for="audit-action">Action</label>
            <select id="audit-action">
              <option value="">All actions</option>
            </select>
          </div>
          <div class="field">
            <label for="audit-range">When</label>
            <select id="audit-range">
              <option value="all">All time</option>
              <option value="today">Today</option>
              <option value="7">Last 7 days</option>
              <option value="30">Last 30 days</option>
              <option value="custom">Custom dates</option>
            </select>
          </div>
          <div class="field audit-custom-dates" id="audit-custom-dates" hidden>
            <label for="audit-from">From</label>
            <input type="date" id="audit-from">
          </div>
          <div class="field audit-custom-dates" id="audit-custom-dates-to" hidden>
            <label for="audit-to">To</label>
            <input type="date" id="audit-to">
          </div>
          <div class="audit-filter-actions">
            <button type="button" class="btn btn-ghost btn-sm" id="audit-reset">Clear filters</button>
            <?php if (can('reports.export')): ?>
            <button type="button" class="btn btn-accent btn-sm" id="audit-export">Download CSV</button>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <!-- Results -->
      <section class="panel audit-results" aria-labelledby="audit-results-title">
        <div class="audit-results-head">
          <h2 class="panel-title" id="audit-results-title">Activity</h2>
          <p class="text-muted" id="audit-count" aria-live="polite">Loading...</p>
        </div>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table audit-table">
            <thead><tr><th>When</th><th>Who</th><th>What happened</th><th>Reason</th></tr></thead>
            <tbody id="audit-table">
              <tr class="admin-empty-row"><td colspan="4" class="text-muted">Loading the audit log...</td></tr>
            </tbody>
          </table>
        </div>
        <nav class="audit-pager" aria-label="Audit log pages">
          <button type="button" class="btn btn-ghost btn-sm" id="audit-prev">← Newer</button>
          <span id="audit-page-label" class="text-muted"></span>
          <button type="button" class="btn btn-ghost btn-sm" id="audit-next">Older →</button>
        </nav>
      </section>

    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js?v=22"></script>
<script src="assets/audit.js?v=3"></script>
</body>
</html>
