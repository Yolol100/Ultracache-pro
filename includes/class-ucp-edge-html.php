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

        $ttl = $this->edge_ttl();
        $stale = $this->edge_stale();

        $shared = 'max-age=' . $ttl;
        if ($stale > 0) {
            $shared .= ', stale-while-revalidate=' . $stale . ', stale-if-error=' . $stale;
        }

        // Browser always revalidates (max-age=0); shared/edge caches keep the document for the TTL.
        header('Cache-Control: public, max-age=0, s-maxage=' . $ttl);
        header('CDN-Cache-Control: ' . $shared, true);
        header('Cloudflare-CDN-Cache-Control: ' . $shared, true);
        header('Vary: Accept-Encoding', false);

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

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : 'GET';
        if ('GET' !== $method) {
            return false;
        }

        // Edge HTML cache never serves a per-user document, so logged-in requests always bypass.
        if (is_user_logged_in()) {
            return false;
        }

        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $key) {
            if (!empty($_SERVER[$key])) {
                return false;
            }
        }
        if (!empty($_SERVER['PHP_AUTH_USER']) || !empty($_SERVER['PHP_AUTH_DIGEST'])) {
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

        // Query strings: only allow when the page-cache query policy explicitly allows it.
        $query_string = isset($_SERVER['QUERY_STRING']) ? (string) wp_unslash($_SERVER['QUERY_STRING']) : '';
        if ('' !== trim($query_string) && !UCP_Options::get('cache_query_strings')) {
            return false;
        }

        // Excluded cookies + common cart/session cookies force a bypass.
        $sensitive_cookies = UCP_Helpers::normalize_multiline(UCP_Options::get('exclude_cookies', ''));
        $sensitive_cookies = array_merge($sensitive_cookies, array(
            'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session',
            'edd_items_in_cart', 'comment_author', 'wordpress_logged_in',
        ));
        foreach ((array) array_keys($_COOKIE) as $cookie_name) {
            $cookie_name = strtolower(sanitize_key((string) $cookie_name));
            foreach ($sensitive_cookies as $fragment) {
                $fragment = strtolower(trim((string) $fragment));
                if ('' !== $fragment && false !== strpos($cookie_name, sanitize_key($fragment))) {
                    return false;
                }
            }
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
        $tag = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $tag);
        return substr((string) $tag, 0, 60);
    }

    /**
     * Edge shared-cache TTL in seconds (clamped).
     *
     * @return int
     */
    private function edge_ttl() {
        $ttl = absint(UCP_Options::get('edge_html_cache_ttl', 600));
        return min(86400, max(60, $ttl));
    }

    /**
     * stale-while-revalidate / stale-if-error window in seconds (clamped, 0 disables).
     *
     * @return int
     */
    private function edge_stale() {
        $stale = absint(UCP_Options::get('edge_html_cache_stale', 86400));
        return min(604800, max(0, $stale));
    }
}
