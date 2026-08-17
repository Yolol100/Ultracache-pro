<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Full-page HTML edge caching headers.
 *
 * Emits standards-based shared-cache directives (s-maxage, CDN-Cache-Control,
 * Cloudflare-CDN-Cache-Control) plus surgical Cache-Tag headers so a CDN edge
 * (Cloudflare, Fastly, BunnyCDN, Akamai) or the bundled Cloudflare Worker can
 * cache the rendered HTML document and be purged by tag.
 *
 * Safety model is fail-closed: anything that is not provably a public, anonymous,
 * GET page receives `private, no-store` so the edge can never cache personalised,
 * logged-in, cart, checkout or account responses. Existing UltraCache Cloudflare
 * URL purge handles invalidation; the Cache-Tag header additionally enables
 * tag-based purge on Enterprise zones and the bundled Worker.
 */
class UCP_Edge_HTML {
    /**
     * Register the late header hook. Runs after UCP_Edge so eligibility hints and
     * cache directives are emitted together.
     */
    public function __construct() {
        add_action('send_headers', array($this, 'send_edge_html_headers'), 11);
    }

    /**
     * Decide cacheability and emit the matching shared-cache directives.
     *
     * @return void
     */
    public function send_edge_html_headers() {
        if (is_admin() || headers_sent()) {
            return;
        }
        if (!UCP_Options::get('enable_edge_html_cache')) {
            return;
        }

        if (!$this->request_is_edge_cacheable()) {
            $this->send_bypass_headers();
            return;
        }

        $policy = UCP_Cache_Policy::export_header_policy();
        $ttl = (int) $policy['edge_ttl'];
        $stale = (int) $policy['edge_stale'];
        $cache_control = UCP_Cache_Policy::public_html_cache_control($ttl, true, $policy);
        $shared = UCP_Cache_Policy::shared_html_cache_control($ttl, true, $policy);

        header('Cache-Control: ' . $cache_control, true);
        if ('' !== $shared) {
            header('CDN-Cache-Control: ' . $shared, true);
            header('Cloudflare-CDN-Cache-Control: ' . $shared, true);
        }
        header('Vary: ' . $this->edge_vary_header(), false);

        if (UCP_Options::get('edge_html_cache_tags', 1)) {
            $tags = $this->current_request_tags();
            if (!empty($tags)) {
                $joined = implode(',', $tags);
                header('Cache-Tag: ' . $joined, true);
                header('X-UltraCache-Tags: ' . $joined, true);
            }
        }

        header('X-UltraCache-Edge-HTML: cache', true);

        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('edge', 'Edge HTML cache directives sent', array(
                's_maxage' => $ttl,
                'stale'    => $stale,
            ));
        }
    }

    /**
     * Shared-cache dimensions used by edge HTML eligibility and cache keys.
     *
     * @return string
     */
    private function edge_vary_header() {
        return UCP_Cache_Policy::html_vary_header(false);
    }

    /**
     * Fail-closed bypass directives for any non-public response.
     *
     * @return void
     */
    private function send_bypass_headers() {
        header('Cache-Control: private, no-store, no-cache, max-age=0', true);
        header('CDN-Cache-Control: no-store', true);
        header('Cloudflare-CDN-Cache-Control: no-store', true);
        header('X-UltraCache-Edge-HTML: bypass', true);
    }

    /**
     * Conservative, self-contained eligibility check. Returns true only when the
     * request is provably a public, anonymous, cacheable GET document.
     *
     * @return bool
     */
    private function request_is_edge_cacheable() {
        if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
            return false;
        }
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed() || is_preview() || is_search() || is_404()) {
            return false;
        }
        if (function_exists('is_customize_preview') && is_customize_preview()) {
            return false;
        }

        $method = UCP_Helpers::request_method();
        if ('GET' !== $method || !$this->request_accepts_html()) {
            return false;
        }
        $query_args = UCP_Helpers::query_args(100, 4, 8192);
        if (false === $query_args || '' !== UCP_Helpers::server_value('HTTP_X_HTTP_METHOD_OVERRIDE', '', 32) || array_key_exists('_method', $query_args)) {
            return false;
        }

        // Edge HTML cache never serves a per-user document, so logged-in requests always bypass.
        if (is_user_logged_in()) {
            return false;
        }

        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'HTTP_X_WP_NONCE', 'HTTP_RANGE', 'HTTP_IF_RANGE') as $key) {
            if ('' !== UCP_Helpers::server_value($key, '', 8192)) {
                return false;
            }
        }
        if ('' !== UCP_Helpers::server_value('PHP_AUTH_USER', '', 1024) || '' !== UCP_Helpers::server_value('PHP_AUTH_DIGEST', '', 8192)) {
            return false;
        }

        $request_cache_control = UCP_Helpers::server_value('HTTP_CACHE_CONTROL', '', 8192);
        if ('' !== $request_cache_control) {
            $requires_revalidation = class_exists('UCP_Cache_Policy') && method_exists('UCP_Cache_Policy', 'request_cache_control_requires_revalidation')
                ? UCP_Cache_Policy::request_cache_control_requires_revalidation($request_cache_control)
                : 1 === preg_match('/(?:^|,)\s*(?:no-cache|no-store|private|(?:s-)?max-age\s*=\s*"?0"?)(?:\s*(?:,|$))/i', $request_cache_control);
            if ($requires_revalidation) {
                return false;
            }
        }
        $pragma = strtolower(UCP_Helpers::server_value('HTTP_PRAGMA', '', 1024));
        if (false !== strpos($pragma, 'no-cache')) {
            return false;
        }

        if (function_exists('post_password_required') && is_singular() && post_password_required()) {
            return false;
        }

        // WooCommerce / EDD transactional contexts are never edge-cacheable.
        if (function_exists('is_cart') && is_cart()) {
            return false;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return false;
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return false;
        }
        if (function_exists('edd_is_checkout') && edd_is_checkout()) {
            return false;
        }

        // Query strings must follow the same disabled/allow-list policy as the page cache.
        $query_string = UCP_Helpers::server_value('QUERY_STRING', '', 8192);
        $query_inclusions = UCP_Helpers::normalize_multiline(UCP_Options::get('cache_query_string_inclusions', ''));
        if (!UCP_Helpers::query_string_is_cacheable($query_string, !empty(UCP_Options::get('cache_query_strings')), $query_inclusions)) {
            return false;
        }

        // Edge HTML is a shared cache, so any unknown or malformed request cookie must bypass.
        $raw_cookie_header = UCP_Helpers::server_value('HTTP_COOKIE', '', 16384);
        if ('' !== trim($raw_cookie_header)) {
            if (!class_exists('UCP_Cache_Policy') || !UCP_Cache_Policy::cookie_header_is_safe_for_shared_cache($raw_cookie_header)) {
                return false;
            }
        }

        // Excluded cookies + common cart/session cookies force a bypass.
        $sensitive_cookies = UCP_Helpers::normalize_multiline(UCP_Options::get('exclude_cookies', ''));
        $sensitive_cookies = array_merge($sensitive_cookies, array(
            'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session',
            'edd_items_in_cart', 'comment_author', 'wordpress_logged_in',
        ));
        $request_cookies = UCP_Helpers::cookie_map(128, 4096);
        if (false === $request_cookies) {
            return false;
        }
        foreach (array_keys($request_cookies) as $cookie_name) {
            $cookie_name = strtolower(sanitize_key((string) $cookie_name));
            foreach ($sensitive_cookies as $fragment) {
                $fragment = strtolower(trim((string) $fragment));
                if ('' !== $fragment && false !== strpos($cookie_name, sanitize_key($fragment))) {
                    return false;
                }
            }
        }

        if (!$this->response_headers_allow_shared_cache(headers_list())) {
            return false;
        }

        // Defer to the central safety layer when present (builder/preview/transactional bypass).
        if (class_exists('UCP_Quality_Suite')) {
            $reason = UCP_Quality_Suite::bypass_reason(UCP_Helpers::current_full_url());
            if ('' !== $reason) {
                return false;
            }
        }

        return (bool) apply_filters('ucp_edge_html_cacheable', true);
    }


    /**
     * Parse a request-header qvalue. Invalid q parameters fail closed.
     *
     * @param array<int,string> $parameters Media-range parameters.
     * @return float
     */
    private function request_header_quality($parameters) {
        return UCP_Cache_Policy::request_header_quality($parameters);
    }

    /**
     * Return the effective quality for one concrete media type.
     * Exact ranges override wildcards, including an exact q=0 exclusion.
     *
     * @param string $header Accept header.
     * @param string $type Media type.
     * @param string $subtype Media subtype.
     * @param array<string,string> $parameters Representation parameters.
     * @return float
     */
    private function request_media_quality($header, $type, $subtype, $parameters = array()) {
        $header = strtolower(trim((string) $header));
        $parameters = is_array($parameters) ? array_change_key_case($parameters, CASE_LOWER) : array();
        if ('' === $header) {
            return 1.0;
        }

        $best_specificity = -1;
        $best_quality = 0.0;
        foreach (explode(',', $header) as $item) {
            $segments = array_map('trim', explode(';', $item));
            $range = explode('/', (string) array_shift($segments), 2);
            if (2 !== count($range)) {
                continue;
            }
            $range_type = trim($range[0]);
            $range_subtype = trim($range[1]);
            if ('*' === $range_type && '*' === $range_subtype) {
                $specificity = 0;
            } elseif ($type === $range_type && '*' === $range_subtype) {
                $specificity = 1;
            } elseif ($type === $range_type && $subtype === $range_subtype) {
                $specificity = 2;
            } else {
                continue;
            }

            $parameter_count = 0;
            $matches = true;
            foreach ($segments as $parameter) {
                if (preg_match('/^q\s*=/i', (string) $parameter)) {
                    continue;
                }
                $pair = explode('=', (string) $parameter, 2);
                if (2 !== count($pair)) {
                    $matches = false;
                    break;
                }
                $name = strtolower(trim($pair[0]));
                $value = strtolower(trim($pair[1], " \t\n\r\0\x0B\""));
                if ('' === $name || !array_key_exists($name, $parameters)
                    || strtolower(trim((string) $parameters[$name], " \t\n\r\0\x0B\"")) !== $value) {
                    $matches = false;
                    break;
                }
                ++$parameter_count;
            }
            if (!$matches) {
                continue;
            }

            $specificity = ($specificity * 1000) + $parameter_count;
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

    /**
     * The edge key represents an HTML document and must not satisfy JSON-only clients.
     *
     * @return bool
     */
    private function request_accepts_html() {
        $accept = UCP_Helpers::server_value('HTTP_ACCEPT', '', 8192);
        $charset = function_exists('get_bloginfo') ? strtolower((string) get_bloginfo('charset')) : 'utf-8';
        return $this->request_media_quality($accept, 'text', 'html', array('charset' => $charset)) > 0.0;
    }

    /**
     * Reject response headers that make a representation private, stateful or
     * dependent on a request header the edge key does not vary by.
     *
     * @param array<int,string> $headers Current response headers.
     * @return bool
     */
    private function response_headers_allow_shared_cache($headers) {
        if (!is_array($headers)) {
            return false;
        }
        foreach ($headers as $header_line) {
            $raw_header = trim((string) $header_line);
            $lower_header = strtolower($raw_header);
            if (0 === strpos($lower_header, 'set-cookie:')
                || 0 === strpos($lower_header, 'content-range:')
                || 0 === strpos($lower_header, 'www-authenticate:')) {
                return false;
            }
            if (0 === strpos($lower_header, 'pragma:') && false !== strpos($lower_header, 'no-cache')) {
                return false;
            }
            if (0 === strpos($lower_header, 'cache-control:')) {
                $value = trim(substr($raw_header, strlen('cache-control:')));
                $blocked = class_exists('UCP_Cache_Policy') && method_exists('UCP_Cache_Policy', 'cache_control_disallows_shared_storage')
                    ? UCP_Cache_Policy::cache_control_disallows_shared_storage($value)
                    : 1 === preg_match('/(?:^|,)\s*(?:private|no-store|no-cache)(?:\s*(?:,|$))/i', $value);
                if ($blocked) {
                    return false;
                }
            }
            if (0 === strpos($lower_header, 'vary:')) {
                $value = trim(substr($raw_header, strlen('vary:')));
                foreach (explode(',', strtolower($value)) as $vary_header) {
                    $vary_header = trim($vary_header);
                    if ('' !== $vary_header && 'accept-encoding' !== $vary_header) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Cache tags for the current request, namespaced to the site so multiple
     * zones/blogs never collide, length/count-capped for header safety.
     *
     * @return array<int,string>
     */
    private function current_request_tags() {
        $prefix = $this->tag_namespace();
        $tags = array($prefix); // site-wide tag enables a full edge flush.

        if (is_front_page() || is_home()) {
            $tags[] = $prefix . '-home';
        }

        if (is_singular()) {
            $object_id = get_queried_object_id();
            if ($object_id && class_exists('UCP_Cache_Tags') && method_exists('UCP_Cache_Tags', 'tags_for_post')) {
                foreach ((array) UCP_Cache_Tags::tags_for_post($object_id) as $tag) {
                    $tags[] = $prefix . '-' . sanitize_key((string) $tag);
                }
            } elseif ($object_id) {
                $tags[] = $prefix . '-post-' . absint($object_id);
            }
        }

        $tags = array_values(array_unique(array_filter(array_map(array($this, 'sanitize_tag'), $tags))));
        $tags = array_slice($tags, 0, 16);

        return (array) apply_filters('ucp_edge_html_cache_tags', $tags);
    }

    /**
     * Short, stable per-site tag namespace.
     *
     * @return string
     */
    private function tag_namespace() {
        $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        return 'ucp' . substr(md5((string) $host), 0, 8);
    }

    /**
     * Constrain a tag to a header-safe token.
     *
     * @param string $tag Raw tag.
     * @return string
     */
    private function sanitize_tag($tag) {
        $tag = UCP_Helpers::sanitize_preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $tag);
        return substr((string) $tag, 0, 60);
    }

    /**
     * Edge shared-cache TTL in seconds (clamped).
     *
     * @return int
     */
    private function edge_ttl() {
        $policy = UCP_Cache_Policy::export_header_policy();
        return (int) $policy['edge_ttl'];
    }

    /**
     * stale-while-revalidate / stale-if-error window in seconds (clamped, 0 disables).
     *
     * @return int
     */
    private function edge_stale() {
        $policy = UCP_Cache_Policy::export_header_policy();
        return (int) $policy['edge_stale'];
    }
}
