<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Scheduled maintenance works on validated plugin-owned custom tables and sanitized retention values.

trait UCP_Maintenance_Schedule_Trait {
    public static function register_schedule($schedules) {
        if (!isset($schedules['daily'])) {
            $schedules['daily'] = array(
                'interval' => DAY_IN_SECONDS,
                'display'  => __('Elke dag', 'ultracache-pro'),
            );
        }
        return $schedules;
    }

    public static function schedule() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'daily', self::CRON_HOOK);
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}

trait UCP_Maintenance_Cleanup_Trait {
    public static function run() {
        self::cleanup_logs();
        self::cleanup_diagnostics();
        self::cleanup_jobs();
        UCP_Logger::log('info', 'maintenance', 'maintenance_ran', 'Scheduled maintenance completed.');
    }

    public static function cleanup_logs() {
        global $wpdb;
        $days = max(7, absint(UCP_Options::get('log_retention_days', 30)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        if (UCP_Helpers::is_safe_table_name(ucp_table_name('logs'))) {
            $table = UCP_Helpers::quote_table_name(ucp_table_name('logs'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table identifier is validated and quoted before use; value is prepared.
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $cutoff));
        }
    }

    public static function cleanup_diagnostics() {
        global $wpdb;
        $days = max(7, absint(UCP_Options::get('diagnostics_retention_days', 14)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        if (UCP_Helpers::is_safe_table_name(ucp_table_name('diagnostics'))) {
            $table = UCP_Helpers::quote_table_name(ucp_table_name('diagnostics'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table identifier is validated and quoted before use; value is prepared.
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE generated_at < %s", $cutoff));
        }
        foreach ((array) glob(UCP_CACHE_DIR . 'diagnostics/*.json') as $diagnostic_file) {
            if (is_file($diagnostic_file) && filemtime($diagnostic_file) < time() - ($days * DAY_IN_SECONDS)) {
                UCP_Helpers::safe_delete_file($diagnostic_file);
            }
        }
    }

    public static function cleanup_jobs() {
        global $wpdb;
        $days = max(7, absint(UCP_Options::get('job_retention_days', 14)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        if (UCP_Helpers::is_safe_table_name(ucp_table_name('jobs'))) {
            $table = UCP_Helpers::quote_table_name(ucp_table_name('jobs'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table identifier is validated and quoted before use; values are prepared.
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE status IN (%s,%s) AND updated_at < %s", 'success', 'failed', $cutoff));
        }
    }

    public static function handle_manual_run() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_run_maintenance');
        self::run();
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&maintenance=1'));
        exit;
    }
}
