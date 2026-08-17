(function () {
    'use strict';

    function ready(callback) {
        if ('loading' !== document.readyState) {
            callback();
            return;
        }
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    }

    var messages = window.ucpAssetInspectorL10n || {};

    function message(key, fallback) {
        return messages[key] || fallback;
    }

    function copyText(textarea, status) {
        var value = textarea ? textarea.value : '';
        function setStatus(text) {
            if (status) {
                status.textContent = text;
            }
        }
        if (!value) {
            setStatus(message('selectFirst', 'Selecteer eerst minimaal één asset.'));
            return;
        }

        function copied() {
            setStatus(message('rulesCopied', 'Regels gekopieerd.'));
        }

        function copyFailed() {
            setStatus(message('copyFailed', 'Kopiëren is niet gelukt. Selecteer de tekst en kopieer deze handmatig.'));
        }

        function legacyCopy() {
            textarea.focus();
            textarea.select();
            try {
                if (document.execCommand('copy')) {
                    copied();
                    return;
                }
            } catch (error) {}
            copyFailed();
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(copied).catch(legacyCopy);
            return;
        }

        legacyCopy();
    }

    ready(function () {
        var inspector = document.getElementById('ucp-asset-inspector');
        if (!inspector) {
            return;
        }

        var search = inspector.querySelector('[data-ucp-inspector-search]');
        var risk = inspector.querySelector('[data-ucp-inspector-risk]');
        var status = inspector.querySelector('[data-ucp-inspector-status]');
        var rows = Array.prototype.slice.call(inspector.querySelectorAll('[data-ucp-inspector-row]'));
        var selects = Array.prototype.slice.call(inspector.querySelectorAll('[data-ucp-inspector-select]'));
        var previousFocus = document.activeElement;

        function setStatus(text) {
            if (status) {
                status.textContent = text;
            }
        }

        function visible(row) {
            return !row.hidden;
        }

        function updateCounts() {
            Array.prototype.forEach.call(inspector.querySelectorAll('[data-ucp-inspector-group]'), function (group) {
                var groupRows = Array.prototype.slice.call(group.querySelectorAll('[data-ucp-inspector-row]'));
                var count = groupRows.filter(visible).length;
                var node = group.querySelector('[data-ucp-inspector-visible-count]');
                if (node) {
                    node.textContent = count + ' ' + message('visible', 'zichtbaar');
                }
            });
        }

        function updateRules() {
            Array.prototype.forEach.call(inspector.querySelectorAll('[data-ucp-inspector-rules]'), function (textarea) {
                var kind = textarea.getAttribute('data-kind');
                var path = textarea.getAttribute('data-path') || '/';
                var handles = selects.filter(function (checkbox) {
                    return checkbox.checked && checkbox.getAttribute('data-kind') === kind;
                }).map(function (checkbox) {
                    return path + ' => ' + checkbox.getAttribute('data-handle');
                });
                textarea.value = handles.join('\n');
                var copyButton = inspector.querySelector('[data-ucp-inspector-copy][data-kind="' + kind + '"]');
                if (copyButton) {
                    var hasRules = handles.length > 0;
                    copyButton.hidden = !hasRules;
                    copyButton.disabled = !hasRules;
                    copyButton.setAttribute('aria-hidden', hasRules ? 'false' : 'true');
                }
            });
        }

        function applyFilter() {
            var query = search ? search.value.trim().toLowerCase() : '';
            var selectedRisk = risk ? risk.value : 'all';
            rows.forEach(function (row) {
                var matchesQuery = !query || (row.getAttribute('data-search') || '').toLowerCase().indexOf(query) !== -1;
                var matchesRisk = 'all' === selectedRisk || row.getAttribute('data-risk') === selectedRisk;
                row.hidden = !(matchesQuery && matchesRisk);
            });
            updateCounts();
        }

        if (search) {
            search.addEventListener('input', applyFilter);
        }
        if (risk) {
            risk.addEventListener('change', applyFilter);
        }
        selects.forEach(function (checkbox) {
            checkbox.addEventListener('change', updateRules);
        });

        var selectCandidates = inspector.querySelector('[data-ucp-inspector-select-candidates]');
        if (selectCandidates) {
            selectCandidates.addEventListener('click', function () {
                selects.forEach(function (checkbox) {
                    var row = checkbox.closest('[data-ucp-inspector-row]');
                    if (!checkbox.disabled && row && visible(row) && 'candidate' === row.getAttribute('data-risk')) {
                        checkbox.checked = true;
                    }
                });
                updateRules();
                setStatus(message('candidatesSelected', 'Zichtbare kandidaten geselecteerd. Test de regels eerst in testmodus.'));
            });
        }

        var clear = inspector.querySelector('[data-ucp-inspector-clear]');
        if (clear) {
            clear.addEventListener('click', function () {
                selects.forEach(function (checkbox) {
                    checkbox.checked = false;
                });
                updateRules();
                setStatus(message('selectionCleared', 'Selectie gewist.'));
            });
        }

        Array.prototype.forEach.call(inspector.querySelectorAll('[data-ucp-inspector-copy]'), function (button) {
            button.addEventListener('click', function () {
                var kind = button.getAttribute('data-kind');
                copyText(inspector.querySelector('[data-ucp-inspector-rules][data-kind="' + kind + '"]'), status);
            });
        });

        function closeInspector() {
            inspector.remove();
            if (previousFocus && 'function' === typeof previousFocus.focus) {
                previousFocus.focus();
            }
        }

        var close = inspector.querySelector('[data-ucp-inspector-close]');
        if (close) {
            close.addEventListener('click', closeInspector);
        }
        inspector.addEventListener('keydown', function (event) {
            if ('Escape' === event.key) {
                event.preventDefault();
                closeInspector();
            }
        });

        applyFilter();
        updateRules();
        if (search) {
            search.focus();
        }
    });
}());
