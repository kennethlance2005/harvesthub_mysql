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
<link rel="stylesheet" href="assets/style.css?v=29">
</head>
<body class="account-page">
<div class="app-layout">
	<?php include __DIR__ . '/customer_sidebar.php'; ?>
	<div class="main-content">
		<main class="wrap gardener-page crops-page" id="top">
			<header class="page-head">
				<div>
					<p class="eyebrow">Garden journal</p>
					<h1>My Crops</h1>
					<p class="text-muted">Track crop progress and keep a record of care and harvests.</p>
				</div>
			</header>

			<div class="crops-layout">
				<!-- WIDE LEFT COLUMN: what you have growing and what you've done -->
				<div class="crops-main">
					<section class="panel crops-card" aria-labelledby="garden-log-title">
						<h2 class="panel-title" id="garden-log-title">My garden log</h2>
						<p class="crops-card-sub">Your plantings and how they're doing. Change a crop's status as it grows.</p>
						<div class="crops-log-controls">
							<label class="sr-only" for="plots-category-filter">Filter crops by status</label>
							<select id="plots-category-filter">
								<option value="All">All statuses</option>
								<option value="Planted">Planted</option>
								<option value="Harvested">Harvested</option>
								<option value="Failed">Failed</option>
							</select>
							<label class="sr-only" for="search-plots">Search crops</label>
							<input type="search" id="search-plots" placeholder="Search crops...">
						</div>
						<div id="plots-list" class="crops-list" aria-live="polite">
							<p class="crops-empty">Loading your garden log...</p>
						</div>
					</section>

					<section class="panel crops-card" aria-labelledby="maintenance-history-title">
						<h2 class="panel-title" id="maintenance-history-title">Maintenance history</h2>
						<p class="crops-card-sub">Every care and harvest entry you've recorded.</p>
						<div id="croplog-list" class="maintenance-log-list" aria-live="polite">
							<p class="crops-empty">Loading maintenance history...</p>
						</div>
					</section>
				</div>

				<!-- NARROW RIGHT COLUMN: forms -->
				<div class="crops-side">
					<section class="panel crops-card" aria-labelledby="add-crop-title">
						<h2 class="panel-title" id="add-crop-title">Log a crop</h2>
						<p class="crops-card-sub">Add a new planting to your garden log.</p>
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
								<label for="plot-notes">Notes <span class="field-optional">(optional)</span></label>
								<textarea id="plot-notes" rows="3" placeholder="e.g., Used organic compost"></textarea>
							</div>
							<button type="submit" class="btn btn-accent btn-block">Log crop</button>
						</form>
					</section>

					<section class="panel crops-card" aria-labelledby="maintenance-form-title">
						<h2 class="panel-title" id="maintenance-form-title">Log maintenance</h2>
						<p class="crops-card-sub">Record care or a harvest for a crop that is still growing.</p>
						<form id="croplog-form" class="maintenance-form" novalidate>
							<div class="field">
								<label for="crop-name">Crop <span class="required">*</span></label>
								<input type="search" id="crop-name" list="maintenance-crop-options" maxlength="60" placeholder="Search your crops..." autocomplete="off" required>
								<datalist id="maintenance-crop-options"></datalist>
								<small id="maintenance-crop-hint" class="maintenance-form-hint">Choose one of your crops to log its care.</small>
							</div>
							<div class="field">
								<label for="crop-notes">Maintenance notes</label>
								<textarea id="crop-notes" maxlength="300" rows="3" placeholder="Watered, pruned, treated for pests..."></textarea>
							</div>
							<div class="field">
								<label for="crop-yield">Harvest yield <span class="field-optional">(optional)</span></label>
								<input type="text" id="crop-yield" maxlength="60" placeholder="e.g., 2 kg">
							</div>
							<p id="croplog-alert" class="form-error" role="alert" hidden></p>
							<button type="submit" class="btn btn-accent btn-block">Save maintenance entry</button>
						</form>
					</section>
				</div>
			</div>
		</main>
	</div>
</div>
<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<script src="assets/app.js"></script>
<script src="assets/customer.js?v=10"></script>
<script src="assets/plots.js?v=8"></script>
</body>
</html>
