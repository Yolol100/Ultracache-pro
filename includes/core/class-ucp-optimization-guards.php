<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central guardrails for feature combinations that premium cache plugins treat
 * as mutually exclusive or advanced-only.
 *
 * The goal is not to remove options, but to let safer execution models win:
 * Delay JS keeps script order, Used/Critical CSS owns CSS delivery, and Combine
 * modes remain opt-in advanced tools.
 */
class UCP_Optimization_Guards {
    /**
     * Register settings guardrails.
     *
     * @return void
     */
    public static function bootstrap() {
        add_filter('pre_update_option_ucp_settings', array(__CLASS__, 'guard_settings'), 20, 3);
    }

    /**
     * Apply safe feature relationship rules before settings are persisted.
     *
     * @param mixed  $value     New option value.
     * @param mixed  $old_value Old option value.
     * @param string $option    Option name.
     * @return mixed
     */
    public static function guard_settings($value, $old_value, $option) {
        if (!is_array($value)) {
            return $value;
        }

        $value = self::sync_testing_mode_aliases($value);
        self::sync_testing_mode_window($value, is_array($old_value) ? $old_value : array());
        $value = self::guard_css_delivery($value);
        $value = self::guard_js_delivery($value);
        if (class_exists('UCP_Options') && method_exists('UCP_Options', 'apply_accessibility_safety')) {
            $value = UCP_Options::apply_accessibility_safety($value);
        }
        $value = self::guard_smart_safe_mode($value);
        $value = self::guard_advanced_combine_modes($value);

        return apply_filters('ucp_optimization_guarded_settings', $value, $old_value, $option);
    }

    /**
     * Keep the new generic Testing Mode and legacy asset test flag aligned.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function sync_testing_mode_aliases(array $settings) {
        $testing = !empty($settings['testing_mode']) || !empty($settings['enable_asset_test_mode']);
        $settings['testing_mode'] = $testing ? 1 : 0;
        $settings['enable_asset_test_mode'] = $testing ? 1 : 0;
        return $settings;
    }

    /**
     * Start one bounded preview window and keep ordinary settings saves from extending it.
     *
     * @param array $settings     New settings.
     * @param array $old_settings Previous settings.
     * @return void
     */
    protected static function sync_testing_mode_window(array $settings, array $old_settings) {
        $active = !empty($settings['testing_mode']) || !empty($settings['enable_asset_test_mode']);
        $was_active = !empty($old_settings['testing_mode']) || !empty($old_settings['enable_asset_test_mode']);

        if (!$active) {
            delete_option('ucp_testing_mode_started_at');
            delete_option('ucp_testing_mode_expires_at');
            return;
        }

        $expires_at = absint(get_option('ucp_testing_mode_expires_at', 0));
        if (!$was_active || 0 === $expires_at || $expires_at <= time()) {
            $started_at = time();
            $ttl = class_exists('UCP_Helpers') ? UCP_Helpers::testing_mode_ttl_seconds() : 4 * HOUR_IN_SECONDS;
            update_option('ucp_testing_mode_started_at', $started_at, false);
            update_option('ucp_testing_mode_expires_at', $started_at + $ttl, false);
            delete_option('ucp_testing_mode_expired_at');
        }
    }

    /**
     * Used CSS and Critical/async CSS are delivery modes, so CSS Combine should
     * not compete with them.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function guard_css_delivery(array $settings) {
        $css_mode = isset($settings['css_delivery_mode']) ? (string) $settings['css_delivery_mode'] : 'none';
        $uses_css_delivery = 'none' !== $css_mode || !empty($settings['enable_used_css']) || !empty($settings['enable_used_css_delivery']) || !empty($settings['enable_critical_css']);

        if ($uses_css_delivery) {
            $settings['enable_css_combine'] = 0;
            $settings['enable_css_queue'] = 1;
        }

        return $settings;
    }

    /**
     * Delay JS and native script strategy both depend on preserving individual
     * scripts and their order, so JS Combine stays off when either is active.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function guard_js_delivery(array $settings) {
        if (!empty($settings['enable_delay_js']) || (!empty($settings['enable_native_script_strategy']) && !empty($settings['defer_all_js']))) {
            $settings['enable_js_combine'] = 0;
        }

        if (!empty($settings['enable_js_combine'])) {
            $settings['enable_js_minify'] = 1;
            $settings['allow_experimental_js_minify'] = 1;
        }

        return $settings;
    }

    /**
     * Preserve safe-mode primitives whenever high-risk optimizations are enabled.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function guard_smart_safe_mode(array $settings) {
        $uses_css_artifacts = !empty($settings['enable_used_css']) || !empty($settings['enable_used_css_delivery']) || !empty($settings['enable_critical_css']) || (isset($settings['css_delivery_mode']) && 'none' !== (string) $settings['css_delivery_mode']);
        if ($uses_css_artifacts) {
            $settings['css_artifact_rollback'] = 1;
            $settings['enable_css_queue'] = 1;
        }

        if (!empty($settings['serve_cache_to_shoppers'])) {
            $settings['enable_woocommerce_rules'] = 1;
            $settings['woocommerce_safety_mode'] = 1;
        }


        if (!empty($settings['enable_delay_js']) || $uses_css_artifacts || !empty($settings['enable_js_combine']) || !empty($settings['enable_css_combine'])) {
            $settings['compatibility_mode'] = 1;
        }

        return $settings;
    }

    /**
     * Keep advanced combine values intact when the admin switches display mode.
     * Runtime conflict guards above still disable combinations that are unsafe.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function guard_advanced_combine_modes(array $settings) {
        return $settings;
    }

    /**
     * Runtime guard for WooCommerce and payment-critical requests.
     *
     * Keep this central so cache, CSS/JS, CDN, lazy render and image rewrites
     * all make the same decision before touching checkout/cart/order flows.
     *
     * @return bool
     */
    public static function is_woocommerce_critical_request() {
        if (is_admin()) {
            return false;
        }

        if (function_exists('is_cart') && is_cart()) {
            return true;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return true;
        }
        if (function_exists('is_wc_endpoint_url')) {
            foreach (array('order-pay', 'order-received', 'add-payment-method', 'edit-account', 'customer-logout') as $endpoint) {
                if (is_wc_endpoint_url($endpoint)) {
                    return true;
                }
            }
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if ('' === $request_uri) {
            return false;
        }

        $path  = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);
        $query_args = array();
        if ('' !== $query) {
            wp_parse_str($query, $query_args);
        }

        if (self::path_has_segment($path, array('cart', 'checkout', 'my-account', 'account', 'order-pay', 'order-received', 'add-payment-method', 'customer-logout', 'winkelwagen', 'afrekenen', 'mijn-account'))) {
            return true;
        }

        foreach (array('wc-ajax', 'wc-api', 'add-to-cart', 'apply_coupon', 'remove_item', 'update_cart', 'payment_method', 'edd_action') as $key) {
            if (array_key_exists($key, $query_args)) {
                return true;
            }
        }

        if (self::query_has_payment_signal($query_args)) {
            return true;
        }

        return false;
    }

    /**
     * Match full path segments only to avoid broad false positives like /blog/cartography/.
     *
     * @param string $path URL path.
     * @param array  $segments Segment names.
     * @return bool
     */
    protected static function path_has_segment($path, array $segments) {
        $parts = array_values(array_filter(array_map('sanitize_title', explode('/', trim((string) $path, '/'))), 'strlen'));
        if (empty($parts)) {
            return false;
        }
        foreach ($segments as $segment) {
            if (in_array(sanitize_title((string) $segment), $parts, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect checkout/payment providers from query values, not the whole URL string.
     *
     * @param array $query_args Query args.
     * @return bool
     */
    protected static function query_has_payment_signal(array $query_args) {
        if (empty($query_args)) {
            return false;
        }
        $payment_needles = array('stripe', 'paypal', 'mollie', 'klarna', 'adyen', 'ideal', 'wcpay', 'woocommerce-payments', 'apple-pay', 'google-pay');
        foreach ($query_args as $key => $value) {
            $haystack = strtolower((string) $key . ' ' . (is_scalar($value) ? (string) $value : UCP_Helpers::safe_json_encode_or($value, 'null')));
            foreach ($payment_needles as $needle) {
                if (false !== strpos($haystack, $needle)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Markup guard for forms, payment widgets and consent layers that should not
     * be delayed, CDN-rewritten or lazy-rendered aggressively.
     *
     * @param string $html HTML snapshot.
     * @return bool
     */
    public static function contains_sensitive_markup($html) {
        if (!is_scalar($html) && null !== $html) {
            $html = '';
        }
        $scan = strtolower(substr((string) $html, 0, 300000));
        foreach (array(
            'woocommerce-checkout', 'woocommerce-cart-form', 'wc-block-checkout', 'wc-block-cart', 'woocommerce-product-gallery',
            'payment_method_', 'stripe', 'paypal', 'mollie', 'klarna', 'adyen', 'ideal', 'wcpay',
            'gform_wrapper', 'wpforms-form', 'wpcf7-form', 'fluentform', 'ninja-forms-form', 'forminator-ui', 'frm_forms',
            'grecaptcha', 'h-captcha', 'cf-turnstile', 'turnstile', 'cookiebot', 'complianz', 'cookieyes',
            'elementor-editor-active', 'elementor-popup-modal', 'bricks-is-frontend', 'et_fb_app',
        ) as $needle) {
            if (false !== strpos($scan, $needle)) {
                return true;
            }
        }
        return false;
    }

}
