<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_REST_Cache {
    protected $cacheable_requests = array();

    public function __construct() {
        add_filter('rest_pre_dispatch', array($this, 'serve_cached'), 10, 3);
        add_filter('rest_post_dispatch', array($this, 'store_response'), 10, 3);

        foreach (array('ucp_cache_purged_all', 'save_post', 'deleted_post', 'trashed_post', 'created_term', 'edited_term', 'delete_term', 'comment_post', 'wp_set_comment_status') as $hook) {
            add_action($hook, array(__CLASS__, 'bump_version'), 30);
        }
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
        if (is_array($cached) && array_key_exists('data', $cached)) {
            $response = new WP_REST_Response($cached['data'], isset($cached['status']) ? absint($cached['status']) : 200);
            if (!empty($cached['headers']) && is_array($cached['headers'])) {
                foreach ($cached['headers'] as $name => $value) {
                    $response->header($name, $value);
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
        if ($status < 200 || $status >= 300) {
            return $response;
        }
        $headers = $cache_response->get_headers();
        foreach (array('Set-Cookie', 'set-cookie', 'Authorization', 'authorization') as $unsafe_header) {
            unset($headers[$unsafe_header]);
        }
        set_transient($this->cacheable_requests[$signature], array(
            'data' => $cache_response->get_data(),
            'status' => $status,
            'headers' => $headers,
        ), absint(UCP_Options::get('rest_cache_ttl', 300)));
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
        if (is_user_logged_in()) {
            return false;
        }
        foreach (array('authorization', 'x-wp-nonce') as $header) {
            if ($request->get_header($header)) {
                return false;
            }
        }
        if ($this->has_sensitive_cookie_header((string) $request->get_header('cookie'))) {
            return false;
        }
        $route = $request->get_route();
        $allowed = false;
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
        foreach (array('context', '_locale') as $sensitive_param) {
            if (isset($params[$sensitive_param]) && !in_array((string) $params[$sensitive_param], array('', 'view'), true)) {
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

        $sensitive = array(
            'wordpress_logged_in_',
            'wordpress_sec_',
            'wp-postpass_',
            'woocommerce_cart_hash',
            'woocommerce_items_in_cart',
            'wp_woocommerce_session_',
            'woocommerce_recently_viewed',
            'comment_author_',
        );
        foreach ($sensitive as $needle) {
            if (false !== strpos($cookie_header, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function bump_version() {
        update_option('ucp_rest_cache_version', time(), false);
    }

    protected function cache_key($request) {
        $params = $request->get_query_params();
        $params = $this->normalize_params($params);
        return 'ucp_rest_' . md5((string) get_option('ucp_rest_cache_version', '1') . '|' . $request->get_route() . '|' . wp_json_encode($params));
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
        return md5($request->get_method() . '|' . $request->get_route() . '|' . wp_json_encode($this->normalize_params($request->get_query_params())));
    }
}
