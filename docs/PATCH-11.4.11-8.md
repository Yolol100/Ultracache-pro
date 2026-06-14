# Patch 11.4.11-8 — customer UI final cleanup

## Goal

Apply the final customer-facing UI plan after comparing UltraCache Pro with broader cache/performance plugin patterns. The normal admin UI should stay premium, calm and safe for clients, while the technical power remains available through `ucp_support=1`.

## Changed

### Cache
- Combined visitor/admin and WooCommerce-safe controls into one customer-facing **Cacheveiligheid** card.
- Moved **Publieke cache voor shoppers** to support mode only.
- Moved extra purge URL controls on the Cache page to support mode; the customer-facing version remains on the Regels page.
- Kept a warning visible if shopper cache is enabled while the normal customer view hides the toggle.

### Optimization
- Made **Veilige interacties** visible in the normal customer JavaScript card.
- Kept **Defer JS** support-only.
- Left CSS/JS combine, exclusions, CDN delivery, provider keys, webhooks and compatibility update settings support-only.

### Media
- Reworked customer groups into **Afbeeldingen**, **Lazyload & LCP**, and **Fonts**.
- Moved LQIP, HTML lazy render, parser controls, unicode-range/font-range tuning, local external assets and Image CDN details to support mode.
- Hid **Afbeeldingskwaliteit** in normal mode when image optimization is off.

### Preload
- Renamed the customer navigation group to **Navigatie versnellen**.
- Removed the prerender choice from normal customer mode.
- Added a support-only prerender control for staging-first usage.

### Object Cache
- Kept status and Redis controls central.
- Collapsed the wp-config Redis snippet under **Technische configuratie tonen**.
- APCu is now shown only in support mode or as a fallback when Redis is not available/connected and APCu exists.

## Not changed

- No option keys renamed.
- No REST slugs changed.
- No hooks, cache keys, drop-in filenames or backend cache behavior changed.
- Regels, Database and Tools were intentionally left in their v7 customer-facing balance.

## Validation

- JavaScript syntax checked for both admin bundles.
- PHP lint passed for all PHP files.
- Zip integrity tested after packaging.

## Runtime verification required

- Check normal UI without `ucp_support=1` on Cache, Optimalisatie, Media, Preload and Object Cache.
- Check full support UI with `ucp_support=1`.
- Test WooCommerce cart, checkout, account, order-pay and mini-cart when cache/shopper options are active.
- Test frontend layout, sliders, forms, cookie banner and checkout after CSS/JS/media changes.
