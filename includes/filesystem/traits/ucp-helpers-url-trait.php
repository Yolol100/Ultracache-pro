<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_URL_Trait {
    public static function normalize_multiline($value) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $lines = array_filter(array_map('trim', $lines));
        return array_values(array_unique($lines));
    }

    protected static function compat_json_list($name) {
        $safe_name = preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
        $path = trailingslashit(UCP_PATH) . 'compat/' . $safe_name . '.json';
        if (!is_readable($path)) {
            return array();
        }
        $data = json_decode(self::read_file($path), true);
        if (!is_array($data)) {
            return array();
        }
        return array_values(array_filter(array_map('strval', $data), 'strlen'));
    }

    public static function query_key_matches($key, $patterns) {
        $key = sanitize_key((string) $key);
        foreach ((array) $patterns as $pattern) {
            $pattern = strtolower((string) $pattern);
            $pattern = preg_replace('/[^a-z0-9_\-*]/', '', $pattern);
            if ('' === $pattern) {
                continue;
            }
            if (substr($pattern, -1) === '*') {
                $prefix = substr($pattern, 0, -1);
                if ('' !== $prefix && 0 === strpos($key, $prefix)) {
                    return true;
                }
            }
            if ($key === $pattern) {
                return true;
            }
        }
        return false;
    }

    public static function cache_ignore_query_patterns() {
        return array_values(array_unique(array_filter(array_map('strval', (array) apply_filters('ucp_cache_ignore_query_params', self::compat_json_list('cache-ignore-query-params'))), 'strlen')));
    }

    public static function cache_include_query_patterns($extra = array()) {
        return array_values(array_unique(array_filter(array_map('strval', array_merge((array) $extra, (array) apply_filters('ucp_cache_include_query_params', self::compat_json_list('cache-include-query-params')))), 'strlen')));
    }

    public static function query_key_is_ignored_for_cache($key, $include_patterns = array()) {
        $key = sanitize_key((string) $key);
        if ('' === $key) {
            return false;
        }
        $include_patterns = self::cache_include_query_patterns($include_patterns);
        if (self::query_key_matches($key, $include_patterns)) {
            return false;
        }
        return self::query_key_matches($key, self::cache_ignore_query_patterns());
    }

    public static function strip_ignored_query_args_from_url($url, $include_patterns = array()) {
        $url = self::strict_local_url($url);
        if (!$url) {
            return '';
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['query'])) {
            return $url;
        }
        parse_str((string) $parts['query'], $args);
        if (!is_array($args) || empty($args)) {
            return $url;
        }
        $kept = array();
        foreach ($args as $key => $value) {
            $clean_key = sanitize_key((string) $key);
            if ('' === $clean_key || self::query_key_is_ignored_for_cache($clean_key, $include_patterns)) {
                continue;
            }
            $kept[$clean_key] = $value;
        }
        $query = !empty($kept) ? '?' . http_build_query($kept, '', '&', PHP_QUERY_RFC3986) : '';
        $path = isset($parts['path']) && '' !== $parts['path'] ? $parts['path'] : '/';
        return self::enforce_local_url($path . $query);
    }

    public static function normalized_cache_query($query_string) {
        $query_string = (string) $query_string;
        if ('' === $query_string) {
            return '';
        }
        parse_str($query_string, $query_args);
        if (!is_array($query_args) || empty($query_args)) {
            return '';
        }
        $ignore = self::cache_ignore_query_patterns();
        $include = self::cache_include_query_patterns();
        $normalized = array();
        foreach ($query_args as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }
            if (self::query_key_matches($key, $ignore) && !self::query_key_matches($key, $include)) {
                continue;
            }
            if (is_array($value)) {
                $value = array_map('sanitize_text_field', wp_unslash($value));
                sort($value);
            } else {
                $value = sanitize_text_field(wp_unslash((string) $value));
            }
            $normalized[$key] = $value;
        }
        if (empty($normalized)) {
            return '';
        }
        ksort($normalized);
        return http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
    }


    public static function validate_local_url_arg($value) {
        if ('' === (string) $value) {
            return true;
        }
        return '' !== self::strict_local_url((string) $value);
    }

    public static function wildcard_match($haystack, $pattern) {
        $haystack = (string) $haystack;
        $pattern = trim((string) $pattern);
        if ('' === $pattern) {
            return false;
        }
        if (false === strpos($pattern, '(.*)') && false === strpos($pattern, '*')) {
            return false !== stripos($haystack, $pattern);
        }
        $regex = preg_quote($pattern, '#');
        $regex = str_replace(array('\\(\\.\\*\\)', '\\*'), '.*', $regex);
        return 1 === preg_match('#' . $regex . '#i', $haystack);
    }


    public static function safe_regex_match($pattern, $subject, $max_length = 180) {
        $pattern = trim((string) $pattern);
        if ('' === $pattern || strlen($pattern) > absint($max_length)) {
            return false;
        }
        if (preg_match('/\([^)]*[+*][^)]*\)\s*(?:[+*]|\{)/', $pattern)) {
            return false;
        }
        $regex = '#' . str_replace('#', '\#', $pattern) . '#i';
        return 1 === @preg_match($regex, (string) $subject);
    }

    public static function current_url_path() {
        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $path = wp_parse_url($uri, PHP_URL_PATH);
        return $path ? trailingslashit($path) : '/';
    }

    public static function current_full_url() {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $uri = self::normalize_url_syntax((string) $uri);
        $parts = wp_parse_url($uri);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return self::enforce_local_url(home_url($path . $query));
    }

    public static function is_mobile_request() {
        return function_exists('wp_is_mobile') ? wp_is_mobile() : false;
    }

    public static function user_state_suffix() {
        $suffix = 'guest';
        if (UCP_Options::get('cache_mobile_separately') && self::is_mobile_request()) {
            $suffix .= '-mobile';
        }
        return $suffix;
    }

    public static function cache_key_for_url($url = '') {
        if (!$url) {
            $url = self::current_full_url();
        }
        $url = self::enforce_local_url($url);
        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? untrailingslashit($parts['path']) : '';
        $path = empty($path) ? 'home' : trim(str_replace('/', '-', $path), '-');
        $normalized_query = isset($parts['query']) ? self::normalized_cache_query($parts['query']) : '';
        $query = '' !== $normalized_query ? md5($normalized_query) : 'noq';
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : wp_parse_url(home_url(), PHP_URL_HOST);
        $host_key = $host ? md5($host) : 'nohost';
        return sanitize_file_name($host_key . '-' . $path . '-' . self::user_state_suffix() . '-' . $query);
    }

    public static function cache_file_path($url = '') {
        return UCP_CACHE_DIR . 'pages/' . self::cache_key_for_url($url) . '.html';
    }

    public static function normalize_domain_host($value) {
        $value = strtolower(trim((string) $value));
        if ('' === $value) {
            return '';
        }
        $value = preg_replace('#^https?://#i', '', $value);
        $value = preg_replace('/[\?#].*$/', '', $value);
        $value = preg_replace('#/.*$#', '', $value);
        $value = preg_replace('/:\d+$/', '', $value);
        $value = trim($value, '.: /');
        if ('' === $value || !preg_match('/^[a-z0-9.-]+$/', $value)) {
            return '';
        }
        return $value;
    }

    public static function get_first_cdn_host() {
        foreach (self::normalize_multiline(UCP_Options::get('cdn_cnames', '')) as $cname) {
            $host = self::normalize_domain_host($cname);
            if ('' !== $host) {
                return $host;
            }
        }
        return '';
    }

    public static function should_skip_cdn_url($url) {
        $url = (string) $url;
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $host = (string) wp_parse_url($url, PHP_URL_HOST);
        foreach (self::normalize_multiline(UCP_Options::get('cdn_exclude', '')) as $rule) {
            $rule = trim((string) $rule);
            if ('' === $rule) {
                continue;
            }
            if (self::wildcard_match($url, $rule) || self::wildcard_match($path, $rule) || ('' !== $host && self::wildcard_match($host, $rule))) {
                return true;
            }
        }
        return false;
    }

    public static function cdn_file_type_allows($url) {
        $mode = UCP_Options::get('cdn_file_types', 'all');
        $path = (string) wp_parse_url((string) $url, PHP_URL_PATH);
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ('' === $ext) {
            return false;
        }
        $css_js = array('css', 'js');
        $images = array('jpg','jpeg','png','gif','webp','avif','svg','ico');
        $fonts = array('woff','woff2','ttf','otf','eot');
        if ('css_js' === $mode) {
            return in_array($ext, $css_js, true);
        }
        if ('images' === $mode) {
            return in_array($ext, $images, true);
        }
        return in_array($ext, array_merge($css_js, $images, $fonts), true);
    }

    public static function local_path_from_url($url) {
        $url = html_entity_decode((string) $url);
        if (!self::is_local_url($url)) {
            return '';
        }
        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        if ('' === $path || !preg_match('~\.(?:css|js)$~i', $path)) {
            return '';
        }

        // map asset URLs by parsed path only. Stylesheet URLs commonly include
        // cache-busting query strings such as ?ver=...; including the query in a filesystem
        // path makes realpath() fail and causes false "Geen lokale CSS-inhoud gevonden" jobs.
        $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
        $content_path = wp_parse_url(content_url('/'), PHP_URL_PATH);
        $includes_path = wp_parse_url(includes_url('/'), PHP_URL_PATH);
        $home_path = '/' . trim((string) $home_path, '/') . '/';
        $content_path = '/' . trim((string) $content_path, '/') . '/';
        $includes_path = '/' . trim((string) $includes_path, '/') . '/';
        $request_path = '/' . ltrim($path, '/');

        $candidate = '';
        if (0 === strpos($request_path, $content_path)) {
            $relative = ltrim(substr($request_path, strlen($content_path)), '/');
            $candidate = WP_CONTENT_DIR . '/' . $relative;
        } elseif (0 === strpos($request_path, $includes_path)) {
            $relative = ltrim(substr($request_path, strlen($includes_path)), '/');
            $candidate = ABSPATH . WPINC . '/' . $relative;
        } elseif ('//' !== $home_path && 0 === strpos($request_path, $home_path)) {
            $relative = ltrim(substr($request_path, strlen($home_path)), '/');
            $candidate = ABSPATH . $relative;
        } else {
            $candidate = ABSPATH . ltrim($request_path, '/');
        }
        if (!$candidate) {
            return '';
        }
        $real = realpath($candidate);
        if (!$real || !is_file($real)) {
            return '';
        }
        $allowed_bases = array_filter(array_map('realpath', array(ABSPATH, WP_CONTENT_DIR, ABSPATH . WPINC)));
        foreach ($allowed_bases as $base) {
            $base = rtrim(wp_normalize_path($base), '/') . '/';
            if (0 === strpos(wp_normalize_path($real), $base)) {
                return $real;
            }
        }
        return '';
    }

    public static function normalize_url_syntax($url) {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        // Repair malformed scheme separators such as https:/example.com that can be produced by over-eager slash normalization.
        $url = preg_replace('#^(https?):/{1,}(?!/)#i', '$1://', $url);
        $url = preg_replace('#^(https?):/{3,}#i', '$1://', $url);

        return $url;
    }

    public static function is_local_url($url) {
        $url = self::normalize_url_syntax($url);
        $host = wp_parse_url($url, PHP_URL_HOST);
        $home = wp_parse_url(home_url(), PHP_URL_HOST);
        return !$host || strtolower((string) $host) === strtolower((string) $home);
    }

    public static function strict_local_url($url, $default = '') {
        $raw = trim((string) $url);
        if ('' === $raw) {
            $raw = (string) $default;
        }
        $raw = self::normalize_url_syntax($raw);
        if ('' === $raw) {
            return '';
        }

        // AI-PATCH: strict URL validation must reject foreign/unsafe schemes instead of silently converting them to local paths.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $raw) && !preg_match('#^https?://#i', $raw)) {
            return '';
        }

        if (0 === strpos($raw, '//')) {
            $scheme = wp_parse_url(home_url('/'), PHP_URL_SCHEME);
            $raw = ($scheme ? $scheme : 'https') . ':' . $raw;
        }

        $parts = wp_parse_url($raw);
        if (!is_array($parts)) {
            return '';
        }

        if (empty($parts['host'])) {
            $path = isset($parts['path']) ? (string) $parts['path'] : '/';
            $query = isset($parts['query']) ? '?' . (string) $parts['query'] : '';
            if ('' === $path) {
                $path = '/';
            }
            $raw = home_url('/' . ltrim($path, '/') . $query);
        }

        $raw = esc_url_raw($raw);
        if (!$raw || !self::is_local_url($raw) || !wp_http_validate_url($raw)) {
            return '';
        }

        return self::enforce_local_url($raw);
    }

    public static function enforce_local_url($url) {
        $url = esc_url_raw(self::normalize_url_syntax($url));
        if (!$url) {
            return home_url('/');
        }
        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        if (!self::is_local_url($url)) {
            return home_url($path . $query);
        }
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return home_url($path . $query);
        }
        return $parts['scheme'] . '://' . $parts['host'] . $path . $query;
    }

    public static function normalize_url($url) {
        return self::enforce_local_url($url);
    }

    public static function current_request_category() {
        if (function_exists('is_cart') && is_cart()) {
            return 'cart';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return 'account';
        }
        if (is_front_page()) {
            return 'front_page';
        }
        if (is_singular()) {
            return 'singular';
        }
        if (is_archive()) {
            return 'archive';
        }
        return 'generic';
    }

    public static function asset_rule_matches_current_request($rules_string) {
        $rules = self::normalize_multiline($rules_string);
        if (empty($rules)) {
            return false;
        }
        $url = self::current_full_url();
        $path = self::current_url_path();
        $category = self::current_request_category();
        foreach ($rules as $rule) {
            if (0 === strpos($rule, 'url:') && false !== strpos($url, substr($rule, 4))) {
                return true;
            }
            if (0 === strpos($rule, 'path:') && false !== strpos($path, substr($rule, 5))) {
                return true;
            }
            if (0 === strpos($rule, 'type:') && $category === substr($rule, 5)) {
                return true;
            }
        }
        return false;
    }

    public static function collect_preload_candidates() {
        $candidates = array();
        foreach (self::normalize_multiline(UCP_Options::get('preload_fonts', '')) as $font_url) {
            $font_url = esc_url_raw($font_url);
            if ($font_url) {
                $candidates[] = array('href' => $font_url, 'as' => 'font');
            }
        }
        $critical_url = self::file_url_from_path(self::get_critical_css_path());
        if ($critical_url && file_exists(self::get_critical_css_path())) {
            $candidates[] = array('href' => $critical_url, 'as' => 'style');
        }
        return $candidates;
    }
}
