<?php
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/reports.php';
$navTitle = 'Reports';
$reports = availableReports();
$h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Reports</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=60">
</head>
<body class="account-page">

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap gardener-page reports-page" id="top" data-can-export="<?= can('reports.export') ? '1' : '0' ?>">

      <header class="page-head">
        <div>
          <p class="eyebrow">Reports</p>
          <h1>Reports</h1>
          <p class="text-muted">Pick a report and a time period. Every report can be downloaded as a spreadsheet (CSV) or printed and saved as a PDF.</p>
        </div>
      </header>

      <div class="reports-layout">
        <nav class="reports-menu" aria-label="Reports">
          <label class="sr-only" for="report-select">Report</label>
          <select id="report-select" class="reports-menu-select">
            <?php foreach ($reports as $key => [$title]): ?>
              <option value="<?= $h($key) ?>"><?= $h($title) ?></option>
            <?php endforeach; ?>
          </select>
          <ul class="reports-menu-list">
            <?php foreach ($reports as $key => [$title, $description]): ?>
              <li><button type="button" class="reports-menu-item" data-report="<?= $h($key) ?>"><strong><?= $h($title) ?></strong><small><?= $h($description) ?></small></button></li>
            <?php endforeach; ?>
          </ul>
        </nav>

        <section class="reports-main" aria-live="polite">
          <div class="panel dash-filters reports-filters">
            <div class="dash-range" role="group" aria-label="Time period">
              <button type="button" class="dash-range-btn" data-range="today">Today</button>
              <button type="button" class="dash-range-btn" data-range="7">Last 7 days</button>
              <button type="button" class="dash-range-btn is-active" data-range="30" aria-pressed="true">Last 30 days</button>
              <button type="button" class="dash-range-btn" data-range="90">Last 90 days</button>
              <button type="button" class="dash-range-btn" data-range="365">Last 12 months</button>
              <button type="button" class="dash-range-btn" data-range="custom">Custom dates</button>
            </div>
            <form class="dash-custom" id="report-custom" hidden novalidate>
              <label>From <input type="date" id="report-from"></label>
              <label>To <input type="date" id="report-to"></label>
              <button type="submit" class="btn btn-accent btn-sm">Show</button>
            </form>
            <div class="reports-extra" id="report-person-wrap" hidden>
              <label for="report-person">Person</label>
              <select id="report-person"><option value="">Everyone</option></select>
            </div>
            <div class="reports-extra" id="report-module-wrap" hidden>
              <label for="report-module">Area</label>
              <select id="report-module">
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
          </div>

          <article class="panel report-view" id="report-view">
            <header class="report-head">
              <div>
                <h2 id="report-title">Loading...</h2>
                <p class="text-muted" id="report-description"></p>
                <p class="report-period" id="report-period"></p>
              </div>
              <?php if (can('reports.export')): ?>
              <div class="report-actions">
                <button type="button" class="btn btn-ghost btn-sm" id="report-print">Print / Save as PDF</button>
                <button type="button" class="btn btn-accent btn-sm" id="report-csv">Download CSV</button>
              </div>
              <?php endif; ?>
            </header>
            <div class="dash-kpis report-summary" id="report-summary"></div>
            <div id="report-tables"></div>
          </article>
        </section>
      </div>

    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js?v=25"></script>
<script src="assets/reports.js?v=3"></script>
</body>
</html>
