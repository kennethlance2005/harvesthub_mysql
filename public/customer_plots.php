<?php
require_once __DIR__ . '/auth.php';
$user = requireRole('customer');
$navTitle = 'My Plots & Crops';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — My Plots & Crops</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=5">
</head>
<body>

<div class="app-layout">
  
  <?php include __DIR__ . '/customer_sidebar.php'; ?>

  <div class="main-content">
    <main class="wrap" id="top" style="max-width: 1200px; padding-top: 32px;">

      <div style="display: flex; gap: 32px; align-items: flex-start; flex-wrap: wrap;">
        
        <!-- WIDE LEFT COLUMN: Active Plots (flex: 3) -->
        <div style="flex: 3; min-width: 600px; display: flex; flex-direction: column; gap: 32px;">
          
          <div class="board-panel">
            <div class="board-head" style="margin-bottom: 24px; padding: 0 24px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
              <div>
                <h2 style="margin-bottom: 8px;">My Garden Log</h2>
                <p class="text-muted" style="margin: 0;">Track your planted crops and estimate harvest timelines.</p>
              </div>
              
              <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <select id="plots-category-filter" style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff; min-width: 150px;">
                  <option value="All">All Categories</option>
                  <option value="Planted">Planted</option>
                  <option value="Harvested">Harvested</option>
                  <option value="Failed">Failed</option>
                </select>
                <input type="search" id="search-plots" placeholder="Search crops..." style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 4px; width: 220px;">
              </div>
            </div>

            <div class="panel" style="padding: 0 24px;">
              <div id="plots-list" class="scroll-y" aria-live="polite" style="max-height: 420px; min-height: 180px;">
                  <p class="empty-state">Loading your garden plots...</p>
              </div>
            </div>
          </div>

          <!-- Interactive Garden Map -->
          <div class="board-panel" style="margin-top: 32px; padding: 24px;">
            <div style="margin-bottom: 24px;">
              <h2 style="font-size: 1.25rem; margin-bottom: 8px;">Community Garden Map</h2>
              <p class="text-muted" style="margin: 0;">Click on any green available plot to request space from the coordinator.</p>
            </div>
            
            <!-- Map Grid -->
            <div id="garden-map-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; background: #f8fafc; padding: 32px; border-radius: 8px; border: 2px dashed #cbd5e1; min-height: 250px; max-height: 420px;">
                <p class="text-muted" style="grid-column: span 4; text-align: center;">Loading map...</p>
            </div>
            
            <!-- Map Legend -->
            <div style="display: flex; gap: 24px; margin-top: 24px; justify-content: center; font-size: 0.85rem; color: #475569;">
                <div style="display: flex; align-items: center; gap: 8px;"><div style="width: 16px; height: 16px; background: #dcfce7; border: 1px solid #22c55e; border-radius: 4px;"></div> Available</div>
                <div style="display: flex; align-items: center; gap: 8px;"><div style="width: 16px; height: 16px; background: #fef08a; border: 1px solid #eab308; border-radius: 4px;"></div> Pending</div>
                <div style="display: flex; align-items: center; gap: 8px;"><div style="width: 16px; height: 16px; background: #e2e8f0; border: 1px solid #94a3b8; border-radius: 4px;"></div> Occupied</div>
            </div>
          </div>

        </div>

        <!-- NARROW RIGHT COLUMN: Log New Crop Form (flex: 1) -->
        <div style="flex: 1; min-width: 280px; display: flex; flex-direction: column; gap: 24px;">
          
          <div class="board-panel" style="padding: 24px;">
            <h2 style="font-size: 1.25rem; margin-bottom: 8px;">Log a Crop</h2>
            <p class="text-muted" style="margin-bottom: 16px; font-size: 0.9rem;">Planted something new? Record it here.</p>
            
            <form id="add-plot-form" style="display: flex; flex-direction: column; gap: 12px;" novalidate>
              
              <div>
                <label style="display: block; margin-bottom: 4px; font-size: 0.85rem; color: #475569; font-weight: 500;">Crop Name</label>
                <input type="text" id="plot-crop-name" placeholder="e.g., Cherry Tomatoes" pattern="[A-Za-z\s]+" title="Letters and spaces only." style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;" required>
              </div>
              
              <div>
                <label style="display: block; margin-bottom: 4px; font-size: 0.85rem; color: #475569; font-weight: 500;">Planted Date</label>
                <input type="date" id="plot-planted-date" style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;" required>
              </div>
              
              <div>
                <label style="display: block; margin-bottom: 4px; font-size: 0.85rem; color: #475569; font-weight: 500;">Notes (Optional)</label>
                <textarea id="plot-notes" placeholder="e.g., Used organic compost" rows="2" style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; resize: none;"></textarea>
              </div>

              <button type="submit" class="btn btn-accent" style="width: 100%; padding: 10px; margin-top: 4px;">Log Crop</button>
            </form>
          </div>

        </div>

      </div>

    </main>
  </div>
</div>

<div class="toast-container" id="toast-container" aria-live="polite"></div>
<!-- Plot Request Modal Overlay -->
<div id="plot-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 16px;">
  <div class="board-panel" style="padding: 24px; width: 100%; max-width: 400px; background: #fff; border-radius: 8px; text-align: center;">
    <h3 style="margin-bottom: 12px; font-size: 1.25rem;">Request Plot</h3>
    <p class="text-muted" style="margin-bottom: 24px;">Do you want to send a request to the coordinator to claim <strong id="modal-plot-name" style="color: var(--accent);">--</strong>?</p>
    
    <input type="hidden" id="modal-plot-id">
    
    <div style="display: flex; justify-content: center; gap: 12px;">
      <button type="button" class="btn btn-ghost" id="cancel-plot-btn" style="border: 1px solid #cbd5e1; width: 100px;">Cancel</button>
      <button type="button" class="btn btn-accent" id="confirm-plot-btn" style="width: 120px;">Send Request</button>
    </div>
  </div>
</div>
<script src="assets/app.js"></script>
<script src="assets/plots.js"></script> 
</body>
</html>