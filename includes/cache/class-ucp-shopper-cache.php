<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * UCP_Shopper_Cache
 *
 * Lifts the WooCommerce full-page cache hit-rate for active shoppers without
 * leaking personalised carts, mirroring the model used by WP Rocket (public
 * page cache + client-side cart-fragment hydration) instead of LiteSpeed ESI,
 * which would require a LiteSpeed Enterprise / QUIC.cloud server.
 *
 * Responsibilities:
 *  - "Serve cache to shoppers": keep public pages cached for visitors who own a
 *    WooCommerce cart/session cookie, and refuse to STORE any render that was
 *    produced with a non-empty cart (so a shopper's cart can never be baked into
 *    a shared cache file).
 *  - Cookie policy bridge: subtract the multi-currency / language "vary" cookies
 *    and (optionally) the cart/session cookies from the cache bypass lists used
 *    by both the PHP request policy and the advanced-cache.php drop-in.
 *  - Client-side mini-cart hydration nudge.
 *  - Cart-fragments AJAX optimisation (cache the empty-cart response, optionally
 *    dequeue wc-cart-fragments where no mini-cart is present).
 *  - Webshop readiness recommendations (e.g. nudge a persistent object cache).
 *
 * Staging-first: everything here is gated behind options that default to OFF.
 */
class UCP_Shopper_Cache {

    const EMPTY_FRAGMENTS_TRANSIENT = 'ucp_empty_cart_fragments';

    public function __construct() {
        // Cookie policy: vary cookies should *vary* the cache, not bypass it; cart
        // cookies should not bypass when "serve cache to shoppers" is enabled.
        add_filter('ucp_excluded_cookie_fragments', array($this, 'filter_runtime_excluded_cookies'), 20);
        add_filter('ucp_dropin_exclude_cookies', array($this, 'filter_dropin_excluded_cookies'), 20);

        // Make sure promoted cookies are treated as cache-safe (never "unknown") under strict modes.
        add_filter('ucp_cache_safe_request_cookie_fragments', array($this, 'filter_safe_request_cookies'), 20);
        add_filter('ucp_dropin_safe_cookies', array($this, 'filter_dropin_safe_cookies'), 20);

        // Front-end behaviour only.
        if (!is_admin()) {
            // Never persist a render that carries a non-empty cart into the shared cache.
            add_action('template_redirect', array($this, 'guard_personalised_render'), 0);

            if (UCP_Options::get('serve_cache_to_shoppers')) {
                add_action('wp_footer', array($this, 'output_cart_hydration_script'), 99);
            }

            if (UCP_Options::get('optimize_cart_fragments') && UCP_Options::get('safe_cart_fragments_mode')) {
                // Capture the empty-cart fragment payload so we can replay it cheaply,
                // and short-circuit the refresh request when the cart is empty.
                add_filter('woocommerce_add_to_cart_fragments', array($this, 'capture_empty_cart_fragments'), 999);
                add_action('wc_ajax_get_refreshed_fragments', array($this, 'maybe_serve_cached_empty_fragments'), 0);
                add_action('woocommerce_ajax_get_refreshed_fragments', array($this, 'maybe_serve_cached_empty_fragments'), 0);
                add_filter('woocommerce_get_script_data', array($this, 'filter_cart_fragments_script_data'), 10, 2);
            }

            if (UCP_Options::get('limit_cart_fragments_to_woo') && UCP_Options::get('safe_cart_fragments_mode')) {
                add_action('wp_enqueue_scripts', array($this, 'maybe_dequeue_cart_fragments'), 99);
            }
        }

        // Invalidate the cached empty-cart fragment payload whenever the cart changes shape.
        add_action('woocommerce_add_to_cart', array($this, 'flush_empty_cart_fragments'));
        add_action('woocommerce_cart_item_removed', array($this, 'flush_empty_cart_fragments'));
        add_action('woocommerce_cart_emptied', array($this, 'flush_empty_cart_fragments'));

        // Webshop readiness nudge (admin only).
        if (is_admin()) {
            add_action('admin_notices', array($this, 'maybe_render_object_cache_notice'));
        }
    }

    /* ---------------------------------------------------------------------
     * Cookie policy bridge
     * ------------------------------------------------------------------- */

    /**
     * Cookie name-fragments that should VARY the cache key instead of bypassing
     * the cache entirely (multi-currency / multi-language).
     *
     * @return string[]
     */
    public static function vary_cookie_fragments() {
        $fragments = UCP_Helpers::normalize_multiline(UCP_Options::get('cache_vary_cookies', ''));
        return (array) apply_filters('ucp_cache_vary_cookie_fragments', $fragments);
    }

    /**
     * WooCommerce cart / session cookies that normally force a full bypass.
     * When "serve cache to shoppers" is enabled they are promoted to cache-safe
     * so browsing shoppers keep hitting the public cache.
     *
     * @return string[]
     */
    public static function cart_session_cookie_fragments() {
        return (array) apply_filters('ucp_cart_session_cookie_fragments', array(
            'woocommerce_items_in_cart',
            'woocommerce_cart_hash',
            'wp_woocommerce_session_',
            'woocommerce_recently_viewed',
            'edd_items_in_cart',
        ));
    }

    /**
     * Fragments that must be removed from any "bypass" list, given the current options.
     *
     * @return string[]
     */
    public static function fragments_promoted_from_bypass() {
        $promote = self::vary_cookie_fragments();
        if (UCP_Options::get('serve_cache_to_shoppers')) {
            $promote = array_merge($promote, self::cart_session_cookie_fragments());
        }
        return array_values(array_unique(array_filter(array_map('trim', $promote), 'strlen')));
    }

    /**
     * Subtract promoted fragments from a bypass list (runtime PHP request policy).
     *
     * @param string[] $fragments
     * @return string[]
     */
    public function filter_runtime_excluded_cookies($fragments) {
        return $this->subtract_promoted_fragments((array) $fragments);
    }

    /**
     * Subtract promoted fragments from the drop-in bypass list (written to dropin-config.php).
     *
     * @param string[] $fragments
     * @return string[]
     */
    public function filter_dropin_excluded_cookies($fragments) {
        return $this->subtract_promoted_fragments((array) $fragments);
    }

    /**
     * Ensure promoted cookies count as cache-safe for the PHP request policy.
     *
     * @param string[] $fragments
     * @return string[]
     */
    public function filter_safe_request_cookies($fragments) {
        return array_values(array_unique(array_merge((array) $fragments, self::fragments_promoted_from_bypass())));
    }

    /**
     * Ensure promoted cookies count as cache-safe for the advanced-cache.php drop-in.
     *
     * @param string[] $fragments
     * @return string[]
     */
    public function filter_dropin_safe_cookies($fragments) {
        return array_values(array_unique(array_merge((array) $fragments, self::fragments_promoted_from_bypass())));
    }

    private function subtract_promoted_fragments($fragments) {
        $promote = self::fragments_promoted_from_bypass();
        if (empty($promote)) {
            return array_values($fragments);
        }
        $promote_keys = array();
        foreach ($promote as $p) {
            $promote_keys[sanitize_key($p)] = true;
        }
        $kept = array();
        foreach ($fragments as $fragment) {
            if ('' === trim((string) $fragment)) {
                continue;
            }
            if (isset($promote_keys[sanitize_key((string) $fragment)])) {
                continue; // promoted: do not bypass on this cookie.
            }
            $kept[] = $fragment;
        }
        return array_values($kept);
    }

    /* ---------------------------------------------------------------------
     * Store guard — never bake a personalised cart into a shared cache file
     * ------------------------------------------------------------------- */

    /**
     * If the current render carries a non-empty WooCommerce cart, mark it
     * uncacheable so UCP_Cache::store_buffer() refuses to persist it. The
     * shopper is still SERVED the public cache on subsequent hits; only the
     * seeding of the shared file is restricted to empty-cart renders.
     */
    public function guard_personalised_render() {
        if (!UCP_Options::get('serve_cache_to_shoppers')) {
            return;
        }
        if ($this->cart_is_empty()) {
            return;
        }
        if (!defined('DONOTCACHEPAGE')) {
            // store_buffer() already honours DONOTCACHEPAGE; serving is unaffected.
            define('DONOTCACHEPAGE', true);
        }
        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('cache', 'Shopper cache: skipped storing a render with a non-empty cart', array());
        }
    }

    /**
     * @return bool True when there is no active WooCommerce cart with items.
     */
    private function cart_is_empty() {
        if (!function_exists('WC')) {
            return true;
        }
        $wc = WC();
        if (!is_object($wc) || !isset($wc->cart) || !is_object($wc->cart)) {
            return true;
        }
        if (!method_exists($wc->cart, 'is_empty')) {
            return true;
        }
        // Suppress notices if the cart is not fully initialised this early.
        $is_empty = true;
        try {
            $is_empty = (bool) $wc->cart->is_empty();
        } catch (\Throwable $e) {
            $is_empty = true;
        }
        return $is_empty;
    }

    /* ---------------------------------------------------------------------
     * Client-side mini-cart hydration
     * ------------------------------------------------------------------- */

    /**
     * Nudge WooCommerce to refresh cart fragments on load so the (empty) mini-cart
     * in the cached HTML is replaced by the visitor's real cart count. wc-cart-fragments
     * normally restores from localStorage immediately; this guarantees a refresh even
     * when the cached markup was produced for an empty cart.
     */
    public function output_cart_hydration_script() {
        if ($this->should_skip_hydration()) {
            return;
        }
        $js = "(function(){function go(){try{if(window.jQuery&&jQuery.fn){jQuery(document.body).trigger('wc_fragment_refresh');}}catch(e){}}if(document.readyState==='complete'){go();}else{window.addEventListener('load',go,{once:true});}})();";
        if (function_exists('wp_get_inline_script_tag')) {
            echo wp_get_inline_script_tag($js, array('id' => 'ucp-cart-hydrate')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated via wp_get_inline_script_tag.
            return;
        }
        echo '<script id="ucp-cart-hydrate">' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline script literal.
    }

    private function should_skip_hydration() {
        if (!function_exists('is_woocommerce') && !function_exists('WC')) {
            return true;
        }
        if (is_user_logged_in()) {
            return true; // logged-in users are not served the public shopper cache by default.
        }
        // No need to hydrate on transactional pages (they are excluded from cache anyway).
        if (function_exists('is_cart') && (is_cart() || (function_exists('is_checkout') && is_checkout()) || (function_exists('is_account_page') && is_account_page()))) {
            return true;
        }
        return (bool) apply_filters('ucp_skip_cart_hydration', false);
    }

    /* ---------------------------------------------------------------------
     * Cart-fragments AJAX optimisation
     * ------------------------------------------------------------------- */

    /**
     * Memoise the fragment payload while the cart is empty. WooCommerce passes the
     * fragments array through this filter; we only snapshot the empty-cart variant.
     *
     * @param array $fragments
     * @return array
     */
    public function capture_empty_cart_fragments($fragments) {
        if (!is_array($fragments) || !$this->cart_is_empty()) {
            return $fragments;
        }
        $payload = array(
            'fragments' => $fragments,
            'cart_hash' => '',
        );
        if (function_exists('WC') && is_object(WC()->cart) && method_exists(WC()->cart, 'get_cart_hash')) {
            $payload['cart_hash'] = (string) WC()->cart->get_cart_hash();
        }
        // Short TTL: fragments embed nonces and prices that should not go stale for long.
        set_transient(self::EMPTY_FRAGMENTS_TRANSIENT, $payload, (int) apply_filters('ucp_empty_cart_fragments_ttl', 5 * MINUTE_IN_SECONDS));
        return $fragments;
    }

    /**
     * Serve the cached empty-cart fragment payload directly, skipping WooCommerce's
     * own (relatively expensive) fragment rebuild, but only while the cart is empty.
     */
    public function maybe_serve_cached_empty_fragments() {
        if (!$this->cart_is_empty()) {
            return; // let WooCommerce render the real, non-empty fragments.
        }
        $payload = get_transient(self::EMPTY_FRAGMENTS_TRANSIENT);
        if (!is_array($payload) || empty($payload['fragments']) || !is_array($payload['fragments'])) {
            return; // nothing cached yet; let WooCommerce build it (and we snapshot it).
        }
        if (!headers_sent()) {
            // Private, short-lived: the response is per-visitor-safe (empty cart) but still nonce-bearing.
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            header('X-UltraCache-Fragments: empty-cart-hit');
        }
        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('cache', 'Shopper cache: served cached empty-cart fragments', array());
        }
        wp_send_json($payload);
    }

    public function flush_empty_cart_fragments() {
        delete_transient(self::EMPTY_FRAGMENTS_TRANSIENT);
    }

    /**
     * Disable wc-cart-fragments script data only in explicit safe mode and only
     * outside WooCommerce/cart/account contexts where no mini-cart is detected.
     *
     * @param array|null $script_data
     * @param string     $handle
     * @return array|null
     */
    public function filter_cart_fragments_script_data($script_data, $handle) {
        if ('wc-cart-fragments' !== $handle || !UCP_Options::get('safe_cart_fragments_mode')) {
            return $script_data;
        }
        if ($this->is_woocommerce_runtime_context() || $this->page_has_mini_cart()) {
            return $script_data;
        }
        return null;
    }

    private function is_woocommerce_runtime_context() {
        if (function_exists('is_cart') && is_cart()) {
            return true;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return true;
        }
        if (function_exists('is_woocommerce') && is_woocommerce()) {
            return true;
        }
        return false;
    }

    /**
     * Dequeue wc-cart-fragments on pages that have no mini-cart, mirroring (and
     * extending to older WooCommerce / themes) the WooCommerce 7.8+ behaviour of
     * only loading the script when a Cart Widget block is present.
     */
    public function maybe_dequeue_cart_fragments() {
        if (!wp_script_is('wc-cart-fragments', 'enqueued') && !wp_script_is('wc-cart-fragments', 'registered')) {
            return;
        }
        if ($this->page_has_mini_cart()) {
            return;
        }
        if ((bool) apply_filters('ucp_keep_cart_fragments', false)) {
            return;
        }
        wp_dequeue_script('wc-cart-fragments');
        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('scripts', 'Shopper cache: dequeued wc-cart-fragments (no mini-cart on page)', array());
        }
    }

    /**
     * Best-effort detection of a mini-cart / cart widget on the current page.
     *
     * @return bool
     */
    private function page_has_mini_cart() {
        // Transactional pages always keep fragments.
        if ($this->is_woocommerce_runtime_context()) {
            return true;
        }
        if (is_active_widget(false, false, 'woocommerce_widget_cart', true)) {
            return true;
        }
        $post = get_post();
        if ($post instanceof WP_Post && is_string($post->post_content) && '' !== $post->post_content) {
            if (
                has_block('woocommerce/mini-cart', $post)
                || has_block('woocommerce/cart', $post)
                || false !== stripos($post->post_content, 'mini-cart')
                || false !== stripos($post->post_content, 'widget_shopping_cart')
            ) {
                return true;
            }
        }
        return (bool) apply_filters('ucp_page_has_mini_cart', false, $post);
    }

    /* ---------------------------------------------------------------------
     * Webshop readiness recommendations
     * ------------------------------------------------------------------- */

    /**
     * Structured recommendations for store owners. Surfaced in the admin UI and
     * reusable from the status/diagnostics layer.
     *
     * @return array<int,array{id:string,severity:string,message:string}>
     */
    public static function webshop_recommendations() {
        $recommendations = array();
        $woo_active = class_exists('WooCommerce') || (function_exists('UCP_Integrations') && false);

        if (!$woo_active && !function_exists('WC')) {
            return $recommendations;
        }

        $persistent_object_cache = UCP_Options::get('enable_redis_object_cache')
            || UCP_Options::get('enable_apcu_object_cache')
            || wp_using_ext_object_cache();

        if (!$persistent_object_cache) {
            $recommendations[] = array(
                'id'       => 'object_cache',
                'severity' => 'recommended',
                'message'  => __('Een webshop laat cart, checkout en account-pagina\'s altijd ongecachet (terecht). Een persistente object cache (Redis of APCu) versnelt juist die dynamische pagina\'s plus sessies en transients. Overweeg Redis aan te zetten.', 'ultracache-pro'),
            );
        }

        if (!UCP_Options::get('serve_cache_to_shoppers')) {
            $recommendations[] = array(
                'id'       => 'serve_to_shoppers',
                'severity' => 'tip',
                'message'  => __('Schakel "Publieke cache voor shoppers" in om bezoekers met een mandje toch de gecachete pagina te tonen; de mini-cart wordt client-side bijgewerkt. Test eerst op staging.', 'ultracache-pro'),
            );
        }

        if (!UCP_Options::get('optimize_cart_fragments') || !UCP_Options::get('safe_cart_fragments_mode')) {
            $recommendations[] = array(
                'id'       => 'cart_fragments',
                'severity' => 'tip',
                'message'  => __('Cart-fragments optimalisatie is standaard uit. Schakel dit alleen in met veilige modus en test mini-cart, cart en checkout op staging.', 'ultracache-pro'),
            );
        }

        return (array) apply_filters('ucp_webshop_recommendations', $recommendations);
    }

    public function maybe_render_object_cache_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        // Only on UltraCache admin screens, and only the highest-value nudge.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || false === stripos((string) $screen->id, 'ultracache')) {
            return;
        }
        foreach (self::webshop_recommendations() as $rec) {
            if ('object_cache' !== $rec['id']) {
                continue;
            }
            echo '<div class="notice notice-info"><p><strong>UltraCache Pro</strong> — ' . esc_html($rec['message']) . '</p></div>';
            break;
        }
    }
}
