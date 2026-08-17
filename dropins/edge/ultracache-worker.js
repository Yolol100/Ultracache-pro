/**
 * UltraCache Pro — Cloudflare edge HTML Worker (optional).
 *
 * Caches full HTML documents at the Cloudflare edge using the Workers Cache API,
 * honouring the CDN-Cache-Control / Cloudflare-CDN-Cache-Control headers that
 * UltraCache Pro emits when "Edge HTML cache" is enabled.
 *
 * Safety: requests carrying a logged-in, cart, session or comment cookie bypass
 * the cache entirely, and only 200 text/html GET responses without Set-Cookie
 * are stored. Invalidation uses Cloudflare's normal URL purge (the plugin's
 * existing Cloudflare purge already issues this on content changes).
 *
 * Deploy: paste into a Worker and add a route for your zone, e.g. example.com/*.
 * No configuration required. Remove the route to disable.
 */

// Keep these lists aligned with UCP_Cache_Policy. The Worker performs its cache
// lookup before the request reaches WordPress, so every cookie must be classified:
// known state cookies bypass, known non-personalising cookies are allowed, and all
// unknown or malformed names fail closed.
const BYPASS_COOKIE_PREFIXES = [
	// WordPress authentication, password-protected content and comments.
	'wordpress_logged_in_',
	'wordpress_sec_',
	'wp-postpass_',
	'wp-resetpass_',
	'comment_author_',
	'switch_to_olduser_',
	'wordpress_test_cookie',

	// WooCommerce, Easy Digital Downloads and payment/session state.
	'woocommerce_items_in_cart',
	'woocommerce_cart_hash',
	'wp_woocommerce_session_',
	'woocommerce_recently_viewed',
	'woocommerce_checkout_',
	'woocommerce_pay_',
	'edd_items_in_cart',

	// Multilingual and multicurrency cookies that vary page HTML.
	'pll_language',
	'_icl_current_language',
	'wp-wpml_current_language',
	'wpml_browser_redirect_test',
	'trp_language',
	'wp_lang',
	'wcml_client_currency',
	'woocommerce_multicurrency_forced_currency',
	'aelia_cs_selected_currency',
	'aelia_customer_country',
	'aelia_customer_state',
	'aelia_tax_exempt',

	// Consent plugins can alter visible HTML, scripts and banners per visitor.
	'cookie_notice_',
	'cmplz_',
	'complianz_',
	'cookieyes',
	'cky-',
	'borlabs',
];

const BROWSER_CACHE_CONTROL_HEADER = 'X-UltraCache-Browser-Cache-Control';
const ORIGIN_VARY_HEADER = 'X-UltraCache-Origin-Vary';

const SAFE_COOKIE_PREFIXES = [
	'ct_', 'apbct_', 'ct_sfw', 'cleantalk', 'cookiebot', 'cookie_notice_',
	'cmplz_', 'complianz_', 'cookieyes', 'cky-', 'borlabs', 'joinchat_',
	'wordpress_test_cookie', 'wp-settings-', 'wp-settings-time-',
	'_ga', '_gid', '_gat', '_gcl_', '_fbp', '_fbc', '_hj', '_clck', '_clsk',
	'_pk_id', '_pk_ses', '_uetsid', '_uetvid', '_pin_unauth', '_scid',
	'li_gc', 'li_mc', 'lidc', 'bcookie', 'bscookie', 'tk_ai', 'tk_qs',
	'__stripe_mid', '__stripe_sid', '__cf_bm', 'cf_clearance',
];

addEventListener('fetch', (event) => {
	event.respondWith(handle(event));
});

function requestHasUnsafeCookie(request) {
	const cookieHeader = request.headers.get('Cookie') || '';
	if (!cookieHeader) {
		return false;
	}

	for (const pair of cookieHeader.split(';')) {
		const rawName = pair.split('=', 1)[0].trim();
		if (!rawName || !/^[!#$%&'()*+\-.^_`|~0-9A-Za-z]+$/.test(rawName)) {
			return true;
		}
		const normalizedCookieName = rawName.toLowerCase();
		if (BYPASS_COOKIE_PREFIXES.some((fragment) => normalizedCookieName.indexOf(fragment) !== -1)) {
			return true;
		}
		if (!SAFE_COOKIE_PREFIXES.some((prefix) => rawName.indexOf(prefix) === 0)) {
			return true;
		}
	}
	return false;
}

function requestCacheControlRequiresRevalidation(value) {
	for (const directive of String(value || '').toLowerCase().split(',')) {
		const parts = directive.trim().split('=', 2).map((part) => part.trim());
		const name = parts[0] || '';
		if (['no-cache', 'no-store', 'private'].indexOf(name) !== -1) {
			return true;
		}
		if (['max-age', 's-maxage'].indexOf(name) === -1) {
			continue;
		}
		const rawValue = parts.length > 1 ? parts[1].replace(/^"|"$/g, '').trim() : '';
		if (!/^-?\d+$/.test(rawValue) || Number(rawValue) <= 0) {
			return true;
		}
	}
	return false;
}

function responseCacheControlDisallowsSharedStorage(value) {
	let hasSharedMaxAge = false;
	let sharedMaxAge = null;
	let maxAge = null;
	for (const directive of String(value || '').toLowerCase().split(',')) {
		const parts = directive.trim().split('=', 2).map((part) => part.trim());
		const name = parts[0] || '';
		if (['private', 'no-store', 'no-cache'].indexOf(name) !== -1) {
			return true;
		}
		if (['max-age', 's-maxage'].indexOf(name) === -1) {
			continue;
		}
		const rawValue = parts.length > 1 ? parts[1].replace(/^"|"$/g, '').trim() : '';
		if (!/^-?\d+$/.test(rawValue)) {
			return true;
		}
		const age = Number(rawValue);
		if (name === 's-maxage') {
			hasSharedMaxAge = true;
			sharedMaxAge = sharedMaxAge === null ? age : Math.min(sharedMaxAge, age);
		} else {
			maxAge = maxAge === null ? age : Math.min(maxAge, age);
		}
	}
	if (hasSharedMaxAge) {
		return sharedMaxAge === null || sharedMaxAge <= 0;
	}
	return maxAge !== null && maxAge <= 0;
}

function requestHeaderQuality(parameters) {
	for (const parameter of parameters) {
		if (!/^q\s*=/i.test(parameter)) {
			continue;
		}
		const match = parameter.match(/^q\s*=\s*(0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/i);
		return match ? Number(match[1]) : 0;
	}
	return 1;
}

function requestMediaQuality(header, type, subtype, parameters = {}) {
	header = String(header || '').trim().toLowerCase();
	const normalizedParameters = {};
	for (const [name, value] of Object.entries(parameters || {})) {
		normalizedParameters[String(name).toLowerCase()] = String(value).trim().replace(/^"|"$/g, '').toLowerCase();
	}
	if (!header) {
		return 1;
	}
	let bestSpecificity = -1;
	let bestQuality = 0;
	for (const item of header.split(',')) {
		const segments = item.split(';').map((part) => part.trim());
		const range = String(segments.shift() || '').split('/');
		if (range.length !== 2) {
			continue;
		}
		let specificity = -1;
		if (range[0] === '*' && range[1] === '*') {
			specificity = 0;
		} else if (range[0] === type && range[1] === '*') {
			specificity = 1;
		} else if (range[0] === type && range[1] === subtype) {
			specificity = 2;
		}
		if (specificity < 0) {
			continue;
		}

		let parameterCount = 0;
		let matches = true;
		for (const parameter of segments) {
			if (/^q\s*=/i.test(parameter)) {
				continue;
			}
			const pair = parameter.split('=', 2);
			if (pair.length !== 2) {
				matches = false;
				break;
			}
			const name = String(pair[0] || '').trim().toLowerCase();
			const value = String(pair[1] || '').trim().replace(/^"|"$/g, '').toLowerCase();
			if (!name || !Object.prototype.hasOwnProperty.call(normalizedParameters, name) || normalizedParameters[name] !== value) {
				matches = false;
				break;
			}
			parameterCount++;
		}
		if (!matches) {
			continue;
		}

		specificity = (specificity * 1000) + parameterCount;
		const quality = requestHeaderQuality(segments);
		if (specificity > bestSpecificity) {
			bestSpecificity = specificity;
			bestQuality = quality;
		} else if (specificity === bestSpecificity) {
			bestQuality = Math.max(bestQuality, quality);
		}
	}
	return bestSpecificity >= 0 ? bestQuality : 0;
}

function requestAcceptsHtml(request) {
	return requestMediaQuality(request.headers.get('Accept') || '', 'text', 'html', { charset: 'utf-8' }) > 0;
}

function requestShouldBypass(request) {
	const requestUrl = new URL(request.url);
	return request.method !== 'GET' ||
		request.headers.has('X-HTTP-Method-Override') ||
		requestUrl.searchParams.has('_method') ||
		!requestAcceptsHtml(request) ||
		request.headers.has('Authorization') ||
		request.headers.has('X-WP-Nonce') ||
		request.headers.has('Range') ||
		request.headers.has('If-Range') ||
		requestCacheControlRequiresRevalidation(request.headers.get('Cache-Control') || '') ||
		/no-cache/i.test(request.headers.get('Pragma') || '') ||
		requestHasUnsafeCookie(request);
}

function parseCachePolicy(response) {
	// Prefer Cloudflare-specific directives, then the generic CDN header. Parse
	// exact directives so lookalike names or duplicate restrictive values cannot
	// accidentally make a response cacheable.
	const header =
		response.headers.get('Cloudflare-CDN-Cache-Control') ||
		response.headers.get('CDN-Cache-Control') ||
		'';
	const policy = {
		maxAge: null,
		staleWhileRevalidate: null,
		staleIfError: null,
	};
	const names = {
		'max-age': 'maxAge',
		'stale-while-revalidate': 'staleWhileRevalidate',
		'stale-if-error': 'staleIfError',
	};
	for (const directive of header.split(',')) {
		const parts = directive.trim().split('=', 2);
		const name = String(parts[0] || '').trim().toLowerCase();
		if (name === 'private' || name === 'no-store' || name === 'no-cache') {
			return { maxAge: 0, staleWhileRevalidate: 0, staleIfError: 0 };
		}
		if (!Object.prototype.hasOwnProperty.call(names, name)) {
			continue;
		}
		const rawValue = String(parts[1] || '').trim().replace(/^"|"$/g, '');
		if (!/^\d+$/.test(rawValue)) {
			return { maxAge: 0, staleWhileRevalidate: 0, staleIfError: 0 };
		}
		const value = Number(rawValue);
		const key = names[name];
		policy[key] = policy[key] === null ? value : Math.min(policy[key], value);
	}
	return {
		maxAge: policy.maxAge !== null && policy.maxAge > 0 ? policy.maxAge : 0,
		staleWhileRevalidate: policy.staleWhileRevalidate !== null ? policy.staleWhileRevalidate : 0,
		staleIfError: policy.staleIfError !== null ? policy.staleIfError : 0,
	};
}

function parseMaxAge(response) {
	return parseCachePolicy(response).maxAge;
}

function isCacheableHtml(response) {
	if (response.status !== 200) {
		return false;
	}
	if (response.headers.get('Set-Cookie') || response.headers.get('Content-Range') || response.headers.get('WWW-Authenticate')) {
		return false;
	}
	if (/no-cache/i.test(response.headers.get('Pragma') || '')) {
		return false;
	}
	const cacheControl = response.headers.get('Cache-Control') || '';
	if (responseCacheControlDisallowsSharedStorage(cacheControl)) {
		return false;
	}
	const vary = (response.headers.get('Vary') || '').toLowerCase();
	if (vary) {
		const supported = ['accept', 'accept-encoding'];
		const unsupported = vary.split(',').map((name) => name.trim()).filter(Boolean)
			.some((name) => supported.indexOf(name) === -1);
		if (unsupported) {
			return false;
		}
	}
	const type = (response.headers.get('Content-Type') || '').split(';', 1)[0].trim().toLowerCase();
	if (type !== 'text/html') {
		return false;
	}
	return parseMaxAge(response) > 0;
}

const EDGE_STORED_AT_HEADER = 'X-UltraCache-Edge-Stored-At';
const EDGE_MAX_AGE_HEADER = 'X-UltraCache-Edge-Max-Age';
const EDGE_STALE_REVALIDATE_HEADER = 'X-UltraCache-Edge-Stale-While-Revalidate';
const EDGE_STALE_ERROR_HEADER = 'X-UltraCache-Edge-Stale-If-Error';
const MAX_EDGE_STORAGE_TTL = 31 * 24 * 60 * 60;

function safeSeconds(value) {
	value = Number(value);
	return Number.isFinite(value) && value >= 0 ? Math.floor(value) : 0;
}

function cachedPolicy(response) {
	const storedAt = safeSeconds(response.headers.get(EDGE_STORED_AT_HEADER));
	const maxAge = safeSeconds(response.headers.get(EDGE_MAX_AGE_HEADER));
	return {
		valid: storedAt > 0 && maxAge > 0,
		storedAt,
		maxAge,
		staleWhileRevalidate: safeSeconds(response.headers.get(EDGE_STALE_REVALIDATE_HEADER)),
		staleIfError: safeSeconds(response.headers.get(EDGE_STALE_ERROR_HEADER)),
	};
}

function browserResponse(response, marker, legacy = false) {
	const served = new Response(response.body, response);
	const browserCacheControl = served.headers.get(BROWSER_CACHE_CONTROL_HEADER);
	if (browserCacheControl !== null) {
		if (browserCacheControl) {
			served.headers.set('Cache-Control', browserCacheControl);
		} else {
			served.headers.delete('Cache-Control');
		}
	} else if (legacy) {
		// Fail safely for entries written by an older Worker version: never expose
		// the edge-storage TTL as a browser-cache TTL.
		served.headers.set('Cache-Control', 'public, max-age=0, must-revalidate');
	}

	const originVary = served.headers.get(ORIGIN_VARY_HEADER);
	if (originVary !== null) {
		if (originVary) {
			served.headers.set('Vary', originVary);
		} else {
			served.headers.delete('Vary');
		}
	}

	for (const header of [
		BROWSER_CACHE_CONTROL_HEADER,
		ORIGIN_VARY_HEADER,
		EDGE_STORED_AT_HEADER,
		EDGE_MAX_AGE_HEADER,
		EDGE_STALE_REVALIDATE_HEADER,
		EDGE_STALE_ERROR_HEADER,
	]) {
		served.headers.delete(header);
	}
	served.headers.set('X-UltraCache-Edge', marker);
	return served;
}

function storageResponse(response) {
	const policy = parseCachePolicy(response);
	const staleWindow = Math.max(policy.staleWhileRevalidate, policy.staleIfError);
	const storageTtl = Math.max(1, Math.min(MAX_EDGE_STORAGE_TTL, policy.maxAge + staleWindow));
	const stored = new Response(response.clone().body, response);
	const browserCacheControl = response.headers.get('Cache-Control');
	const originVary = response.headers.get('Vary');
	stored.headers.set(BROWSER_CACHE_CONTROL_HEADER, browserCacheControl === null ? '' : browserCacheControl);
	stored.headers.set(ORIGIN_VARY_HEADER, originVary === null ? '' : originVary);
	// Cloudflare's Cache API keys entries by request URL rather than by the
	// response Vary header. Store one canonical HTML representation and restore
	// the origin Vary header only on the visitor response.
	stored.headers.delete('Vary');
	stored.headers.set(EDGE_STORED_AT_HEADER, String(Math.floor(Date.now() / 1000)));
	stored.headers.set(EDGE_MAX_AGE_HEADER, String(policy.maxAge));
	stored.headers.set(EDGE_STALE_REVALIDATE_HEADER, String(policy.staleWhileRevalidate));
	stored.headers.set(EDGE_STALE_ERROR_HEADER, String(policy.staleIfError));
	stored.headers.set('Cache-Control', 'public, max-age=' + storageTtl);
	stored.headers.set('X-UltraCache-Edge', 'MISS');
	return stored;
}

function isStaleIfErrorResponse(response) {
	return response && [500, 502, 503, 504].indexOf(Number(response.status)) !== -1;
}

async function refreshCachedResponse(cache, cacheKey, request) {
	let response;
	try {
		response = await fetch(request);
	} catch (err) {
		return false;
	}
	if (!isCacheableHtml(response)) {
		// Retain the stale object when origin revalidation fails with a transient
		// server error so a configured stale-if-error window remains usable.
		if (isStaleIfErrorResponse(response)) {
			return false;
		}
		if (typeof cache.delete === 'function') {
			await cache.delete(cacheKey);
		}
		return false;
	}
	await cache.put(cacheKey, storageResponse(response));
	return true;
}

async function handle(event) {
	const request = event.request;

	if (requestShouldBypass(request)) {
		return fetch(request);
	}

	const cache = caches.default;
	const cacheKey = new Request(request.url, { method: 'GET' });

	// Match with the original GET request so the Cache API can evaluate
	// conditional headers such as If-None-Match while storage remains URL-keyed.
	let cached = await cache.match(request);
	if (cached) {
		const policy = cachedPolicy(cached);
		if (!policy.valid) {
			return browserResponse(cached, 'HIT', true);
		}

		const age = Math.max(0, Math.floor(Date.now() / 1000) - policy.storedAt);
		if (age <= policy.maxAge) {
			return browserResponse(cached, 'HIT');
		}

		if (policy.staleWhileRevalidate > 0 && age <= policy.maxAge + policy.staleWhileRevalidate) {
			event.waitUntil(refreshCachedResponse(cache, cacheKey, request));
			return browserResponse(cached, 'STALE');
		}

		if (policy.staleIfError > 0 && age <= policy.maxAge + policy.staleIfError) {
			let response;
			try {
				response = await fetch(request);
			} catch (err) {
				return browserResponse(cached, 'STALE-IF-ERROR');
			}
			if (isStaleIfErrorResponse(response)) {
				return browserResponse(cached, 'STALE-IF-ERROR');
			}
			if (isCacheableHtml(response)) {
				event.waitUntil(cache.put(cacheKey, storageResponse(response)));
				return browserResponse(response, 'MISS');
			}
			if (typeof cache.delete === 'function') {
				event.waitUntil(cache.delete(cacheKey));
			}
			const passthrough = new Response(response.body, response);
			passthrough.headers.set('X-UltraCache-Edge', 'BYPASS');
			return passthrough;
		}

		if (typeof cache.delete === 'function') {
			event.waitUntil(cache.delete(cacheKey));
		}
	}

	let response;
	try {
		response = await fetch(request);
	} catch (err) {
		return new Response('Service temporarily unavailable', { status: 503 });
	}

	if (isCacheableHtml(response)) {
		event.waitUntil(cache.put(cacheKey, storageResponse(response)));
		return browserResponse(response, 'MISS');
	}

	const passthrough = new Response(response.body, response);
	passthrough.headers.set('X-UltraCache-Edge', 'BYPASS');
	return passthrough;
}
