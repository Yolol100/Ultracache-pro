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
        return '' === $group ? 'default' : preg_replace('/[^A-Za-z0-9_.:-]/', '_', $group);
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
        return function_exists('apcu_fetch') && function_exists('apcu_store') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN);
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

if (!function_exists('ucp_apcu_delete_by_prefix')) {
    function ucp_apcu_delete_by_prefix($prefix) {
        if (!ucp_apcu_is_available() || !class_exists('APCUIterator')) {
            return true;
        }

        $deleted = true;
        $iterator = new APCUIterator('/^' . preg_quote((string) $prefix, '/') . '/');

        foreach ($iterator as $entry) {
            if (empty($entry['key']) || !apcu_delete((string) $entry['key'])) {
                $deleted = false;
            }
        }

        return $deleted;
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
        $cache_key = ucp_apcu_key($key, $group);

        if (array_key_exists($cache_key, $GLOBALS['ucp_apcu_cache_runtime'])) {
            $found = true;
            return $GLOBALS['ucp_apcu_cache_runtime'][$cache_key];
        }

        if (ucp_apcu_group_is_non_persistent($group) || !ucp_apcu_is_available()) {
            $found = false;
            return false;
        }

        $success = false;
        $value = apcu_fetch($cache_key, $success);
        $found = (bool) $success;

        if ($success) {
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
            return $value;
        }

        return false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        $cache_key = ucp_apcu_key($key, $group);
        $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $data;

        if (ucp_apcu_group_is_non_persistent($group) || !ucp_apcu_is_available()) {
            return true;
        }

        return (bool) apcu_store($cache_key, $data, max(0, (int) $expire));
    }
}

if (!function_exists('wp_cache_add')) {
    function wp_cache_add($key, $data, $group = '', $expire = 0) {
        $cache_key = ucp_apcu_key($key, $group);

        if (array_key_exists($cache_key, $GLOBALS['ucp_apcu_cache_runtime'])) {
            return false;
        }

        if (ucp_apcu_group_is_non_persistent($group) || !ucp_apcu_is_available()) {
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $data;
            return true;
        }

        if (!function_exists('apcu_add') || !apcu_add($cache_key, $data, max(0, (int) $expire))) {
            return false;
        }

        $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_replace')) {
    function wp_cache_replace($key, $data, $group = '', $expire = 0) {
        $found = false;
        wp_cache_get($key, $group, false, $found);

        return $found ? wp_cache_set($key, $data, $group, $expire) : false;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        $cache_key = ucp_apcu_key($key, $group);
        unset($GLOBALS['ucp_apcu_cache_runtime'][$cache_key]);

        if (ucp_apcu_group_is_non_persistent($group) || !ucp_apcu_is_available()) {
            return true;
        }

        return function_exists('apcu_delete') ? (bool) apcu_delete($cache_key) : false;
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
        ucp_apcu_delete_runtime_by_prefix($prefix);

        return ucp_apcu_delete_by_prefix($prefix);
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        wp_cache_flush_runtime();
        return ucp_apcu_delete_by_prefix('ucp:' . md5(ucp_apcu_installation_salt()) . ':');
    }
}

if (!function_exists('wp_cache_incr')) {
    function wp_cache_incr($key, $offset = 1, $group = '') {
        $offset = abs((int) $offset);
        $cache_key = ucp_apcu_key($key, $group);
        $found = false;
        $current = wp_cache_get($key, $group, false, $found);

        if (!$found) {
            return false;
        }

        $value = (int) $current + $offset;

        if (ucp_apcu_group_is_non_persistent($group) || !ucp_apcu_is_available()) {
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
            return $value;
        }

        if (function_exists('apcu_inc')) {
            $success = false;
            $stored_value = apcu_inc($cache_key, $offset, $success);
            if ($success) {
                $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = (int) $stored_value;
                return (int) $stored_value;
            }
        }

        if (function_exists('apcu_store') && apcu_store($cache_key, $value, 0)) {
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
            return $value;
        }

        return false;
    }
}

if (!function_exists('wp_cache_decr')) {
    function wp_cache_decr($key, $offset = 1, $group = '') {
        $offset = abs((int) $offset);
        $cache_key = ucp_apcu_key($key, $group);
        $found = false;
        $current = wp_cache_get($key, $group, false, $found);

        if (!$found) {
            return false;
        }

        $value = max(0, (int) $current - $offset);

        if (ucp_apcu_group_is_non_persistent($group) || !ucp_apcu_is_available()) {
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
            return $value;
        }

        if (function_exists('apcu_dec')) {
            $success = false;
            $stored_value = apcu_dec($cache_key, $offset, $success);
            if ($success) {
                $value = (int) $stored_value;
                if ($value < 0) {
                    // Mirror WP core and the Redis drop-in: a decremented counter must not
                    // persist below zero. apcu_dec() does not floor, so re-store 0 instead of
                    // leaving a negative value that a later request would read back.
                    $value = 0;
                    if (function_exists('apcu_store')) {
                        apcu_store($cache_key, 0, 0);
                    }
                }
                $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
                return $value;
            }
        }

        if (function_exists('apcu_store') && apcu_store($cache_key, $value, 0)) {
            $GLOBALS['ucp_apcu_cache_runtime'][$cache_key] = $value;
            return $value;
        }

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
        return in_array(
            (string) $feature,
            array('add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple', 'flush_runtime', 'flush_group'),
            true
        );
    }
}
