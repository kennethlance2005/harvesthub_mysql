<?php
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
$navTitle = 'Garden Overview';

// Tabs this person may see: [id => [label, permission]]
$tabs = array_filter([
    'plots' => ['Plots', 'plots.view'],
    'resources' => ['Resources', 'resources.view'],
    'requests' => ['Requests', null],
    'exchange' => ['Exchange', null],
    'registrations' => ['Registrations', 'registrations.review'],
], static fn ($tab) => $tab[1] === null || can($tab[1]));
$requestKinds = array_filter([
    'plot' => can('plots.view'),
    'resource' => can('resources.view'),
    'coordinator' => can('coordinator_applications.review'),
    'crop' => can('crops.review_catalog'),
]);
if (!$requestKinds) unset($tabs['requests']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Garden Overview</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=50">
</head>
<body class="account-page">

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap gardener-page overview-page" id="top">

      <header class="page-head">
        <div>
          <p class="eyebrow">Everything in one place</p>
          <h1>Garden Overview</h1>
          <p class="text-muted">Every plot, shared resource, request, exchange listing and registration, including finished and rejected ones. This page only shows information; make changes from the Plots, Inventory and Manage Accounts pages.</p>
        </div>
      </header>

      <div class="records-tabs overview-tabs" role="tablist" aria-label="Overview sections">
        <?php $first = true; foreach ($tabs as $id => [$label]): ?>
          <button class="records-tab<?= $first ? ' is-active' : '' ?>" type="button" role="tab" id="overview-tab-<?= $id ?>" aria-controls="overview-panel-<?= $id ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" data-overview-tab="<?= $id ?>"><?= $label ?></button>
        <?php $first = false; endforeach; ?>
      </div>

      <?php if (isset($tabs['plots'])): ?>
      <section class="panel overview-panel" id="overview-panel-plots" role="tabpanel" aria-labelledby="overview-tab-plots">
        <div class="overview-toolbar">
          <p class="text-muted">Who holds each plot and since when. Open a plot to see everything that happened to it.</p>
          <input type="search" class="overview-search" data-search="plots" placeholder="Search plot, gardener or city..." aria-label="Search plots">
        </div>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table">
            <thead><tr><th>Plot</th><th>Status</th><th>Held by</th><th>Held since</th><th>Requests</th><th>History</th></tr></thead>
            <tbody id="overview-plots"><tr class="admin-empty-row"><td colspan="6" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (isset($tabs['resources'])): ?>
      <section class="panel overview-panel" id="overview-panel-resources" role="tabpanel" aria-labelledby="overview-tab-resources" hidden>
        <div class="overview-toolbar">
          <p class="text-muted" id="overview-resources-help">Who is holding what right now.</p>
          <label class="overview-check"><input type="checkbox" id="overview-overdue-only"> Only overdue returns</label>
          <input type="search" class="overview-search" data-search="resources" placeholder="Search resource or gardener..." aria-label="Search resources">
        </div>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table">
            <thead><tr><th>Resource</th><th>Gardener</th><th>Quantity</th><th>Held for</th><th>Return</th></tr></thead>
            <tbody id="overview-resources"><tr class="admin-empty-row"><td colspan="5" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (isset($tabs['requests'])): ?>
      <section class="panel overview-panel" id="overview-panel-requests" role="tabpanel" aria-labelledby="overview-tab-requests" hidden>
        <div class="overview-toolbar">
          <select id="overview-request-kind" aria-label="Request type">
            <option value="">All request types</option>
            <?php foreach (['plot' => 'Plot requests', 'resource' => 'Resource requests', 'coordinator' => 'Coordinator applications', 'crop' => 'Crop catalog requests'] as $kind => $label): ?>
              <?php if (isset($requestKinds[$kind])): ?><option value="<?= $kind ?>"><?= $label ?></option><?php endif; ?>
            <?php endforeach; ?>
          </select>
          <select id="overview-request-outcome" aria-label="Outcome">
            <option value="">Every outcome</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
            <option value="Cancelled">Cancelled</option>
          </select>
          <input type="search" class="overview-search" data-search="requests" placeholder="Search gardener, item or reason..." aria-label="Search requests">
        </div>
        <p class="text-muted overview-count" id="overview-requests-count" aria-live="polite"></p>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table requests-table">
            <thead><tr><th>Requested</th><th>Gardener</th><th>Request</th><th>Outcome</th><th>Decided by</th><th>Reason or notes</th></tr></thead>
            <tbody id="overview-requests"><tr class="admin-empty-row"><td colspan="6" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (isset($tabs['exchange'])): ?>
      <section class="panel overview-panel" id="overview-panel-exchange" role="tabpanel" aria-labelledby="overview-tab-exchange" hidden>
        <div class="overview-toolbar">
          <p class="text-muted">Every listing on the exchange board and the claims made on it.</p>
          <input type="search" class="overview-search" data-search="exchange" placeholder="Search produce or gardener..." aria-label="Search listings">
        </div>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table">
            <thead><tr><th>Listing</th><th>Posted by</th><th>Status</th><th>Claims</th></tr></thead>
            <tbody id="overview-exchange"><tr class="admin-empty-row"><td colspan="4" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (isset($tabs['registrations'])): ?>
      <section class="panel overview-panel" id="overview-panel-registrations" role="tabpanel" aria-labelledby="overview-tab-registrations" hidden>
        <div class="overview-toolbar">
          <select id="overview-registration-status" aria-label="Registration status">
            <option value="">Every status</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
          </select>
          <input type="search" class="overview-search" data-search="registrations" placeholder="Search name, email or reason..." aria-label="Search registrations">
        </div>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table">
            <thead><tr><th>Applicant</th><th>Signed up</th><th>Status</th><th>Reviewed</th><th>Reason</th></tr></thead>
            <tbody id="overview-registrations"><tr class="admin-empty-row"><td colspan="5" class="text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js?v=22"></script>
<script src="assets/overview.js?v=2"></script>
</body>
</html>
