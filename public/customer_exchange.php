<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'Exchange Board';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Exchange Board</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=44">
</head>
<body class="account-page">

<div class="app-layout">

  <?php include __DIR__ . '/customer_sidebar.php'; ?>

  <div class="main-content">
    <main class="wrap gardener-page exchange-page" id="top">

      <header class="page-head">
        <div>
          <p class="eyebrow">Share the harvest</p>
          <h1>Community Exchange</h1>
          <p class="text-muted">Trade surplus crops, seeds, or homemade goods with other gardeners.</p>
        </div>
      </header>

      <div class="ex-layout">

        <!-- WIDE LEFT COLUMN: Community Exchange Feed -->
        <section class="panel ex-card" aria-labelledby="exchange-feed-title">
          <div class="ex-card-head">
            <div>
              <h2 class="panel-title" id="exchange-feed-title">Available now</h2>
              <p class="ex-card-sub">Claim something you'd like and arrange a pickup with the grower.</p>
            </div>
            <input type="search" id="search-exchange" class="ex-search" placeholder="Search produce..." aria-label="Search produce">
          </div>

          <div id="exchange-feed-list" aria-live="polite">
            <p class="ex-empty">Loading exchange feed...</p>
          </div>
        </section>

        <!-- NARROW RIGHT COLUMN: Post Form, My Listings, Requests -->
        <div class="ex-side">

          <!-- Create Listing Form -->
          <section class="panel ex-card" aria-labelledby="post-item-title">
            <h2 class="panel-title" id="post-item-title">Post an item</h2>
            <p class="ex-card-sub">Have extra harvest? List it here.</p>

            <form id="add-exchange-form" class="ex-form">
              <div class="field">
                <label for="exchange-item">Harvested crop <span class="required">*</span></label>
                <select id="exchange-item" required>
                  <option value="" selected disabled>Loading harvested crops...</option>
                </select>
                <small id="exchange-crop-hint" class="ex-hint">Only crops marked as Harvested in My Crops can be listed.</small>
              </div>

              <div class="ex-form-row">
                <div class="field">
                  <label for="exchange-qty-num">Quantity <span class="required">*</span></label>
                  <input type="number" id="exchange-qty-num" placeholder="e.g., 2.5" step="any" min="0.1" required>
                </div>
                <div class="field">
                  <label for="exchange-qty-unit">Unit <span class="required">*</span></label>
                  <select id="exchange-qty-unit" required>
                    <option value="pcs">pcs</option>
                    <option value="kg">kg</option>
                    <option value="g">g</option>
                    <option value="bundles">bundles</option>
                  </select>
                </div>
              </div>

              <div class="field">
                <label for="exchange-desc">Details <span class="field-optional">(optional)</span></label>
                <textarea id="exchange-desc" placeholder="e.g., Freshly picked, pesticide-free" rows="2"></textarea>
              </div>
              <button type="submit" class="btn btn-accent btn-block">Post to board</button>
            </form>
          </section>

          <!-- My Active Listings -->
          <section class="panel ex-card" aria-labelledby="my-listings-title">
            <h2 class="panel-title" id="my-listings-title">My active listings</h2>
            <div id="my-exchange-list" class="ex-list" aria-live="polite">
              <p class="ex-empty">Loading your listings...</p>
            </div>
          </section>

          <!-- Pending Requests (Action Needed) -->
          <section class="panel ex-card" aria-labelledby="pending-claims-title">
            <h2 class="panel-title" id="pending-claims-title">Requests for my items</h2>
            <div id="pending-claims-list" class="ex-list" aria-live="polite">
              <p class="ex-empty">Loading requests...</p>
            </div>
          </section>

        </div>

      </div>
    </main>
  </div>
</div>

<?php include __DIR__ . '/account_footer.php'; ?>
<div class="toast-container" id="toast-container" aria-live="polite"></div>
<!-- Claim Request Modal Overlay (shown by exchange.js) -->
<div id="claim-modal" class="gardener-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="claim-modal-title">
  <div class="gardener-modal-box gardener-modal-form">
    <h3 id="claim-modal-title">Request to claim</h3>

    <form id="submit-claim-form" class="ex-form">
      <!-- Hidden input to remember which post is being claimed -->
      <input type="hidden" id="claim-post-id">

      <div class="field">
        <label for="claim-qty">Quantity wanted <span class="required">*</span></label>
        <input type="text" id="claim-qty" maxlength="50" placeholder="e.g., 2 pcs, 1 kg" required>
      </div>

      <div class="field">
        <label for="claim-pickup">Preferred pickup details <span class="required">*</span></label>
        <textarea id="claim-pickup" maxlength="500" placeholder="e.g., Tomorrow at 10 AM by the main gate" rows="3" required></textarea>
      </div>

      <div class="gardener-modal-actions">
        <button type="button" class="btn btn-ghost" id="cancel-claim-btn">Cancel</button>
        <button type="submit" class="btn btn-accent">Send request</button>
      </div>
    </form>
  </div>
</div>
<script src="assets/app.js"></script>
<script src="assets/exchange.js?v=4"></script>
</body>
</html>
