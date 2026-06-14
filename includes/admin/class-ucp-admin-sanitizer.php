<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Sanitizer {
    protected static function sanitize_multiline($value, $mode = 'fragment') {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $clean = array();

        foreach ((array) $lines as $line) {
            $line = wp_unslash((string) $line);
            $line = wp_strip_all_tags($line, false);
            $line = preg_replace('/[[:cntrl:]]+/', '', $line);
            $line = trim((string) $line);
            if ('' === $line) {
                continue;
            }

            switch ($mode) {
                case 'key':
                    $line = sanitize_key($line);
                    break;
                case 'query_key_pattern':
                    $line = strtolower((string) $line);
                    $line = preg_replace('/[^a-z0-9_\-*]/', '', $line);
                    if (false !== strpos($line, '*')) {
                        $prefix = strtok($line, '*');
                        $line = (false !== $prefix && '' !== $prefix) ? $prefix . '*' : '';
                    }
                    break;
                case 'domain':
                    $line = strtolower($line);
                    $line = preg_replace('#^https?://#i', '', $line);
                    $line = preg_replace('/[\?#].*$/', '', $line);
                    $line = preg_replace('#/.*$#', '', $line);
                    $line = preg_replace('/:\d+$/', '', $line);
                    $line = preg_replace('/[^a-z0-9.-]/', '', $line);
                    $line = trim($line, "/ ");
                    break;
                case 'extension_list':
                    $line = strtolower((string) $line);
                    $line = preg_replace('/[^a-z0-9]/', '', $line);
                    break;
                case 'urlish':
                    $line = esc_url_raw($line);
                    break;
                case 'selector':
                    $line = preg_replace('/\s+/', ' ', $line);
                    break;
                case 'path':
                case 'fragment':
                default:
                    $line = preg_replace('/\s+/', ' ', $line);
                    break;
            }

            if ('' === $line) {
                continue;
            }

            if (strlen($line) > 200) {
                $line = substr($line, 0, 200);
            }
            $clean[] = $line;
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    protected static function apply_checkbox_fields($output, $input, $current, $fields) {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = !empty($current[$field]) ? 1 : 0;
                continue;
            }
            $value = $input[$field];
            if (is_array($value)) {
                $value = end($value);
            }
            $output[$field] = empty($value) ? 0 : 1;
        }
        return $output;
    }

    protected static function apply_number_fields($output, $input, $current, $defaults, $fields) {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = isset($current[$field]) ? absint($current[$field]) : (isset($defaults[$field]) ? $defaults[$field] : 0);
                continue;
            }
            $output[$field] = absint($input[$field]);
        }
        return $output;
    }

    protected static function apply_textarea_fields($output, $input, $current, $modes) {
        foreach ($modes as $field => $mode) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = isset($current[$field]) ? (string) $current[$field] : '';
                continue;
            }
            $output[$field] = self::sanitize_multiline($input[$field], $mode);
        }
        return $output;
    }

    protected static function apply_text_fields($output, $input, $current, $fields) {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = isset($current[$field]) ? (string) $current[$field] : '';
                continue;
            }
            if ('delay_js_presets' === $field && is_array($input[$field])) {
                $items = array_filter(array_map('sanitize_key', (array) $input[$field]));
                $output[$field] = implode(',', array_values(array_unique($items)));
                continue;
            }
            $output[$field] = sanitize_text_field($input[$field]);
        }
        return $output;
    }

    protected static function apply_secret_fields($output, $input, $current, $fields) {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = isset($current[$field]) ? (string) $current[$field] : '';
                continue;
            }
            $value = is_scalar($input[$field]) ? trim((string) wp_unslash($input[$field])) : '';
            if (class_exists('UCP_Options') && method_exists('UCP_Options', 'is_masked_secret_value') && UCP_Options::is_masked_secret_value($value)) {
                $output[$field] = isset($current[$field]) ? (string) $current[$field] : '';
                continue;
            }
            $value = wp_strip_all_tags($value, false);
            $value = preg_replace('/[[:cntrl:]]+/', '', $value);
            $output[$field] = substr((string) $value, 0, 512);
        }
        return $output;
    }


    protected static function apply_virtual_control_aliases($input) {
        // UX-only combined controls. These are not stored as separate runtime flags; they map back
        // to the existing stable options before the normal sanitizer runs.
        $input = UCP_Settings_Combined_Controls::apply($input, false, false);

        if (array_key_exists('query_string_cache_mode', $input)) {
            $query_mode = sanitize_key((string) $input['query_string_cache_mode']);
            $input['cache_query_strings'] = 'allow_list' === $query_mode ? 1 : 0;
        }

        $input = self::apply_speculative_loading_alias($input);
        $input = self::apply_cdn_rewrite_alias($input);
        $input = self::apply_browser_cache_alias($input);
        $input = self::apply_heartbeat_interval_alias($input);

        return $input;
    }

    protected static function apply_speculative_loading_alias($input) {
        if (!array_key_exists('speculative_loading_mode', $input)) {
            return $input;
        }

        $spec_mode = sanitize_key((string) $input['speculative_loading_mode']);
        // Backward-compatible labels from 11.2.3 virtual controls.
        if ('prefetch_conservative' === $spec_mode || 'balanced' === $spec_mode) {
            $spec_mode = 'enhanced';
        } elseif ('prerender_conservative' === $spec_mode) {
            $spec_mode = 'prerender';
        }

        $profiles = array(
            'off' => array(0, 'prefetch', 'conservative'),
            'enhanced' => array(1, 'prefetch', 'conservative'),
            'prerender' => array(1, 'prerender', 'conservative'),
            'core' => array(0, 'prefetch', 'conservative'),
        );
        $spec_mode = isset($profiles[$spec_mode]) ? $spec_mode : 'core';

        $input['speculative_loading_mode'] = $spec_mode;
        $input['enable_speculative_loading'] = $profiles[$spec_mode][0];
        $input['speculation_mode'] = $profiles[$spec_mode][1];
        $input['speculation_eagerness'] = $profiles[$spec_mode][2];

        return $input;
    }

    protected static function apply_cdn_rewrite_alias($input) {
        if (!array_key_exists('cdn_rewrite_mode', $input)) {
            return $input;
        }

        $cdn_mode = sanitize_key((string) $input['cdn_rewrite_mode']);
        if ('off' === $cdn_mode) {
            $input['enable_cdn'] = 0;
            return $input;
        }

        $input['enable_cdn'] = 1;
        $input['cdn_file_types'] = in_array($cdn_mode, array('css_js','images','all'), true) ? $cdn_mode : 'all';
        return $input;
    }

    protected static function apply_browser_cache_alias($input) {
        if (!array_key_exists('browser_cache_mode', $input)) {
            return $input;
        }

        $browser_mode = sanitize_key((string) $input['browser_cache_mode']);
        if ('off' === $browser_mode) {
            $input['browser_cache_headers'] = 0;
            return $input;
        }

        $input['browser_cache_headers'] = 1;
        $max_age_by_mode = array(
            '30d' => 2592000,
            '180d' => 15552000,
            '365d' => 31536000,
        );
        if (isset($max_age_by_mode[$browser_mode])) {
            $input['cache_control_max_age'] = $max_age_by_mode[$browser_mode];
        }
        return $input;
    }

    protected static function apply_heartbeat_interval_alias($input) {
        if (!array_key_exists('heartbeat_interval_mode', $input)) {
            return $input;
        }

        $heartbeat_interval_mode = sanitize_key((string) $input['heartbeat_interval_mode']);
        if ('custom' === $heartbeat_interval_mode) {
            $input['heartbeat_frontend_frequency'] = 60;
            $input['heartbeat_editor_frequency'] = 30;
            $input['heartbeat_backend_frequency'] = 60;
            $input['heartbeat_frequency'] = 60;
            return $input;
        }

        if (in_array($heartbeat_interval_mode, array('30','60','120'), true)) {
            $heartbeat_interval = absint($heartbeat_interval_mode);
            $input['heartbeat_frontend_frequency'] = $heartbeat_interval;
            $input['heartbeat_editor_frequency'] = $heartbeat_interval;
            $input['heartbeat_backend_frequency'] = $heartbeat_interval;
            $input['heartbeat_frequency'] = $heartbeat_interval;
        }

        return $input;
    }

    protected static function apply_output_constraints($output, $input, $current) {
        $output['cache_lifespan'] = min(720, max(0, absint($output['cache_lifespan'])));
        $output['cache_backend'] = isset($output['cache_backend']) && in_array($output['cache_backend'], array('auto', 'disk', 'litespeed'), true) ? $output['cache_backend'] : (isset($current['cache_backend']) ? $current['cache_backend'] : 'auto');
        $output['preload_delay_ms'] = min(5000, max(0, absint($output['preload_delay_ms'])));
        $output['preload_batch_size'] = min(100, max(1, absint($output['preload_batch_size'])));
        $output['preload_max_urls'] = min(2000, max(1, absint($output['preload_max_urls'])));
        $output['delay_js_timeout'] = min(15, max(1, absint($output['delay_js_timeout'])));
        $output['lazyload_exclude_leading_images'] = min(5, max(0, absint(isset($output['lazyload_exclude_leading_images']) ? $output['lazyload_exclude_leading_images'] : 1)));
        $output['preload_critical_images'] = min(3, max(0, absint(isset($output['preload_critical_images']) ? $output['preload_critical_images'] : 0)));
        $output['resource_hints_preconnect_limit'] = min(4, max(0, absint(isset($output['resource_hints_preconnect_limit']) ? $output['resource_hints_preconnect_limit'] : 2)));
        $output['resource_hints_dns_limit'] = min(12, max(0, absint(isset($output['resource_hints_dns_limit']) ? $output['resource_hints_dns_limit'] : 8)));
        $output['autosave_interval'] = min(600, max(15, absint(isset($output['autosave_interval']) ? $output['autosave_interval'] : 60)));
        $output['lazyload_threshold'] = min(1000, max(0, absint(isset($output['lazyload_threshold']) ? $output['lazyload_threshold'] : 0)));
        $output['used_css_max_rules'] = min(5000, max(250, absint($output['used_css_max_rules'])));
        $output['critical_css_max_bytes'] = min(50000, max(2000, absint($output['critical_css_max_bytes'])));
        $output['css_artifact_min_bytes'] = min(5000, max(50, absint(isset($output['css_artifact_min_bytes']) ? $output['css_artifact_min_bytes'] : 200)));
        $output['css_artifact_retry_limit'] = min(10, max(1, absint(isset($output['css_artifact_retry_limit']) ? $output['css_artifact_retry_limit'] : 3)));
        $output['rum_sample_rate'] = min(100, max(1, absint(isset($output['rum_sample_rate']) ? $output['rum_sample_rate'] : 10)));
        $output['used_css_auto_refresh_days'] = min(365, max(0, absint(isset($output['used_css_auto_refresh_days']) ? $output['used_css_auto_refresh_days'] : 30)));

        $output['delay_js_mode'] = isset($input['delay_js_mode']) && in_array($input['delay_js_mode'], array('specified','all'), true) ? $input['delay_js_mode'] : 'specified';
        $output['css_delivery_mode'] = isset($input['css_delivery_mode']) && in_array($input['css_delivery_mode'], array('none','remove_unused','async'), true) ? $input['css_delivery_mode'] : (isset($current['css_delivery_mode']) ? $current['css_delivery_mode'] : 'none');
        $output['used_css_delivery_method'] = isset($input['used_css_delivery_method']) && in_array($input['used_css_delivery_method'], array('inline','file'), true) ? $input['used_css_delivery_method'] : (isset($current['used_css_delivery_method']) ? $current['used_css_delivery_method'] : 'file');
        $output['css_artifact_scope'] = isset($input['css_artifact_scope']) && in_array($input['css_artifact_scope'], array('url','template'), true) ? $input['css_artifact_scope'] : (isset($current['css_artifact_scope']) ? $current['css_artifact_scope'] : 'url');
        $output['cdn_file_types'] = isset($input['cdn_file_types']) && in_array($input['cdn_file_types'], array('all','css_js','images'), true) ? $input['cdn_file_types'] : (isset($current['cdn_file_types']) ? $current['cdn_file_types'] : 'all');

        foreach (array('heartbeat_frontend_behavior','heartbeat_editor_behavior','heartbeat_backend_behavior') as $heartbeat_behavior_key) {
            $output[$heartbeat_behavior_key] = isset($input[$heartbeat_behavior_key]) && in_array($input[$heartbeat_behavior_key], array('keep','reduce','disable'), true) ? $input[$heartbeat_behavior_key] : (isset($current[$heartbeat_behavior_key]) ? $current[$heartbeat_behavior_key] : 'reduce');
        }
        // The separate Heartbeat master toggle is now a legacy mirror. If all contexts are kept,
        // Heartbeat control is off; any reduced/disabled context activates it.
        $output['enable_heartbeat_control'] = (
            'keep' === $output['heartbeat_frontend_behavior']
            && 'keep' === $output['heartbeat_editor_behavior']
            && 'keep' === $output['heartbeat_backend_behavior']
        ) ? 0 : 1;

        $output['cdn_provider'] = isset($output['cdn_provider']) && in_array($output['cdn_provider'], array('none', 'cloudflare', 'bunny', 'generic'), true)
            ? $output['cdn_provider']
            : (isset($current['cdn_provider']) ? $current['cdn_provider'] : 'none');

        $output['image_cdn_transform_provider'] = isset($output['image_cdn_transform_provider']) && in_array($output['image_cdn_transform_provider'], array('auto', 'bunny', 'cloudflare', 'generic'), true)
            ? $output['image_cdn_transform_provider']
            : (isset($current['image_cdn_transform_provider']) ? $current['image_cdn_transform_provider'] : 'auto');

        $output['font_unicode_ranges'] = isset($output['font_unicode_ranges']) && in_array($output['font_unicode_ranges'], array('latin', 'latin-ext', 'latin-plus-ext'), true)
            ? $output['font_unicode_ranges']
            : (isset($current['font_unicode_ranges']) ? $current['font_unicode_ranges'] : 'latin');
        $output['speculative_loading_mode'] = isset($output['speculative_loading_mode']) && in_array($output['speculative_loading_mode'], array('core', 'enhanced', 'prerender', 'off'), true)
            ? $output['speculative_loading_mode']
            : (isset($current['speculative_loading_mode']) && in_array($current['speculative_loading_mode'], array('core', 'enhanced', 'prerender', 'off'), true) ? $current['speculative_loading_mode'] : 'core');

        if ('off' === $output['speculative_loading_mode'] || 'core' === $output['speculative_loading_mode']) {
            $output['enable_speculative_loading'] = 0;
            $output['speculation_mode'] = 'prefetch';
            $output['speculation_eagerness'] = 'conservative';
        } elseif ('prerender' === $output['speculative_loading_mode']) {
            $output['enable_speculative_loading'] = 1;
            $output['speculation_mode'] = 'prerender';
            $output['speculation_eagerness'] = 'conservative';
        } else {
            $output['enable_speculative_loading'] = 1;
            $output['speculation_mode'] = 'prefetch';
            $output['speculation_eagerness'] = 'conservative';
        }

        return $output;
    }

    public static function sanitize($input) {
        $defaults = UCP_Options::defaults();
        $current  = UCP_Options::get_all();
        $output   = $current;
        $input    = is_array($input) ? $input : array();

        $input = self::apply_virtual_control_aliases($input);

        $checkbox_fields = array(
            'enable_cache', 'cache_logged_in', 'cache_mobile_separately', 'cache_query_strings', 'block_unknown_request_cookies', 'serve_cache_to_shoppers', 'optimize_cart_fragments', 'limit_cart_fragments_to_woo', 'enable_stale_cache', 'enable_woocommerce_rules', 'compatibility_mode', 'woocommerce_safety_mode',
            'enable_preload', 'preload_homepage', 'preload_sitemaps', 'enable_html_minify', 'enable_html_test_mode', 'remove_html_comments', 'wp_rocket_style_defaults',
            'enable_css_minify', 'enable_css_combine', 'allow_experimental_js_minify', 'enable_js_minify', 'enable_js_combine', 'enable_delay_js', 'delay_js_safe_mode', 'delay_js_disable_click_delay',
            'enable_defer_js_fallback', 'defer_all_js', 'delay_js_temporary_safe_mode', 'delay_js_log_delayed_scripts', 'enable_delay_js_preload_delayed_scripts', 'enable_native_script_strategy', 'enable_remove_emojis', 'enable_disable_embeds', 'enable_prefetch_links',
            'enable_speculative_loading', 'show_advanced_options', 'disable_logged_in_optimizations', 'accessibility_mode', 'clean_uninstall', 'enable_font_display_swap', 'enable_lazy_images', 'enable_lazy_iframes', 'enable_lazy_youtube_preview', 'enable_add_image_dimensions', 'enable_image_optimization', 'enable_webp_generation', 'enable_avif_generation', 'enable_local_google_fonts', 'enable_cwv_monitoring', 'enable_fragment_cache', 'enable_rest_cache', 'enable_used_css', 'enable_used_css_delivery',
            'enable_critical_css', 'enable_css_queue', 'enable_remote_css_render', 'css_artifact_rollback', 'enable_cdn', 'browser_cache_headers', 'enable_remove_query_strings',
            'enable_heartbeat_control', 'enable_db_cleanup', 'db_cleanup_post_revisions', 'db_cleanup_auto_drafts', 'db_cleanup_drafts', 'db_cleanup_expired_transients', 'db_cleanup_all_transients',
            'db_cleanup_spam_comments', 'db_cleanup_trashed_comments', 'db_cleanup_trashed_posts', 'db_cleanup_optimize_tables', 'db_cleanup_optimize_all_tables',
            'db_cleanup_wc_sessions', 'enable_cloud', 'cloud_pull_used_css', 'cloud_pull_critical_css', 'enable_edge_cache_headers',
            'enable_cloudflare_apo_mode', 'enable_early_hints_links', 'enable_edge_html_cache', 'edge_html_cache_tags', 'enable_admin_bar', 'enable_asset_test_mode', 'enable_asset_manager_snapshot', 'purge_on_post_update', 'purge_on_comment',
            'purge_on_theme_switch', 'purge_on_extension_change', 'purge_on_core_update', 'purge_on_global_change', 'enable_cache_tags', 'enable_object_cache_support', 'object_cache_fail_safe', 'enable_diagnostics', 'enable_logs', 'enable_dynamic_compatibility_rules', 'enable_runtime_debug_headers', 'enable_health_checks', 'enable_admin_queue_runner', 'autopilot_enabled', 'onboarding_completed',
            'enable_local_critical_css', 'enable_brotli_precompression', 'enable_gzip_precompression', 'enable_cls_iframe_reservation', 'enable_expand_missing_srcset', 'enable_worker_lazyload', 'enable_apcu_object_cache', 'enable_redis_object_cache', 'db_allow_myisam_innodb_convert', 'allow_wp_config_write', 'allow_dropin_writes', 'allow_browser_cache_rule_writes', 'enable_preload_queue', 'enable_targeted_purge', 'enable_light_preload_requests', 'enable_lazy_render', 'enable_self_host_third_party_assets', 'enable_disable_dashicons', 'enable_disable_jquery_migrate', 'enable_move_module_scripts_footer', 'safe_settings_export', 'enable_disable_xmlrpc', 'enable_hide_wp_version', 'enable_remove_rsd_link', 'enable_remove_shortlink', 'enable_disable_rss_feeds', 'enable_remove_rss_feed_links', 'enable_disable_self_pingbacks', 'enable_disable_rest_api', 'enable_remove_rest_api_links', 'enable_disable_google_maps', 'enable_disable_password_strength_meter', 'allow_dropin_takeover', 'enable_disable_comments', 'enable_remove_comment_links', 'enable_blank_favicon', 'enable_remove_global_styles', 'enable_separate_block_styles', 'enable_disable_google_fonts', 'enable_hide_toolbar_menu', 'enable_lazyload_fade_in', 'enable_lazyload_background_images', 'enable_auto_resource_hints', 'enable_auto_font_preloads', 'enable_css_profiles', 'preload_pause_on_high_load', 'enable_sensitive_asset_unload_override',
            // Compatibility modules introduced in earlier 11.x releases.
            'enable_headless_renderer', 'enable_direct_cache_htaccess', 'enable_async_image_optimization', 'enable_image_cdn', 'enable_image_cdn_transforms', 'enable_adaptive_image_srcset', 'enable_esi', 'enable_compat_updates',
            'enable_lqip', 'enable_rum', 'enable_viewport_images', 'enable_local_gravatar', 'enable_local_youtube_thumbnails', 'enable_asset_inspector', 'enable_font_unicode_ranges',
            'enable_host_cache_purge', 'enable_html_parser'
        );

        $number_fields = array(
            'cache_lifespan', 'stale_cache_lifespan', 'preload_delay_ms', 'delay_js_timeout', 'lazyload_exclude_leading_images', 'preload_critical_images', 'used_css_max_rules', 'critical_css_max_bytes', 'css_artifact_min_bytes', 'css_artifact_retry_limit',
            'cache_control_max_age', 'heartbeat_frequency', 'heartbeat_frontend_frequency', 'heartbeat_editor_frequency', 'heartbeat_backend_frequency', 'db_keep_post_revisions', 'job_batch_size', 'job_max_attempts', 'job_lock_ttl', 'log_retention_days', 'diagnostics_retention_days', 'job_retention_days',
            'preload_batch_size', 'preload_max_urls', 'preload_max_server_load', 'headless_renderer_timeout', 'headless_renderer_max_response_bytes', 'preload_menu_urls_limit', 'preload_recent_purge_limit', 'css_profile_max_age_days', 'lcp_profile_min_confidence', 'lcp_profile_max_age_days', 'image_quality', 'fragment_cache_ttl', 'rest_cache_ttl', 'autosave_interval', 'lazyload_threshold', 'resource_hints_preconnect_limit', 'resource_hints_dns_limit', 'edge_html_cache_ttl', 'edge_html_cache_stale',
            'used_css_auto_refresh_days', 'rum_sample_rate'
        );

        $textarea_modes = array(
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
        );

        $text_fields = array(
            'ui_mode', 'active_preset', 'cache_backend', 'css_delivery_mode', 'used_css_delivery_method', 'css_artifact_scope', 'delay_js_mode', 'speculative_loading_mode', 'speculation_mode', 'speculation_eagerness', 'cache_refresh_interval', 'db_cleanup_frequency', 'preload_content_scope', 'cloud_endpoint', 'cloud_site_id',
            'cloudflare_zone_id', 'onboarding_site_type', 'onboarding_goal', 'delay_js_presets', 'cdn_file_types', 'heartbeat_frontend_behavior', 'heartbeat_editor_behavior', 'heartbeat_backend_behavior', 'css_artifact_retry_backoff',
            'headless_renderer_endpoint', 'image_cdn_base', 'image_cdn_query', 'image_cdn_transform_provider', 'font_unicode_ranges', 'cdn_provider', 'bunny_pull_zone_id', 'compat_update_url', 'cdn_purge_webhook'
        );

        $secret_fields = array('cloud_api_key', 'cloudflare_api_token', 'secret_cache_key', 'css_cache_key', 'js_cache_key', 'headless_renderer_token', 'bunny_api_key', 'cdn_purge_webhook_token');

        $output = self::apply_checkbox_fields($output, $input, $current, $checkbox_fields);

        // Legacy 11.2.2 RUM toggle now maps to the existing CWV monitoring collector.
        if (!empty($output['enable_rum'])) {
            $output['enable_cwv_monitoring'] = 1;
        }
        // enable_async_image_optimization and enable_viewport_images default to 1 (see defaults
        // trait) and are part of $checkbox_fields, so apply_checkbox_fields() already preserves
        // the current value when the key is absent from $input. They must therefore NOT be force-
        // enabled here: doing so would silently re-enable them on every partial update/import/CLI
        // call that omits them, overriding an administrator who deliberately turned them off.

        $output = self::apply_number_fields($output, $input, $current, $defaults, $number_fields);

        $output = self::apply_textarea_fields($output, $input, $current, $textarea_modes);

        $output = self::apply_text_fields($output, $input, $current, $text_fields);

        $output = self::apply_secret_fields($output, $input, $current, $secret_fields);

        $output = self::apply_output_constraints($output, $input, $current);

        if (array_key_exists('asset_rules', $input)) {
            $output['asset_rules'] = UCP_Rule_Engine::sanitize_rules($input['asset_rules']);
        } else {
            $output['asset_rules'] = isset($current['asset_rules']) ? $current['asset_rules'] : UCP_Rule_Engine::default_rules();
        }

        $output = UCP_Options::normalize($output, $current);

        return $output;
    }
}
