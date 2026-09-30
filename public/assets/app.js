// app.js — Produce Exchange Board frontend logic

const API_URL = 'api.php';
const CROP_PATTERN = /^[A-Za-z\s\-']+$/;

const listingsEl = document.getElementById('listings');
const emptyStateEl = document.getElementById('empty-state');
const resultsCountEl = document.getElementById('results-count');

const searchEl = document.getElementById('search');
const minQtyEl = document.getElementById('min-qty');
const sortEl = document.getElementById('sort');

const formEl = document.getElementById('listing-form');
const cropEl = document.getElementById('crop');
const qtyEl = document.getElementById('qty');
const notesEl = document.getElementById('notes');
const notesCountEl = document.getElementById('notes-count');
const cropErrorEl = document.getElementById('crop-error');
const qtyErrorEl = document.getElementById('qty-error');
const formAlertEl = document.getElementById('form-alert');
const formSuccessEl = document.getElementById('form-success');

const toastContainer = document.getElementById('toast-container');

const claimModal = document.getElementById('claim-modal');
const claimModalBody = document.getElementById('claim-modal-body');
const claimCancelBtn = document.getElementById('claim-cancel');
const claimConfirmBtn = document.getElementById('claim-confirm');

let pendingClaim = null;

// ---------- Rendering ----------

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function renderListings(listings) {
  if (!listingsEl || !resultsCountEl) return;
  listingsEl.innerHTML = '';
  resultsCountEl.textContent = listings.length;

  if (listings.length === 0) {
    if (emptyStateEl) emptyStateEl.hidden = false;
    return;
  }
  if (emptyStateEl) emptyStateEl.hidden = true;

  for (const item of listings) {
    const li = document.createElement('li');
    li.className = 'listing';
    li.innerHTML = `
      <div class="listing-main">
        <p class="listing-crop">${escapeHtml(item.Crop)}</p>
        <p class="listing-by">Posted by ${escapeHtml(item.GardenerName)}</p>
        ${item.Notes ? `<p class="listing-notes">${escapeHtml(item.Notes)}</p>` : ''}
      </div>
      <div class="listing-side">
        <span class="qty-tag">Qty: ${escapeHtml(String(item.Qty))}</span>
        <button class="btn btn-accent claim-btn" data-id="${item.ListingID}" data-crop="${escapeHtml(item.Crop)}" data-qty="${escapeHtml(String(item.Qty))}">
          Claim
        </button>
      </div>
    `;
    listingsEl.appendChild(li);
  }

  document.querySelectorAll('.claim-btn').forEach((btn) => {
    btn.addEventListener('click', () => openClaimModal(btn));
  });
}

// ---------- API calls ----------

async function loadListings() {
  if (!sortEl || !listingsEl) return;
  const params = new URLSearchParams({ action: 'list', sort: sortEl.value });
  if (searchEl && searchEl.value.trim()) params.set('search', searchEl.value.trim());
  if (minQtyEl && minQtyEl.value) params.set('min_qty', minQtyEl.value);

  try {
    const res = await fetch(`${API_URL}?${params.toString()}`);
    const data = await res.json();
    if (data.ok) {
      renderListings(data.listings);
    } else {
      showToast('Could not load listings.', 'danger');
    }
  } catch (err) {
    showToast('Network error while loading listings.', 'danger');
  }
}

async function claimListing(listingId, btnEl) {
  btnEl.disabled = true;
  btnEl.textContent = 'Claiming...';

  try {
    const res = await fetch(`${API_URL}?action=claim`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ listing_id: listingId }),
    });
    const data = await res.json();

    if (data.ok) {
      showToast('Listing claimed! The gardener will be notified.', 'success');
      loadListings();
    } else {
      showToast(data.error || 'Could not claim this listing.', 'danger');
      btnEl.disabled = false;
      btnEl.textContent = 'Claim';
    }
  } catch (err) {
    showToast('Network error while claiming listing.', 'danger');
    btnEl.disabled = false;
    btnEl.textContent = 'Claim';
  }
}

// ---------- Claim confirmation modal ----------

function openClaimModal(btnEl) {
  pendingClaim = { listingId: btnEl.dataset.id, btnEl };
  if (claimModalBody) {
    claimModalBody.textContent = `Claim ${btnEl.dataset.qty} of "${btnEl.dataset.crop}"? It will be removed from the board once you confirm.`;
  }
  if (claimModal) claimModal.hidden = false;
  if (claimConfirmBtn) claimConfirmBtn.focus();
}

function closeClaimModal() {
  if (claimModal) claimModal.hidden = true;
  pendingClaim = null;
}

if (claimCancelBtn) claimCancelBtn.addEventListener('click', closeClaimModal);
if (claimModal) {
  claimModal.addEventListener('click', (e) => {
    if (e.target === claimModal) closeClaimModal();
  });
}
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && claimModal && !claimModal.hidden) closeClaimModal();
});

if (claimConfirmBtn) {
  claimConfirmBtn.addEventListener('click', () => {
    if (!pendingClaim) return;
    const { listingId, btnEl } = pendingClaim;
    if (claimModal) claimModal.hidden = true;
    claimListing(listingId, btnEl);
    pendingClaim = null;
  });
}

// ---------- Form validation + submit ----------

function validateForm() {
  let valid = true;
  if (!cropEl || !qtyEl) return valid;
  const crop = cropEl.value.trim();

  if (crop.length === 0 || crop.length > 60 || !CROP_PATTERN.test(crop)) {
    cropEl.classList.add('invalid');
    if (cropErrorEl) cropErrorEl.hidden = false;
    valid = false;
  } else {
    cropEl.classList.remove('invalid');
    if (cropErrorEl) cropErrorEl.hidden = true;
  }

  const qtyNum = Number(qtyEl.value);
  if (!Number.isInteger(qtyNum) || qtyNum < 1 || qtyNum > 1000) {
    qtyEl.classList.add('invalid');
    if (qtyErrorEl) qtyErrorEl.hidden = false;
    valid = false;
  } else {
    qtyEl.classList.remove('invalid');
    if (qtyErrorEl) qtyErrorEl.hidden = true;
  }

  return valid;
}

function updateNotesCount() {
  if (!notesEl || !notesCountEl) return;
  const remaining = 200 - notesEl.value.length;
  notesCountEl.textContent = remaining;
}

if (notesEl) notesEl.addEventListener('input', updateNotesCount);
if (cropEl) cropEl.addEventListener('blur', () => { if (cropEl.value) validateForm(); });
if (qtyEl) qtyEl.addEventListener('blur', () => { if (qtyEl.value) validateForm(); });

if (formEl) {
  formEl.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (formAlertEl) formAlertEl.hidden = true;
    if (formSuccessEl) formSuccessEl.hidden = true;

    if (!validateForm()) return;

    const formData = new URLSearchParams({
      action: 'create',
      crop: cropEl.value.trim(),
      qty: qtyEl.value,
      notes: notesEl ? notesEl.value.trim() : '',
    });

    try {
      const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData,
      });
      const data = await res.json();

      if (data.ok) {
        if (formSuccessEl) {
          formSuccessEl.textContent = 'Listing posted successfully!';
          formSuccessEl.hidden = false;
        }
        formEl.reset();
        updateNotesCount();
        loadListings();
      } else {
        if (formAlertEl) {
          formAlertEl.textContent = (data.errors || [data.error]).join(' ');
          formAlertEl.hidden = false;
        }
      }
    } catch (err) {
      if (formAlertEl) {
        formAlertEl.textContent = 'Network error while posting listing.';
        formAlertEl.hidden = false;
      }
    }
  });
}

// ---------- Search / filter / sort ----------

let debounceTimer;
function debouncedLoad() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(loadListings, 300);
}
if (searchEl) searchEl.addEventListener('input', debouncedLoad);
if (minQtyEl) minQtyEl.addEventListener('input', debouncedLoad);
if (sortEl) sortEl.addEventListener('change', loadListings);

// ---------- Toasts ----------

function showToast(message, type = 'success') {
  if (!toastContainer) return;
  const toastEl = document.createElement('div');
  toastEl.className = `toast${type === 'danger' ? ' toast-danger' : ''}`;
  toastEl.textContent = message;
  toastContainer.appendChild(toastEl);
  setTimeout(() => toastEl.remove(), 3500);
}

// ---------- Init ----------

document.addEventListener('DOMContentLoaded', () => {
  updateNotesCount();
  loadListings();
});