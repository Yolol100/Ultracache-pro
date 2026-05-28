<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
/**
 * UltraCache Pro Redis Object Cache drop-in.
 *
 * Persistent WordPress object cache backed by the phpredis extension, with an in-request
 * runtime layer, multisite-aware key segmentation and graceful degradation to a non-persistent
 * runtime cache when Redis is unavailable. Mirrors the API surface of the UltraCache APCu drop-in.
 *
 * Connection is configured with WordPress constants (define them in wp-config.php):
 *   define('WP_REDIS_HOST', '127.0.0.1');     // or a unix socket path like '/var/run/redis.sock'
 *   define('WP_REDIS_PORT', 6379);            // ignored for unix sockets
 *   define('WP_REDIS_PASSWORD', '');          // optional AUTH
 *   define('WP_REDIS_DATABASE', 0);           // optional SELECT
 *   define('WP_REDIS_TIMEOUT', 1.0);          // connect timeout (seconds)
 *   define('WP_REDIS_PREFIX', '');            // optional extra namespace
 *   define('WP_CACHE_KEY_SALT', '...');       // shared salt (recommended)
 */
if (!defined('ABSPATH')) {
    exit;
}

$GLOBALS['ucp_redis_cache_global_groups'] = isset($GLOBALS['ucp_redis_cache_global_groups']) && is_array($GLOBALS['ucp_redis_cache_global_groups']) ? $GLOBALS['ucp_redis_cache_global_groups'] : array();
$GLOBALS['ucp_redis_cache_non_persistent_groups'] = isset($GLOBALS['ucp_redis_cache_non_persistent_groups']) && is_array($GLOBALS['ucp_redis_cache_non_persistent_groups']) ? $GLOBALS['ucp_redis_cache_non_persistent_groups'] : array();
$GLOBALS['ucp_redis_cache_runtime'] = isset($GLOBALS['ucp_redis_cache_runtime']) && is_array($GLOBALS['ucp_redis_cache_runtime']) ? $GLOBALS['ucp_redis_cache_runtime'] : array();
$GLOBALS['ucp_redis_connection'] = isset($GLOBALS['ucp_redis_connection']) ? $GLOBALS['ucp_redis_connection'] : null;
$GLOBALS['ucp_redis_connection_failed'] = isset($GLOBALS['ucp_redis_connection_failed']) ? (bool) $GLOBALS['ucp_redis_connection_failed'] : false;

if (!function_exists('ucp_redis_normalize_group')) {
    function ucp_redis_normalize_group($group) {
        $group = (string) $group;
        return '' === $group ? 'default' : preg_replace('/[^A-Za-z0-9_.:-]/', '_', $group);
    }
}

if (!function_exists('ucp_redis_normalize_groups')) {
    function ucp_redis_normalize_groups($groups) {
        $groups = is_array($groups) ? $groups : array($groups);
        $normalized = array();
        foreach ($groups as $group) {
            $group = ucp_redis_normalize_group($group);
            if ('' !== $group) {
                $normalized[$group] = true;
            }
        }
        return $normalized;
    }
}

if (!function_exists('ucp_redis_group_is_global')) {
    function ucp_redis_group_is_global($group) {
        $group = ucp_redis_normalize_group($group);
        return !empty($GLOBALS['ucp_redis_cache_global_groups'][$group]);
    }
}

if (!function_exists('ucp_redis_group_is_non_persistent')) {
    function ucp_redis_group_is_non_persistent($group) {
        $group = ucp_redis_normalize_group($group);
        return !empty($GLOBALS['ucp_redis_cache_non_persistent_groups'][$group]);
    }
}

if (!function_exists('ucp_redis_site_segment')) {
    function ucp_redis_site_segment($group) {
        if (ucp_redis_group_is_global($group)) {
            return 'global';
        }
        return function_exists('get_current_blog_id') ? (string) max(0, (int) get_current_blog_id()) : '0';
    }
}

if (!function_exists('ucp_redis_installation_salt')) {
    function ucp_redis_installation_salt() {
        if (defined('WP_CACHE_KEY_SALT') && '' !== (string) WP_CACHE_KEY_SALT) {
            return (string) WP_CACHE_KEY_SALT;
        }
        $prefix = defined('WP_REDIS_PREFIX') ? (string) WP_REDIS_PREFIX : '';
        return ($prefix ? $prefix . '|' : '') . (defined('ABSPATH') ? (string) ABSPATH : 'wordpress');
    }
}

if (!function_exists('ucp_redis_prefix')) {
    function ucp_redis_prefix($group = '') {
        return 'ucp:' . md5(ucp_redis_installation_salt()) . ':' . ucp_redis_site_segment($group) . ':';
    }
}

if (!function_exists('ucp_redis_key')) {
    function ucp_redis_key($key, $group = 'default') {
        $group = ucp_redis_normalize_group($group);
        return ucp_redis_prefix($group) . $group . ':' . md5((string) $key);
    }
}

if (!function_exists('ucp_redis_connection')) {
    /**
     * Lazily establish and memoize the Redis connection. Returns null on any failure so the
     * drop-in can degrade to a runtime-only (non-persistent) cache without fatal errors.
     *
     * @return Redis|null
     */
    function ucp_redis_connection() {
        if (null !== $GLOBALS['ucp_redis_connection']) {
            return $GLOBALS['ucp_redis_connection'];
        }
        if ($GLOBALS['ucp_redis_connection_failed'] || !class_exists('Redis')) {
            return null;
        }

        try {
            $redis = new Redis();
            $host = defined('WP_REDIS_HOST') ? (string) WP_REDIS_HOST : '127.0.0.1';
            $port = defined('WP_REDIS_PORT') ? (int) WP_REDIS_PORT : 6379;
            $timeout = defined('WP_REDIS_TIMEOUT') ? (float) WP_REDIS_TIMEOUT : 1.0;

            // Unix socket when the host looks like a path; TCP otherwise.
            if ('' !== $host && ('/' === $host[0] || false !== strpos($host, '.sock'))) {
                $connected = $redis->connect($host);
            } else {
                $connected = $redis->connect($host, $port, $timeout);
            }
            if (!$connected) {
                $GLOBALS['ucp_redis_connection_failed'] = true;
                return null;
            }

            if (defined('WP_REDIS_PASSWORD') && '' !== (string) WP_REDIS_PASSWORD) {
                $redis->auth((string) WP_REDIS_PASSWORD);
            }
            if (defined('WP_REDIS_DATABASE') && (int) WP_REDIS_DATABASE > 0) {
                $redis->select((int) WP_REDIS_DATABASE);
            }
            // Use PHP serialization so arrays/objects round-trip correctly.
            if (defined('Redis::OPT_SERIALIZER')) {
                $serializer = defined('Redis::SERIALIZER_IGBINARY') && extension_loaded('igbinary') ? Redis::SERIALIZER_IGBINARY : Redis::SERIALIZER_PHP;
                $redis->setOption(Redis::OPT_SERIALIZER, $serializer);
            }

            $GLOBALS['ucp_redis_connection'] = $redis;
            return $redis;
        } catch (Throwable $e) {
            $GLOBALS['ucp_redis_connection_failed'] = true;
            $GLOBALS['ucp_redis_connection'] = null;
            return null;
        }
    }
}

if (!function_exists('ucp_redis_is_available')) {
    function ucp_redis_is_available() {
        return null !== ucp_redis_connection();
    }
}

if (!function_exists('ucp_redis_delete_by_prefix')) {
    function ucp_redis_delete_by_prefix($prefix) {
        $redis = ucp_redis_connection();
        if (!$redis) {
            return true;
        }
        try {
            $iterator = null;
            $pattern = (string) $prefix . '*';
            do {
                $keys = $redis->scan($iterator, $pattern, 500);
                if (is_array($keys) && !empty($keys)) {
                    $redis->del($keys);
                }
            } while ($iterator > 0);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('ucp_redis_delete_runtime_by_prefix')) {
    function ucp_redis_delete_runtime_by_prefix($prefix) {
        foreach (array_keys($GLOBALS['ucp_redis_cache_runtime']) as $cache_key) {
            if (0 === strpos($cache_key, (string) $prefix)) {
                unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            }
        }
    }
}

if (!function_exists('wp_cache_init')) {
    function wp_cache_init() {
        ucp_redis_connection();
        return true;
    }
}

if (!function_exists('wp_cache_close')) {
    function wp_cache_close() {
        return true;
    }
}

if (!function_exists('wp_cache_add_global_groups')) {
    function wp_cache_add_global_groups($groups) {
        $GLOBALS['ucp_redis_cache_global_groups'] = array_merge(
            $GLOBALS['ucp_redis_cache_global_groups'],
            ucp_redis_normalize_groups($groups)
        );
        return true;
    }
}

if (!function_exists('wp_cache_add_non_persistent_groups')) {
    function wp_cache_add_non_persistent_groups($groups) {
        $GLOBALS['ucp_redis_cache_non_persistent_groups'] = array_merge(
            $GLOBALS['ucp_redis_cache_non_persistent_groups'],
            ucp_redis_normalize_groups($groups)
        );
        return true;
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null) {
        $cache_key = ucp_redis_key($key, $group);

        if (!$force && array_key_exists($cache_key, $GLOBALS['ucp_redis_cache_runtime'])) {
            $found = true;
            return $GLOBALS['ucp_redis_cache_runtime'][$cache_key];
        }

        if (ucp_redis_group_is_non_persistent($group) || !ucp_redis_is_available()) {
            // For non-persistent groups, fall back to the runtime value if present.
            if (array_key_exists($cache_key, $GLOBALS['ucp_redis_cache_runtime'])) {
                $found = true;
                return $GLOBALS['ucp_redis_cache_runtime'][$cache_key];
            }
            $found = false;
            return false;
        }

        try {
            $redis = ucp_redis_connection();
            $value = $redis->get($cache_key);
        } catch (Throwable $e) {
            $found = false;
            return false;
        }

        if (false === $value && !$redis->exists($cache_key)) {
            $found = false;
            return false;
        }

        $found = true;
        $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $value;
        return $value;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        $cache_key = ucp_redis_key($key, $group);
        if (is_object($data)) {
            $data = clone $data;
        }
        $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $data;

        if (ucp_redis_group_is_non_persistent($group) || !ucp_redis_is_available()) {
            return true;
        }

        try {
            $redis = ucp_redis_connection();
            $expire = max(0, (int) $expire);
            if ($expire > 0) {
                return (bool) $redis->setex($cache_key, $expire, $data);
            }
            return (bool) $redis->set($cache_key, $data);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('wp_cache_add')) {
    function wp_cache_add($key, $data, $group = '', $expire = 0) {
        $cache_key = ucp_redis_key($key, $group);

        if (array_key_exists($cache_key, $GLOBALS['ucp_redis_cache_runtime'])) {
            return false;
        }

        if (ucp_redis_group_is_non_persistent($group) || !ucp_redis_is_available()) {
            $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $data;
            return true;
        }

        try {
            $redis = ucp_redis_connection();
            $expire = max(0, (int) $expire);
            $options = array('nx');
            if ($expire > 0) {
                $options['ex'] = $expire;
            }
            if (!$redis->set($cache_key, $data, $options)) {
                return false;
            }
        } catch (Throwable $e) {
            return false;
        }

        $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_replace')) {
    function wp_cache_replace($key, $data, $group = '', $expire = 0) {
        $found = false;
        wp_cache_get($key, $group, true, $found);
        return $found ? wp_cache_set($key, $data, $group, $expire) : false;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        $cache_key = ucp_redis_key($key, $group);
        unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);

        if (ucp_redis_group_is_non_persistent($group) || !ucp_redis_is_available()) {
            return true;
        }

        try {
            $redis = ucp_redis_connection();
            return (bool) $redis->del($cache_key);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('wp_cache_get_multiple')) {
    function wp_cache_get_multiple($keys, $group = '', $force = false) {
        $values = array();
        foreach ((array) $keys as $key) {
            $values[$key] = wp_cache_get($key, $group, $force);
        }
        return $values;
    }
}

if (!function_exists('wp_cache_set_multiple')) {
    function wp_cache_set_multiple($data, $group = '', $expire = 0) {
        $results = array();
        foreach ((array) $data as $key => $value) {
            $results[$key] = wp_cache_set($key, $value, $group, $expire);
        }
        return $results;
    }
}

if (!function_exists('wp_cache_add_multiple')) {
    function wp_cache_add_multiple($data, $group = '', $expire = 0) {
        $results = array();
        foreach ((array) $data as $key => $value) {
            $results[$key] = wp_cache_add($key, $value, $group, $expire);
        }
        return $results;
    }
}

if (!function_exists('wp_cache_delete_multiple')) {
    function wp_cache_delete_multiple($keys, $group = '') {
        $results = array();
        foreach ((array) $keys as $key) {
            $results[$key] = wp_cache_delete($key, $group);
        }
        return $results;
    }
}

if (!function_exists('wp_cache_flush_runtime')) {
    function wp_cache_flush_runtime() {
        $GLOBALS['ucp_redis_cache_runtime'] = array();
        return true;
    }
}

if (!function_exists('wp_cache_reset')) {
    function wp_cache_reset() {
        return wp_cache_flush_runtime();
    }
}

if (!function_exists('wp_cache_flush_group')) {
    function wp_cache_flush_group($group) {
        $group = ucp_redis_normalize_group($group);
        $prefix = ucp_redis_prefix($group) . $group . ':';
        ucp_redis_delete_runtime_by_prefix($prefix);
        return ucp_redis_delete_by_prefix($prefix);
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        wp_cache_flush_runtime();
        // Only clear this installation's namespace, never the whole Redis database.
        return ucp_redis_delete_by_prefix('ucp:' . md5(ucp_redis_installation_salt()) . ':');
    }
}

if (!function_exists('wp_cache_incr')) {
    function wp_cache_incr($key, $offset = 1, $group = '') {
        $offset = abs((int) $offset);
        $cache_key = ucp_redis_key($key, $group);
        $found = false;
        $current = wp_cache_get($key, $group, true, $found);
        if (!$found) {
            return false;
        }
        $value = (int) $current + $offset;

        if (ucp_redis_group_is_non_persistent($group) || !ucp_redis_is_available()) {
            $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $value;
            return $value;
        }

        // Re-store as a plain integer string and use atomic INCRBY to avoid lost updates.
        try {
            $redis = ucp_redis_connection();
            $redis->set($cache_key, (int) $current);
            $value = (int) $redis->incrBy($cache_key, $offset);
            $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $value;
            return $value;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('wp_cache_decr')) {
    function wp_cache_decr($key, $offset = 1, $group = '') {
        $offset = abs((int) $offset);
        $cache_key = ucp_redis_key($key, $group);
        $found = false;
        $current = wp_cache_get($key, $group, true, $found);
        if (!$found) {
            return false;
        }
        $value = max(0, (int) $current - $offset);

        if (ucp_redis_group_is_non_persistent($group) || !ucp_redis_is_available()) {
            $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $value;
            return $value;
        }

        try {
            $redis = ucp_redis_connection();
            $redis->set($cache_key, (int) $current);
            $value = (int) $redis->decrBy($cache_key, $offset);
            if ($value < 0) {
                $value = 0;
                $redis->set($cache_key, 0);
            }
            $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $value;
            return $value;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('wp_cache_switch_to_blog')) {
    function wp_cache_switch_to_blog($blog_id) {
        // Keys are segmented by blog id at read/write time, so no persistent state to swap.
        return true;
    }
}

if (!function_exists('wp_cache_supports')) {
    function wp_cache_supports($feature) {
        return in_array(
            (string) $feature,
            array('add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple', 'flush_runtime', 'flush_group'),
            true
        );
    }
}
