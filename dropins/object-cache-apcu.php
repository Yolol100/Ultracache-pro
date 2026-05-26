<?php
/**
 * UltraCache Pro APCu Object Cache drop-in.
 * Optional, self-hosted object cache for hosts with APCu enabled.
 */
if (!defined('ABSPATH')) { exit; }

function ucp_apcu_key($key, $group = 'default') {
    $blog = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
    return 'ucp:' . $blog . ':' . preg_replace('/[^A-Za-z0-9_.:-]/', '_', (string) $group) . ':' . md5((string) $key);
}
function wp_cache_get($key, $group = '', $force = false, &$found = null) {
    if (!function_exists('apcu_fetch')) { $found = false; return false; }
    $success = false;
    $value = apcu_fetch(ucp_apcu_key($key, $group ?: 'default'), $success);
    $found = (bool) $success;
    return $success ? $value : false;
}
function wp_cache_set($key, $data, $group = '', $expire = 0) {
    if (!function_exists('apcu_store')) { return false; }
    return apcu_store(ucp_apcu_key($key, $group ?: 'default'), $data, max(0, (int) $expire));
}
function wp_cache_add($key, $data, $group = '', $expire = 0) {
    if (!function_exists('apcu_add')) { return false; }
    return apcu_add(ucp_apcu_key($key, $group ?: 'default'), $data, max(0, (int) $expire));
}
function wp_cache_replace($key, $data, $group = '', $expire = 0) {
    $found = false; wp_cache_get($key, $group, false, $found);
    return $found ? wp_cache_set($key, $data, $group, $expire) : false;
}
function wp_cache_delete($key, $group = '') {
    return function_exists('apcu_delete') ? apcu_delete(ucp_apcu_key($key, $group ?: 'default')) : false;
}
function wp_cache_flush() { return function_exists('apcu_clear_cache') ? apcu_clear_cache() : false; }
function wp_cache_init() { return true; }
function wp_cache_close() { return true; }
function wp_cache_add_global_groups($groups) { return true; }
function wp_cache_add_non_persistent_groups($groups) { return true; }
function wp_cache_incr($key, $offset = 1, $group = '') { $found=false; $v=wp_cache_get($key,$group,false,$found); $v=$found?(int)$v:0; $v += (int)$offset; wp_cache_set($key,$v,$group); return $v; }
function wp_cache_decr($key, $offset = 1, $group = '') { $found=false; $v=wp_cache_get($key,$group,false,$found); $v=max(0,($found?(int)$v:0) - (int)$offset); wp_cache_set($key,$v,$group); return $v; }
