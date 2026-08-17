(function() {
    "use strict";
    function dismiss(toast) {
        if (!toast || toast.classList.contains("is-dismissing")) {
            return;
        }
        toast.classList.add("is-dismissing");
        window.setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 220);
    }
    function initToast(toast) {
        var close = toast.querySelector("[data-ucp-cache-toast-close]");
        if (close) {
            close.addEventListener("click", function() {
                dismiss(toast);
            });
        }
    }
    function init() {
        Array.prototype.forEach.call(document.querySelectorAll("[data-ucp-cache-toast]"), initToast);
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init, { once: true });
    } else {
        init();
    }
})();
