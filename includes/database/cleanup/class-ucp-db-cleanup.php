<?php
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
        $audit['autoload_top'] = $wpdb->get_results("SELECT option_name, LENGTH(option_value) AS bytes FROM {$options} WHERE autoload IN ('yes','on','auto-on','auto') ORDER BY bytes DESC LIMIT 20", ARRAY_A);
        $table = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $options), ARRAY_A);
        if (is_array($table) && !empty($table['Engine'])) {
            $audit['options_engine'] = (string) $table['Engine'];
        }
        $postmeta = $wpdb->postmeta;
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$postmeta}", ARRAY_A);
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
        $wpdb->query("ALTER TABLE {$wpdb->options} ENGINE=InnoDB");
        wp_safe_redirect(admin_url('options-general.php?page=ultracache-pro&tab=database&ucp_notice=options_innodb'));
        exit;
    }
}
