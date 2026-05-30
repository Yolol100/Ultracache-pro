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
            'quality_summary' => self::quality_summary($settings),
            'runtime_tests' => class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array(),
        );

        return apply_filters('ucp_support_report', $report);
    }


    public static function quality_summary($settings = null) {
        $settings = is_array($settings) ? $settings : (class_exists('UCP_Options') ? UCP_Options::get_all() : array());
        $runtime = class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array();
        $conflicts = class_exists('UCP_Compat') ? UCP_Compat::detected_conflicts() : array();
        $features = array(
            'page_cache' => !empty($settings['enable_cache']),
            'preload_queue' => !empty($settings['enable_preload']) && !empty($settings['enable_preload_queue']),
            'woocommerce_safety' => !empty($settings['enable_woocommerce_rules']) || !empty($settings['woocommerce_safety_mode']),
            'asset_manager_test_mode' => !empty($settings['enable_asset_test_mode']) || !empty($settings['testing_mode']),
            'asset_manager_snapshot' => !empty($settings['enable_asset_manager_snapshot']),
            'cloudflare_edge' => !empty($settings['enable_cloudflare_apo_mode']) || !empty($settings['cloudflare_zone_id']),
            'webp_avif_pipeline' => !empty($settings['enable_webp_generation']) || !empty($settings['enable_avif_generation']),
            'cwv_monitoring' => !empty($settings['enable_cwv_monitoring']),
            'runtime_tests' => !empty($runtime),
            'wp_cli' => defined('WP_CLI') && WP_CLI,
        );

        $gates = array();
        if (empty($features['page_cache'])) {
            $gates[] = __('Page cache staat uit; dit beperkt de basisimpact ten opzichte van cache-first concurrenten.', 'ultracache-pro');
        }
        if (empty($features['woocommerce_safety']) && class_exists('WooCommerce')) {
            $gates[] = __('WooCommerce gevonden maar veiligheidsregels staan niet duidelijk aan.', 'ultracache-pro');
        }
        if (!empty($settings['enable_delay_js']) && empty($settings['delay_js_safe_mode'])) {
            $gates[] = __('Delay JS staat aan zonder veilige modus; test formulieren, checkout, consent en menu’s handmatig.', 'ultracache-pro');
        }
        if (!empty($settings['enable_css_combine']) || !empty($settings['enable_js_combine'])) {
            $gates[] = __('CSS/JS combineren staat aan; moderne HTTP/2-sites en builders vragen extra visuele regressietests.', 'ultracache-pro');
        }
        if (!empty($conflicts)) {
            $gates[] = __('Er is overlap met andere cache/optimalisatie lagen. Kies per feature één verantwoordelijke plugin.', 'ultracache-pro');
        }
        if (empty($runtime)) {
            $gates[] = __('Runtime-tests zijn nog niet uitgevoerd of niet opgeslagen.', 'ultracache-pro');
        }

        $recommendations = array();
        if (empty($features['asset_manager_snapshot'])) {
            $recommendations[] = array(
                'type' => 'quick_win',
                'title' => __('Zet Asset Manager snapshot aan op staging.', 'ultracache-pro'),
                'impact' => __('Geeft een Perfmatters-achtige inventaris van styles/scripts per URL zonder direct bezoekers te raken.', 'ultracache-pro'),
            );
        }
        if (empty($features['cwv_monitoring'])) {
            $recommendations[] = array(
                'type' => 'product_improvement',
                'title' => __('Activeer lokale CWV-monitoring voor before/after bewijs.', 'ultracache-pro'),
                'impact' => __('Helpt verkoopclaims onderbouwen met site-eigen LCP, INP, CLS, FCP en TTFB trends.', 'ultracache-pro'),
            );
        }
        if (empty($features['cloudflare_edge'])) {
            $recommendations[] = array(
                'type' => 'premium_differentiator',
                'title' => __('Configureer Cloudflare/edge status alleen wanneer de site dit gebruikt.', 'ultracache-pro'),
                'impact' => __('Maakt edge-cache purges en statuscontrole concreter zonder LiteSpeed-afhankelijkheid.', 'ultracache-pro'),
            );
        }

        $score = 100;
        $score -= empty($features['page_cache']) ? 18 : 0;
        $score -= empty($features['preload_queue']) ? 8 : 0;
        $score -= empty($features['woocommerce_safety']) && class_exists('WooCommerce') ? 12 : 0;
        $score -= empty($features['asset_manager_test_mode']) ? 6 : 0;
        $score -= empty($features['cwv_monitoring']) ? 6 : 0;
        $score -= empty($runtime) ? 10 : 0;
        $score -= min(15, count((array) $conflicts) * 5);

        return array(
            'generated_at' => gmdate('c'),
            'score_estimate' => max(0, min(100, $score)),
            'features' => $features,
            'runtime_tests_generated_at' => isset($runtime['generated_at']) ? (string) $runtime['generated_at'] : '',
            'conflict_count' => count((array) $conflicts),
            'gates' => array_values($gates),
            'recommendations' => array_slice($recommendations, 0, 6),
            'positioning' => __('Veilige Core Web Vitals autopilot voor agencies, builders en WooCommerce-sites.', 'ultracache-pro'),
        );
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
