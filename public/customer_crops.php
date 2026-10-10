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
<link rel="stylesheet" href="assets/style.css?v=39">
</head>
<body class="account-page">
<div class="app-layout">
	<?php include __DIR__ . '/customer_sidebar.php'; ?>
	<div class="main-content">
		<main class="wrap gardener-page crops-page" id="top">
			<header class="page-head crops-page-head">
				<div>
					<p class="eyebrow">Garden journal</p>
					<h1>My Crops</h1>
					<p class="text-muted">Track crop progress and keep a record of care and harvests.</p>
				</div>
				<button type="button" class="btn btn-accent" id="open-add-crop">+ Plant a Crop</button>
			</header>

			<section class="panel crops-card crops-journal" aria-labelledby="garden-log-title">
				<div class="crops-journal-heading">
					<div>
						<h2 class="panel-title" id="garden-log-title">Your garden journal</h2>
						<p class="crops-card-sub">Open a crop to record its progress and view its care history.</p>
					</div>
					<div class="crops-log-controls">
						<label class="sr-only" for="plots-category-filter">Filter crops by status</label>
						<select id="plots-category-filter">
							<option value="All">All statuses</option>
							<option value="Planted">Planted</option>
							<option value="Growing">Growing</option>
							<option value="Harvested">Harvested</option>
							<option value="Failed">Failed</option>
						</select>
						<label class="sr-only" for="search-plots">Search crops</label>
						<input type="search" id="search-plots" placeholder="Search crops...">
					</div>
				</div>
				<div id="plots-list" class="crops-list" aria-live="polite">
					<p class="crops-empty">Loading your garden journal...</p>
				</div>
			</section>
		</main>
	</div>
</div>
<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<dialog class="hh-dialog" id="add-crop-dialog" aria-labelledby="add-crop-title">
	<form class="hh-dialog-box" id="add-plot-form">
		<h3 id="add-crop-title">Plant a crop</h3>
		<p class="hh-dialog-message">Add a new planting to your garden journal.</p>
		<div class="field hh-dialog-field">
			<label for="plot-crop-name">Crop name <span class="required">*</span></label>
			<input type="text" id="plot-crop-name" list="native-crop-options" maxlength="60" placeholder="Search the crop catalog..." autocomplete="off" required>
			<datalist id="native-crop-options"></datalist>
			<small class="text-muted">Choose from the crop catalog. Crops not listed can be requested for review.</small>
		</div>
		<div class="field hh-dialog-field">
			<label for="plot-planted-date">Planted date <span class="required">*</span></label>
			<input type="date" id="plot-planted-date" required>
		</div>
		<div class="field hh-dialog-field">
			<label for="plot-notes">Initial notes <span class="field-optional">(optional)</span></label>
			<textarea id="plot-notes" maxlength="1000" rows="3" placeholder="e.g., Used organic compost"></textarea>
		</div>
		<div class="crop-catalog-request-toggle">
			<p>Can’t find your crop?</p>
			<button type="button" class="btn btn-ghost btn-sm" id="show-crop-request">Request it for the catalog</button>
		</div>
		<section id="crop-request-panel" class="crop-catalog-request-panel" hidden aria-labelledby="crop-request-title">
			<h4 id="crop-request-title">Request a crop addition</h4>
			<p class="text-muted">A coordinator will review the requested crop before adding it to the catalog.</p>
			<div id="crop-request-form">
				<div class="field">
					<label for="requested-crop-name">Common name <span class="required">*</span></label>
					<input type="text" id="requested-crop-name" maxlength="60" required>
				</div>
				<div class="field">
					<label for="requested-crop-notes">Notes or source <span class="field-optional">(optional)</span></label>
					<textarea id="requested-crop-notes" maxlength="500" rows="2" placeholder="Share why this plant belongs in the crop catalog."></textarea>
				</div>
				<button type="button" class="btn btn-accent btn-sm" id="submit-crop-request">Send crop request</button>
			</div>
		</section>
		<div id="my-crop-catalog-requests" class="my-crop-catalog-requests" aria-live="polite"></div>
		<div class="hh-dialog-actions">
			<button type="button" class="btn btn-ghost" id="cancel-add-crop">Cancel</button>
			<button type="submit" class="btn btn-accent">Plant crop</button>
		</div>
	</form>
</dialog>
<script src="assets/app.js"></script>
<script src="assets/customer.js?v=16"></script>
<script src="assets/crops.js?v=3"></script>
</body>
</html>
