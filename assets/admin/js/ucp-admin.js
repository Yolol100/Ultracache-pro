(function () {
  'use strict';

  const params = new URLSearchParams(window.location.search);
  const allowedTabs = new Set(
    (window.ucpAdmin && Array.isArray(window.ucpAdmin.allowedTabs))
      ? window.ucpAdmin.allowedTabs
      : ['overview', 'simple', 'cache', 'optimization', 'media', 'preload', 'woocommerce', 'compatibility', 'cdn', 'tools', 'expert']
  );

  function normalizeTab(tab) {
    return allowedTabs.has(tab) ? tab : 'overview';
  }

  function onReady(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
      return;
    }
    callback();
  }

  window.UCPAdminApp = window.UCPAdminApp || {};
  Object.assign(window.UCPAdminApp, {
    allowedTabs,
    params,
    normalizeTab,
    currentTab: normalizeTab(params.get('tab') || 'overview'),
    onReady,
    markDirty(form) {
      if (form && typeof window.UCPAdminApp.updateDirtyState === 'function') {
        window.UCPAdminApp.updateDirtyState(form);
      }
    },
    warnBeforeLeave(callback) {
      const dirtyForm = document.querySelector('.ucp-workspace__main form.is-dirty[action="options.php"]');
      if (!dirtyForm) {
        callback();
        return;
      }
      const message = (window.ucpAdmin && ucpAdmin.messages && ucpAdmin.messages.leaveWithoutSave)
        ? ucpAdmin.messages.leaveWithoutSave
        : 'Je hebt iets aangepast. Wil je weggaan zonder op te slaan?';
      if (window.confirm(message)) {
        callback();
      }
    }
  });
}());
