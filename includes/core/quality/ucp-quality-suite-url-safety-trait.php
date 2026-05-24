<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Quality_Suite_Url_Safety_Trait {
    public static function transactional_patterns() {
        return array('cart','checkout','winkelwagen','afrekenen','my-account','mijn-account','account','order-pay','order-received','add-payment-method','customer-logout','wc-ajax','wc-api','add-to-cart','apply_coupon','remove_item','update_cart','_wpnonce','preview=');
    }

    public static function builder_patterns() {
        return array('elementor-preview','elementor_library','bricks=','bricks-run','ct_builder','oxygen_iframe','breakdance=','et_fb=','fl_builder','vc_editable','customize_changeset_uuid','preview_id','preview_nonce');
    }

    public static function is_transactional_url($url) {
        $url = esc_url_raw((string) $url);
        if (!$url) {
            return true;
        }
        $path = strtolower(rawurldecode((string) wp_parse_url($url, PHP_URL_PATH)));
        $query = strtolower(rawurldecode((string) wp_parse_url($url, PHP_URL_QUERY)));
        $haystack = trim($path . '?' . $query, '?');
        foreach (self::transactional_patterns() as $pattern) {
            if (false !== strpos($haystack, strtolower($pattern))) {
                return true;
            }
        }
        if (function_exists('wc_get_page_id')) {
            foreach (array('cart', 'checkout', 'myaccount') as $page) {
                $page_id = wc_get_page_id($page);
                if ($page_id && $page_id > 0) {
                    $page_url = get_permalink($page_id);
                    $page_path = strtolower(rawurldecode((string) wp_parse_url($page_url, PHP_URL_PATH)));
                    if ($page_path && 0 === strpos($path, untrailingslashit($page_path))) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function is_builder_preview_url($url) {
        $url = strtolower(rawurldecode((string) $url));
        foreach (self::builder_patterns() as $pattern) {
            if (false !== strpos($url, strtolower($pattern))) {
                return true;
            }
        }
        return false;
    }

    public static function bypass_reason($url) {
        if (self::is_transactional_url($url)) {
            return 'transactional_or_woocommerce';
        }
        if (self::is_builder_preview_url($url)) {
            return 'builder_or_preview';
        }
        $path = strtolower((string) wp_parse_url((string) $url, PHP_URL_PATH));
        if (preg_match('/\.(?:png|jpe?g|gif|webp|avif|svg|ico|css|js|json|xml|txt|pdf|zip|php|env)$/i', $path)) {
            return 'non_html_asset';
        }
        return '';
    }

    public static function filter_safe_preload_urls($urls) {
        $safe = array();
        foreach ((array) $urls as $url) {
            $reason = self::bypass_reason($url);
            if ('' !== $reason) {
                UCP_Logger::log('info', 'preload', 'preload_url_filtered_safety', 'Preload URL filtered by central safety layer.', array('url' => $url, 'reason' => $reason));
                continue;
            }
            $safe[] = $url;
        }
        return array_values(array_unique($safe));
    }
}
