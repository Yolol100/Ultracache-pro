<?php
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
            'html' => self::test_html_runtime(),
            'cloudflare' => self::test_cloudflare_runtime(),
        );
        update_option(self::OPTION_KEY, $results, false);
        ucp_noop('info', 'runtime', 'runtime_tests_ran', 'Runtime compatibility tests executed.', array(
            'woocommerce' => $results['woocommerce']['status'],
            'elementor' => $results['elementor']['status'],
            'html' => $results['html']['status'],
        ));
        return $results;
    }

    public static function handle_manual_run() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_run_runtime_tests');
        self::run_all();
        $redirect = wp_get_referer();
        if (!$redirect || false === strpos($redirect, 'page=ultracache-pro')) {
            $redirect = admin_url('admin.php?page=ultracache-pro&tab=tools');
        }
        wp_safe_redirect(add_query_arg('runtime', '1', $redirect));
        exit;
    }

    protected static function test_wordpress_runtime() {
        global $wp_rewrite;
        $issues = array();
        if (!is_writable(WP_CONTENT_DIR)) {
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
                $issues[] = sprintf(__('Deze WooCommerce pagina ontbreekt: %s.', 'ultracache-pro'), $key);
            }
        }
        $effective_exclusions = class_exists('UCP_Compat') ? UCP_Compat::get_effective_cache_exclusions() : UCP_Helpers::normalize_multiline(UCP_Options::get('exclude_urls', ''));
        $required_exclusions = array('cart', 'checkout', 'my-account', 'order-pay', 'add-payment-method', 'order-received', 'wc-api', 'add-to-cart=');
        $missing_exclusions = array_values(array_diff($required_exclusions, $effective_exclusions));
        if (!empty($missing_exclusions)) {
            $issues[] = sprintf(__('WooCommerce cache-uitsluitingen ontbreken: %s.', 'ultracache-pro'), implode(', ', $missing_exclusions));
        }
        $details['effective_exclusions'] = $effective_exclusions;
        if (UCP_Options::get('enable_delay_js') && false === strpos((string) UCP_Options::get('delay_js_exclusions', ''), 'wc-cart-fragments')) {
            $issues[] = __('Delay JS staat aan, maar wc-cart-fragments staat niet in de uitzonderingen.', 'ultracache-pro');
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
        if (UCP_Options::get('enable_delay_js') && false === strpos((string) $details['delay_exclusions'], 'elementor')) {
            $issues[] = __('Delay JS staat aan, maar Elementor staat niet in de uitzonderingen.', 'ultracache-pro');
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
        $detected_plugin = defined('CLOUDFLARE_VERSION') || class_exists('CF\\WordPress\\Hooks');
        $status = $detected_plugin ? 'info' : 'pass';
        $issues = array();
        $details = array('detected' => (bool) $detected_plugin);
        return self::format_result($status, $issues, $details);
    }

    protected static function format_result($status, $issues = array(), $details = array()) {
        return array(
            'status' => $status,
            'issues' => array_values(array_filter((array) $issues)),
            'details' => is_array($details) ? $details : array(),
        );
    }
}
