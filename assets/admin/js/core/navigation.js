document.addEventListener('DOMContentLoaded', function () {
  const core = window.ucpAdminCore || {};
  const normalizeTab = core.normalizeTab || function (tab) { return tab || 'overview'; };
  const warnBeforeLeave = core.warnBeforeLeave || function (callback) { callback(); };

  document.querySelectorAll('.ucp-tab').forEach(function (tabLink) {
    tabLink.addEventListener('click', function (event) {
      const href = tabLink.getAttribute('href');
      if (!href) return;

      event.preventDefault();
      warnBeforeLeave(function () {
        try {
          const url = new URL(href, window.location.origin);
          const nextTab = normalizeTab(url.searchParams.get('tab') || 'overview');
          window.localStorage.setItem('ucpCurrentTab', nextTab);
        } catch (e) {}
        window.location.href = href;
      });
    });
  });

  document.querySelectorAll('.ucp-toolbar').forEach(function (toolbar) {
    const button = toolbar.querySelector('button');
    if (!button) return;
    button.addEventListener('click', function (e) {
      e.preventDefault();
      const url = new URL(window.location.href);
      toolbar.querySelectorAll('input[name], select[name]').forEach(function (field) {
        if (field.value) {
          url.searchParams.set(field.name, field.value);
        } else {
          url.searchParams.delete(field.name);
        }
      });
      warnBeforeLeave(function () {
        window.location.href = url.toString();
      });
    });
  });
});
