<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Loader {
    /**
     * Tracks whether the UltraCache autoloader has been registered.
     *
     * @var bool
     */
    private static $registered = false;

    /**
     * Return known runtime files for diagnostics.
     *
     * @return array<int,string>
     */
    public static function files() {
        return array_values(array_unique(array_values(self::classmap())));
    }

    /**
     * Register the lightweight UltraCache class/trait autoloader.
     *
     * @return void
     */
    public static function load() {
        self::register();
    }

    /**
     * Register the autoloader once.
     *
     * @return void
     */
    public static function register() {
        if (self::$registered) {
            return;
        }

        spl_autoload_register(array(__CLASS__, 'autoload'));
        self::$registered = true;
    }

    /**
     * Load a mapped UltraCache class or trait only when PHP asks for it.
     *
     * @param string $symbol Class or trait name.
     * @return void
     */
    public static function autoload($symbol) {
        if (!is_string($symbol) || 0 !== strpos($symbol, 'UCP_')) {
            return;
        }

        $map = self::classmap();
        $key = strtolower($symbol);
        if (empty($map[$key])) {
            return;
        }

        $file = UCP_PATH . $map[$key];
        if (is_file($file)) {
            require_once $file;
        }
    }

    /**
     * Map public UltraCache classes and internal traits to their canonical files.
     *
     * The loader targets canonical implementation files directly. This keeps frontend requests leaner
     * and prevents loading admin-only files until they are needed.
     *
     * Convention: only symbols that live in their own file get an entry. Traits that are
     * defined inside their composing class file (e.g. UCP_Admin_Render_Trait in
     * class-ucp-admin.php) are intentionally omitted: that file is already loaded via the
     * class entry before the trait is ever resolved, so a separate map entry would be dead.
     *
     * @return array<string,string>
     */
    private static function classmap() {
        return array(
            'ucp_admin' => 'includes/admin/class-ucp-admin.php',
            'ucp_admin_actions' => 'includes/admin/class-ucp-admin-actions.php',
            'ucp_admin_actions_cleanup_trait' => 'includes/admin/actions/ucp-admin-actions-cleanup-trait.php',
            'ucp_admin_actions_import_export_trait' => 'includes/admin/actions/ucp-admin-actions-import-export-trait.php',
            'ucp_admin_actions_maintenance_trait' => 'includes/admin/actions/ucp-admin-actions-maintenance-trait.php',
            'ucp_admin_actions_presets_trait' => 'includes/admin/actions/ucp-admin-actions-presets-trait.php',
            'ucp_admin_config' => 'includes/admin/class-ucp-admin-config.php',
            'ucp_admin_object_cache_page' => 'includes/admin/class-ucp-admin-object-cache-page.php',
            'ucp_admin_notices' => 'includes/admin/notices/class-ucp-admin-notices.php',
            'ucp_admin_notices_flash_toast_trait' => 'includes/admin/notices/traits/ucp-admin-notices-flash-toast-trait.php',
            'ucp_admin_notices_render_trait' => 'includes/admin/notices/traits/ucp-admin-notices-render-trait.php',
            'ucp_admin_react_app' => 'includes/admin/class-ucp-admin-react-app.php',
            'ucp_admin_router' => 'includes/admin/class-ucp-admin-router.php',
            'ucp_admin_sanitizer' => 'includes/admin/class-ucp-admin-sanitizer.php',
            'ucp_assets' => 'includes/assets/class-ucp-assets.php',
            'ucp_assets_combine_trait' => 'includes/assets/traits/ucp-assets-combine-trait.php',
            'ucp_assets_minify_trait' => 'includes/assets/traits/ucp-assets-minify-trait.php',
            'ucp_assets_unload_trait' => 'includes/assets/traits/ucp-assets-unload-trait.php',
            'ucp_cli' => 'includes/core/class-ucp-cli.php',
            'ucp_css' => 'includes/css/class-ucp-css.php',
            'ucp_css_profile' => 'includes/css/class-ucp-css-profile.php',
            'ucp_css_artifact_trait' => 'includes/css/traits/ucp-css-artifact-trait.php',
            'ucp_css_delivery_trait' => 'includes/css/traits/ucp-css-delivery-trait.php',
            'ucp_css_generation_trait' => 'includes/css/traits/ucp-css-generation-trait.php',
            'ucp_cwv' => 'includes/class-ucp-cwv.php',
            'ucp_cwv_lcp_sanitizer' => 'includes/cwv/class-ucp-cwv-lcp-sanitizer.php',
            'ucp_cwv_lcp_profile_repository' => 'includes/cwv/class-ucp-cwv-lcp-profile-repository.php',
            'ucp_cwv_rate_limiter' => 'includes/cwv/class-ucp-cwv-rate-limiter.php',
            'ucp_cwv_metric_summary' => 'includes/cwv/class-ucp-cwv-metric-summary.php',
            'ucp_cache' => 'includes/cache/class-ucp-cache.php',
            'ucp_litespeed_cache' => 'includes/cache/class-ucp-litespeed-cache.php',
            'ucp_cache_policy' => 'includes/cache/class-ucp-cache-policy.php',
            'ucp_cache_admin_bar_trait' => 'includes/cache/traits/ucp-cache-admin-bar-trait.php',
            'ucp_cache_purge_actions_trait' => 'includes/cache/traits/purge/ucp-cache-purge-actions-trait.php',
            'ucp_cache_purge_content_events_trait' => 'includes/cache/traits/purge/ucp-cache-purge-content-events-trait.php',
            'ucp_cache_purge_lifecycle_trait' => 'includes/cache/traits/purge/ucp-cache-purge-lifecycle-trait.php',
            'ucp_cache_purge_url_map_trait' => 'includes/cache/traits/purge/ucp-cache-purge-url-map-trait.php',
            'ucp_cache_request_policy_trait' => 'includes/cache/traits/ucp-cache-request-policy-trait.php',
            'ucp_cache_storage_trait' => 'includes/cache/traits/ucp-cache-storage-trait.php',
            'ucp_shopper_cache' => 'includes/cache/class-ucp-shopper-cache.php',
            'ucp_cache_tags' => 'includes/cache/tags/class-ucp-cache-tags.php',
            'ucp_cache_tags_resolver_trait' => 'includes/cache/tags/traits/ucp-cache-tags-resolver-trait.php',
            'ucp_cache_tags_storage_trait' => 'includes/cache/tags/traits/ucp-cache-tags-storage-trait.php',
            'ucp_cloud' => 'includes/cloud/class-ucp-cloud.php',
            'ucp_cdn' => 'includes/cloud/class-ucp-cdn.php',
            'ucp_host_cache' => 'includes/cloud/class-ucp-host-cache.php',
            'ucp_render_bridge' => 'includes/css/class-ucp-render-bridge.php',
            'ucp_image_queue' => 'includes/class-ucp-image-queue.php',
            'ucp_esi' => 'includes/cache/class-ucp-esi.php',
            'ucp_compat_updater' => 'includes/compat/class-ucp-compat-updater.php',
            'ucp_safe_autopilot' => 'includes/core/class-ucp-safe-autopilot.php',
            'ucp_lqip' => 'includes/class-ucp-lqip.php',
            'ucp_viewport_images' => 'includes/css/class-ucp-viewport-images.php',
            'ucp_self_host_media' => 'includes/optimization/class-ucp-self-host-media.php',
            'ucp_asset_inspector' => 'includes/assets/class-ucp-asset-inspector.php',
            // Cloud traits are defined inside class-ucp-cloud.php and are loaded with UCP_Cloud.
            'ucp_compat' => 'includes/compat/class-ucp-compat.php',
            'ucp_compat_combine_trait' => 'includes/compat/traits/ucp-compat-combine-trait.php',
            'ucp_compat_detection_trait' => 'includes/compat/traits/ucp-compat-detection-trait.php',
            'ucp_compat_filters_trait' => 'includes/compat/traits/ucp-compat-filters-trait.php',
            'ucp_db_cleanup' => 'includes/database/cleanup/class-ucp-db-cleanup.php',
            'ucp_dashboard_widget' => 'includes/core/class-ucp-dashboard-widget.php',
            'ucp_db_cleanup_runner_trait' => 'includes/database/cleanup/ucp-db-cleanup-runner-trait.php',
            'ucp_diagnostics' => 'includes/diagnostics/class-ucp-diagnostics.php',
            'ucp_edge' => 'includes/class-ucp-edge.php',
            'ucp_edge_html' => 'includes/class-ucp-edge-html.php',
            'ucp_fonts' => 'includes/class-ucp-fonts.php',
            'ucp_fragment_cache' => 'includes/class-ucp-fragment-cache.php',
            'ucp_health' => 'includes/core/class-ucp-health.php',
            'ucp_helpers' => 'includes/filesystem/class-ucp-helpers.php',
            'ucp_helpers_dropin_trait' => 'includes/filesystem/traits/ucp-helpers-dropin-trait.php',
            'ucp_helpers_filesystem_trait' => 'includes/filesystem/traits/ucp-helpers-filesystem-trait.php',
            'ucp_helpers_minify_and_log_trait' => 'includes/filesystem/traits/ucp-helpers-minify-and-log-trait.php',
            'ucp_helpers_url_trait' => 'includes/filesystem/traits/ucp-helpers-url-trait.php',
            'ucp_image_optimizer' => 'includes/class-ucp-image-optimizer.php',
            'ucp_installer' => 'includes/core/installer/class-ucp-installer.php',
            'ucp_installer_lifecycle_trait' => 'includes/core/installer/ucp-installer-lifecycle-trait.php',
            // UCP_Installer_Schedule_Trait is defined inside class-ucp-installer.php and only
            // composed by UCP_Installer itself; never autoloaded separately.
            'ucp_installer_schema_trait' => 'includes/core/installer/ucp-installer-schema-trait.php',
            'ucp_integrations' => 'includes/integrations/class-ucp-integrations.php',
            'ucp_integrations_autopilot_trait' => 'includes/integrations/traits/ucp-integrations-autopilot-trait.php',
            'ucp_integrations_delay_js_profiles_trait' => 'includes/integrations/traits/ucp-integrations-delay-js-profiles-trait.php',
            'ucp_integrations_delay_js_trait' => 'includes/integrations/traits/ucp-integrations-delay-js-trait.php',
            'ucp_integrations_detection_trait' => 'includes/integrations/traits/ucp-integrations-detection-trait.php',
            'ucp_jobs' => 'includes/jobs/class-ucp-jobs.php',
            'ucp_jobs_repository_trait' => 'includes/jobs/traits/ucp-jobs-repository-trait.php',
            'ucp_jobs_runner_trait' => 'includes/jobs/traits/ucp-jobs-runner-trait.php',
            'ucp_jobs_schedule_trait' => 'includes/jobs/traits/ucp-jobs-schedule-trait.php',
            'ucp_loader' => 'includes/bootstrap/class-ucp-loader.php',
            'ucp_log_package' => 'includes/core/log-package/class-ucp-log-package.php',
            'ucp_log_package_download_trait' => 'includes/core/log-package/ucp-log-package-download-trait.php',
            'ucp_logger' => 'includes/core/class-ucp-logger.php',
            'ucp_maintenance' => 'includes/core/class-ucp-maintenance.php',
            'ucp_maintenance_cleanup_trait' => 'includes/core/maintenance/ucp-maintenance-cleanup-trait.php',
            'ucp_maintenance_privacy_trait' => 'includes/core/maintenance/ucp-maintenance-privacy-trait.php',
            // UCP_Maintenance_Schedule_Trait is loaded with UCP_Maintenance.
            'ucp_modules' => 'includes/class-ucp-modules.php',
            'ucp_pagespeed_browser_scan' => 'includes/core/class-ucp-pagespeed-browser-scan.php',
            'ucp_pagespeed_browser_scan_optimizer' => 'includes/core/pagespeed/class-ucp-pagespeed-browser-scan-optimizer.php',
            'ucp_pagespeed_browser_scan_sanitizer' => 'includes/core/pagespeed/class-ucp-pagespeed-browser-scan-sanitizer.php',
            'ucp_page_overrides' => 'includes/core/class-ucp-page-overrides.php',
            'ucp_object_cache' => 'includes/class-ucp-object-cache.php',
            'ucp_optimization_intelligence' => 'includes/core/class-ucp-optimization-intelligence.php',
            'ucp_html_parser' => 'includes/optimization/class-ucp-html-parser.php',
            'ucp_optimizer' => 'includes/optimization/class-ucp-optimizer.php',
            'ucp_optimizer_cdn_hints_trait' => 'includes/optimization/traits/ucp-optimizer-cdn-hints-trait.php',
            'ucp_optimizer_core_bloat_trait' => 'includes/optimization/traits/ucp-optimizer-core-bloat-trait.php',
            'ucp_optimizer_html_trait' => 'includes/optimization/traits/ucp-optimizer-html-trait.php',
            'ucp_optimizer_media_iframe_trait' => 'includes/optimization/traits/media/ucp-optimizer-media-iframe-trait.php',
            'ucp_optimizer_media_image_trait' => 'includes/optimization/traits/media/ucp-optimizer-media-image-trait.php',
            'ucp_optimizer_media_preload_trait' => 'includes/optimization/traits/media/ucp-optimizer-media-preload-trait.php',
            'ucp_optimizer_scripts_trait' => 'includes/optimization/traits/ucp-optimizer-scripts-trait.php',
            'ucp_options' => 'includes/options/class-ucp-options.php',
            'ucp_options_defaults_trait' => 'includes/options/traits/ucp-options-defaults-trait.php',
            'ucp_options_lifecycle_trait' => 'includes/options/traits/ucp-options-lifecycle-trait.php',
            'ucp_options_normalize_trait' => 'includes/options/traits/ucp-options-normalize-trait.php',
            'ucp_plugin' => 'includes/bootstrap/class-ucp-plugin.php',
            'ucp_preload' => 'includes/preload/class-ucp-preload.php',
            'ucp_preload_collector_trait' => 'includes/preload/traits/ucp-preload-collector-trait.php',
            'ucp_preload_runner_trait' => 'includes/preload/traits/ucp-preload-runner-trait.php',
            'ucp_preload_safety_trait' => 'includes/preload/traits/ucp-preload-safety-trait.php',
            'ucp_presets' => 'includes/core/class-ucp-presets.php',
            'ucp_quality_suite' => 'includes/core/class-ucp-quality-suite.php',
            'ucp_quality_suite_runtime_trait' => 'includes/core/quality/ucp-quality-suite-runtime-trait.php',
            'ucp_rest_actions_trait' => 'includes/rest/admin/traits/ucp-rest-actions-trait.php',
            'ucp_rest_admin_controller' => 'includes/rest/admin/class-ucp-rest-admin-controller.php',
            'ucp_rest_cache' => 'includes/class-ucp-rest-cache.php',
            'ucp_rest_diagnostics_trait' => 'includes/rest/admin/traits/ucp-rest-diagnostics-trait.php',
            'ucp_rest_settings_trait' => 'includes/rest/admin/traits/ucp-rest-settings-trait.php',
            'ucp_rest_status_trait' => 'includes/rest/admin/traits/ucp-rest-status-trait.php',
            'ucp_rule_engine' => 'includes/core/class-ucp-rule-engine.php',
            'ucp_runtime_tests' => 'includes/core/class-ucp-runtime-tests.php',
            'ucp_settings_combined_controls' => 'includes/settings/class-ucp-settings-combined-controls.php',
            'ucp_site_health' => 'includes/core/class-ucp-site-health.php',
            'ucp_support_report' => 'includes/core/class-ucp-support-report.php',
        );
    }
}
