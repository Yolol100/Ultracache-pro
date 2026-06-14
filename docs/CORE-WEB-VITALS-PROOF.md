# Core Web Vitals Runtime Proof

Status: runtime proof required for Performance / Core Web Vitals 15/15.

## Score gate

Performance / Core Web Vitals may be scored 15/15 only when measured browser/runtime proof shows no regressions for LCP, INP/TBT, CLS, checkout UI, forms, mobile menu, sliders, cookie banner, and payment UI.

## Required measurement tools

Use at least one lab tool and one browser/runtime inspection method:

- Lighthouse
- Chrome DevTools Performance panel
- PageSpeed Insights when the staging URL is publicly accessible
- WebPageTest or GTmetrix when available
- Browser console and visual regression checks

## Required pages and scenarios

| Page | Device | Scenario | LCP | INP/TBT | CLS | TTFB | Console errors | Visual result | Status |
|---|---|---|---:|---:|---:|---:|---|---|---|
| Homepage | Mobile | Baseline page cache |  |  |  |  | Not executed | Not executed | Pending |
| Homepage | Desktop | Baseline page cache |  |  |  |  | Not executed | Not executed | Pending |
| Product page | Mobile | Optimizations enabled |  |  |  |  | Not executed | Not executed | Pending |
| Product page | Desktop | Optimizations enabled |  |  |  |  | Not executed | Not executed | Pending |
| Checkout | Mobile | Delay JS + Used CSS + Critical CSS |  |  |  |  | Not executed | Not executed | Pending |
| Checkout | Desktop | Delay JS + Used CSS + Critical CSS |  |  |  |  | Not executed | Not executed | Pending |
| Elementor page | Mobile | Used CSS + Critical CSS |  |  |  |  | Not executed | Not executed | Pending |
| Elementor page | Desktop | Used CSS + Critical CSS |  |  |  |  | Not executed | Not executed | Pending |
| Form page | Mobile | Delay JS + lazyload |  |  |  |  | Not executed | Not executed | Pending |
| Form page | Desktop | Delay JS + lazyload |  |  |  |  | Not executed | Not executed | Pending |

## Regression checks

| Component | Required result | Actual result | Status |
|---|---|---|---|
| Forms | Submit and validation work | Not executed | Pending |
| Cookie banner | Displays and records consent | Not executed | Pending |
| Sliders | Render and interact correctly | Not executed | Pending |
| WooCommerce checkout | No broken payment UI | Not executed | Pending |
| Mobile menu | Opens/closes correctly | Not executed | Pending |
| Hero/LCP image | Not incorrectly lazyloaded | Not executed | Pending |
| Layout stability | No visible CLS regression | Not executed | Pending |

## Conclusion

Current score gate result: 14/15 remains until runtime browser proof is filled and passed.
