<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Runtime_Tests {
    const OPTION_KEY = 'ucp_runtime_tests_snapshot';

    public static function bootstrap() {
        add_action('admin_post_ucp_run_runtime_tests', array(__CLASS__, 'handle_manual_run'));
    }

    public static function latest() {
        return get_option(self::OPTION_KEY, array());
    }

    public static function run_all() {
        $results = array(
            'generated_at' => current_time('mysql', true),
            'wordpress' => self::test_wordpress_runtime(),
            'woocommerce' => self::test_woocommerce_runtime(),
            'elementor' => self::test_elementor_runtime(),
            'cloudflare' => self::test_cloudflare_runtime(),
            'html' => self::test_html_runtime(),
        );
        update_option(self::OPTION_KEY, $results, false);
        UCP_Logger::log('info', 'runtime', 'runtime_tests_ran', 'Runtime compatibility tests executed.', array(
            'woocommerce' => $results['woocommerce']['status'],
            'elementor' => $results['elementor']['status'],
            'cloudflare' => $results['cloudflare']['status'],
            'html' => $results['html']['status'],
        ));
        return $results;
    }

    public static function handle_manual_run() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_run_runtime_tests');
        self::run_all();
        wp_safe_redirect(UCP_Admin_Router::url('tools', array('runtime' => 1)));
        exit;
    }

    protected static function test_wordpress_runtime() {
        global $wp_rewrite;
        $issues = array();
        if (!wp_is_writable(WP_CONTENT_DIR)) {
            $issues[] = __('wp-content is niet schrijfbaar.', 'ultracache-pro');
        }
        if (!file_exists(UCP_CACHE_DIR)) {
            $issues[] = __('De UltraCache cachemap ontbreekt.', 'ultracache-pro');
        }
        if (!($wp_rewrite instanceof WP_Rewrite)) {
            $issues[] = __('Het rewrite-systeem is niet gestart.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, array(
            'cache_dir' => UCP_CACHE_DIR,
            'pretty_permalinks' => (bool) get_option('permalink_structure'),
            'object_cache' => UCP_Helpers::has_persistent_object_cache(),
        ));
    }

    protected static function test_woocommerce_runtime() {
        if (!class_exists('WooCommerce')) {
            return self::format_result('info', array(__('WooCommerce staat niet aan op deze site.', 'ultracache-pro')), array('detected' => false));
        }
        $issues = array();
        $details = array(
            'detected' => true,
            'cart_page' => function_exists('wc_get_page_id') ? absint(wc_get_page_id('cart')) : 0,
            'checkout_page' => function_exists('wc_get_page_id') ? absint(wc_get_page_id('checkout')) : 0,
            'myaccount_page' => function_exists('wc_get_page_id') ? absint(wc_get_page_id('myaccount')) : 0,
            'smart_mode' => (bool) UCP_Options::get('enable_woocommerce_rules'),
        );
        foreach (array('cart_page', 'checkout_page', 'myaccount_page') as $key) {
            if (empty($details[$key])) {
                /* translators: %s: WooCommerce page key, for example cart_page or checkout_page. */
                $issues[] = sprintf(__('Deze WooCommerce pagina ontbreekt: %s.', 'ultracache-pro'), $key);
            }
        }
        if (false === strpos((string) UCP_Options::get('exclude_urls', ''), 'cart')) {
            $issues[] = __("De winkelwagen staat niet in uitgesloten URL's.", 'ultracache-pro');
        }
        if (false === strpos((string) UCP_Options::get('delay_js_exclusions', ''), 'wc-cart-fragments')) {
            $issues[] = __('wc-cart-fragments staat niet in Uitgesteld JS.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, $details);
    }

    protected static function test_elementor_runtime() {
        if (!defined('ELEMENTOR_VERSION')) {
            return self::format_result('info', array(__('Elementor staat niet aan op deze site.', 'ultracache-pro')), array('detected' => false));
        }
        $issues = array();
        $details = array(
            'detected' => true,
            'version' => ELEMENTOR_VERSION,
            'css_combine' => (bool) UCP_Options::get('enable_css_combine'),
            'js_combine' => (bool) UCP_Options::get('enable_js_combine'),
            'delay_exclusions' => UCP_Options::get('delay_js_exclusions', ''),
        );
        if (!empty($details['css_combine'])) {
            $issues[] = __('CSS samenvoegen staat aan. Bij builders werkt uit vaak beter.', 'ultracache-pro');
        }
        if (false === strpos((string) $details['delay_exclusions'], 'elementor')) {
            $issues[] = __('Uitgesteld JS noemt Elementor nu niet.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, $details);
    }


    protected static function test_html_runtime() {
        $issues = array();
        $details = array(
            'html_minify' => (bool) UCP_Options::get('enable_html_minify'),
            'html_test_mode' => (bool) UCP_Options::get('enable_html_test_mode'),
            'remove_comments' => (bool) UCP_Options::get('remove_html_comments'),
        );
        $detected = class_exists('UCP_Integrations') ? UCP_Integrations::detected() : array();
        if (!empty($details['html_minify']) && empty($details['html_test_mode']) && (!empty($detected['builder']) || !empty($detected['commerce']) || !empty($detected['seo']) || !empty($detected['consent']) || !empty($detected['forms']) || !empty($detected['analytics']) || !empty($detected['acf']) || !empty($detected['multilingual']))) {
            $issues[] = __('HTML kleiner maken staat live aan op een site met gevoelige plugins. Test dit eerst rustig op staging of handmatig.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, $details);
    }

    protected static function test_cloudflare_runtime() {
        $detected_plugin = defined('CLOUDFLARE_VERSION') || class_exists('CF\WordPress\Hooks');
        $headers_present = UCP_Edge::cloudflare_headers_present();
        $api_configured = UCP_Edge::cloudflare_api_configured();
        $issues = array();
        if (!$detected_plugin && !$headers_present && !$api_configured) {
            return self::format_result('info', array(__('Cloudflare is niet gevonden in plugin, headers of API.', 'ultracache-pro')), array('detected' => false));
        }
        if (!$api_configured) {
            $issues[] = __('Cloudflare Zone ID en API token zijn nog niet compleet.', 'ultracache-pro');
        }
        if (!UCP_Options::get('enable_early_hints_links')) {
            $issues[] = __('Preload Link headers staat uit.', 'ultracache-pro');
        }
        if (!UCP_Options::get('enable_edge_cache_headers')) {
            $issues[] = __('Edge cache headers staan uit.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, array(
            'detected' => true,
            'plugin' => $detected_plugin,
            'headers' => $headers_present,
            'api_configured' => $api_configured,
            'apo_mode' => (bool) UCP_Options::get('enable_cloudflare_apo_mode'),
        ));
    }

    protected static function format_result($status, $issues, $details) {
        return array(
            'status' => $status,
            'issues' => array_values($issues),
            'details' => $details,
        );
    }
}
