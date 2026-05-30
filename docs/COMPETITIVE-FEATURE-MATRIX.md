# UltraCache Pro competitive execution matrix

This release keeps the product positioning concrete without claiming unverified benchmark wins. Use this as the internal acceptance matrix before comparing the plugin with WP Rocket, LiteSpeed Cache, FlyingPress, Perfmatters, W3 Total Cache or Autoptimize.

## Current strengths

- Page cache with preload, purge and stale-cache controls.
- WooCommerce/account/order safety exclusions.
- Asset Manager snapshot, protected handles, test mode and URL-scoped rule syntax.
- CSS/LCP profiles, Critical/Used CSS workflows and safe rollback metadata.
- Local CWV/RUM monitoring, browser scan storage and PageSpeed Readiness checks.
- Cloudflare/edge-cache headers and purge helpers when configured.
- WP-CLI commands for status, purge, preload, settings import/export, conflicts, diagnostics and runtime tests.
- Support reports with redacted settings, runtime-test snapshot and quality summary.

## Non-negotiable release gates

1. Clean activation/deactivation/uninstall on staging.
2. Runtime tests saved after activation and after preset changes.
3. WooCommerce cart, checkout, order-pay, account and payment flow tested when WooCommerce is active.
4. Asset unload rules tested with Asset Test Mode before being made public.
5. Delay JS, CSS combine, JS combine, Used CSS and HTML minify tested on key templates before production.
6. Cloudflare/edge purge tested only when the site actually uses Cloudflare or a compatible edge cache.
7. Support report reviewed for redaction before sharing externally.

## Roadmap status after 11.0.32

| Improvement | Status | Notes |
|---|---|---|
| Safe/Balanced/Aggressive presets | Implemented | Existing preset/onboarding flow retained. |
| Autopilot/quality dashboard | Improved | Added REST quality summary and support report summary. |
| Asset Manager UX | Implemented foundation | Snapshot, grouping, protected assets, test mode and recommendations exist. |
| Benchmark report | Partial | Browser scan and CWV data exist; external lab comparison remains manual. |
| Cloudflare integration | Partial | API config, headers and purge helpers exist; advanced APO/rules audit remains runtime-only. |
| WP-CLI | Improved | Added diagnostics and runtime-tests commands. |
| Image pipeline | Partial | WebP/AVIF generation hooks exist; bulk conversion UX still roadmap. |
| WooCommerce test mode | Improved | Runtime test and Site Health visibility added. |
| Conflict Guard | Implemented foundation | Feature-overlap detection exists; keep expanding compatibility rules with real support cases. |
| Public feature matrix/docs | Added | This document plus readme/release notes. |

## Benchmark claim policy

Do not claim UltraCache Pro is faster than a named competitor unless the statement is backed by reproducible before/after tests on the same site, same host, same theme, same content, same cache state and same measurement window. Prefer: "designed for safe Core Web Vitals optimization on agency, builder and WooCommerce sites."
