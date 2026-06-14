# UltraCache Pro 11.4.11-1 patch notes

This patch keeps public APIs, plugin headers, option keys, REST namespaces, drop-in names and file layout unchanged.

## Fixed in code

- CWV REST success responses now explicitly send `Cache-Control: no-store, no-cache, must-revalidate, private`, `X-Robots-Tag: noindex, nofollow` and `X-UCP-CWV: accepted`.
- CWV REST rate-limit responses now use one shared helper that adds `Retry-After`, `Cache-Control: no-store, no-cache, must-revalidate, private` and `X-Robots-Tag: noindex, nofollow`.
- ESI rate-limit responses now also include explicit `Cache-Control: no-store, no-cache, must-revalidate, private` and `X-Robots-Tag: noindex, nofollow` headers.

## Intentionally not changed

- `Tested up to: 7.0` is preserved because WordPress.org's current version-check endpoint lists 7.0 as current on 2026-06-12. This remains a release claim that must be proven per target stack with the runtime checks below.
- Composer vendor directories are not fabricated. The plugin already exposes fallback status through Site Health and REST status; bundling vendor code must be done from the real Composer lock/build process, not guessed.
- Drop-in writes remain disabled by default through existing settings. Existing takeover/ownership checks are preserved.

## Required runtime verification before production

1. Activate on staging with `WP_DEBUG` and check PHP error logs.
2. Run Site Health and confirm optional dependency status is clear.
3. Test CWV endpoint with valid beacon, missing token, wrong origin/referer and rate-limit cases.
4. Test ESI endpoint as logged-out visitor, logged-in admin, logged-in subscriber, missing nonce and invalid nonce.
5. Test WooCommerce cart, mini-cart, checkout, order-pay, my-account, coupon, payment and order-status purge.
6. Enable/disable page cache, object cache and drop-in writes only on staging; verify rollback removes or restores the intended drop-ins.
7. Run before/after Lighthouse/PageSpeed and browser console checks on key templates.
