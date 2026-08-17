<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Server-agnostic ESI-style fragment hole-punching.
 *
 * True LiteSpeed ESI requires a LiteSpeed/QUIC.cloud server. This emulates the same idea on any
 * host (Apache/nginx/disk cache): the public page is cached with lightweight placeholders, and a
 * single batched front-end request hydrates every "hole" with the visitor's own personalised
 * content (cart count, account greeting, etc.). The cached HTML stays fully shared; only the holes
 * are per-visitor, fetched uncached with the visitor's session cookies.
 *
 * Register fragments with UCP_ESI::register('id', $callback) or the `ucp_esi_fragments` filter,
 * and drop a hole into a template with UCP_ESI::placeholder('id', $fallback) or [ucp_esi id="..."].
 * Default OFF.
 */
class UCP_ESI {

    /** @var array<string,callable> */
    protected static $fragments = array();

    /** @var array<string,array<string,mixed>> */
    protected static $fragment_meta = array();

    public function __construct() {
        $this->register_default_fragments();
        add_shortcode('ucp_esi', array($this, 'shortcode'));
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_loader'));
    }

    /**
     * Register a hole-punch fragment.
     *
     * @param string   $id       Stable fragment id (sanitised to [a-z0-9_-]).
     * @param callable $callback Returns the live HTML for this visitor.
     * @param array    $args     Optional metadata. Supported: visibility public|guest_session|auth_required.
     * @return void
     */
    public static function register($id, $callback, $args = array()) {
        $id = self::clean_id($id);
        if ('' === $id || !is_callable($callback)) {
            return;
        }

        $meta = is_array($args) ? $args : array();
        $visibility = !empty($meta['visibility']) ? sanitize_key((string) $meta['visibility']) : 'auth_required';
        if (!in_array($visibility, array('public', 'guest_session', 'auth_required'), true)) {
            $visibility = 'auth_required';
        }

        self::$fragments[$id] = $callback;
        self::$fragment_meta[$id] = array(
            'visibility' => $visibility,
        );
        if (class_exists('UCP_Fragment_Cache')) {
            UCP_Fragment_Cache::register($id, $callback, array('mode' => 'client', 'visibility' => $visibility, 'ttl' => MINUTE_IN_SECONDS));
        }
    }

    /**
     * Built-in fragments. Authors can add/override via `ucp_esi_fragments`.
     *
     * @return void
     */
    public function register_default_fragments() {
        if (!UCP_Options::get('enable_esi')) {
            return;
        }

        // WooCommerce mini-cart count — the classic reason cart-bearing shoppers miss the cache.
        if (function_exists('WC')) {
            self::register('wc_cart_count', function () {
                $count = (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;
                return '<span class="ucp-cart-count">' . esc_html((string) $count) . '</span>';
            }, array('visibility' => 'guest_session'));
        }

        // Logged-in greeting / account state.
        self::register('account_state', function () {
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                /* translators: %s: dynamic value. */
                return '<span class="ucp-account ucp-account--in">' . esc_html(sprintf(__('Hallo, %s', 'ultracache-pro'), $user->display_name)) . '</span>';
            }
            return '<span class="ucp-account ucp-account--out"></span>';
        }, array('visibility' => 'public'));

        /**
         * Let integrations register their own fragments.
         *
         * @param UCP_ESI $instance
         */
        do_action('ucp_esi_register', $this);
    }

    /**
     * Resolve the full fragment map (registered + filtered).
     *
     * @return array<string,callable>
     */
    protected static function fragments() {
        $map = self::$fragments;
        if (class_exists('UCP_Fragment_Cache')) {
            $map = array_merge($map, UCP_Fragment_Cache::registered_callbacks('client'));
        }
        /**
         * Filter the active ESI fragment map.
         *
         * @param array<string,callable> $map
         */
        $map = apply_filters('ucp_esi_fragments', $map);
        $clean = array();
        foreach ((array) $map as $id => $cb) {
            $id = self::clean_id($id);
            if ('' !== $id && is_callable($cb)) {
                $clean[$id] = $cb;
            }
        }
        return $clean;
    }

    /**
     * Resolve sanitized fragment metadata.
     *
     * @return array<string,array<string,mixed>>
     */
    protected static function fragment_meta() {
        $meta = self::$fragment_meta;
        if (class_exists('UCP_Fragment_Cache')) {
            $meta = array_merge($meta, UCP_Fragment_Cache::registered_meta('client'));
        }
        $meta = apply_filters('ucp_esi_fragment_meta', $meta);

        $clean = array();
        foreach ((array) $meta as $id => $args) {
            $id = self::clean_id($id);
            if ('' === $id || !is_array($args)) {
                continue;
            }
            $visibility = !empty($args['visibility']) ? sanitize_key((string) $args['visibility']) : 'auth_required';
            if (!in_array($visibility, array('public', 'guest_session', 'auth_required'), true)) {
                $visibility = 'auth_required';
            }
            $clean[$id] = array('visibility' => $visibility);
        }
        return $clean;
    }

    /**
     * Output a placeholder hole. The fallback is shown until hydration completes (and if JS is off).
     *
     * @param string $id
     * @param string $fallback_html Pre-escaped, trusted fallback markup.
     * @return string
     */
    public static function placeholder($id, $fallback_html = '') {
        $id = self::clean_id($id);
        if ('' === $id || !UCP_Options::get('enable_esi')) {
            return $fallback_html;
        }
        return '<span class="ucp-esi" data-ucp-esi="' . esc_attr($id) . '">' . $fallback_html . '</span>';
    }

    /**
     * [ucp_esi id="wc_cart_count"]fallback[/ucp_esi]
     *
     * @param array  $atts
     * @param string $content
     * @return string
     */
    public function shortcode($atts, $content = '') {
        $atts = shortcode_atts(array('id' => ''), $atts, 'ucp_esi');
        return self::placeholder($atts['id'], (string) $content);
    }

    public function register_routes() {
        register_rest_route('ultracache-pro/v1', '/esi', array(
            'methods'             => 'POST',
            'permission_callback' => array($this, 'permission_check'), // public for guests; logged-in fragments require a REST nonce
            'callback'            => array($this, 'resolve'),
            'args'                => array(
                'ids' => array(
                    'required'          => true,
                    'type'              => 'array',
                    'sanitize_callback' => array($this, 'sanitize_ids_param'),
                    'validate_callback' => array($this, 'validate_ids_param'),
                    'items'             => array(
                        'type'      => 'string',
                        'maxLength' => 80,
                        'pattern'   => '^[A-Za-z0-9_-]+$',
                    ),
                ),
            ),
        ));
    }

    /**
     * Public ESI hydration stays available for guests, but logged-in/personalised
     * requests must prove same-site intent with the normal WordPress REST nonce.
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function permission_check($request) {
        if (!is_user_logged_in()) {
            return true;
        }

        $nonce = $request instanceof WP_REST_Request ? (string) $request->get_header('X-WP-Nonce') : '';
        if ('' === $nonce && $request instanceof WP_REST_Request) {
            $nonce_value = $request->get_param('_wpnonce');
            $nonce = is_scalar($nonce_value) ? (string) $nonce_value : '';
        }
        $nonce = sanitize_text_field(wp_unslash($nonce));

        if ('' !== $nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }

        return new WP_Error(
            'ucp_esi_nonce_required',
            __('ESI-fragmenten voor ingelogde bezoekers vereisen een geldige sessiecontrole.', 'ultracache-pro'),
            array('status' => 403)
        );
    }

    /**
     * Resolve visibility metadata for a registered fragment.
     *
     * @param string $id Fragment id.
     * @return array<string,mixed>
     */
    protected function fragment_meta_for($id) {
        $meta = self::fragment_meta();
        return isset($meta[$id]) ? $meta[$id] : array('visibility' => 'auth_required');
    }

    /**
     * Detect a real anonymous shopper/session context without creating one.
     * Extensions can add their own session signal through the filter.
     *
     * @return bool
     */
    protected function has_guest_session_cookie() {
        $has_session = false;
        if (function_exists('WC')) {
            try {
                $woocommerce = WC();
                $session = is_object($woocommerce) && isset($woocommerce->session) ? $woocommerce->session : null;
                if (is_object($session) && method_exists($session, 'get_session_cookie')) {
                    $cookie = $session->get_session_cookie();
                    $has_session = is_array($cookie)
                        && !empty($cookie[0])
                        && isset($cookie[1], $cookie[2], $cookie[3])
                        && is_numeric($cookie[1])
                        && is_numeric($cookie[2])
                        && is_scalar($cookie[3])
                        && '' !== (string) $cookie[3];
                }
            } catch (\Throwable $e) {
                $has_session = false;
            }
        }

        return (bool) apply_filters('ucp_esi_has_guest_session', $has_session, (array) $_COOKIE);
    }

    /**
     * Resolve a batch of holes for the current visitor. Never cached.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function resolve($request) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();

        $rate_limit_response = $this->enforce_rate_limits();
        if ($rate_limit_response instanceof WP_REST_Response) {
            return $rate_limit_response;
        }

        $requested = (array) $request->get_param('ids');
        $fragments = self::fragments();
        $out = array();
        $limit = 25;
        foreach ($requested as $raw) {
            if (count($out) >= $limit) {
                break;
            }
            $id = self::clean_id($raw);
            if ('' === $id || empty($fragments[$id])) {
                continue;
            }

            $meta = $this->fragment_meta_for($id);
            if ('auth_required' === $meta['visibility'] && !is_user_logged_in()) {
                continue;
            }
            if ('guest_session' === $meta['visibility'] && !is_user_logged_in() && !$this->has_guest_session_cookie()) {
                continue;
            }

            $html = '';
            try {
                $central = class_exists('UCP_Fragment_Cache') ? UCP_Fragment_Cache::registered_callbacks('client') : array();
                $html = isset($central[$id]) ? UCP_Fragment_Cache::render($id, array('transport' => 'client')) : (string) call_user_func($fragments[$id]);
            } catch (\Throwable $e) {
                if (class_exists('UCP_Logger')) {
                    UCP_Logger::log('warning', 'esi', 'fragment_error', __('ESI-fragment gaf een fout.', 'ultracache-pro'), array('id' => $id, 'exception' => get_class($e), 'code' => (string) $e->getCode()));
                }
                continue;
            }
            // Allow safe inline markup but strip scripts.
            $out[$id] = wp_kses_post($html);
        }

        $response = rest_ensure_response(array('fragments' => $out));
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');
        $response->header('Vary', 'Cookie, X-WP-Nonce');
        $response->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->header('X-UCP-ESI', 'no-store');
        return $response;
    }

    /**
     * Enqueue the batched hydration loader once, only when ESI is active.
     *
     * @return void
     */
    public function enqueue_loader() {
        if (!UCP_Options::get('enable_esi') || is_admin()) {
            return;
        }

        $asset = UCP_Helpers::frontend_asset_with_min_fallback('assets/frontend/js/ucp-esi-loader');
        if ('' === $asset['url']) {
            return;
        }

        wp_register_script('ucp-esi-loader', $asset['url'], array(), $asset['version'], true);
        wp_add_inline_script(
            'ucp-esi-loader',
            'window.UCP=window.UCP||{};window.UCP.esiLoader=' . UCP_Helpers::safe_inline_json(array(
                'endpoint' => esc_url_raw(rest_url('ultracache-pro/v1/esi')),
                'nonce'    => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            ), '{}') . ';window.ucpEsiLoader=window.UCP.esiLoader;',
            'before'
        );
        wp_enqueue_script('ucp-esi-loader');
    }

    public function print_loader() {
        $this->enqueue_loader();
    }

    public function sanitize_ids_param($ids) {
        $clean = array();
        foreach ((array) $ids as $id) {
            $id = self::clean_id($id);
            if ('' !== $id) {
                $clean[] = $id;
            }
            if (count($clean) >= 25) {
                break;
            }
        }
        return array_values(array_unique($clean));
    }

    public function validate_ids_param($ids) {
        if (!is_array($ids) || empty($ids) || count($ids) > 25) {
            return false;
        }
        foreach ($ids as $id) {
            if (!is_string($id)
                || '' === $id
                || strlen($id) > 80
                || 1 !== preg_match('/^[a-z0-9_-]+$/iD', $id)
                || !hash_equals($id, self::clean_id($id))) {
                return false;
            }
        }
        return true;
    }

    protected function enforce_rate_limits() {
        $ip = UCP_Helpers::server_value('REMOTE_ADDR', 'unknown', 64);
        if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = 'unknown';
        }
        $minute = (int) floor(time() / MINUTE_IN_SECONDS);
        $ip_key = 'ucp_esi_ip_' . md5($ip . '|' . $minute);
        $site_key = 'ucp_esi_site_' . $minute;

        $ip_limit = min(120, max(10, (int) apply_filters('ucp_esi_ip_rate_limit_per_minute', 60)));
        $site_limit = min(1200, max(100, (int) apply_filters('ucp_esi_site_rate_limit_per_minute', 600)));

        if (class_exists('UCP_CWV_Rate_Limiter') && method_exists('UCP_CWV_Rate_Limiter', 'bump_many_status')) {
            $result = UCP_CWV_Rate_Limiter::bump_many_status(array(
                array($ip_key, $ip_limit, 2 * MINUTE_IN_SECONDS),
                array($site_key, $site_limit, 2 * MINUTE_IN_SECONDS),
            ));
            if (UCP_CWV_Rate_Limiter::ALLOWED === $result['status']) {
                $ip_allowed = true;
                $site_allowed = true;
            } elseif (UCP_CWV_Rate_Limiter::LIMITED === $result['status']) {
                $ip_allowed = 0 !== (int) $result['index'];
                $site_allowed = 1 !== (int) $result['index'];
            } else {
                $response = new WP_REST_Response(array('ok' => false, 'code' => 'rate_limiter_unavailable'), 503);
                $response->header('Retry-After', '1');
                $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
                $response->header('X-Robots-Tag', 'noindex, nofollow');
                return $response;
            }
        } else {
            $ip_count = (int) get_transient($ip_key);
            $site_count = (int) get_transient($site_key);
            $ip_allowed = $ip_count < $ip_limit;
            $site_allowed = $site_count < $site_limit;
            if ($ip_allowed && $site_allowed) {
                $ip_written = set_transient($ip_key, $ip_count + 1, 2 * MINUTE_IN_SECONDS);
                $site_written = $ip_written && set_transient($site_key, $site_count + 1, 2 * MINUTE_IN_SECONDS);
                if (!$site_written) {
                    if ($ip_written) {
                        if ($ip_count > 0) {
                            set_transient($ip_key, $ip_count, 2 * MINUTE_IN_SECONDS);
                        } else {
                            delete_transient($ip_key);
                        }
                    }
                    $response = new WP_REST_Response(array('ok' => false, 'code' => 'rate_limiter_unavailable'), 503);
                    $response->header('Retry-After', '1');
                    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
                    $response->header('X-Robots-Tag', 'noindex, nofollow');
                    return $response;
                }
            }
        }

        if (!$ip_allowed || !$site_allowed) {
            $response = new WP_REST_Response(array('ok' => false, 'code' => 'rate_limited'), 429);
            $response->header('Retry-After', '60');
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->header('X-Robots-Tag', 'noindex, nofollow');
            return $response;
        }

        return null;
    }

    protected static function clean_id($id) {
        if (!is_scalar($id)) {
            return '';
        }
        $clean = UCP_Helpers::sanitize_preg_replace('/[^a-z0-9_-]/i', '', (string) $id);
        return is_string($clean) ? substr($clean, 0, 80) : '';
    }
}
