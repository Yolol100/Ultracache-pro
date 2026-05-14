(function (wp) {
    'use strict';

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var render = wp.element.render;
    var __ = wp.i18n.__;
    var apiFetch = wp.apiFetch;
    var c = wp.components;
    var Card = c.Card, CardHeader = c.CardHeader, CardBody = c.CardBody, CardFooter = c.CardFooter;
    var Button = c.Button, Notice = c.Notice, Spinner = c.Spinner, Placeholder = c.Placeholder;
    var ToggleControl = c.ToggleControl, TextControl = c.TextControl, TextareaControl = c.TextareaControl;
    var SelectControl = c.SelectControl, Panel = c.Panel, PanelBody = c.PanelBody;
    var ExternalLink = c.ExternalLink;
    var NumberControl = c.__experimentalNumberControl || c.NumberControl || TextControl;
    var config = window.UCP_ADMIN_CONFIG || {};

    if (apiFetch && apiFetch.use && config.nonce) {
        apiFetch.use(apiFetch.createNonceMiddleware(config.nonce));
    }

    var tabs = [
        {key:'dashboard', label:__('Dashboard','ultracache-pro')},
        {key:'optimization', label:__('Bestandsoptimalisatie','ultracache-pro')},
        {key:'media', label:__('Media','ultracache-pro')},
        {key:'preload', label:__('Preloaden','ultracache-pro')},
        {key:'advanced', label:__('Geavanceerde regels','ultracache-pro')},
        {key:'database', label:__('Database','ultracache-pro')},
        {key:'cdn', label:'CDN'},
        {key:'heartbeat', label:'Heartbeat'},
        {key:'diagnostics', label:__('Diagnostiek','ultracache-pro')},
        {key:'tools', label:__('Tools','ultracache-pro')}
    ];

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
            el('div', {className:'ucp-layout-toolbar__controls', role:'toolbar', 'aria-label':props.title || __('Indeling','ultracache-pro')}, options.map(function(option){
                return el(Button, {
                    key:option,
                    variant:columns === option ? 'primary' : 'secondary',
                    onClick:function(){ props.onChange(option); }
                }, option + 'x' + option);
            }))
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
    function runAction(action){ return request('actions/' + action, {method:'POST'}); }
    function getJobs(){ return request('diagnostics/jobs'); }
    function getLogs(){ return request('diagnostics/logs'); }
    function getRequests(){ return request('diagnostics/requests'); }
    function getBrowserScan(){ return request('diagnostics/browser-scan'); }
    function saveBrowserScan(payload){ return request('actions/browser-scan', {method:'POST', data:payload}); }
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
        var state = props.state || 'info';
        return el('span', {className:'ucp-status-badge ucp-status-badge--' + state}, props.children || props.label);
    }
    function boolBadge(value, good, bad) {
        return value ? el(StatusBadge, {state:'good'}, good || __('Goed','ultracache-pro')) : el(StatusBadge, {state:'warning'}, bad || __('Check','ultracache-pro'));
    }
    function infoBadge(text) { return el(StatusBadge, {state:'info'}, text); }

    function MetricCard(props) {
        return el(Card, {className:'ucp-card ucp-metric-card'},
            el(CardBody, {},
                el('div', {className:'ucp-metric-label'}, props.label),
                el('div', {className:'ucp-metric-value'}, props.value),
                props.help ? el('p', {className:'ucp-muted'}, props.help) : null
            )
        );
    }

    function AdminShell(props) {
        return el('div', {className:'ucp-admin-app__shell'},
            el('div', {className:'ucp-app-header'},
                el('div', {},
                    el('h1', {}, __('UltraCache Pro','ultracache-pro')),
                    el('p', {}, __('WordPress-native beheeromgeving voor cache, optimalisatie, tools en diagnostiek.','ultracache-pro'))
                ),
                el('div', {className:'ucp-header-actions'},
                    el(Button, {variant:'secondary', onClick:props.onOpenWizard}, __('Setup','ultracache-pro')),
                    el(Button, {variant:'secondary', isBusy:props.loading, disabled:props.loading, onClick:props.onRefresh}, props.loading ? __('Verversen…','ultracache-pro') : __('Status verversen','ultracache-pro'))
                )
            ),
            el('nav', {className:'ucp-nav-tabs', role:'tablist', 'aria-label':__('UltraCache Pro hoofdtabbladen','ultracache-pro')}, tabs.map(function(tab){
                return el(Button, {
                    key:tab.key,
                    role:'tab',
                    variant:props.activeTab === tab.key ? 'primary' : 'secondary',
                    'aria-selected':props.activeTab === tab.key,
                    'aria-current':props.activeTab === tab.key ? 'page' : undefined,
                    onClick:function(){props.onTab(tab.key);}
                }, tab.label);
            })),
            props.children
        );
    }

    function StatusRow(props) {
        return el('div', {className:'ucp-status-row'}, el('span', {}, props.label), el('strong', {}, props.value));
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
    function DashboardPage(props) {
        var status = props.status || {};
        return el('div', {className:'ucp-page ucp-page--dashboard'},
            el(PresetCards, Object.assign({}, props, {dashboard:true})),
            el(DashboardHero, Object.assign({}, props, {status:status})),
            el(QueueRunnerCard, Object.assign({}, props, {status:status})),
            el(RecommendationCard, {status:status})
        );
    }

    function QueueRunnerCard(props) {
        var queue = (props.status && props.status.queue) || {};
        var runner = queue.runner || {};
        var hasJobs = (queue.pending || 0) + (queue.retrying || 0) > 0;
        if (!hasJobs) {
            return null;
        }
        return el(Card, {className:'ucp-card ucp-queue-card'},
            el(CardHeader, {}, el('h2', {}, __('Achtergrondtaken','ultracache-pro'))),
            el(CardBody, {},
                runner.cronDisabled ? el(Notice, {status:'warning', isDismissible:false}, __('WP-Cron lijkt uitgeschakeld. UltraCache verwerkt taken daarom ook via de admin fallback en via de knop hieronder. Stel voor productie bij voorkeur een echte server-cron in.','ultracache-pro')) : null,
                runner.due > 0 ? el(Notice, {status:'info', isDismissible:false}, __('Er staan taken klaar om nu te verwerken.','ultracache-pro')) : el(Notice, {status:'info', isDismissible:false}, __('Er staan taken in de wachtrij, maar ze wachten nog op hun volgende poging.','ultracache-pro')),
                el('div', {className:'ucp-action-list'},
                    el(ActionButton, {action:'run-due-jobs', label:__('Verwerk taken nu','ultracache-pro'), help:__('Verwerkt direct de eerstvolgende wachtrijtaken zonder te wachten op WP-Cron.','ultracache-pro'), variant:'primary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh}),
                    el(ActionButton, {action:'retry-failed-jobs', label:__('Mislukte taken opnieuw proberen','ultracache-pro'), help:__('Zet mislukte taken terug in de wachtrij.','ultracache-pro'), addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                ),
                el('p', {className:'ucp-muted'}, runner.nextCron ? __('Volgende geplande runner: ','ultracache-pro') + runner.nextCron : __('Geen geplande runner gevonden. De plugin plant deze opnieuw zodra er taken zijn.','ultracache-pro'))
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
                help:'Voor builders, shops en websites met veel plugins. Cache, preload, CSS/JS verkleinen en veilige lazy load staan aan; combineren, Delay JS en Used CSS blijven uit.',
                level:'Laag risico',
                bestFor:'Builders, WooCommerce, veel plugins',
                values:{active_preset:'safe', enable_cache:1, browser_cache_headers:1, compatibility_mode:1, woocommerce_safety_mode:1, enable_preload:1, enable_preload_queue:1, preload_homepage:1, preload_sitemaps:1, preload_batch_size:10, preload_max_urls:150, preload_delay_ms:750, remove_html_comments:1, enable_html_minify:0, enable_css_minify:1, enable_css_combine:0, css_delivery_mode:'none', enable_used_css:0, enable_used_css_delivery:0, enable_critical_css:0, enable_css_queue:0, enable_js_minify:1, enable_js_combine:0, enable_defer_js_fallback:0, defer_all_js:0, enable_delay_js:0, delay_js_mode:'specified', delay_js_safe_mode:1, enable_lazy_images:1, lazyload_exclude_leading_images:1, enable_lazy_iframes:0, enable_lazy_youtube_preview:0, preload_critical_images:1, enable_add_image_dimensions:1, enable_local_google_fonts:0, enable_disable_google_fonts:0, enable_font_display_swap:1, enable_prefetch_links:1, enable_speculative_loading:0, enable_lazy_render:0, enable_rest_cache:0, enable_stale_cache:0, enable_db_cleanup:0, db_cleanup_frequency:'off'}
            },
            {
                key:'balanced',
                title:'Gebalanceerd',
                help:'Aanbevolen standaard voor de meeste websites. Minify, preload, lazy images, font-display swap en veilige preload staan aan; combineren en Delay JS blijven uit.',
                level:'Aanbevolen',
                bestFor:'Meeste bedrijfswebsites',
                values:{active_preset:'balanced', enable_cache:1, browser_cache_headers:1, compatibility_mode:1, woocommerce_safety_mode:1, enable_preload:1, enable_preload_queue:1, preload_homepage:1, preload_sitemaps:1, preload_batch_size:15, preload_max_urls:250, preload_delay_ms:500, remove_html_comments:1, enable_html_minify:0, enable_css_minify:1, enable_css_combine:0, css_delivery_mode:'none', enable_used_css:0, enable_used_css_delivery:0, enable_critical_css:0, enable_css_queue:0, enable_js_minify:1, enable_js_combine:0, enable_defer_js_fallback:1, defer_all_js:0, enable_delay_js:0, delay_js_mode:'specified', delay_js_safe_mode:1, enable_lazy_images:1, lazyload_exclude_leading_images:1, enable_lazy_iframes:1, enable_lazy_youtube_preview:1, preload_critical_images:1, enable_add_image_dimensions:1, enable_local_google_fonts:1, enable_disable_google_fonts:0, enable_font_display_swap:1, enable_prefetch_links:1, enable_speculative_loading:0, enable_lazy_render:0, enable_rest_cache:0, enable_stale_cache:0, enable_db_cleanup:0, db_cleanup_frequency:'off'}
            },
            {
                key:'fast',
                title:'Snelste modus',
                help:'Voor staging of technische gebruikers. Activeert Used CSS, Delay JS en agressievere lazy loading. Combine blijft uit omdat dit met HTTP/2 en Delay JS vaak niet nodig is.',
                level:'Test op staging',
                bestFor:'Performance tuning met QA',
                values:{active_preset:'fast', enable_cache:1, browser_cache_headers:1, compatibility_mode:0, woocommerce_safety_mode:1, enable_preload:1, enable_preload_queue:1, preload_homepage:1, preload_sitemaps:1, preload_batch_size:20, preload_max_urls:500, preload_delay_ms:350, remove_html_comments:1, enable_html_minify:1, enable_css_minify:1, enable_css_combine:0, css_delivery_mode:'remove_unused', enable_used_css:1, enable_used_css_delivery:1, enable_critical_css:0, enable_css_queue:1, enable_js_minify:1, enable_js_combine:0, enable_defer_js_fallback:1, defer_all_js:0, enable_delay_js:1, delay_js_mode:'all', delay_js_safe_mode:0, enable_lazy_images:1, lazyload_exclude_leading_images:1, enable_lazy_iframes:1, enable_lazy_youtube_preview:1, preload_critical_images:1, enable_add_image_dimensions:1, enable_local_google_fonts:1, enable_disable_google_fonts:0, enable_font_display_swap:1, enable_prefetch_links:1, enable_speculative_loading:0, enable_lazy_render:1, enable_rest_cache:0, enable_stale_cache:1, enable_db_cleanup:0, db_cleanup_frequency:'off'}
            },
            {
                key:'shop',
                title:'Webshop veilig',
                help:'Voor WooCommerce. Beschermt cart, checkout, account en AJAX flows. Cache, preload, minify, lazy images en lokale fonts staan aan; Delay JS, combineren en REST cache blijven uit.',
                level:'Shop veilig',
                bestFor:'WooCommerce en LMS/membership',
                values:{active_preset:'shop', enable_cache:1, browser_cache_headers:1, compatibility_mode:1, woocommerce_safety_mode:1, enable_woocommerce_rules:1, enable_preload:1, enable_preload_queue:1, preload_homepage:1, preload_sitemaps:1, preload_batch_size:10, preload_max_urls:200, preload_delay_ms:750, remove_html_comments:1, enable_html_minify:0, enable_css_minify:1, enable_css_combine:0, css_delivery_mode:'none', enable_used_css:0, enable_used_css_delivery:0, enable_critical_css:0, enable_css_queue:0, enable_js_minify:1, enable_js_combine:0, enable_defer_js_fallback:0, defer_all_js:0, enable_delay_js:0, delay_js_mode:'specified', delay_js_safe_mode:1, enable_lazy_images:1, lazyload_exclude_leading_images:1, enable_lazy_iframes:1, enable_lazy_youtube_preview:1, preload_critical_images:1, enable_add_image_dimensions:1, enable_local_google_fonts:1, enable_disable_google_fonts:0, enable_font_display_swap:1, enable_prefetch_links:1, enable_speculative_loading:0, enable_lazy_render:0, enable_rest_cache:0, enable_stale_cache:0, enable_db_cleanup:0, db_cleanup_frequency:'off'}
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
                    el('p', {}, __('Online best practices toegepast: kies direct een veilige preset. Minify staat standaard aan; combineren, Delay JS en Used CSS blijven uit tot je bewust test.','ultracache-pro'))
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
        function execute(){
            setBusy(true); setModal(false);
            var promise = runAction(props.action);
            promise.then(function(resp){
                props.addNotice({status:'success', message:(resp && resp.message) || __('Actie uitgevoerd.','ultracache-pro')});
                if (resp && resp.status && props.setStatus) props.setStatus(resp.status);
                if (props.onComplete) props.onComplete(resp);
            }).catch(function(err){
                props.addNotice({status:'error', message:cleanErrorMessage(err, __('Actie mislukt.','ultracache-pro'))});
            }).finally(function(){ setBusy(false); });
        }
        var button = el(Button, {variant:props.variant || 'secondary', isDestructive:!!props.destructive, isBusy:busy, disabled:busy, onClick:function(){props.confirm ? setModal(true) : execute();}}, busy ? __('Bezig…','ultracache-pro') : props.label);
        var dialog = modal ? el(CustomDialog, {
                title:props.label,
                eyebrow:props.destructive ? __('Bevestigen','ultracache-pro') : __('Actie uitvoeren','ultracache-pro'),
                onClose:function(){setModal(false);},
                footer:el('div', {className:'ucp-modal-actions'},
                    el(Button,{variant:'secondary', onClick:function(){setModal(false);}},__('Annuleren','ultracache-pro')),
                    el(Button,{variant:'primary', isDestructive:!!props.destructive, onClick:execute}, props.label)
                )
            },
            el('p', {className:'ucp-dialog-intro'}, props.confirmText || props.help)
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
            onClick:execute,
            className:'ucp-bulk-action-button'
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
                    {action:'preload', label:__('Cache opwarmen','ultracache-pro'), help:__('Start de preload queue om pagina’s vooraf in cache te zetten.')},
                    {action:'run-due-jobs', label:__('Verwerk taken nu','ultracache-pro'), help:__('Verwerkt taken die nu klaarstaan zonder te wachten op WP-Cron.'), variant:'primary'},
                    {action:'retry-failed-jobs', label:__('Mislukte taken opnieuw proberen','ultracache-pro'), help:__('Zet mislukte of opnieuw geplande achtergrondtaken terug in de wachtrij.'), confirm:true}
                ]
            },
            {
                key:'css',
                title:__('CSS','ultracache-pro'),
                description:__('Herbouw of wis CSS-bestanden.','ultracache-pro'),
                bulk:{label:__('CSS volledig vernieuwen','ultracache-pro'), actions:['clear-minified-css','critical-css','used-css','run-due-jobs'], success:__('CSS-bestanden gewist en Critical/Used CSS opnieuw gestart.','ultracache-pro')},
                actions:[
                    {action:'critical-css', label:__('Genereer kritieke CSS','ultracache-pro'), help:__('Start CSS-generatie voor de homepage.')},
                    {action:'used-css', label:__('Used CSS opnieuw genereren','ultracache-pro'), help:__('Bouwt gebruikte CSS-artifacten opnieuw op.')},
                    {action:'clear-minified-css', label:__('Verkleinde CSS wissen','ultracache-pro'), help:__('Verwijdert opgebouwde CSS-bestanden zodat ze opnieuw kunnen worden aangemaakt.'), variant:'tertiary'}
                ]
            },
            {
                key:'js',
                title:__('JavaScript','ultracache-pro'),
                description:__('Wis opgebouwde JavaScript-bestanden.','ultracache-pro'),
                actions:[
                    {action:'clear-minified-js', label:__('Verkleinde JS wissen','ultracache-pro'), help:__('Verwijdert opgebouwde JavaScript-bestanden zodat ze opnieuw kunnen worden aangemaakt.'), variant:'tertiary'}
                ]
            },
            {
                key:'support',
                title:__('Support','ultracache-pro'),
                description:__('Controle en supportacties.','ultracache-pro'),
                bulk:{label:__('Controlepakket uitvoeren','ultracache-pro'), actions:['health-check','runtime-cache-test','detect-conflicts','release-checklist'], success:__('Health check, runtime test, conflictcheck en release checklist uitgevoerd.','ultracache-pro')},
                actions:[
                    {action:'health-check', label:__('Health check uitvoeren','ultracache-pro'), help:__('Controleert cachemap, drop-in, conflicten en runtimevoorwaarden.','ultracache-pro')}
                ]
            },
            {
                key:'maintenance',
                title:__('Risico','ultracache-pro'),
                description:__('Gebruik alleen bewust en na backup.','ultracache-pro'),
                danger:true,
                actions:[
                    {action:'database-cleanup', label:__('Database opschonen','ultracache-pro'), help:__('Maak eerst een database-backup. Deze actie kan niet ongedaan worden gemaakt.'), variant:'primary', destructive:true, confirm:true}
                ]
            }
        ];
        var layoutKey = 'ucp-layout-tools';
        var colsKey = layoutKey + '-cols';
        var ids = actionGroups.map(function(group){ return group.key; });
        var orderState = useState(function(){ return normalizeOrder(storageGet(layoutKey + '-order', ids), ids); }), order = orderState[0], setOrder = orderState[1];
        var colsState = useState(function(){ return parseInt(storageGet(colsKey, 2), 10) || 2; }), columns = colsState[0], setColumns = colsState[1];
        var dragState = useState(null), draggingId = dragState[0], setDraggingId = dragState[1];
        useEffect(function(){
            setOrder(normalizeOrder(storageGet(layoutKey + '-order', ids), ids));
            setColumns(parseInt(storageGet(colsKey, 2), 10) || 2);
        }, [props.title]);
        function updateOrder(nextOrder) {
            var normalized = normalizeOrder(nextOrder, ids);
            setOrder(normalized);
            storageSet(layoutKey + '-order', normalized);
        }
        function updateColumns(nextColumns) {
            setColumns(nextColumns);
            storageSet(colsKey, nextColumns);
        }
        var groupsById = {};
        actionGroups.forEach(function(group){ groupsById[group.key] = group; });
        var orderedGroups = normalizeOrder(order, ids).map(function(id){ return groupsById[id]; }).filter(Boolean);
        return el('div', {className:'ucp-page ucp-actions-page'},
            el(LayoutToolbar, {
                title:__('Kaartenindeling','ultracache-pro'),
                help:__('Kies 1x1 t/m 4x4. In 1x1 zet UltraCache compacte velden netjes op een rij; langere of risicovolle inhoud blijft automatisch onder elkaar.','ultracache-pro'),
                columns:columns,
                onChange:updateColumns
            }),
            el('div', {className:'ucp-layout-grid ucp-layout-grid--actions', style:{'--ucp-grid-columns': columns}}, orderedGroups.map(function(group){
                return el(Card, {
                    key:group.key,
                    className:'ucp-card ucp-layout-card ucp-action-group-card' + (group.danger ? ' ucp-action-group-card--danger' : '') + (draggingId === group.key ? ' is-dragging' : ''),
                    draggable:true,
                    onDragStart:function(){ setDraggingId(group.key); },
                    onDragEnd:function(){ setDraggingId(null); },
                    onDragOver:function(event){ event.preventDefault(); },
                    onDrop:function(event){ event.preventDefault(); if (draggingId && draggingId !== group.key) { updateOrder(moveIdBefore(order, draggingId, group.key)); } setDraggingId(null); }
                },
                    el(CardHeader, {},
                        el('div', {className:'ucp-layout-card__header'},
                            el('div', {className:'ucp-action-group-heading'},
                                el('h2', {}, group.title),
                                el('p', {className:'ucp-action-group-description'}, group.description)
                            ),
                            group.bulk ? el('div', {className:'ucp-action-group-header-actions'},
                                el(BulkActionButton, {label:group.bulk.label, actions:group.bulk.actions, successMessage:group.bulk.success, addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                            ) : null,
                            el('button', {type:'button', className:'ucp-layout-card__drag', title:__('Verplaats kaart met pijltjestoetsen of sleep met de muis','ultracache-pro'), 'aria-label':__('Verplaats kaart. Gebruik pijltoetsen, Home of End.','ultracache-pro'), onKeyDown:function(event){ keyboardReorder(event, group.key, order, updateOrder); }}, '↕')
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
                    el('section', {className:'ucp-import-export-box ucp-import-export-box--import'},
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
            {title:'HTML', fields:[['remove_html_comments','HTML comments verwijderen','toggle','Veilige eerste stap.'],['enable_html_minify','HTML verkleinen','toggle','Test builders en checkout na inschakelen.'],['html_exclude_urls','HTML uitsluitingen','textarea','Eén URL/patroon per regel.']]},
            {title:'CSS', fields:[['enable_css_minify','CSS verkleinen','toggle','Veilig voor de meeste sites.'],['enable_css_combine','CSS combineren','toggle','Wordt automatisch vergrendeld bij HTTP/2/3, builders, formulieren, andere optimalisatieplugins of actieve CSS-levering.'],['css_delivery_mode','CSS-levering optimaliseren','css_delivery','Kies één methode. Aanbevolen is Ongebruikte CSS verwijderen; gebruik Asynchroon laden alleen als fallback.',[['none','Uit - veiligste keuze'],['remove_unused','Ongebruikte CSS verwijderen - aanbevolen'],['async','CSS asynchroon laden - fallback']]],['css_exclusions','CSS uitsluitingen / safelist','textarea','Handles, bestandsnamen, selectors of fragmenten die niet mogen worden aangepast.']]},
            {title:'JavaScript', fields:[['enable_js_minify','JavaScript verkleinen','toggle','Veilig voor de meeste sites.'],['enable_js_combine','JavaScript combineren','toggle','Wordt automatisch vergrendeld bij HTTP/2/3, Delay JS, script strategies, shops, builders, formulieren, cookieplugins of andere optimalisatieplugins.'],['defer_all_js','Defer JS','toggle','Stelt scripts later in de laadvolgorde.'],['enable_delay_js','Delay JS','toggle','Kan formulieren, sliders of checkout beïnvloeden.'],['delay_js_mode','Delay JS modus','select','Kies hoe agressief Delay JS werkt.',[['specified','Alleen opgegeven scripts'],['all','Alle scripts']]],['delay_js_exclusions','Delay JS uitsluitingen','textarea','Eén script/fragment per regel.']]},
            {title:'Extra', fields:[['enable_remove_emojis','Emoji scripts verwijderen','toggle','Kleine opruiming.'],['enable_disable_embeds','Embeds uitschakelen','toggle','Gebruik alleen als je geen embeds nodig hebt.'],['enable_prefetch_links','Links prefetch/load voorbereiden','toggle','Versnelt navigatie.'],['enable_speculative_loading','Speculative loading','toggle','Test formulieren en checkout.']]}
        ],
        media: [
            {title:'Basis', fields:[[ 'enable_lazy_images','Afbeeldingen lazyloaden','toggle','Laad afbeeldingen pas wanneer ze bijna zichtbaar zijn. Laat logo’s en hero-afbeeldingen uitsluiten.'],['enable_lazy_iframes','Iframes en video lazyloaden','toggle','Aanbevolen voor offscreen video’s en embeds.'],['enable_lazy_youtube_preview','YouTube preview gebruiken','toggle','Vervangt YouTube embeds door een lichte preview tot interactie.'],['enable_add_image_dimensions','Afbeeldingsafmetingen toevoegen','toggle','Aanbevolen om layout shifts te beperken.']]} ,
            {title:'Afbeeldingen', fields:[['preload_critical_images','Kritieke afbeeldingen preloaden','number','Alleen voor zichtbare boven-de-vouw afbeeldingen. Meestal 0 of 1.'],['enable_image_optimization','Afbeeldingen optimaliseren','toggle','Activeert de optimalisatieflow. Gebruik bewust als je al een aparte image optimizer gebruikt.'],['enable_webp_generation','WebP genereren','toggle','Aanbevolen wanneer de server dit ondersteunt.'],['enable_avif_generation','AVIF genereren','toggle','Gebruik alleen als de server dit stabiel ondersteunt.'],['image_quality','Afbeeldingskwaliteit','number','0-100. Gebruik meestal 80-85 voor goede balans.']]} ,
            {title:'Fonts', fields:[['enable_local_google_fonts','Google Fonts lokaal hosten','toggle','Aanbevolen om externe fontrequests te verminderen.'],['enable_font_display_swap','Font-display swap','toggle','Aanbevolen om onzichtbare tekst te beperken.'],['preload_fonts','Fonts preloaden','textarea','Alleen kritieke WOFF2-fontbestanden, één URL per regel.']]} ,
            {title:'Uitsluitingen', fields:[['lazyload_exclusions','LazyLoad uitsluitingen','textarea','Eén patroon per regel. Gebruik voor logo’s, hero-afbeeldingen of sliders die direct zichtbaar zijn.'],['enable_lazy_render','Lazy render inschakelen','toggle','Stelt onder-de-vouw blokken uit op basis van de selectors hieronder. Test dit visueel.'],['lazy_render_selectors','Lazy render selectors','textarea','CSS selectors voor onder-de-vouw blokken. Werkt alleen wanneer Lazy render is ingeschakeld.'],['enable_disable_google_fonts','Google Fonts uitschakelen','toggle','Alleen gebruiken als je zeker weet dat thema/plugins geen Google Fonts meer nodig hebben.'],['preconnect_domains','Preconnect domeinen','textarea','Alleen 1-3 echt belangrijke externe domeinen, één domein per regel.'],['dns_prefetch_domains','DNS prefetch domeinen','textarea','Minder kritieke externe domeinen, één domein per regel.']]}
        ],
        preload: [
            {title:'Cache opbouwen', fields:[['enable_preload','Preload inschakelen','toggle','Bouwt cache vooraf op zodat bezoekers sneller een cachebestand krijgen.'],['enable_preload_queue','Queue gebruiken','toggle','Aanbevolen. Verwerkt URL’s gecontroleerd en voorkomt piekbelasting.'],['preload_sitemaps','Sitemaps gebruiken','toggle','Aanbevolen. Gebruikt publieke sitemaps als bron voor preload-URL’s.'],['preload_homepage','Homepage altijd meenemen','toggle','Aanbevolen. De homepage is meestal de belangrijkste cachepagina.']]} ,
            {title:'Link preload', fields:[['enable_prefetch_links','Link preload activeren','toggle','Verbetert de ervaren navigatiesnelheid bij hover/klik. Heeft meestal geen effect op PageSpeed-score. Gebruik voorzichtig op shops of sites met veel unieke links.']]} ,
            {title:'Uitsluitingen', fields:[['preload_exclude_urls','URL’s uitsluiten van preload','textarea','Eén URL of patroon per regel. Gebruik voor author-, account-, checkout-, zoek-, filter- of paginatiepagina’s.']]} ,
            {title:'Serverbelasting', fields:[['preload_batch_size','Batchgrootte','number','Aantal URL’s per run. Gebruik 10-20 op shared hosting en verhoog alleen als de server stabiel blijft.'],['preload_max_urls','Maximaal aantal URL’s','number','Maximaal aantal URL’s per preloadronde.'],['preload_delay_ms','Pauze tussen requests','number','Milliseconden tussen requests. 500 ms is een veilige basis; verhoog dit als hosting traag reageert.'],['preload_content_scope','Content scope','text','Bijv. posts,archives,terms. Laat standaard staan als je twijfelt.']]}
        ],
        advanced: [
            {title:'Cache levensduur', fields:[['cache_lifespan','Cache bewaren voor','number','Aantal uren voordat een cachebestand automatisch wordt vernieuwd. 10 uur is veilig voor de meeste websites.'],['enable_stale_cache','Stale cache gebruiken','toggle','Serveer tijdelijk oude cache als vernieuwen mislukt. Handig bij piekbelasting of tijdelijke serverfouten.'],['stale_cache_lifespan','Stale cache bewaren voor','number','Aantal uren dat oude cache nog gebruikt mag worden als vernieuwen niet lukt.']]},
            {title:'Pagina’s', fields:[['exclude_urls','Nooit URL’s cachen','textarea','Eén pad of patroon per regel. Gebruik dit voor cart, checkout, account, zoekresultaten, filters of persoonlijke content.']]},
            {title:'Cookies / agents', fields:[['exclude_cookies','Nooit cachen bij cookies','textarea','Eén cookie of gedeeltelijke cookienaam per regel. Nuttig voor winkelwagens en gepersonaliseerde content.'],['exclude_user_agents','Nooit cachen voor user-agents','textarea','Eén user-agent fragment per regel. Laat leeg tenzij een apparaat, browser of bot afwijkende content krijgt.']]},
            {title:'Automatisch legen bij wijzigingen', fields:[['always_purge_urls','Altijd extra URL’s legen','textarea','Eén URL of patroon per regel. Gebruik voor pagina’s die mee moeten verversen wanneer content wijzigt, zoals homepage of archieven.']]},
            {title:'Query strings cachen', fields:[['cache_query_strings','Query strings toestaan','toggle','Alleen inschakelen voor bekende parameters die geen persoonlijke content tonen.'],['cache_query_string_inclusions','Toegestane query parameters','textarea','Eén parameter per regel, zonder vraagteken. Bijvoorbeeld lang, currency of min_price.']]},
            {title:'Developer cache', fields:[['enable_fragment_cache','Fragment cache','toggle','Voor ontwikkelaars: cachet losse outputfragmenten. Alleen gebruiken als je weet welke fragments veilig zijn.'],['fragment_cache_ttl','Fragment cache TTL','number','Aantal seconden voordat fragment cache verloopt.'],['enable_rest_cache','REST cache','toggle','Cachet REST API responses. Test formulieren, zoekfuncties en externe koppelingen na inschakelen.'],['rest_cache_ttl','REST cache TTL','number','Aantal seconden voordat REST cache verloopt.'],['rest_cache_inclusions','REST inclusies','textarea','Eén endpoint per regel dat expliciet gecachet mag worden.'],['rest_cache_exclusions','REST uitsluitingen','textarea','Eén endpoint per regel dat nooit gecachet mag worden.'],['accessibility_mode','Accessibility mode','toggle','Vermindert risicovolle optimalisaties om interacties, focus en dynamische UI veiliger te houden.']]}
        ],
        database: [
            {title:'Automatisch onderhoud', fields:[['enable_db_cleanup','Automatisch opschonen plannen','toggle','Laat UltraCache geselecteerde database-onderdelen volgens schema opruimen. Gebruik dit pas nadat je de selectie bewust hebt gecontroleerd.'],['db_cleanup_frequency','Frequentie','select','Kies hoe vaak de geplande opschoning mag draaien.',[['off','Uit'],['daily','Dagelijks'],['weekly','Wekelijks'],['monthly','Maandelijks']]]]},
            {title:'Berichten opruimen', fields:[['db_cleanup_post_revisions','Revisies opschonen','toggle','Verwijdert oude revisies, maar bewaart het ingestelde aantal recente versies per bericht.'],['db_keep_post_revisions','Revisies bewaren','number','Aanbevolen: bewaar minimaal 5 revisies voor contentherstel.'],['db_cleanup_auto_drafts','Automatische concepten opschonen','toggle','Verwijdert oude automatische concepten die niet meer gebruikt worden.'],['db_cleanup_trashed_posts','Prullenbakberichten verwijderen','toggle','Verwijdert berichten en pagina’s die al in de prullenbak staan.']]},
            {title:'Reacties opruimen', fields:[['db_cleanup_spam_comments','Spamreacties verwijderen','toggle','Verwijdert reacties die al als spam zijn gemarkeerd.'],['db_cleanup_trashed_comments','Prullenbakreacties verwijderen','toggle','Verwijdert reacties die al in de prullenbak staan.']]},
            {title:'Transients opruimen', fields:[['db_cleanup_expired_transients','Verlopen transients verwijderen','toggle','Veilige basis. Verwijdert tijdelijke data waarvan de verloopdatum voorbij is.'],['db_cleanup_all_transients','Alle transients verwijderen','toggle','Kan tijdelijke caches van plugins wissen. Gebruik dit alleen wanneer je problemen met tijdelijke data vermoedt.']]},
            {title:'Tabellen optimaliseren', fields:[['db_cleanup_optimize_tables','Databasetabellen optimaliseren','toggle','Ruimt tabel-overhead op waar de database-engine dit ondersteunt. Maak eerst een backup.']]}
        ],
        cdn: [
            {title:'CDN', fields:[['enable_cdn','CDN inschakelen','toggle','Herschrijft asset URLs naar CDN.'],['cdn_cnames','CDN CNAMEs','textarea','Eén domein per regel.'],['cdn_file_types','Bestandstypen','select','Welke assets via CDN.',[['all','Alle bestanden'],['css_js','CSS en JS'],['images','Afbeeldingen']]],['cdn_exclude','CDN uitsluitingen','textarea','Eén patroon per regel.']]},
            {title:'Cloudflare / Edge', fields:[['enable_edge_cache_headers','Edge cache headers','toggle','Stuurt edge cache hints.'],['enable_cloudflare_apo_mode','Cloudflare APO modus','toggle','Alleen met correcte Cloudflare setup.'],['enable_early_hints_links','Early hints links','toggle','Experimenteel.'],['cloudflare_zone_id','Cloudflare zone ID','text','Alleen nodig voor purge/API.'],['cloudflare_api_token','Cloudflare API token','text','Bewaar veilig.']]}
        ],
        heartbeat: [
            {title:'Heartbeat', fields:[['enable_heartbeat_control','Heartbeat beheren','toggle','Vermindert admin-ajax belasting.'],['heartbeat_frontend_behavior','Frontend gedrag','select','Heartbeat op frontend.',[['keep','Ongewijzigd'],['reduce','Verminderen'],['disable','Uitschakelen']]],['heartbeat_editor_behavior','Editor gedrag','select','Heartbeat in editor.',[['keep','Ongewijzigd'],['reduce','Verminderen'],['disable','Uitschakelen']]],['heartbeat_backend_behavior','Backend gedrag','select','Heartbeat in dashboard.',[['keep','Ongewijzigd'],['reduce','Verminderen'],['disable','Uitschakelen']]],['heartbeat_frontend_frequency','Frontend frequentie','number','Seconden.'],['heartbeat_editor_frequency','Editor frequentie','number','Seconden.'],['heartbeat_backend_frequency','Backend frequentie','number','Seconden.']]}
        ],
        diagnostics: [
            {title:'Diagnostiek en logs', fields:[['enable_diagnostics','Diagnostiek registreren','toggle','Slaat beperkte runtime-diagnostiek op voor cache- en optimalisatiecontrole.'],['enable_logs','Logboek inschakelen','toggle','Bewaar technische meldingen voor foutopsporing. Zet uit op productie als je dit niet actief gebruikt.'],['enable_health_checks','Health checks plannen','toggle','Controleert cachemap, drop-in en runtimevoorwaarden.'],['enable_admin_queue_runner','Admin queue runner','toggle','Mag wachtrijtaken vanuit het dashboard proberen te verwerken.']]},
            {title:'Bewaartermijnen', fields:[['log_retention_days','Logs bewaren dagen','number','Aantal dagen dat logs blijven staan.'],['diagnostics_retention_days','Diagnostiek bewaren dagen','number','Aantal dagen dat diagnostiekdata blijft staan.'],['job_retention_days','Jobs bewaren dagen','number','Aantal dagen dat afgeronde jobs blijven staan.']]}
        ],

    };

    function settingsIntro(kind) {
        var data = {
            optimization: {title: __('Bestandsoptimalisatie overzicht','ultracache-pro'), text: __('Begin met veilige optimalisaties. Zet risicovolle CSS- en JavaScript-opties pas aan nadat je de frontend, formulieren en checkout hebt getest.','ultracache-pro'), steps: [__('1. Kies eerst een preset','ultracache-pro'), __('2. Controleer HTML en CSS','ultracache-pro'), __('3. Test JavaScript apart','ultracache-pro')]},
            media: {title: __('Media optimalisatie overzicht','ultracache-pro'), text: __('Gebruik veilige media-optimalisaties eerst: lazy load voor offscreen media, vaste afbeeldingsafmetingen en font-display swap. Gebruik preload alleen voor kritieke fonts of hero-afbeeldingen.','ultracache-pro'), steps: [__('Aanbevolen basis','ultracache-pro'), __('Afbeeldingen','ultracache-pro'), __('Fonts','ultracache-pro'), __('Uitsluitingen en connecties','ultracache-pro')]},
            preload: {title: __('Preload overzicht','ultracache-pro'), text: __('Preload maakt cachebestanden klaar voordat bezoekers pagina’s openen. Gebruik de queue en sitemap als veilige basis; stuur snelheid met batchgrootte en pauze.','ultracache-pro'), steps: [__('Cache vooraf opbouwen','ultracache-pro'), __('Link preload','ultracache-pro'), __('Uitsluitingen','ultracache-pro'), __('Serverbelasting','ultracache-pro')]},
            advanced: {title: __('Geavanceerde regels overzicht','ultracache-pro'), text: __('Gebruik deze pagina alleen voor uitzonderingen: pagina’s die nooit gecachet mogen worden, cookies die gepersonaliseerde content tonen, query parameters en developer-cache. Laat velden leeg wanneer je geen specifieke uitzondering nodig hebt.','ultracache-pro'), steps: [__('Cache levensduur','ultracache-pro'), __('Uitsluitingen','ultracache-pro'), __('Query strings','ultracache-pro'), __('Developer cache','ultracache-pro')]},
            database: {title: __('Database onderhoud','ultracache-pro'), text: __('Ruim alleen gegevens op die je bewust hebt geselecteerd. Verlopen transients zijn meestal veilig; revisies, prullenbak, alle transients en tabeloptimalisatie vragen meer voorzichtigheid.','ultracache-pro'), steps: [__('Veilig eerst','ultracache-pro'), __('Berichten','ultracache-pro'), __('Reacties','ultracache-pro'), __('Transients','ultracache-pro'), __('Tabellen','ultracache-pro')]},
            cdn: {title: __('CDN overzicht','ultracache-pro'), text: __('Configureer CDN- en edge-opties alleen wanneer je CNAMEs en Cloudflare-instellingen bekend zijn.','ultracache-pro'), steps: [__('CDN CNAMEs','ultracache-pro'), __('Uitsluitingen','ultracache-pro'), __('Cloudflare','ultracache-pro')]},
            heartbeat: {title: __('Heartbeat overzicht','ultracache-pro'), text: __('Verminder WordPress Heartbeat voorzichtig. Uitschakelen kan invloed hebben op editors, autosave of plugins.','ultracache-pro'), steps: [__('Frontend','ultracache-pro'), __('Editor','ultracache-pro'), __('Backend','ultracache-pro')]},
        };
        return data[kind] || data.optimization;
    }

    function SettingsIntro(props) {
        return null;
    }

    function countLines(value) {
        if (!value) return 0;
        return String(value).split(/\r?\n/).map(function(line){ return line.trim(); }).filter(Boolean).length;
    }


    function DatabaseOverviewPanel(props) {
        var settings = props.settings || {};
        var autoOn = parseInt(settings.enable_db_cleanup || 0, 10);
        var freq = settings.db_cleanup_frequency || 'off';
        var postItems = ['db_cleanup_post_revisions','db_cleanup_auto_drafts','db_cleanup_trashed_posts'].filter(function(key){ return parseInt(settings[key] || 0, 10); }).length;
        var commentItems = ['db_cleanup_spam_comments','db_cleanup_trashed_comments'].filter(function(key){ return parseInt(settings[key] || 0, 10); }).length;
        var transientItems = ['db_cleanup_expired_transients','db_cleanup_all_transients'].filter(function(key){ return parseInt(settings[key] || 0, 10); }).length;
        var riskyItems = ['db_cleanup_all_transients','db_cleanup_optimize_tables','db_cleanup_trashed_posts','db_cleanup_trashed_comments'].filter(function(key){ return parseInt(settings[key] || 0, 10); }).length;
        return el('div', {className:'ucp-database-overview-grid'},
            el(Card, {className:'ucp-card ucp-database-summary-card'},
                el(CardHeader, {}, el('h2', {}, __('Veilige basis','ultracache-pro'))),
                el(CardBody, {},
                    el('p', {}, __('Start met verlopen transients. Zet revisies, prullenbak en alle transients alleen aan wanneer je zeker weet dat je die gegevens niet meer nodig hebt.','ultracache-pro')),
                    el('div', {className:'ucp-preload-checks'},
                        el('span', {}, boolBadge(parseInt(settings.db_cleanup_expired_transients || 0, 10), __('Verlopen transients aan','ultracache-pro'), __('Verlopen transients uit','ultracache-pro'))),
                        el('span', {}, boolBadge(autoOn && freq !== 'off', __('Schema actief','ultracache-pro'), __('Geen schema','ultracache-pro')))
                    )
                )
            ),
            el(Card, {className:'ucp-card ucp-database-summary-card'},
                el(CardHeader, {}, el('h2', {}, __('Selectie','ultracache-pro'))),
                el(CardBody, {},
                    el('p', {}, __('UltraCache ruimt alleen de onderdelen op die hieronder zijn ingeschakeld. Controleer de selectie voordat je opschoont.','ultracache-pro')),
                    el('div', {className:'ucp-preload-stats'},
                        el('div', {}, el('strong', {}, postItems), el('span', {}, __('Berichten','ultracache-pro'))),
                        el('div', {}, el('strong', {}, commentItems), el('span', {}, __('Reacties','ultracache-pro'))),
                        el('div', {}, el('strong', {}, transientItems), el('span', {}, __('Transients','ultracache-pro')))
                    )
                )
            ),
            el(Card, {className:'ucp-card ucp-database-summary-card ucp-database-danger-card'},
                el(CardHeader, {}, el('h2', {}, __('Opschonen','ultracache-pro'))),
                el(CardBody, {},
                    el('p', {}, __('Maak eerst een database-backup. Database-acties kunnen niet ongedaan worden gemaakt.','ultracache-pro')),
                    riskyItems ? el('p', {className:'ucp-db-risk-note'}, riskyItems + ' ' + __('risicovolle onderdelen geselecteerd.','ultracache-pro')) : el('p', {className:'ucp-muted'}, __('Geen extra risicovolle onderdelen geselecteerd.','ultracache-pro')),
                    el(ActionButton, {compact:true, action:'database-cleanup', label:__('Geselecteerde onderdelen opschonen','ultracache-pro'), variant:'primary', destructive:true, confirm:true, addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                )
            )
        );
    }

    function AdvancedRulesOverviewPanel(props) {
        var settings = props.settings || {};
        var excludeUrlCount = countLines(settings.exclude_urls);
        var cookieCount = countLines(settings.exclude_cookies);
        var agentCount = countLines(settings.exclude_user_agents);
        var queryCount = countLines(settings.cache_query_string_inclusions);
        return el('div', {className:'ucp-advanced-overview-grid'},
            el(Card, {className:'ucp-card ucp-advanced-summary-card'},
                el(CardHeader, {}, el('h2', {}, __('Veilige basis','ultracache-pro'))),
                el(CardBody, {},
                    el('p', {}, __('Voor de meeste sites hoef je alleen cachelevensduur en WooCommerce-uitsluitingen te controleren. Laat user-agents en query strings leeg tenzij er een duidelijke reden is.','ultracache-pro')),
                    el('div', {className:'ucp-preload-limits'},
                        el('span', {}, __('Cache','ultracache-pro') + ': ', el('strong', {}, (settings.cache_lifespan || 10) + ' uur')),
                        el('span', {}, __('Stale cache','ultracache-pro') + ': ', el('strong', {}, parseInt(settings.enable_stale_cache || 0, 10) ? __('Aan','ultracache-pro') : __('Uit','ultracache-pro')))
                    )
                )
            ),
            el(Card, {className:'ucp-card ucp-advanced-summary-card'},
                el(CardHeader, {}, el('h2', {}, __('Uitsluitingen','ultracache-pro'))),
                el(CardBody, {},
                    el('p', {}, __('Gebruik uitsluitingen voor dynamische of persoonlijke pagina’s, zoals cart, checkout, account, zoek- en filterpagina’s.','ultracache-pro')),
                    el('div', {className:'ucp-preload-stats'},
                        el('div', {}, el('strong', {}, excludeUrlCount), el('span', {}, __('URL-regels','ultracache-pro'))),
                        el('div', {}, el('strong', {}, cookieCount), el('span', {}, __('Cookies','ultracache-pro'))),
                        el('div', {}, el('strong', {}, agentCount), el('span', {}, __('User-agents','ultracache-pro')))
                    )
                )
            ),
            el(Card, {className:'ucp-card ucp-advanced-summary-card'},
                el(CardHeader, {}, el('h2', {}, __('Query strings','ultracache-pro'))),
                el(CardBody, {},
                    el('p', {}, __('Standaard worden URL’s met query strings voorzichtig behandeld. Cache alleen bekende parameters die geen persoonlijke content veranderen.','ultracache-pro')),
                    el('div', {className:'ucp-preload-limits'},
                        el('span', {}, __('Status','ultracache-pro') + ': ', el('strong', {}, parseInt(settings.cache_query_strings || 0, 10) ? __('Aan','ultracache-pro') : __('Uit','ultracache-pro'))),
                        el('span', {}, __('Parameters','ultracache-pro') + ': ', el('strong', {}, queryCount))
                    )
                )
            )
        );
    }

    function PreloadOverviewPanel(props) {
        var settings = props.settings || {};
        var status = props.status || {};
        var queue = status.queue || {};
        var runner = queue.runner || {};
        var batch = settings.preload_batch_size || 15;
        var delay = settings.preload_delay_ms || 500;
        var maxUrls = settings.preload_max_urls || 250;
        return el('div', {className:'ucp-preload-overview-grid'},
            el(Card, {className:'ucp-card ucp-preload-summary-card'},
                el(CardHeader, {}, el('h2', {}, __('Aanbevolen basis','ultracache-pro'))),
                el(CardBody, {},
                    el('p', {}, __('Voor de meeste sites is dit de veiligste preload-opzet: queue aan, sitemaps aan, homepage aan en een rustige batchgrootte.','ultracache-pro')),
                    el('div', {className:'ucp-preload-checks'},
                        el('span', {}, boolBadge(parseInt(settings.enable_preload || 0, 10), __('Preload aan','ultracache-pro'), __('Preload uit','ultracache-pro'))),
                        el('span', {}, boolBadge(parseInt(settings.enable_preload_queue || 0, 10), __('Queue aan','ultracache-pro'), __('Queue uit','ultracache-pro'))),
                        el('span', {}, boolBadge(parseInt(settings.preload_sitemaps || 0, 10), __('Sitemap aan','ultracache-pro'), __('Sitemap uit','ultracache-pro'))),
                        el('span', {}, boolBadge(parseInt(settings.preload_homepage || 0, 10), __('Homepage aan','ultracache-pro'), __('Homepage uit','ultracache-pro')))
                    )
                )
            ),
            el(Card, {className:'ucp-card ucp-preload-summary-card'},
                el(CardHeader, {}, el('h2', {}, __('Wachtrij','ultracache-pro'))),
                el(CardBody, {},
                    el('div', {className:'ucp-preload-stats'},
                        el('div', {}, el('strong', {}, queue.pending || 0), el('span', {}, __('Open','ultracache-pro'))),
                        el('div', {}, el('strong', {}, runner.due || 0), el('span', {}, __('Nu klaar','ultracache-pro'))),
                        el('div', {}, el('strong', {}, queue.retrying || 0), el('span', {}, __('Opnieuw','ultracache-pro')))
                    ),
                    el('div', {className:'ucp-action-inline'},
                        el(ActionButton, {compact:true, action:'preload', label:__('Preload starten','ultracache-pro'), addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh}),
                        el(ActionButton, {compact:true, action:'run-due-jobs', label:__('Verwerk nu','ultracache-pro'), variant:'primary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:props.onRefresh})
                    )
                )
            ),
            el(Card, {className:'ucp-card ucp-preload-summary-card'},
                el(CardHeader, {}, el('h2', {}, __('Snelheid en belasting','ultracache-pro'))),
                el(CardBody, {},
                    el('p', {}, __('Gebruik lagere batches op shared hosting. Verhoog de pauze als taken opnieuw ingepland worden of hosting traag reageert.','ultracache-pro')),
                    el('div', {className:'ucp-preload-limits'},
                        el('span', {}, __('Batch','ultracache-pro') + ': ', el('strong', {}, batch)),
                        el('span', {}, __('Pauze','ultracache-pro') + ': ', el('strong', {}, delay + ' ms')),
                        el('span', {}, __('Max','ultracache-pro') + ': ', el('strong', {}, maxUrls))
                    )
                )
            )
        );
    }

    function SettingsPage(props) {
        var rawGroups = settingsGroups[props.kind] || settingsGroups.optimization;
        var groups = rawGroups.map(function(group, index){
            return Object.assign({__id:(group.key || slugify(group.title) || ('group-' + index)) + '-' + index}, group);
        });
        var ids = groups.map(function(group){ return group.__id; });
        var layoutKey = 'ucp-layout-' + props.kind;
        var colsKey = layoutKey + '-cols';
        var defaultColumns = props.kind === 'database' ? 1 : 2;
        var orderState = useState(function(){ return normalizeOrder(storageGet(layoutKey + '-order', ids), ids); }), order = orderState[0], setOrder = orderState[1];
        var colsState = useState(function(){ return parseInt(storageGet(colsKey, defaultColumns), 10) || defaultColumns; }), columns = colsState[0], setColumns = colsState[1];
        var dragState = useState(null), draggingId = dragState[0], setDraggingId = dragState[1];
        useEffect(function(){
            setOrder(normalizeOrder(storageGet(layoutKey + '-order', ids), ids));
            setColumns(parseInt(storageGet(colsKey, defaultColumns), 10) || defaultColumns);
        }, [props.kind]);
        function updateOrder(nextOrder) {
            var normalized = normalizeOrder(nextOrder, ids);
            setOrder(normalized);
            storageSet(layoutKey + '-order', normalized);
        }
        function updateColumns(nextColumns) {
            setColumns(nextColumns);
            storageSet(colsKey, nextColumns);
        }
        var groupsById = {};
        groups.forEach(function(group){ groupsById[group.__id] = group; });
        var orderedGroups = normalizeOrder(order, ids).map(function(id){ return groupsById[id]; }).filter(Boolean);
        return el('div', {className:'ucp-settings-page ucp-settings-page--' + props.kind},
            el(LayoutToolbar, {
                title:__('Kaartenindeling','ultracache-pro'),
                help:__('Zet groepen in 1, 2, 3 of 4 kolommen en sleep kaarten om de volgorde aan te passen. In 1x1 blijven compacte velden op een nette rij; brede velden, lange hulptekst en risicovolle opties stapelen automatisch.','ultracache-pro'),
                columns:columns,
                onChange:updateColumns
            }),
            el('div', {className:'ucp-layout-grid ucp-layout-grid--settings', style:{'--ucp-grid-columns': columns}}, orderedGroups.map(function(group){
                return el(Card, {
                    key:group.__id,
                    className:'ucp-card ucp-layout-card ucp-settings-card' + (draggingId === group.__id ? ' is-dragging' : ''),
                    draggable:true,
                    onDragStart:function(){ setDraggingId(group.__id); },
                    onDragEnd:function(){ setDraggingId(null); },
                    onDragOver:function(event){ event.preventDefault(); },
                    onDrop:function(event){ event.preventDefault(); if (draggingId && draggingId !== group.__id) { updateOrder(moveIdBefore(order, draggingId, group.__id)); } setDraggingId(null); }
                },
                    el(CardHeader, {},
                        el('div', {className:'ucp-layout-card__header'},
                            el('div', {className:'ucp-layout-card__title-wrap'},
                                el('h2', {}, group.title)
                            ),
                            el('button', {type:'button', className:'ucp-layout-card__drag', title:__('Verplaats kaart met pijltjestoetsen of sleep met de muis','ultracache-pro'), 'aria-label':__('Verplaats kaart. Gebruik pijltoetsen, Home of End.','ultracache-pro'), onKeyDown:function(event){ keyboardReorder(event, group.__id, order, updateOrder); }}, '↕')
                        )
                    ),
                    el(CardBody, {}, group.fields.map(function(field){
                        return el(SettingField,{key:field[0], field:field, kind:props.kind, settings:props.settings, status:props.status || {}, setSettings:props.setSettings, addNotice:props.addNotice, setStatus:props.setStatus});
                    }))
                );
            }))
        );
    }

    function SettingField(props) {
        var key = props.field[0], label = props.field[1], type = props.field[2], help = props.field[3] || '', options = props.field[4] || [];
        var savingState = useState(false), saving = savingState[0], setSaving = savingState[1];
        var dirtyState = useState(false), dirty = dirtyState[0], setDirty = dirtyState[1];
        var currentValue = props.settings && Object.prototype.hasOwnProperty.call(props.settings, key) ? props.settings[key] : '';
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
            var next = Object.assign({}, previousSettings); next[key] = newValue;
            props.setSettings(next); setSaving(true); setDirty(false);
            var payload = {}; payload[key] = newValue;
            if (key === 'css_delivery_mode') {
                payload.enable_used_css = newValue === 'remove_unused' ? 1 : 0;
                payload.enable_used_css_delivery = newValue === 'remove_unused' ? 1 : 0;
                payload.enable_critical_css = newValue === 'async' ? 1 : 0;
                payload.enable_css_queue = newValue === 'none' ? 0 : 1;
                if (newValue !== 'none') payload.enable_css_combine = 0;
            }
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
        var control;
        if (type === 'toggle') {
            control = el(ToggleControl,{label:label, help:help, checked:!!parseInt(currentValue || 0,10), disabled:saving || isLocked, onChange:function(v){commit(v ? 1 : 0);}});
        } else if (type === 'textarea') {
            control = el(Fragment, {}, el(TextareaControl,{label:label, help:help, value:draft || '', disabled:saving, onChange:function(v){setDraft(v); setDirty(true);}}), dirty ? el(Button,{variant:'secondary', isBusy:saving, disabled:saving, onClick:function(){commit(draft || '');}},__('Opslaan','ultracache-pro')) : null);
        } else if (type === 'number') {
            control = el(Fragment, {}, el(NumberControl,{label:label, help:help, value:draft || 0, disabled:saving, onChange:function(v){setDraft(v); setDirty(true);}}), dirty ? el(Button,{variant:'secondary', isBusy:saving, disabled:saving, onClick:function(){commit(parseInt(draft || 0,10));}},__('Opslaan','ultracache-pro')) : null);
        } else if (type === 'select') {
            control = el(SelectControl,{label:label, help:help, value:currentValue || (options[0] ? options[0][0] : ''), options:options.map(function(o){return {value:o[0], label:o[1]};}), disabled:saving, onChange:function(v){commit(v);}});
        } else if (type === 'css_delivery') {
            control = el(CssDeliveryControl,{label:label, help:help, value:currentValue || 'none', options:options, saving:saving, onChange:commit});
        } else {
            control = el(Fragment, {}, el(TextControl,{label:label, help:help, value:draft || '', disabled:saving, onChange:function(v){setDraft(v); setDirty(true);}}), dirty ? el(Button,{variant:'secondary', isBusy:saving, disabled:saving, onClick:function(){commit(draft || '');}},__('Opslaan','ultracache-pro')) : null);
        }
        var layoutClass = isCompactSettingField(props.field, props.kind) ? ' is-rowable' : ' is-stacked';
        return el('div',{className:'ucp-setting-field ucp-setting-field--' + type + layoutClass, 'data-ucp-field-key':key}, control, warning, saving ? el('span',{className:'ucp-saving-text'},__('Opslaan…','ultracache-pro')) : null);
    }

    function CssDeliveryControl(props) {
        var options = props.options || [];
        var value = props.value || 'none';
        var details = {
            none: { tone: 'info', title: __('Veiligste keuze','ultracache-pro'), text: __('Laat CSS normaal laden. Gebruik dit voor maximale compatibiliteit of als de layout gevoelig is.','ultracache-pro') },
            remove_unused: { tone: 'good', title: __('Aanbevolen voor prestatie','ultracache-pro'), text: __('Genereert Used CSS per pagina en verwijdert ongebruikte stylesheets. Gebruik samen met preload en controleer de layout visueel.','ultracache-pro') },
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

    function riskText(key){
        if(key==='enable_delay_js') return 'Kan formulieren, sliders of checkout beïnvloeden. Test dit eerst op staging.';
        if(key==='enable_css_combine' || key==='enable_js_combine') return 'Alleen gebruiken op eenvoudige HTTP/1-sites zonder builder/shop/formulier-conflicten.';
        if(key==='enable_rest_cache') return 'Controleer API-koppelingen en formulieren na inschakelen.';
        if(key==='db_cleanup_all_transients' || key==='db_cleanup_optimize_tables') return 'Maak eerst een database-backup. Deze actie kan niet ongedaan worden gemaakt.';
        if(key==='enable_cloudflare_apo_mode') return 'Gebruik alleen als Cloudflare correct is geconfigureerd.';
        return '';
    }

    function DataList(props) {
        var rows = props.rows || [];
        if (!rows.length) {
            return el('p', {className:'ucp-muted'}, props.empty || __('Niets gevonden.','ultracache-pro'));
        }
        return el('div', {className:'ucp-data-list'}, rows.map(function(row, index){
            return el('div', {className:'ucp-data-row', key:row.id || row.job_uuid || index},
                el('strong', {}, props.primary(row)),
                el('span', {}, props.secondary(row))
            );
        }));
    }


    function uniqueResourceList(items, limit) {
        var seen = {}, out = [];
        (items || []).forEach(function(item){
            var url = typeof item === 'string' ? item : (item && item.url ? item.url : '');
            var label = item && item.label ? item.label : '';
            var key = url || label;
            if (!key || seen[key]) return;
            seen[key] = true;
            out.push({url:url, label:label});
        });
        return out.slice(0, limit || 50);
    }
    function resourceDomain(url) {
        try { return (new URL(url, window.location.href)).hostname; } catch(e) { return ''; }
    }
    function scoreVisualCandidate(item, viewportHeight) {
        var score = Math.max(0, item.width || 0) * Math.max(0, item.height || 0) / 1000;
        var top = Number(item.top || 0);
        if (top >= 0 && top < viewportHeight) score += 800;
        if (top >= 0 && top < Math.max(320, viewportHeight * 0.65)) score += 450;
        var hay = String((item.className || '') + ' ' + (item.url || '')).toLowerCase();
        ['hero','banner','cover','above-fold','elementor-top-section','swiper-slide-active','lcp'].forEach(function(n){ if (hay.indexOf(n) !== -1) score += 650; });
        ['logo','icon','avatar','badge','placeholder','thumbnail','thumb','spinner','loader'].forEach(function(n){ if (hay.indexOf(n) !== -1) score -= 1400; });
        if (/\.(svg|gif)(\?|$)/i.test(hay)) score -= 1400;
        return Math.round(score);
    }
    function collectBrowserRenderedMetrics(doc, win, url) {
        var viewport = {width:win.innerWidth || 0, height:win.innerHeight || 0, dpr:win.devicePixelRatio || 1, type:(win.innerWidth || 0) <= 782 ? 'mobile' : 'desktop'};
        var originHost = resourceDomain(url || win.location.href);
        var images = [];
        Array.prototype.slice.call(doc.images || []).forEach(function(img){
            var rect = img.getBoundingClientRect ? img.getBoundingClientRect() : {top:0,width:0,height:0};
            var src = img.currentSrc || img.getAttribute('src') || '';
            if (!src) return;
            var item = {url:src, tag:'img', className:img.className || '', width:Math.round(rect.width || img.naturalWidth || 0), height:Math.round(rect.height || img.naturalHeight || 0), top:Math.round(rect.top || 0), background:0};
            item.score = scoreVisualCandidate(item, viewport.height);
            images.push(item);
        });
        var backgrounds = [];
        Array.prototype.slice.call(doc.querySelectorAll('body *')).slice(0, 700).forEach(function(node){
            var rect = node.getBoundingClientRect ? node.getBoundingClientRect() : null;
            if (!rect || rect.width < 80 || rect.height < 80 || rect.top > viewport.height * 1.4) return;
            var bg = '';
            try { bg = win.getComputedStyle(node).backgroundImage || ''; } catch(e) {}
            if (!bg || bg === 'none' || bg.indexOf('url(') === -1) return;
            var matches = bg.match(/url\(["']?([^"')]+)["']?\)/g) || [];
            matches.forEach(function(raw){
                var u = raw.replace(/^url\(["']?/, '').replace(/["']?\)$/, '');
                if (!u || /^data:/i.test(u)) return;
                try { u = (new URL(u, win.location.href)).href; } catch(e) {}
                var item = {url:u, tag:(node.tagName || '').toLowerCase(), className:node.className || '', width:Math.round(rect.width || 0), height:Math.round(rect.height || 0), top:Math.round(rect.top || 0), background:1};
                item.score = scoreVisualCandidate(item, viewport.height) + 250;
                backgrounds.push(item);
            });
        });
        var allCandidates = images.concat(backgrounds).filter(function(item){ return item.url && item.score > 0; }).sort(function(a,b){ return b.score - a.score; });
        var resourceTiming = [];
        try {
            resourceTiming = (win.performance && win.performance.getEntriesByType) ? win.performance.getEntriesByType('resource').map(function(entry){
                return {url:entry.name || '', initiator:entry.initiatorType || '', duration:Math.round(entry.duration || 0), transferSize:entry.transferSize || 0, renderBlocking: !!entry.renderBlockingStatus && entry.renderBlockingStatus === 'blocking'};
            }) : [];
        } catch(e) {}
        function timingFor(urlValue){
            if (!urlValue) return null;
            for (var i=0;i<resourceTiming.length;i++) {
                if (resourceTiming[i].url && resourceTiming[i].url === urlValue) return resourceTiming[i];
            }
            return null;
        }
        var stylesheets = uniqueResourceList(Array.prototype.slice.call(doc.querySelectorAll('link[rel~="stylesheet"],link[rel="preload"][as="style"]')).map(function(link){
            var href = link.href || '';
            var timing = timingFor(href) || {};
            var isBlocking = link.rel === 'stylesheet' && !link.media && !link.disabled && !link.getAttribute('data-ucp-async');
            return {url:href, label:link.getAttribute('id') || link.getAttribute('href') || '', kind:'stylesheet', blocking:isBlocking || !!timing.renderBlocking, duration:timing.duration || 0};
        }), 100);
        var scripts = uniqueResourceList(Array.prototype.slice.call(doc.scripts || []).map(function(script){
            var src = script.src || '';
            var timing = timingFor(src) || {};
            var label = script.getAttribute('id') || script.getAttribute('type') || (src ? src.split('/').pop() : 'inline');
            var asyncish = script.async || script.defer || script.type === 'module' || /ucpdelayed/i.test(script.type || '');
            return {url:src, label:label, kind:'script', blocking:!!src && !asyncish, duration:timing.duration || 0};
        }), 140);
        var renderBlockingStylesheets = stylesheets.filter(function(item){ return item.blocking && item.url && resourceDomain(item.url) === originHost; });
        var earlyScripts = scripts.filter(function(item){ return item.blocking && item.url; });
        var delayNeedles = /(gtm|googletagmanager|gtag|analytics|facebook|fbevents|fbq|cookiebot|complianz|cookieyes|joinchat|whatsapp|elementor|frontend-modules|elements-handlers|jeg-|sticky|swiper|slick|paypal|stripe|mollie|klarna)/i;
        var delayCandidates = scripts.filter(function(item){
            if (!item.url) return false;
            var hay = String(item.url + ' ' + item.label).toLowerCase();
            if (/(jquery|recaptcha|wp-i18n|wp-hooks|wp-element|wp-api-fetch)/i.test(hay)) return false;
            return item.blocking || delayNeedles.test(hay) || resourceDomain(item.url) !== originHost;
        });
        var cssCandidates = renderBlockingStylesheets.concat(stylesheets.filter(function(item){ return item.url && resourceDomain(item.url) === originHost && /(elementor|theme|style|frontend|woocommerce|jeg|global|post-)/i.test(item.url + ' ' + item.label); }));
        var third = [];
        scripts.concat(stylesheets).concat(images.slice(0, 20)).forEach(function(item){
            var host = resourceDomain(item.url || '');
            if (host && originHost && host !== originHost) third.push({url:item.url, label:host, kind:item.kind || ''});
        });
        var recommendations = {
            lcp: allCandidates[0] && allCandidates[0].url ? allCandidates[0].url : '',
            render_blocking_stylesheets: String(renderBlockingStylesheets.length),
            delay_candidates: String(delayCandidates.length),
            third_party: String(uniqueResourceList(third, 50).length)
        };
        return {url:url || win.location.href, viewport:viewport, lcp:allCandidates[0] || {}, images:images.sort(function(a,b){return b.score-a.score;}).slice(0,25), backgrounds:backgrounds.sort(function(a,b){return b.score-a.score;}).slice(0,25), stylesheets:stylesheets, scripts:scripts, thirdParty:uniqueResourceList(third, 80), renderBlockingStylesheets:uniqueResourceList(renderBlockingStylesheets, 80), earlyScripts:uniqueResourceList(earlyScripts, 120), delayCandidates:uniqueResourceList(delayCandidates, 120), cssCandidates:uniqueResourceList(cssCandidates, 80), resourceTiming:resourceTiming.slice(0,160), recommendations:recommendations};
    }
    function runBrowserRenderedScan(targetUrl) {
        targetUrl = targetUrl || config.homeUrl || window.location.origin + '/';
        return new Promise(function(resolve, reject){
            var frame = document.createElement('iframe');
            var done = false;
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'position:absolute;left:-99999px;top:0;width:390px;height:844px;border:0;opacity:0;pointer-events:none;';
            var timeout = window.setTimeout(function(){
                if (done) return;
                done = true;
                try { frame.remove(); } catch(e) {}
                reject(new Error('Browser scan timeout. Controleer of de homepage in een iframe mag laden.'));
            }, 15000);
            frame.onload = function(){
                window.setTimeout(function(){
                    if (done) return;
                    try {
                        var doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
                        if (!doc) throw new Error('Geen toegang tot iframe-document.');
                        var metrics = collectBrowserRenderedMetrics(doc, frame.contentWindow, targetUrl);
                        done = true;
                        window.clearTimeout(timeout);
                        try { frame.remove(); } catch(e) {}
                        resolve(metrics);
                    } catch(e) {
                        done = true;
                        window.clearTimeout(timeout);
                        try { frame.remove(); } catch(removeErr) {}
                        reject(e);
                    }
                }, 1800);
            };
            frame.src = targetUrl + (targetUrl.indexOf('?') === -1 ? '?' : '&') + 'ucp-browser-scan=' + Date.now();
            document.body.appendChild(frame);
        });
    }

    function DiagnosticsPage(props) {
        var status = props.status || {}, health = status.health || {}, queue = status.queue || {}, runner = queue.runner || {};
        var jobsState = useState(null), jobs = jobsState[0], setJobs = jobsState[1];
        var logsState = useState(null), logs = logsState[0], setLogs = logsState[1];
        var reqState = useState(null), requests = reqState[0], setRequests = reqState[1];
        var browserState = useState(null), browserScan = browserState[0], setBrowserScan = browserState[1];
        var browserBusyState = useState(false), browserBusy = browserBusyState[0], setBrowserBusy = browserBusyState[1];
        var loadState = useState(false), loading = loadState[0], setLoading = loadState[1];
        function loadDiagnostics(){
            setLoading(true);
            Promise.all([getJobs(), getLogs(), getRequests(), getBrowserScan()]).then(function(res){
                setJobs(res[0]); setLogs(res[1]); setRequests(res[2]); setBrowserScan(res[3] && res[3].scan ? res[3].scan : null);
            }).catch(function(err){
                props.addNotice({status:'error', message:cleanErrorMessage(err, __('Diagnostiek kon niet geladen worden.','ultracache-pro'))});
            }).finally(function(){ setLoading(false); });
        }
        function runPageSpeedBrowserScan(){
            setBrowserBusy(true);
            runBrowserRenderedScan(config.homeUrl).then(function(metrics){
                return saveBrowserScan(metrics);
            }).then(function(res){
                setBrowserScan(res && res.scan ? res.scan : null);
                props.addNotice({status:'success', message:__('PageSpeed browser scan opgeslagen. Gebruik daarna cache/Used CSS opnieuw genereren zodat deze hints worden toegepast.','ultracache-pro')});
                if (props.onRefresh) props.onRefresh();
            }).catch(function(err){
                props.addNotice({status:'error', message:cleanErrorMessage(err, __('PageSpeed browser scan mislukt.','ultracache-pro'))});
            }).finally(function(){ setBrowserBusy(false); });
        }
        useEffect(function(){ loadDiagnostics(); }, []);
        return el('div', {className:'ucp-page'},
            el(Card, {className:'ucp-card'}, el(CardHeader, {}, el('h2', {}, __('Diagnostiek','ultracache-pro'))), el(CardBody, {},
                el(StatusRow,{label:'Open taken', value:queue.pending || 0}),
                el(StatusRow,{label:'Bezig', value:queue.running || 0}),
                el(StatusRow,{label:'Opnieuw proberen', value:queue.retrying || 0}),
                el(StatusRow,{label:'Mislukt', value:queue.failed || 0}),
                el(StatusRow,{label:'Nu uitvoerbaar', value:runner.due || 0}),
                el(StatusRow,{label:'WP-Cron', value:runner.cronDisabled ? el(StatusBadge,{state:'warning'},'Uitgeschakeld') : el(StatusBadge,{state:'good'},'Beschikbaar')}),
                el(StatusRow,{label:'Volgende runner', value:runner.nextCron || '—'}),
                el(StatusRow,{label:'Health status', value:health && typeof health === 'object' ? infoBadge('Beschikbaar') : infoBadge('Geen recente data')}),
                queue.failed > 0 ? el('div', {className:'ucp-callout ucp-callout--warning ucp-callout--compact'},
                    el('strong', {}, __('Mislukte taken gevonden','ultracache-pro')),
                    el('p', {}, __('Verwerk taken nu pakt alleen open of opnieuw geplande taken. Mislukte taken moet je eerst opnieuw inplannen.','ultracache-pro'))
                ) : null,
                el('div', {className:'ucp-action-inline ucp-action-inline--cards'},
                    el('div', {className:'ucp-action-row ucp-action-row--static'},
                        el('div', {className:'ucp-action-copy'},
                            el('strong', {}, __('Diagnostiek verversen','ultracache-pro')),
                            el('p', {}, __('Laadt taken, logs en diagnostiekrequests opnieuw in.','ultracache-pro'))
                        ),
                        el('div', {className:'ucp-action-button'},
                            el(Button, {variant:'secondary', isBusy:loading, disabled:loading, onClick:loadDiagnostics}, __('Verversen','ultracache-pro'))
                        )
                    ),
                    el(ActionButton, {action:'run-due-jobs', label:__('Verwerk taken nu','ultracache-pro'), help:__('Verwerkt direct de eerstvolgende wachtrijtaken.','ultracache-pro'), variant:'primary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:function(){loadDiagnostics(); if (props.onRefresh) props.onRefresh();}}),
                    queue.failed > 0 ? el(ActionButton, {action:'retry-failed-jobs', label:__('Mislukte taken opnieuw proberen','ultracache-pro'), help:__('Zet mislukte taken terug in de wachtrij en verwerkt direct de eerste batch.','ultracache-pro'), confirm:true, addNotice:props.addNotice, setStatus:props.setStatus, onComplete:function(){loadDiagnostics(); if (props.onRefresh) props.onRefresh();}}) : null,
                    el(ActionButton, {action:'health-check', label:__('Health check uitvoeren','ultracache-pro'), help:__('Controleert de belangrijkste runtime voorwaarden.','ultracache-pro'), addNotice:props.addNotice, setStatus:props.setStatus, onComplete:loadDiagnostics}),
                    el(ActionButton, {action:'runtime-cache-test', label:__('Cache runtime test uitvoeren','ultracache-pro'), help:__('Test WP_CACHE, advanced-cache.php, drop-in config, homepage HIT-signalen en WooCommerce BYPASS-pagina’s.','ultracache-pro'), variant:'primary', addNotice:props.addNotice, setStatus:props.setStatus, onComplete:function(){loadDiagnostics(); if (props.onRefresh) props.onRefresh();}}),
                    el('div', {className:'ucp-action-row ucp-action-row--static'},
                        el('div', {className:'ucp-action-copy'},
                            el('strong', {}, __('PageSpeed browser scan uitvoeren','ultracache-pro')),
                            el('p', {}, __('Rendert de homepage in je browser en verzamelt echte viewport-hints voor LCP-afbeelding, background images, CSS, scripts en third-party resources.','ultracache-pro'))
                        ),
                        el('div', {className:'ucp-action-button'},
                            el(Button, {variant:'primary', isBusy:browserBusy, disabled:browserBusy, onClick:runPageSpeedBrowserScan}, __('Browser scan uitvoeren','ultracache-pro'))
                        )
                    ),
                    browserScan && browserScan.lcp && browserScan.lcp.url ? el('div', {className:'ucp-callout ucp-callout--info ucp-callout--compact'},
                        el('strong', {}, __('Laatste PageSpeed browser scan','ultracache-pro')),
                        el('p', {}, (browserScan.created_at || '') + ' · LCP: ' + browserScan.lcp.url),
                        el('p', {className:'ucp-muted'}, __('CSS/JS hints:', 'ultracache-pro') + ' ' +
                            __('render-blocking CSS', 'ultracache-pro') + ' ' + ((browserScan.render_blocking_stylesheets || []).length || 0) + ' · ' +
                            __('delay-kandidaten', 'ultracache-pro') + ' ' + ((browserScan.delay_candidates || []).length || 0) + ' · ' +
                            __('third-party', 'ultracache-pro') + ' ' + ((browserScan.third_party || []).length || 0)
                        )
                    ) : null,
                    el(ActionButton, {action:'repair-cache-files', label:__('WP_CACHE en drop-in herstellen','ultracache-pro'), help:__('Probeert WP_CACHE, advanced-cache.php en de UltraCache drop-in configuratie veilig te herstellen met back-up.','ultracache-pro'), confirm:true, addNotice:props.addNotice, setStatus:props.setStatus, onComplete:function(){loadDiagnostics(); if (props.onRefresh) props.onRefresh();}}),
                    el(ActionButton, {action:'detect-conflicts', label:__('Conflicten scannen','ultracache-pro'), help:__('Controleert actieve cache-, optimalisatie-, WooCommerce- en builderplugins op mogelijke overlap.','ultracache-pro'), addNotice:props.addNotice, setStatus:props.setStatus, onComplete:loadDiagnostics}),
                    el(ActionButton, {action:'enable-debug-mode', label:__('Debug/testmodus 30 min','ultracache-pro'), help:__('Zet logs, diagnostiek, debugheaders en admin queue runner tijdelijk aan voor een betere QA-run.','ultracache-pro'), addNotice:props.addNotice, setStatus:props.setStatus, onComplete:loadDiagnostics}),
                    el(ActionButton, {action:'release-checklist', label:__('Release checklist ophalen','ultracache-pro'), help:__('Toont in de log/response de belangrijkste releasechecks: Plugin Check, runtime cache test en WooCommerce transactietest.','ultracache-pro'), addNotice:props.addNotice, setStatus:props.setStatus, onComplete:loadDiagnostics}),
                    el('div', {className:'ucp-action-row ucp-action-row--static'},
                        el('div', {className:'ucp-action-copy'},
                            el('strong', {}, __('Logpakket downloaden','ultracache-pro')),
                            el('p', {}, __('Download een geredigeerde ZIP met activity logs, errors, runtime info, queue-status en systeeminformatie. Controleer het pakket voordat je het extern deelt.','ultracache-pro'))
                        ),
                        el('div', {className:'ucp-action-button'},
                            el(Button, {variant:'secondary', onClick:function(){ if (config.logDownloadUrl) { window.location.href = config.logDownloadUrl; } else { props.addNotice({status:'error', message:__('Logdownload is niet beschikbaar.','ultracache-pro')}); } }}, __('Logpakket downloaden','ultracache-pro'))
                        )
                    )
                )
            )),
            el('div', {className:'ucp-dashboard-grid'},
                el(Card, {className:'ucp-card'}, el(CardHeader, {}, el('h2', {}, __('Recente taken','ultracache-pro'))), el(CardBody, {}, jobs ? el(DataList, {rows:jobs.rows || [], primary:function(r){return (r.type || 'job') + ' · ' + (r.status || '');}, secondary:function(r){return (r.updated_at || r.created_at || '') + ' · pogingen ' + (r.attempts || 0) + '/' + (r.max_attempts || 0);}}) : el(Spinner, {}))),
                el(Card, {className:'ucp-card'}, el(CardHeader, {}, el('h2', {}, __('Recente logs','ultracache-pro'))), el(CardBody, {}, logs ? el(DataList, {rows:logs.rows || [], primary:function(r){return (r.level || 'info') + ' · ' + (r.event || r.component || 'log');}, secondary:function(r){return (r.message || '') + ' · ' + (r.created_at || '');}}) : el(Spinner, {})))
            ),
            el(Card, {className:'ucp-card'}, el(CardHeader, {}, el('h2', {}, __('Recente diagnostiekrequests','ultracache-pro'))), el(CardBody, {}, requests ? el(DataList, {rows:requests.rows || [], primary:function(r){return (r.cache_decision || 'request') + ' · ' + (r.request_type || '');}, secondary:function(r){return (r.url || r.path || '') + ' · ' + (r.generated_at || '');}}) : el(Spinner, {}))),
            el(SettingsPage, Object.assign({}, props, {kind:'diagnostics'}))
        );
    }

    function LoadingScreen(){
        return el('div', {className:'ucp-loading-screen', role:'status', 'aria-live':'polite'},
            el('div', {className:'ucp-loading-card'},
                el('div', {className:'ucp-loading-mark', 'aria-hidden':'true'},
                    el('span', {className:'ucp-loading-ring'}),
                    el('span', {className:'ucp-loading-dot'})
                ),
                el('div', {className:'ucp-loading-copy'},
                    el('h1', {}, __('UltraCache Pro wordt voorbereid','ultracache-pro')),
                    el('p', {}, __('We laden je cache-instellingen en diagnostiek. Dit duurt meestal maar even.','ultracache-pro'))
                )
            )
        );
    }

    function App(){
        var tabState = useState('dashboard'), activeTab = tabState[0], setActiveTab = tabState[1];
        var statusState = useState(null), status = statusState[0], setStatus = statusState[1];
        var settingsState = useState(null), settings = settingsState[0], setSettings = settingsState[1];
        var loadingState = useState(true), loading = loadingState[0], setLoading = loadingState[1];
        var noticesState = useState([]), notices = noticesState[0], setNotices = noticesState[1];
        var wizardState = useState(false), wizardOpen = wizardState[0], setWizardOpen = wizardState[1];
        function addNotice(n){
            var notice = Object.assign({id:Date.now()+Math.random()}, n);
            setNotices(function(cur){ return cur.concat([notice]); });
            window.setTimeout(function(){ removeNotice(notice.id); }, notice.status === 'error' ? 6500 : 4500);
        }
        function removeNotice(id){ setNotices(function(cur){ return cur.filter(function(n){return n.id !== id;}); }); }
        function refresh(){ setLoading(true); Promise.all([getStatus(), getSettings()]).then(function(res){ setStatus(res[0].status); setSettings(res[1].settings); }).catch(function(err){ addNotice({status:'error', message:cleanErrorMessage(err, 'UltraCache data kon niet geladen worden.')}); }).finally(function(){ setLoading(false); }); }
        useEffect(function(){ refresh(); }, []);
        if (loading && !settings) return el(LoadingScreen, {});
        var shared = {settings:settings || {}, setSettings:setSettings, status:status || {}, setStatus:setStatus, addNotice:addNotice, onRefresh:refresh, loading:loading};
        return el(AdminShell,{activeTab:activeTab,onTab:setActiveTab,onRefresh:refresh,loading:loading,onOpenWizard:function(){setWizardOpen(true);}},
            el(NoticeArea,{notices:notices,onRemove:removeNotice}),
            wizardOpen ? el(SetupWizard,Object.assign({}, shared, {onClose:function(){setWizardOpen(false); saveSettings({onboarding_completed:1}).then(function(resp){ if(resp.settings) setSettings(resp.settings); });}})) : null,
            activeTab === 'dashboard' ? el(DashboardPage,Object.assign({}, shared, {status:status,onOpenWizard:function(){}})) : null,
            activeTab === 'optimization' ? el(SettingsPage,Object.assign({}, shared, {kind:'optimization'})) : null,
            activeTab === 'media' ? el(SettingsPage,Object.assign({}, shared, {kind:'media'})) : null,
            activeTab === 'preload' ? el(SettingsPage,Object.assign({}, shared, {kind:'preload'})) : null,
            activeTab === 'advanced' ? el(SettingsPage,Object.assign({}, shared, {kind:'advanced'})) : null,
            activeTab === 'database' ? el(SettingsPage,Object.assign({}, shared, {kind:'database'})) : null,
            activeTab === 'cdn' ? el(SettingsPage,Object.assign({}, shared, {kind:'cdn'})) : null,
            activeTab === 'heartbeat' ? el(SettingsPage,Object.assign({}, shared, {kind:'heartbeat'})) : null,
            activeTab === 'diagnostics' ? el(DiagnosticsPage,Object.assign({}, shared, {status:status})) : null,
            activeTab === 'tools' ? el(ActionsPage,Object.assign({}, shared, {title:__('Tools','ultracache-pro')})) : null
        );
    }

    document.addEventListener('DOMContentLoaded', function(){
        var root = document.getElementById('ucp-admin-root');
        if (root) render(el(App), root);
    });
})(window.wp);
