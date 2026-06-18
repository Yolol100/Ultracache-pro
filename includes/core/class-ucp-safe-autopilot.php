<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Safe out-of-the-box autopilot.
 *
 * The plugin ships everything default-OFF (staging-first), so a fresh install delivers little until
 * the operator manually enables features. NitroPack and FlyingPress win first impressions because
 * sensible, low-risk optimisation is on by default. This applies a CONSERVATIVE safe preset exactly
 * once on first run — only render-safe options (cache, lazyload, image dimensions, browser-cache
 * headers, heartbeat control, prefetch, font-display swap). Render-CHANGING options (Used CSS
 * removal, Critical CSS, Delay JS, JS combine/minify, image conversion) stay OFF and opt-in.
 *
 * Opt out with the `UCP_DISABLE_AUTOPILOT` constant or the `ucp_enable_safe_autopilot` filter.
 * Never re-applies and never overrides a value the operator has already chosen.
 */
class UCP_Safe_Autopilot {

    const DONE_FLAG = 'ucp_safe_autopilot_done';

    public static function bootstrap() {
        add_action('admin_init', array(__CLASS__, 'maybe_apply'));
    }

    /**
     * Apply the safe preset once, in admin context, without clobbering operator choices.
     *
     * @return void
     */
    public static function maybe_apply() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (get_option(self::DONE_FLAG)) {
            return;
        }

        // Honour explicit opt-out, but still mark done so we never reconsider.
        $enabled = !(defined('UCP_DISABLE_AUTOPILOT') && UCP_DISABLE_AUTOPILOT);
        /**
         * Filter whether the one-time safe autopilot preset is applied.
         *
         * @param bool $enabled
         */
        $enabled = (bool) apply_filters('ucp_enable_safe_autopilot', $enabled);
        if (!$enabled) {
            update_option(self::DONE_FLAG, time(), false);
            return;
        }

        // Only fill keys the operator hasn't already turned on; never downgrade an existing choice.
        $stored = get_option(UCP_Options::OPTION_KEY, array());
        $stored = is_array($stored) ? $stored : array();
        $apply  = array();
        foreach (self::safe_preset() as $key => $value) {
            if (!array_key_exists($key, $stored)) {
                $apply[$key] = $value;
            }
        }

        if (!empty($apply)) {
            UCP_Options::update($apply);
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('autopilot', 'Veilige standaard-optimalisaties toegepast bij eerste run.', array('applied' => count($apply)));
            }
            // Seed a single homepage cache warm so the first visitor already gets a hit.
            if (class_exists('UCP_Jobs') && !empty($apply['enable_cache'])) {
                UCP_Jobs::enqueue_unique('preload_url', array('url' => home_url('/')), 20, 'preload');
            }
        }

        update_option(self::DONE_FLAG, time(), false);

        if (class_exists('UCP_Admin_Notices')) {
            UCP_Admin_Notices::flash(__('UltraCache heeft veilige standaard-optimalisaties ingeschakeld. Geavanceerde CSS/JS-opties blijven uit tot je ze zelf aanzet (test eerst op staging).', 'ultracache-pro'), 'info');
        }
    }

    /**
     * The conservative, render-safe baseline. Deliberately excludes anything that can change layout
     * or break scripts on a live site without testing.
     *
     * @return array<string,int|string>
     */
    protected static function safe_preset() {
        return array(
            // Land first-run sites in the simple, one-screen UI; power users can reveal everything.
            'ui_mode'                      => 'simple',

            // Core caching — the single biggest safe win.
            'enable_cache'                 => 1,
            'cache_logged_in'              => 0,
            'cache_mobile_separately'      => 1,
            'browser_cache_headers'        => 1,
            'enable_preload'               => 1,
            'enable_preload_queue'         => 1,
            'preload_homepage'             => 1,
            'preload_sitemaps'             => 1,

            // Smart purge — only flush affected URLs/tags. Pure win, no render risk.
            'enable_targeted_purge'        => 1,
            'enable_cache_tags'            => 1,

            // WooCommerce safeguards are enabled by default when WooCommerce is present.
            'enable_woocommerce_rules'     => 1,
            'woocommerce_safety_mode'      => 1,
            'compatibility_mode'           => 1,

            // Media: lazyload + layout-stability dimensions (no format conversion).
            'enable_lazy_images'           => 1,
            'enable_lazy_iframes'          => 0,
            'enable_lazy_youtube_preview'  => 0,
            'enable_add_image_dimensions'  => 1,

            // LCP protection: never lazy-load the leading above-the-fold images and preload the
            // detected hero image. Prevents the "LCP image was lazily loaded" PSI warning that
            // bare lazyload would otherwise introduce. Mirrors the install-profile values.
            'lazyload_exclude_leading_images' => 3,
            'preload_critical_images'         => 2,

            // Low-risk delivery hints + render-SAFE asset wins.
            'enable_prefetch_links'        => 1,
            'enable_font_display_swap'     => 1,
            'enable_auto_font_preloads'    => 1,
            'enable_auto_resource_hints'   => 1,
            'enable_heartbeat_control'     => 1,
            'enable_remove_emojis'         => 1,

            // CSS minify is low-risk (no combine/used/critical). Compression is capability-guarded
            // (function_exists) so it safely no-ops where the server lacks brotli/gzip.
            'enable_css_minify'            => 1,
            'enable_gzip_precompression'   => 1,
            'enable_brotli_precompression' => 1,

            // Explicitly keep render-CHANGING optimisations OFF (documents intent; matches defaults).
            // These can shift layout or break scripts and stay opt-in / staging-first.
            'enable_used_css'              => 0,
            'enable_critical_css'          => 0,
            'enable_css_combine'           => 0,
            'enable_delay_js'              => 0,
            'enable_js_combine'            => 0,
            'enable_js_minify'             => 0,
        );
    }
}
