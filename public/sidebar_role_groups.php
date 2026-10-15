<?php
$sidebarRoleGroups = [
    'gardener' => ['label' => 'Gardener Tools', 'pages' => [
        'customer_dashboard.php' => 'Dashboard',
        'customer_crops.php' => 'My Crops',
        'customer_plots.php' => 'My Plots',
        'customer_inventory.php' => 'Resource Inventory',
        'customer_exchange.php' => 'Exchange Board',
    ]],
    'coordinator' => ['label' => 'Coordinator Tools', 'pages' => [
        'staff_dashboard.php' => 'Dashboard',
        'staff_plots.php' => 'Plots',
        'staff_inventory.php' => 'Inventory',
        'staff_records.php' => 'Records',
    ]],
    'admin' => ['label' => 'Admin Tools', 'pages' => [
        'admin_dashboard.php' => 'Dashboard',
        'admin_reports.php' => 'Reports',
        'admin_overview.php' => 'Garden Overview',
        'admin_manage_gardeners.php' => 'Gardeners',
        'admin_manage_coordinators.php' => 'Coordinators',
        'admin_manage_admins.php' => 'Administrators',
        'admin_activity.php' => 'User Activity',
        'admin_archived_accounts.php' => 'Archived Accounts',
        'admin_roles.php' => 'Roles',
        'admin_audit_log.php' => 'Audit Log',
        'admin_help.php' => 'Help',
    ]],
];
$currentSidebarPage = basename($_SERVER['PHP_SELF']);
$visibleSidebarGroups = [];
foreach ($sidebarRoleGroups as $groupId => $group) {
    $links = [];
    foreach ($group['pages'] as $page => $label) {
        $allowed = str_starts_with($page, 'customer_')
            ? hasRole('customer')
            : canOpen($page);
        if ($allowed) $links[$page] = $label;
    }
    if ($links) $visibleSidebarGroups[$groupId] = ['label' => $group['label'], 'links' => $links];
}
?>
<nav class="sidebar-nav sidebar-role-nav" id="<?= htmlspecialchars($sidebarNavId, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($sidebarNavLabel, ENT_QUOTES, 'UTF-8') ?>" style="flex-wrap: nowrap; gap: 10px;">
  <?php foreach ($visibleSidebarGroups as $groupId => $group): ?>
  <?php $groupActive = isset($group['links'][$currentSidebarPage]); ?>
  <div class="sidebar-dropdown">
    <button type="button" class="sidebar-link dropdown-toggle<?= $groupActive ? ' active' : '' ?>" aria-expanded="false" aria-controls="<?= htmlspecialchars($sidebarNavId . ucfirst($groupId), ENT_QUOTES, 'UTF-8') ?>" style="text-align: left; background: none; border: none; font-family: inherit; font-size: inherit; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 5px;">
      <?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') ?>
      <span class="chevron" style="transform: rotate(0); transition: transform 0.2s; font-size: 0.75rem;">▼</span>
    </button>
    <div class="dropdown-menu" id="<?= htmlspecialchars($sidebarNavId . ucfirst($groupId), ENT_QUOTES, 'UTF-8') ?>" style="display: none; flex-direction: column; padding-left: 12px; margin-top: 4px; gap: 4px;">
      <?php foreach ($group['links'] as $page => $label): ?>
      <a href="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>" class="sidebar-link<?= $currentSidebarPage === $page ? ' active' : '' ?>" style="padding: 8px 16px; font-size: 0.9em;"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</nav>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const dropdowns = Array.from(document.querySelectorAll('#<?= htmlspecialchars($sidebarNavId, ENT_QUOTES, 'UTF-8') ?> .sidebar-dropdown'));
  const setOpen = (dropdown, isOpen) => {
    const button = dropdown.querySelector('.dropdown-toggle');
    const menu = document.getElementById(button.getAttribute('aria-controls'));
    const chevron = button.querySelector('.chevron');
    menu.style.display = isOpen ? 'flex' : 'none';
    button.setAttribute('aria-expanded', String(isOpen));
    if (chevron) chevron.style.transform = isOpen ? 'rotate(180deg)' : 'rotate(0deg)';
  };

  dropdowns.forEach(dropdown => {
    dropdown.querySelector('.dropdown-toggle').addEventListener('click', () => {
      const wasOpen = dropdown.querySelector('.dropdown-toggle').getAttribute('aria-expanded') === 'true';
      dropdowns.forEach(item => setOpen(item, false));
      if (!wasOpen) setOpen(dropdown, true);
    });
  });

  document.addEventListener('click', event => {
    if (!event.target.closest('#<?= htmlspecialchars($sidebarNavId, ENT_QUOTES, 'UTF-8') ?> .sidebar-dropdown')) {
      dropdowns.forEach(dropdown => setOpen(dropdown, false));
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') dropdowns.forEach(dropdown => setOpen(dropdown, false));
  });
});
</script>
