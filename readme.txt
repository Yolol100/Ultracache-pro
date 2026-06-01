=== UltraCache Pro ===
Contributors: ultracache-pro
Tags: cache, performance, core web vitals, critical css, used css
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 11.0.38
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

= 11.0.37 =
* Tweak: LCP-detectie scant het document nog maar één keer — de aparte <img>- en alle-tags-passes in detect_lcp_image_candidate() zijn samengevoegd tot één tag-walk.
* Note: gedrag 1-op-1 behouden (images eerst gescoord, daarna backgrounds; identieke tie-breaking); geen hooks/REST/options/output gewijzigd. De cross-pass lazyload/LCP-samenvoeging is bewust niet aangeraakt.

= 11.0.36 =
* Nieuw (default-off): Edge HTML cache — echte shared-cache directives (s-maxage, CDN-Cache-Control, Cloudflare-CDN-Cache-Control, stale-while-revalidate/stale-if-error) plus Cache-Tag headers, fail-closed voor ingelogde/cart/checkout/account/preview.
* Nieuw: meegeleverde Cloudflare Worker en docs voor volledige edge-HTML-caching op elk Cloudflare-plan; UCP_Edge::cloudflare_purge_tags() voor tag-purge.
* Nieuw (default-off): per-pagina Script Manager — native Gutenberg-documentpaneel om afzonderlijke scripts/stijlen per pagina uit te schakelen, gegroepeerd per plugin/thema, met read-only inventory-endpoint.
* Tweak: native toggles toegevoegd aan de React-admin (Cloudflare/Edge + Asset Manager); editorpaneel op @wordpress/components.
* Note: beide features zijn opt-in; geen gedragswijziging op bestaande installs. Inclusief de classmap-opschoning uit 11.0.35.

= 11.0.35 =
* Tweak: autoloader-classmap opgeschoond — 27 redundante trait-entries verwijderd die nooit vuurden omdat de trait in zijn eigen composing class-bestand staat (van 143 naar 116 entries).
* Tweak: de co-located-trait-conventie expliciet in de loader gedocumenteerd zodat de map niet opnieuw uit sync loopt.
* Note: puur release-hygiene — geen runtime-, hook-, REST-, option- of gedragswijziging; geen migratie nodig.

= 11.0.34 =
* Tweak: React admin volgt nu het WordPress-beheerkleurenschema — primair, focusringen, badges, toasts en navigatie zijn afgeleid van `--wp-admin-theme-color` in plaats van een vaste merkkleur, zodat ze overeenkomen met de aangrenzende native @wordpress/components.
* Tweak: semantische statuskleuren afgestemd op het WordPress admin notice-palet (success #00a32a, warning #dba617, error #d63638).
* Tweak: verouderde "premium" decoratielaag platgeslagen naar native stijl (4px radii, geen slagschaduwen, geen gradient-headerbalk).
* Fix: tab-navigatie in de React admin werkte niet door een ontbrekende `selectTab`-handler (ReferenceError bij klikken); tab wisselt nu correct en synchroniseert de URL.
* Tweak: dode code verwijderd uit de React admin (~490 regels JS): niet-gemounte DiagnosticsPage en hulpfuncties, ongebruikte overzichtspanelen, MetricCard/DataList en wees-API-helpers, plus bijbehorende ongebruikte CSS.
* Note: geen PHP-logica, REST-namespace of option keys gewijzigd; geen migratie nodig.

= 11.0.33 =
* Tweak: Delay-JS safe-exclusion lijst uitgebreid met payment SDKs (Braintree, Razorpay, Square, Paddle, Authorize.Net).
* Tweak: Delay-JS safe-exclusion lijst uitgebreid met consent managers (CookieYes/Cookie Law Info, Usercentrics, iubenda).
* Note: pure data/allowlist-wijziging in `compat/delay-js-exclusions.json` (13 entries); strikt conservatief, vertraagt nooit méér scripts.
* Note: geen PHP-logica, hooks, REST-namespace of option keys gewijzigd; geen migratie nodig.

= 11.0.32 =
* Fix: React admin `.min` CSS/JS assets zijn nu daadwerkelijk kleiner dan de debugvarianten.
* Fix: ontbrekende 11.0.30 changelog-entry toegevoegd voor volledige releasehistorie.
* Tweak: versie- en POT-metadata gelijkgetrokken naar 11.0.32.
* Note: geen runtime- of businesslogica gewijzigd.

= 11.0.30 =
* Added admin-only diagnostics quality-summary endpoint.
* Extended support report and log package with quality summary and runtime-test snapshots.
* Added WP-CLI diagnostics and runtime-test commands with JSON output support.
* Added Site Health runtime-test visibility and competitive feature matrix documentation.

= 11.0.29 =
* Hardened sensitive admin downloads with nosniff, noindex and no-store response headers.
* Reduced public CWV beacon token window to daily buckets while keeping previous-bucket support for cached HTML.
* Improved private cache directory protection rules and package hygiene.

= 11.0.26 =
* Bugfix: hardens log/support-package redaction for user identifiers and sensitive WooCommerce URL path segments.
* Bugfix: redacts user identifiers in JSONL log exports and support packages.
* Bugfix: protects order-pay, order-received, checkout, cart, account, payment, token, nonce and session path markers in logged URLs.


= 11.0.25 =
* Bugfix privacy hardening: plain-text helper logs and REST log rows now redact URL queries, emails, IPs and common secret/payment/order tokens before admin display or storage.
* No cache, WooCommerce, asset, CSS, LCP or admin UI behavior changes.

= 11.0.24 =
* Runtime acceptance privacy hardening: diagnostics and log-package redaction now redact WooCommerce/order, customer, payment, cart, checkout and session-related context keys.
* No feature behavior changes; staging runtime testing remains required for browser, WooCommerce and payment flows.

= 11.0.23 =
* Polished the React admin toward a WordPress-native Gutenberg/@wordpress/components style without changing cache behavior.
* Added a compact native admin header with accessible tab semantics and clearer top-level status badges.
* Added a DataViews-like PageSpeed Readiness card with expandable details for impact, risk and runtime-test status.
* Improved Asset Manager copy, list semantics, button labels and protected/test-mode action wording.
* Added scoped admin CSS polish for cards, tabs, readiness details, loading states, asset rows and responsive wp-admin layouts.

= 11.0.22 =
* Ultimate polish: CSS profile expiry metadata, renderer status and protected-wins enforcement for external Used CSS payloads.
* Added optional allowed LCP CDN host support while keeping measured page URLs same-origin and text LCP out of auto-preload.
* Asset Manager now fails closed on sensitive WooCommerce/account/payment requests unless an explicit manual override is enabled.
* Preload crawler status reasons now distinguish sensitive URLs, dynamic nonce/query URLs, redirects and unsupported content types.
* Expanded PageSpeed Readiness with advanced-cache, cache-writability, font preload, WooCommerce safety and REST/admin security status.
* Added PageSpeed Auto v12 safelist/default migration without enabling aggressive CSS, JS or asset unload behavior.

= 11.0.21 =
* Polished CSS profile sanitization so external renderer output is bounded, typed and still fails closed on WooCommerce/account/payment-sensitive URLs.
* Raised default LCP auto-preload confidence to 85 and restricted automatic LCP resource use to same-origin resource-like image URLs.
* Added per-URL/device LCP stale rollback helper for bad or outdated measured profiles.
* Cleaned Conflict Guard plugin detection, including Cloudflare and Cloudflare Super Page Cache labels without duplicate/malformed entries.
* Added friendlier Asset Manager rule aliases for unload/protect, URL exceptions, post type, device and logged-in/logged-out scopes.
* Hardened preload status metadata sanitization and added a PageSpeed Auto v11 safelist polish migration.

= 11.0.20 =
* Added conservative per-URL CSS profiles with separate critical, delayed, protected and renderer-ready safe-removal buckets.
* Added high-confidence LCP profiles per URL/device, including image, background-image, text and video-poster metadata.
* Added Preload Crawler v2 prioritization for homepage, purge URLs, menu URLs, recent content and sitemap URLs with status logging and throttling.
* Expanded Asset Manager rule scopes and protected-asset warnings for safer URL, post type, device and logged-out unload workflows.
* Expanded conflict diagnostics for WP Rocket, LiteSpeed Cache, FlyingPress, Autoptimize, Perfmatters, Asset CleanUp, SG Optimizer and Cloudflare/edge cache overlap.
* Added PageSpeed Readiness diagnostics covering cache, LCP, CSS, JS delay, resource hints, Asset Test Mode, preload and conflicts.

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
