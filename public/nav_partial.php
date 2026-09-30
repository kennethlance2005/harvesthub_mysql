<?php
/** Expects $navTitle and $user (from requireRole) to be set before include. */
?>
<header class="site-header app-header">
  <div class="wrap app-header-row">
    <a class="wordmark" href="#top">HarvestHub</a>
    <div class="app-header-right">
      <span class="app-header-title"><?= htmlspecialchars($navTitle) ?></span>
      <span class="app-header-greeting">Hi, <?= htmlspecialchars($user['name'] ?? '') ?></span>
      <a href="logout.php" class="btn btn-on-dark btn-sm">Log Out</a>
    </div>
  </div>
</header>
