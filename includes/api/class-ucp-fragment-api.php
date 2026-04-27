<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ucp_fragment_cache')) {
    function ucp_fragment_cache($key, $callback, $ttl = 300, $args = array()) {
        if (!class_exists('UCP_Fragment_Cache')) {
            return is_callable($callback) ? call_user_func($callback) : '';
        }
        return UCP_Fragment_Cache::render($key, $callback, $ttl, $args);
    }
}
