(() => {
  const getSubmitButtons = (form) => form.querySelectorAll(
    'button[type="submit"], button:not([type]), input[type="submit"], input[type="image"]'
  );

  const isMissingRequiredField = (field) => {
    if (!field.willValidate) return false;
    if (field.type === 'checkbox' || field.type === 'radio' || field.tagName === 'SELECT') {
      return field.validity.valueMissing;
    }
    return field.value.trim() === '';
  };

  const hasMissingRequiredFields = (form) => {
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    return Array.from(requiredFields).some(isMissingRequiredField);
  };

  const updateForm = (form) => {
    const missingRequiredFields = hasMissingRequiredFields(form);

    getSubmitButtons(form).forEach((button) => {
      const shouldDisable = missingRequiredFields && !button.dataset.forceEnabled;
      if (shouldDisable && !button.disabled) {
        button.dataset.requiredFieldsDisabled = 'true';
        button.disabled = true;
      }
      if (!shouldDisable && button.dataset.requiredFieldsDisabled === 'true') {
        button.disabled = false;
        delete button.dataset.requiredFieldsDisabled;
      }
    });
  };

  const attachFormListeners = (form) => {
    if (!form || form.dataset.formButtonListenerBound === 'true') return;
    form.dataset.formButtonListenerBound = 'true';

    form.addEventListener('input', () => updateForm(form));
    form.addEventListener('change', () => updateForm(form));
    form.addEventListener('submit', (event) => {
      if (hasMissingRequiredFields(form)) {
        event.preventDefault();
        updateForm(form);
        Array.from(form.querySelectorAll('input[required], select[required], textarea[required]'))
          .find((field) => isMissingRequiredField(field))
          ?.focus();
      }
    }, true);
  };

  const initForms = () => {
    document.querySelectorAll('form').forEach((form) => {
      attachFormListeners(form);
      updateForm(form);
    });
  };

  initForms();
  window.addEventListener('load', initForms);
})();
