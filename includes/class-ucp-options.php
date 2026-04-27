<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Options {
    const OPTION_KEY = 'ucp_settings';

    public static function defaults() {
        return array(
            'ui_mode'                        => 'simple',
            'active_preset'                  => 'recommended',
            'enable_cache'                   => 0,
            'cache_lifespan'                 => 10,
            'cache_logged_in'                => 0,
            'cache_mobile_separately'        => 1,
            'cache_query_strings'            => 0,
            'enable_stale_cache'             => 0,
            'stale_cache_lifespan'           => 24,
            'enable_woocommerce_rules'       => 1,
            'exclude_urls'                   => "cart\ncheckout\nmy-account\norder-pay\nadd-payment-method\norder-received\nwc-api\nadd-to-cart=",
            'exclude_cookies'                => "wordpress_logged_in_\ncomment_author_\nwp-postpass_\nwoocommerce_items_in_cart\nwp_woocommerce_session_\nwoocommerce_cart_hash\nwoocommerce_recently_viewed\nedd_items_in_cart\nPHPSESSID",
            'enable_preload'                 => 0,
            'enable_preload_queue'           => 1,
            'preload_homepage'               => 1,
            'preload_sitemaps'               => 1,
            'preload_delay_ms'               => 500,
            'preload_batch_size'             => 15,
            'preload_max_urls'               => 250,
            'enable_html_minify'             => 1,
            'enable_html_test_mode'          => 0,
            'remove_html_comments'           => 1,
            'html_exclude_urls'              => "cart\ncheckout\nmy-account\nwp-json\npreview=true\nelementor-preview=\nfl_builder\nbricks=run\nct_builder=\nbreakdance=\ncustomize_changeset_uuid=",
            'html_exclude_templates'         => "template-elementor-canvas.php\nfl-theme-builder-layout.php\nbricks-template.php\nblank-template.php",
            'enable_css_minify'              => 1,
            'enable_css_combine'             => 0,
            'enable_js_minify'               => 1,
            'enable_js_combine'              => 0,
            'css_exclusions'                 => "admin-bar\nwp-block-library\nwp-interactivity",
            'disabled_style_handles'        => '',
            'js_exclusions'                  => "jquery\nrecaptcha\ngtag\ngoogle-analytics\nwp-interactivity",
            'disabled_script_handles'       => '',
            'conditional_style_unloads'      => '',
            'conditional_script_unloads'     => '',
            'enable_delay_js'                => 0,
            'delay_js_timeout'               => 4,
            'delay_js_safe_mode'             => 1,
            'delay_js_exclusions'            => "jquery\nrecaptcha\nwc-cart-fragments\njs-cookie\nwp-interactivity",
            'delay_js_presets'               => '',
            'enable_defer_js_fallback'       => 0,
            'defer_all_js'                  => 0,
            'enable_native_script_strategy'  => 1,
            'native_script_handles'          => '',
            'asset_rules'                    => UCP_Rule_Engine::default_rules(),
            'enable_remove_emojis'           => 1,
            'enable_disable_embeds'         => 1,
            'enable_prefetch_links'          => 1,
            'enable_speculative_loading'     => 0,
            'speculation_mode'               => 'prefetch',
            'speculation_eagerness'          => 'moderate',
            'speculation_exclusions'         => "cart\ncheckout\nmy-account\norder-pay\nadd-payment-method\norder-received\nwc-api\nadd-to-cart=\nlogout\n_wpnonce\nnonce",
            'enable_lazy_images'             => 1,
            'enable_lazy_iframes'            => 1,
            'enable_youtube_preview'        => 0,
            'enable_lazy_background_images' => 0,
            'lazy_background_exclusions'    => ".hero\n.banner\n.site-header\n.elementor-location-header\n.woocommerce-product-gallery\n.ucp-no-bg-lazyload",
            'lazyload_exclusions'            => "hero
site-logo
wp-post-image
woocommerce-product-gallery
ucp-no-lazyload",
            'enable_image_dimensions'        => 1,
            'enable_image_optimization'      => 0,
            'enable_webp_generation'         => 1,
            'enable_avif_generation'         => 0,
            'image_quality'                  => 82,
            'preload_fonts'                  => '',
            'dns_prefetch_domains'           => '',
            'enable_used_css'                => 0,
            'enable_used_css_delivery'       => 0,
            'used_css_max_rules'             => 1200,
            'used_css_safelist'              => ".is-active\n.open\n.current-menu-item\n.woocommerce-error",
            'enable_critical_css'            => 0,
            'critical_css_max_bytes'         => 12000,
            'css_artifact_min_bytes'         => 200,
            'css_artifact_retry_limit'       => 3,
            'css_artifact_rollback'          => 1,
            'enable_css_queue'               => 0,
            'enable_remote_css_render'       => 0,
            'used_css_beta_test_mode'       => 1,
            'used_css_fallback_enabled'     => 1,
            'critical_css_beta_test_mode'   => 1,
            'critical_css_fallback_enabled' => 1,
            'browser_cache_headers'          => 0,
            'cache_control_max_age'          => 2592000,
            'enable_heartbeat_control'       => 1,
            'heartbeat_frequency'            => 60,
            'heartbeat_frontend_frequency'   => 60,
            'heartbeat_editor_frequency'     => 30,
            'heartbeat_backend_frequency'    => 60,
            'enable_db_cleanup'              => 1,
            'db_keep_post_revisions'         => 5,
            'db_cleanup_expired_transients'  => 1,
            'db_cleanup_all_transients'      => 0,
            'db_cleanup_spam_comments'       => 1,
            'db_cleanup_trashed_comments'    => 1,
            'db_cleanup_trashed_posts'       => 1,
            'db_cleanup_optimize_tables'     => 0,
            'db_cleanup_wc_sessions'         => 1,
            'allow_wp_config_write'          => 0,
            'allow_dropin_writes'            => 0,
            'auto_advanced_cache_takeover'   => 1,
            'allow_browser_cache_rule_writes'=> 0,
            'enable_cdn_purge'              => 0,
            'cdn_provider'                  => 'none',
            'cloudflare_zone_id'            => '',
            'cloudflare_api_token'          => '',
            'bunny_pullzone_id'             => '',
            'bunny_api_key'                 => '',
            'cdn_custom_webhook_url'        => '',
            'confirm_page_cache_takeover'   => 0,
            'enable_admin_bar'               => 1,
            'enable_guest_mode'             => 0,
            'guest_mode_optimize_first_visit' => 1,
            'enable_asset_test_mode'        => 0,
            'purge_on_post_update'           => 1,
            'enable_targeted_purge'          => 1,
            'enable_cache_tags'             => 1,
            'enable_local_google_fonts'      => 0,
            'purge_on_comment'               => 1,
            'purge_on_theme_switch'          => 1,
            'job_batch_size'                 => 5,
            'job_max_attempts'               => 3,
            'job_lock_ttl'                   => 300,
            'enable_health_checks'           => 1,
            'autopilot_enabled'              => 1,
            'onboarding_completed'           => 0,
            'onboarding_site_type'           => 'general',
            'onboarding_goal'                => 'safe',
            'log_retention_days'             => 30,
            'diagnostics_retention_days'     => 14,
            'enable_rest_cache'              => 0,
            'rest_cache_debug'               => 0,
            'rest_cache_rules'               => array(),
            'enable_fragment_cache'          => 0,
            'enable_crawler'                 => 0,
            'crawler_mode'                   => 'sitemap',
            'crawler_custom_sitemap'         => '',
            'crawler_seed_urls'              => '',
            'crawler_max_urls'               => 250,
            'crawler_concurrency'            => 2,
            'crawler_delay_seconds'          => 1,
            'crawler_max_attempts'           => 3,
            'enable_cache_vary'              => 0,
            'vary_mobile_desktop'            => 0,
            'vary_cookie_rules'              => '',
            'serve_mode'                     => 'safe',
            'compat_remote_updates_enabled'  => 0,
            'compat_remote_endpoint'         => '',
            'job_retention_days'             => 14,
        );
    }

    public static function get_all() {
        $raw = wp_parse_args(get_option(self::OPTION_KEY, array()), self::defaults());
        return self::normalize($raw, $raw);
    }

    public static function get($key, $default = null) {
        $options = self::get_all();
        return isset($options[$key]) ? $options[$key] : $default;
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

        $settings['defer_all_js'] = !empty($settings['defer_all_js']) ? 1 : 0;
        $settings['enable_defer_js_fallback'] = !empty($settings['enable_defer_js_fallback']) ? 1 : 0;

        if (!empty($settings['enable_delay_js'])) {
            $settings['enable_js_combine'] = 0;
            $settings['enable_native_script_strategy'] = 0;
        }

        if (empty($settings['enable_preload']) || empty($settings['enable_cache'])) {
            $settings['enable_preload_queue'] = 0;
        }

        if (empty($settings['enable_used_css']) && empty($settings['enable_critical_css'])) {
            $settings['enable_css_queue'] = 0;
            $settings['enable_remote_css_render'] = 0;
        }

        if (isset($settings['delay_js_exclusions'])) {
            $settings['delay_js_exclusions'] = (string) $settings['delay_js_exclusions'];
        }

        if (!in_array($settings['speculation_mode'], array('prefetch', 'prerender'), true)) {
            $settings['speculation_mode'] = 'prefetch';
        }

        if (!in_array($settings['speculation_eagerness'], array('conservative', 'moderate', 'eager'), true)) {
            $settings['speculation_eagerness'] = 'moderate';
        }

        if (!in_array($settings['ui_mode'], array('simple', 'advanced'), true)) {
            $settings['ui_mode'] = 'simple';
        }
        if (class_exists('UCP_Compat')) {
            $takeover = UCP_Compat::safe_takeover_status($settings);
            if (empty($settings['confirm_page_cache_takeover']) && empty($takeover['can_auto_enable'])) {
                $settings['enable_cache'] = 0;
                $settings['allow_wp_config_write'] = 0;
                $settings['allow_dropin_writes'] = 0;
            }
        }

        $settings['allow_wp_config_write'] = !empty($settings['allow_wp_config_write']) ? 1 : 0;
        $settings['allow_dropin_writes'] = !empty($settings['allow_dropin_writes']) ? 1 : 0;
        $settings['auto_advanced_cache_takeover'] = !empty($settings['auto_advanced_cache_takeover']) ? 1 : 0;
        $settings['allow_browser_cache_rule_writes'] = !empty($settings['allow_browser_cache_rule_writes']) ? 1 : 0;
        $settings['enable_cdn_purge'] = !empty($settings['enable_cdn_purge']) ? 1 : 0;
        $settings['confirm_page_cache_takeover'] = !empty($settings['confirm_page_cache_takeover']) ? 1 : 0;
        $settings['cdn_provider'] = sanitize_key((string) $settings['cdn_provider']);
        if (!in_array($settings['cdn_provider'], array('none', 'cloudflare', 'bunny', 'custom_webhook'), true)) {
            $settings['cdn_provider'] = 'none';
        }
        foreach (array('cloudflare_zone_id', 'bunny_pullzone_id') as $key) {
            $settings[$key] = sanitize_text_field((string) $settings[$key]);
        }
        $settings['cdn_custom_webhook_url'] = esc_url_raw((string) $settings['cdn_custom_webhook_url']);
        $settings['enable_stale_cache'] = !empty($settings['enable_stale_cache']) ? 1 : 0;
        $settings['stale_cache_lifespan'] = min(168, max(1, absint($settings['stale_cache_lifespan'])));
        $settings['css_artifact_min_bytes'] = min(5000, max(50, absint($settings['css_artifact_min_bytes'])));
        $settings['css_artifact_retry_limit'] = min(10, max(1, absint($settings['css_artifact_retry_limit'])));
        $settings['css_artifact_rollback'] = !empty($settings['css_artifact_rollback']) ? 1 : 0;

        $settings['enable_html_test_mode'] = !empty($settings['enable_html_test_mode']) ? 1 : 0;
        $settings['enable_asset_test_mode'] = !empty($settings['enable_asset_test_mode']) ? 1 : 0;
        $settings['ui_mode'] = in_array($settings['ui_mode'], array('simple', 'advanced'), true) ? $settings['ui_mode'] : 'simple';
        $settings['autopilot_enabled'] = 1;
        $settings['preload_homepage'] = 1;
        $settings['heartbeat_frequency'] = isset($settings['heartbeat_backend_frequency']) ? absint($settings['heartbeat_backend_frequency']) : (isset($defaults['heartbeat_frequency']) ? (int) $defaults['heartbeat_frequency'] : 60);
        $settings['image_quality'] = min(95, max(50, absint($settings['image_quality'])));

        $settings['enable_rest_cache'] = !empty($settings['enable_rest_cache']) ? 1 : 0;
        $settings['rest_cache_debug'] = !empty($settings['rest_cache_debug']) ? 1 : 0;
        $settings['rest_cache_rules'] = self::normalize_rest_cache_rules(isset($settings['rest_cache_rules']) ? $settings['rest_cache_rules'] : array());
        $settings['enable_fragment_cache'] = !empty($settings['enable_fragment_cache']) ? 1 : 0;
        $settings['enable_crawler'] = !empty($settings['enable_crawler']) ? 1 : 0;
        $settings['crawler_mode'] = in_array(isset($settings['crawler_mode']) ? $settings['crawler_mode'] : 'sitemap', array('sitemap', 'seed', 'delta'), true) ? $settings['crawler_mode'] : 'sitemap';
        $settings['crawler_custom_sitemap'] = esc_url_raw((string) (isset($settings['crawler_custom_sitemap']) ? $settings['crawler_custom_sitemap'] : ''));
        $settings['crawler_seed_urls'] = (string) (isset($settings['crawler_seed_urls']) ? $settings['crawler_seed_urls'] : '');
        $settings['crawler_max_urls'] = min(2000, max(1, absint(isset($settings['crawler_max_urls']) ? $settings['crawler_max_urls'] : 250)));
        $settings['crawler_concurrency'] = min(5, max(1, absint(isset($settings['crawler_concurrency']) ? $settings['crawler_concurrency'] : 2)));
        $settings['crawler_delay_seconds'] = min(10, max(0, absint(isset($settings['crawler_delay_seconds']) ? $settings['crawler_delay_seconds'] : 1)));
        $settings['crawler_max_attempts'] = min(5, max(1, absint(isset($settings['crawler_max_attempts']) ? $settings['crawler_max_attempts'] : 3)));
        $settings['enable_cache_vary'] = !empty($settings['enable_cache_vary']) ? 1 : 0;
        $settings['vary_mobile_desktop'] = !empty($settings['vary_mobile_desktop']) ? 1 : 0;
        $settings['vary_cookie_rules'] = (string) (isset($settings['vary_cookie_rules']) ? $settings['vary_cookie_rules'] : '');
        $settings['serve_mode'] = in_array(isset($settings['serve_mode']) ? $settings['serve_mode'] : 'safe', array('safe', 'fast', 'expert'), true) ? $settings['serve_mode'] : 'safe';
        $settings['compat_remote_updates_enabled'] = !empty($settings['compat_remote_updates_enabled']) ? 1 : 0;
        $settings['compat_remote_endpoint'] = esc_url_raw((string) (isset($settings['compat_remote_endpoint']) ? $settings['compat_remote_endpoint'] : ''));

        return wp_parse_args($settings, $defaults);
    }

    public static function normalize_rest_cache_rules($rules) {
        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            $rules = is_array($decoded) ? $decoded : array();
        }
        if (!is_array($rules)) {
            return array();
        }
        $clean = array();
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $namespace = sanitize_text_field(isset($rule['namespace']) ? $rule['namespace'] : '');
            $route = sanitize_text_field(isset($rule['route']) ? $rule['route'] : '');
            if ('' === $namespace && '' === $route) {
                continue;
            }
            $tags = isset($rule['tags']) ? $rule['tags'] : array();
            if (is_string($tags)) {
                $tags = preg_split('/[\r\n,]+/', $tags);
            }
            $clean[] = array(
                'active' => !empty($rule['active']) ? 1 : 0,
                'namespace' => $namespace,
                'route' => $route,
                'ttl' => min(DAY_IN_SECONDS, max(60, absint(isset($rule['ttl']) ? $rule['ttl'] : 300))),
                'tags' => array_values(array_filter(array_map('sanitize_key', (array) $tags))),
            );
            if (count($clean) >= 25) {
                break;
            }
        }
        return $clean;
    }

    public static function handle_option_updated($old_settings, $new_settings) {
        self::after_settings_save($new_settings, $old_settings);
    }

    public static function after_settings_save($new_settings, $old_settings = array()) {
        $new_settings = self::normalize($new_settings, $old_settings);
        $old_settings = wp_parse_args((array) $old_settings, self::defaults());

        UCP_Helpers::maybe_write_browser_cache_rules();
        if (!empty($new_settings['allow_dropin_writes']) || !empty($new_settings['auto_advanced_cache_takeover'])) {
            UCP_Helpers::write_dropin_config(true);
        }
        if (!empty($new_settings['enable_cache']) && !empty($new_settings['confirm_page_cache_takeover'])) {
            UCP_Helpers::write_advanced_cache_stub(true);
        } elseif (!empty($new_settings['enable_cache']) && !empty($new_settings['auto_advanced_cache_takeover']) && class_exists('UCP_Helpers')) {
            UCP_Helpers::maybe_install_own_advanced_cache_automatically(false);
        }
        if (empty($new_settings['enable_cache'])) {
            UCP_Helpers::remove_own_advanced_cache_stub();
        }
        if (class_exists('UCP_Preload')) {
            UCP_Preload::sync_schedule($new_settings);
        }
        if (class_exists('UCP_Jobs')) {
            UCP_Jobs::sync_schedule($new_settings);
        }
        if (class_exists('UCP_Health')) {
            UCP_Health::sync_schedule($new_settings);
        }
        do_action('ucp_after_settings_save', $new_settings, $old_settings);
    }

    public static function validate_import_payload($decoded) {
        if (!is_array($decoded) || empty($decoded)) {
            return array();
        }
        $defaults = self::defaults();
        $allowed = array_intersect_key($decoded, $defaults);
        return is_array($allowed) ? $allowed : array();
    }

    public static function maybe_init_defaults() {
        if (false === get_option(self::OPTION_KEY, false)) {
            add_option(self::OPTION_KEY, self::normalize(self::defaults(), self::defaults()), '', false);
            return true;
        }

        return false;
    }

    public static function maybe_apply_install_profile($force = false) {
        $settings = get_option(self::OPTION_KEY, false);
        if (!is_array($settings)) {
            return;
        }
        if (!$force && '2026-commercial-first-install-v1' === get_option('ucp_install_profile_version', '')) {
            return;
        }
        if (class_exists('UCP_Integrations')) {
            UCP_Integrations::autodetect();
        }
        $settings = self::recommended_safe_settings(wp_parse_args($settings, self::defaults()));
        if (class_exists('UCP_Compat')) {
            $takeover = UCP_Compat::safe_takeover_status($settings);
            update_option('ucp_safe_takeover_status', $takeover, false);
            if (empty($takeover['can_auto_enable'])) {
                $settings['enable_cache'] = 0;
                $settings['enable_preload'] = 0;
                $settings['enable_preload_queue'] = 0;
            }
        }
        $settings['onboarding_completed'] = 0;
        $settings['ui_mode'] = 'simple';
        $settings['allow_wp_config_write'] = 0;
        $settings['allow_dropin_writes'] = 0;
        $settings['allow_browser_cache_rule_writes'] = 0;
        update_option(self::OPTION_KEY, self::normalize($settings, self::defaults()), false);
        update_option('ucp_install_profile_version', '2026-commercial-first-install-v1', false);
    }

    public static function recommended_safe_settings($settings = array()) {
        $settings = wp_parse_args((array) $settings, self::defaults());
        $recommended = array(
            'ui_mode' => 'simple',
            'active_preset' => 'recommended',
            'enable_cache' => 0,
            'cache_mobile_separately' => 1,
            'cache_logged_in' => 0,
            'cache_lifespan' => 10,
            'enable_preload' => 0,
            'enable_preload_queue' => 0,
            'preload_homepage' => 1,
            'preload_sitemaps' => 1,
            'enable_prefetch_links' => 1,
            'enable_html_minify' => 1,
            'enable_html_test_mode'          => 0,
            'remove_html_comments' => 1,
            'enable_css_minify' => 1,
            'enable_js_minify' => 1,
            'enable_css_combine' => 0,
            'enable_js_combine' => 0,
            'enable_delay_js' => 0,
            'enable_used_css' => 0,
            'enable_critical_css' => 0,
            'enable_css_queue' => 0,
            'enable_remote_css_render' => 0,
            'enable_remove_emojis' => 1,
            'enable_disable_embeds' => 1,
            'enable_lazy_images' => 1,
            'enable_lazy_iframes' => 1,
            'enable_youtube_preview' => 0,
            'enable_lazy_background_images' => 0,
            'enable_image_dimensions' => 1,
            'enable_image_optimization' => 0,
            'enable_webp_generation' => 1,
            'enable_avif_generation' => 0,
            'browser_cache_headers' => 0,
            'enable_heartbeat_control' => 1,
            'heartbeat_frequency' => 60,
            'heartbeat_frontend_frequency' => 60,
            'heartbeat_editor_frequency' => 30,
            'heartbeat_backend_frequency' => 60,
            'enable_woocommerce_rules' => 1,
            'enable_cache_tags' => 1,
            'enable_targeted_purge' => 1,
            'purge_on_post_update' => 1,
            'purge_on_comment' => 1,
            'purge_on_theme_switch' => 1,
            'enable_admin_bar' => 1,
            'enable_health_checks' => 1,
            'autopilot_enabled' => 1,
            'enable_cdn_purge' => 0,
            'cdn_provider' => 'none',
            'db_cleanup_optimize_tables' => 0,
            'cache_query_strings' => 0,
            'allow_wp_config_write' => 0,
            'allow_dropin_writes'            => 0,
            'auto_advanced_cache_takeover'   => 1,
            'allow_browser_cache_rule_writes' => 0,
        );
        if (class_exists('UCP_Compat')) {
            $takeover = UCP_Compat::safe_takeover_status($settings);
            if (!empty($takeover['can_auto_enable'])) {
                $recommended['enable_cache'] = 1;
                $recommended['enable_preload'] = 1;
                $recommended['enable_preload_queue'] = 1;
            }
            $recommended['exclude_urls'] = implode("\n", UCP_Compat::get_effective_cache_exclusions($settings));
        }
        return array_merge($settings, $recommended);
    }

    public static function backup_current_settings($reason = 'manual') {
        $backup = array(
            'created_at' => current_time('mysql', true),
            'reason' => sanitize_key((string) $reason),
            'settings' => self::get_all(),
        );
        update_option('ucp_settings_rollback', $backup, false);
        return $backup;
    }

    public static function restore_rollback() {
        $backup = get_option('ucp_settings_rollback', array());
        if (empty($backup['settings']) || !is_array($backup['settings'])) {
            return false;
        }
        update_option(self::OPTION_KEY, self::normalize($backup['settings'], self::defaults()), false);
        return true;
    }
    public static function maybe_apply_performance_migration() {
        $settings = get_option(self::OPTION_KEY, false);
        if (!is_array($settings)) {
            return;
        }

        if ('2026-commercial-phase1' === get_option('ucp_performance_profile_version', '')) {
            return;
        }

        // AI-PATCH: Move upgraded installs to safer low-overhead defaults plus smarter exclusions.
        if (class_exists('UCP_Integrations')) {
            UCP_Integrations::autodetect();
            $settings = UCP_Integrations::apply_autopilot_v2_settings(
                wp_parse_args($settings, self::defaults()),
                UCP_Integrations::detected(),
                class_exists('UCP_Compat') ? UCP_Compat::detected_conflicts() : array()
            );
        }

        $settings['defer_all_js'] = !empty($settings['defer_all_js']) ? 1 : 0;
        $settings['enable_defer_js_fallback'] = !empty($settings['enable_defer_js_fallback']) ? 1 : 0;
        $settings['ui_mode'] = isset($settings['ui_mode']) && 'advanced' === $settings['ui_mode'] ? 'advanced' : 'simple';
        update_option(self::OPTION_KEY, self::normalize(wp_parse_args($settings, self::defaults()), self::defaults()), false);
        update_option('ucp_performance_profile_version', '2026-commercial-phase1', false);
    }
}
