# Maxscore Audit Evidence

UltraCache Pro version: 11.4.3

## Static/package evidence

| Category | Evidence | Status |
|---|---|---|
| Security | REST permission callbacks, destructive action confirmations, Site Health/runtime test definitions, security checklist | Passed statically |
| Stability & compatibility | PHP lint, classmap smoke checks, version consistency, docs/COMPATIBILITY-MATRIX.md | Passed statically |
| Privacy/i18n/output hygiene | POT file, script translation artifact, privacy notes, output-hygiene checks | Passed statically |
| Release readiness | Production zip excludes dev tooling/tests/scripts/configs; version consistent; zip integrity OK | Passed statically |
| Maintainability | Source build includes PHPCS/PHPUnit/minify check scaffolding; production build remains clean | Passed statically |

## Runtime proof gates

The following categories are intentionally score-gated and require real staging/browser proof:

| Category | Static score | Max score condition |
|---|---:|---|
| WooCommerce / checkout safety | 14/15 | docs/WOOCOMMERCE-CHECKOUT-PROOF.md must contain passed checkout/payment/order-pay/mobile tests |
| Performance / Core Web Vitals | 14/15 | docs/CORE-WEB-VITALS-PROOF.md and docs/BROWSER-REGRESSION-PROOF.md must contain passed LCP/INP/CLS/browser regression results |

## Current honest score

| Category | Score |
|---|---:|
| Security | 25/25 |
| Stability & compatibility | 20/20 |
| WooCommerce / checkout safety | 14/15 |
| Performance / Core Web Vitals | 14/15 |
| Privacy / i18n / output hygiene | 10/10 |
| Release readiness | 10/10 |
| Maintainability | 5/5 |
| Total | 98/100 |

## 100/100 rule

Do not score this package 100/100 unless the runtime proof documents show passed results from a real staging environment. Static code/package analysis alone is not enough to prove WooCommerce payment safety or real Core Web Vitals behavior.
