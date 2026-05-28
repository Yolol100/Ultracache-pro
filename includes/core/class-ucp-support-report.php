<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Support_Report {
    public static function generate() {
        $settings = class_exists('UCP_Options') ? UCP_Options::get_all() : array();
        $theme = function_exists('wp_get_theme') ? wp_get_theme() : null;
        $report = array(
            'generated_at' => gmdate('c'),
            'site' => array(
                'home_url' => function_exists('home_url') ? home_url('/') : '',
                'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
                'multisite' => function_exists('is_multisite') ? (bool) is_multisite() : false,
                'php_version' => PHP_VERSION,
                'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '',
            ),
            'theme' => array(
                'name' => $theme ? $theme->get('Name') : '',
                'version' => $theme ? $theme->get('Version') : '',
                'template' => $theme ? $theme->get_template() : '',
            ),
            'plugin' => array(
                'version' => defined('UCP_VERSION') ? UCP_VERSION : '',
                'active_preset' => isset($settings['active_preset']) ? $settings['active_preset'] : '',
                'wp_cache_constant' => defined('WP_CACHE') && WP_CACHE,
                'advanced_cache_owned' => class_exists('UCP_Helpers') && file_exists(WP_CONTENT_DIR . '/advanced-cache.php') ? UCP_Helpers::is_own_advanced_cache(UCP_Helpers::read_file(WP_CONTENT_DIR . '/advanced-cache.php')) : false,
                'object_cache_dropin' => file_exists(WP_CONTENT_DIR . '/object-cache.php'),
            ),
            'active_plugins' => function_exists('get_option') ? (array) get_option('active_plugins', array()) : array(),
            'conflicts' => UCP_Compat::conflict_report(),
            'settings_summary' => self::settings_summary($settings),
        );

        return apply_filters('ucp_support_report', $report);
    }

    protected static function settings_summary($settings) {
        $keys = array('enable_cache','cache_logged_in','cache_mobile_separately','enable_preload','enable_css_minify','enable_js_minify','enable_css_combine','css_delivery_mode','enable_js_combine','enable_lazy_images','enable_lazy_iframes','lazyload_exclude_leading_images','enable_add_image_dimensions','preload_critical_images','enable_delay_js','delay_js_mode','delay_js_safe_mode','delay_js_disable_click_delay','enable_defer_js_fallback','defer_all_js','enable_used_css','enable_critical_css','enable_font_display_swap','enable_remove_query_strings','enable_light_preload_requests','preload_content_scope','cache_refresh_interval','enable_lazy_render','lazy_render_selectors','enable_disable_dashicons','enable_disable_jquery_migrate','enable_move_module_scripts_footer','safe_settings_export','show_advanced_options','disable_logged_in_optimizations','accessibility_mode','clean_uninstall','enable_woocommerce_rules','woocommerce_safety_mode','enable_cdn','enable_cloudflare_apo_mode','enable_local_google_fonts','enable_image_optimization','enable_rest_cache','enable_fragment_cache','enable_object_cache_support','enable_diagnostics','enable_logs','allow_wp_config_write','allow_dropin_writes','allow_dropin_takeover','enable_admin_queue_runner');
        $summary = array();
        foreach ($keys as $key) {
            if (array_key_exists($key, $settings)) {
                $summary[$key] = $settings[$key];
            }
        }
        foreach (array('cloud_api_key','cloudflare_api_token','secret_cache_key','css_cache_key','js_cache_key') as $secret) {
            if (!empty($settings[$secret])) {
                $summary[$secret] = self::mask_secret($settings[$secret]);
            }
        }
        return $summary;
    }

    protected static function mask_secret($value) {
        $value = (string) $value;
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', max(4, $len));
        }
        return substr($value, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($value, -4);
    }
}
