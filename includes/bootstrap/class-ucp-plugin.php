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

        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'bootstrap'));
        add_action('wpmu_new_blog', array($this, 'activate_new_blog'), 10, 1);
        add_action('update_option_' . UCP_Options::OPTION_KEY, array('UCP_Options', 'handle_option_updated'), 10, 2);
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
        UCP_Installer::maybe_upgrade();
        UCP_Helpers::ensure_cache_dirs();
        if ($backend_context || UCP_Options::get('enable_health_checks')) {
            UCP_Health::bootstrap();
        }
        if ($backend_context) {
            UCP_Integrations::bootstrap();
            UCP_Runtime_Tests::bootstrap();
            UCP_Maintenance::bootstrap();
            UCP_Site_Health::bootstrap();
        }

        new UCP_Compat();

        if ($backend_context || UCP_Options::get('enable_cache')) {
            new UCP_Cache();
        }
        if ($backend_context || UCP_Options::get('enable_preload')) {
            new UCP_Preload();
        }
        if ($backend_context || UCP_Options::get('enable_rest_cache')) {
            new UCP_REST_Cache();
        }
        if ($backend_context || UCP_Options::get('enable_crawler')) {
            new UCP_Crawler();
        }
        if ($backend_context || UCP_Options::get('enable_css_combine') || UCP_Options::get('enable_js_combine') || UCP_Options::get('disabled_style_handles') || UCP_Options::get('disabled_script_handles')) {
            new UCP_Assets();
        }
        if ($backend_context || UCP_Options::get('enable_used_css') || UCP_Options::get('enable_critical_css')) {
            new UCP_CSS();
        }
        if ($backend_context || UCP_Options::get('enable_remove_emojis') || UCP_Options::get('enable_disable_embeds') || UCP_Options::get('enable_delay_js') || UCP_Options::get('remove_html_comments') || UCP_Options::get('enable_html_minify') || UCP_Options::get('enable_lazy_images') || UCP_Options::get('enable_lazy_iframes') || UCP_Options::get('enable_lazy_background_images') || UCP_Options::get('enable_defer_js_fallback') || UCP_Options::get('enable_native_script_strategy') || UCP_Options::get('enable_heartbeat_control') || UCP_Options::get('enable_prefetch_links') || UCP_Options::get('enable_speculative_loading') || UCP_Options::get('preload_fonts') || UCP_Options::get('dns_prefetch_domains')) {
            new UCP_Optimizer();
        }
        if ($backend_context) {
            new UCP_DB_Cleanup();
        }
        if ($backend_context || UCP_Options::get('enable_delay_js') || UCP_Options::get('enable_used_css') || UCP_Options::get('enable_critical_css')) {
            new UCP_Modules();
        }
        if ($backend_context || UCP_Options::get('enable_css_queue') || UCP_Options::get('enable_preload_queue') || UCP_Options::get('enable_health_checks')) {
            new UCP_Jobs();
        }

        if ($backend_context || UCP_Options::get('enable_image_optimization')) {
            new UCP_Image_Optimizer();
        }
        if ($backend_context || UCP_Options::get('enable_local_google_fonts')) {
            new UCP_Fonts();
        }

        if (is_admin()) {
            new UCP_Post_Meta();
            new UCP_Admin();
        }
    }
}
