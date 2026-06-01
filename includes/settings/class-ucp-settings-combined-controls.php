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
     * @param bool                $strict_values   Preserve previous normalizer behavior by ignoring unknown values.
     * @return array<string,mixed>
     */
    public static function apply(array $settings, $remove_controls = false, $strict_values = false) {
        if (array_key_exists('html_optimization_mode', $settings)) {
            $html_mode = sanitize_key((string) $settings['html_optimization_mode']);
            if ('minify' === $html_mode) {
                $settings['remove_html_comments'] = 1;
                $settings['enable_html_minify'] = 1;
            } elseif ('comments' === $html_mode) {
                $settings['remove_html_comments'] = 1;
                $settings['enable_html_minify'] = 0;
            } elseif ('off' === $html_mode || !$strict_values) {
                $settings['remove_html_comments'] = 0;
                $settings['enable_html_minify'] = 0;
            }
            self::remove_control($settings, 'html_optimization_mode', $remove_controls);
        }

        if (array_key_exists('image_optimization_mode', $settings)) {
            $image_mode = sanitize_key((string) $settings['image_optimization_mode']);
            if ('webp_avif' === $image_mode) {
                $settings['enable_image_optimization'] = 1;
                $settings['enable_webp_generation'] = 1;
                $settings['enable_avif_generation'] = 1;
            } elseif ('webp' === $image_mode) {
                $settings['enable_image_optimization'] = 1;
                $settings['enable_webp_generation'] = 1;
                $settings['enable_avif_generation'] = 0;
            } elseif ('optimize' === $image_mode) {
                $settings['enable_image_optimization'] = 1;
                $settings['enable_webp_generation'] = 0;
                $settings['enable_avif_generation'] = 0;
            } elseif ('off' === $image_mode || !$strict_values) {
                $settings['enable_image_optimization'] = 0;
                $settings['enable_webp_generation'] = 0;
                $settings['enable_avif_generation'] = 0;
            }
            self::remove_control($settings, 'image_optimization_mode', $remove_controls);
        }

        if (array_key_exists('delay_js_control', $settings)) {
            $delay_mode_combined = sanitize_key((string) $settings['delay_js_control']);
            if ('off' === $delay_mode_combined) {
                $settings['enable_delay_js'] = 0;
            } elseif ('specified' === $delay_mode_combined) {
                $settings['enable_delay_js'] = 1;
                $settings['delay_js_mode'] = 'specified';
                $settings['delay_js_safe_mode'] = 0;
            } elseif ('all' === $delay_mode_combined) {
                $settings['enable_delay_js'] = 1;
                $settings['delay_js_mode'] = 'all';
                $settings['delay_js_safe_mode'] = 0;
            } elseif ('safe' === $delay_mode_combined) {
                $settings['enable_delay_js'] = 1;
                $settings['delay_js_mode'] = 'all';
                $settings['delay_js_safe_mode'] = 1;
                $settings['delay_js_disable_click_delay'] = 1;
            }
            self::remove_control($settings, 'delay_js_control', $remove_controls);
        }

        if (array_key_exists('media_lazyload_mode', $settings)) {
            $media_mode = sanitize_key((string) $settings['media_lazyload_mode']);
            $settings['enable_lazy_images'] = in_array($media_mode, array('images', 'iframes', 'youtube'), true) ? 1 : 0;
            $settings['enable_lazy_iframes'] = in_array($media_mode, array('iframes', 'youtube'), true) ? 1 : 0;
            $settings['enable_lazy_youtube_preview'] = 'youtube' === $media_mode ? 1 : 0;
            self::remove_control($settings, 'media_lazyload_mode', $remove_controls);
        }

        if (array_key_exists('lcp_image_mode', $settings)) {
            $lcp_mode = sanitize_key((string) $settings['lcp_image_mode']);
            if ('off' === $lcp_mode) {
                $settings['preload_critical_images'] = 0;
                $settings['lazyload_exclude_leading_images'] = 0;
            } elseif ('protect_hero' === $lcp_mode) {
                $settings['preload_critical_images'] = 0;
                $settings['lazyload_exclude_leading_images'] = 1;
            } elseif ('preload_hero' === $lcp_mode) {
                $settings['preload_critical_images'] = 1;
                $settings['lazyload_exclude_leading_images'] = 1;
            } elseif ('recommended' === $lcp_mode) {
                $settings['preload_critical_images'] = 2;
                $settings['lazyload_exclude_leading_images'] = 4;
            }
            self::remove_control($settings, 'lcp_image_mode', $remove_controls);
        }

        if (array_key_exists('google_fonts_mode', $settings)) {
            $fonts_mode = sanitize_key((string) $settings['google_fonts_mode']);
            $settings['enable_disable_google_fonts'] = 'disable' === $fonts_mode ? 1 : 0;
            $settings['enable_local_google_fonts'] = 'local' === $fonts_mode ? 1 : 0;
            $settings['enable_font_display_swap'] = in_array($fonts_mode, array('swap', 'local'), true) ? 1 : 0;
            self::remove_control($settings, 'google_fonts_mode', $remove_controls);
        }

        if (array_key_exists('preload_mode', $settings)) {
            $preload_mode = sanitize_key((string) $settings['preload_mode']);
            if ('off' === $preload_mode) {
                $settings['enable_preload'] = 0;
                $settings['enable_preload_queue'] = 0;
                $settings['preload_sitemaps'] = 0;
                $settings['preload_homepage'] = 0;
            } elseif ('recommended' === $preload_mode) {
                $settings['enable_preload'] = 1;
                $settings['enable_preload_queue'] = 1;
                $settings['preload_sitemaps'] = 1;
                $settings['preload_homepage'] = 1;
            } elseif ('homepage' === $preload_mode) {
                $settings['enable_preload'] = 1;
                $settings['enable_preload_queue'] = 1;
                $settings['preload_sitemaps'] = 0;
                $settings['preload_homepage'] = 1;
            } elseif ('manual' === $preload_mode) {
                $settings['enable_preload'] = 1;
            }
            self::remove_control($settings, 'preload_mode', $remove_controls);
        }

        if (array_key_exists('stale_cache_mode', $settings)) {
            $stale_mode = sanitize_key((string) $settings['stale_cache_mode']);
            if ('off' === $stale_mode) {
                $settings['enable_stale_cache'] = 0;
            } elseif (in_array($stale_mode, array('6', '12', '24', '48'), true)) {
                $settings['enable_stale_cache'] = 1;
                $settings['stale_cache_lifespan'] = absint($stale_mode);
            }
            self::remove_control($settings, 'stale_cache_mode', $remove_controls);
        }

        if (array_key_exists('heartbeat_interval_mode', $settings)) {
            $heartbeat_interval_mode = sanitize_key((string) $settings['heartbeat_interval_mode']);
            if ('custom' === $heartbeat_interval_mode) {
                $settings['heartbeat_frontend_frequency'] = 60;
                $settings['heartbeat_editor_frequency'] = 30;
                $settings['heartbeat_backend_frequency'] = 60;
                $settings['heartbeat_frequency'] = 60;
            } elseif (in_array($heartbeat_interval_mode, array('30', '60', '120'), true)) {
                $heartbeat_interval = absint($heartbeat_interval_mode);
                $settings['heartbeat_frontend_frequency'] = $heartbeat_interval;
                $settings['heartbeat_editor_frequency'] = $heartbeat_interval;
                $settings['heartbeat_backend_frequency'] = $heartbeat_interval;
                $settings['heartbeat_frequency'] = $heartbeat_interval;
            }
            self::remove_control($settings, 'heartbeat_interval_mode', $remove_controls);
        }

        return $settings;
    }

    /**
     * Remove a UX-only control key when the caller is normalizing stored settings.
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
