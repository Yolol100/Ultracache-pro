# UltraCache Pro repair31

## 4.22.7.1-repair31

- Dead code: removed unused `UCP_Jobs::has_pending_job()` (defined in `ucp-jobs-repository-trait.php` but never called anywhere in the plugin).
- Dead code: removed unused `UCP_Jobs::runner_lock_key()` (defined in `ucp-jobs-schedule-trait.php`; the lock implementation uses `runner_lock_option_name()` instead).
- Dead code: removed unused `UCP_Cache::response_is_uncacheable()` (defined in `ucp-cache-request-policy-trait.php` but never called; callers use `response_uncacheable_details()` directly).
- Dead code: removed unused `UCP_CSS::append_link_attribute()` (defined in `ucp-css-delivery-trait.php` but never called).
- Cleanup: removed 6 unreachable autoloader classmap entries for inlined traits (`ucp_cloud_*_trait`, `ucp_installer_schedule_trait`, `ucp_maintenance_schedule_trait`). These traits are composed only by their own host class, so the autoloader is never asked to resolve them. Added inline comments where they used to be so the structure stays discoverable.
- CSS cleanup: in `assets/admin/react/css/ucp-react-admin.css` removed three redundant rules inside the `@media (max-width: 782px)` block that all set `grid-template-columns: 1fr` on the same `.ucp-dashboard-grid` / `.ucp-status-grid` / `.ucp-preset-grid` selectors. Behaviour preserved: the remaining single rule `.ucp-dashboard-grid, .ucp-status-grid, .ucp-preset-grid { grid-template-columns: 1fr; }` already covered all three. The unique `.ucp-hero-metrics` declaration was kept as a standalone rule.
- Safety: no public hooks, class names, REST routes, option names, plugin headers or runtime behavior changed. All removed PHP methods were `protected`/`protected static` with zero call sites verified by full-codebase grep (incl. `->`, `::`, `array(__CLASS__, …)`, string callbacks, `method_exists`/`is_callable`/`call_user_func`). All removed CSS rules were verified redundant: identical selectors AND identical bodies inside the same media query, or strict subsets of a sibling rule with the same declaration.

## Validation

- Static parse: all 170 PHP files have balanced braces, parentheses and brackets.
- Cross-reference: every autoloader entry maps to a class/trait that is actually declared in the target file.
- No missing includes; no orphan PHP files; no broken `UCP_*` class references.
- All 10 compatibility JSON files validate as JSON.
- Dead-code sweep across the full plugin re-run after the patch; only false positives (constructor + callback used via `__CLASS__`) remain.
- CSS sweep: zero fully-identical (selector + body) duplicates in `ucp-react-admin.css`, `ucp-admin-design-system.css`, `ucp-admin-tokens.css`, `ucp-cache-toast.css` after the patch. Two intentional `!important` overrides for `.ucp-modern-topbar` and `.ucp-workspace__main` in the design-system file are documented and kept.
- JS sweep: no file-level duplicate function declarations. The three "duplicate" names flagged inside `ucp-react-admin.js` (`execute`, `updateOrder`, `updateColumns`) are nested helpers scoped inside separate React component bodies and are intentional. All core admin JS files export their helpers via `window.ucpAdminCore`; every export is consumed by a sibling core script (verified by full-tree grep).

# UltraCache Pro repair30

## 4.22.7.1-repair30

- Cleanup/refactor: admin hook suffixes are now generated from the canonical router page slug instead of repeating `toplevel_page_ultracache-pro` and `ultracache_page_ultracache-pro` in the enqueue flow.
- Cleanup/refactor: tab asset mapping, allowed tabs and settings-tab checks now come from `UCP_Admin_Router`, reducing repeated tab arrays across admin routing, settings screen and asset enqueueing.
- Safety: no public hooks, class names, REST routes, option names, plugin headers or runtime cache behavior changed.

## Validation

- PHP lint passed on all plugin PHP files.
- JSON validation passed for all compatibility files.
- JavaScript syntax check passed for admin assets.
- Static include/require scan found no missing fixed includes.
- ZIP integrity check passed for this repaired package.

# UltraCache Pro repair29

## 4.22.7.1-repair29

- Cleanup/refactor: admin page slug is now centralized in `UCP_Admin_Router::PAGE_SLUG` and exposed through `page_slug()`.
- Cleanup/refactor: admin menu registration, current-page checks, tab URL generation and compatibility page map now reuse the same canonical page slug instead of repeating the literal.
- Safety: no public hooks, class names, REST routes, option names, plugin headers or runtime cache behavior changed.

## Validation

- PHP lint passed on all plugin PHP files.
- JSON validation passed for all compatibility files.
- JavaScript syntax check passed for admin assets.
- Static include/require scan found no missing fixed includes.
- ZIP integrity check passed for this repaired package.

# UltraCache Pro repair28

## 4.22.7.1-repair28

- Cleanup/refactor: repeated jobs count SQL patterns now use one internal `count_jobs_where()` helper.
- Cleanup/refactor: `tab_slug()` now delegates to the existing current admin page slug helper instead of repeating the literal admin slug.
- Safety: no public hooks, class names, REST routes, option names, plugin headers, or runtime behavior changed.

## Validation

- PHP lint passed on all plugin PHP files.
- JSON validation passed for all compatibility files.
- JavaScript syntax check passed for admin assets.
- Static include/require scan found no missing fixed includes.
- ZIP integrity check passed for this repaired package.

# UltraCache Pro repair27

## 4.22.7.1-repair27

- Cleanup: compatibility JSON loading now uses one cached loader inside the compat detection trait.
- Cleanup: removed duplicate literal entries from the built-in asset exclusion list.
- Safety: no public hooks, plugin headers, class names, REST routes, or option names changed.

# UltraCache Pro repair26

## 4.22.7.1-repair26
- Cleanup/refactor: table-name validation and quoting centralized in `UCP_Helpers` and reused by maintenance/privacy/database cleanup flows.
- Cleanup/refactor: removed remaining runtime wildcard/query matcher wrappers from cache and preload traits; both now call the central helper directly.
- Safety: preserved standalone uninstall helpers because uninstall must work without loading the plugin runtime.

## Validation
- PHP lint passed on all plugin PHP files.
- JSON validation passed for all compatibility files.
- JavaScript syntax check passed for admin assets.
- Static include/require scan found no missing fixed includes.
- ZIP integrity check passed for this repaired package.

# UltraCache Pro repair25

## 4.22.7.1-repair25
- Cleanup/refactor: dubbele CSS safety-list verwerking samengevoegd in een gedeelde helper binnen de compat filters.
- Cleanup/refactor: PageSpeed auto/aggressive delay check gedelegeerd naar de bestaande autopilot mode helper.
- Cleanup/refactor: lokale REST URL-validatie gecentraliseerd in UCP_Helpers en callers daarop aangesloten.
- Cleanup/refactor: dubbele cache-tags bump_version uit storage trait verwijderd; de publieke class method blijft de enige bron.
- Cleanup/refactor: admin rule-row rendering delegeert naar de assets controller met fallback voor backward compatibility.

## 4.22.7.1-repair24
- Fixed uninstall WP-Cron cleanup so every plugin-owned scheduled hook is cleared before the clean-uninstall early return.
- Corrected the stale health hook name from `ucp_health_event` to the actual `ucp_health_check_event`.
- Added missing uninstall cleanup for lifecycle preload seed, maintenance, Google Font refresh and log retention hooks.
- Hardened the shared wildcard matcher by requiring `preg_match()` to return exactly `1`.
- Optimized Used CSS safelist handling by preparing plain and wildcard selector buckets once per extraction run instead of recalculating them for every selector.

## Validation
- PHP lint passed on all plugin PHP files.
- JSON validation passed for all compatibility files.
- JavaScript syntax check passed for admin assets.
- ZIP integrity check passed for this repaired package.

# UltraCache Pro repair23

## 4.22.7.0-repair23
- Expanded settings sanitization coverage so all non-internal default options used by presets, quality tools and runtime flags survive admin saves and receive the intended type-specific cleanup.
- Hardened domain-style settings by stripping schemes, paths, query strings, fragments and ports before storing CDN, DNS-prefetch and self-host asset domains.
- Normalized CDN host handling before HTML asset rewriting to avoid malformed CDN URLs when an admin enters a scheme or path by mistake.
- Reused the central wildcard and query-pattern matchers in preload/cache request code to reduce drift between early cache, runtime cache and preload exclusion behavior.
- Tightened preload safety to reject non-local URLs before exclusion checks.
- Simplified speculation-rule query exclusions to URLPattern-safe wildcard strings and removed a duplicate inline PHPCS comment.

## Validation
- PHP lint passed on all plugin PHP files.
- JSON validation passed for all compatibility files.
- JavaScript syntax check passed for admin assets.
- Targeted static checks reran for security patterns, REST/AJAX, HPOS, release readiness, plugin-check readiness and WPCS-style signals.

# UltraCache Pro repair15

## 4.22.7.0-repair22
- Fixed Used CSS safelist wildcard matching direction so patterns such as `.elementor-*`, `.woocommerce-*`, `.swiper-*`, `.slick-*`, `.jet-*`, `.jeg-*` and `joinchat__*` actually protect matching dynamic selectors during extraction.


## Confirmed fixes
- Reconnected the Asset Manager 2.0 admin panels to the Optimization tab so the new asset rules, snapshot controls and asset overview are visible in the UI.
- Reconnected the existing request-rule editor to the Advanced rules tab so the `asset_rules` rules can be managed again.
- Added a visible Asset Manager test-mode toggle next to the asset manager controls.
- Frontend asset snapshots now store and display reverse dependency information (`used by`) instead of losing it after capture.

## Validation
- PHP lint passed on all plugin PHP files.
- JavaScript syntax check passed.
- JSON validation passed.
- ZIP integrity check passed.

# UltraCache Pro repair17

## Confirmed fixes
- Hardened plugin-managed write path checks against dot-segment traversal, null bytes and stream-wrapper paths before cache/config writes or deletes.
- Added a bounded safe regex matcher for user-configured URL/rule regexes and routed rule-engine and asset-unload regex checks through it.
- Restored wildcard query-string inclusion matching in the early `advanced-cache.php` drop-in by preserving `*` in query-key patterns.
- Normalized host comparisons in the early cache drop-in so allowed hosts and cache keys handle host headers with ports consistently.

## Validation
- PHP lint passed on all 170 PHP files.
- Targeted static checks reran for security patterns, REST/AJAX, HPOS, release readiness, i18n, plugin-check readiness and WPCS-style patterns.
- ZIP integrity check passed for this repaired package.

## repair18 - FlyingPress benchmark-inspired hardening
- Added drop-in support for cache ignore query parameters, so tracking URLs such as `utm_*`, `gclid` and `fbclid` can reuse the canonical cache entry instead of bypassing early cache serving.
- Aligned runtime cache policy and early drop-in query handling: ignored query parameters are skipped unless explicitly included for separate cache variants.
- Normalized preload candidates by stripping ignored tracking query parameters before queueing, reducing duplicate preload jobs and unnecessary cache files.
- Added reusable helper methods for include/ignore query pattern handling without copying third-party plugin code.
- Preserved wildcard query patterns such as `filter_*` and `query_type_*` in both runtime cache policy and helper normalization.

## repair19 - query-pattern hardening
- Preserved controlled trailing wildcard query patterns such as `filter_*`, `query_type_*`, `utm_*` and `mtm_*` when settings are saved from the classic or React admin UI, while rejecting empty catch-all wildcards.
- Expanded ignored tracking query parameters with controlled wildcard families so campaign URLs reuse canonical cache entries more reliably.
- Kept the early `advanced-cache.php` fallback aligned with the runtime ignore list when the generated drop-in config is unavailable.

## Validation
- PHP lint passed on all 170 PHP files.
- JavaScript syntax check passed for admin assets.
- JSON validation passed for all compatibility files.
- ZIP integrity check passed for this repaired package.

## repair20 - LiteSpeed Cache benchmark-inspired compatibility hardening
- Added original UltraCache compatibility entries inspired by LiteSpeed Cache's public exclusion lists for brittle third-party scripts such as Jetpack Stats, Google CSE, SyntaxHighlighter, UserWay, Spotlight feeds, wp-statistics REST fragments, tagDiv/Newspaper runtime blocks and The Events Calendar breakpoint data.
- Added dynamic CSS safety exclusions for known theme-generated selector families such as Flatsome, tagDiv/Newspaper and WoodMart so Used CSS / async CSS is less likely to strip runtime-generated styling.
- Added safer dynamic-plugin profiles for FacetWP, bbPress, wpDiscuz, User Switching, Aelia Currency Switcher, NextGEN Gallery and Perfmatters without copying third-party implementation code.
- Treated Aelia currency/location cookies as cache-sensitive in runtime cache checks and in the generated early cache drop-in config.
- Fixed the Pagespeed Auto preset query inclusion pattern so `filter_*` and `query_type_*` stay wildcard patterns instead of plain fragments, and added safe WooCommerce pagination query variants.
- Added `no-lazy` and `wmu-preview-img` to lazyload exclusions to avoid breaking images already marked as non-lazy or preview-only by other plugins.

## Validation
- PHP lint passed on all plugin PHP files.
- JSON validation passed for all compatibility files.
- ZIP integrity check passed for this repaired package.

## repair21 - post-merge compatibility verification fixes
- Routed dynamic CSS safety patterns through a dedicated Used CSS safelist filter so selector families from compatibility rules are actually preserved during Used CSS extraction, not only listed as stylesheet/file exclusions.
- Moved LiteSpeed-inspired dynamic selector defaults from generic CSS file exclusions into the Used CSS safelist for cleaner settings and safer runtime behavior.
- Added `switch_to_olduser_` to default, preset and early drop-in cookie bypass rules so User Switching sessions cannot receive public page cache when unknown-cookie blocking is relaxed.

## Validation
- PHP lint passed on all plugin PHP files.
- JSON validation passed for all compatibility files.
- JavaScript syntax check passed for admin assets.
- ZIP integrity check passed for this repaired package.
