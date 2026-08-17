<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Request data is inspected only to decide cache eligibility; no form data is processed or persisted here.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Request_Policy_Trait {

    protected function should_bypass_cache_for_woocommerce() {
        if (!function_exists('WC') && !class_exists('WooCommerce')) {
            return '';
        }

        $method = UCP_Helpers::request_method();
        if ('' === $method) {
            return 'invalid_request_method';
        }
        if ('POST' === $method) {
            return 'woocommerce_post';
        }

        if (function_exists('is_cart') && is_cart()) {
            return 'woocommerce_cart';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return 'woocommerce_checkout';
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return 'woocommerce_account';
        }

        // Exact URL/query matching is handled by UCP_Quality_Suite before
        // this WooCommerce-specific guard. Do not repeat it with substring
        // checks here: values such as ?x=wc-ajax and /order-payments/ are safe.

        $request_cookies = UCP_Helpers::cookie_map(128, 4096);
        if (false === $request_cookies) {
            return 'invalid_cookie_shape';
        }
        foreach ($request_cookies as $cookie_name => $cookie_value) {
            $name = strtolower((string) $cookie_name);
            foreach (array('woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_', 'woocommerce_recently_viewed', 'woocommerce_checkout_', 'woocommerce_pay') as $fragment) {
                if (false !== strpos($name, $fragment)) {
                    return 'woocommerce_cookie';
                }
            }
        }

        return '';
    }

    protected function request_has_method_override() {
        if ('' !== UCP_Helpers::server_value('HTTP_X_HTTP_METHOD_OVERRIDE', '', 32)) {
            return true;
        }
        $query = UCP_Helpers::query_args(100, 4, 8192);
        return false === $query || array_key_exists('_method', $query);
    }

    protected function request_has_auth_headers() {
        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $key) {
            if ('' !== UCP_Helpers::server_value($key, '', 8192)) {
                return true;
            }
        }

        return '' !== UCP_Helpers::server_value('PHP_AUTH_USER', '', 1024) || '' !== UCP_Helpers::server_value('PHP_AUTH_DIGEST', '', 8192);
    }

    protected function request_has_nocache_headers() {
        $cache_control = strtolower(UCP_Helpers::server_value('HTTP_CACHE_CONTROL', '', 8192));
        $pragma = strtolower(UCP_Helpers::server_value('HTTP_PRAGMA', '', 1024));

        if (UCP_Cache_Policy::request_cache_control_requires_revalidation($cache_control)) {
            return true;
        }

        return false !== strpos($pragma, 'no-cache');
    }

    protected function request_has_range_header() {
        foreach (array('HTTP_RANGE', 'HTTP_IF_RANGE') as $header) {
            if ('' !== UCP_Helpers::server_value($header, '', 8192)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse an RFC 9110 qvalue. Invalid q parameters are deliberately treated as q=0.
     *
     * @param array $parameters Header parameters following a media range.
     * @return float
     */
    protected function request_header_quality($parameters) {
        return UCP_Cache_Policy::request_header_quality($parameters);
    }

    /**
     * Return the quality of a concrete media type in an Accept header.
     * More-specific ranges override wildcards, including when their qvalue is zero.
     *
     * @param string $header     Accept header value.
     * @param string $type       Response media type.
     * @param string $subtype    Response media subtype.
     * @param array  $parameters Representation parameters.
     * @return float
     */
    protected function request_media_quality($header, $type, $subtype, $parameters = array()) {
        $header = strtolower(trim((string) $header));
        $type = strtolower(trim((string) $type));
        $subtype = strtolower(trim((string) $subtype));
        $parameters = is_array($parameters) ? array_change_key_case($parameters, CASE_LOWER) : array();
        if ('' === $header) {
            return 1.0;
        }
        if ('' === $type || '' === $subtype) {
            return 0.0;
        }

        $best_specificity = -1;
        $best_quality = 0.0;
        foreach (explode(',', $header) as $item) {
            $segments = array_map('trim', explode(';', $item));
            $media_range = array_shift($segments);
            $range_parts = explode('/', (string) $media_range, 2);
            if (2 !== count($range_parts)) {
                continue;
            }
            $range_type = trim($range_parts[0]);
            $range_subtype = trim($range_parts[1]);
            if ('*' === $range_type && '*' === $range_subtype) {
                $specificity = 0;
            } elseif ($type === $range_type && '*' === $range_subtype) {
                $specificity = 1;
            } elseif ($type === $range_type && $subtype === $range_subtype) {
                $specificity = 2;
            } else {
                continue;
            }

            $media_parameter_count = 0;
            $range_matches = true;
            foreach ($segments as $parameter) {
                if (preg_match('/^q\s*=/i', (string) $parameter)) {
                    continue;
                }
                $pair = explode('=', (string) $parameter, 2);
                if (2 !== count($pair)) {
                    $range_matches = false;
                    break;
                }
                $parameter_name = strtolower(trim($pair[0]));
                $parameter_value = strtolower(trim($pair[1], " \t\n\r\0\x0B\""));
                if ('' === $parameter_name
                    || !array_key_exists($parameter_name, $parameters)
                    || strtolower(trim((string) $parameters[$parameter_name], " \t\n\r\0\x0B\"")) !== $parameter_value) {
                    $range_matches = false;
                    break;
                }
                ++$media_parameter_count;
            }
            if (!$range_matches) {
                continue;
            }

            $specificity = ($specificity * 1000) + $media_parameter_count;
            $quality = $this->request_header_quality($segments);
            if ($specificity > $best_specificity) {
                $best_specificity = $specificity;
                $best_quality = $quality;
            } elseif ($specificity === $best_specificity) {
                $best_quality = max($best_quality, $quality);
            }
        }

        return $best_specificity >= 0 ? $best_quality : 0.0;
    }

    protected function request_accepts_html() {
        $accept = strtolower(UCP_Helpers::server_value('HTTP_ACCEPT', '', 16384));
        $parameters = array('charset' => strtolower((string) get_bloginfo('charset')));
        // WordPress' normal frontend representation is text/html. The cached
        // representation is checked again against its concrete Content-Type by the drop-in.
        return $this->request_media_quality($accept, 'text', 'html', $parameters) > 0.0;
    }

    protected function request_has_sensitive_query_args() {
        $query = UCP_Helpers::query_args(100, 4, 8192);
        if (false === $query) {
            return true;
        }
        foreach (array('preview', 'preview_id', 'preview_nonce', 'customize_changeset_uuid', 'customize_theme', 'elementor-preview', 'ct_builder', 'bricks', 'breakdance', 'fl_builder', 'oxygen_iframe', 'et_fb', 'vc_editable', 'nonce', '_wpnonce', 'add-to-cart', 'wc-ajax', 'wc-api', 'apply_coupon', 'remove_item', 'undo_item', 'update_cart', 'add-payment-method', 'order-pay', 'customer-logout') as $key) {
            if (array_key_exists($key, $query)) {
                return true;
            }
        }

        return false;
    }


    protected function can_cache_current_query($settings) {
        $query = UCP_Helpers::query_args(100, 4, 8192);
        if (false === $query) {
            return false;
        }
        if (empty($query)) {
            return true;
        }
        $query_string = UCP_Helpers::server_value('QUERY_STRING', '', 8192);
        $allowed = UCP_Helpers::normalize_multiline(isset($settings['cache_query_string_inclusions']) ? $settings['cache_query_string_inclusions'] : '');
        return UCP_Helpers::query_string_is_cacheable($query_string, !empty($settings['cache_query_strings']), $allowed);
    }

    protected function request_matches_excluded_user_agent($settings) {
        $ua = UCP_Helpers::server_value('HTTP_USER_AGENT', '', 2048);
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
            $matched_urls = class_exists('UCP_Cache_Tags') && method_exists('UCP_Cache_Tags', 'registered_urls_matching')
                ? UCP_Cache_Tags::registered_urls_matching($patterns)
                : array();
            $urls = array_merge($urls, $matched_urls);

            // Cache tags can be disabled, leaving no URL registry from which wildcard matches can
            // be resolved. In that case a full purge is the only correctness-safe interpretation.
            if (empty($matched_urls) && (!class_exists('UCP_Cache_Tags') || !method_exists('UCP_Cache_Tags', 'has_registered_urls') || !UCP_Cache_Tags::has_registered_urls())) {
                $this->always_purge_requires_full_purge = true;
            }
        }
        return array_values(array_unique(array_filter($urls)));
    }

    protected function response_cookie_name_from_header($raw_header) {
        $cookie = trim((string) UCP_Helpers::redact_preg_replace('/^set-cookie\s*:/i', '', (string) $raw_header));
        $name = trim((string) strtok($cookie, '='));
        if (class_exists('UCP_Cache_Policy') && !UCP_Cache_Policy::cookie_name_is_valid($name)) {
            return '';
        }
        return $name;
    }

    protected function response_cookie_matches_fragments($cookie_name, $fragments) {
        return UCP_Cache_Policy::cookie_name_matches_fragments($cookie_name, $fragments);
    }

    protected function response_cookie_matches_prefixes($cookie_name, $prefixes) {
        if (class_exists('UCP_Cache_Policy')) {
            return UCP_Cache_Policy::cookie_name_matches_prefixes($cookie_name, $prefixes);
        }
        $cookie_name = (string) $cookie_name;
        if (1 !== preg_match('/^[!#$%&\'()*+\-.^_`|~0-9A-Za-z]+$/', $cookie_name)) {
            return false;
        }
        foreach ((array) $prefixes as $prefix) {
            $prefix = trim((string) $prefix);
            if (1 !== preg_match('/^[!#$%&\'()*+\-.^_`|~0-9A-Za-z]+$/', $prefix)) {
                continue;
            }
            if (0 === strpos($cookie_name, $prefix)) {
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
        if (class_exists('UCP_Cache_Policy') && method_exists('UCP_Cache_Policy', 'safe_request_cookie_prefixes')) {
            return UCP_Cache_Policy::safe_request_cookie_prefixes();
        }
        return apply_filters('ucp_cache_safe_request_cookie_fragments', array(
            'ct_', 'apbct_', 'ct_sfw', 'cleantalk', 'cookiebot', 'cookie_notice_',
            'cmplz_', 'complianz_', 'cookieyes', 'cky-', 'borlabs', 'joinchat_',
            'wordpress_test_cookie', 'wp-settings-', 'wp-settings-time-',
            '_ga', '_gid', '_gat', '_gcl_', '_fbp', '_fbc', '_hj', '_clck', '_clsk',
            '_pk_id', '_pk_ses', '_uetsid', '_uetvid', '_pin_unauth', '_scid',
            'li_gc', 'li_mc', 'lidc', 'bcookie', 'bscookie', 'tk_ai', 'tk_qs',
            '__stripe_mid', '__stripe_sid', '__cf_bm', 'cf_clearance',
        ));
    }

    protected function request_cookie_is_cache_safe($cookie_name) {
        return $this->response_cookie_matches_prefixes($cookie_name, $this->cache_safe_request_cookie_fragments());
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

    protected function response_vary_is_unsupported($raw_header) {
        return UCP_Cache_Policy::response_vary_is_unsupported($raw_header);
    }

    protected function is_internal_light_preload_request() {
        $header = UCP_Helpers::server_value('HTTP_X_ULTRACACHE_LIGHT_PRELOAD', '', 8);
        if ('1' !== $header) {
            return false;
        }
        $user_agent = UCP_Helpers::server_value('HTTP_USER_AGENT', '', 8192);
        return false !== strpos($user_agent, 'UltraCachePro-Preloader/')
            || false !== strpos($user_agent, 'UltraCachePro-Preload-Queue/')
            || false !== strpos($user_agent, 'UltraCache-Mobile-Preloader/')
            || false !== strpos($user_agent, 'UltraCache-Mobile-Preload-Queue/');
    }

    protected function response_uncacheable_details() {
        $safe_set_cookies = array();
        $unknown_set_cookies = array();

        foreach (headers_list() as $header_line) {
            $raw_header = (string) $header_line;
            $header_line = strtolower($raw_header);
            if (0 === strpos($header_line, 'cache-control:')) {
                $cache_control_value = trim(substr($raw_header, strlen('cache-control:')));
                if (UCP_Cache_Policy::cache_control_disallows_shared_storage($cache_control_value)) {
                    return array(
                        'blocked'       => true,
                        'reason'        => 'response_cache_control',
                        'header'        => $raw_header,
                        'unconditional' => UCP_Cache_Policy::cache_control_forbids_storage_unconditionally($cache_control_value),
                    );
                }
            }
            if (0 === strpos($header_line, 'vary:') && $this->response_vary_is_unsupported($raw_header)) {
                return array(
                    'blocked' => true,
                    'reason'  => 'response_vary_unsupported',
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
            if (0 === strpos($header_line, 'content-encoding:')
                && UCP_Cache_Policy::response_content_encoding_disallows_storage(trim(substr($raw_header, strlen('content-encoding:'))))) {
                return array(
                    'blocked' => true,
                    'reason'  => 'response_content_encoding',
                    'header'  => $raw_header,
                );
            }
            if (0 === strpos($header_line, 'content-range:')) {
                return array(
                    'blocked' => true,
                    'reason'  => 'response_partial_content',
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
                        'header'  => UCP_Helpers::redact_preg_replace('/=.*/', '=[redacted]', $raw_header),
                    );
                }
                if ($this->response_cookie_matches_prefixes($cookie_name, $this->cache_safe_response_cookie_fragments())) {
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

    protected function cache_policy_decision($settings = null) {
        if (is_array($this->cache_policy_decision)) {
            return $this->cache_policy_decision;
        }
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $this->cache_policy_decision = class_exists('UCP_Cache_Policy')
            ? UCP_Cache_Policy::decision_for_current_request($settings)
            : array('matched' => false, 'action' => 'cache', 'ttl' => absint($settings['cache_lifespan']) * HOUR_IN_SECONDS, 'stale' => 0, 'scope' => '');
        return $this->cache_policy_decision;
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
        if ('bypass' === strtolower((string) $status) && class_exists('UCP_Cache_Insights')) {
            UCP_Cache_Insights::record_request('BYPASS', $reason);
        }
        if ('' !== $reason) {
            header('X-UltraCache-Reason: ' . $reason);
            if (0 === strpos($reason, 'woocommerce_')) {
                header('X-UCP-Cache-Bypass: ' . $reason);
            }
        }
    }

    protected function request_matches_excluded_cookie_rules($settings) {
        $cookies = apply_filters('ucp_excluded_cookie_fragments', UCP_Helpers::normalize_multiline($settings['exclude_cookies']));
        $block_unknown = !empty($settings['block_unknown_request_cookies']);
        $request_cookies = UCP_Helpers::cookie_map(128, 4096);
        if (false === $request_cookies) {
            return 'cookie';
        }
        foreach (array_keys($request_cookies) as $cookie_name) {
            $cookie_name = (string) $cookie_name;
            $normalized_cookie_name = sanitize_key($cookie_name);
            foreach ($cookies as $cookie_fragment) {
                if ('' !== $cookie_fragment && false !== strpos($normalized_cookie_name, sanitize_key((string) $cookie_fragment))) {
                    UCP_Diagnostics::record('cache', 'Bypassed cache for cookie rule', array(
                        'cookie' => $normalized_cookie_name,
                        'fragment' => trim($cookie_fragment),
                    ));
                    return 'cookie';
                }
            }
            $should_block_unknown = (bool) apply_filters('ucp_block_unknown_request_cookies', $block_unknown, $cookie_name);
            if (!$this->request_cookie_is_cache_safe($cookie_name) && $should_block_unknown) {
                UCP_Diagnostics::record('cache', 'Bypassed cache for unknown request cookie', array('cookie' => $normalized_cookie_name));
                return 'unknown_cookie';
            }
        }
        if ($block_unknown && class_exists('UCP_Cache_Policy')) {
            $raw_cookie_header = UCP_Helpers::server_value('HTTP_COOKIE', '', 16384);
            if ('' !== trim((string) $raw_cookie_header) && !UCP_Cache_Policy::cookie_header_is_safe_for_shared_cache($raw_cookie_header)) {
                UCP_Diagnostics::record('cache', 'Bypassed cache for malformed or unsafe raw request cookie header');
                return 'unknown_cookie';
            }
        }
        return '';
    }

    protected function request_matches_excluded_url_rules($settings) {
        $path = UCP_Helpers::current_url_path();
        $excluded = apply_filters('ucp_excluded_url_fragments', UCP_Helpers::normalize_multiline($settings['exclude_urls']));
        $query = UCP_Helpers::server_value('QUERY_STRING', '', 8192);
        $request_url = $path . ('' !== $query ? '?' . $query : '');
        foreach ($excluded as $fragment) {
            $matched = class_exists('UCP_Quality_Suite') && method_exists('UCP_Quality_Suite', 'matches_configured_url_pattern')
                ? UCP_Quality_Suite::matches_configured_url_pattern($request_url, $fragment)
                : UCP_Helpers::wildcard_match($request_url, $fragment);
            if ($matched) {
                return 'excluded_url';
            }
        }
        return '';
    }

    protected function request_matches_uri_optimization_exclusion($path) {
        $uri_rules = apply_filters('ucp_uri_optimization_exclusions', array());
        $request_uri = UCP_Helpers::server_value('REQUEST_URI', $path, 8192);
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
        $policy = $this->cache_policy_decision($settings);

        $reason = $this->cache_preflight_bypass_reason($settings, $policy);
        if ('' !== $reason) {
            return $this->bypass_cache($reason);
        }
        $reason = $this->cache_transport_bypass_reason($settings, $policy);
        if ('' !== $reason) {
            return $this->bypass_cache($reason);
        }
        $reason = $this->cache_identity_bypass_reason($settings);
        if ('' !== $reason) {
            return $this->bypass_cache($reason);
        }
        $reason = $this->cache_path_bypass_reason($settings);
        if ('' !== $reason) {
            return $this->bypass_cache($reason);
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

    protected function cache_preflight_bypass_reason($settings, $policy) {
        if (empty($settings['enable_cache'])) {
            return 'disabled';
        }
        if (class_exists('UCP_Quality_Suite')) {
            $reason = UCP_Quality_Suite::bypass_reason(UCP_Helpers::current_full_url());
            if ('' !== $reason) {
                UCP_Diagnostics::record('cache', 'Bypassed cache by central safety layer', array('reason' => $reason));
                return $reason;
            }
        }
        $reason = $this->should_bypass_cache_for_woocommerce();
        if ('' !== $reason) {
            UCP_Diagnostics::record('cache', 'Bypassed cache by hard WooCommerce guard', array('reason' => $reason));
            return $reason;
        }
        if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
            UCP_Diagnostics::record('cache', 'Bypassed cache because DONOTCACHEPAGE is active');
            return 'donotcachepage';
        }
        if (!empty($policy['matched']) && 'bypass' === $policy['action']) {
            UCP_Diagnostics::record('cache', 'Bypassed cache by cache policy rule', array('scope' => $policy['scope'], 'match' => $policy['match']));
            return 'policy_rule';
        }
        if ($this->cache_context_requires_bypass($policy)) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for admin/ajax/rest/feed/search/customizer context');
            return 'context';
        }
        return '';
    }

    protected function cache_context_requires_bypass($policy) {
        $policy_allows_special_context = !empty($policy['matched']) && 'cache' === $policy['action'] && in_array($policy['scope'], array('feed', 'status'), true);
        return is_admin()
            || wp_doing_ajax()
            || (defined('REST_REQUEST') && REST_REQUEST)
            || is_preview()
            || is_search()
            || (function_exists('is_customize_preview') && is_customize_preview())
            || ((is_feed() || is_404()) && !$policy_allows_special_context);
    }

    protected function cache_transport_bypass_reason($settings, $policy) {
        $method = UCP_Helpers::request_method();
        if (!in_array($method, array('GET', 'HEAD'), true)) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for non-GET/HEAD request');
            return 'method';
        }
        if ($this->request_has_method_override()) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for an HTTP method override');
            return 'method_override';
        }
        if ($this->request_has_auth_headers()) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for authenticated request headers');
            return 'auth';
        }
        if ($this->request_has_nocache_headers()) {
            UCP_Diagnostics::record('cache', 'Bypassed cache because request asked for no-cache');
            return 'request_no_cache';
        }
        if ($this->request_has_range_header()) {
            UCP_Diagnostics::record('cache', 'Bypassed cache generation for a byte-range request');
            return 'range_request';
        }
        if (!$this->request_accepts_html() && !(function_exists('is_feed') && is_feed() && !empty($policy['matched']) && 'feed' === $policy['scope'])) {
            UCP_Diagnostics::record('cache', 'Bypassed HTML cache because the request does not accept HTML');
            return 'accept_header';
        }
        if ($this->request_has_sensitive_query_args()) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for preview or editor query arguments');
            return 'sensitive_query';
        }
        if (!$this->can_cache_current_query($settings)) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for query string that is not explicitly cacheable');
            return 'query';
        }
        return '';
    }

    protected function cache_identity_bypass_reason($settings) {
        if (is_user_logged_in()) {
            if (empty($settings['cache_logged_in'])) {
                UCP_Diagnostics::record('cache', 'Bypassed cache for logged-in user');
                return 'logged_in';
            }
            $session_token = function_exists('wp_get_session_token') ? (string) wp_get_session_token() : '';
            if ('' === $session_token) {
                UCP_Diagnostics::record('cache', 'Bypassed private cache because the WordPress session token is unavailable');
                return 'logged_in_session';
            }
        }
        if (function_exists('post_password_required') && post_password_required()) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for password-protected content');
            return 'password';
        }
        if ($this->request_matches_excluded_user_agent($settings)) {
            UCP_Diagnostics::record('cache', 'Bypassed cache for user-agent rule');
            return 'user_agent';
        }
        return $this->request_matches_excluded_cookie_rules($settings);
    }

    protected function cache_path_bypass_reason($settings) {
        $reason = $this->request_matches_excluded_url_rules($settings);
        if ('' !== $reason) {
            return $reason;
        }
        return $this->request_matches_uri_optimization_exclusion(UCP_Helpers::current_url_path());
    }

}
