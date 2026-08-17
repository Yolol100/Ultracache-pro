<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Options_Migrations_Trait {
    /**
     * Whether the given settings array represents an active PageSpeed Auto profile.
     *
     * The PageSpeed Auto migrations (maybe_upgrade_pagespeed_auto_v2..v12) each only adjust
     * sites that run this profile and must leave custom configurations untouched. The detection
     * was previously duplicated verbatim in every migration; this is the single source of truth.
     *
     * @param array<string,mixed> $settings Settings snapshot from self::get_all().
     * @return bool
     */
    protected static function is_pagespeed_auto_profile($settings) {
        if (!is_array($settings)) {
            return false;
        }
        return !empty($settings['autopilot_enabled'])
            || (isset($settings['active_preset']) && 'pagespeed_auto' === $settings['active_preset']);
    }

    /**
     * Persist an option and distinguish an unchanged value from a failed write.
     *
     * @param string $key   Option key.
     * @param mixed  $value Option value.
     * @return bool
     */
    protected static function persist_migration_option($key, $value) {
        return self::persist_option_value($key, $value);
    }
    public static function maybe_migrate_private_user_cache_keys() {
        $version = '2026-private-user-cache-v3';
        if ($version === get_option('ucp_private_user_cache_key_version', '')) {
            return;
        }
        if (!defined('UCP_CACHE_DIR') || !class_exists('UCP_Helpers')) {
            return;
        }

        if (method_exists('UCP_Helpers', 'invalidate_cache_dirs_check')) {
            UCP_Helpers::invalidate_cache_dirs_check();
        }
        $pattern = trailingslashit(UCP_CACHE_DIR) . 'pages/*-user-*.html*';
        UCP_Helpers::safe_glob_delete($pattern);
        $remaining = UCP_Helpers::safe_glob_files($pattern, 5000);
        if (false !== $remaining && empty($remaining)) {
            self::persist_migration_option('ucp_private_user_cache_key_version', $version);
        }
    }
    public static function maybe_init_defaults() {
        if (false === get_option(self::OPTION_KEY, false)) {
            $defaults = self::normalize(self::defaults(), self::defaults());
            if (add_option(self::OPTION_KEY, $defaults, '', false)) {
                return true;
            }
            return get_option(self::OPTION_KEY, null) === $defaults;
        }

        $settings = get_option(self::OPTION_KEY, array());
        if (is_array($settings) && !array_key_exists('css_delivery_mode', $settings)) {
            if (!empty($settings['enable_used_css'])) {
                $settings['css_delivery_mode'] = 'remove_unused';
            } elseif (!empty($settings['enable_critical_css'])) {
                $settings['css_delivery_mode'] = 'async';
            } else {
                $settings['css_delivery_mode'] = 'none';
            }
            update_option(self::OPTION_KEY, self::normalize($settings, self::defaults()), false);
        }
        if (is_array($settings) && array_key_exists('lazyload_threshprevious', $settings) && !array_key_exists('lazyload_threshold', $settings)) {
            // Migrate the accidental lazyload_threshprevious key from an earlier release without losing saved settings.
            $settings['lazyload_threshold'] = absint($settings['lazyload_threshprevious']);
            unset($settings['lazyload_threshprevious']);
            update_option(self::OPTION_KEY, self::normalize($settings, self::defaults()), false);
        }

        return false;
    }
    public static function maybe_apply_install_profile($force = false) {
        $settings = get_option(self::OPTION_KEY, false);
        if (!is_array($settings)) {
            return;
        }

        if (!$force && '2026-pagespeed-auto-v1' === get_option('ucp_install_profile_version', '')) {
            return;
        }

        if (class_exists('UCP_Integrations')) {
            UCP_Integrations::autodetect();
        }

        $detected = class_exists('UCP_Integrations') ? (array) UCP_Integrations::detected() : array();
        $settings = wp_parse_args($settings, self::defaults());
        $settings = array_merge($settings, class_exists('UCP_Presets') ? UCP_Presets::pagespeed_auto_overrides() : self::rocket_style_default_overrides(), self::automatic_managed_settings());

        $settings['ui_mode'] = isset($settings['ui_mode']) && 'advanced' === $settings['ui_mode'] ? 'advanced' : 'simple';
        $settings['defer_all_js'] = !empty($settings['defer_all_js']) ? 1 : 0;
        $settings['enable_defer_js_fallback'] = !empty($settings['enable_defer_js_fallback']) ? 1 : 0;
        if (!empty($settings['defer_all_js'])) {
            $settings['enable_defer_js_fallback'] = 1;
        }
        $settings = class_exists('UCP_Integrations') ? UCP_Integrations::apply_autopilot_v2_settings($settings, $detected, UCP_Compat::detected_conflicts()) : $settings;
        if (class_exists('UCP_Presets')) {
            $settings = array_merge($settings, UCP_Presets::pagespeed_auto_overrides());
        }

        if (!self::update($settings)) {
            return;
        }
        self::persist_migration_option('ucp_install_profile_version', '2026-pagespeed-auto-v1');
    }
    public static function maybe_apply_runtime_write_and_log_migration() {
        $settings = get_option(self::OPTION_KEY, false);
        if (!is_array($settings)) {
            return;
        }

        if ('2026-runtime-writes-logs-v3' === get_option('ucp_runtime_writes_logs_version', '')) {
            return;
        }

        $settings = wp_parse_args($settings, self::defaults());
        $logs_enabled = !empty($settings['enable_logs']) ? 1 : 0;
        $settings['enable_logs'] = $logs_enabled;
        $settings['enable_diagnostics'] = 1;
        $settings['enable_admin_queue_runner'] = !empty($settings['enable_admin_queue_runner']) ? 1 : 0;

        $preload_exclusions = UCP_Helpers::normalize_multiline(isset($settings['preload_exclude_urls']) ? $settings['preload_exclude_urls'] : '');
        $preload_exclusions = array_merge($preload_exclusions, array(
            'cart', 'checkout', 'winkelwagen', 'afrekenen', 'my-account', 'mijn-account', 'account',
            'order-pay', 'order-received', 'add-payment-method', 'customer-logout', 'wc-ajax', 'wc-api', 'add-to-cart'
        ));
        $settings['preload_exclude_urls'] = implode("\n", array_values(array_unique(array_filter($preload_exclusions, 'strlen'))));

        if (!self::update(self::normalize($settings, $settings))) {
            return;
        }
        self::persist_migration_option('ucp_runtime_writes_logs_version', '2026-runtime-writes-logs-v3');
    }
    /**
     * Require an explicit administrator choice before server-side Google Fonts fetching.
     *
     * Earlier releases enabled local Google Fonts through defaults and automatic presets.
     * Disable that inherited value once; administrators can deliberately select the
     * "Lokaal laden" font mode again after reviewing the external request disclosure.
     *
     * @return void
     */
    public static function maybe_require_local_google_fonts_opt_in_v1() {
        if ('2026-local-google-fonts-opt-in-v1' === get_option('ucp_local_google_fonts_opt_in_version', '')) {
            return;
        }

        $settings = get_option(self::OPTION_KEY, false);
        if (is_array($settings) && !empty($settings['enable_local_google_fonts'])) {
            $settings['enable_local_google_fonts'] = 0;
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_local_google_fonts_opt_in_version', '2026-local-google-fonts-opt-in-v1');
    }

    public static function maybe_apply_queue_repair_migration() {
        if ('2026-queue-url-repair-v1' === get_option('ucp_queue_repair_version', '')) {
            return;
        }

        if (!class_exists('UCP_Jobs') || !method_exists('UCP_Jobs', 'cleanup_unsafe_preload_jobs')) {
            return;
        }

        $result = UCP_Jobs::cleanup_unsafe_preload_jobs(1000);
        if (!is_array($result) || empty($result['ok'])) {
            return;
        }

        self::persist_migration_option('ucp_queue_repair_version', '2026-queue-url-repair-v1');
    }
    public static function maybe_apply_preload_safety_migration() {
        $settings = get_option(self::OPTION_KEY, false);
        if (!is_array($settings)) {
            return;
        }

        if ('2026-preload-safety-v2' === get_option('ucp_preload_safety_version', '')) {
            return;
        }

        $settings = wp_parse_args($settings, self::defaults());
        $preload_exclusions = UCP_Helpers::normalize_multiline(isset($settings['preload_exclude_urls']) ? $settings['preload_exclude_urls'] : '');
        $preload_exclusions = array_merge($preload_exclusions, array(
            '/author/(.*)',
            '/wp-content/(.*)',
            '/uploads/(.*)',
            '/feed/(.*)',
            'feed=',
            'attachment_id=',
            '-zip/',
            '.zip',
            '?attachment_id=',
            '/search/(.*)',
            '?s=',
        ));
        $settings['preload_exclude_urls'] = implode("\n", array_values(array_unique(array_filter($preload_exclusions, 'strlen'))));

        if (!self::update(self::normalize($settings, $settings))) {
            return;
        }
        if (!self::persist_migration_option('ucp_preload_safety_version', '2026-preload-safety-v2')) {
            return;
        }

        if (class_exists('UCP_Jobs') && method_exists('UCP_Jobs', 'cleanup_unsafe_preload_jobs')) {
            UCP_Jobs::cleanup_unsafe_preload_jobs();
        }
    }
    public static function maybe_apply_performance_migration() {
        $settings = get_option(self::OPTION_KEY, false);
        if (!is_array($settings)) {
            return;
        }

        if ('2026-pagespeed-auto-v1' === get_option('ucp_performance_profile_version', '')) {
            return;
        }

        if (!self::is_pagespeed_auto_profile($settings)) {
            self::persist_migration_option('ucp_performance_profile_version', '2026-pagespeed-auto-v1');
            return;
        }

        $settings = array_merge(wp_parse_args($settings, self::defaults()), class_exists('UCP_Presets') ? UCP_Presets::pagespeed_auto_overrides() : self::rocket_style_default_overrides(), self::automatic_managed_settings());
        if (class_exists('UCP_Integrations')) {
            UCP_Integrations::autodetect();
            $settings = UCP_Integrations::apply_autopilot_v2_settings(
                $settings,
                UCP_Integrations::detected(),
                UCP_Compat::detected_conflicts()
            );
        }
        $settings['enable_diagnostics'] = 1;
        $settings['enable_logs'] = 0;
        $settings['enable_cdn'] = 0;

        $settings['ui_mode'] = 'advanced';
        if (!self::update(wp_parse_args($settings, self::defaults()))) {
            return;
        }
        self::persist_migration_option('ucp_performance_profile_version', '2026-pagespeed-auto-v1');
    }
    /**
     * Upgrade PageSpeed Auto defaults for CSS delivery and JS delay behavior.
     *
     * Keeps existing non-PageSpeed configurations untouched.
     */
    public static function maybe_upgrade_pagespeed_auto_v2() {
        if ('2026-pagespeed-auto-v2' === get_option('ucp_performance_profile_version_v2', '')) {
            return;
        }

        $settings = self::get_all();
        $is_pagespeed = self::is_pagespeed_auto_profile($settings);
        if ($is_pagespeed) {
            $settings['active_preset'] = 'pagespeed_auto';
            $settings['enable_used_css'] = 0;
            $settings['enable_used_css_delivery'] = 0;
            $settings['css_delivery_mode'] = 'none';
            $settings['enable_critical_css'] = 0;
            $settings['enable_css_queue'] = 0;
            $settings['enable_delay_js'] = 0;
            $settings['delay_js_mode'] = 'specified';
            $settings['delay_js_safe_mode'] = 1;
            $settings['enable_defer_js_fallback'] = 1;
            $settings['defer_all_js'] = 0;
            $settings['preload_critical_images'] = max(2, absint(isset($settings['preload_critical_images']) ? $settings['preload_critical_images'] : 0));
            $settings['lazyload_exclude_leading_images'] = max(4, absint(isset($settings['lazyload_exclude_leading_images']) ? $settings['lazyload_exclude_leading_images'] : 0));
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_performance_profile_version_v2', '2026-pagespeed-auto-v2');
    }
    /**
     * Upgrade PageSpeed Auto defaults for LCP, CSS delivery and third-party delay behavior.
     */
    public static function maybe_upgrade_pagespeed_auto_v3() {
        if ('2026-pagespeed-auto-v3' === get_option('ucp_performance_profile_version_v3', '')) {
            return;
        }

        $settings = self::get_all();
        $is_pagespeed = self::is_pagespeed_auto_profile($settings);
        if ($is_pagespeed) {
            $overrides = class_exists('UCP_Presets') ? UCP_Presets::pagespeed_auto_overrides() : array();
            $settings = array_merge($settings, $overrides);
            $settings['active_preset'] = 'pagespeed_auto';
            $settings['enable_used_css'] = 0;
            $settings['enable_used_css_delivery'] = 0;
            $settings['css_delivery_mode'] = 'none';
            $settings['enable_critical_css'] = 0;
            $settings['enable_css_queue'] = 0;
            $settings['enable_delay_js'] = 0;
            $settings['delay_js_mode'] = 'specified';
            $settings['delay_js_safe_mode'] = 1;
            $settings['enable_defer_js_fallback'] = 1;
            $settings['defer_all_js'] = 0;
            $settings['preload_critical_images'] = max(3, absint(isset($settings['preload_critical_images']) ? $settings['preload_critical_images'] : 0));
            $settings['lazyload_exclude_leading_images'] = max(4, absint(isset($settings['lazyload_exclude_leading_images']) ? $settings['lazyload_exclude_leading_images'] : 0));
            $settings['enable_lazyload_background_images'] = !empty($settings['enable_lazyload_background_images']) ? 1 : 0;
            $settings['enable_image_optimization'] = 0;
            $settings['enable_webp_generation'] = 0;
            $settings['used_css_max_rules'] = max(3200, absint(isset($settings['used_css_max_rules']) ? $settings['used_css_max_rules'] : 0));
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_performance_profile_version_v3', '2026-pagespeed-auto-v3');
    }
    /**
     * Re-apply browser scan optimizations for older profiles so existing scans immediately refresh LCP and measured delay candidates.
     */
    public static function maybe_upgrade_pagespeed_auto_v4() {
        if ('2026-pagespeed-auto-v4' === get_option('ucp_performance_profile_version_v4', '')) {
            return;
        }

        if (class_exists('UCP_PageSpeed_Browser_Scan') && method_exists('UCP_PageSpeed_Browser_Scan', 'reapply_latest_scan_optimization_settings')) {
            UCP_PageSpeed_Browser_Scan::reapply_latest_scan_optimization_settings();
        }

        self::persist_migration_option('ucp_performance_profile_version_v4', '2026-pagespeed-auto-v4');
    }
    public static function maybe_upgrade_pagespeed_auto_v5() {
        if ('2026-pagespeed-auto-v5' === get_option('ucp_performance_profile_version_v5', '')) {
            return;
        }

        if (class_exists('UCP_PageSpeed_Browser_Scan') && method_exists('UCP_PageSpeed_Browser_Scan', 'reapply_latest_scan_optimization_settings')) {
            UCP_PageSpeed_Browser_Scan::reapply_latest_scan_optimization_settings();
        }

        self::persist_migration_option('ucp_performance_profile_version_v5', '2026-pagespeed-auto-v5');
    }
    /**
     * Upgrade PageSpeed Auto for full-page preload warming and delayed-script preloads.
     * This keeps non-PageSpeed custom configurations untouched.
     */
    public static function maybe_upgrade_pagespeed_auto_v6() {
        if ('2026-pagespeed-auto-v6' === get_option('ucp_performance_profile_version_v6', '')) {
            return;
        }

        $settings = self::get_all();
        $is_pagespeed = self::is_pagespeed_auto_profile($settings);
        if ($is_pagespeed) {
            $settings['enable_light_preload_requests'] = 0;
            $settings['enable_delay_js_preload_delayed_scripts'] = 1;
            $scope = array_values(array_filter(array_map('trim', explode(',', isset($settings['preload_content_scope']) ? (string) $settings['preload_content_scope'] : ''))));
            if (!in_array('posts', $scope, true)) {
                array_unshift($scope, 'posts');
            }
            $settings['preload_content_scope'] = implode(',', array_values(array_unique($scope)));
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_performance_profile_version_v6', '2026-pagespeed-auto-v6');
    }
    /**
     * Upgrade PageSpeed Auto LCP handling for measured background heroes and responsive image preloads.
     * Does not force public RUM monitoring on; it only improves how existing browser/RUM hints are used.
     */
    public static function maybe_upgrade_pagespeed_auto_v7() {
        if ('2026-pagespeed-auto-v7' === get_option('ucp_performance_profile_version_v7', '')) {
            return;
        }

        $settings = self::get_all();
        $is_pagespeed = self::is_pagespeed_auto_profile($settings);
        if ($is_pagespeed) {
            $settings['enable_lazyload_background_images'] = !empty($settings['enable_lazyload_background_images']) ? 1 : 0;
            $settings['preload_critical_images'] = max(2, absint(isset($settings['preload_critical_images']) ? $settings['preload_critical_images'] : 0));
            $settings['lazyload_exclude_leading_images'] = max(2, absint(isset($settings['lazyload_exclude_leading_images']) ? $settings['lazyload_exclude_leading_images'] : 0));
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_performance_profile_version_v7', '2026-pagespeed-auto-v7');
    }
    /**
     * Upgrade PageSpeed Auto asset intelligence defaults.
     * Keeps destructive unload behaviour off; it only enables measurement/test infrastructure.
     */
    public static function maybe_upgrade_pagespeed_auto_v8() {
        if ('2026-pagespeed-auto-v8' === get_option('ucp_performance_profile_version_v8', '')) {
            return;
        }

        $settings = self::get_all();
        $is_pagespeed = self::is_pagespeed_auto_profile($settings);
        if ($is_pagespeed) {
            $settings['enable_delay_js_preload_delayed_scripts'] = 1;
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_performance_profile_version_v8', '2026-pagespeed-auto-v8');
    }
    /**
     * Upgrade PageSpeed Auto with conservative automatic browser resource hints.
     * Uses only local measurements and small limits; it does not add aggressive third-party loading.
     */
    public static function maybe_upgrade_pagespeed_auto_v9() {
        if ('2026-pagespeed-auto-v9' === get_option('ucp_performance_profile_version_v9', '')) {
            return;
        }

        $settings = self::get_all();
        $is_pagespeed = self::is_pagespeed_auto_profile($settings);
        if ($is_pagespeed) {
            $settings['enable_auto_resource_hints'] = 1;
            $settings['enable_auto_font_preloads'] = !empty($settings['enable_auto_font_preloads']) ? 1 : 0;
            $settings['resource_hints_preconnect_limit'] = min(2, max(1, absint(isset($settings['resource_hints_preconnect_limit']) ? $settings['resource_hints_preconnect_limit'] : 2)));
            $settings['resource_hints_dns_limit'] = min(8, max(4, absint(isset($settings['resource_hints_dns_limit']) ? $settings['resource_hints_dns_limit'] : 8)));
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_performance_profile_version_v9', '2026-pagespeed-auto-v9');
    }
    /**
     * Upgrade PageSpeed Auto with CSS/LCP profile infrastructure and preload v2 safety defaults.
     * Keeps aggressive CSS removal disabled unless the existing delivery mode already enables it.
     */
    public static function maybe_upgrade_pagespeed_auto_v10() {
        if ('2026-pagespeed-auto-v10' === get_option('ucp_performance_profile_version_v10', '')) {
            return;
        }

        $settings = self::get_all();
        $is_pagespeed = self::is_pagespeed_auto_profile($settings);
        if ($is_pagespeed) {
            $settings['enable_css_profiles'] = 1;
            $settings['css_profile_max_age_days'] = isset($settings['css_profile_max_age_days']) ? max(7, absint($settings['css_profile_max_age_days'])) : 14;
            $settings['lcp_profile_min_confidence'] = isset($settings['lcp_profile_min_confidence']) ? max(80, absint($settings['lcp_profile_min_confidence'])) : 80;
            $settings['lcp_profile_max_age_days'] = isset($settings['lcp_profile_max_age_days']) ? max(14, absint($settings['lcp_profile_max_age_days'])) : 21;
            $settings['preload_pause_on_high_load'] = 1;
            $settings['preload_menu_urls_limit'] = isset($settings['preload_menu_urls_limit']) ? max(20, absint($settings['preload_menu_urls_limit'])) : 40;
            $settings['preload_recent_purge_limit'] = isset($settings['preload_recent_purge_limit']) ? max(20, absint($settings['preload_recent_purge_limit'])) : 30;

            $safe_css = UCP_Helpers::normalize_multiline(isset($settings['used_css_safelist']) ? $settings['used_css_safelist'] : '');
            $safe_css = array_merge($safe_css, array('elementor-popup', 'elementor-nav-menu', 'woocommerce-checkout', 'woocommerce-cart', 'woocommerce-account', 'order-pay', 'mobile-menu', 'sticky-header', 'swiper', 'slick', 'splide', 'popup', 'modal', 'cookie', 'consent', 'captcha', 'hidden', 'is-active', 'is-visible'));
            $settings['used_css_safelist'] = implode("\n", array_values(array_unique(array_filter($safe_css, 'strlen'))));
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_performance_profile_version_v10', '2026-pagespeed-auto-v10');
    }
    /**
     * Polish PageSpeed Auto safety defaults after the CSS/LCP profile rollout.
     * Extends safelists only; it does not enable aggressive unload or CSS removal.
     */
    public static function maybe_upgrade_pagespeed_auto_v11() {
        if ('2026-pagespeed-auto-v11' === get_option('ucp_performance_profile_version_v11', '')) {
            return;
        }

        $settings = self::get_all();
        $is_pagespeed = self::is_pagespeed_auto_profile($settings);
        if ($is_pagespeed) {
            $safe_css = UCP_Helpers::normalize_multiline(isset($settings['used_css_safelist']) ? $settings['used_css_safelist'] : '');
            $safe_css = array_merge($safe_css, array(
                'elementor-hidden-mobile', 'elementor-hidden-tablet', 'elementor-hidden-desktop', 'elementor-sticky', 'elementor-motion-effects',
                'menu-item-has-children', 'current-menu-item', 'current-menu-parent', 'current-menu-ancestor', 'sub-menu', 'mega-menu',
                'woocommerce-notices-wrapper', 'woocommerce-NoticeGroup', 'wc-block-cart', 'wc-block-checkout', 'wc-block-components', 'wc-block-components-notice-banner',
                'is-sticky', 'is-open', 'is-active', 'is-visible', 'is-expanded', 'aria-expanded', 'hidden-mobile', 'hidden-tablet', 'hidden-desktop',
                'cmplz-', 'cc-window', 'cookie-notice', 'grecaptcha-badge', 'h-captcha', 'cf-turnstile',
                'splide__', 'swiper-', 'slick-', 'fancybox', 'pswp', 'mfp-', 'modal', 'popup'
            ));
            $settings['used_css_safelist'] = implode("
", array_values(array_unique(array_filter($safe_css, 'strlen'))));
            $settings['lcp_profile_min_confidence'] = isset($settings['lcp_profile_min_confidence']) ? max(85, absint($settings['lcp_profile_min_confidence'])) : 85;
            $settings['preload_delay_ms'] = isset($settings['preload_delay_ms']) ? max(250, absint($settings['preload_delay_ms'])) : 500;
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_performance_profile_version_v11', '2026-pagespeed-auto-v11');
    }
    /**
     * Ultimate polish defaults for 11.0.22.
     * Keeps risky features opt-in while updating safety lists and renderer/LCP checks.
     */
    public static function maybe_upgrade_pagespeed_auto_v12() {
        if ('2026-pagespeed-auto-v12' === get_option('ucp_performance_profile_version_v12', '')) {
            return;
        }

        $settings = self::get_all();
        $is_pagespeed = self::is_pagespeed_auto_profile($settings);
        if ($is_pagespeed) {
            $safe_css = UCP_Helpers::normalize_multiline(isset($settings['used_css_safelist']) ? $settings['used_css_safelist'] : '');
            $safe_css = array_merge($safe_css, array(
                ':focus', ':focus-visible', '[aria-expanded]', '[aria-hidden]', '.screen-reader-text', '.sr-only', '.skip-link',
                '.has-modal-open', '.modal-open', '.is-menu-open', '.is-nav-open', '.is-submenu-open', '.is-transitioning',
                '.wc-block-components-form', '.wc-block-components-checkout-step', '.wc-block-components-payment-methods', '.wc-block-components-totals-wrapper',
                '.woocommerce-form-login', '.woocommerce-form-register', '.woocommerce-order-pay', '.woocommerce-order-received',
                '.elementor-nav-menu--dropdown', '.elementor-menu-toggle', '.elementor-popup-modal', '.elementor-lightbox',
                '.swiper-slide-active', '.swiper-slide-visible', '.slick-active', '.splide__slide.is-active', '.mfp-ready', '.pswp--open',
                '.wpcf7-not-valid-tip', '.wpcf7-response-output', '.gfield_error', '.wpforms-error', '.fluentform-error', '.ff-message-success'
            ));
            $settings['used_css_safelist'] = implode("\n", array_values(array_unique(array_filter($safe_css, 'strlen'))));
            $settings['lcp_profile_min_confidence'] = isset($settings['lcp_profile_min_confidence']) ? max(85, absint($settings['lcp_profile_min_confidence'])) : 85;
            $settings['css_profile_max_age_days'] = isset($settings['css_profile_max_age_days']) ? min(30, max(7, absint($settings['css_profile_max_age_days']))) : 14;
            $settings['enable_sensitive_asset_unload_override'] = 0;
            $settings['enable_asset_test_mode'] = !empty($settings['enable_asset_test_mode']) ? 1 : 0;
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_performance_profile_version_v12', '2026-pagespeed-auto-v12');
    }
    /**
     * Apply the automatic conservative baseline to existing installs.
     *
     * This intentionally keeps risky render-changing optimizations off, but makes the
     * required cache, WooCommerce safety, preload, browser-cache and support settings managed.
     *
     * @return void
     */
    public static function maybe_apply_rocket_style_automation_v1() {
        if ('2026-rocket-style-automation-v1' === get_option('ucp_rocket_style_automation_version', '')) {
            return;
        }

        $settings = get_option(self::OPTION_KEY, false);
        if (is_array($settings)) {
            $settings = array_merge(wp_parse_args($settings, self::defaults()), self::automatic_managed_settings());

            $preload_exclusions = UCP_Helpers::normalize_multiline(isset($settings['preload_exclude_urls']) ? $settings['preload_exclude_urls'] : '');
            $preload_exclusions = array_merge($preload_exclusions, array(
                'cart', 'checkout', 'winkelwagen', 'afrekenen', 'my-account', 'mijn-account', 'account',
                'order-pay', 'order-received', 'add-payment-method', 'customer-logout', 'wc-ajax', 'wc-api', 'add-to-cart',
                'wp-json', '/wp-json/', '/wc/', '/wc-api/', '?wc-ajax=', '?add-to-cart='
            ));
            $settings['preload_exclude_urls'] = implode("\n", array_values(array_unique(array_filter($preload_exclusions, 'strlen'))));

            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_rocket_style_automation_version', '2026-rocket-style-automation-v1');
    }
    /**
     * Convert only untouched built-in cart/checkout rules to exact path-segment matching.
     *
     * Custom rules, changed actions and changed values are deliberately left untouched.
     *
     * @return void
     */
    public static function maybe_upgrade_exact_transaction_rules_v1() {
        $version = '2026-exact-transaction-rules-v1';
        if ($version === get_option('ucp_exact_transaction_rules_version', '')) {
            return;
        }

        $settings = get_option(self::OPTION_KEY, false);
        if (!is_array($settings)) {
            return;
        }

        $rules = isset($settings['asset_rules']) && is_array($settings['asset_rules']) ? $settings['asset_rules'] : array();
        $built_in_rules = array(
            'rule_checkout_cache' => array('/checkout', 'checkout', 'disable_cache'),
            'rule_checkout_delay' => array('/checkout', 'checkout', 'disable_delay_js'),
            'rule_checkout_css' => array('/checkout', 'checkout', 'disable_css_optimization'),
            'rule_checkout_js' => array('/checkout', 'checkout', 'disable_js_optimization'),
            'rule_cart_cache' => array('/cart', 'cart', 'disable_cache'),
            'rule_cart_speculation' => array('/cart', 'cart', 'disable_speculation'),
            'rule_cart_delay' => array('/cart', 'cart', 'disable_delay_js'),
            'rule_cart_css' => array('/cart', 'cart', 'disable_css_optimization'),
            'rule_cart_js' => array('/cart', 'cart', 'disable_js_optimization'),
        );
        $changed = false;

        foreach ($rules as $index => $rule) {
            if (!is_array($rule) || empty($rule['id']) || !isset($built_in_rules[$rule['id']])) {
                continue;
            }
            list($old_value, $new_value, $expected_action) = $built_in_rules[$rule['id']];
            if (
                isset($rule['scope'], $rule['value'], $rule['action'])
                && 'path_contains' === $rule['scope']
                && $old_value === $rule['value']
                && $expected_action === $rule['action']
            ) {
                $rules[$index]['scope'] = 'path_segment';
                $rules[$index]['value'] = $new_value;
                $changed = true;
            }
        }

        if ($changed) {
            $settings['asset_rules'] = $rules;
            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_exact_transaction_rules_version', $version);
    }

    /**
     * Normalize hidden automatic defaults and Speculative Loading policy for 11.2.4.
     *
     * Existing installs could have stale hidden values from earlier test builds. This
     * migration makes automatic technical defaults effective without enabling risky
     * external features, and converts the old speculation toggle pair to the new
     * Core-aware policy model.
     *
     * @return void
     */
    public static function maybe_upgrade_refactor_1124() {
        if ('2026-refactor-1124' === get_option('ucp_refactor_1124_version', '')) {
            return;
        }

        $settings = get_option(self::OPTION_KEY, false);
        if (is_array($settings)) {
            $settings = wp_parse_args($settings, self::defaults());

            $settings['enable_async_image_optimization'] = 1;
            $settings['enable_viewport_images'] = 1;
            $settings['used_css_auto_refresh_days'] = isset($settings['used_css_auto_refresh_days']) ? min(365, max(0, absint($settings['used_css_auto_refresh_days']))) : 30;
            if (0 === absint($settings['used_css_auto_refresh_days'])) {
                $settings['used_css_auto_refresh_days'] = 30;
            }

            if (empty($settings['speculative_loading_mode']) || !in_array($settings['speculative_loading_mode'], array('core', 'enhanced', 'prerender', 'off'), true)) {
                if (!empty($settings['enable_speculative_loading'])) {
                    $settings['speculative_loading_mode'] = ('prerender' === (isset($settings['speculation_mode']) ? $settings['speculation_mode'] : 'prefetch')) ? 'prerender' : 'enhanced';
                } else {
                    $settings['speculative_loading_mode'] = 'core';
                }
            }

            if (!self::update($settings)) {
                return;
            }
        }

        self::persist_migration_option('ucp_refactor_1124_version', '2026-refactor-1124');
    }
}
