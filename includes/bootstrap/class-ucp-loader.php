<?php
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
     * @return array<string,string>
     */
    private static function classmap() {
        return array(
            'ucp_admin' => 'includes/admin/class-ucp-admin.php',
            'ucp_admin_action_proxies_trait' => 'includes/admin/traits/ucp-admin-action-proxies-trait.php',
            'ucp_admin_actions' => 'includes/admin/class-ucp-admin-actions.php',
            'ucp_admin_actions_cleanup_trait' => 'includes/admin/actions/ucp-admin-actions-cleanup-trait.php',
            'ucp_admin_actions_import_export_trait' => 'includes/admin/actions/ucp-admin-actions-import-export-trait.php',
            'ucp_admin_actions_maintenance_trait' => 'includes/admin/actions/ucp-admin-actions-maintenance-trait.php',
            'ucp_admin_actions_presets_trait' => 'includes/admin/actions/ucp-admin-actions-presets-trait.php',
            'ucp_admin_assets_controller' => 'includes/admin/controllers/class-ucp-admin-assets-controller.php',
            'ucp_admin_assets_trait' => 'includes/admin/traits/ucp-admin-assets-trait.php',
            'ucp_admin_config' => 'includes/admin/class-ucp-admin-config.php',
            'ucp_admin_field_logic' => 'includes/admin/field-logic/class-ucp-admin-field-logic.php',
            'ucp_admin_field_logic_schema_trait' => 'includes/admin/field-logic/traits/ucp-admin-field-logic-schema-trait.php',
            'ucp_admin_field_logic_state_trait' => 'includes/admin/field-logic/traits/ucp-admin-field-logic-state-trait.php',
            'ucp_admin_fields' => 'includes/admin/helpers/class-ucp-admin-fields.php',
            'ucp_admin_lifecycle_trait' => 'includes/admin/traits/ucp-admin-lifecycle-trait.php',
            'ucp_admin_metrics' => 'includes/admin/class-ucp-admin-metrics.php',
            'ucp_admin_notices' => 'includes/admin/notices/class-ucp-admin-notices.php',
            'ucp_admin_notices_flash_toast_trait' => 'includes/admin/notices/traits/ucp-admin-notices-flash-toast-trait.php',
            'ucp_admin_notices_render_trait' => 'includes/admin/notices/traits/ucp-admin-notices-render-trait.php',
            'ucp_admin_react_app' => 'includes/admin/class-ucp-admin-react-app.php',
            'ucp_admin_render_trait' => 'includes/admin/traits/ucp-admin-render-trait.php',
            'ucp_admin_router' => 'includes/admin/class-ucp-admin-router.php',
            'ucp_admin_routing_trait' => 'includes/admin/traits/ucp-admin-routing-trait.php',
            'ucp_admin_rules' => 'includes/admin/class-ucp-admin-rules.php',
            'ucp_admin_sanitizer' => 'includes/admin/class-ucp-admin-sanitizer.php',
            'ucp_admin_settings_screen' => 'includes/admin/class-ucp-admin-settings-screen.php',
            'ucp_admin_shell' => 'includes/admin/class-ucp-admin-shell.php',
            'ucp_admin_submit' => 'includes/admin/class-ucp-admin-submit.php',
            'ucp_admin_tab_cdn' => 'includes/admin/tabs/class-ucp-admin-tab-cdn.php',
            'ucp_admin_tab_database' => 'includes/admin/tabs/class-ucp-admin-tab-database.php',
            'ucp_admin_tab_heartbeat' => 'includes/admin/tabs/class-ucp-admin-tab-heartbeat.php',
            'ucp_admin_tab_media' => 'includes/admin/tabs/class-ucp-admin-tab-media.php',
            'ucp_admin_tab_onboarding' => 'includes/admin/tabs/class-ucp-admin-tab-onboarding.php',
            'ucp_admin_tab_optimization' => 'includes/admin/tabs/class-ucp-admin-tab-optimization.php',
            'ucp_admin_tab_overview' => 'includes/admin/tabs/class-ucp-admin-tab-overview.php',
            'ucp_admin_tab_tools' => 'includes/admin/tabs/class-ucp-admin-tab-tools.php',
            'ucp_admin_tabs' => 'includes/admin/class-ucp-admin-tabs.php',
            'ucp_admin_ui' => 'includes/admin/class-ucp-admin-ui.php',
            'ucp_admin_view' => 'includes/admin/views/class-ucp-admin-view.php',
            'ucp_assets' => 'includes/assets/class-ucp-assets.php',
            'ucp_assets_combine_trait' => 'includes/assets/traits/ucp-assets-combine-trait.php',
            'ucp_assets_minify_trait' => 'includes/assets/traits/ucp-assets-minify-trait.php',
            'ucp_assets_unload_trait' => 'includes/assets/traits/ucp-assets-unload-trait.php',
            'ucp_cli' => 'includes/core/class-ucp-cli.php',
            'ucp_css' => 'includes/css/class-ucp-css.php',
            'ucp_css_artifact_trait' => 'includes/css/traits/ucp-css-artifact-trait.php',
            'ucp_css_delivery_trait' => 'includes/css/traits/ucp-css-delivery-trait.php',
            'ucp_css_generation_trait' => 'includes/css/traits/ucp-css-generation-trait.php',
            'ucp_cwv' => 'includes/class-ucp-cwv.php',
            'ucp_cache' => 'includes/cache/class-ucp-cache.php',
            'ucp_cache_admin_bar_trait' => 'includes/cache/traits/ucp-cache-admin-bar-trait.php',
            'ucp_cache_purge_trait' => 'includes/cache/traits/ucp-cache-purge-trait.php',
            'ucp_cache_request_policy_trait' => 'includes/cache/traits/ucp-cache-request-policy-trait.php',
            'ucp_cache_storage_trait' => 'includes/cache/traits/ucp-cache-storage-trait.php',
            'ucp_cache_tags' => 'includes/cache/tags/class-ucp-cache-tags.php',
            'ucp_cache_tags_registry_trait' => 'includes/cache/tags/traits/ucp-cache-tags-registry-trait.php',
            'ucp_cache_tags_resolver_trait' => 'includes/cache/tags/traits/ucp-cache-tags-resolver-trait.php',
            'ucp_cache_tags_storage_trait' => 'includes/cache/tags/traits/ucp-cache-tags-storage-trait.php',
            'ucp_cloud' => 'includes/cloud/class-ucp-cloud.php',
            // UCP_Cloud_Routes_Trait, UCP_Cloud_CSS_Trait, UCP_Cloud_Endpoint_Trait,
            // UCP_Cloud_HTTP_Trait are defined inside class-ucp-cloud.php and only
            // composed by UCP_Cloud itself; they are never autoloaded separately.
            'ucp_compat' => 'includes/compat/class-ucp-compat.php',
            'ucp_compat_combine_trait' => 'includes/compat/traits/ucp-compat-combine-trait.php',
            'ucp_compat_detection_trait' => 'includes/compat/traits/ucp-compat-detection-trait.php',
            'ucp_compat_filters_trait' => 'includes/compat/traits/ucp-compat-filters-trait.php',
            'ucp_db_cleanup' => 'includes/database/cleanup/class-ucp-db-cleanup.php',
            'ucp_db_cleanup_counts_trait' => 'includes/database/cleanup/ucp-db-cleanup-counts-trait.php',
            'ucp_db_cleanup_runner_trait' => 'includes/database/cleanup/ucp-db-cleanup-runner-trait.php',
            'ucp_db_cleanup_schedule_trait' => 'includes/database/cleanup/ucp-db-cleanup-schedule-trait.php',
            'ucp_diagnostics' => 'includes/diagnostics/class-ucp-diagnostics.php',
            'ucp_diagnostics_query_trait' => 'includes/diagnostics/ucp-diagnostics-query-trait.php',
            'ucp_diagnostics_record_trait' => 'includes/diagnostics/ucp-diagnostics-record-trait.php',
            'ucp_diagnostics_storage_trait' => 'includes/diagnostics/ucp-diagnostics-storage-trait.php',
            'ucp_edge' => 'includes/class-ucp-edge.php',
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
            'ucp_integrations_status_trait' => 'includes/integrations/traits/ucp-integrations-status-trait.php',
            'ucp_jobs' => 'includes/jobs/class-ucp-jobs.php',
            'ucp_jobs_admin_actions_trait' => 'includes/jobs/traits/ucp-jobs-admin-actions-trait.php',
            'ucp_jobs_payload_trait' => 'includes/jobs/traits/ucp-jobs-payload-trait.php',
            'ucp_jobs_repository_trait' => 'includes/jobs/traits/ucp-jobs-repository-trait.php',
            'ucp_jobs_runner_trait' => 'includes/jobs/traits/ucp-jobs-runner-trait.php',
            'ucp_jobs_schedule_trait' => 'includes/jobs/traits/ucp-jobs-schedule-trait.php',
            'ucp_loader' => 'includes/bootstrap/class-ucp-loader.php',
            'ucp_log_package' => 'includes/core/log-package/class-ucp-log-package.php',
            'ucp_log_package_data_trait' => 'includes/core/log-package/ucp-log-package-data-trait.php',
            'ucp_log_package_download_trait' => 'includes/core/log-package/ucp-log-package-download-trait.php',
            'ucp_log_package_redaction_trait' => 'includes/core/log-package/ucp-log-package-redaction-trait.php',
            'ucp_log_package_writer_trait' => 'includes/core/log-package/ucp-log-package-writer-trait.php',
            'ucp_logger' => 'includes/core/class-ucp-logger.php',
            'ucp_maintenance' => 'includes/core/class-ucp-maintenance.php',
            'ucp_maintenance_cleanup_trait' => 'includes/core/maintenance/ucp-maintenance-cleanup-trait.php',
            'ucp_maintenance_privacy_trait' => 'includes/core/maintenance/ucp-maintenance-privacy-trait.php',
            // UCP_Maintenance_Schedule_Trait is defined inside ucp-maintenance-cleanup-trait.php
            // and only composed by UCP_Maintenance; never autoloaded separately.
            'ucp_modules' => 'includes/class-ucp-modules.php',
            'ucp_pagespeed_browser_scan' => 'includes/core/class-ucp-pagespeed-browser-scan.php',
            'ucp_object_cache' => 'includes/class-ucp-object-cache.php',
            'ucp_optimizer' => 'includes/optimization/class-ucp-optimizer.php',
            'ucp_optimizer_cdn_hints_trait' => 'includes/optimization/traits/ucp-optimizer-cdn-hints-trait.php',
            'ucp_optimizer_core_bloat_trait' => 'includes/optimization/traits/ucp-optimizer-core-bloat-trait.php',
            'ucp_optimizer_html_trait' => 'includes/optimization/traits/ucp-optimizer-html-trait.php',
            'ucp_optimizer_media_trait' => 'includes/optimization/traits/ucp-optimizer-media-trait.php',
            'ucp_optimizer_scripts_trait' => 'includes/optimization/traits/ucp-optimizer-scripts-trait.php',
            'ucp_options' => 'includes/options/class-ucp-options.php',
            'ucp_options_defaults_trait' => 'includes/options/traits/ucp-options-defaults-trait.php',
            'ucp_options_lifecycle_trait' => 'includes/options/traits/ucp-options-lifecycle-trait.php',
            'ucp_options_normalize_trait' => 'includes/options/traits/ucp-options-normalize-trait.php',
            'ucp_plugin' => 'includes/bootstrap/class-ucp-plugin.php',
            'ucp_preload' => 'includes/preload/class-ucp-preload.php',
            'ucp_preload_admin_trait' => 'includes/preload/traits/ucp-preload-admin-trait.php',
            'ucp_preload_collector_trait' => 'includes/preload/traits/ucp-preload-collector-trait.php',
            'ucp_preload_runner_trait' => 'includes/preload/traits/ucp-preload-runner-trait.php',
            'ucp_preload_safety_trait' => 'includes/preload/traits/ucp-preload-safety-trait.php',
            'ucp_preload_schedule_trait' => 'includes/preload/traits/ucp-preload-schedule-trait.php',
            'ucp_presets' => 'includes/core/class-ucp-presets.php',
            'ucp_quality_suite' => 'includes/core/class-ucp-quality-suite.php',
            'ucp_quality_suite_actions_trait' => 'includes/core/quality/ucp-quality-suite-actions-trait.php',
            'ucp_quality_suite_conflicts_trait' => 'includes/core/quality/ucp-quality-suite-conflicts-trait.php',
            'ucp_quality_suite_release_logs_trait' => 'includes/core/quality/ucp-quality-suite-release-logs-trait.php',
            'ucp_quality_suite_routing_trait' => 'includes/core/quality/ucp-quality-suite-routing-trait.php',
            'ucp_quality_suite_runtime_trait' => 'includes/core/quality/ucp-quality-suite-runtime-trait.php',
            'ucp_quality_suite_site_health_trait' => 'includes/core/quality/ucp-quality-suite-site-health-trait.php',
            'ucp_quality_suite_url_safety_trait' => 'includes/core/quality/ucp-quality-suite-url-safety-trait.php',
            'ucp_rest_actions_trait' => 'includes/rest/admin/traits/ucp-rest-actions-trait.php',
            'ucp_rest_admin_controller' => 'includes/rest/admin/class-ucp-rest-admin-controller.php',
            'ucp_rest_cache' => 'includes/class-ucp-rest-cache.php',
            'ucp_rest_diagnostics_trait' => 'includes/rest/admin/traits/ucp-rest-diagnostics-trait.php',
            'ucp_rest_settings_trait' => 'includes/rest/admin/traits/ucp-rest-settings-trait.php',
            'ucp_rest_status_trait' => 'includes/rest/admin/traits/ucp-rest-status-trait.php',
            'ucp_rule_engine' => 'includes/core/class-ucp-rule-engine.php',
            'ucp_runtime_tests' => 'includes/core/class-ucp-runtime-tests.php',
            'ucp_site_health' => 'includes/core/class-ucp-site-health.php',
            'ucp_support_report' => 'includes/core/class-ucp-support-report.php',
        );
    }
}
