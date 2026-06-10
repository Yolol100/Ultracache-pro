<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Request data is inspected only to decide cache eligibility; no form data is processed or persisted here.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Request_Policy_Trait {
    protected function request_has_auth_headers() {
        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $key) {
            if (!empty($_SERVER[$key]) && is_string($_SERVER[$key])) {
                return true;
            }
        }

        return !empty($_SERVER['PHP_AUTH_USER']) || !empty($_SERVER['PHP_AUTH_DIGEST']);
    }

    protected function request_has_nocache_headers() {
        $cache_control = isset($_SERVER['HTTP_CACHE_CONTROL']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_CACHE_CONTROL']))) : '';
        $pragma = isset($_SERVER['HTTP_PRAGMA']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_PRAGMA']))) : '';

        foreach (array('no-cache', 'no-store', 'private', 'max-age=0') as $fragment) {
            if (false !== strpos($cache_control, $fragment)) {
                return true;
            }
        }

        return false !== strpos($pragma, 'no-cache');
    }

    protected function request_has_sensitive_query_args() {
        foreach (array('preview', 'preview_id', 'preview_nonce', 'customize_changeset_uuid', 'customize_theme', 'elementor-preview', 'ct_builder', 'bricks', 'breakdance', 'fl_builder', 'oxygen_iframe', 'et_fb', 'vc_editable', 'nonce', '_wpnonce', 'add-to-cart', 'wc-ajax', 'wc-api', 'apply_coupon', 'remove_item', 'undo_item', 'update_cart', 'add-payment-method', 'order-pay', 'customer-logout') as $key) {
            if (isset($_GET[$key])) {
                return true;
            }
        }

        return false;
    }


    protected function can_cache_current_query($settings) {
        if (empty($_GET)) {
            return true;
        }
        if (!empty($settings['cache_query_strings'])) {
            return true;
        }
        $query_string = isset($_SERVER['QUERY_STRING']) ? sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING'])) : '';
        $normalized_query = UCP_Helpers::normalized_cache_query($query_string);
        if ('' === $normalized_query) {
            return true;
        }
        parse_str((string) $query_string, $args);
        $allowed = class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'cache_include_query_patterns')
            ? UCP_Helpers::cache_include_query_patterns(UCP_Helpers::normalize_multiline(isset($settings['cache_query_string_inclusions']) ? $settings['cache_query_string_inclusions'] : ''))
            : array_merge(
                UCP_Helpers::normalize_multiline(isset($settings['cache_query_string_inclusions']) ? $settings['cache_query_string_inclusions'] : ''),
                apply_filters('ucp_cache_include_query_params', array())
            );
        foreach ((array) $args as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }
            if (class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'query_key_is_ignored_for_cache') && UCP_Helpers::query_key_is_ignored_for_cache($key, $allowed)) {
                continue;
            }
            if (!UCP_Helpers::query_key_matches($key, $allowed)) {
                return false;
            }
        }
        return true;
    }

    protected function request_matches_excluded_user_agent($settings) {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        if ('' === $ua) {
            return false;
        }
        foreach (UCP_Helpers::normalize_multiline(isset($settings['exclude_user_agents']) ? $settings['exclude_user_agents'] : '') as $pattern) {
            if (UCP_Helpers::wildcard_match($ua, $pattern)) {
                return true;
            }
        }
        return false;
    }

    protected function configured_always_purge_urls() {
        $urls = array();
        $patterns = array();
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('always_purge_urls', '')) as $line) {
            $line = trim((string) $line);
            if ('' === $line) {
                continue;
            }
            if (false !== strpos($line, '(.*)') || false !== strpos($line, '*')) {
                $patterns[] = $line;
                continue;
            }
            if (0 === strpos($line, 'http://') || 0 === strpos($line, 'https://')) {
                $urls[] = esc_url_raw($line);
            } else {
                $urls[] = home_url('/' . ltrim($line, '/'));
            }
        }
        if (!empty($patterns)) {
            do_action('ucp_always_purge_url_patterns', $patterns);
            $urls[] = home_url('/');
        }
        return array_values(array_unique(array_filter($urls)));
    }

    protected function response_cookie_name_from_header($raw_header) {
        $cookie = trim((string) preg_replace('/^set-cookie\s*:/i', '', (string) $raw_header));
        $name = trim((string) strtok($cookie, '='));
        return sanitize_key($name);
    }

    protected function response_cookie_matches_fragments($cookie_name, $fragments) {
        $cookie_name = strtolower(sanitize_key((string) $cookie_name));
        if ('' === $cookie_name) {
            return false;
        }
        foreach ((array) $fragments as $fragment) {
            $fragment = strtolower(sanitize_key((string) $fragment));
            if ('' !== $fragment && false !== strpos($cookie_name, $fragment)) {
                return true;
            }
        }
        return false;
    }

    protected function cache_safe_response_cookie_fragments() {
        return apply_filters('ucp_cache_safe_set_cookie_fragments', array(
            'ct_',
            'apbct_',
            'ct_sfw',
            'cleantalk',
            'cookiebot',
            'cookie_notice_',
            'cmplz_',
            'complianz_',
            'cookieyes',
            'cky-',
            'borlabs',
            'joinchat_',
            'wp-settings-',
            '_ga',
            '_gid',
            '_gat',
            '_gcl_',
            '_fbp',
            '_fbc',
            '_hj',
            '_clck',
            '_clsk',
            '_pk_id',
            '_pk_ses',
            '_uetsid',
            '_uetvid',
            '_pin_unauth',
            '_scid',
            'li_gc',
            'lidc',
            'bcookie',
            'bscookie',
            'tk_ai',
            '__stripe_mid',
            '__stripe_sid',
            '__cf_bm',
            'cf_clearance',
        ));
    }

    protected function cache_safe_request_cookie_fragments() {
        return apply_filters('ucp_cache_safe_request_cookie_fragments', array(
            'ct_',
            'apbct_',
            'ct_sfw',
            'cleantalk',
            'cookiebot',
            'cookie_notice_',
            'cmplz_',
            'complianz_',
            'cookieyes',
            'cky-',
            'borlabs',
            'joinchat_',
            'wordpress_test_cookie',
            'wp-settings-',
            'wp-settings-time-',
            // Analytics / heatmap / advertising client-side cookies (no server personalization).
            '_ga',
            '_gid',
            '_gat',
            '_gcl_',
            '_fbp',
            '_fbc',
            '_hj',
            '_clck',
            '_clsk',
            '_pk_id',
            '_pk_ses',
            '_uetsid',
            '_uetvid',
            '_pin_unauth',
            '_scid',
            'li_gc',
            'li_mc',
            'lidc',
            'bcookie',
            'bscookie',
            'tk_ai',
            'tk_qs',
            // Payment-provider browser fingerprints that do not change page HTML.
            '__stripe_mid',
            '__stripe_sid',
            // Cloudflare bot-management cookie (per-request, not user state).
            '__cf_bm',
            'cf_clearance',
        ));
    }

    protected function request_cookie_is_cache_safe($cookie_name) {
        return $this->response_cookie_matches_fragments($cookie_name, $this->cache_safe_request_cookie_fragments());
    }

    protected function sensitive_response_cookie_fragments() {
        return apply_filters('ucp_sensitive_set_cookie_fragments', array(
            'wordpress_logged_in_',
            'wordpress_sec_',
            'wp-postpass_',
            'woocommerce_items_in_cart',
            'woocommerce_cart_hash',
            'wp_woocommerce_session_',
            'woocommerce_recently_viewed',
            'woocommerce_checkout_',
            'woocommerce_pay',
            'aelia_cs_selected_currency',
            'aelia_customer_country',
            'aelia_customer_state',
            'aelia_tax_exempt',
            'comment_author_',
            'wp-resetpass-',
        ));
    }

    protected function response_uncacheable_details() {
        $safe_set_cookies = array();
        $unknown_set_cookies = array();

        foreach (headers_list() as $header_line) {
            $raw_header = (string) $header_line;
            $header_line = strtolower($raw_header);
            if (0 === strpos($header_line, 'cache-control:') && (false !== strpos($header_line, 'no-cache') || false !== strpos($header_line, 'no-store') || false !== strpos($header_line, 'private'))) {
                return array(
                    'blocked' => true,
                    'reason'  => 'response_cache_control',
                    'header'  => $raw_header,
                );
            }
            if (0 === strpos($header_line, 'pragma:') && false !== strpos($header_line, 'no-cache')) {
                return array(
                    'blocked' => true,
                    'reason'  => 'response_pragma',
                    'header'  => $raw_header,
                );
            }
            if (0 === strpos($header_line, 'set-cookie:')) {
                $cookie_name = $this->response_cookie_name_from_header($raw_header);
                if ($this->response_cookie_matches_fragments($cookie_name, $this->sensitive_response_cookie_fragments())) {
                    return array(
                        'blocked' => true,
                        'reason'  => 'response_set_cookie_sensitive',
                        'cookie'  => $cookie_name,
                        'header'  => preg_replace('/=.*/', '=[redacted]', $raw_header),
                    );
                }
                if ($this->response_cookie_matches_fragments($cookie_name, $this->cache_safe_response_cookie_fragments())) {
                    $safe_set_cookies[] = $cookie_name;
                    continue;
                }
                $unknown_set_cookies[] = $cookie_name;
            }
        }

        if (!empty($unknown_set_cookies) && (bool) apply_filters('ucp_block_unknown_set_cookie_headers', true)) {
            return array(
                'blocked' => true,
                'reason'  => 'response_set_cookie_unknown',
                'cookies' => array_values(array_unique($unknown_set_cookies)),
            );
        }

        return array(
            'blocked' => false,
            'reason' => !empty($safe_set_cookies) || !empty($unknown_set_cookies) ? 'response_set_cookie_ignored' : '',
            'safe_cookies' => array_values(array_unique($safe_set_cookies)),
            'unknown_cookies' => array_values(array_unique($unknown_set_cookies)),
        );
    }

    protected function bypass_cache($reason) {
        $this->bypass_reason = sanitize_key((string) $reason);
        return false;
    }

    protected function maybe_send_cache_debug_header($status, $reason = '') {
        if (headers_sent() || is_admin()) {
            return;
        }
        $status = sanitize_key((string) $status);
        $reason = sanitize_key((string) $reason);
        header('X-UltraCache: ' . strtoupper($status));
        if ('' !== $reason) {
            header('X-UltraCache-Reason: ' . $reason);
        }
    }

    protected function request_matches_excluded_cookie_rules($settings) {
        $cookies = apply_filters('ucp_excluded_cookie_fragments', UCP_Helpers::normalize_multiline($settings['exclude_cookies']));
        foreach ((array) array_keys($_COOKIE) as $cookie_name) {
            $cookie_name = sanitize_key((string) $cookie_name);
            foreach ($cookies as $cookie_fragment) {
                if ('' !== $cookie_fragment && false !== strpos($cookie_name, sanitize_key((string) $cookie_fragment))) {
                    UCP_Diagnostics::record('cache', 'Bypassed cache for cookie rule', array(
                        'cookie' => $cookie_name,
                        'fragment' => trim($cookie_fragment),
                    ));
                    return 'cookie';
                }
            }
            $block_unknown = !empty($settings['block_unknown_request_cookies']);
            $block_unknown = (bool) apply_filters('ucp_block_unknown_request_cookies', $block_unknown, $cookie_name);
            if (!$this->request_cookie_is_cache_safe($cookie_name) && $block_unknown) {
                UCP_Diagnostics::record('cache', 'Bypassed cache for unknown request cookie', array('cookie' => $cookie_name));
                return 'unknown_cookie';
            }
        }
        return '';
    }

    protected function request_matches_excluded_url_rules($settings) {
        $path = UCP_Helpers::current_url_path();
        $excluded = apply_filters('ucp_excluded_url_fragments', UCP_Helpers::normalize_multiline($settings['exclude_urls']));
        foreach ($excluded as $fragment) {
            if (UCP_Helpers::wildcard_match($path . '?' . (isset($_SERVER['QUERY_STRING']) ? sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING'])) : ''), $fragment)) {
                return 'excluded_url';
            }
        }
        return '';
    }

    protected function request_matches_uri_optimization_exclusion($path) {
        $uri_rules = apply_filters('ucp_uri_optimization_exclusions', array());
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : $path;
        foreach ((array) $uri_rules as $rule) {
            $rule = trim((string) $rule);
            if ('' === $rule) {
                continue;
            }
            if ('^' === substr($rule, 0, 1)) {
                if (UCP_Helpers::safe_regex_match($rule, $request_uri)) {
                    return 'uri_rule';
                }
                continue;
            }
            if (false !== stripos($request_uri, $rule)) {
                return 'uri_rule';
            }
        }
        return '';
    }

    public function can_cache_request() {
        $settings = UCP_Options::get_all();

        if (empty($settings['enable_cache'])) {
            return $this->bypass_cache('disabled');
        }
        if (class_exists('UCP_Quality_Suite')) {
            $reason = UCP_Quality_Suite::bypass_reason(UCP_Helpers::current_full_url());
            if ('' !== $reason) {
                UCP_Diagnostics::record('cache', 'Bypassed cache by central safety layer', array('reason' => $reason));
                return $this->bypass_cache($reason);
            }
        }
        if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
            UCP_Diagnostics::record('cache', 'Bypassed cache because DONOTCACHEPAGE is active');
            return $this->bypass_cache('donotcachepage');
        }

        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed() || is_preview() || is_search() || is_404() || (function_exists('is_customize_preview') && is_customize_preview())) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for admin/ajax/rest/feed/search/customizer context');
            return $this->bypass_cache('context');
        }
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';
        if (!in_array(strtoupper($method), array('GET', 'HEAD'), true)) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for non-GET/HEAD request');
            return $this->bypass_cache('method');
        }
        if ($this->request_has_auth_headers()) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for authenticated request headers');
            return $this->bypass_cache('auth');
        }
        if ($this->request_has_nocache_headers()) {
            UCP_Diagnostics::record('cache', 'Bypassed cache because request asked for no-cache');
            return $this->bypass_cache('request_no_cache');
        }
        if ($this->request_has_sensitive_query_args()) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for preview or editor query arguments');
            return $this->bypass_cache('sensitive_query');
        }
        if (!$this->can_cache_current_query($settings)) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for query string that is not explicitly cacheable');
            return $this->bypass_cache('query');
        }
        if (is_user_logged_in() && empty($settings['cache_logged_in'])) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for logged-in user');
            return $this->bypass_cache('logged_in');
        }
        if (function_exists('post_password_required') && post_password_required()) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for password-protected content');
            return $this->bypass_cache('password');
        }
        if ($this->request_matches_excluded_user_agent($settings)) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for user-agent rule');
            return $this->bypass_cache('user_agent');
        }

        $cookie_bypass_reason = $this->request_matches_excluded_cookie_rules($settings);
        if ('' !== $cookie_bypass_reason) {
            return $this->bypass_cache($cookie_bypass_reason);
        }

        $url_bypass_reason = $this->request_matches_excluded_url_rules($settings);
        if ('' !== $url_bypass_reason) {
            return $this->bypass_cache($url_bypass_reason);
        }

        $path = UCP_Helpers::current_url_path();
        $uri_bypass_reason = $this->request_matches_uri_optimization_exclusion($path);
        if ('' !== $uri_bypass_reason) {
            return $this->bypass_cache($uri_bypass_reason);
        }

        if (UCP_Rule_Engine::has_action('disable_cache')) {
            UCP_Diagnostics::record('cache', 'Bypassed cache because visual rule builder matched current request');
            return $this->bypass_cache('rule');
        }
        if (UCP_Rule_Engine::evaluate_request()) {
            UCP_Diagnostics::record('cache', 'Rule builder matched current request');
        }
        return true;
    }

}
