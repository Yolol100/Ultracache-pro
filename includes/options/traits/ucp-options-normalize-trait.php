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
        return array('cloud_api_key', 'cloudflare_api_token', 'secret_cache_key', 'css_cache_key', 'js_cache_key');
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


        if (isset($settings['self_host_asset_domains'])) {
            $domains = array();
            foreach (UCP_Helpers::normalize_multiline($settings['self_host_asset_domains']) as $domain) {
                $domain = strtolower(trim((string) preg_replace('/^https?:\/\//i', '', $domain)));
                $domain = preg_replace('/\/.*$/', '', $domain);
                if (preg_match('/^[a-z0-9.-]+$/', $domain) && false !== strpos($domain, '.')) {
                    $domains[] = $domain;
                }
            }
            $settings['self_host_asset_domains'] = implode("\n", array_values(array_unique($domains)));
        }
        if (isset($settings['fetchpriority_rules'])) {
            $rules = array();
            foreach (UCP_Helpers::normalize_multiline($settings['fetchpriority_rules']) as $rule) {
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
            $settings['fetchpriority_rules'] = implode("\n", array_values(array_unique($rules)));
        }
        return $settings;
    }

    public static function update($values) {
        $current = self::get_all();
        $merged = wp_parse_args((array) $values, $current);
        $merged = self::normalize($merged, $current);
        update_option(self::OPTION_KEY, $merged, false);
    }

    public static function normalize($settings, $current = array()) {
        $defaults = self::defaults();
        $current = wp_parse_args((array) $current, $defaults);
        $settings = wp_parse_args((array) $settings, $current);

        if (array_key_exists('html_optimization_mode', $settings)) {
            $html_mode = sanitize_key((string) $settings['html_optimization_mode']);
            if ('minify' === $html_mode) {
                $settings['remove_html_comments'] = 1;
                $settings['enable_html_minify'] = 1;
            } elseif ('comments' === $html_mode) {
                $settings['remove_html_comments'] = 1;
                $settings['enable_html_minify'] = 0;
            } elseif ('off' === $html_mode) {
                $settings['remove_html_comments'] = 0;
                $settings['enable_html_minify'] = 0;
            }
            unset($settings['html_optimization_mode']);
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
            } elseif ('off' === $image_mode) {
                $settings['enable_image_optimization'] = 0;
                $settings['enable_webp_generation'] = 0;
                $settings['enable_avif_generation'] = 0;
            }
            unset($settings['image_optimization_mode']);
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
            unset($settings['delay_js_control']);
        }

        if (array_key_exists('media_lazyload_mode', $settings)) {
            $media_mode = sanitize_key((string) $settings['media_lazyload_mode']);
            $settings['enable_lazy_images'] = in_array($media_mode, array('images','iframes','youtube'), true) ? 1 : 0;
            $settings['enable_lazy_iframes'] = in_array($media_mode, array('iframes','youtube'), true) ? 1 : 0;
            $settings['enable_lazy_youtube_preview'] = 'youtube' === $media_mode ? 1 : 0;
            unset($settings['media_lazyload_mode']);
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
            unset($settings['lcp_image_mode']);
        }

        if (array_key_exists('google_fonts_mode', $settings)) {
            $fonts_mode = sanitize_key((string) $settings['google_fonts_mode']);
            $settings['enable_disable_google_fonts'] = 'disable' === $fonts_mode ? 1 : 0;
            $settings['enable_local_google_fonts'] = 'local' === $fonts_mode ? 1 : 0;
            $settings['enable_font_display_swap'] = in_array($fonts_mode, array('swap','local'), true) ? 1 : 0;
            unset($settings['google_fonts_mode']);
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
            unset($settings['preload_mode']);
        }

        if (array_key_exists('stale_cache_mode', $settings)) {
            $stale_mode = sanitize_key((string) $settings['stale_cache_mode']);
            if ('off' === $stale_mode) {
                $settings['enable_stale_cache'] = 0;
            } elseif (in_array($stale_mode, array('6','12','24','48'), true)) {
                $settings['enable_stale_cache'] = 1;
                $settings['stale_cache_lifespan'] = absint($stale_mode);
            }
            unset($settings['stale_cache_mode']);
        }

        if (array_key_exists('heartbeat_interval_mode', $settings)) {
            $heartbeat_interval_mode = sanitize_key((string) $settings['heartbeat_interval_mode']);
            if ('custom' === $heartbeat_interval_mode) {
                $settings['heartbeat_frontend_frequency'] = 60;
                $settings['heartbeat_editor_frequency'] = 30;
                $settings['heartbeat_backend_frequency'] = 60;
                $settings['heartbeat_frequency'] = 60;
            } elseif (in_array($heartbeat_interval_mode, array('30','60','120'), true)) {
                $heartbeat_interval = absint($heartbeat_interval_mode);
                $settings['heartbeat_frontend_frequency'] = $heartbeat_interval;
                $settings['heartbeat_editor_frequency'] = $heartbeat_interval;
                $settings['heartbeat_backend_frequency'] = $heartbeat_interval;
                $settings['heartbeat_frequency'] = $heartbeat_interval;
            }
            unset($settings['heartbeat_interval_mode']);
        }

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

        if (empty($settings['enable_preload']) || empty($settings['enable_cache'])) {
            $settings['enable_preload_queue'] = 0;
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

        if (!in_array($settings['speculation_mode'], array('prefetch', 'prerender'), true)) {
            $settings['speculation_mode'] = 'prefetch';
        }

        if (!in_array($settings['speculation_eagerness'], array('conservative', 'moderate', 'eager'), true)) {
            $settings['speculation_eagerness'] = 'moderate';
        }
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
        $settings["css_delivery_mode"] = $css_delivery_mode;

        // Only CSS combining is disabled for delivery modes, because combined files make per-page Used/Critical CSS artifacts unreliable.
        $settings["enable_used_css"] = "remove_unused" === $css_delivery_mode ? 1 : 0;
        $settings["enable_used_css_delivery"] = "remove_unused" === $css_delivery_mode ? 1 : 0;
        $settings["enable_critical_css"] = ("async" === $css_delivery_mode || ("remove_unused" === $css_delivery_mode && !empty($settings["enable_critical_css"]))) ? 1 : 0;

        if ("none" === $css_delivery_mode) {
            $settings["enable_css_queue"] = 0;
            $settings["enable_remote_css_render"] = 0;
        } else {
            $settings["enable_css_combine"] = 0;
            $settings["enable_css_queue"] = 1;
            if (empty($settings["enable_cloud"])) {
                $settings["enable_remote_css_render"] = 0;
            }
        }

        if (empty($settings["enable_css_queue"])) {
            $settings["enable_remote_css_render"] = 0;
        }

        $settings['allow_wp_config_write'] = !empty($settings['allow_wp_config_write']) ? 1 : 0;
        $settings['allow_dropin_writes'] = !empty($settings['allow_dropin_writes']) ? 1 : 0;
        $settings['allow_dropin_takeover'] = !empty($settings['allow_dropin_takeover']) ? 1 : 0;
        $settings['allow_browser_cache_rule_writes'] = !empty($settings['allow_browser_cache_rule_writes']) ? 1 : 0;
        $settings['enable_stale_cache'] = !empty($settings['enable_stale_cache']) ? 1 : 0;
        $settings['purge_on_extension_change'] = !empty($settings['purge_on_extension_change']) ? 1 : 0;
        $settings['purge_on_core_update'] = !empty($settings['purge_on_core_update']) ? 1 : 0;
        $settings['purge_on_global_change'] = !empty($settings['purge_on_global_change']) ? 1 : 0;
        $settings['stale_cache_lifespan'] = min(168, max(1, absint($settings['stale_cache_lifespan'])));
        $settings['css_artifact_min_bytes'] = min(5000, max(50, absint($settings['css_artifact_min_bytes'])));
        $settings['css_artifact_retry_limit'] = min(10, max(1, absint($settings['css_artifact_retry_limit'])));
        $settings['css_artifact_rollback'] = !empty($settings['css_artifact_rollback']) ? 1 : 0;

        $settings['enable_html_test_mode'] = !empty($settings['enable_html_test_mode']) ? 1 : 0;
        $settings['enable_asset_test_mode'] = !empty($settings['enable_asset_test_mode']) ? 1 : 0;
        $settings['enable_asset_manager_snapshot'] = !empty($settings['enable_asset_manager_snapshot']) ? 1 : 0;
        if (isset($settings['advanced_asset_rules']) && !is_string($settings['advanced_asset_rules'])) {
            $settings['advanced_asset_rules'] = '';
        }
        $settings['ui_mode'] = isset($settings['ui_mode']) && 'advanced' === $settings['ui_mode'] ? 'advanced' : 'simple';
        $settings['autopilot_enabled'] = !empty($settings['autopilot_enabled']) ? 1 : 0;
        $settings['preload_homepage'] = !empty($settings['preload_homepage']) ? 1 : 0;
        $settings['heartbeat_frequency'] = isset($settings['heartbeat_backend_frequency']) ? absint($settings['heartbeat_backend_frequency']) : (isset($defaults['heartbeat_frequency']) ? (int) $defaults['heartbeat_frequency'] : 60);
        $settings['image_quality'] = min(95, max(50, absint($settings['image_quality'])));
        $settings['fragment_cache_ttl'] = min(DAY_IN_SECONDS, max(MINUTE_IN_SECONDS, absint($settings['fragment_cache_ttl'])));
        $settings['rest_cache_ttl'] = min(HOUR_IN_SECONDS, max(30, absint($settings['rest_cache_ttl'])));

        $settings['compatibility_mode'] = !empty($settings['compatibility_mode']) ? 1 : 0;
        $settings['woocommerce_safety_mode'] = !empty($settings['woocommerce_safety_mode']) ? 1 : 0;
        $settings['wp_rocket_style_defaults'] = !empty($settings['wp_rocket_style_defaults']) ? 1 : 0;
        $settings['enable_admin_queue_runner'] = !empty($settings['enable_admin_queue_runner']) ? 1 : 0;
        $settings['show_advanced_options'] = !empty($settings['show_advanced_options']) ? 1 : 0;
        $settings['disable_logged_in_optimizations'] = !empty($settings['disable_logged_in_optimizations']) ? 1 : 0;
        $settings['accessibility_mode'] = !empty($settings['accessibility_mode']) ? 1 : 0;
        $settings['clean_uninstall'] = !empty($settings['clean_uninstall']) ? 1 : 0;
        $settings['delay_js_disable_click_delay'] = !empty($settings['delay_js_disable_click_delay']) ? 1 : 0;
        $settings['enable_lazy_youtube_preview'] = !empty($settings['enable_lazy_youtube_preview']) ? 1 : 0;
        $settings['enable_add_image_dimensions'] = !empty($settings['enable_add_image_dimensions']) ? 1 : 0;
        $settings['enable_image_optimization'] = !empty($settings['enable_image_optimization']) ? 1 : 0;
        $settings['enable_webp_generation'] = !empty($settings['enable_webp_generation']) ? 1 : 0;
        $settings['enable_avif_generation'] = !empty($settings['enable_avif_generation']) ? 1 : 0;
        if (!empty($settings['enable_avif_generation'])) {
            $settings['enable_webp_generation'] = 1;
        }
        $settings['lazyload_exclude_leading_images'] = min(5, max(0, absint(isset($settings['lazyload_exclude_leading_images']) ? $settings['lazyload_exclude_leading_images'] : 1)));
        $settings['preload_critical_images'] = min(3, max(0, absint(isset($settings['preload_critical_images']) ? $settings['preload_critical_images'] : 1)));
        if (!empty($settings['active_preset']) && 'pagespeed_auto' === $settings['active_preset']) {
            $settings['preload_critical_images'] = min(3, max(1, absint($settings['preload_critical_images'])));
        }
        $settings['enable_font_display_swap'] = !empty($settings['enable_font_display_swap']) ? 1 : 0;
        $settings['enable_remove_query_strings'] = !empty($settings['enable_remove_query_strings']) ? 1 : 0;
        $settings['enable_light_preload_requests'] = !empty($settings['enable_light_preload_requests']) ? 1 : 0;
        $settings['enable_lazy_render'] = !empty($settings['enable_lazy_render']) ? 1 : 0;
        $settings['enable_self_host_third_party_assets'] = !empty($settings['enable_self_host_third_party_assets']) ? 1 : 0;
        $settings['enable_disable_dashicons'] = !empty($settings['enable_disable_dashicons']) ? 1 : 0;
        $settings['enable_disable_jquery_migrate'] = !empty($settings['enable_disable_jquery_migrate']) ? 1 : 0;
        foreach (array('enable_disable_xmlrpc','enable_hide_wp_version','enable_remove_rsd_link','enable_remove_shortlink','enable_disable_rss_feeds','enable_remove_rss_feed_links','enable_disable_self_pingbacks','enable_disable_rest_api','enable_remove_rest_api_links','enable_disable_google_maps','enable_disable_password_strength_meter','enable_disable_comments','enable_remove_comment_links','enable_blank_favicon','enable_remove_global_styles','enable_separate_block_styles','enable_disable_google_fonts','enable_hide_toolbar_menu','enable_lazyload_fade_in','enable_lazyload_background_images') as $ucp_bool_key) {
            $settings[$ucp_bool_key] = !empty($settings[$ucp_bool_key]) ? 1 : 0;
        }
        $settings['autosave_interval'] = min(600, max(15, absint(isset($settings['autosave_interval']) ? $settings['autosave_interval'] : 60)));
        $settings['lazyload_threshold'] = min(1000, max(0, absint(isset($settings['lazyload_threshold']) ? $settings['lazyload_threshold'] : 0)));
        $settings['enable_move_module_scripts_footer'] = !empty($settings['enable_move_module_scripts_footer']) ? 1 : 0;
        $settings['safe_settings_export'] = !empty($settings['safe_settings_export']) ? 1 : 0;
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


        // UCP: normalize structured self-host and fetchpriority settings before storing.
        if (isset($settings['self_host_asset_domains'])) {
            $domains = array();
            foreach (UCP_Helpers::normalize_multiline($settings['self_host_asset_domains']) as $domain) {
                $domain = strtolower(trim((string) preg_replace('/^https?:\/\//i', '', $domain)));
                $domain = preg_replace('/\/.*$/', '', $domain);
                if (preg_match('/^[a-z0-9.-]+$/', $domain) && false !== strpos($domain, '.')) {
                    $domains[] = $domain;
                }
            }
            $settings['self_host_asset_domains'] = implode("\n", array_values(array_unique($domains)));
        }
        if (isset($settings['fetchpriority_rules'])) {
            $rules = array();
            foreach (UCP_Helpers::normalize_multiline($settings['fetchpriority_rules']) as $rule) {
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
            $settings['fetchpriority_rules'] = implode("\n", array_values(array_unique($rules)));
        }

        unset($settings['enable_guest_mode'], $settings['guest_mode_optimize_first_visit']);

        return wp_parse_args($settings, $defaults);
    }

}
