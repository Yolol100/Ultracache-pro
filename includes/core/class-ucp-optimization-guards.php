<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central guardrails for feature combinations that premium cache plugins treat
 * as mutually exclusive or advanced-only.
 *
 * The goal is not to remove options, but to let safer execution models win:
 * Delay JS keeps script order, Used/Critical CSS owns CSS delivery, and Combine
 * modes remain opt-in advanced tools.
 */
class UCP_Optimization_Guards {
    /**
     * Register settings guardrails.
     *
     * @return void
     */
    public static function bootstrap() {
        add_filter('pre_update_option_ucp_settings', array(__CLASS__, 'guard_settings'), 20, 3);
    }

    /**
     * Apply safe feature relationship rules before settings are persisted.
     *
     * @param mixed  $value     New option value.
     * @param mixed  $old_value Old option value.
     * @param string $option    Option name.
     * @return mixed
     */
    public static function guard_settings($value, $old_value, $option) {
        if (!is_array($value)) {
            return $value;
        }

        $value = self::sync_testing_mode_aliases($value);
        $value = self::guard_css_delivery($value);
        $value = self::guard_js_delivery($value);
        $value = self::guard_advanced_combine_modes($value);

        return apply_filters('ucp_optimization_guarded_settings', $value, $old_value, $option);
    }

    /**
     * Keep the new generic Testing Mode and legacy asset test flag aligned.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function sync_testing_mode_aliases(array $settings) {
        $testing = !empty($settings['testing_mode']) || !empty($settings['enable_asset_test_mode']);
        $settings['testing_mode'] = $testing ? 1 : 0;
        $settings['enable_asset_test_mode'] = $testing ? 1 : 0;
        return $settings;
    }

    /**
     * Used CSS and Critical/async CSS are delivery modes, so CSS Combine should
     * not compete with them.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function guard_css_delivery(array $settings) {
        $css_mode = isset($settings['css_delivery_mode']) ? (string) $settings['css_delivery_mode'] : 'none';
        $uses_css_delivery = 'none' !== $css_mode || !empty($settings['enable_used_css']) || !empty($settings['enable_used_css_delivery']) || !empty($settings['enable_critical_css']);

        if ($uses_css_delivery) {
            $settings['enable_css_combine'] = 0;
            $settings['enable_css_queue'] = 1;
        }

        return $settings;
    }

    /**
     * Delay JS and native script strategy both depend on preserving individual
     * scripts and their order, so JS Combine stays off when either is active.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function guard_js_delivery(array $settings) {
        if (!empty($settings['enable_delay_js']) || !empty($settings['enable_native_script_strategy'])) {
            $settings['enable_js_combine'] = 0;
        }

        if (!empty($settings['enable_js_combine'])) {
            $settings['enable_js_minify'] = 1;
            $settings['allow_experimental_js_minify'] = 1;
        }

        return $settings;
    }

    /**
     * Combine modes stay advanced-only. This mirrors how modern cache plugins
     * keep combine available, but avoid enabling it silently on complex sites.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function guard_advanced_combine_modes(array $settings) {
        if (empty($settings['show_advanced_options'])) {
            $settings['enable_css_combine'] = 0;
            $settings['enable_js_combine'] = 0;
        }

        return $settings;
    }
}
