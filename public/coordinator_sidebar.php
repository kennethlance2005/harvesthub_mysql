<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar coordinator-sidebar">
  <div class="sidebar-inner">
    <div class="sidebar-brand"><span class="sprout">🌱</span> HarvestHub</div>
    <button class="sidebar-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="coordinatorSidebarUser coordinatorSidebarNav coordinatorSidebarLogout">
      <span></span><span></span><span></span>
    </button>
    <div class="sidebar-user" id="coordinatorSidebarUser"><?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="sidebar-nav" id="coordinatorSidebarNav" aria-label="Coordinator navigation">
      <?php if (canOpen('staff_dashboard.php')): ?><a href="staff_dashboard.php" class="sidebar-link <?= $currentPage === 'staff_dashboard.php' ? 'active' : '' ?>">Dashboard</a><?php endif; ?>
      <?php if (canOpen('staff_plots.php')): ?><a href="staff_plots.php" class="sidebar-link <?= $currentPage === 'staff_plots.php' ? 'active' : '' ?>">Plots</a><?php endif; ?>
      <?php if (canOpen('staff_inventory.php')): ?><a href="staff_inventory.php" class="sidebar-link <?= $currentPage === 'staff_inventory.php' ? 'active' : '' ?>">Inventory</a><?php endif; ?>
      <?php if (canOpen('staff_records.php')): ?><a href="staff_records.php" class="sidebar-link <?= $currentPage === 'staff_records.php' ? 'active' : '' ?>">Records</a><?php endif; ?>
      <?php if (hasRole('customer')): ?>
      <a href="customer_dashboard.php" class="sidebar-link <?= $currentPage === 'customer_dashboard.php' ? 'active' : '' ?>">Gardener Dashboard</a>
      <a href="customer_crops.php" class="sidebar-link <?= $currentPage === 'customer_crops.php' ? 'active' : '' ?>">My Crops</a>
      <a href="customer_plots.php" class="sidebar-link <?= $currentPage === 'customer_plots.php' ? 'active' : '' ?>">My Plots</a>
      <a href="customer_inventory.php" class="sidebar-link <?= $currentPage === 'customer_inventory.php' ? 'active' : '' ?>">Resource Inventory</a>
      <a href="customer_exchange.php" class="sidebar-link <?= $currentPage === 'customer_exchange.php' ? 'active' : '' ?>">Exchange Board</a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer" id="coordinatorSidebarLogout"><a href="logout.php" class="sidebar-link coordinator-logout">Log Out</a></div>
  </div>
</aside>
<?= permissionsScript() ?>
<script>
(function () {
  var nav = document.querySelector('.sidebar');
  if (!nav) return;
  var toggle = nav.querySelector('.sidebar-toggle');
  toggle.addEventListener('click', function () {
    var isOpen = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(isOpen));
    toggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
  });
  function onScroll() {
    nav.classList.toggle('is-scrolled', window.scrollY > 8);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();
</script>