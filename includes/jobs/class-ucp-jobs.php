<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Jobs {
    use UCP_Jobs_Schedule_Trait;
    use UCP_Jobs_Payload_Trait;
    use UCP_Jobs_Repository_Trait;
    use UCP_Jobs_Runner_Trait;
    use UCP_Jobs_Admin_Actions_Trait;

    const CRON_HOOK = 'ucp_jobs_event';

    /**
     * Whether the current run was manually forced by an admin action.
     * Used to bypass stale CSS artifact retry locks during explicit retries.
     *
     * @var bool
     */
    protected $force_current_run = false;

    public function __construct() {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
        add_action(self::CRON_HOOK, array($this, 'run_queue'));
        add_action('admin_post_ucp_run_jobs', array($this, 'handle_manual_run'));
        add_action('admin_post_ucp_seed_jobs', array($this, 'handle_seed_jobs'));
        add_action('shutdown', array($this, 'maybe_run_queue_on_admin_shutdown'), 1000);
        self::sync_schedule();
    }
}
