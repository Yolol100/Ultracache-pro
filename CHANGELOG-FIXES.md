# UltraCache Pro safe repair changelog

## 2026-05-14 follow-up safe release fixes

- Added `Tested up to: 6.9` to the main plugin header to align with the readme metadata and current WordPress 6.9 release line.
- Normalized support-log package line splitting and newline output in `ucp-log-package-redaction-trait.php`; this keeps redaction behavior intact while avoiding brittle multiline string literals.
- Expanded `readme.txt` third-party and privacy disclosures for Cloudflare, custom cloud rendering, Google Fonts localization, preload/sitemap requests, and support log packages.
- Kept runtime cache behavior, public hooks, option names, REST route names and plugin identity unchanged.

## 4.22.6 fixed-v3 design-system consolidation
- Consolidated the classic admin CSS cascade into `assets/admin/css/ucp-admin-design-system.css`.
- Consolidated the React admin CSS import tree into one stable `assets/admin/react/css/ucp-react-admin.css` entrypoint.
- Removed legacy fragmented CSS partial folders from the release package after consolidation.
- Renamed the global cache-toast stylesheet to `assets/admin/css/ucp-cache-toast.css` and updated the enqueue path.
- Preserved existing selectors, cascade order, class names, markup assumptions, handles for JS, and visual values to avoid appearance changes.

## 4.22.6 fixed-v4 strict static hardening
- Replaced remaining multiline newline string literals with explicit `"\\n"` and `/\\r\\n|\\r|\\n/` forms.
- Added direct-access `ABSPATH` guards to included trait/helper files that were missing a local guard.
- Re-ran PHP, JavaScript, CSS, REST-route, asset-reference and packaging checks.
