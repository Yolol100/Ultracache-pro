# UltraCache Pro Core Web Vitals Validation

UltraCache can improve speed, but aggressive optimization must be proven per template.

## Metrics

- LCP: Largest Contentful Paint
- INP: Interaction to Next Paint
- CLS: Cumulative Layout Shift

## Required templates

- Homepage
- Main service/content page
- Product page if WooCommerce is active
- Cart
- Checkout
- Elementor/builder landing page
- Form page
- Mobile and desktop views

## Feature-specific checks

| Feature | Risk | Required proof |
|---|---|---|
| Delay JS | Broken forms, menus, consent, checkout, payment scripts | Console clean and interaction works |
| Used CSS | Missing dynamic/builder/WooCommerce styles | Visual comparison before/after |
| Critical CSS | CLS or flash of unstyled content | CLS check and screenshot comparison |
| Lazyload | Lazyloaded LCP image | Confirm hero/product primary image is not delayed |
| REST cache | Stale dynamic data | Check private/session endpoints stay uncached |
| Combine CSS/JS | Dependency order breakage | Browser smoke test and console clean |

## Pass criteria

- LCP, INP and CLS do not regress on key templates.
- No visible layout break after optimization.
- No checkout or form interaction break.
- No payment script delayed past the point it is needed.
