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
        if (!is_scalar($url)) {
            return false;
        }
        $url = trim((string) $url);
        return '' === $url || self::is_same_origin_url($url);
    }

    /**
     * @param mixed $url Raw URL.
     * @return string
     */
    public static function sanitize_local_url_param($url) {
        if (!is_scalar($url)) {
            return '';
        }
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
        if (is_array($json)) {
            $raw = UCP_Helpers::safe_json_encode($json);
        } elseif (is_scalar($json)) {
            $raw = (string) $json;
        } else {
            return '';
        }

        if (!is_string($raw) || '' === $raw || strlen($raw) > 2048) {
            return '';
        }

        $decoded = UCP_Helpers::safe_json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }

        $allowed = array();
        foreach (array('tag', 'id', 'class', 'selector', 'sizes', 'type') as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                $allowed[$key] = substr(sanitize_text_field((string) $decoded[$key]), 0, 240);
            }
        }
        if (isset($decoded['url']) && is_scalar($decoded['url'])) {
            $allowed['url'] = self::sanitize_resource_url($decoded['url']);
        }
        if (isset($decoded['srcset']) && is_scalar($decoded['srcset'])) {
            $allowed['srcset'] = self::sanitize_srcset($decoded['srcset']);
        }
        if (isset($decoded['background']) && is_scalar($decoded['background']) && !empty($decoded['background'])) {
            $allowed['background'] = 1;
        }

        return $allowed ? UCP_Helpers::safe_json_encode($allowed) : '';
    }

    /**
     * @param mixed $element Raw decoded element metadata.
     * @return array<string,mixed>
     */
    public static function sanitize_element_array($element) {
        $element = is_array($element) ? $element : array();
        $allowed = array();
        foreach (array('tag', 'id', 'class', 'selector', 'sizes', 'type') as $key) {
            if (isset($element[$key]) && is_scalar($element[$key])) {
                $allowed[$key] = substr(sanitize_text_field((string) $element[$key]), 0, 240);
            }
        }
        if (isset($element['url']) && is_scalar($element['url'])) {
            $allowed['url'] = self::sanitize_resource_url($element['url']);
        }
        if (isset($element['srcset']) && is_scalar($element['srcset'])) {
            $allowed['srcset'] = self::sanitize_srcset($element['srcset']);
        }
        if (isset($element['background']) && is_scalar($element['background']) && !empty($element['background'])) {
            $allowed['background'] = 1;
        }

        return $allowed;
    }

    /**
     * @param string $url Raw resource URL.
     * @return string
     */
    public static function sanitize_resource_url($url) {
        if (!is_scalar($url)) {
            return '';
        }
        $url = trim((string) $url);
        if ('' === $url || strlen($url) > 2048) {
            return '';
        }

        if (class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'normalize_url_syntax')) {
            $url = UCP_Helpers::normalize_url_syntax($url);
        }
        if (0 === strpos($url, '//')) {
            $scheme = wp_parse_url(home_url('/'), PHP_URL_SCHEME);
            $url = ($scheme ? $scheme : 'https') . ':' . $url;
        }

        $absolute = wp_parse_url($url, PHP_URL_HOST) ? $url : home_url('/' . ltrim($url, '/'));
        $absolute = esc_url_raw($absolute);
        if ('' === $absolute) {
            return '';
        }

        if (!self::is_resource_origin_allowed($absolute)) {
            return '';
        }

        $parts = wp_parse_url($absolute);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, array('http', 'https'), true)) {
            return '';
        }

        $query = '';
        if (!empty($parts['query'])) {
            $query = self::sanitize_resource_query((string) $parts['query']);
            if ('' === $query) {
                return '';
            }
        }

        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':' . absint($parts['port']) : '';
        $path = isset($parts['path']) && '' !== (string) $parts['path'] ? (string) $parts['path'] : '/';
        $path = '/' . ltrim($path, '/');

        return esc_url_raw($scheme . '://' . $host . $port . $path . ('' !== $query ? '?' . $query : ''));
    }

    /**
     * Keep only non-sensitive image/CDN transformation parameters.
     *
     * Any unknown query key causes the resource hint to be discarded rather than
     * storing or preloading a potentially signed or visitor-specific URL.
     *
     * @param string $query Raw query string.
     * @return string
     */
    private static function sanitize_resource_query($query) {
        $allowed = (array) apply_filters('ucp_lcp_profile_allowed_resource_query_args', array(
            'ver', 'v', 'version', 'w', 'width', 'h', 'height', 'q', 'quality',
            'fit', 'crop', 'format', 'fm', 'auto', 'dpr', 'resize',
        ));
        $allowed = array_values(array_unique(array_filter(array_map('sanitize_key', $allowed), 'strlen')));

        $args = array();
        wp_parse_str((string) $query, $args);
        if (empty($args)) {
            return '';
        }

        $safe = array();
        foreach ($args as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key || !in_array($key, $allowed, true) || !is_scalar($value)) {
                return '';
            }
            $safe[$key] = substr(sanitize_text_field((string) $value), 0, 160);
        }

        return http_build_query($safe, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param string $url Raw page URL.
     * @return string
     */
    public static function sanitize_page_url($url) {
        if (!is_scalar($url)) {
            return '';
        }
        $url = trim((string) $url);
        if ('' === $url || strlen($url) > 2048) {
            return '';
        }

        $absolute = wp_parse_url($url, PHP_URL_HOST) ? esc_url_raw($url) : esc_url_raw(home_url('/' . ltrim($url, '/')));
        if ('' === $absolute || !self::is_same_origin_url($absolute)) {
            return '';
        }

        $parts = wp_parse_url($absolute);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':' . absint($parts['port']) : '';
        $path = isset($parts['path']) && '' !== (string) $parts['path'] ? (string) $parts['path'] : '/';
        $path = '/' . ltrim($path, '/');

        // Page profiles are keyed by origin + path only. Query strings can contain
        // personal data, tokens or campaign values and must never reach storage.
        return esc_url_raw($scheme . '://' . $host . $port . $path);
    }

    /**
     * @param string $url Absolute URL.
     * @return bool
     */
    public static function is_same_origin_url($url) {
        if (!is_scalar($url)) {
            return false;
        }
        $url_parts = wp_parse_url((string) $url);
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
        if (!is_scalar($url)) {
            return false;
        }
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
            if (!is_scalar($allowed_host)) {
                continue;
            }
            $allowed_host = strtolower(trim((string) $allowed_host));
            $allowed_host = UCP_Helpers::sanitize_preg_replace('#^https?://#', '', $allowed_host);
            $allowed_host = UCP_Helpers::sanitize_preg_replace('#/.*$#', '', $allowed_host);
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
        if (!is_scalar($srcset)) {
            return '';
        }
        $srcset = sanitize_textarea_field((string) $srcset);
        if ('' === trim($srcset) || strlen($srcset) > 16384) {
            return '';
        }

        $safe = array();
        $length = 0;
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
            $descriptor = isset($parts[1]) ? trim((string) $parts[1]) : '';
            if (!self::srcset_descriptor_is_valid($descriptor)) {
                continue;
            }
            $item = $url . ('' !== $descriptor ? ' ' . $descriptor : '');
            $addition = (empty($safe) ? 0 : 2) + strlen($item);
            if ($length + $addition > 1200) {
                break;
            }
            $safe[] = $item;
            $length += $addition;
        }

        return implode(', ', $safe);
    }

    private static function srcset_descriptor_is_valid($descriptor) {
        if ('' === $descriptor) {
            return true;
        }
        if (1 === preg_match('/^[0-9]+w$/', $descriptor)) {
            return '' !== trim(substr($descriptor, 0, -1), '0');
        }
        if (1 !== preg_match('/^(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?x$/', $descriptor)) {
            return false;
        }
        $density = (float) substr($descriptor, 0, -1);
        return is_finite($density) && $density > 0;
    }

    /**
     * @param string $type Type.
     * @return string
     */
    public static function normalize_type($type) {
        if (!is_scalar($type)) {
            return '';
        }
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
        if (!is_scalar($selector)) {
            return '';
        }
        $selector = substr(sanitize_text_field((string) $selector), 0, 240);
        $selector = UCP_Helpers::sanitize_preg_replace('/\s+/', ' ', (string) $selector);
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
