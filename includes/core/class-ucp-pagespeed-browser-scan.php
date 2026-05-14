<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stores browser-rendered PageSpeed scan hints collected from the admin user's browser.
 *
 * This is not a remote cloud renderer. It is a safe, same-origin browser scan that lets
 * UltraCache make better LCP/CSS/JS decisions without shipping a headless browser.
 */
class UCP_PageSpeed_Browser_Scan {
    const OPTION_KEY = 'ucp_pagespeed_browser_scan_latest';

    /**
     * Persist a sanitized browser scan payload.
     *
     * @param array<string,mixed> $payload Raw scan payload from the admin browser.
     * @return array<string,mixed>
     */
    public static function save($payload) {
        $payload = is_array($payload) ? $payload : array();
        $url = isset($payload['url']) ? UCP_Helpers::enforce_local_url((string) $payload['url']) : '';
        if ('' === $url) {
            $url = home_url('/');
        }

        $scan = array(
            'url'        => esc_url_raw($url),
            'created_at' => current_time('mysql'),
            'timestamp'  => time(),
            'viewport'   => self::sanitize_viewport(isset($payload['viewport']) ? $payload['viewport'] : array()),
            'lcp'        => self::sanitize_candidate(isset($payload['lcp']) ? $payload['lcp'] : array()),
            'images'     => self::sanitize_candidates(isset($payload['images']) ? $payload['images'] : array(), 25),
            'backgrounds'=> self::sanitize_candidates(isset($payload['backgrounds']) ? $payload['backgrounds'] : array(), 25),
            'stylesheets'=> self::sanitize_resources(isset($payload['stylesheets']) ? $payload['stylesheets'] : array(), 80),
            'scripts'    => self::sanitize_resources(isset($payload['scripts']) ? $payload['scripts'] : array(), 120),
            'third_party'=> self::sanitize_resources(isset($payload['thirdParty']) ? $payload['thirdParty'] : array(), 80),
            'render_blocking_stylesheets' => self::sanitize_resources(isset($payload['renderBlockingStylesheets']) ? $payload['renderBlockingStylesheets'] : array(), 80),
            'early_scripts' => self::sanitize_resources(isset($payload['earlyScripts']) ? $payload['earlyScripts'] : array(), 120),
            'delay_candidates' => self::sanitize_resources(isset($payload['delayCandidates']) ? $payload['delayCandidates'] : array(), 120),
            'css_candidates' => self::sanitize_resources(isset($payload['cssCandidates']) ? $payload['cssCandidates'] : array(), 80),
            'resource_timing' => self::sanitize_resource_timing(isset($payload['resourceTiming']) ? $payload['resourceTiming'] : array(), 160),
            'recommendations' => self::sanitize_recommendations(isset($payload['recommendations']) ? $payload['recommendations'] : array()),
            'source'     => 'admin_browser',
        );

        update_option(self::OPTION_KEY, $scan, false);

        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('pagespeed_browser_scan', 'Browser-rendered PageSpeed scan saved.', array(
                'url' => $scan['url'],
                'lcp_url' => isset($scan['lcp']['url']) ? $scan['lcp']['url'] : '',
                'lcp_background' => !empty($scan['lcp']['background']) ? 1 : 0,
                'scripts' => count($scan['scripts']),
                'stylesheets' => count($scan['stylesheets']),
                'third_party' => count($scan['third_party']),
                'render_blocking_stylesheets' => count($scan['render_blocking_stylesheets']),
                'delay_candidates' => count($scan['delay_candidates']),
            ));
        }

        return $scan;
    }

    /**
     * Get latest scan.
     *
     * @return array<string,mixed>
     */
    public static function latest() {
        $scan = get_option(self::OPTION_KEY, array());
        return is_array($scan) ? $scan : array();
    }

    /**
     * Return the browser-rendered LCP hint if it matches the current request URL.
     *
     * @return array<string,mixed>
     */
    public static function lcp_hint_for_current_request() {
        $scan = self::latest();
        if (empty($scan['lcp']['url']) || empty($scan['url'])) {
            return array();
        }
        $current = self::current_url_without_query();
        $scanned = self::url_without_query((string) $scan['url']);
        if ('' === $current || '' === $scanned || untrailingslashit($current) !== untrailingslashit($scanned)) {
            return array();
        }
        $url = UCP_Helpers::enforce_local_url((string) $scan['lcp']['url']);
        if ('' === $url) {
            return array();
        }
        return array(
            'url' => esc_url_raw($url),
            'background' => !empty($scan['lcp']['background']),
            'score' => isset($scan['lcp']['score']) ? absint($scan['lcp']['score']) : 0,
            'source' => 'browser_scan',
        );
    }


    /**
     * Return render-blocking stylesheet hints collected by the browser scan for the current URL.
     *
     * @return string[] URL fragments/URLs.
     */
    public static function stylesheet_hints_for_current_request() {
        return self::resource_hint_urls_for_current_request(array('render_blocking_stylesheets', 'css_candidates', 'stylesheets'), true);
    }

    /**
     * Return JS URLs/fragments that the browser scan marked as safe delay candidates.
     *
     * @return string[] URL fragments/URLs.
     */
    public static function delay_script_hints_for_current_request() {
        if (self::current_request_is_sensitive()) {
            return array();
        }
        return self::resource_hint_urls_for_current_request(array('delay_candidates', 'early_scripts', 'third_party'), false);
    }

    /**
     * Summarize scan data for diagnostics/admin output.
     *
     * @return array<string,mixed>
     */
    public static function optimization_summary_for_current_request() {
        $scan = self::latest();
        if (!self::scan_matches_current_request($scan)) {
            return array();
        }
        return array(
            'url' => isset($scan['url']) ? esc_url_raw((string) $scan['url']) : '',
            'lcp' => isset($scan['lcp']) && is_array($scan['lcp']) ? $scan['lcp'] : array(),
            'stylesheets' => isset($scan['stylesheets']) && is_array($scan['stylesheets']) ? count($scan['stylesheets']) : 0,
            'render_blocking_stylesheets' => isset($scan['render_blocking_stylesheets']) && is_array($scan['render_blocking_stylesheets']) ? count($scan['render_blocking_stylesheets']) : 0,
            'scripts' => isset($scan['scripts']) && is_array($scan['scripts']) ? count($scan['scripts']) : 0,
            'early_scripts' => isset($scan['early_scripts']) && is_array($scan['early_scripts']) ? count($scan['early_scripts']) : 0,
            'delay_candidates' => isset($scan['delay_candidates']) && is_array($scan['delay_candidates']) ? count($scan['delay_candidates']) : 0,
            'third_party' => isset($scan['third_party']) && is_array($scan['third_party']) ? count($scan['third_party']) : 0,
            'recommendations' => isset($scan['recommendations']) && is_array($scan['recommendations']) ? $scan['recommendations'] : array(),
        );
    }

    protected static function resource_hint_urls_for_current_request($keys, $local_only) {
        $scan = self::latest();
        if (!self::scan_matches_current_request($scan)) {
            return array();
        }
        $out = array();
        foreach ((array) $keys as $key) {
            if (empty($scan[$key]) || !is_array($scan[$key])) {
                continue;
            }
            foreach ($scan[$key] as $item) {
                $url = is_array($item) && isset($item['url']) ? (string) $item['url'] : (is_string($item) ? $item : '');
                if ('' === $url) {
                    continue;
                }
                if ($local_only && !UCP_Helpers::is_local_url($url)) {
                    continue;
                }
                $out[] = esc_url_raw($url);
                $path = wp_parse_url($url, PHP_URL_PATH);
                if (!empty($path)) {
                    $out[] = (string) $path;
                    $out[] = basename((string) $path);
                }
            }
        }
        return array_values(array_unique(array_filter($out, 'strlen')));
    }

    protected static function scan_matches_current_request($scan) {
        if (empty($scan) || !is_array($scan) || empty($scan['url'])) {
            return false;
        }
        $current = self::current_url_without_query();
        $scanned = self::url_without_query((string) $scan['url']);
        return '' !== $current && '' !== $scanned && untrailingslashit($current) === untrailingslashit($scanned);
    }

    protected static function current_request_is_sensitive() {
        if (is_admin() || is_feed() || is_preview() || is_customize_preview()) {
            return true;
        }
        foreach (array('is_cart', 'is_checkout', 'is_account_page') as $fn) {
            if (function_exists($fn) && call_user_func($fn)) {
                return true;
            }
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        return (bool) preg_match('#/(cart|checkout|my-account|order-pay|order-received|wc-api|wc-ajax)(/|$|\?)#i', $uri);
    }

    protected static function current_url_without_query() {
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : wp_parse_url(home_url('/'), PHP_URL_HOST);
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $scheme = is_ssl() ? 'https' : 'http';
        return self::url_without_query($scheme . '://' . $host . $request_uri);
    }

    protected static function url_without_query($url) {
        $parts = wp_parse_url((string) $url);
        if (empty($parts['host'])) {
            return '';
        }
        $scheme = empty($parts['scheme']) ? 'https' : $parts['scheme'];
        $path = isset($parts['path']) ? $parts['path'] : '/';
        return esc_url_raw($scheme . '://' . $parts['host'] . $path);
    }

    protected static function sanitize_viewport($viewport) {
        $viewport = is_array($viewport) ? $viewport : array();
        return array(
            'width' => isset($viewport['width']) ? absint($viewport['width']) : 0,
            'height' => isset($viewport['height']) ? absint($viewport['height']) : 0,
            'dpr' => isset($viewport['dpr']) ? min(4, max(1, (float) $viewport['dpr'])) : 1,
            'type' => isset($viewport['type']) ? sanitize_key((string) $viewport['type']) : '',
        );
    }

    protected static function sanitize_candidates($items, $limit) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $item) {
            $clean = self::sanitize_candidate($item);
            if (!empty($clean['url'])) {
                $out[] = $clean;
            }
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    protected static function sanitize_candidate($item) {
        $item = is_array($item) ? $item : array();
        $url = isset($item['url']) ? UCP_Helpers::enforce_local_url((string) $item['url']) : '';
        if ('' === $url && !empty($item['url'])) {
            $url = esc_url_raw((string) $item['url']);
        }
        return array(
            'url' => esc_url_raw($url),
            'score' => isset($item['score']) ? (int) $item['score'] : 0,
            'background' => !empty($item['background']) ? 1 : 0,
            'tag' => isset($item['tag']) ? sanitize_key((string) $item['tag']) : '',
            'class' => isset($item['className']) ? sanitize_text_field((string) $item['className']) : (isset($item['class']) ? sanitize_text_field((string) $item['class']) : ''),
            'width' => isset($item['width']) ? absint($item['width']) : 0,
            'height' => isset($item['height']) ? absint($item['height']) : 0,
            'top' => isset($item['top']) ? (int) $item['top'] : 0,
        );
    }

    protected static function sanitize_resources($items, $limit) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $item) {
            if (is_string($item)) {
                $url = esc_url_raw($item);
                $label = '';
                $kind = '';
                $blocking = 0;
                $duration = 0;
            } elseif (is_array($item)) {
                $url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
                $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
                $kind = isset($item['kind']) ? sanitize_key((string) $item['kind']) : '';
                $blocking = !empty($item['blocking']) ? 1 : 0;
                $duration = isset($item['duration']) ? (float) $item['duration'] : 0;
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

    protected static function sanitize_resource_timing($items, $limit) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
            if ('' === $url) {
                continue;
            }
            $out[] = array(
                'url' => $url,
                'initiator' => isset($item['initiator']) ? sanitize_key((string) $item['initiator']) : '',
                'duration' => isset($item['duration']) ? max(0, (float) $item['duration']) : 0,
                'transfer_size' => isset($item['transferSize']) ? absint($item['transferSize']) : 0,
                'render_blocking' => !empty($item['renderBlocking']) ? 1 : 0,
            );
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    protected static function sanitize_recommendations($items) {
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
                $out[$key] = array_map('sanitize_text_field', array_slice(array_map('strval', $value), 0, 20));
            }
        }
        return $out;
    }
}
