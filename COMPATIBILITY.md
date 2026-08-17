# Compatibility policy

## Declared platform support

- WordPress: 6.3 through 7.0
- PHP: 8.0 or newer
- Single site and multisite
- Apache and Nginx, with server-specific rule validation
- WooCommerce Classic and Blocks-safe cache exclusions; each store must still validate its complete payment and extension stack

## One owner per optimization layer

Use one active owner for each of these responsibilities:

- Full-page cache
- Browser/server cache rules
- Redis/APCu object cache drop-in
- CSS removal or Critical CSS
- JavaScript combination, defer or delay
- Image CDN/transforms
- Edge/CDN HTML cache

UltraCache detects common overlaps and normalizes incompatible internal settings, but it cannot safely arbitrate every host-level or third-party optimization outside WordPress.

## Required staging checks

Before production, validate public and logged-in pages, forms, consent, search, 404s, builder previews, mobile layouts, cache purge/preload, cron/queues, and conditional requests. WooCommerce stores must also test variations, add-to-cart, mini-cart, cart, Checkout Blocks or Classic Checkout, order-pay, order-received, My Account and two isolated sessions.

A compatibility declaration means the relevant code paths are supported. It is not a guarantee that every third-party theme, extension, payment provider, CDN or hosting combination is conflict-free.
