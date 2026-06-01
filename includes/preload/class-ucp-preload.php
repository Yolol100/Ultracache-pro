<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// Consolidated from includes/preload/traits/ucp-preload-schedule-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
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

// Consolidated from includes/preload/traits/ucp-preload-admin-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
trait UCP_Preload_Admin_Trait {
    public function handle_manual_preload() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_run_preload');

        if (UCP_Options::get('enable_preload_queue') && class_exists('UCP_Jobs')) {
            $queued = $this->seed_preload_queue();
            wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=cache&preload_queued=' . absint($queued)));
            exit;
        }

        $this->run_direct();
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=cache&preloaded=1'));
        exit;
    }
}

class UCP_Preload {
    use UCP_Preload_Schedule_Trait;
    use UCP_Preload_Runner_Trait;
    use UCP_Preload_Safety_Trait;
    use UCP_Preload_Collector_Trait;
    use UCP_Preload_Admin_Trait;

    public static function run_now() {
        $instance = UCP_Helpers::new_without_constructor(__CLASS__);
        $instance->run_preload();
    }

    public function __construct() {
        add_action('ucp_preload_event', array($this, 'run_preload'));
        add_action('admin_post_ucp_run_preload', array($this, 'handle_manual_preload'));
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
        self::sync_schedule();
    }
}
