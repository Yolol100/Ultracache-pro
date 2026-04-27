# UltraCache Pro power features phase 2

This build adds safe foundations for advanced caching features. All risky modules are opt-in and default to disabled.

## REST API cache
- Allowlist-first, GET-only REST cache.
- Auth headers, logged-in users, nonce requests, sensitive cookies and WooCommerce-sensitive endpoints are bypassed.
- Use `rest_cache_rules` JSON in the Advanced tab.

## Fragment cache API
- Public helper: `ucp_fragment_cache( $key, $callback, $ttl = 300, $args = array() )`.
- Logged-in users and WooCommerce cart/checkout/account contexts are bypassed by default.
- Supports purge all, purge by key, purge by tag.

## Crawler and vary engine
- Crawler supports sitemap/seed/delta queues with hard concurrency and retry limits.
- Cache vary is opt-in and ignores auth/session/WooCommerce cookies for public variants.

## Serve modes
- Safe mode remains default.
- Apache rules are preview/apply/rollback only with explicit admin action.
- Nginx is snippet-only; the plugin never writes Nginx config.

## Compatibility rules
- Bundled rules work offline.
- Remote rule updates are opt-in, HTTPS-only and JSON-validated. No remote PHP is executed.

## Provider wizard
- Cloudflare, Bunny and custom webhook providers support credential and purge tests.
- Secrets are masked in UI, logs and exports.
