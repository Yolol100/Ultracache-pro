<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic table identifiers are validated with UCP_Helpers::is_safe_table_name() and quoted before use; values remain prepared.
if (!defined('ABSPATH')) {
    exit;
}

// Sub-traits are autoloaded via the classmap (UCP_Loader); no require_once needed.

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
