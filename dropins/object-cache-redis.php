<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
/**
 * UltraCache Pro Redis Object Cache drop-in.
 *
 * Persistent WordPress object cache backed by the phpredis extension, with an in-request
 * runtime layer and multisite-aware key segmentation. Non-persistent groups remain available
 * in memory during an outage; persistent operations fail explicitly instead of reporting false durability.
 * Mirrors the API surface of the UltraCache APCu drop-in.
 *
 * Connection is configured with WordPress constants (define them in wp-config.php):
 *   define('WP_REDIS_HOST', '127.0.0.1');     // or a unix socket path like '/var/run/redis.sock'
 *   define('WP_REDIS_PORT', 6379);            // ignored for unix sockets
 *   define('WP_REDIS_PASSWORD', '');          // optional AUTH
 *   define('WP_REDIS_DATABASE', 0);           // optional SELECT
 *   define('WP_REDIS_TIMEOUT', 1.0);          // connect timeout (seconds)
 *   define('WP_REDIS_READ_TIMEOUT', 1.0);     // command read timeout (seconds)
 *   define('WP_REDIS_RETRY_INTERVAL', 100);   // reconnect delay (milliseconds)
 *   define('WP_REDIS_SCHEME', 'tcp');         // tcp, tls or unix
 *   define('WP_REDIS_SSL_CONTEXT', array(     // optional TLS stream options (PhpRedis >= 5.3)
 *       'cafile' => '/etc/ssl/redis-ca.pem',
 *       'verify_peer' => true,
 *       'verify_peer_name' => true,
 *   ));
 *   define('WP_REDIS_PREFIX', '');            // optional extra namespace
 *   define('WP_REDIS_SCAN_COUNT', 500);       // SCAN count hint
 *   define('WP_REDIS_SCAN_MAX_ROUNDS', 1000); // hard per-request SCAN round budget
 *   define('WP_REDIS_SCAN_MAX_KEYS', 100000); // hard per-request deletion budget
 *   define('WP_REDIS_SCAN_MAX_SECONDS', 5.0); // hard per-request time budget
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
        if ('' === $group) {
            return 'default';
        }
        if (1 === preg_match('/^ucp-group-[a-f0-9]{64}$/', $group)
            || 1 === preg_match('/^[A-Za-z0-9_.:-]+$/', $group)) {
            return $group;
        }
        return 'ucp-group-' . hash('sha256', $group);
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

if (!function_exists('ucp_redis_is_valid_key')) {
    function ucp_redis_is_valid_key($key) {
        return is_int($key) || (is_string($key) && '' !== trim($key));
    }
}

if (!function_exists('ucp_redis_clone_value')) {
    function ucp_redis_clone_value($value) {
        return is_object($value) ? clone $value : $value;
    }
}

if (!function_exists('ucp_redis_connection_scheme')) {
    /**
     * Resolve the configured transport without accepting arbitrary stream schemes.
     *
     * @param string $host Configured host.
     * @return string tcp, tls or unix.
     */
    function ucp_redis_connection_scheme($host) {
        $host = strtolower(trim((string) $host));
        if (0 === strpos($host, 'tls://')) {
            return 'tls';
        }
        if (0 === strpos($host, 'unix://') || 1 === preg_match('~^(?:/|.*\.sock$)~i', $host)) {
            return 'unix';
        }

        $scheme = defined('WP_REDIS_SCHEME') ? strtolower(trim((string) WP_REDIS_SCHEME)) : 'tcp';
        return in_array($scheme, array('tcp', 'tls', 'unix'), true) ? $scheme : 'tcp';
    }
}

if (!function_exists('ucp_redis_stream_options')) {
    /**
     * Build secure TLS stream options for PhpRedis 5.3+.
     *
     * @return array
     */
    function ucp_redis_stream_options() {
        $options = array(
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
            'disable_compression' => true,
        );
        if (defined('WP_REDIS_SSL_CONTEXT') && is_array(WP_REDIS_SSL_CONTEXT)) {
            $options = array_merge($options, WP_REDIS_SSL_CONTEXT);
        }
        return array('stream' => $options);
    }
}

if (!function_exists('ucp_redis_persistent_id')) {
    /**
     * Isolate persistent sockets by installation and authentication context.
     *
     * PhpRedis persistent sockets are reused by their persistent identifier. Including a
     * credential fingerprint prevents two WordPress installations in the same PHP worker
     * from reusing a socket whose authenticated ACL user was selected by another site.
     */
    function ucp_redis_persistent_id($host, $port) {
        $password = defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : '';
        $identity = array(
            (string) $host,
            (int) $port,
            defined('WP_REDIS_DATABASE') ? (int) WP_REDIS_DATABASE : 0,
            defined('WP_REDIS_PREFIX') ? (string) WP_REDIS_PREFIX : '',
            ucp_redis_installation_salt(),
            defined('WP_REDIS_USERNAME') ? (string) WP_REDIS_USERNAME : '',
            hash('sha256', serialize($password)),
        );

        return 'ucp-' . substr(hash('sha256', serialize($identity)), 0, 20);
    }
}

if (!function_exists('ucp_redis_connection')) {
    /**
     * Lazily establish and memoize the Redis connection. Returns null on any failure so the
     * drop-in can fail persistent operations cleanly while non-persistent groups remain usable.
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
            $host = defined('WP_REDIS_HOST') ? trim((string) WP_REDIS_HOST) : '127.0.0.1';
            $port = defined('WP_REDIS_PORT') ? max(0, (int) WP_REDIS_PORT) : 6379;
            $timeout = defined('WP_REDIS_TIMEOUT') ? max(0.1, min(30.0, (float) WP_REDIS_TIMEOUT)) : 1.0;
            $read_timeout = defined('WP_REDIS_READ_TIMEOUT') ? max(0.1, min(30.0, (float) WP_REDIS_READ_TIMEOUT)) : $timeout;
            $retry_interval = defined('WP_REDIS_RETRY_INTERVAL') ? max(0, min(1000, (int) WP_REDIS_RETRY_INTERVAL)) : 100;
            $scheme = ucp_redis_connection_scheme($host);
            $is_socket = 'unix' === $scheme;

            if ($is_socket) {
                $connection_host = preg_replace('~^unix://~i', '', $host);
                $connection_port = 0;
            } else {
                $connection_host = preg_replace('~^(?:tcp|tls)://~i', '', $host);
                $connection_host = ('tls' === $scheme ? 'tls://' : '') . $connection_host;
                $connection_port = $port > 0 ? $port : 6379;
            }
            if ('' === (string) $connection_host) {
                throw new RuntimeException('Redis host is empty.');
            }

            $connection_options = 'tls' === $scheme ? ucp_redis_stream_options() : array();
            $redis_extension_version = phpversion('redis');
            if (!empty($connection_options)
                && is_string($redis_extension_version)
                && version_compare($redis_extension_version, '5.3.0', '<')) {
                throw new RuntimeException('Custom Redis TLS verification requires PhpRedis 5.3 or newer.');
            }

            // Persistent connections reuse the socket across requests (skips the TCP
            // handshake per request). Falls back to a non-persistent connect() when
            // pconnect is unavailable or disabled via WP_REDIS_PERSISTENT.
            $use_persistent = (!defined('WP_REDIS_PERSISTENT') || WP_REDIS_PERSISTENT) && method_exists($redis, 'pconnect');
            $persistent_id = ucp_redis_persistent_id($connection_host, $connection_port);

            $connect_args = array($connection_host, $connection_port, $timeout, null, $retry_interval, $read_timeout);
            $persistent_args = array($connection_host, $connection_port, $timeout, $persistent_id, $retry_interval, $read_timeout);
            if (!empty($connection_options)) {
                $connect_args[] = $connection_options;
                $persistent_args[] = $connection_options;
            }

            $connected = false;
            if ($use_persistent) {
                try {
                    $connected = (bool) call_user_func_array(array($redis, 'pconnect'), $persistent_args);
                } catch (Throwable $e) {
                    $connected = false;
                }
            }
            if (!$connected) {
                $connected = (bool) call_user_func_array(array($redis, 'connect'), $connect_args);
            }
            if (!$connected) {
                $GLOBALS['ucp_redis_connection_failed'] = true;
                return null;
            }

            if (defined('Redis::OPT_READ_TIMEOUT')
                && false === $redis->setOption(Redis::OPT_READ_TIMEOUT, $read_timeout)) {
                throw new RuntimeException('Redis read timeout configuration failed.');
            }

            if (defined('WP_REDIS_PASSWORD')) {
                $password = WP_REDIS_PASSWORD;
                $authenticated = true;
                if (is_array($password)) {
                    $credentials = array_values(array_filter($password, static function($value) {
                        return is_scalar($value) && '' !== (string) $value;
                    }));
                    if (!empty($credentials)) {
                        $authenticated = (bool) $redis->auth($credentials);
                    }
                } elseif (is_scalar($password) && '' !== (string) $password) {
                    if (defined('WP_REDIS_USERNAME') && '' !== (string) WP_REDIS_USERNAME) {
                        $authenticated = (bool) $redis->auth(array((string) WP_REDIS_USERNAME, (string) $password));
                    } else {
                        $authenticated = (bool) $redis->auth((string) $password);
                    }
                }
                if (!$authenticated) {
                    throw new RuntimeException('Redis authentication failed.');
                }
            }
            if (defined('WP_REDIS_DATABASE') && !$redis->select(max(0, (int) WP_REDIS_DATABASE))) {
                throw new RuntimeException('Redis database selection failed.');
            }
            if (false === $redis->ping()) {
                throw new RuntimeException('Redis ping failed.');
            }
            // Use PHP serialization so arrays/objects round-trip correctly.
            if (defined('Redis::OPT_SERIALIZER')) {
                $serializer = defined('Redis::SERIALIZER_IGBINARY') && extension_loaded('igbinary') ? Redis::SERIALIZER_IGBINARY : Redis::SERIALIZER_PHP;
                $redis->setOption(Redis::OPT_SERIALIZER, $serializer);
            }
            // Optional value compression (opt-in via WP_REDIS_COMPRESSION: zstd|lz4|lzf).
            // Off by default: switching compression on existing data requires a Redis
            // flush, so this must be a deliberate choice. Only applied when PhpRedis was
            // compiled with the requested algorithm.
            if (defined('WP_REDIS_COMPRESSION') && defined('Redis::OPT_COMPRESSION')) {
                $requested = strtolower((string) WP_REDIS_COMPRESSION);
                $map = array();
                if (defined('Redis::COMPRESSION_ZSTD')) { $map['zstd'] = Redis::COMPRESSION_ZSTD; }
                if (defined('Redis::COMPRESSION_LZ4')) { $map['lz4'] = Redis::COMPRESSION_LZ4; }
                if (defined('Redis::COMPRESSION_LZF')) { $map['lzf'] = Redis::COMPRESSION_LZF; }
                if (isset($map[$requested])) {
                    try {
                        $redis->setOption(Redis::OPT_COMPRESSION, $map[$requested]);
                    } catch (Throwable $e) {
                        // Algorithm not compiled in: leave values uncompressed.
                    }
                }
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
            return false;
        }
        try {
            $iterator = null;
            $pattern = (string) $prefix . '*';
            $scan_count = defined('WP_REDIS_SCAN_COUNT') ? max(10, min(2000, (int) WP_REDIS_SCAN_COUNT)) : 500;
            $max_rounds = defined('WP_REDIS_SCAN_MAX_ROUNDS') ? max(1, min(100000, (int) WP_REDIS_SCAN_MAX_ROUNDS)) : 1000;
            $max_keys = defined('WP_REDIS_SCAN_MAX_KEYS') ? max(250, (int) WP_REDIS_SCAN_MAX_KEYS) : 100000;
            $max_seconds = defined('WP_REDIS_SCAN_MAX_SECONDS') ? max(1.0, min(30.0, (float) WP_REDIS_SCAN_MAX_SECONDS)) : 5.0;
            $rounds = 0;
            $deleted_total = 0;
            $deadline = microtime(true) + $max_seconds;

            do {
                ++$rounds;
                if ($rounds > $max_rounds || microtime(true) >= $deadline) {
                    return false;
                }

                $keys = $redis->scan($iterator, $pattern, $scan_count);
                if (!is_array($keys) || empty($keys)) {
                    continue;
                }

                foreach (array_chunk($keys, 250) as $batch) {
                    if (microtime(true) >= $deadline || ($deleted_total + count($batch)) > $max_keys) {
                        return false;
                    }

                    $deleted = false;
                    if (method_exists($redis, 'unlink')) {
                        try {
                            $deleted = false !== $redis->unlink($batch);
                        } catch (Throwable $e) {
                            $deleted = false;
                        }
                    }
                    if (!$deleted && false === $redis->del($batch)) {
                        return false;
                    }
                    $deleted_total += count($batch);
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
        if (!ucp_redis_is_valid_key($key)) {
            $found = false;
            return false;
        }

        $cache_key = ucp_redis_key($key, $group);

        if (!$force && array_key_exists($cache_key, $GLOBALS['ucp_redis_cache_runtime'])) {
            $found = true;
            return ucp_redis_clone_value($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
        }

        if (ucp_redis_group_is_non_persistent($group)) {
            if (array_key_exists($cache_key, $GLOBALS['ucp_redis_cache_runtime'])) {
                $found = true;
                return ucp_redis_clone_value($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            }
            $found = false;
            return false;
        }
        if ($force) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
        }
        if (!ucp_redis_is_available()) {
            // A forced read must never fall back to a potentially stale runtime value.
            $found = false;
            return false;
        }

        try {
            $redis = ucp_redis_connection();
            $value = $redis->get($cache_key);
            $exists = false !== $value || (bool) $redis->exists($cache_key);
        } catch (Throwable $e) {
            $found = false;
            return false;
        }

        if (!$exists) {
            $found = false;
            return false;
        }

        $found = true;
        $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = ucp_redis_clone_value($value);
        return ucp_redis_clone_value($value);
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        if (!ucp_redis_is_valid_key($key)) {
            return false;
        }

        $cache_key = ucp_redis_key($key, $group);
        $data = ucp_redis_clone_value($data);

        if (ucp_redis_group_is_non_persistent($group)) {
            $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $data;
            return true;
        }
        if (!ucp_redis_is_available()) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }

        try {
            $redis = ucp_redis_connection();
            $expire = max(0, (int) $expire);
            $stored = $expire > 0
                ? (bool) $redis->setex($cache_key, $expire, $data)
                : (bool) $redis->set($cache_key, $data);
        } catch (Throwable $e) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }

        if (!$stored) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }

        $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_add')) {
    function wp_cache_add($key, $data, $group = '', $expire = 0) {
        if (!ucp_redis_is_valid_key($key) || (function_exists('wp_suspend_cache_addition') && wp_suspend_cache_addition())) {
            return false;
        }

        $cache_key = ucp_redis_key($key, $group);

        if (ucp_redis_group_is_non_persistent($group)) {
            if (array_key_exists($cache_key, $GLOBALS['ucp_redis_cache_runtime'])) {
                return false;
            }
            $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = ucp_redis_clone_value($data);
            return true;
        }
        if (!ucp_redis_is_available()) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
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
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }

        $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = ucp_redis_clone_value($data);
        return true;
    }
}

if (!function_exists('wp_cache_replace')) {
    function wp_cache_replace($key, $data, $group = '', $expire = 0) {
        if (!ucp_redis_is_valid_key($key)) {
            return false;
        }

        $cache_key = ucp_redis_key($key, $group);
        $data = ucp_redis_clone_value($data);

        if (ucp_redis_group_is_non_persistent($group)) {
            if (!array_key_exists($cache_key, $GLOBALS['ucp_redis_cache_runtime'])) {
                return false;
            }
            $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $data;
            return true;
        }
        if (!ucp_redis_is_available()) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }

        try {
            $redis = ucp_redis_connection();
            $expire = max(0, (int) $expire);
            $options = array('xx');
            if ($expire > 0) {
                $options['ex'] = $expire;
            }
            $stored = (bool) $redis->set($cache_key, $data, $options);
        } catch (Throwable $e) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }

        if (!$stored) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }
        $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        if (!ucp_redis_is_valid_key($key)) {
            return false;
        }

        $cache_key = ucp_redis_key($key, $group);

        if (ucp_redis_group_is_non_persistent($group)) {
            $deleted = array_key_exists($cache_key, $GLOBALS['ucp_redis_cache_runtime']);
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return $deleted;
        }
        if (!ucp_redis_is_available()) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }

        try {
            $redis = ucp_redis_connection();
            $deleted = (bool) $redis->del($cache_key);
        } catch (Throwable $e) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }

        // Backend access succeeded, so the in-request layer may now mirror its state.
        unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
        return $deleted;
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
        if (ucp_redis_group_is_non_persistent($group)) {
            ucp_redis_delete_runtime_by_prefix($prefix);
            return true;
        }
        $deleted = ucp_redis_delete_by_prefix($prefix);
        ucp_redis_delete_runtime_by_prefix($prefix);
        return $deleted;
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        // Only clear this installation's namespace, never the whole Redis database.
        $deleted = ucp_redis_delete_by_prefix('ucp:' . md5(ucp_redis_installation_salt()) . ':');
        wp_cache_flush_runtime();
        return $deleted;
    }
}

if (!function_exists('ucp_redis_update_counter')) {
    function ucp_redis_update_counter($cache_key, $offset, $decrement = false) {
        $redis = ucp_redis_connection();
        if (!$redis) {
            return false;
        }

        $offset = abs((int) $offset);
        $transactional = method_exists($redis, 'watch') && method_exists($redis, 'multi') && method_exists($redis, 'exec');
        $attempts = $transactional ? 3 : 1;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                if ($transactional) {
                    $redis->watch($cache_key);
                }

                $current = $redis->get($cache_key);
                if (false === $current && !$redis->exists($cache_key)) {
                    if ($transactional && method_exists($redis, 'unwatch')) {
                        $redis->unwatch();
                    }
                    return false;
                }

                $value = $decrement
                    ? max(0, (int) $current - $offset)
                    : (int) $current + $offset;

                $ttl = -1;
                if (method_exists($redis, 'pttl')) {
                    $remaining_ttl = $redis->pttl($cache_key);
                    if (is_int($remaining_ttl)) {
                        $ttl = $remaining_ttl;
                    }
                }
                if (-2 === $ttl) {
                    if ($transactional && method_exists($redis, 'unwatch')) {
                        $redis->unwatch();
                    }
                    return false;
                }

                if (!$transactional) {
                    if ($ttl >= 0 && method_exists($redis, 'psetex')) {
                        return $redis->psetex($cache_key, max(1, $ttl), $value) ? $value : false;
                    }
                    if ($ttl >= 0 && method_exists($redis, 'setex')) {
                        return $redis->setex($cache_key, max(1, (int) ceil($ttl / 1000)), $value) ? $value : false;
                    }
                    return $redis->set($cache_key, $value) ? $value : false;
                }

                $redis->multi();
                if ($ttl >= 0 && method_exists($redis, 'psetex')) {
                    $redis->psetex($cache_key, max(1, $ttl), $value);
                } elseif ($ttl >= 0 && method_exists($redis, 'setex')) {
                    $redis->setex($cache_key, max(1, (int) ceil($ttl / 1000)), $value);
                } else {
                    $redis->set($cache_key, $value);
                }
                $result = $redis->exec();
                if (false !== $result) {
                    return $value;
                }
            } catch (Throwable $e) {
                if (method_exists($redis, 'discard')) {
                    try {
                        $redis->discard();
                    } catch (Throwable $discard_error) {
                    }
                }
                if (method_exists($redis, 'unwatch')) {
                    try {
                        $redis->unwatch();
                    } catch (Throwable $unwatch_error) {
                    }
                }
                return false;
            }
        }

        if (method_exists($redis, 'unwatch')) {
            try {
                $redis->unwatch();
            } catch (Throwable $e) {
            }
        }
        return false;
    }
}

if (!function_exists('wp_cache_incr')) {
    function wp_cache_incr($key, $offset = 1, $group = '') {
        if (!ucp_redis_is_valid_key($key)) {
            return false;
        }

        $offset = abs((int) $offset);
        $cache_key = ucp_redis_key($key, $group);

        if (ucp_redis_group_is_non_persistent($group)) {
            $found = false;
            $current = wp_cache_get($key, $group, false, $found);
            if (!$found) {
                return false;
            }
            $value = (int) $current + $offset;
            $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $value;
            return $value;
        }
        if (!ucp_redis_is_available()) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }

        $value = ucp_redis_update_counter($cache_key, $offset, false);
        if (false === $value) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }
        $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $value;
        return $value;
    }
}

if (!function_exists('wp_cache_decr')) {
    function wp_cache_decr($key, $offset = 1, $group = '') {
        if (!ucp_redis_is_valid_key($key)) {
            return false;
        }

        $offset = abs((int) $offset);
        $cache_key = ucp_redis_key($key, $group);

        if (ucp_redis_group_is_non_persistent($group)) {
            $found = false;
            $current = wp_cache_get($key, $group, false, $found);
            if (!$found) {
                return false;
            }
            $value = max(0, (int) $current - $offset);
            $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $value;
            return $value;
        }
        if (!ucp_redis_is_available()) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }

        $value = ucp_redis_update_counter($cache_key, $offset, true);
        if (false === $value) {
            unset($GLOBALS['ucp_redis_cache_runtime'][$cache_key]);
            return false;
        }
        $GLOBALS['ucp_redis_cache_runtime'][$cache_key] = $value;
        return $value;
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
