<?php
/**
 * UltraCache Pro — First-run onboarding wizard.
 *
 * A self-contained, build-free 3-step wizard that lands first-run admins on a goal,
 * applies a render-safe overlay through the existing settings endpoint, warms the
 * homepage and shows a real first-result timing so the operator sees a win in <60s.
 *
 * It deliberately reuses the existing REST surface (ultracache-pro/v1) and never
 * enables advanced CSS/JS render features — those stay opt-in, exactly like the
 * Safe Autopilot baseline. The wizard complements the autopilot; it does not fight it.
 *
 * @package UltraCache_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class UCP_Onboarding_Wizard {

    /** Completion flag (separate from the autopilot DONE flag). */
    const DONE_FLAG = 'ucp_onboarding_completed';

    /** First-install trigger. Set only during a genuinely new install. */
    const FIRST_INSTALL_FLAG = 'ucp_onboarding_first_install_pending';

    /** Asset handles. */
    const SCRIPT_HANDLE = 'ucp-onboarding-wizard';
    const STYLE_HANDLE  = 'ucp-onboarding-wizard';

    /** REST namespace shared with the rest of the admin controller. */
    const REST_NAMESPACE = 'ultracache-pro/v1';

    public static function bootstrap() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'maybe_enqueue'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Should the wizard run for this request?
     *
     * Runs when not yet completed, or forced via ?ucp-wizard=1 (so it can be re-opened
     * from a "Re-run setup" link without resetting any options).
     *
     * @return bool
     */
    public static function should_run() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only UI toggle, no state change here.
        if (isset($_GET['ucp-wizard']) && '1' === sanitize_text_field(wp_unslash($_GET['ucp-wizard']))) {
            return true;
        }

        // Only auto-open on a real first install. Existing sites that receive this
        // plugin update should not suddenly get the setup popup again.
        return !get_option(self::DONE_FLAG) && (bool) get_option(self::FIRST_INSTALL_FLAG, false);
    }

    /**
     * Mark the setup popup as pending for a genuinely fresh install.
     *
     * @return void
     */
    public static function mark_fresh_install_pending() {
        if (!get_option(self::DONE_FLAG)) {
            update_option(self::FIRST_INSTALL_FLAG, time(), false);
        }
    }

    /**
     * Enqueue the wizard only on the UltraCache admin page and only when it should run.
     *
     * @param string $hook Current admin page hook suffix.
     * @return void
     */
    public static function maybe_enqueue($hook) {
        if (!class_exists('UCP_Admin_Router') || !UCP_Admin_Router::is_plugin_hook_suffix($hook)) {
            return;
        }
        if (!self::should_run()) {
            return;
        }

        $base = defined('UCP_URL') ? UCP_URL : plugin_dir_url(dirname(__FILE__, 4) . '/ultracache-pro.php');
        $path = defined('UCP_PATH') ? UCP_PATH : plugin_dir_path(dirname(__FILE__, 4) . '/ultracache-pro.php');

        $script_src = 'assets/admin/js/onboarding/ucp-onboarding-wizard.js';
        $style_src  = 'assets/admin/css/onboarding/ucp-onboarding-wizard.css';

        // Prefer the production .min variant through the shared helper (honours
        // SCRIPT_DEBUG and falls back to source when no .min file is present).
        $has_helper = class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'asset_path');
        $script_rel = $has_helper ? UCP_Helpers::asset_path($script_src) : $script_src;
        $style_rel  = $has_helper ? UCP_Helpers::asset_path($style_src) : $style_src;

        $script_ver = file_exists($path . $script_rel) ? (string) filemtime($path . $script_rel) : (defined('UCP_VERSION') ? UCP_VERSION : '1');
        $style_ver  = file_exists($path . $style_rel) ? (string) filemtime($path . $style_rel) : (defined('UCP_VERSION') ? UCP_VERSION : '1');

        wp_enqueue_style(
            self::STYLE_HANDLE,
            $base . $style_rel,
            array('wp-components'),
            $style_ver
        );

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            $base . $script_rel,
            array('wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-a11y'),
            $script_ver,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::SCRIPT_HANDLE, 'ultracache-pro', $path . 'languages');
        }

        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            'window.UCP_WIZARD_CONFIG = ' . wp_json_encode(self::client_config()) . ';',
            'before'
        );
    }

    /**
     * Config handed to the JS wizard. Self-contained so the wizard works even if the
     * main React config is not present.
     *
     * @return array<string,mixed>
     */
    protected static function client_config() {
        return array(
            'restUrl'   => esc_url_raw(rest_url(self::REST_NAMESPACE . '/')),
            'homeUrl'   => esc_url_raw(home_url('/')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'completed' => (bool) get_option(self::DONE_FLAG),
            'isWoo'     => class_exists('WooCommerce'),
            'goals'     => self::goal_definitions(),
        );
    }

    /**
     * Goal presets. Render-safe keys only — anything that can shift layout or break
     * scripts on a live site (Used CSS, Critical CSS, combine, delay/defer JS) is left
     * out on purpose and stays opt-in.
     *
     * The values are sent verbatim to POST settings/bulk, which merges + sanitizes
     * against the real defaults, so unknown keys are dropped safely server-side.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function goal_definitions() {
        $base = array(
            'ui_mode'                     => 'simple',
            'enable_cache'                => 1,
            'enable_preload'              => 1,
            'enable_preload_queue'        => 1,
            'enable_targeted_purge'       => 1,
            'enable_cache_tags'           => 1,
            'enable_lazy_images'          => 1,
            'enable_lazy_iframes'         => 1,
            'enable_add_image_dimensions' => 1,
            'enable_font_display_swap'    => 1,
            'enable_auto_font_preloads'   => 1,
            'enable_heartbeat_control'    => 1,
            'enable_remove_emojis'        => 1,
            'enable_css_minify'           => 1,
            'enable_gzip_precompression'  => 1,
            'enable_brotli_precompression' => 1,
            'browser_cache_headers'       => 1,
        );

        $speed = array_merge($base, array(
            'enable_prefetch_links'     => 1,
            'enable_auto_resource_hints' => 1,
            'enable_lazy_youtube_preview' => 1,
        ));

        $woo = array_merge($base, array(
            'enable_woocommerce_rules' => 1,
            'woocommerce_safety_mode'  => 1,
            'optimize_cart_fragments'  => 1,
            'cache_mobile_separately'  => 1,
            'cache_logged_in'          => 0,
        ));

        return array(
            'speed' => array(
                'label'       => __('Maximale snelheid voorbereiden', 'ultracache-pro'),
                'description' => __('Meer optimalisatie, maar zonder risicovolle CSS/JS automatisch aan te zetten.', 'ultracache-pro'),
                'settings'    => $speed,
            ),
            'woo' => array(
                'label'       => __('WooCommerce optimaliseren', 'ultracache-pro'),
                'description' => __('Snelheid met veilige cache-bypass voor winkelwagen, checkout en account.', 'ultracache-pro'),
                'settings'    => $woo,
            ),
            'safe' => array(
                'label'       => __('Veilig optimaliseren', 'ultracache-pro'),
                'description' => __('Alleen rustige basisoptimalisaties zoals cache, lazy-load en browser-cache.', 'ultracache-pro'),
                'settings'    => $base,
            ),
        );
    }

    /**
     * REST: mark onboarding complete. Stores the flag without touching any setting.
     *
     * @return void
     */
    public static function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/onboarding/complete', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'mark_complete'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
    }

    /**
     * @param WP_REST_Request|null $request Request.
     * @return bool
     */
    public static function permissions_check($request = null) {
        if (class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'rest_admin_permission_check')) {
            return UCP_Helpers::rest_admin_permission_check($request);
        }
        return current_user_can('manage_options');
    }

    /**
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public static function mark_complete(WP_REST_Request $request) {
        update_option(self::DONE_FLAG, time(), false);
        delete_option(self::FIRST_INSTALL_FLAG);
        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('onboarding', 'Onboarding-wizard afgerond.', array());
        }
        return rest_ensure_response(array(
            'success'   => true,
            'message'   => __('Setup voltooid.', 'ultracache-pro'),
            'timestamp' => time(),
        ));
    }
}
