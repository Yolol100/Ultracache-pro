<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic table identifiers are validated with UCP_Helpers::is_safe_table_name() and quoted before use; values remain prepared.
if (!defined('ABSPATH')) {
    exit;
}

// Sub-traits are autoloaded via the classmap (UCP_Loader); no require_once needed.

// Lightweight internal traits consolidated here to avoid one-purpose micro-files while preserving the public UCP_* symbols.
trait UCP_DB_Cleanup_Schedule_Trait {
    public static function cron_schedules($schedules) {
        if (!isset($schedules['ucp_monthly'])) {
            $schedules['ucp_monthly'] = array(
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __('Monthly', 'ultracache-pro'),
            );
        }
        return $schedules;
    }

    protected static function table_exists($table) {
        global $wpdb;
        if (!UCP_Helpers::is_safe_table_name($table)) {
            return false;
        }
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function sync_schedule($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $frequency = isset($settings['db_cleanup_frequency']) ? (string) $settings['db_cleanup_frequency'] : 'off';
        $enabled = !empty($settings['enable_db_cleanup']) && in_array($frequency, array('daily', 'weekly', 'monthly'), true);
        if ($enabled && !wp_next_scheduled(self::CRON_HOOK)) {
            $schedule = 'monthly' === $frequency ? 'ucp_monthly' : $frequency;
            wp_schedule_event(time() + HOUR_IN_SECONDS, $schedule, self::CRON_HOOK);
        }
        if (!$enabled) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }
}

trait UCP_DB_Cleanup_Counts_Trait {
    public static function get_counts() {
        global $wpdb;
        $counts = array(
            'revisions' => 0,
            'auto_drafts' => 0,
            'trash_posts' => 0,
            'spam_comments' => 0,
            'trash_comments' => 0,
            'transients' => 0,
            'expired_transients' => 0,
            'optimizable_tables' => 0,
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- read-only dashboard counts
        $counts['revisions'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- read-only dashboard counts
        $counts['auto_drafts'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- read-only dashboard counts
        $counts['trash_posts'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'");
        $counts['spam_comments'] = (int) get_comments(array('status' => 'spam', 'count' => true));
        $counts['trash_comments'] = (int) get_comments(array('status' => 'trash', 'count' => true));
        $transient_like      = $wpdb->esc_like('_transient_') . '%';
        $site_transient_like = $wpdb->esc_like('_site_transient_') . '%';
        $timeout_like        = $wpdb->esc_like('_transient_timeout_') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only dashboard counts.
        $counts['transients'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $transient_like, $site_transient_like));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only dashboard counts.
        $counts['expired_transients'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < UNIX_TIMESTAMP()", $timeout_like));
        return $counts;
    }
}


class UCP_DB_Cleanup {
    use UCP_DB_Cleanup_Schedule_Trait;
    use UCP_DB_Cleanup_Counts_Trait;
    use UCP_DB_Cleanup_Runner_Trait;

    const CRON_HOOK = 'ucp_db_cleanup_event';

    public function __construct() {
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_cleanup'));
        add_action('admin_post_ucp_run_db_cleanup', array($this, 'handle_manual_cleanup'));
        add_action('admin_post_ucp_convert_options_innodb', array($this, 'handle_convert_options_innodb'));
    }

    public static function get_performance_audit() {
        global $wpdb;
        $audit = array('autoload_top' => array(), 'missing_indexes' => array(), 'options_engine' => 'unknown');
        $options = $wpdb->options;
        $options_sql = UCP_Helpers::quote_table_name($options);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin diagnostics query against validated WordPress options table identifier.
        $audit['autoload_top'] = $wpdb->get_results("SELECT option_name, LENGTH(option_value) AS bytes FROM {$options_sql} WHERE autoload IN ('yes','on','auto-on','auto') ORDER BY bytes DESC LIMIT 20", ARRAY_A);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- diagnostics query for the WordPress options table engine.
        $table = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $options), ARRAY_A);
        if (is_array($table) && !empty($table['Engine'])) {
            $audit['options_engine'] = (string) $table['Engine'];
        }
        $postmeta = $wpdb->postmeta;
        $postmeta_sql = UCP_Helpers::quote_table_name($postmeta);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- diagnostics query against validated WordPress postmeta table identifier.
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$postmeta_sql}", ARRAY_A);
        $has_post_id = false;
        $has_meta_key = false;
        foreach ((array) $indexes as $index) {
            if (!empty($index['Column_name']) && 'post_id' === $index['Column_name']) {
                $has_post_id = true;
            }
            if (!empty($index['Column_name']) && 'meta_key' === $index['Column_name']) {
                $has_meta_key = true;
            }
        }
        if (!$has_post_id) {
            $audit['missing_indexes'][] = 'wp_postmeta.post_id';
        }
        if (!$has_meta_key) {
            $audit['missing_indexes'][] = 'wp_postmeta.meta_key';
        }
        return $audit;
    }

    public function handle_convert_options_innodb() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen rechten.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_convert_options_innodb');
        if (!UCP_Options::get('db_allow_myisam_innodb_convert')) {
            wp_die(esc_html__('InnoDB-conversie vereist expliciete bevestiging in de instellingen.', 'ultracache-pro'));
        }
        global $wpdb;
        $options_table = (string) $wpdb->options;
        if (!UCP_Helpers::is_safe_table_name($options_table)) {
            wp_die(esc_html__('Ongeldige options-tabelnaam.', 'ultracache-pro'));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- admin-confirmed schema change; table identifier is validated before quoting.
        $wpdb->query('ALTER TABLE ' . UCP_Helpers::quote_table_name($options_table) . ' ENGINE=InnoDB');
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=database&ucp_notice=options_innodb'));
        exit;
    }
}
