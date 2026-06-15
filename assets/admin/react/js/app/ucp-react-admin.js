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
    var FormTokenField = c.FormTokenField || null;
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
        {key:'dashboard', label:__('Overzicht','ultracache-pro'), icon:'dashicons-dashboard'},
        {key:'cache', label:__('Cache','ultracache-pro'), icon:'dashicons-admin-generic'},
        {key:'media', label:__('Media','ultracache-pro'), icon:'dashicons-format-image'},
        {key:'woocommerce', label:__('WooCommerce','ultracache-pro'), icon:'dashicons-cart'},
        {key:'preload', label:__('Preload','ultracache-pro'), icon:'dashicons-controls-repeat'},
        {key:'optimization', label:__('CSS & JS','ultracache-pro'), icon:'dashicons-performance'},
        {key:'server', label:__('Server & CDN','ultracache-pro'), icon:'dashicons-cloud'},
        {key:'advanced', label:__('Regels','ultracache-pro'), icon:'dashicons-list-view'},
        {key:'tools', label:__('Tools','ultracache-pro'), icon:'dashicons-admin-tools'}
    ];

    var SIMPLE_TABS = ['dashboard','cache','media','woocommerce','preload'];

    function visibleTabsForMode(uiMode) {
        if (uiMode === 'advanced') {
            return tabs;
        }
        return tabs.filter(function(tab){ return SIMPLE_TABS.indexOf(tab.key) !== -1; });
    }

    function normalizeTabKey(tab) {
        var key = String(tab || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
        var map = {
            overview:'dashboard', dashboard:'dashboard',
            diagnostics:'tools', toolbox:'tools', integrations:'tools', addons:'tools', database:'tools',
            expert:'advanced', assets:'optimization', assetmanager:'optimization', 'asset-manager':'optimization', css:'optimization', js:'optimization', 'css-js':'optimization',
            cdn:'server', server:'server', objectcache:'server', 'object-cache':'server', 'server-cdn':'server',
            woo:'woocommerce', shop:'woocommerce', ecommerce:'woocommerce',
            'advanced-rules':'advanced', advanced_rules:'advanced',
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
            'ultracache-pro-cdn':'server',
            'ultracache-pro-object-cache':'server',
            'ultracache-pro-woocommerce':'woocommerce',
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

    // Actions that legitimately take longer (preload, CSS generation, browser
    // scan, queue processing) get a wider window; everything else uses a calm
    // default. The timeout is a UI safety net only: it never cancels work that
    // is already running on the server, it just stops a button from hanging.
    var ucpLongActionPattern = /actions\/(preload|used-css|critical-css|browser-scan|run-due-jobs|renderer-test|repair-cache-files|release-checklist|database-cleanup|detect-conflicts)(\/|$)/;
    function requestTimeoutMs(path) {
        return ucpLongActionPattern.test(String(path || '')) ? 90000 : 30000;
    }
    function request(path, options) {
        var cleanPath = String(path || "").replace(/^\/+/, "");
        var baseUrl = String(config.restUrl || "").replace(/\/?$/, "/");
        var opts = Object.assign({}, options || {});
        if (baseUrl) {
            opts.url = baseUrl + cleanPath;
        } else {
            opts.path = "/ultracache-pro/v1/" + cleanPath;
        }
        var controller = (typeof window.AbortController === 'function') ? new window.AbortController() : null;
        if (controller && !opts.signal) {
            opts.signal = controller.signal;
        }
        var timer = null;
        var timeout = new Promise(function(resolve, reject){
            timer = window.setTimeout(function(){
                if (controller) { try { controller.abort(); } catch (e) {} }
                reject(new Error(__('De actie duurde te lang. De taak draait mogelijk nog op de achtergrond — controleer Tools of probeer het opnieuw.','ultracache-pro')));
            }, requestTimeoutMs(cleanPath));
        });
        function clearTimer(){ if (timer) { window.clearTimeout(timer); timer = null; } }
        return Promise.race([apiFetch(opts), timeout]).then(function(resp){
            clearTimer();
            return resp;
        }, function(err){
            clearTimer();
            throw err;
        });
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
        var uiMode = props.uiMode === 'advanced' ? 'advanced' : 'simple';
        function headerModeToggle() {
            return el('div', {className:'ucp-mode-switch ucp-mode-switch--header', role:'group', 'aria-label':__('Weergavemodus','ultracache-pro')},
                el('span', {className:'ucp-mode-switch__label'}, __('Weergave','ultracache-pro')),
                el(Button, {variant:uiMode === 'simple' ? 'primary' : 'secondary', onClick:function(){ if (props.onToggleMode) { props.onToggleMode('simple'); } }}, __('Simpel','ultracache-pro')),
                el(Button, {variant:uiMode === 'advanced' ? 'primary' : 'secondary', onClick:function(){ if (props.onToggleMode) { props.onToggleMode('advanced'); } }}, __('Advanced','ultracache-pro'))
            );
        }
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
                ),
                el('div', {className:'ucp-app-header__controls'},
                    headerModeToggle(),
                    props.onOpenWizard ? el(Button, {variant:'secondary', className:'ucp-header-setup-button', onClick:props.onOpenWizard}, __('Setup openen','ultracache-pro')) : null
                )
            )
        );
    }

    function AdminShell(props) {
        var activeTab = props.activeTab || 'dashboard';
        var uiMode = props.uiMode === 'advanced' ? 'advanced' : 'simple';
        var navTabs = visibleTabsForMode(uiMode).filter(function(tab){ return tab.key !== 'woocommerce' || hasActiveWooCommerce(props.status || {}); });
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
        return el('div', {className:'ucp-admin-app__shell ucp-admin-app__shell--' + uiMode},
            el(ProductHeader, {status:props.status || {}, uiMode:uiMode, onToggleMode:props.onToggleMode, onOpenWizard:props.onOpenWizard}),
            el('nav', {className:'ucp-admin-nav', role:'tablist', 'aria-label':__('UltraCache Pro onderdelen','ultracache-pro')}, navTabs.map(function(tab){
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
            })),
            el('section', uiMode === 'advanced'
                ? {id:'ucp-admin-panel', className:'ucp-admin-panel', role:'tabpanel', 'aria-labelledby':'ucp-admin-tab-' + (activeMeta ? activeMeta.key : 'dashboard')}
                : {id:'ucp-admin-panel', className:'ucp-admin-panel', role:'region', 'aria-labelledby':'ucp-admin-page-title'}, props.children)
        );
    }

    function DashboardHero(props) {
        var status = props.status || {};
        var cache = status.cache || {}, queue = status.queue || {}, opt = status.optimization || {};
        var settings = props.settings || {};
        var totalWaiting = (parseInt(queue.pending || 0, 10) || 0) + (parseInt(queue.retrying || 0, 10) || 0);
        var cacheOn = boolSetting(settings, 'enable_cache') || !!cache.enabled;
        var lazyOn = boolSetting(settings, 'enable_lazy_images') || !!opt.lazyImages;
        var cssOn = boolSetting(settings, 'enable_css_minify') || !!opt.cssMinify;
        var jsOn = boolSetting(settings, 'enable_js_minify') || !!opt.jsMinify;
        var wooActive = hasActiveWooCommerce(status);
        var title = totalWaiting ? __('Actie nodig: taken klaar','ultracache-pro') : __('Alles draait rustig','ultracache-pro');
        var subtitle = totalWaiting ? __('Verwerk de wachtrij om cache, preload en optimalisatie bij te werken.','ultracache-pro') : __('Cache en veilige optimalisaties beschermen snelheid, checkout en formulieren.','ultracache-pro');
        var primaryAction = totalWaiting
            ? el(ActionButton, {action:'run-due-jobs', data:{maxBatches:1, dashboard:1}, label:__('Verwerk taken','ultracache-pro'), compact:true, variant:'primary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
            : el(ActionButton, {action:'purge-all', label:__('Cache verversen','ultracache-pro'), compact:true, variant:'secondary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh});
        var stats = [
            {key:'cache', icon:'dashicons-performance', label:__('Page cache','ultracache-pro'), value:cacheOn ? __('Actief','ultracache-pro') : __('Uit','ultracache-pro'), state:cacheOn ? 'good' : 'warning'},
            {key:'media', icon:'dashicons-format-image', label:__('Media','ultracache-pro'), value:lazyOn ? __('Lazyload','ultracache-pro') : __('Basis','ultracache-pro'), state:lazyOn ? 'good' : 'info'},
            {key:'css', icon:'dashicons-admin-customizer', label:__('CSS','ultracache-pro'), value:cssOn ? __('Verkleind','ultracache-pro') : __('Standaard','ultracache-pro'), state:cssOn ? 'good' : 'info'},
            {key:'tasks', icon:'dashicons-update', label:__('Taken','ultracache-pro'), value:totalWaiting ? totalWaiting + ' ' + __('klaar','ultracache-pro') : __('Rustig','ultracache-pro'), state:totalWaiting ? 'warning' : 'good'}
        ];
        if (wooActive) {
            stats.push({key:'woo', icon:'dashicons-cart', label:__('Webshop','ultracache-pro'), value:(cache.wooSafety || boolSetting(settings, 'enable_woocommerce_rules')) ? __('Beschermd','ultracache-pro') : __('Controleer','ultracache-pro'), state:(cache.wooSafety || boolSetting(settings, 'enable_woocommerce_rules')) ? 'good' : 'warning'});
        }
        return el(Card, {className:'ucp-card ucp-dashboard-saas-hero'},
            el(CardBody, {},
                el('div', {className:'ucp-dashboard-saas-hero__top'},
                    el('div', {className:'ucp-dashboard-saas-hero__copy'},
                        el('p', {className:'ucp-eyebrow'}, __('Overzicht','ultracache-pro')),
                        el('h2', {}, title),
                        el('p', {}, subtitle)
                    ),
                    el('div', {className:'ucp-dashboard-saas-hero__actions'},
                        el(StatusBadge, {state:totalWaiting ? 'warning' : 'good'}, totalWaiting ? __('Actie nodig','ultracache-pro') : __('Gezond','ultracache-pro')),
                        primaryAction
                    )
                ),
                el('div', {className:'ucp-dashboard-saas-metrics'}, stats.map(function(item){
                    return el('div', {className:'ucp-dashboard-saas-metric ucp-dashboard-saas-metric--' + item.state, key:item.key},
                        el('span', {className:'dashicons ' + item.icon, 'aria-hidden':'true'}),
                        el('div', {},
                            el('strong', {}, item.label),
                            el(StatusBadge, {state:item.state}, item.value)
                        )
                    );
                }))
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
            {key:'cache', title:__('Cache','ultracache-pro'), text:__('Controleer page cache, preload en veilige purge-acties.','ultracache-pro')},
            {key:'media', title:__('Afbeeldingen','ultracache-pro'), text:__('Beheer lazy load, afbeeldingsdimensies en visuele veiligheid.','ultracache-pro')},
            {key:'woocommerce', title:__('WooCommerce','ultracache-pro'), text:__('Controleer bescherming voor winkelwagen, checkout en betalingen.','ultracache-pro')}
        ];
        return el(Card, {className:'ucp-card ucp-simple-shortcuts'},
            el(CardHeader, {}, el('div', {className:'ucp-section-heading'}, el('h2', {}, __('Snelle routes','ultracache-pro')), el('p', {}, __('Simpel toont alleen veilige hoofdonderdelen. Zet Advanced aan voor CSS/JS, CDN, object cache, regels en tools.','ultracache-pro')))),
            el(CardBody, {}, el('div', {className:'ucp-simple-shortcuts__grid'}, actions.map(function(item){
                return el('button', {key:item.key, type:'button', className:'ucp-simple-shortcut', onClick:function(){ if (props.onSelectTab) { props.onSelectTab(item.key); } }},
                    el('strong', {}, item.title),
                    el('span', {}, item.text)
                );
            })))
        );
    }

    function boolSetting(settings, key) {
        return parseInt((settings || {})[key] || 0, 10) === 1;
    }

    function settingStatusBadge(settings, key, optionalLabel) {
        if (boolSetting(settings, key)) {
            return el(StatusBadge, {state:'good'}, __('Aan','ultracache-pro'));
        }
        return el(StatusBadge, {state:optionalLabel ? 'info' : 'warning'}, optionalLabel || __('Uit','ultracache-pro'));
    }

    function SafetyLabel(props) {
        var advanced = props.type === 'advanced';
        return el('span', {className:'ucp-safety-label ' + (advanced ? 'ucp-safety-label--advanced' : 'ucp-safety-label--safe')},
            advanced ? __('Geavanceerd — eerst testen','ultracache-pro') : __('Render-veilig','ultracache-pro')
        );
    }

    function DashboardMiniToggle(props) {
        var field = {key:props.settingKey, label:props.label, type:'toggle', help:props.help || ''};
        var active = boolSetting(props.settings || {}, props.settingKey);
        return el('div', {className:'ucp-dashboard-toggle-card ' + (active ? 'is-active' : 'is-inactive')},
            el('div', {className:'ucp-dashboard-toggle-card__icon'}, el('span', {className:'dashicons ' + props.icon, 'aria-hidden':'true'})),
            el('div', {className:'ucp-dashboard-toggle-card__body'},
                el('div', {className:'ucp-dashboard-toggle-card__head'},
                    el('strong', {}, props.label),
                    el(StatusBadge, {state:active ? 'good' : (props.recommended ? 'warning' : 'info')}, active ? __('Aan','ultracache-pro') : (props.recommended ? __('Aanbevolen','ultracache-pro') : __('Uit','ultracache-pro')))
                ),
                el('p', {}, props.text)
            ),
            el('div', {className:'ucp-dashboard-toggle-card__control'},
                el(SettingField, {field:field, kind:props.kind || 'cache', settings:props.settings, status:props.status || {}, setSettings:props.setSettings, addNotice:props.addNotice, setStatus:props.setStatus})
            )
        );
    }

    function DashboardControlsCard(props) {
        var settings = props.settings || {};
        var status = props.status || {};
        var wooActive = hasActiveWooCommerce(status);
        var toggles = [
            {key:'enable_cache', icon:'dashicons-performance', label:__('Page cache','ultracache-pro'), text:__('Snelle statische pagina’s voor bezoekers.','ultracache-pro'), kind:'cache', recommended:true},
            {key:'enable_preload', icon:'dashicons-controls-repeat', label:__('Preload','ultracache-pro'), text:__('Belangrijke pagina’s vooraf klaarzetten.','ultracache-pro'), kind:'preload', recommended:true},
            {key:'enable_lazy_images', icon:'dashicons-format-image', label:__('Lazyload media','ultracache-pro'), text:__('Media pas laden wanneer nodig.','ultracache-pro'), kind:'media', recommended:true},
            {key:'enable_css_minify', icon:'dashicons-admin-customizer', label:__('CSS verkleinen','ultracache-pro'), text:__('Veilige bestandsgrootte-optimalisatie.','ultracache-pro'), kind:'optimization', recommended:false}
        ];
        if (wooActive) {
            toggles.push({key:'enable_woocommerce_rules', icon:'dashicons-cart', label:__('Shopbescherming','ultracache-pro'), text:__('Beschermt winkelwagen en checkout.','ultracache-pro'), kind:'cache', recommended:true});
        }
        return el(Card, {className:'ucp-card ucp-dashboard-controls-card'},
            el(CardHeader, {},
                el('div', {className:'ucp-section-heading'},
                    el('h2', {}, __('Belangrijkste instellingen','ultracache-pro')),
                    el('p', {}, __('De veilige basis direct zichtbaar. Geavanceerde details blijven in de losse onderdelen.','ultracache-pro'))
                ),
                el(StatusBadge, {state:'info'}, __('SaaS-overzicht','ultracache-pro'))
            ),
            el(CardBody, {},
                el('div', {className:'ucp-dashboard-toggle-grid'}, toggles.map(function(item){
                    return el(DashboardMiniToggle, {
                        key:item.key,
                        settingKey:item.key,
                        icon:item.icon,
                        label:item.label,
                        text:item.text,
                        help:item.text,
                        recommended:item.recommended,
                        kind:item.kind,
                        settings:settings,
                        status:status,
                        setSettings:props.setSettings,
                        addNotice:props.addNotice,
                        setStatus:props.setStatus
                    });
                }))
            )
        );
    }

    function DashboardClarityCard(props) {
        var settings = props.settings || {};
        var status = props.status || {};
        var wooActive = hasActiveWooCommerce(status);
        var cssAdvanced = boolSetting(settings, 'enable_used_css') || boolSetting(settings, 'enable_used_css_delivery') || settings.css_delivery_mode === 'remove_unused';
        var cards = [
            {key:'cache', icon:'dashicons-performance', label:__('Page cache','ultracache-pro'), value:boolSetting(settings, 'enable_cache') ? __('Actief','ultracache-pro') : __('Uit','ultracache-pro'), state:boolSetting(settings, 'enable_cache') ? 'good' : 'warning', text:__('Maakt HTML-pagina’s snel beschikbaar voor bezoekers.','ultracache-pro')},
            {key:'lazy', icon:'dashicons-format-image', label:__('Lazyload','ultracache-pro'), value:boolSetting(settings, 'enable_lazy_images') ? __('Actief','ultracache-pro') : __('Basis','ultracache-pro'), state:boolSetting(settings, 'enable_lazy_images') ? 'good' : 'info', text:__('Laadt media pas wanneer die nodig is.','ultracache-pro')},
            {key:'css', icon:'dashicons-admin-customizer', label:__('CSS','ultracache-pro'), value:cssAdvanced ? __('Geavanceerd','ultracache-pro') : (boolSetting(settings, 'enable_css_minify') ? __('Veilig','ultracache-pro') : __('Standaard','ultracache-pro')), state:cssAdvanced ? 'warning' : (boolSetting(settings, 'enable_css_minify') ? 'good' : 'info'), text:cssAdvanced ? __('Eerst visueel testen bij layouts, formulieren en checkout.','ultracache-pro') : __('Veilige basis zonder agressieve levering.','ultracache-pro')},
            {key:'preload', icon:'dashicons-controls-repeat', label:__('Preload','ultracache-pro'), value:boolSetting(settings, 'enable_preload') ? __('Actief','ultracache-pro') : __('Uit','ultracache-pro'), state:boolSetting(settings, 'enable_preload') ? 'good' : 'info', text:__('Zet belangrijke pagina’s vooraf klaar.','ultracache-pro')}
        ];
        if (wooActive) {
            cards.push({key:'woo', icon:'dashicons-cart', label:__('Webshop','ultracache-pro'), value:boolSetting(settings, 'enable_woocommerce_rules') || boolSetting(settings, 'woocommerce_safety_mode') ? __('Beschermd','ultracache-pro') : __('Controleer','ultracache-pro'), state:boolSetting(settings, 'enable_woocommerce_rules') || boolSetting(settings, 'woocommerce_safety_mode') ? 'good' : 'warning', text:__('Checkout, winkelwagen en betaalflows blijven veilig.','ultracache-pro')});
        }
        return el(Card, {className:'ucp-card ucp-dashboard-insight-card'},
            el(CardHeader, {},
                el('div', {className:'ucp-section-heading'},
                    el('h2', {}, __('Actieve optimalisaties','ultracache-pro')),
                    el('p', {}, __('Een compact overzicht van wat aan staat en welke onderdelen aandacht vragen.','ultracache-pro'))
                ),
                props.onOpenWizard ? el(Button, {variant:'secondary', onClick:props.onOpenWizard}, __('Setup openen','ultracache-pro')) : null
            ),
            el(CardBody, {},
                el('div', {className:'ucp-dashboard-insight-grid'}, cards.map(function(item){
                    return el('div', {key:item.key, className:'ucp-dashboard-insight-tile ucp-dashboard-insight-tile--' + item.state},
                        el('div', {className:'ucp-dashboard-insight-tile__icon'}, el('span', {className:'dashicons ' + item.icon, 'aria-hidden':'true'})),
                        el('div', {className:'ucp-dashboard-insight-tile__body'},
                            el('div', {className:'ucp-dashboard-insight-tile__top'},
                                el('strong', {}, item.label),
                                el(StatusBadge, {state:item.state}, item.value)
                            ),
                            el('p', {}, item.text)
                        )
                    );
                }))
            )
        );
    }

    function DashboardProtectionCard(props) {
        var settings = props.settings || {};
        var status = props.status || {};
        var cache = status.cache || {};
        var wooActive = hasActiveWooCommerce(status);
        var protectedItems = [
            {key:'forms', icon:'dashicons-feedback', label:__('Formulieren','ultracache-pro'), text:__('Interacties blijven betrouwbaar.'), active:boolSetting(settings, 'delay_js_safe_mode') || !boolSetting(settings, 'enable_delay_js')},
            {key:'consent', icon:'dashicons-privacy', label:__('Consent & captcha','ultracache-pro'), text:__('Niet agressief vertraagd.'), active:boolSetting(settings, 'delay_js_safe_mode') || !boolSetting(settings, 'enable_delay_js')},
            {key:'loggedin', icon:'dashicons-admin-users', label:__('Ingelogde gebruikers','ultracache-pro'), text:__('Beheer en builders blijven stabiel.'), active:boolSetting(settings, 'disable_logged_in_optimizations')}
        ];
        if (wooActive) {
            protectedItems = [
                {key:'cart', icon:'dashicons-cart', label:__('Winkelwagen','ultracache-pro'), text:__('Nooit agressief gecachet.'), active:boolSetting(settings, 'enable_woocommerce_rules') || !!cache.wooSafety},
                {key:'checkout', icon:'dashicons-money-alt', label:__('Checkout','ultracache-pro'), text:__('Betaalflow blijft betrouwbaar.'), active:boolSetting(settings, 'woocommerce_safety_mode') || !!cache.wooSafety},
                {key:'account', icon:'dashicons-admin-users', label:__('Account','ultracache-pro'), text:__('Persoonlijke pagina’s blijven vers.'), active:boolSetting(settings, 'enable_woocommerce_rules') || !!cache.wooSafety},
                {key:'payments', icon:'dashicons-shield', label:__('Betalingen','ultracache-pro'), text:__('Scripts blijven beschikbaar.'), active:boolSetting(settings, 'woocommerce_safety_mode') || !!cache.wooSafety}
            ].concat(protectedItems);
        }
        return el(Card, {className:'ucp-card ucp-dashboard-protection-card'},
            el(CardHeader, {},
                el('div', {className:'ucp-section-heading'},
                    el('h2', {}, __('Beschermde onderdelen','ultracache-pro')),
                    el('p', {}, wooActive ? __('UltraCache beschermt winkelwagen, checkout, formulieren en beheer tegen risicovolle optimalisaties.','ultracache-pro') : __('UltraCache beschermt formulieren, consent en beheer tegen risicovolle optimalisaties.','ultracache-pro'))
                ),
                el(StatusBadge, {state:'good'}, __('Veiligheidslaag actief','ultracache-pro'))
            ),
            el(CardBody, {},
                el('div', {className:'ucp-dashboard-protection-grid'}, protectedItems.map(function(item){
                    return el('div', {key:item.key, className:'ucp-dashboard-protection-tile ' + (item.active ? 'is-active' : 'is-passive')},
                        el('span', {className:'dashicons ' + item.icon, 'aria-hidden':'true'}),
                        el('div', {},
                            el('strong', {}, item.label),
                            el('p', {}, item.text)
                        ),
                        el(StatusBadge, {state:item.active ? 'good' : 'info'}, item.active ? __('Beschermd','ultracache-pro') : __('Standaard','ultracache-pro'))
                    );
                }))
            )
        );
    }

    function DashboardPage(props) {
        var status = props.status || {};
        var settings = props.settings || {};
        var cache = status.cache || {}, queue = status.queue || {}, opt = status.optimization || {};
        var wooActive = hasActiveWooCommerce(status);
        var totalWaiting = (parseInt(queue.pending || 0, 10) || 0) + (parseInt(queue.retrying || 0, 10) || 0);
        var cacheOn = boolSetting(settings, 'enable_cache') || !!cache.enabled;
        var lazyOn = boolSetting(settings, 'enable_lazy_images') || !!opt.lazyImages;
        var cssOn = boolSetting(settings, 'enable_css_minify') || !!opt.cssMinify;
        var preloadOn = boolSetting(settings, 'enable_preload');
        var wooSafe = wooActive && (boolSetting(settings, 'enable_woocommerce_rules') || boolSetting(settings, 'woocommerce_safety_mode') || !!cache.wooSafety);
        var optimizationRows = [
            {key:'cache', tab:'cache', label:__('Page cache','ultracache-pro'), help:__('Statische pagina-cache voor snellere laadtijd.','ultracache-pro'), value:cacheOn ? __('Aan','ultracache-pro') : __('Uit','ultracache-pro'), state:cacheOn ? 'good' : 'warning'},
            {key:'media', tab:'media', label:__('Media','ultracache-pro'), help:__('Lazyload en veilige media-optimalisatie.','ultracache-pro'), value:lazyOn ? __('Lazyload','ultracache-pro') : __('Basis','ultracache-pro'), state:lazyOn ? 'good' : 'info'},
            {key:'css', tab:'optimization', label:__('CSS','ultracache-pro'), help:__('Bestanden verkleinen zonder agressieve levering.','ultracache-pro'), value:cssOn ? __('Verkleind','ultracache-pro') : __('Standaard','ultracache-pro'), state:cssOn ? 'good' : 'info'},
            {key:'preload', tab:'preload', label:__('Preload','ultracache-pro'), help:__('Belangrijke pagina’s vooraf opbouwen.','ultracache-pro'), value:preloadOn ? __('Aan','ultracache-pro') : __('Uit','ultracache-pro'), state:preloadOn ? 'good' : 'info'}
        ];
        if (wooActive) {
            optimizationRows.push({key:'woo', tab:'woocommerce', label:__('Webshop','ultracache-pro'), help:__('Winkelwagen en checkout blijven beschermd.','ultracache-pro'), value:wooSafe ? __('Beschermd','ultracache-pro') : __('Controleer','ultracache-pro'), state:wooSafe ? 'good' : 'warning'});
        }
        var protectedRows = [
            {key:'forms', label:__('Formulieren','ultracache-pro'), help:__('Interactie-elementen blijven betrouwbaar.','ultracache-pro')},
            {key:'consent', label:__('Consent','ultracache-pro'), help:__('Cookiebanners en captcha worden niet agressief vertraagd.','ultracache-pro')},
            {key:'login', label:__('Beheer','ultracache-pro'), help:__('Ingelogde gebruikers en builders blijven stabiel.','ultracache-pro')}
        ];
        if (wooActive) {
            protectedRows = [
                {key:'cart', label:__('Winkelwagen','ultracache-pro'), help:__('Winkelwagenpagina’s worden niet agressief gecachet.','ultracache-pro')},
                {key:'checkout', label:__('Checkout','ultracache-pro'), help:__('Betaalflow blijft betrouwbaar.','ultracache-pro')},
                {key:'account', label:__('Account','ultracache-pro'), help:__('Persoonlijke pagina’s blijven vers.','ultracache-pro')},
                {key:'payments', label:__('Betalingen','ultracache-pro'), help:__('Betaalscripts blijven beschikbaar.','ultracache-pro')}
            ].concat(protectedRows);
        }
        function tabButton(tab) {
            return props.onSelectTab ? el(Button, {variant:'tertiary', onClick:function(){ props.onSelectTab(tab); }}, __('Instellen','ultracache-pro')) : null;
        }
        function renderActionGroup(group) {
            return el(Card, {
                key:group.key,
                className:'ucp-card ucp-layout-card ucp-action-group-card' + (group.danger ? ' ucp-action-group-card--danger' : '')
            },
                el(CardHeader, {},
                    el('div', {className:'ucp-layout-card__header'},
                        el('div', {className:'ucp-action-group-heading'},
                            el('h2', {}, group.title),
                            group.description ? el('p', {className:'ucp-action-group-description'}, group.description) : null
                        ),
                        group.headerAction || null
                    )
                ),
                el(CardBody, {}, el('div', {className:'ucp-action-list'}, group.rows))
            );
        }
        var groups = [
            {
                key:'cache',
                title:__('Dagelijkse cache-acties','ultracache-pro'),
                description:__('Gebruik deze acties na content-, thema- of pluginwijzigingen.','ultracache-pro'),
                headerAction:el(BulkActionButton, {label:__('Cache legen + opwarmen','ultracache-pro'), actions:['purge-all','clear-minified-css','clear-minified-js','critical-css','used-css','preload','run-due-jobs'], variant:'primary', successMessage:__('Cache, CSS en JavaScript vernieuwd. Preload is gestart en de wachtrij is verwerkt.','ultracache-pro'), addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh}),
                rows:[
                    el('div', {key:'purge', className:'ucp-action-row'},
                        el('div', {className:'ucp-action-copy'}, el('strong', {}, __('Alleen cache legen','ultracache-pro')), el('p', {}, __('Leegt cachebestanden en vernieuwt CSS/JS zonder preload te starten.','ultracache-pro'))),
                        el(BulkActionButton, {label:__('Alleen cache legen','ultracache-pro'), actions:['purge-all','clear-minified-css','clear-minified-js','critical-css','used-css','run-due-jobs'], successMessage:__('Cache, CSS en JavaScript zijn vernieuwd.','ultracache-pro'), variant:'secondary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                    ),
                    el(ActionButton, {key:'page', action:'purge-page-cache', label:__('Leeg pagina-cache','ultracache-pro'), help:__('Verwijdert alleen de HTML-pagina-cache.','ultracache-pro'), variant:'secondary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                ]
            },
            {
                key:'preload',
                title:__('Preload en wachtrij','ultracache-pro'),
                description:__('Start preload of verwerk taken wanneer WP-Cron niet meteen draait.','ultracache-pro'),
                headerAction:el(BulkActionButton, {label:__('Preload volledig uitvoeren','ultracache-pro'), actions:['preload','run-due-jobs'], successMessage:__('Preload gestart en open taken verwerkt.','ultracache-pro'), addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh}),
                rows:[
                    el(ActionButton, {key:'warm', action:'preload', label:__('Cache opwarmen','ultracache-pro'), help:__('Start de preload queue om pagina’s vooraf in cache te zetten.','ultracache-pro'), variant:'secondary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh}),
                    el(ActionButton, {key:'jobs', action:'run-due-jobs', label:__('Verwerk taken nu','ultracache-pro'), help:totalWaiting ? sprintf(__('Er staan %d taken klaar.','ultracache-pro'), totalWaiting) : __('Verwerkt taken zonder te wachten op WP-Cron.','ultracache-pro'), variant:totalWaiting ? 'primary' : 'secondary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                ]
            },
            {
                key:'status',
                title:__('Actieve optimalisaties','ultracache-pro'),
                description:__('Compacte status van de onderdelen die direct invloed hebben.','ultracache-pro'),
                rows:optimizationRows.map(function(item){
                    return el('div', {key:item.key, className:'ucp-action-row ucp-dashboard-tools-row', 'aria-label':item.help},
                        el('div', {className:'ucp-action-copy'}, el('strong', {}, item.label), el('p', {}, item.help)),
                        el('div', {className:'ucp-dashboard-tools-row__meta'}, el(StatusBadge, {state:item.state}, item.value), tabButton(item.tab))
                    );
                })
            },
            {
                key:'protection',
                title:__('Bescherming','ultracache-pro'),
                description:__('UltraCache houdt gevoelige onderdelen buiten risicovolle optimalisaties.','ultracache-pro'),
                headerAction:el(StatusBadge, {state:'good'}, __('Beschermd','ultracache-pro')),
                rows:protectedRows.map(function(item){
                    return el('div', {key:item.key, className:'ucp-action-row ucp-dashboard-tools-row ucp-dashboard-tools-row--protected', 'aria-label':item.help},
                        el('div', {className:'ucp-action-copy'}, el('strong', {}, item.label), el('p', {}, item.help)),
                        el(StatusBadge, {state:'good'}, __('Veilig','ultracache-pro'))
                    );
                })
            }
        ];
        return el('div', {className:'ucp-tools-premium-page ucp-dashboard-tools-page'},
            el(Card, {className:'ucp-card ucp-tools-hero'},
                el(CardBody, {},
                    el('div', {className:'ucp-tools-hero__inner'},
                        el('div', {},
                            el('span', {className:'ucp-eyebrow'}, __('Overzicht','ultracache-pro')),
                            el('h2', {}, __('Snelle acties','ultracache-pro')),
                            el('p', {}, __('Klantvriendelijke acties voor cache, preload, status en bescherming. Geavanceerde technische instellingen blijven uit beeld.','ultracache-pro'))
                        ),
                        el('span', {className:'ucp-status-badge ' + (totalWaiting ? 'ucp-status-badge--warning' : 'ucp-status-badge--neutral')}, totalWaiting ? sprintf(__('%d taken klaar','ultracache-pro'), totalWaiting) : __('Klantvriendelijk','ultracache-pro'))
                    )
                )
            ),
            el('div', {className:'ucp-page ucp-actions-page ucp-dashboard-actions-page'},
                el('div', {className:'ucp-layout-grid ucp-layout-grid--actions ucp-layout-grid--simple'}, groups.map(renderActionGroup))
            )
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

    function wizardProfiles() {
        return [
            {key:'safe', title:__('Veilig optimaliseren','ultracache-pro'), desc:__('Voor normale websites. Alleen rustige basisoptimalisaties.','ultracache-pro'), preset:'balanced'},
            {key:'woo', title:__('WooCommerce optimaliseren','ultracache-pro'), desc:__('Voor webshops. Cart, checkout, account en order-pay blijven beschermd.','ultracache-pro'), preset:'shop'},
            {key:'fast', title:__('Maximale snelheid voorbereiden','ultracache-pro'), desc:__('Voor gevorderden. Toont duidelijk wat extra controle nodig heeft.','ultracache-pro'), preset:'fast'}
        ];
    }

    function presetForProfile(profileKey) {
        var profile = wizardProfiles().filter(function(item){ return item.key === profileKey; })[0] || wizardProfiles()[0];
        var presets = presetList();
        return presets.filter(function(item){ return item.key === profile.preset; })[0] || presets[0];
    }

    function friendlySettingName(key) {
        var map = {
            enable_cache: __('Page cache','ultracache-pro'),
            browser_cache_headers: __('Browser-cache','ultracache-pro'),
            enable_preload: __('Cache preload','ultracache-pro'),
            enable_preload_queue: __('Preload-wachtrij','ultracache-pro'),
            preload_homepage: __('Homepage preloaden','ultracache-pro'),
            enable_lazy_images: __('Lazy load afbeeldingen','ultracache-pro'),
            enable_lazy_iframes: __('Lazy load iframes','ultracache-pro'),
            enable_add_image_dimensions: __('Afbeeldingsdimensies toevoegen','ultracache-pro'),
            enable_font_display_swap: __('Font-display swap','ultracache-pro'),
            enable_css_minify: __('CSS verkleinen','ultracache-pro'),
            enable_defer_js_fallback: __('JavaScript veilig deferen','ultracache-pro'),
            enable_woocommerce_rules: __('WooCommerce cache-regels','ultracache-pro'),
            woocommerce_safety_mode: __('WooCommerce veiligheidsmodus','ultracache-pro'),
            optimize_cart_fragments: __('Cart-fragments beschermen','ultracache-pro'),
            cache_mobile_separately: __('Mobiel apart cachen','ultracache-pro'),
            enable_stale_cache: __('Verouderde cache tonen','ultracache-pro')
        };
        return map[key] || key;
    }

    function changedPresetRows(preset, currentSettings) {
        var values = preset && preset.values ? preset.values : {};
        var current = currentSettings || {};
        var rows = [];
        Object.keys(values).forEach(function(key){
            if (key === 'active_preset' || key === 'ui_mode') return;
            if (typeof values[key] === 'undefined') return;
            if (!friendlySettingName(key) || friendlySettingName(key) === key) return;
            var before = typeof current[key] === 'undefined' ? '' : current[key];
            var after = values[key];
            if (String(before) !== String(after) && after) {
                var advanced = /(used_css|critical_css|css_queue|delay_js|js_combine|css_combine|stale_cache|defer)/.test(key);
                rows.push({key:key, label:friendlySettingName(key), type:advanced ? 'advanced' : 'safe'});
            }
        });
        return rows.slice(0, 12);
    }

    function SetupWizard(props) {
        var stepState = useState(1), step = stepState[0], setStep = stepState[1];
        var selectedState = useState((props.status && props.status.cache && props.status.cache.wooSafety) ? 'woo' : 'safe'), selected = selectedState[0], setSelected = selectedState[1];
        var savingState = useState(false), saving = savingState[0], setSaving = savingState[1];
        var appliedState = useState([]), applied = appliedState[0], setApplied = appliedState[1];
        var preset = presetForProfile(selected);
        var rows = changedPresetRows(preset, props.settings || {});

        function doApply() {
            setSaving(true);
            saveBulk(preset.values).then(function(resp){
                if (resp.settings) props.setSettings(resp.settings);
                if (resp.status) props.setStatus(resp.status);
                setApplied(rows.map(function(row){ return row.label; }));
                props.addNotice({status:'success', message:__('Startprofiel toegepast.','ultracache-pro')});
                setStep(3);
            }).catch(function(err){
                props.addNotice({status:'error', message:cleanErrorMessage(err, __('Startprofiel kon niet worden toegepast.','ultracache-pro'))});
            }).finally(function(){ setSaving(false); });
        }

        function stepOne() {
            return el(Fragment, {},
                el('p', {className:'ucp-dialog-intro'}, __('Kies een startprofiel of sla dit over. Je kunt alles later aanpassen.','ultracache-pro')),
                el('div', {className:'ucp-preset-grid ucp-wizard-profile-grid'}, wizardProfiles().map(function(profile){
                    return el('button', {key:profile.key, type:'button', className:'ucp-wizard-profile' + (selected === profile.key ? ' is-selected' : ''), onClick:function(){ setSelected(profile.key); }, 'aria-pressed':selected === profile.key ? 'true' : 'false'},
                        el('strong', {}, profile.title),
                        el('span', {}, profile.desc)
                    );
                }))
            );
        }

        function stepTwo() {
            return el(Fragment, {},
                el('p', {className:'ucp-dialog-intro'}, __('Controleer eerst wat er wordt aangepast. Er wordt niets stilletjes overschreven zonder deze bevestiging.','ultracache-pro')),
                rows.length ? el('ul', {className:'ucp-wizard-change-list'}, rows.map(function(row){
                    return el('li', {key:row.key}, el('span', {}, row.label), el(SafetyLabel, {type:row.type}));
                })) : el(Notice, {status:'info', isDismissible:false}, __('Dit profiel verandert op basis van je huidige instellingen niets essentieels.','ultracache-pro')),
                selected === 'fast' ? el(Notice, {status:'warning', isDismissible:false}, __('Geavanceerd — eerst testen bij WooCommerce, formulieren, pagebuilders of maatwerk JavaScript.','ultracache-pro')) : null
            );
        }

        function stepThree() {
            return el(Fragment, {},
                el('p', {className:'ucp-dialog-intro'}, __('Deze optimalisaties zijn aangezet:','ultracache-pro')),
                applied.length ? el('ul', {className:'ucp-wizard-change-list'}, applied.map(function(label, idx){
                    return el('li', {key:idx}, el('span', {}, label), el(StatusBadge, {state:'good'}, __('Aan','ultracache-pro')));
                })) : el(Notice, {status:'info', isDismissible:false}, __('Er waren geen nieuwe wijzigingen nodig.','ultracache-pro')),
                el('p', {className:'ucp-muted'}, __('Controleer na opslaan je homepage. Bij WooCommerce controleer je ook winkelwagen, checkout, mijn account en order-pay.','ultracache-pro'))
            );
        }

        return el(CustomDialog, {title:__('Start optimalisatie','ultracache-pro'), eyebrow:__('UltraCache setup','ultracache-pro'), onClose:props.onClose, size:'large', footer:el('div', {className:'ucp-modal-actions'},
                step > 1 && step < 3 ? el(Button, {variant:'secondary', disabled:saving, onClick:function(){ setStep(step - 1); }}, __('Terug','ultracache-pro')) : null,
                step === 1 ? el(Button, {variant:'secondary', onClick:props.onClose}, __('Overslaan','ultracache-pro')) : null,
                step === 1 ? el(Button, {variant:'primary', onClick:function(){ setStep(2); }}, __('Volgende','ultracache-pro')) : null,
                step === 2 ? el(Button, {variant:'primary', isBusy:saving, disabled:saving, onClick:doApply}, __('Bevestigen en toepassen','ultracache-pro')) : null,
                step === 3 ? el(Button, {variant:'secondary', onClick:function(){ props.onClose(); if (props.onSelectTab) props.onSelectTab('cache'); }}, __('Bekijk instellingen','ultracache-pro')) : null,
                step === 3 ? el(Button, {variant:'primary', onClick:props.onClose}, __('Klaar','ultracache-pro')) : null
            )},
            el('div', {className:'ucp-setup-modal ucp-setup-modal--steps'},
                el('div', {className:'ucp-wizard-stepbar'}, [1,2,3].map(function(n){ return el('span', {key:n, className:'ucp-wizard-step' + (n === step ? ' is-active' : '') + (n < step ? ' is-done' : '')}, String(n)); })),
                step === 1 ? stepOne() : (step === 2 ? stepTwo() : stepThree())
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

        // UX-PATCH: compact recommendation-only dashboard removed; show normal preset cards everywhere.

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
            var actionData = Object.assign({}, props.data || {}, props.confirmBackup ? {confirmBackup:true, confirmIrreversible:true} : {});
            var promise = runAction(props.action, actionData);
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
        var supportMode = isTools && window.location && /(?:[?&])ucp_support=1(?:&|$)/.test(window.location.search || '');
        var actionGroups = [
            {
                key:'cache',
                title:__('Dagelijkse cache-acties','ultracache-pro'),
                description:__('Gebruik deze acties na content-, thema- of pluginwijzigingen.','ultracache-pro'),
                bulk:{label:__('Cache legen + opwarmen','ultracache-pro'), actions:['purge-all','clear-minified-css','clear-minified-js','critical-css','used-css','preload','run-due-jobs'], variant:'primary', success:__('Cache, CSS en JavaScript vernieuwd. Preload is gestart en de wachtrij is verwerkt.','ultracache-pro')},
                actions:[
                    {actions:['purge-all','clear-minified-css','clear-minified-js','critical-css','used-css','run-due-jobs'], label:__('Alleen cache legen','ultracache-pro'), help:__('Leegt cachebestanden en vernieuwt CSS/JS zonder preload te starten.','ultracache-pro'), variant:'secondary', success:__('Cache, CSS en JavaScript zijn vernieuwd.','ultracache-pro')},
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
                title:__('Geavanceerd onderhoud','ultracache-pro'),
                description:__('Alleen gebruiken na een recente databasebackup.','ultracache-pro'),
                danger:true,
                actions:[
                    {action:'database-cleanup', label:__('Database opschonen','ultracache-pro'), help:__('Alleen gebruiken na databasebackup. Deze actie kan niet ongedaan worden gemaakt.', 'ultracache-pro'), variant:'secondary', destructive:true, confirm:true, confirmBackup:true}
                ]
            }
        ];
        var orderedGroups = isTools && !supportMode ? actionGroups.filter(function(group){
            return ['cache','preload','support','maintenance'].indexOf(group.key) !== -1;
        }).map(function(group){
            if (group.key !== 'preload') { return group; }
            return Object.assign({}, group, {
                actions:(group.actions || []).filter(function(action){ return action.action !== 'retry-failed-jobs'; })
            });
        }) : actionGroups;
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
                                el(BulkActionButton, {label:group.bulk.label, actions:group.bulk.actions, successMessage:group.bulk.success, variant:group.bulk.variant || 'secondary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                            ) : null
                        )
                    ),
                    el(CardBody, {},
                        el('div', {className:'ucp-action-list'}, group.actions.map(function(a){
                            if (Array.isArray(a.actions)) {
                                return el('div', {key:a.label, className:'ucp-action-row'},
                                    el('div', {className:'ucp-action-copy'},
                                        el('strong', {}, a.label),
                                        el('p', {}, a.help)
                                    ),
                                    el(BulkActionButton,{
                                        label:a.label,
                                        actions:a.actions,
                                        successMessage:a.success,
                                        variant:a.variant || 'secondary',
                                        confirm:!!a.confirm,
                                        confirmText:a.help,
                                        addNotice:props.addNotice,
                                        setStatus:props.setStatus,
                                        onComplete:props.onRefresh
                                    })
                                );
                            }
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
            {title:'HTML', fields:[['html_optimization_mode','HTML optimalisatie','select','Kies of HTML rustig wordt opgeschoond. In het PageSpeed Auto-profiel kan HTML verkleinen actief zijn; WooCommerce-, builder- en previewpagina’s blijven beschermd.',[['off','Uit'],['comments','Alleen comments verwijderen'],['minify','HTML verkleinen + comments verwijderen']]]]},
            {title:'CSS', fields:[['enable_css_minify','CSS verkleinen','toggle','Staat standaard aan in de veilige basis. Zet uit bij visuele CSS-conflicten.'],['css_delivery_mode','CSS-levering','css_delivery','Kies hoe CSS wordt geladen. Ongebruikte CSS verwijderen en async CSS blijven staging-first.',[['none','Uit - veiligste keuze'],['remove_unused','Ongebruikte CSS verwijderen - staging'],['async','CSS asynchroon laden - fallback']]]]},
            {title:'JavaScript', fields:[['enable_js_minify','JavaScript verkleinen','toggle','Staat standaard uit. Alleen inschakelen na test op formulieren, cookie banners en checkout.'],['delay_js_control','Delay JS','select','Stel JavaScript uit zonder belangrijke interacties te breken. Test formulieren, sliders, cookie banners en checkout.',[['off','Uit'],['specified','Alleen opgegeven scripts'],['all','Alle scripts behalve uitsluitingen'],['safe','Veilige modus']]],['defer_all_js','JavaScript uitgesteld laden','toggle','Laadt JavaScript uitgesteld zodat de pagina sneller kan renderen. Test na inschakelen menu\'s, formulieren, sliders, cookiebanner en checkout.'],['accessibility_mode','Veilige interacties','toggle','Beperkt risicovolle optimalisaties zodat knoppen, focus en dynamische onderdelen betrouwbaar blijven.']]},
            {title:'Combineren en uitsluitingen', advanced:true, fields:[['enable_css_combine','CSS combineren','toggle','Alleen gebruiken als dit echt nodig is. Vaak niet nodig bij HTTP/2/3.'],['css_exclusions','CSS uitsluitingen / safelist','textarea','Handles, bestandsnamen, selectors of fragmenten die niet mogen worden aangepast.'],['enable_js_combine','JavaScript combineren','toggle','Staging-first. Niet combineren bij shops, builders, formulieren, cookieplugins of Delay JS-conflicten.'],['delay_js_exclusions','Delay JS uitsluitingen','textarea','Eén script/fragment per regel.'],['html_exclude_urls','HTML uitsluitingen','textarea','Eén URL/patroon per regel.']]},
            {title:'CDN levering', advanced:true, fields:[['cdn_rewrite_mode','CDN herschrijven','select','Vervangt statische asset-URL’s door je CDN-domein. Laat uit als je geen CDN gebruikt.',[['off','Uit'],['css_js','Alleen CSS en JS'],['images','Alleen afbeeldingen'],['all','Alle statische bestanden']]],['cdn_cnames','CDN CNAMEs','textarea','Eén domein per regel.'],['cdn_exclude','CDN uitsluitingen','textarea','Eén patroon per regel.'],['browser_cache_mode','Browser-cache statische bestanden','select','Bepaalt browser-cache headers voor statische bestanden.',[['off','Uit'],['30d','30 dagen'],['180d','6 maanden'],['365d','1 jaar'],['custom','Aangepast']]],['cache_control_max_age','Aangepaste browser-cache bewaartijd','number','In seconden. Alleen nodig wanneer je Aangepast gebruikt.']]},
            {title:'CDN provider en compat-lijsten', advanced:true, fields:[['cdn_provider','CDN purge-provider','select','Kies alleen een provider die echt actief is. Cloudflare gebruikt de bestaande zone/token-velden.',[['none','Geen'],['cloudflare','Cloudflare'],['bunny','Bunny CDN'],['generic','Generieke webhook']]],['bunny_pull_zone_id','Bunny pull-zone ID','text','Alleen nodig bij Bunny purge.'],['bunny_api_key','Bunny API-key','text','Server-side secret. Wordt gemaskeerd bij export en in de UI.'],['cdn_purge_webhook','Generieke purge-webhook','text','Alleen voor vertrouwde interne endpoints.'],['cdn_purge_webhook_token','Webhook token','text','Server-side secret voor de generieke purge-webhook.'],['enable_compat_updates','Compat-lijsten automatisch bijwerken','toggle','Haalt remote compat-overlays op. Alleen gebruiken met een vertrouwde bron.'],['compat_update_url','Compat-update URL','text','JSON-endpoint voor compat-overlays.'],['enable_host_cache_purge','Hosting-cache mee legen','toggle','Stuurt UltraCache-purges ook naar de servercache van bekende managed hosts. Fail-safe: doet niets als de host niet wordt herkend.']]}
        ],
        media: [
            {title:'Afbeeldingen', fields:[['image_optimization_mode','Upload-optimalisatie','select','Gebruik dit alleen als UltraCache je uploads mag optimaliseren.',[['off','Uit'],['optimize','Nieuwe uploads optimaliseren'],['webp','Optimaliseren + WebP maken'],['webp_avif','Optimaliseren + WebP + AVIF maken']]],['image_quality','Afbeeldingskwaliteit','number','Aanbevolen: 80-85.'],['enable_add_image_dimensions','Afmetingen toevoegen','toggle','Voorkomt layout shifts.']]},
            {title:'Lazyload & LCP', fields:[['media_lazyload_mode','Lazyload','select','Laadt offscreen media later.',[['off','Uit'],['images','Alleen afbeeldingen'],['iframes','Afbeeldingen + iframes/video'],['youtube','Afbeeldingen + iframes/video + YouTube preview']]],['lcp_image_mode','Belangrijke boven-de-vouw beelden','lcp_images','Houd belangrijke beelden direct beschikbaar.',[['off','Uit'],['protect_hero','Hero beschermen: niet lazyloaden'],['preload_hero','Hero preloaden'],['recommended','Aanbevolen: 2 preloaden + 4 beschermen'],['custom','Aangepast']]],['lazyload_exclusions','Uitsluitingen','textarea','Logo’s, hero’s en sliders direct laden.']]},
            {title:'Fonts', fields:[['google_fonts_mode','Fontgedrag','select','Maak fontgedrag voorspelbaar.',[['standard','Standaard'],['swap','Alleen font-display swap'],['local','Lokaal hosten + swap'],['disable','Google Fonts uitschakelen']]],['enable_auto_font_preloads','Automatische font-preloads','toggle','Alleen kritieke lokale fonts.'],['preload_fonts','Kritieke fonts','textarea','Eén kritieke WOFF2-URL per regel.']]},
            {title:'Geavanceerde media-rendering', advanced:true, fields:[['enable_lqip','LQIP placeholders','toggle','Genereert lichte placeholders voor lazyloaded afbeeldingen. Test visueel bij hero’s en sliders.'],['enable_lazy_render','HTML lazy render','toggle','Staging-first: stelt offscreen secties uit met content-visibility. Test anchor-links, printen en zoeken-in-pagina.'],['lazy_render_selectors','Lazy render selectors','textarea','Eén CSS-selector per regel. Alleen actief als HTML lazy render aanstaat.'],['enable_html_parser','HTML-parser engine','toggle','Gebruikt een fouttolerante HTML-tokenizer voor afbeelding- en iframe-passes. Valt automatisch terug bij fouten.']]},
            {title:'Font details', advanced:true, fields:[['enable_font_unicode_ranges','Unicode-range optimalisatie','toggle','Laat browsers alleen passende lokale font-ranges gebruiken.'],['font_unicode_ranges','Font-bereik','select','Kies de veilige taalrange voor lokaal gehoste fonts.',[['latin','Latin'],['latin-ext','Latin-ext'],['latin-plus-ext','Latin + Latin-ext']]]]},
            {title:'Externe bronnen lokaal hosten', advanced:true, fields:[['enable_auto_resource_hints','Externe bronnen versnellen','toggle','Voegt beperkte preconnect en DNS-prefetch toe voor externe domeinen.'],['enable_local_gravatar','Gravatars lokaal hosten','toggle','Cacht externe Gravatar-afbeeldingen lokaal.'],['enable_local_youtube_thumbnails','YouTube thumbnails lokaal hosten','toggle','Cacht YouTube thumbnails lokaal voor lazy previews.'],['enable_self_host_third_party_assets','Externe scripts lokaal hosten','toggle','Cacht ondersteunde third-party scripts lokaal. Test koppelingen en tracking na inschakelen.']]},
            {title:'Adaptieve afbeeldingen', advanced:true, fields:[['enable_image_cdn','Afbeeldingen via CDN laden','toggle','Alleen gebruiken met een actieve image-CDN.'],['enable_image_cdn_transforms','CDN-resizing','toggle','Levert passende breedtes via CDN.'],['enable_adaptive_image_srcset','Extra afbeeldingsformaten aanbieden','toggle','Vult ontbrekende srcsets aan.'],['image_cdn_transform_provider','Resize-provider','select','Kies de resize-provider.',[['auto','Automatisch'],['bunny','Bunny Optimizer'],['cloudflare','Cloudflare Image Resizing'],['generic','Generiek query-template']]],['image_cdn_base','CDN basis-URL','text','Bijvoorbeeld https://cdn.example.com.'],['image_cdn_query','Image-CDN query','text','Optioneel query-template met {width} en {quality}.'],['image_cdn_widths','Breedtes','textarea','Eén breedte per regel.']]}
        ],
        preload: [
            {title:'Cache opbouwen', fields:[['preload_mode','Cache preload','select','Bouw belangrijke pagina’s vooraf op.',[['off','Uit'],['recommended','Veilig aanbevolen: queue + sitemap + homepage'],['homepage','Alleen homepage'],['manual','Handmatig / geavanceerd']]]]},
            {title:'Navigatie versnellen', fields:[['enable_prefetch_links','Link preload activeren','toggle','Verbetert de ervaren navigatiesnelheid bij hover/klik. Gebruik voorzichtig op shops of sites met veel unieke links.'],['speculative_loading_mode','Speculative Loading','select','Gebruik de veilige browsermodus. Prerender blijft support-only en staging-first.',[['core','Core standaard volgen'],['enhanced','UltraCache veilig versterken'],['off','Volledig uitschakelen']]]]},
            {title:'Uitsluitingen', fields:[['preload_exclude_urls','URL’s uitsluiten van preload','textarea','Eén URL of patroon per regel. Gebruik voor account-, checkout-, zoek-, filter- of paginatiepagina’s.']]},
            {title:'Prerender', advanced:true, fields:[['speculative_loading_mode','Speculative Loading inclusief prerender','select','Staging-first. Prerender kan analytics, sessies of dynamische pagina’s beïnvloeden.',[['core','Core standaard volgen'],['enhanced','UltraCache veilig versterken'],['prerender','Prerender — staging'],['off','Volledig uitschakelen']]]]}
        ],
        cache: [
            {title:'Page cache', fields:[['enable_cache','Pagina-cache inschakelen','toggle','Maakt statische cachebestanden voor bezoekers.'],['cache_lifespan','Cache bewaren voor aantal uren','number','Gebruik uren, bijvoorbeeld 10.'],['stale_cache_mode','Verouderde cache tonen','select','Toont tijdelijk oude cache als vernieuwen niet lukt.',[['off','Uit'],['6','6 uur'],['12','12 uur'],['24','24 uur'],['48','48 uur']]]]},
            {title:'Automatisch vernieuwen', fields:[['enable_cache_tags','Gerelateerde pagina’s meeverversen','toggle','Vernieuwt lijsten en archieven na contentwijzigingen.']]},
            {title:'Extra purge-regels', advanced:true, fields:[['always_purge_urls','Extra URL’s legen bij wijzigingen','textarea','Eén URL of patroon per regel. Gebruik voor pagina’s die mee moeten verversen, zoals homepage of archieven.']]},
            {title:'Cacheveiligheid', fields:[['cache_mobile_separately','Mobiele cache apart bewaren','toggle','Gebruik dit alleen wanneer mobiel en desktop duidelijk andere HTML krijgen.'],['disable_logged_in_optimizations','Optimalisaties uit voor ingelogde gebruikers','toggle','Aanbevolen voor builders en beheerwerk.'],['enable_woocommerce_rules','WooCommerce veilig cachen','toggle','Beschermt winkelwagen, checkout en accountpagina’s.'],['optimize_cart_fragments','Cart-fragments optimaliseren','toggle','Versnelt lege winkelwagens en laat gevulde manden met rust.'],['limit_cart_fragments_to_woo','Cart-fragments alleen waar nodig','toggle','Laadt winkelwagen-scripts alleen waar ze nodig zijn.']]},
            {title:'Webshop cache-risico', advanced:true, fields:[['serve_cache_to_shoppers','Publieke cache voor shoppers','toggle','Developer-only/staging-first. Test winkelwagen, checkout, account en sessiecookies voordat je dit gebruikt.']]}
        ],
        advanced: [
            {title:'Pagina’s nooit cachen', fields:[['exclude_urls','Nooit URL’s cachen','textarea','Eén pad of patroon per regel. Gebruik dit voor cart, checkout, account, zoekresultaten, filters of persoonlijke content.']]},
            {title:'Pagina’s altijd verversen', fields:[['always_purge_urls','Extra URL’s legen bij wijzigingen','textarea','Eén URL of patroon per regel. Gebruik voor pagina’s die mee moeten verversen, zoals homepage, blogoverzichten of archieven.']]},
            {title:'Pagina’s niet preloaden', fields:[['preload_exclude_urls','URL’s uitsluiten van preload','textarea','Eén URL of patroon per regel. Gebruik voor account-, checkout-, zoek-, filter- of paginatiepagina’s.']]},
            {title:'Technische uitsluitingen', advanced:true, fields:[['exclude_cookies','Nooit cachen bij cookies','textarea','Eén cookie of gedeeltelijke cookienaam per regel. Nuttig voor winkelwagens en gepersonaliseerde content.'],['exclude_user_agents','Nooit cachen voor user-agents','textarea','Eén user-agent fragment per regel. Laat leeg tenzij een apparaat, browser of bot afwijkende content krijgt.'],['block_unknown_request_cookies','Strikte cookie-modus','toggle','Staging-first. Bypasst cache bij onbekende cookies. Kan cache-hit ratio verlagen.'],['cache_vary_cookies','Cache variëren per valuta/taal','textarea','Eén cookie-fragment per regel. Deze cookies variëren de cache in plaats van te bypassen.']]},
            {title:'Query strings', advanced:true, fields:[['query_string_cache_mode','Query strings cachen','select','Gebruik dit alleen voor bekende parameters die geen persoonlijke content tonen.',[['off','Uit'],['allow_list','Alleen onderstaande parameters toestaan']]],['cache_query_string_inclusions','Toegestane query parameters','textarea','Eén parameter per regel, zonder vraagteken. Wildcards aan het einde zijn toegestaan.']]}
        ],
        database: [
            {title:'Automatisch onderhoud', fields:[['db_cleanup_frequency','Automatische database-opschoning','select','Kies Uit of een schema. UltraCache zet de interne planning automatisch goed.',[['off','Uit'],['daily','Dagelijks'],['weekly','Wekelijks'],['monthly','Maandelijks']]]]},
            {title:'Veilig opruimen', fields:[['db_cleanup_post_revisions','Revisies opschonen','toggle','Verwijdert oude revisies, maar bewaart het ingestelde aantal recente versies per bericht.'],['db_keep_post_revisions','Revisies bewaren','number','Aanbevolen: bewaar minimaal 5 revisies voor contentherstel.'],['db_cleanup_auto_drafts','Automatische concepten opschonen','toggle','Verwijdert oude automatische concepten die niet meer gebruikt worden.'],['db_cleanup_trashed_posts','Prullenbakberichten verwijderen','toggle','Verwijdert berichten en pagina’s die al in de prullenbak staan.'],['db_cleanup_spam_comments','Spamreacties verwijderen','toggle','Verwijdert reacties die al als spam zijn gemarkeerd.'],['db_cleanup_trashed_comments','Prullenbakreacties verwijderen','toggle','Verwijdert reacties die al in de prullenbak staan.'],['db_cleanup_expired_transients','Verlopen transients verwijderen','toggle','Veilige basis. Verwijdert tijdelijke data waarvan de verloopdatum voorbij is.']]},
            {title:'Backup nodig', fields:[['db_cleanup_drafts','Gewone concepten opschonen','toggle','Verwijdert gewone concepten definitief. Standaard uit; alleen gebruiken als concepten niet bewaard hoeven blijven.'],['db_cleanup_all_transients','Alle transients verwijderen','toggle','Kan tijdelijke caches van plugins wissen. Gebruik dit alleen wanneer je problemen met tijdelijke data vermoedt.'],['db_cleanup_optimize_tables','Plugin-tabellen optimaliseren','toggle','Ruimt overhead op in UltraCache-tabellen. Maak eerst een backup.'],['db_cleanup_optimize_all_tables','Alle WordPress-tabellen optimaliseren','toggle','Breder dan plugin-tabellen. Wordt alleen handmatig uitgevoerd na backup- en onomkeerbaarheidsbevestiging.']]}
        ],
        diagnostics: [
            {title:'WordPress opschonen', fields:[['bloat_removal_mode','WordPress opschonen','select','Verwijdert ongebruikte WordPress-onderdelen. Gebruik Agressief alleen na stagingtest.',[['off','Uit'],['safe','Veilig — aanbevolen'],['aggressive','Agressief — staging-first']]]]},
            {title:'Diagnostiek en logs', fields:[['enable_diagnostics','Diagnostiek registreren','toggle','Slaat beperkte runtime-diagnostiek op voor cache- en optimalisatiecontrole.'],['enable_logs','Logboek inschakelen','toggle','Bewaar technische meldingen voor foutopsporing. Zet uit op productie als je dit niet actief gebruikt.'],['enable_health_checks','Health checks plannen','toggle','Controleert cachemap, drop-in en runtimevoorwaarden.'],['enable_admin_queue_runner','Admin queue runner','toggle','Mag wachtrijtaken vanuit het dashboard proberen te verwerken.']]},
            {title:'Core Web Vitals fielddata', fields:[['enable_cwv_monitoring','CWV-monitoring','toggle','Verzamelt lokale Core Web Vitals-fielddata uit echte bezoeken. Slaat alleen metricwaarde en apparaatklasse op.']]},
            {title:'Headless renderer en fragments', advanced:true, fields:[['enable_headless_renderer','Headless-renderer activeren','toggle','Nodig voor precieze VPI-detectie en browsergebaseerde CSS-analyse.'],['headless_renderer_endpoint','Headless-renderer endpoint','text','Publieke endpoint-URL van de render service.'],['headless_renderer_token','Headless-renderer token','text','Server-side secret. Wordt gemaskeerd bij export en in de UI.'],['enable_esi','ESI fragment-cache','toggle','Staging-first. Custom fragments mogen geen persoonlijke data in gedeelde cache lekken.']]},
            {title:'Bewaartermijnen', advanced:true, fields:[['log_retention_days','Logs bewaren dagen','number','Aantal dagen dat logs blijven staan.'],['diagnostics_retention_days','Diagnostiek bewaren dagen','number','Aantal dagen dat diagnostiekdata blijft staan.'],['job_retention_days','Jobs bewaren dagen','number','Aantal dagen dat afgeronde jobs blijven staan.']]},
            {title:'Verwijderen', advanced:true, fields:[['clean_uninstall','Instellingen wissen bij verwijderen','toggle','Verwijdert alle plugininstellingen wanneer je UltraCache deïnstalleert. Laat uit als je je configuratie wilt bewaren.']]}
        ],

    };;

    function settingsIntro(kind) {
        var data = {
            cache: {title: __('Cache overzicht','ultracache-pro'), text: __('Beheer de algemene page cache, bewaartijd en veilige purge-instellingen zonder de uitzonderingsregels te mengen met optimalisatie.','ultracache-pro'), steps: [__('Page cache','ultracache-pro'), __('Bewaartijd','ultracache-pro'), __('Varianten en purge','ultracache-pro')]},
            optimization: {title: __('Optimalisatie overzicht','ultracache-pro'), text: __('Begin met veilige optimalisaties. Zet risicovolle CSS- en JavaScript-opties pas aan nadat je de frontend, formulieren en checkout hebt getest.','ultracache-pro'), steps: [__('1. Kies eerst een preset','ultracache-pro'), __('2. Controleer HTML en CSS','ultracache-pro'), __('3. Test JavaScript apart','ultracache-pro')]},
            media: {title: __('Media optimalisatie overzicht','ultracache-pro'), text: __('Gebruik veilige media-optimalisaties eerst: lazy load voor offscreen media, vaste afbeeldingsafmetingen en font-display swap. Gebruik preload alleen voor kritieke fonts of hero-afbeeldingen.','ultracache-pro'), steps: [__('Aanbevolen basis','ultracache-pro'), __('Afbeeldingen','ultracache-pro'), __('Fonts','ultracache-pro'), __('Uitsluitingen en connecties','ultracache-pro')]},
            preload: {title: __('Preload overzicht','ultracache-pro'), text: __('Preload maakt cachebestanden klaar voordat bezoekers pagina’s openen. Gebruik de queue en sitemap als veilige basis; stuur snelheid met batchgrootte en pauze.','ultracache-pro'), steps: [__('Cache vooraf opbouwen','ultracache-pro'), __('Link preload','ultracache-pro'), __('Uitsluitingen','ultracache-pro'), __('Serverbelasting','ultracache-pro')]},
            advanced: {title: __('Regels overzicht','ultracache-pro'), text: __('Gebruik deze pagina alleen voor cache-uitzonderingen: URL’s, cookies, user-agents en veilige query-parameters. Technische CDN-, purge- en rendererinstellingen staan bij Cache, Optimalisatie en Tools.','ultracache-pro'), steps: [__('Pagina’s','ultracache-pro'), __('Cookies en agents','ultracache-pro'), __('Query strings','ultracache-pro')]},
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

    function getGroupField(groups, key) {
        var found = null;
        (groups || []).some(function(group){
            return (group.fields || []).some(function(field){
                if (field[0] === key) { found = field; return true; }
                return false;
            });
        });
        return found;
    }

    function hasActiveWooCommerce(status) {
        var cache = (status && status.cache) || {};
        var detected = (status && status.detected) || {};
        return !!(cache.woocommerceActive || detected.woocommerceActive || detected.hasWooCommerceActive);
    }

    function CacheSettingsPage(props) {
        var rawGroups = settingsGroups.cache || [];
        var supportMode = window.location && /(?:[?&])ucp_support=1(?:&|$)/.test(window.location.search || '');
        var wooActive = hasActiveWooCommerce(props.status || {});
        function field(key){ return getGroupField(rawGroups, key); }
        function renderField(key, extraClass){
            var f = field(key);
            if (!f) { return null; }
            return el('div', {className:'ucp-cache-tools-option ' + (extraClass || ''), title:f[3] || ''},
                el(SettingField,{key:f[0], field:f, kind:'cache', settings:props.settings, status:props.status || {}, setSettings:props.setSettings, addNotice:props.addNotice, setStatus:props.setStatus})
            );
        }
        function hero(title, text, badge){
            return el(Card, {className:'ucp-card ucp-tools-hero ucp-cache-tools-hero'},
                el(CardBody, {},
                    el('div', {className:'ucp-tools-hero__inner'},
                        el('div', {},
                            el('span', {className:'ucp-eyebrow'}, __('Cache','ultracache-pro')),
                            el('h2', {}, title),
                            el('p', {}, text)
                        ),
                        el('span', {className:'ucp-status-badge ucp-status-badge--neutral'}, badge)
                    )
                )
            );
        }
        function card(title, text, badge, body, className){
            return el(Card, {className:'ucp-card ucp-action-group-card ucp-cache-tools-card ' + (className || '')},
                el(CardHeader, {},
                    el('div', {className:'ucp-action-group-heading'},
                        el('div', {},
                            el('h2', {}, title),
                            text ? el('p', {className:'ucp-action-group-description'}, text) : null
                        ),
                        badge ? el('span', {className:'ucp-status-badge ucp-status-badge--neutral'}, badge) : null
                    )
                ),
                el(CardBody, {}, body)
            );
        }
        var shopperEnabled = !!parseInt((props.settings || {}).serve_cache_to_shoppers || 0, 10);
        var safetyFields = ['cache_mobile_separately','disable_logged_in_optimizations'];
        if (wooActive) {
            safetyFields = safetyFields.concat(['enable_woocommerce_rules','optimize_cart_fragments','limit_cart_fragments_to_woo']);
        }
        return el('div', {className:'ucp-settings-page ucp-settings-page--cache ucp-cache-tools-page'},
            hero(__('Cachebeheer','ultracache-pro'), __('Beheer page cache, vernieuwing en bescherming met dezelfde rustige opbouw als Tools.','ultracache-pro'), __('Basis','ultracache-pro')),
            el('div', {className:'ucp-cache-tools-grid'},
                card(__('Page cache','ultracache-pro'), __('Statische HTML-cache voor snellere pagina’s.','ultracache-pro'), __('Aanbevolen','ultracache-pro'),
                    el('div', {className:'ucp-cache-tools-stack'},
                        renderField('enable_cache'),
                        el('div', {className:'ucp-cache-tools-fields ucp-cache-tools-fields--two'},
                            renderField('cache_lifespan'),
                            renderField('stale_cache_mode')
                        )
                    )
                ),
                card(__('Automatisch vernieuwen','ultracache-pro'), __('Houd lijsten en archieven actueel na wijzigingen.','ultracache-pro'), __('Basis','ultracache-pro'),
                    el('div', {className:'ucp-cache-tools-stack'}, renderField('enable_cache_tags'))
                ),
                card(wooActive ? __('Veiligheid en WooCommerce','ultracache-pro') : __('Cacheveiligheid','ultracache-pro'), wooActive ? __('Bescherm beheer, mobiele varianten, winkelwagen en checkout.','ultracache-pro') : __('Bescherm beheer en mobiele varianten.','ultracache-pro'), __('Veiligheid','ultracache-pro'),
                    el('div', {className:'ucp-cache-tools-fields ucp-cache-tools-fields--three'}, safetyFields.map(function(key){ return renderField(key); })),
                    'ucp-cache-tools-card--wide'
                )
            ),
            supportMode ? card(__('Extra purge-regels','ultracache-pro'), __('Alleen voor support: URL’s die altijd mee moeten verversen.','ultracache-pro'), __('Support','ultracache-pro'), renderField('always_purge_urls'), 'ucp-cache-tools-card--wide') : null,
            supportMode && wooActive ? card(__('Webshop cache-risico','ultracache-pro'), __('Alleen gebruiken na stagingtest van cart, checkout, account en sessies.','ultracache-pro'), __('Staging','ultracache-pro'),
                el('div', {className:'ucp-cache-tools-stack'},
                    renderField('serve_cache_to_shoppers', shopperEnabled ? 'ucp-cache-option--attention' : ''),
                    shopperEnabled ? el(Notice,{status:'warning', isDismissible:false}, __('Publieke cache voor shoppers staat aan. Test cart, checkout, account en sessiecookies eerst op staging.','ultracache-pro')) : null
                ),
                'ucp-cache-tools-card--wide'
            ) : null
        );
    }

    function OptimizationSettingsPage(props) {
        var rawGroups = settingsGroups.optimization || [];
        var supportMode = window.location && /(?:[?&])ucp_support=1(?:&|$)/.test(window.location.search || '');
        function field(key){ return getGroupField(rawGroups, key); }
        function renderField(key, extraClass){
            var f = field(key);
            if (!f) { return null; }
            return el('div', {className:'ucp-opt-option ucp-cache-tools-option ' + (extraClass || '')},
                el(SettingField,{key:f[0], field:f, kind:'optimization', settings:props.settings, status:props.status || {}, setSettings:props.setSettings, addNotice:props.addNotice, setStatus:props.setStatus})
            );
        }
        function sectionHeader(eyebrow, title, text, badge, badgeClass){
            return el('div', {className:'ucp-opt-section-head'},
                el('div', {},
                    eyebrow ? el('span', {className:'ucp-eyebrow'}, eyebrow) : null,
                    el('h2', {}, title),
                    text ? el('p', {}, text) : null
                ),
                badge ? el('span', {className:'ucp-status-badge ' + (badgeClass || 'ucp-status-badge--neutral')}, badge) : null
            );
        }
        function currentToggleState(key){ return parseInt(props.settings[key] || 0, 10) ? __('Aan','ultracache-pro') : __('Uit','ultracache-pro'); }
        function htmlOptimizationState(){
            if (parseInt(props.settings.enable_html_minify || 0, 10)) { return __('HTML verkleinen','ultracache-pro'); }
            if (parseInt(props.settings.remove_html_comments || 0, 10)) { return __('Comments opschonen','ultracache-pro'); }
            return __('Uit','ultracache-pro');
        }
        function statusPill(title, state, active){
            return el('div', {className:'ucp-opt-quick-status' + (active ? ' is-active' : '')},
                el('span', {}, title),
                el('strong', {}, state)
            );
        }
        return el('div', {className:'ucp-settings-page ucp-settings-page--optimization ucp-opt-premium-page ucp-opt-clean-page ucp-cache-tools-page'},
            el(Card, {className:'ucp-card ucp-opt-premium-card ucp-opt-premium-card--intro ucp-opt-clean-hero ucp-tools-hero ucp-cache-tools-hero'},
                el(CardBody, {},
                    sectionHeader(__('Optimalisatie','ultracache-pro'), __('CSS & JS optimalisatie','ultracache-pro'), __('Kerninstellingen voor veilige front-end optimalisatie. Geavanceerde combinaties staan alleen in supportmodus.','ultracache-pro'), __('Rustige basis','ultracache-pro'), 'ucp-status-badge--good'),
                    el('div', {className:'ucp-opt-quick-status-grid'},
                        statusPill(__('HTML','ultracache-pro'), htmlOptimizationState(), parseInt(props.settings.enable_html_minify || props.settings.remove_html_comments || 0, 10)),
                        statusPill(__('CSS','ultracache-pro'), currentToggleState('enable_css_minify'), parseInt(props.settings.enable_css_minify || 0, 10)),
                        statusPill(__('JavaScript','ultracache-pro'), currentToggleState('enable_js_minify'), parseInt(props.settings.enable_js_minify || 0, 10))
                    )
                )
            ),
            el('div', {className:'ucp-opt-clean-grid'},
                el(Card, {className:'ucp-card ucp-opt-premium-card ucp-opt-clean-card ucp-cache-tools-card'},
                    el(CardHeader, {}, sectionHeader(null, __('HTML','ultracache-pro'), __('Kleine HTML-opschoning.','ultracache-pro'))),
                    el(CardBody, {}, renderField('html_optimization_mode'))
                ),
                el(Card, {className:'ucp-card ucp-opt-premium-card ucp-opt-clean-card ucp-cache-tools-card'},
                    el(CardHeader, {}, sectionHeader(null, __('CSS','ultracache-pro'), __('Verkleinen aan; levering alleen testen.','ultracache-pro'))),
                    el(CardBody, {},
                        renderField('enable_css_minify'),
                        renderField('css_delivery_mode')
                    )
                ),
                el(Card, {className:'ucp-card ucp-opt-premium-card ucp-opt-clean-card ucp-opt-clean-card--wide ucp-cache-tools-card ucp-cache-tools-card--wide'},
                    el(CardHeader, {}, sectionHeader(null, __('JavaScript','ultracache-pro'), __('Alleen aanpassen na frontend-test.','ultracache-pro'), __('Testen','ultracache-pro'), 'ucp-status-badge--warning')),
                    el(CardBody, {},
                        el('div', {className:'ucp-opt-clean-field-grid'},
                            renderField('enable_js_minify'),
                            renderField('defer_all_js'),
                            renderField('accessibility_mode'),
                            renderField('delay_js_control', 'ucp-opt-option--full')
                        ),
                        el('p', {className:'ucp-opt-clean-note'}, __('Test na wijzigingen: menu’s, formulieren, checkout en cookiebanner.', 'ultracache-pro'))
                    )
                )
            ),
            supportMode ? el('details', {className:'ucp-opt-advanced-details'},
                el('summary', {},
                    el('span', {}, __('Geavanceerde optimalisaties','ultracache-pro')),
                    el('small', {}, __('Combineren en uitsluitingen alleen gebruiken bij support of staging-tests.','ultracache-pro'))
                ),
                el(Card, {className:'ucp-card ucp-opt-premium-card ucp-cache-tools-card'},
                    el(CardBody, {},
                        el('div', {className:'ucp-opt-grid ucp-opt-grid--two'},
                            renderField('enable_css_combine'),
                            renderField('enable_js_combine')
                        ),
                        el('div', {className:'ucp-opt-grid ucp-opt-grid--three ucp-opt-textarea-grid'},
                            renderField('css_exclusions'),
                            renderField('delay_js_exclusions'),
                            renderField('html_exclude_urls')
                        )
                    )
                )
            ) : null
        );
    }

    function PremiumSettingsPage(props) {
        var supportMode = window.location && /(?:[?&])ucp_support=1(?:&|$)/.test(window.location.search || '');
        var allGroups = settingsGroups[props.kind] || [];
        var rawGroups = allGroups.filter(function(group){ return supportMode || !group.advanced; });
        function pageMeta(kind) {
            var map = {
                media: {eyebrow: __('Media','ultracache-pro'), title: __('Afbeeldingen optimaliseren','ultracache-pro'), text: __('Snellere beelden, veilige lazyload en voorspelbare fonts zonder visuele breuk.' ,'ultracache-pro'), badge: __('Veilige media','ultracache-pro'), badgeClass: 'ucp-status-badge--good'},
                preload: {eyebrow: __('Preload','ultracache-pro'), title: __('Cache slim vooraf opbouwen','ultracache-pro'), text: __('Laat UltraCache belangrijke pagina’s klaarzetten zonder de server onnodig zwaar te belasten.','ultracache-pro'), badge: __('Rustige queue','ultracache-pro'), badgeClass: 'ucp-status-badge--good'},
                advanced: {eyebrow: __('Regels','ultracache-pro'), title: __('Uitzonderingen overzichtelijk beheren','ultracache-pro'), text: __('Gebruik regels alleen voor pagina’s, cookies of parameters die bewust anders gecachet moeten worden.','ultracache-pro'), badge: __('Veilig afbakenen','ultracache-pro'), badgeClass: 'ucp-status-badge--neutral'},
                database: {eyebrow: __('Database','ultracache-pro'), title: __('Onderhoud met controle','ultracache-pro'), text: __('Ruim alleen op wat je bewust kiest. Maak bij destructieve acties eerst een database-back-up.','ultracache-pro'), badge: __('Backup bij risico','ultracache-pro'), badgeClass: 'ucp-status-badge--warning'},
                diagnostics: {eyebrow: __('Tools','ultracache-pro'), title: __('Controle en technische hulpmiddelen','ultracache-pro'), text: __('Zet alleen diagnostiek of externe koppelingen aan wanneer je ze nodig hebt voor controle of support.','ultracache-pro'), badge: __('Technisch','ultracache-pro'), badgeClass: 'ucp-status-badge--neutral'}
            };
            return map[kind] || {eyebrow: __('Instellingen','ultracache-pro'), title: __('Instellingen','ultracache-pro'), text: __('Beheer de instellingen in rustige groepen.','ultracache-pro'), badge: '', badgeClass: 'ucp-status-badge--neutral'};
        }
        function getField(key) { return getGroupField(rawGroups, key); }
        function getAnyField(key) { return getGroupField(allGroups, key); }
        function databaseCounts() {
            return (((props.status || {}).databaseCleanup || {}).counts) || {};
        }
        function databaseCountItems() {
            var counts = databaseCounts();
            function n(key){ return parseInt(counts[key] || 0, 10) || 0; }
            return [
                {key:'revisions', label:__('Revisies','ultracache-pro'), value:n('revisions'), text:sprintf(__('%d revisies in je database.','ultracache-pro'), n('revisions'))},
                {key:'auto_drafts', label:__('Automatische concepten','ultracache-pro'), value:n('auto_drafts'), text:sprintf(__('%d automatische concepten in je database.','ultracache-pro'), n('auto_drafts'))},
                {key:'drafts', label:__('Concepten','ultracache-pro'), value:n('drafts'), text:sprintf(__('%d gewone concepten in je database.','ultracache-pro'), n('drafts'))},
                {key:'trash_posts', label:__('Prullenbakberichten','ultracache-pro'), value:n('trash_posts'), text:sprintf(__('%d verwijderde berichten in je database.','ultracache-pro'), n('trash_posts'))},
                {key:'spam_comments', label:__('Spamreacties','ultracache-pro'), value:n('spam_comments'), text:sprintf(__('%d spamreacties in je database.','ultracache-pro'), n('spam_comments'))},
                {key:'trash_comments', label:__('Prullenbakreacties','ultracache-pro'), value:n('trash_comments'), text:sprintf(__('%d verwijderde reacties in je database.','ultracache-pro'), n('trash_comments'))},
                {key:'expired_transients', label:__('Verlopen transients','ultracache-pro'), value:n('expired_transients'), text:sprintf(__('%d verlopen transients in je database.','ultracache-pro'), n('expired_transients'))},
                {key:'transients', label:__('Transients totaal','ultracache-pro'), value:n('transients'), text:sprintf(__('%d transients in je database.','ultracache-pro'), n('transients'))},
                {key:'optimizable_tables', label:__('Tabel-overhead','ultracache-pro'), value:n('optimizable_tables'), text:sprintf(__('%d te optimaliseren WordPress-tabellen in je database.','ultracache-pro'), n('optimizable_tables'))}
            ];
        }
        function databaseCountsCard() {
            if (props.kind !== 'database') { return null; }
            var items = databaseCountItems();
            return el(Card, {className:'ucp-card ucp-db-counts-card'},
                el(CardHeader, {}, el('div', {className:'ucp-compact-section-head'},
                    el('div', {},
                        el('span', {className:'ucp-eyebrow'}, __('Database status','ultracache-pro')),
                        el('h2', {}, __('Huidige aantallen','ultracache-pro')),
                        el('p', {}, __('Controleer eerst wat er verwijderd of geoptimaliseerd kan worden.','ultracache-pro'))
                    ),
                    el('span', {className:'ucp-status-badge ucp-status-badge--neutral'}, __('Live telling','ultracache-pro'))
                )),
                el(CardBody, {}, el('div', {className:'ucp-db-counts-grid'}, items.map(function(item){
                    return el('div', {className:'ucp-db-count-item', key:item.key},
                        el('strong', {}, String(item.value)),
                        el('span', {}, item.label),
                        el('p', {}, item.text)
                    );
                })))
            );
        }
        function databaseFieldWithCount(field) {
            if (props.kind !== 'database' || !field) { return field; }
            var counts = databaseCounts();
            var map = {
                db_cleanup_post_revisions:['revisions', __('revisies in je database.','ultracache-pro')],
                db_cleanup_auto_drafts:['auto_drafts', __('automatische concepten in je database.','ultracache-pro')],
                db_cleanup_drafts:['drafts', __('gewone concepten in je database.','ultracache-pro')],
                db_cleanup_trashed_posts:['trash_posts', __('verwijderde berichten in je database.','ultracache-pro')],
                db_cleanup_spam_comments:['spam_comments', __('spamreacties in je database.','ultracache-pro')],
                db_cleanup_trashed_comments:['trash_comments', __('verwijderde reacties in je database.','ultracache-pro')],
                db_cleanup_expired_transients:['expired_transients', __('verlopen transients in je database.','ultracache-pro')],
                db_cleanup_all_transients:['transients', __('transients in je database.','ultracache-pro')],
                db_cleanup_optimize_tables:['plugin_tables', __('UltraCache-tabellen beschikbaar.','ultracache-pro')],
                db_cleanup_optimize_all_tables:['optimizable_tables', __('te optimaliseren WordPress-tabellen in je database.','ultracache-pro')]
            };
            var meta = map[field[0]];
            if (!meta) { return field; }
            var amount = parseInt(counts[meta[0]] || 0, 10) || 0;
            var clone = field.slice();
            clone[3] = (clone[3] ? clone[3] + ' ' : '') + sprintf(__('%d %s','ultracache-pro'), amount, meta[1]);
            return clone;
        }
        function renderAnyFieldObject(key) {
            var field = getAnyField(key);
            if (!field) { return null; }
            return renderFieldObject(field);
        }
        function supplementalCards() {
            if (props.kind !== 'media' || supportMode) { return null; }
            return el(Fragment, {},
                    el(Card, {className:'ucp-card ucp-compact-card ucp-compact-card--advanced ucp-compact-card--adaptive-images ucp-cache-tools-card'},
                    el(CardHeader, {}, el('div', {className:'ucp-compact-section-head'},
                        el('div', {},
                            el('span', {className:'ucp-eyebrow'}, __('Geavanceerd — eerst testen','ultracache-pro')),
                            el('h2', {}, __('Adaptieve afbeeldingen','ultracache-pro')),
                            el('p', {}, __('Levert kleinere afbeeldingsformaten per schermgrootte. Logo’s, icons, hero/LCP-beelden en productgalerijen blijven beschermd.','ultracache-pro'))
                        ),
                        el('span', {className:'ucp-status-badge ucp-status-badge--warning'}, __('Handmatig controleren','ultracache-pro'))
                    )),
                    el(CardBody, {},
                        el('div', {className:'ucp-compact-field-grid ucp-compact-field-grid--two'},
                            renderAnyFieldObject('enable_image_cdn'),
                            renderAnyFieldObject('enable_image_cdn_transforms'),
                            renderAnyFieldObject('enable_adaptive_image_srcset'),
                            renderAnyFieldObject('image_cdn_transform_provider'),
                            renderAnyFieldObject('image_cdn_base'),
                            renderAnyFieldObject('image_cdn_widths')
                        )
                    )
                ),
                el(Card, {className:'ucp-card ucp-compact-card ucp-compact-card--font-suggestions ucp-cache-tools-card'},
                    el(CardHeader, {}, el('div', {className:'ucp-compact-section-head'},
                        el('div', {},
                            el('h2', {}, __('Boven-de-vouw fonts','ultracache-pro')),
                            el('p', {}, __('Laat UltraCache maximaal enkele lokale fonts voorstellen of beheer de lijst handmatig. Te veel preloads kunnen vertragen.','ultracache-pro'))
                        ),
                        el('span', {className:'ucp-status-badge ucp-status-badge--warning'}, __('Geavanceerd','ultracache-pro'))
                    )),
                    el(CardBody, {},
                        el('div', {className:'ucp-compact-field-grid ucp-compact-field-grid--two'},
                            renderAnyFieldObject('enable_auto_font_preloads'),
                            renderAnyFieldObject('preload_fonts')
                        ),
                        el('p', {className:'ucp-muted'}, __('Accepteer alleen fonts die echt boven de vouw nodig zijn. Controleer LCP en console na wijzigingen.','ultracache-pro'))
                    )
                )
            );
        }
        function renderFieldObject(field) {
            if (!field) { return null; }
            if (props.kind === 'media' && field[0] === 'image_quality' && !supportMode && ((props.settings || {}).image_optimization_mode || 'off') === 'off') { return null; }
            field = databaseFieldWithCount(field);
            var type = field[2] || 'text';
            var fullTypes = {textarea:true, lcp_images:true, css_delivery:true};
            var fullKeys = {lazyload_exclusions:true, lazy_render_selectors:true, preload_fonts:true, preload_exclude_urls:true, exclude_urls:true, exclude_cookies:true, exclude_user_agents:true, cache_vary_cookies:true, cache_query_string_inclusions:true};
            var cls = 'ucp-compact-option ucp-cache-tools-option';
            if (fullTypes[type] || fullKeys[field[0]]) { cls += ' ucp-compact-option--full'; }
            return el('div', {key:field[0], className:cls},
                el(SettingField,{field:field, kind:props.kind, settings:props.settings, status:props.status || {}, setSettings:props.setSettings, addNotice:props.addNotice, setStatus:props.setStatus})
            );
        }
        function sectionHeader(group) {
            var badge = group.advanced ? __('Extra controle','ultracache-pro') : '';
            var description = {
                'Afbeeldingen': __('Optimaliseer uploads zonder andere image optimizers te kruisen.','ultracache-pro'),
                'Lazyload & LCP': __('Bescherm belangrijke beelden en laat alleen offscreen media later laden.','ultracache-pro'),

                'Geavanceerde media-rendering': __('Gebruik dit alleen na visuele controle op staging.','ultracache-pro'),
                'Font details': __('Gebruik dit alleen als lokale font-ranges bewust zijn ingericht.','ultracache-pro'),
                'Fonts': __('Maak fonts voorspelbaar en voorkom onnodige vertraging.','ultracache-pro'),
                'Externe bronnen lokaal hosten': __('Alleen gebruiken als privacy, tracking en visuele output gecontroleerd zijn.','ultracache-pro'),
                'Adaptieve afbeeldingen': __('Gebruik CDN-resizing alleen met een provider die width/quality-transforms ondersteunt.','ultracache-pro'),
                'Cache opbouwen': __('Kies hoe UltraCache pagina’s vooraf klaarzet.','ultracache-pro'),
                'Navigatie versnellen': __('Versnelt navigatie voorzichtig, vooral bij gewone pagina’s.','ultracache-pro'),
                'Uitsluitingen': __('Sluit persoonlijke, checkout- of filterpagina’s uit.','ultracache-pro'),
                'Pagina’s nooit cachen': __('Voorkom cache op persoonlijke, checkout-, filter- of portaalpagina’s.','ultracache-pro'),
                'Pagina’s altijd verversen': __('Laat belangrijke overzichten automatisch mee legen na contentwijzigingen.','ultracache-pro'),
                'Pagina’s niet preloaden': __('Voorkom onnodige preload op zware, dynamische of niet-publieke pagina’s.','ultracache-pro'),
                'Technische uitsluitingen': __('Alleen gebruiken wanneer cookies, taal of apparaat echt andere content geven.','ultracache-pro'),
                'Query strings': __('Cache alleen veilige parameters die geen persoonlijke content tonen.','ultracache-pro'),
                'Automatisch onderhoud': __('Kies of UltraCache database-opschoning mag plannen.','ultracache-pro'),
                'Berichten opruimen': __('Ruim oude contentresten op, maar behoud herstelruimte.','ultracache-pro'),
                'Reacties opruimen': __('Verwijder alleen reacties die al als spam of prullenbak gemarkeerd zijn.','ultracache-pro'),
                'Transients opruimen': __('Verlopen tijdelijke data is veilig; alles verwijderen blijft voorzichtig.','ultracache-pro'),
                'Backup nodig': __('Gebruik deze opties alleen bewust en met een recente database-back-up.','ultracache-pro'),
                'Tabellen optimaliseren': __('Alleen uitvoeren met een recente database-back-up.','ultracache-pro'),
                'WordPress opschonen': __('Gebruik veilige opschoning; agressief alleen na stagingtest.','ultracache-pro'),
                'Diagnostiek en logs': __('Bewaar alleen technische informatie die je echt nodig hebt.','ultracache-pro'),
                'Core Web Vitals fielddata': __('Meet echte bezoekersdata zonder onnodige persoonlijke gegevens.','ultracache-pro'),
                'Headless renderer en fragments': __('Extern en personalisatiegevoelig: alleen gebruiken als dit bewust is ingericht.','ultracache-pro'),
                'Bewaartermijnen': __('Houd logs en taken kort genoeg voor privacy en overzicht.','ultracache-pro'),
                'Verwijderen': __('Bepaal bewust wat er gebeurt bij deïnstallatie.','ultracache-pro')
            }[group.title] || '';
            return el('div', {className:'ucp-compact-section-head'},
                el('div', {},
                    group.advanced ? el('span', {className:'ucp-eyebrow'}, __('Geavanceerd','ultracache-pro')) : null,
                    el('h2', {}, group.title),
                    description ? el('p', {}, description) : null
                ),
                badge ? el('span', {className:'ucp-status-badge ucp-status-badge--neutral'}, badge) : null
            );
        }
        function cardClass(group) {
            var fields = group.fields || [];
            var hasTextarea = fields.some(function(field){ return field[2] === 'textarea'; });
            var cls = 'ucp-card ucp-compact-card ucp-cache-tools-card components-card';
            if (hasTextarea) { cls += ' ucp-compact-card--textarea'; }
            if (group.advanced) { cls += ' ucp-compact-card--advanced'; }
            return cls;
        }
        function gridClass(group) {
            var fields = group.fields || [];
            if (fields.length <= 1) { return 'ucp-compact-field-grid ucp-compact-field-grid--one'; }
            if (fields.length <= 2) { return 'ucp-compact-field-grid ucp-compact-field-grid--two'; }
            return 'ucp-compact-field-grid ucp-compact-field-grid--auto';
        }
        function mediaCleanCard(className, eyebrow, title, text, badge, badgeClass, children) {
            return el(Card, {className:'ucp-card ucp-media-card ' + (className || '')},
                el(CardHeader, {}, el('div', {className:'ucp-media-card-head'},
                    el('div', {},
                        eyebrow ? el('span', {className:'ucp-eyebrow'}, eyebrow) : null,
                        el('h2', {}, title),
                        text ? el('p', {}, text) : null
                    ),
                    badge ? el('span', {className:'ucp-status-badge ' + (badgeClass || 'ucp-status-badge--neutral')}, badge) : null
                )),
                el(CardBody, {}, children)
            );
        }
        function mediaField(key, extraClass) {
            var field = getAnyField(key);
            if (!field) { return null; }
            var cls = 'ucp-media-option';
            if (extraClass) { cls += ' ' + extraClass; }
            return el('div', {className:cls, key:key}, renderFieldObject(field));
        }
        function renderMediaCleanPage() {
            var meta = pageMeta('media');
            var mediaSettings = props.settings || {};
            var adaptiveEnabled = !!parseInt(mediaSettings.enable_image_cdn || 0, 10) || !!parseInt(mediaSettings.enable_image_cdn_transforms || 0, 10) || !!parseInt(mediaSettings.enable_adaptive_image_srcset || 0, 10);
            var fontPreloadsEnabled = !!parseInt(mediaSettings.enable_auto_font_preloads || 0, 10);
            function mediaCacheField(key, extraClass){
                var field = getAnyField(key);
                if (!field) { return null; }
                if (key === 'image_quality' && !supportMode && ((props.settings || {}).image_optimization_mode || 'off') === 'off') { return null; }
                return el('div', {key:key, className:'ucp-cache-tools-option ' + (extraClass || ''), title:field[3] || ''},
                    el(SettingField,{field:field, kind:'media', settings:props.settings, status:props.status || {}, setSettings:props.setSettings, addNotice:props.addNotice, setStatus:props.setStatus})
                );
            }
            function compactFields(className, fields){
                return el('div', {className:'ucp-cache-tools-fields ' + (className || '')}, fields.filter(Boolean));
            }
            function mediaHero(title, text, badge){
                return el(Card, {className:'ucp-card ucp-tools-hero ucp-cache-tools-hero'},
                    el(CardBody, {},
                        el('div', {className:'ucp-tools-hero__inner'},
                            el('div', {},
                                el('span', {className:'ucp-eyebrow'}, meta.eyebrow),
                                el('h2', {}, title),
                                el('p', {}, text)
                            ),
                            el('span', {className:'ucp-status-badge ucp-status-badge--neutral'}, badge)
                        )
                    )
                );
            }
            function mediaCard(title, text, badge, body, className){
                return el(Card, {className:'ucp-card ucp-action-group-card ucp-cache-tools-card ' + (className || '')},
                    el(CardHeader, {},
                        el('div', {className:'ucp-action-group-heading'},
                            el('div', {},
                                el('h2', {}, title),
                                text ? el('p', {className:'ucp-action-group-description'}, text) : null
                            ),
                            badge ? el('span', {className:'ucp-status-badge ucp-status-badge--neutral'}, badge) : null
                        )
                    ),
                    el(CardBody, {}, body)
                );
            }
            function inlineDetails(summary, body, className){
                return el('details', {className:'ucp-media-inline-details ' + (className || '')},
                    el('summary', {}, el('span', {}, summary)),
                    el('div', {className:'ucp-media-inline-details__body'}, body)
                );
            }
            return el('div', {className:'ucp-settings-page ucp-settings-page--media ucp-cache-tools-page ucp-media-calm-page'},
                mediaHero(__('Media optimaliseren','ultracache-pro'), __('Snellere beelden, veilige lazyload en voorspelbare fonts zonder visuele breuk.','ultracache-pro'), __('Veilige media','ultracache-pro')),
                el('div', {className:'ucp-cache-tools-grid ucp-media-calm-grid'},
                    mediaCard(__('Uploads en layout','ultracache-pro'), __('Beheer uploadoptimalisatie en afbeeldingsafmetingen.','ultracache-pro'), __('Basis','ultracache-pro'),
                        el('div', {className:'ucp-cache-tools-stack'},
                            compactFields('ucp-media-fields--single', [
                                mediaCacheField('image_optimization_mode', 'ucp-cache-tools-option--full'),
                                mediaCacheField('enable_add_image_dimensions', 'ucp-cache-tools-option--full ucp-media-toggle-center')
                            ]),
                            mediaCacheField('image_quality', 'ucp-cache-tools-option--full')
                        ),
                        'ucp-media-calm-card ucp-media-uploads-card'
                    ),
                    mediaCard(__('Zichtbare beelden beschermen','ultracache-pro'), __('Bescherm logo’s, boven-de-vouw beelden en sliders.','ultracache-pro'), __('Visueel testen','ultracache-pro'),
                        el('div', {className:'ucp-cache-tools-stack'},
                            compactFields('ucp-cache-tools-fields--two', [
                                mediaCacheField('media_lazyload_mode'),
                                mediaCacheField('lcp_image_mode')
                            ]),
                            inlineDetails(__('Uitsluitingen beheren','ultracache-pro'), mediaCacheField('lazyload_exclusions', 'ucp-cache-tools-option--full'), 'ucp-media-exclusions-details')
                        ),
                        'ucp-media-calm-card ucp-media-visual-card'
                    ),
                    mediaCard(__('Adaptieve afbeeldingen','ultracache-pro'), null, __('Geavanceerd','ultracache-pro'),
                        el('div', {className:'ucp-cache-tools-stack'},
                            compactFields('ucp-cache-tools-fields--three ucp-media-calm-toggle-row', [
                                mediaCacheField('enable_image_cdn', 'ucp-media-toggle-center'),
                                mediaCacheField('enable_image_cdn_transforms', 'ucp-media-toggle-center'),
                                mediaCacheField('enable_adaptive_image_srcset', 'ucp-media-toggle-center')
                            ]),
                            adaptiveEnabled ? el(Fragment, {},
                                compactFields('ucp-media-fields--single', [
                                    mediaCacheField('image_cdn_transform_provider', 'ucp-cache-tools-option--full'),
                                    mediaCacheField('image_cdn_base', 'ucp-cache-tools-option--full'),
                                    mediaCacheField('image_cdn_widths', 'ucp-cache-tools-option--full')
                                ])
                            ) : null
                        ),
                        'ucp-cache-tools-card--wide ucp-media-calm-card ucp-media-adaptive-card'
                    ),
                    mediaCard(__('Fontoptimalisatie','ultracache-pro'), __('Beheer fontstrategie en kritieke preloads.','ultracache-pro'), __('Compact','ultracache-pro'),
                        el('div', {className:'ucp-cache-tools-stack'},
                            compactFields('ucp-media-fields--single', [
                                mediaCacheField('google_fonts_mode', 'ucp-cache-tools-option--full'),
                                mediaCacheField('enable_auto_font_preloads', 'ucp-cache-tools-option--full ucp-media-toggle-center')
                            ]),
                            fontPreloadsEnabled ? inlineDetails(__('Kritieke fonts beheren','ultracache-pro'), mediaCacheField('preload_fonts', 'ucp-cache-tools-option--full'), 'ucp-media-font-details') : null
                        ),
                        'ucp-cache-tools-card--wide ucp-media-calm-card ucp-media-font-card'
                    )
                ),
                supportMode ? el('details', {className:'ucp-tools-advanced-details ucp-media-advanced-details'},
                    el('summary', {}, el('span', {}, __('Extra media-opties voor supportmodus','ultracache-pro'))),
                    el('div', {className:'ucp-compact-card-grid', style:{padding:'18px 20px 20px'}}, rawGroups.filter(function(group){ return group.advanced && group.title !== 'Adaptieve afbeeldingen' && group.title !== 'Font details'; }).map(function(group, index){
                        return el(Card, {key:(group.title || 'group') + '-' + index, className:cardClass(group)},
                            el(CardHeader, {}, sectionHeader(group)),
                            el(CardBody, {}, el('div', {className:gridClass(group)}, (group.fields || []).map(renderFieldObject)))
                        );
                    }))
                ) : null
            );
        }
        if (props.kind === 'media') { return renderMediaCleanPage(); }
        var meta = pageMeta(props.kind);
        return el('div', {className:'ucp-settings-page ucp-settings-page--' + props.kind + ' ucp-compact-page ucp-compact-page--' + props.kind + ' ucp-cache-tools-page'},
            el(Card, {className:'ucp-card ucp-compact-hero'},
                el(CardBody, {},
                    el('div', {className:'ucp-compact-hero__inner'},
                        el('div', {},
                            el('span', {className:'ucp-eyebrow'}, meta.eyebrow),
                            el('h2', {}, meta.title),
                            el('p', {}, meta.text)
                        ),
                        meta.badge ? el('span', {className:'ucp-status-badge ' + meta.badgeClass}, meta.badge) : null
                    )
                )
            ),
            databaseCountsCard(),
            el('div', {className:'ucp-compact-card-grid'},
                rawGroups.map(function(group, index){
                    return el(Card, {key:(group.title || 'group') + '-' + index, className:cardClass(group)},
                        el(CardHeader, {}, sectionHeader(group)),
                        el(CardBody, {}, el('div', {className:gridClass(group)}, (group.fields || []).map(renderFieldObject)))
                    );
                }).concat([supplementalCards()])
            )
        );
    }

    function WooCommerceSettingsPage(props) {
        var groups = settingsGroups.cache || [];
        function field(key){ return getGroupField(groups, key); }
        function cloneField(key, overrides){
            var f = field(key);
            if (!f) { return null; }
            f = f.slice();
            overrides = overrides || {};
            if (overrides.label) { f[1] = overrides.label; }
            if (overrides.help) { f[3] = overrides.help; }
            return f;
        }
        function renderSetting(key, overrides){
            var f = cloneField(key, overrides);
            return f ? el('div', {className:'ucp-woo-setting-row ucp-cache-tools-option'}, el(SettingField,{field:f, kind:'cache', settings:props.settings, status:props.status || {}, setSettings:props.setSettings, addNotice:props.addNotice, setStatus:props.setStatus})) : null;
        }
        var enabled = boolSetting(props.settings, 'enable_woocommerce_rules') || boolSetting(props.settings, 'woocommerce_safety_mode');
        var fragmentsOn = boolSetting(props.settings, 'optimize_cart_fragments');
        var protectedItems = [
            ['cart', __('Winkelwagen','ultracache-pro'), __('Winkelwagenpagina’s worden niet agressief gecachet.','ultracache-pro')],
            ['checkout', __('Checkout','ultracache-pro'), __('Betaalflow blijft betrouwbaar.','ultracache-pro')],
            ['account', __('Account','ultracache-pro'), __('Persoonlijke pagina’s blijven vers.','ultracache-pro')],
            ['payment', __('Betalingen','ultracache-pro'), __('Betaalscripts blijven beschikbaar.','ultracache-pro')],
            ['gallery', __('Productmedia','ultracache-pro'), __('Galerijen en productbeelden blijven beschermd.','ultracache-pro')]
        ];
        return el('div', {className:'ucp-settings-page ucp-settings-page--woocommerce ucp-compact-page ucp-woo-page ucp-cache-tools-page'},
            el(Card, {className:'ucp-card ucp-compact-hero ucp-woo-hero ucp-tools-hero ucp-cache-tools-hero'}, el(CardBody, {},
                el('div', {className:'ucp-compact-hero__inner'},
                    el('div', {},
                        el('span', {className:'ucp-eyebrow'}, __('WooCommerce','ultracache-pro')),
                        el('h2', {}, __('WooCommerce bescherming','ultracache-pro')),
                        el('p', {}, __('Bescherm winkelwagen, checkout, account en betaalflow met dezelfde rustige opbouw als de andere UltraCache-instellingen.','ultracache-pro'))
                    ),
                    el(StatusBadge, {state:enabled ? 'good' : 'info'}, enabled ? __('Bescherming actief','ultracache-pro') : __('Basis','ultracache-pro'))
                )
            )),
            el('div', {className:'ucp-woo-settings-grid'},
                el(Card, {className:'ucp-card ucp-compact-card ucp-woo-settings-card ucp-cache-tools-card'},
                    el(CardHeader, {}, el('div', {className:'ucp-compact-section-head'}, el('div', {}, el('h2', {}, __('Veilige cache','ultracache-pro')), el('p', {}, __('Houd dynamische webshoproutes betrouwbaar.','ultracache-pro'))))),
                    el(CardBody, {}, el('div', {className:'ucp-woo-setting-list'},
                        renderSetting('enable_woocommerce_rules', {label:__('WooCommerce bescherming','ultracache-pro'), help:__('Sluit winkelwagen, checkout en account automatisch uit van agressieve cache.','ultracache-pro')}),
                        renderSetting('disable_logged_in_optimizations', {label:__('Ingelogde gebruikers beschermen','ultracache-pro'), help:__('Voorkomt storende optimalisaties tijdens beheer, accountgebruik en aankopen.','ultracache-pro')})
                    ))
                ),
                el(Card, {className:'ucp-card ucp-compact-card ucp-woo-settings-card ucp-cache-tools-card'},
                    el(CardHeader, {}, el('div', {className:'ucp-compact-section-head'}, el('div', {}, el('h2', {}, __('Winkelwagen-performance','ultracache-pro')), el('p', {}, __('Versnel lege winkelwagens zonder gevulde manden te verstoren.','ultracache-pro'))))),
                    el(CardBody, {}, el('div', {className:'ucp-woo-setting-list'},
                        renderSetting('optimize_cart_fragments', {label:__('Cart fragments optimaliseren','ultracache-pro'), help:__('Minder onnodige requests bij bezoekers zonder actieve winkelwagen.','ultracache-pro')}),
                        renderSetting('limit_cart_fragments_to_woo', {label:__('Alleen laden waar nodig','ultracache-pro'), help:__('Laadt WooCommerce winkelwagenscripts alleen op relevante pagina’s.','ultracache-pro')})
                    ))
                )
            ),
            el(Card, {className:'ucp-card ucp-woo-protection-card ucp-cache-tools-card'},
                el(CardHeader, {}, el('div', {className:'ucp-section-heading ucp-woo-section-heading'},
                    el('div', {}, el('h2', {}, __('Bescherming','ultracache-pro')), el('p', {}, __('UltraCache houdt gevoelige webshoponderdelen buiten risicovolle optimalisaties.','ultracache-pro'))),
                    el(StatusBadge,{state:'good'}, fragmentsOn ? __('Cart geoptimaliseerd','ultracache-pro') : __('Veilig','ultracache-pro'))
                )),
                el(CardBody, {}, el('div', {className:'ucp-woo-protection-list'}, protectedItems.map(function(item){
                    return el('div', {key:item[0], className:'ucp-status-row ucp-woo-protection-row'},
                        el('span', {}, el('strong', {}, item[1]), el('small', {}, item[2])),
                        el(StatusBadge,{state:'good'}, __('Veilig','ultracache-pro'))
                    );
                })))
            )
        );
    }

    function ServerCdnSettingsPage(props) {
        var groups = settingsGroups.optimization || [];
        function field(key){ return getGroupField(groups, key); }
        function renderField(key, extraClass, overrides){
            var f = field(key);
            if (!f) { return null; }
            if (overrides) {
                f = f.slice();
                if (overrides.label) { f[1] = overrides.label; }
                if (overrides.help !== undefined) { f[3] = overrides.help; }
            }
            return el('div', {className:'ucp-server-field ucp-cache-tools-option ' + (extraClass || '')}, el(SettingField,{field:f, kind:'optimization', settings:props.settings, status:props.status || {}, setSettings:props.setSettings, addNotice:props.addNotice, setStatus:props.setStatus}));
        }
        var settings = props.settings || {};
        var status = props.status || {};
        var cache = status.cache || {};
        var cdnOn = settings.cdn_rewrite_mode && settings.cdn_rewrite_mode !== 'off';
        var redisOn = boolSetting(settings, 'enable_redis_object_cache');
        var apcuOn = boolSetting(settings, 'enable_apcu_object_cache');
        var objectOn = !!(cache.objectCache || redisOn || apcuOn);
        var cdnNames = String(settings.cdn_cnames || '').trim();
        var cdnNeedsHost = cdnOn && !cdnNames;
        var headline = cdnOn || objectOn ? __('Serveroptimalisatie actief','ultracache-pro') : __('Serveropties klaarzetten','ultracache-pro');
        var subline = cdnOn || objectOn ? __('Beheer CDN en object cache vanuit één compact statusoverzicht.','ultracache-pro') : __('Schakel alleen in wat je hosting of CDN daadwerkelijk ondersteunt.','ultracache-pro');

        function saveServerToggle(key, value, label){
            var previousSettings = Object.assign({}, settings);
            var next = Object.assign({}, previousSettings);
            var payload = {};
            if (key === 'cdn_rewrite_mode') {
                applyCdnRewriteMode(next, value ? 'all' : 'off');
                applyCdnRewriteMode(payload, value ? 'all' : 'off');
            } else {
                next[key] = value ? 1 : 0;
                payload[key] = value ? 1 : 0;
            }
            props.setSettings(next);
            saveSettings(payload).then(function(resp){
                props.setSettings(resp.settings);
                if (resp.status && props.setStatus) { props.setStatus(resp.status); }
                props.addNotice({status:'success', message:label + ' opgeslagen.'});
            }).catch(function(err){
                props.setSettings(previousSettings);
                props.addNotice({status:'error', message:cleanErrorMessage(err, label + ' kon niet worden opgeslagen.')});
            });
        }

        function quickToggle(key, checked, label, help){
            return el('div', {className:'ucp-server-toggle', title:help || ''},
                el(ToggleControl, {label:label, checked:!!checked, onChange:function(v){ saveServerToggle(key, v, label); }})
            );
        }

        function serverCard(card){
            var activeClass = card.active ? 'is-active' : (card.warning ? 'needs-attention' : 'is-passive');
            return el(Card, {key:card.key, className:'ucp-card ucp-server-status-card ucp-cache-tools-card ' + activeClass},
                el(CardBody, {},
                    el('div', {className:'ucp-server-status-card__top'},
                        el('span', {className:'ucp-server-status-card__icon dashicons ' + card.icon, 'aria-hidden':'true'}),
                        el('div', {className:'ucp-server-status-card__title'},
                            el('strong', {}, card.title),
                            el('span', {}, card.text)
                        ),
                        el(StatusBadge, {state:card.warning ? 'warning' : (card.active ? 'good' : 'info')}, card.status)
                    ),
                    el('div', {className:'ucp-server-status-card__meta'},
                        el('span', {className:'ucp-priority-tag ucp-priority-tag--' + card.priorityType}, card.priority),
                        card.switchKey ? quickToggle(card.switchKey, card.active, card.toggleLabel, card.tooltip) : null
                    ),
                    card.action ? el('div', {className:'ucp-server-status-card__action'}, card.action) : null
                )
            );
        }

        var cards = [
            {key:'cdn', icon:'dashicons-cloud', title:__('CDN','ultracache-pro'), text:cdnNeedsHost ? __('CDN staat aan, maar mist een domein.','ultracache-pro') : __('Levert statische bestanden via je CDN.','ultracache-pro'), active:!!cdnOn, warning:cdnNeedsHost, status:cdnNeedsHost ? __('Controle nodig','ultracache-pro') : (cdnOn ? __('Actief','ultracache-pro') : __('Uit','ultracache-pro')), priority:__('Optioneel','ultracache-pro'), priorityType:'info', switchKey:'cdn_rewrite_mode', toggleLabel:__('CDN inschakelen','ultracache-pro'), tooltip:__('Alleen inschakelen bij een losse CDN-URL of CNAME.','ultracache-pro'), action:el('button', {type:'button', className:'button button-secondary ucp-scroll-action', onClick:function(){ var target=document.querySelector('.ucp-server-cdn-panel'); if (target) { target.scrollIntoView({behavior:'smooth', block:'start'}); }}}, __('CDN instellen','ultracache-pro'))},
            {key:'object', icon:'dashicons-database', title:__('Object cache','ultracache-pro'), text:__('Versnelt database-afhankelijke sites.','ultracache-pro'), active:objectOn, status:objectOn ? __('Actief','ultracache-pro') : __('Niet actief','ultracache-pro'), priority:__('Serverafhankelijk','ultracache-pro'), priorityType:'warning', action:el('a', {className:'button button-secondary', href:'admin.php?page=ultracache-object-cache'}, __('Beheren','ultracache-pro'))},
            {key:'redis', icon:'dashicons-networking', title:__('Redis','ultracache-pro'), text:__('Gebruik alleen als Redis beschikbaar is.','ultracache-pro'), active:redisOn, status:redisOn ? __('Aan','ultracache-pro') : __('Uit','ultracache-pro'), priority:__('Aanbevolen bij hosting-support','ultracache-pro'), priorityType:'good', switchKey:'enable_redis_object_cache', toggleLabel:__('Redis inschakelen','ultracache-pro'), tooltip:__('Schakel alleen in wanneer Redis op de server actief en bereikbaar is.','ultracache-pro')},
            {key:'apcu', icon:'dashicons-admin-generic', title:__('APCu','ultracache-pro'), text:__('Lokale object cache voor geschikte servers.','ultracache-pro'), active:apcuOn, status:apcuOn ? __('Aan','ultracache-pro') : __('Uit','ultracache-pro'), priority:__('Optioneel','ultracache-pro'), priorityType:'info', switchKey:'enable_apcu_object_cache', toggleLabel:__('APCu inschakelen','ultracache-pro'), tooltip:__('Alleen gebruiken als APCu beschikbaar is in PHP en past bij de hostingconfiguratie.','ultracache-pro')}
        ];

        return el('div', {className:'ucp-settings-page ucp-settings-page--server ucp-compact-page ucp-server-dashboard-page ucp-cache-tools-page'},
            el(Card, {className:'ucp-card ucp-compact-hero ucp-server-hero ucp-tools-hero ucp-cache-tools-hero'}, el(CardBody, {},
                el('div', {className:'ucp-compact-hero__inner ucp-server-hero__inner'},
                    el('div', {}, el('span', {className:'ucp-eyebrow'}, __('Server & CDN','ultracache-pro')), el('h2', {}, headline), el('p', {}, subline)),
                    el('div', {className:'ucp-server-hero__badges'},
                        el(StatusBadge, {state:cdnOn ? 'good' : 'info'}, cdnOn ? __('CDN actief','ultracache-pro') : __('CDN uit','ultracache-pro')),
                        el(StatusBadge, {state:objectOn ? 'good' : 'info'}, objectOn ? __('Object cache actief','ultracache-pro') : __('Object cache optioneel','ultracache-pro'))
                    )
                )
            )),
            el('div', {className:'ucp-server-status-grid'}, cards.map(serverCard)),
            el(Card, {className:'ucp-card ucp-server-config-card ucp-server-cdn-panel ucp-cache-tools-card'},
                el(CardHeader, {},
                    el('div', {className:'ucp-section-heading'},
                        el('h2', {}, __('CDN-instellingen','ultracache-pro')),
                        el('p', {}, __('Alleen nodig bij een losse CDN-URL. Cloudflare of hosting-CDN zonder URL-rewrite kan uit blijven.','ultracache-pro'))
                    ),
                    el(StatusBadge, {state:cdnNeedsHost ? 'warning' : (cdnOn ? 'good' : 'info')}, cdnNeedsHost ? __('Domein mist','ultracache-pro') : (cdnOn ? __('Actief','ultracache-pro') : __('Uit','ultracache-pro')))
                ),
                el(CardBody, {},
                    el('div', {className:'ucp-server-config-grid'},
                        renderField('cdn_rewrite_mode', '', {label:__('CDN gebruiken voor','ultracache-pro'), help:__('Kies welke statische bestanden via de CDN-URL lopen.','ultracache-pro')}),
                        el('div', {className:'ucp-server-field ' + (cdnNeedsHost ? 'has-warning' : '')},
                            renderField('cdn_cnames', '', {label:__('CDN URL / CNAME','ultracache-pro'), help:__('Bijvoorbeeld cdn.domein.nl. Eén domein per regel.','ultracache-pro')}),
                            cdnNeedsHost ? el('p', {className:'ucp-inline-validation'}, __('Vul eerst een CDN-domein in of zet CDN uit.','ultracache-pro')) : null
                        )
                    ),
                    el('details', {className:'ucp-server-advanced'},
                        el('summary', {}, __('Geavanceerd: uitsluitingen en serverregels','ultracache-pro')),
                        el('div', {className:'ucp-server-advanced__body'},
                            renderField('cdn_exclude', 'ucp-server-field--full', {label:__('CDN-uitsluitingen','ultracache-pro'), help:__('Paden of bestandstypen die niet via de CDN-URL mogen lopen.','ultracache-pro')}),
                            el('p', {className:'ucp-muted'}, __('Tip: test na CDN-wijzigingen altijd afbeeldingen, fonts, formulieren en checkout.','ultracache-pro'))
                        )
                    )
                )
            )
        );
    }

    function PreloadSettingsPage(props) {
        var groups = settingsGroups.preload || [];
        function field(key){ return getGroupField(groups, key); }
        function renderField(key, extraClass, overrides){
            var f = field(key);
            if (!f) { return null; }
            f = f.slice();
            if (overrides) {
                if (overrides.label) { f[1] = overrides.label; }
                if (overrides.help !== undefined) { f[3] = overrides.help; }
            }
            return el('div', {className:'ucp-compact-option ucp-cache-tools-option ' + (extraClass || '')},
                el(SettingField,{field:f, kind:'preload', settings:props.settings, status:props.status || {}, setSettings:props.setSettings, addNotice:props.addNotice, setStatus:props.setStatus})
            );
        }
        var settings = props.settings || {};
        var mode = preloadMode(settings);
        var preloadLabel = mode === 'off' ? __('Uit','ultracache-pro') : (mode === 'homepage' ? __('Homepage','ultracache-pro') : (mode === 'manual' ? __('Handmatig','ultracache-pro') : __('Aanbevolen','ultracache-pro')));
        return el('div', {className:'ucp-settings-page ucp-settings-page--preload ucp-compact-page ucp-preload-clean-page ucp-cache-tools-page'},
            el(Card, {className:'ucp-card ucp-compact-hero ucp-preload-hero ucp-tools-hero ucp-cache-tools-hero'}, el(CardBody, {},
                el('div', {className:'ucp-compact-hero__inner'},
                    el('div', {},
                        el('span', {className:'ucp-eyebrow'}, __('Preload','ultracache-pro')),
                        el('h2', {}, __('Preload overzichtelijk beheren','ultracache-pro')),
                        el('p', {}, __('Zet belangrijke pagina’s vooraf klaar en houd dynamische pagina’s buiten de preload.','ultracache-pro'))
                    ),
                    el(StatusBadge,{state: mode === 'off' ? 'info' : 'good'}, preloadLabel)
                )
            )),
            el(Card, {className:'ucp-card ucp-compact-card ucp-preload-control-card ucp-cache-tools-card'},
                el(CardHeader, {}, el('div', {className:'ucp-compact-section-head'},
                    el('div', {}, el('h2', {}, __('Preload instellingen','ultracache-pro')), el('p', {}, __('De veilige basis staat vooraan; extra browsergedrag blijft beperkt en controleerbaar.','ultracache-pro'))),
                    el(StatusBadge,{state: mode === 'off' ? 'info' : 'good'}, mode === 'off' ? __('Uit','ultracache-pro') : __('Actief','ultracache-pro'))
                )),
                el(CardBody, {}, el('div', {className:'ucp-preload-settings-grid'},
                    renderField('preload_mode', '', {label:__('Cache opbouwen','ultracache-pro'), help:__('Kies hoe UltraCache pagina’s vooraf klaarzet.','ultracache-pro')}),
                    renderField('enable_prefetch_links', '', {label:__('Link preload','ultracache-pro'), help:__('Versnelt normale navigatie via hover of klik.','ultracache-pro')}),
                    renderField('speculative_loading_mode', '', {label:__('Browser-navigatie','ultracache-pro'), help:__('Gebruik de veilige browsermodus; prerender blijft verborgen voor support.','ultracache-pro')})
                ))
            ),
            el(Card, {className:'ucp-card ucp-compact-card ucp-preload-exclusions-card ucp-cache-tools-card'},
                el(CardHeader, {}, el('div', {className:'ucp-compact-section-head'},
                    el('div', {}, el('h2', {}, __('Uitsluitingen','ultracache-pro')), el('p', {}, __('Sluit dynamische of persoonlijke pagina’s uit van preload.','ultracache-pro'))),
                    el(StatusBadge,{state:'info'}, __('Veilig afbakenen','ultracache-pro'))
                )),
                el(CardBody, {}, renderField('preload_exclude_urls', 'ucp-compact-option--full', {label:__('Niet preloaden','ultracache-pro'), help:__('Eén patroon per regel, zoals checkout, account, zoekresultaten of filterpagina’s.','ultracache-pro')}))
            )
        );
    }

    function SettingsPage(props) {
        if (props.kind === 'cache') {
            return el(CacheSettingsPage, props);
        }
        if (props.kind === 'optimization') {
            return el(OptimizationSettingsPage, props);
        }
        if (props.kind === 'woocommerce') {
            return el(WooCommerceSettingsPage, props);
        }
        if (props.kind === 'server') {
            return el(ServerCdnSettingsPage, props);
        }
        if (props.kind === 'preload') {
            return el(PreloadSettingsPage, props);
        }
        if (['media','advanced','database','diagnostics'].indexOf(props.kind) !== -1) {
            return el(PremiumSettingsPage, props);
        }
        var rawGroups = settingsGroups[props.kind] || settingsGroups.optimization;
        var hideManagedSettings = false;
        var groups = rawGroups.map(function(group, index){
            var visibleFields = (group.fields || []).filter(function(field){ return !hideManagedSettings || !isManagedSetting(field[0]); });
            return Object.assign({__id:(group.key || slugify(group.title) || ('group-' + index)) + '-' + index}, group, {fields: visibleFields});
        }).filter(function(group){ return group.fields && group.fields.length; });
        var ids = groups.map(function(group){ return group.__id; });
        var columns = 1;
        var layoutAnnouncement = '';
        var groupsById = {};
        groups.forEach(function(group){ groupsById[group.__id] = group; });
        var orderedGroups = groups;
        function renderSettingsCard(group){
            return el(Card, {
                key:group.__id,
                className:'ucp-card ucp-layout-card ucp-settings-card ucp-cache-tools-card'
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
        }
        return el('div', {className:'ucp-settings-page ucp-settings-page--' + props.kind + ' ucp-cache-tools-page'},
            el('div', {className:'screen-reader-text', 'aria-live':'polite'}, layoutAnnouncement),
            props.kind === 'advanced' ? el(SettingsIntro, {kind:'advanced'}) : null,
            el('div', {className:'ucp-layout-grid ucp-layout-grid--settings ucp-layout-grid--simple', style:{'--ucp-grid-columns': columns}}, orderedGroups.map(function(group){
                return renderSettingsCard(group);
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
            ) : null
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

    function normalizeMediaWidthTokens(value) {
        var source = Array.isArray(value) ? value.join('\n') : String(value || '');
        var seen = {};
        return source.split(/[\s,;]+/).map(function(token){
            return String(token || '').replace(/[^0-9]/g, '');
        }).filter(function(token){
            var width = parseInt(token, 10);
            if (!token || !width || width < 1 || width > 10000 || seen[token]) { return false; }
            seen[token] = true;
            return true;
        });
    }

    function mediaWidthTokensToValue(tokens) {
        return normalizeMediaWidthTokens(tokens).join('\n');
    }

    function MediaWidthsControl(props) {
        var tokenState = useState(normalizeMediaWidthTokens(props.currentValue));
        var tokens = tokenState[0], setTokens = tokenState[1];
        var suggestions = ['320','360','480','640','768','1024','1280','1536','1920'];
        useEffect(function(){
            setTokens(normalizeMediaWidthTokens(props.currentValue));
        }, [props.currentValue]);

        function update(nextTokens) {
            setTokens(normalizeMediaWidthTokens(nextTokens));
            props.setDirty(true);
        }

        return el('div', {className:'ucp-media-widths-control'},
            el(FormTokenField, {
                label: props.label,
                value: tokens,
                suggestions: suggestions,
                disabled: props.saving,
                onChange: update,
                __experimentalExpandOnFocus: true
            }),
            props.help ? el('p', {className:'components-base-control__help'}, props.help + ' ' + __('Gebruik losse waarden zoals 360, 640 en 1024. Enter of komma voegt een breedte toe.', 'ultracache-pro')) : null,
            props.dirty ? el(Button, {variant:'secondary', isBusy:props.saving, disabled:props.saving, onClick:function(){ props.commit(mediaWidthTokensToValue(tokens)); }}, __('Opslaan','ultracache-pro')) : null
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
            control = el('div', {className:'ucp-toggle-save-row'},
                el(ToggleControl,{label:label, help:help, checked:!!parseInt(currentValue || 0,10), disabled:saving || isLocked, onChange:function(v){commit(v ? 1 : 0);}}),
                saving ? el('span',{className:'ucp-saving-text ucp-saving-text--inline'},__('Opslaan…','ultracache-pro')) : null
            );
        } else if (key === 'image_cdn_widths' && FormTokenField) {
            control = el(MediaWidthsControl,{label:label, help:help, currentValue:currentValue, saving:saving || isLocked, dirty:dirty, setDirty:setDirty, commit:commit});
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
        return el('div',{className:'ucp-setting-field ucp-setting-field--' + type + layoutClass, 'data-ucp-field-key':key}, badge, control, warning, type !== 'toggle' && saving ? el('span',{className:'ucp-saving-text'},__('Opslaan…','ultracache-pro')) : null);
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
            cdn_rewrite_mode:{level:'staging', label:__('Geavanceerd — eerst testen','ultracache-pro'), text:__('Gebruik CDN rewrite eerst op staging; checkout en gevoelige formulieren worden automatisch beschermd.','ultracache-pro')},
            enable_self_host_third_party_assets:{level:'external', label:__('Externe bronnen','ultracache-pro'), text:__('Controleer privacy, bronallowlist en visuele output na lokaal hosten.','ultracache-pro')},
            serve_cache_to_shoppers:{level:'shop', label:__('Shopgevoelig','ultracache-pro'), text:__('Alleen gebruiken als cart, checkout, account en sessiecookies aantoonbaar veilig zijn uitgesloten.','ultracache-pro')},
            db_cleanup_drafts:{level:'destructive', label:__('Backup nodig','ultracache-pro'), text:__('Gewone concepten kunnen content in voorbereiding zijn. Maak eerst een database-backup.','ultracache-pro')},
            db_cleanup_all_transients:{level:'destructive', label:__('Destructief','ultracache-pro'), text:__('Maak eerst een database-backup. Deze actie kan niet ongedaan worden gemaakt.','ultracache-pro')},
            db_cleanup_optimize_tables:{level:'destructive', label:__('Backup nodig','ultracache-pro'), text:__('Maak eerst een database-backup. Deze actie kan niet ongedaan worden gemaakt.','ultracache-pro')},
            db_cleanup_optimize_all_tables:{level:'destructive', label:__('Alle tabellen','ultracache-pro'), text:__('Optimaliseert alle WordPress-tabellen en draait alleen handmatig met backupbevestiging. Niet gebruiken zonder recente database-backup.','ultracache-pro')},
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
        var supportMode = window.location && /(?:[?&])ucp_support=1(?:&|$)/.test(window.location.search || '');
        return el('div', {className:'ucp-tools-premium-page' + (supportMode ? ' ucp-tools-premium-page--support' : '')},
            el(Card, {className:'ucp-card ucp-tools-hero'},
                el(CardBody, {},
                    el('div', {className:'ucp-tools-hero__inner'},
                        el('div', {},
                            el('span', {className:'ucp-eyebrow'}, __('Tools','ultracache-pro')),
                            el('h2', {}, __('Snelle acties','ultracache-pro')),
                            el('p', {}, __('Klantvriendelijke acties voor cache, preload, support, database en import/export. Geavanceerde technische instellingen blijven uit beeld.','ultracache-pro'))
                        ),
                        el('span', {className:'ucp-status-badge ucp-status-badge--neutral'}, supportMode ? __('Supportmodus','ultracache-pro') : __('Klantvriendelijk','ultracache-pro'))
                    )
                )
            ),
            el(ActionsPage, Object.assign({}, props, {title:__('Tools','ultracache-pro')})),
            supportMode ? el('details', {className:'ucp-tools-advanced-details'},
                el('summary', {},
                    el('span', {}, __('Geavanceerde diagnose en instellingen','ultracache-pro')),
                    el('small', {}, __('Alleen openen voor support, renderer, logs en bewaartermijnen.','ultracache-pro'))
                ),
                el(SettingsPage, Object.assign({}, props, {kind:'diagnostics'}))
            ) : null
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
        var allowedTabs = visibleTabsForMode(uiMode).filter(function(tab){ return tab.key !== 'woocommerce' || hasActiveWooCommerce(status || {}); }).map(function(tab){ return tab.key; });
        var effectiveTab = allowedTabs.indexOf(activeTab) !== -1 ? activeTab : 'dashboard';
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
            effectiveTab === 'dashboard' ? el(DashboardPage,Object.assign({}, shared, {status:status,onOpenWizard:function(){setWizardOpen(true);}})) : null,
            effectiveTab === 'cache' ? el(SettingsPage,Object.assign({}, shared, {kind:'cache'})) : null,
            effectiveTab === 'optimization' ? el(SettingsPage,Object.assign({}, shared, {kind:'optimization'})) : null,
            effectiveTab === 'media' ? el(SettingsPage,Object.assign({}, shared, {kind:'media'})) : null,
            effectiveTab === 'woocommerce' ? el(SettingsPage,Object.assign({}, shared, {kind:'woocommerce'})) : null,
            effectiveTab === 'preload' ? el(SettingsPage,Object.assign({}, shared, {kind:'preload'})) : null,
            effectiveTab === 'server' ? el(SettingsPage,Object.assign({}, shared, {kind:'server'})) : null,
            effectiveTab === 'advanced' ? el(SettingsPage,Object.assign({}, shared, {kind:'advanced'})) : null,
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
