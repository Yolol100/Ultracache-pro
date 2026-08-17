<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Canonical implementation service for URL validation, cache-key and CDN URL operations. */
final class UCP_URL_Validator {
    use UCP_Helpers_URL_Trait {
        compat_json_list as public;
        normalize_cache_query_value as public;
        direct_cache_bypass_cookie_pattern as public;
    }

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected static function file_url_from_path($path) {
        return UCP_Filesystem_Service::file_url_from_path($path);
    }

    protected static function get_critical_css_path($url = '') {
        return UCP_Minify_Service::get_critical_css_path($url);
    }

}
