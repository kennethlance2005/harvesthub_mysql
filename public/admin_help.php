<?php
require_once __DIR__ . '/auth.php';
$user = requirePageAccess();
$navTitle = 'Getting started';
// Each section only links to pages this person can open.
$link = static fn (string $page, string $text): string => canOpen($page)
    ? '<a href="' . $page . '">' . $text . '</a>'
    : '<strong>' . $text . '</strong>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub â€” Getting started</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=63">
</head>
<body class="account-page">

<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap gardener-page help-page" id="top">

      <header class="page-head">
        <div>
          <p class="eyebrow">Help</p>
          <h1>Getting started</h1>
          <p class="text-muted">A five-minute guide to running HarvestHub. You can come back to it any time from <strong>Help</strong> in the menu.</p>
        </div>
      </header>

      <nav class="panel help-contents" aria-label="On this page">
        <p class="panel-title">On this page</p>
        <ol>
          <li><a href="#daily">Your daily check</a></li>
          <li><a href="#tasks">Everyday tasks</a></li>
          <li><a href="#find">Finding information</a></li>
          <li><a href="#words">Words HarvestHub uses</a></li>
          <li><a href="#safe">Good to know</a></li>
        </ol>
      </nav>

      <section class="panel help-section" id="daily" aria-labelledby="daily-title">
        <h2 id="daily-title">1. Your daily check</h2>
        <p>Open the <?= $link('admin_dashboard.php', 'Dashboard') ?> and look at <strong>Needs attention</strong>. It lists everything waiting on someone: sign-ups to approve, requests to answer, locked accounts and overdue returns. Each line has an <strong>Open</strong> button that takes you straight to the right page.</p>
        <p>When it says <em>Nothing is waiting</em>, you are done for the day.</p>
      </section>

      <section class="panel help-section" id="tasks" aria-labelledby="tasks-title">
        <h2 id="tasks-title">2. Everyday tasks</h2>
        <dl class="help-tasks">
          <div>
            <dt>Approve someone who signed up</dt>
            <dd>Go to <?= $link('admin_manage_gardeners.php', 'Manage Accounts â†’ Gardeners') ?>. Under <em>Pending Gardener Requests</em>, press <strong>Review</strong>, check their details, then <strong>Approve</strong>. If you decline, write a short reason: they will see it.</dd>
          </div>
          <div>
            <dt>Make a gardener a coordinator</dt>
            <dd>Gardeners apply from their own dashboard. Their application appears in <?= $link('admin_manage_coordinators.php', 'Manage Accounts â†’ Coordinators') ?>. <strong>Review</strong> shows their answers and how active they have been in the garden.</dd>
          </div>
          <div>
            <dt>Someone can't sign in</dt>
            <dd>After 3 wrong passwords an account locks itself. Find the person in Manage Accounts and press <strong>Enable Account</strong>. If they forgot their password, they can use <em>Forgot password</em> on the sign-in page.</dd>
          </div>
          <div>
            <dt>Someone should stop using HarvestHub</dt>
            <dd><strong>Archive</strong> their account. They can no longer sign in and their plots are freed up, but nothing is deleted. You can <strong>Unarchive</strong> them later from <?= $link('admin_archived_accounts.php', 'Archived Accounts') ?>.</dd>
          </div>
          <div>
            <dt>A coordinator should go back to being a gardener</dt>
            <dd>Use <strong>Remove role</strong> on the Coordinators page instead of archiving. They keep their gardener account and lose the coordinator tools straight away. You must give a reason, which they will see.</dd>
          </div>
          <div>
            <dt>Give someone a few extra tools</dt>
            <dd>For example, a gardener who helps with the tool shed. On <?= $link('admin_roles.php', 'Roles') ?>, create a role such as &ldquo;Inventory Clerk&rdquo;, tick what it may do, then give it to that person. Only tick what they really need.</dd>
          </div>
        </dl>
      </section>

      <section class="panel help-section" id="find" aria-labelledby="find-title">
        <h2 id="find-title">3. Finding information</h2>
        <ul class="help-list">
          <li><?= $link('admin_overview.php', 'Garden â†’ Overview') ?>: every plot, shared item, request, exchange listing and sign-up in one place, including finished and declined ones.</li>
          <li><?= $link('admin_activity.php', 'Manage Accounts â†’ User Activity') ?>: who signed in when, and who has gone quiet. Click a name for that person&rsquo;s full profile and history.</li>
          <li><?= $link('admin_reports.php', 'Reports') ?>: ready-made reports for any time period. Download them as a spreadsheet or save them as a PDF.</li>
          <li><?= $link('admin_audit_log.php', 'Audit Log') ?>: a permanent record of everything important that happened, who did it, and why.</li>
        </ul>
      </section>

      <section class="panel help-section" id="words" aria-labelledby="words-title">
        <h2 id="words-title">4. Words HarvestHub uses</h2>
        <dl class="help-glossary">
          <div><dt>Gardener</dt><dd>A community member who looks after a plot, borrows shared tools and trades produce.</dd></div>
          <div><dt>Coordinator</dt><dd>A gardener (or staff member) who runs the garden day to day: answers plot and resource requests and manages stock.</dd></div>
          <div><dt>Administrator</dt><dd>Someone who runs HarvestHub itself: accounts, roles, reports. Administrators can do everything.</dd></div>
          <div><dt>Role</dt><dd>A set of things someone is allowed to do. Everyone gets one from their account type; you can add extra roles.</dd></div>
          <div><dt>Permission</dt><dd>One thing a role allows, such as &ldquo;Approve plot requests&rdquo;.</dd></div>
          <div><dt>Archive</dt><dd>Switch an account off without deleting it. It can be switched back on.</dd></div>
          <div><dt>Locked</dt><dd>An account that switched itself off after 3 wrong passwords, until someone enables it.</dd></div>
          <div><dt>Overdue return</dt><dd>A borrowed item a coordinator asked for back 7 or more days ago that has not come back.</dd></div>
        </dl>
      </section>

      <section class="panel help-section" id="safe" aria-labelledby="safe-title">
        <h2 id="safe-title">5. Good to know</h2>
        <ul class="help-list">
          <li>HarvestHub asks before anything that cannot easily be undone, and tells you what will happen.</li>
          <li>Nothing you do deletes history. Archived people, declined requests and removed roles all stay in the records.</li>
          <li>Every important action is written to the Audit Log, which nobody can change, including administrators.</li>
          <li>You cannot archive yourself, and HarvestHub always keeps at least one active administrator.</li>
          <li>If a page says something could not be loaded, refresh it. If it keeps happening, tell your technical contact.</li>
        </ul>
      </section>

    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/admin.js?v=25"></script>
</body>
</html>
