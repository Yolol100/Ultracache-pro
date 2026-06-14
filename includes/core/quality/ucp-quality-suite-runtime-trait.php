<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Quality_Suite_Runtime_Trait {
    public static function expire_debug_mode() {
        $until = (int) get_option(self::DEBUG_UNTIL_OPTION, 0);
        if ($until && time() > $until) {
            $settings = UCP_Options::get_all();
            $settings['enable_runtime_debug_headers'] = 0;
            UCP_Options::update($settings);
            delete_option(self::DEBUG_UNTIL_OPTION);
            UCP_Logger::log('notice', 'diagnostics', 'debug_mode_expired', 'Debug/testmodus automatisch verlopen.');
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
        $report['home']['first'] = self::probe_url($home);
        $report['home']['second'] = self::probe_url($home);
        $report['home']['result'] = self::classify_cache_result($report['home']['first'], $report['home']['second']);

        // Guard against drift between the drop-in and PHP cache-key logic.
        $report['key_consistency'] = self::check_key_consistency();

        $tests = array('winkelwagen' => home_url('/winkelwagen/'), 'afrekenen' => home_url('/afrekenen/'), 'mijn_account' => home_url('/mijn-account/'));
        if (function_exists('wc_get_page_id')) {
            foreach (array('cart' => 'cart', 'checkout' => 'checkout', 'my_account' => 'myaccount') as $key => $wc_page) {
                $page_id = wc_get_page_id($wc_page);
                if ($page_id && $page_id > 0) {
                    $tests[$key] = get_permalink($page_id);
                }
            }
        }
        foreach ($tests as $key => $url) {
            $probe = self::probe_url($url);
            $probe['expected'] = 'bypass';
            $probe['safety_reason'] = self::bypass_reason($url);
            $report['transactional'][$key] = $probe;
        }
        update_option(self::RUNTIME_OPTION, $report, false);
        UCP_Logger::log('notice', 'runtime', 'runtime_cache_test_completed', 'Cache runtime test completed.', array('home_result' => $report['home']['result'], 'wp_cache' => $report['wp_cache']));
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
        $slug = preg_replace('/[^A-Za-z0-9_.-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', (string) $slug);
        $slug = trim((string) $slug, '-');
        $path = '' === $slug ? 'home' : $slug;
        $path_hash = substr(md5($raw_path), 0, 8);
        $query = isset($parts['query']) ? UCP_Helpers::normalized_cache_query($parts['query']) : '';
        $query_key = '' !== $query ? md5($query) : 'noq';
        $raw_host = isset($parts['host']) ? (string) $parts['host'] : (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        // Independently mirror ucp_dropin_normalize_host() (strip port, keep IPv6 brackets, restrict
        // to [a-z0-9.-]) so this drift-check validates against the canonical drop-in recipe, not the
        // pre-normalization host string.
        $host = strtolower(trim(wp_strip_all_tags($raw_host)));
        if (preg_match('/^\[([a-f0-9:]+)\](?::\d+)?$/i', $host, $host_match)) {
            $host = '[' . strtolower($host_match[1]) . ']';
        } else {
            if (preg_match('/^([^:]+):\d+$/', $host, $host_match)) {
                $host = $host_match[1];
            }
            $host = preg_replace('/[^a-z0-9.-]/', '', $host);
        }
        $host_key = '' !== $host ? md5($host) : 'nohost';
        $is_mobile = UCP_Options::get('cache_mobile_separately') && UCP_Helpers::is_mobile_request();
        $suffix = 'guest' . ($is_mobile ? '-mobile' : '');
        return $host_key . '-' . $path . '-' . $path_hash . '-' . $suffix . '-' . $query_key;
    }

    protected static function probe_url($url) {
        $start = microtime(true);
        $response = wp_remote_get($url, array(
            'timeout' => 12,
            'redirection' => 3,
            'sslverify' => apply_filters('https_local_ssl_verify', true),
            'headers' => array('X-UltraCache-Test' => '1'),
            'user-agent' => 'UltraCache Runtime Tester/' . (defined('UCP_VERSION') ? UCP_VERSION : 'dev'),
        ));
        $duration = round((microtime(true) - $start) * 1000);
        if (is_wp_error($response)) {
            return array('url' => $url, 'ok' => false, 'error' => $response->get_error_message(), 'duration_ms' => $duration);
        }
        $headers = wp_remote_retrieve_headers($response);
        $headers_array = is_object($headers) && method_exists($headers, 'getAll') ? $headers->getAll() : (array) $headers;
        $body = (string) wp_remote_retrieve_body($response);
        return array(
            'url' => $url,
            'ok' => true,
            'status' => (int) wp_remote_retrieve_response_code($response),
            'duration_ms' => $duration,
            'headers' => self::redacted_probe_headers($headers_array),
            'body_bytes' => strlen($body),
            'has_ultracache_comment' => false !== stripos($body, 'UltraCache'),
        );
    }


    protected static function redacted_probe_headers($headers_array) {
        $headers = array_intersect_key(array_change_key_case((array) $headers_array, CASE_LOWER), array_flip(array('x-ultracache','x-cache','cache-control','age','server','set-cookie')));
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
        $joined = strtolower(wp_json_encode($headers));
        if (false !== strpos($joined, 'hit') || !empty($second['has_ultracache_comment'])) {
            return 'hit_or_cached_signal';
        }
        if (!empty($first['ok']) && !empty($second['ok'])) {
            return 'reachable_no_hit_header';
        }
        return 'failed';
    }
}
