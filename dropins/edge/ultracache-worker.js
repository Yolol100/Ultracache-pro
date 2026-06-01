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

const BYPASS_COOKIE_MARKERS = [
	'wordpress_logged_in',
	'wp-postpass',
	'comment_author',
	'woocommerce_items_in_cart',
	'woocommerce_cart_hash',
	'wp_woocommerce_session',
	'edd_items_in_cart',
];

addEventListener('fetch', (event) => {
	event.respondWith(handle(event));
});

function requestHasBypassCookie(request) {
	const cookie = request.headers.get('Cookie') || '';
	if (!cookie) {
		return false;
	}
	const lower = cookie.toLowerCase();
	return BYPASS_COOKIE_MARKERS.some((marker) => lower.indexOf(marker) !== -1);
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
