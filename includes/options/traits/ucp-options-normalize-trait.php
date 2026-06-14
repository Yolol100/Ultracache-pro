<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Options_Normalize_Trait {
    public static function get_all() {
        $raw = wp_parse_args(get_option(self::OPTION_KEY, array()), self::defaults());
        return self::normalize($raw, $raw);
    }

    public static function get($key, $default = null) {
        $options = self::get_all();
        return isset($options[$key]) ? $options[$key] : $default;
    }

    public static function sensitive_keys() {
        return array('cloud_api_key', 'cloudflare_api_token', 'secret_cache_key', 'css_cache_key', 'js_cache_key', 'headless_renderer_token', 'bunny_api_key', 'cdn_purge_webhook_token');
    }

    public static function is_sensitive_key($key) {
        return in_array((string) $key, self::sensitive_keys(), true);
    }

    public static function mask_secret_value($value) {
        $value = is_scalar($value) ? (string) $value : '';
        if ('' === $value) {
            return '';
        }
        $suffix = substr($value, -4);
        return '••••••••' . $suffix;
    }

    public static function is_masked_secret_value($value) {
        $value = is_scalar($value) ? (string) $value : '';
        return (bool) preg_match('/^(?:•{4,}|\*{4,}|x{4,})/i', $value);
    }


    protected static function normalize_self_host_asset_domains_value($value) {
        $domains = array();
        foreach (UCP_Helpers::normalize_multiline($value) as $domain) {
            $domain = strtolower(trim((string) preg_replace('/^https?:\/\//i', '', $domain)));
            $domain = preg_replace('/\/.*$/', '', $domain);
            if (preg_match('/^[a-z0-9.-]+$/', $domain) && false !== strpos($domain, '.')) {
                $domains[] = $domain;
            }
        }
        return implode("\n", array_values(array_unique($domains)));
    }

    protected static function normalize_fetchpriority_rules_value($value) {
        $rules = array();
        foreach (UCP_Helpers::normalize_multiline($value) as $rule) {
            $parts = array_map('trim', explode('|', (string) $rule));
            $selector = isset($parts[0]) ? sanitize_text_field($parts[0]) : '';
            $device = isset($parts[1]) ? sanitize_key($parts[1]) : 'all';
            $context = isset($parts[2]) ? sanitize_key($parts[2]) : 'all';
            $priority = isset($parts[3]) ? sanitize_key($parts[3]) : (in_array($context, array('high','low','auto'), true) ? $context : 'high');
            if (in_array($context, array('high','low','auto'), true)) {
                $context = 'all';
            }
            if ('' !== $selector && in_array($device, array('all','mobile','desktop'), true) && in_array($context, array('all','front_page','singular','archive','product','cart','checkout'), true) && in_array($priority, array('high','low','auto'), true)) {
                $rules[] = $selector . '|' . $device . '|' . $context . '|' . $priority;
            }
        }
        return implode("\n", array_values(array_unique($rules)));
    }

    protected static function normalize_structured_settings($settings) {
        if (isset($settings['self_host_asset_domains'])) {
            $settings['self_host_asset_domains'] = self::normalize_self_host_asset_domains_value($settings['self_host_asset_domains']);
        }
        if (isset($settings['fetchpriority_rules'])) {
            $settings['fetchpriority_rules'] = self::normalize_fetchpriority_rules_value($settings['fetchpriority_rules']);
        }
        return $settings;
    }

    protected static function normalize_internal_secret_keys($settings) {
        foreach (array('secret_cache_key', 'css_cache_key', 'js_cache_key') as $internal_key) {
            if (empty($settings[$internal_key])) {
                $settings[$internal_key] = self::random_key(20);
            } else {
                $settings[$internal_key] = sanitize_key((string) $settings[$internal_key]);
                if ('' === $settings[$internal_key]) {
                    $settings[$internal_key] = self::random_key(20);
                }
            }
        }
        return $settings;
    }

    public static function redact_sensitive_settings($settings, $mode = 'mask') {
        $settings = is_array($settings) ? $settings : array();
        foreach (self::sensitive_keys() as $key) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }
            if ('remove' === $mode) {
                unset($settings[$key]);
                continue;
            }
            $settings[$key] = self::mask_secret_value($settings[$key]);
        }
        return $settings;
    }

    public static function settings_for_export($settings = null) {
        $settings = is_array($settings) ? $settings : self::get_all();
        $settings = self::redact_sensitive_settings($settings, 'remove');

        foreach (array_keys($settings) as $key) {
            if (preg_match('/(license|email)/i', (string) $key)) {
                unset($settings[$key]);
            }
        }


        $settings = self::normalize_structured_settings($settings);
        return $settings;
    }

    public static function update($values) {
        $current = self::get_all();
        $merged = wp_parse_args((array) $values, $current);
        $merged = self::normalize($merged, $current);
        update_option(self::OPTION_KEY, $merged, false);
    }


    protected static function normalize_core_cache_backend($settings) {
        $settings['cache_backend'] = isset($settings['cache_backend']) ? sanitize_key((string) $settings['cache_backend']) : 'auto';
        if (!in_array($settings['cache_backend'], array('auto', 'disk', 'litespeed'), true)) {
            $settings['cache_backend'] = 'auto';
        }
        return $settings;
    }

    protected static function normalize_js_html_settings($settings) {
        $settings['defer_all_js'] = !empty($settings['defer_all_js']) ? 1 : 0;
        $settings['enable_defer_js_fallback'] = !empty($settings['enable_defer_js_fallback']) ? 1 : 0;
        if (!empty($settings['defer_all_js'])) {
            $settings['enable_defer_js_fallback'] = 1;
        }

        // Only true conflicts are corrected automatically: combining is disabled when Delay JS or native script-strategy rewrites are active.
        // Combining also implies minify. HTML minify automatically includes comment cleanup.
        $settings['enable_js_minify'] = !empty($settings['enable_js_minify']) ? 1 : 0;
        // Legacy compatibility: this used to be a separate safety toggle. It is now mirrored to the single JS minify setting.
        $settings['allow_experimental_js_minify'] = $settings['enable_js_minify'];
        $settings['enable_js_combine'] = !empty($settings['enable_js_combine']) ? 1 : 0;
        $settings['enable_delay_js'] = !empty($settings['enable_delay_js']) ? 1 : 0;
        $settings['enable_native_script_strategy'] = !empty($settings['enable_native_script_strategy']) ? 1 : 0;
        $settings['enable_move_module_scripts_footer'] = !empty($settings['enable_move_module_scripts_footer']) ? 1 : 0;
        $settings['enable_sensitive_asset_unload_override'] = !empty($settings['enable_sensitive_asset_unload_override']) ? 1 : 0;
        if (!empty($settings['enable_js_combine'])) {
            $settings['enable_js_minify'] = 1;
            $settings['allow_experimental_js_minify'] = 1;
        }
        if (!empty($settings['enable_delay_js'])) {
            $settings['enable_js_combine'] = 0;
            $settings['enable_native_script_strategy'] = 0;
        }
        if (!empty($settings['enable_native_script_strategy'])) {
            $settings['enable_js_combine'] = 0;
        }
        if (class_exists('UCP_Compat') && UCP_Compat::should_lock_combine('js', $settings)) {
            $settings['enable_js_combine'] = 0;
        }
        $settings['enable_html_minify'] = !empty($settings['enable_html_minify']) ? 1 : 0;
        $settings['remove_html_comments'] = !empty($settings['remove_html_comments']) ? 1 : 0;
        if (!empty($settings['enable_html_minify'])) {
            $settings['remove_html_comments'] = 1;
        }

        if (isset($settings['delay_js_exclusions'])) {
            $settings['delay_js_exclusions'] = (string) $settings['delay_js_exclusions'];
        }
        if (isset($settings['delay_js_specified_scripts'])) {
            $settings['delay_js_specified_scripts'] = (string) $settings['delay_js_specified_scripts'];
        }
        if (!in_array(isset($settings['delay_js_mode']) ? $settings['delay_js_mode'] : 'specified', array('specified', 'all'), true)) {
            $settings['delay_js_mode'] = 'specified';
        }

        return $settings;
    }

    protected static function normalize_shopper_cache_settings($settings) {
        // Webshop cache (UCP_Shopper_Cache): coerce to clean 0/1 and keep the vary list as text.
        $settings['serve_cache_to_shoppers'] = !empty($settings['serve_cache_to_shoppers']) ? 1 : 0;
        $settings['block_unknown_request_cookies'] = !empty($settings['block_unknown_request_cookies']) ? 1 : 0;
        $settings['optimize_cart_fragments'] = !empty($settings['optimize_cart_fragments']) ? 1 : 0;
        $settings['limit_cart_fragments_to_woo'] = !empty($settings['limit_cart_fragments_to_woo']) ? 1 : 0;
        if (isset($settings['cache_vary_cookies'])) {
            $settings['cache_vary_cookies'] = (string) $settings['cache_vary_cookies'];
        }
        return $settings;
    }

    protected static function normalize_speculative_loading_settings($settings) {
        if (empty($settings['speculative_loading_mode']) || !in_array($settings['speculative_loading_mode'], array('core', 'enhanced', 'prerender', 'off'), true)) {
            if (!empty($settings['enable_speculative_loading'])) {
                $settings['speculative_loading_mode'] = ('prerender' === (isset($settings['speculation_mode']) ? $settings['speculation_mode'] : 'prefetch')) ? 'prerender' : 'enhanced';
            } else {
                $settings['speculative_loading_mode'] = 'core';
            }
        }

        if (in_array($settings['speculative_loading_mode'], array('core', 'off'), true)) {
            $settings['enable_speculative_loading'] = 0;
            $settings['speculation_mode'] = 'prefetch';
            $settings['speculation_eagerness'] = 'conservative';
        } elseif ('prerender' === $settings['speculative_loading_mode']) {
            $settings['enable_speculative_loading'] = 1;
            $settings['speculation_mode'] = 'prerender';
            $settings['speculation_eagerness'] = 'conservative';
        } else {
            $settings['enable_speculative_loading'] = 1;
            $settings['speculation_mode'] = 'prefetch';
            $settings['speculation_eagerness'] = 'conservative';
        }

        if (!in_array($settings['speculation_mode'], array('prefetch', 'prerender'), true)) {
            $settings['speculation_mode'] = 'prefetch';
        }

        if (!in_array($settings['speculation_eagerness'], array('conservative', 'moderate', 'eager'), true)) {
            $settings['speculation_eagerness'] = 'moderate';
        }

        return $settings;
    }

    protected static function normalize_css_delivery_settings($settings) {
        $css_delivery_mode = isset($settings['css_delivery_mode']) ? (string) $settings['css_delivery_mode'] : 'none';
        if (!in_array($css_delivery_mode, array('none', 'remove_unused', 'async'), true)) {
            $css_delivery_mode = 'none';
        }
        if ('none' === $css_delivery_mode) {
            if (!empty($settings['enable_used_css'])) {
                $css_delivery_mode = 'remove_unused';
            } elseif (!empty($settings['enable_critical_css'])) {
                $css_delivery_mode = 'async';
            }
        }

        if (empty($settings['show_advanced_options'])) {
            $settings['enable_css_combine'] = 0;
            $settings['enable_js_combine'] = 0;
            $settings['enable_cdn'] = 0;
            $settings['enable_cloudflare_apo_mode'] = 0;
            $settings['enable_rest_cache'] = 0;
        }
        if (class_exists('UCP_Compat') && UCP_Compat::should_lock_combine('css', $settings)) {
            $settings['enable_css_combine'] = 0;
        }
        $settings['css_delivery_mode'] = $css_delivery_mode;

        // Only CSS combining is disabled for delivery modes, because combined files make per-page Used/Critical CSS artifacts unreliable.
        $settings['enable_used_css'] = 'remove_unused' === $css_delivery_mode ? 1 : 0;
        $settings['enable_used_css_delivery'] = 'remove_unused' === $css_delivery_mode ? 1 : 0;
        $settings['enable_critical_css'] = ('async' === $css_delivery_mode || ('remove_unused' === $css_delivery_mode && !empty($settings['enable_critical_css']))) ? 1 : 0;

        if ('none' === $css_delivery_mode) {
            $settings['enable_css_queue'] = 0;
            $settings['enable_remote_css_render'] = 0;
        } else {
            $settings['enable_css_combine'] = 0;
            $settings['enable_css_queue'] = 1;
            if (empty($settings['enable_cloud'])) {
                $settings['enable_remote_css_render'] = 0;
            }
        }

        if (empty($settings['enable_css_queue'])) {
            $settings['enable_remote_css_render'] = 0;
        }

        return $settings;
    }

    protected static function normalize_boolean_settings($settings, $keys) {
        foreach ($keys as $key) {
            $settings[$key] = !empty($settings[$key]) ? 1 : 0;
        }
        return $settings;
    }

    protected static function normalize_system_write_and_asset_settings($settings, $defaults) {
        $settings = self::normalize_boolean_settings(
            $settings,
            class_exists('UCP_Settings_Schema') ? UCP_Settings_Schema::boolean_keys('system_write_and_assets') : array(
                'allow_wp_config_write', 'allow_dropin_writes', 'allow_dropin_takeover', 'allow_browser_cache_rule_writes',
                'enable_stale_cache', 'purge_on_extension_change', 'purge_on_core_update', 'purge_on_global_change',
                'css_artifact_rollback', 'enable_html_test_mode', 'autopilot_enabled', 'preload_homepage',
            )
        );
        $settings['stale_cache_lifespan'] = min(168, max(1, absint($settings['stale_cache_lifespan'])));
        $settings['css_artifact_min_bytes'] = min(5000, max(50, absint($settings['css_artifact_min_bytes'])));
        $settings['css_artifact_retry_limit'] = min(10, max(1, absint($settings['css_artifact_retry_limit'])));

        // Asset Manager UI and runtime are removed in this simplified admin build.
        $settings['enable_asset_test_mode'] = 0;
        $settings['enable_asset_manager_snapshot'] = 0;
        $settings['advanced_asset_rules'] = '';
        $settings['disabled_style_handles'] = '';
        $settings['disabled_script_handles'] = '';
        $settings['conditional_style_unloads'] = '';
        $settings['conditional_script_unloads'] = '';
        $settings['ui_mode'] = isset($settings['ui_mode']) && 'advanced' === $settings['ui_mode'] ? 'advanced' : 'simple';
        $settings['heartbeat_frequency'] = isset($settings['heartbeat_backend_frequency']) ? absint($settings['heartbeat_backend_frequency']) : (isset($defaults['heartbeat_frequency']) ? (int) $defaults['heartbeat_frequency'] : 60);
        $settings['image_quality'] = min(95, max(50, absint($settings['image_quality'])));
        $settings['fragment_cache_ttl'] = min(DAY_IN_SECONDS, max(MINUTE_IN_SECONDS, absint($settings['fragment_cache_ttl'])));
        $settings['rest_cache_ttl'] = min(HOUR_IN_SECONDS, max(30, absint($settings['rest_cache_ttl'])));

        return $settings;
    }

    protected static function normalize_media_performance_settings($settings) {
        $settings = self::normalize_boolean_settings(
            $settings,
            class_exists('UCP_Settings_Schema') ? UCP_Settings_Schema::boolean_keys('media_performance') : array(
                'compatibility_mode', 'woocommerce_safety_mode', 'wp_rocket_style_defaults', 'enable_admin_queue_runner',
                'show_advanced_options', 'disable_logged_in_optimizations', 'accessibility_mode', 'clean_uninstall',
                'delay_js_disable_click_delay', 'enable_lazy_images', 'enable_lazy_iframes', 'enable_lazy_youtube_preview', 'enable_add_image_dimensions',
                'enable_image_optimization', 'enable_webp_generation', 'enable_avif_generation', 'enable_font_display_swap',
                'enable_remove_query_strings', 'enable_light_preload_requests', 'preload_pause_on_high_load',
                'enable_css_profiles', 'enable_lazy_render', 'enable_edge_html_cache', 'edge_html_cache_tags',
                'enable_self_host_third_party_assets', 'enable_disable_dashicons', 'enable_disable_jquery_migrate',
                'enable_move_module_scripts_footer', 'safe_settings_export',
            )
        );
        if (!empty($settings['enable_avif_generation'])) {
            $settings['enable_webp_generation'] = 1;
        }
        $settings['lazyload_exclude_leading_images'] = min(5, max(0, absint(isset($settings['lazyload_exclude_leading_images']) ? $settings['lazyload_exclude_leading_images'] : 1)));
        $settings['preload_critical_images'] = min(3, max(0, absint(isset($settings['preload_critical_images']) ? $settings['preload_critical_images'] : 1)));
        if (!empty($settings['active_preset']) && 'pagespeed_auto' === $settings['active_preset']) {
            $settings['preload_critical_images'] = min(3, max(1, absint($settings['preload_critical_images'])));
        }
        $settings['preload_max_server_load'] = min(32, max(1, absint(isset($settings['preload_max_server_load']) ? $settings['preload_max_server_load'] : 4)));
        $settings['preload_menu_urls_limit'] = min(100, max(1, absint(isset($settings['preload_menu_urls_limit']) ? $settings['preload_menu_urls_limit'] : 40)));
        $settings['preload_recent_purge_limit'] = min(100, max(1, absint(isset($settings['preload_recent_purge_limit']) ? $settings['preload_recent_purge_limit'] : 30)));
        $settings['css_profile_max_age_days'] = min(90, max(1, absint(isset($settings['css_profile_max_age_days']) ? $settings['css_profile_max_age_days'] : 14)));
        $settings['lcp_profile_min_confidence'] = min(100, max(50, absint(isset($settings['lcp_profile_min_confidence']) ? $settings['lcp_profile_min_confidence'] : 85)));
        $settings['lcp_profile_max_age_days'] = min(90, max(1, absint(isset($settings['lcp_profile_max_age_days']) ? $settings['lcp_profile_max_age_days'] : 21)));
        $allowed_lcp_hosts = class_exists('UCP_Helpers') ? UCP_Helpers::normalize_multiline(isset($settings['lcp_profile_allowed_hosts']) ? $settings['lcp_profile_allowed_hosts'] : '') : array();
        $settings['lcp_profile_allowed_hosts'] = implode("
", array_values(array_unique(array_filter(array_map('sanitize_text_field', $allowed_lcp_hosts), 'strlen'))));
        $settings['edge_html_cache_ttl'] = min(86400, max(60, absint(isset($settings['edge_html_cache_ttl']) ? $settings['edge_html_cache_ttl'] : 600)));
        $settings['edge_html_cache_stale'] = min(604800, max(0, absint(isset($settings['edge_html_cache_stale']) ? $settings['edge_html_cache_stale'] : 86400)));
        $settings['autosave_interval'] = min(600, max(15, absint(isset($settings['autosave_interval']) ? $settings['autosave_interval'] : 60)));
        $settings['lazyload_threshold'] = min(1000, max(0, absint(isset($settings['lazyload_threshold']) ? $settings['lazyload_threshold'] : 0)));
        $settings = self::normalize_boolean_settings(
            $settings,
            class_exists('UCP_Settings_Schema') ? UCP_Settings_Schema::boolean_keys('hardening_and_ui') : array('enable_disable_xmlrpc','enable_hide_wp_version','enable_remove_rsd_link','enable_remove_shortlink','enable_disable_rss_feeds','enable_remove_rss_feed_links','enable_disable_self_pingbacks','enable_disable_rest_api','enable_remove_rest_api_links','enable_disable_google_maps','enable_disable_password_strength_meter','enable_disable_comments','enable_remove_comment_links','enable_blank_favicon','enable_remove_global_styles','enable_separate_block_styles','enable_disable_google_fonts','enable_hide_toolbar_menu','enable_lazyload_fade_in','enable_lazyload_background_images')
        );

        return $settings;
    }

    protected static function normalize_schedule_and_cleanup_settings($settings) {
        if (!in_array(isset($settings['cache_refresh_interval']) ? $settings['cache_refresh_interval'] : 'off', array('off','2hours','daily','weekly'), true)) {
            $settings['cache_refresh_interval'] = 'off';
        }
        if (!in_array(isset($settings['cdn_file_types']) ? $settings['cdn_file_types'] : 'all', array('all','css_js','images'), true)) {
            $settings['cdn_file_types'] = 'all';
        }
        foreach (array('heartbeat_frontend_behavior','heartbeat_editor_behavior','heartbeat_backend_behavior') as $heartbeat_behavior_key) {
            if (!in_array(isset($settings[$heartbeat_behavior_key]) ? $settings[$heartbeat_behavior_key] : 'reduce', array('keep','reduce','disable'), true)) {
                $settings[$heartbeat_behavior_key] = 'reduce';
            }
        }
        $settings['enable_heartbeat_control'] = ('keep' === $settings['heartbeat_frontend_behavior'] && 'keep' === $settings['heartbeat_editor_behavior'] && 'keep' === $settings['heartbeat_backend_behavior']) ? 0 : 1;
        $db_cleanup_frequency = isset($settings['db_cleanup_frequency']) ? (string) $settings['db_cleanup_frequency'] : 'off';
        if (!in_array($db_cleanup_frequency, array('off','daily','weekly','monthly'), true)) {
            $db_cleanup_frequency = 'off';
        }
        if (empty($settings['enable_db_cleanup']) || 'off' === $db_cleanup_frequency) {
            $settings['enable_db_cleanup'] = 0;
            $settings['db_cleanup_frequency'] = 'off';
        } else {
            $settings['enable_db_cleanup'] = 1;
            $settings['db_cleanup_frequency'] = $db_cleanup_frequency;
        }
        if (!is_string(isset($settings['preload_content_scope']) ? $settings['preload_content_scope'] : '')) {
            $settings['preload_content_scope'] = 'posts,archives,terms';
        }
        return $settings;
    }

    public static function normalize($settings, $current = array()) {
        $defaults = self::defaults();
        $current = wp_parse_args((array) $current, $defaults);
        $settings = wp_parse_args((array) $settings, $current);

        $settings = UCP_Settings_Combined_Controls::apply($settings, true, true);

        $settings = self::normalize_core_cache_backend($settings);
        $settings = self::normalize_js_html_settings($settings);
        $settings = self::normalize_shopper_cache_settings($settings);

        if (empty($settings['enable_preload']) || empty($settings['enable_cache'])) {
            $settings['enable_preload_queue'] = 0;
        }

        $settings = self::normalize_speculative_loading_settings($settings);
        $settings = self::normalize_css_delivery_settings($settings);
        $settings = self::normalize_system_write_and_asset_settings($settings, $defaults);
        $settings = self::normalize_media_performance_settings($settings);
        $settings = self::normalize_schedule_and_cleanup_settings($settings);
        $settings = self::normalize_internal_secret_keys($settings);


        // UCP: normalize structured self-host and fetchpriority settings before storing.
        $settings = self::normalize_structured_settings($settings);

        // Keep the WP Rocket-style baseline automatic, even after REST/import saves.
        $automatic = self::automatic_managed_settings();
        $settings = array_merge($settings, $automatic);

        unset($settings['enable_guest_mode'], $settings['guest_mode_optimize_first_visit']);

        return wp_parse_args($settings, $defaults);
    }

}
