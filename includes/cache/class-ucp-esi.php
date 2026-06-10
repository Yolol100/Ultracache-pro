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
        add_action('init', array($this, 'register_default_fragments'), 5);
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
        $visibility = !empty($meta['visibility']) ? sanitize_key((string) $meta['visibility']) : 'public';
        if (!in_array($visibility, array('public', 'guest_session', 'auth_required'), true)) {
            $visibility = 'public';
        }

        self::$fragments[$id] = $callback;
        self::$fragment_meta[$id] = array(
            'visibility' => $visibility,
        );
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
        $meta = apply_filters('ucp_esi_fragment_meta', $meta);

        $clean = array();
        foreach ((array) $meta as $id => $args) {
            $id = self::clean_id($id);
            if ('' === $id || !is_array($args)) {
                continue;
            }
            $visibility = !empty($args['visibility']) ? sanitize_key((string) $args['visibility']) : 'public';
            if (!in_array($visibility, array('public', 'guest_session', 'auth_required'), true)) {
                $visibility = 'public';
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
            $nonce = (string) $request->get_param('_wpnonce');
        }

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
     * Return metadata for one fragment.
     *
     * @param string $id Fragment id.
     * @return array<string,mixed>
     */
    protected function fragment_meta_for($id) {
        $meta = self::fragment_meta();
        return isset($meta[$id]) ? $meta[$id] : array('visibility' => 'public');
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

            $html = '';
            try {
                $html = (string) call_user_func($fragments[$id]);
            } catch (\Throwable $e) {
                if (class_exists('UCP_Logger')) {
                    UCP_Logger::log('warning', 'esi', 'fragment_error', 'ESI-fragment gaf een fout.', array('id' => $id, 'error' => $e->getMessage()));
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
        $response->header('Vary', 'Cookie');
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
            'window.ucpEsiLoader=' . wp_json_encode(array(
                'endpoint' => esc_url_raw(rest_url('ultracache-pro/v1/esi')),
                'nonce'    => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            )) . ';',
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
            if ('' === self::clean_id($id)) {
                return false;
            }
        }
        return true;
    }

    protected function enforce_rate_limits() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $minute = (int) floor(time() / MINUTE_IN_SECONDS);
        $ip_key = 'ucp_esi_ip_' . md5($ip . '|' . $minute);
        $site_key = 'ucp_esi_site_' . $minute;

        $ip_count = (int) get_transient($ip_key);
        $site_count = (int) get_transient($site_key);

        if ($ip_count >= 120 || $site_count >= 1200) {
            $response = new WP_REST_Response(array('ok' => false, 'code' => 'rate_limited'), 429);
            $response->header('Retry-After', '60');
            return $response;
        }

        set_transient($ip_key, $ip_count + 1, 2 * MINUTE_IN_SECONDS);
        set_transient($site_key, $site_count + 1, 2 * MINUTE_IN_SECONDS);
        return null;
    }

    protected static function clean_id($id) {
        $clean = preg_replace('/[^a-z0-9_-]/i', '', (string) $id);
        return is_string($clean) ? substr($clean, 0, 80) : '';
    }
}
