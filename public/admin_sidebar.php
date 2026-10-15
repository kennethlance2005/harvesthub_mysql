<?php
// Get the current filename to highlight the active tab
$currentPage = basename($_SERVER['PHP_SELF']);

// Links grouped into dropdowns. Each link only shows when the person's roles
// allow them to open that page (see PAGE_PERMISSIONS in auth.php).
$gardenPages = ['admin_overview.php' => 'Overview', 'staff_plots.php' => 'Plots', 'staff_inventory.php' => 'Resource Inventory', 'staff_records.php' => 'Records'];
$managePages = ['admin_manage_gardeners.php' => 'Gardeners', 'admin_manage_coordinators.php' => 'Coordinators', 'admin_manage_admins.php' => 'Administrators', 'admin_activity.php' => 'User Activity', 'admin_archived_accounts.php' => 'Archived Accounts'];
$visible = static fn (array $pages) => array_filter($pages, 'canOpen', ARRAY_FILTER_USE_KEY);
$gardenLinks = $visible($gardenPages);
$manageLinks = $visible($managePages);

$sidebarLink = static function (string $page, string $label) use ($currentPage): string {
    if (!canOpen($page)) return '';
    $active = $currentPage === $page ? ' active' : '';
    return "<a href=\"$page\" class=\"sidebar-link$active\">$label</a>";
};
$sidebarDropdown = static function (string $id, string $label, array $links) use ($currentPage): string {
    if (!$links) return '';
    $current = isset($links[$currentPage]); // highlighted, but closed until clicked so it never covers the page
    $items = '';
    foreach ($links as $page => $text) {
        $active = $currentPage === $page ? ' active' : '';
        $items .= "<a href=\"$page\" class=\"sidebar-link$active\" style=\"padding: 8px 16px; font-size: 0.9em;\">$text</a>";
    }
    return '<div class="sidebar-dropdown">'
        . "<button type=\"button\" class=\"sidebar-link dropdown-toggle" . ($current ? ' active' : '') . "\" aria-expanded=\"" . 'false' . "\" aria-controls=\"{$id}Menu\" style=\"width: 100%; text-align: left; background: none; border: none; font-family: inherit; font-size: inherit; cursor: pointer; display: flex; justify-content: space-between; align-items: center;\">"
        . "$label <span class=\"chevron\" style=\"transform: " . 'rotate(0)' . "; transition: transform 0.2s; font-size: 0.75rem;\">▼</span></button>"
        . "<div class=\"dropdown-menu\" id=\"{$id}Menu\" style=\"display: " . 'none' . "; flex-direction: column; padding-left: 12px; margin-top: 4px; gap: 4px;\">$items</div>"
        . '</div>';
};
?>
<aside class="sidebar">
  <div class="sidebar-inner">
  <div class="sidebar-brand">
    <span class="sprout">🌱</span> HarvestHub
  </div>

  <button class="sidebar-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="adminSidebarNav adminSidebarLogout">
    <span></span><span></span><span></span>
  </button>

  <nav class="sidebar-nav" id="adminSidebarNav" aria-label="Administrator navigation">
    <?= $sidebarLink('admin_dashboard.php', 'Dashboard') ?>
    <?= $sidebarLink('admin_reports.php', 'Reports') ?>
    <?= $sidebarDropdown('garden', 'Garden', $gardenLinks) ?>
    <?= $sidebarDropdown('manageAccounts', 'Manage Accounts', $manageLinks) ?>
    <?= $sidebarLink('admin_roles.php', 'Roles') ?>
    <?= $sidebarLink('admin_audit_log.php', 'Audit Log') ?>
    <?php if (!hasRole('admin') && hasRole('customer')): ?>
    <a href="customer_dashboard.php" class="sidebar-link">My Gardener Dashboard</a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer" id="adminSidebarLogout">
    <?= $sidebarLink('admin_help.php', 'Help') ?>
    <a href="logout.php" class="sidebar-link" style="color: #fca5a5;">Log Out</a>
  </div>
  </div>
</aside>
<?= permissionsScript() ?>

<script>
// Open and close the sidebar dropdowns (Garden, Manage Accounts)
document.addEventListener('DOMContentLoaded', () => {
  const dropdowns = Array.from(document.querySelectorAll('.sidebar .sidebar-dropdown'));

  const setDropdownOpen = (dropdown, isOpen) => {
    const btn = dropdown.querySelector('.dropdown-toggle');
    const menu = document.getElementById(btn.getAttribute('aria-controls'));
    const chevron = btn.querySelector('.chevron');
    if (!menu) return;

    menu.style.display = isOpen ? 'flex' : 'none';
    btn.setAttribute('aria-expanded', String(isOpen));
    if (chevron) chevron.style.transform = isOpen ? 'rotate(180deg)' : 'rotate(0deg)';
  };

  dropdowns.forEach(dropdown => {
    const btn = dropdown.querySelector('.dropdown-toggle');
    const menu = document.getElementById(btn.getAttribute('aria-controls'));
    if (!menu) return;
    btn.addEventListener('click', () => {
      const isExpanded = menu.style.display === 'flex';
      dropdowns.forEach(otherDropdown => setDropdownOpen(otherDropdown, false));
      if (!isExpanded) setDropdownOpen(dropdown, true);
    });
  });

  document.addEventListener('click', event => {
    if (!event.target.closest('.sidebar-dropdown')) {
      dropdowns.forEach(dropdown => setDropdownOpen(dropdown, false));
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      dropdowns.forEach(dropdown => setDropdownOpen(dropdown, false));
    }
  });
});

// Fade the top nav to a translucent look once the page is scrolled;
// keep it solid while at the very top.
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
