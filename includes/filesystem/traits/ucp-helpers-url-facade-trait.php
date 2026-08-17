<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP helper symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Backward-compatible static wrappers for the extracted service implementation. */
trait UCP_Helpers_URL_Facade_Trait {
    public static function normalize_multiline($value) {
        return UCP_URL_Validator::normalize_multiline($value);
    }

    public static function validate_public_https_url($url, $opts = array()) {
        return UCP_URL_Validator::validate_public_https_url($url, $opts);
    }

    public static function host_resolves_to_public_ip($host) {
        return UCP_URL_Validator::host_resolves_to_public_ip($host);
    }

    public static function default_remote_args($overrides = array()) {
        return UCP_URL_Validator::default_remote_args($overrides);
    }

    public static function bounded_response_body($body, $max_bytes, $min_bytes = 1) {
        if (!is_scalar($max_bytes) && null !== $max_bytes) {
            $max_bytes = 0;
        }
        if (!is_scalar($min_bytes) && null !== $min_bytes) {
            $min_bytes = 1;
        }
        return UCP_URL_Validator::bounded_response_body($body, $max_bytes, $min_bytes);
    }

    public static function bounded_remote_response_body($response, $max_bytes, $min_bytes = 1) {
        if (!is_scalar($max_bytes) && null !== $max_bytes) {
            $max_bytes = 0;
        }
        if (!is_scalar($min_bytes) && null !== $min_bytes) {
            $min_bytes = 1;
        }
        return UCP_URL_Validator::bounded_remote_response_body($response, $max_bytes, $min_bytes);
    }

    public static function uploads_baseurl_relative() {
        return UCP_URL_Validator::uploads_baseurl_relative();
    }

    public static function uploads_url_to_path($url) {
        return UCP_URL_Validator::uploads_url_to_path($url);
    }

    public static function uploads_path_to_url($path) {
        return UCP_URL_Validator::uploads_path_to_url($path);
    }

    public static function validate_local_url_arg($value) {
        return UCP_URL_Validator::validate_local_url_arg($value);
    }

    public static function wildcard_match($haystack, $pattern) {
        return UCP_URL_Validator::wildcard_match($haystack, $pattern);
    }

    public static function safe_regex_match($pattern, $subject, $max_length = 180) {
        if (!is_scalar($max_length) && null !== $max_length) {
            $max_length = 180;
        }
        return UCP_URL_Validator::safe_regex_match($pattern, $subject, $max_length);
    }

    public static function current_url_path() {
        return UCP_URL_Validator::current_url_path();
    }

    public static function current_full_url() {
        return UCP_URL_Validator::current_full_url();
    }

    public static function normalize_domain_host($value) {
        return UCP_URL_Validator::normalize_domain_host($value);
    }

    public static function normalize_url_syntax($url) {
        return UCP_URL_Validator::normalize_url_syntax($url);
    }

    public static function is_local_url($url) {
        return UCP_URL_Validator::is_local_url($url);
    }

    public static function strict_local_url($url, $default = '') {
        return UCP_URL_Validator::strict_local_url($url, $default);
    }

    public static function normalize_local_url_list($urls) {
        return UCP_URL_Validator::normalize_local_url_list($urls);
    }

    public static function enforce_local_url($url) {
        return UCP_URL_Validator::enforce_local_url($url);
    }

    public static function normalize_url($url) {
        return UCP_URL_Validator::normalize_url($url);
    }

    public static function compat_json_raw($name) {
        if (!is_scalar($name) && null !== $name) {
            $name = '';
        }
        return UCP_URL_Validator::compat_json_raw($name);
    }

    protected static function compat_json_list($name) {
        return UCP_URL_Validator::compat_json_list($name);
    }

    public static function query_key_matches($key, $patterns) {
        return UCP_URL_Validator::query_key_matches($key, $patterns);
    }

    public static function cache_ignore_query_patterns() {
        return UCP_URL_Validator::cache_ignore_query_patterns();
    }

    public static function cache_include_query_patterns($extra = array()) {
        return UCP_URL_Validator::cache_include_query_patterns($extra);
    }

    public static function query_key_is_ignored_for_cache($key, $include_patterns = array()) {
        return UCP_URL_Validator::query_key_is_ignored_for_cache($key, $include_patterns);
    }

    public static function query_string_is_cacheable($query_string, $allow_list_enabled, $include_patterns = array()) {
        return UCP_URL_Validator::query_string_is_cacheable($query_string, $allow_list_enabled, $include_patterns);
    }

    public static function strip_ignored_query_args_from_url($url, $include_patterns = array()) {
        return UCP_URL_Validator::strip_ignored_query_args_from_url($url, $include_patterns);
    }

    protected static function normalize_cache_query_value($value) {
        return UCP_URL_Validator::normalize_cache_query_value($value);
    }

    public static function normalized_cache_query($query_string, $allow_list_enabled = null, $include_patterns = null) {
        return UCP_URL_Validator::normalized_cache_query($query_string, $allow_list_enabled, $include_patterns);
    }

    public static function is_mobile_request() {
        return UCP_URL_Validator::is_mobile_request();
    }

    public static function mobile_user_agent_regex() {
        return UCP_URL_Validator::mobile_user_agent_regex();
    }

    public static function user_state_suffix() {
        return UCP_URL_Validator::user_state_suffix();
    }

    public static function cache_vary_suffix() {
        return UCP_URL_Validator::cache_vary_suffix();
    }

    public static function cache_path_slug($raw_path) {
        return UCP_URL_Validator::cache_path_slug($raw_path);
    }

    public static function normalize_host($host) {
        return UCP_URL_Validator::normalize_host($host);
    }

    public static function cache_key_for_url($url = '') {
        return UCP_URL_Validator::cache_key_for_url($url);
    }

    public static function cache_file_path($url = '') {
        return UCP_URL_Validator::cache_file_path($url);
    }

    public static function direct_cache_file_path($url = '') {
        return UCP_URL_Validator::direct_cache_file_path($url);
    }

    public static function direct_cache_bypass_cookie_fragments() {
        return UCP_URL_Validator::direct_cache_bypass_cookie_fragments();
    }

    protected static function direct_cache_bypass_cookie_pattern() {
        return UCP_URL_Validator::direct_cache_bypass_cookie_pattern();
    }

    public static function direct_cache_server_rules($server = 'nginx') {
        return UCP_URL_Validator::direct_cache_server_rules($server);
    }

    public static function current_request_category() {
        return UCP_URL_Validator::current_request_category();
    }

    public static function asset_rule_matches_current_request($rules_string) {
        return UCP_URL_Validator::asset_rule_matches_current_request($rules_string);
    }

    public static function get_first_cdn_host() {
        return UCP_URL_Validator::get_first_cdn_host();
    }

    public static function should_skip_cdn_url($url) {
        return UCP_URL_Validator::should_skip_cdn_url($url);
    }

    public static function cdn_file_type_allows($url) {
        return UCP_URL_Validator::cdn_file_type_allows($url);
    }

    public static function local_path_from_url($url, $allowed_extensions = array('css', 'js')) {
        return UCP_URL_Validator::local_path_from_url($url, $allowed_extensions);
    }

    public static function collect_preload_candidates() {
        return UCP_URL_Validator::collect_preload_candidates();
    }

}
