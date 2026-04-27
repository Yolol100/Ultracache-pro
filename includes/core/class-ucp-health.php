<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Health {
    const CRON_HOOK = 'ucp_health_check_event';
    const OPTION_KEY = 'ucp_health_snapshot';

    public static function bootstrap() {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'run_checks'));
        self::sync_schedule();
    }

    public static function sync_schedule($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $should_run = !empty($settings['enable_health_checks']);

        if ($should_run && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 240, 'hourly', self::CRON_HOOK);
        }

        if (!$should_run) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    public static function register_schedule($schedules) {
        $schedules['ucp_five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Elke 5 minuten', 'ultracache-pro'),
        );
        return $schedules;
    }

    public static function run_checks() {
        $snapshot = array(
            'generated_at'      => current_time('mysql', true),
            'cache_dir_writable'=> is_writable(UCP_CACHE_DIR),
            'advanced_cache'    => file_exists(WP_CONTENT_DIR . '/advanced-cache.php'),
            'cache_conflicts'   => class_exists('UCP_Compat') ? UCP_Compat::detected_conflicts() : array(),
            'jobs_pending'      => UCP_Jobs::count_by_status('pending'),
            'jobs_failed'       => UCP_Jobs::count_by_status('failed'),
            'browser_cache'     => (bool) UCP_Options::get('browser_cache_headers'),
            'object_cache'      => UCP_Helpers::has_persistent_object_cache(),
        );
        update_option(self::OPTION_KEY, $snapshot, false);
        ucp_noop('info', 'health', 'snapshot_refreshed', 'Controle bijgewerkt.', $snapshot);
    }

    public static function latest() {
        return wp_parse_args(get_option(self::OPTION_KEY, array()), array(
            'generated_at'       => '',
            'cache_dir_writable' => false,
            'advanced_cache'     => false,
            'cache_conflicts'    => array(),
            'jobs_pending'       => 0,
            'jobs_failed'        => 0,
            'logs_recent'        => 0,
            'browser_cache'      => false,
            'object_cache'       => false,
        ));
    }
}
