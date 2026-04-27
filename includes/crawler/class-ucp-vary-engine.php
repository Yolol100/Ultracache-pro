<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Vary_Engine {
    public static function enabled() {
        return (bool) UCP_Options::get('enable_cache_vary', 0);
    }

    public static function current_suffix() {
        if (!self::enabled()) {
            return '';
        }
        $parts = array();
        if (UCP_Options::get('vary_mobile_desktop', 0)) {
            $parts[] = wp_is_mobile() ? 'mobile' : 'desktop';
        }
        $cookie_rules = self::cookie_rules();
        foreach ($cookie_rules as $rule) {
            foreach (array_keys((array) $_COOKIE) as $cookie_name) {
                if (false !== strpos((string) $cookie_name, $rule)) {
                    if (self::is_sensitive_cookie($cookie_name)) {
                        continue;
                    }
                    $parts[] = 'cookie-' . sanitize_key($rule);
                }
            }
        }
        $parts = array_unique(array_filter($parts));
        return empty($parts) ? '' : '-' . md5(implode('|', $parts));
    }

    public static function cookie_rules() {
        $raw = (string) UCP_Options::get('vary_cookie_rules', '');
        $rules = preg_split('/[\r\n,]+/', $raw);
        return array_values(array_filter(array_map('sanitize_text_field', (array) $rules)));
    }

    protected static function is_sensitive_cookie($cookie) {
        foreach (array('wordpress_logged_in_', 'wp_woocommerce_session_', 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'PHPSESSID', 'edd_items_in_cart') as $fragment) {
            if (false !== strpos((string) $cookie, $fragment)) {
                return true;
            }
        }
        return false;
    }

    public static function estimate_multiplier() {
        $multiplier = 1;
        if (UCP_Options::get('vary_mobile_desktop', 0)) {
            $multiplier *= 2;
        }
        $rules = self::cookie_rules();
        if (!empty($rules)) {
            $multiplier *= min(8, count($rules) + 1);
        }
        return $multiplier;
    }
}
