document.addEventListener('DOMContentLoaded', function () {
  const core = window.ucpAdminCore = window.ucpAdminCore || {};
  const params = core.params || new URLSearchParams(window.location.search);
  const currentTab = core.currentTab || 'overview';

  function getFormFields(form) {
    return Array.from(form.querySelectorAll('input, select, textarea')).filter(function (field) {
      return field.name && field.type !== 'hidden' && !field.disabled;
    });
  }

  function getFieldValue(field) {
    if (field.type === 'checkbox' || field.type === 'radio') {
      return field.checked ? '1' : '0';
    }
    return field.value;
  }

  function getFieldKey(field) {
    const type = field.type || field.tagName.toLowerCase();
    const id = field.id || '';
    const optionValue = (field.type === 'radio') ? (field.getAttribute('value') || '') : '';
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
      let changed = 0;
      getFormFields(form).forEach(function (field) {
        const key = getFieldKey(field);
        if (!(key in initial) || initial[key] !== getFieldValue(field)) {
          changed += 1;
        }
      });
      return changed > 0;
    } catch (e) {
      return form.classList.contains('is-dirty');
    }
  }

  function updateDirtyState(form) {
    if (!form) return;
    if (typeof core.evaluateConditionalFields === 'function') {
      core.evaluateConditionalFields(form);
    }
    form.classList.toggle('is-dirty', formHasChanges(form));
  }

  function markDirty(form) {
    if (!form) return;
    updateDirtyState(form);
  }

  function warnBeforeLeave(callback) {
    const dirtyForm = document.querySelector('.ucp-workspace__main form.is-dirty[action="options.php"]');
    if (!dirtyForm) {
      callback();
      return;
    }

    const message = (window.ucpAdmin && ucpAdmin.messages && ucpAdmin.messages.leaveWithoutSave) ? ucpAdmin.messages.leaveWithoutSave : 'Je hebt iets aangepast. Wil je weggaan zonder op te slaan?';
    const confirmed = window.confirm(message);
    if (confirmed) {
      callback();
    }
  }

  core.markDirty = markDirty;
  core.warnBeforeLeave = warnBeforeLeave;

  document.querySelectorAll('.ucp-workspace__main form[action="options.php"]').forEach(function (form) {
    const submitRow = form.querySelector('.ucp-submit-row');
    if (!submitRow) return;

    if (typeof core.evaluateConditionalFields === 'function') {
      core.evaluateConditionalFields(form);
    }
    captureInitialValues(form);

    form.addEventListener('change', function () { updateDirtyState(form); });
    form.addEventListener('input', function () { updateDirtyState(form); });
    form.addEventListener('submit', function () {
      form.classList.remove('is-dirty');
      try { window.localStorage.setItem('ucpCurrentTab', currentTab); } catch (e) {}
    });

    if (params.has('settings-updated')) {
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
          Array.from(field.options).forEach(function (option) {
            option.selected = option.defaultSelected;
          });
        });
        form.querySelectorAll('textarea, input[type="text"], input[type="number"], input[type="url"], input[type="email"]').forEach(function (field) {
          field.value = field.defaultValue;
        });
        if (typeof core.evaluateConditionalFields === 'function') {
          core.evaluateConditionalFields(form);
        }
        updateDirtyState(form);
      });
    }

    document.addEventListener('keydown', function (event) {
      const isSaveShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's';
      if (!isSaveShortcut || !form.classList.contains('is-dirty') || !form.contains(document.activeElement)) return;
      event.preventDefault();
      const submitButton = form.querySelector('.ucp-submit-row .button-primary');
      if (submitButton) {
        submitButton.click();
      }
    });
  });

  window.addEventListener('beforeunload', function (event) {
    const dirtyForm = document.querySelector('.ucp-workspace__main form.is-dirty[action="options.php"]');
    if (!dirtyForm) return;
    event.preventDefault();
    event.returnValue = '';
  });
});
