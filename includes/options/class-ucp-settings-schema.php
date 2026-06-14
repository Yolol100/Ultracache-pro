<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP option names are intentionally preserved.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central metadata for UltraCache settings.
 *
 * This is intentionally additive: existing defaults, option keys and migration
 * behaviour stay unchanged, while normalization code can consume one shared
 * source of truth for type groups.
 */
final class UCP_Settings_Schema {
    /**
     * Return boolean option keys grouped by normalization area.
     *
     * @param string $group Schema group name.
     * @return array<int,string>
     */
    public static function boolean_keys($group = 'all') {
        $groups = self::boolean_key_groups();
        if ('all' === $group) {
            $all = array();
            foreach ($groups as $keys) {
                $all = array_merge($all, $keys);
            }
            return array_values(array_unique($all));
        }

        return isset($groups[$group]) ? $groups[$group] : array();
    }

    /**
     * @return array<string,array<int,string>>
     */
    public static function boolean_key_groups() {
        return apply_filters('ucp_settings_schema_boolean_groups', array(
            'system_write_and_assets' => array(
                'allow_wp_config_write',
                'allow_dropin_writes',
                'allow_dropin_takeover',
                'allow_browser_cache_rule_writes',
                'enable_stale_cache',
                'purge_on_extension_change',
                'purge_on_core_update',
                'purge_on_global_change',
                'css_artifact_rollback',
                'enable_html_test_mode',
                'autopilot_enabled',
                'preload_homepage',
            ),
            'media_performance' => array(
                'compatibility_mode',
                'woocommerce_safety_mode',
                'wp_rocket_style_defaults',
                'enable_admin_queue_runner',
                'show_advanced_options',
                'disable_logged_in_optimizations',
                'accessibility_mode',
                'clean_uninstall',
                'delay_js_disable_click_delay',
                'enable_lazy_images',
                'enable_lazy_iframes',
                'enable_lazy_youtube_preview',
                'enable_add_image_dimensions',
                'enable_image_optimization',
                'enable_image_cdn_transforms',
                'enable_adaptive_image_srcset',
                'enable_webp_generation',
                'enable_avif_generation',
                'enable_font_display_swap',
                'enable_font_unicode_ranges',
                'enable_remove_query_strings',
                'enable_light_preload_requests',
                'preload_pause_on_high_load',
                'enable_css_profiles',
                'enable_lazy_render',
                'enable_edge_html_cache',
                'edge_html_cache_tags',
                'enable_self_host_third_party_assets',
                'enable_disable_dashicons',
                'enable_disable_jquery_migrate',
                'enable_move_module_scripts_footer',
                'safe_settings_export',
            ),
            'hardening_and_ui' => array(
                'enable_disable_xmlrpc',
                'enable_hide_wp_version',
                'enable_remove_rsd_link',
                'enable_remove_shortlink',
                'enable_disable_rss_feeds',
                'enable_remove_rss_feed_links',
                'enable_disable_self_pingbacks',
                'enable_disable_rest_api',
                'enable_remove_rest_api_links',
                'enable_disable_google_maps',
                'enable_disable_password_strength_meter',
                'enable_disable_comments',
                'enable_remove_comment_links',
                'enable_blank_favicon',
                'enable_remove_global_styles',
                'enable_separate_block_styles',
                'enable_disable_google_fonts',
                'enable_hide_toolbar_menu',
                'enable_lazyload_fade_in',
                'enable_lazyload_background_images',
            ),
        ));
    }
}
