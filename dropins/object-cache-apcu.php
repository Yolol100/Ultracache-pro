<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
/**
 * UltraCache Pro APCu Object Cache drop-in.
 */
if (!defined('ABSPATH')) {
    exit;
}

$GLOBALS['ucp_apcu_cache_global_groups'] = isset($GLOBALS['ucp_apcu_cache_global_groups']) && is_array($GLOBALS['ucp_apcu_cache_global_groups']) ? $GLOBALS['ucp_apcu_cache_global_groups'] : array();
$GLOBALS['ucp_apcu_cache_non_persistent_groups'] = isset($GLOBALS['ucp_apcu_cache_non_persistent_groups']) && is_array($GLOBALS['ucp_apcu_cache_non_persistent_groups']) ? $GLOBALS['ucp_apcu_cache_non_persistent_groups'] : array();
$GLOBALS['ucp_apcu_cache_runtime'] = isset($GLOBALS['ucp_apcu_cache_runtime']) && is_array($GLOBALS['ucp_apcu_cache_runtime']) ? $GLOBALS['ucp_apcu_cache_runtime'] : array();

if (!function_exists('ucp_apcu_normalize_group')) {
    function ucp_apcu_normalize_group($group) {
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

if (!function_exists('ucp_apcu_normalize_groups')) {
    function ucp_apcu_normalize_groups($groups) {
        $groups = is_array($groups) ? $groups : array($groups);
        $normalized = array();

        foreach ($groups as $group) {
            $group = ucp_apcu_normalize_group($group);
            if ('' !== $group) {
                $normalized[$group] = true;
            }
        }

        return $normalized;
    }
}

if (!function_exists('ucp_apcu_is_available')) {
    function ucp_apcu_is_available() {
        if (!function_exists('apcu_fetch') || !function_exists('apcu_store') || !filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if ('cli' === PHP_SAPI && !filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('ucp_apcu_group_is_global')) {
    function ucp_apcu_group_is_global($group) {
        $group = ucp_apcu_normalize_group($group);
        return !empty($GLOBALS['ucp_apcu_cache_global_groups'][$group]);
    }
}

if (!function_exists('ucp_apcu_group_is_non_persistent')) {
    function ucp_apcu_group_is_non_persistent($group) {
        $group = ucp_apcu_normalize_group($group);
        return !empty($GLOBALS['ucp_apcu_cache_non_persistent_groups'][$group]);
    }
}

if (!function_exists('ucp_apcu_site_segment')) {
    function ucp_apcu_site_segment($group) {
        if (ucp_apcu_group_is_global($group)) {
            return 'global';
        }

        return function_exists('get_current_blog_id') ? (string) max(0, (int) get_current_blog_id()) : '0';
    }
}

if (!function_exists('ucp_apcu_installation_salt')) {
    function ucp_apcu_installation_salt() {
        if (defined('WP_CACHE_KEY_SALT') && '' !== (string) WP_CACHE_KEY_SALT) {
            return (string) WP_CACHE_KEY_SALT;
        }

        return defined('ABSPATH') ? (string) ABSPATH : 'wordpress';
    }
}

if (!function_exists('ucp_apcu_prefix')) {
    function ucp_apcu_prefix($group = '') {
        return 'ucp:' . md5(ucp_apcu_installation_salt()) . ':' . ucp_apcu_site_segment($group) . ':';
    }
}

if (!function_exists('ucp_apcu_key')) {
    function ucp_apcu_key($key, $group = 'default') {
        $group = ucp_apcu_normalize_group($group);
        return ucp_apcu_prefix($group) . $group . ':' . md5((string) $key);
    }
}

if (!function_exists('ucp_apcu_is_valid_key')) {
    function ucp_apcu_is_valid_key($key) {
        return is_int($key) || (is_string($key) && '' !== trim($key));
    }
}

if (!function_exists('ucp_apcu_clone_value')) {
    function ucp_apcu_clone_value($value) {
        return is_object($value) ? clone $value : $value;
    }
}

if (!function_exists('ucp_apcu_remaining_ttl')) {
    /**
     * Return the remaining TTL for an APCu key without assuming that APCu uses
     * Unix timestamps internally. A zero return value means that the key is
     * intentionally persistent; null means that its expiry cannot be read.
     */
    function ucp_apcu_remaining_ttl($cache_key) {
        if (!function_exists('apcu_key_info')) {
            return null;
        }

        $info = apcu_key_info((string) $cache_key);
        if (!is_array($info)) {
            return null;
        }

        $ttl = isset($info['ttl']) ? (int) $info['ttl'] : 0;
        if ($ttl <= 0) {
            return 0;
        }

        if (!isset($info['creation_time']) || !function_exists('apcu_store') || !function_exists('apcu_delete')) {
            return null;
        }

        // APCu may use a monotonic clock. Read "now" from APCu itself instead
        // of comparing creation_time with PHP's wall clock.
        $probe_key = (string) $cache_key . ':ucp-ttl-probe:' . md5(uniqid('', true));
        if (!apcu_store($probe_key, 1, 1)) {
            return null;
        }

        $probe_info = apcu_key_info($probe_key);
        apcu_delete($probe_key);
        if (!is_array($probe_info) || !isset($probe_info['creation_time'])) {
            return null;
        }

        $elapsed = max(0, (int) $probe_info['creation_time'] - (int) $info['creation_time']);
        return max(1, $ttl - $elapsed);
    }
}

if (!function_exists('ucp_apcu_store_preserving_ttl')) {
    function ucp_apcu_store_preserving_ttl($cache_key, $value) {
        if (!function_exists('apcu_store')) {
            return false;
        }

        $ttl = ucp_apcu_remaining_ttl($cache_key);
        if (null === $ttl) {
            return false;
        }

        return apcu_store((string) $cache_key, $value, (int) $ttl);
    }
}

if (!function_exists('ucp_apcu_floor_counter')) {
    /**
     * Floor a negative APCu counter at zero without resetting its expiry.
     */
    function ucp_apcu_floor_counter($cache_key, $stored_value) {
        $stored_value = (int) $stored_value;
        if ($stored_value >= 0) {
            return $stored_value;
        }

        if (function_exists('apcu_cas') && function_exists('apcu_fetch')) {
            $expected = $stored_value;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                if (apcu_cas((string) $cache_key, $expected, 0)) {
                    return 0;
                }

                $success = false;
                $current = apcu_fetch((string) $cache_key, $success);
                if (!$success || !is_numeric($current)) {
                    return false;
                }

                $expected = (int) $current;
                if ($expected >= 0) {
                    return $expected;
                }
            }

            return false;
        }

        return ucp_apcu_store_preserving_ttl($cache_key, 0) ? 0 : false;
    }
}

if (!function_exists('ucp_apcu_delete_by_prefix')) {
    function ucp_apcu_delete_by_prefix($prefix) {
        if (!ucp_apcu_is_available() || !class_exists('APCUIterator')) {
            return false;
        }

        try {
            $deleted = true;
            $iterator = new APCUIterator('/^' . preg_quote((string) $prefix, '/') . '/');

            foreach ($iterator as $entry) {
                if (empty($entry['key']) || !apcu_delete((string) $entry['key'])) {
                    $deleted = false;
                }
            }

            return $deleted;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('ucp_apcu_delete_runtime_by_prefix')) {
    function ucp_apcu_delete_runtime_by_prefix($prefix) {
        foreach (array_keys($GLOBALS['ucp_apcu_cache_runtime']) as $cache_key) {
            if (0 === strpos($cache_key, (string) $prefix)) {
                unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
            }
        }
    }
}

if (!function_exists('wp_cache_init')) {
    function wp_cache_init() {
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
        $GLOBALS['ucp_apcu_cache_global_groups'] = array_merge(
            $GLOBALS['ucp_apcu_cache_global_groups'],
            ucp_apcu_normalize_groups($groups)
        );

        return true;
    }
}

if (!function_exists('wp_cache_add_non_persistent_groups')) {
    function wp_cache_add_non_persistent_groups($groups) {
        $GLOBALS['ucp_apcu_cache_non_persistent_groups'] = array_merge(
            $GLOBALS['ucp_apcu_cache_non_persistent_groups'],
            ucp_apcu_normalize_groups($groups)
        );

        return true;
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null) {
        if (!ucp_apcu_is_valid_key($key)) {
            $found = false;
            return false;
        }

        $cache_key = ucp_apcu_key($key, $group);

        if (!$force && array_key_exists($cache_key, $GLOBALS['ucp_apcu_cache_runtime'])) {
            $found = true;
            return ucp_apcu_clone_value($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
        }

        if (ucp_apcu_group_is_non_persistent($group)) {
            if (array_key_exists($cache_key, $GLOBALS['ucp_apcu_cache_runtime'])) {
                $found = true;
                return ucp_apcu_clone_value($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
            }
            $found = false;
            return false;
        }
        if ($force) {
            unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
        }
        if (!ucp_apcu_is_available()) {
            // A forced read must never fall back to a potentially stale runtime value.
            $found = false;
            return false;
        }

        $success = false;
        $value = apcu_fetch($cache_key, $success);
        $found = (bool) $success;

        if ($success) {
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = ucp_apcu_clone_value($value);
            return ucp_apcu_clone_value($value);
        }

        return false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        if (!ucp_apcu_is_valid_key($key)) {
            return false;
        }

        $cache_key = ucp_apcu_key($key, $group);
        $data = ucp_apcu_clone_value($data);

        if (ucp_apcu_group_is_non_persistent($group)) {
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $data;
            return true;
        }
        if (!ucp_apcu_is_available()) {
            unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
            return false;
        }

        if (!apcu_store($cache_key, $data, max(0, (int) $expire))) {
            unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
            return false;
        }

        $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_add')) {
    function wp_cache_add($key, $data, $group = '', $expire = 0) {
        if (!ucp_apcu_is_valid_key($key) || (function_exists('wp_suspend_cache_addition') && wp_suspend_cache_addition())) {
            return false;
        }

        $cache_key = ucp_apcu_key($key, $group);

        if (ucp_apcu_group_is_non_persistent($group)) {
            if (array_key_exists($cache_key, $GLOBALS['ucp_apcu_cache_runtime'])) {
                return false;
            }
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = ucp_apcu_clone_value($data);
            return true;
        }
        if (!ucp_apcu_is_available()) {
            unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
            return false;
        }

        if (!function_exists('apcu_add') || !apcu_add($cache_key, $data, max(0, (int) $expire))) {
            return false;
        }

        $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = ucp_apcu_clone_value($data);
        return true;
    }
}

if (!function_exists('wp_cache_replace')) {
    function wp_cache_replace($key, $data, $group = '', $expire = 0) {
        if (!ucp_apcu_is_valid_key($key)) {
            return false;
        }

        $found = false;
        wp_cache_get($key, $group, true, $found);

        return $found ? wp_cache_set($key, $data, $group, $expire) : false;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        if (!ucp_apcu_is_valid_key($key)) {
            return false;
        }

        $cache_key = ucp_apcu_key($key, $group);

        if (ucp_apcu_group_is_non_persistent($group)) {
            $deleted = array_key_exists($cache_key, $GLOBALS['ucp_apcu_cache_runtime']);
            unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
            return $deleted;
        }
        if (!ucp_apcu_is_available() || !function_exists('apcu_delete')) {
            unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
            return false;
        }

        try {
            $deleted = (bool) apcu_delete($cache_key);
        } catch (Throwable $e) {
            unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
            return false;
        }

        // Backend access succeeded, so the in-request layer may now mirror its state.
        unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
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
        $GLOBALS['ucp_apcu_cache_runtime'] = array();
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
        $group = ucp_apcu_normalize_group($group);
        $prefix = ucp_apcu_prefix($group) . $group . ':';
        if (ucp_apcu_group_is_non_persistent($group)) {
            ucp_apcu_delete_runtime_by_prefix($prefix);
            return true;
        }
        $deleted = ucp_apcu_delete_by_prefix($prefix);
        ucp_apcu_delete_runtime_by_prefix($prefix);
        return $deleted;
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        $deleted = ucp_apcu_delete_by_prefix('ucp:' . md5(ucp_apcu_installation_salt()) . ':');
        wp_cache_flush_runtime();
        return $deleted;
    }
}

if (!function_exists('wp_cache_incr')) {
    function wp_cache_incr($key, $offset = 1, $group = '') {
        if (!ucp_apcu_is_valid_key($key)) {
            return false;
        }

        $offset = abs((int) $offset);
        $cache_key = ucp_apcu_key($key, $group);
        if (ucp_apcu_group_is_non_persistent($group)) {
            $found = false;
            $current = wp_cache_get($key, $group, false, $found);
            if (!$found) {
                return false;
            }
            $value = (int) $current + $offset;
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
            return $value;
        }
        if (!ucp_apcu_is_available()) {
            unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
            return false;
        }

        $found = false;
        $current = wp_cache_get($key, $group, true, $found);
        if (!$found) {
            return false;
        }
        $value = (int) $current + $offset;

        if (function_exists('apcu_inc')) {
            $success = false;
            $stored_value = apcu_inc($cache_key, $offset, $success);
            if ($success) {
                $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = (int) $stored_value;
                return (int) $stored_value;
            }
        }

        // APCu increments only numeric values. Preserve WordPress' numeric cast
        // fallback without turning a temporary key into a permanent one.
        if (!is_int($current) && ucp_apcu_store_preserving_ttl($cache_key, $value)) {
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
            return $value;
        }

        unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
        return false;
    }
}

if (!function_exists('wp_cache_decr')) {
    function wp_cache_decr($key, $offset = 1, $group = '') {
        if (!ucp_apcu_is_valid_key($key)) {
            return false;
        }

        $offset = abs((int) $offset);
        $cache_key = ucp_apcu_key($key, $group);
        if (ucp_apcu_group_is_non_persistent($group)) {
            $found = false;
            $current = wp_cache_get($key, $group, false, $found);
            if (!$found) {
                return false;
            }
            $value = max(0, (int) $current - $offset);
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
            return $value;
        }
        if (!ucp_apcu_is_available()) {
            unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
            return false;
        }

        $found = false;
        $current = wp_cache_get($key, $group, true, $found);
        if (!$found) {
            return false;
        }
        $value = max(0, (int) $current - $offset);

        if (function_exists('apcu_dec')) {
            $success = false;
            $stored_value = apcu_dec($cache_key, $offset, $success);
            if ($success) {
                $value = (int) $stored_value;
                if ($value < 0) {
                    // Mirror WP core and the Redis drop-in while retaining the
                    // original expiry. CAS updates the existing APCu entry in
                    // place and therefore avoids re-storing it with TTL 0.
                    $value = ucp_apcu_floor_counter($cache_key, $value);
                    if (false === $value) {
                        unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
                        return false;
                    }
                }
                $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
                return $value;
            }
        }

        if (!is_int($current) && ucp_apcu_store_preserving_ttl($cache_key, $value)) {
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
            return $value;
        }

        unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);
        return false;
    }
}

if (!function_exists('wp_cache_switch_to_blog')) {
    function wp_cache_switch_to_blog($blog_id) {
        return true;
    }
}

if (!function_exists('wp_cache_supports')) {
    function wp_cache_supports($feature) {
        $feature = (string) $feature;
        if ('flush_group' === $feature) {
            return ucp_apcu_is_available() && class_exists('APCUIterator');
        }
        return in_array(
            $feature,
            array('add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple', 'flush_runtime'),
            true
        );
    }
}
