document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.ucp-js-advanced-grid details, .ucp-js-advanced-grid--live details').forEach(function (detail) {
    detail.addEventListener('toggle', function () {
      try {
        detail.dataset.ucpOpenState = detail.open ? '1' : '0';
      } catch (e) {}
    });
  });
});
