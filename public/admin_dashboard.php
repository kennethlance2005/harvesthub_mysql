<?php
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
$navTitle = 'Dashboard';

// One chart card: plain-English title, a one-line explanation, the chart, and a table view.
function dashCard(string $id, string $title, string $help, bool $wide = false): string {
    $h = static fn ($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    return '<article class="dash-card' . ($wide ? ' dash-card-wide' : '') . '" id="card-' . $h($id) . '" aria-labelledby="title-' . $h($id) . '">'
        . '<header class="dash-card-head"><div><h3 id="title-' . $h($id) . '">' . $h($title) . '</h3><p class="dash-card-help">' . $h($help) . '</p></div>'
        . '<button type="button" class="btn btn-ghost btn-sm dash-table-toggle" data-card="' . $h($id) . '" aria-pressed="false">Show as table</button></header>'
        . '<div class="dash-chart" data-chart="' . $h($id) . '"><canvas role="img" aria-label="' . $h($title) . '"></canvas><p class="dash-empty" hidden></p></div>'
        . '<div class="dash-table" data-table="' . $h($id) . '" hidden></div>'
        . '<p class="dash-card-note" data-note="' . $h($id) . '" hidden></p>'
        . '</article>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=63">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="account-page">

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap gardener-page dash-page" id="top">

      <header class="page-head">
        <div>
          <p class="eyebrow">Overview</p>
          <h1>Dashboard</h1>
          <p class="text-muted">How the garden is doing: plots, shared resources, requests and people. Pick a time period below; every chart follows it.</p>
        </div>
        <?php if (can('reports.export')): ?>
        <div class="dash-export">
          <button type="button" class="btn btn-ghost btn-sm" id="dash-export-pdf">Save as PDF</button>
          <button type="button" class="btn btn-accent btn-sm" id="dash-export-csv">Download CSV</button>
        </div>
        <?php endif; ?>
      </header>

      <!-- First-visit pointer to the guide (hidden again once dismissed, in this browser) -->
      <aside class="panel dash-welcome" id="dash-welcome" hidden>
        <div>
          <h2>New to HarvestHub?</h2>
          <p class="text-muted">The five-minute guide explains your daily check, everyday tasks like approving sign-ups, and the words HarvestHub uses.</p>
        </div>
        <div class="dash-welcome-actions">
          <a class="btn btn-accent btn-sm" href="admin_help.php">Read the guide</a>
          <button type="button" class="btn btn-ghost btn-sm" id="dash-welcome-close">Don't show again</button>
        </div>
      </aside>

      <!-- One filter row for every chart -->
      <section class="panel dash-filters" aria-label="Time period">
        <div class="dash-range" role="group" aria-label="Time period">
          <button type="button" class="dash-range-btn" data-range="today">Today</button>
          <button type="button" class="dash-range-btn" data-range="7">Last 7 days</button>
          <button type="button" class="dash-range-btn is-active" data-range="30" aria-pressed="true">Last 30 days</button>
          <button type="button" class="dash-range-btn" data-range="90">Last 90 days</button>
          <button type="button" class="dash-range-btn" data-range="365">Last 12 months</button>
          <button type="button" class="dash-range-btn" data-range="custom">Custom dates</button>
        </div>
        <form class="dash-custom" id="dash-custom" hidden novalidate>
          <label>From <input type="date" id="dash-from"></label>
          <label>To <input type="date" id="dash-to"></label>
          <button type="submit" class="btn btn-accent btn-sm">Show</button>
        </form>
        <p class="dash-range-label" id="dash-range-label" aria-live="polite">Loading...</p>
      </section>

      <section class="dash-kpis" id="dash-kpis" aria-label="Headline numbers"></section>

      <section class="panel dash-attention" aria-labelledby="dash-attention-title">
        <h2 class="panel-title" id="dash-attention-title">Needs attention</h2>
        <p class="dash-card-help">Things waiting on someone right now, whatever time period is picked.</p>
        <ul class="dash-attention-list" id="dash-attention"></ul>
      </section>

      <h2 class="dash-section-title">The garden</h2>
      <div class="dash-grid">
        <?= dashCard('occupancy', 'Plots in use over time', 'How many plots had a gardener, compared with how many plots there were.', true) ?>
        <?= dashCard('crops', 'Crops by status', 'Every crop gardeners have logged, by where it is now. Not affected by the time period.') ?>
        <?= dashCard('harvests', 'Harvests logged', 'How many times gardeners marked a crop harvested or recorded a yield.') ?>
      </div>

      <h2 class="dash-section-title">Shared resources</h2>
      <div class="dash-grid">
        <?= dashCard('levels', 'Stock right now', 'For each shared item: how many are on the shelf and how many are out with gardeners.', true) ?>
        <?= dashCard('borrowed', 'Most borrowed', 'Items lent out most often in this period, by quantity.') ?>
        <?= dashCard('held', 'Items out, by how long', 'Everything currently borrowed, grouped by how long the gardener has had it.') ?>
      </div>

      <h2 class="dash-section-title">Requests and decisions</h2>
      <div class="dash-grid">
        <?= dashCard('requests', 'Requests over time', 'New plot, resource and coordinator requests made in each period.', true) ?>
        <?= dashCard('outcomes', 'Approved and declined', 'What happened to requests decided in this period, and how long a decision took.') ?>
        <?= dashCard('reasons', 'Most common reasons for declining', 'Reasons written when declining requests and sign-ups. The same wording is counted together.') ?>
        <?= dashCard('workload', 'Coordinator workload', 'Decisions each coordinator made in this period.', true) ?>
      </div>

      <h2 class="dash-section-title">People</h2>
      <div class="dash-grid">
        <?= dashCard('newusers', 'New accounts', 'Sign-ups approved and administrators added in each period.') ?>
        <?= dashCard('registrations', 'Sign-ups by status', 'Sign-up requests made in this period and where they stand now.') ?>
        <?= dashCard('signins', 'Sign-in activity', 'People who used HarvestHub, and wrong-password attempts, in each period.') ?>
        <?= dashCard('locations', 'Gardeners by location', 'Where gardeners are based. Not affected by the time period.') ?>
      </div>

    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js?v=25"></script>
<script src="assets/dashboard.js?v=2"></script>
</body>
</html>
