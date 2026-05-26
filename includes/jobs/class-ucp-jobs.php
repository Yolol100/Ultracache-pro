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

    /**
     * @param bool $skip_hook_registration Pass true to construct an instance without
     *                                     registering WordPress hooks. Used by short-lived
     *                                     callers (e.g. REST actions) that only need to
     *                                     invoke the runner without leaking duplicate hooks
     *                                     into the current request.
     */
    public function __construct($skip_hook_registration = false) {
        if ($skip_hook_registration) {
            return;
        }
        add_action(self::CRON_HOOK, array($this, 'run_queue'));
        add_action('admin_post_ucp_run_jobs', array($this, 'handle_manual_run'));
        add_action('admin_post_ucp_seed_jobs', array($this, 'handle_seed_jobs'));
        add_action('shutdown', array($this, 'maybe_run_queue_on_admin_shutdown'), 1000);
        // sync_schedule() invokes ensure_cron_schedule_registered() which adds the
        // cron_schedules filter, so we do not register it twice here.
        self::sync_schedule();
    }
}
