<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Scheduled maintenance works on validated plugin-owned custom tables and sanitized retention values.

require_once __DIR__ . '/maintenance/ucp-maintenance-cleanup-trait.php';
require_once __DIR__ . '/maintenance/ucp-maintenance-privacy-trait.php';

class UCP_Maintenance {
    use UCP_Maintenance_Schedule_Trait;
    use UCP_Maintenance_Cleanup_Trait;
    use UCP_Maintenance_Privacy_Trait;

    const CRON_HOOK = 'ucp_maintenance_event';

    public static function bootstrap() {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'run'));
        add_action('admin_post_ucp_run_maintenance', array(__CLASS__, 'handle_manual_run'));
        add_action('admin_init', array(__CLASS__, 'maybe_register_privacy_content'));
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_privacy_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_privacy_eraser'));
    }
}
