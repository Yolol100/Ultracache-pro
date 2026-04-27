(function (app) {
  'use strict';
  if (!app) return;

  function getFormFields(form) {
    return Array.from(form.querySelectorAll('input, select, textarea')).filter(function (field) {
      return field.name && field.type !== 'hidden' && !field.disabled;
    });
  }

  function getFieldValue(field) {
    return (field.type === 'checkbox' || field.type === 'radio') ? (field.checked ? '1' : '0') : field.value;
  }

  function getFieldKey(field) {
    const type = field.type || field.tagName.toLowerCase();
    const id = field.id || '';
    const optionValue = field.type === 'radio' ? (field.getAttribute('value') || '') : '';
    return [field.name, type, id, optionValue].join('::');
  }

  function captureInitialValues(form) {
    const values = {};
    getFormFields(form).forEach(function (field) {
      values[getFieldKey(field)] = getFieldValue(field);
    });
    form.dataset.ucpInitial = JSON.stringify(values);
  }

  function formHasChanges(form) {
    try {
      const initial = JSON.parse(form.dataset.ucpInitial || '{}');
      return getFormFields(form).some(function (field) {
        const key = getFieldKey(field);
        return !(key in initial) || initial[key] !== getFieldValue(field);
      });
    } catch (e) {
      return form.classList.contains('is-dirty');
    }
  }

  function updateDirtyState(form) {
    if (!form) return;
    if (typeof app.evaluateConditionalFields === 'function') {
      app.evaluateConditionalFields(form);
    }
    form.classList.toggle('is-dirty', formHasChanges(form));
  }

  app.updateDirtyState = updateDirtyState;
  app.markDirty = updateDirtyState;

  app.onReady(function () {
    document.querySelectorAll('.ucp-workspace__main form[action="options.php"]').forEach(function (form) {
      const submitRow = form.querySelector('.ucp-submit-row');
      if (!submitRow) return;

      if (typeof app.evaluateConditionalFields === 'function') {
        app.evaluateConditionalFields(form);
      }
      captureInitialValues(form);

      form.addEventListener('change', function () { updateDirtyState(form); });
      form.addEventListener('input', function () { updateDirtyState(form); });
      form.addEventListener('submit', function () {
        form.classList.remove('is-dirty');
        try { window.localStorage.setItem('ucpCurrentTab', app.currentTab); } catch (e) {}
      });

      if (app.params.has('settings-updated')) {
        form.classList.remove('is-dirty');
      }

      const resetButton = form.querySelector('.ucp-reset-form');
      if (resetButton) {
        resetButton.addEventListener('click', function () {
          form.reset();
          form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(function (field) {
            field.checked = field.defaultChecked;
          });
          form.querySelectorAll('select').forEach(function (field) {
            Array.from(field.options).forEach(function (option) { option.selected = option.defaultSelected; });
          });
          form.querySelectorAll('textarea, input[type="text"], input[type="number"], input[type="url"], input[type="email"]').forEach(function (field) {
            field.value = field.defaultValue;
          });
          updateDirtyState(form);
        });
      }
    });

    document.addEventListener('keydown', function (event) {
      const isSaveShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's';
      if (!isSaveShortcut) return;
      const form = document.querySelector('.ucp-workspace__main form.is-dirty[action="options.php"]');
      if (!form || !form.contains(document.activeElement)) return;
      event.preventDefault();
      const submitButton = form.querySelector('.ucp-submit-row .button-primary, .ucp-submit-row .ucp-button--primary');
      if (submitButton) submitButton.click();
    });

    window.addEventListener('beforeunload', function (event) {
      if (!document.querySelector('.ucp-workspace__main form.is-dirty[action="options.php"]')) return;
      event.preventDefault();
      event.returnValue = '';
    });
  });
}(window.UCPAdminApp));
