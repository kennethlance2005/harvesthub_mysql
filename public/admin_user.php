<?php
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
$navTitle = 'Profile';
$profileType = in_array($_GET['type'] ?? '', ['gardener', 'coordinator', 'admin'], true) ? $_GET['type'] : '';
$profileId = ctype_digit((string) ($_GET['id'] ?? '')) ? (int) $_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Profile</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=50">
</head>
<body class="account-page">

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap gardener-page profile-page" id="top" data-profile-type="<?= htmlspecialchars($profileType, ENT_QUOTES, 'UTF-8') ?>" data-profile-id="<?= $profileId ?>">
      <a class="profile-back" href="admin_activity.php">← All people</a>

      <header class="page-head profile-head" id="profile-head">
        <div>
          <p class="eyebrow">Profile</p>
          <h1 id="profile-name">Loading...</h1>
          <p class="text-muted" id="profile-subtitle"></p>
        </div>
      </header>

      <section class="activity-stats" id="profile-stats" aria-label="Activity summary"></section>

      <div class="profile-columns">
        <section class="panel profile-panel" aria-labelledby="profile-details-title">
          <h2 class="panel-title" id="profile-details-title">Details</h2>
          <dl class="review-grid" id="profile-details"></dl>
        </section>
        <section class="panel profile-panel" aria-labelledby="profile-signin-title">
          <h2 class="panel-title" id="profile-signin-title">Sign-in</h2>
          <dl class="review-grid" id="profile-signin"></dl>
          <p class="text-muted activity-note" id="profile-tracking-note"></p>
        </section>
      </div>

      <section class="panel profile-panel" aria-labelledby="profile-roles-title">
        <h2 class="panel-title" id="profile-roles-title">Roles</h2>
        <div id="profile-roles"></div>
      </section>

      <section class="panel profile-panel" id="profile-crops-section" aria-labelledby="profile-crops-title" hidden>
        <h2 class="panel-title" id="profile-crops-title">Crops and maintenance</h2>
        <div id="profile-crops"></div>
      </section>

      <section class="panel profile-panel" id="profile-requests-section" aria-labelledby="profile-requests-title" hidden>
        <div class="audit-results-head">
          <h2 class="panel-title" id="profile-requests-title">Requests</h2>
          <label class="sr-only" for="profile-requests-filter">Show requests</label>
          <select id="profile-requests-filter" class="profile-select">
            <option value="">All requests</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
            <option value="Cancelled">Cancelled</option>
          </select>
        </div>
        <div class="table-wrap">
          <table class="data-table admin-responsive-table requests-table">
            <thead><tr><th>Requested</th><th>Request</th><th>Outcome</th><th>Decided by</th><th>Reason or notes</th></tr></thead>
            <tbody id="profile-requests"></tbody>
          </table>
        </div>
      </section>

      <section class="panel profile-panel" aria-labelledby="profile-timeline-title">
        <h2 class="panel-title" id="profile-timeline-title">Activity timeline</h2>
        <ol class="profile-timeline" id="profile-timeline"></ol>
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
