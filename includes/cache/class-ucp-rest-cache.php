<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_REST_Cache {
    protected static $pending = array();
    const CACHE_GROUP = 'ucp_rest_cache';
    const TAG_INDEX_OPTION = 'ucp_rest_cache_tags';

    public function __construct() {
        add_filter('rest_pre_dispatch', array($this, 'maybe_serve_cached_response'), 5, 3);
        add_filter('rest_post_dispatch', array($this, 'maybe_store_response'), 20, 3);
        add_action('save_post', array(__CLASS__, 'purge_all'), 30);
        add_action('deleted_post', array(__CLASS__, 'purge_all'), 30);
        add_action('edited_terms', array(__CLASS__, 'purge_all'), 30);
        add_action('woocommerce_update_product', array(__CLASS__, 'purge_all'), 30);
    }

    public static function enabled() {
        return (bool) UCP_Options::get('enable_rest_cache', 0);
    }

    public static function rules() {
        $rules = UCP_Options::get('rest_cache_rules', array());
        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            $rules = is_array($decoded) ? $decoded : array();
        }
        return is_array($rules) ? $rules : array();
    }

    public function maybe_serve_cached_response($result, $server, $request) {
        if (null !== $result) {
            return $result;
        }
        $decision = self::cacheability($request);
        if (!$decision['cacheable']) {
            self::send_header('BYPASS', $decision['reason']);
            return $result;
        }
        $key = self::cache_key($request, $decision['rule']);
        $cached = get_transient($key);
        if (is_array($cached) && array_key_exists('data', $cached)) {
            self::send_header('HIT', 'allowlisted');
            if (class_exists('UCP_Audit_Log')) {
                UCP_Audit_Log::record('rest_cache_hit', 'success', array('route' => $request->get_route()));
            }
            return rest_ensure_response($cached['data']);
        }
        self::$pending[spl_object_hash($request)] = array('key' => $key, 'rule' => $decision['rule']);
        self::send_header('MISS', 'allowlisted');
        return $result;
    }

    public function maybe_store_response($response, $server, $request) {
        $pending_key = spl_object_hash($request);
        $pending = isset(self::$pending[$pending_key]) ? self::$pending[$pending_key] : array();
        unset(self::$pending[$pending_key]);
        $key = isset($pending['key']) ? $pending['key'] : '';
        $rule = isset($pending['rule']) ? $pending['rule'] : array();
        if (!$key || !is_array($rule) || is_wp_error($response)) {
            return $response;
        }
        $status = method_exists($response, 'get_status') ? (int) $response->get_status() : 200;
        if ($status < 200 || $status >= 300) {
            return $response;
        }
        $data = method_exists($response, 'get_data') ? $response->get_data() : null;
        if (null === $data) {
            return $response;
        }
        $ttl = isset($rule['ttl']) ? max(60, min(DAY_IN_SECONDS, absint($rule['ttl']))) : 300;
        set_transient($key, array('data' => $data, 'created' => time()), $ttl);
        self::register_tags($key, isset($rule['tags']) ? $rule['tags'] : array());
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('rest_cache_store', 'success', array('route' => $request->get_route(), 'ttl' => $ttl));
        }
        return $response;
    }

    public static function cacheability($request) {
        if (!self::enabled()) {
            return array('cacheable' => false, 'reason' => 'disabled');
        }
        if (!($request instanceof WP_REST_Request)) {
            return array('cacheable' => false, 'reason' => 'invalid_request');
        }
        if ('GET' !== strtoupper($request->get_method())) {
            return array('cacheable' => false, 'reason' => 'non_get');
        }
        if (is_user_logged_in() || self::has_auth_headers() || self::has_nonce($request) || self::has_sensitive_cookies()) {
            return array('cacheable' => false, 'reason' => 'private_context');
        }
        $route = $request->get_route();
        if (self::is_woocommerce_sensitive_route($route)) {
            return array('cacheable' => false, 'reason' => 'woocommerce_sensitive');
        }
        foreach (self::rules() as $rule) {
            $rule = self::normalize_rule($rule);
            if (empty($rule['active'])) {
                continue;
            }
            if (self::rule_matches($rule, $route)) {
                return array('cacheable' => true, 'reason' => 'allowlisted', 'rule' => $rule);
            }
        }
        return array('cacheable' => false, 'reason' => 'not_allowlisted');
    }

    protected static function normalize_rule($rule) {
        $rule = is_array($rule) ? $rule : array();
        $tags = isset($rule['tags']) ? $rule['tags'] : array();
        if (is_string($tags)) {
            $tags = preg_split('/[\r\n,]+/', $tags);
        }
        $tags = array_values(array_filter(array_map('sanitize_key', (array) $tags)));
        return array(
            'active' => !empty($rule['active']) ? 1 : 0,
            'namespace' => sanitize_text_field(isset($rule['namespace']) ? $rule['namespace'] : ''),
            'route' => sanitize_text_field(isset($rule['route']) ? $rule['route'] : ''),
            'ttl' => max(60, min(DAY_IN_SECONDS, absint(isset($rule['ttl']) ? $rule['ttl'] : 300))),
            'tags' => $tags,
        );
    }

    protected static function rule_matches($rule, $route) {
        $route = '/' . ltrim((string) $route, '/');
        $namespace = trim((string) $rule['namespace'], '/');
        $pattern = trim((string) $rule['route']);
        if ('' !== $namespace && 0 !== strpos($route, '/' . $namespace . '/')) {
            return false;
        }
        if ('' === $pattern) {
            return true;
        }
        $pattern = '/' . ltrim($pattern, '/');
        return 0 === strpos($route, '/' . $namespace . $pattern) || 0 === strpos($route, $pattern);
    }

    protected static function has_auth_headers() {
        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'PHP_AUTH_USER', 'PHP_AUTH_DIGEST') as $key) {
            if (!empty($_SERVER[$key])) {
                return true;
            }
        }
        return false;
    }

    protected static function has_nonce($request) {
        if ($request->get_header('x_wp_nonce') || $request->get_header('x-wp-nonce')) {
            return true;
        }
        foreach (array('_wpnonce', 'nonce') as $key) {
            if (null !== $request->get_param($key)) {
                return true;
            }
        }
        return false;
    }

    protected static function has_sensitive_cookies() {
        foreach (array_keys((array) $_COOKIE) as $cookie) {
            foreach (array('wordpress_logged_in_', 'wp_woocommerce_session_', 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'PHPSESSID', 'edd_items_in_cart') as $fragment) {
                if (false !== strpos((string) $cookie, $fragment)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected static function is_woocommerce_sensitive_route($route) {
        $route = strtolower((string) $route);
        foreach (array('/wc/', '/wc-', 'cart', 'checkout', 'payment', 'order', 'customer', 'session', 'account', 'coupon') as $fragment) {
            if (false !== strpos($route, $fragment)) {
                return true;
            }
        }
        return false;
    }

    public static function cache_key($request, $rule = array()) {
        $route = ($request instanceof WP_REST_Request) ? $request->get_route() : '';
        $params = ($request instanceof WP_REST_Request) ? $request->get_query_params() : array();
        ksort($params);
        return 'ucp_rest_' . md5($route . '|' . wp_json_encode($params) . '|' . get_current_blog_id());
    }

    protected static function register_tags($key, $tags) {
        $tags = array_values(array_filter(array_map('sanitize_key', (array) $tags)));
        if (empty($tags)) {
            return;
        }
        $index = get_option(self::TAG_INDEX_OPTION, array());
        $index = is_array($index) ? $index : array();
        foreach ($tags as $tag) {
            if (!isset($index[$tag]) || !is_array($index[$tag])) {
                $index[$tag] = array();
            }
            $index[$tag][$key] = time();
        }
        update_option(self::TAG_INDEX_OPTION, $index, false);
    }

    public static function purge_all() {
        $index = get_option(self::TAG_INDEX_OPTION, array());
        if (is_array($index)) {
            foreach ($index as $keys) {
                foreach ((array) $keys as $key => $created) {
                    delete_transient(sanitize_key($key));
                }
            }
        }
        delete_option(self::TAG_INDEX_OPTION);
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('rest_cache_purge_all', 'success');
        }
    }

    public static function purge_tag($tag) {
        $tag = sanitize_key($tag);
        $index = get_option(self::TAG_INDEX_OPTION, array());
        if (empty($index[$tag]) || !is_array($index[$tag])) {
            return 0;
        }
        $count = 0;
        foreach ($index[$tag] as $key => $created) {
            delete_transient(sanitize_key($key));
            $count++;
        }
        unset($index[$tag]);
        update_option(self::TAG_INDEX_OPTION, $index, false);
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('rest_cache_purge_tag', 'success', array('tag' => $tag, 'count' => $count));
        }
        return $count;
    }

    protected static function send_header($state, $reason = '') {
        if (!headers_sent()) {
            header('X-UltraCache-REST: ' . sanitize_key($state));
            if (UCP_Options::get('rest_cache_debug', 0) && '' !== $reason) {
                header('X-UltraCache-REST-Reason: ' . sanitize_key($reason));
            }
        }
    }

    public static function test_endpoint($url) {
        $url = esc_url_raw($url);
        if (!$url || !wp_http_validate_url($url)) {
            return array('ok' => false, 'reason' => 'invalid_url');
        }
        $response = wp_safe_remote_get($url, array('timeout' => 10, 'redirection' => 2));
        if (is_wp_error($response)) {
            return array('ok' => false, 'reason' => $response->get_error_message());
        }
        return array('ok' => true, 'code' => wp_remote_retrieve_response_code($response), 'cache_header' => wp_remote_retrieve_header($response, 'x-ultracache-rest'));
    }
}
