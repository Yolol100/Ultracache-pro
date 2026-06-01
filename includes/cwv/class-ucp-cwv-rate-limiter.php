<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CWV beacon rate limit helper.
 *
 * Keeps the public beacon throttling rules out of the main CWV controller while
 * preserving the existing transient keys, lock keys, limits and response reasons.
 */
final class UCP_CWV_Rate_Limiter {
    /**
     * Apply CWV beacon rate limits in the same order as the legacy inline checks.
     *
     * @param string $metric Metric key.
     * @return WP_REST_Response|null Error response when rate-limited, otherwise null.
     */
    public static function enforce($metric) {
        if (!self::bump(self::visitor_rate_key($metric), 1, MINUTE_IN_SECONDS)) {
            return new WP_REST_Response(array('ok' => false, 'reason' => 'rate_limited'), 429);
        }

        if (!self::bump(self::daily_rate_key($metric), UCP_CWV::MAX_DAILY_SAMPLES_PER_METRIC, DAY_IN_SECONDS)) {
            return new WP_REST_Response(array('ok' => false, 'reason' => 'daily_limit_reached'), 429);
        }

        if (!self::bump(self::ip_minute_rate_key(), UCP_CWV::MAX_IP_SAMPLES_PER_MINUTE, MINUTE_IN_SECONDS)) {
            return new WP_REST_Response(array('ok' => false, 'reason' => 'ip_rate_limited'), 429);
        }

        if (!self::bump(self::site_minute_rate_key(), UCP_CWV::MAX_SITE_SAMPLES_PER_MINUTE, MINUTE_IN_SECONDS)) {
            return new WP_REST_Response(array('ok' => false, 'reason' => 'site_rate_limited'), 429);
        }

        return null;
    }

    /**
     * Increment a transient-backed rate counter using the existing option lock.
     *
     * @param string $key   Counter key.
     * @param int    $limit Counter limit.
     * @param int    $ttl   Counter TTL in seconds.
     * @return bool
     */
    public static function bump($key, $limit, $ttl) {
        $key = sanitize_key((string) $key);
        $limit = max(1, absint($limit));
        $ttl = max(1, absint($ttl));

        if ('' === $key) {
            return false;
        }

        $lock_key = '_ucp_lock_' . $key;
        $now = time();
        $locked = add_option($lock_key, $now, '', 'no');

        if (!$locked) {
            $created = absint(get_option($lock_key));
            if ($created && $created < ($now - 5)) {
                delete_option($lock_key);
                $locked = add_option($lock_key, $now, '', 'no');
            }
        }

        if (!$locked) {
            return false;
        }

        try {
            $count = absint(get_transient($key));
            if ($count >= $limit) {
                return false;
            }

            if (!set_transient($key, $count + 1, $ttl)) {
                return false;
            }

            return true;
        } finally {
            delete_option($lock_key);
        }
    }

    /**
     * Build the site-wide minute bucket key.
     *
     * @return string
     */
    public static function site_minute_rate_key() {
        return 'ucp_cwv_site_' . gmdate('YmdHi');
    }

    /**
     * Build the per-IP minute bucket key.
     *
     * @return string
     */
    public static function ip_minute_rate_key() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        return 'ucp_cwv_ip_' . wp_hash($ip . '|' . gmdate('YmdHi'));
    }

    /**
     * Build the per-metric daily bucket key.
     *
     * @param string $metric Metric key.
     * @return string
     */
    public static function daily_rate_key($metric) {
        return 'ucp_cwv_daily_' . gmdate('Ymd') . '_' . sanitize_key((string) $metric);
    }

    /**
     * Build the short-lived visitor bucket key.
     *
     * @param string $metric Metric key.
     * @return string
     */
    public static function visitor_rate_key($metric) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        return 'ucp_cwv_rate_' . wp_hash($metric . '|' . $ip . '|' . substr($agent, 0, 120));
    }
}
