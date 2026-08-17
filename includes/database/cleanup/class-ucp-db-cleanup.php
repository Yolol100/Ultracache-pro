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
        if (!is_array($schedules)) {
            $schedules = array();
        }
        if (!isset($schedules['ucp_monthly'])) {
            $schedules['ucp_monthly'] = array(
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __('Maandelijks', 'ultracache-pro'),
            );
        }
        return $schedules;
    }

    protected static function table_exists($table) {
        global $wpdb;
        if (!UCP_Helpers::is_safe_table_name($table)) {
            return false;
        }
        $like = $wpdb->esc_like($table);
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like)) === $table;
    }

    public static function sync_schedule($settings = null) {
        // Static settings saves can call this before an instance registers custom schedules.
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
        wp_get_schedules();
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $frequency = isset($settings['db_cleanup_frequency']) ? (string) $settings['db_cleanup_frequency'] : 'off';
        $enabled = !empty($settings['enable_db_cleanup']) && in_array($frequency, array('daily', 'weekly', 'monthly'), true);
        $schedule = 'monthly' === $frequency ? 'ucp_monthly' : $frequency;
        $event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event(self::CRON_HOOK) : false;

        if ($enabled) {
            // Reschedule when the cleanup frequency changes; wp_next_scheduled() alone keeps the old interval.
            if ($event && isset($event->schedule) && $schedule !== $event->schedule) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
                $event = false;
            }
            if (!$event && !wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, $schedule, self::CRON_HOOK);
            }
        }

        if (!$enabled) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }
}

trait UCP_DB_Cleanup_Counts_Trait {
    protected static function plugin_table_names() {
        $tables = array_unique(array(
            function_exists('ucp_table_name') ? ucp_table_name('jobs') : '',
            function_exists('ucp_table_name') ? ucp_table_name('logs') : '',
            function_exists('ucp_table_name') ? ucp_table_name('diagnostics') : '',
            function_exists('ucp_table_name') ? ucp_table_name('cache_events') : '',
        ));
        $safe_tables = array();
        foreach ($tables as $table) {
            if ($table && self::table_exists($table)) {
                $safe_tables[] = $table;
            }
        }
        return $safe_tables;
    }

    protected static function wordpress_table_names() {
        global $wpdb;
        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
        if ('' === $prefix) {
            return array();
        }
        $like = $wpdb->esc_like($prefix) . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only inventory of WordPress-prefixed tables for admin cleanup counts.
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        $tables = array_filter(array_map('strval', (array) $tables), array('UCP_Helpers', 'is_safe_table_name'));
        return array_values(array_unique($tables));
    }

    protected static function get_optimizable_table_count() {
        global $wpdb;
        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
        if ('' === $prefix) {
            return 0;
        }
        $like = $wpdb->esc_like($prefix) . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only database table status for admin cleanup counts.
        $rows = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $like), ARRAY_A);
        $count = 0;
        foreach ((array) $rows as $row) {
            $table = isset($row['Name']) ? (string) $row['Name'] : '';
            if (!$table || !UCP_Helpers::is_safe_table_name($table)) {
                continue;
            }
            if (!empty($row['Data_free'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Return revision IDs that the cleanup runner would actually delete.
     *
     * @param int|null $keep Revisions to keep per parent.
     * @return int[]
     */
    protected static function revision_ids_for_cleanup($keep = null) {
        global $wpdb;
        $keep = null === $keep ? absint(UCP_Options::get('db_keep_post_revisions', 5)) : absint($keep);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- read-only candidate selection from the core posts table; no user input.
        $revision_rows = $wpdb->get_results("SELECT ID, post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' ORDER BY post_parent ASC, post_modified_gmt DESC", ARRAY_A);
        $grouped = array();
        foreach ((array) $revision_rows as $revision) {
            $parent = isset($revision['post_parent']) ? (int) $revision['post_parent'] : 0;
            $id = isset($revision['ID']) ? (int) $revision['ID'] : 0;
            if ($id > 0) {
                $grouped[$parent][] = $id;
            }
        }

        $ids = array();
        foreach ($grouped as $parent_ids) {
            $ids = array_merge($ids, array_slice($parent_ids, $keep));
        }
        return array_values(array_filter(array_map('absint', $ids)));
    }

    /**
     * Return the exact post IDs selected by status cleanup.
     *
     * @param string $status Post status.
     * @return int[]
     */
    protected static function post_ids_for_status_cleanup($status) {
        if (!is_scalar($status)) {
            return array();
        }
        $status = sanitize_key((string) $status);
        if ('' === $status) {
            return array();
        }
        $ids = get_posts(array(
            'post_status'    => $status,
            'post_type'      => 'any',
            'fields'         => 'ids',
            'posts_per_page' => 500,
            'no_found_rows'  => true,
        ));
        return array_values(array_filter(array_map('absint', (array) $ids)));
    }

    public static function get_counts() {
        global $wpdb;
        $counts = array(
            'revisions' => 0,
            'auto_drafts' => 0,
            'drafts' => 0,
            'trash_posts' => 0,
            'spam_comments' => 0,
            'trash_comments' => 0,
            'transients' => 0,
            'expired_transients' => 0,
            'wc_sessions' => 0,
            'plugin_tables' => 0,
            'wordpress_tables' => 0,
            'optimizable_tables' => 0,
        );
        $counts['revisions'] = count(self::revision_ids_for_cleanup());
        $counts['auto_drafts'] = count(self::post_ids_for_status_cleanup('auto-draft'));
        $counts['drafts'] = count(self::post_ids_for_status_cleanup('draft'));
        $counts['trash_posts'] = count(self::post_ids_for_status_cleanup('trash'));
        $counts['spam_comments'] = (int) get_comments(array('status' => 'spam', 'count' => true));
        $counts['trash_comments'] = (int) get_comments(array('status' => 'trash', 'count' => true));
        $transient_like      = $wpdb->esc_like('_transient_') . '%';
        $site_transient_like = $wpdb->esc_like('_site_transient_') . '%';
        $timeout_like        = $wpdb->esc_like('_transient_timeout_') . '%';
        $site_timeout_like   = $wpdb->esc_like('_site_transient_timeout_') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only dashboard counts.
        $counts['transients'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $transient_like, $site_transient_like));
        // Site transients are stored in this options table only on single-site. Multisite network transients remain untouched.
        if (function_exists('is_multisite') && is_multisite()) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only dashboard counts.
            $counts['expired_transients'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < UNIX_TIMESTAMP()", $timeout_like));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only dashboard counts.
            $counts['expired_transients'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE (option_name LIKE %s OR option_name LIKE %s) AND option_value < UNIX_TIMESTAMP()", $timeout_like, $site_timeout_like));
        }
        if (class_exists('WooCommerce')) {
            $wc_sessions_table = $wpdb->prefix . 'woocommerce_sessions';
            if (self::table_exists($wc_sessions_table)) {
                $quoted_wc_sessions_table = UCP_Helpers::quote_table_name($wc_sessions_table);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table identifier is validated and quoted before use; query is read-only.
                $counts['wc_sessions'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$quoted_wc_sessions_table} WHERE session_expiry < UNIX_TIMESTAMP()");
            }
        }
        $counts['plugin_tables'] = count(self::plugin_table_names());
        $counts['wordpress_tables'] = count(self::wordpress_table_names());
        $counts['optimizable_tables'] = self::get_optimizable_table_count();
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
        $table = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($options)), ARRAY_A);
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
        UCP_Helpers::require_post_admin_action('ucp_convert_options_innodb');
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
