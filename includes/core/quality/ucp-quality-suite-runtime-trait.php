<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Quality_Suite_Runtime_Trait {
    public static function expire_debug_mode() {
        $until = (int) get_option(self::DEBUG_UNTIL_OPTION, 0);
        if ($until && time() > $until) {
            self::disable_support_mode('expired');
        }
    }

    public static function rest_runtime_cache_test() {
        $report = self::run_runtime_cache_test();
        return self::action_success(__('Cache runtime test uitgevoerd.', 'ultracache-pro'), array('report' => $report));
    }

    public static function run_runtime_cache_test() {
        $home = home_url('/');
        $report = array(
            'generated_at' => gmdate('c'),
            'wp_cache' => UCP_Helpers::has_valid_wp_cache_constant(),
            'advanced_cache' => file_exists(WP_CONTENT_DIR . '/advanced-cache.php'),
            'dropin_config' => class_exists('UCP_Helpers') ? file_exists(UCP_Helpers::dropin_config_path()) : false,
            'home' => array(),
            'transactional' => array(),
        );
        do_action('ucp_operation_heartbeat');
        $report['home']['first'] = self::probe_url($home);
        do_action('ucp_operation_heartbeat');
        $report['home']['second'] = self::probe_url($home);
        $report['home']['result'] = self::classify_cache_result($report['home']['first'], $report['home']['second']);

        // Guard against drift between the drop-in and PHP cache-key logic.
        do_action('ucp_operation_heartbeat');
        $report['key_consistency'] = self::check_key_consistency();

        $tests = array('winkelwagen' => home_url('/winkelwagen/'), 'afrekenen' => home_url('/afrekenen/'), 'mijn_account' => home_url('/mijn-account/'));
        if (function_exists('wc_get_page_id')) {
            foreach (array('winkelwagen' => 'cart', 'afrekenen' => 'checkout', 'mijn_account' => 'myaccount') as $key => $wc_page) {
                $page_id = wc_get_page_id($wc_page);
                if ($page_id && $page_id > 0) {
                    $tests[$key] = get_permalink($page_id);
                }
            }
        }
        foreach ($tests as $key => $url) {
            do_action('ucp_operation_heartbeat');
            $probe = self::probe_url($url);
            $probe['expected'] = 'bypass';
            $probe['safety_reason'] = self::bypass_reason($url);
            $report['transactional'][$key] = $probe;
        }
        do_action('ucp_operation_heartbeat');
        update_option(self::RUNTIME_OPTION, $report, false);
        UCP_Logger::log('notice', 'runtime', 'runtime_cache_test_completed', __('Cache-runtimetest is voltooid.', 'ultracache-pro'), array('home_result' => $report['home']['result'], 'wp_cache' => $report['wp_cache']));
        return $report;
    }

    /**
     * Recompute the cache key the way advanced-cache.php does and compare it to the PHP
     * helper for a set of tricky URLs. Returns ok=false (with the offending samples) if they
     * ever diverge, so the quality suite surfaces drop-in/PHP key drift before it ships.
     */
    protected static function check_key_consistency() {
        $samples = array(
            home_url('/'),
            home_url('/foo/bar/'),
            home_url('/foo-bar/'),
            home_url('/a/b/c'),
            home_url('/sample-page/'),
        );
        $mismatches = array();
        foreach ($samples as $url) {
            $php_key = UCP_Helpers::cache_key_for_url($url);
            $dropin_key = self::dropin_style_key($url);
            if ($php_key !== $dropin_key) {
                $mismatches[] = array('url' => $url, 'php' => $php_key, 'dropin' => $dropin_key);
            }
        }
        // The collision pair must also map to DIFFERENT keys.
        $k1 = UCP_Helpers::cache_key_for_url(home_url('/foo/bar/'));
        $k2 = UCP_Helpers::cache_key_for_url(home_url('/foo-bar/'));
        $collision = ($k1 === $k2);

        return array(
            'ok' => empty($mismatches) && !$collision,
            'mismatches' => $mismatches,
            'slash_dash_collision' => $collision,
        );
    }

    /**
     * Mirror the advanced-cache.php key recipe for verification only.
     * Keep this close to the drop-in so key changes are caught during testing.
     */
    protected static function dropin_style_key($url) {
        $parts = wp_parse_url($url);
        $path_only = isset($parts['path']) && '' !== $parts['path'] ? $parts['path'] : '/';
        $raw_path = '' === rtrim($path_only, '/') ? '/' : rtrim($path_only, '/');
        // Independently re-implement the advanced-cache.php slug recipe (do NOT call the shared
        // helper here) so this check still fails if the drop-in and PHP helper drift.
        $slug = str_replace('/', '-', rtrim($path_only, '/'));
        $slug = UCP_Helpers::sanitize_preg_replace('/[^A-Za-z0-9_.-]/', '-', $slug);
        $slug = UCP_Helpers::sanitize_preg_replace('/-+/', '-', (string) $slug);
        $slug = trim((string) $slug, '-');
        $path = '' === $slug ? 'home' : $slug;
        $path_hash = substr(md5($raw_path), 0, 8);
        $query = isset($parts['query']) ? UCP_Helpers::normalized_cache_query($parts['query']) : '';
        $query_key = '' !== $query ? md5($query) : 'noq';
        $raw_host = isset($parts['host']) ? (string) $parts['host'] : (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $host = self::dropin_style_normalize_host($raw_host);
        $host_key = '' !== $host ? md5($host) : 'nohost';
        $is_mobile = UCP_Options::get('cache_mobile_separately') && UCP_Helpers::is_mobile_request();
        $suffix = 'guest' . ($is_mobile ? '-mobile' : '') . self::dropin_style_vary_suffix();
        return $host_key . '-' . $path . '-' . $path_hash . '-' . $suffix . '-' . $query_key;
    }

    /**
     * Independently mirror ucp_dropin_normalize_host() for the runtime drift check.
     *
     * @param mixed $host Candidate host value.
     * @return string
     */
    protected static function dropin_style_normalize_host($host) {
        if (!is_scalar($host)) {
            return '';
        }
        $host = trim((string) $host);
        if ('' === $host || preg_match('~[\x00-\x20\x7f<>{}\\/@?#]~', $host)) {
            return '';
        }
        $host = strtolower($host);
        if (preg_match('/^\[([^]]+)\](?::([0-9]{1,5}))?$/', $host, $match)) {
            if (!filter_var($match[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return '';
            }
            if (isset($match[2]) && ((int) $match[2] < 1 || (int) $match[2] > 65535)) {
                return '';
            }
            return '[' . strtolower($match[1]) . ']';
        }
        if (1 === substr_count($host, ':')) {
            if (!preg_match('/^([^:]+):([0-9]{1,5})$/', $host, $match)
                || (int) $match[2] < 1 || (int) $match[2] > 65535) {
                return '';
            }
            $host = $match[1];
        } elseif (false !== strpos($host, ':')) {
            return '';
        }
        $host = rtrim($host, '.');
        if ('' === $host) {
            return '';
        }
        if (preg_match('/[^\x20-\x7e]/', $host)) {
            if (!function_exists('idn_to_ascii')) {
                return '';
            }
            $flags = defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0;
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
            $ascii = idn_to_ascii($host, $flags, $variant);
            if (!is_string($ascii) || '' === $ascii) {
                return '';
            }
            $host = strtolower($ascii);
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $host;
        }
        if (strlen($host) > 253 || !preg_match('/^[a-z0-9.-]+$/', $host)) {
            return '';
        }
        foreach (explode('.', $host) as $label) {
            if ('' === $label || strlen($label) > 63 || '-' === $label[0] || '-' === substr($label, -1)) {
                return '';
            }
        }
        return $host;
    }

    protected static function dropin_style_vary_suffix() {
        if (!class_exists('UCP_Shopper_Cache')) {
            return '';
        }
        $fragments = UCP_Shopper_Cache::vary_cookie_fragments();
        $request_cookies = UCP_Helpers::cookie_map(128, 4096);
        if (empty($fragments) || false === $request_cookies || empty($request_cookies)) {
            return '';
        }

        $pairs = array();
        foreach ($request_cookies as $name => $value) {
            $raw_name = (string) $name;
            $match_name = sanitize_key($raw_name);
            if ('' === $match_name || is_array($value)) {
                continue;
            }
            foreach ($fragments as $fragment) {
                $fragment = sanitize_key((string) $fragment);
                if ('' !== $fragment && 0 === strpos($match_name, $fragment)) {
                    $raw_value = (string) wp_unslash($value);
                    $name_hash = hash('sha256', $raw_name);
                    $pairs[$name_hash] = $name_hash . '=' . hash('sha256', $raw_value);
                    break;
                }
            }
        }
        if (empty($pairs)) {
            return '';
        }
        ksort($pairs);
        return '-v' . substr(md5(implode('|', $pairs)), 0, 10);
    }

    protected static function probe_url($url, $request_headers = array()) {
        $requested_url = class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'strict_local_url')
            ? UCP_Helpers::strict_local_url($url)
            : '';
        if ('' === $requested_url) {
            return array(
                'url' => is_scalar($url) ? esc_url_raw((string) $url) : '',
                'ok' => false,
                'error' => __('Ongeldige of externe test-URL.', 'ultracache-pro'),
                'duration_ms' => 0,
            );
        }

        $start = microtime(true);
        $current_url = $requested_url;
        $response = null;
        $redirects = 0;
        while ($redirects <= 3) {
            $response = wp_remote_get($current_url, array(
                'timeout' => 12,
                // Follow redirects manually so every hop remains on the exact configured
                // WordPress origin. This preserves private/local staging support without
                // allowing a same-site redirect to become a server-side request elsewhere.
                'redirection' => 0,
                'limit_response_size' => 2 * MB_IN_BYTES,
                'sslverify' => apply_filters('https_local_ssl_verify', true),
                'headers' => array_merge(array('X-UltraCache-Test' => '1', 'Accept-Encoding' => 'br, gzip'), is_array($request_headers) ? $request_headers : array()),
                'user-agent' => 'UltraCache Runtime Tester/' . (defined('UCP_VERSION') ? UCP_VERSION : 'dev'),
            ));
            if (is_wp_error($response)) {
                break;
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            if (!in_array($status, array(301, 302, 303, 307, 308), true)) {
                break;
            }
            if ($redirects >= 3) {
                $response = new WP_Error('ucp_probe_redirect_limit', __('Te veel omleidingen tijdens de runtimecontrole.', 'ultracache-pro'));
                break;
            }

            $location = (string) wp_remote_retrieve_header($response, 'location');
            $next_url = self::resolve_probe_redirect_url($location, $current_url);
            if ('' === $next_url) {
                $response = new WP_Error('ucp_probe_unsafe_redirect', __('Externe of onveilige omleiding geblokkeerd tijdens de runtimecontrole.', 'ultracache-pro'));
                break;
            }
            $current_url = $next_url;
            $redirects++;
        }
        $duration = round((microtime(true) - $start) * 1000);
        if (is_wp_error($response)) {
            return array('url' => $requested_url, 'ok' => false, 'error' => $response->get_error_message(), 'duration_ms' => $duration);
        }
        $headers = wp_remote_retrieve_headers($response);
        $headers_array = is_object($headers) && method_exists($headers, 'getAll') ? $headers->getAll() : (array) $headers;
        $raw_body = wp_remote_retrieve_body($response);
        $body = UCP_Helpers::bounded_response_body($raw_body, 2 * MB_IN_BYTES, 0);
        if (false === $body) {
            return array(
                'url' => $requested_url,
                'final_url' => $current_url,
                'redirects' => $redirects,
                'ok' => false,
                'error' => __('Het testantwoord is te groot of mogelijk afgekapt.', 'ultracache-pro'),
                'duration_ms' => $duration,
                'body_bytes' => is_string($raw_body) ? strlen($raw_body) : 0,
            );
        }
        return array(
            'url' => $requested_url,
            'final_url' => $current_url,
            'redirects' => $redirects,
            'ok' => true,
            'status' => (int) wp_remote_retrieve_response_code($response),
            'duration_ms' => $duration,
            'headers' => self::redacted_probe_headers($headers_array),
            'body_bytes' => strlen($body),
            'has_ultracache_comment' => false !== stripos($body, 'UltraCache'),
        );
    }


    /**
     * Resolve a redirect Location against the URL that produced it.
     *
     * Location may be absolute, scheme-relative, root-relative, path-relative,
     * or query-only. Resolving before strict origin validation prevents both
     * redirect-based SSRF and incorrect probes of /bar when the server meant
     * /foo/bar relative to /foo/.
     *
     * @param string $location Redirect Location header.
     * @param string $current_url URL that returned the redirect.
     * @return string Validated absolute local URL, or an empty string.
     */
    protected static function resolve_probe_redirect_url($location, $current_url) {
        if (!class_exists('UCP_Helpers') || !method_exists('UCP_Helpers', 'strict_local_url')) {
            return '';
        }

        $location = trim((string) $location);
        if ('' === $location || preg_match('/[\x00-\x1F\x7F]/', $location)) {
            return '';
        }
        $location = str_replace('\\', '/', $location);

        // Fragments are not sent in HTTP requests. Preserve the current resource
        // when Location contains only a fragment and strip fragments otherwise.
        $fragment_pos = strpos($location, '#');
        if (false !== $fragment_pos) {
            $location = substr($location, 0, $fragment_pos);
            if ('' === $location) {
                return UCP_Helpers::strict_local_url($current_url);
            }
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) && !preg_match('#^https?://#i', $location)) {
            return '';
        }
        if (preg_match('#^https?://#i', $location) || 0 === strpos($location, '//')) {
            return UCP_Helpers::strict_local_url($location);
        }

        $current = wp_parse_url($current_url);
        if (!is_array($current) || empty($current['scheme']) || empty($current['host'])) {
            return '';
        }
        $host = (string) $current['host'];
        if (false !== strpos($host, ':') && '[' !== substr($host, 0, 1)) {
            $host = '[' . $host . ']';
        }
        $origin = strtolower((string) $current['scheme']) . '://' . $host;
        if (!empty($current['port'])) {
            $origin .= ':' . absint($current['port']);
        }

        if (0 === strpos($location, '/')) {
            return UCP_Helpers::strict_local_url($origin . $location);
        }

        $current_path = isset($current['path']) && '' !== (string) $current['path'] ? (string) $current['path'] : '/';
        if (0 === strpos($location, '?')) {
            return UCP_Helpers::strict_local_url($origin . $current_path . $location);
        }

        $relative = wp_parse_url($location);
        if (!is_array($relative)) {
            return '';
        }
        $relative_path = isset($relative['path']) ? (string) $relative['path'] : '';
        $base_path = '/' === substr($current_path, -1) ? $current_path : dirname($current_path) . '/';
        $combined = $base_path . $relative_path;
        $trailing_slash = '/' === substr($combined, -1);
        $segments = array();
        foreach (explode('/', $combined) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $path = '/' . implode('/', $segments);
        if ($trailing_slash && '/' !== $path) {
            $path .= '/';
        }
        $query = isset($relative['query']) ? '?' . (string) $relative['query'] : '';

        return UCP_Helpers::strict_local_url($origin . $path . $query);
    }

    protected static function redacted_probe_headers($headers_array) {
        $headers = array_intersect_key(array_change_key_case((array) $headers_array, CASE_LOWER), array_flip(array('x-ultracache','x-cache','cache-control','age','server','set-cookie','content-encoding','vary')));
        if (isset($headers['set-cookie'])) {
            $set_cookie = $headers['set-cookie'];
            $cookies = is_array($set_cookie) ? $set_cookie : array($set_cookie);
            $redacted = array();
            foreach ($cookies as $cookie) {
                $name = trim((string) strtok((string) $cookie, '='));
                if ('' !== $name) {
                    $redacted[] = sanitize_key($name) . '=[redacted]';
                }
            }
            $headers['set-cookie'] = $redacted;
        }
        return $headers;
    }

    protected static function classify_cache_result($first, $second) {
        $headers = isset($second['headers']) ? $second['headers'] : array();
        $joined = strtolower(UCP_Helpers::safe_json_encode_or($headers, '{}'));
        if (false !== strpos($joined, 'hit') || !empty($second['has_ultracache_comment'])) {
            return 'hit_or_cached_signal';
        }
        if (!empty($first['ok']) && !empty($second['ok'])) {
            return 'reachable_no_hit_header';
        }
        return 'failed';
    }
}
