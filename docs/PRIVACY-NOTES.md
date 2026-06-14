# UltraCache Pro Privacy Notes

UltraCache Pro is a local performance and caching plugin. The plugin may store cache metadata, logs, runtime test snapshots, queue items, generated CSS artifacts, page URLs and compatibility status locally in the WordPress database or `wp-content/cache/ultracache-pro/`.

## Data handling expectations

- Do not log secrets, API tokens, payment data, raw order data or unnecessary personal data.
- Runtime debug headers should be temporary and disabled in production.
- Cached URLs can contain personal data if a site places personal data in query strings; sites should avoid personal data in URLs.
- WooCommerce cart, checkout, order-pay and account pages must be excluded from public page cache.
- External render or PageSpeed integrations should only be configured with trusted endpoints and tokens.

## Privacy policy suggestion

This site uses UltraCache Pro to improve performance. The plugin may store temporary cache files, generated optimization artifacts and technical logs locally on the server. These records are used for performance, debugging and cache invalidation. The site should not place personal data in URLs because URLs may appear in cache metadata or logs.

## Export/erase

If site-specific logs or runtime snapshots are used to diagnose an identifiable user's request, review the built-in privacy exporter/eraser flow and clear diagnostic logs after support work is complete.
