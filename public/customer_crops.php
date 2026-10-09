<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'My Crops';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — My Crops</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=24">
</head>
<body class="account-page">
<div class="app-layout">
	<?php include __DIR__ . '/customer_sidebar.php'; ?>
	<div class="main-content">
		<main class="wrap customer-crops-page" id="top">
			<header class="page-head maintenance-page-head">
				<div>
					<p class="eyebrow">Garden journal</p>
					<h1>My Crops</h1>
					<p class="text-muted">Track crop progress and keep a record of care and harvests.</p>
				</div>
			</header>

			<div class="crops-overview-layout">
				<section class="panel crops-garden-log" aria-labelledby="garden-log-title">
					<div class="crops-section-heading">
						<div>
							<p class="eyebrow">Plant lifecycle</p>
							<h2 class="panel-title" id="garden-log-title">My Garden Log</h2>
						</div>
						<div class="crops-log-controls">
							<label class="sr-only" for="plots-category-filter">Filter crops by status</label>
							<select id="plots-category-filter">
								<option value="All">All categories</option>
								<option value="Planted">Planted</option>
								<option value="Harvested">Harvested</option>
								<option value="Failed">Failed</option>
							</select>
							<label class="sr-only" for="search-plots">Search crops</label>
							<input type="search" id="search-plots" placeholder="Search crops...">
						</div>
					</div>
					<div id="plots-list" class="scroll-y crops-list" aria-live="polite">
						<p class="empty-state">Loading your garden log...</p>
					</div>
				</section>

				<section class="panel crops-add-panel" aria-labelledby="add-crop-title">
					<p class="eyebrow">Get growing</p>
					<h2 class="panel-title" id="add-crop-title">Log a Crop</h2>
					<p class="text-muted crops-form-intro">Add a planting to your garden log.</p>
					<form id="add-plot-form" class="maintenance-form" novalidate>
						<div class="field">
							<label for="plot-crop-name">Crop name <span class="required">*</span></label>
							<input type="text" id="plot-crop-name" placeholder="e.g., Cherry Tomatoes" pattern="[A-Za-z\s]+" title="Letters and spaces only." required>
						</div>
						<div class="field">
							<label for="plot-planted-date">Planted date <span class="required">*</span></label>
							<input type="date" id="plot-planted-date" required>
						</div>
						<div class="field">
							<label for="plot-notes">Notes <span class="text-muted">(optional)</span></label>
							<textarea id="plot-notes" rows="3" placeholder="e.g., Used organic compost"></textarea>
						</div>
						<button type="submit" class="btn btn-accent">Log crop</button>
					</form>
				</section>
			</div>

			<div class="maintenance-layout crops-maintenance-layout">
				<section class="panel maintenance-form-panel" aria-labelledby="maintenance-form-title">
					<p class="eyebrow">Care & harvest</p>
					<h2 class="panel-title" id="maintenance-form-title">Log Maintenance</h2>
					<form id="croplog-form" class="maintenance-form" novalidate>
						<div class="field">
							<label for="crop-name">Select crop from your garden log <span class="required">*</span></label>
							<input type="search" id="crop-name" list="maintenance-crop-options" maxlength="60" placeholder="Search your crops..." autocomplete="off" required>
							<datalist id="maintenance-crop-options"></datalist>
							<small id="maintenance-crop-hint" class="text-muted">Choose one of your crops to log its care.</small>
						</div>
						<div class="field">
							<label for="crop-notes">Maintenance notes</label>
							<textarea id="crop-notes" maxlength="300" rows="4" placeholder="Watered, pruned, treated for pests..."></textarea>
						</div>
						<div class="field">
							<label for="crop-yield">Harvest yield <span class="text-muted">(optional)</span></label>
							<input type="text" id="crop-yield" maxlength="60" placeholder="e.g., 2 kg">
						</div>
						<p class="maintenance-form-hint text-muted">Only crops that are still planted or growing can receive maintenance entries.</p>
						<p id="croplog-alert" class="form-error" role="alert" hidden></p>
						<button type="submit" class="btn btn-accent">Save maintenance entry</button>
					</form>
				</section>

				<section class="panel maintenance-history-panel" aria-labelledby="maintenance-history-title">
					<div class="panel-header">
						<div>
						<p class="eyebrow">Your records</p>
						<h2 class="panel-title" id="maintenance-history-title">Maintenance History</h2>
						</div>
					</div>
					<div id="croplog-list" class="maintenance-log-list" aria-live="polite">
						<p class="text-muted">Loading maintenance history...</p>
					</div>
				</section>
			</div>
		</main>
	</div>
</div>
<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/app.js"></script>
<script src="assets/customer.js?v=8"></script>
<script src="assets/plots.js?v=7"></script>
</body>
</html>
