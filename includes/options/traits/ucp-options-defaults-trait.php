<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Options_Defaults_Trait {
    protected static function random_key($length = 20) {
        if (function_exists('wp_generate_password')) {
            return wp_generate_password($length, false, false);
        }

        try {
            return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
        } catch (Exception $e) {
            return substr(md5(uniqid('ucp', true)), 0, $length);
        }
    }

    protected static function rocket_style_default_overrides() {
        $defaults = self::defaults();
        $keys = array(
            'enable_cache', 'cache_backend', 'cache_lifespan', 'cache_logged_in', 'cache_mobile_separately', 'cache_query_strings', 'cache_query_string_inclusions',
            'exclude_user_agents', 'always_purge_urls',
            'enable_woocommerce_rules', 'enable_preload', 'enable_preload_queue', 'preload_homepage', 'preload_sitemaps', 'preload_exclude_urls',
            'enable_css_minify', 'enable_js_minify', 'allow_experimental_js_minify', 'enable_css_combine', 'enable_js_combine', 'enable_delay_js',
            'delay_js_mode', 'delay_js_safe_mode', 'delay_js_disable_click_delay', 'enable_defer_js_fallback', 'defer_all_js',
            'enable_used_css', 'enable_used_css_delivery', 'enable_critical_css', 'enable_lazy_images', 'enable_lazy_iframes', 'enable_lazy_youtube_preview',
            'enable_prefetch_links', 'enable_speculative_loading', 'show_advanced_options', 'disable_logged_in_optimizations',
            'accessibility_mode', 'clean_uninstall', 'enable_font_display_swap', 'enable_remove_query_strings',
            'enable_light_preload_requests', 'preload_content_scope', 'cache_refresh_interval', 'enable_lazy_render', 'enable_self_host_third_party_assets',
            'lazy_render_selectors', 'enable_disable_dashicons', 'enable_disable_jquery_migrate',
            'enable_move_module_scripts_footer', 'safe_settings_export', 'enable_remove_emojis', 'enable_disable_embeds',
            'cdn_file_types', 'enable_heartbeat_control', 'heartbeat_frontend_behavior', 'heartbeat_editor_behavior', 'heartbeat_backend_behavior', 'browser_cache_headers', 'enable_db_cleanup', 'db_cleanup_frequency', 'db_cleanup_post_revisions', 'db_cleanup_auto_drafts', 'db_cleanup_drafts', 'enable_cdn',
            'enable_cloudflare_apo_mode', 'enable_edge_cache_headers', 'enable_cloud', 'enable_local_google_fonts',
            'enable_image_optimization', 'compatibility_mode', 'woocommerce_safety_mode', 'wp_rocket_style_defaults', 'enable_delay_js_preload_delayed_scripts',
            'enable_auto_resource_hints', 'enable_auto_font_preloads', 'resource_hints_preconnect_limit', 'resource_hints_dns_limit', 'enable_css_profiles', 'css_profile_max_age_days', 'lcp_profile_min_confidence', 'lcp_profile_max_age_days', 'lcp_profile_allowed_hosts', 'preload_pause_on_high_load', 'preload_max_server_load', 'preload_menu_urls_limit', 'preload_recent_purge_limit', 'enable_sensitive_asset_unload_override'
        );

        return array_intersect_key($defaults, array_flip($keys));
    }


    /**
     * Return WP Rocket-style automatic defaults that should stay managed by the plugin.
     *
     * These are intentionally conservative for webshops: safe baseline features are kept on,
     * while high-risk render-changing optimizations remain opt-in.
     *
     * @return array<string,mixed>
     */
    public static function automatic_managed_settings() {
        $settings = array(
            'wp_rocket_style_defaults'        => 1,
            'compatibility_mode'              => 1,
            'woocommerce_safety_mode'         => 1,
            'enable_cache'                    => 1,
            'cache_logged_in'                 => 0,
            'cache_mobile_separately'         => 1,
            'cache_query_strings'             => 0,
            'cache_query_string_inclusions'  => "lang\ncurrency\nv\norderby\nmin_price\nmax_price\nrating_filter\nfilter_*\nquery_type_*\n_paged\nproduct-page\nproduct-page-*",
            'enable_woocommerce_rules'        => 1,
            'serve_cache_to_shoppers'         => 0,
            'optimize_cart_fragments'         => 1,
            'limit_cart_fragments_to_woo'     => 0,
            'enable_preload'                  => 1,
            'enable_preload_queue'            => 1,
            'preload_homepage'                => 1,
            'preload_sitemaps'                => 1,
            'enable_css_minify'               => 1,
            'enable_css_combine'              => 0,
            'enable_js_combine'               => 0,
            'enable_delay_js'                 => 0,
            'delay_js_safe_mode'              => 1,
            'defer_all_js'                    => 0,
            'css_delivery_mode'               => 'none',
            'enable_used_css'                 => 0,
            'enable_used_css_delivery'        => 0,
            'enable_critical_css'             => 0,
            'enable_css_queue'                => 0,
            'enable_lazy_images'              => 1,
            'enable_lazy_iframes'             => 1,
            'enable_lazy_youtube_preview'     => 1,
            'enable_add_image_dimensions'     => 1,
            'enable_html_parser'              => 0,
            'enable_font_display_swap'        => 1,
            'enable_local_google_fonts'       => 1,
            'enable_remove_emojis'            => 1,
            'enable_disable_embeds'           => 1,
            'enable_prefetch_links'           => 1,
            'enable_auto_resource_hints'      => 1,
            'enable_auto_font_preloads'       => 1,
            'browser_cache_headers'           => 1,
            'cache_control_max_age'           => 31536000,
            'enable_gzip_precompression'      => 1,
            'enable_brotli_precompression'    => 1,
            'enable_heartbeat_control'        => 1,
            'heartbeat_frontend_behavior'     => 'reduce',
            'heartbeat_editor_behavior'       => 'reduce',
            'heartbeat_backend_behavior'      => 'reduce',
            'heartbeat_frontend_frequency'    => 60,
            'heartbeat_editor_frequency'      => 30,
            'heartbeat_backend_frequency'     => 60,
            'disable_logged_in_optimizations' => 1,
            'enable_rest_cache'               => 0,
            'enable_db_cleanup'               => 0,
            'db_cleanup_frequency'            => 'off',
            'allow_wp_config_write'           => 0,
            'allow_dropin_writes'             => 0,
            'allow_dropin_takeover'           => 0,
            'allow_browser_cache_rule_writes' => 0,
            'enable_diagnostics'              => 1,
            'enable_logs'                     => 1,
            'enable_health_checks'            => 1,
            'enable_admin_queue_runner'       => 1,
        );

        if (class_exists('UCP_Compat') && UCP_Compat::has_page_cache_conflict()) {
            $settings['enable_cache'] = 0;
            $settings['enable_preload'] = 0;
            $settings['enable_preload_queue'] = 0;
        }

        return apply_filters('ucp_automatic_managed_settings', $settings);
    }

    /**
     * Return setting keys that are managed automatically and hidden from the normal UI.
     *
     * @return array<int,string>
     */
    public static function automatic_managed_keys() {
        return array_values(array_unique(array_merge(
            array_keys(self::automatic_managed_settings()),
            array(
                'browser_cache_mode',
                'google_fonts_mode',
                'media_lazyload_mode',
                'preload_mode',
                'query_string_cache_mode',
                'delay_js_control'
            )
        )));
    }

    public static function defaults() {
        return UCP_Options_Default_Groups::defaults();
    }

}
