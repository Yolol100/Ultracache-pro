# UltraCache Pro Admin UX Map

This build consolidates the admin UI around seven primary sections so the plugin feels like one premium product instead of separate modules.

## Primary navigation

1. **Overview**
   - Status cards, quick actions, safe baseline, provider/CDN status and WooCommerce safety signals.

2. **Cache**
   - Page cache, browser cache, TTL, exclusions, WooCommerce bypasses and purge behaviour.
   - Advanced page-cache options stay inside disclosures.

3. **Optimization**
   - CSS, JavaScript, HTML and media optimization.
   - Risky toggles use impact labels such as `Staging-first`, `May affect layout` and `May affect checkout`.

4. **Preload & Crawler**
   - Safe preload, sitemap discovery, crawler queue, throttling and retry controls.
   - Cache vary remains advanced and disabled by default.

5. **CDN & Edge**
   - Provider setup for Cloudflare, Bunny and custom webhooks.
   - Credential tests, purge tests, health status and read-only edge hints live here.

6. **Advanced**
   - Developer-only features: REST API cache, fragment cache API, compatibility rules, remote rule updates and expert serve modes.
   - All are disabled by default and require staging validation.

7. **Tools & Logs**
   - Audit logs, purge/preload logs, runtime tests, support bundle, settings import/export and retention settings.

## Safety rules

- REST cache is allowlist-first and disabled by default.
- Fragment cache is disabled by default and bypasses logged-in, cart, checkout and account contexts.
- Cache vary is disabled by default and ignores auth/session/cart cookies.
- Expert mode is reversible and never writes Nginx config automatically.
- Remote compatibility updates are opt-in, HTTPS-only and JSON-validated.
- Provider secrets are masked in UI, logs and exports.

## Manual QA checklist

- Open every primary tab with `WP_DEBUG` enabled.
- Save settings on each tab and confirm no option keys are lost.
- Run provider credential and purge tests with invalid credentials.
- Confirm empty states render for no logs, no REST rules, no fragments and no compatibility matches.
- Confirm WooCommerce cart, checkout, account, order-pay and payment flows remain bypassed.
- Confirm support bundle does not contain raw API tokens, cookies or auth headers.
- Confirm Apache expert mode preview/apply/rollback only runs with nonce and capability checks.
