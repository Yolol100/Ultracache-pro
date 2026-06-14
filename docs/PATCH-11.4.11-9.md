# Patch 11.4.11-9 — WP Rocket-style defaults and visible Defer JS

## Changed

- `defer_all_js` is visible again in the normal customer Optimization > JavaScript view.
- The customer label is now `JavaScript uitgesteld laden` instead of `Defer JS`.
- Added a short JavaScript QA warning for menus, forms, sliders, cookie banner and checkout.
- Checked the existing WP Rocket-style safe baseline defaults:
  - page cache is enabled by default for new installs;
  - preload and preload queue are enabled by default for new installs;
  - homepage preload is enabled by default and activation already triggers purge/preload;
  - WooCommerce safety and sensitive URL/cookie exclusions are enabled by default.

## Not changed

- Existing saved settings are not overwritten.
- No option keys, public hooks, REST routes, cache filenames or drop-in names were renamed.
- JavaScript uitgesteld laden, Delay JS, database cleanup, object cache, CDN and image optimization are not forced on automatically.
- CSS/JS combine, technical exclusions and CDN/provider fields remain support-only.

## Verification

- JavaScript syntax check on the admin bundle.
- Full PHP lint on all plugin PHP files.
- ZIP integrity test after packaging.

## Runtime checks still required

- Test menus, forms, sliders, cookie banner and WooCommerce checkout after enabling JavaScript uitgesteld laden or Delay JS.
- Confirm first activation/preload behavior on a clean staging install.
