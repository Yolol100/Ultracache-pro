=== UltraCache Pro ===
Contributors: ultracache-pro
Tags: cache, performance, core web vitals, critical css, used css
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 11.0.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern caching and frontend optimization suite for WordPress.

== Description ==

UltraCache Pro provides:
- Static page caching
- Scheduled preload
- CSS minify and optional experimental JavaScript minify/combine
- Optional staging-first Delay JavaScript engine
- Basic local Used CSS generation per URL (staging-first)
- Critical CSS generation
- Lazy loading for images and iframes
- Browser cache header helpers
- WooCommerce smart cache rules
- Database cleanup tools
- Cloud connector hooks
- Admin bar purge controls

== Premium safety notes ==

Used CSS, Critical CSS, Delay JS and JavaScript minify are advanced features. Enable them first on staging, use the safelist for dynamic selectors, and visually check key templates before production.
Stale cache is disabled by default and should only be enabled when showing temporarily older cached content is acceptable.
UltraCache preserves existing advanced-cache.php drop-ins and warns about known cache or optimization overlap.

== Filesystem and configuration changes ==

To serve full-page cache before WordPress fully loads, UltraCache Pro installs its own `wp-content/advanced-cache.php` drop-in and, when page caching is enabled and `wp-config.php` is writable, sets the `WP_CACHE` constant to `true`. Optional object caching installs a `wp-content/object-cache.php` drop-in (APCu or Redis). UltraCache only overwrites these files when they are its own (verified by signature) and removes them again on deactivation/uninstall. If `wp-config.php` is not writable, add `define( 'WP_CACHE', true );` manually. These are standard mechanisms for full-page cache plugins; review them on staging first.

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open UltraCache in wp-admin en kies een tab.
4. Sla op en leeg daarna eenmalig de cache.
5. Test CSS en script-opties eerst op staging.

== Frequently Asked Questions ==

= Should I enable every optimization option immediately? =
No. Start with the safe preset, then enable advanced CSS and JavaScript options on staging before production.

= Does UltraCache Pro support WooCommerce? =
UltraCache Pro includes WooCommerce-aware cache bypasses for cart, checkout, account and order-related flows. Always test the full transaction flow after changing cache or script settings.

= Does the plugin send data to third-party services? =
Core caching and local optimization features run inside WordPress. Cloud/CDN or external optimization integrations only apply when configured by an administrator and should be disclosed in the site privacy policy.

= What should I test after activation? =
Test cache purge, preload, forms, checkout, logged-in pages, builder previews, cookie banners, analytics and key templates on staging.

== Privacy ==

UltraCache Pro may store cache metadata, diagnostic records, Core Web Vitals samples, logs and support-report data inside the WordPress installation. It adds privacy policy text and includes exporter and eraser integrations for matching email addresses found in stored logs or diagnostics. If Core Web Vitals monitoring is enabled, the plugin stores aggregated local performance metrics such as LCP, INP, CLS, FCP and TTFB; these are sampled, rate-limited, and stored without retaining visitor IP addresses. Administrators should review retention settings and disclose configured third-party cloud/CDN integrations in their privacy policy where applicable.

== Screenshots ==

1. Dashboard overview and safe preset cards.
2. Cache controls and purge tools.
3. Optimization settings for CSS, JavaScript, media and fonts.
4. Diagnostics and Core Web Vitals overview.

== Changelog ==

= 11.0.14 =
* Removed the unused pre-React admin field-rendering layer (admin UI field helpers, field-logic schema/state and admin metrics helper) that the React admin no longer calls.
* Consolidated the three identical admin REST permission callbacks into a single shared check (capability + nonce for mutations).
* Documented advanced-cache.php / object-cache.php drop-in installation and the optional WP_CACHE constant write.
* Removed a duplicate Privacy section from this readme.
* No runtime cache, optimization, WooCommerce, REST, preload or settings behavior was changed.

= 11.0.10 =
* Hardened APCu object-cache increment/decrement paths with atomic APCu operations.
* Added validated job-table helpers for queue SQL and guarded empty table states.
* Reformatted remaining compact callbacks and lazy-render selector assembly for stricter WPCS readability.

= 11.0.8 =
* Hardened CWV/RUM burst limits so per-IP and site-wide caps are global per minute instead of per metric.
* Added locked counter updates for all CWV/RUM minute, visitor and daily rate-limit counters to reduce concurrent bypass risk.
* Added strict scheme, host and port checks for same-origin CWV/RUM headers and LCP hint URLs.
* Added length bounds before decoding LCP element JSON metadata.
* Switched APCu object-cache drop-in installation to WordPress filesystem writes.
* Removed UltraCache-owned object-cache.php during clean uninstall.
* Removed duplicate REST action route registrations from the quality suite layer.
* Added HTTP response-size limits for remote CSS/font/cloud/Cloudflare requests.
* Reformatted compact admin/font/router code paths for stricter WPCS readability.

= 11.0.3 =
* Fixed cache toast CSS keyframes in both readable and production assets so admin cache notifications animate and dismiss consistently.
* Re-ran static package validation after the UI/release fixes.

= 11.0.2 =
* Fixed production minified React asset to preserve translated string spacing.
* Corrected malformed legacy admin CSS selectors in the fallback navigation layer.
* Synced release assets after UI polish validation.

= 11.0.1 =
* Native WordPress/Gutenberg admin polish: flatter cards, WordPress-style buttons, calmer status badges and a permanent dashboard header.
* Improved dashboard hierarchy with visible cache, WooCommerce, queue and JavaScript test status.
* Replaced ARIA-tab semantics with simpler WordPress-style navigation to reduce accessibility risk.
* Added resettable card layout controls and screen-reader announcements for layout changes.
* Synced release assets and prepared a cleaner production package without tests/dev notes.

= 11.0.0 =
* Performance suite 11.0: parser-first CSS, improved HTML minify, precompressed cache variants, database audit, APCu drop-in template and safer JS combine.

= 10.12.2 =
* Regression audit cleanup: removed unused legacy UX cleanup CSS asset.
* Scoped React admin navigation CSS to the React admin wrapper only.
* Removed React-only selectors from the classic admin design-system stylesheet.
* Kept page override editor styling in its dedicated scoped stylesheet.
* Regenerated the translation template (`languages/ultracache-pro.pot`) to cover all PHP and JavaScript admin strings.
* Added minified asset variants for the React admin bundle, design-system CSS and core/tab admin scripts; the plugin now serves `.min` files automatically when `SCRIPT_DEBUG` is off.
* Added weak/list ETag handling and 304 responses for cached HTML in the advanced-cache drop-in.
* No runtime cache, CSS optimization, JS optimization, lazyload, preload, WooCommerce, REST, fragment or settings logic was removed.

= 10.12.1 =
* Restored visibility of important compatibility settings that disappeared from the classic and React UI after the Developer reorganization.
* Kept `cache_logged_in`, `enable_object_cache_support` and `enable_woocommerce_rules` available under Developer > Compatibility.
* Added a separate, scoped CSS asset for the page exclusions meta box so no inline styling is needed and the post editor layout stays intact.
* Preserved the central design tokens and avoided global CSS hacks, `!important` overrides and broad admin selectors.

= 10.12 =
* Moved Developer cache settings out of the regular advanced rules and into a dedicated Developer section.
* Tightened the builder-safe layer for Elementor, Bricks, Oxygen, Breakdance, Divi, Beaver Builder, WPBakery, Flatsome UX Builder and SiteOrigin editor/preview flows.
* Frontend optimizations now skip builder previews and transactional WooCommerce flows through the same central safety layer.
* React admin and classic admin share the same design tokens; global CSS hiding and hard override rules have been cleaned up.
* Added settings intro cards for consistent per-section explanations.

= 10.11 =
* Added automatic settings snapshots before settings changes, keeping the latest 5 snapshots.
* Added manual settings snapshot creation and a restore action in Tools.
* Added custom presets saved from the current configuration.
* Added per-page UltraCache overrides via a post/page meta box.
* Added a lightweight WordPress dashboard widget with the active optimization status.
* Added REST endpoints for settings snapshots and custom preset saving for the React admin.
* Kept import/export and existing legacy settings compatible.

= 4.22.6.9 =
* Added automatic replacement cleanup for older duplicate UltraCache Pro plugin copies during activation when WordPress file permissions allow deletion.
* Added repository governance files, GitHub Actions quality gates, Plugin Check readiness documentation and privacy/support disclosure notes.

= 4.22.6.8 =
* Restored the dashboard quick actions as separate explanatory cards.
* Placed each action button underneath its explanation text.
* Kept the recent technical lists removed from the user-facing dashboard.

= 4.22.6.5 =
* Removed recent technical diagnostic lists from the Websitecontrole screen.
* Restored the extended action buttons as cards with each button underneath the explanatory text.
* Kept the stable plugin folder so WordPress can replace the existing plugin on upload.

= 4.22.6.2 =
* Simplified the Diagnostics screen for client-friendly use by removing technical lists and advanced controls.
* Improved diagnostics card layout so action buttons sit neatly below the explanatory text.
* Includes earlier accessibility, i18n, stricter SSL verification and packaging fixes.

= 4.22.6 =
* Added optimization intelligence layer for CSS status, stale refresh, JS delay guards, WooCommerce purge coverage, server detection and REST diagnostics.

= 4.22.5 =
* Improve: added safer WooCommerce/payment/form/builder guards before Delay JS, lazy loading and HTML rewrites run.
* Improve: Delay JS now leaves non-JavaScript data/template scripts untouched.
* Fix: Core Web Vitals rolling averages remain accurate after the sample cap is reached.
* Fix: log package download errors now return the intended HTTP status code.

= 4.22.4 =
* Add: PageSpeed Auto preset for Elementor/WooCommerce sites with cache, preload, CSS minify, local fonts, lazyload and safe checkout/account bypasses; image generation, Used CSS and Delay JS remain manual staging-first options.
* Improve: fresh installs and upgrades apply the PageSpeed Auto profile automatically when autopilot is enabled.
* Improve: Asset CleanUp detection now supports both common plugin slugs and no longer disables UltraCache Used CSS/Delay JS when Asset CleanUp is used only for selective unloads.


= 4.21.76 =
* Added staging safety hardening for WooCommerce cache bypasses, builder preview bypasses, REST settings import confirmation, and advanced-cache.php ownership protection.
* Added release hardening for packaged installs, explicit import/database cleanup confirmations, CWV daily sample caps, and admin audit logging for high-impact actions.
* Hardened Core Web Vitals collection with same-origin beacon token, local URL validation and retained rate limiting.
* Made Google Fonts localization non-blocking on first uncached frontend renders by scheduling cache refreshes instead of fetching during page output.
* Hardened preload and Cloudflare remote requests with stricter redirect and unsafe URL handling.
* Limited and sanitized Preload Link headers preload headers, including safer font preload handling.

= 4.21.75 =
* Hardened the advanced-cache drop-in for early WordPress bootstrap by removing direct dependency on WordPress helper functions.
* Changed duplicate plugin cleanup from automatic deletion to manual-review candidate recording.
* Aligned release metadata with plugin version and conservative tested-up-to value.
* Switched first-run defaults to a safer preset with staging-first optimizations left disabled until explicitly enabled.

= 4.21.68 =
* Improved LCP image handling for hero/excluded images and added low fetch priority hints for lazy images.
* Hardened Delay JS defaults and exclusions for builders, forms, cookies, payment gateways and WooCommerce.
* Added YouTube iframe dimensions to reduce CLS and prevented frontend Google Font downloads during page render.
* Added cache BYPASS/MISS debug headers with reasons for easier PageSpeed troubleshooting.
* Fixed Used CSS/Critical CSS cleanup paths.
* Added upgrade cleanup for previous UltraCache-generated cache artifacts and own drop-in refresh.
* Added duplicate UltraCache Pro plugin cleanup during activation when permissions allow.

= 4.21.50 =
* Ultra-minimal Tools UI met rustigere kaart-hiërarchie.
* Secundaire acties worden subtieler als tekstlinks weergegeven.
* Risico-kaart visueel rustiger gemaakt zonder destructive confirmation te veranderen.
* Drag-and-drop layout en 1x1/2x2/3x3/4x4-keuze blijven actief.


= 4.21.45 =
* Adjusted CSS and JavaScript compatibility rules to match WP Rocket-style behavior.
* CSS minify can stay enabled with CSS delivery; only CSS combine is auto-disabled for Used CSS or async CSS delivery.
* JavaScript minify is experimental and should be enabled only after staging tests; JS combine/native script-strategy conflicts are auto-disabled.
= 4.21.28 =
* Cleanup release: removed leftover guest-mode option keys and kept the logged-in optimization bypass as the single maintained safe approach.
* Deduplicated support-report fields and consolidated WP Rocket-style default overrides into one source of truth.
* Added LiteSpeed-inspired safe presets: Veilig, Gebalanceerd, Snel, Agressief - staging, Webshop veilig, Builder veilig and Edge eerst.
* Added URI optimization exclusions and broader default JS/CSS safety exclusions for WooCommerce, builders, forms, maps, analytics, anti-bot and payment scripts.
* Added optional font-display swap and remove-query-strings settings for safer fast presets.
* Added WordPress Site Health support for the X-UltraCache page-cache header.
* Added a masked support-report export for faster troubleshooting.
* Made purge hooks filterable for post, full-cache and WooCommerce events.
* Fixed cache TTL header handling when writing fresh cache files.
* Added compatibility JSON lists for cache, asset, and delay JavaScript exclusions.
* Improved lazyload behavior so the first likely LCP image is not lazy-loaded and receives fetch priority.
* Added extra purge hooks for taxonomy, menu, customizer, permalink, WooCommerce, and builder cache changes.

= 4.19.0 =
* Auto delay JS exclusions now scan active plugins and the current theme when Delay JS is enabled.
* Added broader built-in exclusions for builders, forms, commerce, consent, and common themes.
* Delay JS exclusions are synced into the saved list while remaining filterable at runtime.

* Cleaned up remaining admin controller screens so they match the simplified UI and hidden safe defaults.
* Improved fallback tools/import UI consistency, including support for importing .txt settings files in fallback admin flows.

= 4.9.0 =
* Added smart cache tags for related-page invalidation with optional persistent object-cache acceleration.
* Improved Cloudflare purging to support batched URL purges from the background jobs queue.
* Kept the admin UI simple with only a few new controls.

= 4.1.0 =
* Refined WP Admin UX with Material 3 inspired cards, tabs, chips and clearer information architecture.
* Added richer overview, jobs, diagnostics and rule builder presentation.
* Improved simple vs advanced mode clarity and surfaced existing premium architecture features.

= 3.0.0 =
* Added queue/jobs engine, CSS artifact hooks, Cloudflare/APO and Preload Link headers helpers, script modules compatibility and per-URL diagnostics.

= 2.0.0 =
* Added file optimization, optional delay JS, basic used CSS, critical CSS, DB cleanup and cloud connector architecture.


= 4.16.4 =
* Added privacy exporter and eraser hooks for matching emails in logs and diagnostics.
* Improved admin accessibility with clearer focus states and accessible labels on search and rule controls.
* Tightened admin table and toolbar spacing for more consistent formatting.

= 4.16.3 =
* Admin UI simpeler en consistenter gemaakt.
* Automatische veilige hulp toegevoegd voor compatibiliteit.
* Veilige knoppen toegevoegd voor Heartbeat, preload en server cache setup.


== Accessibility ==
The admin interface uses real form controls, visible focus states and accessible labels for search and rule controls.


== Third-Party Services ==

UltraCache Pro can connect to third-party services only when an administrator explicitly configures or enables those integrations.

= Cloudflare purge =
* Service: Cloudflare API
* Purpose: purge the full zone cache or selected URLs after manual purge actions or plugin-driven purge jobs.
* Data sent: configured Cloudflare Zone ID, API token in the authorization header, and the URL or URL list being purged.
* When: only when Cloudflare-related settings are configured and a purge request is triggered.
* Privacy / terms: document Cloudflare use in your site privacy policy and review Cloudflare privacy and terms documentation for your account/region.

= Cloud sync / render endpoint =
* Service: custom cloud endpoint configured by the site owner
* Purpose: sync, remote render, or related cloud tasks initiated by UltraCache Pro features.
* Data sent: configured endpoint URL, site code, API key, and task-specific URLs or payload fragments needed to complete the requested operation.
* When: only when the cloud integration is configured and the related cloud feature or job is enabled.
* Privacy / terms: document the privacy policy, terms and processor role for the configured service in your site or service documentation.


= Google Fonts localization =
* Service: Google Fonts / Google Fonts CSS endpoints when this feature fetches font CSS or font files for local storage.
* Purpose: download configured font assets so future visitor requests can be served locally from this WordPress installation.
* Data sent: the requested Google Fonts CSS/font URL and standard server-side HTTP request metadata. UltraCache Pro does not intentionally send visitor personal data for this fetch.
* When: only when local Google Fonts handling is enabled and a font cache refresh is needed.
* Privacy / terms: document Google Fonts localization in your site privacy policy if you use this feature.

= URL preload / sitemap collection =
* Service: this WordPress site and its configured sitemap URLs.
* Purpose: warm page cache and collect same-site URLs for preload jobs.
* Data sent: server-side GET requests to local site URLs and sitemap URLs.
* When: only when preload features are enabled or triggered by an administrator.

= Support report / log package =
* Service: local WordPress only unless an administrator manually sends the exported package to a support provider.
* Purpose: troubleshooting cache, queue, conflict and diagnostics issues.
* Data included: redacted settings, plugin status, queue summaries, recent jobs, recent diagnostics and file logs. API keys, tokens, nonces, cookies, sessions, authorization values, email addresses, IP addresses and URL query strings are redacted where detected.
* When: only when an administrator with the required capability downloads the package.

= Premium modules added =
UltraCache Pro includes optional premium modules for WebP/AVIF image variants, Google Fonts localization, link preload/speculation, REST API caching, fragment cache helper API, object-cache detection, and lightweight Core Web Vitals monitoring. Enable these modules gradually and test dynamic pages such as checkout, account, forms and dashboards.
