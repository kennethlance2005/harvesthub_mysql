<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'Maintenance Log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Maintenance Log</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=16">
</head>
<body>
<div class="app-layout">
	<?php include __DIR__ . '/customer_sidebar.php'; ?>
	<div class="main-content">
		<main class="wrap maintenance-page" id="top">
			<header class="page-head maintenance-page-head">
				<div>
					<p class="eyebrow">Garden journal</p>
					<h1>Maintenance Log</h1>
					<p class="text-muted">Record care, progress, and harvests for your garden.</p>
				</div>
			</header>

			<div class="maintenance-layout">
				<section class="panel maintenance-form-panel" aria-labelledby="maintenance-form-title">
					<p class="panel-title" id="maintenance-form-title">Log an entry</p>
					<form id="croplog-form" class="maintenance-form" novalidate>
						<div class="field">
							<label for="crop-name">Crop name</label>
							<input type="text" id="crop-name" maxlength="60" placeholder="e.g., Tomatoes" required>
						</div>
						<div class="field">
							<label for="crop-notes">Maintenance notes</label>
							<textarea id="crop-notes" maxlength="300" rows="4" placeholder="Watered, pruned, treated for pests..."></textarea>
						</div>
						<div class="field">
							<label for="crop-yield">Harvest yield <span class="text-muted">(optional)</span></label>
							<input type="text" id="crop-yield" maxlength="60" placeholder="e.g., 2 kg">
						</div>
						<p class="maintenance-form-hint text-muted">An assigned garden plot is required to create an entry.</p>
						<p id="croplog-alert" class="form-error" role="alert" hidden></p>
						<button type="submit" class="btn btn-accent">Save entry</button>
					</form>
				</section>

				<section class="panel maintenance-history-panel" aria-labelledby="maintenance-history-title">
					<div class="panel-header">
						<div>
							<p class="eyebrow">Your records</p>
							<h2 class="panel-title" id="maintenance-history-title">Recent entries</h2>
						</div>
					</div>
					<div id="croplog-list" class="maintenance-log-list" aria-live="polite">
						<p class="text-muted">Loading maintenance entries...</p>
					</div>
				</section>
			</div>
		</main>
	</div>
</div>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/app.js"></script>
<script src="assets/customer.js?v=5"></script>
</body>
</html>
