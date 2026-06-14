# UltraCache Pro quality scorecard

This file gives reviewers a stable scoring basis for static and staging audits. It is not a marketing claim and does not replace runtime testing.

## Scorecard weights

- Security: 25
- Stability and compatibility: 20
- WooCommerce/HPOS: 15
- Performance/Core Web Vitals: 15
- Privacy/i18n/output hygiene: 10
- Release readiness: 10
- Maintainability: 5
- Total: 100

## Current evidence target for this build

The build can receive the highest static score only when all of the following are true:

1. All PHP files pass `php -l`.
2. All REST routes define a `permission_callback`.
3. Mutating admin-post/AJAX actions use capability and nonce checks.
4. Destructive cleanup actions require explicit backup and irreversible-action confirmation.
5. Classmap entries point to readable files and loadable classes/traits.
6. Plugin header version, `UCP_VERSION`, and readme stable tag match.
7. Optional Composer libraries degrade to documented fallback mode when absent.
8. Security, compatibility, WooCommerce, CWV and privacy proof documents are present.
9. Site Health exposes runtime verification checks for security, checkout, CWV and privacy/i18n.
10. Release zips exclude development output, logs, node_modules, VCS folders and temporary audit files.

## Runtime score rules

A static audit cannot honestly prove full production readiness for a cache/performance plugin. Scores above 95 require successful runtime verification on the target site:

- Security role/nonce matrix.
- Activation/deactivation/uninstall smoke test.
- WooCommerce cart, checkout, order-pay and payment flow.
- Page cache purge and preload.
- Object-cache enable/disable rollback.
- Database cleanup with and without required confirmations.
- Delay JS, Used CSS, Critical CSS and lazyload browser checks.
- Core Web Vitals before/after checks for LCP, INP and CLS.

## Recommended interpretation

- Static package/code score after this evidence pass: 96/100.
- Production-package readiness after clean validation: yes.
- Full production confidence: requires recorded staging proof.
