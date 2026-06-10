# UltraCache Pro Runtime Release Manifest

This manifest defines what belongs in the production ZIP.

## Keep in runtime ZIP

- `ultracache-pro.php`
- `uninstall.php`
- `advanced-cache.php` when used as bundled drop-in/stub
- `includes/`
- `assets/`
- `compat/`
- `dropins/`
- `languages/`
- `readme.txt`
- `LICENSE.txt`
- `RELEASE-NOTES.txt` when distributed to clients
- `docs/` files that explain runtime safety, ESI, release checks, or operator usage

## Exclude from runtime ZIP

- `composer.json`, `composer.lock`, `vendor/bin/`, test-only vendor packages
- `package.json`, lock files, bundler configs, build scripts
- source maps (`*.map`)
- logs, cache, tmp files, screenshots, archives and local OS files
- CI files and development-only scripts unless shipping a source package

## Required release checks

- PHP lint on all plugin PHP files
- Node syntax check on bundled JavaScript files
- Classmap integrity check
- REST route permission callback check
- Admin-post capability/nonce check
- Package hygiene check
- ZIP integrity check
- WordPress activation/deactivation/uninstall smoke test on staging
