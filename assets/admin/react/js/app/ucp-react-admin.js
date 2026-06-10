(function (wp) {
    'use strict';

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var createRoot = wp.element.createRoot;
    var render = wp.element.render;
    var __ = wp.i18n.__;
    var sprintf = wp.i18n.sprintf || function (format) {
        var args = Array.prototype.slice.call(arguments, 1);
        var index = 0;
        return String(format || '').replace(/%([0-9]+\$)?[sd]/g, function () {
            var value = args[index++];
            return typeof value === 'undefined' ? '' : String(value);
        });
    };
    var apiFetch = wp.apiFetch;
    var c = wp.components;
    var Card = c.Card, CardHeader = c.CardHeader, CardBody = c.CardBody;
    var Button = c.Button, Notice = c.Notice;
    var ToggleControl = c.ToggleControl, TextControl = c.TextControl, TextareaControl = c.TextareaControl;
    var SelectControl = c.SelectControl;
    var CheckboxControl = c.CheckboxControl;
    var NumberControl = c.__experimentalNumberControl || c.NumberControl || TextControl;
    var config = window.UCP_ADMIN_CONFIG || {};
    var managedSettingKeys = Array.isArray(config.managedSettingKeys) ? config.managedSettingKeys : [];
    function isManagedSetting(key) {
        return managedSettingKeys.indexOf(String(key || '')) !== -1;
    }

    if (apiFetch && apiFetch.use && config.nonce) {
        apiFetch.use(apiFetch.createNonceMiddleware(config.nonce));
    }

    var tabs = [
        {key:'dashboard', label:__('Dashboard','ultracache-pro'), icon:'dashicons-dashboard'},
        {key:'cache', label:__('Cache','ultracache-pro'), icon:'dashicons-admin-generic'},
        {key:'optimization', label:__('Bestandsoptimalisatie','ultracache-pro'), icon:'dashicons-performance'},
        {key:'media', label:__('Media','ultracache-pro'), icon:'dashicons-format-image'},
        {key:'preload', label:__('Preloaden','ultracache-pro'), icon:'dashicons-controls-repeat'},
        {key:'advanced', label:__('Regels','ultracache-pro'), icon:'dashicons-list-view'},
        {key:'database', label:__('Database','ultracache-pro'), icon:'dashicons-database'},
        {key:'tools', label:__('Tools','ultracache-pro'), icon:'dashicons-admin-tools'}
    ];

    // In simple mode only the Dashboard is shown: it already carries the speed presets, status,
    // CWV data and recommendations, so a normal customer never needs the technical tabs. Power
    // users flip to advanced mode (persisted as ui_mode) to reveal the full set above.
    var SIMPLE_TABS = ['dashboard'];

    function visibleTabsForMode(uiMode) {
        if (uiMode === 'advanced') return tabs;
        return tabs.filter(function(tab){ return SIMPLE_TABS.indexOf(tab.key) !== -1; });
    }

    function normalizeTabKey(tab) {
        var key = String(tab || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
        var map = {
            overview:'dashboard', dashboard:'dashboard',
            diagnostics:'tools', toolbox:'tools', integrations:'tools', addons:'tools',
            expert:'advanced', assets:'optimization', assetmanager:'optimization', 'asset-manager':'optimization', cdn:'advanced', 'advanced-rules':'advanced', advanced_rules:'advanced',
            heartbeat:'advanced',
            'ultracache-pro':'dashboard',
            'ultracache-pro-cache':'cache',
            'ultracache-pro-file-optimization':'optimization',
            'ultracache-pro-media':'media',
            'ultracache-pro-preload':'preload',
            'ultracache-pro-assets':'optimization',
            'ultracache-pro-advanced-rules':'advanced',
            'ultracache-pro-assets-manager':'optimization',
            'ultracache-pro-asset-manager':'optimization',
            'ultracache-pro-asset-cleanup':'optimization',
            'ultracache-pro-database':'database',
            'ultracache-pro-cdn':'advanced',
            'ultracache-pro-heartbeat':'advanced',
            'ultracache-pro-addons':'tools',
            'ultracache-pro-tools':'tools',
            'ultracache-pro-toolbox':'tools',
            'ultracache-pro-integrations':'tools'
        };
        key = map[key] || key;
        return tabs.some(function (tabMeta) { return tabMeta.key === key; }) ? key : 'dashboard';
    }

    function initialTabFromLocation() {
        try {
            var params = new URLSearchParams(window.location.search || '');
            return normalizeTabKey(params.get('tab') || params.get('page') || 'dashboard');
        } catch (e) {
            return 'dashboard';
        }
    }

    function slugify(text) {
        return String(text || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'item';
    }
    function storageGet(key, fallback) {
        try {
            var raw = window.localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (e) {
            return fallback;
        }
    }
    function storageSet(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {}
    }
    function normalizeOrder(savedOrder, ids) {
        var safeIds = Array.isArray(ids) ? ids.slice() : [];
        if (!Array.isArray(savedOrder) || !savedOrder.length) {
            return safeIds;
        }
        var seen = {};
        var ordered = [];
        savedOrder.forEach(function(id){
            if (safeIds.indexOf(id) !== -1 && !seen[id]) {
                seen[id] = true;
                ordered.push(id);
            }
        });
        safeIds.forEach(function(id){
            if (!seen[id]) {
                ordered.push(id);
            }
        });
        return ordered;
    }
    function moveIdBefore(list, draggedId, targetId) {
        if (!draggedId || !targetId || draggedId === targetId) {
            return list.slice();
        }
        var next = list.filter(function(id){ return id !== draggedId; });
        var targetIndex = next.indexOf(targetId);
        if (targetIndex === -1) {
            next.push(draggedId);
            return next;
        }
        next.splice(targetIndex, 0, draggedId);
        return next;
    }
    function moveIdAfter(list, draggedId, targetId) {
        if (!draggedId || !targetId || draggedId === targetId) {
            return list.slice();
        }
        var next = list.filter(function(id){ return id !== draggedId; });
        var targetIndex = next.indexOf(targetId);
        if (targetIndex === -1) {
            next.push(draggedId);
            return next;
        }
        next.splice(targetIndex + 1, 0, draggedId);
        return next;
    }
    function keyboardReorder(event, currentId, order, updateOrder) {
        var key = event.key || '';
        if (['ArrowUp', 'ArrowLeft', 'ArrowDown', 'ArrowRight', 'Home', 'End'].indexOf(key) === -1) {
            return;
        }
        event.preventDefault();
        var currentIndex = order.indexOf(currentId);
        if (currentIndex === -1) {
            return;
        }
        if ('Home' === key) {
            updateOrder(moveIdBefore(order, currentId, order[0]));
        } else if ('End' === key) {
            updateOrder(moveIdAfter(order, currentId, order[order.length - 1]));
        } else if (('ArrowUp' === key || 'ArrowLeft' === key) && currentIndex > 0) {
            updateOrder(moveIdBefore(order, currentId, order[currentIndex - 1]));
        } else if (('ArrowDown' === key || 'ArrowRight' === key) && currentIndex < order.length - 1) {
            updateOrder(moveIdAfter(order, currentId, order[currentIndex + 1]));
        }
    }
    function isCompactSettingField(field, tabKind) {
        var key = String(field && field[0] || '');
        var label = String(field && field[1] || '');
        var type = String(field && field[2] || '');
        var help = String(field && field[3] || '');
        var compactTypes = {toggle:true, number:true, select:true, text:true};
        if (!compactTypes[type]) return false;
        if (type === 'text' && /(exclude|exclusion|urls|uri|scope|include|safelist|pattern|agent|param|path|regels|rule)/i.test(key + ' ' + label)) return false;
        if (help.length > 135) return false;
        if (label.length > 44) return false;
        if (tabKind === 'database' && /(all_transients|optimize_tables|trashed|spam|revisions)/i.test(key)) return false;
        if (/(delivery|combine|delay|critical|used_css|cleanup|delete|remove|purge|unsafe|risk)/i.test(key) && help.length > 78) return false;
        return true;
    }

    function LayoutToolbar(props) {
        var columns = parseInt(props.columns || 1, 10) || 1;
        var options = props.options || [1, 2, 3, 4];
        return el('div', {className:'ucp-layout-toolbar'},
            el('div', {className:'ucp-layout-toolbar__title'},
                el('strong', {}, props.title || __('Indeling','ultracache-pro')),
                props.help ? el('span', {}, props.help) : null
            ),
            el('div', {className:'ucp-layout-toolbar__controls', role:'toolbar', 'aria-label':props.title || __('Indeling','ultracache-pro')},
                options.map(function(option){
                    return el(Button, {
                        key:option,
                        variant:columns === option ? 'primary' : 'secondary',
                        onClick:function(){ props.onChange(option); }
                    }, option + ' ' + (option === 1 ? __('kolom','ultracache-pro') : __('kolommen','ultracache-pro')));
                }),
                props.onReset ? el(Button, {variant:'tertiary', onClick:props.onReset}, __('Herstel indeling','ultracache-pro')) : null
            )
        );
    }

    function request(path, options) {
        var cleanPath = String(path || "").replace(/^\/+/, "");
        var baseUrl = String(config.restUrl || "").replace(/\/?$/, "/");
        if (baseUrl) {
            return apiFetch(Object.assign({url: baseUrl + cleanPath}, options || {}));
        }
        return apiFetch(Object.assign({path:"/ultracache-pro/v1/" + cleanPath}, options || {}));
    }
    function cleanErrorMessage(err, fallback) {
        var raw = err && err.message ? String(err.message) : String(fallback || 'Actie mislukt.');
        raw = raw.replace(/<\/?p>/g, ' ').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        if (!raw || raw === 'null' || raw === 'undefined') {
            return fallback || 'Actie mislukt.';
        }
        return raw;
    }
    function getStatus(){ return request('status'); }
    function getSettings(){ return request('settings'); }
    function saveSettings(settings){ return request('settings', {method:'POST', data:settings}); }
    function saveBulk(settings){ return request('settings/bulk', {method:'POST', data:settings}); }
    function runAction(action, data){ return request('actions/' + action, {method:'POST', data:data || {}}); }
    function getAssetSnapshot(){ return request('diagnostics/asset-snapshot'); }
    function scanPreset(){ return request('scan-preset'); }
    function exportSettings(){ return request('settings/export'); }
    function importSettings(payload){ return request('settings/import', {method:'POST', data:{settings:payload, confirmBackup:true}}); }

    function NoticeArea(props) {
        return el('div', {className:'ucp-notice-area', 'aria-live':'polite', 'aria-relevant':'additions removals'}, props.notices.map(function(n){
            var status = n.status || 'info';
            var icon = status === 'error' ? '!' : (status === 'warning' ? '!' : '✓');
            return el('div', {key:n.id, className:'ucp-react-toast ucp-react-toast--' + status, role:status === 'error' ? 'alert' : 'status'},
                el('span', {className:'ucp-react-toast__icon', 'aria-hidden':'true'}, icon),
                el('span', {className:'ucp-react-toast__message'}, n.message),
                el('button', {type:'button', className:'ucp-react-toast__close', onClick:function(){props.onRemove(n.id);}, 'aria-label':__('Melding sluiten','ultracache-pro')}, '×')
            );
        }));
    }

    function StatusBadge(props) {
        var rawState = String(props.state || 'info');
        var aliases = {success:'good', danger:'error', blocked:'error', enabled:'good', disabled:'info', ready:'good', clear:'good', review:'warning'};
        var state = aliases[rawState] || rawState;
        if (['good','warning','info','error'].indexOf(state) === -1) {
            state = 'info';
        }
        var label = props.label || props.children || rawState;
        return el('span', {className:'ucp-status-badge ucp-status-badge--' + state, 'aria-label':String(label)}, props.children || props.label);
    }

    function ProductHeader(props) {
        var status = props.status || {};
        var cache = status.cache || {};
        var queue = status.queue || {};
        var pending = (parseInt(queue.pending || 0, 10) || 0) + (parseInt(queue.retrying || 0, 10) || 0);
        var titleId = 'ucp-admin-page-title';
        return el('header', {className:'ucp-app-header ucp-app-header--native ucp-app-header--compact', 'aria-labelledby':titleId},
            el('div', {className:'ucp-app-header__main'},
                el('p', {className:'ucp-eyebrow'}, __('WordPress performance','ultracache-pro')),
                el('h1', {id:titleId}, __('UltraCache Pro','ultracache-pro')),
                el('p', {}, __('Native WordPress-beheer voor cache, optimalisatie, preload en websitecontrole.','ultracache-pro'))
            ),
            el('div', {className:'ucp-app-header__side', 'aria-label':__('Belangrijkste status','ultracache-pro')},
                el('div', {className:'ucp-app-header__status'},
                    cache.enabled ? el(StatusBadge,{state:'good', label:__('Cache actief','ultracache-pro')},__('Cache actief','ultracache-pro')) : el(StatusBadge,{state:'warning', label:__('Cache niet actief','ultracache-pro')},__('Cache niet actief','ultracache-pro')),
                    pending ? el(StatusBadge,{state:'info', label:pending + ' ' + __('taken in wachtrij','ultracache-pro')}, pending + ' ' + __('taken','ultracache-pro')) : el(StatusBadge,{state:'good', label:__('Wachtrij rustig','ultracache-pro')},__('Wachtrij rustig','ultracache-pro')),
                    cache.wooSafety ? el(StatusBadge,{state:'good', label:__('WooCommerce safety mode actief','ultracache-pro')},__('Woo safety','ultracache-pro')) : null
                )
            )
        );
    }

    function AdminShell(props) {
        var activeTab = props.activeTab || 'dashboard';
        var uiMode = props.uiMode === 'advanced' ? 'advanced' : 'simple';
        var navTabs = visibleTabsForMode(uiMode);
        var activeMeta = navTabs.filter(function(tab){ return tab.key === activeTab; })[0] || navTabs[0] || tabs[0];
        function focusTab(key) {
            window.setTimeout(function(){
                var node = document.getElementById('ucp-admin-tab-' + key);
                if (node && node.focus) node.focus();
            }, 0);
        }
        function onTabKeyDown(event, tab) {
            var key = event.key || '';
            if (['ArrowLeft','ArrowUp','ArrowRight','ArrowDown','Home','End'].indexOf(key) === -1) return;
            event.preventDefault();
            var index = navTabs.map(function(item){ return item.key; }).indexOf(tab.key);
            if (index === -1) return;
            var nextIndex = index;
            if (key === 'Home') nextIndex = 0;
            else if (key === 'End') nextIndex = navTabs.length - 1;
            else if (key === 'ArrowLeft' || key === 'ArrowUp') nextIndex = (index - 1 + navTabs.length) % navTabs.length;
            else if (key === 'ArrowRight' || key === 'ArrowDown') nextIndex = (index + 1) % navTabs.length;
            var nextKey = navTabs[nextIndex].key;
            props.onTab(nextKey);
            focusTab(nextKey);
        }
        function modeToggle() {
            if (!props.onToggleMode) return null;
            var toAdvanced = uiMode !== 'advanced';
            return el('button', {
                type:'button',
                className:'ucp-admin-mode-toggle',
                onClick:function(){ props.onToggleMode(toAdvanced ? 'advanced' : 'simple'); }
            },
                el('span', {className:'dashicons ' + (toAdvanced ? 'dashicons-admin-settings' : 'dashicons-arrow-left-alt2'), 'aria-hidden':'true'}),
                el('span', {}, toAdvanced ? __('Geavanceerde instellingen','ultracache-pro') : __('Eenvoudige weergave','ultracache-pro'))
            );
        }
        return el('div', {className:'ucp-admin-app__shell ucp-admin-app__shell--' + uiMode},
            uiMode === 'advanced'
                ? el('nav', {className:'ucp-admin-nav', role:'tablist', 'aria-label':__('UltraCache Pro onderdelen','ultracache-pro')}, navTabs.map(function(tab){
                    var active = activeTab === tab.key;
                    var tabId = 'ucp-admin-tab-' + tab.key;
                    return el('button', {
                        key:tab.key,
                        id:tabId,
                        type:'button',
                        role:'tab',
                        className:'ucp-admin-nav__item' + (active ? ' is-active' : ''),
                        'aria-selected':active ? 'true' : 'false',
                        'aria-controls':'ucp-admin-panel',
                        tabIndex:active ? 0 : -1,
                        onClick:function(){props.onTab(tab.key);},
                        onKeyDown:function(event){ onTabKeyDown(event, tab); }
                    },
                        el('span', {className:'dashicons ' + tab.icon, 'aria-hidden':'true'}),
                        el('span', {className:'ucp-admin-nav__text'},
                            el('strong', {className:'ucp-admin-nav__label'}, tab.label)
                        )
                    );
                }).concat([el('div', {key:'__ucp_mode', className:'ucp-admin-nav__mode'}, modeToggle())]))
                : el('div', {className:'ucp-admin-simplebar'}, modeToggle()),
            el('section', uiMode === 'advanced'
                ? {id:'ucp-admin-panel', className:'ucp-admin-panel', role:'tabpanel', 'aria-labelledby':'ucp-admin-tab-' + (activeMeta ? activeMeta.key : 'dashboard')}
                : {id:'ucp-admin-panel', className:'ucp-admin-panel', role:'region', 'aria-labelledby':'ucp-admin-page-title'}, props.children)
        );
    }

    function DashboardHero(props) {
        var status = props.status || {};
        var cache = status.cache || {}, queue = status.queue || {}, runner = queue.runner || {}, opt = status.optimization || {};
        var totalWaiting = (parseInt(queue.pending || 0, 10) || 0) + (parseInt(queue.retrying || 0, 10) || 0);
        var title = cache.enabled ? __('Cache is actief','ultracache-pro') : __('Cache vraagt aandacht','ultracache-pro');
        var help = cache.enabled ? __('De basis staat goed. Controleer alleen de frontend na CSS- of JavaScript-wijzigingen.','ultracache-pro') : __('Kies bovenaan een preset of pas de aanbevolen slimme scan toe om veilig te starten.','ultracache-pro');
        if (totalWaiting > 0 && !runner.due) {
            help = __('Er staan achtergrondtaken gepland voor een volgende poging. Dit is normaal na CSS/preload-acties; gebruik Diagnostiek als dit blijft oplopen.','ultracache-pro');
        }
        return el(Card, {className:'ucp-card ucp-dashboard-hero'},
            el(CardBody, {},
                el('div', {className:'ucp-hero-main'},
                    el('div', {},
                        el('p', {className:'ucp-eyebrow'}, __('Overzicht','ultracache-pro')),
                        el('h2', {}, title),
                        el('p', {className:'ucp-muted'}, help)
                    ),
                    el('div', {className:'ucp-hero-actions'},
                        el(ActionButton, {action:'run-due-jobs', label:__('Verwerk taken nu','ultracache-pro'), compact:true, variant:'secondary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                    )
                ),
                el('div', {className:'ucp-hero-metrics'},
                    el('div', {}, el('span', {}, __('Cache','ultracache-pro')), cache.enabled ? el(StatusBadge,{state:'good'},__('Actief','ultracache-pro')) : el(StatusBadge,{state:'warning'},__('Niet actief','ultracache-pro'))),
                    el('div', {}, el('span', {}, __('Optimalisatie','ultracache-pro')), (opt.cssMinify || opt.jsMinify || opt.lazyImages) ? el(StatusBadge,{state:'good'},__('Ingesteld','ultracache-pro')) : el(StatusBadge,{state:'info'},__('Basis','ultracache-pro'))),
                    el('div', {}, el('span', {}, __('Queue','ultracache-pro')), totalWaiting ? el(StatusBadge,{state:'warning'}, totalWaiting + ' ' + __('wachtend','ultracache-pro')) : el(StatusBadge,{state:'good'},__('Rustig','ultracache-pro'))),
                    el('div', {}, el('span', {}, __('WooCommerce','ultracache-pro')), cache.wooSafety ? el(StatusBadge,{state:'good'},__('Veilig','ultracache-pro')) : el(StatusBadge,{state:'info'},__('Standaard','ultracache-pro')))
                )
            )
        );
    }


    function ProductReadinessCard(props) {
        var status = props.status || {}, proof = status.proof || {}, autopilot = status.autopilot || {}, conflicts = status.conflictGuard || {};
        var items = [
            {label:__('Cache bewijs','ultracache-pro'), value:(proof.cache && proof.cache.enabled ? (proof.cache.cachedPages || 0) + ' ' + __('pagina’s','ultracache-pro') : __('Uit','ultracache-pro')), state:(proof.cache && proof.cache.enabled ? 'good' : 'warning')},
            {label:__('CWV-fielddata','ultracache-pro'), value:(proof.fieldData && proof.fieldData.hasSamples ? __('Samples beschikbaar','ultracache-pro') : (proof.fieldData && proof.fieldData.enabled ? __('Wacht op data','ultracache-pro') : __('Uit','ultracache-pro'))), state:(proof.fieldData && proof.fieldData.hasSamples ? 'good' : 'info')},
            {label:__('CSS-renderer','ultracache-pro'), value:(proof.cssArtifacts && proof.cssArtifacts.rendererState ? proof.cssArtifacts.rendererState : 'off'), state:(proof.cssArtifacts && proof.cssArtifacts.rendererState === 'ready' ? 'good' : 'info')},
            {label:__('Afbeeldingen','ultracache-pro'), value:(proof.images && proof.images.mode ? proof.images.mode : 'off'), state:(proof.images && proof.images.queueFailed ? 'warning' : 'info')},
            {label:__('Conflict Guard','ultracache-pro'), value:(proof.conflicts && proof.conflicts.count ? proof.conflicts.count + ' ' + __('overlap','ultracache-pro') : __('Geen overlap','ultracache-pro')), state:(proof.conflicts && proof.conflicts.count ? 'warning' : 'good')}
        ];
        return el(Card, {className:'ucp-card ucp-product-readiness-card'},
            el(CardHeader, {},
                el('h2', {}, __('Performance bewijsdashboard','ultracache-pro')),
                autopilot.stagingRecommended ? el(StatusBadge,{state:'warning'},__('Staging-first','ultracache-pro')) : el(StatusBadge,{state:'good'},__('Productieklaar basisbeeld','ultracache-pro'))
            ),
            el(CardBody, {},
                el('div', {className:'ucp-queue-status-grid'}, items.map(function(item){
                    return el('div', {className:'ucp-status-row', key:item.label}, el('span',{}, item.label), el(StatusBadge,{state:item.state}, item.value));
                })),
                conflicts.summary ? el('p', {className:'ucp-muted'}, conflicts.summary) : null,
                autopilot.nextStep ? el('p', {className:'ucp-muted'}, autopilot.nextStep) : null
            )
        );
    }

    function RendererPipelineCard(props) {
        var opt = (props.status && props.status.optimization) || {};
        var readiness = opt.rendererReadiness || {};
        var image = opt.imagePipeline || {};
        var checklist = readiness.checklist || [];
        var state = readiness.state === 'ready' ? 'good' : (readiness.state === 'needs_setup' || readiness.state === 'warning' ? 'warning' : 'info');
        return el(Card, {className:'ucp-card ucp-renderer-pipeline-card'},
            el(CardHeader, {},
                el('h2', {}, __('Renderer en image pipeline','ultracache-pro')),
                el(StatusBadge, {state:state}, readiness.state || __('Uit','ultracache-pro'))
            ),
            el(CardBody, {},
                el('div', {className:'ucp-queue-status-grid'},
                    el('div', {className:'ucp-status-row'}, el('span',{},__('Renderer endpoint','ultracache-pro')), readiness.endpointSet ? el(StatusBadge,{state:'good'},__('Ingesteld','ultracache-pro')) : el(StatusBadge,{state:'info'},__('Niet ingesteld','ultracache-pro'))),
                    el('div', {className:'ucp-status-row'}, el('span',{},__('Renderer token','ultracache-pro')), readiness.tokenSet ? el(StatusBadge,{state:'good'},__('Ingesteld','ultracache-pro')) : el(StatusBadge,{state:'info'},__('Niet ingesteld','ultracache-pro'))),
                    el('div', {className:'ucp-status-row'}, el('span',{},__('Image modus','ultracache-pro')), el(StatusBadge,{state:image.mode && image.mode !== 'off' ? 'good' : 'info'}, image.mode || 'off')),
                    el('div', {className:'ucp-status-row'}, el('span',{},__('Image queue fouten','ultracache-pro')), image.queue && image.queue.failed ? el(StatusBadge,{state:'warning'}, String(image.queue.failed)) : el(StatusBadge,{state:'good'},'0'))
                ),
                checklist.length ? el('ul', {className:'ucp-checklist'}, checklist.map(function(item){
                    return el('li', {key:item.key, className:item.ok ? 'is-ok' : 'is-missing'}, item.ok ? '✓ ' : '• ', item.label);
                })) : null
            )
        );
    }

    function ConflictGuardCard(props) {
        var guard = (props.status && props.status.conflictGuard) || {};
        var matches = guard.matches || [];
        return el(Card, {className:'ucp-card ucp-conflict-guard-card'},
            el(CardHeader, {},
                el('h2', {}, __('Conflict Guard','ultracache-pro')),
                matches.length ? el(StatusBadge,{state:'warning'}, matches.length + ' ' + __('match(es)','ultracache-pro')) : el(StatusBadge,{state:'good'},__('Geen bekende overlap','ultracache-pro'))
            ),
            el(CardBody, {},
                el('p', {className:'ucp-muted'}, guard.summary || __('Controleert bekende performance-plugins en dubbele optimalisatielagen.','ultracache-pro')),
                matches.length ? el('div', {className:'ucp-action-list'}, matches.map(function(match, index){
                    return el('div', {className:'ucp-action-item', key:match.file || index},
                        el('strong', {}, match.plugin),
                        el('p', {className:'ucp-muted'}, __('Overlap: ','ultracache-pro') + (match.overlap || []).join(', ')),
                        el('p', {className:'ucp-muted'}, match.advice)
                    );
                })) : null
            )
        );
    }

    function SimpleModeQuickActions(props) {
        if (props.uiMode !== 'simple') { return null; }
        var actions = [
            {key:'cache', title:__('Cache controleren','ultracache-pro'), text:__('Controleer page cache, bewaartijd en webshopveilige uitzonderingen.','ultracache-pro')},
            {key:'advanced', title:__('Webshopveiligheid en regels','ultracache-pro'), text:__('Bekijk URL-, cookie- en query-regels voordat je agressiever cachet.','ultracache-pro')},
            {key:'tools', title:__('Tools en controles','ultracache-pro'), text:__('Leeg cache, start preload of voer een websitecontrole uit.','ultracache-pro')}
        ];
        function openAdvanced(tab){
            if (props.onToggleMode) { props.onToggleMode('advanced'); }
            window.setTimeout(function(){ if (props.onSelectTab) { props.onSelectTab(tab); } }, 0);
        }
        return el(Card, {className:'ucp-card ucp-simple-shortcuts'},
            el(CardHeader, {}, el('div', {className:'ucp-section-heading'}, el('h2', {}, __('Snelle controles','ultracache-pro')), el('p', {}, __('Eenvoudige modus toont de belangrijkste status. Gebruik deze veilige routes voor instellingen die extra aandacht vragen.','ultracache-pro')))),
            el(CardBody, {}, el('div', {className:'ucp-simple-shortcuts__grid'}, actions.map(function(item){
                return el('button', {key:item.key, type:'button', className:'ucp-simple-shortcut', onClick:function(){openAdvanced(item.key);}},
                    el('strong', {}, item.title),
                    el('span', {}, item.text)
                );
            })))
        );
    }

    function DashboardPage(props) {
        var status = props.status || {};
        return el('div', {className:'ucp-page ucp-page--dashboard'},
            el(QueueRunnerCard, Object.assign({}, props, {status:status})),
            el(DashboardHero, Object.assign({}, props, {status:status})),
            el(SimpleModeQuickActions, props),
            el(ProductReadinessCard, Object.assign({}, props, {status:status})),
            el(RendererPipelineCard, Object.assign({}, props, {status:status})),
            el(ConflictGuardCard, Object.assign({}, props, {status:status})),
            el(RumDashboardCard, Object.assign({}, props, {status:status})),
            el(PresetCards, Object.assign({}, props, {dashboard:true})),
            el(RecommendationCard, {status:status})
        );
    }

    function rumMetricLabel(metric) {
        return ({lcp:'LCP', inp:'INP', cls:'CLS', fcp:'FCP', ttfb:'TTFB'})[metric] || String(metric || '').toUpperCase();
    }

    function rumMetricState(metric, value) {
        value = parseFloat(value || 0);
        if (!value) return 'info';
        if (metric === 'cls') return value <= 0.1 ? 'good' : (value <= 0.25 ? 'warning' : 'error');
        if (metric === 'lcp') return value <= 2500 ? 'good' : (value <= 4000 ? 'warning' : 'error');
        if (metric === 'inp') return value <= 200 ? 'good' : (value <= 500 ? 'warning' : 'error');
        if (metric === 'fcp') return value <= 1800 ? 'good' : (value <= 3000 ? 'warning' : 'error');
        if (metric === 'ttfb') return value <= 800 ? 'good' : (value <= 1800 ? 'warning' : 'error');
        return 'info';
    }

    function rumMetricValue(metric, value) {
        if (value === null || typeof value === 'undefined' || value === '') return '-';
        value = parseFloat(value || 0);
        if (metric === 'cls') return value.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
        return Math.round(value) + ' ms';
    }

    function RumDashboardCard(props) {
        var status = props.status || {}, rum = status.rum || {}, vpi = status.vpi || {};
        var summary = rum.summary || {};
        var metrics = ['lcp','inp','cls','fcp','ttfb'];
        var devices = ['mobile','desktop','all'];
        var rows = [];
        metrics.forEach(function(metric){
            devices.forEach(function(device){
                var row = summary[metric] && summary[metric][device] ? summary[metric][device] : null;
                if (row && parseInt(row.samples || 0, 10) > 0) {
                    rows.push({metric:metric, device:device, samples:parseInt(row.samples || 0, 10), p75:row.p75, avg:row.avg});
                }
            });
        });
        var badgeState = rum.enabled ? (rows.length ? 'good' : 'info') : 'warning';
        var badgeText = rum.enabled ? (rows.length ? __('Data beschikbaar','ultracache-pro') : __('Wacht op samples','ultracache-pro')) : __('Uit','ultracache-pro');
        var vpiSummary = vpi.summary || {};
        return el(Card, {className:'ucp-card ucp-rum-dashboard-card'},
            el(CardHeader, {},
                el('h2', {}, __('CWV-fielddata en viewportbeelden','ultracache-pro')),
                el(StatusBadge, {state:badgeState}, badgeText)
            ),
            el(CardBody, {},
                el('div', {className:'ucp-queue-status-grid'},
                    el('div', {className:'ucp-status-row'}, el('span',{},__('CWV sample rate','ultracache-pro')), el('strong',{}, (rum.sampleRate || 10) + '%')),
                    el('div', {className:'ucp-status-row'}, el('span',{},__('VPI detectie','ultracache-pro')), vpi.preciseDetection ? el(StatusBadge,{state:'good'},__('Precies via headless','ultracache-pro')) : (vpi.enabled ? el(StatusBadge,{state:'info'},__('Heuristiek/fallback','ultracache-pro')) : el(StatusBadge,{state:'warning'},__('Uit','ultracache-pro')))),
                    el('div', {className:'ucp-status-row'}, el('span',{},__('VPI profielen','ultracache-pro')), el('strong',{}, String(vpiSummary.profiles || 0)))
                ),
                vpi.enabled && !vpi.headlessRenderer ? el(Notice, {status:'info', isDismissible:false}, __('VPI gebruikt pas precieze boven-de-vouwdetectie als de headless-renderer actief is. Zonder renderer blijft UltraCache terugvallen op de bestaande leading-image-heuristiek.','ultracache-pro')) : null,
                !rum.enabled ? el(Notice, {status:'warning', isDismissible:false}, __('CWV-monitoring staat uit. Zet Core Web Vitals monitoring aan bij Diagnostiek om lokale p75-fielddata op te bouwen.','ultracache-pro')) : null,
                rum.enabled && !rows.length ? el('p', {className:'ucp-muted'}, __('Nog geen CWV-samples. De collector slaat alleen metricwaarde en apparaatklasse lokaal op; er is geen per-gebruiker tracking.','ultracache-pro')) : null,
                rows.length ? el('div', {className:'ucp-rum-table-wrap'},
                    el('table', {className:'widefat striped ucp-rum-table'},
                        el('thead', {}, el('tr', {},
                            el('th', {}, __('Metric','ultracache-pro')),
                            el('th', {}, __('Device','ultracache-pro')),
                            el('th', {}, __('p75','ultracache-pro')),
                            el('th', {}, __('Gemiddeld','ultracache-pro')),
                            el('th', {}, __('Samples','ultracache-pro'))
                        )),
                        el('tbody', {}, rows.map(function(row){
                            return el('tr', {key:row.metric + '-' + row.device},
                                el('td', {}, el('strong', {}, rumMetricLabel(row.metric))),
                                el('td', {}, row.device),
                                el('td', {}, el(StatusBadge, {state:rumMetricState(row.metric, row.p75)}, rumMetricValue(row.metric, row.p75))),
                                el('td', {}, rumMetricValue(row.metric, row.avg)),
                                el('td', {}, String(row.samples))
                            );
                        }))
                    )
                ) : null,
                rum.enabled && rows.length ? el('div', {className:'ucp-action-list', style:{marginTop:'12px'}},
                    el(ActionButton, {action:'clear-cwv-fielddata', label:__('CWV-fielddata resetten','ultracache-pro'), help:__('Wist alleen lokale CWV-aggregaten; instellingen blijven behouden.','ultracache-pro'), confirm:true, destructive:true, addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                ) : null
            )
        );
    }

    function QueueRunnerCard(props) {
        var queue = (props.status && props.status.queue) || {};
        var runner = queue.runner || {};
        var pending  = parseInt(queue.pending  || 0, 10) || 0;
        var retrying = parseInt(queue.retrying || 0, 10) || 0;
        var failed   = parseInt(queue.failed   || 0, 10) || 0;
        var running  = parseInt(queue.running  || 0, 10) || 0;
        var hasJobs  = pending + retrying > 0;
        var hasFailed = failed > 0;
        var cronOk   = !runner.cronDisabled;

        var overallState = 'good';
        var statusLabel  = __('Wachtrij rustig','ultracache-pro');
        if (runner.cronDisabled)               { overallState = 'warning'; statusLabel = __('WP-Cron uitgeschakeld','ultracache-pro'); }
        else if (hasFailed)                    { overallState = 'warning'; statusLabel = failed + ' ' + __('mislukt','ultracache-pro'); }
        else if (hasJobs && runner.due > 0)    { overallState = 'info';    statusLabel = runner.due + ' ' + __('klaar om te verwerken','ultracache-pro'); }
        else if (hasJobs)                      { overallState = 'info';    statusLabel = (pending + retrying) + ' ' + __('gepland','ultracache-pro'); }
        else if (running > 0)                  { overallState = 'info';    statusLabel = running + ' ' + __('actief','ultracache-pro'); }

        return el(Card, {className:'ucp-card ucp-queue-card ucp-queue-card--always'},
            el(CardHeader, {},
                el('h2', {}, __('Taakstatus','ultracache-pro')),
                el(StatusBadge, {state: overallState}, statusLabel)
            ),
            el(CardBody, {},
                el('div', {className:'ucp-queue-status-grid'},
                    el('div', {className:'ucp-status-row'}, el('span',{},__('In wachtrij','ultracache-pro')), el('strong',{}, String(pending + retrying))),
                    el('div', {className:'ucp-status-row'}, el('span',{},__('Actief','ultracache-pro')),      el('strong',{}, String(running))),
                    el('div', {className:'ucp-status-row'}, el('span',{},__('Mislukt','ultracache-pro')),     el('strong',{className: hasFailed ? 'ucp-count--warning' : ''}, String(failed))),
                    el('div', {className:'ucp-status-row'}, el('span',{},__('WP-Cron','ultracache-pro')),    cronOk ? el(StatusBadge,{state:'good'},__('OK','ultracache-pro')) : el(StatusBadge,{state:'warning'},__('Uit','ultracache-pro'))),
                    el('div', {className:'ucp-status-row'}, el('span',{},__('Laatste run','ultracache-pro')), el('strong',{}, runner.lastRun || '—')),
                    el('div', {className:'ucp-status-row'}, el('span',{},__('Laatste batch','ultracache-pro')), el('strong',{}, String(runner.lastProcessed || 0) + '/' + String(runner.batchSize || 0)))
                ),
                runner.cronDisabled ? el(Notice, {status:'warning', isDismissible:false}, __('WP-Cron lijkt uitgeschakeld. UltraCache verwerkt taken ook via de admin-fallback. Stel voor productie een echte server-cron in.','ultracache-pro')) : null,
                (hasJobs || hasFailed) ? el('div', {className:'ucp-action-list', style:{marginTop:'12px'}},
                    (runner.due > 0) ? el(ActionButton, {action:'run-due-jobs',     label:__('Verwerk taken nu','ultracache-pro'),         help:__('Verwerkt direct de eerstvolgende wachtrijtaken zonder te wachten op WP-Cron.','ultracache-pro'), variant:'primary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh}) : null,
                    hasFailed         ? el(ActionButton, {action:'retry-failed-jobs', label:__('Mislukte taken opnieuw','ultracache-pro'), help:__('Zet mislukte taken terug in de wachtrij.','ultracache-pro'),                                                         addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh}) : null
                ) : null,
                el('p', {className:'ucp-muted', style:{marginTop:'8px'}},
                    runner.nextCron
                        ? __('Volgende geplande run: ','ultracache-pro') + runner.nextCron
                        : __('Geen geplande runner — wordt automatisch aangemaakt zodra er taken zijn.','ultracache-pro')
                )
            )
        );
    }

    function RecommendationCard(props) {
        var s = props.status || {}, cache = s.cache || {}, opt = s.optimization || {}, queue = s.queue || {};
        var message = __('Alles ziet er goed uit. Controleer de site frontend na CSS- of JavaScript-wijzigingen.','ultracache-pro');
        var state = 'good';
        if (!cache.enabled) { state = 'warning'; message = __('Zet pagina caching aan om de basisversnelling te activeren.','ultracache-pro'); }
        else if (!cache.wooSafety) { state = 'warning'; message = __('WooCommerce safety mode staat uit. Zet dit aan voor shops.','ultracache-pro'); }
        else if (queue.failed > 0) { state = 'warning'; message = __('Er zijn mislukte achtergrondtaken. Open Diagnostiek of start de taken opnieuw.','ultracache-pro'); }
        else if (opt.delayJs) { state = 'warning'; message = __('Delay JS is actief. Test formulieren, sliders en checkout handmatig.','ultracache-pro'); }
        return el(Card, {className:'ucp-card'}, el(CardHeader, {}, el('h2', {}, __('Aanbevolen volgende stap','ultracache-pro'))), el(CardBody, {}, el(Notice, {status:state, isDismissible:false}, message)));
    }

    function presetList() {
        return [
            {
                key:'safe',
                title:'Veilige start',
                help:'Voor builders, shops en websites met veel plugins. Cache, preload, CSS verkleinen en veilige lazy load staan aan; JavaScript verkleinen, combineren, Delay JS en Used CSS blijven uit.',
                level:'Laag risico',
                bestFor:'Builders, WooCommerce, veel plugins',
                values:{active_preset:'safe', enable_cache:1, browser_cache_headers:1, compatibility_mode:1, woocommerce_safety_mode:1, enable_preload:1, enable_preload_queue:1, preload_homepage:1, preload_sitemaps:1, preload_batch_size:10, preload_max_urls:150, preload_delay_ms:750, remove_html_comments:1, enable_html_minify:0, enable_css_minify:1, enable_css_combine:0, css_delivery_mode:'none', enable_used_css:0, enable_used_css_delivery:0, enable_critical_css:0, enable_css_queue:0, enable_js_minify:0, enable_js_combine:0, enable_defer_js_fallback:0, defer_all_js:0, enable_delay_js:0, delay_js_mode:'specified', delay_js_safe_mode:1, enable_lazy_images:1, lazyload_exclude_leading_images:1, enable_lazy_iframes:0, enable_lazy_youtube_preview:0, preload_critical_images:1, enable_add_image_dimensions:1, enable_local_google_fonts:0, enable_image_optimization:0, enable_webp_generation:0, enable_avif_generation:0, enable_disable_google_fonts:0, enable_font_display_swap:1, enable_prefetch_links:1, speculative_loading_mode:'core', enable_speculative_loading:0, enable_lazy_render:0, enable_rest_cache:0, enable_stale_cache:0, enable_db_cleanup:0, db_cleanup_frequency:'off'}
            },
            {
                key:'balanced',
                title:'Gebalanceerd',
                help:'Aanbevolen standaard voor de meeste websites. CSS-minify, preload, lazy images, font-display swap en veilige preload staan aan; JS-minify, combineren, Delay JS en image generation blijven uit.',
                level:'Aanbevolen',
                bestFor:'Meeste bedrijfswebsites',
                values:{active_preset:'balanced', enable_cache:1, browser_cache_headers:1, compatibility_mode:1, woocommerce_safety_mode:1, enable_preload:1, enable_preload_queue:1, preload_homepage:1, preload_sitemaps:1, preload_batch_size:15, preload_max_urls:250, preload_delay_ms:500, remove_html_comments:1, enable_html_minify:0, enable_css_minify:1, enable_css_combine:0, css_delivery_mode:'none', enable_used_css:0, enable_used_css_delivery:0, enable_critical_css:0, enable_css_queue:0, enable_js_minify:0, enable_js_combine:0, enable_defer_js_fallback:1, defer_all_js:0, enable_delay_js:0, delay_js_mode:'specified', delay_js_safe_mode:1, enable_lazy_images:1, lazyload_exclude_leading_images:1, enable_lazy_iframes:1, enable_lazy_youtube_preview:1, preload_critical_images:1, enable_add_image_dimensions:1, enable_local_google_fonts:1, enable_image_optimization:0, enable_webp_generation:0, enable_avif_generation:0, enable_disable_google_fonts:0, enable_font_display_swap:1, enable_prefetch_links:1, speculative_loading_mode:'core', enable_speculative_loading:0, enable_lazy_render:0, enable_rest_cache:0, enable_stale_cache:0, enable_db_cleanup:0, db_cleanup_frequency:'off'}
            },
            {
                key:'fast',
                title:'Snelste modus',
                help:'Voor staging of technische gebruikers. Houdt Used CSS en Delay JS uit; schakel die pas bewust apart in na QA.',
                level:'Test op staging',
                bestFor:'Performance tuning met QA',
                values:{active_preset:'fast', enable_cache:1, browser_cache_headers:1, compatibility_mode:0, woocommerce_safety_mode:1, enable_preload:1, enable_preload_queue:1, preload_homepage:1, preload_sitemaps:1, preload_batch_size:20, preload_max_urls:500, preload_delay_ms:350, remove_html_comments:1, enable_html_minify:1, enable_css_minify:1, enable_css_combine:0, css_delivery_mode:'none', enable_used_css:0, enable_used_css_delivery:0, enable_critical_css:0, enable_css_queue:0, enable_js_minify:0, enable_js_combine:0, enable_defer_js_fallback:1, defer_all_js:0, enable_delay_js:0, delay_js_mode:'specified', delay_js_safe_mode:1, enable_lazy_images:1, lazyload_exclude_leading_images:1, enable_lazy_iframes:1, enable_lazy_youtube_preview:1, preload_critical_images:1, enable_add_image_dimensions:1, enable_local_google_fonts:1, enable_image_optimization:0, enable_webp_generation:0, enable_avif_generation:0, enable_disable_google_fonts:0, enable_font_display_swap:1, enable_prefetch_links:1, speculative_loading_mode:'core', enable_speculative_loading:0, enable_lazy_render:0, enable_rest_cache:0, enable_stale_cache:1, enable_db_cleanup:0, db_cleanup_frequency:'off'}
            },
            {
                key:'shop',
                title:'Webshop veilig',
                help:'Voor WooCommerce. Beschermt cart, checkout, account en AJAX flows. Cache, preload, CSS-minify, lazy images en lokale fonts staan aan; JS-minify, Delay JS, combineren, REST cache en image generation blijven uit.',
                level:'Shop veilig',
                bestFor:'WooCommerce en LMS/membership',
                values:{active_preset:'shop', enable_cache:1, browser_cache_headers:1, compatibility_mode:1, woocommerce_safety_mode:1, enable_woocommerce_rules:1, enable_preload:1, enable_preload_queue:1, preload_homepage:1, preload_sitemaps:1, preload_batch_size:10, preload_max_urls:200, preload_delay_ms:750, remove_html_comments:1, enable_html_minify:0, enable_css_minify:1, enable_css_combine:0, css_delivery_mode:'none', enable_used_css:0, enable_used_css_delivery:0, enable_critical_css:0, enable_css_queue:0, enable_js_minify:0, enable_js_combine:0, enable_defer_js_fallback:0, defer_all_js:0, enable_delay_js:0, delay_js_mode:'specified', delay_js_safe_mode:1, enable_lazy_images:1, lazyload_exclude_leading_images:1, enable_lazy_iframes:1, enable_lazy_youtube_preview:1, preload_critical_images:1, enable_add_image_dimensions:1, enable_local_google_fonts:1, enable_image_optimization:0, enable_webp_generation:0, enable_avif_generation:0, enable_disable_google_fonts:0, enable_font_display_swap:1, enable_prefetch_links:1, speculative_loading_mode:'core', enable_speculative_loading:0, enable_lazy_render:0, enable_rest_cache:0, enable_stale_cache:0, enable_db_cleanup:0, db_cleanup_frequency:'off'}
            }
        ];
    }

    function applyPreset(preset, props, setSaving, onDone) {
        setSaving(true);
        saveBulk(preset.values).then(function(resp){
            props.setSettings(resp.settings);
            if (resp.status) props.setStatus(resp.status);
            props.addNotice({status:'success', message:preset.title + ' toegepast.'});
            if (onDone) onDone(resp);
        }).catch(function(err){
            props.addNotice({status:'error', message:cleanErrorMessage(err, 'Preset kon niet worden toegepast.')});
        }).finally(function(){ setSaving(false); });
    }

    function CustomDialog(props) {
        useEffect(function(){
            var previousOverflow = document.body.style.overflow;
            var previousFocus = document.activeElement;
            var focusableSelector = 'a[href], area[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, object, embed, [tabindex]:not([tabindex="-1"]), [contenteditable="true"]';
            var dialog = document.querySelector('.ucp-dialog[role="dialog"]');

            function focusableElements() {
                if (!dialog) {
                    return [];
                }
                return Array.prototype.slice.call(dialog.querySelectorAll(focusableSelector)).filter(function(node){
                    return node.offsetParent !== null || node === document.activeElement;
                });
            }

            function onKeyDown(event) {
                if (event.key === 'Escape' && props.onClose) {
                    props.onClose();
                    return;
                }

                if (event.key !== 'Tab') {
                    return;
                }

                var nodes = focusableElements();
                if (!nodes.length) {
                    event.preventDefault();
                    if (dialog) {
                        dialog.focus();
                    }
                    return;
                }

                var first = nodes[0];
                var last = nodes[nodes.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }

            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', onKeyDown);
            window.setTimeout(function(){
                var nodes = focusableElements();
                if (nodes.length) {
                    nodes[0].focus();
                } else if (dialog) {
                    dialog.focus();
                }
            }, 0);

            return function(){
                document.removeEventListener('keydown', onKeyDown);
                document.body.style.overflow = previousOverflow;
                if (previousFocus && typeof previousFocus.focus === 'function' && document.body.contains(previousFocus)) {
                    previousFocus.focus();
                }
            };
        }, []);
        return el('div', {
                className:'ucp-dialog-layer',
                role:'presentation',
                onMouseDown:function(event){ if (event.target === event.currentTarget && props.onClose) props.onClose(); }
            },
            el('div', {
                    className:'ucp-dialog ucp-dialog--' + (props.size || 'default'),
                    role:'dialog',
                    'aria-modal':'true',
                    'aria-labelledby':'ucp-dialog-title',
                    tabIndex:'-1',
                    onMouseDown:function(event){ event.stopPropagation(); }
                },
                el('div', {className:'ucp-dialog__header'},
                    el('div', {},
                        props.eyebrow ? el('span', {className:'ucp-dialog__eyebrow'}, props.eyebrow) : null,
                        el('h2', {id:'ucp-dialog-title'}, props.title)
                    ),
                    el(Button, {variant:'tertiary', className:'ucp-dialog__close', onClick:props.onClose, 'aria-label':__('Sluiten','ultracache-pro')}, '×')
                ),
                el('div', {className:'ucp-dialog__body'}, props.children),
                props.footer ? el('div', {className:'ucp-dialog__footer'}, props.footer) : null
            )
        );
    }

    function SetupWizard(props) {
        var savingState = useState(false), saving = savingState[0], setSaving = savingState[1];
        return el(CustomDialog, {title:__('Kies een startconfiguratie','ultracache-pro'), eyebrow:__('UltraCache setup','ultracache-pro'), onClose:props.onClose, size:'large'},
            el('div', {className:'ucp-setup-modal'},
                el('p', {className:'ucp-dialog-intro'}, __('Kies direct een veilige basis. Je kunt later alle instellingen handmatig aanpassen.','ultracache-pro')),
                el('div', {className:'ucp-preset-grid'}, presetList().map(function(preset){
                    return el('div', {className:'ucp-preset-card ucp-preset-card--' + preset.key, key:preset.key},
                        el('div', {className:'ucp-preset-card__top'},
                            el('h3', {}, preset.title),
                            el(StatusBadge, {state:preset.key === 'fast' ? 'warning' : (preset.key === 'balanced' ? 'good' : 'info')}, preset.level || '')
                        ),
                        el('p', {className:'ucp-preset-bestfor'}, preset.bestFor || ''),
                        el('p', {}, preset.help),
                        el(Button, {variant:preset.key === 'balanced' ? 'primary' : 'secondary', disabled:saving, isBusy:saving, onClick:function(){applyPreset(preset, props, setSaving, props.onClose);}}, __('Toepassen','ultracache-pro'))
                    );
                })),
                el('div', {className:'ucp-dialog-tip'}, __('Tip: gebruik Webshop veilig als WooCommerce actief is. Delay JS en combineren blijven dan bewust uit.','ultracache-pro'))
            )
        );
    }

    function PresetScanPanel(props) {
        var scan = props.scan;
        var recommendation = scan && scan.recommendation ? scan.recommendation : null;
        var title = recommendation ? recommendation.title : __('Nog geen scan uitgevoerd','ultracache-pro');
        var label = recommendation ? recommendation.label : __('Scan je WordPress-installatie','ultracache-pro');
        var reasons = scan && Array.isArray(scan.reasons) ? scan.reasons : [];
        var warnings = scan && Array.isArray(scan.warnings) ? scan.warnings : [];
        var detected = scan && scan.detected ? scan.detected : {};
        var pluginCount = detected.activePlugins || 0;
        return el('div', {className:'ucp-preset-scan'},
            el('div', {className:'ucp-preset-scan__copy'},
                el('span', {className:'ucp-preset-scan__eyebrow'}, __('Slimme scan','ultracache-pro')),
                el('h3', {}, title),
                el('p', {}, recommendation ? recommendation.summary : __('UltraCache kijkt naar actieve plugins, thema, WooCommerce/LMS, builders, cache-plugins, WordPress-omgeving en veiligheidsrisico’s en adviseert daarna de beste preset of een veilige maatwerkpreset.','ultracache-pro')),
                recommendation ? el('div', {className:'ucp-preset-scan__meta'},
                    el(StatusBadge, {state:recommendation.key === 'fast' ? 'warning' : (recommendation.key === 'custom' ? 'info' : 'good')}, label),
                    el('span', {}, pluginCount ? pluginCount + ' ' + __('actieve plugins','ultracache-pro') : __('Plugins gecontroleerd','ultracache-pro'))
                ) : null,
                reasons.length ? el('ul', {className:'ucp-preset-scan__list'}, reasons.map(function(reason, idx){ return el('li', {key:'r' + idx}, reason); })) : null,
                warnings.length ? el('ul', {className:'ucp-preset-scan__warnings'}, warnings.map(function(warning, idx){ return el('li', {key:'w' + idx}, warning); })) : null
            ),
            el('div', {className:'ucp-preset-scan__actions'},
                el(Button, {variant:'secondary', isBusy:props.scanning, disabled:props.scanning || props.saving, onClick:props.onScan}, props.scanning ? __('Scannen…','ultracache-pro') : __('Site scannen','ultracache-pro')),
                recommendation ? el(Button, {variant:'primary', isBusy:props.saving, disabled:props.scanning || props.saving, onClick:props.onApply}, recommendation.key === 'custom' ? __('Maatwerk toepassen','ultracache-pro') : __('Aanbevolen preset toepassen','ultracache-pro')) : null
            )
        );
    }

    function PresetCards(props) {
        var presetState = useState(false), saving = presetState[0], setSaving = presetState[1];
        var scanState = useState(null), scan = scanState[0], setScan = scanState[1];
        var scanningState = useState(false), scanning = scanningState[0], setScanning = scanningState[1];

        function doScan(showNotice) {
            setScanning(true);
            scanPreset().then(function(resp){
                setScan(resp);
                if (showNotice && resp && resp.recommendation) {
                    props.addNotice({status:'success', message:__('Scan klaar: ','ultracache-pro') + resp.recommendation.title});
                }
            }).catch(function(err){
                props.addNotice({status:'error', message:cleanErrorMessage(err, __('Scan kon niet worden uitgevoerd.','ultracache-pro'))});
            }).finally(function(){ setScanning(false); });
        }

        useEffect(function(){
            if (props.dashboard) {
                doScan(false);
            }
        }, []);

        function applyScannedPreset() {
            if (!scan || !scan.recommendation || !scan.recommendation.values) {
                props.addNotice({status:'error', message:__('Geen scanadvies gevonden om toe te passen.','ultracache-pro')});
                return;
            }
            setSaving(true);
            saveBulk(scan.recommendation.values).then(function(resp){
                props.setSettings(resp.settings);
                if (resp.status) props.setStatus(resp.status);
                props.addNotice({status:'success', message:scan.recommendation.title + ' toegepast.'});
            }).catch(function(err){
                props.addNotice({status:'error', message:cleanErrorMessage(err, __('Scanadvies kon niet worden toegepast.','ultracache-pro'))});
            }).finally(function(){ setSaving(false); });
        }

        return el(Card, {className:'ucp-card ucp-presets-card'},
            el(CardHeader, {},
                el('div', {className:'ucp-section-heading'},
                    el('h2', {}, __('Kies je startinstelling','ultracache-pro')),
                    el('p', {}, __('Kies een veilige startconfiguratie. UltraCache houdt risicovolle optimalisaties zoals combineren, Delay JS en Used CSS standaard uit totdat je ze bewust test.','ultracache-pro'))
                )
            ),
            el(CardBody, {},
                el('div', {className:'ucp-preset-grid'}, presetList().map(function(p){
                    return el('div', {className:'ucp-preset-card ucp-preset-card--' + p.key, key:p.key},
                        el('div', {className:'ucp-preset-card__top'},
                            el('h3', {}, p.title),
                            el(StatusBadge, {state:p.key === 'fast' ? 'warning' : (p.key === 'balanced' ? 'good' : 'info')}, p.level || '')
                        ),
                        el('p', {className:'ucp-preset-bestfor'}, p.bestFor || ''),
                        el('p', {}, p.help),
                        el(Button, {variant:p.key === 'balanced' ? 'primary' : 'secondary', disabled:saving, isBusy:saving, onClick:function(){applyPreset(p, props, setSaving);}}, __('Toepassen','ultracache-pro'))
                    );
                })),
                el(PresetScanPanel, {scan:scan, scanning:scanning, saving:saving, onScan:function(){doScan(true);}, onApply:applyScannedPreset})
            )
        );
    }

    function ActionButton(props) {
        var st = useState(false), busy = st[0], setBusy = st[1];
        var md = useState(false), modal = md[0], setModal = md[1];
        var backupState = useState(false), backupConfirmed = backupState[0], setBackupConfirmed = backupState[1];
        var irreversibleState = useState(false), irreversibleConfirmed = irreversibleState[0], setIrreversibleConfirmed = irreversibleState[1];
        function execute(){
            if (props.confirmBackup && (!backupConfirmed || !irreversibleConfirmed)) {
                props.addNotice({status:'warning', message:__('Bevestig eerst dat er een recente backup is en dat je begrijpt dat deze actie niet kan worden teruggedraaid.','ultracache-pro')});
                return;
            }
            setBusy(true); setModal(false);
            var promise = runAction(props.action, props.confirmBackup ? {confirmBackup:true} : {});
            promise.then(function(resp){
                props.addNotice({status:'success', message:(resp && resp.message) || __('Actie uitgevoerd.','ultracache-pro')});
                if (resp && resp.status && props.setStatus) props.setStatus(resp.status);
                if (props.onComplete) props.onComplete(resp);
            }).catch(function(err){
                props.addNotice({status:'error', message:cleanErrorMessage(err, __('Actie mislukt.','ultracache-pro'))});
            }).finally(function(){ setBusy(false); });
        }
        var button = el(Button, {variant:props.variant || 'secondary', isDestructive:!!props.destructive, isBusy:busy, disabled:busy, onClick:function(){ if (props.confirm) { setBackupConfirmed(false); setIrreversibleConfirmed(false); setModal(true); } else { execute(); } }}, busy ? __('Bezig…','ultracache-pro') : props.label);
        var dialog = modal ? el(CustomDialog, {
                title:props.label,
                eyebrow:props.destructive ? __('Bevestigen','ultracache-pro') : __('Actie uitvoeren','ultracache-pro'),
                onClose:function(){setModal(false);},
                footer:el('div', {className:'ucp-modal-actions'},
                    el(Button,{variant:'secondary', onClick:function(){setModal(false);}},__('Annuleren','ultracache-pro')),
                    el(Button,{variant:'primary', isDestructive:!!props.destructive, disabled:!!props.confirmBackup && (!backupConfirmed || !irreversibleConfirmed), onClick:execute}, props.label)
                )
            },
            el('p', {className:'ucp-dialog-intro'}, props.confirmText || props.help),
            props.confirmBackup ? el('div', {className:'ucp-confirmation-checks'},
                CheckboxControl ? el(CheckboxControl, {label:__('Ik heb een recente databasebackup.','ultracache-pro'), checked:backupConfirmed, onChange:setBackupConfirmed}) : el('label', {}, el('input', {type:'checkbox', checked:backupConfirmed, onChange:function(e){setBackupConfirmed(!!e.target.checked);}}), ' ', __('Ik heb een recente databasebackup.','ultracache-pro')),
                CheckboxControl ? el(CheckboxControl, {label:__('Ik begrijp dat deze actie niet ongedaan kan worden gemaakt.','ultracache-pro'), checked:irreversibleConfirmed, onChange:setIrreversibleConfirmed}) : el('label', {}, el('input', {type:'checkbox', checked:irreversibleConfirmed, onChange:function(e){setIrreversibleConfirmed(!!e.target.checked);}}), ' ', __('Ik begrijp dat deze actie niet ongedaan kan worden gemaakt.','ultracache-pro'))
            ) : null
        ) : null;
        if (props.compact) {
            return el(Fragment, {}, button, dialog);
        }
        return el('div', {className:'ucp-action-row'},
            el('div', {className:'ucp-action-copy'}, el('strong', {}, props.label), props.help ? el('p', {}, props.help) : null),
            el('div', {className:'ucp-action-button'}, button),
            dialog
        );
    }

    function downloadText(filename, text) {
        var blob = new Blob([String(text)], {type:'application/json;charset=utf-8'});
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); a.remove();
        window.setTimeout(function(){ URL.revokeObjectURL(url); }, 1000);
    }

    function BulkActionButton(props) {
        var st = useState(false), busy = st[0], setBusy = st[1];
        var actions = Array.isArray(props.actions) ? props.actions : [];
        function execute(){
            if (!actions.length || busy) { return; }
            if (props.confirm && !window.confirm(props.confirmText || __('Deze gecombineerde actie voert meerdere stappen uit. Doorgaan?','ultracache-pro'))) { return; }
            setBusy(true);
            var completed = 0;
            actions.reduce(function(chain, action){
                return chain.then(function(){
                    return runAction(action).then(function(resp){
                        completed++;
                        if (resp && resp.status && props.setStatus) props.setStatus(resp.status);
                        return resp;
                    });
                });
            }, Promise.resolve()).then(function(){
                props.addNotice({status:'success', message:props.successMessage || sprintf(__('Klaar: %d acties uitgevoerd.','ultracache-pro'), completed)});
                if (props.onComplete) props.onComplete();
            }).catch(function(err){
                props.addNotice({status:'error', message:cleanErrorMessage(err, __('Gecombineerde actie mislukt. Controleer logs en probeer de losse stap opnieuw.','ultracache-pro'))});
            }).finally(function(){ setBusy(false); });
        }
        return el(Button, {
            variant:props.variant || 'secondary',
            isBusy:busy,
            disabled:busy || !actions.length,
            onClick:execute
        }, busy ? __('Bezig…','ultracache-pro') : props.label);
    }

    function ActionsPage(props) {
        var isTools = props.title === __('Tools','ultracache-pro');
        var actionGroups = [
            {
                key:'cache',
                title:__('Dagelijkse cache-acties','ultracache-pro'),
                description:__('Gebruik deze acties na content-, thema- of pluginwijzigingen.','ultracache-pro'),
                bulk:{label:__('Cache legen + opwarmen','ultracache-pro'), actions:['purge-all','preload','run-due-jobs'], success:__('Cache geleegd, preload gestart en wachtrij verwerkt.','ultracache-pro')},
                actions:[
                    {action:'purge-all', label:__('Leeg alle cache','ultracache-pro'), help:__('Verwijdert alle cachebestanden en laat nieuwe cache opnieuw opbouwen.','ultracache-pro'), variant:'primary'},
                    {action:'purge-page-cache', label:__('Leeg pagina-cache','ultracache-pro'), help:__('Verwijdert alleen de HTML-pagina-cache. CSS, JS en instellingen blijven ongemoeid.','ultracache-pro')}
                ]
            },
            {
                key:'preload',
                title:__('Preload en wachtrij','ultracache-pro'),
                description:__('Start preload of verwerk taken wanneer WP-Cron niet meteen draait.','ultracache-pro'),
                bulk:{label:__('Preload volledig uitvoeren','ultracache-pro'), actions:['preload','run-due-jobs'], success:__('Preload gestart en open taken verwerkt.','ultracache-pro')},
                actions:[
                    {action:'preload', label:__('Cache opwarmen','ultracache-pro'), help:__('Start de preload queue om pagina’s vooraf in cache te zetten.', 'ultracache-pro')},
                    {action:'run-due-jobs', label:__('Verwerk taken nu','ultracache-pro'), help:__('Verwerkt taken die nu klaarstaan zonder te wachten op WP-Cron.', 'ultracache-pro'), variant:'primary'},
                    {action:'retry-failed-jobs', label:__('Mislukte taken opnieuw proberen','ultracache-pro'), help:__('Zet mislukte of opnieuw geplande achtergrondtaken terug in de wachtrij.', 'ultracache-pro'), confirm:true}
                ]
            },
            {
                key:'css',
                title:__('CSS','ultracache-pro'),
                description:__('Herbouw of wis CSS-bestanden.','ultracache-pro'),
                bulk:{label:__('CSS volledig vernieuwen','ultracache-pro'), actions:['clear-minified-css','critical-css','used-css','run-due-jobs'], success:__('CSS-bestanden gewist en Critical/Used CSS opnieuw gestart.','ultracache-pro')},
                actions:[
                    {action:'critical-css', label:__('Genereer kritieke CSS','ultracache-pro'), help:__('Start CSS-generatie voor de homepage.', 'ultracache-pro')},
                    {action:'used-css', label:__('Used CSS opnieuw genereren','ultracache-pro'), help:__('Bouwt gebruikte CSS-artifacten opnieuw op.', 'ultracache-pro')},
                    {action:'clear-minified-css', label:__('Verkleinde CSS wissen','ultracache-pro'), help:__('Verwijdert opgebouwde CSS-bestanden zodat ze opnieuw kunnen worden aangemaakt.', 'ultracache-pro'), variant:'tertiary'}
                ]
            },
            {
                key:'js',
                title:__('JavaScript','ultracache-pro'),
                description:__('Wis opgebouwde JavaScript-bestanden.','ultracache-pro'),
                actions:[
                    {action:'clear-minified-js', label:__('Verkleinde JS wissen','ultracache-pro'), help:__('Verwijdert opgebouwde JavaScript-bestanden zodat ze opnieuw kunnen worden aangemaakt.', 'ultracache-pro'), variant:'tertiary'}
                ]
            },
            {
                key:'support',
                title:__('Support','ultracache-pro'),
                description:__('Controle en supportacties.','ultracache-pro'),
                bulk:{label:__('Controlepakket uitvoeren','ultracache-pro'), actions:['health-check','runtime-cache-test','detect-conflicts','release-checklist'], success:__('Health check, runtime test, conflictcheck en release checklist uitgevoerd.','ultracache-pro')},
                actions:[
                    {action:'health-check', label:__('Websitecontrole uitvoeren','ultracache-pro'), help:__('Controleert cachemap, drop-in, conflicten en runtimevoorwaarden.','ultracache-pro')}
                ]
            },
            {
                key:'maintenance',
                title:__('Risico','ultracache-pro'),
                description:__('Gebruik alleen bewust en na backup.','ultracache-pro'),
                danger:true,
                actions:[
                    {action:'database-cleanup', label:__('Database opschonen','ultracache-pro'), help:__('Maak eerst een database-backup. Deze actie kan niet ongedaan worden gemaakt.', 'ultracache-pro'), variant:'primary', destructive:true, confirm:true, confirmBackup:true}
                ]
            }
        ];
        var orderedGroups = actionGroups;
        return el('div', {className:'ucp-page ucp-actions-page'},
            el('div', {className:'ucp-layout-grid ucp-layout-grid--actions ucp-layout-grid--simple'}, orderedGroups.map(function(group){
                return el(Card, {
                    key:group.key,
                    className:'ucp-card ucp-layout-card ucp-action-group-card' + (group.danger ? ' ucp-action-group-card--danger' : '')
                },
                    el(CardHeader, {},
                        el('div', {className:'ucp-layout-card__header'},
                            el('div', {className:'ucp-action-group-heading'},
                                el('h2', {}, group.title),
                                el('p', {className:'ucp-action-group-description'}, group.description)
                            ),
                            group.bulk ? el('div', {className:'ucp-action-group-header-actions'},
                                el(BulkActionButton, {label:group.bulk.label, actions:group.bulk.actions, successMessage:group.bulk.success, addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                            ) : null
                        )
                    ),
                    el(CardBody, {},
                        el('div', {className:'ucp-action-list'}, group.actions.map(function(a){
                            return el(ActionButton,{
                                key:a.action,
                                action:a.action,
                                label:a.label,
                                help:a.help,
                                variant:a.variant || 'secondary',
                                destructive:!!a.destructive,
                                confirm:!!a.confirm,
                                confirmText:a.help,
                                confirmBackup:!!a.confirmBackup,
                                addNotice:props.addNotice,
                                setStatus:props.setStatus,
                                onComplete:props.onRefresh
                            });
                        }))
                    )
                );
            })),
            isTools ? el(ImportExportPanel, props) : null
        );
    }

    function ImportExportPanel(props) {
        var busyState = useState(false), busy = busyState[0], setBusy = busyState[1];
        function doExport(){
            setBusy(true);
            exportSettings().then(function(resp){
                downloadText('ultracache-settings.json', JSON.stringify(resp.settings || {}, null, 2));
                props.addNotice({status:'success', message:__('Instellingen geëxporteerd.','ultracache-pro')});
            }).catch(function(err){
                props.addNotice({status:'error', message:cleanErrorMessage(err, __('Export mislukt.','ultracache-pro'))});
            }).finally(function(){ setBusy(false); });
        }
        function importParsedSettings(parsed){
            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                props.addNotice({status:'error', message:__('Ongeldig JSON-bestand. Gebruik een UltraCache exportbestand.','ultracache-pro')});
                return;
            }
            if (!window.confirm(__('Import overschrijft bestaande UltraCache-instellingen. Maak eerst een export of database-back-up. Doorgaan?','ultracache-pro'))) { return; }
            setBusy(true);
            importSettings(parsed).then(function(resp){
                props.setSettings(resp.settings);
                if (resp.status) props.setStatus(resp.status);
                props.addNotice({status:'success', message:__('JSON-instellingen geïmporteerd.','ultracache-pro')});
            }).catch(function(err){
                props.addNotice({status:'error', message:cleanErrorMessage(err, __('Import mislukt.','ultracache-pro'))});
            }).finally(function(){ setBusy(false); });
        }
        function doFileImport(event){
            var file = event && event.target && event.target.files ? event.target.files[0] : null;
            if (!file) { return; }
            if (!/\.(json|txt)$/i.test(file.name || '')) {
                props.addNotice({status:'error', message:__('Kies een .json of .txt bestand.','ultracache-pro')});
                event.target.value = '';
                return;
            }
            if (file.size && file.size > 256 * 1024) {
                props.addNotice({status:'error', message:__('Het JSON-bestand is te groot. Maximale grootte is 256 KB.','ultracache-pro')});
                event.target.value = '';
                return;
            }
            var reader = new FileReader();
            reader.onload = function(){
                var text = String(reader.result || '');
                var parsed;
                try { parsed = JSON.parse(text); } catch(e) {
                    props.addNotice({status:'error', message:__('Ongeldige JSON in het gekozen bestand.','ultracache-pro')});
                    event.target.value = '';
                    return;
                }
                importParsedSettings(parsed);
                event.target.value = '';
            };
            reader.onerror = function(){
                props.addNotice({status:'error', message:__('Het JSON-bestand kon niet worden gelezen.','ultracache-pro')});
                event.target.value = '';
            };
            reader.readAsText(file);
        }
        return el(Card, {className:'ucp-card ucp-import-export-card'},
            el(CardHeader, {}, el('h2', {}, __('Import / export','ultracache-pro'))),
            el(CardBody, {},
                el('div', {className:'ucp-import-export-grid ucp-import-export-grid--simple'},
                    el('section', {className:'ucp-import-export-box ucp-import-export-box--export'},
                        el('div', {className:'ucp-import-export-head'},
                            el('span', {className:'ucp-import-export-eyebrow'}, __('Export','ultracache-pro')),
                            el('h3', {className:'ucp-import-export-title'}, __('Instellingen exporteren','ultracache-pro')),
                            el('p', {className:'ucp-import-export-help'}, __('Download je huidige UltraCache-instellingen als JSON-back-up voordat je nieuwe instellingen importeert.','ultracache-pro'))
                        ),
                        el('div', {className:'ucp-import-export-actions'},
                            el(Button, {variant:'secondary', isBusy:busy, disabled:busy, onClick:doExport}, __('Exporteren','ultracache-pro'))
                        )
                    ),
                    el('section', {className:'ucp-import-export-box'},
                        el('div', {className:'ucp-import-export-head'},
                            el('span', {className:'ucp-import-export-eyebrow'}, __('Import','ultracache-pro')),
                            el('h3', {className:'ucp-import-export-title'}, __('Instellingen importeren','ultracache-pro')),
                            el('p', {className:'ucp-import-export-help'}, __('Upload een UltraCache .json exportbestand. Na bevestiging worden je instellingen direct bijgewerkt.','ultracache-pro'))
                        ),
                        el('label', {className:'ucp-json-file-import ucp-json-file-import--button'},
                            el('span', {className:'ucp-json-file-import__button'}, busy ? __('Bezig…','ultracache-pro') : __('JSON importeren','ultracache-pro')),
                            el('input', {type:'file', accept:'.json,.txt,application/json,text/plain', disabled:busy, onChange:doFileImport})
                        )
                    )
                )
            )
        );
    }

    var settingsGroups = {
        optimization: [
            {title:'HTML', fields:[['html_optimization_mode','HTML optimalisatie','select','Eén duidelijke keuze. HTML verkleinen zet comments verwijderen automatisch aan en slaat gevoelige WooCommerce-, builder- en previewpagina’s over.',[['off','Uit'],['comments','Alleen comments verwijderen'],['minify','HTML verkleinen + comments verwijderen']]],['html_exclude_urls','HTML uitsluitingen','textarea','Eén URL/patroon per regel.']]},
            {title:'CSS', fields:[['enable_css_minify','CSS verkleinen','toggle','Veilig voor de meeste sites.'],['enable_css_combine','CSS combineren','toggle','Wordt automatisch vergrendeld bij HTTP/2/3, builders, formulieren, andere optimalisatieplugins of actieve CSS-levering.'],['css_delivery_mode','CSS-levering optimaliseren','css_delivery','Staging-first: Ongebruikte CSS verwijderen gebruikt de AST-parser wanneer vendor libraries aanwezig zijn en valt anders veilig terug. Test builders en checkout eerst.',[['none','Uit - veiligste keuze'],['remove_unused','Ongebruikte CSS verwijderen - staging'],['async','CSS asynchroon laden - fallback']]],['css_exclusions','CSS uitsluitingen / safelist','textarea','Handles, bestandsnamen, selectors of fragmenten die niet mogen worden aangepast.']]},
            {title:'JavaScript', fields:[['enable_js_minify','JavaScript verkleinen','toggle','Experimenteel. Zet dit alleen aan na stagingtests, vooral checkout, formulieren en cookie banners.'],['enable_js_combine','JavaScript combineren','toggle','Wordt automatisch vergrendeld bij HTTP/2/3, Delay JS, script strategies, shops, builders, formulieren, cookieplugins of andere optimalisatieplugins.'],['defer_all_js','Defer JS','toggle','Stelt scripts later in de laadvolgorde.'],['delay_js_control','JavaScript uitstellen','select','Eén keuze vervangt Delay JS, modus en veilige modus. Test altijd formulieren, sliders, cookie banners en checkout op staging.',[['off','Uit'],['specified','Alleen opgegeven scripts'],['all','Alle scripts behalve uitsluitingen'],['safe','Veilige modus']]],['delay_js_exclusions','Delay JS uitsluitingen','textarea','Eén script/fragment per regel.']]},
            {title:'WordPress opschonen', fields:[['bloat_removal_mode','WordPress opschonen','select','Eén keuze verwijdert ongebruikte WordPress-onderdelen. Emoji-scripts en embeds worden sowieso al automatisch opgeruimd. Veilig: dashicons op de frontend, WP-versie, RSD/shortlink/feed/REST-links en self-pingbacks. Agressief voegt jQuery Migrate, XML-RPC, RSS-feeds, globale blokstijlen en query strings toe — test builders en koppelingen op staging.',[['off','Uit'],['safe','Veilig — aanbevolen'],['aggressive','Agressief — staging-first']]]]}
        ],
        media: [
            {title:'Basis', fields:[['media_lazyload_mode','Media lazyload','select','Eén keuze vervangt lazyload voor afbeeldingen, iframes/video en YouTube preview.',[['off','Uit'],['images','Alleen afbeeldingen'],['iframes','Afbeeldingen + iframes/video'],['youtube','Afbeeldingen + iframes/video + YouTube preview']]],['enable_add_image_dimensions','Afbeeldingsafmetingen toevoegen','toggle','Aanbevolen om layout shifts te beperken.'],['enable_lazy_render','HTML lazy render','toggle','Staging-first: stelt het renderen van offscreen secties uit met content-visibility. Kan zoeken-in-pagina, anchor-links en printen beïnvloeden — test eerst op staging.'],['lazy_render_selectors','Lazy render selectors','textarea','Eén CSS-selector per regel voor secties die pas onder de vouw gerenderd worden. Alleen actief als HTML lazy render aanstaat.'],['enable_html_parser','HTML-parser engine (experimenteel)','toggle','Gebruikt een fouttolerante HTML-tokenizer i.p.v. regex voor afbeelding- en iframe-passes: slaat <script>/<style>/<textarea>/comments correct over en breekt niet op > binnen attributen. Valt automatisch terug op de regex-methode bij een fout. Test op staging.']]} ,
            {title:'Afbeeldingen', fields:[['lcp_image_mode','LCP-afbeeldingen','lcp_images','Eén keuze voor boven-de-vouw afbeeldingen. Dit vervangt kritieke afbeeldingen preloaden en bovenste afbeeldingen niet lazyloaden.',[['off','Uit'],['protect_hero','Hero beschermen: niet lazyloaden'],['preload_hero','Hero preloaden'],['recommended','Aanbevolen: 2 preloaden + 4 beschermen'],['custom','Aangepast']]],['image_optimization_mode','Afbeeldingsoptimalisatie','select','Eén keuze vervangt de losse optimalisatie-, WebP- en AVIF-knoppen. Laat uit als een andere image optimizer dit al doet.',[['off','Uit'],['optimize','Nieuwe uploads optimaliseren'],['webp','Optimaliseren + WebP maken'],['webp_avif','Optimaliseren + WebP + AVIF maken']]],['image_quality','Afbeeldingskwaliteit','number','0-100. Gebruik meestal 80-85 voor goede balans.'],['enable_lqip','LQIP placeholders','toggle','Genereert lichte placeholders voor lazyloaded afbeeldingen. Test visueel bij hero’s en sliders.']]} ,
            {title:'Fonts', fields:[['google_fonts_mode','Google Fonts gedrag','select','Eén keuze vervangt lokaal hosten, font-display swap en Google Fonts uitschakelen.',[['standard','Standaard'],['swap','Alleen font-display swap'],['local','Lokaal hosten + swap'],['disable','Google Fonts uitschakelen']]],['enable_auto_font_preloads','Automatische font-preloads','toggle','Preloadt maximaal drie lokaal gecachete WOFF/WOFF2 fonts die door UltraCache zelf zijn opgeslagen.'],['preload_fonts','Fonts preloaden','textarea','Alleen kritieke WOFF2-fontbestanden, één URL per regel.']]} ,
            {title:'Uitsluitingen en externe bronnen', fields:[['lazyload_exclusions','LazyLoad uitsluitingen','textarea','Eén patroon per regel. Gebruik voor logo’s, hero-afbeeldingen of sliders die direct zichtbaar zijn.'],['enable_auto_resource_hints','Externe bronnen versnellen','toggle','Voegt automatisch beperkte preconnect en DNS-prefetch toe voor externe domeinen die je site gebruikt. Veilig om aan te laten.']]},
            {title:'Externe bronnen lokaal hosten', fields:[['enable_local_gravatar','Gravatars lokaal hosten','toggle','Cacht externe Gravatar-afbeeldingen lokaal om third-party requests te beperken.'],['enable_local_youtube_thumbnails','YouTube thumbnails lokaal hosten','toggle','Cacht YouTube thumbnails lokaal voor lazy previews.'],['enable_self_host_third_party_assets','Externe scripts lokaal hosten','toggle','Cacht ondersteunde third-party scripts (zoals Google Analytics) lokaal om externe requests te beperken. Test koppelingen en tracking na inschakelen.']]},
            {title:'Image CDN', fields:[['enable_image_cdn','Image CDN herschrijven','toggle','Herschrijft afbeeldings-URL’s naar een extern image-CDN. Dit is het tegenovergestelde van lokaal hosten: assets gaan naar een CDN dat jij aanlevert. Test responsive images en srcset.'],['image_cdn_base','Image-CDN basis-URL','text','Bijvoorbeeld https://cdn.example.com. Laat leeg om niet te herschrijven.'],['image_cdn_query','Image-CDN query','text','Optionele querystring zonder vraagteken, bijvoorbeeld width=auto&quality=80.']]}
        ],
        preload: [
            {title:'Cache opbouwen', fields:[['preload_mode','Cache preload','select','Eén keuze vervangt preload aan/uit, queue, sitemap en homepage meenemen.',[['off','Uit'],['recommended','Veilig aanbevolen: queue + sitemap + homepage'],['homepage','Alleen homepage'],['manual','Handmatig / geavanceerd']]]]} ,
            {title:'Link preload', fields:[['enable_prefetch_links','Link preload activeren','toggle','Verbetert de ervaren navigatiesnelheid bij hover/klik. Heeft meestal geen effect op PageSpeed-score. Gebruik voorzichtig op shops of sites met veel unieke links.'],['speculative_loading_mode','Speculative Loading','select','Core standaard volgen gebruikt WordPress 6.8+ zoals WordPress het zelf levert. Uitschakelen zet Core Speculative Loading voor de request uit. Prerender alleen op staging testen.',[['core','Core standaard volgen'],['enhanced','UltraCache veilig versterken'],['prerender','Prerender — staging'],['off','Volledig uitschakelen']]]]} ,
            {title:'Uitsluitingen', fields:[['preload_exclude_urls','URL’s uitsluiten van preload','textarea','Eén URL of patroon per regel. Gebruik voor author-, account-, checkout-, zoek-, filter- of paginatiepagina’s.']]}
        ],
        cache: [
            {title:'Page cache', fields:[['enable_cache','Pagina-cache inschakelen','toggle','Maakt statische cachebestanden voor bezoekers. Zet uit bij diagnose of conflicten.'],['cache_lifespan','Cache bewaren voor','number','Aantal uren voordat een cachebestand automatisch wordt vernieuwd. 10 uur is veilig voor de meeste websites.'],['stale_cache_mode','Stale cache','select','Serveer tijdelijk oude cache als vernieuwen mislukt.',[['off','Uit'],['6','6 uur'],['12','12 uur'],['24','24 uur'],['48','48 uur']]]]},
            {title:'Varianten en purge', fields:[['cache_mobile_separately','Mobiele cache apart bewaren','toggle','Gebruik dit alleen wanneer mobiel en desktop duidelijk andere HTML krijgen.'],['enable_cache_tags','Gerelateerde pagina’s meeverversen','toggle','Handig voor blogs, categorieën en archieven.']]},
            {title:'Webshop en veiligheid', fields:[['enable_woocommerce_rules','WooCommerce veilig cachen','toggle','Beschermt winkelwagen, afrekenen, account en wc-ajax tegen caching en agressieve optimalisaties. Aanbevolen voor elke shop.'],['disable_logged_in_optimizations','Optimalisaties uit voor ingelogde gebruikers','toggle','Aanbevolen voor builders en beheerwerk. Houdt de editor, previews en beheerflows veilig.'],['accessibility_mode','Veilige modus voor interacties','toggle','Vermindert risicovolle optimalisaties zodat knoppen, focus en dynamische onderdelen veilig blijven.'],['serve_cache_to_shoppers','Publieke cache voor shoppers','toggle','Toont bezoekers met een mandje toch de gecachete pagina; de mini-cart wordt client-side bijgewerkt. Een render met gevulde mand wordt nooit opgeslagen. Test eerst op staging.'],['optimize_cart_fragments','Cart-fragments optimaliseren','toggle','Cachet de lege-mand fragments-respons en bedient die snel. Bij een gevulde mand laat WooCommerce zijn eigen werk doen.'],['limit_cart_fragments_to_woo','Cart-fragments alleen waar nodig','toggle','Laadt wc-cart-fragments alleen waar een mini-cart of cart-widget staat.']]}
        ],
        advanced: [
            {title:'Pagina’s', fields:[['exclude_urls','Nooit URL’s cachen','textarea','Eén pad of patroon per regel. Gebruik dit voor cart, checkout, account, zoekresultaten, filters of persoonlijke content.']]},
            {title:'Cookies / agents', fields:[['exclude_cookies','Nooit cachen bij cookies','textarea','Eén cookie of gedeeltelijke cookienaam per regel. Nuttig voor winkelwagens en gepersonaliseerde content.'],['block_unknown_request_cookies','Strikte cookie-modus','toggle','Staging-first. Bypasst cache bij onbekende cookies. Aanbevolen voor membership, portals, custom sessies en sterk gepersonaliseerde sites; kan cache-hit ratio verlagen.'],['cache_vary_cookies','Cache variëren per valuta/taal','textarea','Eén cookie-fragment per regel. Deze cookies variëren de cache in plaats van te bypassen — ideaal voor multi-currency of meertalige shops. Aanbevolen: wcml_client_currency, pll_language, aelia_cs_selected_currency.'],['exclude_user_agents','Nooit cachen voor user-agents','textarea','Eén user-agent fragment per regel. Laat leeg tenzij een apparaat, browser of bot afwijkende content krijgt.']]},
            {title:'Automatisch legen bij wijzigingen', fields:[['always_purge_urls','Altijd extra URL’s legen','textarea','Eén URL of patroon per regel. Gebruik voor pagina’s die mee moeten verversen wanneer content wijzigt, zoals homepage of archieven.']]},
            {title:'Query strings cachen', fields:[['query_string_cache_mode','Query strings cachen','select','Eén keuze vervangt de losse aan/uit-knop. Gebruik dit alleen voor bekende parameters die geen persoonlijke content tonen.',[['off','Uit'],['allow_list','Alleen onderstaande parameters toestaan']]],['cache_query_string_inclusions','Toegestane query parameters','textarea','Eén parameter per regel, zonder vraagteken. Wildcards aan het einde zijn toegestaan, bijvoorbeeld filter_* of query_type_*.']]},
            {title:'CDN basis', fields:[['cdn_rewrite_mode','CDN herschrijven','select','Eén keuze vervangt CDN inschakelen en bestandstypen herschrijven.',[['off','Uit'],['css_js','Alleen CSS en JS'],['images','Alleen afbeeldingen'],['all','Alle statische bestanden']]],['cdn_cnames','CDN CNAMEs','textarea','Eén domein per regel.'],['cdn_exclude','CDN uitsluitingen','textarea','Eén patroon per regel.'],['browser_cache_mode','Browser-cache statische bestanden','select','Eén keuze vervangt browser-cache headers en bewaartijd.',[['off','Uit'],['30d','30 dagen'],['180d','6 maanden'],['365d','1 jaar'],['custom','Aangepast']]],['cache_control_max_age','Aangepaste browser-cache bewaartijd','number','In seconden. Alleen nodig wanneer je Aangepast gebruikt.']]},
            {title:'Headless renderer en fragments', fields:[['enable_headless_renderer','Headless-renderer activeren','toggle','Nodig voor precieze VPI-detectie en browsergebaseerde CSS-analyse. Staging-first: externe renderer-output is een trust boundary.'],['headless_renderer_endpoint','Headless-renderer endpoint','text','Publieke endpoint-URL van de render service.'],['headless_renderer_token','Headless-renderer token','text','Server-side secret. Wordt gemaskeerd bij export en in de UI.'],['enable_esi','ESI fragment-cache','toggle','Server-agnostische hole-punching voor dynamische fragments. Staging-first: custom fragments mogen geen persoonlijke data in gedeelde cache lekken en moeten gesaniteerde HTML teruggeven. Test account, formulieren, mini-cart en checkout.']]},
            {title:'CDN provider en compat-lijsten', fields:[['cdn_provider','CDN purge-provider','select','Kies alleen een provider die echt actief is. Cloudflare gebruikt de bestaande zone/token-velden.',[['none','Geen'],['cloudflare','Cloudflare'],['bunny','Bunny CDN'],['generic','Generieke webhook']]],['bunny_pull_zone_id','Bunny pull-zone ID','text','Alleen nodig bij Bunny purge.'],['bunny_api_key','Bunny API-key','text','Server-side secret. Wordt gemaskeerd bij export en in de UI.'],['cdn_purge_webhook','Generieke purge-webhook','text','Alleen voor vertrouwde interne endpoints.'],['cdn_purge_webhook_token','Webhook token','text','Server-side secret voor de generieke purge-webhook.'],['enable_compat_updates','Compat-lijsten automatisch bijwerken','toggle','Haalt remote compat-overlays op. Alleen gebruiken met een vertrouwde bron.'],['compat_update_url','Compat-update URL','text','JSON-endpoint voor compat-overlays.'],['enable_host_cache_purge','Hosting-cache mee legen','toggle','Stuurt UltraCache-purges ook naar de servercache van bekende managed hosts (WP Engine, SiteGround, SpinupWP, Nginx FastCGI via Nginx Helper, Pantheon). Fail-safe: doet niets als de host niet wordt herkend.']]}
        ],
        database: [
            {title:'Automatisch onderhoud', fields:[['db_cleanup_frequency','Automatische database-opschoning','select','Kies Uit of een schema. Dit vervangt de losse inschakelknop; UltraCache zet de interne planning automatisch goed.',[['off','Uit'],['daily','Dagelijks'],['weekly','Wekelijks'],['monthly','Maandelijks']]]]},
            {title:'Berichten opruimen', fields:[['db_cleanup_post_revisions','Revisies opschonen','toggle','Verwijdert oude revisies, maar bewaart het ingestelde aantal recente versies per bericht.'],['db_keep_post_revisions','Revisies bewaren','number','Aanbevolen: bewaar minimaal 5 revisies voor contentherstel.'],['db_cleanup_auto_drafts','Automatische concepten opschonen','toggle','Verwijdert oude automatische concepten die niet meer gebruikt worden.'],['db_cleanup_trashed_posts','Prullenbakberichten verwijderen','toggle','Verwijdert berichten en pagina’s die al in de prullenbak staan.']]},
            {title:'Reacties opruimen', fields:[['db_cleanup_spam_comments','Spamreacties verwijderen','toggle','Verwijdert reacties die al als spam zijn gemarkeerd.'],['db_cleanup_trashed_comments','Prullenbakreacties verwijderen','toggle','Verwijdert reacties die al in de prullenbak staan.']]},
            {title:'Transients opruimen', fields:[['db_cleanup_expired_transients','Verlopen transients verwijderen','toggle','Veilige basis. Verwijdert tijdelijke data waarvan de verloopdatum voorbij is.'],['db_cleanup_all_transients','Alle transients verwijderen','toggle','Kan tijdelijke caches van plugins wissen. Gebruik dit alleen wanneer je problemen met tijdelijke data vermoedt.']]},
            {title:'Tabellen optimaliseren', fields:[['db_cleanup_optimize_tables','Databasetabellen optimaliseren','toggle','Ruimt tabel-overhead op waar de database-engine dit ondersteunt. Maak eerst een backup.']]}
        ],
        diagnostics: [
            {title:'Diagnostiek en logs', fields:[['enable_diagnostics','Diagnostiek registreren','toggle','Slaat beperkte runtime-diagnostiek op voor cache- en optimalisatiecontrole.'],['enable_logs','Logboek inschakelen','toggle','Bewaar technische meldingen voor foutopsporing. Zet uit op productie als je dit niet actief gebruikt.'],['enable_health_checks','Health checks plannen','toggle','Controleert cachemap, drop-in en runtimevoorwaarden.'],['enable_admin_queue_runner','Admin queue runner','toggle','Mag wachtrijtaken vanuit het dashboard proberen te verwerken.']]},
            {title:'Core Web Vitals fielddata', fields:[['enable_cwv_monitoring','CWV-monitoring','toggle','Verzamelt lokale Core Web Vitals-fielddata uit echte bezoeken. Slaat alleen metricwaarde en apparaatklasse op.']]},
            {title:'Bewaartermijnen', fields:[['log_retention_days','Logs bewaren dagen','number','Aantal dagen dat logs blijven staan.'],['diagnostics_retention_days','Diagnostiek bewaren dagen','number','Aantal dagen dat diagnostiekdata blijft staan.'],['job_retention_days','Jobs bewaren dagen','number','Aantal dagen dat afgeronde jobs blijven staan.']]},
            {title:'Verwijderen', fields:[['clean_uninstall','Instellingen wissen bij verwijderen','toggle','Verwijdert alle plugininstellingen wanneer je UltraCache deïnstalleert. Laat uit als je je configuratie wilt bewaren.']]}
        ],

    };

    function settingsIntro(kind) {
        var data = {
            cache: {title: __('Cache overzicht','ultracache-pro'), text: __('Beheer de algemene page cache, bewaartijd en veilige purge-instellingen zonder de uitzonderingsregels te mengen met optimalisatie.','ultracache-pro'), steps: [__('Page cache','ultracache-pro'), __('Bewaartijd','ultracache-pro'), __('Varianten en purge','ultracache-pro')]},
            optimization: {title: __('Bestandsoptimalisatie overzicht','ultracache-pro'), text: __('Begin met veilige optimalisaties. Zet risicovolle CSS- en JavaScript-opties pas aan nadat je de frontend, formulieren en checkout hebt getest.','ultracache-pro'), steps: [__('1. Kies eerst een preset','ultracache-pro'), __('2. Controleer HTML en CSS','ultracache-pro'), __('3. Test JavaScript apart','ultracache-pro')]},
            media: {title: __('Media optimalisatie overzicht','ultracache-pro'), text: __('Gebruik veilige media-optimalisaties eerst: lazy load voor offscreen media, vaste afbeeldingsafmetingen en font-display swap. Gebruik preload alleen voor kritieke fonts of hero-afbeeldingen.','ultracache-pro'), steps: [__('Aanbevolen basis','ultracache-pro'), __('Afbeeldingen','ultracache-pro'), __('Fonts','ultracache-pro'), __('Uitsluitingen en connecties','ultracache-pro')]},
            preload: {title: __('Preload overzicht','ultracache-pro'), text: __('Preload maakt cachebestanden klaar voordat bezoekers pagina’s openen. Gebruik de queue en sitemap als veilige basis; stuur snelheid met batchgrootte en pauze.','ultracache-pro'), steps: [__('Cache vooraf opbouwen','ultracache-pro'), __('Link preload','ultracache-pro'), __('Uitsluitingen','ultracache-pro'), __('Serverbelasting','ultracache-pro')]},
            advanced: {title: __('Regels overzicht','ultracache-pro'), text: __('Gebruik deze pagina voor cache-uitzonderingen, cookies, query parameters, purge-regels en CDN-basisinstellingen. Laat velden leeg wanneer je geen specifieke regel nodig hebt.','ultracache-pro'), steps: [__('URL’s en cookies','ultracache-pro'), __('Query strings','ultracache-pro'), __('CDN basis','ultracache-pro')]},
            database: {title: __('Database onderhoud','ultracache-pro'), text: __('Ruim alleen gegevens op die je bewust hebt geselecteerd. Verlopen transients zijn meestal veilig; revisies, prullenbak, alle transients en tabeloptimalisatie vragen meer voorzichtigheid.','ultracache-pro'), steps: [__('Veilig eerst','ultracache-pro'), __('Berichten','ultracache-pro'), __('Reacties','ultracache-pro'), __('Transients','ultracache-pro'), __('Tabellen','ultracache-pro')]},
        };
        return data[kind] || data.optimization;
    }

    function SettingsIntro(props) {
        var intro = settingsIntro(props.kind);
        return el(Card, {className:'ucp-card ucp-settings-intro'},
            el(CardBody, {},
                el('span', {className:'ucp-eyebrow'}, __('Overzicht','ultracache-pro')),
                el('h2', {}, intro.title),
                el('p', {}, intro.text),
                el('div', {className:'ucp-step-list'}, (intro.steps || []).map(function(step){ return el('span', {key:step}, step); }))
            )
        );
    }


    function SettingsPage(props) {
        var rawGroups = settingsGroups[props.kind] || settingsGroups.optimization;
        var groups = rawGroups.map(function(group, index){
            var visibleFields = (group.fields || []).filter(function(field){ return !isManagedSetting(field[0]); });
            return Object.assign({__id:(group.key || slugify(group.title) || ('group-' + index)) + '-' + index}, group, {fields: visibleFields});
        }).filter(function(group){ return group.fields && group.fields.length; });
        var ids = groups.map(function(group){ return group.__id; });
        var columns = 1;
        var layoutAnnouncement = '';
        var groupsById = {};
        groups.forEach(function(group){ groupsById[group.__id] = group; });
        var orderedGroups = groups;
        return el('div', {className:'ucp-settings-page ucp-settings-page--' + props.kind},
            el('div', {className:'screen-reader-text', 'aria-live':'polite'}, layoutAnnouncement),
            el('div', {className:'ucp-layout-grid ucp-layout-grid--settings ucp-layout-grid--simple', style:{'--ucp-grid-columns': columns}}, orderedGroups.map(function(group){
                return el(Card, {
                    key:group.__id,
                    className:'ucp-card ucp-layout-card ucp-settings-card'
                },
                    el(CardHeader, {},
                        el('div', {className:'ucp-layout-card__header'},
                            el('div', {className:'ucp-layout-card__title-wrap'},
                                el('h2', {}, group.title)
                            )
                        )
                    ),
                    el(CardBody, {}, group.fields.map(function(field){
                        return el(SettingField,{key:field[0], field:field, kind:props.kind, settings:props.settings, status:props.status || {}, setSettings:props.setSettings, addNotice:props.addNotice, setStatus:props.setStatus});
                    }))
                );
            }))
        );
    }

    function htmlOptimizationMode(settings) {
        settings = settings || {};
        if (parseInt(settings.enable_html_minify || 0, 10)) return 'minify';
        if (parseInt(settings.remove_html_comments || 0, 10)) return 'comments';
        return 'off';
    }
    function applyHtmlOptimizationMode(target, mode) {
        target.remove_html_comments = mode === 'comments' || mode === 'minify' ? 1 : 0;
        target.enable_html_minify = mode === 'minify' ? 1 : 0;
    }
    function imageOptimizationMode(settings) {
        settings = settings || {};
        if (parseInt(settings.enable_avif_generation || 0, 10)) return 'webp_avif';
        if (parseInt(settings.enable_webp_generation || 0, 10)) return 'webp';
        if (parseInt(settings.enable_image_optimization || 0, 10)) return 'optimize';
        return 'off';
    }
    function applyImageOptimizationMode(target, mode) {
        target.enable_image_optimization = mode === 'optimize' || mode === 'webp' || mode === 'webp_avif' ? 1 : 0;
        target.enable_webp_generation = mode === 'webp' || mode === 'webp_avif' ? 1 : 0;
        target.enable_avif_generation = mode === 'webp_avif' ? 1 : 0;
    }

    function delayJsMode(settings) {
        settings = settings || {};
        if (!parseInt(settings.enable_delay_js || 0, 10)) return 'off';
        if (parseInt(settings.delay_js_safe_mode || 0, 10)) return 'safe';
        return settings.delay_js_mode === 'all' ? 'all' : 'specified';
    }
    function applyDelayJsMode(target, mode) {
        target.enable_delay_js = mode === 'off' ? 0 : 1;
        if (mode === 'specified') {
            target.delay_js_mode = 'specified';
            target.delay_js_safe_mode = 0;
        } else if (mode === 'all') {
            target.delay_js_mode = 'all';
            target.delay_js_safe_mode = 0;
        } else if (mode === 'safe') {
            target.delay_js_mode = 'all';
            target.delay_js_safe_mode = 1;
            target.delay_js_disable_click_delay = 1;
        }
    }
    function mediaLazyloadMode(settings) {
        settings = settings || {};
        if (parseInt(settings.enable_lazy_youtube_preview || 0, 10)) return 'youtube';
        if (parseInt(settings.enable_lazy_iframes || 0, 10)) return 'iframes';
        if (parseInt(settings.enable_lazy_images || 0, 10)) return 'images';
        return 'off';
    }
    function applyMediaLazyloadMode(target, mode) {
        target.enable_lazy_images = mode === 'images' || mode === 'iframes' || mode === 'youtube' ? 1 : 0;
        target.enable_lazy_iframes = mode === 'iframes' || mode === 'youtube' ? 1 : 0;
        target.enable_lazy_youtube_preview = mode === 'youtube' ? 1 : 0;
    }
    function lcpImageMode(settings) {
        settings = settings || {};
        var preload = parseInt(settings.preload_critical_images || 0, 10) || 0;
        var protect = parseInt(settings.lazyload_exclude_leading_images || 0, 10) || 0;
        if (preload === 0 && protect === 0) return 'off';
        if (preload === 0 && protect === 1) return 'protect_hero';
        if (preload === 1 && protect === 1) return 'preload_hero';
        if (preload === 2 && protect === 4) return 'recommended';
        return 'custom';
    }
    function applyLcpImageMode(target, mode) {
        if (mode === 'off') {
            target.preload_critical_images = 0;
            target.lazyload_exclude_leading_images = 0;
        } else if (mode === 'protect_hero') {
            target.preload_critical_images = 0;
            target.lazyload_exclude_leading_images = 1;
        } else if (mode === 'preload_hero') {
            target.preload_critical_images = 1;
            target.lazyload_exclude_leading_images = 1;
        } else if (mode === 'recommended') {
            target.preload_critical_images = 2;
            target.lazyload_exclude_leading_images = 4;
        }
    }
    function googleFontsMode(settings) {
        settings = settings || {};
        if (parseInt(settings.enable_disable_google_fonts || 0, 10)) return 'disable';
        if (parseInt(settings.enable_local_google_fonts || 0, 10)) return 'local';
        if (parseInt(settings.enable_font_display_swap || 0, 10)) return 'swap';
        return 'standard';
    }
    function applyGoogleFontsMode(target, mode) {
        target.enable_disable_google_fonts = mode === 'disable' ? 1 : 0;
        target.enable_local_google_fonts = mode === 'local' ? 1 : 0;
        target.enable_font_display_swap = mode === 'swap' || mode === 'local' ? 1 : 0;
    }
    // Bloat removal — one control instead of ~12 individual WordPress-cleanup toggles. Emoji and
    // embed cleanup are handled automatically by the managed layer and are intentionally not listed
    // here, so this control only touches user-decidable cleanup.
    var BLOAT_SAFE_KEYS = ['enable_disable_dashicons','enable_hide_wp_version','enable_remove_rsd_link','enable_remove_shortlink','enable_remove_rss_feed_links','enable_remove_rest_api_links','enable_disable_self_pingbacks'];
    var BLOAT_AGGRESSIVE_KEYS = ['enable_disable_jquery_migrate','enable_disable_xmlrpc','enable_disable_rss_feeds','enable_remove_global_styles','enable_remove_query_strings'];
    function bloatRemovalMode(settings) {
        settings = settings || {};
        var anyAggressive = BLOAT_AGGRESSIVE_KEYS.some(function(k){ return parseInt(settings[k] || 0, 10); });
        if (anyAggressive) return 'aggressive';
        var anySafe = BLOAT_SAFE_KEYS.some(function(k){ return parseInt(settings[k] || 0, 10); });
        return anySafe ? 'safe' : 'off';
    }
    function applyBloatRemovalMode(target, mode) {
        BLOAT_SAFE_KEYS.forEach(function(k){ target[k] = (mode === 'safe' || mode === 'aggressive') ? 1 : 0; });
        BLOAT_AGGRESSIVE_KEYS.forEach(function(k){ target[k] = (mode === 'aggressive') ? 1 : 0; });
    }
    function preloadMode(settings) {
        settings = settings || {};
        if (!parseInt(settings.enable_preload || 0, 10)) return 'off';
        if (parseInt(settings.enable_preload_queue || 0, 10) && parseInt(settings.preload_sitemaps || 0, 10) && parseInt(settings.preload_homepage || 0, 10)) return 'recommended';
        if (parseInt(settings.enable_preload_queue || 0, 10) && !parseInt(settings.preload_sitemaps || 0, 10) && parseInt(settings.preload_homepage || 0, 10)) return 'homepage';
        return 'manual';
    }
    function applyPreloadMode(target, mode) {
        if (mode === 'off') {
            target.enable_preload = 0;
            target.enable_preload_queue = 0;
            target.preload_sitemaps = 0;
            target.preload_homepage = 0;
        } else if (mode === 'recommended') {
            target.enable_preload = 1;
            target.enable_preload_queue = 1;
            target.preload_sitemaps = 1;
            target.preload_homepage = 1;
        } else if (mode === 'homepage') {
            target.enable_preload = 1;
            target.enable_preload_queue = 1;
            target.preload_sitemaps = 0;
            target.preload_homepage = 1;
        } else if (mode === 'manual') {
            target.enable_preload = 1;
        }
    }
    function staleCacheMode(settings) {
        settings = settings || {};
        if (!parseInt(settings.enable_stale_cache || 0, 10)) return 'off';
        var hours = parseInt(settings.stale_cache_lifespan || 0, 10) || 24;
        if ([6,12,24,48].indexOf(hours) !== -1) return String(hours);
        return '24';
    }
    function applyStaleCacheMode(target, mode) {
        target.enable_stale_cache = mode === 'off' ? 0 : 1;
        if (mode !== 'off') target.stale_cache_lifespan = parseInt(mode || 24, 10) || 24;
    }

    function queryStringCacheMode(settings) {
        settings = settings || {};
        return parseInt(settings.cache_query_strings || 0, 10) ? 'allow_list' : 'off';
    }
    function applyQueryStringCacheMode(target, mode) {
        target.cache_query_strings = mode === 'allow_list' ? 1 : 0;
    }
    function speculativeLoadingMode(settings) {
        settings = settings || {};
        if (['core','enhanced','prerender','off'].indexOf(settings.speculative_loading_mode) !== -1) return settings.speculative_loading_mode;
        if (!parseInt(settings.enable_speculative_loading || 0, 10)) return 'core';
        return settings.speculation_mode === 'prerender' ? 'prerender' : 'enhanced';
    }
    function applySpeculativeLoadingMode(target, mode) {
        mode = ['core','enhanced','prerender','off'].indexOf(mode) !== -1 ? mode : 'core';
        target.speculative_loading_mode = mode;
        if (mode === 'off' || mode === 'core') {
            target.enable_speculative_loading = 0;
            target.speculation_mode = 'prefetch';
            target.speculation_eagerness = 'conservative';
            return;
        }
        target.enable_speculative_loading = 1;
        if (mode === 'prerender') {
            target.speculation_mode = 'prerender';
            target.speculation_eagerness = 'conservative';
        } else {
            target.speculation_mode = 'prefetch';
            target.speculation_eagerness = 'conservative';
        }
    }
    function cdnRewriteMode(settings) {
        settings = settings || {};
        if (!parseInt(settings.enable_cdn || 0, 10)) return 'off';
        return ['css_js','images','all'].indexOf(settings.cdn_file_types) !== -1 ? settings.cdn_file_types : 'all';
    }
    function applyCdnRewriteMode(target, mode) {
        target.enable_cdn = mode === 'off' ? 0 : 1;
        if (mode !== 'off') target.cdn_file_types = ['css_js','images','all'].indexOf(mode) !== -1 ? mode : 'all';
    }
    function browserCacheMode(settings) {
        settings = settings || {};
        if (!parseInt(settings.browser_cache_headers || 0, 10)) return 'off';
        var age = parseInt(settings.cache_control_max_age || 0, 10) || 31536000;
        if (age === 2592000) return '30d';
        if (age === 15552000) return '180d';
        if (age === 31536000) return '365d';
        return 'custom';
    }
    function applyBrowserCacheMode(target, mode) {
        target.browser_cache_headers = mode === 'off' ? 0 : 1;
        if (mode === '30d') target.cache_control_max_age = 2592000;
        if (mode === '180d') target.cache_control_max_age = 15552000;
        if (mode === '365d') target.cache_control_max_age = 31536000;
    }

    function heartbeatControlValue(settings) {
        settings = settings || {};
        return (settings.heartbeat_frontend_behavior === 'keep' && settings.heartbeat_editor_behavior === 'keep' && settings.heartbeat_backend_behavior === 'keep') ? 0 : 1;
    }

    function heartbeatIntervalMode(settings) {
        settings = settings || {};
        var frontend = parseInt(settings.heartbeat_frontend_frequency || 0, 10) || 60;
        var editor = parseInt(settings.heartbeat_editor_frequency || 0, 10) || 30;
        var backend = parseInt(settings.heartbeat_backend_frequency || 0, 10) || 60;
        if (frontend === editor && editor === backend && [30,60,120].indexOf(frontend) !== -1) return String(frontend);
        return 'custom';
    }
    function applyHeartbeatIntervalMode(target, mode) {
        if (mode === 'custom') {
            target.heartbeat_frontend_frequency = 60;
            target.heartbeat_editor_frequency = 30;
            target.heartbeat_backend_frequency = 60;
            target.heartbeat_frequency = 60;
            return;
        }
        var interval = parseInt(mode || 0, 10);
        if ([30,60,120].indexOf(interval) === -1) return;
        target.heartbeat_frontend_frequency = interval;
        target.heartbeat_editor_frequency = interval;
        target.heartbeat_backend_frequency = interval;
        target.heartbeat_frequency = interval;
    }

    function isSensitiveSetting(key){
        return ['cloud_api_key','cloudflare_api_token','secret_cache_key','css_cache_key','js_cache_key','headless_renderer_token','bunny_api_key','cdn_purge_webhook_token'].indexOf(String(key || '')) !== -1;
    }

    // Single source of truth for UX-only "mode" controls. Each maps a friendly select value to a
    // group of stored option keys via derive (stored flags -> mode value) and apply (mode value ->
    // stored flags). Add a control here once so currentSettingValue,
    // applySettingProjection and buildSettingPayload stay aligned.
    // Keys with bespoke projection (css_delivery_mode, db_cleanup_frequency, heartbeat_* behaviors)
    // are intentionally handled inline below rather than in this registry.
    var COMBINED_CONTROLS = {
        html_optimization_mode:   { derive: htmlOptimizationMode,   apply: applyHtmlOptimizationMode },
        image_optimization_mode:  { derive: imageOptimizationMode,  apply: applyImageOptimizationMode },
        lcp_image_mode:           { derive: lcpImageMode,           apply: applyLcpImageMode },
        delay_js_control:         { derive: delayJsMode,            apply: applyDelayJsMode },
        media_lazyload_mode:      { derive: mediaLazyloadMode,      apply: applyMediaLazyloadMode },
        google_fonts_mode:        { derive: googleFontsMode,        apply: applyGoogleFontsMode },
        preload_mode:             { derive: preloadMode,            apply: applyPreloadMode },
        stale_cache_mode:         { derive: staleCacheMode,         apply: applyStaleCacheMode },
        query_string_cache_mode:  { derive: queryStringCacheMode,   apply: applyQueryStringCacheMode },
        speculative_loading_mode: { derive: speculativeLoadingMode, apply: applySpeculativeLoadingMode },
        cdn_rewrite_mode:         { derive: cdnRewriteMode,         apply: applyCdnRewriteMode },
        browser_cache_mode:       { derive: browserCacheMode,       apply: applyBrowserCacheMode },
        heartbeat_interval_mode:  { derive: heartbeatIntervalMode,  apply: applyHeartbeatIntervalMode },
        bloat_removal_mode:       { derive: bloatRemovalMode,       apply: applyBloatRemovalMode }
    };

    function currentSettingValue(key, settings) {
        settings = settings || {};
        var currentValue = Object.prototype.hasOwnProperty.call(settings, key) ? settings[key] : '';
        if (COMBINED_CONTROLS[key]) return COMBINED_CONTROLS[key].derive(settings);
        return currentValue;
    }

    function applySettingProjection(target, key, value) {
        target[key] = value;
        if (key === 'db_cleanup_frequency') target.enable_db_cleanup = value === 'off' ? 0 : 1;
        if (COMBINED_CONTROLS[key]) COMBINED_CONTROLS[key].apply(target, value);
        if (key === 'heartbeat_frontend_behavior' || key === 'heartbeat_editor_behavior' || key === 'heartbeat_backend_behavior') {
            target.enable_heartbeat_control = heartbeatControlValue(target);
        }
        return target;
    }

    function buildSettingPayload(key, value, projectedSettings) {
        var payload = {};
        payload[key] = value;
        if (COMBINED_CONTROLS[key]) { payload = {}; COMBINED_CONTROLS[key].apply(payload, value); }
        if (key === 'css_delivery_mode') {
            payload.enable_used_css = value === 'remove_unused' ? 1 : 0;
            payload.enable_used_css_delivery = value === 'remove_unused' ? 1 : 0;
            payload.enable_critical_css = value === 'async' ? 1 : 0;
            payload.enable_css_queue = value === 'none' ? 0 : 1;
            if (value !== 'none') payload.enable_css_combine = 0;
        }
        if (key === 'db_cleanup_frequency') payload.enable_db_cleanup = value === 'off' ? 0 : 1;
        if (key === 'heartbeat_frontend_behavior' || key === 'heartbeat_editor_behavior' || key === 'heartbeat_backend_behavior') {
            payload.enable_heartbeat_control = heartbeatControlValue(projectedSettings || {});
        }
        return payload;
    }


    function LcpImagesControl(props) {
        var lcpDraftState = useState({
            preload:parseInt((props.settings || {}).preload_critical_images || 0, 10),
            protect:parseInt((props.settings || {}).lazyload_exclude_leading_images || 0, 10)
        }), lcpDraft = lcpDraftState[0], setLcpDraft = lcpDraftState[1];
        useEffect(function(){ setLcpDraft({preload:parseInt((props.settings || {}).preload_critical_images || 0, 10), protect:parseInt((props.settings || {}).lazyload_exclude_leading_images || 0, 10)}); }, [(props.settings || {}).preload_critical_images, (props.settings || {}).lazyload_exclude_leading_images]);

        function commitCustomLcp(){
            var previousSettings = Object.assign({}, props.settings || {});
            var payload = {preload_critical_images:parseInt(lcpDraft.preload || 0,10), lazyload_exclude_leading_images:parseInt(lcpDraft.protect || 0,10)};
            var next = Object.assign({}, previousSettings, payload);
            props.setSettings(next); props.setSaving(true); props.setDirty(false);
            saveSettings(payload).then(function(resp){
                props.setSettings(resp.settings);
                if (resp.status && props.setStatus) props.setStatus(resp.status);
                props.addNotice({status:'success', message:props.label + ' opgeslagen.'});
            }).catch(function(err){
                props.setSettings(previousSettings);
                props.addNotice({status:'error', message:cleanErrorMessage(err, props.label + ' kon niet worden opgeslagen.')});
            }).finally(function(){ props.setSaving(false); });
        }

        return el('div', {className:'ucp-setting-field-control'},
            el(SelectControl,{label:props.label, help:props.help, value:props.currentValue || 'recommended', options:(props.options || []).map(function(o){return {value:o[0], label:o[1]};}), disabled:props.saving, onChange:function(v){props.commit(v);}}),
            props.currentValue === 'custom' ? el('div', {className:'ucp-setting-field-custom'},
                el(NumberControl,{label:__('Kritieke afbeeldingen preloaden','ultracache-pro'), help:__('Aantal zichtbare boven-de-vouw afbeeldingen. Maximaal 3.','ultracache-pro'), value:lcpDraft.preload, min:0, max:3, disabled:props.saving, onChange:function(v){setLcpDraft(Object.assign({}, lcpDraft, {preload:v})); props.setDirty(true);}}),
                el(NumberControl,{label:__('Bovenste afbeeldingen niet lazyloaden','ultracache-pro'), help:__('Aantal eerste afbeeldingen dat niet lazyloadt. Maximaal 5.','ultracache-pro'), value:lcpDraft.protect, min:0, max:5, disabled:props.saving, onChange:function(v){setLcpDraft(Object.assign({}, lcpDraft, {protect:v})); props.setDirty(true);}}),
                props.dirty ? el(Button,{variant:'secondary', isBusy:props.saving, disabled:props.saving, onClick:commitCustomLcp},__('Aangepaste LCP-instellingen opslaan','ultracache-pro')) : null
            ) : null,
            el('p', {className:'ucp-muted'}, __('Aanbevolen voorkomt dat hero/LCP-afbeeldingen per ongeluk lazyloaden en zet alleen beperkte preloads klaar.','ultracache-pro'))
        );
    }

    function HeartbeatIntervalControl(props) {
        var intervalDraftState = useState({
            frontend:parseInt((props.settings || {}).heartbeat_frontend_frequency || 60, 10),
            editor:parseInt((props.settings || {}).heartbeat_editor_frequency || 30, 10),
            backend:parseInt((props.settings || {}).heartbeat_backend_frequency || 60, 10)
        }), intervalDraft = intervalDraftState[0], setIntervalDraft = intervalDraftState[1];
        useEffect(function(){ setIntervalDraft({frontend:parseInt((props.settings || {}).heartbeat_frontend_frequency || 60, 10), editor:parseInt((props.settings || {}).heartbeat_editor_frequency || 30, 10), backend:parseInt((props.settings || {}).heartbeat_backend_frequency || 60, 10)}); }, [(props.settings || {}).heartbeat_frontend_frequency, (props.settings || {}).heartbeat_editor_frequency, (props.settings || {}).heartbeat_backend_frequency]);

        function commitCustomIntervals(){
            var previousSettings = Object.assign({}, props.settings || {});
            var payload = {heartbeat_frontend_frequency:parseInt(intervalDraft.frontend || 60,10), heartbeat_editor_frequency:parseInt(intervalDraft.editor || 30,10), heartbeat_backend_frequency:parseInt(intervalDraft.backend || 60,10)};
            payload.heartbeat_frequency = payload.heartbeat_backend_frequency;
            var next = Object.assign({}, previousSettings, payload);
            props.setSettings(next); props.setSaving(true); props.setDirty(false);
            saveSettings(payload).then(function(resp){
                props.setSettings(resp.settings);
                if (resp.status && props.setStatus) props.setStatus(resp.status);
                props.addNotice({status:'success', message:props.label + ' opgeslagen.'});
            }).catch(function(err){
                props.setSettings(previousSettings);
                props.addNotice({status:'error', message:cleanErrorMessage(err, props.label + ' kon niet worden opgeslagen.')});
            }).finally(function(){ props.setSaving(false); });
        }

        return el('div', {className:'ucp-setting-field-control'},
            el(SelectControl,{label:props.label, help:props.help, value:props.currentValue || 'custom', options:(props.options || []).map(function(o){return {value:o[0], label:o[1]};}), disabled:props.saving, onChange:function(v){props.commit(v);}}),
            props.currentValue === 'custom' ? el('div', {className:'ucp-setting-field-custom'},
                el(NumberControl,{label:__('Frontend interval','ultracache-pro'), help:__('Seconden bij Verminderen.','ultracache-pro'), value:intervalDraft.frontend, min:15, max:300, disabled:props.saving, onChange:function(v){setIntervalDraft(Object.assign({}, intervalDraft, {frontend:v})); props.setDirty(true);}}),
                el(NumberControl,{label:__('Editor interval','ultracache-pro'), help:__('Seconden bij Verminderen.','ultracache-pro'), value:intervalDraft.editor, min:15, max:300, disabled:props.saving, onChange:function(v){setIntervalDraft(Object.assign({}, intervalDraft, {editor:v})); props.setDirty(true);}}),
                el(NumberControl,{label:__('Backend interval','ultracache-pro'), help:__('Seconden bij Verminderen.','ultracache-pro'), value:intervalDraft.backend, min:15, max:300, disabled:props.saving, onChange:function(v){setIntervalDraft(Object.assign({}, intervalDraft, {backend:v})); props.setDirty(true);}}),
                props.dirty ? el(Button,{variant:'secondary', isBusy:props.saving, disabled:props.saving, onClick:commitCustomIntervals},__('Aangepaste intervallen opslaan','ultracache-pro')) : null
            ) : null
        );
    }

    function SettingField(props) {
        var key = props.field[0], label = props.field[1], type = props.field[2], help = props.field[3] || '', options = props.field[4] || [];
        var savingState = useState(false), saving = savingState[0], setSaving = savingState[1];
        var dirtyState = useState(false), dirty = dirtyState[0], setDirty = dirtyState[1];
        var currentValue = currentSettingValue(key, props.settings);
        var lockReason = combineLockReason(key, props.settings || {}, props.status || {});
        var isLocked = !!lockReason;
        var draftState = useState(currentValue), draft = draftState[0], setDraft = draftState[1];
        useEffect(function(){ setDraft(currentValue); setDirty(false); }, [currentValue, key]);

        function commit(newValue){
            if (isLocked) {
                props.addNotice({status:'warning', message:lockReason});
                return;
            }
            var previousSettings = Object.assign({}, props.settings || {});
            var next = applySettingProjection(Object.assign({}, previousSettings), key, newValue);
            props.setSettings(next); setSaving(true); setDirty(false);
            var payload = buildSettingPayload(key, newValue, next);
            saveSettings(payload).then(function(resp){
                props.setSettings(resp.settings);
                if (resp.status && props.setStatus) props.setStatus(resp.status);
                props.addNotice({status:'success', message:label + ' opgeslagen.'});
            }).catch(function(err){
                props.setSettings(previousSettings);
                setDraft(previousSettings[key]);
                props.addNotice({status:'error', message:cleanErrorMessage(err, label + ' kon niet worden opgeslagen.')});
            }).finally(function(){ setSaving(false); });
        }
        var warning = isLocked ? el(Notice,{status:'info', isDismissible:false}, lockReason) : (riskText(key) ? el(Notice,{status:'warning', isDismissible:false}, riskText(key)) : null);
        var badge = el(RiskBadge, {settingKey:key});
        var control;
        if (type === 'toggle') {
            control = el(ToggleControl,{label:label, help:help, checked:!!parseInt(currentValue || 0,10), disabled:saving || isLocked, onChange:function(v){commit(v ? 1 : 0);}});
        } else if (type === 'textarea') {
            control = el(Fragment, {}, el(TextareaControl,{label:label, help:help, value:draft || '', disabled:saving, onChange:function(v){setDraft(v); setDirty(true);}}), dirty ? el(Button,{variant:'secondary', isBusy:saving, disabled:saving, onClick:function(){commit(draft || '');}},__('Opslaan','ultracache-pro')) : null);
        } else if (type === 'number') {
            control = el(Fragment, {}, el(NumberControl,{label:label, help:help, value:draft || 0, disabled:saving, onChange:function(v){setDraft(v); setDirty(true);}}), dirty ? el(Button,{variant:'secondary', isBusy:saving, disabled:saving, onClick:function(){commit(parseInt(draft || 0,10));}},__('Opslaan','ultracache-pro')) : null);
        } else if (type === 'select') {
            control = el(SelectControl,{label:label, help:help, value:currentValue || (options[0] ? options[0][0] : ''), options:options.map(function(o){return {value:o[0], label:o[1]};}), disabled:saving, onChange:function(v){commit(v);}});
        } else if (type === 'lcp_images') {
            control = el(LcpImagesControl,{label:label, help:help, currentValue:currentValue, options:options, settings:props.settings, saving:saving, dirty:dirty, setDirty:setDirty, setSaving:setSaving, setSettings:props.setSettings, setStatus:props.setStatus, addNotice:props.addNotice, commit:commit});
        } else if (type === 'heartbeat_interval') {
            control = el(HeartbeatIntervalControl,{label:label, help:help, currentValue:currentValue, options:options, settings:props.settings, saving:saving, dirty:dirty, setDirty:setDirty, setSaving:setSaving, setSettings:props.setSettings, setStatus:props.setStatus, addNotice:props.addNotice, commit:commit});
        } else if (type === 'css_delivery') {
            control = el(CssDeliveryControl,{label:label, help:help, value:currentValue || 'none', options:options, saving:saving, onChange:commit});
        } else {
            control = el(Fragment, {}, el(TextControl,{label:label, help:help, type:isSensitiveSetting(key) ? 'password' : 'text', autoComplete:isSensitiveSetting(key) ? 'new-password' : 'off', value:draft || '', disabled:saving, onChange:function(v){setDraft(v); setDirty(true);}}), dirty ? el(Button,{variant:'secondary', isBusy:saving, disabled:saving, onClick:function(){commit(draft || '');}},__('Opslaan','ultracache-pro')) : null);
        }
        var layoutClass = isCompactSettingField(props.field, props.kind) ? ' is-rowable' : ' is-stacked';
        return el('div',{className:'ucp-setting-field ucp-setting-field--' + type + layoutClass, 'data-ucp-field-key':key}, badge, control, warning, saving ? el('span',{className:'ucp-saving-text'},__('Opslaan…','ultracache-pro')) : null);
    }


    function CssDeliveryControl(props) {
        var options = props.options || [];
        var value = props.value || 'none';
        var details = {
            none: { tone: 'info', title: __('Veiligste keuze','ultracache-pro'), text: __('Laat CSS normaal laden. Gebruik dit voor maximale compatibiliteit of als de layout gevoelig is.','ultracache-pro') },
            remove_unused: { tone: 'warning', title: __('Staging-first optimalisatie','ultracache-pro'), text: __('Gebruikt de AST-parser wanneer vendor libraries aanwezig zijn en valt anders terug op de lokale parser. Test builders, formulieren en checkout visueel.','ultracache-pro') },
            async: { tone: 'warning', title: __('Fallback als Used CSS problemen geeft','ultracache-pro'), text: __('Genereert Critical CSS en laadt de overige CSS asynchroon. Minder agressief dan Used CSS, maar nog steeds visueel testen.','ultracache-pro') }
        };
        var current = details[value] || details.none;
        return el('div', {className:'ucp-css-delivery-control'},
            el(SelectControl,{label:props.label, help:props.help, value:value, options:options.map(function(o){return {value:o[0], label:o[1]};}), disabled:props.saving, onChange:function(v){props.onChange(v);}}),
            el('div', {className:'ucp-css-delivery-summary ucp-css-delivery-summary--' + current.tone},
                el('strong', {}, current.title),
                el('p', {}, current.text),
                el('p', {className:'ucp-muted'}, __('UltraCache zet de onderliggende Used CSS/Critical CSS flags automatisch goed. Je hoeft hiervoor geen losse toggles meer te beheren.','ultracache-pro'))
            )
        );
    }

    function combineLockReason(key, settings, status){
        var system = (status && status.system) || {};
        var locks = system.combineLocks || {};
        var reasons = [];
        if (key === 'enable_css_combine') {
            reasons = locks.css || [];
            if (!reasons.length && settings.css_delivery_mode && settings.css_delivery_mode !== 'none') {
                reasons.push('CSS-levering optimaliseren is actief. CSS combineren is daarom vergrendeld.');
            }
        }
        if (key === 'enable_js_combine') {
            reasons = locks.js || [];
            if (!reasons.length && parseInt(settings.enable_delay_js || 0, 10)) {
                reasons.push('Delay JS is actief. JavaScript combineren wordt vergrendeld om de uitvoervolgorde te beschermen.');
            }
            if (!reasons.length && (parseInt(settings.defer_all_js || 0, 10) || parseInt(settings.enable_native_script_strategy || 0, 10))) {
                reasons.push('Er is al een script-laadstrategie actief. JavaScript combineren is daarom vergrendeld.');
            }
        }
        return reasons.length ? reasons[0] : '';
    }

    function riskMeta(key){
        var map = {
            enable_delay_js:{level:'staging', label:__('Staging-first','ultracache-pro'), text:__('Kan formulieren, sliders of checkout beïnvloeden. Test dit eerst op staging.','ultracache-pro')},
            enable_css_combine:{level:'staging', label:__('Staging-first','ultracache-pro'), text:__('Alleen gebruiken op eenvoudige HTTP/1-sites zonder builder/shop/formulier-conflicten.','ultracache-pro')},
            enable_js_combine:{level:'staging', label:__('Staging-first','ultracache-pro'), text:__('Alleen gebruiken op eenvoudige HTTP/1-sites zonder builder/shop/formulier-conflicten.','ultracache-pro')},
            css_delivery_mode:{level:'staging', label:__('Staging-first','ultracache-pro'), text:__('Controleer layout, builders, formulieren en checkout visueel na inschakelen.','ultracache-pro')},
            delay_js_control:{level:'staging', label:__('Staging-first','ultracache-pro'), text:__('Test formulieren, sliders, cookie banners en checkout voordat je dit live gebruikt.','ultracache-pro')},
            enable_rest_cache:{level:'staging', label:__('API-gevoelig','ultracache-pro'), text:__('Controleer API-koppelingen en formulieren na inschakelen.','ultracache-pro')},
            enable_esi:{level:'staging', label:__('Personalisatiegevoelig','ultracache-pro'), text:__('Custom fragments mogen geen persoonlijke data in gedeelde cache lekken.','ultracache-pro')},
            enable_headless_renderer:{level:'external', label:__('Extern endpoint','ultracache-pro'), text:__('Renderer-output is een trust boundary. Test endpoint, timeout en foutafhandeling.','ultracache-pro')},
            headless_renderer_endpoint:{level:'external', label:__('Extern endpoint','ultracache-pro'), text:__('Gebruik alleen publieke HTTPS endpoints die je vertrouwt.','ultracache-pro')},
            cdn_rewrite_mode:{level:'staging', label:__('Staging-first','ultracache-pro'), text:__('Controleer statische assets, fonts, afbeeldingen en checkout na CDN rewrite.','ultracache-pro')},
            enable_self_host_third_party_assets:{level:'external', label:__('Externe bronnen','ultracache-pro'), text:__('Controleer privacy, bronallowlist en visuele output na lokaal hosten.','ultracache-pro')},
            serve_cache_to_shoppers:{level:'shop', label:__('Shopgevoelig','ultracache-pro'), text:__('Alleen gebruiken als cart, checkout, account en sessiecookies aantoonbaar veilig zijn uitgesloten.','ultracache-pro')},
            db_cleanup_all_transients:{level:'destructive', label:__('Destructief','ultracache-pro'), text:__('Maak eerst een database-backup. Deze actie kan niet ongedaan worden gemaakt.','ultracache-pro')},
            db_cleanup_optimize_tables:{level:'destructive', label:__('Backup nodig','ultracache-pro'), text:__('Maak eerst een database-backup. Deze actie kan niet ongedaan worden gemaakt.','ultracache-pro')},
            clean_uninstall:{level:'destructive', label:__('Destructief','ultracache-pro'), text:__('Verwijdert plugininstellingen bij deïnstallatie. Controleer dit bewust.','ultracache-pro')},
            enable_cloudflare_apo_mode:{level:'external', label:__('CDN-gevoelig','ultracache-pro'), text:__('Gebruik alleen als Cloudflare correct is geconfigureerd.','ultracache-pro')}
        };
        return map[key] || null;
    }

    function riskText(key){
        var meta = riskMeta(key);
        return meta ? meta.text : '';
    }

    function RiskBadge(props){
        var meta = riskMeta(props.settingKey);
        if (!meta) { return null; }
        return el('span', {className:'ucp-risk-badge ucp-risk-badge--' + meta.level}, meta.label);
    }


    function ToolsPage(props) {
        return el(Fragment, {},
            el(SettingsPage, Object.assign({}, props, {kind:'diagnostics'})),
            el(ActionsPage, Object.assign({}, props, {title:__('Tools','ultracache-pro')}))
        );
    }

    function LoadingScreen(){
        return el('div', {className:'ucp-loading-screen', role:'status', 'aria-live':'polite', 'aria-busy':'true'},
            el('div', {className:'ucp-loading-card'},
                el('div', {className:'ucp-loading-mark', 'aria-hidden':'true'},
                    el('span', {className:'ucp-loading-ring'}),
                    el('span', {className:'ucp-loading-dot'})
                ),
                el('div', {className:'ucp-loading-copy'},
                    el('h1', {}, __('UltraCache Pro wordt geladen','ultracache-pro')),
                    el('p', {}, __('We laden je cache-instellingen en websitecontrole. Dit duurt meestal maar even.','ultracache-pro'))
                )
            )
        );
    }

    function App(){
        var tabState = useState(initialTabFromLocation), activeTab = tabState[0], setActiveTab = tabState[1];
        var statusState = useState(null), status = statusState[0], setStatus = statusState[1];
        var settingsState = useState(null), settings = settingsState[0], setSettings = settingsState[1];
        var loadingState = useState(true), loading = loadingState[0], setLoading = loadingState[1];
        var noticesState = useState([]), notices = noticesState[0], setNotices = noticesState[1];
        var wizardState = useState(false), wizardOpen = wizardState[0], setWizardOpen = wizardState[1];
        function addNotice(n){
            var notice = Object.assign({id:Date.now()+Math.random()}, n);
            setNotices(function(cur){ return cur.concat([notice]); });
            if (notice.status === 'success') {
                window.setTimeout(function(){ removeNotice(notice.id); }, 4500);
            }
        }
        function removeNotice(id){ setNotices(function(cur){ return cur.filter(function(n){return n.id !== id;}); }); }
        function selectTab(tab){
            var normalized = normalizeTabKey(tab);
            setActiveTab(normalized);
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('page', 'ultracache-pro');
                url.searchParams.set('tab', normalized === 'dashboard' ? 'overview' : normalized);
                window.history.replaceState(null, '', url.toString());
            } catch (e) {}
        }
        function refresh(){ setLoading(true); Promise.all([getStatus(), getSettings()]).then(function(res){ setStatus(res[0].status); setSettings(res[1].settings); }).catch(function(err){ addNotice({status:'error', message:cleanErrorMessage(err, 'UltraCache data kon niet geladen worden.')}); }).finally(function(){ setLoading(false); }); }
        useEffect(function(){ refresh(); }, []);
        if (loading && !settings) return el(LoadingScreen, {});
        var uiMode = (settings && settings.ui_mode === 'advanced') ? 'advanced' : 'simple';
        var effectiveTab = (uiMode === 'advanced' || SIMPLE_TABS.indexOf(activeTab) !== -1) ? activeTab : 'dashboard';
        function toggleUiMode(mode){
            var next = mode === 'advanced' ? 'advanced' : 'simple';
            setSettings(Object.assign({}, settings || {}, {ui_mode:next}));
            if (next !== 'advanced') selectTab('dashboard');
            saveSettings({ui_mode:next}).then(function(resp){ if (resp && resp.settings) setSettings(resp.settings); }).catch(function(err){ addNotice({status:'error', message:cleanErrorMessage(err, __('Kon de weergavemodus niet opslaan.','ultracache-pro'))}); });
        }
        var shared = {settings:settings || {}, setSettings:setSettings, status:status || {}, setStatus:setStatus, addNotice:addNotice, onRefresh:refresh, loading:loading, onSelectTab:selectTab, onToggleMode:toggleUiMode, uiMode:uiMode};
        return el(AdminShell,{activeTab:effectiveTab,uiMode:uiMode,onToggleMode:toggleUiMode,onTab:selectTab,onRefresh:refresh,loading:loading,onOpenWizard:function(){setWizardOpen(true);},status:status || {},addNotice:addNotice,setStatus:setStatus},
            el(NoticeArea,{notices:notices,onRemove:removeNotice}),
            wizardOpen ? el(SetupWizard,Object.assign({}, shared, {onClose:function(){setWizardOpen(false); saveSettings({onboarding_completed:1}).then(function(resp){ if(resp.settings) setSettings(resp.settings); });}})) : null,
            effectiveTab === 'dashboard' ? el(DashboardPage,Object.assign({}, shared, {status:status,onOpenWizard:function(){}})) : null,
            effectiveTab === 'cache' ? el(SettingsPage,Object.assign({}, shared, {kind:'cache'})) : null,
            effectiveTab === 'optimization' ? el(SettingsPage,Object.assign({}, shared, {kind:'optimization'})) : null,
            effectiveTab === 'media' ? el(SettingsPage,Object.assign({}, shared, {kind:'media'})) : null,
            effectiveTab === 'preload' ? el(SettingsPage,Object.assign({}, shared, {kind:'preload'})) : null,
            effectiveTab === 'advanced' ? el(SettingsPage,Object.assign({}, shared, {kind:'advanced'})) : null,
            effectiveTab === 'database' ? el(SettingsPage,Object.assign({}, shared, {kind:'database'})) : null,
            effectiveTab === 'tools' ? el(ToolsPage,Object.assign({}, shared, {status:status})) : null
        );
    }

    document.addEventListener('DOMContentLoaded', function(){
        var root = document.getElementById('ucp-admin-root');
        if (root) {
            if (typeof createRoot === 'function') {
                createRoot(root).render(el(App));
            } else {
                render(el(App), root);
            }
        }
    });
})(window.wp);
