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
      if (missingRequiredFields) {
        if (!button.disabled) {
          button.dataset.requiredFieldsDisabled = 'true';
          button.disabled = true;
        }
      } else if (button.dataset.requiredFieldsDisabled === 'true') {
        button.disabled = false;
        delete button.dataset.requiredFieldsDisabled;
      }
    });
  };

  const updateAllForms = () => {
    document.querySelectorAll('form').forEach(updateForm);
  };

  document.addEventListener('input', (event) => {
    if (event.target.form) updateForm(event.target.form);
  });

  document.addEventListener('change', (event) => {
    if (event.target.form) updateForm(event.target.form);
  });

  document.addEventListener('submit', (event) => {
    if (!hasMissingRequiredFields(event.target)) {
      getSubmitButtons(event.target).forEach((button) => {
        delete button.dataset.requiredFieldsDisabled;
      });
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    updateForm(event.target);
    Array.from(event.target.querySelectorAll('input[required], select[required], textarea[required]'))
      .find((field) => isMissingRequiredField(field))
      ?.focus();
  }, true);

  const observer = new MutationObserver((mutations) => {
    const forms = new Set();

    mutations.forEach((mutation) => {
      if (mutation.type === 'childList') {
        if (mutation.target.form) forms.add(mutation.target.form);
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType !== Node.ELEMENT_NODE) return;
          if (node.matches('form')) forms.add(node);
          node.querySelectorAll('form').forEach((form) => forms.add(form));
          if (node.form) forms.add(node.form);
          node.querySelectorAll('input[required], select[required], textarea[required]')
            .forEach((field) => {
              if (field.form) forms.add(field.form);
            });
        });
      } else if (mutation.target.form) {
        forms.add(mutation.target.form);
      }
    });

    forms.forEach(updateForm);
  });

  updateAllForms();
  observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['required', 'disabled']
  });

  window.addEventListener('load', () => setTimeout(updateAllForms, 300));
})();
