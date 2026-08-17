<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Maps UX-only combined controls back to stable stored option keys.
 *
 * These controls are convenience fields used by the admin UI. They must never
 * become stored runtime flags because the rest of the plugin already depends on
 * the existing option names.
 */
final class UCP_Settings_Combined_Controls {
    /**
     * Apply supported combined controls to the settings array.
     *
     * @param array<string,mixed> $settings        Incoming settings.
     * @param bool                $remove_controls Remove UX-only keys after mapping.
     * @param bool                $strict_values   Retained for backward compatibility.
     * @return array<string,mixed>
     */
    public static function apply(array $settings, $remove_controls = false, $strict_values = false) {
        unset($strict_values);

        foreach (self::control_handlers() as $key => $handler) {
            if (array_key_exists($key, $settings)) {
                self::{$handler}($settings, $remove_controls);
            }
        }

        return $settings;
    }

    /**
     * UX-only combined-control keys.
     *
     * @return array<int,string>
     */
    public static function control_keys() {
        return array_keys(self::control_handlers());
    }

    /**
     * Map incoming controls to their private handlers.
     *
     * @return array<string,string>
     */
    private static function control_handlers() {
        return array(
            'html_optimization_mode'  => 'apply_html_optimization_mode_control',
            'image_optimization_mode' => 'apply_image_optimization_mode_control',
            'delay_js_control'        => 'apply_delay_js_control_control',
            'media_lazyload_mode'     => 'apply_media_lazyload_mode_control',
            'lcp_image_mode'          => 'apply_lcp_image_mode_control',
            'google_fonts_mode'       => 'apply_google_fonts_mode_control',
            'preload_mode'            => 'apply_preload_mode_control',
            'stale_cache_mode'        => 'apply_stale_cache_mode_control',
            'heartbeat_interval_mode' => 'apply_heartbeat_interval_mode_control',
            'bloat_removal_mode'      => 'apply_bloat_removal_mode_control',
        );
    }

    private static function sanitize_control_value($value) {
        return is_scalar($value) ? sanitize_key((string) $value) : '';
    }

    private static function apply_html_optimization_mode_control(array &$settings, $remove_controls) {
        $mode = self::sanitize_control_value($settings['html_optimization_mode']);
        if (!in_array($mode, array('off', 'comments', 'minify'), true)) {
            self::remove_control($settings, 'html_optimization_mode', $remove_controls);
            return;
        }

        $settings['remove_html_comments'] = in_array($mode, array('comments', 'minify'), true) ? 1 : 0;
        $settings['enable_html_minify']    = 'minify' === $mode ? 1 : 0;
        self::remove_control($settings, 'html_optimization_mode', $remove_controls);
    }

    private static function apply_image_optimization_mode_control(array &$settings, $remove_controls) {
        $mode = self::sanitize_control_value($settings['image_optimization_mode']);
        if (!in_array($mode, array('off', 'webp', 'webp_avif'), true)) {
            self::remove_control($settings, 'image_optimization_mode', $remove_controls);
            return;
        }

        $settings['enable_image_optimization'] = in_array($mode, array('webp', 'webp_avif'), true) ? 1 : 0;
        $settings['enable_webp_generation']    = in_array($mode, array('webp', 'webp_avif'), true) ? 1 : 0;
        $settings['enable_avif_generation']    = 'webp_avif' === $mode ? 1 : 0;
        self::remove_control($settings, 'image_optimization_mode', $remove_controls);
    }

    private static function apply_delay_js_control_control(array &$settings, $remove_controls) {
        $mode = self::sanitize_control_value($settings['delay_js_control']);
        if (!in_array($mode, array('off', 'specified', 'all', 'safe'), true)) {
            self::remove_control($settings, 'delay_js_control', $remove_controls);
            return;
        }

        $settings['enable_delay_js'] = 'off' === $mode ? 0 : 1;
        if ('off' !== $mode) {
            $settings['delay_js_mode']      = 'specified' === $mode ? 'specified' : 'all';
            $settings['delay_js_safe_mode'] = 'safe' === $mode ? 1 : 0;
        }
        if ('safe' === $mode) {
            $settings['delay_js_disable_click_delay'] = 1;
        }
        self::remove_control($settings, 'delay_js_control', $remove_controls);
    }

    private static function apply_media_lazyload_mode_control(array &$settings, $remove_controls) {
        $mode = self::sanitize_control_value($settings['media_lazyload_mode']);
        if (!in_array($mode, array('off', 'images', 'iframes', 'youtube'), true)) {
            self::remove_control($settings, 'media_lazyload_mode', $remove_controls);
            return;
        }

        $settings['enable_lazy_images']          = in_array($mode, array('images', 'iframes', 'youtube'), true) ? 1 : 0;
        $settings['enable_lazy_iframes']         = in_array($mode, array('iframes', 'youtube'), true) ? 1 : 0;
        $settings['enable_lazy_youtube_preview'] = 'youtube' === $mode ? 1 : 0;
        self::remove_control($settings, 'media_lazyload_mode', $remove_controls);
    }

    private static function apply_lcp_image_mode_control(array &$settings, $remove_controls) {
        $mode = self::sanitize_control_value($settings['lcp_image_mode']);
        $map  = array(
            'off'          => array(0, 0),
            'protect_hero' => array(0, 1),
            'preload_hero' => array(1, 1),
            'recommended'  => array(2, 4),
            'custom'       => array(1, 2),
        );

        if (isset($map[$mode])) {
            $settings['preload_critical_images']          = $map[$mode][0];
            $settings['lazyload_exclude_leading_images'] = $map[$mode][1];
        }
        self::remove_control($settings, 'lcp_image_mode', $remove_controls);
    }

    private static function apply_google_fonts_mode_control(array &$settings, $remove_controls) {
        $mode = self::sanitize_control_value($settings['google_fonts_mode']);
        if (!in_array($mode, array('standard', 'swap', 'local', 'disable'), true)) {
            self::remove_control($settings, 'google_fonts_mode', $remove_controls);
            return;
        }

        $settings['enable_disable_google_fonts'] = 'disable' === $mode ? 1 : 0;
        $settings['enable_local_google_fonts']   = 'local' === $mode ? 1 : 0;
        $settings['enable_font_display_swap']    = in_array($mode, array('swap', 'local'), true) ? 1 : 0;
        if ('local' === $mode) {
            $settings['enable_auto_font_preloads'] = 1;
        }
        self::remove_control($settings, 'google_fonts_mode', $remove_controls);
    }

    private static function apply_preload_mode_control(array &$settings, $remove_controls) {
        $mode = self::sanitize_control_value($settings['preload_mode']);
        $map  = array(
            'off'         => array(0, 0, 0, 0),
            'recommended' => array(1, 1, 1, 1),
            'homepage'    => array(1, 1, 0, 1),
            'manual'      => array(1, 0, 0, 0),
        );

        if (isset($map[$mode])) {
            $settings['enable_preload']       = $map[$mode][0];
            $settings['enable_preload_queue'] = $map[$mode][1];
            $settings['preload_sitemaps']     = $map[$mode][2];
            $settings['preload_homepage']     = $map[$mode][3];
        }
        self::remove_control($settings, 'preload_mode', $remove_controls);
    }

    private static function apply_stale_cache_mode_control(array &$settings, $remove_controls) {
        $mode = self::sanitize_control_value($settings['stale_cache_mode']);
        if ('off' === $mode) {
            $settings['enable_stale_cache'] = 0;
        } elseif (in_array($mode, array('6', '12', '24', '48'), true)) {
            $settings['enable_stale_cache']    = 1;
            $settings['stale_cache_lifespan'] = absint($mode);
        }
        self::remove_control($settings, 'stale_cache_mode', $remove_controls);
    }

    private static function apply_heartbeat_interval_mode_control(array &$settings, $remove_controls) {
        $mode = self::sanitize_control_value($settings['heartbeat_interval_mode']);
        if ('custom' === $mode) {
            $settings['heartbeat_frontend_frequency'] = 60;
            $settings['heartbeat_editor_frequency']   = 30;
            $settings['heartbeat_backend_frequency']  = 60;
            $settings['heartbeat_frequency']          = 60;
        } elseif (in_array($mode, array('30', '60', '120'), true)) {
            $interval                                = absint($mode);
            $settings['heartbeat_frontend_frequency'] = $interval;
            $settings['heartbeat_editor_frequency']   = $interval;
            $settings['heartbeat_backend_frequency']  = $interval;
            $settings['heartbeat_frequency']          = $interval;
        }
        self::remove_control($settings, 'heartbeat_interval_mode', $remove_controls);
    }

    private static function apply_bloat_removal_mode_control(array &$settings, $remove_controls) {
        $mode = self::sanitize_control_value($settings['bloat_removal_mode']);
        if (!in_array($mode, array('off', 'safe', 'aggressive'), true)) {
            self::remove_control($settings, 'bloat_removal_mode', $remove_controls);
            return;
        }

        $safe_keys = array(
            'enable_disable_dashicons',
            'enable_hide_wp_version',
            'enable_remove_rsd_link',
            'enable_remove_shortlink',
            'enable_remove_rss_feed_links',
            'enable_remove_rest_api_links',
            'enable_disable_self_pingbacks',
        );
        $aggressive_keys = array(
            'enable_disable_jquery_migrate',
            'enable_disable_xmlrpc',
            'enable_disable_rss_feeds',
            'enable_remove_global_styles',
            'enable_remove_query_strings',
        );

        foreach ($safe_keys as $key) {
            $settings[$key] = 'off' === $mode ? 0 : 1;
        }
        foreach ($aggressive_keys as $key) {
            $settings[$key] = 'aggressive' === $mode ? 1 : 0;
        }
        self::remove_control($settings, 'bloat_removal_mode', $remove_controls);
    }

    /**
     * Remove a UX-only control key when normalizing stored settings.
     *
     * @param array<string,mixed> $settings Settings array passed by reference.
     * @param string              $key      Control key.
     * @param bool                $remove   Whether to remove the key.
     * @return void
     */
    private static function remove_control(array &$settings, $key, $remove) {
        if ($remove) {
            unset($settings[$key]);
        }
    }
}
