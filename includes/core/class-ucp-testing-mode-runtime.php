<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Smooth frontend safety layer for Testing Mode.
 *
 * This keeps the existing module architecture intact: admins can preview active
 * optimization settings, while regular visitors keep receiving the stable public
 * output until Testing Mode is disabled again.
 */
class UCP_Testing_Mode_Runtime {
    /**
     * Register late enough so normal modules can attach their callbacks first.
     *
     * @return void
     */
    public static function bootstrap() {
        add_action('init', array(__CLASS__, 'maybe_manage_expiration'), 0);
        add_action('init', array(__CLASS__, 'maybe_apply_frontend_guard'), PHP_INT_MAX);
    }

    /**
     * Create or expire the bounded Testing Mode preview window.
     *
     * @return void
     */
    public static function maybe_manage_expiration() {
        if (!class_exists('UCP_Options')) {
            return;
        }

        $settings = UCP_Options::get_all();
        $active = !empty($settings['testing_mode']) || !empty($settings['enable_asset_test_mode']);
        if (!$active) {
            delete_option('ucp_testing_mode_started_at');
            delete_option('ucp_testing_mode_expires_at');
            return;
        }

        $expires_at = absint(get_option('ucp_testing_mode_expires_at', 0));
        if (0 === $expires_at) {
            $started_at = time();
            $ttl = class_exists('UCP_Helpers') ? UCP_Helpers::testing_mode_ttl_seconds() : 4 * HOUR_IN_SECONDS;
            update_option('ucp_testing_mode_started_at', $started_at, false);
            update_option('ucp_testing_mode_expires_at', $started_at + $ttl, false);
            return;
        }
        if (time() < $expires_at) {
            return;
        }

        $settings['testing_mode'] = 0;
        $settings['enable_asset_test_mode'] = 0;
        if (!UCP_Options::update($settings)) {
            return;
        }
        update_option('ucp_testing_mode_expired_at', time(), false);
        set_transient('ucp_testing_mode_expired_notice', 1, HOUR_IN_SECONDS);
    }

    /**
     * Remove public-facing optimization callbacks during Testing Mode.
     *
     * @return void
     */
    public static function maybe_apply_frontend_guard() {
        if (!class_exists('UCP_Helpers') || UCP_Helpers::frontend_optimizations_allowed()) {
            return;
        }

        if (is_admin() || (function_exists('wp_doing_cron') && wp_doing_cron()) || (defined('WP_CLI') && WP_CLI)) {
            return;
        }

        foreach (self::frontend_hooks() as $hook) {
            self::remove_ucp_callbacks_from_hook($hook);
        }

        do_action('ucp_testing_mode_frontend_guard_applied');
    }

    /**
     * Frontend hooks that may alter public output or cacheable frontend responses.
     *
     * @return array
     */
    protected static function frontend_hooks() {
        return apply_filters('ucp_testing_mode_guarded_hooks', array(
            'template_redirect',
            'wp_enqueue_scripts',
            'wp_print_footer_scripts',
            'wp_head',
            'wp_footer',
            'the_content',
            'widget_text',
            'post_thumbnail_html',
            'wp_get_attachment_image_attributes',
            'script_loader_tag',
            'style_loader_tag',
            'script_loader_src',
            'style_loader_src',
            'wp_resource_hints',
            'ucp_process_html',
            'rest_pre_dispatch',
            'rest_post_dispatch',
        ));
    }

    /**
     * Classes whose frontend callbacks are considered optimization output.
     *
     * @return array
     */
    protected static function frontend_classes() {
        return apply_filters('ucp_testing_mode_guarded_classes', array(
            'UCP_Cache',
            'UCP_Assets',
            'UCP_CSS',
            'UCP_Optimizer',
            'UCP_Modules',
            'UCP_Edge',
            'UCP_REST_Cache',
            'UCP_Fragment_Cache',
            'UCP_CWV',
            'UCP_Fonts',
        ));
    }

    /**
     * Remove callbacks from a hook when their object belongs to a guarded class.
     *
     * @param string $hook Hook name.
     * @return void
     */
    protected static function remove_ucp_callbacks_from_hook($hook) {
        global $wp_filter;

        if (empty($wp_filter[$hook]) || !is_object($wp_filter[$hook]) || empty($wp_filter[$hook]->callbacks)) {
            return;
        }

        $classes = self::frontend_classes();
        foreach ((array) $wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ((array) $callbacks as $callback) {
                if (empty($callback['function']) || !is_array($callback['function']) || empty($callback['function'][0])) {
                    continue;
                }

                $object = $callback['function'][0];
                if (!is_object($object)) {
                    continue;
                }

                foreach ($classes as $class) {
                    if ($object instanceof $class) {
                        remove_filter($hook, $callback['function'], (int) $priority);
                        break;
                    }
                }
            }
        }
    }
}
