/**
 * UltraCache Pro — first-run onboarding wizard (build-free, wp.element).
 *
 * Step 1: pick a goal (+ live environment readiness from /status).
 * Step 2: apply the render-safe overlay via POST /settings/bulk, with a clear
 *         "advanced stays off" reassurance.
 * Step 3: warm the homepage via /actions/preload, then run a real client-side
 *         timing probe and show the first cached-response time. Marks complete.
 *
 * No JSX / no build step — matches the current admin bundle approach.
 * Translatable strings use wp.i18n.__ with the 'ultracache-pro' text domain so
 * they extract into the .pot and load via wp_set_script_translations.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.element || !wp.apiFetch) {
		return;
	}

	var cfg = window.UCP_WIZARD_CONFIG || {};
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var render = wp.element.render;
	var createRoot = wp.element.createRoot || null;
	var apiFetch = wp.apiFetch;
	var __ = (wp.i18n && wp.i18n.__) ? wp.i18n.__ : function (s) { return s; };
	var DOMAIN = 'ultracache-pro';

	// Route apiFetch through the plugin nonce so POSTs authenticate.
	if (cfg.nonce && apiFetch.createNonceMiddleware) {
		apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
	}

	function rest(path, options) {
		options = options || {};
		options.url = cfg.restUrl + path;
		return apiFetch(options);
	}

	/* ------------------------------------------------------------------ */
	/* Step 1 — goal + environment readiness                               */
	/* ------------------------------------------------------------------ */

	function GoalCard(props) {
		var g = props.goal;
		return el(
			'button',
			{
				type: 'button',
				className: 'ucp-wiz-card' + (props.selected ? ' is-selected' : ''),
				onClick: function () { props.onSelect(props.id); },
				'aria-pressed': props.selected ? 'true' : 'false'
			},
			el('span', { className: 'ucp-wiz-card__label' }, g.label),
			el('span', { className: 'ucp-wiz-card__desc' }, g.description)
		);
	}

	function Readiness(props) {
		var s = props.status;
		if (!s) {
			return el('p', { className: 'ucp-wiz-readiness is-loading' }, __('Omgeving controleren…', DOMAIN));
		}
		var items = [];
		var sys = s.system || {};
		var deps = s.dependencies || {};

		function row(ok, label) {
			return el(
				'li',
				{ className: 'ucp-wiz-readiness__row ' + (ok ? 'is-ok' : 'is-warn') },
				el('span', { className: 'ucp-wiz-readiness__dot' }, ok ? '\u2713' : '!'),
				label
			);
		}

		items.push(row(true, 'PHP ' + (sys.phpVersion || '') + ' \u00b7 WP ' + (sys.wpVersion || '')));
		items.push(row(
			sys.wpCache !== false,
			sys.wpCache === false
				? __('WP_CACHE staat uit — UltraCache zet dit veilig aan bij activatie van cache', DOMAIN)
				: __('Full-page cache kan worden geserveerd (WP_CACHE actief)', DOMAIN)
		));
		var depOk = !deps.missing || deps.missing.length === 0;
		items.push(row(
			depOk,
			depOk
				? __('Minify/used-CSS libraries beschikbaar', DOMAIN)
				: __('Native fallbacks actief voor minify (prima, libraries optioneel)', DOMAIN)
		));

		return el('ul', { className: 'ucp-wiz-readiness' }, items);
	}

	function StepGoal(props) {
		var goals = cfg.goals || {};
		var ids = Object.keys(goals);
		return el(
			Fragment,
			null,
			el('h2', { className: 'ucp-wiz-title' }, __('Start optimalisatie', DOMAIN)),
			el('p', { className: 'ucp-wiz-sub' }, __('Kies een startprofiel of sla dit over. Je kunt alles later aanpassen.', DOMAIN)),
			el(
				'div',
				{ className: 'ucp-wiz-cards' },
				ids.map(function (id) {
					return el(GoalCard, {
						key: id,
						id: id,
						goal: goals[id],
						selected: props.goal === id,
						onSelect: props.setGoal
					});
				})
			),
			el(Readiness, { status: props.status }),
			el(
				'div',
				{ className: 'ucp-wiz-actions' },
				el('button', { type: 'button', className: 'ucp-wiz-btn is-ghost', onClick: props.onSkip }, __('Later', DOMAIN)),
				el(
					'button',
					{
						type: 'button',
						className: 'ucp-wiz-btn is-primary',
						disabled: !props.goal,
						onClick: props.onNext
					},
					__('Volgende', DOMAIN)
				)
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Step 2 — apply render-safe overlay                                  */
	/* ------------------------------------------------------------------ */

	function FRIENDLY() {
		return {
			enable_cache: __('Full-page cache', DOMAIN),
			enable_preload: __('Cache opwarmen (preload)', DOMAIN),
			enable_lazy_images: __('Lazy-load afbeeldingen', DOMAIN),
			enable_lazy_iframes: __('Lazy-load iframes', DOMAIN),
			enable_add_image_dimensions: __('Afbeeldingsdimensies toevoegen (CLS)', DOMAIN),
			enable_css_minify: __('CSS verkleinen', DOMAIN),
			enable_gzip_precompression: __('Gzip-precompressie', DOMAIN),
			enable_brotli_precompression: __('Brotli-precompressie', DOMAIN),
			browser_cache_headers: __('Browser-cache headers', DOMAIN),
			enable_font_display_swap: __('font-display: swap', DOMAIN),
			enable_auto_font_preloads: __('Fonts preloaden', DOMAIN),
			enable_auto_resource_hints: __('Resource hints', DOMAIN),
			enable_prefetch_links: __('Links prefetchen', DOMAIN),
			enable_heartbeat_control: __('Heartbeat beperken', DOMAIN),
			enable_remove_emojis: __('Emoji-script verwijderen', DOMAIN),
			enable_woocommerce_rules: __('WooCommerce cache-regels', DOMAIN),
			woocommerce_safety_mode: __('WooCommerce veiligheidsmodus', DOMAIN),
			optimize_cart_fragments: __('Cart-fragments optimaliseren', DOMAIN),
			cache_mobile_separately: __('Mobiel apart cachen', DOMAIN)
		};
	}

	function humanList(settings) {
		var map = FRIENDLY();
		var out = [];
		Object.keys(settings).forEach(function (k) {
			if (settings[k] && map[k]) { out.push(map[k]); }
		});
		return out;
	}

	function StepApply(props) {
		var goal = (cfg.goals && cfg.goals[props.goal]) ? cfg.goals[props.goal] : null;
		var list = goal ? humanList(goal.settings) : [];
		return el(
			Fragment,
			null,
			el('h2', { className: 'ucp-wiz-title' }, __('Controleer wat wordt toegepast', DOMAIN)),
			el('p', { className: 'ucp-wiz-sub' }, __('Er wordt niets toegepast zonder deze bevestiging. Je kunt elk punt later aanpassen.', DOMAIN)),
			el(
				'ul',
				{ className: 'ucp-wiz-applylist' },
				list.map(function (label, i) {
					return el('li', { key: i }, el('span', { className: 'ucp-wiz-check' }, '\u2713'), label);
				})
			),
			el(
				'div',
				{ className: 'ucp-wiz-callout' },
				el('strong', null, __('Geavanceerd blijft uit.', DOMAIN)),
				' ',
				__('Used CSS, Critical CSS, JS combineren en Delay JS blijven uitgeschakeld tot jij ze bewust aanzet en test.', DOMAIN)
			),
			props.error ? el('p', { className: 'ucp-wiz-error' }, props.error) : null,
			el(
				'div',
				{ className: 'ucp-wiz-actions' },
				el('button', { type: 'button', className: 'ucp-wiz-btn is-ghost', onClick: props.onBack, disabled: props.busy }, __('Terug', DOMAIN)),
				el(
					'button',
					{ type: 'button', className: 'ucp-wiz-btn is-primary', onClick: props.onApply, disabled: props.busy },
					props.busy ? __('Bezig met toepassen…', DOMAIN) : __('Pas toe en ga door', DOMAIN)
				)
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Step 3 — warm + real first-result timing probe                      */
	/* ------------------------------------------------------------------ */

	function cacheHeaderOf(res) {
		if (!res || !res.headers) { return ''; }
		var keys = ['x-ultracache', 'x-ultracache-pro', 'x-cache', 'cf-cache-status', 'x-litespeed-cache'];
		for (var i = 0; i < keys.length; i++) {
			var v = res.headers.get(keys[i]);
			if (v) { return keys[i] + ': ' + v; }
		}
		return '';
	}

	// Fetch the public (guest) homepage without admin cookies and time the response.
	function timeFetch(url) {
		var start = (window.performance && performance.now) ? performance.now() : Date.now();
		return fetch(url, { credentials: 'omit', cache: 'no-store' }).then(function (res) {
			return res.text().then(function () {
				var end = (window.performance && performance.now) ? performance.now() : Date.now();
				return { ms: Math.round(end - start), header: cacheHeaderOf(res), ok: res.ok };
			});
		});
	}

	function StepResult(props) {
		var probe = props.probe;
		return el(
			Fragment,
			null,
			el('h2', { className: 'ucp-wiz-title' }, __('Deze optimalisaties zijn aangezet', DOMAIN)),
			props.busy
				? el('div', { className: 'ucp-wiz-probe is-busy' },
					el('div', { className: 'ucp-wiz-spinner' }),
					el('p', null, __('Cache opwarmen en homepage meten…', DOMAIN)))
				: el(Fragment, null,
					probe && probe.warm
						? el('div', { className: 'ucp-wiz-probe' },
							el('div', { className: 'ucp-wiz-metric' },
								el('span', { className: 'ucp-wiz-metric__value' }, probe.warm.ms + ' ms'),
								el('span', { className: 'ucp-wiz-metric__label' }, __('warme cache-respons', DOMAIN)) ),
							probe.cold
								? el('p', { className: 'ucp-wiz-probe__note' },
									__('Eerste (ongecachte) hit:', DOMAIN) + ' ' + probe.cold.ms + ' ms \u00b7 ' +
									__('daarna geserveerd in', DOMAIN) + ' ' + probe.warm.ms + ' ms')
								: null,
							probe.warm.header
								? el('p', { className: 'ucp-wiz-probe__header' }, probe.warm.header)
								: null
						)
						: el('p', { className: 'ucp-wiz-sub' }, __('Cache opgewarmd. Je site serveert nu gecachte pagina\u2019s aan bezoekers.', DOMAIN)),
					el('div', { className: 'ucp-wiz-callout is-soft' },
						__('Controleer na opslaan je homepage. Bij WooCommerce controleer je ook winkelwagen, checkout, mijn account en order-pay.', DOMAIN)
					)
				),
			el(
				'div',
				{ className: 'ucp-wiz-actions' },
				el('button', { type: 'button', className: 'ucp-wiz-btn is-primary', onClick: props.onFinish, disabled: props.busy }, __('Klaar', DOMAIN))
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Shell                                                               */
	/* ------------------------------------------------------------------ */

	function Wizard() {
		var stepState = useState(1); var step = stepState[0]; var setStep = stepState[1];
		var goalState = useState(cfg.isWoo ? 'woo' : 'safe'); var goal = goalState[0]; var setGoal = goalState[1];
		var statusState = useState(null); var status = statusState[0]; var setStatus = statusState[1];
		var busyState = useState(false); var busy = busyState[0]; var setBusy = busyState[1];
		var errState = useState(''); var error = errState[0]; var setError = errState[1];
		var probeState = useState(null); var probe = probeState[0]; var setProbe = probeState[1];
		var openState = useState(true); var open = openState[0]; var setOpen = openState[1];

		// Load environment readiness once.
		useEffect(function () {
			rest('status', { method: 'GET' }).then(function (r) {
				setStatus(r && r.status ? r.status : {});
			}).catch(function () { setStatus({}); });
		}, []);

		function close() { setOpen(false); }

		function skip() {
			rest('onboarding/complete', { method: 'POST', data: {} }).finally(close);
		}

		function applyGoal() {
			setBusy(true); setError('');
			var settings = (cfg.goals && cfg.goals[goal]) ? cfg.goals[goal].settings : {};
			rest('settings/bulk', { method: 'POST', data: settings })
				.then(function () { setBusy(false); setStep(3); runProbe(); })
				.catch(function () {
					setBusy(false);
					setError(__('Toepassen lukte niet in één keer. Probeer opnieuw of stel het handmatig in via de instellingen.', DOMAIN));
				});
		}

		function runProbe() {
			setBusy(true);
			var home = cfg.homeUrl;
			// 1) Warm the homepage server-side.
			rest('actions/preload', { method: 'POST', data: {} }).catch(function () {})
				.then(function () {
					// 2) Cold-ish measurement (cache-buster), then a warm one.
					return timeFetch(home + (home.indexOf('?') === -1 ? '?' : '&') + 'ucpwarm=' + Date.now());
				})
				.then(function (cold) {
					return timeFetch(home).then(function (warm1) {
						return timeFetch(home).then(function (warm2) {
							var warm = (warm2.ms < warm1.ms) ? warm2 : warm1;
							setProbe({ cold: cold, warm: warm });
						});
					});
				})
				.catch(function () { setProbe(null); })
				.finally(function () { setBusy(false); });
		}

		function finish() {
			rest('onboarding/complete', { method: 'POST', data: {} }).finally(close);
		}

		if (!open) { return null; }

		var body;
		if (step === 1) {
			body = el(StepGoal, {
				goal: goal, setGoal: setGoal, status: status,
				onNext: function () { setStep(2); }, onSkip: skip
			});
		} else if (step === 2) {
			body = el(StepApply, {
				goal: goal, busy: busy, error: error,
				onBack: function () { setStep(1); }, onApply: applyGoal
			});
		} else {
			body = el(StepResult, { busy: busy, probe: probe, onFinish: finish });
		}

		return el(
			'div',
			{ className: 'ucp-wiz-overlay', role: 'region', 'aria-label': 'UltraCache setup' },
			el(
				'div',
				{ className: 'ucp-wiz-modal' },
				el(
					'div',
					{ className: 'ucp-wiz-head' },
					el('span', { className: 'ucp-wiz-brand' }, 'UltraCache Pro'),
					el(
						'div',
						{ className: 'ucp-wiz-steps' },
						[1, 2, 3].map(function (n) {
							return el('span', {
								key: n,
								className: 'ucp-wiz-step' + (n === step ? ' is-active' : '') + (n < step ? ' is-done' : '')
							});
						})
					)
				),
				el('div', { className: 'ucp-wiz-body' }, body)
			)
		);
	}

	function mount() {
		var node = document.createElement('div');
		node.id = 'ucp-onboarding-wizard-root';
		var adminRoot = document.getElementById('ucp-admin-root');
		var wrap = document.querySelector('.ucp-react-admin-wrap') || document.querySelector('.wrap') || document.body;
		if (adminRoot && adminRoot.parentNode) {
			adminRoot.parentNode.insertBefore(node, adminRoot);
		} else {
			wrap.insertBefore(node, wrap.firstChild);
		}
		if (createRoot) {
			createRoot(node).render(el(Wizard));
		} else {
			render(el(Wizard), node);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', mount);
	} else {
		mount();
	}
})(window.wp);
