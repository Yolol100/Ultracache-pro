(function (wp) {
    'use strict';

    if (!wp || !wp.element || !wp.components || !wp.apiFetch || !wp.i18n) {
        return;
    }

    var el = wp.element.createElement;
    var render = wp.element.render;
    var __ = wp.i18n.__;
    var apiFetch = wp.apiFetch;
    var Card = wp.components.Card;
    var CardHeader = wp.components.CardHeader;
    var CardBody = wp.components.CardBody;
    var Notice = wp.components.Notice;
    var config = window.UCP_ADMIN_CONFIG || {};
    var mountId = 'ucp-optimization-center-panel-root';

    function request(path) {
        var cleanPath = String(path || '').replace(/^\/+/, '');
        var baseUrl = String(config.restUrl || '').replace(/\/?$/, '/');
        return apiFetch(baseUrl ? {url: baseUrl + cleanPath} : {path: '/ultracache-pro/v1/' + cleanPath});
    }

    function badgeState(state) {
        state = String(state || '').toLowerCase();
        if (state === 'active' || state === 'success' || state === 'valid') return 'good';
        if (state === 'failed' || state === 'error' || state === 'fallback' || state === 'rollback') return 'warning';
        return 'info';
    }

    function Badge(props) {
        return el('span', {className: 'ucp-status-badge ucp-status-badge--' + badgeState(props.state)}, props.label || props.state || __('Status', 'ultracache-pro'));
    }

    function FeatureGrid(props) {
        var features = props.features || {};
        var keys = Object.keys(features).slice(0, 9);
        if (!keys.length) {
            return el(Notice, {status: 'info', isDismissible: false}, __('Nog geen Optimization Center status beschikbaar.', 'ultracache-pro'));
        }
        return el('div', {className: 'ucp-queue-status-grid ucp-optimization-center-grid'}, keys.map(function (key) {
            var item = features[key] || {};
            return el('div', {key: key, className: 'ucp-status-row'},
                el('span', {}, item.label || key),
                el('strong', {}, el(Badge, {state: item.state, label: item.summary || item.state}))
            );
        }));
    }

    function QueueSummary(props) {
        var queue = props.queue || {};
        return el('div', {className: 'ucp-queue-status-grid', style: {marginTop: '12px'}},
            el('div', {className: 'ucp-status-row'}, el('span', {}, __('Pending', 'ultracache-pro')), el('strong', {}, String(queue.pending || 0))),
            el('div', {className: 'ucp-status-row'}, el('span', {}, __('Running', 'ultracache-pro')), el('strong', {}, String(queue.running || 0))),
            el('div', {className: 'ucp-status-row'}, el('span', {}, __('Retrying', 'ultracache-pro')), el('strong', {}, String(queue.retrying || 0))),
            el('div', {className: 'ucp-status-row'}, el('span', {}, __('Failed', 'ultracache-pro')), el('strong', {}, String(queue.failed || 0)))
        );
    }

    function EngineQueue(props) {
        var center = props.center || {};
        var engines = center.engines || {};
        var lifecycle = (center.lifecycle && center.lifecycle.features) || {};
        var rows = [
            ['preload', __('Preload', 'ultracache-pro'), lifecycle.preload],
            ['usedCss', __('Used CSS', 'ultracache-pro'), lifecycle.usedCss || engines.usedCss],
            ['criticalCss', __('Critical CSS', 'ultracache-pro'), lifecycle.criticalCss || engines.criticalCss],
            ['delayJs', __('Delay JS', 'ultracache-pro'), lifecycle.delayJs || engines.delayJs],
            ['imageOptimize', __('Images', 'ultracache-pro'), lifecycle.imageOptimize],
            ['restCache', __('REST Cache', 'ultracache-pro'), lifecycle.restCache]
        ];
        return el('div', {style: {marginTop: '14px'}},
            el('h3', {}, __('Queue Monitor per engine', 'ultracache-pro')),
            el('div', {className: 'ucp-queue-status-grid'}, rows.map(function (row) {
                var item = row[2] || {};
                var state = item.state || item.status || (item.enabled ? 'active' : 'skipped');
                var label = item.summary || state;
                return el('div', {key: row[0], className: 'ucp-status-row'},
                    el('span', {}, row[1]),
                    el('strong', {}, el(Badge, {state: state, label: label}))
                );
            }))
        );
    }

    function ScriptManagerSummary(props) {
        var scriptManager = props.scriptManager || {};
        var fields = scriptManager.fields || {};
        var total = scriptManager.total || 0;
        return el('div', {style: {marginTop: '14px'}},
            el('h3', {}, __('Script Manager', 'ultracache-pro')),
            total ? el('div', {className: 'ucp-queue-status-grid'},
                el('div', {className: 'ucp-status-row'}, el('span', {}, __('Regels totaal', 'ultracache-pro')), el('strong', {}, String(total))),
                el('div', {className: 'ucp-status-row'}, el('span', {}, __('Disabled styles', 'ultracache-pro')), el('strong', {}, String(fields.disabled_style_handles || 0))),
                el('div', {className: 'ucp-status-row'}, el('span', {}, __('Disabled scripts', 'ultracache-pro')), el('strong', {}, String(fields.disabled_script_handles || 0))),
                el('div', {className: 'ucp-status-row'}, el('span', {}, __('Advanced rules', 'ultracache-pro')), el('strong', {}, String(fields.advanced_asset_rules || 0)))
            ) : el(Notice, {status: 'info', isDismissible: false}, __('Nog geen Script Manager regels actief.', 'ultracache-pro'))
        );
    }

    function ArtifactBrowser(props) {
        var artifacts = props.artifacts || {};
        var summary = artifacts.summary || {};
        var items = Array.isArray(artifacts.items) ? artifacts.items.slice(-6).reverse() : [];
        if (!summary.total && !items.length) {
            return el('div', {style: {marginTop: '14px'}},
                el('h3', {}, __('Artifact Browser', 'ultracache-pro')),
                el(Notice, {status: 'info', isDismissible: false}, __('Nog geen Used CSS/Critical CSS artifacts gevonden.', 'ultracache-pro'))
            );
        }
        return el('div', {style: {marginTop: '14px'}},
            el('h3', {}, __('Artifact Browser', 'ultracache-pro')),
            el('div', {className: 'ucp-queue-status-grid'},
                el('div', {className: 'ucp-status-row'}, el('span', {}, __('Totaal', 'ultracache-pro')), el('strong', {}, String(summary.total || 0))),
                el('div', {className: 'ucp-status-row'}, el('span', {}, __('Actief', 'ultracache-pro')), el('strong', {}, String(summary.active || 0))),
                el('div', {className: 'ucp-status-row'}, el('span', {}, __('Processing', 'ultracache-pro')), el('strong', {}, String(summary.processing || 0))),
                el('div', {className: 'ucp-status-row'}, el('span', {}, __('Failed', 'ultracache-pro')), el('strong', {}, String(summary.failed || 0)))
            ),
            items.length ? el('div', {style: {marginTop: '10px'}}, items.map(function (item, index) {
                var status = item && item.status ? String(item.status) : 'pending';
                var label = item && (item.url || item.path || item.key || item.type) ? String(item.url || item.path || item.key || item.type) : __('Artifact', 'ultracache-pro') + ' #' + (index + 1);
                var attempts = item && item.attempts ? String(item.attempts) : '0';
                return el('div', {key: index, className: 'ucp-status-row'},
                    el('span', {title: label}, label.length > 54 ? label.slice(0, 54) + '…' : label),
                    el('strong', {}, el(Badge, {state: status, label: status + ' / ' + attempts + 'x'}))
                );
            })) : null
        );
    }

    function Panel(props) {
        var center = props.center || {};
        var lifecycle = center.lifecycle || {};
        var features = lifecycle.features || {};
        var queue = (center.summary && center.summary.queue) || lifecycle.queue || {};

        return el(Card, {className: 'ucp-card ucp-optimization-center-card'},
            el(CardHeader, {},
                el('h2', {}, __('Optimization Center', 'ultracache-pro')),
                el(Badge, {state: center.summary && center.summary.testingMode ? 'pending' : 'active', label: center.summary && center.summary.testingMode ? __('Testmodus', 'ultracache-pro') : __('Actief', 'ultracache-pro')})
            ),
            el(CardBody, {},
                center.summary && center.summary.message ? el('p', {className: 'ucp-muted'}, center.summary.message) : null,
                el(FeatureGrid, {features: features}),
                el(QueueSummary, {queue: queue}),
                el(EngineQueue, {center: center}),
                el(ArtifactBrowser, {artifacts: center.artifacts || {}}),
                el(ScriptManagerSummary, {scriptManager: center.scriptManager || {}})
            )
        );
    }

    function mountPanel() {
        var dashboard = document.querySelector('.ucp-page--dashboard');
        if (!dashboard || document.getElementById(mountId)) {
            return;
        }
        var mount = document.createElement('div');
        mount.id = mountId;
        var hero = dashboard.querySelector('.ucp-dashboard-hero');
        if (hero && hero.parentNode) {
            hero.parentNode.insertBefore(mount, hero.nextSibling);
        } else {
            dashboard.insertBefore(mount, dashboard.firstChild);
        }
        request('optimization-center').then(function (response) {
            render(el(Panel, {center: response && response.center ? response.center : {}}), mount);
        }).catch(function () {
            render(el(Card, {className: 'ucp-card'}, el(CardHeader, {}, el('h2', {}, __('Optimization Center', 'ultracache-pro'))), el(CardBody, {}, el(Notice, {status: 'warning', isDismissible: false}, __('Optimization Center kon niet geladen worden.', 'ultracache-pro')))), mount);
        });
    }

    window.setTimeout(mountPanel, 700);
    document.addEventListener('click', function () {
        window.setTimeout(mountPanel, 300);
    });
})(window.wp);
