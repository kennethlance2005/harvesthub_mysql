<?php
// Get the current filename (e.g., 'customer_dashboard.php')
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div class="sidebar-inner">
    <div class="sidebar-brand">
      <span class="sprout">🌱</span> HarvestHub
    </div>

    <button class="sidebar-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="customerSidebarUser customerSidebarNav customerSidebarLogout">
      <span></span><span></span><span></span>
    </button>

    <div class="sidebar-user" id="customerSidebarUser">
      <?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
    </div>

    <nav class="sidebar-nav" id="customerSidebarNav" aria-label="Customer navigation">
      <a href="customer_dashboard.php" class="sidebar-link <?= $currentPage === 'customer_dashboard.php' ? 'active' : '' ?>">
        Dashboard
      </a>
      <a href="customer_crops.php" class="sidebar-link <?= $currentPage === 'customer_crops.php' ? 'active' : '' ?>">
        My Crops
      </a>
      <a href="customer_plots.php" class="sidebar-link <?= $currentPage === 'customer_plots.php' ? 'active' : '' ?>">
        My Plots
      </a>
      <a href="customer_inventory.php" class="sidebar-link <?= $currentPage === 'customer_inventory.php' ? 'active' : '' ?>">
        Resource Inventory
      </a>
      <a href="customer_exchange.php" class="sidebar-link <?= $currentPage === 'customer_exchange.php' ? 'active' : '' ?>">
        Exchange Board
      </a>
      <?php if (hasRole('staff')): ?>
      <a href="staff_dashboard.php" class="sidebar-link <?= str_starts_with($currentPage, 'staff_') ? 'active' : '' ?>">
        Coordinator Workspace
      </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-footer" id="customerSidebarLogout">
      <a href="logout.php" class="sidebar-link" style="color: #fca5a5;">Log Out</a>
    </div>
  </div>
</aside>
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