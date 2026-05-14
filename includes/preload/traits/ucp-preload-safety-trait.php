<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Preload_Safety_Trait {
    protected function wildcard_match($haystack, $pattern) {
        $haystack = (string) $haystack;
        $pattern = trim((string) $pattern);
        if ('' === $pattern) {
            return false;
        }
        if (false !== strpos($pattern, '(.*)') || false !== strpos($pattern, '*')) {
            $regex = preg_quote($pattern, '#');
            $regex = str_replace(array('\(\.\*\)', '\*'), '.*', $regex);
            return 1 === preg_match('#' . $regex . '#i', $haystack);
        }
        return false !== stripos($haystack, $pattern);
    }

    public static function is_safety_excluded_url($url) {
        $url = esc_url_raw(UCP_Helpers::normalize_url_syntax($url));
        if (!$url) {
            return true;
        }
        if (class_exists('UCP_Quality_Suite') && '' !== UCP_Quality_Suite::bypass_reason($url)) {
            return true;
        }
        if (self::is_non_preloadable_url($url)) {
            return true;
        }
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $query = (string) wp_parse_url($url, PHP_URL_QUERY);
        $haystack = strtolower(rawurldecode($path . '?' . $query));
        $needles = array(
            '/cart', '/checkout', '/my-account', '/account', '/order-pay', '/order-received', '/add-payment-method', '/customer-logout',
            '/winkelwagen', '/afrekenen', '/mijn-account', 'wc-ajax', 'wc-api', 'add-to-cart', 'apply_coupon', 'remove_item', 'update_cart', '_wpnonce', 'preview='
        );
        foreach ($needles as $needle) {
            if (false !== strpos($haystack, strtolower($needle))) {
                return true;
            }
        }
        if (function_exists('wc_get_page_id')) {
            foreach (array('cart', 'checkout', 'myaccount') as $page) {
                $page_id = wc_get_page_id($page);
                if ($page_id && $page_id > 0) {
                    $page_url = get_permalink($page_id);
                    if ($page_url) {
                        $page_path = strtolower(rawurldecode((string) wp_parse_url($page_url, PHP_URL_PATH)));
                        if ($page_path && 0 === strpos($haystack, $page_path)) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public static function is_non_preloadable_url($url) {
        $url = UCP_Helpers::normalize_url_syntax($url);
        $path = strtolower(rawurldecode((string) wp_parse_url($url, PHP_URL_PATH)));
        $query = strtolower(rawurldecode((string) wp_parse_url($url, PHP_URL_QUERY)));
        $haystack = trim($path . '?' . $query, '?');

        if ('' === $haystack) {
            return false;
        }

        if (preg_match('#/(?:wp-admin|wp-login\.php|wp-json|xmlrpc\.php)(?:/|$)#i', $path)) {
            return true;
        }
        if (preg_match('#/(?:wp-content|uploads)(?:/|$)#i', $path)) {
            return true;
        }
        if (preg_match('#/(?:author|feed|search)(?:/|$)#i', $path)) {
            return true;
        }
        if (preg_match('#(?:^|/)[^/]+-zip/?$#i', untrailingslashit($path))) {
            return true;
        }
        if (preg_match('/\.(?:png|jpe?g|gif|webp|avif|svg|ico|css|js|json|xml|txt|pdf|zip|php|env|map)(?:$|[?#])/i', $haystack)) {
            return true;
        }

        $fragments = apply_filters('ucp_preload_excluded_path_fragments', array(
            'attachment_id=',
            'download=',
            'preview=',
            'elementor-preview=',
            'wc-ajax=',
            'add-to-cart=',
            's=',
        ));
        foreach ((array) $fragments as $fragment) {
            $fragment = strtolower((string) $fragment);
            if ('' !== $fragment && false !== strpos($haystack, $fragment)) {
                return true;
            }
        }

        return false;
    }

    protected function is_preload_excluded($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = $path ? $path : $url;
        if (self::is_safety_excluded_url($url)) {
            UCP_Logger::log('info', 'preload', 'preload_url_skipped_safety', 'Preload URL overgeslagen vanwege shop/account safety rule.', array('url' => $url));
            return true;
        }
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('preload_exclude_urls', '')) as $pattern) {
            if ($this->wildcard_match($url, $pattern) || $this->wildcard_match($path, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
