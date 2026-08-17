<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitizes browser-rendered PageSpeed scan payload fragments.
 *
 * Kept separate from UCP_PageSpeed_Browser_Scan so persistence, lookup, and
 * optimization application can evolve without duplicating payload cleanup rules.
 */
final class UCP_PageSpeed_Browser_Scan_Sanitizer {
    /**
     * Reduce a same-origin page URL to origin and path for persistent storage.
     *
     * @param mixed $url Raw page URL.
     * @return string
     */
    public static function page_url($url) {
        if (!is_scalar($url)) {
            return '';
        }
        $url = trim((string) $url);
        if ('' === $url || strlen($url) > 2048) {
            return '';
        }
        if (class_exists('UCP_CWV_LCP_Sanitizer')) {
            return UCP_CWV_LCP_Sanitizer::sanitize_page_url($url);
        }
        $absolute = class_exists('UCP_Helpers') ? UCP_Helpers::strict_local_url($url, home_url('/')) : esc_url_raw($url);
        $parts = wp_parse_url($absolute);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':' . absint($parts['port']) : '';
        $path = isset($parts['path']) && '' !== (string) $parts['path'] ? (string) $parts['path'] : '/';
        return esc_url_raw($scheme . '://' . $host . $port . '/' . ltrim($path, '/'));
    }

    /**
     * Sanitize a resource URL for persistent diagnostics and optimization hints.
     *
     * Unknown query keys are discarded with the full query so tokens, signatures,
     * emails and visitor-specific values never reach stored scan records.
     *
     * @param mixed $url Raw resource URL.
     * @return string
     */
    public static function resource_url($url) {
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
        $parts = wp_parse_url($absolute);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, array('http', 'https'), true)) {
            return '';
        }
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':' . absint($parts['port']) : '';
        $path = isset($parts['path']) && '' !== (string) $parts['path'] ? (string) $parts['path'] : '/';
        $query = isset($parts['query']) ? self::resource_query((string) $parts['query']) : '';
        return esc_url_raw($scheme . '://' . $host . $port . '/' . ltrim($path, '/') . ('' !== $query ? '?' . $query : ''));
    }

    /**
     * @param mixed $srcset Raw srcset value.
     * @return string
     */
    public static function srcset($srcset) {
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
            $parts = preg_split('/\s+/', trim($candidate), 2);
            $url = !empty($parts[0]) ? self::resource_url($parts[0]) : '';
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
     * Re-sanitize a previously stored scan during privacy migrations.
     *
     * @param mixed $scan Stored scan.
     * @return array<string,mixed>
     */
    public static function stored_scan($scan) {
        $scan = is_array($scan) ? $scan : array();
        $scan['url'] = self::page_url(isset($scan['url']) ? $scan['url'] : '');
        $scan['lcp'] = self::candidate(isset($scan['lcp']) ? $scan['lcp'] : array());
        foreach (array('images', 'backgrounds') as $key) {
            $scan[$key] = self::candidates(isset($scan[$key]) ? $scan[$key] : array(), 25);
        }
        foreach (array('stylesheets', 'scripts', 'third_party', 'render_blocking_stylesheets', 'early_scripts', 'delay_candidates', 'css_candidates') as $key) {
            $scan[$key] = self::resources(isset($scan[$key]) ? $scan[$key] : array(), 160);
        }
        $scan['resource_timing'] = self::resource_timing(isset($scan['resource_timing']) ? $scan['resource_timing'] : array(), 160);
        return $scan;
    }

    /**
     * Keep only non-sensitive versioning and media-transformation parameters.
     *
     * @param string $query Raw query string.
     * @return string
     */
    private static function resource_query($query) {
        $allowed = (array) apply_filters('ucp_pagespeed_scan_allowed_resource_query_args', array(
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
     * @param mixed $viewport Raw viewport payload.
     * @return array<string,mixed>
     */
    public static function viewport($viewport) {
        $viewport = is_array($viewport) ? $viewport : array();
        $width = isset($viewport['width']) && is_scalar($viewport['width']) ? absint($viewport['width']) : 0;
        $height = isset($viewport['height']) && is_scalar($viewport['height']) ? absint($viewport['height']) : 0;
        $dpr = isset($viewport['dpr']) && is_scalar($viewport['dpr']) && is_numeric($viewport['dpr']) ? (float) $viewport['dpr'] : 1.0;
        if (!is_finite($dpr)) {
            $dpr = 1.0;
        }
        return array(
            'width' => $width,
            'height' => $height,
            'dpr' => min(4, max(1, $dpr)),
            'type' => isset($viewport['type']) && is_scalar($viewport['type']) ? sanitize_key((string) $viewport['type']) : '',
        );
    }

    /**
     * @param mixed $items Raw LCP/image candidates.
     * @param int   $limit Maximum number of candidates.
     * @return array<int,array<string,mixed>>
     */
    public static function candidates($items, $limit) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $item) {
            $clean = self::candidate($item);
            if (!empty($clean['url'])) {
                $out[] = $clean;
            }
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $item Raw candidate payload.
     * @return array<string,mixed>
     */
    public static function candidate($item) {
        $item = is_array($item) ? $item : array();
        $raw_url = isset($item['url']) && is_scalar($item['url']) ? (string) $item['url'] : '';
        $url = '' !== $raw_url && class_exists('UCP_Helpers') ? UCP_Helpers::strict_local_url($raw_url) : '';
        $url = self::resource_url($url);
        $background = isset($item['background']) && is_scalar($item['background']) && !empty($item['background']) ? 1 : 0;
        $start_time = isset($item['startTime']) && is_scalar($item['startTime']) && is_numeric($item['startTime']) ? (float) $item['startTime'] : 0.0;
        if (!is_finite($start_time)) {
            $start_time = 0.0;
        }
        $max_value = class_exists('UCP_CWV') ? (float) UCP_CWV::MAX_VALUE : 120000.0;
        return array(
            'url' => esc_url_raw($url),
            'score' => isset($item['score']) && is_scalar($item['score']) && is_numeric($item['score']) ? (int) $item['score'] : 0,
            'startTime' => max(0.0, min($start_time, $max_value)),
            'background' => $background,
            'tag' => isset($item['tag']) && is_scalar($item['tag']) ? sanitize_key((string) $item['tag']) : '',
            'class' => isset($item['className']) && is_scalar($item['className']) ? sanitize_text_field((string) $item['className']) : (isset($item['class']) && is_scalar($item['class']) ? sanitize_text_field((string) $item['class']) : ''),
            'width' => isset($item['width']) && is_scalar($item['width']) ? absint($item['width']) : 0,
            'height' => isset($item['height']) && is_scalar($item['height']) ? absint($item['height']) : 0,
            'top' => isset($item['top']) && is_scalar($item['top']) && is_numeric($item['top']) ? (int) $item['top'] : 0,
            'srcset' => isset($item['srcset']) && is_scalar($item['srcset']) ? self::srcset($item['srcset']) : '',
            'sizes' => isset($item['sizes']) && is_scalar($item['sizes']) ? substr(sanitize_text_field((string) $item['sizes']), 0, 240) : '',
            'type' => isset($item['type']) && is_scalar($item['type']) ? sanitize_key((string) $item['type']) : ($background ? 'background-image' : ''),
            'selector' => isset($item['selector']) && is_scalar($item['selector']) ? substr(sanitize_text_field((string) $item['selector']), 0, 240) : '',
        );
    }

    /**
     * @param mixed $items Raw below-fold/lazy-render selectors.
     * @param int   $limit Maximum number of selectors.
     * @return string[]
     */
    public static function selectors($items, $limit = 20) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $item) {
            if (is_array($item)) {
                if (!isset($item['selector']) || !is_scalar($item['selector'])) {
                    continue;
                }
                $selector = (string) $item['selector'];
            } elseif (is_scalar($item)) {
                $selector = (string) $item;
            } else {
                continue;
            }
            $selector = trim(sanitize_text_field($selector));
            if ('' === $selector || strlen($selector) > 160) {
                continue;
            }
            if (preg_match('/[{}<>\"\'`;]/', $selector)) {
                continue;
            }
            if (!preg_match('/^[#.a-zA-Z0-9_:\-\[\]\(\)=\*\^\$\|~+> ,.]+$/', $selector)) {
                continue;
            }
            $out[$selector] = $selector;
            if (count($out) >= $limit) {
                break;
            }
        }
        return array_values($out);
    }

    /**
     * @param mixed $items Raw resource payloads.
     * @param int   $limit Maximum number of resources.
     * @return array<int,array<string,mixed>>
     */
    public static function resources($items, $limit) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $item) {
            if (is_string($item)) {
                $url = self::resource_url($item);
                $label = '';
                $kind = '';
                $blocking = 0;
                $duration = 0;
            } elseif (is_array($item)) {
                $url = isset($item['url']) && is_scalar($item['url']) ? self::resource_url($item['url']) : '';
                $label = isset($item['label']) && is_scalar($item['label']) ? sanitize_text_field((string) $item['label']) : '';
                $kind = isset($item['kind']) && is_scalar($item['kind']) ? sanitize_key((string) $item['kind']) : '';
                $blocking = isset($item['blocking']) && is_scalar($item['blocking']) && !empty($item['blocking']) ? 1 : 0;
                $duration = isset($item['duration']) && is_scalar($item['duration']) && is_numeric($item['duration']) ? (float) $item['duration'] : 0;
                if (!is_finite($duration)) {
                    $duration = 0;
                }
            } else {
                continue;
            }
            if ('' === $url && '' === $label) {
                continue;
            }
            $out[] = array('url' => $url, 'label' => $label, 'kind' => $kind, 'blocking' => $blocking, 'duration' => max(0, $duration));
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $items Raw Resource Timing payloads.
     * @param int   $limit Maximum number of resources.
     * @return array<int,array<string,mixed>>
     */
    public static function resource_timing($items, $limit) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = isset($item['url']) && is_scalar($item['url']) ? self::resource_url($item['url']) : '';
            if ('' === $url) {
                continue;
            }
            $duration = isset($item['duration']) && is_scalar($item['duration']) && is_numeric($item['duration']) ? (float) $item['duration'] : 0;
            if (!is_finite($duration)) {
                $duration = 0;
            }
            $transfer_size = 0;
            if (isset($item['transferSize']) && is_scalar($item['transferSize'])) {
                $transfer_size = absint($item['transferSize']);
            } elseif (isset($item['transfer_size']) && is_scalar($item['transfer_size'])) {
                $transfer_size = absint($item['transfer_size']);
            }
            $out[] = array(
                'url' => $url,
                'initiator' => isset($item['initiator']) && is_scalar($item['initiator']) ? sanitize_key((string) $item['initiator']) : '',
                'duration' => max(0, $duration),
                'transfer_size' => $transfer_size,
                'render_blocking' => isset($item['renderBlocking']) && is_scalar($item['renderBlocking']) && !empty($item['renderBlocking']) ? 1 : 0,
            );
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $items Raw recommendation payload.
     * @return array<string,mixed>
     */
    public static function recommendations($items) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }
            if (is_scalar($value)) {
                $out[$key] = sanitize_text_field((string) $value);
            } elseif (is_array($value)) {
                $values = array();
                foreach (array_slice($value, 0, 20) as $item) {
                    if (is_scalar($item)) {
                        $values[] = sanitize_text_field((string) $item);
                    }
                }
                if (empty($values)) {
                    continue;
                }
                $out[$key] = $values;
            }
            if (count($out) >= 100) {
                break;
            }
        }
        return $out;
    }
}
