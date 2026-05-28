<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Preload_Schedule_Trait {
    public static function cron_schedules($schedules) {
        if (!isset($schedules['ucp_twohours'])) {
            $schedules['ucp_twohours'] = array(
                'interval' => 2 * HOUR_IN_SECONDS,
                'display'  => __('Every 2 hours', 'ultracache-pro'),
            );
        }
        if (!isset($schedules['ucp_weekly'])) {
            $schedules['ucp_weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => __('Weekly', 'ultracache-pro'),
            );
        }
        return $schedules;
    }

    public static function sync_schedule($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $should_run = !empty($settings['enable_cache']) && !empty($settings['enable_preload']);

        $interval = isset($settings['cache_refresh_interval']) ? (string) $settings['cache_refresh_interval'] : 'off';
        $schedule = 'hourly';
        if ('2hours' === $interval) {
            $schedule = 'ucp_twohours';
        } elseif ('daily' === $interval) {
            $schedule = 'daily';
        } elseif ('weekly' === $interval) {
            $schedule = 'ucp_weekly';
        }
        $enabled = $should_run && 'off' !== $interval;

        if ($enabled && !wp_next_scheduled('ucp_preload_event')) {
            wp_schedule_event(time() + 120, $schedule, 'ucp_preload_event');
        }

        if (!$enabled) {
            wp_clear_scheduled_hook('ucp_preload_event');
        }
    }
}
