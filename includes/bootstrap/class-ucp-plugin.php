<?php
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

        add_action('init', array($this, 'load_textdomain'), 0);
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

    public function load_textdomain() {
        load_plugin_textdomain('ultracache-pro', false, dirname(UCP_BASENAME) . '/languages');
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

    public function bootstrap() {
        $backend_context = is_admin() || (function_exists('wp_doing_cron') && wp_doing_cron()) || (defined('WP_CLI') && WP_CLI);

        UCP_Options::maybe_init_defaults();
        UCP_Options::maybe_apply_runtime_write_and_log_migration();
        UCP_Options::maybe_apply_preload_safety_migration();
        if (method_exists('UCP_Options', 'maybe_apply_queue_repair_migration')) {
            UCP_Options::maybe_apply_queue_repair_migration();
        }
        if (method_exists('UCP_Options', 'maybe_upgrade_pagespeed_auto_v2')) {
            UCP_Options::maybe_upgrade_pagespeed_auto_v2();
        }
        if (method_exists('UCP_Options', 'maybe_upgrade_pagespeed_auto_v3')) {
            UCP_Options::maybe_upgrade_pagespeed_auto_v3();
        }
        if (method_exists('UCP_Options', 'maybe_upgrade_pagespeed_auto_v4')) {
            UCP_Options::maybe_upgrade_pagespeed_auto_v4();
        }
        if (method_exists('UCP_Options', 'maybe_upgrade_pagespeed_auto_v5')) {
            UCP_Options::maybe_upgrade_pagespeed_auto_v5();
        }
        UCP_Installer::maybe_upgrade();
        UCP_Helpers::ensure_cache_dirs();
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
        if (class_exists('UCP_Optimization_Intelligence')) {
            UCP_Optimization_Intelligence::bootstrap();
        }
        UCP_REST_Admin_Controller::init();

        if ($backend_context) {
            UCP_Integrations::bootstrap();
            UCP_Runtime_Tests::bootstrap();
            UCP_Maintenance::bootstrap();
            UCP_Site_Health::bootstrap();
            if (class_exists('UCP_Quality_Suite')) {
                UCP_Quality_Suite::bootstrap();
            }
        }

        new UCP_Compat();

        if ($backend_context || UCP_Options::get('enable_cache')) {
            new UCP_Cache();
        }
        if ($backend_context || UCP_Options::get('enable_preload')) {
            new UCP_Preload();
        }
        if ($backend_context || UCP_Options::get('enable_css_minify') || UCP_Options::get('enable_js_minify') || UCP_Options::get('enable_css_combine') || UCP_Options::get('enable_js_combine') || UCP_Options::get('disabled_style_handles') || UCP_Options::get('disabled_script_handles') || UCP_Options::get('conditional_style_unloads') || UCP_Options::get('conditional_script_unloads') || UCP_Options::get('advanced_asset_rules') || UCP_Options::get('enable_asset_manager_snapshot')) {
            new UCP_Assets();
        }
        if ($backend_context || UCP_Options::get('enable_used_css') || UCP_Options::get('enable_critical_css')) {
            new UCP_CSS();
        }
        if ($backend_context || UCP_Options::get('enable_remove_emojis') || UCP_Options::get('enable_disable_embeds') || UCP_Options::get('enable_delay_js') || UCP_Options::get('remove_html_comments') || UCP_Options::get('enable_html_minify') || UCP_Options::get('enable_lazy_images') || UCP_Options::get('enable_lazy_iframes') || UCP_Options::get('enable_lazy_youtube_preview') || UCP_Options::get('defer_all_js') || UCP_Options::get('enable_defer_js_fallback') || UCP_Options::get('enable_native_script_strategy') || UCP_Options::get('enable_heartbeat_control') || UCP_Options::get('enable_cdn') || UCP_Options::get('enable_prefetch_links') || UCP_Options::get('enable_speculative_loading') || UCP_Options::get('preload_fonts') || UCP_Options::get('dns_prefetch_domains') || UCP_Options::get('enable_font_display_swap') || UCP_Options::get('enable_remove_query_strings') || UCP_Options::get('enable_add_image_dimensions') || UCP_Options::get('preload_critical_images') || UCP_Options::get('enable_disable_google_fonts') || UCP_Options::get('preconnect_domains') || UCP_Options::get('enable_lazy_render') || UCP_Options::get('enable_disable_dashicons') || UCP_Options::get('enable_disable_jquery_migrate') || UCP_Options::get('enable_move_module_scripts_footer') || UCP_Options::get('enable_self_host_third_party_assets')) {
            new UCP_Optimizer();
        }
        if ($backend_context || UCP_Options::get('enable_db_cleanup')) {
            new UCP_DB_Cleanup();
        }
        if ($backend_context || UCP_Options::get('enable_cloud')) {
            new UCP_Cloud();
        }
        if ($backend_context || UCP_Options::get('enable_edge_cache_headers') || UCP_Options::get('enable_cloudflare_apo_mode') || UCP_Options::get('enable_early_hints_links')) {
            new UCP_Edge();
        }
        if ($backend_context || UCP_Options::get('enable_delay_js') || UCP_Options::get('enable_used_css') || UCP_Options::get('enable_critical_css')) {
            new UCP_Modules();
        }
        if ($backend_context || UCP_Options::get('enable_cloud') || UCP_Options::get('enable_cloudflare_apo_mode') || UCP_Options::get('enable_css_queue') || UCP_Options::get('enable_preload_queue') || UCP_Options::get('enable_health_checks')) {
            new UCP_Jobs();
        }

        if ($backend_context || UCP_Options::get('enable_image_optimization') || UCP_Options::get('enable_webp_generation') || UCP_Options::get('enable_avif_generation')) {
            new UCP_Image_Optimizer();
        }
        if ($backend_context || UCP_Options::get('enable_object_cache_support')) {
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

        if (is_admin()) {
            new UCP_Admin();
        }
    }
}
