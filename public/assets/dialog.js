// Shared confirm / prompt / alert dialogs that replace the browser's
// window.confirm(), window.prompt() and window.alert().
//
//   if (!await hhConfirm({ title: 'Delete plot?', message: '...', confirmText: 'Delete', tone: 'danger' })) return;
//   const reason = await hhPrompt({ title: 'Why is this declined?', label: 'Reason' }); // null when cancelled
//   await hhAlert({ title: 'Password updated', message: 'You can now log in.' });
//
// Each call returns a Promise. Esc, the Cancel button and clicking the
// backdrop all count as cancelling.
(function () {
  function buildDialog({ title, message, confirmText, cancelText, tone, input }) {
    const dialog = document.createElement('dialog');
    dialog.className = 'hh-dialog';
    dialog.setAttribute('aria-labelledby', 'hh-dialog-title');

    const form = document.createElement('form');
    form.method = 'dialog';
    form.className = 'hh-dialog-box';
    form.noValidate = true;

    const heading = document.createElement('h3');
    heading.id = 'hh-dialog-title';
    heading.textContent = title;
    form.appendChild(heading);

    if (message) {
      const text = document.createElement('p');
      text.className = 'hh-dialog-message';
      text.textContent = message;
      form.appendChild(text);
    }

    let field = null;
    let error = null;
    if (input) {
      const wrap = document.createElement('div');
      wrap.className = 'field hh-dialog-field';
      const label = document.createElement('label');
      label.htmlFor = 'hh-dialog-input';
      label.innerHTML = `${escapeText(input.label || 'Reason')} <span class="required">*</span>`;
      field = document.createElement('textarea');
      field.id = 'hh-dialog-input';
      field.rows = 4;
      field.maxLength = input.maxLength || 1000;
      field.placeholder = input.placeholder || '';
      // Validated here (not with `required`) so required-form-buttons.js
      // doesn't intercept the submit; the button stays off until text is typed.
      field.setAttribute('aria-required', 'true');
      error = document.createElement('p');
      error.className = 'hh-dialog-error';
      error.setAttribute('role', 'alert');
      error.hidden = true;
      wrap.append(label, field, error);
      form.appendChild(wrap);
    }

    const actions = document.createElement('div');
    actions.className = 'hh-dialog-actions';
    let cancelBtn = null;
    if (cancelText) {
      cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'btn btn-ghost';
      cancelBtn.textContent = cancelText;
      actions.appendChild(cancelBtn);
    }
    const confirmBtn = document.createElement('button');
    confirmBtn.type = 'submit';
    confirmBtn.className = `btn ${tone === 'danger' ? 'btn-danger' : 'btn-accent'}`;
    confirmBtn.textContent = confirmText;
    actions.appendChild(confirmBtn);
    form.appendChild(actions);

    dialog.appendChild(form);
    return { dialog, form, field, error, cancelBtn, confirmBtn };
  }

  function escapeText(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
  }

  function open(options) {
    const parts = buildDialog(options);
    const { dialog, form, field, error, cancelBtn, confirmBtn } = parts;
    document.body.appendChild(dialog);

    return new Promise(resolve => {
      let settled = false;
      const finish = value => {
        if (settled) return;
        settled = true;
        if (dialog.open) dialog.close();
        dialog.remove();
        resolve(value);
      };

      form.addEventListener('submit', event => {
        event.preventDefault();
        if (field) {
          const value = field.value.trim();
          if (!value) {
            error.textContent = 'Please provide a reason.';
            error.hidden = false;
            field.focus();
            return;
          }
          finish(value);
        } else {
          finish(true);
        }
      });
      if (field) {
        // Same convention as the app's other forms: the submit button stays
        // greyed out until the required reason has been typed.
        const syncButton = () => { confirmBtn.disabled = field.value.trim() === ''; };
        field.addEventListener('input', () => { error.hidden = true; syncButton(); });
        syncButton();
      }
      if (cancelBtn) cancelBtn.addEventListener('click', () => finish(options.cancelValue));
      // Esc key
      dialog.addEventListener('cancel', event => {
        event.preventDefault();
        finish(options.cancelValue);
      });
      // Click on the dimmed backdrop (outside the box)
      dialog.addEventListener('click', event => {
        if (event.target === dialog) finish(options.cancelValue);
      });

      dialog.showModal();
      // Destructive actions start on Cancel so a stray Enter doesn't confirm them.
      (field || (options.tone === 'danger' && cancelBtn) || confirmBtn).focus();
    });
  }

  window.hhConfirm = function ({ title, message = '', confirmText = 'Confirm', cancelText = 'Cancel', tone = 'default' }) {
    return open({ title, message, confirmText, cancelText, tone, cancelValue: false });
  };

  window.hhPrompt = function ({ title, message = '', label = 'Reason', placeholder = '', maxLength = 1000, confirmText = 'Submit', cancelText = 'Cancel', tone = 'default' }) {
    return open({ title, message, confirmText, cancelText, tone, cancelValue: null, input: { label, placeholder, maxLength } });
  };

  window.hhAlert = function ({ title, message = '', buttonText = 'OK' }) {
    return open({ title, message, confirmText: buttonText, cancelText: null, tone: 'default', cancelValue: undefined });
  };

  // A wider dialog for reviewing details before a decision.
  //   const choice = await hhDetails({ title, bodyHtml, actions: [{ label: 'Reject', value: 'reject', tone: 'danger' }, { label: 'Approve', value: 'approve' }] });
  // Resolves to the clicked action's value, or null when closed.
  // bodyHtml is inserted as HTML, so callers must escape any user data in it.
  window.hhDetails = function ({ title, bodyHtml, actions = [], closeText = 'Close' }) {
    const dialog = document.createElement('dialog');
    dialog.className = 'hh-dialog hh-dialog-wide';
    dialog.setAttribute('aria-labelledby', 'hh-dialog-title');

    const box = document.createElement('div');
    box.className = 'hh-dialog-box';
    const heading = document.createElement('h3');
    heading.id = 'hh-dialog-title';
    heading.textContent = title;
    const body = document.createElement('div');
    body.className = 'hh-dialog-body';
    body.innerHTML = bodyHtml;

    const actionsEl = document.createElement('div');
    actionsEl.className = 'hh-dialog-actions';
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn btn-ghost';
    closeBtn.textContent = closeText;
    actionsEl.appendChild(closeBtn);
    const actionButtons = actions.map(action => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = `btn ${action.tone === 'danger' ? 'btn-danger' : 'btn-accent'}`;
      btn.textContent = action.label;
      btn.disabled = Boolean(action.disabled);
      if (action.title) btn.title = action.title;
      actionsEl.appendChild(btn);
      return { btn, value: action.value };
    });

    box.append(heading, body, actionsEl);
    dialog.appendChild(box);
    document.body.appendChild(dialog);

    return new Promise(resolve => {
      const finish = value => {
        if (dialog.open) dialog.close();
        dialog.remove();
        resolve(value);
      };
      closeBtn.addEventListener('click', () => finish(null));
      actionButtons.forEach(({ btn, value }) => btn.addEventListener('click', () => finish(value)));
      dialog.addEventListener('cancel', event => { event.preventDefault(); finish(null); });
      dialog.addEventListener('click', event => { if (event.target === dialog) finish(null); });
      dialog.showModal();
      closeBtn.focus();
    });
  };
})();
