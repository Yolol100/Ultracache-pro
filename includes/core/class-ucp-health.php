<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
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
        if (!isset($schedules['ucp_five_minutes'])) {
            $schedules['ucp_five_minutes'] = array(
                'interval' => 300,
                'display'  => __('Elke 5 minuten', 'ultracache-pro'),
            );
        }
        return $schedules;
    }

    public static function run_checks() {
        $snapshot = array(
            'generated_at'      => current_time('mysql', true),
            'cache_dir_writable'=> wp_is_writable(UCP_CACHE_DIR),
            'advanced_cache'    => file_exists(WP_CONTENT_DIR . '/advanced-cache.php'),
            'wp_cache'          => UCP_Helpers::has_valid_wp_cache_constant(),
            'dropin_config'     => class_exists('UCP_Helpers') ? file_exists(UCP_Helpers::dropin_config_path()) : false,
            'cache_conflicts'   => UCP_Compat::detected_conflicts(),
            'jobs_pending'      => UCP_Jobs::count_by_status('pending'),
            'jobs_failed'       => UCP_Jobs::count_by_status('failed'),
            'logs_recent'       => count(UCP_Logger::recent(10)),
            'cloud_configured'  => (bool) UCP_Options::get('cloud_endpoint'),
            'edge_configured'   => (bool) UCP_Options::get('cloudflare_zone_id'),
            'browser_cache'     => (bool) UCP_Options::get('browser_cache_headers'),
            'object_cache'      => UCP_Helpers::has_persistent_object_cache(),
        );
        update_option(self::OPTION_KEY, $snapshot, false);
        UCP_Logger::log('info', 'health', 'snapshot_refreshed', 'Controle bijgewerkt.', $snapshot);
    }

    public static function latest() {
        return wp_parse_args(get_option(self::OPTION_KEY, array()), array(
            'generated_at'       => '',
            'cache_dir_writable' => false,
            'advanced_cache'     => false,
            'wp_cache'           => false,
            'dropin_config'      => false,
            'cache_conflicts'    => array(),
            'jobs_pending'       => 0,
            'jobs_failed'        => 0,
            'logs_recent'        => 0,
            'cloud_configured'   => false,
            'edge_configured'    => false,
            'browser_cache'      => false,
            'object_cache'       => false,
        ));
    }
}
