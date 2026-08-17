<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
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
    const OPTION_MAP_KEY = 'ucp_pagespeed_browser_scan_map';
    const MAX_STORED_SCANS = 40;
    const PRIVACY_VERSION = 1;

    /**
     * Persist a sanitized browser scan payload.
     *
     * @param array<string,mixed> $payload Raw scan payload from the admin browser.
     * @return array<string,mixed>
     */
    public static function save($payload) {
        $payload = is_array($payload) ? $payload : array();
        $url = UCP_PageSpeed_Browser_Scan_Sanitizer::page_url(isset($payload['url']) ? $payload['url'] : home_url('/'));
        if ('' === $url) {
            $url = UCP_PageSpeed_Browser_Scan_Sanitizer::page_url(home_url('/'));
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
            'below_fold_selectors' => self::sanitize_selectors(isset($payload['belowFoldSelectors']) ? $payload['belowFoldSelectors'] : (isset($payload['lazyRenderSelectors']) ? $payload['lazyRenderSelectors'] : array()), 24),
            'resource_timing' => self::sanitize_resource_timing(isset($payload['resourceTiming']) ? $payload['resourceTiming'] : array(), 160),
            'recommendations' => self::sanitize_recommendations(isset($payload['recommendations']) ? $payload['recommendations'] : array()),
            'source'     => 'admin_browser',
        );

        $previous_latest = get_option(self::OPTION_KEY, null);
        if (!self::persist_option_value(self::OPTION_KEY, $scan)) {
            return array();
        }
        if (!self::store_scan_map($scan)) {
            if (null === $previous_latest) {
                delete_option(self::OPTION_KEY);
            } else {
                self::persist_option_value(self::OPTION_KEY, $previous_latest);
            }
            return array();
        }

        if (class_exists('UCP_CWV') && !empty($scan['lcp']['url'])) {
            UCP_CWV::store_lcp_hint(array(
                'url' => $scan['url'],
                'device' => !empty($scan['viewport']['width']) && absint($scan['viewport']['width']) <= 767 ? 'mobile' : 'desktop',
                'lcp_url' => $scan['lcp']['url'],
                'lcp_element_json' => UCP_Helpers::safe_json_encode_or($scan['lcp'], '{}'),
                'lcp_imagesrcset' => isset($scan['lcp']['srcset']) ? $scan['lcp']['srcset'] : '',
                'lcp_type' => !empty($scan['lcp']['type']) ? sanitize_key((string) $scan['lcp']['type']) : (!empty($scan['lcp']['background']) ? 'background-image' : 'image'),
                'source' => 'browser_scan',
                'value_ms' => isset($scan['lcp']['startTime']) ? (float) $scan['lcp']['startTime'] : 0,
            ));
        }

        $applied = self::apply_scan_optimization_settings($scan);

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
                'below_fold_selectors' => count($scan['below_fold_selectors']),
                'auto_applied' => implode(',', $applied),
            ));
        }

        if (!empty($applied)) {
            $scan['auto_applied'] = $applied;
        }

        return $scan;
    }

    /**
     * Re-apply safe optimization settings from the latest stored browser scan.
     * Used by migrations so an already saved scan immediately fixes LCP/delay hints after update.
     *
     * @return string[] Applied setting groups.
     */
    public static function reapply_latest_scan_optimization_settings() {
        $scan = self::latest();
        if (empty($scan) || !is_array($scan)) {
            return array();
        }
        return self::apply_scan_optimization_settings($scan);
    }

    protected static function apply_scan_optimization_settings($scan) {
        return UCP_PageSpeed_Browser_Scan_Optimizer::apply($scan);
    }

    /**
     * Remove per-image LCP rules from older repairs.
     * Measured LCP hints are applied dynamically.
     *
     * @param string[] $rules Existing multiline rules.
     * @return string[]
     */
    protected static function remove_generated_lcp_fetchpriority_rules($rules) {
        return UCP_PageSpeed_Browser_Scan_Optimizer::remove_generated_lcp_fetchpriority_rules($rules);
    }

    /**
     * Build safe script fragments for Delay JS from browser-scan resources.
     *
     * @param array<int,mixed> $items Scan resources.
     * @return string[]
     */
    protected static function delay_fragments_from_scan_resources($items) {
        return UCP_PageSpeed_Browser_Scan_Optimizer::delay_fragments_from_scan_resources($items);
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
     * Remove sensitive query data from browser scans saved by older versions.
     *
     * @return void
     */
    public static function maybe_migrate_sensitive_urls() {
        if ((int) get_option('ucp_pagespeed_scan_privacy_version', 0) >= self::PRIVACY_VERSION) {
            return;
        }

        $latest = UCP_PageSpeed_Browser_Scan_Sanitizer::stored_scan(get_option(self::OPTION_KEY, array()));
        if (!empty($latest['url']) && !self::persist_option_value(self::OPTION_KEY, $latest)) {
            return;
        }

        $legacy_map = get_option(self::OPTION_MAP_KEY, array());
        $normalized = array();
        foreach (is_array($legacy_map) ? $legacy_map : array() as $scan) {
            $scan = UCP_PageSpeed_Browser_Scan_Sanitizer::stored_scan($scan);
            if (empty($scan['url'])) {
                continue;
            }
            $device = !empty($scan['viewport']['type']) ? sanitize_key((string) $scan['viewport']['type']) : 'all';
            if (!in_array($device, array('mobile', 'desktop', 'all'), true)) {
                $device = !empty($scan['viewport']['width']) && absint($scan['viewport']['width']) <= 767 ? 'mobile' : 'desktop';
            }
            $key = self::url_match_key((string) $scan['url']) . '|' . $device;
            if ('|' === $key) {
                continue;
            }
            if (!isset($normalized[$key]) || absint($scan['timestamp'] ?? 0) >= absint($normalized[$key]['timestamp'] ?? 0)) {
                $normalized[$key] = $scan;
            }
        }
        uasort($normalized, static function($a, $b) {
            $at = is_array($a) && isset($a['timestamp']) ? absint($a['timestamp']) : 0;
            $bt = is_array($b) && isset($b['timestamp']) ? absint($b['timestamp']) : 0;
            return $bt <=> $at;
        });
        if (!self::persist_option_value(self::OPTION_MAP_KEY, array_slice($normalized, 0, self::MAX_STORED_SCANS, true))) {
            return;
        }
        self::persist_option_value('ucp_pagespeed_scan_privacy_version', self::PRIVACY_VERSION);
    }

    /**
     * Return the best stored browser-rendered scan for the current URL/device.
     * Falls back to the legacy latest scan only when it matches the request.
     *
     * @return array<string,mixed>
     */
    public static function scan_for_current_request() {
        $current = self::current_url_without_query();
        $key = self::url_match_key($current);
        if ('' === $key) {
            return array();
        }

        $map = get_option(self::OPTION_MAP_KEY, array());
        $map = is_array($map) ? $map : array();
        $device = self::current_device();
        foreach (array($device, 'all', 'desktop', 'mobile') as $candidate_device) {
            $map_key = $key . '|' . $candidate_device;
            if (!empty($map[$map_key]) && is_array($map[$map_key])) {
                return $map[$map_key];
            }
        }

        foreach ($map as $scan) {
            if (is_array($scan) && self::scan_matches_current_request($scan)) {
                return $scan;
            }
        }

        $latest = self::latest();
        return self::scan_matches_current_request($latest) ? $latest : array();
    }

    /**
     * Store a bounded per-URL/device scan map so LCP, CSS and JS hints are no longer global.
     *
     * @param array<string,mixed> $scan Sanitized scan payload.
     * @return bool
     */
    protected static function store_scan_map($scan) {
        if (empty($scan['url'])) {
            return false;
        }
        $key = self::url_match_key((string) $scan['url']);
        if ('' === $key) {
            return false;
        }
        $device = !empty($scan['viewport']['type']) ? sanitize_key((string) $scan['viewport']['type']) : 'all';
        if (!in_array($device, array('mobile', 'desktop', 'all'), true)) {
            $device = !empty($scan['viewport']['width']) && absint($scan['viewport']['width']) <= 767 ? 'mobile' : 'desktop';
        }
        $map = get_option(self::OPTION_MAP_KEY, array());
        $map = is_array($map) ? $map : array();
        $map[$key . '|' . $device] = $scan;
        uasort($map, static function($a, $b) {
            $at = is_array($a) && isset($a['timestamp']) ? absint($a['timestamp']) : 0;
            $bt = is_array($b) && isset($b['timestamp']) ? absint($b['timestamp']) : 0;
            return $bt <=> $at;
        });
        $map = array_slice($map, 0, self::MAX_STORED_SCANS, true);
        return self::persist_option_value(self::OPTION_MAP_KEY, $map);
    }

    /**
     * Persist an option while distinguishing unchanged data from a failed write.
     *
     * @param string $key   Option key.
     * @param mixed  $value Option value.
     * @return bool
     */
    protected static function persist_option_value($key, $value) {
        return UCP_Options::persist_option_value($key, $value);
    }

    /**
     * Return the browser-rendered LCP hint if it matches the current request URL.
     *
     * @return array<string,mixed>
     */
    public static function lcp_hint_for_current_request() {
        $scan = self::scan_for_current_request();
        if (empty($scan['lcp']) || !is_array($scan['lcp']) || empty($scan['url'])) {
            return array();
        }
        $current = self::current_url_without_query();
        $scanned = self::url_without_query((string) $scan['url']);
        if (!self::urls_match_request($current, $scanned)) {
            return array();
        }
        $type = isset($scan['lcp']['type']) ? sanitize_key((string) $scan['lcp']['type']) : (!empty($scan['lcp']['background']) ? 'background-image' : 'image');
        $url = !empty($scan['lcp']['url']) ? UCP_Helpers::strict_local_url((string) $scan['lcp']['url']) : '';
        if ('' === $url && 'text' !== $type) {
            return array();
        }
        return array(
            'url' => '' !== $url ? esc_url_raw($url) : '',
            'background' => !empty($scan['lcp']['background']),
            'score' => isset($scan['lcp']['score']) ? absint($scan['lcp']['score']) : 0,
            'srcset' => isset($scan['lcp']['srcset']) ? sanitize_textarea_field((string) $scan['lcp']['srcset']) : '',
            'sizes' => isset($scan['lcp']['sizes']) ? substr(sanitize_text_field((string) $scan['lcp']['sizes']), 0, 240) : '',
            'type' => $type,
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
     * Return safe below-fold selectors from the current URL browser scan.
     *
     * @return string[]
     */
    public static function lazy_render_selectors_for_current_request() {
        if (self::current_request_is_sensitive()) {
            return array();
        }
        $scan = self::scan_for_current_request();
        if (empty($scan['below_fold_selectors']) || !is_array($scan['below_fold_selectors'])) {
            return array();
        }
        return array_values(array_unique(array_filter(array_map('strval', $scan['below_fold_selectors']), 'strlen')));
    }

    /**
     * Summarize scan data for diagnostics/admin output.
     *
     * @return array<string,mixed>
     */
    public static function optimization_summary_for_current_request() {
        $scan = self::scan_for_current_request();
        if (empty($scan)) {
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
            'below_fold_selectors' => isset($scan['below_fold_selectors']) && is_array($scan['below_fold_selectors']) ? count($scan['below_fold_selectors']) : 0,
            'recommendations' => isset($scan['recommendations']) && is_array($scan['recommendations']) ? $scan['recommendations'] : array(),
        );
    }

    protected static function resource_hint_urls_for_current_request($keys, $local_only) {
        $scan = self::scan_for_current_request();
        if (empty($scan)) {
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
        return self::urls_match_request($current, $scanned);
    }

    protected static function urls_match_request($a, $b) {
        $ka = self::url_match_key($a);
        $kb = self::url_match_key($b);
        return '' !== $ka && '' !== $kb && $ka === $kb;
    }

    protected static function url_match_key($url) {
        $parts = wp_parse_url((string) $url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }
        $scheme = !empty($parts['scheme']) ? strtolower((string) $parts['scheme']) : 'https';
        if (!in_array($scheme, array('http', 'https'), true)) {
            return '';
        }
        $host = strtolower((string) $parts['host']);
        if (false !== strpos($host, ':') && '[' !== substr($host, 0, 1)) {
            $host = '[' . $host . ']';
        }
        $port = isset($parts['port']) ? absint($parts['port']) : ('https' === $scheme ? 443 : 80);
        $path = isset($parts['path']) && '' !== $parts['path'] ? (string) $parts['path'] : '/';
        $path = '/' . ltrim($path, '/');
        return $scheme . '://' . $host . ':' . $port . untrailingslashit($path);
    }

    protected static function current_device() {
        if (function_exists('wp_is_mobile') && wp_is_mobile()) {
            return 'mobile';
        }
        return 'desktop';
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
        return (bool) preg_match('#/(cart|checkout|my-account|account|order-pay|order-received|add-payment-method|customer-logout|winkelwagen|afrekenen|mijn-account|wc-api|wc-ajax)(/|$|\?)#i', $uri);
    }

    protected static function current_url_without_query() {
        // Build from WordPress' configured origin, not request-controlled Host or
        // forwarded-proto headers. Only the path is taken from the current request.
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $request_parts = wp_parse_url((string) $request_uri);
        $path = is_array($request_parts) && isset($request_parts['path']) ? (string) $request_parts['path'] : '/';
        if ('' === $path) {
            $path = '/';
        }
        return self::url_without_query(home_url('/' . ltrim($path, '/')));
    }

    protected static function url_without_query($url) {
        $parts = wp_parse_url((string) $url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }
        $scheme = empty($parts['scheme']) ? 'https' : strtolower((string) $parts['scheme']);
        if (!in_array($scheme, array('http', 'https'), true)) {
            return '';
        }
        $host = (string) $parts['host'];
        if (false !== strpos($host, ':') && '[' !== substr($host, 0, 1)) {
            $host = '[' . $host . ']';
        }
        $port = isset($parts['port']) ? ':' . absint($parts['port']) : '';
        $path = isset($parts['path']) && '' !== $parts['path'] ? (string) $parts['path'] : '/';
        return esc_url_raw($scheme . '://' . $host . $port . '/' . ltrim($path, '/'));
    }

    protected static function sanitize_viewport($viewport) {
        return UCP_PageSpeed_Browser_Scan_Sanitizer::viewport($viewport);
    }

    protected static function sanitize_candidates($items, $limit) {
        return UCP_PageSpeed_Browser_Scan_Sanitizer::candidates($items, $limit);
    }

    protected static function sanitize_candidate($item) {
        return UCP_PageSpeed_Browser_Scan_Sanitizer::candidate($item);
    }

    protected static function sanitize_resources($items, $limit) {
        return UCP_PageSpeed_Browser_Scan_Sanitizer::resources($items, $limit);
    }

    protected static function sanitize_selectors($items, $limit) {
        return UCP_PageSpeed_Browser_Scan_Sanitizer::selectors($items, $limit);
    }

    protected static function sanitize_resource_timing($items, $limit) {
        return UCP_PageSpeed_Browser_Scan_Sanitizer::resource_timing($items, $limit);
    }

    protected static function sanitize_recommendations($items) {
        return UCP_PageSpeed_Browser_Scan_Sanitizer::recommendations($items);
    }
}
