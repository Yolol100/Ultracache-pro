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

// Keep this list aligned with the PHP/drop-in excluded + vary cookie fragments.
// The Worker cannot vary its Cache API key by arbitrary WooCommerce/language cookies,
// so edge HTML caching must fail closed and bypass when these cookies are present.
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

addEventListener('fetch', (event) => {
	event.respondWith(handle(event));
});

function requestHasBypassCookie(request) {
	const cookieHeader = request.headers.get('Cookie') || '';
	if (!cookieHeader) {
		return false;
	}

	const cookieNames = cookieHeader
		.split(';')
		.map((part) => part.split('=')[0].trim().toLowerCase())
		.filter(Boolean);

	return cookieNames.some((cookieName) =>
		BYPASS_COOKIE_PREFIXES.some((prefix) => cookieName.indexOf(prefix) === 0)
	);
}

function parseMaxAge(response) {
	// Prefer Cloudflare-specific directive, then the generic CDN directive.
	const header =
		response.headers.get('Cloudflare-CDN-Cache-Control') ||
		response.headers.get('CDN-Cache-Control') ||
		'';
	if (/no-store/i.test(header)) {
		return 0;
	}
	const match = header.match(/max-age=(\d+)/i);
	return match ? parseInt(match[1], 10) : 0;
}

function isCacheableHtml(response) {
	if (response.status !== 200) {
		return false;
	}
	if (response.headers.get('Set-Cookie')) {
		return false;
	}
	const type = (response.headers.get('Content-Type') || '').toLowerCase();
	if (type.indexOf('text/html') === -1) {
		return false;
	}
	return parseMaxAge(response) > 0;
}

async function handle(event) {
	const request = event.request;

	if (request.method !== 'GET' || requestHasBypassCookie(request)) {
		return fetch(request);
	}

	const cache = caches.default;
	const cacheKey = new Request(request.url, { method: 'GET' });

	let cached = await cache.match(cacheKey);
	if (cached) {
		const hit = new Response(cached.body, cached);
		hit.headers.set('X-UltraCache-Edge', 'HIT');
		return hit;
	}

	let response;
	try {
		response = await fetch(request);
	} catch (err) {
		// stale-if-error: nothing cached and origin unreachable.
		return new Response('Service temporarily unavailable', { status: 503 });
	}

	if (isCacheableHtml(response)) {
		const ttl = parseMaxAge(response);
		const toStore = new Response(response.clone().body, response);
		toStore.headers.set('Cache-Control', 'public, max-age=' + ttl);
		toStore.headers.set('X-UltraCache-Edge', 'MISS');
		event.waitUntil(cache.put(cacheKey, toStore.clone()));
		const served = new Response(toStore.body, toStore);
		return served;
	}

	const passthrough = new Response(response.body, response);
	passthrough.headers.set('X-UltraCache-Edge', 'BYPASS');
	return passthrough;
}
