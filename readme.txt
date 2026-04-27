=== UltraCache Pro 4.27.1 ===
Contributors: ultracache-pro
Tags: cache, performance, core web vitals, critical css, used css, preload, minify
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 4.27.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern caching and frontend optimization suite for WordPress.

== Description ==

UltraCache Pro provides:
- Static page caching
- Scheduled preload
- CSS/JS minify and combine
- Delay JavaScript engine
- Used CSS generation per URL
- Critical CSS generation
- Lazy loading for images and iframes
- Browser cache header helpers
- WooCommerce smart cache rules
- Database cleanup tools
- Cloud connector hooks
- Admin bar purge controls

== Premium safety notes ==

Used CSS and Critical CSS are advanced features. Enable them first on staging, use the safelist for dynamic selectors, and visually check key templates before production.
Stale cache is disabled by default and should only be enabled when showing temporarily older cached content is acceptable.
UltraCache preserves existing advanced-cache.php drop-ins and warns about known cache or optimization overlap.

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open UltraCache in wp-admin en kies een tab.
4. Sla op en leeg daarna eenmalig de cache.
5. Test CSS en script-opties eerst op staging.

== Changelog ==
= 4.19.0 =
* Auto delay JS exclusions now scan active plugins and the current theme when Delay JS is enabled.
* Added broader built-in exclusions for builders, forms, commerce, consent, and common themes.
* Delay JS exclusions are synced into the saved list while remaining filterable at runtime.

* Cleaned up remaining admin controller screens so they match the simplified UI and hidden safe defaults.
* Improved fallback tools/import UI consistency, including support for importing .txt settings files in legacy admin flows.

= 4.9.0 =
* Added smart cache tags for related-page invalidation with optional persistent object-cache acceleration.
* Improved Cloudflare purging to support batched URL purges from the background jobs queue.
* Kept the admin UI simple with only a few new controls.

= 4.1.0 =
* Refined WP Admin UX with Material 3 inspired cards, tabs, chips and clearer information architecture.
* Added richer overview, jobs, diagnostics and rule builder presentation.
* Improved simple vs advanced mode clarity and surfaced existing premium architecture features.

= 3.0.0 =
* Added queue/jobs engine, remote Used CSS/Critical CSS rendering hooks, Cloudflare/APO and Early Hints helpers, script modules compatibility and per-URL diagnostics.

= 2.0.0 =
* Added file optimization, delay JS, used CSS, critical CSS, DB cleanup and cloud connector architecture.


= 4.16.4 =
* Added privacy exporter and eraser hooks for matching emails in logs and diagnostics.
* Improved admin accessibility with clearer focus states and accessible labels on search and rule controls.
* Tightened admin table and toolbar spacing for more consistent formatting.

= 4.16.3 =
* Admin UI simpeler en consistenter gemaakt.
* Automatische veilige hulp toegevoegd voor compatibiliteit.
* Veilige knoppen toegevoegd voor Heartbeat, preload en server cache setup.


== Privacy ==
UltraCache Pro can store local logs and diagnostics for administrators. The plugin adds privacy policy text and includes exporter and eraser integrations for matching email addresses found in stored logs or diagnostics.

== Accessibility ==
The admin interface uses real form controls, visible focus states and accessible labels for search and rule controls.


== Third-Party Services ==

UltraCache Pro can connect to third-party services only when an administrator explicitly configures or enables those integrations.

= Cloudflare purge =
* Service: Cloudflare API
* Purpose: purge the full zone cache or selected URLs after manual purge actions or plugin-driven purge jobs.
* Data sent: configured Cloudflare Zone ID, API token in the authorization header, and the URL or URL list being purged.
* When: only when Cloudflare-related settings are configured and a purge request is triggered.
* Privacy / terms: use your Cloudflare privacy policy and Cloudflare terms links where appropriate.

= Cloud sync / render endpoint =
* Service: custom cloud endpoint configured by the site owner
* Purpose: sync, remote render, or related cloud tasks initiated by UltraCache Pro features.
* Data sent: configured endpoint URL, site code, API key, and task-specific URLs or payload fragments needed to complete the requested operation.
* When: only when the cloud integration is configured and the related cloud feature or job is enabled.
* Privacy / terms: document the privacy policy and terms for the configured service in your site or service documentation.

= Premium modules added =
UltraCache Pro includes optional premium modules for WebP/AVIF image variants, Google Fonts localization, link preload/speculation, REST API caching, fragment cache helper API, object-cache detection, and lightweight Core Web Vitals monitoring. Enable these modules gradually and test dynamic pages such as checkout, account, forms and dashboards.
