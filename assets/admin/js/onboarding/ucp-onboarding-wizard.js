!function(e) {
    "use strict";
    if (e && e.element && e.apiFetch) {
        var a = window.UCP_WIZARD_CONFIG || {}, t = e.element.createElement, n = e.element.Fragment, o = e.element.useState, i = e.element.useEffect, UCPUseRef = e.element.useRef, r = e.element.render, s = e.element.createRoot || null, c = e.apiFetch, l = e.i18n && e.i18n.__ ? e.i18n.__ : function(e) {
            return e;
        }, u = "ultracache-pro";
        a.nonce && c.createNonceMiddleware && c.use(c.createNonceMiddleware(a.nonce)), "loading" === document.readyState ? document.addEventListener("DOMContentLoaded", z) : z();
    }
    function d(e, t) {
        var n = String(a.restUrl || "").trim();
        if (!n) {
            return Promise.reject(new Error(l("UltraCache REST URL ontbreekt.", u)));
        }
        n = n.replace(/\/?$/, "/");
        return (t = t || {}).url = n + String(e || "").replace(/^\/+/, ""), c(t);
    }
    function p() {
        try {
            window.localStorage && window.localStorage.setItem("ucp_onboarding_dismissed", "1");
        } catch (e) {}
    }
    function m(e) {
        var a = e.goal;
        return t("button", {
            type: "button",
            className: "ucp-wiz-card" + (e.selected ? " is-selected" : ""),
            onClick: function() {
                e.onSelect(e.id);
            },
            "aria-pressed": e.selected ? "true" : "false"
        }, t("span", {
            className: "ucp-wiz-card__label"
        }, a.label), t("span", {
            className: "ucp-wiz-card__desc"
        }, a.description));
    }
    function f(e) {
        var a = e.status;
        if (e.error) return t("p", {
            className: "ucp-wiz-readiness is-error",
            role: "alert"
        }, e.error);
        if (!a) return t("p", {
            className: "ucp-wiz-readiness is-loading",
            role: "status",
            "aria-live": "polite",
            "aria-atomic": "true"
        }, l("Omgeving controleren…", u));
        var n = [], o = a.system || {}, i = a.dependencies || {};
        function r(e, a, n) {
            return t("li", {
                key: n,
                className: "ucp-wiz-readiness__row " + (e ? "is-ok" : "is-warn")
            }, t("span", {
                className: "ucp-wiz-readiness__dot",
                "aria-hidden": "true"
            }, e ? "✓" : "!"), a);
        }
        n.push(r(!0, String(l("PHP %1$s · WordPress %2$s", u)).replace("%1$s", String(o.phpVersion || "")).replace("%2$s", String(o.wpVersion || "")), "runtime")), n.push(r(!1 !== o.wpCache, !1 === o.wpCache ? l("WP_CACHE staat uit — UltraCache zet dit veilig aan bij activatie van cache", u) : l("Full-page cache kan worden geserveerd (WP_CACHE actief)", u), "page-cache"));
        var s = !i.missing || 0 === i.missing.length;
        return n.push(r(s, s ? l("Minify/used-CSS libraries beschikbaar", u) : l("Minificatie wordt veilig overgeslagen als optionele libraries ontbreken", u), "dependencies")),
        t("div", {
            role: "status",
            "aria-live": "polite",
            "aria-atomic": "true"
        }, t("ul", {
            className: "ucp-wiz-readiness"
        }, n));
    }
    function w(e) {
        var o = a.goals || {}, i = Object.keys(o);
        return t(n, null, t("h2", {
            id: "ucp-wiz-title",
            className: "ucp-wiz-title",
            tabIndex: "-1"
        }, l("Start optimalisatie", u)), t("p", {
            className: "ucp-wiz-sub"
        }, l("Kies een startprofiel of sla dit over. Je kunt alles later aanpassen.", u)), t("div", {
            className: "ucp-wiz-cards"
        }, i.map(function(a) {
            return t(m, {
                key: a,
                id: a,
                goal: o[a],
                selected: e.goal === a,
                onSelect: e.setGoal
            });
        })), t(f, {
            status: e.status,
            error: e.statusError
        }), e.error ? t("p", {
            className: "ucp-wiz-error",
            role: "alert"
        }, e.error) : null, t("div", {
            className: "ucp-wiz-actions"
        }, t("button", {
            type: "button",
            className: "ucp-wiz-btn is-ghost",
            onClick: e.onSkip,
            disabled: e.busy
        }, l("Later", u)), t("button", {
            type: "button",
            className: "ucp-wiz-btn is-primary",
            disabled: !e.goal || e.busy,
            onClick: e.onNext
        }, l("Volgende", u))));
    }
    function b(e) {
        e = e || {};
        var a = [];
        function add(condition, label) {
            if (condition) a.push(label);
        }
        add(e.enable_cache, l("Full-page cache", u));
        add(e.enable_preload || "off" !== String(e.preload_mode || "off"), l("Cache opwarmen (preload)", u));
        add(e.enable_lazy_images || e.enable_lazy_iframes || e.enable_lazy_youtube_preview || "off" !== String(e.media_lazyload_mode || "off"), l("Media later laden", u));
        add(e.enable_add_image_dimensions, l("Afbeeldingsdimensies toevoegen (CLS)", u));
        add(e.enable_css_minify, l("CSS verkleinen", u));
        add(e.enable_gzip_precompression || e.enable_brotli_precompression, l("Bestanden vooraf comprimeren", u));
        add(e.browser_cache_headers, l("Browser-cache headers", u));
        add(e.enable_font_display_swap || e.enable_auto_font_preloads || -1 !== [ "swap", "local" ].indexOf(String(e.google_fonts_mode || "")), l("Lettertypen sneller tonen", u));
        add(e.enable_auto_resource_hints || e.enable_prefetch_links || -1 !== [ "core", "enhanced", "prerender" ].indexOf(String(e.speculative_loading_mode || "")), l("Browsernavigatie versnellen", u));
        add(e.enable_heartbeat_control, l("Heartbeat beperken", u));
        add(e.enable_remove_emojis, l("Emoji-script verwijderen", u));
        add(e.enable_woocommerce_rules || e.woocommerce_safety_mode, l("WooCommerce veilig cachen", u));
        add(e.optimize_cart_fragments, l("Cart-fragments optimaliseren", u));
        return a;
    }
    function h(e) {
        var o = a.goals && a.goals[e.goal] ? a.goals[e.goal] : null, i = o ? b(o.settings) : [];
        return t(n, null, t("h2", {
            id: "ucp-wiz-title",
            className: "ucp-wiz-title",
            tabIndex: "-1"
        }, l("Controleer wat wordt toegepast", u)), t("p", {
            className: "ucp-wiz-sub"
        }, l("Er wordt niets toegepast zonder deze bevestiging. Je kunt elk punt later aanpassen.", u)), t("ul", {
            className: "ucp-wiz-applylist"
        }, i.map(function(e, a) {
            return t("li", {
                key: a
            }, t("span", {
                className: "ucp-wiz-check",
                "aria-hidden": "true"
            }, "✓"), e);
        })), t("div", {
            className: "ucp-wiz-callout"
        }, t("strong", null, l("Geavanceerd blijft uit.", u)), " ", l("Used CSS, Critical CSS, JS combineren en Delay JS blijven uitgeschakeld tot jij ze bewust aanzet en test.", u)), e.error ? t("p", {
            className: "ucp-wiz-error",
            role: "alert"
        }, e.error) : null, t("div", {
            className: "ucp-wiz-actions"
        }, t("button", {
            type: "button",
            className: "ucp-wiz-btn is-ghost",
            onClick: e.onBack,
            disabled: e.busy
        }, l("Terug", u)), t("button", {
            type: "button",
            className: "ucp-wiz-btn is-primary",
            onClick: e.onApply,
            disabled: e.busy
        }, e.busy ? l("Bezig met toepassen…", u) : l("Pas toe en ga door", u))));
    }
    function g(e) {
        if (!e || !e.headers) return "";
        for (var a = [ "x-ultracache", "x-ultracache-pro", "x-cache", "cf-cache-status", "x-litespeed-cache" ], t = 0; t < a.length; t++) {
            var n = e.headers.get(a[t]);
            if (n) return a[t] + ": " + n;
        }
        return "";
    }
    function v(e) {
        var a = window.performance && performance.now ? performance.now() : Date.now(), t = "function" == typeof window.AbortController ? new window.AbortController() : null, n = window.setTimeout(function() {
            t && t.abort();
        }, 15e3), o = {
            credentials: "omit",
            cache: "no-store"
        };
        return t && (o.signal = t.signal), fetch(e, o).then(function(e) {
            if (!e.ok) throw new Error(String(l("Homepage-meting gaf HTTP %s.", u)).replace("%s", String(e.status)));
            e.body && "function" == typeof e.body.cancel && e.body.cancel().catch(function() {});
            var t = window.performance && performance.now ? performance.now() : Date.now();
            return {
                ms: Math.round(t - a),
                header: g(e),
                ok: !0
            };
        }).finally(function() {
            window.clearTimeout(n);
        });
    }
    function y(e) {
        var a = e.probe;
        return t(n, null, t("h2", {
            id: "ucp-wiz-title",
            className: "ucp-wiz-title",
            tabIndex: "-1"
        }, l("Deze optimalisaties zijn aangezet", u)), e.busy ? t("div", {
            className: "ucp-wiz-probe is-busy",
            role: "status",
            "aria-live": "polite"
        }, t("div", {
            className: "ucp-wiz-spinner",
            "aria-hidden": "true"
        }), t("p", null, l("Cache opwarmen en homepage meten…", u))) : t(n, null, a && a.warm ? t("div", {
            className: "ucp-wiz-probe"
        }, t("div", {
            className: "ucp-wiz-metric"
        }, t("span", {
            className: "ucp-wiz-metric__value"
        }, a.warm.ms + " ms"), t("span", {
            className: "ucp-wiz-metric__label"
        }, l("warme cache-respons", u))), a.cold ? t("p", {
            className: "ucp-wiz-probe__note"
        }, l("Eerste (ongecachte) hit:", u) + " " + a.cold.ms + " ms · " + l("daarna geserveerd in", u) + " " + a.warm.ms + " ms") : null, a.warm.header ? t("p", {
            className: "ucp-wiz-probe__header"
        }, a.warm.header) : null) : a && a.error ? t("p", {
            className: "ucp-wiz-probe__warning",
            role: "status"
        }, l("De optimalisaties zijn opgeslagen, maar de homepage-meting kon niet worden afgerond. Controleer je homepage handmatig.", u)) : t("p", {
            className: "ucp-wiz-sub"
        }, l("De optimalisaties zijn opgeslagen. Controleer je homepage om de cachewerking te bevestigen.", u)), t("div", {
            className: "ucp-wiz-callout is-soft"
        }, l("Controleer na opslaan je homepage. Bij WooCommerce controleer je ook winkelwagen, checkout, mijn account en order-pay.", u))), t("div", {
            className: "ucp-wiz-actions"
        }, t("button", {
            type: "button",
            className: "ucp-wiz-btn is-primary",
            onClick: e.onFinish,
            disabled: e.busy
        }, e.dismissBusy ? l("Sluiten…", u) : l("Klaar", u))));
    }
    function _() {
        var e, n = o(1), r = n[0], s = n[1], c = o(a.isWoo ? "woo" : "safe"), m = c[0], f = c[1], b = o(null), g = b[0], _ = b[1], z = o(!1), N = z[0], k = z[1], C = o(""), S = C[0], E = C[1], j = o(null), P = j[0], x = j[1], D = o(!0), O = D[0], B = D[1], dismissState = o(!1), dismissBusy = dismissState[0], setDismissBusy = dismissState[1], dismissErrorState = o(""), dismissError = dismissErrorState[0], setDismissError = dismissErrorState[1], statusErrorState = o(""), statusError = statusErrorState[0], setStatusError = statusErrorState[1], dismissBusyRef = UCPUseRef(dismissBusy), actionBusyRef = UCPUseRef(N), dismissWizardRef = UCPUseRef(null);
        function F() {
            p(), B(!1);
        }
        function UCPDismissWizard() {
            if (dismissBusy || N) return;
            setDismissBusy(!0), setDismissError(""), d("onboarding/complete", {
                method: "POST",
                data: {}
            }).then(F).catch(function() {
                setDismissError(l("De assistent kon niet worden gesloten. Controleer je verbinding en probeer opnieuw.", u));
            }).finally(function() {
                setDismissBusy(!1);
            });
        }
        return dismissWizardRef.current = UCPDismissWizard, i(function() {
            dismissBusyRef.current = dismissBusy;
            actionBusyRef.current = N;
        }, [ dismissBusy, N ]), i(function() {
            if (O) {
                var e = document.activeElement, a = document.body.style.overflow, t = document.querySelector('#ucp-onboarding-wizard-root .ucp-wiz-modal[role="dialog"]');
                return document.body.style.overflow = "hidden", document.addEventListener("keydown", o),
                window.setTimeout(function() {
                    var title = document.getElementById("ucp-wiz-title");
                    title ? title.focus() : t && t.focus();
                }, 0), function() {
                    document.removeEventListener("keydown", o), document.body.style.overflow = a, e && e.focus && document.body.contains(e) && e.focus();
                };
            }
            function n() {
                return t ? Array.prototype.slice.call(t.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter(function(e) {
                    return null !== e.offsetParent || e === document.activeElement;
                }) : [];
            }
            function o(e) {
                if ("Escape" === e.key) return e.preventDefault(), void (dismissBusyRef.current || actionBusyRef.current || !dismissWizardRef.current || dismissWizardRef.current());
                if ("Tab" === e.key) {
                    var a = n();
                    if (!a.length) return e.preventDefault(), void (t && t.focus());
                    var o = a[0], i = a[a.length - 1];
                    e.shiftKey && document.activeElement === o ? (e.preventDefault(), i.focus()) : e.shiftKey || document.activeElement !== i || (e.preventDefault(),
                    o.focus());
                }
            }
        }, [ O ]), i(function() {
            if (!O) return;
            var timer = window.setTimeout(function() {
                var title = document.getElementById("ucp-wiz-title");
                title && title.focus();
            }, 0);
            return function() {
                window.clearTimeout(timer);
            };
        }, [ O, r ]), i(function() {
            d("status", {
                method: "GET"
            }).then(function(e) {
                setStatusError(""), _(e && e.status ? e.status : {});
            }).catch(function() {
                _(null), setStatusError(l("Omgeving kon niet worden gecontroleerd. Controleer je verbinding en probeer het later opnieuw.", u));
            });
        }, []), O ? (e = 1 === r ? t(w, {
            goal: m,
            setGoal: f,
            status: g,
            statusError: statusError,
            busy: N || dismissBusy,
            error: dismissError,
            onNext: function() {
                s(2);
            },
            onSkip: UCPDismissWizard
        }) : 2 === r ? t(h, {
            goal: m,
            busy: N || dismissBusy,
            error: S || dismissError,
            onBack: function() {
                s(1);
            },
            onApply: function() {
                k(!0), E(""), d("settings/bulk", {
                    method: "POST",
                    data: a.goals && a.goals[m] ? a.goals[m].settings : {}
                }).then(function() {
                    k(!1), s(3), function() {
                        k(!0);
                        var e = a.homeUrl || (window.location && window.location.origin ? window.location.origin + "/" : "/");
                        d("actions/preload", {
                            method: "POST",
                            data: {}
                        }).catch(function() {}).then(function() {
                            return v(e + (-1 === e.indexOf("?") ? "?" : "&") + "ucpwarm=" + Date.now());
                        }).then(function(a) {
                            return v(e).then(function(t) {
                                return v(e).then(function(e) {
                                    var n = e.ms < t.ms ? e : t;
                                    x({
                                        cold: a,
                                        warm: n
                                    });
                                });
                            });
                        }).catch(function() {
                            x({
                                error: !0
                            });
                        }).finally(function() {
                            k(!1);
                        });
                    }();
                }).catch(function() {
                    k(!1), E(l("Toepassen lukte niet in één keer. Probeer opnieuw of stel het handmatig in via de instellingen.", u));
                });
            }
        }) : t(y, {
            busy: N || dismissBusy,
            dismissBusy: dismissBusy,
            probe: P,
            onFinish: UCPDismissWizard
        }), t("div", {
            className: "ucp-wiz-overlay",
            role: "presentation"
        }, t("div", {
            className: "ucp-wiz-modal",
            role: "dialog",
            "aria-modal": "true",
            "aria-labelledby": "ucp-wiz-title",
            tabIndex: "-1"
        }, t("div", {
            className: "ucp-wiz-head"
        }, t("span", {
            className: "ucp-wiz-brand"
        }, "UltraCache Pro"), t("div", {
            className: "ucp-wiz-steps",
            role: "list",
            "aria-label": l("Voortgang", u)
        }, [ 1, 2, 3 ].map(function(e) {
            return t("span", {
                key: e,
                role: "listitem",
                "aria-current": e === r ? "step" : void 0,
                "aria-label": l("Stap", u) + " " + e + " " + l("van", u) + " 3" + (e === r ? ", " + l("huidige stap", u) : ""),
                className: "ucp-wiz-step" + (e === r ? " is-active" : "") + (e < r ? " is-done" : "")
            }, String(e));
        })), t("button", {
            type: "button",
            className: "ucp-wiz-close",
            onClick: UCPDismissWizard,
            disabled: dismissBusy || N,
            "aria-label": l("Assistent sluiten en later instellen", u)
        }, t("span", {
            "aria-hidden": "true"
        }, "×"))), t("div", {
            className: "ucp-wiz-body"
        }, e)))) : null;
    }
    function z() {
        if (window.location && /(?:[?&])ucp-wizard=1(?:&|$)/.test(window.location.search || "") || !function() {
            try {
                return window.localStorage && "1" === window.localStorage.getItem("ucp_onboarding_dismissed");
            } catch (e) {
                return !1;
            }
        }()) {
            var e = document.createElement("div");
            e.id = "ucp-onboarding-wizard-root";
            var a = document.getElementById("ucp-admin-root"), n = document.querySelector(".ucp-react-admin-wrap") || document.querySelector(".wrap") || document.body;
            a && a.parentNode ? a.parentNode.insertBefore(e, a) : n.insertBefore(e, n.firstChild),
            s ? s(e).render(t(_)) : r(t(_), e);
        }
    }
}(window.wp);
