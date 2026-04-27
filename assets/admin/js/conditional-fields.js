(function (app) {
  'use strict';
  if (!app) return;

  function getPrimaryControl(wrapper) {
    return wrapper ? wrapper.querySelector('[data-ucp-primary-control="1"]') : null;
  }

  function getWrapperValue(form, key) {
    const wrapper = form.querySelector('[data-ucp-field-key="' + key + '"]');
    const control = getPrimaryControl(wrapper);
    if (!control) return '';
    if (control.type === 'checkbox' || control.type === 'radio') {
      return control.checked ? '1' : '0';
    }
    return String(control.value || '');
  }

  function ruleMatches(form, rule) {
    if (!rule || !rule.field) return false;
    const actual = getWrapperValue(form, rule.field);
    const expected = String(typeof rule.value === 'undefined' ? '1' : rule.value);
    return (rule.operator || '=') === '!=' ? actual !== expected : actual === expected;
  }

  function setWrapperState(wrapper, shouldDisable, shouldHide) {
    if (!wrapper) return;
    wrapper.classList.toggle('is-disabled', shouldDisable);
    wrapper.classList.toggle('is-hidden-by-logic', shouldHide);
    wrapper.querySelectorAll('[data-ucp-control]').forEach(function (control) {
      control.disabled = shouldDisable;
    });
  }

  function evaluateConditionalFields(form) {
    if (!form) return;
    form.querySelectorAll('[data-ucp-field-key]').forEach(function (wrapper) {
      let shouldDisable = false;
      let shouldHide = false;
      const parentKey = wrapper.getAttribute('data-ucp-parent');
      if (parentKey && getWrapperValue(form, parentKey) !== '1') {
        shouldDisable = true;
        shouldHide = wrapper.getAttribute('data-ucp-hide-when-disabled') === '1';
      }
      const rawRules = wrapper.getAttribute('data-ucp-disabled-if');
      if (rawRules) {
        try {
          const rules = JSON.parse(rawRules);
          if (Array.isArray(rules) && rules.some(function (rule) { return ruleMatches(form, rule); })) {
            shouldDisable = true;
            shouldHide = shouldHide || wrapper.getAttribute('data-ucp-hide-when-disabled') === '1';
          }
        } catch (e) {}
      }
      setWrapperState(wrapper, shouldDisable, shouldHide);
    });
  }

  app.evaluateConditionalFields = evaluateConditionalFields;

  app.onReady(function () {
    document.querySelectorAll('form[action="options.php"]').forEach(function (form) {
      form.addEventListener('submit', function () {
        form.querySelectorAll('.ucp-checkbox').forEach(function (wrapper) {
          const hidden = wrapper.querySelector('input[type="hidden"][data-ucp-control="1"]');
          const checkbox = wrapper.querySelector('input[type="checkbox"][data-ucp-primary-control="1"]');
          if (!hidden || !checkbox) return;
          checkbox.disabled = false;
          hidden.value = '0';
          hidden.disabled = checkbox.checked;
        });
      });
    });
  });
}(window.UCPAdminApp));
