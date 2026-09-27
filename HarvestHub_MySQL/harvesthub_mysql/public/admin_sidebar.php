<?php
// Get the current filename to highlight the active tab
$currentPage = basename($_SERVER['PHP_SELF']);

// Define which pages belong inside the Manage Accounts dropdown
$managePages = ['admin_manage_gardeners.php', 'admin_manage_coordinators.php', 'admin_manage_admins.php'];
$isManageActive = in_array($currentPage, $managePages);
?>
<aside class="sidebar">
  <div class="sidebar-inner">
  <div class="sidebar-brand">
    <span class="sprout">🌱</span> HarvestHub
  </div>
  
  <nav class="sidebar-nav">
    <a href="admin_dashboard.php" class="sidebar-link <?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>">
      Dashboard
    </a>
    
    <!-- Collapsible Dropdown -->
    <div class="sidebar-dropdown">
      <button class="sidebar-link dropdown-toggle <?= $isManageActive ? 'active' : '' ?>" id="manageAccountsBtn" style="width: 100%; text-align: left; background: none; border: none; font-family: inherit; font-size: inherit; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
        Manage Accounts
        <span class="chevron" style="transform: <?= $isManageActive ? 'rotate(180deg)' : 'rotate(0)' ?>; transition: transform 0.2s; font-size: 0.75rem;">▼</span>
      </button>
      
      <div class="dropdown-menu" id="manageAccountsMenu" style="display: <?= $isManageActive ? 'flex' : 'none' ?>; flex-direction: column; padding-left: 12px; margin-top: 4px; gap: 4px;">
        <a href="admin_manage_gardeners.php" class="sidebar-link <?= $currentPage === 'admin_manage_gardeners.php' ? 'active' : '' ?>" style="padding: 8px 16px; font-size: 0.9em;">
          Gardeners
        </a>
        <a href="admin_manage_coordinators.php" class="sidebar-link <?= $currentPage === 'admin_manage_coordinators.php' ? 'active' : '' ?>" style="padding: 8px 16px; font-size: 0.9em;">
          Coordinators
        </a>
        <a href="admin_manage_admins.php" class="sidebar-link <?= $currentPage === 'admin_manage_admins.php' ? 'active' : '' ?>" style="padding: 8px 16px; font-size: 0.9em;">
          Administrators
        </a>
      </div>
    </div>

    <!-- ALTERNATIVE FLAT LINKS: Uncomment these 3 lines and delete the dropdown block above -->
    <!--
    <a href="admin_manage_gardeners.php" class="sidebar-link <?= $currentPage === 'admin_manage_gardeners.php' ? 'active' : '' ?>">Manage Gardeners</a>
    <a href="admin_manage_coordinators.php" class="sidebar-link <?= $currentPage === 'admin_manage_coordinators.php' ? 'active' : '' ?>">Manage Coordinators</a>
    <a href="admin_manage_admins.php" class="sidebar-link <?= $currentPage === 'admin_manage_admins.php' ? 'active' : '' ?>">Manage Administrators</a>
    -->

    <a href="admin_archived_accounts.php" class="sidebar-link <?= $currentPage === 'admin_archived_accounts.php' ? 'active' : '' ?>">
      Archived Accounts
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="sidebar-link" style="color: #fca5a5;">Log Out</a>
  </div>
  </div>
</aside>

<script>
// Simple toggle logic for the sidebar dropdown
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('manageAccountsBtn');
  const menu = document.getElementById('manageAccountsMenu');
  const chevron = btn.querySelector('.chevron');
  
  if (btn && menu) {
    btn.addEventListener('click', () => {
      const isExpanded = menu.style.display === 'flex';
      menu.style.display = isExpanded ? 'none' : 'flex';
      chevron.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(180deg)';
    });
  }
});

// Fade the top nav to a translucent look once the page is scrolled;
// keep it solid while at the very top.
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