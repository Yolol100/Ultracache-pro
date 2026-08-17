/*
 * UltraCache Pro React admin runtime.
 * Static settings definitions live in ucp-react-admin-schema.js so this file
 * remains focused on state, rendering and REST interactions.
 */
!function(e) {
    "use strict";
    var a = e.element.createElement, t = e.element.Fragment, r = e.element.useState, n = e.element.useEffect, UCPUseRef = e.element.useRef, c = e.element.createRoot, o = e.element.render, UCPComponent = e.element.Component, i = e.i18n.__, s = e.i18n.sprintf || function(e) {
        var a = Array.prototype.slice.call(arguments, 1), t = 0;
        return String(e || "").replace(/%([0-9]+\$)?[sd]/g, function() {
            var e = a[t++];
            return void 0 === e ? "" : String(e);
        });
    }, l = e.apiFetch, u = e.components, d = u.Card, p = u.CardHeader, h = u.CardBody, g = u.Button, m = u.Notice, M = u.Modal || null, b = u.ToggleControl, FormToggleControl = u.FormToggle || null, f = u.TextControl, v = u.TextareaControl, _ = u.SelectControl, k = u.FormTokenField || null, y = u.CheckboxControl, w = u.__experimentalNumberControl || u.NumberControl || f, N = window.UCP_ADMIN_CONFIG || {};
    var UCPManagedSettingKeys = Array.isArray(N.managedSettingKeys) ? N.managedSettingKeys : [];
    l && l.use && l.createNonceMiddleware && N.nonce && l.use(l.createNonceMiddleware(N.nonce));
    var C = [ {
        key: "dashboard",
        label: i("Overzicht", "ultracache-pro"),
        icon: "dashicons-dashboard"
    }, {
        key: "cache",
        label: i("Cache & opbouw", "ultracache-pro"),
        icon: "dashicons-admin-generic"
    }, {
        key: "media",
        label: i("Media & lettertypen", "ultracache-pro"),
        icon: "dashicons-format-image"
    }, {
        key: "woocommerce",
        label: i("WooCommerce", "ultracache-pro"),
        icon: "dashicons-cart"
    }, {
        key: "optimization",
        label: i("CSS & JS", "ultracache-pro"),
        icon: "dashicons-performance"
    }, {
        key: "server",
        label: i("Server & CDN", "ultracache-pro"),
        icon: "dashicons-cloud"
    }, {
        key: "advanced",
        label: i("Uitsluitingen", "ultracache-pro"),
        icon: "dashicons-list-view"
    }, {
        key: "tools",
        label: i("Onderhoud", "ultracache-pro"),
        icon: "dashicons-admin-tools"
    } ];
    function A(e) {
        var a = String(e || "").toLowerCase().replace(/[^a-z0-9_-]/g, "");
        return a = {
            overview: "dashboard",
            dashboard: "dashboard",
            diagnostics: "tools",
            toolbox: "tools",
            integrations: "tools",
            addons: "tools",
            database: "tools",
            preload: "cache",
            expert: "advanced",
            assets: "optimization",
            assetmanager: "optimization",
            "asset-manager": "optimization",
            css: "optimization",
            js: "optimization",
            "css-js": "optimization",
            cdn: "server",
            server: "server",
            objectcache: "server",
            "object-cache": "server",
            "server-cdn": "server",
            woo: "woocommerce",
            shop: "woocommerce",
            ecommerce: "woocommerce",
            "advanced-rules": "advanced",
            advanced_rules: "advanced",
            heartbeat: "advanced",
            "ultracache-pro": "dashboard",
            "ultracache-pro-cache": "cache",
            "ultracache-pro-file-optimization": "optimization",
            "ultracache-pro-media": "media",
            "ultracache-pro-preload": "cache",
            "ultracache-pro-assets": "optimization",
            "ultracache-pro-advanced-rules": "advanced",
            "ultracache-pro-assets-manager": "optimization",
            "ultracache-pro-asset-manager": "optimization",
            "ultracache-pro-asset-cleanup": "optimization",
            "ultracache-pro-database": "tools",
            "ultracache-pro-cdn": "server",
            "ultracache-object-cache": "server",
            "ultracache-pro-object-cache": "server",
            "ultracache-pro-woocommerce": "woocommerce",
            "ultracache-pro-heartbeat": "advanced",
            "ultracache-pro-addons": "tools",
            "ultracache-pro-tools": "tools",
            "ultracache-pro-toolbox": "tools",
            "ultracache-pro-integrations": "tools"
        }[a] || a, C.some(function(e) {
            return e.key === a;
        }) ? a : "dashboard";
    }
    function I() {
        try {
            var e = new URLSearchParams(window.location.search || "");
            return A(e.get("tab") || e.get("page") || "dashboard");
        } catch (e) {
            return "dashboard";
        }
    }
    function UCPAdminTabUrl(e) {
        var a = A(e);
        try {
            var t = new URL(window.location.href);
            return t.searchParams.set("page", "ultracache-pro"), t.searchParams.set("tab", "dashboard" === a ? "overview" : a), t.toString();
        } catch (e) {
            return "?page=ultracache-pro&tab=" + encodeURIComponent("dashboard" === a ? "overview" : a);
        }
    }
    function D(e) {
        return String(e || "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "item";
    }
    function UCPSettingsRowClass(settingKey, controlType, extraClass) {
        var type = D(controlType || "field"), className = "ucp-settings-row--control-" + type;
        settingKey && (className += " ucp-settings-row--setting-" + D(settingKey));
        "textarea" === type && (className += " ucp-settings-row--wide-control");
        extraClass && (className += " " + extraClass);
        return className;
    }
    function UCPSectionHero(e) {
        e = e || {};
        var t = e.badge || e.action ? a("div", {
            className: "ucp-section-hero__aside"
        }, e.badge ? a("span", {
            className: "ucp-status-badge " + (e.badgeClass || "ucp-status-badge--neutral")
        }, e.badge) : null, e.action ? a("div", {
            className: "ucp-section-hero__action"
        }, e.action) : null) : null;
        return a(d, {
            className: "ucp-card ucp-section-hero" + (e.className ? " " + e.className : "")
        }, a(h, {}, a("div", {
            className: "ucp-section-hero__inner"
        }, a("div", {
            className: "ucp-section-hero__copy"
        }, e.eyebrow ? a("span", {
            className: "ucp-eyebrow"
        }, e.eyebrow) : null, a("h2", {}, e.title || ""), e.description ? a("p", {}, e.description) : null), t)));
    }
    function UCPSettingsSection(e) {
        e = e || {};
        var t = Array.isArray(e.children) ? e.children.filter(Boolean) : e.children ? [ e.children ] : [], r = e.headerAction ? a("div", {
            className: "ucp-settings-section__action"
        }, e.headerAction) : e.badge ? a("span", {
            className: "ucp-status-badge " + (e.badgeClass || "ucp-status-badge--neutral")
        }, e.badge) : null, n = e.hideBody ? null : a(h, {}, a("div", {
            className: "ucp-settings-list"
        }, t));
        return a(d, {
            key: e.key || void 0,
            className: "ucp-card ucp-settings-section " + (e.className || "")
        }, e.hideHeader ? null : a(p, {}, a("div", {
            className: "ucp-settings-section__header"
        }, a("div", {
            className: "ucp-settings-section__heading"
        }, a("h2", {}, e.title || ""), e.description ? a("p", {}, e.description) : null), r)), n);
    }
    function UCPSettingsRow(e) {
        var t = "ucp-settings-row";
        e = e || {};
        e.fields ? t += " ucp-settings-row--fields" : t += " ucp-settings-row--control";
        e.className && (t += " " + e.className);
        return a("div", {
            key: e.key || void 0,
            className: t
        }, a("div", {
            className: "ucp-settings-row__copy"
        }, a("strong", {}, e.title || ""), e.description ? a("p", {}, e.description) : null, e.meta ? a("div", {
            className: "ucp-settings-row__meta"
        }, e.meta) : null), a("div", {
            className: (e.fields ? "ucp-settings-row__fields" : "ucp-settings-row__control") + (e.controlClassName ? " " + e.controlClassName : "")
        }, e.control || e.children || null));
    }
    var UCPRuntimeStatus = {};
    function UCPRememberRuntimeStatus(e) {
        return e && "object" == typeof e && (UCPRuntimeStatus = e), e;
    }
    var W = /actions\/(website-check|preload|refresh-css|used-css|critical-css|browser-scan|run-due-jobs|renderer-test|repair-cache-files|release-checklist|database-cleanup|detect-conflicts)(\/|$)/, UCPVeryLongAction = /actions\/runtime-cache-test(\/|$)/;
    var UCPActionRequestKeys = Object.create(null);
    function UCPNormalizeActionData(e) {
        if (!e || "object" != typeof e) return e;
        if (Array.isArray(e)) return e.map(UCPNormalizeActionData);
        var a = {};
        return Object.keys(e).sort().forEach(function(t) {
            a[t] = UCPNormalizeActionData(e[t]);
        }), a;
    }
    function UCPActionRequestFingerprint(e, a) {
        try {
            return String(e || "") + "|" + JSON.stringify(UCPNormalizeActionData(a && a.data ? a.data : {}));
        } catch (e) {
            return "";
        }
    }
    function UCPActionRequestId(e) {
        var a = Date.now(), t = UCPActionRequestKeys[e];
        return t && t.expires > a ? t.id : (t = window.crypto && "function" == typeof window.crypto.randomUUID ? window.crypto.randomUUID() : String(a) + "-" + Math.random().toString(16).slice(2), UCPActionRequestKeys[e] = {
            id: t,
            expires: a + 3e5
        }, t);
    }
    function R(e, a) {
        var t = String(e || "").replace(/^\/+/, ""), r = String(N.restUrl || "").replace(/\/?$/, "/"), n = Object.assign({}, a || {}), requestFingerprint = "";
        r ? n.url = r + t : n.path = "/ultracache-pro/v1/" + t;
        if (/^actions\//.test(t) && "POST" === String(n.method || "GET").toUpperCase()) {
            requestFingerprint = UCPActionRequestFingerprint(t, n);
            var requestId = UCPActionRequestId(requestFingerprint);
            n.headers = Object.assign({}, n.headers || {}, { "X-UCP-Idempotency-Key": requestId });
        }
        var c = "function" == typeof window.AbortController ? new window.AbortController : null;
        c && !n.signal && (n.signal = c.signal);
        var o = null, s = new Promise(function(_resolve, a) {
            o = window.setTimeout(function() {
                if (c) try {
                    c.abort();
                } catch (e) {}
                a(new Error(i("Timeout. Controleer Onderhoud voordat je de actie opnieuw start.", "ultracache-pro")));
            }, function(e) {
                return UCPVeryLongAction.test(String(e || "")) ? 18e4 : W.test(String(e || "")) ? 9e4 : 3e4;
            }(t));
        });
        function u() {
            o && (window.clearTimeout(o), o = null);
        }
        return Promise.race([ l(n), s ]).then(function(e) {
            return u(), requestFingerprint && delete UCPActionRequestKeys[requestFingerprint], e && e.status && UCPRememberRuntimeStatus(e.status), e;
        }, function(e) {
            throw u(), e;
        });
    }
    function G(e, a) {
        var t = e && e.message ? String(e.message) : String(a || i("Actie mislukt.", "ultracache-pro"));
        return (t = t.replace(/<\/?p>/g, " ").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim()) && "null" !== t && "undefined" !== t ? t : a || i("Actie mislukt.", "ultracache-pro");
    }
    var UCPSettingsSaveCounter = 0;
    var UCPSettingsSaveQueue = Promise.resolve();
    function UCPEmitSaveState(e) {
        try {
            window.dispatchEvent(new window.CustomEvent("ucp:settings-save-state", {
                detail: {
                    state: e
                }
            }));
        } catch (e) {}
    }
    function H(e) {
        UCPSettingsSaveCounter += 1;
        UCPEmitSaveState("saving");
        var a = UCPSettingsSaveQueue.then(function() {
            return R("settings", {
                method: "POST",
                data: e
            });
        });
        return UCPSettingsSaveQueue = a.catch(function() {}), a.then(function(e) {
            UCPSettingsSaveCounter = Math.max(0, UCPSettingsSaveCounter - 1), 0 === UCPSettingsSaveCounter && UCPEmitSaveState("saved");
            try {
                window.setTimeout(function() {
                    window.dispatchEvent(new window.CustomEvent("ucp:settings-saved", { detail: e || {} }));
                }, 0);
            } catch (a) {}
            return e;
        }, function(e) {
            throw UCPSettingsSaveCounter = Math.max(0, UCPSettingsSaveCounter - 1), UCPEmitSaveState("error"), e;
        });
    }
    function UCPW(e) {
        return e && Array.isArray(e.warnings) && e.warnings.length;
    }
    function UCPM(e, a) {
        return UCPW(e) ? e.warnings.map(function(e) {
            return String(e && e.message ? e.message : e);
        }).join(" ") : a;
    }
    function UCPS(e) {
        return UCPW(e) ? "warning" : "success";
    }
    function UCPPostAction(e, a) {
        return R("actions/" + e, {
            method: "POST",
            data: a || {}
        });
    }
    function UCPQueueNotice(e, a) {
        var t = e.findIndex(function(e) {
            return e.id === a.id;
        });
        if (-1 !== t) return [ Object.assign({}, e[t], a) ];
        if (e.length && e[0].status === a.status && e[0].message === a.message) return e;
        return [ a ];
    }
    function UCPNoticeDuration(e) {
        if (!e || e.persistent || "error" === e.status || e.actionLabel && "function" == typeof e.onAction) return 0;
        var a = parseInt(e.timeout || ("warning" === e.status ? 7e3 : 4500), 10);
        return a >= 1e3 ? a : 0;
    }
    function X(e) {
        var t = Array.isArray(e.notices) && e.notices.length ? e.notices[0] : null;
        n(function() {
            var a = UCPNoticeDuration(t);
            if (!a) return;
            var r = window.setTimeout(function() {
                e.onRemove(t.id);
            }, a);
            return function() {
                window.clearTimeout(r);
            };
        }, [ t && t.id, t && t.status, t && t.message, t && t.persistent, t && t.timeout ]);
        if (!t) return null;
        var r = -1 !== [ "success", "warning", "error", "info" ].indexOf(t.status) ? t.status : "info", c = {
            success: "✓",
            warning: "!",
            error: "!",
            info: "i"
        }[r];
        return a("div", {
            className: "ucp-notice-area"
        }, a("div", {
            key: t.id,
            className: "ucp-react-toast ucp-react-toast--" + r + (t.actionLabel && "function" == typeof t.onAction ? " ucp-react-toast--has-action" : ""),
            role: "error" === r ? "alert" : "status",
            "aria-live": "error" === r ? "assertive" : "polite",
            "aria-atomic": "true"
        }, a("span", {
            className: "ucp-react-toast__icon",
            "aria-hidden": "true"
        }, c), a("span", {
            className: "ucp-react-toast__message"
        }, t.message), t.actionLabel && "function" == typeof t.onAction ? a("button", {
            type: "button",
            className: "ucp-react-toast__action",
            onClick: function() {
                t.onAction();
            }
        }, t.actionLabel) : null, a("button", {
            type: "button",
            className: "ucp-react-toast__close",
            onClick: function() {
                e.onRemove(t.id);
            },
            "aria-label": i("Melding sluiten", "ultracache-pro")
        }, "×")));
    }
    function ee(e) {
        var t = String(e.state || "info"), n = {
            success: "good",
            danger: "error",
            blocked: "error",
            enabled: "good",
            disabled: "info",
            ready: "good",
            clear: "good",
            ok: "good",
            safe: "good",
            protected: "good",
            active: "good",
            review: "warning",
            pending: "warning"
        }[t] || t;
        -1 === [ "good", "warning", "info", "error" ].indexOf(n) && (n = "info");
        return a("span", {
            className: "ucp-status-badge ucp-status-badge--" + n
        }, e.children || e.label);
    }
    function ae(e) {
        var t = e.status || {}, r = t.cache || {}, n = "ucp-admin-page-title", c = -1 !== [ "saving", "saved", "error" ].indexOf(e.saveState) ? e.saveState : "idle", o = {
            idle: i("Automatisch opslaan", "ultracache-pro"),
            saving: i("Opslaan…", "ultracache-pro"),
            saved: i("Opgeslagen", "ultracache-pro"),
            error: i("Opslaan mislukt", "ultracache-pro")
        }[c], statusKnown = !1 !== e.statusKnown, readOnly = !!e.readOnly, statusBadge = statusKnown ? r.enabled ? a(ee, {
            state: "good",
            label: i("Cache actief", "ultracache-pro")
        }, i("Cache actief", "ultracache-pro")) : a(ee, {
            state: "warning",
            label: i("Cache niet actief", "ultracache-pro")
        }, i("Cache niet actief", "ultracache-pro")) : a(ee, {
            state: "warning",
            label: i("Status onbekend", "ultracache-pro")
        }, i("Status onbekend", "ultracache-pro"));
        return a("header", {
            className: "ucp-app-header ucp-app-header--native ucp-app-header--compact"
        }, a("div", {
            className: "ucp-app-header__main"
        }, a("p", {
            className: "ucp-eyebrow"
        }, i("WordPress-prestaties", "ultracache-pro")), a("h1", {
            id: n
        }, i("UltraCache Pro", "ultracache-pro")), a("p", {}, i("Bekijk of de versnelling werkt en pas alleen de belangrijkste onderdelen aan.", "ultracache-pro"))), a("div", {
            className: "ucp-app-header__side",
            role: "group",
            "aria-label": i("Belangrijkste status", "ultracache-pro")
        }, a("div", {
            className: "ucp-app-header__status",
            role: "group",
            "aria-label": i("Pluginstatus", "ultracache-pro")
        }, statusBadge, readOnly || !statusKnown ? a("span", {
            className: "ucp-save-state ucp-save-state--error"
        }, i("Alleen lezen", "ultracache-pro")) : a("span", {
            className: "ucp-save-state ucp-save-state--" + c,
            role: "status",
            "aria-live": "polite",
            "aria-atomic": "true"
        }, o)), e.onOpenWizard && statusKnown && !readOnly ? a("div", {
            className: "ucp-app-header__controls"
        }, a(g, {
            type: "button",
            variant: "secondary",
            className: "ucp-header-setup-button",
            "aria-haspopup": "dialog",
            onClick: function() {
                e.onOpenWizard();
            }
        }, i("Configuratie-assistent", "ultracache-pro"))) : null));
    }
    function te(e) {
        var t = e.activeTab || "dashboard", statusKnown = !1 !== e.statusKnown, r = statusKnown ? C.filter(function(a) {
            return "woocommerce" !== a.key || Me(e.status || {});
        }) : C.slice(), n = r.filter(function(e) {
            return e.key === t;
        })[0] || r[0] || C[0];
        function c(a, t) {
            var n = a.key || "";
            if (-1 !== [ "ArrowLeft", "ArrowUp", "ArrowRight", "ArrowDown", "Home", "End" ].indexOf(n)) {
                a.preventDefault();
                var c = r.map(function(e) {
                    return e.key;
                }).indexOf(t.key);
                if (-1 !== c) {
                    var o = c;
                    "Home" === n ? o = 0 : "End" === n ? o = r.length - 1 : "ArrowLeft" === n || "ArrowUp" === n ? o = (c - 1 + r.length) % r.length : "ArrowRight" !== n && "ArrowDown" !== n || (o = (c + 1) % r.length);
                    var s = r[o].key;
                    e.onTab(s), function(e) {
                        window.setTimeout(function() {
                            var a = document.getElementById("ucp-admin-tab-" + e);
                            a && a.focus && a.focus();
                        }, 0);
                    }(s);
                }
            }
        }
        return a("div", {
            className: "ucp-admin-app__shell"
        }, a(ae, {
            status: e.status || {},
            statusKnown: statusKnown,
            readOnly: !!e.readOnly,
            saveState: e.saveState,
            activeTab: t,
            onOpenWizard: e.onOpenWizard,
            onTab: e.onTab
        }), a("div", {
            className: "ucp-admin-section-picker"
        }, a("label", {
            htmlFor: "ucp-admin-section-select"
        }, i("Onderdeel", "ultracache-pro")), a("select", {
            id: "ucp-admin-section-select",
            value: t,
            onChange: function(a) {
                e.onTab(a.target.value);
            }
        }, r.map(function(e) {
            return a("option", {
                key: e.key,
                value: e.key
            }, e.label);
        }))), a("nav", {
            className: "ucp-admin-nav",
            role: "tablist",
            "aria-label": i("UltraCache Pro onderdelen", "ultracache-pro")
        }, r.map(function(r) {
            var n = t === r.key, o = "ucp-admin-tab-" + r.key;
            return a("button", {
                key: r.key,
                id: o,
                type: "button",
                role: "tab",
                className: "ucp-admin-nav__item" + (n ? " is-active" : ""),
                "aria-selected": n ? "true" : "false",
                "aria-current": n ? "page" : void 0,
                "aria-controls": "ucp-admin-panel",
                tabIndex: n ? 0 : -1,
                onClick: function() {
                    e.onTab(r.key);
                },
                onKeyDown: function(e) {
                    c(e, r);
                }
            }, a("span", {
                className: "dashicons " + r.icon,
                "aria-hidden": "true"
            }), a("span", {
                className: "ucp-admin-nav__text"
            }, a("strong", {
                className: "ucp-admin-nav__label"
            }, r.label)));
        })), a("section", {
            id: "ucp-admin-panel",
            className: "ucp-admin-panel",
            role: "tabpanel",
            "aria-labelledby": "ucp-admin-tab-" + (n ? n.key : "dashboard"),
            tabIndex: 0
        }, e.children));
    }
    function se(e, a) {
        return 1 === parseInt((e || {})[a] || 0, 10);
    }
    function UCPQueueState(e) {
        e = e || {};
        var a = e.runner || {}, t = parseInt(a.due || 0, 10) || 0, r = parseInt(e.pending || 0, 10) || 0, n = parseInt(e.retrying || 0, 10) || 0, c = parseInt(e.running || 0, 10) || 0, o = parseInt(e.failed || 0, 10) || 0, scheduled = Math.max(0, r + n - t), parts = [];
        o > 0 && parts.push(o + " " + (1 === o ? i("taak mislukt", "ultracache-pro") : i("taken mislukt", "ultracache-pro")));
        t > 0 && parts.push(t + " " + (1 === t ? i("taak in wachtrij", "ultracache-pro") : i("taken in wachtrij", "ultracache-pro")));
        c > 0 && parts.push(c + " " + (1 === c ? i("taak actief", "ultracache-pro") : i("taken actief", "ultracache-pro")));
        scheduled > 0 && parts.push(scheduled + " " + (1 === scheduled ? i("taak gepland", "ultracache-pro") : i("taken gepland", "ultracache-pro")));
        return {
            count: o + t + c + scheduled,
            due: t,
            running: c,
            scheduled: scheduled,
            failed: o,
            state: o > 0 ? "warning" : t > 0 || c > 0 || scheduled > 0 ? "info" : "good",
            label: parts.length ? parts.join(" · ") : i("Wachtrij rustig", "ultracache-pro")
        };
    }
    function UCPQueueActionHelp(e) {
        e = e || {};
        if (e.failed > 0) {
            if (e.due > 0) return s(i("%1$d mislukt; %2$d wachten. Verwerk de wachtende taken of gebruik de herstelactie voor de mislukte taken.", "ultracache-pro"), e.failed, e.due);
            return 1 === e.failed ? i("1 taak is mislukt. Gebruik de herstelactie hieronder.", "ultracache-pro") : s(i("%d taken zijn mislukt. Gebruik de herstelactie hieronder.", "ultracache-pro"), e.failed);
        }
        if (e.running > 0) {
            var runningText = 1 === e.running ? i("1 taak wordt nu verwerkt.", "ultracache-pro") : s(i("%d taken worden nu verwerkt.", "ultracache-pro"), e.running);
            return e.due > 0 ? runningText + " " + (1 === e.due ? i("1 taak wacht nog.", "ultracache-pro") : s(i("%d taken wachten nog.", "ultracache-pro"), e.due)) : runningText;
        }
        if (e.due > 0) return 1 === e.due ? i("1 taak wacht en kan nu worden verwerkt.", "ultracache-pro") : s(i("%d taken wachten en kunnen nu worden verwerkt.", "ultracache-pro"), e.due);
        if (e.scheduled > 0) return 1 === e.scheduled ? i("1 taak staat gepland en wordt automatisch verwerkt zodra die klaarstaat.", "ultracache-pro") : s(i("%d taken staan gepland en worden automatisch verwerkt zodra ze klaarstaan.", "ultracache-pro"), e.scheduled);
        return i("Er staan momenteel geen taken klaar.", "ultracache-pro");
    }
    function ue(e) {
        var t = "advanced" === e.type;
        return a("span", {
            className: "ucp-safety-label " + (t ? "ucp-safety-label--advanced" : "ucp-safety-label--safe")
        }, t ? i("Eerst testen", "ultracache-pro") : i("Veilige standaard", "ultracache-pro"));
    }
    function UCPFormatAdminDate(value) {
        if (!value) return i("Nog niet", "ultracache-pro");
        var date = new Date(value);
        return isNaN(date.getTime()) ? String(value) : date.toLocaleString();
    }
    function UCPSnapshotContextLabel(value) {
        return {
            auto_save: i("Voor wijziging", "ultracache-pro"),
            before_restore: i("Voor herstel", "ultracache-pro"),
            manual_rest: i("Handmatig", "ultracache-pro"),
            conflict_resolution: i("Voor conflictoplossing", "ultracache-pro")
        }[String(value || "")] || i("Wijziging", "ultracache-pro");
    }
    function UCPWebsiteControlCenter(e) {
        var status = e.status || {}, quality = status.quality || {}, check = quality.websiteCheck || {}, operational = check.operational || quality.operational || {}, conflictPlan = check.conflictPlan || quality.conflictPlan || {}, support = quality.supportMode || {}, showTechnical = UCPAdvancedSettingsVisible(e.settings || {}) || UCPExplicitSupportMode(status), snapshotState = r([]), snapshots = snapshotState[0], setSnapshots = snapshotState[1], snapshotLoadState = r({ loading: !0, error: "" }), snapshotLoad = snapshotLoadState[0], setSnapshotLoad = snapshotLoadState[1], restoreState = r(""), restoring = restoreState[0], setRestoring = restoreState[1], reportState = r(!1), reportBusy = reportState[0], setReportBusy = reportState[1];
        function loadSnapshots() {
            setSnapshotLoad({ loading: !0, error: "" });
            return R("settings/snapshots").then(function(response) {
                setSnapshots(response && Array.isArray(response.summaries) ? response.summaries : []), setSnapshotLoad({ loading: !1, error: "" });
                return response;
            }).catch(function(error) {
                setSnapshotLoad({ loading: !1, error: G(error, i("Herstelpunten konden niet worden geladen.", "ultracache-pro")) });
                return null;
            });
        }
        n(function() {
            loadSnapshots();
            function handleSettingsSaved() {
                loadSnapshots();
            }
            window.addEventListener("ucp:settings-saved", handleSettingsSaved);
            return function() {
                window.removeEventListener("ucp:settings-saved", handleSettingsSaved);
            };
        }, []);
        function restoreSnapshot(snapshot) {
            if (!snapshot || !snapshot.id || restoring) return;
            setRestoring(snapshot.id), R("settings/snapshots/restore", {
                method: "POST",
                data: { id: snapshot.id }
            }).then(function(response) {
                response.settings && e.setSettings(response.settings), response.status && e.setStatus(response.status), setSnapshots(response.summaries || []), e.addNotice({
                    id: "ucp-restore-center",
                    status: "success",
                    message: response.message || i("Vorige instellingen zijn teruggezet.", "ultracache-pro")
                });
            }).catch(function(error) {
                e.addNotice({ id: "ucp-restore-center", status: "error", message: G(error, i("Terugzetten is niet gelukt.", "ultracache-pro")) });
            }).finally(function() {
                setRestoring("");
            });
        }
        function downloadSupportReport() {
            if (reportBusy) return;
            setReportBusy(!0), R("support-report").then(function(response) {
                var blob = new Blob([ JSON.stringify(response && response.report ? response.report : response, null, 2) ], { type: "application/json" }), url = window.URL.createObjectURL(blob), link = document.createElement("a");
                link.href = url, link.download = "ultracache-support-report.json", document.body.appendChild(link), link.click(), document.body.removeChild(link), window.URL.revokeObjectURL(url);
            }).catch(function(error) {
                e.addNotice({ status: "error", message: G(error, i("Het supportrapport kon niet worden gedownload.", "ultracache-pro")) });
            }).finally(function() {
                setReportBusy(!1);
            });
        }
        var page = operational.pageCache || {}, compression = operational.compression || {}, dropin = operational.dropin || {}, objectCache = operational.objectCache || {}, preload = operational.preload || {}, jobs = operational.jobs || {}, checkState = check.state || "warning", jobProblems = parseInt(jobs.failed || 0, 10) + parseInt(jobs.staleRunning || 0, 10), primaryItems = [ {
            key: "cache", label: i("Pagina’s versnellen", "ultracache-pro"), value: !page.configured ? i("Uit", "ultracache-pro") : page.working ? i("Werkt", "ultracache-pro") : i("Controle nodig", "ultracache-pro"), state: page.working ? "good" : page.configured ? "warning" : "info"
        }, {
            key: "compression", label: i("Snellere overdracht", "ultracache-pro"), value: compression.actual ? i("Werkt", "ultracache-pro") : i("Niet gemeten", "ultracache-pro"), state: compression.actual ? "good" : "info"
        }, {
            key: "preload", label: i("Pagina’s voorbereiden", "ultracache-pro"), value: !preload.enabled ? i("Uit", "ultracache-pro") : preload.lastCompleted ? i("Voltooid", "ultracache-pro") : i("Nog niet voltooid", "ultracache-pro"), state: preload.enabled && preload.lastCompleted ? "good" : "info"
        }, {
            key: "jobs", label: i("Automatische verwerking", "ultracache-pro"), value: jobProblems ? s(i("%d aandachtspunt(en)", "ultracache-pro"), jobProblems) : i("In orde", "ultracache-pro"), state: jobProblems ? "warning" : "good"
        } ], technicalItems = [ {
            key: "signal", label: i("Cachesignaal", "ultracache-pro"), value: page.signal || i("Niet getest", "ultracache-pro"), state: page.working ? "good" : "info"
        }, {
            key: "encoding", label: i("Compressiemethode", "ultracache-pro"), value: compression.encoding ? String(compression.encoding).toUpperCase() : i("Niet gemeten", "ultracache-pro"), state: compression.actual ? "good" : "info"
        }, {
            key: "dropin", label: i("Cachekoppeling", "ultracache-pro"), value: dropin.ready ? i("Gereed", "ultracache-pro") : i("Controle nodig", "ultracache-pro"), state: dropin.ready ? "good" : "warning"
        }, {
            key: "object", label: i("Databaseversnelling", "ultracache-pro"), value: objectCache.reachable ? i("Bereikbaar", "ultracache-pro") : objectCache.configured ? i("Niet bereikbaar", "ultracache-pro") : i("Niet gebruikt", "ultracache-pro"), state: objectCache.configured && !objectCache.reachable ? "warning" : "good"
        } ], objectCacheExplicit = UCPSettingEnabled(e.settings || {}, "enable_redis_object_cache") || UCPSettingEnabled(e.settings || {}, "enable_apcu_object_cache") || !!((objectCache.detail || {}).dropin), problemChecks = Array.isArray(check.checks) ? check.checks.filter(function(item) { return item && "good" !== item.state; }).filter(function(item) { if ("page-cache" === item.key && page.configured && !dropin.ready) return !1; if ("compression" === item.key) return !1; if ("object-cache" === item.key && !objectCacheExplicit && !objectCache.reachable) return !1; if ("preload" === item.key && !preload.enabled) return !1; return !0; }).slice(0, 4) : [], conflictItems = Array.isArray(conflictPlan.items) ? conflictPlan.items : [], conflictChanges = Array.isArray(conflictPlan.recommendedChanges) ? conflictPlan.recommendedChanges : [];
        problemChecks.length || (checkState = "good");
        return a("section", {
            className: "ucp-control-center",
            "aria-labelledby": "ucp-control-center-title"
        }, a("div", {
            className: "ucp-control-center__header"
        }, a("div", {}, a("span", { className: "ucp-eyebrow" }, i("Website status", "ultracache-pro")), a("h2", { id: "ucp-control-center-title" }, i("Is alles goed ingesteld?", "ultracache-pro")), a("p", {}, i("Controleert de werking en toont alleen aandachtspunten.", "ultracache-pro"))), a("div", { className: "ucp-control-center__header-actions" }, check.generatedAt ? a(ee, { state: "good" === checkState ? "good" : "warning" }, "good" === checkState ? i("Alles in orde", "ultracache-pro") : i("Controle nodig", "ultracache-pro")) : null, a(Pe, {
            action: "website-check",
            label: i("Website controleren", "ultracache-pro"),
            busyLabel: i("Controleren…", "ultracache-pro"),
            pendingMessage: i("De website-instellingen worden gecontroleerd…", "ultracache-pro"),
            variant: "primary",
            compact: !0,
            addNotice: e.addNotice,
            setStatus: e.setStatus,
            onComplete: function() { loadSnapshots(); }
        }))), a("div", { className: "ucp-control-center__grid" }, primaryItems.map(function(item) {
            return a("article", { className: "ucp-control-center__metric", key: item.key }, a("span", {}, item.label), a(ee, { state: item.state }, item.value));
        })), check.generatedAt ? a("p", { className: "ucp-control-center__timestamp" }, i("Laatst gecontroleerd:", "ultracache-pro") + " " + UCPFormatAdminDate(check.generatedAt)) : a("p", { className: "ucp-control-center__timestamp" }, i("Voer de controle uit om de actuele werking te bekijken.", "ultracache-pro")), problemChecks.length ? a("div", { className: "ucp-control-center__issues" }, problemChecks.map(function(item) {
            return a("div", { className: "ucp-control-center__issue", key: item.key }, a("div", {}, a("strong", {}, item.label), a("p", {}, item.detail || item.value)), item.action && "apply-conflict-resolution" !== item.action ? a(Pe, {
                action: item.action,
                label: i("Oplossen", "ultracache-pro"),
                compact: !0,
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                onComplete: e.onRefresh
            }) : null);
        })) : null, conflictItems.length ? a("details", { className: "ucp-control-center__details" }, a("summary", {}, a("span", {}, i("Dubbele optimalisatie gevonden", "ultracache-pro")), a(ee, { state: "warning" }, String(conflictItems.length))), a("div", { className: "ucp-control-center__details-body" }, conflictItems.map(function(item) {
            return a("article", { className: "ucp-conflict-plan-item", key: item.id }, a("div", {}, a("strong", {}, item.label), a("p", {}, i("Wordt al geregeld door:", "ultracache-pro") + " " + (item.owner || item.label)), item.featureLabels && item.featureLabels.length ? a("small", {}, i("Overlap:", "ultracache-pro") + " " + item.featureLabels.join(" · ")) : null), item.changes && item.changes.length ? a("ul", {}, item.changes.map(function(change) {
                return a("li", { key: change.key }, s(i("UltraCache schakelt ‘%s’ uit.", "ultracache-pro"), change.label));
            })) : a("p", { className: "ucp-muted" }, i("Kies handmatig welke plugin deze functie moet beheren.", "ultracache-pro")));
        }), conflictChanges.length ? a(Pe, {
            action: "apply-conflict-resolution",
            data: { confirmed: !0 },
            label: i("Dubbele functies uitschakelen", "ultracache-pro"),
            confirm: !0,
            confirmText: i("Alleen overlappende UltraCache-functies worden uitgeschakeld.", "ultracache-pro"),
            compact: !0,
            addNotice: e.addNotice,
            setStatus: e.setStatus,
            onComplete: function(response) {
                response.settings && e.setSettings(response.settings), loadSnapshots();
            }
        }) : null)) : null, a("details", { className: "ucp-control-center__details" }, a("summary", {}, a("span", {}, i("Vorige instellingen", "ultracache-pro")), a(ee, { state: snapshotLoad.error ? "warning" : snapshots.length ? "good" : "info" }, snapshotLoad.loading ? "…" : snapshotLoad.error ? "!" : String(snapshots.length))), a("div", { className: "ucp-control-center__details-body" }, snapshotLoad.loading ? a("p", { className: "ucp-muted", role: "status" }, i("Herstelpunten laden…", "ultracache-pro")) : snapshotLoad.error ? a(m, { status: "error", isDismissible: !1 }, a("p", {}, snapshotLoad.error), a(g, { variant: "secondary", onClick: loadSnapshots }, i("Opnieuw proberen", "ultracache-pro"))) : snapshots.length ? snapshots.slice(0, 3).map(function(snapshot) {
            var difference = UCPSnapshotChangeLabel(snapshot);
            return a("article", {
                className: "ucp-snapshot-row",
                key: snapshot.id
            }, a("div", {
                className: "ucp-snapshot-row__content"
            }, a("span", {
                className: "dashicons dashicons-backup ucp-snapshot-row__icon",
                "aria-hidden": "true"
            }), a("div", {
                className: "ucp-snapshot-row__identity"
            }, a("strong", {}, UCPSnapshotContextLabel(snapshot.context)), a("time", {
                dateTime: String(snapshot.createdAt || "")
            }, UCPFormatAdminDate(snapshot.createdAt))), a("p", {
                className: "ucp-snapshot-row__changes"
            }, difference)), a(g, {
                variant: "secondary",
                isBusy: restoring === snapshot.id,
                disabled: !!restoring,
                onClick: function() { restoreSnapshot(snapshot); }
            }, i("Terugzetten", "ultracache-pro")));
        }) : a("p", { className: "ucp-muted" }, i("Vóór elke wijziging wordt automatisch een herstelpunt gemaakt.", "ultracache-pro")))), showTechnical ? a("details", { className: "ucp-control-center__details" }, a("summary", {}, a("span", {}, i("Technische details voor support", "ultracache-pro"))), a("div", { className: "ucp-control-center__details-body" }, a("div", { className: "ucp-control-center__grid" }, technicalItems.map(function(item) {
            return a("article", { className: "ucp-control-center__metric", key: item.key }, a("span", {}, item.label), a(ee, { state: item.state }, item.value));
        })))) : null, a("details", { className: "ucp-control-center__details ucp-control-center__support", open: support.active ? !0 : void 0 }, a("summary", {}, a("span", {}, i("Hulp bij problemen", "ultracache-pro")), support.active ? a(ee, { state: "warning" }, i("Extra controles actief", "ultracache-pro")) : null), a("div", { className: "ucp-control-center__details-body" }, a("div", {
            className: "ucp-control-center__support-layout"
        }, a("div", {
            className: "ucp-control-center__support-copy"
        }, a("span", {
            className: "dashicons dashicons-sos ucp-control-center__support-icon",
            "aria-hidden": "true"
        }), a("div", {}, a("strong", {}, support.active ? i("Extra controles zijn tijdelijk actief", "ultracache-pro") : i("Tijdelijk extra informatie verzamelen", "ultracache-pro")), a("p", {}, support.active ? i("Download het rapport voor support. De modus stopt vanzelf.", "ultracache-pro") : i("Alleen gebruiken voor onderzoek of support.", "ultracache-pro")))), a("div", {
            className: "ucp-control-center__support-actions"
        }, a(Pe, {
            action: support.active ? "disable-debug-mode" : "enable-debug-mode",
            label: support.active ? i("Extra controles stoppen", "ultracache-pro") : i("Extra controles starten", "ultracache-pro"),
            variant: "secondary",
            compact: !0,
            addNotice: e.addNotice,
            setStatus: e.setStatus,
            onComplete: e.onRefresh
        }))), support.active ? a("div", { className: "ucp-control-center__support-downloads" }, a(g, {
            variant: "secondary",
            isBusy: reportBusy,
            disabled: reportBusy,
            onClick: downloadSupportReport
        }, i("Supportrapport downloaden", "ultracache-pro")), support.logPackageUrl ? a("a", { className: "components-button is-secondary", href: support.logPackageUrl }, i("Logpakket downloaden", "ultracache-pro")) : null, support.until ? a("small", {}, i("Actief tot", "ultracache-pro") + " " + UCPFormatAdminDate(1e3 * parseInt(support.until, 10))) : null) : null)))
    }
    function me(e) {
        var t = e.status || {}, r = e.settings || {}, n = t.cache || {}, c = t.optimization || {}, o = Me(t), s = UCPQueueState(t.queue || {}), l = se(r, "enable_cache") || !!n.enabled, u = se(r, "enable_lazy_images") || se(r, "enable_lazy_iframes") || se(r, "enable_lazy_youtube_preview") || !!c.lazyImages || !!c.lazyIframes || !!c.lazyYoutube, d = se(r, "enable_css_minify") || !!c.cssMinify, p = se(r, "enable_preload"), h = o && (se(r, "enable_woocommerce_rules") || !!n.wooRules) && (se(r, "woocommerce_safety_mode") || !!n.wooSafety), m = [ {
            key: "cache",
            tab: "cache",
            label: i("Paginacache", "ultracache-pro"),
            help: i("Statische pagina-cache voor snellere laadtijd.", "ultracache-pro"),
            value: l ? i("Aan", "ultracache-pro") : i("Uit", "ultracache-pro"),
            state: l ? "good" : "warning"
        }, {
            key: "media",
            tab: "media",
            label: i("Afbeeldingen", "ultracache-pro"),
            help: i("Lazyload en veilige media-optimalisatie.", "ultracache-pro"),
            value: u ? i("Lazyload", "ultracache-pro") : i("Basis", "ultracache-pro"),
            state: u ? "good" : "info"
        }, {
            key: "css",
            tab: "optimization",
            label: i("CSS", "ultracache-pro"),
            help: i("Bestanden verkleinen zonder agressieve levering.", "ultracache-pro"),
            value: d ? i("Verkleind", "ultracache-pro") : i("Standaard", "ultracache-pro"),
            state: d ? "good" : "info"
        }, {
            key: "preload",
            tab: "cache",
            label: i("Vooraf opbouwen", "ultracache-pro"),
            help: i("Belangrijke pagina’s vooraf opbouwen.", "ultracache-pro"),
            value: p ? i("Aan", "ultracache-pro") : i("Uit", "ultracache-pro"),
            state: p ? "good" : "info"
        } ];
        o && m.push({
            key: "woo",
            tab: "woocommerce",
            label: i("Webshop", "ultracache-pro"),
            help: i("Winkelwagen en checkout blijven beschermd.", "ultracache-pro"),
            value: h ? i("Beschermd", "ultracache-pro") : i("Controleer", "ultracache-pro"),
            state: h ? "good" : "warning"
        });
        var dashboardGroups = Ve.dashboard || [], cwvField = We(dashboardGroups, "enable_cwv_monitoring"), rumStatus = t.rum || {}, rumSummary = rumStatus.summary || {}, rumTimeseries = rumStatus.timeseries || {}, cwvDataAvailable = Object.keys(rumSummary).length > 0 || parseInt(rumTimeseries.bucketCount || 0, 10) > 0, cwvRow = cwvField ? UCPSettingsRow({
            key: "enable_cwv_monitoring",
            title: cwvField[1],
            description: cwvField[3],
            className: "ucp-settings-row--action",
            control: a("div", {
                className: "ucp-settings-control ucp-settings-control--toggle ucp-settings-control--hide-primary-label"
            }, a(Ba, {
                field: cwvField,
                kind: "dashboard",
                settings: r,
                status: t,
                setSettings: e.setSettings,
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                hideInlineHelp: !0
            }))
        }) : null;
        var b = o ? [ {
            key: "cart",
            label: i("Winkelwagen", "ultracache-pro"),
            help: i("Winkelwagenpagina’s worden niet agressief gecachet.", "ultracache-pro")
        }, {
            key: "checkout",
            label: i("Checkout", "ultracache-pro"),
            help: i("Betaalflow blijft betrouwbaar.", "ultracache-pro")
        }, {
            key: "account",
            label: i("Account", "ultracache-pro"),
            help: i("Persoonlijke pagina’s blijven vers.", "ultracache-pro")
        }, {
            key: "payments",
            label: i("Betalingen", "ultracache-pro"),
            help: i("Betaalscripts blijven beschikbaar.", "ultracache-pro")
        } ] : [ {
            key: "forms",
            label: i("Formulieren", "ultracache-pro"),
            help: i("Interactie-elementen blijven betrouwbaar.", "ultracache-pro")
        }, {
            key: "consent",
            label: i("Consent", "ultracache-pro"),
            help: i("Cookiebanners en captcha worden niet agressief vertraagd.", "ultracache-pro")
        }, {
            key: "login",
            label: i("Beheer", "ultracache-pro"),
            help: i("Ingelogde gebruikers en builders blijven stabiel.", "ultracache-pro")
        } ];
        return a("div", {
            className: "ucp-settings-page ucp-settings-page--dashboard ucp-cache-tools-page"
        }, UCPSectionHero({
            eyebrow: i("Overzicht", "ultracache-pro"),
            title: i("Snelle acties", "ultracache-pro"),
            description: i("Vernieuw cache, start preload en controleer actieve optimalisaties.", "ultracache-pro"),
            badge: s.count ? s.label : null,
            badgeClass: "ucp-status-badge--" + s.state
        }), a(Le, Object.assign({}, e, {
            sectionKeys: [ "cache", "preload" ],
            includeImportExport: !1
        })), a("div", {
            className: "ucp-settings-stack ucp-settings-stack--dashboard-status"
        }, UCPSettingsSection({
            key: "dashboard-optimizations",
            className: "ucp-dashboard-status-section",
            title: i("Actieve optimalisaties", "ultracache-pro"),
            description: i("Compacte status van de onderdelen die direct invloed hebben.", "ultracache-pro"),
            children: m.map(function(t) {
                return UCPSettingsRow({
                    key: t.key,
                    title: t.label,
                    description: t.help,
                    className: "ucp-settings-row--action ucp-dashboard-status-row ucp-dashboard-status-row--" + D(t.key),
                    control: a("div", {
                        className: "ucp-dashboard-status-actions"
                    }, a(ee, {
                        state: t.state
                    }, t.value), e.onSelectTab ? a("a", {
                        className: "ucp-dashboard-settings-link",
                        href: UCPAdminTabUrl(t.tab),
                        "aria-label": t.label + " " + i("instellen", "ultracache-pro"),
                        onClick: function(a) {
                            a.defaultPrevented || 0 !== a.button || a.metaKey || a.ctrlKey || a.shiftKey || a.altKey || (a.preventDefault(), e.onSelectTab(t.tab));
                        }
                    }, i("Instellen", "ultracache-pro")) : null)
                });
            })
        }), cwvRow ? UCPSettingsSection({
            key: "dashboard-measurements",
            className: "ucp-dashboard-status-section ucp-dashboard-measurements-section",
            title: i("Metingen", "ultracache-pro"),
            description: i("Meet echte bezoekersprestaties alleen wanneer je deze gegevens nodig hebt.", "ultracache-pro"),
            badge: UCPSettingEnabled(r, "enable_cwv_monitoring") ? i("Actief", "ultracache-pro") : i("Uit", "ultracache-pro"),
            badgeClass: UCPSettingEnabled(r, "enable_cwv_monitoring") ? "ucp-status-badge--good" : "ucp-status-badge--neutral",
            children: [ cwvRow, cwvDataAvailable ? UCPSettingsRow({
                key: "clear-cwv-fielddata",
                title: i("Opgeslagen meetgegevens", "ultracache-pro"),
                description: i("Wis bestaande Core Web Vitals-metingen wanneer je opnieuw wilt beginnen.", "ultracache-pro"),
                className: "ucp-settings-row--action ucp-settings-row--danger",
                control: a("div", {
                    className: "ucp-settings-action-control"
                }, a(Pe, {
                    action: "clear-cwv-fielddata",
                    label: i("Meetgegevens wissen", "ultracache-pro"),
                    destructive: !0,
                    confirm: !0,
                    confirmText: i("Verwijdert opgeslagen CWV-data; nieuwe bezoeken vullen de meting opnieuw.", "ultracache-pro"),
                    addNotice: e.addNotice,
                    setStatus: e.setStatus,
                    onComplete: e.onRefresh,
                    compact: !0
                }))
            }) : null ].filter(Boolean)
        }) : null, UCPSettingsSection({
            key: "dashboard-protection",
            className: "ucp-dashboard-status-section ucp-dashboard-protection-section",
            title: i("Bescherming", "ultracache-pro"),
            description: i("UltraCache houdt gevoelige onderdelen buiten risicovolle optimalisaties.", "ultracache-pro"),
            badge: o ? h ? i("Shop beschermd", "ultracache-pro") : i("Shopcontrole nodig", "ultracache-pro") : i("Beschermd", "ultracache-pro"),
            badgeClass: o && !h ? "ucp-status-badge--warning" : "ucp-status-badge--good",
            children: b.map(function(e) {
                return UCPSettingsRow({
                    key: e.key,
                    title: e.label,
                    description: e.help,
                    className: "ucp-settings-row--action",
                    control: a(ee, {
                        state: o && !h ? "warning" : "good"
                    }, o && !h ? i("Controle nodig", "ultracache-pro") : i("Veilig", "ultracache-pro"))
                });
            })
        })));
    }
    function Se(e) {
        var titleId = (e.id || "ucp-dialog") + "-title", closeDisabledRef = UCPUseRef(!!e.closeDisabled), onCloseRef = UCPUseRef(e.onClose);
        n(function() {
            closeDisabledRef.current = !!e.closeDisabled;
            onCloseRef.current = e.onClose;
        }, [ e.closeDisabled, e.onClose ]), n(function() {
            var a = document.body.style.overflow, t = document.activeElement, dialog = e.id ? document.getElementById(e.id) : document.querySelector('.ucp-dialog[role="dialog"]');
            function c() {
                return dialog ? Array.prototype.slice.call(dialog.querySelectorAll('a[href], area[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, object, embed, [tabindex]:not([tabindex="-1"]), [contenteditable="true"]')).filter(function(e) {
                    return null !== e.offsetParent || e === document.activeElement;
                }) : [];
            }
            function o(a) {
                if ("Escape" === a.key && onCloseRef.current && !closeDisabledRef.current) a.preventDefault(), onCloseRef.current(); else if ("Tab" === a.key) {
                    var t = c();
                    if (!t.length) return a.preventDefault(), void (dialog && dialog.focus());
                    var r = t[0], n = t[t.length - 1];
                    a.shiftKey && document.activeElement === r ? (a.preventDefault(), n.focus()) : a.shiftKey || document.activeElement !== n || (a.preventDefault(), r.focus());
                }
            }
            return document.body.style.overflow = "hidden", document.addEventListener("keydown", o), window.setTimeout(function() {
                var title = document.getElementById(titleId), focusable = c();
                "title" === e.initialFocus && title ? title.focus() : focusable.length ? focusable[0].focus() : dialog && dialog.focus();
            }, 0), function() {
                document.removeEventListener("keydown", o), document.body.style.overflow = a, t && "function" == typeof t.focus && document.body.contains(t) && t.focus();
            };
        }, []), n(function() {
            if (void 0 === e.focusKey) return;
            var timer = window.setTimeout(function() {
                var title = document.getElementById(titleId);
                title && title.focus();
            }, 0);
            return function() {
                window.clearTimeout(timer);
            };
        }, [ e.focusKey ]);
        return a("div", {
            className: "ucp-dialog-layer",
            role: "presentation",
            onMouseDown: function(a) {
                a.target === a.currentTarget && e.onClose && !e.closeDisabled && e.onClose();
            }
        }, a("div", {
            id: e.id || void 0,
            className: "ucp-dialog ucp-dialog--" + (e.size || "default"),
            role: "dialog",
            "aria-modal": "true",
            "aria-labelledby": titleId,
            tabIndex: "-1",
            onMouseDown: function(e) {
                e.stopPropagation();
            }
        }, a("div", {
            className: "ucp-dialog__header"
        }, a("div", {}, e.eyebrow ? a("span", {
            className: "ucp-dialog__eyebrow"
        }, e.eyebrow) : null, a("h2", {
            id: titleId,
            tabIndex: "-1"
        }, e.title)), a(g, {
            variant: "tertiary",
            className: "ucp-dialog__close",
            disabled: !!e.closeDisabled,
            onClick: e.onClose,
            "aria-label": e.closeDisabled ? i("Sluiten niet beschikbaar tijdens verwerken", "ultracache-pro") : i("Sluiten", "ultracache-pro")
        }, a("span", {
            className: "ucp-dialog__close-glyph",
            "aria-hidden": "true"
        }, "×"))), a("div", {
            className: "ucp-dialog__body",
            tabIndex: void 0 !== e.bodyTabIndex ? e.bodyTabIndex : void 0
        }, e.children), e.footer ? a("div", {
            className: "ucp-dialog__footer"
        }, e.footer) : null));
    }
    function je() {
        return [ {
            key: "safe",
            title: i("Veilige basis", "ultracache-pro"),
            desc: i("Voor blogs, bedrijfssites en builders; stabiele optimalisaties.", "ultracache-pro"),
            meta: i("Aanbevolen", "ultracache-pro"),
            preset: "balanced"
        }, {
            key: "woo",
            title: i("Webshop", "ultracache-pro"),
            desc: i("Voor WooCommerce; cart, checkout, account en order-pay blijven beschermd.", "ultracache-pro"),
            meta: i("WooCommerce", "ultracache-pro"),
            preset: "shop"
        }, {
            key: "fast",
            title: i("Ervaren beheerder", "ultracache-pro"),
            desc: i("Toont server- en testopties voor technisch beheer.", "ultracache-pro"),
            meta: i("Eerst testen", "ultracache-pro"),
            preset: "fast"
        } ];
    }
    function ze(e) {
        return {
            enable_cache: i("Paginacache", "ultracache-pro"),
            browser_cache_headers: i("Browser-cache", "ultracache-pro"),
            enable_preload: i("Cache vooraf opbouwen", "ultracache-pro"),
            enable_preload_queue: i("Opbouwtaken verwerken", "ultracache-pro"),
            preload_homepage: i("Homepage vooraf opbouwen", "ultracache-pro"),
            enable_lazy_images: i("Afbeeldingen later laden", "ultracache-pro"),
            enable_lazy_iframes: i("Ingesloten inhoud later laden", "ultracache-pro"),
            enable_add_image_dimensions: i("Afbeeldingsdimensies toevoegen", "ultracache-pro"),
            enable_font_display_swap: i("Tekst direct zichtbaar maken", "ultracache-pro"),
            enable_css_minify: i("CSS verkleinen", "ultracache-pro"),
            enable_defer_js_fallback: i("JavaScript later uitvoeren", "ultracache-pro"),
            enable_woocommerce_rules: i("WooCommerce cache-regels", "ultracache-pro"),
            woocommerce_safety_mode: i("WooCommerce veiligheidsmodus", "ultracache-pro"),
            optimize_cart_fragments: i("Winkelwagenupdates beschermen", "ultracache-pro"),
            cache_mobile_separately: i("Mobiel apart cachen", "ultracache-pro"),
            enable_stale_cache: i("Verouderde cache tonen", "ultracache-pro")
        }[e] || e;
    }
    function Ae(e) {
        var n = r(1), c = n[0], o = n[1], profileState = r(e.status && e.status.cache && e.status.cache.wooSafety ? "woo" : "safe"), l = profileState[0], u = profileState[1], d = r(!1), p = d[0], h = d[1], wizardErrorState = r(""), wizardError = wizardErrorState[0], setWizardError = wizardErrorState[1], _ = function(e) {
            var a = je().filter(function(a) {
                return a.key === e;
            })[0] || je()[0], t = [ {
                key: "safe",
                title: i("Veilige start", "ultracache-pro"),
                help: i("Voor builders, shops en websites met veel plugins. Cache, rustig vooraf opbouwen, CSS verkleinen en media later laden staan aan; risicovollere JavaScript- en CSS-optimalisaties blijven uit.", "ultracache-pro"),
                level: i("Laag risico", "ultracache-pro"),
                bestFor: i("Builders, WooCommerce, veel plugins", "ultracache-pro"),
                values: {
                    active_preset: "safe",
                    enable_cache: 1,
                    browser_cache_headers: 1,
                    compatibility_mode: 1,
                    woocommerce_safety_mode: 1,
                    enable_preload: 1,
                    enable_preload_queue: 1,
                    preload_homepage: 1,
                    preload_sitemaps: 1,
                    preload_batch_size: 10,
                    preload_max_urls: 150,
                    preload_delay_ms: 750,
                    remove_html_comments: 1,
                    enable_html_minify: 0,
                    enable_css_minify: 1,
                    enable_css_combine: 0,
                    css_delivery_mode: "none",
                    enable_used_css: 0,
                    enable_used_css_delivery: 0,
                    enable_critical_css: 0,
                    enable_css_queue: 0,
                    enable_js_minify: 0,
                    enable_js_combine: 0,
                    enable_defer_js_fallback: 0,
                    defer_all_js: 0,
                    enable_delay_js: 0,
                    delay_js_mode: "specified",
                    delay_js_safe_mode: 1,
                    enable_lazy_images: 1,
                    lazyload_exclude_leading_images: 1,
                    enable_lazy_iframes: 0,
                    enable_lazy_youtube_preview: 0,
                    preload_critical_images: 1,
                    enable_add_image_dimensions: 1,
                    enable_local_google_fonts: 0,
                    enable_image_optimization: 0,
                    enable_webp_generation: 0,
                    enable_avif_generation: 0,
                    enable_disable_google_fonts: 0,
                    enable_font_display_swap: 1,
                    enable_prefetch_links: 0,
                    speculative_loading_mode: "core",
                    enable_speculative_loading: 0,
                    enable_lazy_render: 0,
                    enable_rest_cache: 0,
                    enable_stale_cache: 0,
                    enable_db_cleanup: 0,
                    db_cleanup_frequency: "off"
                }
            }, {
                key: "balanced",
                title: i("Gebalanceerd", "ultracache-pro"),
                help: i("Aanbevolen standaard voor de meeste websites. Cache, rustig vooraf opbouwen, CSS verkleinen, afbeeldingen later laden en WooCommerce-bescherming staan aan; risicovollere optimalisaties blijven uit.", "ultracache-pro"),
                level: i("Aanbevolen", "ultracache-pro"),
                bestFor: i("Meeste bedrijfswebsites", "ultracache-pro"),
                values: {
                    active_preset: "balanced",
                    enable_cache: 1,
                    browser_cache_headers: 1,
                    compatibility_mode: 1,
                    woocommerce_safety_mode: 1,
                    enable_preload: 1,
                    enable_preload_queue: 1,
                    preload_homepage: 1,
                    preload_sitemaps: 1,
                    preload_batch_size: 10,
                    preload_max_urls: 200,
                    preload_delay_ms: 500,
                    remove_html_comments: 1,
                    enable_html_minify: 0,
                    enable_css_minify: 1,
                    enable_css_combine: 0,
                    css_delivery_mode: "none",
                    enable_used_css: 0,
                    enable_used_css_delivery: 0,
                    enable_critical_css: 0,
                    enable_css_queue: 0,
                    enable_js_minify: 0,
                    enable_js_combine: 0,
                    enable_defer_js_fallback: 1,
                    defer_all_js: 0,
                    enable_delay_js: 0,
                    delay_js_mode: "specified",
                    delay_js_safe_mode: 1,
                    enable_lazy_images: 1,
                    lazyload_exclude_leading_images: 1,
                    enable_lazy_iframes: 0,
                    enable_lazy_youtube_preview: 0,
                    preload_critical_images: 1,
                    enable_add_image_dimensions: 1,
                    enable_local_google_fonts: 0,
                    enable_image_optimization: 0,
                    enable_webp_generation: 0,
                    enable_avif_generation: 0,
                    enable_disable_google_fonts: 0,
                    enable_font_display_swap: 1,
                    enable_prefetch_links: 0,
                    speculative_loading_mode: "core",
                    enable_speculative_loading: 0,
                    enable_lazy_render: 0,
                    enable_rest_cache: 0,
                    enable_stale_cache: 0,
                    enable_db_cleanup: 0,
                    db_cleanup_frequency: "off"
                }
            }, {
                key: "fast",
                title: i("Snelste modus", "ultracache-pro"),
                help: i("Voor een testomgeving of technische gebruikers. Houdt ongebruikte-CSS-verwijdering en uitgesteld JavaScript uit; schakel die pas bewust apart in na controle.", "ultracache-pro"),
                level: i("Testomgeving vereist", "ultracache-pro"),
                bestFor: i("Performance tuning met QA", "ultracache-pro"),
                values: {
                    active_preset: "fast",
                    enable_cache: 1,
                    browser_cache_headers: 1,
                    compatibility_mode: 0,
                    woocommerce_safety_mode: 1,
                    enable_preload: 1,
                    enable_preload_queue: 1,
                    preload_homepage: 1,
                    preload_sitemaps: 1,
                    preload_batch_size: 20,
                    preload_max_urls: 500,
                    preload_delay_ms: 350,
                    remove_html_comments: 1,
                    enable_html_minify: 1,
                    enable_css_minify: 1,
                    enable_css_combine: 0,
                    css_delivery_mode: "none",
                    enable_used_css: 0,
                    enable_used_css_delivery: 0,
                    enable_critical_css: 0,
                    enable_css_queue: 0,
                    enable_js_minify: 0,
                    enable_js_combine: 0,
                    enable_defer_js_fallback: 1,
                    defer_all_js: 0,
                    enable_delay_js: 0,
                    delay_js_mode: "specified",
                    delay_js_safe_mode: 1,
                    enable_lazy_images: 1,
                    lazyload_exclude_leading_images: 1,
                    enable_lazy_iframes: 0,
                    enable_lazy_youtube_preview: 0,
                    preload_critical_images: 1,
                    enable_add_image_dimensions: 1,
                    enable_local_google_fonts: 0,
                    enable_image_optimization: 0,
                    enable_webp_generation: 0,
                    enable_avif_generation: 0,
                    enable_disable_google_fonts: 0,
                    enable_font_display_swap: 1,
                    enable_prefetch_links: 0,
                    speculative_loading_mode: "core",
                    enable_speculative_loading: 0,
                    enable_lazy_render: 0,
                    enable_rest_cache: 0,
                    enable_stale_cache: 1,
                    enable_db_cleanup: 0,
                    db_cleanup_frequency: "off"
                }
            }, {
                key: "shop",
                title: i("Webshop veilig", "ultracache-pro"),
                help: i("Voor WooCommerce. Beschermt winkelwagen, checkout, account en dynamische verzoeken. Cache, vooraf opbouwen, CSS verkleinen, afbeeldingen later laden en font-display swap staan aan; risicovollere optimalisaties blijven uit.", "ultracache-pro"),
                level: i("Shop beschermd", "ultracache-pro"),
                bestFor: i("WooCommerce en LMS/membership", "ultracache-pro"),
                values: {
                    active_preset: "shop",
                    enable_cache: 1,
                    browser_cache_headers: 1,
                    compatibility_mode: 1,
                    woocommerce_safety_mode: 1,
                    enable_woocommerce_rules: 1,
                    enable_preload: 1,
                    enable_preload_queue: 1,
                    preload_homepage: 1,
                    preload_sitemaps: 1,
                    preload_batch_size: 10,
                    preload_max_urls: 200,
                    preload_delay_ms: 750,
                    remove_html_comments: 1,
                    enable_html_minify: 0,
                    enable_css_minify: 1,
                    enable_css_combine: 0,
                    css_delivery_mode: "none",
                    enable_used_css: 0,
                    enable_used_css_delivery: 0,
                    enable_critical_css: 0,
                    enable_css_queue: 0,
                    enable_js_minify: 0,
                    enable_js_combine: 0,
                    enable_defer_js_fallback: 0,
                    defer_all_js: 0,
                    enable_delay_js: 0,
                    delay_js_mode: "specified",
                    delay_js_safe_mode: 1,
                    enable_lazy_images: 1,
                    lazyload_exclude_leading_images: 1,
                    enable_lazy_iframes: 0,
                    enable_lazy_youtube_preview: 0,
                    preload_critical_images: 1,
                    enable_add_image_dimensions: 1,
                    enable_local_google_fonts: 0,
                    enable_image_optimization: 0,
                    enable_webp_generation: 0,
                    enable_avif_generation: 0,
                    enable_disable_google_fonts: 0,
                    enable_font_display_swap: 1,
                    enable_prefetch_links: 0,
                    speculative_loading_mode: "core",
                    enable_speculative_loading: 0,
                    enable_lazy_render: 0,
                    enable_rest_cache: 0,
                    enable_stale_cache: 0,
                    enable_db_cleanup: 0,
                    db_cleanup_frequency: "off"
                }
            } ];
            return t.filter(function(e) {
                return e.key === a.preset;
            })[0] || t[0];
        }(l), k = function(e, a) {
            var t = e && e.values ? e.values : {}, r = a || {}, n = [];
            return Object.keys(t).forEach(function(e) {
                if ("active_preset" !== e && "ui_mode" !== e && void 0 !== t[e] && ze(e) && ze(e) !== e) {
                    var a = void 0 === r[e] ? null : r[e], c = t[e];
                    if (String(a) !== String(c)) {
                        var o = /(used_css|critical_css|css_queue|delay_js|js_combine|css_combine|stale_cache|defer)/.test(e), impact = {
                            enable_cache: i("Maakt snelle openbare paginaversies en verlaagt de serverbelasting.", "ultracache-pro"),
                            browser_cache_headers: i("Laat browsers statische bestanden langer bewaren.", "ultracache-pro"),
                            enable_preload: i("Bouwt belangrijke pagina’s vooraf op na cachevernieuwing.", "ultracache-pro"),
                            enable_preload_queue: i("Verwerkt het vooraf opbouwen in beheersbare achtergrondstappen.", "ultracache-pro"),
                            preload_homepage: i("Zorgt dat de homepage direct na vernieuwing wordt opgebouwd.", "ultracache-pro"),
                            enable_lazy_images: i("Laadt afbeeldingen buiten beeld pas wanneer ze nodig zijn.", "ultracache-pro"),
                            enable_lazy_iframes: i("Laadt video’s en ingesloten inhoud later; controleer consent en interactie.", "ultracache-pro"),
                            enable_add_image_dimensions: i("Reserveert afbeeldingsruimte en beperkt layout shifts.", "ultracache-pro"),
                            enable_font_display_swap: i("Toont tekst direct terwijl het gekozen lettertype nog laadt.", "ultracache-pro"),
                            enable_css_minify: i("Verkleint CSS-bestanden zonder de laadvolgorde te wijzigen.", "ultracache-pro"),
                            enable_defer_js_fallback: i("Voert geschikte scripts later uit; test formulieren, builders en checkout.", "ultracache-pro"),
                            enable_woocommerce_rules: i("Houdt winkelwagen, checkout en account buiten gedeelde cache.", "ultracache-pro"),
                            woocommerce_safety_mode: i("Past extra bescherming toe op dynamische webshopflows.", "ultracache-pro"),
                            optimize_cart_fragments: i("Beperkt winkelwagenupdates zonder actieve winkelwagens te verstoren.", "ultracache-pro"),
                            cache_mobile_separately: i("Maakt een aparte cache voor mobiel; alleen nodig bij afwijkende mobiele HTML.", "ultracache-pro"),
                            enable_stale_cache: i("Toont tijdelijk de vorige cache tijdens de nieuwe opbouw.", "ultracache-pro")
                        }[e] || (o ? i("Kan rendering of interactie wijzigen. Test op staging.", "ultracache-pro") : i("Past een veilige basisinstelling aan.", "ultracache-pro"));
                        n.push({
                            key: e,
                            label: ze(e),
                            type: o ? "advanced" : "safe",
                            current: null === a ? i("Niet ingesteld", "ultracache-pro") : parseInt(a || 0, 10) ? i("Aan", "ultracache-pro") : i("Uit", "ultracache-pro"),
                            next: parseInt(c || 0, 10) ? i("Aan", "ultracache-pro") : i("Uit", "ultracache-pro"),
                            impact: impact
                        });
                    }
                }
            }), n.slice(0, 12);
        }(_, e.settings || {}), visibleWizardError = wizardError || e.error || "", wizardTitles = {
            1: i("Kies een profiel", "ultracache-pro"),
            2: i("Controleer de wijzigingen", "ultracache-pro")
        };
        return a(Se, {
            id: "ucp-configuratie-assistent-dialog",
            title: wizardTitles[c] || i("Start optimalisatie", "ultracache-pro"),
            eyebrow: s(i("UltraCache setup · stap %d van 2", "ultracache-pro"), c),
            onClose: e.onClose,
            closeDisabled: p || !!e.closing,
            initialFocus: "title",
            focusKey: c,
            size: "large",
            bodyTabIndex: 0,
            footer: a("div", {
                className: "ucp-dialog-footer-stack"
            }, visibleWizardError ? a("p", {
                className: "ucp-dialog-error",
                role: "alert"
            }, visibleWizardError) : null, a("div", {
                className: "ucp-modal-actions"
            }, 2 === c ? a(g, {
                variant: "tertiary",
                disabled: p || !!e.closing,
                onClick: function() {
                    setWizardError(""), o(1);
                }
            }, i("Terug", "ultracache-pro")) : null, 1 === c ? a(g, {
                variant: "tertiary",
                disabled: !!e.closing,
                onClick: e.onClose
            }, i("Overslaan", "ultracache-pro")) : null, 1 === c ? a(g, {
                variant: "primary",
                disabled: !!e.closing,
                onClick: function() {
                    setWizardError(""), o(2);
                }
            }, i("Volgende", "ultracache-pro")) : null, 2 === c ? k.length ? a(g, {
                variant: "primary",
                isBusy: p,
                disabled: p || !!e.closing,
                onClick: function() {
                    if (p || e.closing) return;
                    setWizardError(""), h(!0), function(e) {
                        return R("settings/bulk", {
                            method: "POST",
                            data: e
                        });
                    }(_.values).then(function(a) {
                        a.settings && e.setSettings(a.settings), a.status && e.setStatus(a.status), e.addNotice({
                            status: UCPS(a),
                            message: UCPM(a, i("Startprofiel toegepast.", "ultracache-pro"))
                        }), h(!1), e.onClose();
                    }).catch(function(a) {
                        setWizardError(G(a, i("Startprofiel kon niet worden toegepast.", "ultracache-pro"))), h(!1);
                    });
                }
            }, i("Bevestigen en toepassen", "ultracache-pro")) : a(g, {
                variant: "primary",
                disabled: !!e.closing,
                onClick: e.onClose
            }, i("Klaar", "ultracache-pro")) : null))
        }, a("div", {
            className: "ucp-setup-modal ucp-setup-modal--steps"
        }, 1 === c ? a(t, {}, a("p", {
            className: "ucp-dialog-intro"
        }, i("Kies een profiel. Je kunt dit later wijzigen.", "ultracache-pro")), a("div", {
            className: "ucp-preset-grid ucp-wizard-profile-grid"
        }, je().map(function(e) {
            return a("button", {
                key: e.key,
                type: "button",
                className: "ucp-wizard-profile" + (l === e.key ? " is-selected" : ""),
                disabled: p || !!e.closing,
                onClick: function() {
                    setWizardError(""), u(e.key);
                },
                "aria-pressed": l === e.key ? "true" : "false"
            }, a("span", {
                className: "ucp-wizard-profile__top"
            }, a("span", {
                className: "ucp-wizard-profile__meta"
            }, l === e.key ? i("Geselecteerd", "ultracache-pro") : e.meta)), a("strong", {}, e.title), a("span", {
                className: "ucp-wizard-profile__description"
            }, e.desc));
        }))) : a(t, {}, a("p", {
            className: "ucp-dialog-intro"
        }, i("Controleer oude waarde, nieuwe waarde en runtime-effect.", "ultracache-pro")), k.length ? a("div", {
            className: "ucp-wizard-change-list",
            role: "list"
        }, k.map(function(e) {
            return a("article", {
                className: "ucp-wizard-change-item",
                key: e.key,
                role: "listitem"
            }, a("div", {
                className: "ucp-wizard-change-item__header"
            }, a("strong", {}, e.label), a(ue, {
                type: e.type
            })), a("dl", {
                className: "ucp-wizard-change-item__values"
            }, a("div", {}, a("dt", {}, i("Nu", "ultracache-pro")), a("dd", {}, e.current)), a("div", {}, a("dt", {}, i("Wordt", "ultracache-pro")), a("dd", {}, e.next))), a("p", {
                className: "ucp-wizard-change-item__impact"
            }, e.impact));
        })) : a("div", {
            className: "ucp-wizard-empty-state",
            role: "status"
        }, a("span", {
            className: "dashicons dashicons-yes-alt",
            "aria-hidden": "true"
        }), a("div", {}, a("strong", {}, i("Geen wijzigingen nodig", "ultracache-pro")), a("p", {}, i("Je huidige instellingen komen al overeen met dit profiel.", "ultracache-pro")))), "fast" === l ? a(m, {
            status: "warning",
            isDismissible: !1
        }, i("Technische modus. Test kritieke frontendflows op staging.", "ultracache-pro")) : null)));
    }
    function UCPDatabaseCleanupPreview(e) {
        e = e || {};
        var t = Array.isArray(e.selectedOperations) ? e.selectedOperations : [], r = e.counts || {}, n = {
            db_cleanup_post_revisions: "revisions",
            db_cleanup_auto_drafts: "auto_drafts",
            db_cleanup_drafts: "drafts",
            db_cleanup_expired_transients: "expired_transients",
            db_cleanup_all_transients: "transients",
            db_cleanup_spam_comments: "spam_comments",
            db_cleanup_trashed_comments: "trash_comments",
            db_cleanup_trashed_posts: "trash_posts",
            db_cleanup_wc_sessions: "wc_sessions",
            db_cleanup_optimize_tables: "plugin_tables",
            db_cleanup_optimize_all_tables: "wordpress_tables"
        };
        return a("div", {
            className: "ucp-cleanup-preview"
        }, a("strong", {}, i("Controleer de huidige selectie", "ultracache-pro")), a("p", {}, i("Momentopname; nieuwe data kan het resultaat wijzigen.", "ultracache-pro")), t.length ? a("ul", {}, t.map(function(e) {
            var t = n[e.key], c = t && Object.prototype.hasOwnProperty.call(r, t) ? parseInt(r[t] || 0, 10) : null;
            return a("li", {
                key: e.key
            }, a("span", {}, e.label || e.key), null !== c ? a(ee, {
                state: c ? "warning" : "info"
            }, String(c)) : null);
        })) : a(m, {
            status: "warning",
            isDismissible: !1
        }, i("Geen databaseacties geselecteerd.", "ultracache-pro")), e.lastRunAt ? a("p", {
            className: "ucp-muted"
        }, i("Laatste uitvoering:", "ultracache-pro") + " " + String(e.lastRunAt)) : null);
    }
    function Pe(e) {
        var n = r(!1), c = n[0], o = n[1], s = r(!1), l = s[0], u = s[1], d = r(!1), p = d[0], h = d[1], m = r(!1), b = m[0], f = m[1], actionNoticeId = "ucp-action-" + String(e.noticeId || e.action || e.label || "action").replace(/[^a-z0-9_-]+/gi, "-").toLowerCase();
        function v() {
            if (!e.confirmBackup || p && b) {
                o(!0), u(!1), e.addNotice && e.addNotice({
                    id: actionNoticeId,
                    status: "info",
                    persistent: !0,
                    message: e.pendingMessage || i("Actie wordt uitgevoerd…", "ultracache-pro")
                });
                var a = Object.assign({}, "run-due-jobs" === e.action ? {
                    dashboard: !0,
                    maxBatches: 3
                } : {}, e.data || {}, e.confirmBackup ? {
                    confirmBackup: !0,
                    confirmIrreversible: !0
                } : {});
                UCPPostAction(e.action, a).then(function(a) {
                    e.addNotice({
                        id: actionNoticeId,
                        status: "success",
                        message: a && a.message || i("Actie uitgevoerd.", "ultracache-pro")
                    }), a && a.status && e.setStatus && e.setStatus(a.status), !1 !== e.refreshAfter && e.onComplete && e.onComplete(a);
                }).catch(function(a) {
                    e.addNotice({
                        id: actionNoticeId,
                        status: "error",
                        message: G(a, i("Actie mislukt.", "ultracache-pro"))
                    });
                }).finally(function() {
                    o(!1);
                });
            } else e.addNotice({
                status: "warning",
                message: i("Bevestig een recente back-up en de onomkeerbare uitvoering.", "ultracache-pro")
            });
        }
        var _ = a(g, {
            variant: e.variant || "secondary",
            className: "ucp-action-control",
            isDestructive: !!e.destructive,
            isBusy: c,
            disabled: c || !!e.disabled,
            accessibleWhenDisabled: !0,
            "aria-busy": c ? "true" : "false",
            onClick: function() {
                if (e.disabled) return;
                e.confirm ? (h(!1), f(!1), u(!0)) : v();
            }
        }, c ? e.busyLabel || i("Bezig…", "ultracache-pro") : e.label), k = l ? a(Se, {
            title: e.label,
            eyebrow: e.destructive ? i("Bevestigen", "ultracache-pro") : i("Actie uitvoeren", "ultracache-pro"),
            onClose: function() {
                u(!1);
            },
            footer: a("div", {
                className: "ucp-modal-actions"
            }, a(g, {
                variant: "secondary",
                onClick: function() {
                    u(!1);
                }
            }, i("Annuleren", "ultracache-pro")), a(g, {
                variant: "primary",
                isDestructive: !!e.destructive,
                disabled: !(!e.confirmBackup || p && b),
                onClick: v
            }, e.label))
        }, a("p", {
            className: "ucp-dialog-intro"
        }, e.confirmText || e.help), e.preview ? a(UCPDatabaseCleanupPreview, e.preview) : null, e.confirmBackup ? a("div", {
            className: "ucp-confirmation-checks"
        }, y ? a(y, {
            label: i("Ik heb een recente databasebackup.", "ultracache-pro"),
            checked: p,
            onChange: h
        }) : a("label", {}, a("input", {
            type: "checkbox",
            checked: p,
            onChange: function(e) {
                h(!!e.target.checked);
            }
        }), " ", i("Ik heb een recente databasebackup.", "ultracache-pro")), y ? a(y, {
            label: i("Ik begrijp dat deze actie niet ongedaan kan worden gemaakt.", "ultracache-pro"),
            checked: b,
            onChange: f
        }) : a("label", {}, a("input", {
            type: "checkbox",
            checked: b,
            onChange: function(e) {
                f(!!e.target.checked);
            }
        }), " ", i("Ik begrijp dat deze actie niet ongedaan kan worden gemaakt.", "ultracache-pro"))) : null) : null;
        return e.compact ? a(t, {}, _, k) : a("div", {
            className: "ucp-action-row"
        }, a("div", {
            className: "ucp-action-copy"
        }, a("strong", {}, e.label), e.help ? a("p", {}, e.help) : null), a("div", {
            className: "ucp-action-button"
        }, _), k);
    }
    function Ue(e) {
        var busyState = r(!1), n = busyState[0], c = busyState[1], modalState = r(!1), modalOpen = modalState[0], setModalOpen = modalState[1], o = Array.isArray(e.actions) ? e.actions : [], actionNoticeId = "ucp-action-group-" + String(e.noticeId || e.label || "action").replace(/[^a-z0-9_-]+/gi, "-").toLowerCase();
        function execute() {
            if (!o.length || n || e.disabled) return;
            setModalOpen(!1), c(!0), e.addNotice && e.addNotice({
                id: actionNoticeId,
                status: "info",
                persistent: !0,
                message: e.pendingMessage || i("Actie wordt uitgevoerd…", "ultracache-pro")
            });
            var completed = 0, lastResponse = null;
            o.reduce(function(t, r) {
                return t.then(function() {
                    var payload = "run-due-jobs" === r ? Object.assign({ dashboard: !0, maxBatches: 3 }, e.runDueJobsData || {}) : {};
                    return UCPPostAction(r, payload).then(function(t) {
                        return completed++, lastResponse = t, t && t.status && e.setStatus && e.setStatus(t.status), t;
                    });
                });
            }, Promise.resolve()).then(function() {
                var message = e.successMessage || s(i("Klaar: %d acties uitgevoerd.", "ultracache-pro"), completed);
                if (e.queueSummary && lastResponse) {
                    var processed = parseInt(lastResponse.processed || 0, 10) || 0, queuePayload = ((lastResponse.status || {}).queue || {}), queue = UCPQueueState(queuePayload.preload || queuePayload);
                    message = s(i("Vooraf opbouwen gestart. %d taken verwerkt.", "ultracache-pro"), processed);
                    queue.due > 0 && (message += " " + s(i("%d taken wachten nog en worden op de achtergrond verwerkt.", "ultracache-pro"), queue.due));
                    queue.running > 0 && (message += " " + s(i("%d taken worden nu verwerkt.", "ultracache-pro"), queue.running));
                    queue.scheduled > 0 && (message += " " + s(i("%d taken blijven gepland.", "ultracache-pro"), queue.scheduled));
                    queue.failed > 0 && (message += " " + s(i("%d taken zijn mislukt.", "ultracache-pro"), queue.failed));
                }
                e.addNotice({ id: actionNoticeId, status: "success", message: message }), e.onComplete && e.onComplete(lastResponse);
            }).catch(function(a) {
                e.addNotice({ id: actionNoticeId, status: "error", message: G(a, i("De actie mislukte. Probeer de losse stap opnieuw.", "ultracache-pro")) });
            }).finally(function() { c(!1); });
        }
        var button = a(g, {
            variant: e.variant || "secondary", className: "ucp-action-control", isBusy: n, disabled: n || !!e.disabled || !o.length, accessibleWhenDisabled: !0,
            "aria-busy": n ? "true" : "false",
            onClick: function() { e.confirm ? setModalOpen(!0) : execute(); }
        }, n ? e.busyLabel || i("Bezig…", "ultracache-pro") : e.label), modal = modalOpen ? a(Se, {
            title: e.label,
            eyebrow: i("Actie bevestigen", "ultracache-pro"),
            onClose: function() { setModalOpen(!1); },
            footer: a("div", { className: "ucp-modal-actions" }, a(g, { variant: "secondary", onClick: function() { setModalOpen(!1); } }, i("Annuleren", "ultracache-pro")), a(g, { variant: "primary", onClick: execute }, i("Doorgaan", "ultracache-pro")))
        }, a("p", {}, e.confirmText || i("Deze gecombineerde actie voert meerdere stappen uit.", "ultracache-pro"))) : null;
        return a(t, {}, button, modal);
    }
    function Le(e) {
        var t = !!e.toolsPage, r = t && UCPAdvancedSettingsVisible(e.settings || {}), explicitSupportMode = t && UCPExplicitSupportMode(), q = UCPQueueState(((e.status || {}).queue || {})), n = [ {
            key: "cache",
            title: i("Website vernieuwen", "ultracache-pro"),
            description: i("Vernieuw de snelle versie van de website na grote wijzigingen.", "ultracache-pro"),
            bulk: {
                label: i("Website vernieuwen", "ultracache-pro"),
                actions: [ "purge-all", "clear-minified-js", "refresh-css", "preload", "run-due-jobs" ],
                runDueJobsData: { jobType: "preload_url", maxBatches: 1 },
                variant: "primary",
                success: i("Cache en assets vernieuwd; preload gestart.", "ultracache-pro")
            },
            actions: [ {
                actions: [ "purge-all", "clear-minified-js", "refresh-css" ],
                label: i("Cache legen zonder opbouw", "ultracache-pro"),
                help: i("Leegt cache en vernieuwt CSS/JS zonder preload.", "ultracache-pro"),
                variant: "secondary",
                success: i("Cache, CSS en JavaScript zijn vernieuwd.", "ultracache-pro")
            }, {
                action: "purge-page-cache",
                label: i("Alleen paginacache legen", "ultracache-pro"),
                help: i("Wist alleen HTML-paginacache; CSS, JS en instellingen blijven staan.", "ultracache-pro")
            } ]
        }, {
            key: "preload",
            title: i("Pagina’s voorbereiden", "ultracache-pro"),
            description: i("Zorgt dat belangrijke pagina’s direct snel beschikbaar zijn.", "ultracache-pro"),
            bulk: {
                label: i("Pagina’s nu voorbereiden", "ultracache-pro"),
                actions: [ "preload", "run-due-jobs" ],
                runDueJobsData: { jobType: "preload_url", maxBatches: 1 },
                success: i("Vooraf opbouwen gestart en beschikbare taken verwerkt.", "ultracache-pro"),
                queueSummary: !0
            },
            actions: [ {
                action: "preload",
                label: i("Alleen opbouw starten", "ultracache-pro"),
                variant: "secondary",
                help: i("Start de opbouwwachtrij om openbare pagina’s vooraf in cache te zetten.", "ultracache-pro")
            }, {
                action: "run-due-jobs",
                data: { jobType: "preload_url", maxBatches: 1 },
                label: q.running > 0 ? i("Taken worden verwerkt", "ultracache-pro") : i("Wachtende taken verwerken", "ultracache-pro"),
                busyLabel: i("Verwerken…", "ultracache-pro"),
                pendingMessage: i("Pagina’s worden voorbereid.", "ultracache-pro"),
                refreshAfter: !1,
                disabled: q.running > 0 || q.due < 1,
                help: UCPQueueActionHelp(q),
                variant: "secondary"
            }, {
                action: "retry-failed-jobs",
                label: i("Mislukte preloadtaken opnieuw proberen", "ultracache-pro"),
                help: i("Zet alleen mislukte of opnieuw geplande preloadtaken terug in de wachtrij.", "ultracache-pro"),
                confirm: !0,
                visible: q.failed > 0
            } ].filter(function(item) { return !1 !== item.visible; })
        }, {
            key: "css",
            title: i("CSS", "ultracache-pro"),
            description: i("Herbouw of wis CSS-bestanden.", "ultracache-pro"),
            bulk: {
                label: i("CSS volledig vernieuwen", "ultracache-pro"),
                actions: [ "refresh-css" ],
                success: i("CSS-bestanden zijn vernieuwd met behoud van de gekozen leveringsmethode.", "ultracache-pro")
            },
            actions: [ {
                action: "critical-css",
                label: i("Genereer kritieke CSS", "ultracache-pro"),
                help: i("Start CSS-generatie voor de homepage.", "ultracache-pro")
            }, {
                action: "used-css",
                label: i("Used CSS opnieuw genereren", "ultracache-pro"),
                help: i("Bouwt gebruikte CSS-artifacten opnieuw op.", "ultracache-pro")
            }, {
                action: "clear-minified-css",
                label: i("CSS opnieuw opbouwen", "ultracache-pro"),
                help: i("Verwijdert de gemaakte CSS-bestanden zodat ze opnieuw worden opgebouwd.", "ultracache-pro"),
                variant: "secondary"
            } ]
        }, {
            key: "js",
            title: i("JavaScript", "ultracache-pro"),
            description: i("Wis opgebouwde JavaScript-bestanden.", "ultracache-pro"),
            actions: [ {
                action: "clear-minified-js",
                label: i("JS opnieuw opbouwen", "ultracache-pro"),
                help: i("Verwijdert opgebouwde JavaScript-bestanden.", "ultracache-pro"),
                variant: "secondary"
            } ]
        }, {
            key: "maintenance",
            title: i("Geavanceerd onderhoud", "ultracache-pro"),
            description: i("Alleen gebruiken na een recente databasebackup.", "ultracache-pro"),
            danger: !0,
            actions: [ {
                action: "database-cleanup",
                label: i("Database opschonen", "ultracache-pro"),
                help: i("Vereist een databasebackup; deze actie is onomkeerbaar.", "ultracache-pro"),
                variant: "secondary",
                destructive: !0,
                confirm: !0,
                confirmBackup: !0
            } ]
        }, {
            key: "support-mode",
            title: i("Tijdelijke supportmodus", "ultracache-pro"),
            description: i("Alleen in supportmodus. Gebruik tijdelijk voor herstel en diagnose.", "ultracache-pro"),
            supportOnly: !0,
            actions: [ {
                action: "enable-debug-mode",
                label: i("Testmodus 30 min", "ultracache-pro"),
                help: i("Activeert logging, runtimeheaders, diagnostiek en dashboardrunner.", "ultracache-pro"),
                confirm: !0
            }, {
                action: "repair-cache-files",
                label: i("Cachebestanden herstellen", "ultracache-pro"),
                help: i("Herstelt WP_CACHE en de UltraCache drop-in; externe drop-ins blijven intact.", "ultracache-pro"),
                confirm: !0
            } ]
        } ], c = Array.isArray(e.sectionKeys) ? n.filter(function(t) {
            return (!t.supportOnly || explicitSupportMode) && -1 !== e.sectionKeys.indexOf(t.key);
        }) : t && !r ? n.filter(function(e) {
            return -1 !== [ "cache", "preload" ].indexOf(e.key);
        }) : n.filter(function(section) {
            return !section.supportOnly || explicitSupportMode;
        });
        t || (c = (Array.isArray(e.sectionKeys) ? c : n.filter(function(section) {
            return "cache" === section.key;
        })).map(function(section) {
            return "cache" !== section.key ? section : Object.assign({}, section, {
                title: i("Cache vernieuwen", "ultracache-pro"),
                description: i("Leegt cache en assets en start preload van kernpagina’s.", "ultracache-pro"),
                actions: (section.actions || []).slice(0, 1)
            });
        }));
        function renderToolSection(section, flat) {
            var headerAction = section.bulk ? a(Ue, {
                label: section.bulk.label,
                actions: section.bulk.actions,
                successMessage: section.bulk.success,
                pendingMessage: section.bulk.pendingMessage,
                busyLabel: section.bulk.busyLabel,
                queueSummary: !!section.bulk.queueSummary,
                runDueJobsData: section.bulk.runDueJobsData || {},
                variant: section.bulk.variant || "secondary",
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                onComplete: e.onRefresh
            }) : null, rows = (section.actions || []).map(function(actionItem) {
                var control;
                return control = Array.isArray(actionItem.actions) ? a(Ue, {
                    label: actionItem.label,
                    actions: actionItem.actions,
                    successMessage: actionItem.success,
                    variant: actionItem.variant || "secondary",
                    confirm: !!actionItem.confirm,
                    confirmText: actionItem.help,
                    disabled: !!actionItem.disabled,
                    addNotice: e.addNotice,
                    setStatus: e.setStatus,
                    onComplete: e.onRefresh
                }) : a(Pe, {
                    action: actionItem.action,
                    data: actionItem.data || {},
                    label: actionItem.label,
                    help: actionItem.help,
                    busyLabel: actionItem.busyLabel,
                    pendingMessage: actionItem.pendingMessage,
                    refreshAfter: !1 !== actionItem.refreshAfter,
                    variant: actionItem.variant || "secondary",
                    destructive: !!actionItem.destructive,
                    confirm: !!actionItem.confirm,
                    confirmText: actionItem.help,
                    confirmBackup: !!actionItem.confirmBackup,
                    disabled: !!actionItem.disabled,
                    preview: "database-cleanup" === actionItem.action ? (e.status || {}).databaseCleanup || {} : null,
                    addNotice: e.addNotice,
                    setStatus: e.setStatus,
                    onComplete: e.onRefresh,
                    compact: !0
                }), UCPSettingsRow({
                    key: actionItem.action || actionItem.label,
                    title: actionItem.label,
                    description: actionItem.help,
                    className: "ucp-settings-row--action" + (actionItem.destructive ? " ucp-settings-row--danger" : ""),
                    control: a("div", {
                        className: "ucp-settings-action-control"
                    }, control)
                });
            });
            if (flat) return a("section", {
                key: section.key,
                className: "ucp-tool-technical-group ucp-tool-technical-group--" + section.key + (section.danger ? " is-danger" : "")
            }, a("header", {
                className: "ucp-tool-technical-group__header"
            }, a("div", {
                className: "ucp-tool-technical-group__copy"
            }, a("h3", {}, section.title), section.description ? a("p", {}, section.description) : null), headerAction ? a("div", {
                className: "ucp-tool-technical-group__action"
            }, headerAction) : null), rows.length ? a("div", {
                className: "ucp-settings-list"
            }, rows) : null);
            return UCPSettingsSection({
                key: section.key,
                title: section.title,
                description: section.description,
                headerAction: headerAction,
                hideBody: !rows.length,
                className: (section.danger ? "ucp-settings-section--danger " : "") + "ucp-settings-section--tool-" + section.key + (!rows.length ? " ucp-settings-section--action-only" : ""),
                children: rows
            });
        }
        function renderSimpleToolAction(item) {
            var buttonLabel = item.buttonLabel || item.title, control = item.actions ? a(Ue, {
                label: buttonLabel,
                actions: item.actions,
                successMessage: item.success,
                variant: item.variant || "secondary",
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                onComplete: e.onRefresh
            }) : a(Pe, {
                action: item.action,
                label: buttonLabel,
                help: item.help,
                variant: item.variant || "secondary",
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                onComplete: e.onRefresh,
                compact: !0
            });
            return UCPSettingsRow({
                key: item.key,
                title: item.title,
                description: item.help,
                className: "ucp-settings-row--action ucp-tool-simple-action",
                control: a("div", {
                    className: "ucp-settings-action-control"
                }, control)
            });
        }
        var primarySections = t ? c.filter(function(section) {
            return -1 !== [ "cache", "preload" ].indexOf(section.key);
        }) : c.map(function(section) {
            return Object.assign({}, section, { actions: [] });
        }), simpleToolActions = [], technicalSections = t && (r || explicitSupportMode) ? n.filter(function(section) {
            return -1 !== [ "css", "js", "support-mode" ].indexOf(section.key) && (!section.supportOnly || explicitSupportMode);
        }).map(function(section) {
            if ("css" === section.key) return Object.assign({}, section, {
                bulk: null
            });
            return section;
        }) : [];
        return a("div", {
            className: "ucp-settings-stack ucp-settings-stack--tools"
        }, primarySections.map(renderToolSection), t && simpleToolActions.length ? a("div", {
            className: "ucp-tool-simple-actions"
        }, simpleToolActions.map(renderSimpleToolAction)) : null, technicalSections.length ? a("details", {
            className: "ucp-settings-advanced ucp-tool-technical-details"
        }, a("summary", {}, a("span", {
            className: "ucp-advanced-summary-copy"
        }, a("strong", {}, i("Technische herstelacties", "ultracache-pro")), a("small", {}, i("Alleen voor technisch herstel.", "ultracache-pro")))), a("div", {
            className: "ucp-settings-stack ucp-settings-stack--tools ucp-settings-stack--tool-actions"
        }, technicalSections.map(function(section) { return renderToolSection(section, !0); }))) : null, t && !1 !== e.includeImportExport ? a(Be, e) : null);
    }
    function Be(e) {
        var busyState = r(!1), busy = busyState[0], setBusy = busyState[1], previewState = r(null), preview = previewState[0], setPreview = previewState[1], backupState = r(!1), backupConfirmed = backupState[0], setBackupConfirmed = backupState[1];
        function normalizePayload(payload) {
            if (!payload || "object" != typeof payload || Array.isArray(payload)) return null;
            var settings = payload.settings && "object" == typeof payload.settings && !Array.isArray(payload.settings) ? payload.settings : payload;
            return { settings: settings, meta: payload.meta || {} };
        }
        function buildPreview(payload, fileName) {
            var normalized = normalizePayload(payload);
            if (!normalized) return null;
            var current = e.settings || {}, keys = Object.keys(normalized.settings || {}), known = keys.filter(function(key) { return Object.prototype.hasOwnProperty.call(current, key); }), unknown = keys.filter(function(key) { return !Object.prototype.hasOwnProperty.call(current, key); }), recognizedSettings = {}, changed = known.filter(function(key) { return JSON.stringify(current[key]) !== JSON.stringify(normalized.settings[key]); }), risky = changed.filter(function(key) { return /^(?:db_cleanup_|enable_object_cache|enable_redis|enable_apcu|cdn_|cloudflare_|bunny_|renderer_|enable_.*(?:combine|delay|async|unused))/i.test(key); });
            known.forEach(function(key) { recognizedSettings[key] = normalized.settings[key]; });
            return { payload: normalized, recognizedSettings: recognizedSettings, fileName: fileName, recognized: known.length, unknown: unknown, changed: changed, risky: risky, schemaMismatch: !!(normalized.meta.schema && "ultracache-settings-export-v1" !== normalized.meta.schema) };
        }
        function importSettings() {
            if (!preview || !backupConfirmed) return;
            setBusy(!0);
            R("settings/import", { method: "POST", data: { settings: preview.recognizedSettings, confirmBackup: !0 } }).then(function(response) {
                e.setSettings(response.settings), response.status && e.setStatus(response.status), e.addNotice({ status: "success", message: s(i("Instellingen geïmporteerd: %d waarden bijgewerkt.", "ultracache-pro"), preview.changed.length) }), setPreview(null);
            }).catch(function(error) { e.addNotice({ status: "error", message: G(error, i("Import mislukt.", "ultracache-pro")) }); }).finally(function() { setBusy(!1); });
        }
        var modal = preview ? a(Se, {
            title: i("Import controleren", "ultracache-pro"), eyebrow: i("Voorbeeld vóór wijzigen", "ultracache-pro"), closeDisabled: busy, initialFocus: "title", onClose: function() { if (!busy) { setPreview(null); setBackupConfirmed(!1); } },
            footer: a("div", { className: "ucp-modal-actions" }, a(g, { variant: "secondary", disabled: busy, onClick: function() { setPreview(null); setBackupConfirmed(!1); } }, i("Annuleren", "ultracache-pro")), a(g, { variant: "primary", isBusy: busy, disabled: busy || !preview.recognized || !backupConfirmed, onClick: importSettings }, busy ? i("Importeren…", "ultracache-pro") : i("Import bevestigen", "ultracache-pro")))
        }, a("div", { className: "ucp-import-preview" }, a("p", {}, a("strong", {}, preview.fileName)), a("dl", {}, a("div", {}, a("dt", {}, i("Herkende instellingen", "ultracache-pro")), a("dd", {}, String(preview.recognized))), a("div", {}, a("dt", {}, i("Wijzigingen", "ultracache-pro")), a("dd", {}, String(preview.changed.length))), a("div", {}, a("dt", {}, i("Risicovolle wijzigingen", "ultracache-pro")), a("dd", {}, String(preview.risky.length))), a("div", {}, a("dt", {}, i("Onbekende sleutels", "ultracache-pro")), a("dd", {}, String(preview.unknown.length))), a("div", {}, a("dt", {}, i("Exportversie", "ultracache-pro")), a("dd", {}, String(preview.payload.meta.pluginVersion || i("Onbekend", "ultracache-pro"))))), preview.schemaMismatch ? a(m, { status: "warning", isDismissible: !1 }, i("Onbekend exportschema; alleen herkende instellingen worden getoond.", "ultracache-pro")) : null, preview.unknown.length ? a(m, { status: "warning", isDismissible: !1 }, s(i("%d onbekende sleutels worden genegeerd.", "ultracache-pro"), preview.unknown.length), a("code", { className: "ucp-import-preview__keys" }, preview.unknown.slice(0, 5).join("\n"))) : null, preview.risky.length ? a(m, { status: "warning", isDismissible: !1 }, s(i("%d wijzigingen raken server- of geavanceerde instellingen. Test op staging.", "ultracache-pro"), preview.risky.length)) : null, y ? a(y, { label: i("Ik heb eerst een actuele instellingenexport of databaseback-up gemaakt.", "ultracache-pro"), checked: backupConfirmed, onChange: setBackupConfirmed }) : a("label", { className: "ucp-import-preview__confirm" }, a("input", { type: "checkbox", checked: backupConfirmed, onChange: function(event) { setBackupConfirmed(!!event.target.checked); } }), " ", i("Ik heb eerst een actuele instellingenexport of databaseback-up gemaakt.", "ultracache-pro")))) : null;
        var pluginGroups = Ve.plugin || [], advancedGroups = Ve.advanced || [], pluginDefinitions = [ {
            key: "show_advanced_options",
            groups: pluginGroups,
            kind: "plugin",
            title: i("Geavanceerde instellingen tonen", "ultracache-pro"),
            description: i("Toont technische opties.", "ultracache-pro")
        }, {
            key: "bloat_removal_mode",
            groups: advancedGroups,
            kind: "advanced",
            title: i("WordPress-overhead beperken", "ultracache-pro"),
            description: i("Schakelt overbodige WordPress-functies uit. Test de agressieve stand eerst op staging.", "ultracache-pro")
        }, {
            key: "clean_uninstall",
            groups: pluginGroups,
            kind: "plugin",
            title: i("Plugininstellingen verwijderen", "ultracache-pro"),
            description: i("Verwijdert alle UltraCache-instellingen wanneer je de plugin verwijdert.", "ultracache-pro")
        } ], pluginRows = pluginDefinitions.map(function(item) {
            var settingKey = item.key, field = We(item.groups, settingKey);
            if (!field) return null;
            var definition = field.slice(), type = definition[2] || "text";
            return UCPSettingsRow({
                key: settingKey,
                title: item.title || definition[1],
                description: item.description || definition[3] || "",
                className: UCPSettingsRowClass(settingKey, type, "ucp-plugin-management-row" + ("clean_uninstall" === settingKey ? " ucp-settings-row--danger" : "")),
                control: a("div", {
                    className: "ucp-settings-control ucp-settings-control--" + type + " ucp-settings-control--hide-primary-label"
                }, a(Ba, {
                    field: definition,
                    kind: item.kind,
                    settings: e.settings,
                    status: e.status || {},
                    setSettings: e.setSettings,
                    addNotice: e.addNotice,
                    setStatus: e.setStatus,
                    hideInlineHelp: !0
                }))
            });
        }).filter(Boolean), pluginManagement = pluginRows.length ? a("details", {
            className: "ucp-settings-advanced ucp-plugin-management"
        }, a("summary", {}, a("span", {
            className: "ucp-advanced-summary-copy"
        }, a("strong", {}, i("Pluginbeheer", "ultracache-pro")), a("small", {}, i("Weergave, beheerinterface en instellingen bij verwijderen.", "ultracache-pro")))), a("div", {
            className: "ucp-settings-advanced__body"
        }, a("div", {
            className: "ucp-settings-list ucp-hybrid-settings-grid"
        }, pluginRows))) : null;
        return a(t, {}, UCPSettingsSection({
            key: "tools-import-export", title: i("Import / export", "ultracache-pro"), description: i("Exporteer eerst een backup; import toont een diff vóór toepassing.", "ultracache-pro"), className: "ucp-settings-section--wide ucp-settings-section--import-export", hideHeader: !!e.embedded,
            children: [ UCPSettingsRow({
                key: "tools-export", title: i("Instellingen exporteren", "ultracache-pro"), description: i("Exporteert instellingen en versiegegevens als JSON-backup.", "ultracache-pro"), className: "ucp-settings-row--action",
                control: a("div", { className: "ucp-settings-action-control" }, a(g, { variant: "secondary", className: "ucp-transfer-button", isBusy: busy, disabled: busy, accessibleWhenDisabled: !0, onClick: function() {
                    setBusy(!0), R("settings/export").then(function(response) {
                        var contents = JSON.stringify({ settings: response.settings || {}, meta: response.meta || {} }, null, 2), blob = new Blob([ contents ], { type: "application/json;charset=utf-8" }), url = URL.createObjectURL(blob), link = document.createElement("a");
                        link.href = url, link.download = "ultracache-settings.json", document.body.appendChild(link), link.click(), link.remove(), window.setTimeout(function() { URL.revokeObjectURL(url); }, 1e3);
                        e.addNotice({ status: "success", message: i("Instellingen geëxporteerd.", "ultracache-pro") });
                    }).catch(function(error) { e.addNotice({ status: "error", message: G(error, i("Export mislukt.", "ultracache-pro")) }); }).finally(function() { setBusy(!1); });
                } }, i("Exporteren", "ultracache-pro")))
            }), UCPSettingsRow({
                key: "tools-import", title: i("Instellingen importeren", "ultracache-pro"), description: i("Kies een UltraCache-JSON; controleer de preview vóór import.", "ultracache-pro"), className: "ucp-settings-row--action",
                control: a("div", { className: "ucp-settings-action-control" }, a(g, {
                    variant: "primary",
                    className: "ucp-transfer-button",
                    isBusy: busy,
                    disabled: busy,
                    accessibleWhenDisabled: !0,
                    onClick: function() {
                        var input = document.getElementById("ucp-settings-import-file");
                        input && input.click();
                    }
                }, busy ? i("Bezig…", "ultracache-pro") : i("JSON kiezen", "ultracache-pro")), a("input", { id: "ucp-settings-import-file", className: "screen-reader-text", type: "file", accept: ".json,application/json", tabIndex: -1, "aria-label": i("JSON kiezen", "ultracache-pro"), disabled: busy, onChange: function(event) {
                    var file = event && event.target && event.target.files ? event.target.files[0] : null;
                    if (!file) return;
                    if (!/\.json$/i.test(file.name || "")) return e.addNotice({ status: "error", message: i("Kies een .json-bestand.", "ultracache-pro") }), void (event.target.value = "");
                    if (file.size && file.size > 262144) return e.addNotice({ status: "error", message: i("Het JSON-bestand is te groot. Maximale grootte is 256 KB.", "ultracache-pro") }), void (event.target.value = "");
                    var reader = new FileReader;
                    reader.onload = function() { var payload; try { payload = JSON.parse(String(reader.result || "")); } catch (error) { return e.addNotice({ status: "error", message: i("Ongeldige JSON in het gekozen bestand.", "ultracache-pro") }), void (event.target.value = ""); } var nextPreview = buildPreview(payload, file.name || "ultracache-settings.json"); nextPreview && nextPreview.recognized ? (setBackupConfirmed(!1), setPreview(nextPreview)) : e.addNotice({ status: "error", message: i("Geen geldige UltraCache-instellingen gevonden.", "ultracache-pro") }), event.target.value = ""; }, reader.onerror = function() { e.addNotice({ status: "error", message: i("Het JSON-bestand kon niet worden gelezen.", "ultracache-pro") }), event.target.value = ""; }, reader.readAsText(file);
                } }))
            }) ]
        }), pluginManagement, modal);
    }
    function UCPTranslateAdminSchema(schema) {
        var translated = {};
        Object.keys(schema || {}).forEach(function(sectionKey) {
            translated[sectionKey] = (schema[sectionKey] || []).map(function(group) {
                var nextGroup = Object.assign({}, group || {});
                nextGroup.title = group && group.title ? String(group.title) : "";
                nextGroup.fields = (group && group.fields || []).map(function(field) {
                    if (!Array.isArray(field)) return field;
                    var nextField = field.slice();
                    nextField[1] = i(String(field[1] || field[0] || ""), "ultracache-pro");
                    nextField[3] = field[3] ? i(String(field[3]), "ultracache-pro") : "";
                    if (Array.isArray(field[4])) {
                        nextField[4] = field[4].map(function(option) {
                            if (!Array.isArray(option)) return option;
                            var nextOption = option.slice();
                            nextOption[1] = i(String(option[1] || option[0] || ""), "ultracache-pro");
                            return nextOption;
                        });
                    }
                    return nextField;
                });
                return nextGroup;
            });
        });
        return translated;
    }
    function UCPGroupTitle(group) {
        return i(String(group && group.title || ""), "ultracache-pro");
    }
    var Ve = UCPTranslateAdminSchema(window.UCP_REACT_ADMIN_SCHEMA || {});
    function UCPSettingLabelMap() {
        var labels = {};
        Object.keys(Ve || {}).forEach(function(sectionKey) {
            (Ve[sectionKey] || []).forEach(function(group) {
                (group && group.fields || []).forEach(function(field) {
                    if (Array.isArray(field) && field[0]) labels[String(field[0])] = String(field[1] || field[0]);
                });
            });
        });
        return labels;
    }
    var UCPSettingLabels = UCPSettingLabelMap();
    function UCPSnapshotChangeLabel(snapshot) {
        var keys = snapshot && Array.isArray(snapshot.changedKeys) ? snapshot.changedKeys : [];
        var labels = keys.slice(0, 3).map(function(key) {
            return UCPSettingLabels[String(key)] || String(key || "").replace(/_/g, " ").replace(/(^|\s)\S/g, function(letter) { return letter.toUpperCase(); });
        }).filter(Boolean);
        if (labels.length) {
            return i("Gewijzigd:", "ultracache-pro") + " " + labels.join(" · ") + (keys.length > 3 ? " +" + String(keys.length - 3) : "");
        }
        var count = parseInt(snapshot && snapshot.changedCount || 0, 10);
        return count ? s(1 === count ? i("%d instelling gewijzigd", "ultracache-pro") : i("%d instellingen gewijzigd", "ultracache-pro"), count) : i("Geen verschil met de huidige instellingen", "ultracache-pro");
    }
    function qe(e) {
        var t = function(e) {
            var a = {
                cache: {
                    title: i("Cache overzicht", "ultracache-pro"),
                    text: i("Configureer page cache, TTL en cache-invalidatie.", "ultracache-pro"),
                    steps: [ i("Paginacache", "ultracache-pro"), i("Bewaartijd", "ultracache-pro"), i("Varianten en purge", "ultracache-pro") ]
                },
                optimization: {
                    title: i("Optimalisatie overzicht", "ultracache-pro"),
                    text: i("Configureer HTML, CSS en JavaScript. Test risicovolle modi op staging.", "ultracache-pro"),
                    steps: [ i("1. Kies eerst een preset", "ultracache-pro"), i("2. Controleer HTML en CSS", "ultracache-pro"), i("3. Test JavaScript apart", "ultracache-pro") ]
                },
                media: {
                    title: i("Media & lettertypen overzicht", "ultracache-pro"),
                    text: i("Controleer de status en open alleen het relevante onderdeel.", "ultracache-pro"),
                    steps: [ i("Nieuwe afbeeldingen", "ultracache-pro"), i("Afbeeldingen laden", "ultracache-pro"), i("Lettertypen", "ultracache-pro"), i("Compatibiliteit", "ultracache-pro") ]
                },
                preload: {
                    title: i("Vooraf opbouwen: overzicht", "ultracache-pro"),
                    text: i("Configureer preloadbron, batchgrootte, pauze en wachtrij.", "ultracache-pro"),
                    steps: [ i("Cache vooraf opbouwen", "ultracache-pro"), i("Link-prefetch", "ultracache-pro"), i("Uitsluitingen", "ultracache-pro"), i("Serverbelasting", "ultracache-pro") ]
                },
                advanced: {
                    title: i("Regels overzicht", "ultracache-pro"),
                    text: i("Beheer cache-, preload- en browsernavigatie-uitsluitingen.", "ultracache-pro"),
                    steps: [ i("Pagina’s", "ultracache-pro"), i("Cookies en agents", "ultracache-pro"), i("Query strings", "ultracache-pro") ]
                },
                database: {
                    title: i("Database onderhoud", "ultracache-pro"),
                    text: i("Selecteer acties expliciet; destructieve acties vereisen backupbevestiging.", "ultracache-pro"),
                    steps: [ i("Veilig eerst", "ultracache-pro"), i("Berichten", "ultracache-pro"), i("Reacties", "ultracache-pro"), i("Transients", "ultracache-pro"), i("Tabellen", "ultracache-pro") ]
                }
            };
            return a[e] || a.optimization;
        }(e.kind);
        return a(d, {
            className: "ucp-card ucp-settings-intro"
        }, a(h, {}, a("span", {
            className: "ucp-eyebrow"
        }, i("Overzicht", "ultracache-pro")), a("h2", {}, t.title), a("p", {}, t.text), a("div", {
            className: "ucp-step-list"
        }, (t.steps || []).map(function(e) {
            return a("span", {
                key: e
            }, e);
        }))));
    }
    function We(e, a) {
        var t = null;
        return (e || []).some(function(e) {
            return (e.fields || []).some(function(e) {
                return e[0] === a && (t = e, !0);
            });
        }), t;
    }
    function UCPSettingEnabled(e, a) {
        return !!parseInt(xa(a, e || {}) || 0, 10);
    }
    function UCPAdvancedSettingsVisible(e) {
        e = e || {};
        return UCPExplicitSupportMode() || "advanced" === String(e.ui_mode || "") || UCPSettingEnabled(e, "show_advanced_options");
    }
    function UCPExplicitSupportMode(status) {
        status = status || UCPRuntimeStatus || {};
        var quality = status.quality || {}, support = quality.supportMode || {}, until = parseInt(support.until || quality.debugUntil || 0, 10);
        return !!(window.location && /(?:[?&])ucp_support=1(?:&|$)/.test(window.location.search || "")) || !!support.active || until > Math.floor(Date.now() / 1e3);
    }
    function UCPShouldRenderSetting(e, a, status) {
        if (!e) return !0;
        if (!UCPExplicitSupportMode(status) && -1 !== UCPManagedSettingKeys.indexOf(e)) return !1;
        a = a || {}, status = status || UCPRuntimeStatus || {};
        var capabilities = (status.system || {}).capabilities || {};
        if ("enable_brotli_precompression" === e && !capabilities.brotliPrecompression && !UCPSettingEnabled(a, e)) return !1;
        if ("enable_gzip_precompression" === e && !capabilities.gzipPrecompression && !UCPSettingEnabled(a, e)) return !1;
        if ("enable_redis_object_cache" === e && !capabilities.redis && !UCPSettingEnabled(a, e)) return !1;
        if ("enable_apcu_object_cache" === e && !capabilities.apcu && !UCPSettingEnabled(a, e)) return !1;
        if ("allow_wp_config_write" === e && !capabilities.wpConfigWritable && !UCPSettingEnabled(a, e)) return !1;
        if ("allow_dropin_writes" === e && !capabilities.dropinWritable && !UCPSettingEnabled(a, e)) return !1;
        if ("allow_browser_cache_rule_writes" === e && !capabilities.browserCacheRuleWrites && !UCPSettingEnabled(a, e)) return !1;
        var t = String(xa("google_fonts_mode", a) || "standard"),
            m = String(xa("image_optimization_mode", a) || "off"),
            r = String(xa("query_string_cache_mode", a) || "off"),
            n = String(xa("browser_cache_mode", a) || "off"),
            c = String(xa("cdn_rewrite_mode", a) || "off"),
            o = String(xa("cdn_provider", a) || "none"),
            i = String(xa("image_cdn_transform_provider", a) || "auto"),
            l = String(xa("media_lazyload_mode", a) || "off"),
            s = {
                enable_auto_font_preloads: !0,
                preload_fonts: UCPSettingEnabled(a, "enable_auto_font_preloads"),
                image_quality: "webp" === m || "webp_avif" === m,
                enable_font_unicode_ranges: "local" === t,
                font_unicode_ranges: "local" === t && UCPSettingEnabled(a, "enable_font_unicode_ranges"),
                lazyload_exclusions: "off" !== l,
                lazy_render_selectors: UCPSettingEnabled(a, "enable_lazy_render"),
                cache_query_string_inclusions: "allow_list" === r,
                cache_control_max_age: "custom" === n,
                db_keep_post_revisions: UCPSettingEnabled(a, "db_cleanup_post_revisions"),
                db_cleanup_wc_sessions: !!((status.cache || {}).woocommerceActive || (status.detected || {}).woocommerceActive),
                headless_renderer_endpoint: UCPSettingEnabled(a, "enable_headless_renderer"),
                headless_renderer_token: UCPSettingEnabled(a, "enable_headless_renderer"),
                cdn_cnames: "off" !== c,
                cdn_exclude: "off" !== c,
                cloudflare_zone_id: "cloudflare" === o,
                cloudflare_api_token: "cloudflare" === o,
                bunny_pull_zone_id: "bunny" === o,
                bunny_api_key: "bunny" === o,
                cdn_purge_webhook: "generic" === o,
                cdn_purge_webhook_token: "generic" === o,
                compat_update_url: UCPSettingEnabled(a, "enable_compat_updates"),
                image_cdn_base: UCPSettingEnabled(a, "enable_image_cdn"),
                image_cdn_query: UCPSettingEnabled(a, "enable_image_cdn") && "generic" === i,
                image_cdn_transform_provider: UCPSettingEnabled(a, "enable_image_cdn"),
                enable_image_cdn_transforms: UCPSettingEnabled(a, "enable_image_cdn"),
                image_cdn_widths: UCPSettingEnabled(a, "enable_image_cdn") && UCPSettingEnabled(a, "enable_image_cdn_transforms"),
                enable_adaptive_image_srcset: UCPSettingEnabled(a, "enable_image_cdn") && UCPSettingEnabled(a, "enable_image_cdn_transforms")
            };
        return !Object.prototype.hasOwnProperty.call(s, e) || !!s[e];
    }
    function Me(e) {
        var a = e && e.cache || {}, t = e && e.detected || {};
        return !!(a.woocommerceActive || t.woocommerceActive || t.hasWooCommerceActive);
    }
    function Re(e) {
        var cacheGroups = Ve.cache || [], preloadGroups = Ve.preload || [], settings = e.settings || {}, status = e.status || {}, advancedVisible = UCPAdvancedSettingsVisible(settings), wooActive = Me(status), queuePayload = status.queue || {}, queueState = UCPQueueState(queuePayload.preload || queuePayload);
        n(function() {
            if (!queueState.count || !e.setStatus) return;
            var active = !0, timer = null, attempts = 0;
            function refreshQueueStatus() {
                R("status").then(function(response) {
                    active && response && response.status && e.setStatus(response.status);
                }).catch(function() {}).finally(function() {
                    attempts += 1;
                    active && attempts < 12 && (timer = window.setTimeout(refreshQueueStatus, 5e3));
                });
            }
            return timer = window.setTimeout(refreshQueueStatus, 1500), function() {
                active = !1, timer && window.clearTimeout(timer);
            };
        }, [ queueState.count ]);
        function definition(groups, key) {
            var standardField = null, advancedField = null;
            (groups || []).forEach(function(group) {
                (group.fields || []).forEach(function(field) {
                    if (field[0] !== key) return;
                    group.advanced ? advancedField = field : standardField || (standardField = field);
                });
            });
            if ("speculative_loading_mode" === key && "prerender" === String(xa(key, settings) || "") && advancedField) return advancedField;
            return advancedVisible && advancedField ? advancedField : standardField || advancedField;
        }
        function control(kind, groups, key, extraClass, overrides) {
            var field = definition(groups, key);
            if (!field) return null;
            field = field.slice(), (overrides = overrides || {}).label && (field[1] = overrides.label), void 0 !== overrides.help && (field[3] = overrides.help), overrides.type && (field[2] = overrides.type), overrides.options && (field[4] = overrides.options);
            var type = field[2] || "field", className = "ucp-settings-control ucp-settings-control--" + type + " ucp-settings-control--hide-primary-label";
            return "toggle" === type && (className += " ucp-settings-control--toggle"), "textarea" === type && (className += " ucp-settings-control--wide"), extraClass && (className += " " + extraClass), a("div", {
                className: className
            }, a(Ba, {
                key: field[0],
                field: field,
                kind: kind,
                settings: settings,
                status: status,
                setSettings: e.setSettings,
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                hideInlineHelp: !0
            }));
        }
        function row(kind, groups, key, options) {
            var field = definition(groups, key);
            if (!field || !UCPShouldRenderSetting(key, settings, status)) return null;
            options = options || {};
            var controlType = options.field && options.field.type ? options.field.type : field[2] || "field";
            return UCPSettingsRow({
                key: key,
                title: options.title || field[1],
                description: void 0 !== options.description ? options.description : field[3],
                className: UCPSettingsRowClass(key, controlType, options.className || ""),
                controlClassName: options.controlClassName || "",
                control: control(kind, groups, key, options.controlClassName || "", options.field || null)
            });
        }
        function fieldGroup(title, description, fields) {
            return UCPSettingsRow({
                key: "cache-refresh-settings",
                title: title,
                description: description,
                fields: !0,
                className: "ucp-cache-refresh-row",
                controlClassName: "ucp-settings-row__fields--two",
                control: fields.map(function(item) {
                    var key = item[0], overrides = item[1] || {}, field = definition(cacheGroups, key);
                    if (!field) return null;
                    return a("div", {
                        key: key,
                        className: "ucp-settings-field-pair"
                    }, a("span", {
                        className: "ucp-settings-field-label"
                    }, overrides.label || field[1]), control("cache", cacheGroups, key, "ucp-settings-control--boxed", overrides));
                }).filter(Boolean)
            });
        }
        function panel(key, title, description, badge, badgeClass, children, defaultOpen, extraClass) {
            return a("details", {
                key: key,
                className: "ucp-media-panel ucp-cache-panel ucp-cache-panel--" + D(key) + (extraClass ? " " + extraClass : ""),
                open: defaultOpen ? !0 : void 0
            }, a("summary", {
                className: "ucp-media-panel__summary ucp-cache-panel__summary"
            }, a("span", {
                className: "ucp-media-panel__copy ucp-cache-panel__copy"
            }, a("strong", {}, title), description ? a("small", {}, description) : null), badge ? a("span", {
                className: "ucp-status-badge " + (badgeClass || "ucp-status-badge--neutral")
            }, badge) : null), a("div", {
                className: "ucp-media-panel__body ucp-cache-panel__body"
            }, a("div", {
                className: "ucp-settings-list"
            }, (children || []).filter(Boolean))));
        }
        var cacheEnabled = UCPSettingEnabled(settings, "enable_cache"), cacheAdvancedVisible = advancedVisible || [ "enable_esi", "enable_prefetch_links", "enable_cache_policy_rules", "enable_fragment_cache", "serve_cache_to_shoppers", "cache_mobile_separately" ].some(function(settingKey) {
            return UCPSettingEnabled(settings, settingKey);
        }) || !UCPSettingEnabled(settings, "disable_logged_in_optimizations") || "" !== String(xa("always_purge_urls", settings) || "").trim() || "auto" !== String(xa("compat_profile_mode", settings) || "auto") || -1 === [ "core", "off" ].indexOf(String(xa("speculative_loading_mode", settings) || "core")), preloadMode = da(settings), preloadEnabled = "off" !== preloadMode, wooRulesEnabled = UCPSettingEnabled(settings, "enable_woocommerce_rules"), publicShopperCache = !!parseInt(settings.serve_cache_to_shoppers || 0, 10);
        var queueActive = queueState.due > 0 || queueState.running > 0 || queueState.scheduled > 0;
        var heroDescription = !cacheEnabled ? i("Schakel paginaversnelling in om pagina’s vooraf op te bouwen.", "ultracache-pro") : queueState.failed ? s(1 === queueState.failed ? i("%d voorbereiding is niet afgerond.", "ultracache-pro") : i("%d voorbereidingen zijn niet afgerond.", "ultracache-pro"), queueState.failed) : queueState.running ? s(1 === queueState.running ? i("%d voorbereiding wordt uitgevoerd.", "ultracache-pro") : i("%d voorbereidingen worden uitgevoerd.", "ultracache-pro"), queueState.running) : queueState.due ? s(1 === queueState.due ? i("%d pagina wacht op voorbereiding.", "ultracache-pro") : i("%d pagina’s wachten op voorbereiding.", "ultracache-pro"), queueState.due) : queueState.scheduled ? s(1 === queueState.scheduled ? i("%d pagina staat klaar voor voorbereiding.", "ultracache-pro") : i("%d pagina’s staan klaar voor voorbereiding.", "ultracache-pro"), queueState.scheduled) : preloadEnabled ? i("Pagina’s versnellen en voorbereiden zijn actief.", "ultracache-pro") : i("Pagina’s versnellen is actief. Voorbereiden staat uit.", "ultracache-pro");
        var heroBadge = !cacheEnabled ? i("Uit", "ultracache-pro") : queueState.failed ? i("Controle nodig", "ultracache-pro") : null, heroBadgeClass = "ucp-status-badge--warning";
        var preloadAction = cacheEnabled && preloadEnabled ? a(Ue, {
            actions: queueState.failed ? [ "retry-failed-jobs", "run-due-jobs" ] : queueActive ? [ "run-due-jobs" ] : [ "preload", "run-due-jobs" ],
            label: queueState.running ? i("Verwerken…", "ultracache-pro") : queueState.failed ? i("Opnieuw proberen", "ultracache-pro") : queueActive ? i("Nu verwerken", "ultracache-pro") : i("Pagina’s voorbereiden", "ultracache-pro"),
            busyLabel: i("Verwerken…", "ultracache-pro"),
            pendingMessage: i("Pagina’s worden voorbereid.", "ultracache-pro"),
            disabled: queueState.running > 0,
            variant: "primary",
            queueSummary: !queueState.failed,
            successMessage: queueState.failed ? i("De niet-afgeronde voorbereidingen zijn opnieuw gestart.", "ultracache-pro") : "",
            addNotice: e.addNotice,
            setStatus: e.setStatus,
            runDueJobsData: { jobType: "preload_url", maxBatches: 1 }
        }) : null;
        var basicRows = [ row("cache", cacheGroups, "enable_cache", {
            title: i("Pagina’s versnellen", "ultracache-pro"),
            description: i("Maakt een snelle versie van openbare pagina’s.", "ultracache-pro"),
            className: "ucp-cache-setting-row--primary",
            field: {
                label: i("Pagina’s versnellen", "ultracache-pro"),
                help: ""
            }
        }) ];
        cacheEnabled && (advancedVisible || UCPSettingEnabled(settings, "enable_stale_cache")) && basicRows.push(fieldGroup(i("Cache vernieuwen", "ultracache-pro"), i("Kies de bewaartijd en of een oude versie tijdelijk mag blijven staan.", "ultracache-pro"), [ [ "cache_lifespan", {
            label: i("Vernieuwen na", "ultracache-pro"),
            help: ""
        } ], [ "stale_cache_mode", {
            label: i("Oude versie", "ultracache-pro"),
            help: ""
        } ] ])), cacheEnabled && basicRows.push(row("cache", cacheGroups, "enable_cache_tags", {
            title: i("Gerelateerde pagina’s vernieuwen", "ultracache-pro"),
            description: i("Vernieuwt archieven en overzichten wanneer inhoud verandert.", "ultracache-pro"),
            field: {
                label: i("Gerelateerde pagina’s vernieuwen", "ultracache-pro"),
                help: ""
            }
        }));
        var queueDescription = queueState.failed ? i("Niet alle pagina’s konden worden voorbereid.", "ultracache-pro") : queueState.due || queueState.running || queueState.scheduled ? i("Pagina’s worden op de achtergrond voorbereid.", "ultracache-pro") : preloadEnabled ? i("Alle geplande pagina’s zijn verwerkt.", "ultracache-pro") : i("Automatisch voorbereiden staat uit.", "ultracache-pro"), queueLabel = queueState.failed ? i("Controle nodig", "ultracache-pro") : queueState.due || queueState.running || queueState.scheduled ? i("Bezig", "ultracache-pro") : preloadEnabled ? i("Klaar", "ultracache-pro") : i("Uit", "ultracache-pro");
        var preloadRows = [ row("preload", preloadGroups, "preload_mode", {
            title: i("Pagina’s vooraf opbouwen", "ultracache-pro"),
            description: i("Kies welke openbare pagina’s vooraf worden opgebouwd.", "ultracache-pro"),
            className: "ucp-settings-row--preload-mode",
            field: {
                label: i("Pagina’s vooraf opbouwen", "ultracache-pro"),
                help: ""
            }
        }), UCPSettingsRow({
            key: "preload-queue",
            title: i("Status", "ultracache-pro"),
            className: "ucp-settings-row--preload-status",
            description: queueDescription,
            control: cacheEnabled && preloadEnabled ? a(ee, {
                state: queueState.failed ? "warning" : queueState.count ? "info" : "good"
            }, queueLabel) : a(ee, {
                state: "disabled"
            }, i("Uit", "ultracache-pro"))
        }) ].filter(Boolean);
        var protectionRows = [];
        (advancedVisible || UCPSettingEnabled(settings, "cache_mobile_separately")) && cacheEnabled && protectionRows.push(row("cache", cacheGroups, "cache_mobile_separately", {
            title: i("Aparte mobiele cache", "ultracache-pro"),
            description: i("Alleen gebruiken als mobiel andere HTML ontvangt.", "ultracache-pro"),
            field: {
                label: i("Aparte mobiele cache", "ultracache-pro"),
                help: ""
            }
        })), (advancedVisible || !UCPSettingEnabled(settings, "disable_logged_in_optimizations")) && protectionRows.push(row("cache", cacheGroups, "disable_logged_in_optimizations", {
            title: i("Ingelogde gebruikers uitsluiten", "ultracache-pro"),
            description: i("Houdt beheer, builders en persoonlijke sessies buiten de cache.", "ultracache-pro"),
            field: {
                label: i("Ingelogde gebruikers uitsluiten", "ultracache-pro"),
                help: ""
            }
        }));
        advancedVisible && wooActive && protectionRows.push(row("cache", cacheGroups, "enable_woocommerce_rules", {
            title: i("Winkelpagina’s beschermen", "ultracache-pro"),
            description: i("Houdt winkelwagen, checkout en account buiten gedeelde cache.", "ultracache-pro"),
            field: {
                label: i("Winkelpagina’s beschermen", "ultracache-pro"),
                help: ""
            }
        }));
        advancedVisible && wooActive && wooRulesEnabled && protectionRows.push(row("cache", cacheGroups, "optimize_cart_fragments", {
            title: i("Winkelwagenscripts gecontroleerd laden", "ultracache-pro"),
            description: i("Versnelt lege winkelwagens zonder actieve winkelwagens te verstoren.", "ultracache-pro"),
            field: {
                label: i("Winkelwagenscripts gecontroleerd laden", "ultracache-pro"),
                help: ""
            }
        }));
        advancedVisible && wooActive && wooRulesEnabled && protectionRows.push(row("cache", cacheGroups, "limit_cart_fragments_to_woo", {
            title: i("Winkelwagenscripts beperken", "ultracache-pro"),
            description: i("Laadt WooCommerce-winkelwagenscripts alleen waar nodig.", "ultracache-pro"),
            field: {
                label: i("Winkelwagenscripts beperken", "ultracache-pro"),
                help: ""
            }
        }));
        var preloadRules = UCPRuleBreakdown("preload_exclude_urls", settings.preload_exclude_urls || ""), speculationRules = UCPRuleBreakdown("speculation_exclusions", settings.speculation_exclusions || ""), standardCount = preloadRules.protectedLines.length + speculationRules.protectedLines.length, customCount = preloadRules.customLines.length + speculationRules.customLines.length;
        protectionRows.push(UCPSettingsRow({
            key: "protected-routes",
            title: i("Beschermde pagina’s", "ultracache-pro"),
            description: s(i("%1$d standaardregels actief. %2$d eigen regels.", "ultracache-pro"), standardCount, customCount),
            className: "ucp-settings-row--action ucp-cache-protected-routes-row",
            control: advancedVisible && e.onSelectTab ? a("a", {
                className: "ucp-dashboard-settings-link ucp-exclusions-link",
                href: UCPAdminTabUrl("advanced"),
                onClick: function(event) {
                    event.preventDefault();
                    e.onSelectTab("advanced");
                }
            }, i("Uitzonderingen beheren", "ultracache-pro")) : a(ee, { state: "good" }, i("Automatisch beschermd", "ultracache-pro"))
        }));
        var advancedNavigationRows = [ row("preload", preloadGroups, "enable_prefetch_links", {
            title: i("Volgende pagina voorbereiden", "ultracache-pro"),
            description: i("Haalt een interne link iets eerder op wanneer een bezoeker deze aanwijst.", "ultracache-pro"),
            field: {
                label: i("Volgende pagina voorbereiden", "ultracache-pro"),
                help: ""
            }
        }), row("preload", preloadGroups, "speculative_loading_mode", {
            title: i("Sneller navigeren", "ultracache-pro"),
            description: i("Gebruik WordPress standaard tenzij een andere modus aantoonbaar beter werkt.", "ultracache-pro"),
            field: {
                label: i("Browsernavigatie", "ultracache-pro"),
                help: ""
            }
        }) ].filter(Boolean);
        var advancedPurgeRows = [ row("cache", cacheGroups, "always_purge_urls", {
            title: i("Extra pagina’s vernieuwen", "ultracache-pro"),
            description: i("Voeg alleen URL’s toe die na een contentwijziging moeten vernieuwen.", "ultracache-pro"),
            className: "ucp-settings-row--wide-control ucp-settings-row--cache-purge-full",
            controlClassName: "ucp-settings-control--wide",
            field: {
                label: i("Extra pagina’s vernieuwen", "ultracache-pro"),
                help: ""
            }
        }) ].filter(Boolean);
        var cachePolicyOverviewRows = [ row("cache", cacheGroups, "enable_cache_policy_rules", {
            title: i("Cachebeleid per route", "ultracache-pro"),
            description: i("Alleen voor routes met afwijkend cachegedrag.", "ultracache-pro"),
            field: {
                label: i("Cachebeleidsregels gebruiken", "ultracache-pro"),
                help: ""
            }
        }), row("cache", cacheGroups, "compat_profile_mode", {
            title: i("Compatibiliteitsprofielen", "ultracache-pro"),
            description: i("Activeert lokale compatibiliteitsregels voor herkende plugins.", "ultracache-pro"),
            field: {
                label: i("Compatibiliteitsprofielen", "ultracache-pro"),
                help: ""
            }
        }) ].filter(Boolean), cachePolicyRuleRows = [ UCPSettingEnabled(settings, "enable_cache_policy_rules") ? row("cache", cacheGroups, "cache_policy_rules", {
            title: i("Cachebeleidsregels", "ultracache-pro"),
            description: i("Formaat: prioriteit|scope|match|ttl|stale|actie.", "ultracache-pro"),
            className: "ucp-settings-row--wide-control ucp-cache-policy-rules-row",
            controlClassName: "ucp-settings-control--wide",
            field: {
                label: i("Cachebeleidsregels", "ultracache-pro"),
                help: ""
            }
        }) : null ].filter(Boolean), fragmentPolicyRows = [ row("cache", cacheGroups, "enable_fragment_cache", {
            title: i("Publieke serverfragmenten cachen", "ultracache-pro"),
            description: i("Cache alleen geregistreerde, openbare fragmenten.", "ultracache-pro"),
            field: {
                label: i("Serverfragmenten cachen", "ultracache-pro"),
                help: ""
            }
        }), UCPSettingEnabled(settings, "enable_fragment_cache") ? row("cache", cacheGroups, "fragment_cache_ttl", {
            title: i("Fragmenten bewaren", "ultracache-pro"),
            description: i("Bewaartijd in seconden voor publieke serverfragmenten.", "ultracache-pro"),
            field: {
                label: i("Fragmenten bewaren", "ultracache-pro"),
                help: ""
            }
        }) : null, cacheAdvancedVisible ? row("cache", cacheGroups, "enable_esi", {
            title: i("Clientfragmenten verversen", "ultracache-pro"),
            description: i("Laadt geregistreerde dynamische fragmenten na paginarendering.", "ultracache-pro"),
            className: UCPSettingEnabled(settings, "enable_esi") ? "ucp-cache-setting-row--attention" : "",
            field: {
                label: i("Clientfragmenten verversen", "ultracache-pro"),
                help: ""
            }
        }) : null, cacheAdvancedVisible && wooActive ? row("cache", cacheGroups, "serve_cache_to_shoppers", {
            title: i("Publieke cache voor shoppers", "ultracache-pro"),
            description: i("Alleen inschakelen na stagingtests van cart, checkout en account.", "ultracache-pro"),
            className: publicShopperCache ? "ucp-cache-setting-row--attention" : "",
            field: {
                label: i("Publieke cache voor shoppers", "ultracache-pro"),
                help: ""
            }
        }) : null ].filter(Boolean);
        var cachePanelOpen = !cacheEnabled || !queueState.failed && !queueActive, preloadPanelOpen = cacheEnabled && (queueState.failed || queueActive), cacheBadge = cacheEnabled ? null : i("Uit", "ultracache-pro"), preloadBadge = queueState.failed ? i("Controle nodig", "ultracache-pro") : queueActive ? queueState.label : preloadEnabled ? null : i("Uit", "ultracache-pro"), protectionBadge = customCount ? s(i("%d eigen regels", "ultracache-pro"), customCount) : null;
        var advancedPanel = a("details", {
            className: "ucp-media-panel ucp-cache-panel ucp-cache-panel--advanced"
        }, a("summary", {
            className: "ucp-media-panel__summary ucp-cache-panel__summary"
        }, a("span", {
            className: "ucp-media-panel__copy ucp-cache-panel__copy"
        }, a("strong", {}, i("Technische cache-instellingen", "ultracache-pro")), a("small", {}, i("Alleen nodig voor specifieke situaties en tests.", "ultracache-pro")))), a("div", {
            className: "ucp-media-panel__body ucp-cache-panel__body ucp-cache-panel__body--advanced"
        }, a("div", {
            className: "ucp-media-advanced-warning ucp-cache-advanced-warning",
            role: "note"
        }, a("strong", {}, i("Eerst testen in een testomgeving", "ultracache-pro")), a("span", {}, i("Controleer formulieren, tracking en dynamische pagina’s na wijziging.", "ultracache-pro"))), a("div", {
            className: "ucp-cache-refinement-layout"
        }, a("div", {
            className: "ucp-settings-list ucp-cache-refinement-grid ucp-cache-refinement-grid--navigation"
        }, advancedNavigationRows), a("div", {
            className: "ucp-settings-list ucp-cache-refinement-grid ucp-cache-refinement-grid--purge"
        }, advancedPurgeRows), a("div", {
            className: "ucp-cache-refinement-policy-group"
        }, cachePolicyOverviewRows.length ? a("div", {
            className: "ucp-settings-list ucp-cache-refinement-list ucp-cache-refinement-list--overview"
        }, cachePolicyOverviewRows) : null, cachePolicyRuleRows.length ? a("div", {
            className: "ucp-settings-list ucp-cache-refinement-list ucp-cache-refinement-list--rules"
        }, cachePolicyRuleRows) : null, fragmentPolicyRows.length ? a("div", {
            className: "ucp-settings-list ucp-cache-refinement-list ucp-cache-refinement-list--fragments"
        }, fragmentPolicyRows) : null))));
        return a("div", {
            className: "ucp-settings-page ucp-settings-page--cache ucp-cache-tools-page"
        }, UCPSectionHero({
            className: "ucp-section-hero--cache",
            eyebrow: i("Snelheid", "ultracache-pro"),
            title: i("Snelheid", "ultracache-pro"),
            description: heroDescription,
            badge: heroBadge,
            badgeClass: heroBadgeClass,
            action: preloadAction
        }), a("div", {
            className: "ucp-settings-stack ucp-settings-stack--cache"
        }, a("div", {
            className: "ucp-media-panel-list ucp-cache-panel-list"
        }, panel("cache-basics", i("Pagina’s sneller laden", "ultracache-pro"), i("Maakt een snelle versie van openbare pagina’s.", "ultracache-pro"), cacheBadge, "ucp-status-badge--warning", basicRows, cachePanelOpen), panel("preload-settings", i("Pagina’s voorbereiden", "ultracache-pro"), i("Zorgt dat belangrijke pagina’s direct beschikbaar zijn.", "ultracache-pro"), preloadBadge, queueState.failed ? "ucp-status-badge--warning" : queueActive ? "ucp-status-badge--info" : "ucp-status-badge--neutral", preloadRows, preloadPanelOpen), panel("cache-protection", i("Automatische bescherming", "ultracache-pro"), i("Houdt persoonlijke en dynamische pagina’s buiten de cache.", "ultracache-pro"), protectionBadge, "ucp-status-badge--neutral", protectionRows, !1), cacheAdvancedVisible ? advancedPanel : null)));
    }
    function Ge(e) {
        var schemaGroups = Ve.optimization || [], advancedVisible = UCPAdvancedSettingsVisible(e.settings || {}), settings = e.settings || {}, status = e.status || {}, optimizationAdvancedVisible = advancedVisible || [ "enable_css_combine", "enable_js_combine", "enable_lazy_render", "enable_html_parser", "enable_self_host_third_party_assets", "enable_headless_renderer" ].some(function(settingKey) {
            return UCPSettingEnabled(settings, settingKey);
        }) || [ "css_exclusions", "delay_js_exclusions", "html_exclude_urls" ].some(function(settingKey) {
            return "" !== String(xa(settingKey, settings) || "").trim();
        }), modalState = r(!1), exclusionModalOpen = modalState[0], setExclusionModalOpen = modalState[1];
        function fieldDefinition(settingKey) {
            return We(schemaGroups, settingKey);
        }
        function lineCount(settingKey) {
            var seen = {};
            return String(xa(settingKey, settings) || "").split(/\r?\n/).map(function(line) {
                return String(line || "").trim();
            }).filter(function(line) {
                if (!line || seen[line]) return !1;
                return seen[line] = !0, !0;
            }).length;
        }
        function control(settingKey, overrides) {
            var field = fieldDefinition(settingKey);
            if (!field || !UCPShouldRenderSetting(settingKey, settings, status)) return null;
            field = field.slice(), (overrides = overrides || {}).label && (field[1] = overrides.label), void 0 !== overrides.help && (field[3] = overrides.help);
            var controlType = field[2] || "text", className = "ucp-settings-control ucp-settings-control--" + controlType + " ucp-settings-control--hide-primary-label";
            "toggle" === controlType && (className += " ucp-settings-control--toggle"), "textarea" === controlType && (className += " ucp-settings-control--wide");
            return a("div", {
                className: className
            }, a(Ba, {
                field: field,
                kind: "optimization",
                hideRiskBadge: !0,
                hideRiskNotice: "css_delivery_mode" === settingKey,
                hideInlineHelp: !0,
                settings: settings,
                status: status,
                setSettings: e.setSettings,
                addNotice: e.addNotice,
                setStatus: e.setStatus
            }));
        }
        function row(settingKey, options) {
            var field = fieldDefinition(settingKey);
            if (!field || !UCPShouldRenderSetting(settingKey, settings, status)) return null;
            options = options || {};
            var controlType = field[2] || "text", className = UCPSettingsRowClass(settingKey, controlType, "ucp-settings-row--optimization");
            options.className && (className += " " + options.className);
            return UCPSettingsRow({
                key: settingKey,
                title: options.title || field[1],
                description: void 0 !== options.description ? options.description : field[3],
                meta: "css_delivery_mode" === settingKey ? null : UCPRiskIsActive(settingKey, xa(settingKey, settings)) ? a(Ma, {
                    settingKey: settingKey
                }) : null,
                className: className,
                control: control(settingKey, options.field || null)
            });
        }
        function optimizationGroup(key, title, description, rows) {
            rows = (rows || []).filter(Boolean);
            return rows.length ? a("section", {
                key: key,
                className: "ucp-optimization-primary-group ucp-optimization-primary-group--" + D(key)
            }, a("header", {
                className: "ucp-optimization-primary-group__header"
            }, a("h3", {}, title), description ? a("p", {}, description) : null), a("div", {
                className: "ucp-settings-list ucp-hybrid-settings-grid"
            }, rows)) : null;
        }
        var delayMode = String(xa("delay_js_control", settings) || "off"), delayActive = "off" !== delayMode, htmlRows = [ row("html_optimization_mode", {
            title: i("Verkleinen", "ultracache-pro"),
            description: i("Maakt HTML kleiner zonder inhoud te wijzigen.", "ultracache-pro")
        }) ].filter(Boolean), cssRows = [ row("enable_css_minify", {
            title: i("Verkleinen", "ultracache-pro"),
            description: i("Maakt CSS-bestanden kleiner.", "ultracache-pro")
        }), row("css_delivery_mode", {
            title: i("Laden", "ultracache-pro"),
            description: i("Standaard: normaal laden. Test alternatieven op staging.", "ultracache-pro")
        }) ].filter(Boolean), jsRows = [ row("enable_js_minify", {
            title: i("Verkleinen", "ultracache-pro"),
            description: i("Maakt JavaScript-bestanden kleiner.", "ultracache-pro")
        }), row("defer_all_js", {
            title: i("Later laden", "ultracache-pro"),
            description: i("Voert scripts uit na de paginaopbouw.", "ultracache-pro")
        }), row("delay_js_control", {
            title: i("Uitstellen tot interactie", "ultracache-pro"),
            description: i("Stelt niet-kritieke scripts uit.", "ultracache-pro")
        }), delayActive || UCPSettingEnabled(settings, "accessibility_mode") ? row("accessibility_mode", {
            title: i("Directe interacties behouden", "ultracache-pro"),
            description: i("Houdt menu’s, formulieren, cookiebanners en checkout direct bruikbaar.", "ultracache-pro")
        }) : null ].filter(Boolean), primaryRows = [ optimizationGroup("optimization-html", i("HTML", "ultracache-pro"), i("Maak de paginabron compacter.", "ultracache-pro"), htmlRows), optimizationGroup("optimization-css", i("CSS", "ultracache-pro"), i("Verklein stylesheets en kies hoe ze worden geladen.", "ultracache-pro"), cssRows), optimizationGroup("optimization-js", i("JavaScript", "ultracache-pro"), i("Verklein scripts en bepaal wanneer ze worden uitgevoerd.", "ultracache-pro"), jsRows) ].filter(Boolean), combineRows = [ row("enable_css_combine", {
            title: i("CSS-bestanden samenvoegen", "ultracache-pro"),
            description: i("Alleen gebruiken bij een bevestigd compatibiliteitsprobleem.", "ultracache-pro"),
            className: "ucp-settings-row--advanced-combine"
        }), row("enable_js_combine", {
            title: i("JavaScript-bestanden samenvoegen", "ultracache-pro"),
            description: i("Alleen gebruiken bij een bevestigd compatibiliteitsprobleem.", "ultracache-pro"),
            className: "ucp-settings-row--advanced-combine"
        }) ].filter(Boolean), exclusionRows = [ row("css_exclusions", {
            title: i("CSS-uitsluitingen", "ultracache-pro"),
            description: i("Eén handle, bestandsnaam, selector of fragment per regel.", "ultracache-pro"),
            className: "ucp-settings-row--advanced-exclusion"
        }), row("delay_js_exclusions", {
            title: i("JavaScript-uitsluitingen", "ultracache-pro"),
            description: i("Eén script, handle of fragment per regel.", "ultracache-pro"),
            className: "ucp-settings-row--advanced-exclusion"
        }), row("html_exclude_urls", {
            title: i("HTML-uitsluitingen", "ultracache-pro"),
            description: i("Eén URL of patroon per regel.", "ultracache-pro"),
            className: "ucp-settings-row--advanced-exclusion"
        }) ].filter(Boolean), renderingRows = [ row("enable_lazy_render", {
            title: i("Onderdelen buiten beeld later tonen", "ultracache-pro"),
            description: i("Toont geselecteerde onderdelen pas vlak voor ze in beeld komen.", "ultracache-pro"),
            className: "ucp-settings-row--situation-half"
        }), row("lazy_render_selectors", {
            title: i("Onderdelen selecteren", "ultracache-pro"),
            description: i("Eén CSS-selector per regel.", "ultracache-pro")
        }) ].filter(Boolean), parserRow = UCPExplicitSupportMode() || UCPSettingEnabled(settings, "enable_html_parser") ? row("enable_html_parser", {
            title: i("Alternatieve HTML-parser", "ultracache-pro"),
            description: i("Alleen gebruiken op advies van support of bij een bevestigd parserprobleem.", "ultracache-pro")
        }) : null, externalScriptRows = [ row("enable_self_host_third_party_assets", {
            title: i("Externe assets lokaal hosten", "ultracache-pro"),
            description: i("Slaat externe bestanden lokaal op. Controleer consent en updates.", "ultracache-pro"),
            className: "ucp-settings-row--situation-full"
        }) ].filter(Boolean), rendererServiceRows = [ row("enable_headless_renderer", {
            title: i("Browsergebaseerde analyse", "ultracache-pro"),
            description: i("Gebruikt een browser voor CSS-analyse.", "ultracache-pro"),
            className: "ucp-settings-row--situation-half"
        }), UCPSettingEnabled(settings, "enable_headless_renderer") ? row("headless_renderer_endpoint", {
            title: i("Renderer endpoint", "ultracache-pro"),
            description: i("Publieke HTTPS-URL van de renderdienst.", "ultracache-pro")
        }) : null, UCPSettingEnabled(settings, "enable_headless_renderer") ? row("headless_renderer_token", {
            title: i("Renderer token", "ultracache-pro"),
            description: i("Geheim token voor de renderdienst. Wordt gemaskeerd in de interface.", "ultracache-pro")
        }) : null, UCPSettingEnabled(settings, "enable_headless_renderer") && "" !== String(xa("headless_renderer_endpoint", settings) || "").trim() ? UCPSettingsRow({
            key: "renderer-test",
            title: i("Rendererverbinding", "ultracache-pro"),
            description: i("Controleer de ingestelde renderdienst met de homepage als veilige test-URL.", "ultracache-pro"),
            className: "ucp-settings-row--action",
            control: a("div", {
                className: "ucp-settings-action-control"
            }, a(Pe, {
                action: "renderer-test",
                label: i("Verbinding testen", "ultracache-pro"),
                data: {
                    url: N.homeUrl || ""
                },
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                onComplete: e.onRefresh,
                compact: !0
            }))
        }) : null ].filter(Boolean), cssExclusionCount = lineCount("css_exclusions"), jsExclusionCount = lineCount("delay_js_exclusions"), htmlExclusionCount = lineCount("html_exclude_urls"), activeAdvancedCount = [ "enable_css_combine", "enable_js_combine", "enable_lazy_render", "enable_html_parser", "enable_self_host_third_party_assets" ].filter(function(settingKey) {
            return UCPSettingEnabled(settings, settingKey);
        }).length + (UCPSettingEnabled(settings, "enable_headless_renderer") ? 1 : 0), advancedRiskActive = [ "enable_css_combine", "enable_js_combine", "enable_lazy_render", "enable_html_parser", "enable_self_host_third_party_assets", "enable_headless_renderer" ].some(function(settingKey) {
            return UCPRiskIsActive(settingKey, xa(settingKey, settings));
        }), advancedSummary = advancedRiskActive ? i("Test vereist", "ultracache-pro") : activeAdvancedCount ? s(i("%d actieve optimalisaties", "ultracache-pro"), activeAdvancedCount) : i("Geen actieve optimalisaties", "ultracache-pro"), exclusionsDescription = s(i("%1$d CSS-, %2$d JavaScript- en %3$d HTML-regels.", "ultracache-pro"), cssExclusionCount, jsExclusionCount, htmlExclusionCount), exclusionsSection = M ? UCPSettingsSection({
            key: "optimization-exclusions",
            className: "ucp-settings-section--optimization-advanced ucp-optimization-exclusions-summary",
            title: i("Uitsluitingen beheren", "ultracache-pro"),
            description: exclusionsDescription,
            headerAction: a(g, {
                variant: "secondary",
                onClick: function() {
                    setExclusionModalOpen(!0);
                }
            }, i("Uitsluitingen beheren", "ultracache-pro")),
            hideBody: !0
        }) : UCPSettingsSection({
            key: "optimization-exclusions",
            className: "ucp-settings-section--optimization-advanced ucp-optimization-exclusions-card",
            title: i("Uitsluitingen beheren", "ultracache-pro"),
            description: exclusionsDescription,
            children: exclusionRows
        }), renderingServiceRows = [].concat(renderingRows, parserRow ? [ parserRow ] : [], rendererServiceRows, externalScriptRows).filter(Boolean), advancedPanel = optimizationAdvancedVisible ? a("details", {
            className: "ucp-settings-advanced ucp-settings-advanced--optimization"
        }, a("summary", {}, a("span", {
            className: "ucp-advanced-summary-copy"
        }, a("strong", {}, i("Extra optimalisaties", "ultracache-pro")), a("small", {}, i("Opties voor uitzonderingen en speciale situaties.", "ultracache-pro"))), a("span", {
            className: "ucp-status-badge " + (advancedRiskActive ? "ucp-status-badge--warning" : "ucp-status-badge--neutral")
        }, advancedSummary)), a("div", {
            className: "ucp-settings-advanced__body ucp-optimization-advanced-grid"
        }, UCPSettingsSection({
            key: "optimization-combine",
            className: "ucp-settings-section--optimization-advanced ucp-optimization-combine-card ucp-settings-section--hybrid-grid",
            title: i("Bestanden samenvoegen", "ultracache-pro"),
            description: i("Meestal niet nodig. Alleen wijzigen na een test.", "ultracache-pro"),
            children: combineRows
        }), exclusionsSection, renderingServiceRows.length ? UCPSettingsSection({
            key: "optimization-rendering-services",
            className: "ucp-settings-section--optimization-advanced ucp-optimization-rendering-card ucp-settings-section--hybrid-grid",
            title: i("Specifieke situaties", "ultracache-pro"),
            description: i("Alleen gebruiken bij een aantoonbaar probleem.", "ultracache-pro"),
            children: renderingServiceRows
        }) : null)) : null, exclusionModal = M && exclusionModalOpen ? a(M, {
            title: i("Uitsluitingen beheren", "ultracache-pro"),
            className: "ucp-optimization-exclusions-modal",
            overlayClassName: "ucp-optimization-exclusions-overlay",
            size: "large",
            onRequestClose: function() {
                setExclusionModalOpen(!1);
            }
        }, a("p", {
            className: "ucp-optimization-exclusions-modal__intro"
        }, i("Voeg alleen bevestigde conflicten toe; wijzigingen slaan automatisch op.", "ultracache-pro")), a("div", {
            className: "ucp-settings-list ucp-optimization-exclusions-modal__list",
            role: "region",
            "aria-label": i("Uitsluitingsvelden", "ultracache-pro"),
            tabIndex: 0
        }, exclusionRows), a("div", {
            className: "ucp-optimization-exclusions-modal__actions"
        }, a(g, {
            variant: "primary",
            onClick: function() {
                setExclusionModalOpen(!1);
            }
        }, i("Sluiten", "ultracache-pro")))) : null;
        return a(t, {}, a("div", {
            className: "ucp-settings-page ucp-settings-page--optimization ucp-cache-tools-page"
        }, UCPSectionHero({
            eyebrow: i("CSS & JS", "ultracache-pro"),
            title: i("Frontend-optimalisatie", "ultracache-pro"),
            description: i("Beheer bestandsgrootte en laadtiming.", "ultracache-pro")
        }), a("div", {
            className: "ucp-settings-stack ucp-settings-stack--optimization"
        }, UCPSettingsSection({
            key: "optimization-primary",
            className: "ucp-settings-section--optimization-primary",
            hideHeader: !0,
            children: primaryRows
        }), advancedPanel)), exclusionModal);
    }
    function Ee(e) {
        var r = UCPAdvancedSettingsVisible(e.settings || {}), n = Ve[e.kind] || [], c = n.filter(function(group) {
            if (r || !group.advanced || "advanced" === e.kind && "WordPress beheer" === group.title && "off" !== String(xa("bloat_removal_mode", e.settings || {}) || "off")) return !0;
            if ("advanced" === e.kind && "Query strings" === group.title && "off" !== String(xa("query_string_cache_mode", e.settings || {}) || "off")) return !0;
            if ("advanced" === e.kind && "Technische uitsluitingen" === group.title && (UCPSettingEnabled(e.settings || {}, "block_unknown_request_cookies") || [ "exclude_cookies", "exclude_user_agents", "cache_vary_cookies" ].some(function(settingKey) {
                return "" !== String(xa(settingKey, e.settings || {}) || "").trim();
            }))) return !0;
            if ("advanced" === e.kind && "Browsernavigatie uitsluiten" === group.title && "" !== String(xa("speculation_exclusions", e.settings || {}) || "").trim()) return !0;
            return "diagnostics" === e.kind && "Bewaartermijnen" === group.title && (UCPSettingEnabled(e.settings || {}, "enable_logs") || UCPSettingEnabled(e.settings || {}, "enable_diagnostics"));
        });
        function o(e) {
            return {
                media: {
                    eyebrow: i("Media", "ultracache-pro"),
                    title: i("Afbeeldingen & lettertypen", "ultracache-pro"),
                    text: i("Configureer uploads, lazyload, LCP-afbeeldingen en fonts.", "ultracache-pro"),
                    badge: i("Status", "ultracache-pro"),
                    badgeClass: "ucp-status-badge--neutral"
                },
                preload: {
                    eyebrow: i("Vooraf opbouwen", "ultracache-pro"),
                    title: i("Cache slim vooraf opbouwen", "ultracache-pro"),
                    text: i("Configureer preload en serverbelasting.", "ultracache-pro"),
                    badge: i("Rustige queue", "ultracache-pro"),
                    badgeClass: "ucp-status-badge--good"
                },
                advanced: {
                    eyebrow: i("Uitsluitingen", "ultracache-pro"),
                    title: i("Uitzonderingen overzichtelijk beheren", "ultracache-pro"),
                    text: i("Definieer uitzonderingen voor routes, cookies en queryparameters.", "ultracache-pro"),
                    badge: "",
                    badgeClass: ""
                },
                database: {
                    eyebrow: i("Database", "ultracache-pro"),
                    title: i("Onderhoud met controle", "ultracache-pro"),
                    text: i("Selecteer databaseacties; maak vóór destructieve acties een backup.", "ultracache-pro"),
                    badge: i("Backup bij risico", "ultracache-pro"),
                    badgeClass: "ucp-status-badge--warning"
                },
                diagnostics: {
                    eyebrow: i("Onderhoud", "ultracache-pro"),
                    title: i("Controle en technische hulpmiddelen", "ultracache-pro"),
                    text: i("Activeer diagnostiek en externe koppelingen alleen tijdelijk.", "ultracache-pro"),
                    badge: i("Technisch", "ultracache-pro"),
                    badgeClass: "ucp-status-badge--neutral"
                }
            }[e] || {
                eyebrow: i("Instellingen", "ultracache-pro"),
                title: i("Instellingen", "ultracache-pro"),
                text: i("Beheer de instellingen in rustige groepen.", "ultracache-pro"),
                badge: "",
                badgeClass: "ucp-status-badge--neutral"
            };
        }
        function u(e) {
            return We(n, e);
        }
        function getDatabaseCounts() {
            return ((e.status || {}).databaseCleanup || {}).counts || {};
        }
        function f(a) {
            if ("database" !== e.kind || !a) return a;
            var t = getDatabaseCounts(), n = {
                db_cleanup_post_revisions: [ "revisions", i("revisies in je database.", "ultracache-pro") ],
                db_cleanup_auto_drafts: [ "auto_drafts", i("automatische concepten in je database.", "ultracache-pro") ],
                db_cleanup_drafts: [ "drafts", i("gewone concepten in je database.", "ultracache-pro") ],
                db_cleanup_trashed_posts: [ "trash_posts", i("verwijderde berichten in je database.", "ultracache-pro") ],
                db_cleanup_spam_comments: [ "spam_comments", i("spamreacties in je database.", "ultracache-pro") ],
                db_cleanup_trashed_comments: [ "trash_comments", i("verwijderde reacties in je database.", "ultracache-pro") ],
                db_cleanup_expired_transients: [ "expired_transients", i("verlopen transients in je database.", "ultracache-pro") ],
                db_cleanup_all_transients: [ "transients", i("transients in je database.", "ultracache-pro") ],
                db_cleanup_wc_sessions: [ "wc_sessions", i("verlopen WooCommerce-sessies in je database.", "ultracache-pro") ],
                db_cleanup_optimize_tables: [ "plugin_tables", i("UltraCache-tabellen beschikbaar.", "ultracache-pro") ],
                db_cleanup_optimize_all_tables: [ "optimizable_tables", i("te optimaliseren WordPress-tabellen in je database.", "ultracache-pro") ]
            }[a[0]];
            if (!n) return a;
            var c = parseInt(t[n[0]] || 0, 10) || 0, o = a.slice();
            return o[3] = (o[3] ? o[3] + " " : "") + s(i("%d %s", "ultracache-pro"), c, n[1]),
            o;
        }
        function k(t) {
            if (!t) return null;
            if (!UCPShouldRenderSetting(t[0], e.settings, e.status || {})) return null;
            var i = "ucp-compact-option ucp-cache-tools-option";
            return ({
                textarea: !0,
                lcp_images: !0,
                css_delivery: !0
            }[(t = f(t))[2] || "text"] || {
                lazyload_exclusions: !0,
                lazy_render_selectors: !0,
                preload_fonts: !0,
                preload_exclude_urls: !0,
                speculation_exclusions: !0,
                exclude_urls: !0,
                exclude_cookies: !0,
                exclude_user_agents: !0,
                cache_vary_cookies: !0,
                cache_query_string_inclusions: !0
            }[t[0]]) && (i += " ucp-compact-option--full"), a("div", {
                key: t[0],
                className: i
            }, a(Ba, {
                field: t,
                kind: e.kind,
                showControlLabel: "diagnostics" === e.kind && -1 !== [ "log_retention_days", "diagnostics_retention_days", "job_retention_days" ].indexOf(t[0]),
                settings: e.settings,
                status: e.status || {},
                setSettings: e.setSettings,
                addNotice: e.addNotice,
                setStatus: e.setStatus
            }));
        }
        function w(e) {
            var r = "ucp-card ucp-compact-card ucp-cache-tools-card components-card", title = String(e.title || "");
            return (e.fields || []).some(function(e) {
                return "textarea" === e[2];
            }) && (r += " ucp-compact-card--textarea"), e.advanced && (r += " ucp-compact-card--advanced"),
            "Automatisch onderhoud" === title && (r += " ucp-db-card ucp-db-card--schedule"),
            "Veilig opruimen" === title && (r += " ucp-db-card ucp-db-card--safe"),
            "Backup nodig" === title && (r += " ucp-db-card ucp-db-card--risk"),
            r;
        }
        function getFieldGridClass(e) {
            var a = e.fields || [];
            return a.length <= 1 ? "ucp-compact-field-grid ucp-compact-field-grid--one" : a.length <= 2 ? "ucp-compact-field-grid ucp-compact-field-grid--two" : "ucp-compact-field-grid ucp-compact-field-grid--auto";
        }
        if ("media" === e.kind) return function() {
            var settings = e.settings || {};
            function renderControl(settingKey, extraClass, overrides) {
                var field = u(settingKey);
                if (!field) return null;
                field = field.slice(), (overrides = overrides || {}).label && (field[1] = overrides.label), void 0 !== overrides.help && (field[3] = overrides.help), overrides.type && (field[2] = overrides.type), overrides.options && (field[4] = overrides.options);
                var controlType = field[2] || "field";
                return a("div", {
                    className: "ucp-settings-control ucp-settings-control--" + controlType + " " + (extraClass || "")
                }, a(Ba, {
                    key: field[0],
                    field: field,
                    kind: "media",
                    settings: e.settings,
                    status: e.status || {},
                    setSettings: e.setSettings,
                    addNotice: e.addNotice,
                    setStatus: e.setStatus,
                    hideInlineHelp: !0
                }));
            }
            function renderRow(settingKey, overrides) {
                var field = u(settingKey);
                if (!field || !UCPShouldRenderSetting(settingKey, e.settings, e.status || {})) return null;
                overrides = overrides || {};
                var controlType = overrides.field && overrides.field.type ? overrides.field.type : field[2] || "field", controlClass = "ucp-settings-control--hide-primary-label", wideControl = "textarea" === controlType;
                return wideControl && (controlClass += " ucp-settings-control--wide"),
                UCPSettingsRow({
                    key: settingKey,
                    title: overrides.title || field[1],
                    description: void 0 !== overrides.description ? overrides.description : field[3],
                    className: UCPSettingsRowClass(settingKey, controlType, overrides.className || ""),
                    controlClassName: overrides.controlClassName || "",
                    control: renderControl(settingKey, controlClass, overrides.field || null)
                });
            }
            function uniqueLineCount(settingKey) {
                var seen = {};
                return String(xa(settingKey, settings) || "").split(/\r?\n/).map(function(line) {
                    return String(line || "").trim();
                }).filter(function(line) {
                    if (!line || seen[line]) return !1;
                    return seen[line] = !0, !0;
                }).length;
            }
            function inlineDetails(key, title, description, countLabel, children, example) {
                return a("details", {
                    key: key,
                    className: "ucp-media-inline-details"
                }, a("summary", {
                    className: "ucp-media-inline-details__summary"
                }, a("span", {
                    className: "ucp-media-inline-details__copy"
                }, a("strong", {}, title), a("small", {}, description)), a("span", {
                    className: "ucp-status-badge ucp-status-badge--neutral"
                }, countLabel)), a("div", {
                    className: "ucp-media-inline-details__body"
                }, example ? a("p", {
                    className: "ucp-media-inline-details__example"
                }, example) : null, children));
            }
            function renderPanel(key, title, description, badge, badgeClass, children, defaultOpen) {
                return a("details", {
                    key: key,
                    className: "ucp-media-panel ucp-media-panel--" + D(key),
                    open: defaultOpen ? !0 : void 0
                }, a("summary", {
                    className: "ucp-media-panel__summary"
                }, a("span", {
                    className: "ucp-media-panel__copy"
                }, a("strong", {}, title), description ? a("small", {}, description) : null), badge ? a("span", {
                    className: "ucp-status-badge " + (badgeClass || "ucp-status-badge--neutral")
                }, badge) : null), a("div", {
                    className: "ucp-media-panel__body"
                }, a("div", {
                    className: "ucp-settings-list ucp-hybrid-settings-grid"
                }, (children || []).filter(Boolean))));
            }
            function renderAdvancedGroup(key, title, description, rows, extraClass) {
                rows = (rows || []).filter(Boolean);
                var compact = extraClass && -1 !== extraClass.indexOf("ucp-media-advanced-group--compact");
                return rows.length ? a("section", {
                    key: key,
                    className: "ucp-media-advanced-group" + (extraClass ? " " + extraClass : "")
                }, compact ? null : a("header", {
                    className: "ucp-media-advanced-group__header"
                }, a("h3", {}, title), description ? a("p", {}, description) : null), a("div", {
                    className: "ucp-settings-list ucp-hybrid-settings-grid"
                }, rows)) : null;
            }
            var imageMode = String(xa("image_optimization_mode", settings) || "off"), rawLegacyImageMode = UCPSettingEnabled(settings, "enable_image_optimization") && !UCPSettingEnabled(settings, "enable_webp_generation") && !UCPSettingEnabled(settings, "enable_avif_generation"), dimensionsActive = UCPSettingEnabled(settings, "enable_add_image_dimensions"), lazyMode = String(xa("media_lazyload_mode", settings) || "off"), lazyActive = "off" !== lazyMode, lcpMode = String(xa("lcp_image_mode", settings) || "off"), lcpActive = "off" !== lcpMode, fontMode = String(xa("google_fonts_mode", settings) || "standard"), autoFontPreloads = UCPSettingEnabled(settings, "enable_auto_font_preloads"), exclusionsCount = uniqueLineCount("lazyload_exclusions"), fontCount = uniqueLineCount("preload_fonts"), statusData = e.status || {}, conflictMatches = statusData.conflictGuard && Array.isArray(statusData.conflictGuard.matches) ? statusData.conflictGuard.matches : [], externalImageOptimizer = conflictMatches.some(function(e) {
                return (e && Array.isArray(e.overlap) ? e.overlap : []).some(function(e) {
                    return /image optimization|image cdn|webp cache/i.test(String(e || ""));
                });
            }), imagePipeline = statusData.optimization && statusData.optimization.imagePipeline ? statusData.optimization.imagePipeline : {}, serverSupport = imagePipeline.serverSupport || {}, webpSupported = !Object.prototype.hasOwnProperty.call(serverSupport, "webp") || !!serverSupport.webp, avifSupported = !Object.prototype.hasOwnProperty.call(serverSupport, "avif") || !!serverSupport.avif, selectedFormatSupported = "webp_avif" === imageMode ? webpSupported && avifSupported : "webp" !== imageMode || webpSupported, uploadNeedsAttention = rawLegacyImageMode || "off" === imageMode && !externalImageOptimizer || !selectedFormatSupported, uploadConfigured = !uploadNeedsAttention, mediaHealthy = dimensionsActive && lazyActive && lcpActive, mediaReady = mediaHealthy && uploadConfigured, uploadDescriptions = {
                off: externalImageOptimizer ? i("Een andere plugin beheert afbeeldingen; UltraCache blijft uit.", "ultracache-pro") : i("Kies origineel, WebP of WebP + AVIF voor nieuwe uploads.", "ultracache-pro"),
                webp: webpSupported ? i("Maakt een WebP-variant en bewaart het origineel.", "ultracache-pro") : i("Deze server kan momenteel geen WebP-bestanden maken.", "ultracache-pro"),
                webp_avif: webpSupported && avifSupported ? i("Maakt WebP/AVIF-varianten en bewaart het origineel. Test op staging.", "ultracache-pro") : i("Deze server ondersteunt niet alle vereiste moderne afbeeldingsformaten.", "ultracache-pro")
            }, fontDescriptions = {
                standard: i("WordPress en het thema bepalen de lettertypen.", "ultracache-pro"),
                swap: i("Toont tekst direct en wisselt zodra het lettertype beschikbaar is.", "ultracache-pro"),
                local: i("Downloadt Google Fonts server-side en serveert ze lokaal. Alleen bewust inschakelen.", "ultracache-pro"),
                disable: i("Blokkeert Google-lettertypen. Controleer daarna de website.", "ultracache-pro")
            }, statusBadge = null, statusBadgeClass = "", uploadBadge = uploadNeedsAttention ? rawLegacyImageMode ? i("Verouderd", "ultracache-pro") : !selectedFormatSupported ? i("Niet ondersteund", "ultracache-pro") : i("Niet ingesteld", "ultracache-pro") : null, loadingNeedsAttention = !lazyActive || !lcpActive, statusText = rawLegacyImageMode ? i("Ongeldige afbeeldingsmodus. Kies Uit, WebP of WebP + AVIF.", "ultracache-pro") : mediaReady && externalImageOptimizer && "off" === imageMode ? i("Laden is actief. Uploadoptimalisatie blijft bij de andere plugin.", "ultracache-pro") : mediaReady ? i("De media-instellingen zijn actief.", "ultracache-pro") : !selectedFormatSupported ? i("Dit afbeeldingsformaat wordt niet ondersteund door de server.", "ultracache-pro") : uploadNeedsAttention && mediaHealthy ? i("Laadinstellingen zijn actief. Kies een formaat voor nieuwe uploads.", "ultracache-pro") : i("Open het onderdeel dat aandacht vraagt en controleer daarna de website.", "ultracache-pro"), uploadRows = [ renderRow("image_optimization_mode", {
                title: i("Nieuwe uploads", "ultracache-pro"),
                description: uploadDescriptions[imageMode] || uploadDescriptions.off,
                field: {
                    type: "select",
                    label: i("Nieuwe uploads", "ultracache-pro"),
                    help: "",
                    options: [ [ "off", i("Niet aanpassen", "ultracache-pro") ], [ "webp", webpSupported ? i("WebP maken", "ultracache-pro") : i("WebP niet beschikbaar", "ultracache-pro"), !webpSupported ], [ "webp_avif", webpSupported && avifSupported ? i("WebP + AVIF maken", "ultracache-pro") : i("WebP + AVIF niet beschikbaar", "ultracache-pro"), !webpSupported || !avifSupported ] ]
                }
            }), (r || 82 !== parseInt(xa("image_quality", settings) || 82, 10)) && selectedFormatSupported ? renderRow("image_quality", {
                title: i("Afbeeldingskwaliteit", "ultracache-pro"),
                description: i("80–85 is meestal een goede balans.", "ultracache-pro")
            }) : null, renderRow("enable_add_image_dimensions", {
                title: i("Afmetingen toevoegen", "ultracache-pro"),
                description: i("Voorkomt verschuiven tijdens het laden.", "ultracache-pro")
            }) ].filter(Boolean), loadingRows = [ renderRow("media_lazyload_mode", {
                title: i("Media later laden", "ultracache-pro"),
                description: i("Laadt media pas wanneer die nodig is.", "ultracache-pro"),
                field: {
                    type: "media_lazyload",
                    label: i("Later laden", "ultracache-pro"),
                    help: ""
                }
            }), renderRow("lcp_image_mode", {
                title: i("Hoofdafbeelding direct laden", "ultracache-pro"),
                description: i("Geeft de eerste grote afbeelding voorrang.", "ultracache-pro"),
                field: {
                    type: r ? "lcp_images" : "media_lcp_toggle",
                    label: i("Hoofdafbeelding direct laden", "ultracache-pro"),
                    help: ""
                }
            }), (r || exclusionsCount) && lazyActive ? inlineDetails("media-lazyload-exclusions", i("Lazyload-uitzonderingen", "ultracache-pro"), i("Logo’s, hero’s en sliders laden direct.", "ultracache-pro"), s(i("%d regels actief", "ultracache-pro"), exclusionsCount), renderRow("lazyload_exclusions", {
                title: i("Eigen uitzonderingen", "ultracache-pro"),
                description: i("Voeg alleen selectors toe voor afbeeldingen die te laat verschijnen.", "ultracache-pro")
            })) : null ].filter(Boolean), fontRows = [ renderRow("google_fonts_mode", {
                title: i("Lettertypen laden", "ultracache-pro"),
                description: fontDescriptions[fontMode] || fontDescriptions.standard,
                field: {
                    type: "select",
                    label: i("Lettertypen laden", "ultracache-pro"),
                    help: "",
                    options: [ [ "standard", i("WordPress volgen", "ultracache-pro") ], [ "swap", i("Tekst direct tonen", "ultracache-pro") ], [ "local", i("Lokaal laden", "ultracache-pro") ], [ "disable", i("Google-lettertypen blokkeren", "ultracache-pro") ] ]
                }
            }), renderRow("enable_auto_font_preloads", {
                title: i("Kritieke fonts preloaden", "ultracache-pro"),
                description: i("Versnelt de eerste tekstweergave.", "ultracache-pro")
            }), autoFontPreloads ? inlineDetails("media-font-preloads", i("Extra lettertypebestanden", "ultracache-pro"), i("Alleen als een lokaal lettertype wordt gemist.", "ultracache-pro"), fontCount ? s(i("%d eigen regels", "ultracache-pro"), fontCount) : i("Geen eigen regels", "ultracache-pro"), renderRow("preload_fonts", {
                title: i("Eigen lettertypebestanden", "ultracache-pro"),
                description: i("Voeg per regel één bestaand lokaal WOFF2-bestand toe.", "ultracache-pro")
            }), i("Voorbeeld: /wp-content/uploads/fonts/inter-var.woff2", "ultracache-pro")) : null ].filter(Boolean), externalRows = [ r || UCPSettingEnabled(settings, "enable_local_gravatar") ? renderRow("enable_local_gravatar", {
                title: i("Profielfoto’s lokaal laden", "ultracache-pro"),
                description: i("Bewaart een lokale kopie.", "ultracache-pro")
            }) : null, r || UCPSettingEnabled(settings, "enable_local_youtube_thumbnails") ? renderRow("enable_local_youtube_thumbnails", {
                title: i("YouTube-voorbeelden lokaal laden", "ultracache-pro"),
                description: i("Laadt voorbeelden lokaal; video start na een klik.", "ultracache-pro")
            }) : null ].filter(Boolean), advancedImageRows = r || UCPSettingEnabled(settings, "enable_lqip") ? [ renderRow("enable_lqip", {
                title: i("Afbeeldingen", "ultracache-pro"),
                description: i("Toont kort een lichte voorvertoning tijdens laden.", "ultracache-pro")
            }) ].filter(Boolean) : [], advancedFontRows = r || UCPSettingEnabled(settings, "enable_font_unicode_ranges") ? [ renderRow("enable_font_unicode_ranges", {
                title: i("Lettertypen", "ultracache-pro"),
                description: i("Laadt alleen de benodigde taaltekens.", "ultracache-pro")
            }), renderRow("font_unicode_ranges", {
                title: i("Taaltekens kiezen", "ultracache-pro"),
                description: i("Kies het bereik dat de website werkelijk gebruikt.", "ultracache-pro")
            }) ].filter(Boolean) : [], advancedRiskActive = UCPSettingEnabled(settings, "enable_lqip") || UCPSettingEnabled(settings, "enable_font_unicode_ranges"), advancedGroups = [ renderAdvancedGroup("media-external", i("Externe media", "ultracache-pro"), i("Laadt externe voorbeelden lokaal.", "ultracache-pro"), externalRows, "ucp-media-advanced-group--external ucp-media-advanced-group--two-up"), renderAdvancedGroup("media-advanced-images", "", "", advancedImageRows, "ucp-media-advanced-group--compact"), renderAdvancedGroup("media-advanced-fonts", "", "", advancedFontRows, "ucp-media-advanced-group--compact") ].filter(Boolean);
            return a("div", {
                className: "ucp-settings-page ucp-settings-page--media ucp-cache-tools-page"
            }, UCPSectionHero({
                className: "ucp-section-hero--media",
                eyebrow: i("Afbeeldingen", "ultracache-pro"),
                title: i("Afbeeldingen & lettertypen", "ultracache-pro"),
                description: statusText,
                badge: statusBadge,
                badgeClass: statusBadgeClass,
                action: null
            }), a("div", {
                className: "ucp-settings-stack ucp-settings-stack--media"
            }, a("div", {
                className: "ucp-media-panel-list"
            }, renderPanel("media-uploads", i("Nieuwe afbeeldingen", "ultracache-pro"), i("Kies het formaat voor nieuwe uploads.", "ultracache-pro"), uploadBadge, uploadNeedsAttention ? "ucp-status-badge--warning" : "", uploadRows, uploadNeedsAttention), renderPanel("media-loading", i("Afbeeldingen slim laden", "ultracache-pro"), i("Laadt media op het juiste moment.", "ultracache-pro"), loadingNeedsAttention ? i("Controle nodig", "ultracache-pro") : null, loadingNeedsAttention ? "ucp-status-badge--warning" : "", loadingRows, !1), renderPanel("media-fonts", i("Lettertypen", "ultracache-pro"), i("Kies de laadwijze.", "ultracache-pro"), null, "", fontRows, !1), advancedGroups.length ? a("details", {
                className: "ucp-media-panel ucp-media-panel--advanced"
            }, a("summary", {
                className: "ucp-media-panel__summary"
            }, a("span", {
                className: "ucp-media-panel__copy"
            }, a("strong", {}, i("Compatibiliteitsopties", "ultracache-pro")), a("small", {}, i("Alleen wijzigen bij een zichtbaar probleem.", "ultracache-pro")))), a("div", {
                className: "ucp-media-panel__body ucp-media-panel__body--advanced"
            }, r && advancedRiskActive ? a("div", {
                className: "ucp-media-advanced-warning",
                role: "note"
            }, a("strong", {}, i("Controleer na wijziging", "ultracache-pro")), a("span", {}, i("Bekijk afbeeldingen en lettertypen op desktop en mobiel.", "ultracache-pro"))) : null, a("div", {
                className: "ucp-media-advanced-groups"
            }, advancedGroups))) : null)));
        }();
        if ("advanced" === e.kind) return function() {
            var t = o("advanced"), sectionDescriptions = {
                "Pagina’s nooit cachen": i("Sluit persoonlijke en dynamische pagina’s uit.", "ultracache-pro"),
                "Pagina’s altijd verversen": i("Invalideert gerelateerde overzichten na contentwijzigingen.", "ultracache-pro"),
                "Pagina’s niet vooraf opbouwen": i("Voorkom cache-opbouw voor persoonlijke, dynamische of niet-publieke routes.", "ultracache-pro"),
                "Technische uitsluitingen": i("Gebruik cookie- en browserregels alleen voor bevestigde siteflows.", "ultracache-pro"),
                "Browsernavigatie uitsluiten": i("Sluit persoonlijke en dynamische routes uit van prefetch en prerender.", "ultracache-pro"),
                "Query strings": i("Sta alleen publieke parameters zonder persoonsgebonden output toe.", "ultracache-pro")
            };
            function r(t) {
                if (!t || !UCPShouldRenderSetting(t[0], e.settings, e.status || {})) return null;
                var r = t.slice(), n = r[2] || "text", c = "ucp-settings-control ucp-settings-control--" + n + " ucp-settings-control--hide-primary-label";
                return "textarea" === n && (c += " ucp-settings-control--wide"), UCPSettingsRow({
                    key: r[0],
                    title: r[1],
                    description: r[3] || "",
                    className: UCPSettingsRowClass(r[0], n),
                    control: a("div", {
                        className: c
                    }, a(Ba, {
                        field: r,
                        kind: "advanced",
                        settings: e.settings,
                        status: e.status || {},
                        setSettings: e.setSettings,
                        addNotice: e.addNotice,
                        setStatus: e.setStatus,
                        hideInlineHelp: !0
                    }))
                });
            }
            function renderRuleGroup(group, index) {
                var fields = (group.fields || []).filter(function(field) {
                    return field && UCPShouldRenderSetting(field[0], e.settings, e.status || {});
                });
                return fields.length ? a("section", {
                    key: "rules-" + index,
                    className: "ucp-rules-primary-section ucp-rules-technical-section"
                }, a("header", {
                    className: "ucp-rules-primary-section__header"
                }, a("h3", {}, UCPGroupTitle(group)), a("p", {}, sectionDescriptions[group.title] || "")), a("div", {
                    className: "ucp-rules-primary-section__body ucp-settings-list"
                }, fields.map(r))) : null;
            }
            function renderRulesPanel(key, title, description, icon, groups, startIndex) {
                var sections = (groups || []).map(function(group, index) {
                    return renderRuleGroup(group, (startIndex || 0) + index);
                }).filter(Boolean);
                return sections.length ? a("details", {
                    key: key,
                    className: "ucp-rules-primary-panel ucp-rules-task-panel"
                }, a("summary", {
                    className: "ucp-rules-primary-panel__summary"
                }, a("span", {
                    className: "ucp-rules-panel-icon dashicons " + icon,
                    "aria-hidden": "true"
                }), a("span", {
                    className: "ucp-advanced-summary-copy"
                }, a("strong", {}, title), a("small", {}, description))), a("div", {
                    className: "ucp-rules-primary-panel__body"
                }, sections)) : null;
            }
            function renderPrimaryRuleSection(group, index) {
                var fields = (group.fields || []).filter(function(field) {
                    return field && UCPShouldRenderSetting(field[0], e.settings, e.status || {});
                });
                return fields.length ? a("section", {
                    key: "rules-primary-" + index,
                    className: "ucp-rules-primary-section"
                }, a("header", {
                    className: "ucp-rules-primary-section__header"
                }, a("h3", {}, UCPGroupTitle(group)), a("p", {}, sectionDescriptions[group.title] || "")), a("div", {
                    className: "ucp-rules-primary-section__body ucp-settings-list"
                }, fields.map(r))) : null;
            }
            var primaryGroups = c.filter(function(group) {
                return !group.advanced;
            }), technicalGroups = c.filter(function(group) {
                return !!group.advanced && "WordPress beheer" !== group.title;
            }), cookieGroups = technicalGroups.filter(function(group) {
                return "Technische uitsluitingen" === group.title;
            }), browserGroups = technicalGroups.filter(function(group) {
                return "Browsernavigatie uitsluiten" === group.title || "Query strings" === group.title;
            }), primarySections = primaryGroups.map(renderPrimaryRuleSection).filter(Boolean);
            return a("div", {
                className: "ucp-settings-page ucp-settings-page--advanced ucp-cache-tools-page"
            }, UCPSectionHero({
                eyebrow: t.eyebrow,
                title: t.title,
                description: t.text,
                badge: t.badge,
                badgeClass: t.badgeClass
            }), a("div", {
                className: "ucp-rules-overview-grid"
            }, primarySections.length ? a("details", {
                className: "ucp-rules-primary-panel ucp-rules-task-panel"
            }, a("summary", {
                className: "ucp-rules-primary-panel__summary"
            }, a("span", {
                className: "ucp-rules-panel-icon dashicons dashicons-admin-page",
                "aria-hidden": "true"
            }), a("span", {
                className: "ucp-advanced-summary-copy"
            }, a("strong", {}, i("Pagina’s en routes", "ultracache-pro")), a("small", {}, i("Beheer cache, vernieuwen en vooraf opbouwen in één overzicht.", "ultracache-pro")))), a("div", {
                className: "ucp-rules-primary-panel__body"
            }, primarySections)) : null, renderRulesPanel("rules-cookies", i("Cookies en browsers", "ultracache-pro"), i("Regels voor cookies en apparaten die echt andere content tonen.", "ultracache-pro"), "dashicons-shield", cookieGroups, primaryGroups.length), renderRulesPanel("rules-browser", i("Browser en parameters", "ultracache-pro"), i("Regels voor browsernavigatie en veilige URL-parameters.", "ultracache-pro"), "dashicons-filter", browserGroups, primaryGroups.length + cookieGroups.length)));
        }();
        var z = o(e.kind), A = a(d, {
            className: "ucp-card ucp-compact-hero"
        }, a(h, {}, a("div", {
            className: "ucp-compact-hero__inner"
        }, a("div", {}, a("span", {
            className: "ucp-eyebrow"
        }, z.eyebrow), a("h2", {}, z.title), a("p", {}, z.text)), z.badge ? a("span", {
            className: "ucp-status-badge " + z.badgeClass
        }, z.badge) : null))), groupDescriptions = {
            Afbeeldingen: i("Optimaliseer uploads zonder andere image optimizers te kruisen.", "ultracache-pro"),
            "Lazyload & LCP": i("Bescherm belangrijke beelden en laat alleen offscreen media later laden.", "ultracache-pro"),
            "Geavanceerde media-rendering": i("Gebruik dit alleen na visuele controle in een testomgeving.", "ultracache-pro"),
            "Font details": i("Gebruik dit alleen als lokale font-ranges bewust zijn ingericht.", "ultracache-pro"),
            Fonts: i("Maak fonts voorspelbaar en voorkom onnodige vertraging.", "ultracache-pro"),
            "Externe bronnen lokaal hosten": i("Alleen gebruiken als privacy, tracking en visuele output gecontroleerd zijn.", "ultracache-pro"),
            "Responsive image delivery": i("Vereist een CDN met width- en quality-transforms.", "ultracache-pro"),
            "Cache opbouwen": i("Kies hoe UltraCache pagina’s vooraf klaarzet.", "ultracache-pro"),
            "Navigatie versnellen": i("Versnelt navigatie voorzichtig, vooral bij gewone pagina’s.", "ultracache-pro"),
            Uitsluitingen: i("Sluit persoonlijke, checkout- of filterpagina’s uit.", "ultracache-pro"),
            "Pagina’s nooit cachen": i("Voorkom cache op persoonlijke, checkout-, filter- of portaalpagina’s.", "ultracache-pro"),
            "Pagina’s altijd verversen": i("Laat belangrijke overzichten automatisch mee legen na contentwijzigingen.", "ultracache-pro"),
            "Pagina’s niet vooraf opbouwen": i("Sluit zware, dynamische en niet-publieke routes uit van preload.", "ultracache-pro"),
            "Technische uitsluitingen": i("Alleen gebruiken wanneer cookies, taal of apparaat echt andere content geven.", "ultracache-pro"),
            "Query strings": i("Cache alleen veilige parameters die geen persoonlijke content tonen.", "ultracache-pro"),
            "Automatisch onderhoud": i("Kies of UltraCache database-opschoning mag plannen.", "ultracache-pro"),
            "Veilig opruimen": i("Kies alleen gegevens die zonder inhoudelijk verlies mogen worden verwijderd.", "ultracache-pro"),
            "Backup nodig": i("Gebruik deze opties alleen bewust en met een recente database-back-up.", "ultracache-pro"),
            "Geavanceerde instellingen": i("Toon technische opties alleen in geavanceerde modus.", "ultracache-pro"),
            Beheerinterface: i("Verberg ongebruikte WordPress-beheeronderdelen; er wordt niets verwijderd.", "ultracache-pro"),
            Diagnostiek: i("Schakel alleen controles in die je actief gebruikt voor foutopsporing.", "ultracache-pro"),
            Bezoekersmetingen: i("Meet echte prestaties zonder volledige URL’s of persoonsgegevens te bewaren.", "ultracache-pro"),
            "Renderer en achtergrondtaken": i("Developer-only: externe renderdiensten, ESI en handmatige taakverwerking.", "ultracache-pro"),
            Bewaartermijnen: i("Beperk technische data tot een korte, doelgerichte bewaartermijn.", "ultracache-pro"),
            Verwijderen: i("Bepaal bewust wat bij deïnstallatie met de configuratie gebeurt.", "ultracache-pro")
        }, databaseSafeKeys = [ "db_cleanup_post_revisions", "db_cleanup_auto_drafts", "db_cleanup_trashed_posts", "db_cleanup_spam_comments", "db_cleanup_trashed_comments", "db_cleanup_expired_transients", "db_cleanup_wc_sessions" ], databaseSafeSelected = databaseSafeKeys.filter(function(key) {
            return UCPShouldRenderSetting(key, e.settings, e.status || {}) && UCPSettingEnabled(e.settings || {}, key);
        }).length, compactGroupClass = function(title) {
            return String(title || "group").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "group";
        };
        function renderCompactGroup(group, index) {
            var groupClass = "ucp-compact-card--" + compactGroupClass(group.title), fieldItems = (group.fields || []).map(k).filter(Boolean);
            if (!fieldItems.length) return null;
            var fieldGrid = a("div", {
                className: getFieldGridClass({ fields: fieldItems })
            }, fieldItems), card = a(d, {
                key: (group.title || "group") + "-" + index,
                className: w(group) + " " + groupClass
            }, a(p, {}, a("div", {
                className: "ucp-compact-section-head"
            }, a("div", {}, group.advanced ? a("span", {
                className: "ucp-eyebrow"
            }, i("Support", "ultracache-pro")) : null, a("h2", {}, UCPGroupTitle(group)), groupDescriptions[group.title] ? a("p", {}, groupDescriptions[group.title]) : null), group.advanced ? a("span", {
                className: "ucp-status-badge ucp-status-badge--neutral"
            }, i("Technisch", "ultracache-pro")) : null)), a(h, {}, fieldGrid));
            if ("database" === e.kind && "Veilig opruimen" === group.title) {
                var selectedLabel = 1 === databaseSafeSelected ? i("1 keuze actief", "ultracache-pro") : s(i("%d keuzes actief", "ultracache-pro"), databaseSafeSelected);
                return a("details", {
                    key: "database-selection-" + index,
                    className: "ucp-db-selection-panel"
                }, a("summary", {}, a("span", {
                    className: "ucp-advanced-summary-copy"
                }, a("strong", {}, i("Selectie aanpassen", "ultracache-pro")), a("small", {}, i("Kies wat mag worden opgeruimd.", "ultracache-pro"))), a(ee, {
                    state: databaseSafeSelected ? "good" : "info"
                }, selectedLabel)), a("div", {
                    className: "ucp-db-selection-panel__body " + groupClass
                }, databaseSafeSelected ? null : a("div", {
                    className: "ucp-db-selection-empty",
                    role: "status"
                }, a("span", {
                    className: "dashicons dashicons-info-outline",
                    "aria-hidden": "true"
                }), a("p", {}, a("strong", {}, i("Nog niets geselecteerd.", "ultracache-pro")), " ", i("Kies hieronder alleen de onderdelen die je wilt opschonen.", "ultracache-pro"))), fieldGrid));
            }
            if ("database" === e.kind && "Backup nodig" === group.title) return a("details", {
                key: "database-risk-" + index,
                className: "ucp-db-risk-panel"
            }, a("summary", {}, a("span", {
                className: "ucp-advanced-summary-copy"
            }, a("strong", {}, i("Risicovolle database-opties", "ultracache-pro")), a("small", {}, i("Alleen met een recente back-up.", "ultracache-pro"))), a(ee, {
                state: "warning"
            }, i("Backup nodig", "ultracache-pro"))), a("div", {
                className: "ucp-db-risk-panel__body " + groupClass
            }, fieldGrid));
            if ("advanced" === e.kind && "Renderer en achtergrondtaken" === group.title) return a("details", {
                key: "advanced-developer-" + index,
                className: "ucp-compact-details-panel ucp-compact-details-panel--developer"
            }, a("summary", {}, a("span", {
                className: "ucp-advanced-summary-copy"
            }, a("strong", {}, UCPGroupTitle(group)), a("small", {}, groupDescriptions[group.title] || i("Open alleen wanneer je deze developer-opties bewust nodig hebt.", "ultracache-pro"))), a(ee, {
                state: "warning"
            }, i("Developer-only", "ultracache-pro"))), a("div", {
                className: "ucp-compact-details-panel__body " + groupClass
            }, fieldGrid));
            return card;
        }
        function renderDatabaseCounts() {
            if ("database" !== e.kind) return null;
            var counts = getDatabaseCounts();
            function count(key) {
                return parseInt(counts[key] || 0, 10) || 0;
            }
            var cleanupCandidates = count("auto_drafts") + count("drafts") + count("trash_posts") + count("spam_comments") + count("trash_comments"), metrics = [ {
                key: "revisions",
                label: i("revisies", "ultracache-pro"),
                value: count("revisions")
            }, {
                key: "cleanup_candidates",
                label: i("opschoonbaar", "ultracache-pro"),
                value: cleanupCandidates
            }, {
                key: "expired_transients",
                label: i("verlopen data", "ultracache-pro"),
                value: count("expired_transients")
            }, {
                key: "optimizable_tables",
                label: i("tabellen met overhead", "ultracache-pro"),
                value: count("optimizable_tables")
            } ];
            return a("div", {
                className: "ucp-db-workspace__overview"
            }, a("div", {
                className: "ucp-db-workspace__heading"
            }, a("div", {}, a("h2", {}, i("Database-status", "ultracache-pro")), a("p", {}, i("Controleer alleen details wanneer je onderhoud wilt uitvoeren.", "ultracache-pro")))), a("div", {
                className: "ucp-db-statline",
                role: "list",
                "aria-label": i("Database-overzicht", "ultracache-pro")
            }, metrics.map(function(metric) {
                return a("span", {
                    className: "ucp-db-statline__item",
                    key: metric.key,
                    role: "listitem"
                }, a("strong", {}, String(metric.value)), " ", a("span", {}, metric.label));
            })));
        }
        function renderDatabaseWorkspace() {
            var scheduleGroup = c.find(function(group) { return "Automatisch onderhoud" === group.title; }), safeGroup = c.find(function(group) { return "Veilig opruimen" === group.title; }), riskGroup = c.find(function(group) { return "Backup nodig" === group.title; }), counts = getDatabaseCounts();
            function count(key) {
                return parseInt(counts[key] || 0, 10) || 0;
            }
            var safeItemCount = count("revisions") + count("auto_drafts") + count("trash_posts") + count("spam_comments") + count("trash_comments") + count("expired_transients") + count("wc_sessions"), hasCleanupItems = safeItemCount > 0, selectedSummary = 1 === databaseSafeSelected ? i("1 keuze actief", "ultracache-pro") : s(i("%d keuzes actief", "ultracache-pro"), databaseSafeSelected), foundSummary = 1 === safeItemCount ? i("1 item gevonden", "ultracache-pro") : s(i("%d items gevonden", "ultracache-pro"), safeItemCount), optionPanels = [ safeGroup ? renderCompactGroup(safeGroup, c.indexOf(safeGroup)) : null, riskGroup ? renderCompactGroup(riskGroup, c.indexOf(riskGroup)) : null ].filter(Boolean);
            return a("section", {
                className: "ucp-db-workspace"
            }, renderDatabaseCounts(), a("div", {
                className: "ucp-db-maintenance-panel"
            }, a("div", {
                className: "ucp-db-maintenance-panel__schedule"
            }, a("div", {
                className: "ucp-db-panel-heading"
            }, a("h3", {}, i("Automatisch onderhoud", "ultracache-pro")), a("p", {}, i("Kies hoe vaak dit gebeurt.", "ultracache-pro"))), a("div", {
                className: "ucp-db-schedule-control"
            }, scheduleGroup ? (scheduleGroup.fields || []).map(k) : null)), a("div", {
                className: "ucp-db-maintenance-panel__action"
            }, a("div", {
                className: "ucp-db-safe-action__copy",
                role: "status",
                "aria-live": "polite"
            }, a("h3", {}, i("Veilig opschonen", "ultracache-pro")), a("p", {}, selectedSummary + " · " + foundSummary), hasCleanupItems ? databaseSafeSelected ? null : a("small", {}, i("Kies eerst onderdelen via Selectie aanpassen.", "ultracache-pro")) : a("small", {}, i("De laatste scan vond niets dat veilig kan worden opgeschoond.", "ultracache-pro"))), a(Pe, {
                action: "database-cleanup",
                label: hasCleanupItems ? databaseSafeSelected ? i("Opschonen", "ultracache-pro") : i("Selecteer eerst", "ultracache-pro") : i("Niets op te schonen", "ultracache-pro"),
                variant: "primary",
                disabled: !hasCleanupItems || databaseSafeSelected < 1,
                destructive: !0,
                confirm: !0,
                confirmText: i("Controleer de selectie; deze actie is onomkeerbaar.", "ultracache-pro"),
                confirmBackup: !0,
                preview: (e.status || {}).databaseCleanup || {},
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                onComplete: e.onRefresh,
                compact: !0
            }))), optionPanels.length ? a("div", {
                className: "ucp-db-option-panels"
            }, optionPanels) : null);
        }
        function renderDiagnostics() {
            function diagnosticGroup(title) {
                return n.find(function(group) {
                    return title === group.title;
                }) || null;
            }
            function diagnosticRow(field) {
                if (!field || !UCPShouldRenderSetting(field[0], e.settings, e.status || {})) return null;
                var definition = field.slice(), type = definition[2] || "text", controlClass = "ucp-settings-control ucp-settings-control--" + type + " ucp-settings-control--hide-primary-label";
                "toggle" === type && (controlClass += " ucp-settings-control--toggle");
                return UCPSettingsRow({
                    key: definition[0],
                    title: definition[1],
                    description: definition[3] || "",
                    className: "ucp-diagnostics-row ucp-settings-row--control-" + D(type),
                    control: a("div", {
                        className: controlClass
                    }, a(Ba, {
                        field: definition,
                        kind: "diagnostics",
                        settings: e.settings,
                        status: e.status || {},
                        setSettings: e.setSettings,
                        addNotice: e.addNotice,
                        setStatus: e.setStatus,
                        hideInlineHelp: !0
                    }))
                });
            }
            function diagnosticAction(action, label, description, icon, variant) {
                return a("article", {
                    className: "ucp-diagnostic-action",
                    key: action
                }, a("div", {
                    className: "ucp-diagnostic-action__copy"
                }, a("span", {
                    className: "dashicons " + icon,
                    "aria-hidden": "true"
                }), a("div", {}, a("strong", {}, label), a("p", {}, description))), a(Pe, {
                    action: action,
                    label: label,
                    busyLabel: i("Controleren…", "ultracache-pro"),
                    pendingMessage: description,
                    variant: variant || "secondary",
                    compact: !0,
                    addNotice: e.addNotice,
                    setStatus: e.setStatus,
                    onComplete: e.onRefresh
                }));
            }
            function statusCard(key, title, description, icon, state, label, generatedAt) {
                return a("article", {
                    className: "ucp-diagnostic-card ucp-diagnostic-card--" + state,
                    key: key
                }, a("div", {
                    className: "ucp-diagnostic-card__header"
                }, a("span", {
                    className: "dashicons " + icon,
                    "aria-hidden": "true"
                }), a(ee, {
                    state: state
                }, label)), a("div", {
                    className: "ucp-diagnostic-card__copy"
                }, a("h3", {}, title), a("p", {}, description)), generatedAt ? a("small", {
                    className: "ucp-diagnostic-card__time"
                }, i("Laatst uitgevoerd:", "ultracache-pro") + " " + UCPFormatAdminDate(generatedAt)) : null);
            }
            var status = e.status || {}, quality = status.quality || {}, website = quality.websiteCheck || {}, runtime = quality.runtimeTest || {}, health = status.health || {}, support = quality.supportMode || {}, websiteState = "good" === website.state ? "good" : "failed" === website.state ? "error" : website.generatedAt ? "warning" : "info", websiteLabel = website.generatedAt ? "good" === website.state ? i("In orde", "ultracache-pro") : "failed" === website.state ? i("Problemen gevonden", "ultracache-pro") : i("Aandacht nodig", "ultracache-pro") : i("Nog niet uitgevoerd", "ultracache-pro"), runtimeResult = String(((runtime.home || {}).result || "")), runtimeState = "hit_or_cached_signal" === runtimeResult ? "good" : "failed" === runtimeResult ? "error" : runtime.generated_at ? "warning" : "info", runtimeLabel = "hit_or_cached_signal" === runtimeResult ? i("Cachehit gemeten", "ultracache-pro") : "reachable_no_hit_header" === runtimeResult ? i("Bereikbaar, nog geen hit", "ultracache-pro") : "failed" === runtimeResult ? i("Test mislukt", "ultracache-pro") : i("Nog niet uitgevoerd", "ultracache-pro"), healthHasSnapshot = !!health.generated_at, healthNeedsAttention = healthHasSnapshot && (!health.cache_dir_writable || parseInt(health.jobs_failed || 0, 10) > 0), healthState = healthNeedsAttention ? "warning" : healthHasSnapshot ? "good" : "info", healthLabel = healthNeedsAttention ? i("Aandacht nodig", "ultracache-pro") : healthHasSnapshot ? i("In orde", "ultracache-pro") : i("Nog niet uitgevoerd", "ultracache-pro"), diagnosticsGroup = diagnosticGroup("Diagnostiek"), retentionGroup = diagnosticGroup("Bewaartermijnen"), technicalRows = ((diagnosticsGroup ? diagnosticsGroup.fields || [] : []).concat(retentionGroup ? retentionGroup.fields || [] : [])).map(diagnosticRow).filter(Boolean);
            return a("div", {
                className: "ucp-diagnostics-workspace"
            }, a("header", {
                className: "ucp-diagnostics-header"
            }, a("div", {
                className: "ucp-diagnostics-header__icon",
                "aria-hidden": "true"
            }, a("span", {
                className: "dashicons dashicons-chart-area"
            })), a("div", {
                className: "ucp-diagnostics-header__copy"
            }, a("h2", {}, i("Diagnostiek", "ultracache-pro")), a("p", {}, i("Controleer de website, cachewerking en serverstatus zonder instellingen te wijzigen.", "ultracache-pro"))), support.active ? a(ee, {
                state: "warning"
            }, i("Supportmodus actief", "ultracache-pro")) : null), a("div", {
                className: "ucp-diagnostics-status-grid"
            }, statusCard("website", i("Websitecontrole", "ultracache-pro"), website.generatedAt ? s(i("%1$d geslaagd · %2$d waarschuwingen · %3$d fouten", "ultracache-pro"), parseInt(website.passed || 0, 10), parseInt(website.warnings || 0, 10), parseInt(website.failed || 0, 10)) : i("Controleert cache, koppelingen, conflicten en achtergrondtaken.", "ultracache-pro"), "dashicons-admin-site-alt3", websiteState, websiteLabel, website.generatedAt), statusCard("runtime", i("Cachewerking", "ultracache-pro"), runtime.generated_at ? i("Test twee openbare verzoeken en controleert het cachesignaal.", "ultracache-pro") : i("Meet of de homepage bereikbaar is en een cachehit teruggeeft.", "ultracache-pro"), "dashicons-performance", runtimeState, runtimeLabel, runtime.generated_at), statusCard("health", i("Servercontrole", "ultracache-pro"), healthHasSnapshot ? healthNeedsAttention ? i("Controleer schrijfrechten of mislukte achtergrondtaken.", "ultracache-pro") : i("Schrijfrechten en achtergrondtaken geven geen direct probleem aan.", "ultracache-pro") : i("Controleert schrijfrechten, cachebestanden en achtergrondtaken.", "ultracache-pro"), "dashicons-shield-alt", healthState, healthLabel, health.generated_at)), a("section", {
                className: "ucp-diagnostics-actions",
                "aria-labelledby": "ucp-diagnostics-actions-title"
            }, a("header", {
                className: "ucp-diagnostics-actions__header"
            }, a("div", {}, a("h3", {
                id: "ucp-diagnostics-actions-title"
            }, i("Controles uitvoeren", "ultracache-pro")), a("p", {}, i("De controles lezen de actuele status en tonen daarna het resultaat hierboven.", "ultracache-pro")))), a("div", {
                className: "ucp-diagnostics-action-grid"
            }, diagnosticAction("website-check", i("Website controleren", "ultracache-pro"), i("Controleert de complete configuratie en bekende conflicten.", "ultracache-pro"), "dashicons-admin-site-alt3", "primary"), diagnosticAction("runtime-cache-test", i("Cachewerking testen", "ultracache-pro"), i("Meet de homepage tweemaal en controleert het cachesignaal.", "ultracache-pro"), "dashicons-performance"), diagnosticAction("health-check", i("Servercontrole uitvoeren", "ultracache-pro"), i("Controleert cachebestanden, schrijfrechten en achtergrondtaken.", "ultracache-pro"), "dashicons-shield-alt"))), a("details", {
                className: "ucp-settings-advanced ucp-diagnostics-support",
                open: support.active ? !0 : void 0
            }, a("summary", {}, a("span", {
                className: "ucp-advanced-summary-copy"
            }, a("strong", {}, i("Tijdelijke supportmodus", "ultracache-pro")), a("small", {}, support.active ? i("Extra logging en diagnostiek zijn tijdelijk actief.", "ultracache-pro") : i("Alleen gebruiken voor gericht onderzoek of support.", "ultracache-pro"))), a(ee, {
                state: support.active ? "warning" : "disabled"
            }, support.active ? i("Actief", "ultracache-pro") : i("Uit", "ultracache-pro"))), a("div", {
                className: "ucp-settings-advanced__body"
            }, a("div", {
                className: "ucp-diagnostics-support__content"
            }, a("div", {
                className: "ucp-diagnostics-support__copy"
            }, a("strong", {}, support.active ? i("Extra informatie wordt verzameld", "ultracache-pro") : i("Meer informatie nodig?", "ultracache-pro")), a("p", {}, support.active ? i("Stop de modus zodra het onderzoek klaar is. Eerdere diagnostiekinstellingen worden hersteld.", "ultracache-pro") : i("De modus schakelt tijdelijke logging en controles in en stopt automatisch na 30 minuten.", "ultracache-pro")), support.active && support.until ? a("small", {}, i("Actief tot", "ultracache-pro") + " " + UCPFormatAdminDate(1e3 * parseInt(support.until, 10))) : null), a("div", {
                className: "ucp-diagnostics-support__action"
            }, a(Pe, {
                action: support.active ? "disable-debug-mode" : "enable-debug-mode",
                label: support.active ? i("Supportmodus stoppen", "ultracache-pro") : i("Supportmodus 30 min starten", "ultracache-pro"),
                variant: "secondary",
                compact: !0,
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                onComplete: e.onRefresh
            }))), technicalRows.length ? a("details", {
                className: "ucp-diagnostics-technical-settings"
            }, a("summary", {}, i("Technische diagnostiekinstellingen", "ultracache-pro")), a("div", {
                className: "ucp-settings-list"
            }, technicalRows)) : null)));
        }
        return a("div", {
            className: "ucp-settings-page ucp-settings-page--" + e.kind + " ucp-compact-page ucp-compact-page--" + e.kind + " ucp-cache-tools-page"
        }, e.hideHero ? null : A, "database" === e.kind ? renderDatabaseWorkspace() : "diagnostics" === e.kind ? renderDiagnostics() : a("div", {
            className: "ucp-compact-card-grid"
        }, c.map(renderCompactGroup)));
    }
    function Je(e) {
        var t = Ve.cache || [];
        function r(e) {
            return We(t, e);
        }
        function n(e, a) {
            var t = r(e);
            return t ? (t = t.slice(), (a = a || {}).label && (t[1] = a.label), a.help && (t[3] = a.help),
            t) : null;
        }
        function c(t, r) {
            var c = n(t, r);
            return c ? a("div", {
                className: "ucp-woo-setting-row ucp-cache-tools-option"
            }, a(Ba, {
                field: c,
                kind: "cache",
                settings: e.settings,
                status: e.status || {},
                setSettings: e.setSettings,
                addNotice: e.addNotice,
                setStatus: e.setStatus
            })) : null;
        }
        var o = se(e.settings, "enable_woocommerce_rules") && se(e.settings, "woocommerce_safety_mode"), s = se(e.settings, "optimize_cart_fragments"), l = [ [ "cart", i("Winkelwagen", "ultracache-pro"), i("Winkelwagenpagina’s worden niet agressief gecachet.", "ultracache-pro") ], [ "checkout", i("Checkout", "ultracache-pro"), i("Betaalflow blijft betrouwbaar.", "ultracache-pro") ], [ "account", i("Account", "ultracache-pro"), i("Persoonlijke pagina’s blijven vers.", "ultracache-pro") ], [ "payment", i("Betalingen", "ultracache-pro"), i("Betaalscripts blijven beschikbaar.", "ultracache-pro") ], [ "gallery", i("Productmedia", "ultracache-pro"), i("Galerijen en productbeelden blijven beschermd.", "ultracache-pro") ] ];
        return a("div", {
            className: "ucp-settings-page ucp-settings-page--woocommerce ucp-compact-page ucp-woo-page ucp-cache-tools-page"
        }, a(d, {
            className: "ucp-card ucp-compact-hero ucp-woo-hero ucp-tools-hero ucp-cache-tools-hero"
        }, a(h, {}, a("div", {
            className: "ucp-compact-hero__inner"
        }, a("div", {}, a("span", {
            className: "ucp-eyebrow"
        }, i("Webshop", "ultracache-pro")), a("h2", {}, i("Webshop veilig versnellen", "ultracache-pro")), a("p", {}, i("Versnel de webshop zonder winkelwagen, betalen of accounts te verstoren.", "ultracache-pro"))), a(ee, {
            state: o ? "good" : "warning"
        }, o ? i("Bescherming actief", "ultracache-pro") : i("Bescherming controleren", "ultracache-pro"))))), a("div", {
            className: "ucp-woo-settings-grid"
        }, a(d, {
            className: "ucp-card ucp-compact-card ucp-woo-settings-card ucp-cache-tools-card"
        }, a(p, {}, a("div", {
            className: "ucp-compact-section-head"
        }, a("div", {}, a("h2", {}, i("Veilige cache", "ultracache-pro")), a("p", {}, i("Houd dynamische webshoproutes betrouwbaar.", "ultracache-pro"))))), a(h, {}, a("div", {
            className: "ucp-woo-setting-list"
        }, c("enable_woocommerce_rules", {
            label: i("Webshop beschermen", "ultracache-pro"),
            help: i("Beschermt winkelwagen, betalen en accounts automatisch.", "ultracache-pro")
        }), c("disable_logged_in_optimizations", {
            label: i("Ingelogde gebruikers beschermen", "ultracache-pro"),
            help: i("Voorkomt storende optimalisaties tijdens beheer, accountgebruik en aankopen.", "ultracache-pro")
        })))), o ? a(d, {
            className: "ucp-card ucp-compact-card ucp-woo-settings-card ucp-cache-tools-card"
        }, a(p, {}, a("div", {
            className: "ucp-compact-section-head"
        }, a("div", {}, a("h2", {}, i("Winkelwagen sneller laden", "ultracache-pro")), a("p", {}, i("Versnel lege winkelwagens zonder gevulde manden te verstoren.", "ultracache-pro"))))), a(h, {}, a("div", {
            className: "ucp-woo-setting-list"
        }, c("optimize_cart_fragments", {
            label: i("Winkelwagen sneller laden", "ultracache-pro"),
            help: i("Vermindert onnodig laden wanneer de winkelwagen leeg is.", "ultracache-pro")
        }), c("limit_cart_fragments_to_woo", {
            label: i("Alleen laden waar nodig", "ultracache-pro"),
            help: i("Laadt WooCommerce winkelwagenscripts alleen op relevante pagina’s.", "ultracache-pro")
        })))) : null, a(d, {
            className: "ucp-card ucp-woo-protection-card ucp-cache-tools-card"
        }, a(p, {}, a("div", {
            className: "ucp-section-heading ucp-woo-section-heading"
        }, a("div", {}, a("h2", {}, i("Bescherming", "ultracache-pro")), a("p", {}, i("Gevoelige webshoponderdelen blijven beschermd.", "ultracache-pro"))), a(ee, {
            state: o ? "good" : "warning"
        }, o ? s ? i("Cart geoptimaliseerd", "ultracache-pro") : i("Veilig", "ultracache-pro") : i("Controle nodig", "ultracache-pro")))), a(h, {}, a("div", {
            className: "ucp-woo-protection-list"
        }, l.map(function(e) {
            return a("div", {
                key: e[0],
                className: "ucp-status-row ucp-woo-protection-row"
            }, a("span", {}, a("strong", {}, e[1]), a("small", {}, e[2])), a(ee, {
                state: o ? "good" : "warning"
            }, o ? i("Veilig", "ultracache-pro") : i("Controle nodig", "ultracache-pro")));
        }))))));
    }
    function He(e) {
        var t = (Ve.optimization || []).concat(Ve.server || []);
        function r(e) {
            return We(t, e);
        }
        function n(t, n) {
            var c = r(t);
            if (!c) return null;
            c = c.slice(), (n = n || {}).label && (c[1] = n.label), void 0 !== n.help && (c[3] = n.help);
            var o = c[2] || "text", i = "ucp-settings-control ucp-settings-control--" + o + " ucp-settings-control--hide-primary-label";
            return "textarea" === o && (i += " ucp-settings-control--wide"), a("div", {
                className: "ucp-settings-control-stack"
            }, a("div", {
                className: i
            }, a(Ba, {
                field: c,
                kind: "optimization",
                settings: e.settings,
                status: e.status || {},
                setSettings: e.setSettings,
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                hideRiskBadge: !0,
                hideInlineHelp: !0
            })), n.after || null);
        }
        function c(t, c) {
            var o = r(t);
            if (!o || !UCPShouldRenderSetting(t, e.settings || {})) return null;
            c = c || {};
            var i = UCPSettingsRowClass(t, o[2] || "text", c.className || "");
            return UCPSettingsRow({
                key: t,
                title: c.title || o[1],
                description: void 0 !== c.description ? c.description : o[3],
                meta: UCPRiskIsActive(t, xa(t, e.settings || {})) ? a(Ma, {
                    settingKey: t
                }) : null,
                className: i,
                control: n(t, c)
            });
        }
        var o = e.settings || {}, l = (e.status || {}).cache || {}, optimizationStatus = (e.status || {}).optimization || {}, q = l.objectCacheDetail || {}, cdnMode = String(xa("cdn_rewrite_mode", o) || "off"), u = "off" !== cdnMode, f = !!(q.enabled || l.objectCache), pendingActivation = !f && !!(q.activation_pending || q.reload_required), v = String(q.dropin_owner || ""), externalOwner = "other" === v, _ = !!q.redis, k = !!q.redis_connected, y = !!(q.apcu_available || q.apcu), E = _ && !k, D = f || pendingActivation || k || y || externalOwner, I = String(o.cdn_cnames || "").trim(), A = u && !I, R = String(q.recommended_backend || ""), reasonKey = String(q.redis_reason || "unknown"), reasonLabel = {
            extension_missing: i("PHP Redis-extensie ontbreekt", "ultracache-pro"),
            connect_failed: i("verbinding mislukt", "ultracache-pro"),
            auth_failed: i("authenticatie mislukt", "ultracache-pro"),
            database_failed: i("Redis-database kon niet worden geselecteerd", "ultracache-pro"),
            ping_failed: i("Redis reageert niet op ping", "ultracache-pro"),
            connected: i("verbonden", "ultracache-pro"),
            unknown: i("onbekende verbindingsfout", "ultracache-pro")
        }[reasonKey] || i("onbekende verbindingsfout", "ultracache-pro"), autoReady = "redis" === R || "apcu" === R, P = pendingActivation ? i("Activering controleren", "ultracache-pro") : externalOwner ? f ? i("Actief via externe drop-in", "ultracache-pro") : i("Externe drop-in aanwezig", "ultracache-pro") : f ? "ucp-redis" === v ? i("Actief via Redis", "ultracache-pro") : "ucp-apcu" === v ? i("Actief via APCu", "ultracache-pro") : i("Actief", "ultracache-pro") : autoReady ? i("Klaar voor automatische configuratie", "ultracache-pro") : E ? i("Redis niet bereikbaar", "ultracache-pro") : i("Niet beschikbaar", "ultracache-pro"), T = pendingActivation ? i("Drop-in geplaatst. Controleer de status in een nieuwe request.", "ultracache-pro") : externalOwner ? i("Externe object-cache.php gedetecteerd; UltraCache overschrijft deze niet.", "ultracache-pro") : f ? i("WordPress gebruikt momenteel een persistente object-cache.", "ultracache-pro") : "apcu" === R && E ? s(i("Redis is niet bereikbaar (%s), maar APCu is beschikbaar als veilige fallback.", "ultracache-pro"), reasonLabel) : E ? s(i("Redis is aanwezig maar niet bereikbaar: %s.", "ultracache-pro"), reasonLabel) : autoReady ? i("Veilige object-cachebackend gedetecteerd; installatie is beschikbaar.", "ultracache-pro") : i("Geen bereikbare Redis- of APCu-backend; geen wijzigingen uitgevoerd.", "ultracache-pro"), C = externalOwner ? i("Beheer via de eigenaar van de drop-in", "ultracache-pro") : "existing" === R ? i("Bestaande object cache behouden", "ultracache-pro") : "redis" === R ? i("Redis gebruiken", "ultracache-pro") : "apcu" === R ? i("APCu gebruiken", "ultracache-pro") : i("Geen veilige backend gevonden", "ultracache-pro"), supportEnabled = f && se(o, "enable_object_cache_support"), canConfigure = !pendingActivation && (f || !externalOwner && autoReady), configureLabel = externalOwner && f ? i("Integratie synchroniseren", "ultracache-pro") : f ? i("Instelling synchroniseren", "ultracache-pro") : "redis" === R ? i("Redis veilig instellen", "ultracache-pro") : "apcu" === R ? i("APCu veilig instellen", "ultracache-pro") : i("Object cache automatisch instellen", "ultracache-pro"), configureText = externalOwner && f ? i("Externe persistente objectcache actief; UltraCache behoudt de drop-in.", "ultracache-pro") : "redis" === R ? i("Redis bereikbaar. Bevestiging plaatst alleen de UltraCache Redis-drop-in.", "ultracache-pro") : "apcu" === R ? i("APCu beschikbaar. Bevestiging plaatst alleen de UltraCache APCu-drop-in.", "ultracache-pro") : i("Detecteert een backend en behoudt externe object-cache.php-bestanden.", "ultracache-pro");
        var resourceHintRows = [ c("enable_auto_resource_hints", {
            title: i("Externe domeinen sneller verbinden", "ultracache-pro"),
            description: i("Voegt beperkte preconnect- en DNS-prefetch-hints toe.", "ultracache-pro")
        }) ].filter(Boolean), x = [ c("cdn_rewrite_mode", {
            title: i("CDN gebruiken voor", "ultracache-pro"),
            description: i("Kies welke statische bestanden via een eigen CDN-URL of CNAME lopen.", "ultracache-pro")
        }), c("cdn_cnames", {
            title: i("CDN URL / CNAME", "ultracache-pro"),
            description: i("Eén vertrouwd CDN-domein per regel; leeg bij host- of proxyrewrites.", "ultracache-pro"),
            after: A ? a("p", {
                className: "ucp-inline-validation"
            }, i("CDN-herschrijven staat aan, maar er is nog geen domein ingevuld.", "ultracache-pro")) : null
        }) ].filter(Boolean), browserCacheRows = [ c("browser_cache_mode"), c("cache_control_max_age") ].filter(Boolean), provider = String(o.cdn_provider || "none"), imageCdnActive = UCPSettingEnabled(o, "enable_image_cdn"), browserCacheMode = String(xa("browser_cache_mode", o) || "off"), serverAdvancedVisible = UCPAdvancedSettingsVisible(o) || imageCdnActive || "none" !== provider || "off" !== browserCacheMode || UCPSettingEnabled(o, "enable_compat_updates") || UCPSettingEnabled(o, "enable_host_cache_purge"), cloudflareZone = String(o.cloudflare_zone_id || "").trim(), bunnyZone = String(o.bunny_pull_zone_id || "").trim(), providerReady = "none" === provider || "cloudflare" === provider && (!!(optimizationStatus.cloudflare || {}).apiConfigured || /^[a-f0-9]{32}$/i.test(cloudflareZone) && !!String(o.cloudflare_api_token || "").trim()) || "bunny" === provider && /^\d+$/.test(bunnyZone) && !!String(o.bunny_api_key || "").trim() || "generic" === provider && /^https:\/\//i.test(String(o.cdn_purge_webhook || "").trim()), imageCdnRows = [ c("enable_image_cdn"), c("enable_image_cdn_transforms"), c("enable_adaptive_image_srcset"), c("image_cdn_transform_provider"), c("image_cdn_base"), c("image_cdn_query"), c("image_cdn_widths") ].filter(Boolean), providerRows = [ c("cdn_provider"), c("cloudflare_zone_id"), c("cloudflare_api_token"), c("bunny_pull_zone_id"), c("bunny_api_key"), c("cdn_purge_webhook"), c("cdn_purge_webhook_token"), "none" !== provider ? UCPSettingsRow({
            key: "cdn-provider-readiness",
            title: i("Providerstatus", "ultracache-pro"),
            description: providerReady ? i("De vereiste providergegevens zijn ingevuld.", "ultracache-pro") : i("Vul eerst alle vereiste providergegevens in; CDN-purges worden anders overgeslagen.", "ultracache-pro"),
            control: a(ee, {
                state: providerReady ? "good" : "warning"
            }, providerReady ? i("Klaar", "ultracache-pro") : i("Configuratie ontbreekt", "ultracache-pro"))
        }) : null, c("enable_compat_updates"), c("compat_update_url"), c("enable_host_cache_purge") ].filter(Boolean), B = [ UCPSettingsRow({
            key: "object-cache-runtime-status",
            title: i("Huidige status", "ultracache-pro"),
            description: T,
            control: a(ee, {
                state: f ? "good" : pendingActivation ? "warning" : E ? "error" : D ? "warning" : "info"
            }, P)
        }), f || autoReady || externalOwner ? UCPSettingsRow({
            key: "object-cache-configuration",
            title: f ? i("Integratie", "ultracache-pro") : externalOwner ? i("Beheer", "ultracache-pro") : i("Veilige configuratie", "ultracache-pro"),
            description: f ? i("Gebruikt de actieve persistente objectcache voor tags en interne opslag.", "ultracache-pro") : externalOwner ? i("Behoudt de bestaande drop-in en wijzigt alleen de integratieoptie.", "ultracache-pro") : i("Automatisch: behoud bestaande drop-in, anders Redis, daarna APCu.", "ultracache-pro"),
            control: a(ee, {
                state: supportEnabled ? "good" : f ? "warning" : autoReady ? "good" : "warning"
            }, supportEnabled ? i("Automatisch actief", "ultracache-pro") : f ? i("Synchronisatie nodig", "ultracache-pro") : C)
        }) : null ].filter(Boolean), G = [ UCPSettingsRow({
            key: "object-cache-detected-backend",
            title: i("Beschikbare object-cache", "ultracache-pro"),
            description: i("Dit is de backend die UltraCache nu ziet voor persistente object-cache.", "ultracache-pro"),
            control: a(ee, {
                state: f || autoReady ? "good" : externalOwner ? "warning" : "info"
            }, externalOwner ? i("Externe drop-in", "ultracache-pro") : f ? "ucp-redis" === v ? i("Redis actief", "ultracache-pro") : "ucp-apcu" === v ? i("APCu actief", "ultracache-pro") : i("Persistente cache actief", "ultracache-pro") : "redis" === R ? i("Redis beschikbaar", "ultracache-pro") : "apcu" === R ? i("APCu beschikbaar", "ultracache-pro") : i("Geen backend", "ultracache-pro"))
        }), UCPSettingsRow({
            key: "object-cache-dropin-owner",
            title: i("Beheer van object-cache.php", "ultracache-pro"),
            description: externalOwner ? i("Externe object-cache.php gedetecteerd; geen wijziging.", "ultracache-pro") : f ? i("UltraCache beheert of gebruikt de huidige persistente cache-integratie.", "ultracache-pro") : i("Er is nog geen actieve persistente drop-in gekoppeld aan UltraCache.", "ultracache-pro"),
            control: a(ee, {
                state: externalOwner ? "warning" : f ? "good" : "info"
            }, externalOwner ? i("Extern beheerd", "ultracache-pro") : f ? i("Door UltraCache gebruikt", "ultracache-pro") : i("Nog niet actief", "ultracache-pro"))
        }), UCPSettingsRow({
            key: "object-cache-redis-connection",
            title: i("Redis", "ultracache-pro"),
            description: _ ? reasonLabel : i("Redis is niet gevonden in deze omgeving of is uitgeschakeld.", "ultracache-pro"),
            control: a(ee, {
                state: _ ? k ? "good" : "error" : "info"
            }, _ ? k ? i("Bereikbaar", "ultracache-pro") : i("Niet bereikbaar", "ultracache-pro") : i("Niet gedetecteerd", "ultracache-pro"))
        }), UCPSettingsRow({
            key: "object-cache-apcu-status",
            title: i("APCu", "ultracache-pro"),
            description: y ? i("APCu is beschikbaar als lokale fallback wanneer Redis niet bruikbaar is.", "ultracache-pro") : i("APCu is niet beschikbaar in deze PHP-omgeving.", "ultracache-pro"),
            control: a(ee, {
                state: y ? "good" : "info"
            }, y ? i("Beschikbaar", "ultracache-pro") : i("Niet beschikbaar", "ultracache-pro"))
        }) ].filter(Boolean);
        return a("div", {
            className: "ucp-settings-page ucp-settings-page--server ucp-cache-tools-page"
        }, UCPSectionHero({
            eyebrow: i("Server & CDN", "ultracache-pro"),
            title: i("Bestandsdistributie en databasecache", "ultracache-pro"),
            description: i("Gebruik een CDN of databasecache alleen als je hosting dit ondersteunt.", "ultracache-pro"),
            badge: A ? i("CDN-domein ontbreekt", "ultracache-pro") : u || f ? i("Actief", "ultracache-pro") : null,
            badgeClass: A ? "ucp-status-badge--warning" : u || f ? "ucp-status-badge--good" : ""
        }), a("div", {
            className: "ucp-settings-stack ucp-settings-stack--server"
        }, UCPSettingsSection({
            key: "server-cdn",
            className: "ucp-settings-section--server-cdn",
            title: i("CDN", "ultracache-pro"),
            description: i("Herschrijft statische assets naar een eigen CDN.", "ultracache-pro"),
            badge: A ? i("Domein ontbreekt", "ultracache-pro") : u ? i("Actief", "ultracache-pro") : null,
            badgeClass: A ? "ucp-status-badge--warning" : u ? "ucp-status-badge--good" : "",
            children: x
        }), UCPSettingsSection({
            key: "server-object-cache",
            className: "ucp-settings-section--server-object-cache",
            title: i("Databasecache (object cache)", "ultracache-pro"),
            description: i("Versnelt databaseverzoeken met Redis, APCu of een bestaande object-cache.", "ultracache-pro"),
            badge: P,
            badgeClass: f ? "ucp-status-badge--good" : pendingActivation ? "ucp-status-badge--warning" : E ? "ucp-status-badge--error" : D ? "ucp-status-badge--warning" : "ucp-status-badge--neutral",
            headerAction: a("div", {
                className: "ucp-object-cache-actions"
            }, canConfigure ? a(Pe, {
                action: "object-cache-auto-configure",
                label: configureLabel,
                variant: "primary",
                confirm: !0,
                confirmText: configureText,
                data: { confirmed: !0 },
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                onComplete: e.onRefresh,
                compact: !0
            }) : null, a(Pe, {
                action: "refresh-object-cache-status",
                label: i("Opnieuw controleren", "ultracache-pro"),
                variant: "secondary",
                addNotice: e.addNotice,
                setStatus: e.setStatus,
                onComplete: e.onRefresh,
                compact: !0
            })),
            children: B
        }), serverAdvancedVisible && imageCdnRows.length && (imageCdnActive || "none" !== provider && providerReady) ? UCPSettingsSection({
            key: "server-image-cdn",
            className: "ucp-settings-section--server-image-cdn",
            title: i("Afbeeldings-CDN", "ultracache-pro"),
            description: i("Schakel transforms alleen in voor een geteste image-CDN-provider.", "ultracache-pro"),
            badge: imageCdnActive ? i("Actief", "ultracache-pro") : i("Uit", "ultracache-pro"),
            badgeClass: imageCdnActive ? "ucp-status-badge--good" : "ucp-status-badge--neutral",
            children: imageCdnRows
        }) : null, a("details", {
            className: "ucp-settings-advanced ucp-settings-advanced--server-details"
        }, a("summary", {}, a("span", {
            className: "ucp-advanced-summary-copy"
        }, a("strong", {}, i("Meer serverinstellingen", "ultracache-pro")), a("small", {}, i("Status, browsercache en CDN-koppelingen.", "ultracache-pro"))), A ? a(ee, {
            state: "warning"
        }, i("Controle nodig", "ultracache-pro")) : f || u ? a(ee, {
            state: "good"
        }, i("Actief", "ultracache-pro")) : null), a("div", {
            className: "ucp-settings-advanced__body"
        }, a("div", {
            className: "ucp-server-details-grid"
        }, a("section", {
            className: "ucp-server-detail-panel"
        }, a("header", {
            className: "ucp-server-detail-panel__header"
        }, a("h3", {}, i("Databasecache-status", "ultracache-pro")), a("p", {}, i("Beschikbaarheid en actieve koppeling.", "ultracache-pro"))), a("div", {
            className: "ucp-settings-list ucp-object-cache-details"
        }, G)), resourceHintRows.length || serverAdvancedVisible && (browserCacheRows.length || providerRows.length) ? a("section", {
            className: "ucp-server-detail-panel"
        }, a("header", {
            className: "ucp-server-detail-panel__header"
        }, a("h3", {}, i("Browsercache en CDN", "ultracache-pro")), a("p", {}, i("Browsercache, externe verbindingen en providerinstellingen.", "ultracache-pro"))), resourceHintRows.length ? a("div", {
            className: "ucp-server-detail-group"
        }, a("div", {
            className: "ucp-settings-list",
            role: "group",
            "aria-label": i("Externe verbindingen", "ultracache-pro")
        }, resourceHintRows)) : null, serverAdvancedVisible && browserCacheRows.length ? a("div", {
            className: "ucp-server-detail-group"
        }, a("div", {
            className: "ucp-settings-list",
            role: "group",
            "aria-label": i("Browsercache", "ultracache-pro")
        }, browserCacheRows)) : null, serverAdvancedVisible && providerRows.length ? a("div", {
            className: "ucp-server-detail-group"
        }, a("div", {
            className: "ucp-settings-list",
            role: "group",
            "aria-label": i("CDN-provider en compatibiliteit", "ultracache-pro")
        }, providerRows)) : null) : null))), u ? a("details", {
            className: "ucp-settings-advanced ucp-settings-advanced--server-exclude"
        }, a("summary", {}, a("span", {}, i("CDN-uitsluitingen", "ultracache-pro")), a("small", {}, i("Alleen gebruiken voor paden die bewust niet via het CDN mogen lopen", "ultracache-pro"))), a("div", {
            className: "ucp-settings-advanced__body"
        }, a("div", {
            className: "ucp-settings-list"
        }, c("cdn_exclude", {
            title: i("Uitgesloten CDN-paden", "ultracache-pro"),
            description: i("Eén pad, bestandsnaam of patroon per regel.", "ultracache-pro")
        })))) : null));
    }
    function Ke(e) {
        var preloadGroups = Ve.preload || [], advancedVisible = UCPAdvancedSettingsVisible(e.settings || {});
        function fieldDefinition(key) {
            if ("speculative_loading_mode" === key && advancedVisible) {
                for (var index = preloadGroups.length - 1; index >= 0; index -= 1) {
                    var fields = preloadGroups[index].fields || [];
                    for (var fieldIndex = 0; fieldIndex < fields.length; fieldIndex += 1) {
                        if (fields[fieldIndex][0] === key) return fields[fieldIndex];
                    }
                }
            }
            return We(preloadGroups, key);
        }
        function control(key, extraClass, overrides) {
            var field = fieldDefinition(key);
            if (!field) return null;
            field = field.slice(), (overrides = overrides || {}).label && (field[1] = overrides.label), void 0 !== overrides.help && (field[3] = overrides.help);
            var type = field[2] || "text", className = "ucp-settings-control ucp-settings-control--" + type + " ucp-settings-control--hide-primary-label";
            return "toggle" === type && (className += " ucp-settings-control--toggle"), "textarea" === type && (className += " ucp-settings-control--wide"), extraClass && (className += " " + extraClass), a("div", { className: className }, a(Ba, { field: field, kind: "preload", settings: e.settings, status: e.status || {}, setSettings: e.setSettings, addNotice: e.addNotice, setStatus: e.setStatus, hideInlineHelp: !0 }));
        }
        function row(key, options) {
            var field = fieldDefinition(key);
            if (!field || !UCPShouldRenderSetting(key, e.settings, e.status || {})) return null;
            options = options || {};
            var controlType = options.field && options.field.type ? options.field.type : field[2] || "text", className = UCPSettingsRowClass(key, controlType, options.className || "");
            return UCPSettingsRow({ key: key, title: options.title || field[1], description: void 0 !== options.description ? options.description : field[3], className: className, control: control(key, options.controlClassName || "", options.field || null) });
        }
        var mode = da(e.settings || {}), statusLabel = i("off" === mode ? "Uit" : "homepage" === mode ? "Homepage" : "manual" === mode ? "Handmatig" : "Aanbevolen", "ultracache-pro"), settingsRows = [ row("preload_mode", {
            title: i("Cache vooraf opbouwen", "ultracache-pro"),
            description: i("Bouw de homepage en sitemap-URL’s gecontroleerd vooraf op via een wachtrij.", "ultracache-pro")
        }), row("enable_prefetch_links", {
            title: i("Link-prefetch", "ultracache-pro"),
            description: i("Prefetcht interne routes bij aanwijzen of aanraken.", "ultracache-pro")
        }), row("speculative_loading_mode", {
            title: i("Browsernavigatie", "ultracache-pro"),
            description: i("Gebruik Core-prefetch of UltraCache-prefetch voor publieke routes.", "ultracache-pro")
        }) ].filter(Boolean), ruleBreakdown = UCPRuleBreakdown("preload_exclude_urls", (e.settings || {}).preload_exclude_urls || ""), speculationBreakdown = UCPRuleBreakdown("speculation_exclusions", (e.settings || {}).speculation_exclusions || ""), standardCount = ruleBreakdown.protectedLines.length + speculationBreakdown.protectedLines.length, customLines = ruleBreakdown.customLines.concat(speculationBreakdown.customLines), exclusionCount = standardCount + customLines.length;
        var preloadSettingsSection = UCPSettingsSection({
            key: "preload-settings",
            title: i("Vooraf opbouwen instellen", "ultracache-pro"),
            description: i("Cache-opbouw en browserhints staan in één voorspelbare volgorde.", "ultracache-pro"),
            badge: i("off" === mode ? "Uit" : "Actief", "ultracache-pro"),
            badgeClass: "off" === mode ? "ucp-status-badge--neutral" : "ucp-status-badge--good",
            children: settingsRows,
            className: "ucp-cache-tools-card--settings ucp-cache-tools-card--preload"
        }), preloadExclusionsSection = "off" !== mode ? UCPSettingsSection({
            key: "preload-exclusions",
            title: i("Bescherming bij vooraf opbouwen", "ultracache-pro"),
            description: i("Beheer centrale uitsluitingen voor preload en speculative loading.", "ultracache-pro"),
            hideBody: !0,
            className: "ucp-cache-tools-card--settings ucp-cache-tools-card--preload-rules",
            headerAction: a("div", {
                className: "ucp-preload-exclusion-actions"
            }, a(ee, {
                state: exclusionCount ? "info" : "disabled"
            }, exclusionCount ? s(i("%1$d ingebouwd · %2$d eigen", "ultracache-pro"), standardCount, customLines.length) : i("Geen regels", "ultracache-pro")), a("a", {
                className: "ucp-dashboard-settings-link ucp-exclusions-link",
                href: UCPAdminTabUrl("advanced"),
                onClick: function(event) {
                    event.preventDefault();
                    e.onSelectTab && e.onSelectTab("advanced");
                }
            }, i("Uitsluitingen beheren", "ultracache-pro")))
        }) : null;
        if (e.embedded) return a(t, {}, preloadSettingsSection, preloadExclusionsSection);
        return a("div", {
            className: "ucp-settings-page ucp-settings-page--preload ucp-cache-tools-page"
        }, UCPSectionHero({
            eyebrow: i("Vooraf opbouwen", "ultracache-pro"),
            title: i("Vooraf opbouwen en navigatie", "ultracache-pro"),
            description: i("Preload publieke pagina’s en sluit persoonlijke routes uit.", "ultracache-pro"),
            badge: statusLabel,
            badgeClass: "off" === mode ? "ucp-status-badge--neutral" : "ucp-status-badge--good"
        }), a("div", {
            className: "ucp-settings-stack ucp-settings-stack--preload"
        }, preloadSettingsSection, preloadExclusionsSection));
    }
    function Fe(e) {
        if ("cache" === e.kind) return a(Re, e);
        if ("optimization" === e.kind) return a(Ge, e);
        if ("woocommerce" === e.kind) return a(Je, e);
        if ("server" === e.kind) return a(He, e);
        if ("preload" === e.kind) return a(Ke, e);
        if (-1 !== [ "media", "advanced", "database", "diagnostics" ].indexOf(e.kind)) return a(Ee, e);
        var t = Ve[e.kind] || Ve.optimization, n = t.map(function(e, a) {
            var t = e.fields || [];
            return Object.assign({
                __id: (e.key || D(e.title) || "group-" + a) + "-" + a
            }, e, {
                fields: t
            });
        }).filter(function(e) {
            return e.fields && e.fields.length;
        }), s = {};
        n.forEach(function(e) {
            s[e.__id] = e;
        });
        var l = n;
        function u(t) {
            return a(d, {
                key: t.__id,
                className: "ucp-card ucp-layout-card ucp-settings-card ucp-cache-tools-card"
            }, a(p, {}, a("div", {
                className: "ucp-layout-card__header"
            }, a("div", {
                className: "ucp-layout-card__title-wrap"
            }, a("h2", {}, t.title)))), a(h, {}, t.fields.map(function(t) {
                return a(Ba, {
                    key: t[0],
                    field: t,
                    kind: e.kind,
                    settings: e.settings,
                    status: e.status || {},
                    setSettings: e.setSettings,
                    addNotice: e.addNotice,
                    setStatus: e.setStatus
                });
            })));
        }
        return a("div", {
            className: "ucp-settings-page ucp-settings-page--" + e.kind + " ucp-cache-tools-page"
        }, a("div", {
            className: "screen-reader-text",
            "aria-live": "polite"
        }, ""), "advanced" === e.kind ? a(qe, {
            kind: "advanced"
        }) : null, a("div", {
            className: "ucp-layout-grid ucp-layout-grid--settings ucp-layout-grid--simple",
            style: {
                "--ucp-grid-columns": 1
            }
        }, l.map(function(e) {
            return u(e);
        })));
    }
    var ia = [ "enable_disable_dashicons", "enable_hide_wp_version", "enable_remove_rsd_link", "enable_remove_shortlink", "enable_remove_rss_feed_links", "enable_remove_rest_api_links", "enable_disable_self_pingbacks" ], sa = [ "enable_disable_jquery_migrate", "enable_disable_xmlrpc", "enable_disable_rss_feeds", "enable_remove_global_styles", "enable_remove_query_strings" ];
    function da(e) {
        return e = e || {}, parseInt(e.enable_preload || 0, 10) ? parseInt(e.preload_sitemaps || 0, 10) && parseInt(e.preload_homepage || 0, 10) ? "recommended" : !parseInt(e.preload_sitemaps || 0, 10) && parseInt(e.preload_homepage || 0, 10) ? "homepage" : "manual" : "off";
    }
    function Na(e) {
        return "keep" === (e = e || {}).heartbeat_frontend_behavior && "keep" === e.heartbeat_editor_behavior && "keep" === e.heartbeat_backend_behavior ? 0 : 1;
    }
    function Ca(e) {
        return -1 !== [ "cloud_api_key", "cloudflare_api_token", "secret_cache_key", "css_cache_key", "js_cache_key", "headless_renderer_token", "bunny_api_key", "cdn_purge_webhook_token" ].indexOf(String(e || ""));
    }
    var za = {
        html_optimization_mode: {
            derive: function(e) {
                return e = e || {}, parseInt(e.enable_html_minify || 0, 10) ? "minify" : parseInt(e.remove_html_comments || 0, 10) ? "comments" : "off";
            },
            apply: function(e, a) {
                e.remove_html_comments = "comments" === a || "minify" === a ? 1 : 0, e.enable_html_minify = "minify" === a ? 1 : 0;
            }
        },
        image_optimization_mode: {
            derive: function(e) {
                return e = e || {}, parseInt(e.enable_avif_generation || 0, 10) ? "webp_avif" : parseInt(e.enable_webp_generation || 0, 10) ? "webp" : "off";
            },
            apply: function(e, a) {
                e.enable_image_optimization = "webp" === a || "webp_avif" === a ? 1 : 0,
                e.enable_webp_generation = "webp" === a || "webp_avif" === a ? 1 : 0, e.enable_avif_generation = "webp_avif" === a ? 1 : 0;
            }
        },
        css_delivery_mode: {
            derive: function(e) {
                return -1 !== [ "none", "remove_unused", "async" ].indexOf((e = e || {}).css_delivery_mode) ? e.css_delivery_mode : "none";
            },
            apply: function(e, a) {
                a = -1 !== [ "none", "remove_unused", "async" ].indexOf(a) ? a : "none", e.css_delivery_mode = a,
                e.enable_used_css = "remove_unused" === a ? 1 : 0, e.enable_used_css_delivery = "remove_unused" === a ? 1 : 0,
                e.enable_critical_css = "async" === a ? 1 : 0, e.enable_css_queue = "none" === a ? 0 : 1,
                "none" === a ? e.enable_remote_css_render = 0 : e.enable_css_combine = 0;
            }
        },
        enable_cache: {
            derive: function(e) {
                return parseInt((e = e || {}).enable_cache || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                e.enable_cache = parseInt(a || 0, 10) ? 1 : 0, e.enable_cache || (e.enable_preload_queue = 0);
            }
        },
        enable_js_minify: {
            derive: function(e) {
                return parseInt((e = e || {}).enable_js_minify || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                e.enable_js_minify = parseInt(a || 0, 10) ? 1 : 0, e.allow_experimental_js_minify = e.enable_js_minify;
            }
        },
        enable_js_combine: {
            derive: function(e) {
                return parseInt((e = e || {}).enable_js_combine || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                e.enable_js_combine = parseInt(a || 0, 10) ? 1 : 0, e.enable_js_combine && (e.enable_js_minify = 1,
                e.allow_experimental_js_minify = 1);
            }
        },
        defer_all_js: {
            derive: function(e) {
                return parseInt((e = e || {}).defer_all_js || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                e.defer_all_js = parseInt(a || 0, 10) ? 1 : 0, e.defer_all_js && (e.enable_defer_js_fallback = 1,
                parseInt(e.enable_native_script_strategy || 0, 10) && (e.enable_js_combine = 0));
            }
        },
        accessibility_mode: {
            derive: function(e) {
                return parseInt((e = e || {}).accessibility_mode || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                if (e.accessibility_mode = parseInt(a || 0, 10) ? 1 : 0, e.accessibility_mode) {
                    [ "enable_js_minify", "allow_experimental_js_minify", "enable_js_combine", "enable_delay_js", "defer_all_js", "enable_defer_js_fallback", "enable_native_script_strategy", "enable_move_module_scripts_footer", "enable_lazy_render" ].forEach(function(a) {
                        e[a] = 0;
                    }), e.delay_js_safe_mode = 1, e.delay_js_disable_click_delay = 1;
                }
            }
        },
        serve_cache_to_shoppers: {
            derive: function(e) {
                return parseInt((e = e || {}).serve_cache_to_shoppers || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                e.serve_cache_to_shoppers = parseInt(a || 0, 10) ? 1 : 0, e.serve_cache_to_shoppers && (e.enable_woocommerce_rules = 1,
                e.woocommerce_safety_mode = 1);
            }
        },
        optimize_cart_fragments: {
            derive: function(e) {
                return parseInt((e = e || {}).optimize_cart_fragments || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                e.optimize_cart_fragments = parseInt(a || 0, 10) ? 1 : 0, e.optimize_cart_fragments && (e.safe_cart_fragments_mode = 1);
            }
        },
        enable_image_cdn: {
            derive: function(e) {
                return parseInt((e = e || {}).enable_image_cdn || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                e.enable_image_cdn = parseInt(a || 0, 10) ? 1 : 0, e.enable_image_cdn || (e.enable_image_cdn_transforms = 0,
                e.enable_adaptive_image_srcset = 0);
            }
        },
        enable_image_cdn_transforms: {
            derive: function(e) {
                return parseInt((e = e || {}).enable_image_cdn_transforms || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                e.enable_image_cdn_transforms = parseInt(e.enable_image_cdn || 0, 10) && parseInt(a || 0, 10) ? 1 : 0,
                e.enable_image_cdn_transforms || (e.enable_adaptive_image_srcset = 0);
            }
        },
        enable_adaptive_image_srcset: {
            derive: function(e) {
                return parseInt((e = e || {}).enable_adaptive_image_srcset || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                e.enable_adaptive_image_srcset = parseInt(e.enable_image_cdn || 0, 10) && parseInt(a || 0, 10) ? 1 : 0,
                e.enable_adaptive_image_srcset && (e.enable_image_cdn_transforms = 1);
            }
        },
        limit_cart_fragments_to_woo: {
            derive: function(e) {
                return parseInt((e = e || {}).limit_cart_fragments_to_woo || 0, 10) ? 1 : 0;
            },
            apply: function(e, a) {
                e.limit_cart_fragments_to_woo = parseInt(a || 0, 10) ? 1 : 0, e.limit_cart_fragments_to_woo && (e.safe_cart_fragments_mode = 1);
            }
        },
        lcp_image_mode: {
            derive: function(e) {
                e = e || {};
                var a = parseInt(e.preload_critical_images || 0, 10) || 0, t = parseInt(e.lazyload_exclude_leading_images || 0, 10) || 0;
                return 0 === a && 0 === t ? "off" : 0 === a && 1 === t ? "protect_hero" : 1 === a && 1 === t ? "preload_hero" : 2 === a && 4 === t ? "recommended" : "custom";
            },
            apply: function(e, a) {
                "off" === a ? (e.preload_critical_images = 0, e.lazyload_exclude_leading_images = 0) : "protect_hero" === a ? (e.preload_critical_images = 0,
                e.lazyload_exclude_leading_images = 1) : "preload_hero" === a ? (e.preload_critical_images = 1,
                e.lazyload_exclude_leading_images = 1) : "recommended" === a ? (e.preload_critical_images = 2,
                e.lazyload_exclude_leading_images = 4) : "custom" === a && (e.preload_critical_images = 1,
                e.lazyload_exclude_leading_images = 2);
            }
        },
        delay_js_control: {
            derive: function(e) {
                return e = e || {}, parseInt(e.enable_delay_js || 0, 10) ? parseInt(e.delay_js_safe_mode || 0, 10) ? "safe" : "all" === e.delay_js_mode ? "all" : "specified" : "off";
            },
            apply: function(e, a) {
                e.enable_delay_js = "off" === a ? 0 : 1, "specified" === a ? (e.delay_js_mode = "specified",
                e.delay_js_safe_mode = 0) : "all" === a ? (e.delay_js_mode = "all", e.delay_js_safe_mode = 0) : "safe" === a && (e.delay_js_mode = "all",
                e.delay_js_safe_mode = 1, e.delay_js_disable_click_delay = 1), "off" !== a && (e.enable_js_combine = 0,
                e.enable_native_script_strategy = 0);
            }
        },
        media_lazyload_mode: {
            derive: function(e) {
                return e = e || {}, parseInt(e.enable_lazy_youtube_preview || 0, 10) ? "youtube" : parseInt(e.enable_lazy_iframes || 0, 10) ? "iframes" : parseInt(e.enable_lazy_images || 0, 10) ? "images" : "off";
            },
            apply: function(e, a) {
                e.enable_lazy_images = "images" === a || "iframes" === a || "youtube" === a ? 1 : 0,
                e.enable_lazy_iframes = "iframes" === a || "youtube" === a ? 1 : 0, e.enable_lazy_youtube_preview = "youtube" === a ? 1 : 0;
            }
        },
        google_fonts_mode: {
            derive: function(e) {
                return e = e || {}, parseInt(e.enable_disable_google_fonts || 0, 10) ? "disable" : parseInt(e.enable_local_google_fonts || 0, 10) ? "local" : parseInt(e.enable_font_display_swap || 0, 10) ? "swap" : "standard";
            },
            apply: function(e, a) {
                e.enable_disable_google_fonts = "disable" === a ? 1 : 0, e.enable_local_google_fonts = "local" === a ? 1 : 0,
                e.enable_font_display_swap = "swap" === a || "local" === a ? 1 : 0, "local" === a && (e.enable_auto_font_preloads = 1);
            }
        },
        preload_mode: {
            derive: da,
            apply: function(e, a) {
                var t = parseInt(e.enable_cache || 0, 10) ? 1 : 0;
                "off" === a ? (e.enable_preload = 0, e.enable_preload_queue = 0, e.preload_sitemaps = 0,
                e.preload_homepage = 0) : "recommended" === a ? (e.enable_preload = 1, e.enable_preload_queue = t,
                e.preload_sitemaps = 1, e.preload_homepage = 1) : "homepage" === a ? (e.enable_preload = 1,
                e.enable_preload_queue = t, e.preload_sitemaps = 0, e.preload_homepage = 1) : "manual" === a && (e.enable_preload = 1,
                e.enable_preload_queue = 0, e.preload_sitemaps = 0, e.preload_homepage = 0);
            }
        },
        stale_cache_mode: {
            derive: function(e) {
                if (e = e || {}, !parseInt(e.enable_stale_cache || 0, 10)) return "off";
                var a = parseInt(e.stale_cache_lifespan || 0, 10) || 24;
                return -1 !== [ 6, 12, 24, 48 ].indexOf(a) ? String(a) : "24";
            },
            apply: function(e, a) {
                e.enable_stale_cache = "off" === a ? 0 : 1, "off" !== a && (e.stale_cache_lifespan = parseInt(a || 24, 10) || 24);
            }
        },
        query_string_cache_mode: {
            derive: function(e) {
                return e = e || {}, parseInt(e.cache_query_strings || 0, 10) ? "allow_list" : "off";
            },
            apply: function(e, a) {
                e.cache_query_strings = "allow_list" === a ? 1 : 0;
            }
        },
        speculative_loading_mode: {
            derive: function(e) {
                return -1 !== [ "core", "enhanced", "prerender", "off" ].indexOf((e = e || {}).speculative_loading_mode) ? e.speculative_loading_mode : parseInt(e.enable_speculative_loading || 0, 10) ? "prerender" === e.speculation_mode ? "prerender" : "enhanced" : "core";
            },
            apply: function(e, a) {
                if (a = -1 !== [ "core", "enhanced", "prerender", "off" ].indexOf(a) ? a : "core",
                e.speculative_loading_mode = a, "off" === a || "core" === a) return e.enable_speculative_loading = 0,
                e.speculation_mode = "prefetch", void (e.speculation_eagerness = "conservative");
                e.enable_speculative_loading = 1, "prerender" === a ? (e.speculation_mode = "prerender",
                e.speculation_eagerness = "conservative") : (e.speculation_mode = "prefetch", e.speculation_eagerness = "conservative");
            }
        },
        cdn_rewrite_mode: {
            derive: function(e) {
                return e = e || {}, parseInt(e.enable_cdn || 0, 10) ? -1 !== [ "css_js", "images", "all" ].indexOf(e.cdn_file_types) ? e.cdn_file_types : "all" : "off";
            },
            apply: function(e, a) {
                e.enable_cdn = "off" === a ? 0 : 1, "off" !== a && (e.cdn_file_types = -1 !== [ "css_js", "images", "all" ].indexOf(a) ? a : "all");
            }
        },
        browser_cache_mode: {
            derive: function(e) {
                if (e = e || {}, !parseInt(e.browser_cache_headers || 0, 10) || !parseInt(e.allow_browser_cache_rule_writes || 0, 10)) return "off";
                var a = parseInt(e.cache_control_max_age || 0, 10) || 31536e3;
                return 2592e3 === a ? "30d" : 15552e3 === a ? "180d" : 31536e3 === a ? "365d" : "custom";
            },
            apply: function(e, a, t) {
                if (t = t || {}, e.browser_cache_headers = "off" === a ? 0 : 1, e.allow_browser_cache_rule_writes = "off" === a ? 0 : 1,
                "30d" === a && (e.cache_control_max_age = 2592e3), "180d" === a && (e.cache_control_max_age = 15552e3),
                "365d" === a && (e.cache_control_max_age = 31536e3), "custom" === a) {
                    var r = parseInt(t.cache_control_max_age || e.cache_control_max_age || 0, 10);
                    e.cache_control_max_age = r > 0 && -1 === [ 2592e3, 15552e3, 31536e3 ].indexOf(r) ? r : 604800;
                }
            }
        },
        heartbeat_interval_mode: {
            derive: function(e) {
                e = e || {};
                var a = parseInt(e.heartbeat_frontend_frequency || 0, 10) || 60, t = parseInt(e.heartbeat_editor_frequency || 0, 10) || 30, r = parseInt(e.heartbeat_backend_frequency || 0, 10) || 60;
                return a === t && t === r && -1 !== [ 30, 60, 120 ].indexOf(a) ? String(a) : "custom";
            },
            apply: function(e, a) {
                if ("custom" === a) return e.heartbeat_frontend_frequency = 60, e.heartbeat_editor_frequency = 30,
                e.heartbeat_backend_frequency = 60, void (e.heartbeat_frequency = 60);
                var t = parseInt(a || 0, 10);
                -1 !== [ 30, 60, 120 ].indexOf(t) && (e.heartbeat_frontend_frequency = t, e.heartbeat_editor_frequency = t,
                e.heartbeat_backend_frequency = t, e.heartbeat_frequency = t);
            }
        },
        bloat_removal_mode: {
            derive: function(e) {
                if (e = e || {}, sa.some(function(a) {
                    return parseInt(e[a] || 0, 10);
                })) return "aggressive";
                var t = ia.some(function(a) {
                    return parseInt(e[a] || 0, 10);
                });
                return t ? "safe" : "off";
            },
            apply: function(e, a) {
                ia.forEach(function(t) {
                    e[t] = "safe" === a || "aggressive" === a ? 1 : 0;
                }), sa.forEach(function(t) {
                    e[t] = "aggressive" === a ? 1 : 0;
                });
            }
        }
    };
    function xa(e, a) {
        a = a || {};
        var t = Object.prototype.hasOwnProperty.call(a, e) ? a[e] : "";
        return za[e] ? za[e].derive(a) : t;
    }
    function Da(e) {
        var t = r({
            preload: parseInt((e.settings || {}).preload_critical_images || 0, 10),
            protect: parseInt((e.settings || {}).lazyload_exclude_leading_images || 0, 10)
        }), c = t[0], o = t[1];
        return n(function() {
            o({
                preload: parseInt((e.settings || {}).preload_critical_images || 0, 10),
                protect: parseInt((e.settings || {}).lazyload_exclude_leading_images || 0, 10)
            });
        }, [ (e.settings || {}).preload_critical_images, (e.settings || {}).lazyload_exclude_leading_images ]),
        a("div", {
            className: "ucp-setting-field-control"
        }, a(Ka, {
            label: e.label,
            help: e.hideInlineHelp ? "" : e.help,
            hideSummary: !!e.hideInlineHelp,
            value: e.currentValue || "recommended",
            options: e.options || [],
            disabled: e.saving,
            onChange: function(a) {
                e.commit(a);
            },
            descriptions: {
                off: i("Geen automatische LCP-ingreep.", "ultracache-pro"),
                protect_hero: i("Voorkomt lazyload op het belangrijkste hero-beeld.", "ultracache-pro"),
                preload_hero: i("Laadt het belangrijkste beeld boven de vouw eerder voor een snellere LCP.", "ultracache-pro"),
                recommended: i("Prioriteert kritieke beelden en beschermt de eerste viewport.", "ultracache-pro"),
                custom: i("Stel zelf het aantal vooraf geladen en beschermde bovenste afbeeldingen in.", "ultracache-pro")
            }
        }), "custom" === e.currentValue ? a("div", {
            className: "ucp-setting-field-custom"
        }, a(w, {
            label: i("Belangrijke afbeeldingen vooraf laden", "ultracache-pro"),
            help: e.hideInlineHelp ? "" : i("Aantal zichtbare boven-de-vouw afbeeldingen. Maximaal 3.", "ultracache-pro"),
            value: c.preload,
            min: 0,
            max: 3,
            disabled: e.saving,
            onChange: function(a) {
                o(Object.assign({}, c, {
                    preload: a
                })), e.setDirty(!0);
            }
        }), a(w, {
            label: i("Bovenste afbeeldingen niet lazyloaden", "ultracache-pro"),
            help: e.hideInlineHelp ? "" : i("Aantal eerste afbeeldingen dat niet lazyloadt. Maximaal 5.", "ultracache-pro"),
            value: c.protect,
            min: 0,
            max: 5,
            disabled: e.saving,
            onChange: function(a) {
                o(Object.assign({}, c, {
                    protect: a
                })), e.setDirty(!0);
            }
        }), e.dirty ? a(g, {
            variant: "secondary",
            isBusy: e.saving,
            disabled: e.saving,
            onClick: function() {
                var a = Object.assign({}, e.settings || {}), t = {
                    preload_critical_images: parseInt(c.preload || 0, 10),
                    lazyload_exclude_leading_images: parseInt(c.protect || 0, 10)
                }, r = Object.assign({}, a, t);
                e.setSettings(r), e.setSaving(!0), e.setDirty(!1), H(t).then(function(a) {
                    e.setSettings(a.settings), a.status && e.setStatus && e.setStatus(a.status), e.addNotice({
                        status: UCPS(a),
                        message: UCPM(a, s(i("%s opgeslagen.", "ultracache-pro"), e.label))
                    });
                }).catch(function(t) {
                    e.setSettings(a), e.addNotice({
                        status: "error",
                        message: G(t, s(i("%s kon niet worden opgeslagen.", "ultracache-pro"), e.label))
                    });
                }).finally(function() {
                    e.setSaving(!1);
                });
            }
        }, i("Aangepaste LCP-instellingen opslaan", "ultracache-pro")) : null) : null);
    }
    function Pa(e) {
        var t = r({
            frontend: parseInt((e.settings || {}).heartbeat_frontend_frequency || 60, 10),
            editor: parseInt((e.settings || {}).heartbeat_editor_frequency || 30, 10),
            backend: parseInt((e.settings || {}).heartbeat_backend_frequency || 60, 10)
        }), c = t[0], o = t[1];
        return n(function() {
            o({
                frontend: parseInt((e.settings || {}).heartbeat_frontend_frequency || 60, 10),
                editor: parseInt((e.settings || {}).heartbeat_editor_frequency || 30, 10),
                backend: parseInt((e.settings || {}).heartbeat_backend_frequency || 60, 10)
            });
        }, [ (e.settings || {}).heartbeat_frontend_frequency, (e.settings || {}).heartbeat_editor_frequency, (e.settings || {}).heartbeat_backend_frequency ]),
        a("div", {
            className: "ucp-setting-field-control"
        }, a(_, {
            label: e.label,
            hideLabelFromVision: !0,
            help: e.help,
            value: e.currentValue || "custom",
            options: (e.options || []).map(function(e) {
                return {
                    value: e[0],
                    label: e[1]
                };
            }),
            disabled: e.saving,
            onChange: function(a) {
                e.commit(a);
            }
        }), "custom" === e.currentValue ? a("div", {
            className: "ucp-setting-field-custom"
        }, a(w, {
            label: i("Frontend interval", "ultracache-pro"),
            help: i("Seconden bij Verminderen.", "ultracache-pro"),
            value: c.frontend,
            min: 15,
            max: 300,
            disabled: e.saving,
            onChange: function(a) {
                o(Object.assign({}, c, {
                    frontend: a
                })), e.setDirty(!0);
            }
        }), a(w, {
            label: i("Editor interval", "ultracache-pro"),
            help: i("Seconden bij Verminderen.", "ultracache-pro"),
            value: c.editor,
            min: 15,
            max: 300,
            disabled: e.saving,
            onChange: function(a) {
                o(Object.assign({}, c, {
                    editor: a
                })), e.setDirty(!0);
            }
        }), a(w, {
            label: i("Backend interval", "ultracache-pro"),
            help: i("Seconden bij Verminderen.", "ultracache-pro"),
            value: c.backend,
            min: 15,
            max: 300,
            disabled: e.saving,
            onChange: function(a) {
                o(Object.assign({}, c, {
                    backend: a
                })), e.setDirty(!0);
            }
        }), e.dirty ? a(g, {
            variant: "secondary",
            isBusy: e.saving,
            disabled: e.saving,
            onClick: function() {
                var a = Object.assign({}, e.settings || {}), t = {
                    heartbeat_frontend_frequency: parseInt(c.frontend || 60, 10),
                    heartbeat_editor_frequency: parseInt(c.editor || 30, 10),
                    heartbeat_backend_frequency: parseInt(c.backend || 60, 10)
                };
                t.heartbeat_frequency = t.heartbeat_backend_frequency;
                var r = Object.assign({}, a, t);
                e.setSettings(r), e.setSaving(!0), e.setDirty(!1), H(t).then(function(a) {
                    e.setSettings(a.settings), a.status && e.setStatus && e.setStatus(a.status), e.addNotice({
                        status: UCPS(a),
                        message: UCPM(a, s(i("%s opgeslagen.", "ultracache-pro"), e.label))
                    });
                }).catch(function(t) {
                    e.setSettings(a), e.addNotice({
                        status: "error",
                        message: G(t, s(i("%s kon niet worden opgeslagen.", "ultracache-pro"), e.label))
                    });
                }).finally(function() {
                    e.setSaving(!1);
                });
            }
        }, i("Aangepaste intervallen opslaan", "ultracache-pro")) : null) : null);
    }
    function Ta(e) {
        var a = Array.isArray(e) ? e.join("\n") : String(e || ""), t = {};
        return a.split(/[\s,;]+/).map(function(e) {
            return String(e || "").replace(/[^0-9]/g, "");
        }).filter(function(e) {
            var a = parseInt(e, 10);
            return !(!e || !a || a < 160 || a > 2560 || t[e]) && (t[e] = !0, !0);
        });
    }
    function La(e) {
        var t = r(Ta(e.currentValue)), c = t[0], o = t[1];
        return n(function() {
            o(Ta(e.currentValue));
        }, [ e.currentValue ]), a("div", {
            className: "ucp-media-widths-control"
        }, a(k, {
            label: e.label,
            value: c,
            suggestions: [ "320", "360", "480", "640", "768", "1024", "1280", "1536", "1920" ],
            disabled: e.saving,
            onChange: function(a) {
                o(Ta(a)), e.setDirty(!0);
            },
            __experimentalExpandOnFocus: !0
        }), e.help ? a("p", {
            className: "components-base-control__help"
        }, e.help + " " + i("Gebruik breedtes zoals 360, 640 en 1024; scheid met Enter of komma.", "ultracache-pro")) : null, e.dirty ? a(g, {
            variant: "secondary",
            isBusy: e.saving,
            disabled: e.saving,
            "aria-label": s(i("%s opslaan", "ultracache-pro"), e.label),
            onClick: function() {
                e.commit(function(e) {
                    return Ta(e).join("\n");
                }(c));
            }
        }, i("Opslaan", "ultracache-pro")) : null);
    }
    var UCPLineBasedSettings = [ "css_exclusions", "delay_js_exclusions", "html_exclude_urls", "cdn_cnames", "cdn_exclude", "lazyload_exclusions", "preload_fonts", "preload_exclude_urls", "speculation_exclusions", "exclude_urls", "always_purge_urls", "exclude_cookies", "exclude_user_agents", "cache_vary_cookies", "cache_query_string_inclusions", "lazy_render_selectors" ];
    function UCPLineDiagnostics(e) {
        var a = String(e || "").split(/\r?\n/).map(function(e) {
            return String(e || "").trim();
        }).filter(Boolean), t = {}, r = 0;
        return a.forEach(function(e) {
            t[e] ? r += 1 : t[e] = !0;
        }), {
            total: a.length,
            unique: Object.keys(t).length,
            duplicates: r
        };
    }
    function UCPUniqueRuleLines(value) {
        return Array.from(new Set(String(value || "").split(/\r?\n/).map(function(line) {
            return String(line || "").trim();
        }).filter(Boolean)));
    }
    function UCPProtectedRuleDefaults(settingKey) {
        var defaults = ((window.UCP_ADMIN_CONFIG || {}).protectedRuleDefaults || {})[settingKey] || [];
        return Array.from(new Set((Array.isArray(defaults) ? defaults : []).map(function(line) {
            return String(line || "").trim();
        }).filter(Boolean)));
    }
    function UCPRuleBreakdown(settingKey, value) {
        var protectedLines = UCPProtectedRuleDefaults(settingKey), protectedSet = new Set(protectedLines), currentLines = UCPUniqueRuleLines(value), currentSet = new Set(currentLines), customLines = currentLines.filter(function(line) {
            return !protectedSet.has(line);
        }), missingProtected = protectedLines.filter(function(line) {
            return !currentSet.has(line);
        });
        return {
            protectedLines: protectedLines,
            customLines: customLines,
            currentLines: currentLines,
            missingProtected: missingProtected,
            mergedValue: protectedLines.concat(customLines).join("\n")
        };
    }
    function UCPRuleEditorValue(settingKey, value) {
        return -1 !== [ "exclude_urls", "preload_exclude_urls", "speculation_exclusions" ].indexOf(settingKey) ? UCPRuleBreakdown(settingKey, value).customLines.join("\n") : value;
    }
    var UCPNumberBounds = {
        cache_lifespan: { min: 0, max: 720 },
        image_quality: { min: 50, max: 95 },
        cache_control_max_age: { min: 60 },
        fragment_cache_ttl: { min: 60, max: 86400 },
        cache_insights_sample_rate: { min: 1, max: 100 },
        cache_insights_retention_days: { min: 1, max: 30 },
        db_keep_post_revisions: { min: 0 },
        log_retention_days: { min: 7 },
        diagnostics_retention_days: { min: 7 },
        job_retention_days: { min: 7 }
    };
    function UCPSettingConfirmation(settingKey, currentValue, nextValue) {
        if ("enable_esi" === settingKey && !parseInt(currentValue || 0, 10) && parseInt(nextValue || 0, 10)) return {
            title: i("ESI fragment-cache inschakelen", "ultracache-pro"),
            text: i("ESI alleen voor publieke fragmenten zonder sessiedata. Test op staging.", "ultracache-pro"),
            confirmLabel: i("ESI inschakelen", "ultracache-pro"),
            destructive: !0
        };
        if ("clean_uninstall" === settingKey && !parseInt(currentValue || 0, 10) && parseInt(nextValue || 0, 10)) return {
            title: i("Instellingen wissen bij verwijderen", "ultracache-pro"),
            text: i("Wist alle instellingen bij deïnstallatie. Exporteer vooraf een back-up.", "ultracache-pro"),
            confirmLabel: i("Wissen bij verwijderen inschakelen", "ultracache-pro"),
            destructive: !0
        };
        if ("bloat_removal_mode" === settingKey && "aggressive" !== String(currentValue || "") && "aggressive" === String(nextValue || "")) return {
            title: i("Agressieve WordPress-optimalisatie inschakelen", "ultracache-pro"),
            text: i("Schakelt onder meer XML-RPC, RSS-feeds, globale stijlen en jQuery Migrate uit. Test frontend, feeds en koppelingen op staging.", "ultracache-pro"),
            confirmLabel: i("Agressieve modus inschakelen", "ultracache-pro"),
            destructive: !1
        };
        return null;
    }
    function UCPRadioChoiceControl(e) {
        var descriptions = {
            off: i("Gebruik dit wanneer een andere plugin uploads optimaliseert.", "ultracache-pro"),
            optimize: i("Comprimeert alleen nieuwe uploads zonder extra bestandsformaten te maken.", "ultracache-pro"),
            webp: i("Comprimeert uploads en genereert WebP.", "ultracache-pro"),
            webp_avif: i("Maakt WebP en AVIF. Test opslag, CDN en weergave op staging.", "ultracache-pro"),
            standard: i("Laat WordPress en het thema bepalen hoe externe lettertypen worden geladen.", "ultracache-pro"),
            swap: i("Toont tekst direct en wisselt het lettertype in zodra het beschikbaar is.", "ultracache-pro"),
            local: i("Downloadt Google Fonts server-side en serveert ze lokaal. Alleen bewust inschakelen.", "ultracache-pro"),
            disable: i("Blokkeert Google-lettertypen. Controleer daarna de website.", "ultracache-pro")
        }, name = "ucp-choice-" + D(e.label || "setting");
        return a("div", {
            className: "ucp-media-radio-control",
            role: "radiogroup",
            "aria-label": e.label
        }, (e.options || []).map(function(option) {
            var value = String(option[0]), checked = String(e.value) === value;
            return a("label", {
                key: value,
                className: "ucp-media-radio-control__option" + (checked ? " is-selected" : "")
            }, a("input", {
                type: "radio",
                name: name,
                value: value,
                checked: checked,
                disabled: e.disabled,
                onChange: function() {
                    !e.disabled && e.onChange(value);
                }
            }), a("span", {
                className: "ucp-media-radio-control__copy"
            }, a("strong", {}, option[1]), descriptions[value] ? a("small", {}, descriptions[value]) : null));
        }));
    }
    function UCPMediaLazyloadControl(e) {
        var value = String(e.value || "off"), imagesEnabled = "off" !== value, embedsEnabled = "iframes" === value || "youtube" === value, youtubeEnabled = "youtube" === value;
        return a("div", {
            className: "ucp-dependent-toggle-group",
            role: "group",
            "aria-label": e.label
        }, FormToggleControl ? a(FormToggleControl, {
            "aria-label": i("Media later laden", "ultracache-pro"),
            checked: imagesEnabled,
            disabled: e.disabled,
            onChange: function() {
                e.onChange(imagesEnabled ? "off" : "images");
            }
        }) : a(b, {
            label: i("Media later laden", "ultracache-pro"),
            checked: imagesEnabled,
            disabled: e.disabled,
            onChange: function(enabled) {
                e.onChange(enabled ? "images" : "off");
            }
        }), imagesEnabled ? a("div", {
            className: "ucp-dependent-toggle-group__option"
        }, a("span", {}, i("Video’s ook later laden", "ultracache-pro")), FormToggleControl ? a(FormToggleControl, {
            "aria-label": i("Video’s ook later laden", "ultracache-pro"),
            checked: embedsEnabled,
            disabled: e.disabled,
            onChange: function() {
                e.onChange(embedsEnabled ? "images" : youtubeEnabled ? "youtube" : "iframes");
            }
        }) : null) : null, embedsEnabled ? a("div", {
            className: "ucp-dependent-toggle-group__option"
        }, a("span", {}, i("Lichte YouTube-weergave", "ultracache-pro")), FormToggleControl ? a(FormToggleControl, {
            "aria-label": i("Lichte YouTube-weergave", "ultracache-pro"),
            checked: youtubeEnabled,
            disabled: e.disabled,
            onChange: function() {
                e.onChange(youtubeEnabled ? "iframes" : "youtube");
            }
        }) : null) : null);
    }
    function UCPMediaLcpControl(e) {
        var value = String(e.value || "off"), enabled = "off" !== value;
        return a("div", {
            className: "ucp-dependent-toggle-group ucp-dependent-toggle-group--compact"
        }, FormToggleControl ? a(FormToggleControl, {
            "aria-label": i("Hoofdafbeelding direct laden", "ultracache-pro"),
            checked: enabled,
            disabled: e.disabled,
            onChange: function() {
                e.onChange(enabled ? "off" : "recommended");
            }
        }) : a(b, {
            label: i("Hoofdafbeelding direct laden", "ultracache-pro"),
            checked: enabled,
            disabled: e.disabled,
            onChange: function(active) {
                e.onChange(active ? "recommended" : "off");
            }
        }));
    }
    function Ba(e) {
        var c = e.field[0], o = e.field[1], controlType = e.field[2], l = e.field[3] || "", u = e.field[4] || [], d = r(!1), p = d[0], h = d[1], fieldSaveHook = r("idle"), fieldSaveState = fieldSaveHook[0], setFieldSaveState = fieldSaveHook[1], y = r(!1), N = y[0], S = y[1], confirmationState = r(null), pendingConfirmation = confirmationState[0], setPendingConfirmation = confirmationState[1], j = xa(c, e.settings), isProtectedRuleField = -1 !== [ "exclude_urls", "preload_exclude_urls", "speculation_exclusions" ].indexOf(c), storedRuleBreakdown = isProtectedRuleField ? UCPRuleBreakdown(c, j) : null, C = function(e, a, t) {
            var r = t && t.system || {}, n = r.combineLocks || {}, c = [];
            "enable_css_combine" === e && !(c = n.css || []).length && a.css_delivery_mode && "none" !== a.css_delivery_mode && c.push(i("CSS-levering optimaliseren is actief. CSS combineren is daarom vergrendeld.", "ultracache-pro"));
            "enable_js_combine" === e && (!(c = n.js || []).length && parseInt(a.enable_delay_js || 0, 10) && c.push(i("Delay JS is actief. JavaScript combineren wordt vergrendeld om de uitvoervolgorde te beschermen.", "ultracache-pro")),
            c.length || !parseInt(a.defer_all_js || 0, 10) || c.push(i("JavaScript defer is actief. JavaScript combineren is daarom vergrendeld.", "ultracache-pro"))),
            parseInt(a.accessibility_mode || 0, 10) && -1 !== [ "enable_js_minify", "enable_js_combine", "delay_js_control", "defer_all_js", "enable_lazy_render" ].indexOf(e) && c.push(i("Directe interacties behouden is actief. Deze optimalisatie blijft uit om menu’s, formulieren en checkout direct bruikbaar te houden.", "ultracache-pro")),
            "enable_woocommerce_rules" === e && parseInt(a.serve_cache_to_shoppers || 0, 10) && c.push(i("Webshopcache voor bezoekers vereist de WooCommerce-veiligheidsregels.", "ultracache-pro")),
            "preload_mode" === e && !parseInt(a.enable_cache || 0, 10) && c.push(i("Schakel pagina’s versnellen in voordat je pagina’s vooraf opbouwt.", "ultracache-pro"));
            return c.length ? c[0] : "";
        }(c, e.settings || {}, e.status || {}), z = !!C, x = r(UCPRuleEditorValue(c, j)), A = x[0], I = x[1];
        function D(a, confirmed) {
            if (z) e.addNotice({
                status: "warning",
                message: C
            }); else {
                var confirmation = confirmed ? null : UCPSettingConfirmation(c, j, a);
                if (confirmation) {
                    setPendingConfirmation(Object.assign({
                        value: a
                    }, confirmation));
                    return;
                }
                if (isProtectedRuleField) {
                    a = UCPProtectedRuleDefaults(c).concat(UCPUniqueRuleLines(a)).join("\n");
                }
                var t = Object.assign({}, e.settings || {}), r = function(e, a, t) {
                    return e[a] = t, "show_advanced_options" === a && (e.ui_mode = t ? "advanced" : "simple"),
                    "db_cleanup_frequency" === a && (e.enable_db_cleanup = "off" === t ? 0 : 1), za[a] && za[a].apply(e, t, e),
                    "heartbeat_frontend_behavior" !== a && "heartbeat_editor_behavior" !== a && "heartbeat_backend_behavior" !== a || (e.enable_heartbeat_control = Na(e)),
                    e;
                }(Object.assign({}, t), c, a);
                e.setSettings(r), h(!0), S(!1), setFieldSaveState("saving");
                var n = function(e, a, t) {
                    var r = {};
                    return r[e] = a, za[e] && (r = {}, za[e].apply(r, a, t || {})),
                    "show_advanced_options" === e && (r.ui_mode = a ? "advanced" : "simple"), "db_cleanup_frequency" === e && (r.enable_db_cleanup = "off" === a ? 0 : 1),
                    "heartbeat_frontend_behavior" !== e && "heartbeat_editor_behavior" !== e && "heartbeat_backend_behavior" !== e || (r.enable_heartbeat_control = Na(t || {})), r;
                }(c, a, r);
                H(n).then(function(a) {
                    e.setSettings(a.settings), a.status && e.setStatus && e.setStatus(a.status), setFieldSaveState("saved"), e.addNotice({
                        status: UCPS(a),
                        message: UCPM(a, s(i("%s opgeslagen.", "ultracache-pro"), o))
                    });
                }).catch(function(a) {
                    e.setSettings(t), I(UCPRuleEditorValue(c, t[c])), setFieldSaveState("error"), e.addNotice({
                        status: "error",
                        message: G(a, s(i("%s kon niet worden opgeslagen.", "ultracache-pro"), o))
                    });
                }).finally(function() {
                    h(!1);
                });
            }
        }
        n(function() {
            I(UCPRuleEditorValue(c, j)), S(!1);
        }, [ j, c ]);
        n(function() {
            if ("saved" !== fieldSaveState) return;
            var timer = window.setTimeout(function() {
                setFieldSaveState("idle");
            }, 1400);
            return function() {
                window.clearTimeout(timer);
            };
        }, [ fieldSaveState ]);
        var textareaPlaceholders = {
            exclude_cookies: "woocommerce_cart_hash",
            exclude_user_agents: "Googlebot-Image",
            cache_vary_cookies: "woocommerce_currency",
            exclude_urls: "/checkout/",
            preload_exclude_urls: "/checkout/",
            speculation_exclusions: "/checkout/",
            always_purge_urls: "/blog/",
            cdn_exclude: "/wp-content/uploads/private/",
            cdn_cnames: "cdn.example.com",
            lazyload_exclusions: ".site-logo img",
            preload_fonts: "",
            image_cdn_widths: "320",
            cache_query_string_inclusions: "orderby",
            lazy_render_selectors: ".below-the-fold"
        }, textareaPlaceholder = textareaPlaceholders[c] || "", U, riskActive = UCPRiskIsActive(c, j), P = e.hideRiskNotice ? null : z ? a(m, {
            status: "info",
            isDismissible: !1
        }, C) : riskActive && Wa(c) ? a(m, {
            status: "warning",
            isDismissible: !1
        }, Wa(c)) : null, T = e.hideRiskBadge || !riskActive ? null : a(Ma, {
            settingKey: c
        });
        var UCPLineInfo = -1 !== UCPLineBasedSettings.indexOf(c) ? UCPLineDiagnostics(A) : null, protectedMissing = storedRuleBreakdown ? storedRuleBreakdown.missingProtected.length : 0;
        U = "toggle" === controlType ? a("div", {
            className: "ucp-toggle-save-row"
        }, e.hideInlineHelp && FormToggleControl ? a(FormToggleControl, {
            className: "ucp-form-toggle--row",
            checked: !!parseInt(j || 0, 10),
            disabled: p || z,
            "aria-label": o,
            onChange: function(e) {
                var a = e && e.target ? !!e.target.checked : !parseInt(j || 0, 10);
                D(a ? 1 : 0);
            }
        }) : a(b, {
            className: e.hideInlineHelp ? "ucp-toggle-control--row" : "",
            label: o,
            help: e.hideInlineHelp ? void 0 : l,
            checked: !!parseInt(j || 0, 10),
            disabled: p || z,
            onChange: function(e) {
                D(e ? 1 : 0);
            }
        })) : "image_cdn_widths" === c && k ? a(La, {
            label: o,
            help: l,
            currentValue: j,
            saving: p || z,
            dirty: N,
            setDirty: S,
            commit: D
        }) : "textarea" === controlType ? a(t, {}, isProtectedRuleField ? a("div", { className: "ucp-protected-rules" }, a("div", { className: "ucp-protected-rules__summary" }, a("span", { className: "ucp-protected-rules__icon dashicons dashicons-shield", "aria-hidden": "true" }), a("span", { className: "ucp-protected-rules__copy" }, a("strong", {}, s(i("%1$d ingebouwde veiligheidsregels · %2$d eigen regels", "ultracache-pro"), storedRuleBreakdown.protectedLines.length, UCPLineInfo ? UCPLineInfo.unique : 0)), a("small", {}, i("Voeg alleen extra uitzonderingen toe; ingebouwde regels blijven behouden.", "ultracache-pro"))), protectedMissing ? a(ee, { state: "warning" }, s(i("%d ontbreekt", "ultracache-pro"), protectedMissing)) : a(ee, { state: "good" }, i("Compleet", "ultracache-pro"))), a("details", { className: "ucp-protected-rules__details" }, a("summary", {}, i("Ingebouwde veiligheidsregels bekijken", "ultracache-pro")), a("code", { className: "ucp-protected-rules__list" }, storedRuleBreakdown.protectedLines.join("\n")))) : null, a(v, {
            label: isProtectedRuleField ? i("Eigen regels", "ultracache-pro") : o,
            hideLabelFromVision: !isProtectedRuleField,
            help: isProtectedRuleField ? i("Voeg alleen extra uitzonderingen toe; ingebouwde regels blijven behouden.", "ultracache-pro") : e.hideInlineHelp ? "" : l,
            value: A || "",
            placeholder: textareaPlaceholder,
            rows: "lazyload_exclusions" === c || "preload_fonts" === c ? 4 : UCPLineInfo ? 5 : 4,
            className: UCPLineInfo ? "ucp-rule-textarea" : "",
            disabled: p || z,
            onChange: function(e) {
                I(e), S(!0), setFieldSaveState("idle");
            }
        }), UCPLineInfo ? a("div", {
            className: "ucp-rule-meta" + (UCPLineInfo.duplicates ? " ucp-rule-meta--warning" : ""),
            role: "status"
        }, UCPLineInfo.duplicates ? s(i("%1$d unieke eigen regels; %2$d dubbele regels gevonden.", "ultracache-pro"), UCPLineInfo.unique, UCPLineInfo.duplicates) : isProtectedRuleField ? s(i("%d unieke eigen regels.", "ultracache-pro"), UCPLineInfo.unique) : s(i("%d unieke regels.", "ultracache-pro"), UCPLineInfo.unique)) : null, a("div", { className: "ucp-rule-editor-actions" }, protectedMissing ? a(g, { variant: "secondary", isBusy: p, disabled: p || z, onClick: function() { D(A || ""); } }, i("Veiligheidsregels herstellen", "ultracache-pro")) : null, N ? a(g, {
            variant: "primary",
            isBusy: p,
            disabled: p || z,
            "aria-label": s(i("%s opslaan", "ultracache-pro"), o),
            onClick: function() {
                D(A || "");
            }
        }, i("Opslaan", "ultracache-pro")) : null)) : "number" === controlType ? a(t, {}, a(w, {
            label: o,
            hideLabelFromVision: !e.showControlLabel,
            help: e.hideInlineHelp ? "" : l,
            value: A || 0,
            min: UCPNumberBounds[c] ? UCPNumberBounds[c].min : void 0,
            max: UCPNumberBounds[c] ? UCPNumberBounds[c].max : void 0,
            disabled: p || z,
            onChange: function(e) {
                I(e), S(!0), setFieldSaveState("idle");
            }
        }), N ? a(g, {
            variant: "primary",
            isBusy: p,
            disabled: p || z,
            "aria-label": s(i("%s opslaan", "ultracache-pro"), o),
            onClick: function() {
                D(parseInt(A || 0, 10));
            }
        }, i("Opslaan", "ultracache-pro")) : null) : "radio" === controlType ? a(UCPRadioChoiceControl, {
            label: o,
            value: j || (u[0] ? u[0][0] : ""),
            options: u,
            disabled: p || z,
            onChange: D
        }) : "media_lazyload" === controlType ? a(UCPMediaLazyloadControl, {
            label: o,
            value: j || "off",
            disabled: p || z,
            onChange: D
        }) : "media_lcp_toggle" === controlType ? a(UCPMediaLcpControl, {
            label: o,
            value: j || "off",
            disabled: p || z,
            onChange: D
        }) : "select" === controlType ? "html_optimization_mode" === c ? a(Za, {
            label: o,
            help: e.hideInlineHelp ? "" : l,
            hideDescriptions: !!e.hideInlineHelp,
            value: j || (u[0] ? u[0][0] : ""),
            options: u,
            disabled: p || z,
            onChange: D,
            descriptions: {
                off: i("Geen HTML-aanpassing. Gebruik dit bij gevoelige templates of debugwerk.", "ultracache-pro"),
                comments: i("Verwijdert alleen HTML-comments. Rustige keuze met minimale visuele impact.", "ultracache-pro"),
                minify: i("Minificeert HTML en verwijdert comments. Test builders en checkout.", "ultracache-pro")
            }
        }) : "image_optimization_mode" === c ? a(Ka, {
            label: o,
            help: e.hideInlineHelp ? "" : l,
            hideSummary: !!e.hideInlineHelp,
            value: j || (u[0] ? u[0][0] : "off"),
            options: u,
            disabled: p || z,
            onChange: D,
            descriptions: {
                off: i("Geen upload-aanpassing. Veilig wanneer een andere image optimizer actief is.", "ultracache-pro"),
                webp: i("Optimaliseert nieuwe uploads en maakt WebP-varianten waar mogelijk.", "ultracache-pro"),
                webp_avif: i("Maakt WebP en AVIF naast optimalisatie. Test opslag, CDN en browserfallback.", "ultracache-pro")
            }
        }) : "media_lazyload_mode" === c ? a(Ka, {
            label: o,
            help: e.hideInlineHelp ? "" : l,
            hideSummary: !!e.hideInlineHelp,
            value: j || (u[0] ? u[0][0] : "off"),
            options: u,
            disabled: p || z,
            onChange: D,
            descriptions: {
                off: i("Geen lazyload-aanpassing. Veilig bij gevoelige sliders, hero’s of debugwerk.", "ultracache-pro"),
                images: i("Lazyload media buiten beeld; controleer logo, hero en productbeeld.", "ultracache-pro"),
                iframes: i("Lazyload media en embeds. Test video, kaarten en formulieren.", "ultracache-pro"),
                youtube: i("Vervangt YouTube-embeds door previews. Test consent en tracking.", "ultracache-pro")
            }
        }) : a(_, {
            label: o,
            hideLabelFromVision: !0,
            help: e.hideInlineHelp ? "" : l,
            value: j || (u[0] ? u[0][0] : ""),
            options: u.map(function(e) {
                return {
                    value: e[0],
                    label: e[1]
                };
            }),
            disabled: p || z,
            onChange: function(e) {
                D(e);
            }
        }) : "lcp_images" === controlType ? a(Da, {
            label: o,
            help: l,
            currentValue: j,
            options: u,
            settings: e.settings,
            saving: p || z,
            dirty: N,
            setDirty: S,
            setSaving: h,
            setSettings: e.setSettings,
            setStatus: e.setStatus,
            addNotice: e.addNotice,
            commit: D,
            hideInlineHelp: !!e.hideInlineHelp
        }) : "heartbeat_interval" === controlType ? a(Pa, {
            label: o,
            help: l,
            currentValue: j,
            options: u,
            settings: e.settings,
            saving: p || z,
            dirty: N,
            setDirty: S,
            setSaving: h,
            setSettings: e.setSettings,
            setStatus: e.setStatus,
            addNotice: e.addNotice,
            commit: D
        }) : "css_delivery" === controlType ? a(Va, {
            label: o,
            help: e.hideInlineHelp ? "" : l,
            value: j || "none",
            options: u,
            saving: p || z,
            onChange: D,
            hideInlineHelp: !!e.hideInlineHelp
        }) : a(t, {}, a(f, {
            label: o,
            hideLabelFromVision: !0,
            help: e.hideInlineHelp ? "" : l,
            type: Ca(c) ? "password" : "text",
            autoComplete: Ca(c) ? "new-password" : "off",
            value: A || "",
            disabled: p || z,
            onChange: function(e) {
                I(e), S(!0), setFieldSaveState("idle");
            }
        }), N ? a(g, {
            variant: "primary",
            isBusy: p,
            disabled: p || z,
            "aria-label": s(i("%s opslaan", "ultracache-pro"), o),
            onClick: function() {
                D(A || "");
            }
        }, i("Opslaan", "ultracache-pro")) : null);
        var confirmationDialog = pendingConfirmation ? a(Se, {
            title: pendingConfirmation.title,
            eyebrow: i("Extra bevestiging", "ultracache-pro"),
            onClose: function() {
                setPendingConfirmation(null);
            },
            footer: a("div", {
                className: "ucp-modal-actions"
            }, a(g, {
                variant: "secondary",
                onClick: function() {
                    setPendingConfirmation(null);
                }
            }, i("Annuleren", "ultracache-pro")), a(g, {
                variant: "primary",
                isDestructive: !!pendingConfirmation.destructive,
                onClick: function() {
                    var value = pendingConfirmation.value;
                    setPendingConfirmation(null);
                    D(value, !0);
                }
            }, pendingConfirmation.confirmLabel || i("Doorgaan", "ultracache-pro")))
        }, a("p", {
            className: "ucp-dialog-intro"
        }, pendingConfirmation.text)) : null, L = function(e, a) {
            var t = String(e && e[0] || ""), r = String(e && e[1] || ""), n = String(e && e[2] || ""), c = String(e && e[3] || "");
            return !(!{
                toggle: !0,
                media_lcp_toggle: !0,
                number: !0,
                select: !0,
                text: !0
            }[n] || "text" === n && /(exclude|exclusion|urls|uri|scope|include|safelist|pattern|agent|param|path|regels|rule)/i.test(t + " " + r) || c.length > 135 || r.length > 44 || "database" === a && /(all_transients|optimize_tables|trashed|spam|revisions)/i.test(t) || /(delivery|combine|delay|critical|used_css|cleanup|delete|remove|purge|unsafe|risk)/i.test(t) && c.length > 78);
        }(e.field, e.kind) ? " is-rowable" : " is-stacked";
        return a("div", {
            className: "ucp-setting-field ucp-setting-field--" + controlType + L,
            "data-ucp-field-key": c
        }, T, U, "idle" !== fieldSaveState ? a("span", {
            className: "ucp-field-save-state ucp-field-save-state--" + fieldSaveState,
            role: "status",
            "aria-live": "polite",
            "aria-atomic": "true"
        }, {
            saving: i("Opslaan…", "ultracache-pro"),
            saved: i("Opgeslagen", "ultracache-pro"),
            error: i("Opslaan mislukt — probeer opnieuw", "ultracache-pro")
        }[fieldSaveState] || "") : null, P, confirmationDialog);
    }
    function Ka(e) {
        var t = e.options || [], r = e.value || (t[0] ? t[0][0] : ""), n = e.descriptions || {}, c = t.filter(function(e) {
            return String(e[0]) === String(r);
        })[0] || t[0] || [ "", "" ], o = String(c[0] || "");
        return a("div", {
            className: "ucp-compact-select-control"
        }, a("div", {
            className: "ucp-compact-select-control__head"
        }, a("span", {
            className: "ucp-compact-select-control__label"
        }, e.label), e.help ? a("span", {
            className: "ucp-compact-select-control__help"
        }, e.help) : null), a(_, {
            label: e.label,
            hideLabelFromVision: !0,
            value: r,
            options: t.map(function(e) {
                return {
                    value: e[0],
                    label: e[1],
                    disabled: !!e[2]
                };
            }),
            disabled: e.disabled,
            onChange: function(a) {
                e.onChange && e.onChange(a);
            }
        }), !e.hideSummary && n[o] ? a("p", {
            className: "ucp-compact-select-control__summary"
        }, n[o]) : null);
    }
    function Za(e) {
        var t = e.options || [], r = String(e.value || (t[0] ? t[0][0] : "")), n = e.descriptions || {}, c = "ucp-choice-" + D(e.label || "setting");
        return a("div", {
            className: "ucp-radio-list",
            role: "radiogroup",
            "aria-label": e.label
        }, t.map(function(t) {
            var o = String(t[0]), i = o === r;
            return a("label", {
                key: o,
                className: "ucp-radio-list__option" + (i ? " is-selected" : "")
            }, a("input", {
                type: "radio",
                name: c,
                value: o,
                checked: i,
                disabled: e.disabled,
                onChange: function() {
                    !e.disabled && e.onChange && e.onChange(o);
                }
            }), a("span", {
                className: "ucp-radio-list__copy"
            }, a("strong", {}, t[1]), !e.hideDescriptions && n[o] ? a("small", {}, n[o]) : null));
        }));
    }
    function Va(e) {
        var t = e.options || [], r = e.value || "none", n = {
            none: i("Normale CSS-levering met maximale compatibiliteit.", "ultracache-pro"),
            remove_unused: i("Verwijdert ongebruikte CSS. Test belangrijke pagina’s.", "ultracache-pro"),
            async: i("Laadt CSS asynchroon. Test de vormgeving daarna.", "ultracache-pro")
        };
        return a(Za, {
            label: e.label,
            help: e.help,
            value: r,
            options: t,
            disabled: e.saving,
            onChange: e.onChange,
            descriptions: n,
            hideDescriptions: !!e.hideInlineHelp
        });
    }
    function qa(e) {
        return {
            enable_delay_js: {
                level: "staging",
                label: i("Eerst testen", "ultracache-pro"),
                text: i("Kan formulieren, sliders en checkout beïnvloeden. Test op staging.", "ultracache-pro")
            },
            enable_css_combine: {
                level: "staging",
                label: i("Eerst testen", "ultracache-pro"),
                text: i("Alleen voor HTTP/1 zonder builder-, shop- of formulierconflicten.", "ultracache-pro")
            },
            enable_js_combine: {
                level: "staging",
                label: i("Eerst testen", "ultracache-pro"),
                text: i("Alleen voor HTTP/1 zonder builder-, shop- of formulierconflicten.", "ultracache-pro")
            },
            css_delivery_mode: {
                level: "staging",
                label: i("Eerst testen", "ultracache-pro"),
                text: i("Controleer layout, builders, formulieren en checkout visueel na inschakelen.", "ultracache-pro")
            },
            delay_js_control: {
                level: "staging",
                label: i("Eerst testen", "ultracache-pro"),
                text: i("Test formulieren, sliders, consent en checkout op staging.", "ultracache-pro")
            },
            enable_rest_cache: {
                level: "staging",
                label: i("API-gevoelig", "ultracache-pro"),
                text: i("Controleer API-koppelingen en formulieren na inschakelen.", "ultracache-pro")
            },
            enable_esi: {
                level: "staging",
                label: i("Personalisatiegevoelig", "ultracache-pro"),
                text: i("Custom fragments mogen geen persoonlijke data in gedeelde cache lekken.", "ultracache-pro")
            },
            enable_headless_renderer: {
                level: "external",
                label: i("Extern endpoint", "ultracache-pro"),
                text: i("Renderer-output is extern. Test endpoint, timeout en fouten.", "ultracache-pro")
            },
            headless_renderer_endpoint: {
                level: "external",
                label: i("Extern endpoint", "ultracache-pro"),
                text: i("Gebruik alleen publieke HTTPS endpoints die je vertrouwt.", "ultracache-pro")
            },
            cdn_rewrite_mode: {
                level: "staging",
                label: i("Voor ervaren beheerders — eerst testen", "ultracache-pro"),
                text: i("Test CDN-rewrites op staging; checkout en formulieren blijven uitgesloten.", "ultracache-pro")
            },
            enable_self_host_third_party_assets: {
                level: "external",
                label: i("Externe bronnen", "ultracache-pro"),
                text: i("Controleer privacy, bronallowlist en visuele output na lokaal hosten.", "ultracache-pro")
            },
            serve_cache_to_shoppers: {
                level: "shop",
                label: i("Shopgevoelig", "ultracache-pro"),
                text: i("Alleen met uitsluitingen voor cart, checkout, account en sessies.", "ultracache-pro")
            },
            db_cleanup_drafts: {
                level: "destructive",
                label: i("Backup nodig", "ultracache-pro"),
                text: i("Concepten kunnen actief werk bevatten. Maak eerst een databasebackup.", "ultracache-pro")
            },
            db_cleanup_all_transients: {
                level: "destructive",
                label: i("Destructief", "ultracache-pro"),
                text: i("Maak eerst een database-backup. Deze actie kan niet ongedaan worden gemaakt.", "ultracache-pro")
            },
            db_cleanup_optimize_tables: {
                level: "destructive",
                label: i("Backup nodig", "ultracache-pro"),
                text: i("Maak eerst een database-backup. Deze actie kan niet ongedaan worden gemaakt.", "ultracache-pro")
            },
            db_cleanup_optimize_all_tables: {
                level: "destructive",
                label: i("Alle tabellen", "ultracache-pro"),
                text: i("Optimaliseert alle WordPress-tabellen; vereist handmatige backupbevestiging.", "ultracache-pro")
            },
            clean_uninstall: {
                level: "destructive",
                label: i("Destructief", "ultracache-pro"),
                text: i("Verwijdert plugininstellingen bij deïnstallatie. Controleer dit bewust.", "ultracache-pro")
            },
            enable_cloudflare_apo_mode: {
                level: "external",
                label: i("CDN-gevoelig", "ultracache-pro"),
                text: i("Gebruik alleen als Cloudflare correct is geconfigureerd.", "ultracache-pro")
            }
        }[e] || null;
    }
    function UCPRiskIsActive(e, value) {
        if (!qa(e)) return !1;
        if (-1 !== [ "css_delivery_mode", "delay_js_control", "cdn_rewrite_mode" ].indexOf(e)) {
            var normalized = String(null == value ? "" : value);
            return "" !== normalized && "off" !== normalized && "none" !== normalized;
        }
        return -1 !== [ "enable_delay_js", "enable_css_combine", "enable_js_combine", "serve_cache_to_shoppers", "db_cleanup_drafts", "db_cleanup_all_transients", "db_cleanup_optimize_tables", "db_cleanup_optimize_all_tables", "clean_uninstall", "enable_cloudflare_apo_mode", "enable_self_host_third_party_assets", "enable_rest_cache", "enable_esi", "enable_headless_renderer" ].indexOf(e) ? !!parseInt(value || 0, 10) : "headless_renderer_endpoint" !== e || "" !== String(value || "").trim();
    }
    function Wa(e) {
        var a = qa(e);
        return a ? a.text : "";
    }
    function Ma(e) {
        var t = qa(e.settingKey);
        return t ? a("span", {
            className: "ucp-risk-badge ucp-risk-badge--" + t.level
        }, t.label) : null;
    }
    function UCPInsightsWorkspace(e) {
        var technicalDetailsVisible = UCPExplicitSupportMode(), dataState = r({
            loading: true,
            error: "",
            insights: {},
            queue: {},
            compatibility: {},
            fragments: {}
        }), data = dataState[0], setData = dataState[1], busyState = r(""), busy = busyState[0], setBusy = busyState[1];
        function load() {
            setData(function(current) {
                return Object.assign({}, current, { loading: true, error: "" });
            });
            var technicalRequests = technicalDetailsVisible ? [ R("diagnostics/compatibility-profiles"), R("diagnostics/fragments") ] : [ Promise.resolve({}), Promise.resolve({}) ];
            return Promise.all([ R("diagnostics/cache-insights?per_page=12"), R("diagnostics/preload-queue?per_page=20") ].concat(technicalRequests)).then(function(results) {
                setData({
                    loading: false,
                    error: "",
                    insights: results[0] || {},
                    queue: results[1] || {},
                    compatibility: results[2] || {},
                    fragments: results[3] || {}
                });
            }).catch(function(error) {
                setData(function(current) {
                    return Object.assign({}, current, { loading: false, error: G(error, i("Inzicht kon niet worden geladen.", "ultracache-pro")) });
                });
            });
        }
        n(function() {
            load();
        }, []);
        function runAction(action, payload, successMessage) {
            setBusy(action + ":" + String(payload && payload.jobId || ""));
            UCPPostAction(action, payload || {}).then(function(response) {
                e.addNotice({ status: "success", message: response && response.message ? response.message : successMessage });
                return load();
            }).catch(function(error) {
                e.addNotice({ status: "error", message: G(error, i("De actie is mislukt.", "ultracache-pro")) });
            }).finally(function() {
                setBusy("");
            });
        }
        function metric(label, value, note) {
            return a("div", { className: "ucp-insight-metric", key: label }, a("span", { className: "ucp-insight-metric__label" }, label), a("strong", {}, null === value || void 0 === value ? "—" : value), note ? a("small", {}, note) : null);
        }
        function statusLabel(status, cancelled) {
            if (cancelled) return i("Geannuleerd", "ultracache-pro");
            return {
                pending: i("Wachtend", "ultracache-pro"),
                running: i("Bezig", "ultracache-pro"),
                retrying: i("Opnieuw proberen", "ultracache-pro"),
                failed: i("Mislukt", "ultracache-pro"),
                success: i("Voltooid", "ultracache-pro")
            }[status] || status || i("Onbekend", "ultracache-pro");
        }
        if (data.loading && !data.insights.success) return a("div", { className: "ucp-tools-loading", role: "status" }, i("Cache-inzicht laden…", "ultracache-pro"));
        if (data.error) return a("div", { className: "ucp-tools-inline-error", role: "alert" }, a("p", {}, data.error), a(g, { variant: "secondary", onClick: load }, i("Opnieuw laden", "ultracache-pro")));
        var insightSummary = data.insights.summary || {}, status = insightSummary.status || {}, purges = Array.isArray(data.insights.recentPurges) ? data.insights.recentPurges : [], queueRows = Array.isArray(data.queue.rows) ? data.queue.rows : [], actionableQueueRows = queueRows.filter(function(job) { return -1 !== [ "pending", "running", "retrying", "failed" ].indexOf(job.status); }), queueSummary = data.queue.summary || {}, compatibility = data.compatibility.compatibility || {}, profiles = Array.isArray(compatibility.profiles) ? compatibility.profiles : [], fragmentSummary = data.fragments.fragments || {}, fragments = Array.isArray(fragmentSummary.fragments) ? fragmentSummary.fragments : [];
        return a("div", { className: "ucp-insights-workspace" },
            a("section", { className: "ucp-insights-section", "aria-labelledby": "ucp-cache-insights-heading" },
                a("div", { className: "ucp-insights-section__header" }, a("div", {}, a("h2", { id: "ucp-cache-insights-heading" }, i("Cache-inzicht", "ultracache-pro")), a("p", {}, i("Controleert cachehits en bypassregels.", "ultracache-pro"))), technicalDetailsVisible ? a(g, { variant: "secondary", isBusy: 0 === busy.indexOf("cache-insights-reset"), disabled: !!busy, onClick: function() { runAction("cache-insights-reset", {}, i("Cache-inzicht is gewist.", "ultracache-pro")); } }, i("Inzicht wissen", "ultracache-pro")) : null),
                a("div", { className: "ucp-insight-metrics" }, metric(i("Hitratio", "ultracache-pro"), null === insightSummary.hit_ratio || void 0 === insightSummary.hit_ratio ? null : insightSummary.hit_ratio + "%", i("HIT + STALE tegenover MISS", "ultracache-pro")), metric(i("Geschatte verzoeken", "ultracache-pro"), insightSummary.estimated_requests || 0, s(i("%d%% steekproef", "ultracache-pro"), insightSummary.sample_rate || 0)), metric(i("Cachetreffers", "ultracache-pro"), (status.HIT || 0) + (status["HIT-304"] || 0) + (status.STALE || 0) + (status["REST-HIT"] || 0), ""), metric(i("Overgeslagen", "ultracache-pro"), status.BYPASS || 0, "")),
                insightSummary.direct_server_cache_enabled ? a("p", { className: "ucp-insights-coverage-note", role: "note" }, i("Direct door de webserver geleverde cachetreffers vallen buiten deze meting.", "ultracache-pro")) : null,
                a("div", { className: "ucp-insights-split" },
                    a("div", { className: "ucp-insight-panel" }, a("h3", {}, i("Overgeslagen", "ultracache-pro")), Object.keys(insightSummary.bypass_reasons || {}).length ? a("ul", { className: "ucp-insight-list" }, Object.keys(insightSummary.bypass_reasons).map(function(reason) { return a("li", { key: reason }, a("span", {}, reason.replace(/_/g, " ")), a("strong", {}, insightSummary.bypass_reasons[reason])); })) : a("p", { className: "ucp-empty-copy" }, i("Nog geen bypassgegevens.", "ultracache-pro"))),
                    a("div", { className: "ucp-insight-panel" }, a("h3", {}, i("Recente purgeacties", "ultracache-pro")), purges.length ? a("ul", { className: "ucp-insight-list ucp-insight-list--purges" }, purges.slice(0, 4).map(function(item) { return a("li", { key: item.id }, a("span", {}, item.path || "/"), a("small", {}, [ item.source, item.scope, item.created_at ].filter(Boolean).join(" · "))); })) : a("p", { className: "ucp-empty-copy" }, i("Nog geen purgeacties geregistreerd.", "ultracache-pro")))
                )
            ),
            a("section", { className: "ucp-insights-section", "aria-labelledby": "ucp-preload-inspector-heading" },
                a("div", { className: "ucp-insights-section__header" }, a("div", {}, a("h2", { id: "ucp-preload-inspector-heading" }, i("Pagina’s vooraf opbouwen", "ultracache-pro")), a("p", {}, i("Bouwt belangrijke openbare pagina’s vooraf op.", "ultracache-pro"))), a(g, { variant: "secondary", onClick: load, disabled: !!busy }, i("Vernieuwen", "ultracache-pro"))),
                a("div", { className: "ucp-insight-metrics ucp-insight-metrics--queue" }, metric(i("Wachtend", "ultracache-pro"), queueSummary.pending || 0, ""), metric(i("Bezig", "ultracache-pro"), queueSummary.running || 0, ""), metric(i("Opnieuw", "ultracache-pro"), queueSummary.retrying || 0, ""), metric(i("Mislukt", "ultracache-pro"), queueSummary.failed || 0, data.queue.loadPaused ? i("Gepauzeerd door serverbelasting", "ultracache-pro") : "")),
                actionableQueueRows.length ? a("div", { className: "ucp-preload-job-list" }, actionableQueueRows.slice(0, 4).map(function(job) {
                    var actionKey = "", actionLabel = "";
                    "failed" === job.status && (actionKey = "job-retry", actionLabel = i("Opnieuw", "ultracache-pro"));
                    -1 !== [ "pending", "retrying" ].indexOf(job.status) && (actionKey = "job-cancel", actionLabel = i("Annuleren", "ultracache-pro"));
                    return a("article", { className: "ucp-preload-job", key: job.id }, a("div", { className: "ucp-preload-job__copy" }, a("strong", {}, job.path || i("Homepage", "ultracache-pro")), a("small", {}, [ job.source || i("wachtrij", "ultracache-pro"), job.http_code ? "HTTP " + job.http_code : "", job.result_reason && "http_ok" !== job.result_reason ? job.result_reason.replace(/_/g, " ") : "", job.updated_at || job.created_at ].filter(Boolean).join(" · ")), job.last_error ? a("p", { className: "ucp-preload-job__error" }, job.last_error) : null), a("div", { className: "ucp-preload-job__status" }, a(ee, { state: "failed" === job.status ? "warning" : "success" === job.status && !job.cancelled ? "good" : "info" }, statusLabel(job.status, job.cancelled)), actionKey ? a(g, { variant: "secondary", isBusy: busy === actionKey + ":" + job.id, disabled: !!busy, "aria-label": s(i("%1$s: %2$s", "ultracache-pro"), actionLabel, job.path || i("Homepage", "ultracache-pro")), onClick: function() { runAction(actionKey, { jobId: job.id }, i("Wachtrij bijgewerkt.", "ultracache-pro")); } }, actionLabel) : null));
                })) : queueRows.length ? null : a("p", { className: "ucp-empty-copy" }, i("Er zijn nog geen preloadtaken.", "ultracache-pro"))
            ),
            technicalDetailsVisible ? a("details", { className: "ucp-settings-advanced ucp-insights-technical" },
                a("summary", {}, a("span", { className: "ucp-advanced-summary-copy" }, a("strong", {}, i("Technische details", "ultracache-pro")), a("small", {}, i("Alleen voor gerichte support, staging of ontwikkelwerk.", "ultracache-pro")))),
                a("div", { className: "ucp-settings-advanced__body" }, a("div", { className: "ucp-insights-columns" },
                    a("section", { className: "ucp-insights-section" }, a("div", { className: "ucp-insights-section__header" }, a("div", {}, a("h2", {}, i("Compatibiliteitsprofielen", "ultracache-pro")), a("p", {}, i("Versiebeheerde veiligheidsregels voor herkende plugins en infrastructuur.", "ultracache-pro")))), profiles.length ? a("div", { className: "ucp-profile-list" }, profiles.map(function(profile) { return a("article", { className: "ucp-profile-card", key: profile.key }, a("div", {}, a("strong", {}, profile.label), a("small", {}, "v" + profile.profile_version + " · " + profile.reviewed)), a(ee, { state: "good" }, i("Actief", "ultracache-pro"))); })) : a("p", { className: "ucp-empty-copy" }, "off" === compatibility.mode ? i("Automatische profielen staan uit.", "ultracache-pro") : i("Geen ondersteunde stack herkend.", "ultracache-pro"))),
                    a("section", { className: "ucp-insights-section" }, a("div", { className: "ucp-insights-section__header" }, a("div", {}, a("h2", {}, i("Fragmentplatform", "ultracache-pro")), a("p", {}, i("Eén register voor publieke serverfragmenten en dynamische clientfragmenten.", "ultracache-pro")))), a("div", { className: "ucp-fragment-state" }, a(ee, { state: fragmentSummary.server_cache_enabled ? "good" : "disabled" }, fragmentSummary.server_cache_enabled ? i("Servercache actief", "ultracache-pro") : i("Servercache uit", "ultracache-pro")), a(ee, { state: fragmentSummary.client_hydration_enabled ? "info" : "disabled" }, fragmentSummary.client_hydration_enabled ? i("Clientverversing actief", "ultracache-pro") : i("Clientverversing uit", "ultracache-pro"))), fragments.length ? a("ul", { className: "ucp-insight-list" }, fragments.map(function(fragment) { var metrics = fragment.metrics || {}; return a("li", { key: fragment.id }, a("span", {}, fragment.id), a("small", {}, s(i("%1$d hits · %2$d misses", "ultracache-pro"), metrics.hit || 0, metrics.miss || 0))); })) : a("p", { className: "ucp-empty-copy" }, i("Nog geen fragmenten geregistreerd.", "ultracache-pro")))
                ))
            ) : null
        );
    }

    function Ra(e) {
        var t = UCPAdvancedSettingsVisible(e.settings || {}), explicitSupportMode = UCPExplicitSupportMode(), workspaceState = r(function() {
            try {
                var requestedSection = new URL(window.location.href).searchParams.get("tools_section") || "status";
                return /^(?:status|actions|insights|database|transfer|diagnostics)$/.test(requestedSection) ? requestedSection : "status";
            } catch (error) {
                return "status";
            }
        }), activeSection = workspaceState[0], setActiveSection = workspaceState[1], sections = [ {
            key: "status",
            label: i("Website status", "ultracache-pro"),
            icon: "dashicons-yes-alt"
        }, {
            key: "actions",
            label: i("Snelle acties", "ultracache-pro"),
            icon: "dashicons-performance"
        }, {
            key: "insights",
            label: i("Cache-inzicht", "ultracache-pro"),
            icon: "dashicons-chart-bar"
        }, {
            key: "database",
            label: i("Database", "ultracache-pro"),
            icon: "dashicons-database"
        }, {
            key: "transfer",
            label: i("Import & export", "ultracache-pro"),
            icon: "dashicons-migrate"
        }, {
            key: "diagnostics",
            label: i("Diagnostiek", "ultracache-pro"),
            icon: "dashicons-chart-area"
        } ];
        function activateSection(sectionKey, moveFocus) {
            setActiveSection(sectionKey);
            try {
                var sectionUrl = new URL(window.location.href);
                "status" === sectionKey ? sectionUrl.searchParams.delete("tools_section") : sectionUrl.searchParams.set("tools_section", sectionKey), window.history.replaceState(null, "", sectionUrl.toString());
            } catch (error) {}
            if (moveFocus) {
                var focusTab = function() {
                    var tab = document.getElementById("ucp-tools-tab-" + sectionKey);
                    tab && tab.focus();
                };
                window.requestAnimationFrame ? window.requestAnimationFrame(focusTab) : window.setTimeout(focusTab, 0);
            }
        }
        function handleTabKeyDown(event, index) {
            var nextIndex = index;
            if ("ArrowRight" === event.key || "ArrowDown" === event.key) nextIndex = (index + 1) % sections.length; else if ("ArrowLeft" === event.key || "ArrowUp" === event.key) nextIndex = (index - 1 + sections.length) % sections.length; else if ("Home" === event.key) nextIndex = 0; else {
                if ("End" !== event.key) return;
                nextIndex = sections.length - 1;
            }
            event.preventDefault(), activateSection(sections[nextIndex].key, !0);
        }
        function renderMaintenanceSettings() {
            var maintenanceGroups = Ve.maintenance || [], rows = [ "enable_admin_queue_runner", "job_retention_days", "enable_cache_insights", "cache_insights_sample_rate", "cache_insights_retention_days" ].map(function(settingKey) {
                var field = We(maintenanceGroups, settingKey);
                if (!field || !UCPShouldRenderSetting(settingKey, e.settings, e.status || {})) return null;
                var definition = field.slice(), type = definition[2] || "text";
                if (-1 !== [ "cache_insights_sample_rate", "cache_insights_retention_days" ].indexOf(settingKey) && !UCPSettingEnabled(e.settings || {}, "enable_cache_insights") && !t) return null;
                return UCPSettingsRow({
                    key: settingKey,
                    title: definition[1],
                    description: definition[3] || "",
                    className: "ucp-maintenance-setting-row ucp-settings-row--control-" + D(type),
                    control: a("div", {
                        className: "ucp-settings-control ucp-settings-control--" + type + " ucp-settings-control--hide-primary-label"
                    }, a(Ba, {
                        field: definition,
                        kind: "maintenance",
                        settings: e.settings,
                        status: e.status || {},
                        setSettings: e.setSettings,
                        addNotice: e.addNotice,
                        setStatus: e.setStatus,
                        hideInlineHelp: !0
                    }))
                });
            }).filter(Boolean);
            return rows.length ? a("details", {
                className: "ucp-settings-advanced ucp-maintenance-queue-settings"
            }, a("summary", {}, a("span", {
                className: "ucp-advanced-summary-copy"
            }, a("strong", {}, i("Taakverwerking", "ultracache-pro")), a("small", {}, i("Pakt taken op als WordPress even achterloopt.", "ultracache-pro"))), a(ee, {
                state: UCPSettingEnabled(e.settings || {}, "enable_admin_queue_runner") ? "info" : "disabled"
            }, UCPSettingEnabled(e.settings || {}, "enable_admin_queue_runner") ? i("Actief", "ultracache-pro") : i("Uit", "ultracache-pro"))), a("div", {
                className: "ucp-settings-advanced__body"
            }, a("div", {
                className: "ucp-settings-list ucp-hybrid-settings-grid"
            }, rows))) : null;
        }
        function renderSection(sectionKey) {
            if ("status" === sectionKey) return a("div", {
                className: "ucp-tools-panel-content ucp-tools-panel-content--status"
            }, a(UCPWebsiteControlCenter, e));
            if ("insights" === sectionKey) return a("div", {
                className: "ucp-tools-panel-content ucp-tools-panel-content--insights"
            }, a(UCPInsightsWorkspace, e));
            if ("database" === sectionKey) return a("div", {
                className: "ucp-tools-panel-content ucp-tools-panel-content--database"
            }, a(Ee, Object.assign({}, e, {
                kind: "database",
                hideHero: !0
            })));
            if ("transfer" === sectionKey) return a("div", {
                className: "ucp-tools-panel-content ucp-tools-panel-content--transfer"
            }, a(Be, Object.assign({}, e, {
                embedded: !0
            })));
            if ("diagnostics" === sectionKey) return a("div", {
                className: "ucp-tools-panel-content ucp-tools-panel-content--diagnostics"
            }, a(Ee, Object.assign({}, e, {
                kind: "diagnostics",
                hideHero: !0
            })));
            return a("div", {
                className: "ucp-tools-panel-content ucp-tools-panel-content--actions"
            }, a(Le, Object.assign({}, e, {
                title: i("Onderhoud", "ultracache-pro"),
                toolsPage: !0,
                includeImportExport: !1
            })), renderMaintenanceSettings());
        }
        return a("div", {
            className: "ucp-settings-page ucp-settings-page--tools ucp-cache-tools-page" + (t ? " ucp-settings-page--advanced" : "") + (explicitSupportMode ? " ucp-settings-page--support" : "")
        }, UCPSectionHero({
            eyebrow: i("Onderhoud", "ultracache-pro"),
            title: i("Onderhoud en herstel", "ultracache-pro"),
            description: i("Beheer cacheacties, database, import/export en diagnostiek.", "ultracache-pro"),
            badge: explicitSupportMode ? i("Supportmodus actief", "ultracache-pro") : null,
            badgeClass: explicitSupportMode ? "ucp-status-badge--warning" : ""
        }), a("div", {
            className: "ucp-tools-workspace"
        }, a("label", {
            className: "ucp-tools-workspace-picker"
        }, a("span", {
            className: "ucp-tools-workspace-picker__label"
        }, i("Onderhoudsonderdeel", "ultracache-pro")), a("select", {
            className: "ucp-tools-workspace-picker__select",
            value: activeSection,
            onChange: function(event) {
                activateSection(event.target.value, !1);
            }
        }, sections.map(function(section) {
            return a("option", {
                key: section.key,
                value: section.key
            }, section.label);
        }))), a("div", {
            className: "ucp-tools-workspace-nav",
            role: "tablist",
            "aria-label": i("Onderhoudsonderdelen", "ultracache-pro"),
            "aria-orientation": "horizontal"
        }, sections.map(function(section, index) {
            var selected = activeSection === section.key;
            return a("button", {
                key: section.key,
                type: "button",
                id: "ucp-tools-tab-" + section.key,
                className: "ucp-tools-workspace-tab" + (selected ? " is-active" : ""),
                role: "tab",
                "aria-selected": selected,
                "aria-controls": "ucp-tools-panel-" + section.key,
                tabIndex: selected ? 0 : -1,
                onClick: function() {
                    activateSection(section.key, !1);
                },
                onKeyDown: function(event) {
                    handleTabKeyDown(event, index);
                }
            }, a("span", {
                className: "dashicons " + section.icon,
                "aria-hidden": "true"
            }), a("span", {
                className: "ucp-tools-workspace-tab__label"
            }, section.label));
        })), a("div", {
            className: "ucp-tools-workspace-panels"
        }, sections.map(function(section) {
            var selected = activeSection === section.key;
            return a("section", {
                key: section.key,
                id: "ucp-tools-panel-" + section.key,
                className: "ucp-tools-workspace-panel ucp-tools-workspace-panel--" + section.key,
                role: "tabpanel",
                "aria-labelledby": "ucp-tools-tab-" + section.key,
                tabIndex: selected ? 0 : -1,
                hidden: !selected
            }, selected ? renderSection(section.key) : null);
        }))));
    }
    function Ga() {
        return a("div", {
            className: "ucp-loading-screen",
            role: "status",
            "aria-live": "polite",
            "aria-busy": "true"
        }, a("div", {
            className: "ucp-loading-card"
        }, a("div", {
            className: "ucp-loading-mark",
            "aria-hidden": "true"
        }, a("span", {
            className: "ucp-loading-ring"
        }), a("span", {
            className: "ucp-loading-dot"
        })), a("div", {
            className: "ucp-loading-copy"
        }, a("h1", {}, i("UltraCache Pro wordt geladen", "ultracache-pro")), a("p", {}, i("Cache-instellingen en status worden geladen.", "ultracache-pro")))));
    }
    function UCPInitializationErrorState(e) {
        var issues = Array.isArray(e.issues) ? e.issues : [];
        return a("section", {
            className: "ucp-initialization-error",
            role: "alert",
            "aria-labelledby": "ucp-initialization-error-title"
        }, a("span", {
            className: "dashicons dashicons-warning",
            "aria-hidden": "true"
        }), a("div", {
            className: "ucp-initialization-error__content"
        }, a("h2", {
            id: "ucp-initialization-error-title"
        }, i("Status kon niet worden geladen", "ultracache-pro")), a("p", {}, i("Instellingen of status ontbreken; de interface blijft alleen-lezen.", "ultracache-pro")), issues.length ? a("ul", {}, issues.map(function(issue, index) {
            return a("li", {
                key: issue.source || index
            }, issue.message);
        })) : null, a("p", {
            className: "ucp-initialization-error__help"
        }, i("Controleer de verbinding. Start extra controles alleen als het probleem blijft.", "ultracache-pro")), a(g, {
            variant: "primary",
            isBusy: !!e.loading,
            disabled: !!e.loading,
            onClick: e.onRetry
        }, e.loading ? i("Opnieuw laden…", "ultracache-pro") : i("Opnieuw proberen", "ultracache-pro"))));
    }
    function Ea() {
        var e = r(I), t = e[0], c = e[1], o = r(null), s = o[0], l = o[1], u = r(null), d = u[0], p = u[1], h = r(!0), g = h[0], m = h[1], b = r([]), f = b[0], v = b[1], _ = r(!1), k = _[0], y = _[1], wizardDismissState = r(!1), wizardDismissing = wizardDismissState[0], setWizardDismissing = wizardDismissState[1], wizardDismissErrorState = r(""), wizardDismissError = wizardDismissErrorState[0], setWizardDismissError = wizardDismissErrorState[1], q = r("idle"), B = q[0], O = q[1], initializationIssueState = r([]), initializationIssues = initializationIssueState[0], setInitializationIssues = initializationIssueState[1];
        function w(e) {
            var a = Object.assign({
                id: Date.now() + Math.random(),
                status: "info",
                message: "",
                persistent: !1,
                timeout: 0
            }, e);
            a.status = -1 !== [ "success", "warning", "error", "info" ].indexOf(a.status) ? a.status : "info", a.message = String(a.message || "").trim(), a.persistent = !!a.persistent;
            if (!a.message) return;
            v(function(e) {
                return UCPQueueNotice(e, a);
            });
        }
        function N(e) {
            v(function(a) {
                return a.filter(function(a) {
                    return a.id !== e;
                });
            });
        }
        function dismissWizard() {
            if (wizardDismissing) return;
            setWizardDismissError(""), setWizardDismissing(!0), H({
                onboarding_completed: 1
            }).then(function(response) {
                response && response.settings && p(response.settings), y(!1);
            }).catch(function(error) {
                setWizardDismissError(G(error, i("De configuratie-assistent kon niet worden gesloten. Probeer opnieuw.", "ultracache-pro")));
            }).finally(function() {
                setWizardDismissing(!1);
            });
        }
        function openWizard() {
            setWizardDismissError(""), y(!0);
        }
        function S(e) {
            var a = A(e);
            c(a);
            try {
                var t = new URL(window.location.href);
                t.searchParams.set("page", "ultracache-pro"), t.searchParams.set("tab", "dashboard" === a ? "overview" : a),
                window.history.replaceState(null, "", t.toString());
            } catch (e) {}
        }
        function j(resetState) {
            m(!0), setInitializationIssues([]), !0 === resetState && (l(null), p(null));
            var statusRequest = R("status").then(function(response) {
                l(UCPRememberRuntimeStatus(response && response.status ? response.status : {}));
                return null;
            }).catch(function(error) {
                return {
                    source: "status",
                    status: "error",
                    message: G(error, i("UltraCache status kon niet geladen worden.", "ultracache-pro"))
                };
            });
            var settingsRequest = R("settings").then(function(response) {
                p(response && response.settings ? response.settings : {});
                return null;
            }).catch(function(error) {
                return {
                    source: "settings",
                    status: "error",
                    message: G(error, i("UltraCache instellingen konden niet geladen worden.", "ultracache-pro"))
                };
            });
            Promise.all([ statusRequest, settingsRequest ]).then(function(results) {
                setInitializationIssues(results.filter(Boolean));
            }).finally(function() {
                m(!1);
            });
        }
        if (n(function() {
            j(!0);
        }, []), n(function() {
            function e(e) {
                O(e && e.detail && e.detail.state || "idle");
            }
            return window.addEventListener("ucp:settings-save-state", e), function() {
                window.removeEventListener("ucp:settings-save-state", e);
            };
        }, []), n(function() {
            function handleSettingsSaved(event) {
                var response = event && event.detail ? event.detail : {}, adjusted = Array.isArray(response.automaticAdjustedKeys) ? response.automaticAdjustedKeys : [], snapshotId = response.snapshotId || "", message = adjusted.length ? String(i("Opgeslagen. %d gekoppelde instellingen zijn aangepast.", "ultracache-pro")).replace("%d", String(adjusted.length)) : i("Instellingen opgeslagen.", "ultracache-pro");
                response.settings && p(response.settings), response.status && l(UCPRememberRuntimeStatus(response.status));
                w({
                    id: "ucp-settings-saved",
                    status: UCPW(response) ? "warning" : "success",
                    message: message,
                    timeout: snapshotId ? 9e3 : 4500,
                    actionLabel: snapshotId ? i("Ongedaan maken", "ultracache-pro") : "",
                    onAction: snapshotId ? function() {
                        w({ id: "ucp-settings-saved", status: "info", persistent: !0, message: i("Vorige instellingen worden hersteld…", "ultracache-pro") }), R("settings/snapshots/restore", { method: "POST", data: { id: snapshotId } }).then(function(result) {
                            result.settings && p(result.settings), result.status && l(UCPRememberRuntimeStatus(result.status)), w({ id: "ucp-settings-saved", status: "success", message: i("Vorige instellingen hersteld.", "ultracache-pro") });
                        }).catch(function(error) {
                            w({ id: "ucp-settings-saved", status: "error", message: G(error, i("Ongedaan maken mislukt.", "ultracache-pro")) });
                        });
                    } : null
                });
            }
            return window.addEventListener("ucp:settings-saved", handleSettingsSaved), function() {
                window.removeEventListener("ucp:settings-saved", handleSettingsSaved);
            };
        }, []), g && (null === d || null === s)) return a(Ga, {});
        var hasInitializationError = initializationIssues.length > 0 || null === d || null === s, statusKnown = null !== s && !initializationIssues.some(function(issue) {
            return "status" === issue.source;
        });
        if (hasInitializationError) return a(te, {
            activeTab: t,
            saveState: "idle",
            statusKnown: statusKnown,
            readOnly: !0,
            onTab: S,
            onRefresh: j,
            loading: g,
            onOpenWizard: null,
            settings: d || {},
            status: s || {},
            addNotice: w,
            setStatus: l
        }, a(X, {
            notices: f,
            onRemove: N
        }), a(UCPInitializationErrorState, {
            issues: initializationIssues,
            loading: g,
            onRetry: function() {
                j(!0);
            }
        }));
        var z = C.filter(function(e) {
            return "woocommerce" !== e.key || Me(s || {});
        }).map(function(e) {
            return e.key;
        }), D = -1 !== z.indexOf(t) ? t : "dashboard";
        var T = {
            settings: d || {},
            setSettings: p,
            status: s || {},
            setStatus: l,
            addNotice: w,
            onRefresh: j,
            loading: g,
            onSelectTab: S
        };
        return a(te, {
            activeTab: D,
            statusKnown: !0,
            saveState: B,
            onTab: S,
            onRefresh: j,
            loading: g,
            onOpenWizard: openWizard,
            settings: d || {},
            status: s || {},
            addNotice: w,
            setStatus: l
        }, a(X, {
            notices: f,
            onRemove: N
        }), k ? a(Ae, Object.assign({}, T, {
            closing: wizardDismissing,
            error: wizardDismissError,
            onClose: dismissWizard
        })) : null, "dashboard" === D ? a(me, Object.assign({}, T, {
            status: s,
            onOpenWizard: function() {
                y(!0);
            }
        })) : null, "cache" === D ? a(Fe, Object.assign({}, T, {
            kind: "cache"
        })) : null, "optimization" === D ? a(Fe, Object.assign({}, T, {
            kind: "optimization"
        })) : null, "media" === D ? a(Fe, Object.assign({}, T, {
            kind: "media"
        })) : null, "woocommerce" === D ? a(Fe, Object.assign({}, T, {
            kind: "woocommerce"
        })) : null, "server" === D ? a(Fe, Object.assign({}, T, {
            kind: "server"
        })) : null, "advanced" === D ? a(Fe, Object.assign({}, T, {
            kind: "advanced"
        })) : null, "tools" === D ? a(Ra, Object.assign({}, T, {
            status: s
        })) : null);
    }
    function UCPAdminFatalFallback(props) {
        var message = props && props.message ? String(props.message) : i("De beheerinterface kon niet worden geladen.", "ultracache-pro");
        return a("section", {
            className: "ucp-admin-fatal-error notice notice-error",
            role: "alert"
        }, a("h1", {}, i("UltraCache Pro kon niet starten", "ultracache-pro")), a("p", {}, message), a("p", {}, i("Ververs de pagina; controleer daarna browserconsole en WordPress-debuglog.", "ultracache-pro")));
    }
    function UCPAdminErrorBoundary(props) {
        UCPComponent.call(this, props);
        this.state = { error: null };
    }
    if (UCPComponent) {
        UCPAdminErrorBoundary.prototype = Object.create(UCPComponent.prototype);
        UCPAdminErrorBoundary.prototype.constructor = UCPAdminErrorBoundary;
        UCPAdminErrorBoundary.getDerivedStateFromError = function(error) {
            return { error: error || new Error(i("De beheerinterface kon niet worden geladen.", "ultracache-pro")) };
        };
        UCPAdminErrorBoundary.prototype.componentDidCatch = function(error, info) {
            if (window.console && console.error) {
                console.error("UltraCache Pro admin render error", error, info);
            }
        };
        UCPAdminErrorBoundary.prototype.render = function() {
            return this.state.error ? a(UCPAdminFatalFallback, {
                message: this.state.error && this.state.error.message
            }) : this.props.children;
        };
    }
    document.addEventListener("DOMContentLoaded", function() {
        var root = document.getElementById("ucp-admin-root");
        if (!root) return;
        try {
            var app = UCPComponent ? a(UCPAdminErrorBoundary, {}, a(Ea)) : a(Ea);
            "function" == typeof c ? c(root).render(app) : o(app, root);
        } catch (error) {
            if (window.console && console.error) {
                console.error("UltraCache Pro admin bootstrap error", error);
            }
            root.innerHTML = "";
            var heading = document.createElement("h1");
            heading.textContent = i("UltraCache Pro kon niet starten", "ultracache-pro");
            var paragraph = document.createElement("p");
            paragraph.textContent = error && error.message ? error.message : i("De beheerinterface kon niet worden geladen.", "ultracache-pro");
            var fallback = document.createElement("section");
            fallback.className = "ucp-admin-fatal-error notice notice-error";
            fallback.setAttribute("role", "alert");
            fallback.appendChild(heading);
            fallback.appendChild(paragraph);
            root.appendChild(fallback);
        }
    });
}(window.wp);
