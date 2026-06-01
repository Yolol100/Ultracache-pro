<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitizes CWV/LCP beacon and profile payload fragments.
 *
 * Centralizing this keeps the public beacon callbacks, browser-scan imports, and
 * stored LCP profile lookups on the same URL/type/selector rules.
 */
final class UCP_CWV_LCP_Sanitizer {
    /**
     * @param string $scheme URL scheme.
     * @return int
     */
    public static function default_port_for_scheme($scheme) {
        if ('https' === $scheme) {
            return 443;
        }

        if ('http' === $scheme) {
            return 80;
        }

        return 0;
    }

    /**
     * @param mixed $url Raw browser header URL.
     * @return bool
     */
    public static function is_local_header_url($url) {
        $url = trim((string) $url);
        return '' === $url || self::is_same_origin_url($url);
    }

    /**
     * @param mixed $url Raw URL.
     * @return string
     */
    public static function sanitize_local_url_param($url) {
        $url = trim((string) $url);
        if ('' === $url || strlen($url) > 2048) {
            return '';
        }

        $absolute = UCP_Helpers::strict_local_url($url);
        if ('' === $absolute || !self::is_local_header_url($absolute)) {
            return '';
        }

        return $absolute;
    }

    /**
     * @param mixed $json Raw JSON string or array.
     * @return string JSON encoded safe metadata.
     */
    public static function sanitize_element_json($json) {
        $raw = is_array($json) ? wp_json_encode($json) : (string) $json;

        if (!is_string($raw) || '' === $raw || strlen($raw) > 2048) {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }

        $allowed = array();
        foreach (array('tag', 'id', 'class', 'selector', 'sizes', 'type') as $key) {
            if (isset($decoded[$key])) {
                $allowed[$key] = substr(sanitize_text_field((string) $decoded[$key]), 0, 240);
            }
        }
        if (isset($decoded['url'])) {
            $allowed['url'] = self::sanitize_resource_url((string) $decoded['url']);
        }
        if (isset($decoded['srcset'])) {
            $allowed['srcset'] = self::sanitize_srcset((string) $decoded['srcset']);
        }
        if (!empty($decoded['background'])) {
            $allowed['background'] = 1;
        }

        return $allowed ? wp_json_encode($allowed) : '';
    }

    /**
     * @param mixed $element Raw decoded element metadata.
     * @return array<string,mixed>
     */
    public static function sanitize_element_array($element) {
        $element = is_array($element) ? $element : array();
        $allowed = array();
        foreach (array('tag', 'id', 'class', 'selector', 'sizes', 'type') as $key) {
            if (isset($element[$key])) {
                $allowed[$key] = substr(sanitize_text_field((string) $element[$key]), 0, 240);
            }
        }
        if (isset($element['url'])) {
            $allowed['url'] = self::sanitize_resource_url((string) $element['url']);
        }
        if (!empty($element['background'])) {
            $allowed['background'] = 1;
        }

        return $allowed;
    }

    /**
     * @param string $url Raw resource URL.
     * @return string
     */
    public static function sanitize_resource_url($url) {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        if (class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'normalize_url_syntax')) {
            $url = UCP_Helpers::normalize_url_syntax($url);
        }

        $absolute = wp_parse_url($url, PHP_URL_HOST) ? $url : home_url('/' . ltrim($url, '/'));
        $absolute = esc_url_raw($absolute);
        if ('' === $absolute) {
            return '';
        }

        if (!self::is_resource_origin_allowed($absolute)) {
            return '';
        }

        return $absolute;
    }

    /**
     * @param string $url Raw page URL.
     * @return string
     */
    public static function sanitize_page_url($url) {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }
        $absolute = wp_parse_url($url, PHP_URL_HOST) ? esc_url_raw($url) : esc_url_raw(home_url('/' . ltrim($url, '/')));
        if ('' === $absolute || !self::is_same_origin_url($absolute)) {
            return '';
        }
        return $absolute;
    }

    /**
     * @param string $url Absolute URL.
     * @return bool
     */
    public static function is_same_origin_url($url) {
        $url_parts = wp_parse_url($url);
        $home_parts = wp_parse_url(home_url('/'));

        if (!is_array($url_parts) || !is_array($home_parts)) {
            return false;
        }

        $url_host = isset($url_parts['host']) ? strtolower((string) $url_parts['host']) : '';
        $home_host = isset($home_parts['host']) ? strtolower((string) $home_parts['host']) : '';
        $url_scheme = isset($url_parts['scheme']) ? strtolower((string) $url_parts['scheme']) : '';
        $home_scheme = isset($home_parts['scheme']) ? strtolower((string) $home_parts['scheme']) : '';
        $url_port = isset($url_parts['port']) ? absint($url_parts['port']) : self::default_port_for_scheme($url_scheme);
        $home_port = isset($home_parts['port']) ? absint($home_parts['port']) : self::default_port_for_scheme($home_scheme);

        return '' !== $url_host
            && '' !== $home_host
            && '' !== $url_scheme
            && '' !== $home_scheme
            && $url_host === $home_host
            && $url_scheme === $home_scheme
            && $url_port === $home_port;
    }

    /**
     * @param string $url Absolute URL.
     * @return bool
     */
    public static function is_resource_origin_allowed($url) {
        if (self::is_same_origin_url($url)) {
            return true;
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ('' === $host || preg_match('/[^a-z0-9\.\-]/i', $host)) {
            return false;
        }
        $allowed = array();
        if (class_exists('UCP_Options') && class_exists('UCP_Helpers')) {
            $allowed = UCP_Helpers::normalize_multiline((string) UCP_Options::get('lcp_profile_allowed_hosts', ''));
        }
        $allowed = apply_filters('ucp_lcp_profile_allowed_resource_hosts', $allowed);
        foreach ((array) $allowed as $allowed_host) {
            $allowed_host = strtolower(trim((string) $allowed_host));
            $allowed_host = preg_replace('#^https?://#', '', $allowed_host);
            $allowed_host = preg_replace('#/.*$#', '', $allowed_host);
            if ('' !== $allowed_host && $host === $allowed_host) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $srcset Raw srcset.
     * @return string
     */
    public static function sanitize_srcset($srcset) {
        $srcset = substr(sanitize_textarea_field((string) $srcset), 0, 1200);
        if ('' === trim($srcset)) {
            return '';
        }

        $safe = array();
        foreach (explode(',', $srcset) as $candidate) {
            $candidate = trim($candidate);
            if ('' === $candidate) {
                continue;
            }
            $parts = preg_split('/\s+/', $candidate, 2);
            $url = isset($parts[0]) ? self::sanitize_resource_url($parts[0]) : '';
            if ('' === $url) {
                continue;
            }
            $descriptor = isset($parts[1]) ? preg_replace('/[^0-9\.wx\s-]/i', '', (string) $parts[1]) : '';
            $safe[] = trim($url . ('' !== trim((string) $descriptor) ? ' ' . trim((string) $descriptor) : ''));
        }

        return substr(implode(', ', $safe), 0, 1200);
    }

    /**
     * @param string $type Type.
     * @return string
     */
    public static function normalize_type($type) {
        $type = sanitize_key((string) $type);
        if ('background' === $type) {
            $type = 'background-image';
        }
        if ('video' === $type || 'poster' === $type) {
            $type = 'video-poster';
        }
        return in_array($type, array('image', 'background-image', 'text', 'video-poster'), true) ? $type : '';
    }

    /**
     * @param string $selector Selector.
     * @return string
     */
    public static function sanitize_selector($selector) {
        $selector = substr(sanitize_text_field((string) $selector), 0, 240);
        $selector = preg_replace('/\s+/', ' ', (string) $selector);
        return trim((string) $selector);
    }

    /**
     * @param string $url  Resource URL.
     * @param string $type LCP type.
     * @return bool
     */
    public static function is_resource_url_safe($url, $type = 'image') {
        $url = self::sanitize_resource_url($url);
        if ('' === $url) {
            return false;
        }
        $type = self::normalize_type($type);
        if ('text' === $type) {
            return false;
        }
        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        if ('' === $path) {
            return false;
        }
        $allowed = apply_filters('ucp_lcp_profile_allowed_resource_extensions', array('avif', 'webp', 'jpg', 'jpeg', 'png', 'gif', 'svg'));
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ('' === $extension || !in_array($extension, array_map('strtolower', array_map('strval', (array) $allowed)), true)) {
            return false;
        }
        return !preg_match('#/(?:wp-admin|wp-login\.php|wp-json|xmlrpc\.php)(?:/|$)#i', $path);
    }
}
