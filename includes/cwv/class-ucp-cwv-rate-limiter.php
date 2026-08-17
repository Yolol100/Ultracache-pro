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
    const ALLOWED = 1;
    const LIMITED = 0;
    const BUSY = -1;
    const STORAGE_ERROR = -2;

    /**
     * Apply CWV beacon rate limits in the same order as the legacy inline checks.
     *
     * @param string $metric Metric key.
     * @return WP_REST_Response|null Error response when rate-limited, otherwise null.
     */
    public static function enforce($metric) {
        $checks = array(
            array(self::visitor_rate_key($metric), 1, MINUTE_IN_SECONDS, 'rate_limited', MINUTE_IN_SECONDS),
            array(self::ip_minute_rate_key(), UCP_CWV::MAX_IP_SAMPLES_PER_MINUTE, MINUTE_IN_SECONDS, 'ip_rate_limited', MINUTE_IN_SECONDS),
            array(self::site_minute_rate_key(), UCP_CWV::MAX_SITE_SAMPLES_PER_MINUTE, MINUTE_IN_SECONDS, 'site_rate_limited', MINUTE_IN_SECONDS),
            array(self::daily_rate_key($metric), UCP_CWV::MAX_DAILY_SAMPLES_PER_METRIC, DAY_IN_SECONDS, 'daily_limit_reached', HOUR_IN_SECONDS),
        );

        $counter_checks = array();
        foreach ($checks as $check) {
            $counter_checks[] = array($check[0], $check[1], $check[2]);
        }

        $result = self::bump_many_status($counter_checks);
        if (self::LIMITED === $result['status']) {
            $index = isset($result['index']) ? absint($result['index']) : 0;
            $limited = isset($checks[$index]) ? $checks[$index] : $checks[0];
            return self::rate_limited_response($limited[3], $limited[4]);
        }
        if (self::ALLOWED !== $result['status']) {
            return self::unavailable_response('rate_limiter_unavailable');
        }

        return null;
    }


    /**
     * Build a consistent non-cacheable 429 response for public CWV beacon limits.
     *
     * @param string $reason      Machine-readable reason.
     * @param int    $retry_after Suggested retry delay in seconds.
     * @return WP_REST_Response
     */
    private static function rate_limited_response($reason, $retry_after = MINUTE_IN_SECONDS) {
        $response = new WP_REST_Response(array('ok' => false, 'reason' => sanitize_key((string) $reason)), 429);
        $response->header('Retry-After', (string) max(1, absint($retry_after)));
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('X-Robots-Tag', 'noindex, nofollow');
        return $response;
    }

    /**
     * Build a short non-cacheable 503 response when the limiter cannot safely
     * determine quota state because its lock or storage backend is unavailable.
     *
     * @param string $reason Machine-readable reason.
     * @return WP_REST_Response
     */
    private static function unavailable_response($reason) {
        $response = new WP_REST_Response(array('ok' => false, 'reason' => sanitize_key((string) $reason)), 503);
        $response->header('Retry-After', '1');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('X-Robots-Tag', 'noindex, nofollow');
        return $response;
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
        if (!is_scalar($key) && null !== $key) {
            $key = '';
        }
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 0;
        }
        if (!is_scalar($ttl) && null !== $ttl) {
            $ttl = 0;
        }
        return self::ALLOWED === self::bump_status($key, $limit, $ttl);
    }

    /**
     * Increment a transient-backed rate counter and return an explicit status.
     *
     * @param string $key   Counter key.
     * @param int    $limit Counter limit.
     * @param int    $ttl   Counter TTL in seconds.
     * @return int One of the class status constants.
     */
    public static function bump_status($key, $limit, $ttl) {
        if (!is_scalar($key) && null !== $key) {
            $key = '';
        }
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 0;
        }
        if (!is_scalar($ttl) && null !== $ttl) {
            $ttl = 0;
        }
        $result = self::bump_many_status(array(array($key, $limit, $ttl)));
        return (int) $result['status'];
    }

    /**
     * Increment multiple counters as one guarded decision.
     *
     * All counter locks are acquired in a stable order. Quota is checked before
     * any write and successful earlier writes are restored if a later write fails.
     *
     * @param array<int,array<int,mixed>> $checks Counter key, limit and TTL tuples.
     * @return array{status:int,index:int|null}
     */
    public static function bump_many_status($checks) {
        if (!is_array($checks) || empty($checks) || count($checks) > 16) {
            return array('status' => self::STORAGE_ERROR, 'index' => null);
        }

        $normalized = array();
        $seen = array();
        foreach (array_values($checks) as $index => $check) {
            if (!is_array($check) || count($check) < 3) {
                return array('status' => self::STORAGE_ERROR, 'index' => null);
            }
            $key = sanitize_key((string) $check[0]);
            if ('' === $key || isset($seen[$key])) {
                return array('status' => self::STORAGE_ERROR, 'index' => null);
            }
            $seen[$key] = true;
            $normalized[] = array(
                'index' => (int) $index,
                'key'   => $key,
                'limit' => max(1, absint($check[1])),
                'ttl'   => max(1, absint($check[2])),
            );
        }

        $lock_order = $normalized;
        usort($lock_order, static function($left, $right) {
            return strcmp((string) $left['key'], (string) $right['key']);
        });

        $locks = array();
        foreach ($lock_order as $check) {
            $lock_key = '_ucp_lock_' . $check['key'];
            $lock = self::acquire_counter_lock($lock_key);
            if (empty($lock)) {
                self::release_counter_locks($locks);
                return array('status' => self::BUSY, 'index' => null);
            }
            $locks[] = array('key' => $lock_key, 'lock' => $lock);
        }

        try {
            $previous = array();
            foreach ($normalized as $check) {
                $raw = get_transient($check['key']);
                $count = false === $raw ? 0 : absint($raw);
                $previous[$check['key']] = array('exists' => false !== $raw, 'count' => $count, 'ttl' => $check['ttl']);
                if ($count >= $check['limit']) {
                    return array('status' => self::LIMITED, 'index' => $check['index']);
                }
            }

            $written = array();
            foreach ($normalized as $check) {
                $next = $previous[$check['key']]['count'] + 1;
                if (!set_transient($check['key'], $next, $check['ttl'])) {
                    foreach (array_reverse($written) as $written_key) {
                        $old = $previous[$written_key];
                        if ($old['exists']) {
                            set_transient($written_key, $old['count'], $old['ttl']);
                        } else {
                            delete_transient($written_key);
                        }
                    }
                    return array('status' => self::STORAGE_ERROR, 'index' => $check['index']);
                }
                $written[] = $check['key'];
            }

            return array('status' => self::ALLOWED, 'index' => null);
        } finally {
            self::release_counter_locks($locks);
        }
    }

    /**
     * Acquire one token-owned option lock with bounded contention.
     *
     * @param string $lock_key Lock option name.
     * @return array<string,mixed>
     */
    private static function acquire_counter_lock($lock_key) {
        global $wpdb;

        $deadline = microtime(true) + 0.100;
        do {
            $now = time();
            $lock = array(
                'token'      => wp_generate_password(20, false, false),
                'expires_at' => $now + 5,
            );
            if (add_option($lock_key, $lock, '', false)) {
                return $lock;
            }

            $current = get_option($lock_key, array());
            $expires_at = is_array($current) && isset($current['expires_at']) ? absint($current['expires_at']) : 0;
            $has_token = is_array($current) && !empty($current['token']) && is_scalar($current['token']);
            if (!$has_token || $expires_at <= $now) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic takeover of a stale plugin-owned limiter lock.
                $updated = $wpdb->update(
                    $wpdb->options,
                    array('option_value' => maybe_serialize($lock)),
                    array('option_name' => $lock_key, 'option_value' => maybe_serialize($current)),
                    array('%s'),
                    array('%s', '%s')
                );
                if (1 === (int) $updated) {
                    wp_cache_delete($lock_key, 'options');
                    wp_cache_delete('alloptions', 'options');
                    return $lock;
                }
            }

            usleep(wp_rand(5000, 15000)); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_usleep -- bounded contention backoff.
        } while (microtime(true) < $deadline);

        return array();
    }

    /**
     * Release acquired counter locks in reverse order.
     *
     * @param array<int,array<string,mixed>> $locks Lock records.
     * @return void
     */
    private static function release_counter_locks($locks) {
        global $wpdb;

        foreach (array_reverse((array) $locks) as $record) {
            $lock_key = isset($record['key']) ? (string) $record['key'] : '';
            $lock = isset($record['lock']) && is_array($record['lock']) ? $record['lock'] : array();
            if ('' === $lock_key || empty($lock['token']) || !is_scalar($lock['token'])) {
                continue;
            }
            $current = get_option($lock_key, array());
            if (!is_array($current) || empty($current['token']) || !is_scalar($current['token']) || !hash_equals((string) $current['token'], (string) $lock['token'])) {
                continue;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact-value release of a plugin-owned limiter lock.
            $wpdb->delete(
                $wpdb->options,
                array('option_name' => $lock_key, 'option_value' => maybe_serialize($current)),
                array('%s', '%s')
            );
            wp_cache_delete($lock_key, 'options');
            wp_cache_delete('alloptions', 'options');
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
        $ip = self::request_ip();
        return 'ucp_cwv_ip_' . wp_hash($ip . '|' . gmdate('YmdHi'));
    }

    /**
     * Build the per-metric daily bucket key.
     *
     * @param string $metric Metric key.
     * @return string
     */
    public static function daily_rate_key($metric) {
        $metric = is_scalar($metric) ? sanitize_key((string) $metric) : '';
        return 'ucp_cwv_daily_' . gmdate('Ymd') . '_' . $metric;
    }

    /**
     * Build the short-lived visitor bucket key.
     *
     * @param string $metric Metric key.
     * @return string
     */
    public static function visitor_rate_key($metric) {
        $metric = is_scalar($metric) ? (string) $metric : '';
        $ip = self::request_ip();
        $agent = UCP_Helpers::server_value('HTTP_USER_AGENT', '', 8192);
        return 'ucp_cwv_rate_' . wp_hash($metric . '|' . $ip . '|' . substr($agent, 0, 120));
    }
    /**
     * Return a bounded, canonical client address for local rate-limit buckets.
     *
     * @return string
     */
    private static function request_ip() {
        $ip = UCP_Helpers::server_value('REMOTE_ADDR', 'unknown', 64);
        return false !== filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

}
