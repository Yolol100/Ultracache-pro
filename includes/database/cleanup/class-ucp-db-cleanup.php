<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/ucp-db-cleanup-schedule-trait.php';
require_once __DIR__ . '/ucp-db-cleanup-counts-trait.php';
require_once __DIR__ . '/ucp-db-cleanup-runner-trait.php';

class UCP_DB_Cleanup {
    use UCP_DB_Cleanup_Schedule_Trait;
    use UCP_DB_Cleanup_Counts_Trait;
    use UCP_DB_Cleanup_Runner_Trait;

    const CRON_HOOK = 'ucp_db_cleanup_event';

    public function __construct() {
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_cleanup'));
        add_action('admin_post_ucp_run_db_cleanup', array($this, 'handle_manual_cleanup'));
    }
}
