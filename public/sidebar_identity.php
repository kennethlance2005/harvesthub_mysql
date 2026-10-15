<?php
$sidebarRoleLabels = currentUserRoleLabels();
?>
<div class="sidebar-user" id="<?= htmlspecialchars($sidebarUserId, ENT_QUOTES, 'UTF-8') ?>">
  <span class="sidebar-user-name"><?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
  <?php if ($sidebarRoleLabels): ?>
  <span class="sidebar-user-roles"><?= htmlspecialchars(implode(' · ', $sidebarRoleLabels), ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
</div>
