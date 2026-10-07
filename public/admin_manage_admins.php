<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('admin');
$navTitle = 'Manage Administrators';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Manage Admins</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=26">
</head>
<body class="account-page">
<div class="app-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="main-content">
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">
      
      <div style="display: flex; flex-direction: column; gap: 32px;">
          
          <!-- Register New Administrator Form -->
          <div class="panel">
            <h2 style="margin-bottom: 4px;">Register New Administrator</h2>
            
            <form id="create-admin-form" novalidate>
              <div class="admin-form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1.5fr 100px; gap: 16px; align-items: start;">
                
                <!-- Row 1: First Name | Last Name | Location | Age -->
                <div class="field" style="margin: 0;">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" id="new-admin-first-name" placeholder="e.g., Juan" required>
                </div>
                <div class="field" style="margin: 0;">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" id="new-admin-last-name" placeholder="e.g., Dela Cruz" required>
                </div>
                <div class="field" style="margin: 0;">
                    <label>Location <span class="required">*</span></label>
                    <select id="new-admin-location" required>
                        <option value="">Select city...</option>
                        <option value="Caloocan">Caloocan</option>
                        <option value="Las Piñas">Las Piñas</option>
                        <option value="Makati">Makati</option>
                        <option value="Malabon">Malabon</option>
                        <option value="Mandaluyong">Mandaluyong</option>
                        <option value="Manila">Manila</option>
                        <option value="Marikina">Marikina</option>
                        <option value="Muntinlupa">Muntinlupa</option>
                        <option value="Navotas">Navotas</option>
                        <option value="Parañaque">Parañaque</option>
                        <option value="Pasay">Pasay</option>
                        <option value="Pasig">Pasig</option>
                        <option value="Pateros">Pateros</option>
                        <option value="Quezon City">Quezon City</option>
                        <option value="San Juan">San Juan</option>
                        <option value="Taguig">Taguig</option>
                        <option value="Valenzuela">Valenzuela</option>
                    </select>
                </div>
                <div class="field" style="margin: 0;">
                    <label>Age <span class="required">*</span></label>
                    <input type="number" id="new-admin-age" min="18" max="120" placeholder="18" required>
                </div>
                
                <!-- Row 2: Email Address | Temporary Password -->
                <div class="field" style="margin: 0;">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" id="new-admin-email" placeholder="admin@harvesthub.test" required>
                </div>
                <div class="field" style="margin: 0; grid-column: 2 / 5;">
                    <label>Temporary Password <span class="required">*</span></label>
                  <input type="password" id="new-admin-pass" placeholder="Min. 8 characters" autocomplete="new-password" minlength="8" required>
                  <ul id="admin-password-reqs" class="password-reqs password-reqs--columns" aria-live="polite">
                    <li data-requirement="length" class="invalid">At least 8 characters</li>
                    <li data-requirement="upper" class="invalid">At least 1 uppercase letter</li>
                    <li data-requirement="lower" class="invalid">At least 1 lowercase letter</li>
                    <li data-requirement="number" class="invalid">At least 1 number</li>
                    <li data-requirement="special" class="invalid">At least 1 special character</li>
                  </ul>
                </div>

                <!-- Row 4: Submit button spanning across all columns (full width of the form) -->
                <div style="grid-column: 1 / -1; margin-top: 8px;">
                    <button type="submit" class="btn btn-accent" style="width: 100%; padding: 12px; font-weight: 600; text-align: center;">Register Administrator</button>
                </div>

              </div>
            </form>
          </div>

          <!-- Admin Accounts Table -->
          <div class="panel">
            <div class="admin-table-heading" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <p class="panel-title" style="margin: 0;">Administrators</p>
              <input type="search" id="search-admins" data-table-search="admins-table" placeholder="Search administrators...">
            </div>
            <div class="table-wrap">
              <table class="data-table admin-responsive-table">
                <thead><tr><th>Name</th><th>Email</th><th>Location</th><th>Actions</th></tr></thead>
                <tbody id="admins-table"></tbody>
              </table>
            </div>
          </div>

      </div>

    </main>
  </div>
</div>
<?php include __DIR__ . '/admin_modal_archive.php'; ?>
<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container"></div>
<script src="assets/admin.js?v=5"></script>
</body>
</html>
