<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Preload_Safety_Trait {
    /**
     * Match preload safety/configuration patterns through the central URL matcher.
     *
     * @param string $url     Candidate URL.
     * @param string $pattern Exclusion pattern.
     * @return bool
     */
    protected static function matches_preload_url_pattern($url, $pattern) {
        if (class_exists('UCP_Quality_Suite') && method_exists('UCP_Quality_Suite', 'matches_configured_url_pattern')) {
            return UCP_Quality_Suite::matches_configured_url_pattern($url, $pattern);
        }
        return UCP_Helpers::wildcard_match($url, $pattern);
    }

    public static function light_request_headers() {
        if (!UCP_Options::get('enable_light_preload_requests')) {
            return array();
        }
        if (class_exists('UCP_LiteSpeed_Cache') && UCP_LiteSpeed_Cache::active()) {
            return array();
        }
        return array('X-UltraCache-Light-Preload' => '1');
    }

    public static function is_safety_excluded_url($url) {
        $url = UCP_Helpers::strict_local_url($url);
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
            if (self::matches_preload_url_pattern($url, $needle)) {
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
                        $request_path = strtolower(rawurldecode((string) wp_parse_url($url, PHP_URL_PATH)));
                        if ($page_path && ($request_path === $page_path || 0 === strpos($request_path, trailingslashit($page_path)))) {
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
            if (self::matches_preload_url_pattern($url, $fragment)) {
                return true;
            }
        }

        return false;
    }

    protected function preload_exclusion_reason($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = $path ? $path : $url;
        $safety_reason = self::preload_safety_reason($url);
        if ('' !== $safety_reason) {
            UCP_Logger::log('info', 'preload', 'preload_url_skipped_safety', __('Preload-URL is overgeslagen door de centrale veiligheidslaag.', 'ultracache-pro'), array('url' => esc_url_raw($url), 'reason' => $safety_reason));
            return $safety_reason;
        }
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('preload_exclude_urls', '')) as $pattern) {
            if (self::matches_preload_url_pattern($url, $pattern)) {
                UCP_Logger::log('info', 'preload', 'preload_url_skipped_pattern', __('Preload-URL is overgeslagen door een uitsluitpatroon.', 'ultracache-pro'), array('url' => esc_url_raw($url), 'pattern' => sanitize_text_field((string) $pattern)));
                return 'blocked_by_settings:' . substr(sanitize_key((string) $pattern), 0, 80);
            }
        }
        return '';
    }

    public static function preload_safety_reason($url) {
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url) {
            return 'invalid_or_external_url';
        }
        if (class_exists('UCP_Quality_Suite')) {
            $reason = UCP_Quality_Suite::bypass_reason($url);
            if ('' !== $reason) {
                return 'quality_suite:' . sanitize_key((string) $reason);
            }
        }
        $path = strtolower(rawurldecode((string) wp_parse_url($url, PHP_URL_PATH)));
        $query = strtolower(rawurldecode((string) wp_parse_url($url, PHP_URL_QUERY)));
        $haystack = trim($path . '?' . $query, '?');
        if (preg_match('#/(?:cart|checkout|my-account|account|order-pay|order-received|add-payment-method|customer-logout|winkelwagen|afrekenen|mijn-account)(?:/|$)#i', $path)) {
            return 'private_sensitive_url';
        }
        if (preg_match('#/(?:wp-admin|wp-login\.php|wp-json|xmlrpc\.php)(?:/|$)#i', $path)) {
            return 'private_sensitive_url';
        }
        foreach (array('wc-ajax=', 'wc-api=', 'add-to-cart=', 'apply_coupon=', 'remove_item=', 'update_cart=', '_wpnonce=', 'preview=', 'customize_changeset_uuid=') as $dynamic_pattern) {
            if (self::matches_preload_url_pattern($url, $dynamic_pattern)) {
                return 'dynamic_query_or_nonce';
            }
        }
        if (preg_match('#/(?:wp-content|uploads)(?:/|$)#i', $path) || preg_match('/\.(?:png|jpe?g|gif|webp|avif|svg|ico|css|js|json|xml|txt|pdf|zip|php|env|map)(?:$|[?#])/i', $haystack)) {
            return 'unsupported_content_type';
        }
        if (preg_match('#/(?:author|feed|search)(?:/|$)#i', $path) || self::matches_preload_url_pattern($url, 's=')) {
            return 'robots_or_indexable_risk';
        }
        foreach ((array) apply_filters('ucp_preload_excluded_path_fragments', array('attachment_id=', 'download=', 'preview=', 'elementor-preview=', 'wc-ajax=', 'add-to-cart=', 's=')) as $fragment) {
            if (self::matches_preload_url_pattern($url, $fragment)) {
                return 'blocked_by_settings';
            }
        }
        if (function_exists('wc_get_page_id')) {
            foreach (array('cart', 'checkout', 'myaccount') as $page) {
                $page_id = wc_get_page_id($page);
                if ($page_id && $page_id > 0) {
                    $page_url = get_permalink($page_id);
                    if ($page_url) {
                        $page_path = strtolower(rawurldecode((string) wp_parse_url($page_url, PHP_URL_PATH)));
                        if ($page_path && ($path === $page_path || 0 === strpos($path, trailingslashit($page_path)))) {
                            return 'private_sensitive_url';
                        }
                    }
                }
            }
        }
        return '';
    }

    public static function mark_preload_status($url, $status, $reason = '', $extra = array()) {
        $url = esc_url_raw((string) $url);
        $status = sanitize_key((string) $status);
        if ('' === $url || !in_array($status, array('pending', 'processing', 'cached', 'skipped', 'failed'), true)) {
            return false;
        }
        $map = get_option('ucp_preload_url_statuses', array());
        $map = is_array($map) ? $map : array();
        $key = md5($url);
        $map[$key] = array(
            'url' => $url,
            'status' => $status,
            'reason' => substr(sanitize_text_field((string) $reason), 0, 240),
            'updated_at' => current_time('mysql'),
            'extra' => self::sanitize_preload_status_extra($extra),
        );
        uasort($map, static function($a, $b) {
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });
        $map = array_slice($map, 0, 250, true);
        return self::persist_preload_option('ucp_preload_url_statuses', $map);
    }


    private static function sanitize_preload_status_extra($extra) {
        if (!is_array($extra)) {
            return array();
        }
        $clean = array();
        foreach ($extra as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }
            if (is_scalar($value) || null === $value) {
                $clean[$key] = substr(sanitize_text_field((string) $value), 0, 160);
            }
            if (count($clean) >= 20) {
                break;
            }
        }
        return $clean;
    }

    public static function preload_status_summary($limit = 50) {
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 50;
        }
        $limit = max(1, min(250, absint($limit)));
        $map = get_option('ucp_preload_url_statuses', array());
        $map = is_array($map) ? $map : array();
        $summary = array('pending' => 0, 'processing' => 0, 'cached' => 0, 'skipped' => 0, 'failed' => 0, 'recent' => array());
        foreach ($map as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = sanitize_key((string) ($row['status'] ?? ''));
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            if (count($summary['recent']) < $limit) {
                $summary['recent'][] = $row;
            }
        }
        return $summary;
    }

    public static function record_purge_url($url, $reason = 'purge') {
        $url = class_exists('UCP_Helpers') ? UCP_Helpers::strict_local_url($url) : esc_url_raw((string) $url);
        if ('' === $url) {
            return false;
        }
        $items = get_option('ucp_preload_recent_purge_urls', array());
        $items = is_array($items) ? $items : array();
        $items[md5($url)] = array('url' => esc_url_raw($url), 'reason' => sanitize_key((string) $reason), 'time' => time());
        uasort($items, static function($a, $b) {
            return absint($b['time'] ?? 0) <=> absint($a['time'] ?? 0);
        });
        $items = array_slice($items, 0, 100, true);
        return self::persist_preload_option('ucp_preload_recent_purge_urls', $items);
    }

    /**
     * Persist preload state and verify unchanged-value writes.
     *
     * @param string $key   Option key.
     * @param mixed  $value Option value.
     * @return bool
     */
    private static function persist_preload_option($key, $value) {
        return UCP_Options::persist_option_value($key, $value);
    }

    public static function server_load_too_high() {
        if (!class_exists('UCP_Options') || !UCP_Options::get('preload_pause_on_high_load')) {
            return false;
        }
        if (!function_exists('sys_getloadavg')) {
            return false;
        }
        $load = sys_getloadavg();
        if (!is_array($load) || !isset($load[0])) {
            return false;
        }
        $threshold = (float) UCP_Options::get('preload_max_server_load', 4);
        if ($threshold <= 0) {
            return false;
        }
        return (float) $load[0] >= $threshold;
    }
}
