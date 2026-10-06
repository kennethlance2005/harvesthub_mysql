<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Records</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=14">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/coordinator_sidebar.php'; ?>
  <div class="main-content coordinator-main">
    <main class="coordinator-page" id="top">
      <header class="coordinator-page-head">
        <div><p class="eyebrow">Garden operations</p><h1>Records</h1><p class="text-muted">A chronological record of borrowed and returned resources.</p></div>
      </header>
      <section class="coordinator-section records-section" aria-labelledby="records-heading">
        <div class="section-heading"><div><p class="eyebrow">Transaction history</p><h2 id="records-heading">Inventory timeline</h2></div></div>
        <div id="resource-records-list" class="records-timeline" aria-live="polite">
          <p class="text-muted">Loading records...</p>
        </div>
      </section>
    </main>
  </div>
</div>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/staff.js?v=4"></script>
</body>
</html>