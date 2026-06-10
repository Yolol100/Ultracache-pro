# Edge HTML cache (11.0.36)

This opt-in feature ships **default-off**: nothing changes on existing installs until you enable it. It still falls under the standard UltraCache release gates — test on staging, with WooCommerce flows, before production.

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
