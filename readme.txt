=== UltraCache Pro ===
Contributors: ultracache-pro
Tags: cache, performance, core web vitals, critical css, used css
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 11.7.19
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular WordPress caching and frontend optimization suite with conservative production defaults.

== Description ==

UltraCache Pro combines:

* Static full-page caching with bounded stale-cache support.
* Scheduled and queued cache preload.
* Cache insights, bypass reasons, purge history and route-level policies.
* CSS minification, local Used CSS and Critical CSS workflows.
* Optional JavaScript minification, combination, defer and Delay JS controls.
* Image optimization, WebP/AVIF, responsive images, lazy loading and Image CDN transforms.
* Font loading, resource hints, browser cache and edge/CDN helpers.
* WooCommerce-aware cache, session, cart, checkout and account safeguards.
* Redis or APCu object-cache drop-ins.
* Database cleanup, diagnostics, support reports and Core Web Vitals sampling.
* Compatibility profiles for common builders, forms, consent tools and dynamic stacks.

Start with the safe preset. Enable advanced CSS and JavaScript processing on staging, then visually check representative templates before production.

== Safety and compatibility ==

UltraCache keeps Stale Cache, Used CSS, Critical CSS, Delay JS and experimental JavaScript processing behind explicit controls. It normalizes conflicting settings, protects transactional WooCommerce routes and avoids overwriting cache drop-ins owned by another plugin.

Do not run two full-page cache engines or two tools that rewrite the same CSS/JavaScript output without a documented compatibility configuration. After changing optimization settings, test public pages, logged-in pages, forms, builder previews and the complete WooCommerce transaction flow.

== Filesystem and configuration changes ==

Full-page caching uses `wp-content/advanced-cache.php` and requires `WP_CACHE` to be enabled. When `wp-config.php` is writable, UltraCache can manage the constant automatically. Otherwise add:

`define( 'WP_CACHE', true );`

Optional object caching installs `wp-content/object-cache.php` for Redis or APCu. UltraCache only replaces or removes a drop-in when ownership is verified. Cache artifacts are stored under `wp-content/cache/ultracache-pro/`.

Production sites should invoke WP-Cron from a real system scheduler so preload, compression and maintenance jobs do not depend only on visitor traffic.

== Remote services ==

Core caching and local optimization run inside WordPress. External requests occur only when the corresponding administrator-controlled integration is enabled. Examples include CDN/provider APIs, optional Google Fonts localization and optional YouTube preview thumbnails.

Remote compatibility overlays are disabled by default. Signed overlays require an Ed25519 public key through `UCP_COMPAT_PUBLIC_KEY` or the `ucp_compat_overlay_public_key` filter. Unsigned overlays are rejected unless explicitly permitted with `UCP_ALLOW_UNSIGNED_COMPAT_OVERLAY`.

Redis TLS supports `WP_REDIS_SCHEME`, `WP_REDIS_READ_TIMEOUT`, `WP_REDIS_RETRY_INTERVAL` and, on compatible PhpRedis versions, `WP_REDIS_SSL_CONTEXT`.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate UltraCache Pro.
3. Open the UltraCache admin screen and start with the safe preset.
4. Save settings and purge the cache once.
5. Verify cache HIT/BYPASS behavior and important site flows on staging.

== Frequently Asked Questions ==

= Should I enable every optimization immediately? =

No. Start with page caching and safe media/browser settings. Introduce Used CSS, Critical CSS, JavaScript combination or Delay JS separately and test each change.

= Does UltraCache Pro support WooCommerce? =

The plugin includes WooCommerce-aware exclusions and session safeguards for cart, checkout, account, order and Store API traffic. Compatibility still depends on the complete store stack, payment providers and theme, so the full transaction flow must be tested after cache or script changes.

= What should I test after activation? =

Check cache purge and preload, public and logged-in pages, forms, consent tools, builder previews, search, 404 responses, mobile layouts and key templates. For WooCommerce, test product variation selection, add-to-cart, mini-cart, cart, checkout, order-pay, order-received and My Account with at least two separate sessions.

= Does the plugin send personal data externally? =

Not by default. Cloud/CDN integrations and optional external-content features can make requests after an administrator enables them. Review enabled integrations and disclose them in the site privacy policy.

== Privacy ==

UltraCache Pro can store cache metadata, diagnostic records, local performance samples, logs and support-report data inside WordPress. Core Web Vitals sampling is rate-limited and does not retain visitor IP addresses. Privacy policy helper text and exporter/eraser integrations are included for matching stored records. Administrators remain responsible for retention settings and disclosure of enabled third-party integrations.

== Screenshots ==

1. Dashboard and safe preset cards.
2. Cache controls, status and purge tools.
3. CSS, JavaScript, media and font optimization settings.
4. Diagnostics, queues and Core Web Vitals overview.

== Changelog ==

= 11.7.19 =
* Removed the programmatically triggered JSON import file input from the normal keyboard tab order while preserving a translated accessible name and the existing import workflow.
* Localized the existing React admin empty-card fallback instead of hardcoding user-visible text in CSS.
* Localized the remaining Cloudflare, CDN and CSS artifact status messages used by existing status and log flows.
* Preserved all existing settings, routes, hooks, storage formats and product scope; no setting, route or feature was added.

= 11.7.18 =
* Preserved allowlisted origin security and representation headers across both normal PHP and early drop-in page-cache HITs while continuing to reject stateful, freshness and malformed header metadata.
* Bypassed REST response caching for WooCommerce Store API Cart-Token and Nonce requests and rotated the internal REST cache schema.
* Isolated opt-in logged-in page cache entries by WordPress session as well as user identity, failing closed when a durable session token is unavailable.
* Corrected Accept-Encoding negotiation so identity preference, explicit refusal, wildcard semantics and unavailable compressed siblings follow HTTP semantics in both cache-serving paths.
* Bounded writer-lock files with a fixed lock pool, cleaned only stale legacy lock artifacts, and bounded/indexed retention cleanup work per maintenance run.
* Removed obsolete imagedestroy() calls for PHP 8.5 compatibility while preserving existing LQIP behavior.
* Preserved existing settings, routes, hooks and product scope; no setting, route or feature was added.

= 11.7.17 =
* Preserved gain-mapped HDR JPEGs by skipping WebP/AVIF cross-codec generation and image-CDN transformation for detected UltraHDR, ISO 21496-1 and Apple gain-map metadata.
* Stopped serving stale WebP/AVIF siblings for detected gain-mapped HDR JPEGs and removed only variants already tracked as UltraCache-owned during re-optimization.
* Preserved ordinary JPEG optimization, existing image settings, routes and public feature scope; no setting or product feature was added.

= 11.7.16 =
* Invalidated existing cached output when CSS/LCP/edge/admin-bar/preload output-affecting settings change.
* Stopped persisting origin freshness/validator/cache-control metadata in the anonymous REST response cache.
* Routed native defer through WordPress 6.3+ dependency-aware script strategy metadata while preserving exclusions and safe markers.

= 11.7.15 =
* Kept queued mobile preload retryable on transport and transient HTTP failures, and reported durable duplicate preload jobs truthfully.

= 11.7.14 =
* Kept enabled LQIP media-library backfill retryable when a subjob cannot be durably queued and treated an existing equivalent job as idempotent success.

= 11.7.13 =
* Rejected replay of an older validly signed compatibility overlay when a newer verified overlay is already installed.
* Kept Used CSS refresh retryable when its maintenance job cannot be durably queued.
* Reported direct-preload overflow as queued only when the overflow job was actually stored or already existed.
* Recorded lifecycle cache warmup as scheduled only after WordPress accepted the cron event.
* Reported seeded test jobs from durable queue outcomes instead of always showing a success notice.
* Kept CSS fallback diagnostics truthful when optimized CSS generation could not be durably queued.

= 11.7.12 =
* Fixed CWV retention reads so timeseries pruning and the CLI use the canonical plugin setting.
* Matched database-cleanup preview counts to the exact revision and post candidates selected by cleanup.
* Extended plain-text secret redaction to colon and whitespace key/value forms.
* Routed transient preload 429/5xx responses through bounded queue retries and respected Retry-After.

= 11.7.11 =
* Preserved genuine HTML attributes, scripts, comments and raw-text content across media, CSS, CDN, Delay JavaScript and minification rewrites.
* Corrected LCP, adaptive-image, responsive-image and YouTube-preview handling for valid attribute whitespace and priority states.
* Fixed protected callback execution, collision-safe internal placeholders and cache-header fallback sanitization without adding features or settings.

= 11.7.10 =
* Ensured disk and LiteSpeed cache finalizers receive the fully optimized HTML response before cache storage or cacheability headers are finalized.
* Added explicit buffer-priority contracts so cache hits remain early while cache writers wrap the frontend optimizer.

= 11.7.9 =
* Aligned cache, preload, optimization, media and server controls with their labels on desktop while retaining stacked mobile layouts.
* Flattened nested rule panels, balanced dashboard and insight cards, and restored consistent spacing between adjacent cards.
* Hid Asset Inspector copy actions until rules are selected and corrected the active hover and focus contrast.

= 11.7.8 =
* Aligned media, rendering and maintenance controls on desktop while preserving responsive stacking on smaller screens.
* Corrected preload queue labels, disabled unavailable queue actions and fixed singular/plural task summaries.

= 11.7.6 =
* Fixed malformed non-scalar search values in job, log and diagnostic queries so they fail safely without PHP warnings or TypeErrors.
* Preserved valid scalar search behavior, prepared SQL placeholders and existing pagination contracts.

= 11.7.5 =
* Fixed 153 reproducible malformed-input warnings, fatals and invalid array accesses in public callbacks and helper boundaries.
* Preserved normal scalar, list, cache, URL, scheduling and diagnostics behavior; no features, optimizations or refactors were added.

= 11.7.4 =
* Rejected non-scalar values at public cache, URL, fragment, router, rate-limit and diagnostics boundaries instead of emitting PHP warnings or fatals.
* Returned existing safe fallback values for malformed inputs without changing valid cache, routing, minification or URL behavior.

= 11.7.3 =

* Browser scan: reject malformed nested values without PHP warnings or incorrect persistence.
* Diagnostics: bound and type-filter recommendation, resource and timing payloads.
* LCP: preserve measured start times and srcset metadata across persistence paths.
* Resource safety: reject non-HTTP, overlong and invalid srcset resource data.
* Srcset: keep only complete valid candidates within the storage limit.

= 11.7.2 =

* Filesystem: reject symlinked or escaped cache artifacts before generating public cache URLs.
* Self-hosting: preserve query-dependent asset identities, refresh stale assets, purge their cache and support extensionless Google Fonts CSS.
* Compatibility: keep vendor CSS bytes intact and fall back when CSS contains unresolved relative dependencies.
* Media: refresh stale locally cached Gravatar and YouTube images through the existing background queue.
* Viewport images: enforce scalar URL contracts while preserving resource queries and case-sensitive page identities.

= 11.7.1 =

* REST: aligned the CWV endpoint schema with its required same-origin page URL contract.
* Security: rejected ambiguous ESI fragment identifiers instead of silently normalizing them.
* Hardening: bounded signed compatibility-overlay depth, node counts, array sizes and structured values before recursive processing.
* Release: clarified that development CI and browser tooling are maintained outside the installable production ZIP.

= 11.7.0 =

* Updates: added manifest- and SHA-256-verified self-service updates through the WordPress Update URI API.
* Diagnostics: added Site Health and support-report status for the public release channel.
* Product readiness: added security, support and compatibility policies; development CI, release and browser tooling are maintained in the source repository rather than the production ZIP.
* Safety: preserved all existing cache, optimization, WooCommerce and public extension contracts.

Complete release history: see `changelog.txt`.
