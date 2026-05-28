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

    /**
     * Persist a sanitized browser scan payload.
     *
     * @param array<string,mixed> $payload Raw scan payload from the admin browser.
     * @return array<string,mixed>
     */
    public static function save($payload) {
        $payload = is_array($payload) ? $payload : array();
        $url = isset($payload['url']) ? UCP_Helpers::strict_local_url((string) $payload['url'], home_url('/')) : home_url('/');
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

        if (class_exists('UCP_CWV') && !empty($scan['lcp']['url'])) {
            UCP_CWV::store_lcp_hint(array(
                'url' => $scan['url'],
                'device' => !empty($scan['viewport']['width']) && absint($scan['viewport']['width']) <= 767 ? 'mobile' : 'desktop',
                'lcp_url' => $scan['lcp']['url'],
                'lcp_element_json' => wp_json_encode($scan['lcp']),
                'lcp_imagesrcset' => isset($scan['lcp']['srcset']) ? $scan['lcp']['srcset'] : '',
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
        if (!class_exists('UCP_Options') || !is_array($scan)) {
            return array();
        }

        $settings = UCP_Options::get_all();
        $updates = array();
        $applied = array();

        if (!empty($scan['lcp']['url'])) {
            $updates['enable_lazy_images'] = 1;
            $updates['enable_add_image_dimensions'] = 1;
            $updates['preload_critical_images'] = max(3, absint(isset($settings['preload_critical_images']) ? $settings['preload_critical_images'] : 0));
            $updates['lazyload_exclude_leading_images'] = max(1, absint(isset($settings['lazyload_exclude_leading_images']) ? $settings['lazyload_exclude_leading_images'] : 1));

            // Note: LCP priority is now driven by measured per-URL/browser hints at render time.
            $existing_rules = UCP_Helpers::normalize_multiline(isset($settings['fetchpriority_rules']) ? $settings['fetchpriority_rules'] : '');
            $clean_rules = self::remove_generated_lcp_fetchpriority_rules($existing_rules);
            if ($clean_rules !== $existing_rules) {
                $updates['fetchpriority_rules'] = implode("\n", array_slice($clean_rules, 0, 80));
            }

            $applied[] = 'automatic_lcp_image_priority';
        }

        $delay_candidates = isset($scan['delay_candidates']) && is_array($scan['delay_candidates']) ? $scan['delay_candidates'] : array();
        $third_party = isset($scan['third_party']) && is_array($scan['third_party']) ? $scan['third_party'] : array();
        $delay_fragments = self::delay_fragments_from_scan_resources(array_merge($delay_candidates, $third_party));

        if (!empty($delay_fragments)) {
            $existing = UCP_Helpers::normalize_multiline(isset($settings['delay_js_specified_scripts']) ? $settings['delay_js_specified_scripts'] : '');
            $merged = array_values(array_unique(array_filter(array_merge($existing, $delay_fragments), 'strlen')));
            $updates['enable_delay_js'] = 1;
            $updates['delay_js_mode'] = 'specified';
            $updates['delay_js_safe_mode'] = 1;
            $updates['delay_js_disable_click_delay'] = 1;
            $updates['delay_js_timeout'] = min(4, max(2, absint(isset($settings['delay_js_timeout']) ? $settings['delay_js_timeout'] : 4)));
            $updates['delay_js_specified_scripts'] = implode("\n", array_slice($merged, 0, 80));
            $updates['defer_all_js'] = 0;
            $updates['enable_native_script_strategy'] = 0;
            $updates['enable_js_combine'] = 0;
            $applied[] = 'measured_delay_js';
        }

        if (empty($updates)) {
            return array();
        }

        UCP_Options::update($updates);
        return array_values(array_unique($applied));
    }

    /**
     * Remove per-image LCP rules from older repairs.
     * Measured LCP hints are applied dynamically.
     *
     * @param string[] $rules Existing multiline rules.
     * @return string[]
     */
    protected static function remove_generated_lcp_fetchpriority_rules($rules) {
        $out = array();
        foreach ((array) $rules as $rule) {
            $rule = trim((string) $rule);
            if ('' === $rule) {
                continue;
            }
            if (preg_match('/^img\[src\*=["\'][^"\']+["\']\]\|all\|all\|high$/i', $rule)) {
                continue;
            }
            $out[] = $rule;
        }
        return array_values(array_unique($out));
    }

    /**
     * Build safe script fragments for Delay JS from browser-scan resources.
     *
     * @param array<int,mixed> $items Scan resources.
     * @return string[]
     */
    protected static function delay_fragments_from_scan_resources($items) {
        $items = is_array($items) ? $items : array();
        $blocked = array('jquery', 'jquery-core', 'jquery-migrate', 'wp-i18n', 'wp-hooks', 'wp-element', 'wp-api-fetch', 'recaptcha', 'grecaptcha', 'hcaptcha', 'turnstile', 'wc-checkout', 'wc-cart-fragments', 'woocommerce', 'stripe', 'paypal', 'mollie', 'klarna', 'adyen');
        $out = array();

        foreach ($items as $item) {
            $url = is_array($item) && isset($item['url']) ? esc_url_raw((string) $item['url']) : (is_string($item) ? esc_url_raw($item) : '');
            $label = is_array($item) && isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
            $haystack = strtolower($url . ' ' . $label);
            if ('' === trim($haystack)) {
                continue;
            }
            $skip = false;
            foreach ($blocked as $needle) {
                if (false !== strpos($haystack, $needle)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $path = $url ? wp_parse_url($url, PHP_URL_PATH) : '';
            $base = $path ? basename((string) $path) : '';
            if ('' !== $base && preg_match('/\.js(?:$|\?)/i', $base)) {
                $out[] = sanitize_text_field($base);
                continue;
            }

            foreach (array('googletagmanager', 'gtag', 'analytics', 'fbevents', 'facebook', 'clarity', 'hotjar', 'joinchat', 'whatsapp', 'elementor', 'frontend-modules', 'elements-handlers', 'swiper', 'slick', 'sticky') as $known) {
                if (false !== strpos($haystack, $known)) {
                    $out[] = $known;
                    continue 2;
                }
            }

            if ('' !== $path) {
                $out[] = sanitize_text_field($path);
            } elseif ('' !== $label) {
                $out[] = sanitize_text_field($label);
            }
        }

        return array_values(array_unique(array_filter($out, 'strlen')));
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
        if (!self::urls_match_request($current, $scanned)) {
            return array();
        }
        $url = UCP_Helpers::strict_local_url((string) $scan['lcp']['url']);
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
        return self::urls_match_request($current, $scanned);
    }

    protected static function urls_match_request($a, $b) {
        $ka = self::url_match_key($a);
        $kb = self::url_match_key($b);
        return '' !== $ka && '' !== $kb && $ka === $kb;
    }

    protected static function url_match_key($url) {
        $parts = wp_parse_url((string) $url);
        if (empty($parts['host'])) {
            return '';
        }
        $host = strtolower((string) $parts['host']);
        $path = isset($parts['path']) && '' !== $parts['path'] ? (string) $parts['path'] : '/';
        $path = '/' . ltrim($path, '/');
        return $host . untrailingslashit($path);
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
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwarded_proto = strtolower(sanitize_key(wp_unslash($_SERVER['HTTP_X_FORWARDED_PROTO'])));
            if (in_array($forwarded_proto, array('http', 'https'), true)) {
                $scheme = $forwarded_proto;
            }
        }
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
        $url = isset($item['url']) ? UCP_Helpers::strict_local_url((string) $item['url']) : '';
        if ('' === $url && !empty($item['url'])) {
            $url = '';
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
            'srcset' => isset($item['srcset']) ? sanitize_text_field((string) $item['srcset']) : '',
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
