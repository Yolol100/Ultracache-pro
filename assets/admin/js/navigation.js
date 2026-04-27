(function (app) {
  'use strict';
  if (!app) return;

  app.onReady(function () {
    const params = app.params;
    const page = params.get('page');
    const explicitTab = params.get('tab');
    const currentTab = app.normalizeTab(explicitTab || 'overview');

    if (page === 'ultracache-pro') {
      try {
        if (explicitTab && app.allowedTabs.has(explicitTab)) {
          window.localStorage.setItem('ucpCurrentTab', currentTab);
        } else {
          const rememberedTab = window.localStorage.getItem('ucpCurrentTab');
          if (rememberedTab && !app.allowedTabs.has(rememberedTab)) {
            window.localStorage.removeItem('ucpCurrentTab');
          }
        }
      } catch (e) {}
    }

    document.querySelectorAll('.ucp-tab').forEach(function (tabLink) {
      tabLink.addEventListener('click', function (event) {
        const href = tabLink.getAttribute('href');
        if (!href) return;
        event.preventDefault();
        app.warnBeforeLeave(function () {
          try {
            const url = new URL(href, window.location.origin);
            const nextTab = app.normalizeTab(url.searchParams.get('tab') || 'overview');
            window.localStorage.setItem('ucpCurrentTab', nextTab);
          } catch (e) {}
          window.location.href = href;
        });
      });
    });

    document.querySelectorAll('.ucp-toolbar').forEach(function (toolbar) {
      const button = toolbar.querySelector('button');
      if (!button) return;
      button.addEventListener('click', function (event) {
        event.preventDefault();
        const url = new URL(window.location.href);
        toolbar.querySelectorAll('input[name], select[name]').forEach(function (field) {
          if (field.value) url.searchParams.set(field.name, field.value);
          else url.searchParams.delete(field.name);
        });
        app.warnBeforeLeave(function () { window.location.href = url.toString(); });
      });
    });
  });
}(window.UCPAdminApp));
