<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin-owned queue table queries use controlled table constants and prepared/sanitized values.

trait UCP_Jobs_Schedule_Trait {
    public static function sync_schedule($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $should_run = !empty($settings['enable_css_queue']) || !empty($settings['enable_preload_queue']) || !empty($settings['enable_health_checks']) || !empty($settings['enable_cloud']) || !empty($settings['enable_cloudflare_apo_mode']) || self::has_due_jobs(false);
        self::ensure_cron_schedule_registered();
        $event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event(self::CRON_HOOK) : false;

        if ($should_run) {
            if ($event && 'ucp_one_minute' !== $event->schedule) {
                wp_unschedule_event($event->timestamp, self::CRON_HOOK, (array) $event->args);
                $event = false;
            }
            if (!$event && !wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + 60, 'ucp_one_minute', self::CRON_HOOK);
            }
        }

        if (!$should_run) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    public static function register_schedule($schedules) {
        if (!isset($schedules['ucp_one_minute'])) {
            $schedules['ucp_one_minute'] = array(
                'interval' => 60,
                'display'  => __('Elke minuut', 'ultracache-pro'),
            );
        }
        if (!isset($schedules['ucp_five_minutes'])) {
            $schedules['ucp_five_minutes'] = array(
                'interval' => 300,
                'display'  => __('Elke 5 minuten', 'ultracache-pro'),
            );
        }
        return $schedules;
    }

    protected static function runner_lock_option_name() {
        return 'ucp_jobs_runner_lock';
    }
    public static function ensure_cron_schedule_registered() {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
        wp_get_schedules();
    }

    protected static function acquire_runner_lock($ttl) {
        global $wpdb;

        $ttl = max(60, absint($ttl));
        $token = wp_generate_password(24, false);
        $lock  = array(
            'token'      => $token,
            'expires_at' => time() + $ttl,
        );
        $option_name = self::runner_lock_option_name();

        if (add_option($option_name, $lock, '', false)) {
            return $token;
        }

        $current = get_option($option_name, array());
        $expires_at = isset($current['expires_at']) ? (int) $current['expires_at'] : 0;
        if ($expires_at >= time()) {
            return false;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $updated = $wpdb->update(
            $wpdb->options,
            array('option_value' => maybe_serialize($lock)),
            array(
                'option_name'  => $option_name,
                'option_value' => maybe_serialize($current),
            ),
            array('%s'),
            array('%s', '%s')
        );

        return $updated ? $token : false;
    }

    protected static function release_runner_lock($token = '') {
        global $wpdb;

        $option_name = self::runner_lock_option_name();
        $current = get_option($option_name, array());
        if (empty($current['token']) || !hash_equals((string) $current['token'], (string) $token)) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $wpdb->delete(
            $wpdb->options,
            array(
                'option_name'  => $option_name,
                'option_value' => maybe_serialize($current),
            ),
            array('%s', '%s')
        );
        wp_cache_delete($option_name, 'options');
        wp_cache_delete('alloptions', 'options');
    }
}
