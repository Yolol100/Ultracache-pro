<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Canonical implementation service for drop-in and server-rule management. */
final class UCP_Dropin_Manager {
    use UCP_Helpers_Dropin_Trait {
        ensure_insert_with_markers as public;
        direct_cache_marker_block as public;
        remove_direct_cache_marker_block as public;
        normalize_htaccess_content as public;
    }

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected static function cache_ignore_query_patterns() {
        return UCP_URL_Validator::cache_ignore_query_patterns();
    }

    protected static function cache_include_query_patterns($extra = array()) {
        return UCP_URL_Validator::cache_include_query_patterns($extra);
    }

    protected static function direct_cache_bypass_cookie_fragments() {
        return UCP_URL_Validator::direct_cache_bypass_cookie_fragments();
    }

    protected static function direct_cache_server_rules($server = 'nginx') {
        return UCP_URL_Validator::direct_cache_server_rules($server);
    }

    protected static function ensure_cache_dirs($force = false) {
        return UCP_Filesystem_Service::ensure_cache_dirs($force);
    }

    protected static function is_root_htaccess_path($path) {
        return UCP_Filesystem_Service::is_root_htaccess_path($path);
    }

    protected static function log($message) {
        return UCP_Minify_Service::log($message);
    }

    protected static function log_throttled($key, $message, $ttl = HOUR_IN_SECONDS) {
        return UCP_Minify_Service::log_throttled($key, $message, $ttl);
    }

    protected static function mobile_user_agent_regex() {
        return UCP_URL_Validator::mobile_user_agent_regex();
    }

    protected static function normalize_multiline($value) {
        return UCP_URL_Validator::normalize_multiline($value);
    }

    protected static function private_dir_htaccess_rules() {
        return UCP_Filesystem_Service::private_dir_htaccess_rules();
    }

    protected static function read_file($path) {
        return UCP_Filesystem_Service::read_file($path);
    }

    protected static function read_root_htaccess() {
        return UCP_Filesystem_Service::read_root_htaccess();
    }

    protected static function root_htaccess_path() {
        return UCP_Filesystem_Service::root_htaccess_path();
    }

    protected static function safe_delete_file($file) {
        return UCP_Filesystem_Service::safe_delete_file($file);
    }

    protected static function write_file($path, $content) {
        return UCP_Filesystem_Service::write_file($path, $content);
    }

    protected static function write_file_atomic($path, $content) {
        return UCP_Filesystem_Service::write_file_atomic($path, $content);
    }

    protected static function write_root_htaccess($content) {
        return UCP_Filesystem_Service::write_root_htaccess($content);
    }

}
