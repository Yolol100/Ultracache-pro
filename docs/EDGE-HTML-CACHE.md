# Edge HTML cache & per-page Script Manager (11.0.36)

Two opt-in features. Both ship **default-off**: nothing changes on existing installs until you enable them. Both still fall under the standard UltraCache release gates — test on staging, with WooCommerce flows, before production.

## Edge HTML cache

Enable under **Cloudflare / Edge → Edge HTML cache**. When on, UltraCache emits shared-cache directives on public, anonymous, cacheable GET pages:

- `Cache-Control: public, max-age=0, s-maxage=<ttl>` — browsers revalidate, shared/edge caches keep the document.
- `CDN-Cache-Control` and `Cloudflare-CDN-Cache-Control: max-age=<ttl>, stale-while-revalidate=<stale>, stale-if-error=<stale>`.
- `Cache-Tag: <site>,<site>-home,<site>-<post-tags…>` for surgical purge (Enterprise zones / the bundled Worker).

It is **fail-closed**: logged-in, cart, checkout, account, password-protected, non-GET, sensitive-cookie, and builder/preview requests receive `private, no-store`, so the edge can never cache a personalised page.

### How the HTML actually gets cached at the edge

Sending the headers is necessary but not sufficient — the edge must be told to cache HTML. Two supported paths:

1. **Cloudflare Cache Rule (no Worker):** create a Cache Rule that sets *Eligible for cache* + *Respect origin TTL* for your HTML. Cloudflare then honours `Cloudflare-CDN-Cache-Control`.
2. **Bundled Worker (any plan):** deploy `dropins/edge/ultracache-worker.js` to a Worker and add a route (`example.com/*`). It caches 200 `text/html` GET responses per the emitted TTL, bypasses on logged-in/cart/session/comment cookies, and serves stale on origin errors. No configuration needed.

### Invalidation

UltraCache's existing Cloudflare purge already issues URL purges on content changes, which clears both the standard edge cache and the Worker `caches.default` entry for that URL. Tag-based purge (`Cache-Tag`) is additionally available via `UCP_Edge::cloudflare_purge_tags()` on Enterprise zones.

### Settings

| Key | Default | Range |
|---|---|---|
| `enable_edge_html_cache` | 0 | toggle |
| `edge_html_cache_ttl` | 600 | 60–86400 s |
| `edge_html_cache_stale` | 86400 | 0–604800 s (0 disables stale) |
| `edge_html_cache_tags` | 1 | toggle |

Filters: `ucp_edge_html_cacheable` (bool), `ucp_edge_html_cache_tags` (array).

## Per-page Script Manager

Enable under **Asset Manager → Script Manager (per pagina)**. Adds a native Gutenberg document panel ("UltraCache — Scripts") listing the scripts and styles actually loaded on the page, grouped by their source plugin/theme. Toggling a handle off disables it **for that page only**.

- Selection is stored as post meta (`_ucp_sm_disabled`) and saved on normal post save.
- Enforcement dequeues + deregisters the chosen handles late on the front end (`wp_enqueue_scripts`, priority `PHP_INT_MAX`), only on the singular page.
- The handle inventory is captured when an **administrator** views the page on the front end, then shown in the panel. Visit a page once as admin to populate its list.
- Read-only inventory endpoint: `GET /wp-json/ultracache-pro/v1/script-manager/<id>` (capability: `edit_post`).

The panel is built on `@wordpress/components` (ToggleControl, PanelBody) so it matches the surrounding editor UI.
