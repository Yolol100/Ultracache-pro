<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Request state is inspected only to decide cache eligibility; no submitted data is processed or persisted.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * LiteSpeed-native page-cache bridge.
 *
 * Keeps the existing UltraCache UI/settings as the source of truth while switching only the
 * full-page cache backend from UltraCache disk HTML to LSCache response headers on LiteSpeed.
 */
class UCP_LiteSpeed_Cache {
    const BACKEND_AUTO = 'auto';
    const BACKEND_DISK = 'disk';
    const BACKEND_LITESPEED = 'litespeed';
    private const CACHE_BUFFER_PRIORITY = 1;

    /**
     * Whether the current response buffer is already owned by this bridge.
     *
     * @var bool
     */
    protected $buffering = false;

    /**
     * Prevent recursive backend resolution while options are being normalized.
     *
     * @var bool
     */
    protected static $resolving_backend = false;

    /**
     * Cached LiteSpeed runtime hints for this request.
     *
     * @var array<string,mixed>|null
     */
    protected static $runtime_hints = null;

    public function __construct() {
        add_action('send_headers', array($this, 'send_default_bypass'), 8);
        add_action('template_redirect', array($this, 'start_buffering'), self::CACHE_BUFFER_PRIORITY);
        add_action('ucp_cache_purged_all', array(__CLASS__, 'purge_all'));
        add_action('ucp_cache_purged_url', array(__CLASS__, 'purge_url'));
        add_action('ucp_cache_purged_urls', array(__CLASS__, 'purge_urls'));
    }

    /**
     * Whether UltraCache disk-page cache should stand down for the active request.
     *
     * @return bool
     */
    public static function should_bypass_disk_page_cache() {
        return self::active();
    }

    /**
     * Sanitized configured full-page cache backend.
     *
     * @return string
     */
    public static function configured_backend() {
        if (!class_exists('UCP_Options')) {
            return self::BACKEND_AUTO;
        }

        $backend = sanitize_key((string) UCP_Options::get('cache_backend', self::BACKEND_AUTO));
        if (!in_array($backend, array(self::BACKEND_AUTO, self::BACKEND_DISK, self::BACKEND_LITESPEED), true)) {
            return self::BACKEND_AUTO;
        }

        return $backend;
    }

    /**
     * Resolved page-cache backend for the current runtime.
     *
     * @return string
     */
    public static function selected_backend() {
        if (self::$resolving_backend) {
            return self::BACKEND_DISK;
        }

        self::$resolving_backend = true;
        try {
            if (!class_exists('UCP_Options') || empty(UCP_Options::get('enable_cache'))) {
                return self::BACKEND_DISK;
            }

            // LiteSpeed varies on exact cookie names, while this plugin intentionally supports
            // configured cookie-name prefixes. Keep that contract safe by using the disk backend.
            if (self::has_fragment_cookie_variation()) {
                return self::BACKEND_DISK;
            }

            $backend = self::configured_backend();
            if (self::BACKEND_DISK === $backend) {
                return self::BACKEND_DISK;
            }

            if (self::BACKEND_LITESPEED === $backend) {
                return self::litespeed_engine_available() ? self::BACKEND_LITESPEED : self::BACKEND_DISK;
            }

            return self::litespeed_engine_available() ? self::BACKEND_LITESPEED : self::BACKEND_DISK;
        } finally {
            self::$resolving_backend = false;
        }
    }

    /**
     * Whether prefix-based cookie variation requires the disk cache backend.
     *
     * @return bool
     */
    protected static function has_fragment_cookie_variation() {
        if (!class_exists('UCP_Options')) {
            return false;
        }

        $configured = (string) UCP_Options::get('cache_vary_cookies', '');
        if (class_exists('UCP_Helpers')) {
            return !empty(UCP_Helpers::normalize_multiline($configured));
        }

        return '' !== trim($configured);
    }

    /**
     * Whether LiteSpeed should be used as the page-cache backend.
     *
     * @return bool
     */
    public static function active() {
        return self::BACKEND_LITESPEED === self::selected_backend();
    }

    /**
     * Detect LiteSpeed/OpenLiteSpeed runtime hints.
     *
     * @return bool
     */
    public static function is_litespeed_server() {
        $hints = self::runtime_hints();
        return !empty($hints['is_litespeed_server']);
    }

    /**
     * Whether the LiteSpeed cache engine should be treated as available.
     *
     * Hosting-panel cache switches are outside WordPress, so UltraCache cannot toggle them from PHP.
     * In auto mode this is fully server-detected: when WordPress is running on
     * LiteSpeed/OpenLiteSpeed, UltraCache emits LSCache headers and lets the server-level
     * cache engine handle public page caching. Site owners can force a safe disk fallback
     * with UCP_DISABLE_LITESPEED_BACKEND or the filter.
     *
     * @return bool
     */
    public static function litespeed_engine_available() {
        $hints = self::runtime_hints();
        if (empty($hints['is_litespeed_server'])) {
            return false;
        }

        if (defined('UCP_DISABLE_LITESPEED_BACKEND') && UCP_DISABLE_LITESPEED_BACKEND) {
            return false;
        }

        if (defined('UCP_FORCE_LITESPEED_BACKEND') && UCP_FORCE_LITESPEED_BACKEND) {
            return true;
        }

        /**
         * Filters whether UltraCache should use LiteSpeed as the page-cache backend.
         *
         * Return false to keep UltraCache disk cache active on a LiteSpeed server, for example
         * when a hosting-panel LiteSpeed cache switch is intentionally disabled.
         *
         * @param bool                $available Default availability.
         * @param array<string,mixed> $hints Runtime detection hints.
         */
        return (bool) apply_filters('ucp_litespeed_backend_available', true, $hints);
    }

    /**
     * LiteSpeed runtime hints used by detection, diagnostics and support.
     *
     * @return array<string,mixed>
     */
    public static function runtime_hints() {
        return UCP_LiteSpeed_Runtime::runtime_hints();
    }

    /**
     * Best-effort Hostinger runtime hint. This is only diagnostic; it does not enable trust by itself.
     *
     * @return bool
     */
    protected static function hostinger_runtime_hint() {
        $hints = UCP_LiteSpeed_Runtime::runtime_hints();
        return !empty($hints['hostinger_hint']);
    }

    /**
     * Send a safe default so unknown/early-flushed responses are not stored by LiteSpeed.
     *
     * @return void
     */
    public function send_default_bypass() {
        if (!$this->can_manage_response_headers() || !$this->is_frontend_runtime()) {
            return;
        }

        $this->send_bypass_headers('pending');
    }

    /**
     * Start a late buffer so final response headers/cookies can still veto caching.
     *
     * @return void
     */
    public function start_buffering() {
        if (!$this->can_manage_response_headers() || !$this->is_frontend_runtime()) {
            return;
        }

        if (!$this->request_is_publicly_cacheable()) {
            $this->send_bypass_headers('request');
            return;
        }

        $this->buffering = true;
        ob_start(array($this, 'finalize_buffer'));
    }

    /**
     * Convert a cacheable WordPress response into LSCache instructions.
     *
     * @param string $html Response body.
     * @return string
     */
    public function finalize_buffer($html) {
        if (!is_string($html) || '' === trim($html)) {
            $this->send_bypass_headers('empty');
            return $html;
        }

        $status_code = function_exists('http_response_code') ? (int) http_response_code() : 200;
        if (200 !== $status_code) {
            $this->send_bypass_headers('status_' . $status_code);
            UCP_Diagnostics::record('cache', 'LiteSpeed backend bypassed non-cacheable response status', array('status' => $status_code));
            return $html;
        }

        if ((defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) || (function_exists('post_password_required') && post_password_required())) {
            $this->send_bypass_headers('donotcachepage');
            return $html;
        }

        $uncacheable = $this->response_uncacheable_details();
        if (!empty($uncacheable['blocked'])) {
            $this->send_bypass_headers(isset($uncacheable['reason']) ? $uncacheable['reason'] : 'response');
            UCP_Diagnostics::record('cache', 'LiteSpeed backend bypassed response after header/cookie inspection', $uncacheable);
            return $html;
        }
        if (!empty($uncacheable['safe_cookies']) || !empty($uncacheable['unknown_cookies'])) {
            $this->send_bypass_headers('response_set_cookie');
            UCP_Diagnostics::record('cache', 'LiteSpeed backend bypassed a cookie-producing response so cached headers cannot replay Set-Cookie', $uncacheable);
            return $html;
        }

        $content_type = 'text/html; charset=' . get_bloginfo('charset');
        foreach (headers_list() as $response_header) {
            if (0 === stripos((string) $response_header, 'Content-Type:')) {
                $candidate_type = trim(substr((string) $response_header, strlen('Content-Type:')));
                if (preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+(?:;\s*charset=[a-z0-9_-]+)?$/i', $candidate_type)) {
                    $content_type = $candidate_type;
                }
                break;
            }
        }
        $policy = UCP_Cache_Policy::decision_for_current_request(UCP_Options::get_all());
        $is_feed_policy = !empty($policy['matched']) && 'cache' === $policy['action'] && 'feed' === $policy['scope'];
        if (!UCP_Cache_Policy::response_content_type_is_page_cacheable($content_type, $is_feed_policy)
            || !UCP_Cache_Policy::response_body_is_complete($html, $content_type)) {
            $this->send_bypass_headers('response_representation');
            UCP_Diagnostics::record('cache', 'LiteSpeed backend bypassed a non-page or incomplete response', array('content_type' => $content_type));
            return $html;
        }

        if (!$this->can_manage_response_headers()) {
            return $html;
        }

        header_remove('X-LiteSpeed-Cache-Control');
        header('X-LiteSpeed-Cache-Control: public,max-age=' . (int) $this->ttl(), true);
        header('X-UltraCache-Backend: litespeed', true);
        header('X-UltraCache: LITESPEED-MISS', true);
        header('X-UltraCache-LiteSpeed: cache', true);
        header('X-UltraCache-LiteSpeed-Bridge: auto-detected', true);
        header('Cache-Control: public, max-age=0, must-revalidate, s-maxage=' . (int) $this->ttl(), true);
        header('Vary: ' . (UCP_Options::get('cache_mobile_separately') ? 'Accept, Accept-Encoding, User-Agent' : 'Accept, Accept-Encoding'), false);

        $tags = $this->current_tags();
        if (!empty($tags)) {
            header('X-LiteSpeed-Tag: ' . implode(',', $tags), true);
        }

        if (UCP_Options::get('cache_mobile_separately')) {
            header('X-LiteSpeed-Vary: value=ismobile', true);
        }

        UCP_Diagnostics::record('cache', 'LiteSpeed backend marked response cacheable', array(
            'ttl'  => $this->ttl(),
            'tags' => $tags,
        ));

        return $html;
    }

    /**
     * Purge the full public LiteSpeed cache.
     *
     * @return void
     */
    public static function purge_all() {
        if (!self::active()) {
            return;
        }

        self::call_lscwp_api('purge_all');
        self::send_purge_header('*');
        UCP_Diagnostics::record('cache', 'Requested LiteSpeed public cache purge all');
    }

    /**
     * Purge one public LiteSpeed URL.
     *
     * @param string $url URL to purge.
     * @return void
     */
    public static function purge_url($url) {
        if (!self::active()) {
            return;
        }

        $url = class_exists('UCP_Helpers') ? UCP_Helpers::strict_local_url($url) : '';
        if (!$url || !wp_http_validate_url($url)) {
            return;
        }

        self::call_lscwp_api('purge_url', $url);
        $path = self::url_to_purge_path($url);
        if ('' !== $path) {
            self::send_purge_header('url=' . $path);
            UCP_Diagnostics::record('cache', 'Requested LiteSpeed URL purge', array('url' => $url, 'path' => $path));
        }
    }

    /**
     * Purge a batch of public LiteSpeed URLs.
     *
     * @param array<int,string> $urls URLs to purge.
     * @return void
     */
    public static function purge_urls($urls) {
        if (!self::active()) {
            return;
        }

        $paths = array();
        foreach ((array) $urls as $url) {
            $url = class_exists('UCP_Helpers') ? UCP_Helpers::strict_local_url($url) : '';
            if (!$url || !wp_http_validate_url($url)) {
                continue;
            }
            self::call_lscwp_api('purge_url', $url);
            $path = self::url_to_purge_path($url);
            if ('' !== $path) {
                $paths[] = 'url=' . $path;
            }
        }

        $paths = array_values(array_unique($paths));
        if (empty($paths)) {
            return;
        }

        foreach (array_chunk($paths, 30) as $path_chunk) {
            self::send_purge_header(implode(',', $path_chunk));
        }
        UCP_Diagnostics::record('cache', 'Requested LiteSpeed URL batch purge', array('count' => count($paths)));
    }

    /**
     * Whether this request should receive LiteSpeed page-cache headers.
     *
     * @return bool
     */
    protected function is_frontend_runtime() {
        if (!self::active()) {
            return false;
        }

        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        return true;
    }

    /**
     * Run the existing UltraCache policy, then keep LiteSpeed public cache guest-only.
     *
     * @return bool
     */
    protected function request_is_publicly_cacheable() {
        if (!class_exists('UCP_Cache') || !class_exists('UCP_Helpers')) {
            return false;
        }

        if (is_user_logged_in()) {
            UCP_Diagnostics::record('cache', 'LiteSpeed backend bypassed logged-in request because private cache/ESI is not managed by UltraCache');
            return false;
        }

        try {
            $cache = UCP_Helpers::new_without_constructor('UCP_Cache');
            return (bool) $cache->can_cache_request();
        } catch (Exception $e) {
            UCP_Diagnostics::record('cache', 'LiteSpeed backend failed to evaluate cache policy', array('message' => $e->getMessage()));
            return false;
        } catch (Throwable $e) {
            UCP_Diagnostics::record('cache', 'LiteSpeed backend failed to evaluate cache policy', array('message' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Response-level vetoes: status/cache headers and Set-Cookie safety.
     *
     * @return bool
     */
    protected function response_vary_is_unsupported($raw_header) {
        return UCP_Cache_Policy::response_vary_is_unsupported($raw_header);
    }

    protected function response_uncacheable_details() {
        $safe_set_cookies = array();
        $unknown_set_cookies = array();

        foreach (headers_list() as $header_line) {
            $raw_header = (string) $header_line;
            $header_line = strtolower($raw_header);

            if (0 === strpos($header_line, 'cache-control:')
                && UCP_Cache_Policy::cache_control_disallows_shared_storage(trim(substr($raw_header, strlen('cache-control:'))))) {
                return array(
                    'blocked' => true,
                    'reason'  => 'response_cache_control',
                    'header'  => $raw_header,
                );
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
                if ($this->cookie_matches_fragments($cookie_name, $this->sensitive_response_cookie_fragments())) {
                    return array(
                        'blocked' => true,
                        'reason'  => 'response_set_cookie_sensitive',
                        'cookie'  => $cookie_name,
                    );
                }

                if ($this->cookie_matches_prefixes($cookie_name, $this->cache_safe_response_cookie_fragments())) {
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
            'blocked'         => false,
            'reason'          => !empty($safe_set_cookies) || !empty($unknown_set_cookies) ? 'response_set_cookie_ignored' : '',
            'safe_cookies'    => array_values(array_unique($safe_set_cookies)),
            'unknown_cookies' => array_values(array_unique($unknown_set_cookies)),
        );
    }

    /**
     * @param string $raw_header Raw Set-Cookie header.
     * @return string
     */
    protected function response_cookie_name_from_header($raw_header) {
        $cookie = trim((string) UCP_Helpers::sanitize_preg_replace('/^set-cookie\s*:/i', '', (string) $raw_header));
        $name = trim((string) strtok($cookie, '='));
        if (1 !== preg_match('/^[!#$%&\'()*+\-.^_`|~0-9A-Za-z]+$/', $name)) {
            return '';
        }
        return $name;
    }

    /**
     * @param string $cookie_name Cookie name.
     * @param array<int,string> $fragments Fragments.
     * @return bool
     */
    protected function cookie_matches_fragments($cookie_name, $fragments) {
        return UCP_Cache_Policy::cookie_name_matches_fragments($cookie_name, $fragments);
    }

    /**
     * @return bool
     */
    protected function cookie_matches_prefixes($cookie_name, $prefixes) {
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
            'ct_', 'apbct_', 'ct_sfw', 'cleantalk', 'cookiebot', 'cookie_notice_', 'cmplz_', 'complianz_', 'cookieyes', 'cky-', 'borlabs', 'joinchat_', 'wp-settings-', 'wp-settings-time-', '_ga', '_gid', '_gat', '_gcl_', '_fbp', '_fbc', '_hj', '_clck', '_clsk', '_pk_id', '_pk_ses', '_uetsid', '_uetvid', '_pin_unauth', '_scid', 'li_gc', 'lidc', 'bcookie', 'bscookie', 'tk_ai', '__stripe_mid', '__stripe_sid', '__cf_bm', 'cf_clearance'
        ));
    }

    /**
     * @return array<int,string>
     */
    protected function sensitive_response_cookie_fragments() {
        return apply_filters('ucp_sensitive_set_cookie_fragments', array(
            'wordpress_logged_in_', 'wordpress_sec_', 'wp-postpass_', 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_', 'woocommerce_recently_viewed', 'woocommerce_checkout_', 'woocommerce_pay', 'aelia_cs_selected_currency', 'aelia_customer_country', 'aelia_customer_state', 'aelia_tax_exempt', 'comment_author_', 'wp-resetpass-'
        ));
    }

    /**
     * Cache TTL in seconds for LiteSpeed public cache.
     *
     * @return int
     */
    protected function ttl() {
        $hours = absint(UCP_Options::get('cache_lifespan', 10));
        if ($hours <= 0) {
            return YEAR_IN_SECONDS;
        }

        return max(60, min(YEAR_IN_SECONDS, $hours * HOUR_IN_SECONDS));
    }

    /**
     * Current LiteSpeed cache tags, namespaced per site.
     *
     * @return array<int,string>
     */
    protected function current_tags() {
        $namespace = self::tag_namespace();
        $tags = array($namespace);

        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            foreach ((array) UCP_Cache_Tags::current_request_tags() as $tag) {
                $tags[] = $namespace . '-' . $tag;
            }
        }

        $tags[] = $namespace . '-site';

        if (function_exists('is_front_page') && is_front_page()) {
            $tags[] = $namespace . '-front';
        }

        if (function_exists('get_queried_object_id')) {
            $object_id = absint(get_queried_object_id());
            if ($object_id > 0) {
                $tags[] = $namespace . '-obj-' . $object_id;
            }
        }

        $clean = array();
        foreach ($tags as $tag) {
            $tag = self::sanitize_tag($tag);
            if ('' !== $tag) {
                $clean[] = $tag;
            }
        }

        return array_slice(array_values(array_unique($clean)), 0, 24);
    }

    /**
     * @return string
     */
    protected static function tag_namespace() {
        $host = function_exists('home_url') ? (string) wp_parse_url(home_url('/'), PHP_URL_HOST) : 'site';
        return 'ucp-' . substr(md5(strtolower($host)), 0, 8);
    }

    /**
     * @param string $tag Raw tag.
     * @return string
     */
    protected static function sanitize_tag($tag) {
        $tag = UCP_Helpers::sanitize_preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $tag);
        $tag = trim((string) $tag, '-_');
        if ('' === $tag) {
            return '';
        }

        return substr($tag, 0, 60);
    }

    /**
     * @param string $url Full local URL.
     * @return string
     */
    protected static function url_to_purge_path($url) {
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $path = isset($parts['path']) && '' !== $parts['path'] ? (string) $parts['path'] : '/';
        $query = isset($parts['query']) && '' !== $parts['query'] ? '?' . (string) $parts['query'] : '';
        $value = str_replace(array("\r", "\n"), '', $path . $query);

        return '' !== $value ? $value : '/';
    }

    /**
     * Send the LiteSpeed bypass headers.
     *
     * @param string $reason Reason code.
     * @return void
     */
    protected function send_bypass_headers($reason = '') {
        if (!$this->can_manage_response_headers()) {
            return;
        }

        $reason = sanitize_key((string) $reason);
        header('X-LiteSpeed-Cache-Control: no-cache', true);
        header('X-UltraCache-Backend: litespeed', true);
        header('X-UltraCache: LITESPEED-BYPASS', true);
        header('X-UltraCache-LiteSpeed-Bridge: auto-detected', true);
        if ('' !== $reason) {
            header('X-UltraCache-LiteSpeed: bypass-' . $reason, true);
        }
    }

    /**
     * @return bool
     */
    protected function can_manage_response_headers() {
        return self::can_send_headers() && self::active();
    }

    /**
     * @return bool
     */
    protected static function can_send_headers() {
        return 'cli' !== PHP_SAPI && !headers_sent();
    }

    /**
     * @param string $value X-LiteSpeed-Purge value.
     * @return void
     */
    protected static function send_purge_header($value) {
        if (!self::can_send_headers()) {
            return;
        }

        $value = trim(str_replace(array("\r", "\n"), '', (string) $value));
        if ('' === $value || strlen($value) > 1024 || 1 !== preg_match('/^[A-Za-z0-9_:\/,.?=&%+*~@! -]+$/D', $value)) {
            return;
        }
        header('X-LiteSpeed-Purge: ' . $value, false);
    }

    /**
     * Optional bridge to the LiteSpeed Cache plugin API when it is loaded.
     *
     * @param string $method API method.
     * @param mixed  $arg Optional argument.
     * @return bool
     */
    protected static function call_lscwp_api($method, $arg = null) {
        if (!class_exists('LiteSpeed_Cache_API') || !method_exists('LiteSpeed_Cache_API', $method)) {
            return false;
        }

        try {
            if (null === $arg) {
                call_user_func(array('LiteSpeed_Cache_API', $method));
            } else {
                call_user_func(array('LiteSpeed_Cache_API', $method), $arg);
            }
            return true;
        } catch (Exception $e) {
            UCP_Diagnostics::record('cache', 'LiteSpeed Cache API call failed', array('method' => $method, 'message' => $e->getMessage()));
        } catch (Throwable $e) {
            UCP_Diagnostics::record('cache', 'LiteSpeed Cache API call failed', array('method' => $method, 'message' => $e->getMessage()));
        }

        return false;
    }
}
