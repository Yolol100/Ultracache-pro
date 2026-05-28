<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned diagnostics/maintenance queries; caching would make these admin metrics stale.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_DB_Cleanup_Schedule_Trait {
    public static function cron_schedules($schedules) {
        if (!isset($schedules['ucp_monthly'])) {
            $schedules['ucp_monthly'] = array(
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __('Monthly', 'ultracache-pro'),
            );
        }
        return $schedules;
    }

    protected static function table_exists($table) {
        global $wpdb;
        if (!UCP_Helpers::is_safe_table_name($table)) {
            return false;
        }
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function sync_schedule($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $frequency = isset($settings['db_cleanup_frequency']) ? (string) $settings['db_cleanup_frequency'] : 'off';
        $enabled = !empty($settings['enable_db_cleanup']) && in_array($frequency, array('daily', 'weekly', 'monthly'), true);
        if ($enabled && !wp_next_scheduled(self::CRON_HOOK)) {
            $schedule = 'monthly' === $frequency ? 'ucp_monthly' : $frequency;
            wp_schedule_event(time() + HOUR_IN_SECONDS, $schedule, self::CRON_HOOK);
        }
        if (!$enabled) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }
}
