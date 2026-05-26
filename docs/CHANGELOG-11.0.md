# UltraCache Pro 11.0.0

## Performance-suite upgrade

### CSS delivery
- Added parser-first Used CSS extraction. When `sabberworm/php-css-parser` is bundled through Composer, UltraCache uses the parser path first and falls back to the existing conservative recursive scanner on parser failure.
- Preserved the existing safelist, queue and async fallback flow.
- Added `enable_local_critical_css` default-on local critical CSS slicing from locally generated Used CSS artifacts.

### HTML optimization
- Replaced the narrow `>\s+<` minifier with a conservative HTML minifier that collapses whitespace outside masked sensitive blocks, compresses boolean attributes and optionally removes safe attribute quotes.
- Preserved existing mask/restore logic and HTML test mode.

### JavaScript optimization
- Hardened local JS combine with a dedicated `wp-content/cache/ultracache-pro/js/` cache directory and hashes based on source mtime/filesize.
- Added `js_combine_exclusions` and kept default JS combine/minify off.
- Skips module scripts, nonce scripts and inline scripts.

### LCP, CLS and media
- RUM LCP collection continues to store measured LCP element data in `ucp_lcp`; measured LCP hints are prioritized over heuristics.
- Added iframe/video aspect-ratio reservation for CLS reduction.
- Existing image dimension and responsive srcset enrichment are retained and enabled through safe defaults.
- Added optional IntersectionObserver runtime with 50% rootMargin for legacy lazyload patterns.

### Cache delivery
- Page-cache writes now generate `.br` and `.gz` variants where supported.
- `advanced-cache.php` serves precompressed Brotli/gzip variants before falling back to on-the-fly compression.

### Edge delivery
- Added guarded 103 Early Hints attempt for supported server contexts and retained normal Link preload headers.

### Third-party self-hosting
- Extended self-hosting allowlist to Google Analytics (`gtag.js`, `analytics.js`), Google Tag Manager and Facebook Pixel hosts.
- Remote assets remain validated, same-origin cache output is local, and JavaScript is kept byte-for-byte to reduce tracking/consent breakage.

### Database performance
- Added autoload audit for the top 20 largest autoloaded options.
- Added basic missing-index detection for core high-impact metadata tables.
- Added explicit, admin-confirmed MyISAM → InnoDB conversion action for `wp_options` only.

### Object cache
- Added optional self-hosted APCu object-cache drop-in template. It is never installed automatically and refuses to overwrite an existing third-party object-cache drop-in.

## Free/open-source libraries
- `sabberworm/php-css-parser` — MIT license. Declared in `composer.json`; release builds should bundle it in `/vendor`.
- `matthiasmullie/minify` — MIT license. Declared in `composer.json`; release builds should bundle it in `/vendor`.
- Composer dependencies must be bundled in `/vendor` during release packaging. The plugin keeps safe fallback paths when these libraries are absent.

## Compatibility and safety
- PHP 8.0+ and WordPress 6.3+ retained.
- WooCommerce/cart/checkout and builder preview bypasses retained.
- New higher-risk features remain default off unless considered safe, such as precompression and local critical CSS fallback.
