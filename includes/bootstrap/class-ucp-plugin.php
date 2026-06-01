<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Plugin {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(UCP_FILE, array($this, 'activate'));
        register_deactivation_hook(UCP_FILE, array($this, 'deactivate'));

        add_action('init', array($this, 'bootstrap'));
        add_action('before_woocommerce_init', array($this, 'declare_woocommerce_features'));
        add_action('wpmu_new_blog', array($this, 'activate_new_blog'), 10, 1);
        add_action('update_option_' . UCP_Options::OPTION_KEY, array('UCP_Options', 'handle_option_updated'), 10, 2);
    }


    /**
     * Declare compatibility with WooCommerce feature flags when WooCommerce is present.
     *
     * The plugin uses WooCommerce CRUD APIs for order-triggered cache purges, so it is
     * safe to declare HPOS compatibility without forcing WooCommerce as a dependency.
     */
    public function declare_woocommerce_features() {
        if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', UCP_FILE, true);
    }


    public function activate() {
        UCP_Installer::activate();
    }

    public function deactivate() {
        UCP_Installer::deactivate();
    }

    public function activate_new_blog($blog_id) {
        if (!is_multisite()) {
            return;
        }
        switch_to_blog((int) $blog_id);
        UCP_Installer::activate();
        restore_current_blog();
    }

    /**
     * Return true when any of the given UCP option keys evaluates as truthy.
     * Used by bootstrap() to keep module-load conditions readable.
     */
    private static function any_option_enabled(array $keys) {
        foreach ($keys as $key) {
            if (UCP_Options::get($key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine whether modules that are admin/cron/CLI sensitive should be booted.
     *
     * @return bool
     */
    private static function is_backend_context() {
        return is_admin() || (function_exists('wp_doing_cron') && wp_doing_cron()) || (defined('WP_CLI') && WP_CLI);
    }

    /**
     * Run option migrations in a single ordered place before modules read settings.
     *
     * @return void
     */
    private static function run_option_migrations() {
        UCP_Options::maybe_apply_runtime_write_and_log_migration();
        UCP_Options::maybe_apply_preload_safety_migration();

        $optional_migrations = array(
            'maybe_apply_queue_repair_migration',
            'maybe_upgrade_pagespeed_auto_v2',
            'maybe_upgrade_pagespeed_auto_v3',
            'maybe_upgrade_pagespeed_auto_v4',
            'maybe_upgrade_pagespeed_auto_v5',
            'maybe_upgrade_pagespeed_auto_v6',
            'maybe_upgrade_pagespeed_auto_v7',
            'maybe_upgrade_pagespeed_auto_v8',
            'maybe_upgrade_pagespeed_auto_v9',
            'maybe_upgrade_pagespeed_auto_v10',
            'maybe_upgrade_pagespeed_auto_v11',
            'maybe_upgrade_pagespeed_auto_v12',
        );

        foreach ($optional_migrations as $migration) {
            if (method_exists('UCP_Options', $migration)) {
                UCP_Options::$migration();
            }
        }
    }

    /**
     * Bootstrap services that should be available before feature modules are instantiated.
     *
     * @param bool $backend_context Whether this is an admin/cron/CLI request.
     * @return void
     */
    private static function bootstrap_core_services($backend_context) {
        if ($backend_context && class_exists('UCP_Log_Package')) {
            UCP_Log_Package::bootstrap();
        }

        if (UCP_Options::get('enable_diagnostics')) {
            UCP_Diagnostics::bootstrap();
        }
        if (UCP_Options::get('enable_logs')) {
            UCP_Logger::bootstrap();
        }
        if ($backend_context || UCP_Options::get('enable_health_checks')) {
            UCP_Health::bootstrap();
        }
        UCP_Optimization_Intelligence::bootstrap();
        UCP_REST_Admin_Controller::init();
    }

    /**
     * Bootstrap backend-only tooling and maintenance services.
     *
     * @param bool $backend_context Whether this is an admin/cron/CLI request.
     * @return void
     */
    private static function bootstrap_backend_services($backend_context) {
        if (!$backend_context) {
            return;
        }

        UCP_Integrations::bootstrap();
        UCP_Runtime_Tests::bootstrap();
        UCP_Maintenance::bootstrap();
        UCP_Site_Health::bootstrap();
        if (class_exists('UCP_Quality_Suite')) {
            UCP_Quality_Suite::bootstrap();
        }
    }

    /**
     * Instantiate runtime modules using the same conditions as the legacy bootstrap method.
     *
     * @param bool $backend_context Whether this is an admin/cron/CLI request.
     * @return void
     */
    private static function bootstrap_runtime_modules($backend_context) {
        new UCP_Compat();

        if ($backend_context || UCP_Options::get('enable_cache')) {
            new UCP_Cache();
        }
        if ($backend_context || UCP_Options::get('enable_preload')) {
            new UCP_Preload();
        }
        if ($backend_context || self::any_option_enabled(array(
            'enable_css_minify', 'enable_js_minify', 'enable_css_combine', 'enable_js_combine',
            'disabled_style_handles', 'disabled_script_handles',
            'conditional_style_unloads', 'conditional_script_unloads',
            'advanced_asset_rules', 'enable_asset_manager_snapshot',
        ))) {
            new UCP_Assets();
        }
        if ($backend_context || UCP_Options::get('enable_used_css') || UCP_Options::get('enable_critical_css')) {
            new UCP_CSS();
        }
        if ($backend_context || self::any_option_enabled(array(
            'enable_remove_emojis', 'enable_disable_embeds', 'enable_delay_js',
            'remove_html_comments', 'enable_html_minify',
            'enable_lazy_images', 'enable_lazy_iframes', 'enable_lazy_youtube_preview',
            'defer_all_js', 'enable_defer_js_fallback', 'enable_native_script_strategy',
            'enable_heartbeat_control', 'enable_cdn', 'enable_prefetch_links',
            'enable_speculative_loading', 'preload_fonts', 'dns_prefetch_domains',
            'enable_auto_resource_hints', 'enable_auto_font_preloads',
            'enable_font_display_swap', 'enable_remove_query_strings',
            'enable_add_image_dimensions', 'preload_critical_images',
            'enable_disable_google_fonts', 'preconnect_domains', 'enable_lazy_render',
            'enable_disable_dashicons', 'enable_disable_jquery_migrate',
            'enable_move_module_scripts_footer', 'enable_self_host_third_party_assets',
            'enable_cls_iframe_reservation', 'enable_worker_lazyload', 'enable_expand_missing_srcset',
        ))) {
            new UCP_Optimizer();
        }
        if ($backend_context || UCP_Options::get('enable_db_cleanup')) {
            new UCP_DB_Cleanup();
        }
        if ($backend_context || UCP_Options::get('enable_cloud')) {
            new UCP_Cloud();
        }
        if ($backend_context || self::any_option_enabled(array(
            'enable_edge_cache_headers', 'enable_cloudflare_apo_mode', 'enable_early_hints_links',
        ))) {
            new UCP_Edge();
        }
        if ($backend_context || UCP_Options::get('enable_edge_html_cache')) {
            new UCP_Edge_HTML();
        }
        if (class_exists('UCP_Script_Manager')) {
            UCP_Script_Manager::bootstrap();
        }
        if ($backend_context || self::any_option_enabled(array(
            'enable_delay_js', 'enable_used_css', 'enable_critical_css',
        ))) {
            new UCP_Modules();
        }
        if ($backend_context || self::any_option_enabled(array(
            'enable_cloud', 'enable_cloudflare_apo_mode',
            'enable_css_queue', 'enable_preload_queue', 'enable_health_checks',
        ))) {
            new UCP_Jobs();
        }

        if ($backend_context || self::any_option_enabled(array(
            'enable_image_optimization', 'enable_webp_generation', 'enable_avif_generation',
        ))) {
            new UCP_Image_Optimizer();
        }
        if ($backend_context || UCP_Options::get('enable_object_cache_support') || UCP_Options::get('enable_apcu_object_cache') || UCP_Options::get('enable_redis_object_cache')) {
            new UCP_Object_Cache();
        }
        if ($backend_context || UCP_Options::get('enable_fragment_cache')) {
            new UCP_Fragment_Cache();
        }
        if (UCP_Options::get('enable_rest_cache')) {
            new UCP_REST_Cache();
        }
        if ($backend_context || UCP_Options::get('enable_cwv_monitoring')) {
            new UCP_CWV();
        }
        if ($backend_context || UCP_Options::get('enable_local_google_fonts')) {
            new UCP_Fonts();
        }
    }

    /**
     * Bootstrap admin UI classes only on admin requests.
     *
     * @return void
     */
    private static function bootstrap_admin_ui() {
        if (!is_admin()) {
            return;
        }

        new UCP_Admin();
        new UCP_Admin_Object_Cache_Page();
    }

    public function bootstrap() {
        $backend_context = self::is_backend_context();

        UCP_Options::maybe_init_defaults();
        self::run_option_migrations();
        UCP_Installer::maybe_upgrade();
        UCP_Helpers::ensure_cache_dirs();

        self::bootstrap_core_services($backend_context);
        self::bootstrap_backend_services($backend_context);
        self::bootstrap_runtime_modules($backend_context);
        self::bootstrap_admin_ui();
    }
}
