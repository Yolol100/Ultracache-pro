<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Preload {
    use UCP_Preload_Schedule_Trait;
    use UCP_Preload_Runner_Trait;
    use UCP_Preload_Safety_Trait;
    use UCP_Preload_Collector_Trait;
    use UCP_Preload_Admin_Trait;

    public static function run_now() {
        $instance = new self();
        $instance->run_preload();
    }

    public function __construct() {
        add_action('ucp_preload_event', array($this, 'run_preload'));
        add_action('admin_post_ucp_run_preload', array($this, 'handle_manual_preload'));
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
        self::sync_schedule();
    }
}
