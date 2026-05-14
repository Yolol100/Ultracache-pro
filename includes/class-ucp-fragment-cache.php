<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Fragment_Cache {
    const GROUP = 'ucp_fragment';

    public function __construct() {
        add_action('init', array($this, 'register_shortcode'));
    }

    public function register_shortcode() {
        add_shortcode('ucp_fragment_cache_status', array($this, 'status_shortcode'));
    }

    public function status_shortcode() {
        if (empty(UCP_Options::get('enable_fragment_cache'))) {
            return '';
        }
        return esc_html__('Fragment cache is actief.', 'ultracache-pro');
    }

    public static function key($key) {
        return 'ucp_fragment_' . md5((string) $key);
    }

    public static function get($key) {
        if (empty(UCP_Options::get('enable_fragment_cache'))) {
            return false;
        }
        return get_transient(self::key($key));
    }

    public static function set($key, $value, $ttl = null) {
        if (empty(UCP_Options::get('enable_fragment_cache'))) {
            return false;
        }
        $ttl = null === $ttl ? absint(UCP_Options::get('fragment_cache_ttl', HOUR_IN_SECONDS)) : absint($ttl);
        return set_transient(self::key($key), $value, max(MINUTE_IN_SECONDS, $ttl));
    }

    public static function remember($key, $callback, $ttl = null) {
        $cached = self::get($key);
        if (false !== $cached) {
            return $cached;
        }
        if (!is_callable($callback)) {
            return '';
        }
        $value = call_user_func($callback);
        self::set($key, $value, $ttl);
        return $value;
    }
}

if (!function_exists('ucp_fragment_cache')) {
    function ucp_fragment_cache($key, $callback, $ttl = null) {
        return UCP_Fragment_Cache::remember($key, $callback, $ttl);
    }
}
