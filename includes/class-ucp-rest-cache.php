<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_REST_Cache {
    protected const CACHE_SCHEMA_VERSION = 'safe-v5';

    protected $cacheable_requests = array();
    protected $policy_decisions = array();

    public function __construct() {
        add_filter('rest_pre_dispatch', array($this, 'serve_cached'), 10, 3);
        add_filter('rest_post_dispatch', array($this, 'store_response'), 10, 3);

        foreach (array(
            'ucp_cache_purged_all', 'save_post', 'deleted_post', 'trashed_post',
            'created_term', 'edited_term', 'delete_term', 'comment_post', 'wp_set_comment_status',
            'added_post_meta', 'updated_post_meta', 'deleted_post_meta',
            'added_term_meta', 'updated_term_meta', 'deleted_term_meta',
            'added_user_meta', 'updated_user_meta', 'deleted_user_meta',
            'added_comment_meta', 'updated_comment_meta', 'deleted_comment_meta',
            'wp_update_nav_menu', 'wp_update_nav_menu_item', 'delete_nav_menu',
            'switch_theme', 'customize_save_after',
        ) as $hook) {
            add_action($hook, array(__CLASS__, 'bump_version'), 30);
        }
        add_action('added_option', array(__CLASS__, 'maybe_bump_for_option'), 30, 2);
        add_action('updated_option', array(__CLASS__, 'maybe_bump_for_option'), 30, 3);
        add_action('deleted_option', array(__CLASS__, 'maybe_bump_for_option'), 30, 1);
        add_action('added_site_option', array(__CLASS__, 'maybe_bump_for_option'), 30, 2);
        add_action('updated_site_option', array(__CLASS__, 'maybe_bump_for_option'), 30, 4);
        add_action('deleted_site_option', array(__CLASS__, 'maybe_bump_for_option'), 30, 2);
    }

    public function serve_cached($result, $server, $request) {
        if (!$this->is_cacheable($request)) {
            return $result;
        }
        $key = $this->cache_key($request);
        $this->cacheable_requests[$this->request_signature($request)] = $key;
        $cached = get_transient($key);
        if (false === $cached) {
            return $result;
        }
        if (class_exists('UCP_Cache_Insights')) {
            UCP_Cache_Insights::record_request('REST-HIT', '', array('route' => $request->get_route()));
        }
        if (is_array($cached) && array_key_exists('data', $cached)) {
            $response = new WP_REST_Response($cached['data'], isset($cached['status']) ? absint($cached['status']) : 200);
            if (!empty($cached['headers']) && is_array($cached['headers'])) {
                foreach ($cached['headers'] as $name => $value) {
                    $response->header($name, $value);
                }
            }
            if (!empty($cached['links']) && is_array($cached['links'])) {
                foreach ($cached['links'] as $rel => $links) {
                    if (!is_string($rel) || !is_array($links)) {
                        continue;
                    }
                    foreach ($links as $link) {
                        if (!is_array($link) || !isset($link['href']) || !is_scalar($link['href'])) {
                            continue;
                        }
                        $attributes = isset($link['attributes']) && is_array($link['attributes']) ? $link['attributes'] : array();
                        $response->add_link($rel, (string) $link['href'], $attributes);
                    }
                }
            }
            $response->header('X-UltraCache-REST', 'HIT');
            return $response;
        }
        $response = rest_ensure_response($cached);
        if ($response instanceof WP_REST_Response) {
            $response->header('X-UltraCache-REST', 'HIT');
        }
        return $response;
    }

    public function store_response($response, $server, $request) {
        $signature = $this->request_signature($request);
        if (empty($this->cacheable_requests[$signature]) || !$this->is_cacheable($request)) {
            return $response;
        }
        $cache_response = rest_ensure_response($response);
        if (!($cache_response instanceof WP_REST_Response)) {
            return $response;
        }
        $status = $cache_response->get_status();
        if (200 !== $status) {
            return $response;
        }
        $headers = $this->sanitize_cacheable_headers($cache_response->get_headers());
        if (false === $headers) {
            return $response;
        }
        $policy = isset($this->policy_decisions[$signature]) ? $this->policy_decisions[$signature] : UCP_Cache_Policy::decision_for_rest_route($request->get_route());
        $rest_ttl = !empty($policy['matched']) ? absint($policy['ttl']) : absint(UCP_Options::get('rest_cache_ttl', 300));
        set_transient($this->cacheable_requests[$signature], array(
            'data' => $cache_response->get_data(),
            'status' => $status,
            'headers' => $headers,
            'links' => $cache_response->get_links(),
        ), max(MINUTE_IN_SECONDS, $rest_ttl));
        if (class_exists('UCP_Cache_Insights')) {
            UCP_Cache_Insights::record_request('REST-MISS', '', array('route' => $request->get_route(), 'ttl' => $rest_ttl));
        }
        $cache_response->header('X-UltraCache-REST', 'MISS');
        return $cache_response;
    }

    protected function is_cacheable($request) {
        if (empty(UCP_Options::get('enable_rest_cache'))) {
            return false;
        }
        if (!($request instanceof WP_REST_Request) || 'GET' !== $request->get_method()) {
            return false;
        }
        if ($request->get_header('x-http-method-override')) {
            return false;
        }
        if (is_user_logged_in()) {
            return false;
        }
        foreach (array('authorization', 'x-wp-nonce', 'cart-token', 'nonce') as $header) {
            if ($request->get_header($header)) {
                return false;
            }
        }
        if (!$this->cookie_header_is_safe_for_shared_cache((string) $request->get_header('cookie'))) {
            return false;
        }
        $route = $request->get_route();
        $signature = $this->request_signature($request);
        $policy = class_exists('UCP_Cache_Policy') ? UCP_Cache_Policy::decision_for_rest_route($route) : array('matched' => false, 'action' => 'cache');
        $this->policy_decisions[$signature] = $policy;
        if (!empty($policy['matched']) && 'bypass' === $policy['action']) {
            return false;
        }
        $allowed = !empty($policy['matched']) && 'cache' === $policy['action'];
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('rest_cache_inclusions', '')) as $fragment) {
            if ('' !== $fragment && 0 === strpos($route, $fragment)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return false;
        }
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('rest_cache_exclusions', '')) as $fragment) {
            if ('' !== $fragment && false !== strpos($route, $fragment)) {
                return false;
            }
        }
        $params = $request->get_query_params();
        if (isset($params['_method']) || $this->has_sensitive_query_params($params)) {
            return false;
        }
        foreach (array('context', '_locale') as $sensitive_param) {
            if (!isset($params[$sensitive_param])) {
                continue;
            }
            $param_value = $params[$sensitive_param];
            if (!is_scalar($param_value) || !in_array((string) $param_value, array('', 'view'), true)) {
                return false;
            }
        }
        return true;
    }

    protected function cookie_header_is_safe_for_shared_cache($cookie_header) {
        if (class_exists('UCP_Cache_Policy') && method_exists('UCP_Cache_Policy', 'cookie_header_is_safe_for_shared_cache')) {
            return UCP_Cache_Policy::cookie_header_is_safe_for_shared_cache($cookie_header);
        }
        if (!is_scalar($cookie_header)) {
            return false;
        }
        $cookie_header = trim((string) $cookie_header);
        if ('' === $cookie_header) {
            return true;
        }
        $safe_prefixes = array(
            'ct_', 'apbct_', 'ct_sfw', 'cleantalk', 'cookiebot', 'cookie_notice_',
            'cmplz_', 'complianz_', 'cookieyes', 'cky-', 'borlabs', 'joinchat_',
            'wordpress_test_cookie', 'wp-settings-', 'wp-settings-time-',
            '_ga', '_gid', '_gat', '_gcl_', '_fbp', '_fbc', '_hj', '_clck', '_clsk',
            '_pk_id', '_pk_ses', '_uetsid', '_uetvid', '_pin_unauth', '_scid',
            'li_gc', 'li_mc', 'lidc', 'bcookie', 'bscookie', 'tk_ai', 'tk_qs',
            '__stripe_mid', '__stripe_sid', '__cf_bm', 'cf_clearance',
        );
        foreach (explode(';', $cookie_header) as $pair) {
            $parts = explode('=', trim($pair), 2);
            $name = isset($parts[0]) ? trim((string) $parts[0]) : '';
            if (1 !== preg_match('/^[!#$%&\'()*+\-.^_`|~0-9A-Za-z]+$/', $name)) {
                return false;
            }
            if ($this->has_sensitive_cookie_header($name)) {
                return false;
            }
            $matched = false;
            foreach ($safe_prefixes as $prefix) {
                if (0 === strpos($name, $prefix)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }
        return true;
    }

    protected function has_sensitive_cookie_header($cookie_header) {
        $cookie_header = strtolower((string) $cookie_header);
        if ('' === trim($cookie_header)) {
            return false;
        }

        $sensitive = class_exists('UCP_Cache_Policy')
            ? UCP_Cache_Policy::bypass_cookie_fragments()
            : array(
                'wordpress_logged_in_',
                'wordpress_sec_',
                'wp-postpass_',
                'woocommerce_cart_hash',
                'woocommerce_items_in_cart',
                'wp_woocommerce_session_',
                'woocommerce_recently_viewed',
                'comment_author_',
            );
        foreach ((array) $sensitive as $needle) {
            $needle = strtolower(trim((string) $needle));
            if ('' !== $needle && false !== strpos($cookie_header, $needle)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Avoid caching tokenized or preview-style anonymous REST requests.
     *
     * REST cache is opt-in per route prefix, but query parameters can still
     * carry nonces, secrets or one-off preview/auth tokens. Treat those names
     * conservatively so a tokenized response is never shared as a public HIT.
     */
    protected function has_sensitive_query_params($params) {
        if (!is_array($params) || empty($params)) {
            return false;
        }

        $exact = array(
            '_wpnonce',
            '_nonce',
            'nonce',
            'token',
            'access_token',
            'auth',
            'authorization',
            'key',
            'api_key',
            'password',
            'pass',
            'secret',
            'signature',
            'preview',
            'preview_id',
            'preview_nonce',
        );

        foreach ($params as $key => $value) {
            $clean_key = strtolower(sanitize_key((string) $key));
            if (in_array($clean_key, $exact, true) || preg_match('/(?:nonce|token|password|secret|signature|auth|key)$/', $clean_key)) {
                return true;
            }

            if (is_array($value) && $this->has_sensitive_query_params($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip unsafe or private response headers before storing a public REST HIT.
     *
     * @return array|false Sanitized headers, or false when the response opts out
     *                     via Cache-Control.
     */
    protected function sanitize_cacheable_headers($headers) {
        if (!is_array($headers)) {
            return array();
        }

        $unsafe = array(
            'authorization',
            'www-authenticate',
            'proxy-authenticate',
            'proxy-authorization',
            'x-wp-nonce',
            'connection',
            'keep-alive',
            'te',
            'trailer',
            'transfer-encoding',
            'upgrade',
            'content-encoding',
            'content-length',
        );
        $generated_cache_metadata = array(
            'x-ultracache-rest',
            'date',
            'age',
            'expires',
            'last-modified',
            'etag',
            'cache-control',
            'pragma',
        );
        $clean = array();

        foreach ($headers as $name => $value) {
            $header_name = (string) $name;
            $lower_name = strtolower($header_name);
            $header_value = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;

            // A cookie-producing REST response may be session-specific even when
            // its data looks public. Never turn it into a shared anonymous HIT.
            if ('set-cookie' === $lower_name) {
                return false;
            }

            if ('vary' === $lower_name) {
                $vary_headers = array_values(array_filter(array_map('trim', explode(',', strtolower($header_value))), 'strlen'));
                foreach ($vary_headers as $vary_header) {
                    if ('accept-encoding' !== $vary_header) {
                        return false;
                    }
                }
            }

            if ('cache-control' === $lower_name
                && UCP_Cache_Policy::cache_control_disallows_shared_storage($header_value)) {
                return false;
            }

            if (in_array($lower_name, $unsafe, true) || in_array($lower_name, $generated_cache_metadata, true)) {
                continue;
            }

            if ('' === $header_name || '' === $header_value) {
                continue;
            }

            $clean[$header_name] = sanitize_text_field($header_value);
        }

        return $clean;
    }

    /**
     * Invalidate once per request. One version bump covers every mutation completed
     * before the response is sent and avoids cascades from bulk metadata updates.
     *
     * @return void
     */
    public static function bump_version() {
        static $bumped = false;
        if ($bumped) {
            return;
        }
        $bumped = true;

        $version = function_exists('wp_generate_uuid4')
            ? wp_generate_uuid4()
            : sprintf('%.6F-%u', microtime(true), function_exists('wp_rand') ? wp_rand() : mt_rand());
        update_option('ucp_rest_cache_version', $version, false);
    }

    /**
     * Invalidate public REST responses when persistent options change, while
     * ignoring transients and UltraCache's own operational counters to avoid loops.
     *
     * @param mixed $option Option name.
     * @return void
     */
    public static function maybe_bump_for_option($option) {
        if (!is_scalar($option)) {
            return;
        }
        $option = (string) $option;
        if ('' === $option
            || 0 === strpos($option, '_transient_')
            || 0 === strpos($option, '_site_transient_')
            || 0 === strpos($option, 'ucp_')) {
            return;
        }
        if (!apply_filters('ucp_rest_cache_invalidate_on_option', true, $option)) {
            return;
        }
        self::bump_version();
    }

    protected function cache_key($request) {
        $params = $request->get_query_params();
        $params = $this->normalize_params($params);
        return 'ucp_rest_' . md5(self::CACHE_SCHEMA_VERSION . '|' . (string) get_option('ucp_rest_cache_version', '1') . '|' . $request->get_route() . '|' . UCP_Helpers::safe_json_encode_or($params, '{}'));
    }

    protected function normalize_params($params) {
        if (!is_array($params)) {
            return array();
        }
        ksort($params);
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = $this->normalize_params($value);
            }
        }
        return $params;
    }

    protected function request_signature($request) {
        if (!($request instanceof WP_REST_Request)) {
            return '';
        }
        return md5($request->get_method() . '|' . $request->get_route() . '|' . UCP_Helpers::safe_json_encode_or($this->normalize_params($request->get_query_params()), '{}'));
    }
}
