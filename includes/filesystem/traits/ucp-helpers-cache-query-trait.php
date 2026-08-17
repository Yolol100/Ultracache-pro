<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_Cache_Query_Trait {
    public static function compat_json_raw($name) {
        if (!is_scalar($name) && null !== $name) {
            $name = '';
        }
        return UCP_Compat_List_Loader::compat_json_raw($name);
    }

    protected static function compat_json_list($name) {
        if (!is_scalar($name) && null !== $name) {
            $name = '';
        }
        return UCP_Compat_List_Loader::compat_json_list($name);
    }

    private static function normalize_cache_query_patterns($patterns, $max_items = 500) {
        $max_items = max(1, min(1000, absint($max_items)));
        $normalized = array();
        foreach ((array) $patterns as $pattern) {
            if (!is_scalar($pattern)) {
                continue;
            }
            $pattern = strtolower(trim((string) $pattern));
            if ('' === $pattern || strlen($pattern) > 128 || false !== strpos($pattern, "\0")) {
                continue;
            }
            $pattern = UCP_Helpers::sanitize_preg_replace('/[^a-z0-9_\-*]/', '', $pattern);
            if ('' === $pattern) {
                continue;
            }
            if (false !== strpos($pattern, '*')) {
                $prefix = strtok($pattern, '*');
                $pattern = (false !== $prefix && '' !== $prefix) ? $prefix . '*' : '';
            }
            if ('' === $pattern) {
                continue;
            }
            $normalized[$pattern] = true;
            if (count($normalized) >= $max_items) {
                break;
            }
        }
        return array_keys($normalized);
    }

    public static function query_key_matches($key, $patterns) {
        if (!is_scalar($key)) {
            return false;
        }
        $raw_key = (string) $key;
        if ('' === $raw_key || strlen($raw_key) > 256 || false !== strpos($raw_key, "\0")) {
            return false;
        }
        $key = sanitize_key($raw_key);
        if ('' === $key) {
            return false;
        }
        foreach (self::normalize_cache_query_patterns($patterns) as $pattern) {
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
        $patterns = apply_filters('ucp_cache_ignore_query_params', self::compat_json_list('cache-ignore-query-params'));
        return self::normalize_cache_query_patterns($patterns);
    }

    public static function cache_include_query_patterns($extra = array()) {
        $filtered = apply_filters('ucp_cache_include_query_params', self::compat_json_list('cache-include-query-params'));
        $patterns = array_merge(is_array($extra) ? $extra : array(), is_array($filtered) ? $filtered : array());
        return self::normalize_cache_query_patterns($patterns);
    }

    protected static function cache_query_key_is_canonical($key) {
        if (!is_scalar($key)) {
            return false;
        }
        $raw_key = (string) $key;
        return '' !== $raw_key && strlen($raw_key) <= 256 && false === strpos($raw_key, "\0") && $raw_key === sanitize_key($raw_key);
    }

    protected static function cache_query_value_has_canonical_keys($value, $depth = 0, &$remaining = null) {
        if (null === $remaining) {
            $remaining = 100;
        }
        if ($depth > 4 || $remaining < 0) {
            return false;
        }
        if (!is_array($value)) {
            if (!is_scalar($value) && null !== $value) {
                return false;
            }
            $scalar = (string) $value;
            return strlen($scalar) <= 8192 && 0 === preg_match('/[\x00-\x1F\x7F]/', $scalar);
        }
        foreach ($value as $key => $item) {
            --$remaining;
            if ($remaining < 0 || (!is_int($key) && !self::cache_query_key_is_canonical($key))) {
                return false;
            }
            if (!self::cache_query_value_has_canonical_keys($item, $depth + 1, $remaining)) {
                return false;
            }
        }
        return true;
    }

    protected static function raw_query_keys_are_canonical($query_string) {
        if (!is_scalar($query_string)) {
            return false;
        }
        $query_string = (string) $query_string;
        if (strlen($query_string) > 8192 || preg_match('/[\x00-\x1F\x7F]/', $query_string)) {
            return false;
        }
        $pairs = preg_split('/[&;]/', $query_string, 102);
        if (!is_array($pairs) || count($pairs) > 100) {
            return false;
        }
        foreach ($pairs as $pair) {
            if ('' === $pair) {
                continue;
            }
            $raw_name = explode('=', $pair, 2)[0];
            if ('' === $raw_name || preg_match('/%(?![0-9A-Fa-f]{2})/', $raw_name)) {
                return false;
            }
            $decoded_name = rawurldecode(str_replace('+', ' ', $raw_name));
            if (strlen($decoded_name) > 256 || 1 !== preg_match('/^([a-z0-9_-]+)((?:\[[a-z0-9_-]*\])*)$/D', $decoded_name, $match)) {
                return false;
            }
            if (!self::cache_query_key_is_canonical($match[1])) {
                return false;
            }
            if ('' !== $match[2]) {
                preg_match_all('/\[([^]]*)\]/', $match[2], $segments);
                if (empty($segments[1]) || count($segments[1]) > 4) {
                    return false;
                }
                foreach ($segments[1] as $segment) {
                    if ('' === $segment || ctype_digit($segment)) {
                        continue;
                    }
                    if (!self::cache_query_key_is_canonical($segment)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    public static function query_key_is_ignored_for_cache($key, $include_patterns = array()) {
        if (!is_scalar($key)) {
            return false;
        }
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

    public static function query_string_is_cacheable($query_string, $allow_list_enabled, $include_patterns = array()) {
        if (!is_scalar($query_string)) {
            return false;
        }
        $query_string = (string) $query_string;
        if ('' === trim($query_string)) {
            return true;
        }
        if (!self::raw_query_keys_are_canonical($query_string)) {
            return false;
        }
        parse_str($query_string, $query_args);
        if (!is_array($query_args) || empty($query_args)) {
            return true;
        }
        $remaining = 100;
        $include_patterns = self::cache_include_query_patterns($include_patterns);
        foreach ($query_args as $key => $value) {
            if (!self::cache_query_key_is_canonical($key) || !self::cache_query_value_has_canonical_keys($value, 0, $remaining)) {
                return false;
            }
            $key = (string) $key;
            if (self::query_key_is_ignored_for_cache($key, $include_patterns)) {
                continue;
            }
            if (!$allow_list_enabled || !self::query_key_matches($key, $include_patterns)) {
                return false;
            }
        }
        return true;
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
        if (!self::raw_query_keys_are_canonical((string) $parts['query'])) {
            return $url;
        }
        parse_str((string) $parts['query'], $args);
        if (!is_array($args) || empty($args)) {
            return $url;
        }
        $remaining = 100;
        $kept = array();
        foreach ($args as $key => $value) {
            if (!self::cache_query_key_is_canonical($key) || !self::cache_query_value_has_canonical_keys($value, 0, $remaining)) {
                return $url;
            }
            $clean_key = (string) $key;
            if (self::query_key_is_ignored_for_cache($clean_key, $include_patterns)) {
                continue;
            }
            $kept[$clean_key] = $value;
        }
        $query = !empty($kept) ? '?' . http_build_query($kept, '', '&', PHP_QUERY_RFC3986) : '';
        $path = isset($parts['path']) && '' !== $parts['path'] ? $parts['path'] : '/';
        return self::enforce_local_url($path . $query);
    }

    protected static function normalize_cache_query_value($value, $depth = 0, &$remaining = null) {
        if (!is_scalar($depth) && null !== $depth) {
            $depth = 0;
        }
        if (null !== $remaining && !is_scalar($remaining)) {
            return false;
        }
        if (null === $remaining) {
            $remaining = 100;
        }
        if ($depth > 4 || $remaining < 0) {
            return false;
        }
        if (!is_array($value)) {
            if (!is_scalar($value) && null !== $value) {
                return false;
            }
            $value = (string) wp_unslash($value);
            if (strlen($value) > 8192 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                return false;
            }
            return $value;
        }

        $normalized = array();
        foreach ($value as $key => $item) {
            --$remaining;
            if ($remaining < 0 || (!is_int($key) && !self::cache_query_key_is_canonical($key))) {
                return false;
            }
            $clean_key = is_int($key) ? $key : (string) $key;
            $normalized_item = self::normalize_cache_query_value($item, $depth + 1, $remaining);
            if (false === $normalized_item) {
                return false;
            }
            $normalized[$clean_key] = $normalized_item;
        }
        if (!empty($normalized)) {
            ksort($normalized);
        }
        return $normalized;
    }

    public static function normalized_cache_query($query_string, $allow_list_enabled = null, $include_patterns = null) {
        if (null === $query_string) {
            return '';
        }
        if (!is_scalar($query_string)) {
            return 'invalid-' . hash('sha256', gettype($query_string));
        }
        $query_string = (string) $query_string;
        if ('' === $query_string) {
            return '';
        }
        if (!self::raw_query_keys_are_canonical($query_string)) {
            return 'invalid-' . hash('sha256', $query_string);
        }
        parse_str($query_string, $query_args);
        if (!is_array($query_args) || empty($query_args)) {
            return '';
        }
        if (null === $allow_list_enabled) {
            $allow_list_enabled = class_exists('UCP_Options') && !empty(UCP_Options::get('cache_query_strings'));
        }
        if (null === $include_patterns) {
            $include_patterns = class_exists('UCP_Options')
                ? self::normalize_multiline(UCP_Options::get('cache_query_string_inclusions', ''))
                : array();
        }
        $ignore = self::cache_ignore_query_patterns();
        $include = self::cache_include_query_patterns($include_patterns);
        $remaining = 100;
        $normalized = array();
        foreach ($query_args as $key => $value) {
            if (!self::cache_query_key_is_canonical($key) || !self::cache_query_value_has_canonical_keys($value, 0, $remaining)) {
                return 'invalid-' . hash('sha256', $query_string);
            }
            $key = (string) $key;
            if (self::query_key_matches($key, $ignore) && !self::query_key_matches($key, $include)) {
                continue;
            }
            if (!$allow_list_enabled || !self::query_key_matches($key, $include)) {
                continue;
            }
            $normalized_value = self::normalize_cache_query_value($value);
            if (false === $normalized_value) {
                return 'invalid-' . hash('sha256', $query_string);
            }
            $normalized[$key] = $normalized_value;
        }
        if (empty($normalized)) {
            return '';
        }
        ksort($normalized);
        return http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
    }
}
