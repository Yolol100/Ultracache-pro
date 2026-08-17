<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Options_Lifecycle_Core_Trait {
    public static function snapshot_option_key() {
        return 'ucp_settings_snapshots';
    }
    public static function settings_snapshots() {
        $snapshots = get_option(self::snapshot_option_key(), array());
        return is_array($snapshots) ? $snapshots : array();
    }
    public static function create_settings_snapshot($settings = null, $context = 'manual') {
        if (!is_scalar($context) && null !== $context) {
            $context = 'manual';
        }
        $settings = is_array($settings) ? $settings : self::get_all();
        if (empty($settings)) {
            return '';
        }
        $snapshots = self::settings_snapshots();
        $id = gmdate('YmdHis') . '-' . wp_generate_password(6, false, false);
        array_unshift($snapshots, array(
            'id' => $id,
            'created_at' => gmdate('c'),
            'context' => sanitize_key((string) $context),
            'settings' => self::settings_for_export($settings),
        ));
        $snapshots = array_slice($snapshots, 0, 5);
        $updated = update_option(self::snapshot_option_key(), $snapshots, false);
        if (!$updated) {
            $stored = self::settings_snapshots();
            $persisted = false;
            foreach ($stored as $snapshot) {
                if (isset($snapshot['id']) && is_scalar($snapshot['id']) && '' !== (string) $snapshot['id'] && hash_equals((string) $snapshot['id'], $id)) {
                    $persisted = true;
                    break;
                }
            }
            if (!$persisted) {
                return '';
            }
        }
        return $id;
    }
    public static function restore_settings_snapshot($snapshot_id) {
        if (!is_scalar($snapshot_id) && null !== $snapshot_id) {
            $snapshot_id = '';
        }
        $snapshot_id = sanitize_text_field((string) $snapshot_id);
        foreach (self::settings_snapshots() as $snapshot) {
            if (isset($snapshot['id']) && is_scalar($snapshot['id']) && '' !== (string) $snapshot['id'] && hash_equals((string) $snapshot['id'], $snapshot_id) && !empty($snapshot['settings']) && is_array($snapshot['settings'])) {
                if (!self::create_settings_snapshot(self::get_all(), 'before_restore')) {
                    return false;
                }
                self::$suppress_auto_snapshot = true;
                try {
                    return (bool) self::update($snapshot['settings']);
                } finally {
                    self::$suppress_auto_snapshot = false;
                }
            }
        }
        return false;
    }
    public static function handle_option_updated($previous_settings, $new_settings) {
        self::invalidate_runtime_cache();
        if (!self::$suppress_auto_snapshot && is_array($previous_settings) && !empty($previous_settings) && $previous_settings !== $new_settings) {
            self::create_settings_snapshot($previous_settings, 'auto_save');
        }
        self::after_settings_save($new_settings, $previous_settings);
    }
    /**
     * Return existing settings whose value is embedded in cached HTML or its cache policy.
     *
     * @return array<int,string>
     */
    protected static function cache_affecting_setting_keys() {
        $keys = array(
            'enable_cache', 'cache_backend', 'cache_lifespan', 'enable_cache_policy_rules', 'cache_policy_rules', 'compat_profile_mode', 'cache_logged_in', 'cache_mobile_separately',
            'cache_query_strings', 'cache_query_string_inclusions', 'block_unknown_request_cookies',
            'serve_cache_to_shoppers', 'cache_vary_cookies', 'enable_stale_cache', 'stale_cache_lifespan',
            'enable_woocommerce_rules', 'exclude_urls', 'exclude_cookies', 'exclude_user_agents',
            'optimize_cart_fragments', 'limit_cart_fragments_to_woo', 'safe_cart_fragments_mode',
            'enable_html_minify', 'enable_html_attribute_quote_removal', 'enable_html_test_mode', 'enable_html_parser', 'remove_html_comments', 'html_exclude_urls', 'html_exclude_templates',
            'enable_brotli_precompression', 'enable_gzip_precompression', 'enable_cls_iframe_reservation',
            'cls_reserve_selectors', 'enable_expand_missing_srcset', 'enable_worker_lazyload',
            'enable_css_minify', 'enable_css_combine', 'css_exclusions', 'disabled_style_handles',
            'enable_js_minify', 'allow_experimental_js_minify', 'enable_js_combine', 'js_combine_exclusions',
            'js_exclusions', 'disabled_script_handles', 'conditional_style_unloads', 'conditional_script_unloads',
            'advanced_asset_rules', 'asset_rules', 'enable_asset_test_mode', 'enable_sensitive_asset_unload_override',
            'enable_delay_js', 'delay_js_timeout', 'delay_js_mode', 'delay_js_specified_scripts',
            'delay_js_disable_click_delay', 'delay_js_safe_mode', 'delay_js_temporary_safe_mode', 'delay_js_exclusions',
            'enable_delay_js_preload_delayed_scripts',
            'enable_defer_js_fallback', 'defer_all_js', 'enable_native_script_strategy', 'native_script_handles',
            'enable_remove_emojis', 'enable_disable_embeds', 'enable_prefetch_links', 'speculative_loading_mode',
            'enable_speculative_loading', 'speculation_mode', 'speculation_eagerness', 'speculation_exclusions',
            'enable_lazy_images', 'enable_lazy_iframes', 'enable_lazy_youtube_preview', 'lazyload_exclude_leading_images',
            'lazyload_exclusions', 'lazyload_parent_exclusions', 'enable_add_image_dimensions', 'preload_critical_images',
            'enable_preload', 'enable_image_optimization', 'enable_webp_generation', 'enable_avif_generation', 'image_quality',
            'preload_fonts', 'preconnect_domains', 'dns_prefetch_domains', 'enable_auto_resource_hints',
            'resource_hints_preconnect_limit', 'resource_hints_dns_limit', 'enable_auto_font_preloads',
            'lcp_profile_max_age_days', 'lcp_profile_allowed_hosts', 'enable_font_display_swap',
            'enable_font_unicode_ranges', 'font_unicode_ranges', 'enable_remove_query_strings',
            'remove_query_string_extensions', 'enable_lazy_render', 'lazy_render_selectors',
            'enable_self_host_third_party_assets', 'self_host_asset_domains', 'fetchpriority_rules',
            'lcp_profile_min_confidence', 'enable_disable_dashicons', 'enable_disable_jquery_migrate',
            'enable_move_module_scripts_footer',
            'css_delivery_mode', 'enable_used_css', 'enable_used_css_delivery', 'used_css_delivery_method',
            'css_artifact_scope', 'used_css_safelist', 'used_css_max_rules', 'critical_css_max_bytes',
            'css_artifact_min_bytes', 'css_profile_max_age_days', 'enable_critical_css', 'enable_cdn', 'cdn_cnames',
            'cdn_file_types', 'cdn_exclude', 'enable_edge_cache_headers', 'enable_cloudflare_apo_mode',
            'enable_early_hints_links', 'enable_edge_html_cache', 'edge_html_cache_ttl', 'edge_html_cache_stale',
            'edge_html_cache_tags',
            'enable_image_cdn', 'enable_image_cdn_transforms', 'enable_adaptive_image_srcset', 'image_cdn_base',
            'image_cdn_query', 'image_cdn_transform_provider', 'image_cdn_widths', 'enable_esi',
            'enable_lqip', 'enable_viewport_images', 'enable_local_gravatar', 'enable_local_youtube_thumbnails',
            'enable_direct_cache_htaccess', 'enable_disable_xmlrpc', 'enable_hide_wp_version', 'enable_remove_rsd_link',
            'enable_remove_shortlink', 'enable_disable_rss_feeds', 'enable_remove_rss_feed_links',
            'enable_disable_self_pingbacks', 'enable_disable_rest_api', 'enable_remove_rest_api_links',
            'enable_disable_google_maps', 'enable_disable_password_strength_meter', 'enable_disable_comments',
            'enable_remove_comment_links', 'enable_blank_favicon', 'enable_remove_global_styles',
            'enable_separate_block_styles', 'enable_disable_google_fonts', 'enable_lazyload_fade_in',
            'enable_lazyload_background_images', 'lazyload_threshold', 'disable_logged_in_optimizations',
            'enable_fragment_cache', 'fragment_cache_ttl', 'enable_rest_cache', 'rest_cache_ttl',
            'rest_cache_inclusions', 'rest_cache_exclusions', 'enable_cwv_monitoring', 'enable_rum', 'rum_sample_rate',
            'enable_local_google_fonts', 'enable_admin_bar', 'enable_hide_toolbar_menu'
        );
        return array_values(array_unique(array_map('sanitize_key', (array) apply_filters('ucp_cache_affecting_setting_keys', $keys))));
    }
    /**
     * Clear generated output once when a saved setting changes cacheable HTML or cache policy.
     *
     * @param array $new_settings      Normalized new settings.
     * @param array $previous_settings Previous normalized settings.
     * @return array<int,string> Changed cache-affecting keys.
     */
    protected static function invalidate_cache_after_settings_change($new_settings, $previous_settings) {
        $changed = array();
        foreach (self::cache_affecting_setting_keys() as $key) {
            $before = array_key_exists($key, $previous_settings) ? $previous_settings[$key] : null;
            $after = array_key_exists($key, $new_settings) ? $new_settings[$key] : null;
            if ($before !== $after) {
                $changed[] = $key;
            }
        }
        if (!empty($changed) && class_exists('UCP_Cache')) {
            UCP_Cache::clear_all();
            do_action('ucp_settings_cache_invalidated', $changed, $new_settings, $previous_settings);
        }
        return $changed;
    }
    public static function after_settings_save($new_settings, $previous_settings = array()) {
        $new_settings = self::normalize($new_settings, $previous_settings);
        $previous_settings = wp_parse_args((array) $previous_settings, self::defaults());

        UCP_Helpers::maybe_write_browser_cache_rules();
        if (method_exists('UCP_Helpers', 'maybe_write_direct_cache_rules')) {
            UCP_Helpers::maybe_write_direct_cache_rules();
        }
        UCP_Helpers::write_dropin_config();
        if (!empty($new_settings['enable_cache'])) {
            UCP_Helpers::write_advanced_cache_stub();
        }
        if (empty($new_settings['enable_cache'])) {
            UCP_Helpers::remove_own_advanced_cache_stub();
        }
        $invalidated_keys = self::invalidate_cache_after_settings_change($new_settings, $previous_settings);
        if (
            array_intersect(array('enable_local_google_fonts', 'enable_font_unicode_ranges', 'font_unicode_ranges'), $invalidated_keys)
            && class_exists('UCP_Fonts')
            && method_exists('UCP_Fonts', 'clear_cache')
        ) {
            UCP_Fonts::clear_cache();
        }
        if (class_exists('UCP_Preload')) {
            UCP_Preload::sync_schedule($new_settings);
        }
        UCP_Jobs::sync_schedule($new_settings);
        if (class_exists('UCP_Health')) {
            UCP_Health::sync_schedule($new_settings);
        }
        if (class_exists('UCP_DB_Cleanup')) {
            UCP_DB_Cleanup::sync_schedule($new_settings);
        }
        if (class_exists('UCP_Logger')) {
            UCP_Logger::log(
                'info',
                'admin',
                'settings_saved',
                __('Instellingen zijn opgeslagen.', 'ultracache-pro'),
                array(
                    'ui_mode' => isset($new_settings['ui_mode']) ? $new_settings['ui_mode'] : 'simple',
                    'preset'  => isset($new_settings['active_preset']) ? $new_settings['active_preset'] : '',
                    'cache_invalidated' => !empty($invalidated_keys),
                    'cache_keys_changed' => array_slice($invalidated_keys, 0, 40),
                )
            );
        }
        do_action('ucp_after_settings_save', $new_settings, $previous_settings);
    }
    public static function validate_import_payload($decoded) {
        if (!is_array($decoded) || empty($decoded)) {
            return array();
        }
        $defaults = self::defaults();
        $allowed = array_intersect_key($decoded, $defaults);
        return is_array($allowed) ? $allowed : array();
    }
}
