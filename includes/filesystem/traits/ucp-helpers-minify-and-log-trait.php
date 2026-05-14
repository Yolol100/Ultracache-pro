<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_Minify_And_Log_Trait {
    public static function minify_css($content) {
        $content = preg_replace('!\s*?/\*.*?\*/\s*!s', '', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        $search = array('; ', ': ', ' {', '{ ', ', ', '} ', ';}', "\n", "\r", "\t");
        $replace = array(';', ':', '{', '{', ',', '}', '', '', '');
        return trim(str_replace($search, $replace, $content));
    }

    public static function minify_js($content) {
        // JavaScript cannot be safely minified with regex without risking valid strings, regex literals, source maps, or modern syntax.
        // Keep this runtime path behavior-preserving; serious minification should happen through a parser/build step.
        return trim((string) $content);
    }

    public static function get_used_css_path($url = '') {
        return UCP_CACHE_DIR . 'used-css/' . self::cache_key_for_url($url) . '.css';
    }

    public static function get_critical_css_path($url = '') {
        return UCP_CACHE_DIR . 'critical-css/' . self::cache_key_for_url($url) . '.css';
    }

    public static function has_persistent_object_cache() {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    public static function is_likely_cache_server_present() {
        return !empty($_SERVER['LITESPEED_CACHE']) || !empty($_SERVER['HTTP_X_LITE_SPEED_CACHE']) || !empty($_SERVER['HTTP_X_VARNISH']) || !empty($_SERVER['HTTP_X_CACHE']);
    }

    public static function log($message) {
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . "\n";
        self::append_file(UCP_CACHE_DIR . 'logs/events.log', $line);
    }

    public static function log_throttled($key, $message, $ttl = HOUR_IN_SECONDS) {
        $key = 'ucp_log_throttle_' . md5((string) $key);
        if (get_transient($key)) {
            return;
        }
        set_transient($key, 1, max(60, absint($ttl)));
        self::log($message);
    }

    public static function get_log_tail($lines = 50) {
        $file = UCP_CACHE_DIR . 'logs/events.log';
        if (!file_exists($file)) {
            return array();
        }
        $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($content)) {
            return array();
        }
        return array_slice($content, -1 * absint($lines));
    }
}
