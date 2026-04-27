(function (app) {
  'use strict';
  if (!app) return;

  app.onReady(function () {
    document.querySelectorAll('.ucp-js-advanced-grid details, .ucp-js-advanced-grid--live details').forEach(function (detail) {
      detail.addEventListener('toggle', function () {
        detail.dataset.ucpOpenState = detail.open ? '1' : '0';
      });
    });

    document.querySelectorAll('.ucp-disclosure').forEach(function (detail, index) {
      if (!detail.id) {
        detail.id = 'ucp-disclosure-' + app.currentTab + '-' + index;
      }
      detail.addEventListener('toggle', function () {
        if (!detail.open || detail.dataset.ucpSingleOpen !== '1') return;
        const group = detail.closest('[data-ucp-disclosure-group]');
        if (!group) return;
        group.querySelectorAll(':scope > .ucp-disclosure[data-ucp-single-open="1"]').forEach(function (sibling) {
          if (sibling !== detail) sibling.open = false;
        });
      });
    });
  });
}(window.UCPAdminApp));
