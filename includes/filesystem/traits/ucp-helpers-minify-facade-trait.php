<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP helper symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Backward-compatible static wrappers for the extracted service implementation. */
trait UCP_Helpers_Minify_Facade_Trait {
    public static function minify_css($content) {
        return UCP_Minify_Service::minify_css($content);
    }

    public static function minify_js($content) {
        return UCP_Minify_Service::minify_js($content);
    }

    public static function get_used_css_path($url = '') {
        return UCP_Minify_Service::get_used_css_path($url);
    }

    public static function get_critical_css_path($url = '') {
        return UCP_Minify_Service::get_critical_css_path($url);
    }

    public static function css_artifact_key_for_url($url = '') {
        return UCP_Minify_Service::css_artifact_key_for_url($url);
    }

    public static function has_persistent_object_cache() {
        return UCP_Minify_Service::has_persistent_object_cache();
    }

    public static function is_likely_cache_server_present() {
        return UCP_Minify_Service::is_likely_cache_server_present();
    }

    public static function redact_log_text($message) {
        return UCP_Minify_Service::redact_log_text($message);
    }

    public static function redact_log_url($url) {
        return UCP_Minify_Service::redact_log_url($url);
    }

    protected static function is_sensitive_log_url_path($path) {
        return UCP_Minify_Service::is_sensitive_log_url_path($path);
    }

    protected static function redact_log_url_callback($matches) {
        return UCP_Minify_Service::redact_log_url_callback($matches);
    }

    public static function log($message) {
        return UCP_Minify_Service::log($message);
    }

    protected static function rotate_legacy_log_if_needed($file) {
        return UCP_Minify_Service::rotate_legacy_log_if_needed($file);
    }

    public static function log_throttled($key, $message, $ttl = HOUR_IN_SECONDS) {
        return UCP_Minify_Service::log_throttled($key, $message, $ttl);
    }

    public static function read_file_head($file, $max_bytes = 65536) {
        return UCP_Minify_Service::read_file_head($file, $max_bytes);
    }

    public static function read_file_tail_lines($file, $lines = 50, $max_read = 0) {
        return UCP_Minify_Service::read_file_tail_lines($file, $lines, $max_read);
    }

    public static function get_log_tail($lines = 50) {
        if (!is_scalar($lines) && null !== $lines) {
            $lines = 50;
        }
        return UCP_Minify_Service::get_log_tail($lines);
    }

}
