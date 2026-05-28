<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Options_Lifecycle_Trait {
    public static function snapshot_option_key() {
        return 'ucp_settings_snapshots';
    }

    public static function settings_snapshots() {
        $snapshots = get_option(self::snapshot_option_key(), array());
        return is_array($snapshots) ? $snapshots : array();
    }

    public static function create_settings_snapshot($settings = null, $context = 'manual') {
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
        update_option(self::snapshot_option_key(), $snapshots, false);
        return $id;
    }

    public static function restore_settings_snapshot($snapshot_id) {
        $snapshot_id = sanitize_text_field((string) $snapshot_id);
        foreach (self::settings_snapshots() as $snapshot) {
            if (!empty($snapshot['id']) && hash_equals((string) $snapshot['id'], $snapshot_id) && !empty($snapshot['settings']) && is_array($snapshot['settings'])) {
                self::create_settings_snapshot(self::get_all(), 'before_restore');
                self::update($snapshot['settings']);
                return true;
            }
        }
        return false;
    }

    public static function handle_option_updated($previous_settings, $new_settings) {
        if (is_array($previous_settings) && !empty($previous_settings) && $previous_settings !== $new_settings) {
            self::create_settings_snapshot($previous_settings, 'auto_save');
        }
        self::after_settings_save($new_settings, $previous_settings);
    }

    public static function after_settings_save($new_settings, $previous_settings = array()) {
        $new_settings = self::normalize($new_settings, $previous_settings);
        $previous_settings = wp_parse_args((array) $previous_settings, self::defaults());

        UCP_Helpers::maybe_write_browser_cache_rules();
        UCP_Helpers::write_dropin_config();
        if (!empty($new_settings['enable_cache'])) {
            UCP_Helpers::write_advanced_cache_stub();
        }
        if (empty($new_settings['enable_cache'])) {
            UCP_Helpers::remove_own_advanced_cache_stub();
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
                'Instellingen opgeslagen.',
                array(
                    'ui_mode' => isset($new_settings['ui_mode']) ? $new_settings['ui_mode'] : 'simple',
                    'preset'  => isset($new_settings['active_preset']) ? $new_settings['active_preset'] : '',
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

    public static function maybe_init_defaults() {
        if (false === get_option(self::OPTION_KEY, false)) {
            add_option(self::OPTION_KEY, self::normalize(self::defaults(), self::defaults()), '', false);
            return true;
        }

        $settings = get_option(self::OPTION_KEY, array());
        if (is_array($settings) && array_key_exists('lazyload_threshprevious', $settings) && !array_key_exists('lazyload_threshold', $settings)) {
            // Note: migrate typo introduced by an old->previous replacement without losing existing settings.
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
        $settings = array_merge($settings, class_exists('UCP_Presets') ? UCP_Presets::pagespeed_auto_overrides() : self::rocket_style_default_overrides());

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

        update_option(self::OPTION_KEY, $settings, false);
        update_option('ucp_install_profile_version', '2026-pagespeed-auto-v1', false);
    }


    public static function maybe_apply_runtime_write_and_log_migration() {
        $settings = get_option(self::OPTION_KEY, false);
        if (!is_array($settings)) {
            return;
        }

        if ('2026-runtime-writes-logs-v2' === get_option('ucp_runtime_writes_logs_version', '')) {
            return;
        }

        $settings = wp_parse_args($settings, self::defaults());
        // H1: do not force drop-in / wp-config writes off here any more. Preserve whatever the
        // site already has (existing sites keep their choice; fresh installs inherit the enabled
        // default). Admins can still disable these in the UI or via filters.
        $settings['enable_logs'] = 1;
        if (class_exists('UCP_Presets')) {
            $settings = array_merge($settings, UCP_Presets::pagespeed_auto_overrides());
        }
        $settings['enable_diagnostics'] = 1;
        $settings['enable_admin_queue_runner'] = 1;

        $preload_exclusions = UCP_Helpers::normalize_multiline(isset($settings['preload_exclude_urls']) ? $settings['preload_exclude_urls'] : '');
        $preload_exclusions = array_merge($preload_exclusions, array(
            'cart', 'checkout', 'winkelwagen', 'afrekenen', 'my-account', 'mijn-account', 'account',
            'order-pay', 'order-received', 'add-payment-method', 'customer-logout', 'wc-ajax', 'wc-api', 'add-to-cart'
        ));
        $settings['preload_exclude_urls'] = implode("\n", array_values(array_unique(array_filter($preload_exclusions, 'strlen'))));

        update_option(self::OPTION_KEY, self::normalize($settings, $settings), false);
        update_option('ucp_runtime_writes_logs_version', '2026-runtime-writes-logs-v2', false);
    }


    public static function maybe_apply_queue_repair_migration() {
        if ('2026-queue-url-repair-v1' === get_option('ucp_queue_repair_version', '')) {
            return;
        }

        if (class_exists('UCP_Jobs') && method_exists('UCP_Jobs', 'cleanup_unsafe_preload_jobs')) {
            UCP_Jobs::cleanup_unsafe_preload_jobs(1000);
        }

        update_option('ucp_queue_repair_version', '2026-queue-url-repair-v1', false);
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

        update_option(self::OPTION_KEY, self::normalize($settings, $settings), false);
        update_option('ucp_preload_safety_version', '2026-preload-safety-v2', false);

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

        $settings = array_merge(wp_parse_args($settings, self::defaults()), class_exists('UCP_Presets') ? UCP_Presets::pagespeed_auto_overrides() : self::rocket_style_default_overrides());
        if (class_exists('UCP_Integrations')) {
            UCP_Integrations::autodetect();
            $settings = UCP_Integrations::apply_autopilot_v2_settings(
                $settings,
                UCP_Integrations::detected(),
                UCP_Compat::detected_conflicts()
            );
        }
        $settings['enable_diagnostics'] = 1;
        $settings['enable_logs'] = 1;
        $settings['enable_cdn'] = 0;

        $settings['ui_mode'] = 'advanced';
        update_option(self::OPTION_KEY, wp_parse_args($settings, self::defaults()), false);
        update_option('ucp_performance_profile_version', '2026-pagespeed-auto-v1', false);
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
        $is_pagespeed = !empty($settings['autopilot_enabled']) || (isset($settings['active_preset']) && 'pagespeed_auto' === $settings['active_preset']);
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
            self::update($settings);
        }

        update_option('ucp_performance_profile_version_v2', '2026-pagespeed-auto-v2', false);
    }

    /**
     * Upgrade PageSpeed Auto defaults for LCP, CSS delivery and third-party delay behavior.
     */
    public static function maybe_upgrade_pagespeed_auto_v3() {
        if ('2026-pagespeed-auto-v3' === get_option('ucp_performance_profile_version_v3', '')) {
            return;
        }

        $settings = self::get_all();
        $is_pagespeed = !empty($settings['autopilot_enabled']) || (isset($settings['active_preset']) && 'pagespeed_auto' === $settings['active_preset']);
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
            $settings['enable_lazyload_background_images'] = 1;
            $settings['enable_image_optimization'] = 0;
            $settings['enable_webp_generation'] = 0;
            $settings['used_css_max_rules'] = max(3200, absint(isset($settings['used_css_max_rules']) ? $settings['used_css_max_rules'] : 0));
            self::update($settings);
        }

        update_option('ucp_performance_profile_version_v3', '2026-pagespeed-auto-v3', false);
    }

    /**
     * Re-apply browser scan optimizations after repair7 so old scans immediately fix LCP and measured delay candidates.
     */
    public static function maybe_upgrade_pagespeed_auto_v4() {
        if ('2026-pagespeed-auto-v4' === get_option('ucp_performance_profile_version_v4', '')) {
            return;
        }

        if (class_exists('UCP_PageSpeed_Browser_Scan') && method_exists('UCP_PageSpeed_Browser_Scan', 'reapply_latest_scan_optimization_settings')) {
            UCP_PageSpeed_Browser_Scan::reapply_latest_scan_optimization_settings();
        }

        update_option('ucp_performance_profile_version_v4', '2026-pagespeed-auto-v4', false);
    }

    public static function maybe_upgrade_pagespeed_auto_v5() {
        if ('2026-pagespeed-auto-v5' === get_option('ucp_performance_profile_version_v5', '')) {
            return;
        }

        if (class_exists('UCP_PageSpeed_Browser_Scan') && method_exists('UCP_PageSpeed_Browser_Scan', 'reapply_latest_scan_optimization_settings')) {
            UCP_PageSpeed_Browser_Scan::reapply_latest_scan_optimization_settings();
        }

        update_option('ucp_performance_profile_version_v5', '2026-pagespeed-auto-v5', false);
    }


}
