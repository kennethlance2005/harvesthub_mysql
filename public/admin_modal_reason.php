<div id="admin-reason-modal" role="dialog" aria-modal="true" aria-labelledby="admin-reason-title" hidden style="position: fixed; inset: 0; z-index: 1000; background: rgba(15, 23, 42, 0.55); place-items: center; padding: 20px;">
  <form id="admin-reason-form" class="panel" style="width: min(100%, 520px);">
    <h2 id="admin-reason-title" style="margin-top: 0;">Reason required</h2>
    <label for="admin-reason-input">Reason</label>
    <textarea id="admin-reason-input" maxlength="1000" required rows="4" style="width: 100%; margin: 8px 0 16px;"></textarea>
    <div style="display: flex; justify-content: flex-end; gap: 10px;">
      <button type="button" class="btn btn-ghost" id="admin-reason-cancel">Cancel</button>
      <button type="submit" class="btn btn-accent">Submit decision</button>
    </div>
  </form>
</div>
