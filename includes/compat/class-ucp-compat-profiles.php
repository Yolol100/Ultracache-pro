<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Versioned, local compatibility profiles for common dynamic WordPress stacks.
 */
final class UCP_Compat_Profiles {
    const SCHEMA_VERSION = '1.0.0';

    private $active = array();

    public function __construct() {
        if ('off' === UCP_Options::get('compat_profile_mode', 'auto')) {
            return;
        }
        $this->active = self::detected_profiles();
        add_filter('ucp_excluded_url_fragments', array($this, 'excluded_urls'));
        add_filter('ucp_excluded_cookie_fragments', array($this, 'excluded_cookies'));
        add_filter('ucp_delay_js_exclusions', array($this, 'delay_js_exclusions'));
        add_filter('ucp_used_css_safelist', array($this, 'used_css_safelist'));
        add_filter('ucp_cache_include_query_params', array($this, 'cache_query_params'));
    }

    public static function definitions() {
        return apply_filters('ucp_compat_profile_definitions', array(
            'woocommerce' => array(
                'label' => 'WooCommerce', 'profile_version' => '1.0.0', 'reviewed' => '2026-07-19',
                'plugins' => array('woocommerce/woocommerce.php'), 'classes' => array('WooCommerce'),
                'buckets' => array(
                    'excluded_urls' => array('cart', 'checkout', 'my-account', 'order-pay', 'order-received', 'wc-ajax', 'wc-api', 'add-to-cart'),
                    'excluded_cookies' => array('woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_', 'woocommerce_checkout_', 'woocommerce_pay_'),
                    'delay_js' => array('woocommerce', 'wc-cart-fragments', 'wc-checkout', 'wc-add-to-cart', 'stripe', 'paypal', 'mollie', 'klarna', 'adyen'),
                    'used_css' => array('.woocommerce', '.woocommerce-page', '.wc-block-', '.added_to_cart', '.widget_shopping_cart'),
                ),
            ),
            'elementor' => array(
                'label' => 'Elementor', 'profile_version' => '1.0.0', 'reviewed' => '2026-07-19',
                'plugins' => array('elementor/elementor.php', 'elementor-pro/elementor-pro.php'), 'classes' => array('Elementor\\Plugin'),
                'buckets' => array(
                    'excluded_urls' => array('elementor-preview=', 'elementor_library'),
                    'delay_js' => array('elementor-frontend', 'elementor-pro-frontend', 'webpack.runtime', 'swiper'),
                    'used_css' => array('.elementor-active', '.elementor-open', '.elementor-popup-modal', '.elementor-sticky--effects', '.e-active'),
                ),
            ),
            'forms' => array(
                'label' => 'Formulieren', 'profile_version' => '1.0.0', 'reviewed' => '2026-07-19',
                'plugins' => array('contact-form-7/wp-contact-form-7.php', 'gravityforms/gravityforms.php', 'wpforms-lite/wpforms.php', 'wpforms/wpforms.php', 'fluentform/fluentform.php', 'formidable/formidable.php'),
                'classes' => array('GFForms', 'WPForms', 'FluentForm'),
                'buckets' => array(
                    'delay_js' => array('contact-form-7', 'wpcf7', 'gravityforms', 'gform', 'wpforms', 'fluentform', 'formidable', 'turnstile', 'hcaptcha', 'recaptcha'),
                    'used_css' => array('.gform_', '.wpforms-', '.wpcf7', '.nf-form', '.frm_'),
                ),
            ),
            'multilingual' => array(
                'label' => 'Meertaligheid', 'profile_version' => '1.0.0', 'reviewed' => '2026-07-19',
                'plugins' => array('sitepress-multilingual-cms/sitepress.php', 'polylang/polylang.php', 'polylang-pro/polylang.php', 'translatepress-multilingual/index.php'),
                'classes' => array('SitePress', 'Polylang', 'TRP_Translate_Press'),
                'buckets' => array(
                    'excluded_cookies' => array('pll_language', '_icl_current_language', 'wp-wpml_current_language', 'trp_language', 'wp_lang'),
                    'query_params' => array('lang'),
                    'delay_js' => array('wpml', 'polylang', 'translatepress'),
                ),
            ),
            'consent' => array(
                'label' => __('Consent en cookies', 'ultracache-pro'), 'profile_version' => '1.0.0', 'reviewed' => '2026-07-19',
                'plugins' => array('complianz-gdpr/complianz-gdpr.php', 'cookie-law-info/cookie-law-info.php', 'cookieyes/cookie-law-info.php', 'borlabs-cookie/borlabs-cookie.php'),
                'classes' => array('COMPLIANZ', 'Cookie_Law_Info'),
                'buckets' => array(
                    'excluded_cookies' => array('cmplz_', 'complianz_', 'cookieyes', 'cky-', 'borlabs'),
                    'delay_js' => array('complianz', 'cookiebot', 'cookieyes', 'borlabs', 'consent'),
                    'used_css' => array('.cmplz-', '.cookie-notice', '.cky-', '.borlabs-'),
                ),
            ),
            'reverse_proxy' => array(
                'label' => __('Reverse proxy/CDN', 'ultracache-pro'), 'profile_version' => '1.0.0', 'reviewed' => '2026-07-19',
                'headers' => array('HTTP_CF_RAY', 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_VARNISH'),
                'buckets' => array(),
            ),
        ));
    }

    public static function detected_profiles() {
        $plugins = self::active_plugins();
        $profiles = array();
        foreach (self::definitions() as $key => $definition) {
            $matched = array();
            foreach ((array) ($definition['plugins'] ?? array()) as $plugin) {
                if (in_array(strtolower($plugin), $plugins, true)) {
                    $matched[] = $plugin;
                }
            }
            foreach ((array) ($definition['classes'] ?? array()) as $class) {
                if (class_exists($class)) {
                    $matched[] = $class;
                }
            }
            foreach ((array) ($definition['headers'] ?? array()) as $header) {
                if (!empty($_SERVER[$header])) {
                    $matched[] = $header;
                }
            }
            if (empty($matched)) {
                continue;
            }
            $profiles[$key] = array(
                'key' => sanitize_key($key),
                'label' => sanitize_text_field((string) $definition['label']),
                'profile_version' => sanitize_text_field((string) $definition['profile_version']),
                'reviewed' => sanitize_text_field((string) $definition['reviewed']),
                'matched_by' => array_values(array_unique(array_map('sanitize_text_field', $matched))),
                'buckets' => isset($definition['buckets']) && is_array($definition['buckets']) ? $definition['buckets'] : array(),
            );
        }
        return $profiles;
    }

    public static function summary() {
        $profiles = self::detected_profiles();
        $output = array();
        foreach ($profiles as $profile) {
            $counts = array();
            foreach ($profile['buckets'] as $bucket => $values) {
                $counts[sanitize_key($bucket)] = count((array) $values);
            }
            $output[] = array(
                'key' => $profile['key'], 'label' => $profile['label'], 'profile_version' => $profile['profile_version'],
                'reviewed' => $profile['reviewed'], 'matched_by' => $profile['matched_by'], 'rule_counts' => $counts,
            );
        }
        return array('mode' => UCP_Options::get('compat_profile_mode', 'auto'), 'schema_version' => self::SCHEMA_VERSION, 'profiles' => $output);
    }

    private static function active_plugins() {
        $plugins = array_map('strtolower', (array) get_option('active_plugins', array()));
        if (is_multisite()) {
            $plugins = array_merge($plugins, array_map('strtolower', array_keys((array) get_site_option('active_sitewide_plugins', array()))));
        }
        return array_values(array_unique($plugins));
    }

    private function bucket($name) {
        $items = array();
        foreach ($this->active as $profile) {
            if (!empty($profile['buckets'][$name])) {
                $items = array_merge($items, (array) $profile['buckets'][$name]);
            }
        }
        return array_values(array_unique(array_filter(array_map('trim', $items), 'strlen')));
    }

    private function merge($current, $bucket) {
        return array_values(array_unique(array_merge((array) $current, $this->bucket($bucket))));
    }

    public function excluded_urls($items) { return $this->merge($items, 'excluded_urls'); }
    public function excluded_cookies($items) { return $this->merge($items, 'excluded_cookies'); }
    public function delay_js_exclusions($items) { return $this->merge($items, 'delay_js'); }
    public function used_css_safelist($items) { return $this->merge($items, 'used_css'); }
    public function cache_query_params($items) { return $this->merge($items, 'query_params'); }
}
