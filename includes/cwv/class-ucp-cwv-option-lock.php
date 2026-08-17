<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Small compare-and-swap option lock for concurrent CWV aggregate updates.
 *
 * WordPress options are read-modify-write values. Without serialization two public
 * beacon requests can read the same counter and one update silently overwrites the
 * other. This lock bounds waiting and fails closed rather than corrupting aggregates.
 */
final class UCP_CWV_Option_Lock {
    const PREFIX = '_ucp_cwv_lock_';
    const TTL_SECONDS = 10;
    const WAIT_TIMEOUT_MICROSECONDS = 2000000;
    const MIN_SLEEP_MICROSECONDS = 4000;
    const MAX_SLEEP_MICROSECONDS = 50000;

    /**
     * @param string $resource Plugin-owned option/resource name.
     * @return string Opaque lock token, or an empty string when busy.
     */
    public static function acquire($resource) {
        $key = self::key($resource);
        if ('' === $key) {
            return '';
        }

        global $wpdb;
        $deadline = microtime(true) + (self::WAIT_TIMEOUT_MICROSECONDS / 1000000);
        $attempt = 0;

        do {
            $now = time();
            $lock = array(
                'token'      => wp_generate_password(24, false, false),
                'expires_at' => $now + self::TTL_SECONDS,
            );
            if (add_option($key, $lock, '', false)) {
                return (string) $lock['token'];
            }

            $current = get_option($key, array());
            $expires_at = is_array($current) && isset($current['expires_at']) ? absint($current['expires_at']) : 0;
            $has_token = is_array($current) && !empty($current['token']) && is_scalar($current['token']);
            $is_stale = !$has_token || $expires_at <= $now;
            if ($is_stale && isset($wpdb->options)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-swap takeover of a stale or malformed plugin-owned lock.
                $updated = $wpdb->update(
                    $wpdb->options,
                    array('option_value' => maybe_serialize($lock)),
                    array(
                        'option_name'  => $key,
                        'option_value' => maybe_serialize($current),
                    ),
                    array('%s'),
                    array('%s', '%s')
                );
                if (1 === (int) $updated) {
                    wp_cache_delete($key, 'options');
                    wp_cache_delete('alloptions', 'options');
                    return (string) $lock['token'];
                }
            }

            $attempt++;
            $remaining = (int) (($deadline - microtime(true)) * 1000000);
            if ($remaining <= 0) {
                break;
            }

            $backoff = min(
                self::MAX_SLEEP_MICROSECONDS,
                self::MIN_SLEEP_MICROSECONDS * (1 << min(4, $attempt - 1))
            );
            $jitter = function_exists('wp_rand') ? wp_rand(0, self::MIN_SLEEP_MICROSECONDS) : mt_rand(0, self::MIN_SLEEP_MICROSECONDS);
            usleep(min($remaining, $backoff + $jitter));
        } while (microtime(true) < $deadline);

        return '';
    }

    /**
     * Release only the exact lock acquired by the caller.
     *
     * @param string $resource Plugin-owned option/resource name.
     * @param string $token    Opaque token returned by acquire().
     * @return void
     */
    public static function release($resource, $token) {
        if (!is_scalar($token)) {
            return;
        }
        $key = self::key($resource);
        $token = (string) $token;
        if ('' === $key || '' === $token) {
            return;
        }

        $current = get_option($key, array());
        if (!is_array($current) || empty($current['token']) || !is_scalar($current['token']) || !hash_equals((string) $current['token'], $token)) {
            return;
        }

        global $wpdb;
        if (!isset($wpdb->options)) {
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-delete release of the exact plugin-owned lock value.
        $wpdb->delete(
            $wpdb->options,
            array(
                'option_name'  => $key,
                'option_value' => maybe_serialize($current),
            ),
            array('%s', '%s')
        );
        wp_cache_delete($key, 'options');
        wp_cache_delete('alloptions', 'options');
    }

    private static function key($resource) {
        if (!is_scalar($resource)) {
            return '';
        }
        $resource = sanitize_key((string) $resource);
        return '' !== $resource ? self::PREFIX . substr($resource, 0, 150) : '';
    }
}
