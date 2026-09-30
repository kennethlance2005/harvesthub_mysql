<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar coordinator-sidebar">
  <div class="sidebar-inner">
    <div class="sidebar-brand"><span class="sprout">🌱</span> HarvestHub</div>
    <div class="sidebar-user"><?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="sidebar-nav" aria-label="Coordinator navigation">
      <a href="staff_dashboard.php" class="sidebar-link <?= $currentPage === 'staff_dashboard.php' ? 'active' : '' ?>">Dashboard</a>
      <a href="staff_plots.php" class="sidebar-link <?= $currentPage === 'staff_plots.php' ? 'active' : '' ?>">Plots</a>
      <a href="staff_inventory.php" class="sidebar-link <?= $currentPage === 'staff_inventory.php' ? 'active' : '' ?>">Inventory</a>
      <a href="staff_records.php" class="sidebar-link <?= $currentPage === 'staff_records.php' ? 'active' : '' ?>">Records</a>
    </nav>
    <div class="sidebar-footer"><a href="logout.php" class="sidebar-link coordinator-logout">Log Out</a></div>
  </div>
</aside>
<script>
(function () {
  var nav = document.querySelector('.sidebar');
  if (!nav) return;
  function onScroll() {
    nav.classList.toggle('is-scrolled', window.scrollY > 8);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();
</script>