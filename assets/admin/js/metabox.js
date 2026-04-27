(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ucp-metabox details').forEach(function (detail) {
      detail.addEventListener('toggle', function () {
        detail.dataset.ucpOpen = detail.open ? '1' : '0';
      });
    });
  });
}());
