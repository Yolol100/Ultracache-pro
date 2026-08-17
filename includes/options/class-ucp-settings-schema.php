<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP option names are intentionally preserved.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical metadata registry for UltraCache settings.
 *
 * Defaults remain owned by UCP_Options_Default_Groups for backward compatibility.
 * This registry centralizes sanitizer types, constraints, secrets and enum values
 * so admin, REST, import/export and normalization layers consume the same keys.
 */
final class UCP_Settings_Schema {
    public static function boolean_keys($group = 'all') {
        if (!is_scalar($group) && null !== $group) {
            $group = 'all';
        }
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

    public static function boolean_key_groups() {
        $sanitizer_keys = self::checkbox_keys();
        return apply_filters('ucp_settings_schema_boolean_groups', array(
            'system_write_and_assets' => array(
                'allow_wp_config_write',
                'allow_dropin_writes',
                'allow_dropin_takeover',
                'allow_browser_cache_rule_writes',
                'enable_stale_cache',
                'enable_cache_policy_rules',
                'enable_cache_insights',
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
            'sanitizer' => $sanitizer_keys,
        ));
    }

    public static function checkbox_keys() {
        return array(

            'enable_cache', 'enable_cache_policy_rules', 'enable_cache_insights', 'cache_logged_in', 'cache_mobile_separately', 'cache_query_strings', 'block_unknown_request_cookies', 'serve_cache_to_shoppers', 'optimize_cart_fragments', 'limit_cart_fragments_to_woo', 'safe_cart_fragments_mode', 'enable_stale_cache', 'enable_woocommerce_rules', 'compatibility_mode', 'woocommerce_safety_mode',
            'enable_preload', 'preload_homepage', 'preload_sitemaps', 'enable_html_minify', 'enable_html_attribute_quote_removal', 'enable_html_test_mode', 'remove_html_comments', 'wp_rocket_style_defaults',
            'enable_css_minify', 'enable_css_combine', 'allow_experimental_js_minify', 'enable_js_minify', 'enable_js_combine', 'enable_delay_js', 'delay_js_safe_mode', 'delay_js_disable_click_delay',
            'enable_defer_js_fallback', 'defer_all_js', 'delay_js_temporary_safe_mode', 'delay_js_log_delayed_scripts', 'enable_delay_js_preload_delayed_scripts', 'enable_native_script_strategy', 'enable_remove_emojis', 'enable_disable_embeds', 'enable_prefetch_links',
            'enable_speculative_loading', 'show_advanced_options', 'disable_logged_in_optimizations', 'accessibility_mode', 'clean_uninstall', 'enable_font_display_swap', 'enable_lazy_images', 'enable_lazy_iframes', 'enable_lazy_youtube_preview', 'enable_add_image_dimensions', 'enable_image_optimization', 'enable_webp_generation', 'enable_avif_generation', 'enable_local_google_fonts', 'enable_cwv_monitoring', 'enable_fragment_cache', 'enable_rest_cache', 'enable_used_css', 'enable_used_css_delivery',
            'enable_critical_css', 'enable_css_queue', 'enable_remote_css_render', 'css_artifact_rollback', 'enable_cdn', 'browser_cache_headers', 'enable_remove_query_strings',
            'enable_heartbeat_control', 'enable_db_cleanup', 'db_cleanup_post_revisions', 'db_cleanup_auto_drafts', 'db_cleanup_drafts', 'db_cleanup_expired_transients', 'db_cleanup_all_transients',
            'db_cleanup_spam_comments', 'db_cleanup_trashed_comments', 'db_cleanup_trashed_posts', 'db_cleanup_optimize_tables', 'db_cleanup_optimize_all_tables',
            'db_cleanup_wc_sessions', 'enable_cloud', 'cloud_pull_used_css', 'cloud_pull_critical_css', 'enable_edge_cache_headers',
            'enable_cloudflare_apo_mode', 'enable_early_hints_links', 'enable_edge_html_cache', 'edge_html_cache_tags', 'enable_admin_bar', 'testing_mode', 'enable_asset_test_mode', 'enable_asset_manager_snapshot', 'purge_on_post_update', 'purge_on_comment',
            'purge_on_theme_switch', 'purge_on_extension_change', 'purge_on_core_update', 'purge_on_global_change', 'enable_cache_tags', 'enable_object_cache_support', 'object_cache_fail_safe', 'enable_diagnostics', 'enable_logs', 'enable_dynamic_compatibility_rules', 'enable_runtime_debug_headers', 'enable_health_checks', 'enable_admin_queue_runner', 'autopilot_enabled', 'onboarding_completed',
            'enable_local_critical_css', 'enable_brotli_precompression', 'enable_gzip_precompression', 'enable_cls_iframe_reservation', 'enable_expand_missing_srcset', 'enable_worker_lazyload', 'enable_apcu_object_cache', 'enable_redis_object_cache', 'db_allow_myisam_innodb_convert', 'allow_wp_config_write', 'allow_dropin_writes', 'allow_browser_cache_rule_writes', 'enable_preload_queue', 'enable_targeted_purge', 'enable_light_preload_requests', 'enable_lazy_render', 'enable_self_host_third_party_assets', 'enable_disable_dashicons', 'enable_disable_jquery_migrate', 'enable_move_module_scripts_footer', 'safe_settings_export', 'enable_disable_xmlrpc', 'enable_hide_wp_version', 'enable_remove_rsd_link', 'enable_remove_shortlink', 'enable_disable_rss_feeds', 'enable_remove_rss_feed_links', 'enable_disable_self_pingbacks', 'enable_disable_rest_api', 'enable_remove_rest_api_links', 'enable_disable_google_maps', 'enable_disable_password_strength_meter', 'allow_dropin_takeover', 'enable_disable_comments', 'enable_remove_comment_links', 'enable_blank_favicon', 'enable_remove_global_styles', 'enable_separate_block_styles', 'enable_disable_google_fonts', 'enable_hide_toolbar_menu', 'enable_lazyload_fade_in', 'enable_lazyload_background_images', 'enable_auto_resource_hints', 'enable_auto_font_preloads', 'enable_css_profiles', 'preload_pause_on_high_load', 'enable_sensitive_asset_unload_override',
            // Compatibility modules introduced in earlier 11.x releases.
            'enable_headless_renderer', 'enable_direct_cache_htaccess', 'enable_async_image_optimization', 'enable_image_cdn', 'enable_image_cdn_transforms', 'enable_adaptive_image_srcset', 'enable_esi', 'enable_compat_updates',
            'enable_lqip', 'enable_rum', 'enable_viewport_images', 'enable_local_gravatar', 'enable_local_youtube_thumbnails', 'enable_asset_inspector', 'enable_font_unicode_ranges',
            'enable_host_cache_purge', 'enable_html_parser'
        );
    }

    public static function number_keys() {
        return array(

            'cache_lifespan', 'stale_cache_lifespan', 'preload_delay_ms', 'delay_js_timeout', 'lazyload_exclude_leading_images', 'preload_critical_images', 'used_css_max_rules', 'critical_css_max_bytes', 'css_artifact_min_bytes', 'css_artifact_retry_limit',
            'cache_control_max_age', 'heartbeat_frequency', 'heartbeat_frontend_frequency', 'heartbeat_editor_frequency', 'heartbeat_backend_frequency', 'db_keep_post_revisions', 'job_batch_size', 'job_max_attempts', 'job_lock_ttl', 'log_retention_days', 'diagnostics_retention_days', 'job_retention_days', 'cache_insights_sample_rate', 'cache_insights_retention_days',
            'preload_batch_size', 'preload_max_urls', 'preload_max_server_load', 'headless_renderer_timeout', 'headless_renderer_max_response_bytes', 'preload_menu_urls_limit', 'preload_recent_purge_limit', 'css_profile_max_age_days', 'lcp_profile_min_confidence', 'lcp_profile_max_age_days', 'image_quality', 'fragment_cache_ttl', 'rest_cache_ttl', 'autosave_interval', 'lazyload_threshold', 'resource_hints_preconnect_limit', 'resource_hints_dns_limit', 'edge_html_cache_ttl', 'edge_html_cache_stale',
            'used_css_auto_refresh_days', 'rum_sample_rate', 'cwv_timeseries_retention_days'
        );
    }

    public static function textarea_modes() {
        return array(

            'exclude_urls' => 'path',
            'exclude_cookies' => 'fragment',
            'cache_vary_cookies' => 'fragment',
            'exclude_user_agents' => 'fragment',
            'always_purge_urls' => 'path',
            'preload_exclude_urls' => 'path',
            'cache_query_string_inclusions' => 'query_key_pattern',
            'remove_query_string_extensions' => 'extension_list',
            'css_exclusions' => 'fragment',
            'disabled_style_handles' => 'fragment',
            'conditional_style_unloads' => 'fragment',
            'js_exclusions' => 'fragment',
            'disabled_script_handles' => 'fragment',
            'conditional_script_unloads' => 'fragment',
            'advanced_asset_rules' => 'fragment',
            'delay_js_exclusions' => 'fragment',
            'delay_js_specified_scripts' => 'fragment',
            'native_script_handles' => 'fragment',
            'speculation_exclusions' => 'path',
            'preload_fonts' => 'urlish',
            'preconnect_domains' => 'urlish',
            'dns_prefetch_domains' => 'domain',
            'lazyload_exclusions' => 'fragment',
            'lazyload_parent_exclusions' => 'selector',
            'lazy_render_selectors' => 'selector',
            'cls_reserve_selectors' => 'selector',
            'js_combine_exclusions' => 'path',
            'self_host_asset_domains' => 'domain',
            'fetchpriority_rules' => 'selector',
            'used_css_safelist' => 'selector',
            'rest_cache_inclusions' => 'path',
            'rest_cache_exclusions' => 'path',
            'cdn_cnames' => 'domain',
            'lcp_profile_allowed_hosts' => 'domain',
            'cdn_exclude' => 'fragment',
            'html_exclude_urls' => 'path',
            'html_exclude_templates' => 'fragment',
            'image_cdn_widths' => 'fragment',
            'cache_policy_rules' => 'fragment',
        );
    }

    public static function text_keys() {
        return array(

            'ui_mode', 'active_preset', 'cache_backend', 'css_delivery_mode', 'used_css_delivery_method', 'css_artifact_scope', 'delay_js_mode', 'speculative_loading_mode', 'speculation_mode', 'speculation_eagerness', 'cache_refresh_interval', 'db_cleanup_frequency', 'preload_content_scope', 'cloud_endpoint', 'cloud_site_id',
            'cloudflare_zone_id', 'onboarding_site_type', 'onboarding_goal', 'delay_js_presets', 'cdn_file_types', 'heartbeat_frontend_behavior', 'heartbeat_editor_behavior', 'heartbeat_backend_behavior', 'css_artifact_retry_backoff',
            'headless_renderer_endpoint', 'image_cdn_base', 'image_cdn_query', 'compat_profile_mode', 'image_cdn_transform_provider', 'font_unicode_ranges', 'cdn_provider', 'bunny_pull_zone_id', 'compat_update_url', 'cdn_purge_webhook'
        );
    }

    public static function secret_keys() {
        return array(
'cloud_api_key', 'cloudflare_api_token', 'secret_cache_key', 'css_cache_key', 'js_cache_key', 'headless_renderer_token', 'bunny_api_key', 'cdn_purge_webhook_token'
        );
    }

    public static function public_https_endpoint_keys() {
        return array(
            'cloud_endpoint',
            'headless_renderer_endpoint',
            'image_cdn_base',
            'compat_update_url',
            'cdn_purge_webhook',
        );
    }

    public static function integer_constraints() {
        return array(
            'cache_lifespan' => array('min' => 0, 'max' => 720, 'default' => 10),
            'cache_insights_sample_rate' => array('min' => 1, 'max' => 100, 'default' => 1),
            'cache_insights_retention_days' => array('min' => 1, 'max' => 30, 'default' => 7),
            'cache_control_max_age' => array('min' => MINUTE_IN_SECONDS, 'default' => 31536000),
            'preload_delay_ms' => array('min' => 0, 'max' => 5000, 'default' => 500),
            'preload_batch_size' => array('min' => 1, 'max' => 100, 'default' => 10),
            'preload_max_urls' => array('min' => 1, 'max' => 2000, 'default' => 200),
            'delay_js_timeout' => array('min' => 1, 'max' => 15, 'default' => 5),
            'lazyload_exclude_leading_images' => array('min' => 0, 'max' => 5, 'default' => 4),
            'preload_critical_images' => array('min' => 0, 'max' => 3, 'default' => 2),
            'resource_hints_preconnect_limit' => array('min' => 0, 'max' => 4, 'default' => 2),
            'resource_hints_dns_limit' => array('min' => 0, 'max' => 12, 'default' => 8),
            'autosave_interval' => array('min' => 15, 'max' => 600, 'default' => 60),
            'lazyload_threshold' => array('min' => 0, 'max' => 1000, 'default' => 0),
            'used_css_max_rules' => array('min' => 250, 'max' => 5000, 'default' => 2800),
            'critical_css_max_bytes' => array('min' => 2000, 'max' => 50000, 'default' => 12000),
            'css_artifact_min_bytes' => array('min' => 50, 'max' => 5000, 'default' => 200),
            'css_artifact_retry_limit' => array('min' => 1, 'max' => 10, 'default' => 3),
            'rum_sample_rate' => array('min' => 1, 'max' => 100, 'default' => 10),
            'used_css_auto_refresh_days' => array('min' => 0, 'max' => 365, 'default' => 30),
        );
    }

    public static function enum_constraints() {
        return array(
            'compat_profile_mode' => array('allowed' => array('auto', 'off'), 'default' => 'auto'),
            'cache_backend' => array('allowed' => array('auto', 'disk', 'litespeed'), 'default' => 'auto'),
            'delay_js_mode' => array('allowed' => array('specified', 'all'), 'default' => 'specified', 'source' => 'input'),
            'css_delivery_mode' => array('allowed' => array('none', 'remove_unused', 'async'), 'default' => 'none', 'source' => 'input'),
            'used_css_delivery_method' => array('allowed' => array('inline', 'file'), 'default' => 'file', 'source' => 'input'),
            'css_artifact_scope' => array('allowed' => array('url', 'template'), 'default' => 'url', 'source' => 'input'),
            'css_artifact_retry_backoff' => array('allowed' => array('none', 'linear', 'exponential'), 'default' => 'exponential', 'source' => 'input'),
            'cdn_file_types' => array('allowed' => array('all', 'css_js', 'images'), 'default' => 'all', 'source' => 'input'),
            'heartbeat_frontend_behavior' => array('allowed' => array('keep', 'reduce', 'disable'), 'default' => 'reduce', 'source' => 'input'),
            'heartbeat_editor_behavior' => array('allowed' => array('keep', 'reduce', 'disable'), 'default' => 'reduce', 'source' => 'input'),
            'heartbeat_backend_behavior' => array('allowed' => array('keep', 'reduce', 'disable'), 'default' => 'reduce', 'source' => 'input'),
            'cdn_provider' => array('allowed' => array('none', 'cloudflare', 'bunny', 'generic'), 'default' => 'none'),
            'image_cdn_transform_provider' => array('allowed' => array('auto', 'bunny', 'cloudflare', 'generic'), 'default' => 'auto'),
            'font_unicode_ranges' => array('allowed' => array('latin', 'latin-ext', 'latin-plus-ext'), 'default' => 'latin'),
            'speculative_loading_mode' => array('allowed' => array('core', 'enhanced', 'prerender', 'off'), 'default' => 'core'),
        );
    }

    public static function field_type($key) {
        if (!is_scalar($key) && null !== $key) {
            $key = '';
        }
        $key = (string) $key;
        if (in_array($key, self::checkbox_keys(), true)) {
            return 'boolean';
        }
        if (in_array($key, self::number_keys(), true)) {
            return 'integer';
        }
        if (array_key_exists($key, self::textarea_modes())) {
            return 'textarea';
        }
        if (in_array($key, self::secret_keys(), true)) {
            return 'secret';
        }
        if (in_array($key, self::text_keys(), true)) {
            return 'text';
        }
        return 'mixed';
    }

    public static function definitions() {
        $defaults = class_exists('UCP_Options_Default_Groups') ? UCP_Options_Default_Groups::defaults() : array();
        $integer_constraints = self::integer_constraints();
        $enum_constraints = self::enum_constraints();
        $textarea_modes = self::textarea_modes();
        $secret_keys = self::secret_keys();
        $definitions = array();

        foreach ($defaults as $key => $default) {
            $definitions[$key] = array(
                'type' => self::field_type($key),
                'default' => $default,
                'secret' => in_array($key, $secret_keys, true),
            );
            if (isset($integer_constraints[$key])) {
                $definitions[$key]['constraints'] = $integer_constraints[$key];
            }
            if (isset($enum_constraints[$key])) {
                $definitions[$key]['enum'] = $enum_constraints[$key]['allowed'];
            }
            if (isset($textarea_modes[$key])) {
                $definitions[$key]['sanitize_mode'] = $textarea_modes[$key];
            }
        }

        return $definitions;
    }

    public static function definition($key) {
        if (!is_scalar($key) && null !== $key) {
            $key = '';
        }
        $definitions = self::definitions();
        return isset($definitions[$key]) ? $definitions[$key] : array();
    }
}
