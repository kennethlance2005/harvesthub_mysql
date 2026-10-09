<?php
$footerSupport = ($user['role'] ?? '') === 'customer'
    ? 'For account help, contact your garden coordinator.'
    : 'For account help, contact your system administrator.';
?>
<footer class="site-footer account-footer">
  <div class="wrap footer-row">
    <div class="account-footer-brand">
      <a class="wordmark-light" href="index.php">
        <span aria-hidden="true">🌱</span> HarvestHub
      </a>
      <p class="footer-tagline">Growing stronger gardens together.</p>
    </div>
    <div class="footer-meta">
      <p>&copy; <?= date('Y') ?> HarvestHub</p>
      <p><?= htmlspecialchars($footerSupport, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>
</footer>
<script src="assets/required-form-buttons.js?v=1"></script>
<script src="assets/dialog.js?v=2"></script>