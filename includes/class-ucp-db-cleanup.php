<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_DB_Cleanup {
    public function __construct() {
        add_action('admin_post_ucp_run_db_cleanup', array($this, 'handle_manual_cleanup'));
    }

    public function handle_manual_cleanup() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_run_db_cleanup');
        $confirmed = isset($_GET['confirm']) ? sanitize_text_field(wp_unslash($_GET['confirm'])) : '';
        if ('yes' !== $confirmed) {
            wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&db_cleanup_confirm=1'));
            exit;
        }
        $results = $this->run_cleanup();
        $summary = rawurlencode(wp_json_encode($results));
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&db_cleanup=' . $summary));
        exit;
    }

    public function run_cleanup() {
        global $wpdb;
        $results = array();

        $keep = absint(UCP_Options::get('db_keep_post_revisions', 5));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- no user input; core table
        $revision_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' ORDER BY post_parent ASC, post_modified_gmt DESC");
        if (!empty($revision_ids) && $keep >= 0) {
            $grouped = array();
            foreach ($revision_ids as $revision_id) {
                $parent = (int) $wpdb->get_var($wpdb->prepare("SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d", $revision_id));
                $grouped[$parent][] = (int) $revision_id;
            }
            $deleted = 0;
            foreach ($grouped as $ids) {
                if (count($ids) > $keep) {
                    $remove = array_slice($ids, $keep);
                    foreach ($remove as $id) {
                        wp_delete_post_revision($id);
                        $deleted++;
                    }
                }
            }
            $results['revisions_deleted'] = $deleted;
        }

        if (UCP_Options::get('db_cleanup_expired_transients')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- no user input; core options tables
            $results['expired_transients_deleted'] = $wpdb->query("DELETE a, b FROM {$wpdb->options} a LEFT JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, '_transient_timeout_', '_transient_') WHERE a.option_name LIKE '_transient_timeout_%' AND a.option_value < UNIX_TIMESTAMP()");
        }

        if (UCP_Options::get('db_cleanup_all_transients')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- no user input; core options tables
            $results['all_transients_deleted'] = (int) $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'");
        }

        if (UCP_Options::get('db_cleanup_spam_comments')) {
            $results['spam_comments_deleted'] = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->comments} WHERE comment_approved = %s", 'spam'));
        }

        if (UCP_Options::get('db_cleanup_trashed_comments')) {
            $results['trash_comments_deleted'] = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->comments} WHERE comment_approved = %s", 'trash'));
        }

        if (UCP_Options::get('db_cleanup_trashed_posts')) {
            $results['trash_posts_deleted'] = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE post_status = %s", 'trash'));
        }

        if (UCP_Options::get('db_cleanup_wc_sessions') && class_exists('WooCommerce')) {
            $table = $wpdb->prefix . 'woocommerce_sessions';
            if (is_string($table) && preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- table name is controlled (prefix + constant)
                $results['wc_sessions_deleted'] = (int) $wpdb->query("DELETE FROM `{$table}` WHERE session_expiry < UNIX_TIMESTAMP()");
            } else {
                $results['wc_sessions_deleted'] = 0;
            }
        }

        if (UCP_Options::get('db_cleanup_optimize_tables')) {
            $plugin_tables = array_filter(array_unique(array(
                defined('UCP_TABLE_JOBS') ? UCP_TABLE_JOBS : '',
                defined('UCP_TABLE_LOGS') ? UCP_TABLE_LOGS : '',
                defined('UCP_TABLE_DIAGNOSTICS') ? UCP_TABLE_DIAGNOSTICS : '',
            )));
            $optimized = 0;
            foreach ($plugin_tables as $table) {
                if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                    continue;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table names are defined by the plugin
                $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
                if ($exists !== $table) {
                    continue;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table name verified above
                $wpdb->query("OPTIMIZE TABLE `{$table}`");
                $optimized++;
            }
            $results['tables_optimized'] = $optimized;
            $results['tables_optimized_scope'] = 'plugin_tables_only';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- no user input
        $results['autoloaded_options_count'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')");
        $results['cron_events_count'] = is_array(_get_cron_array()) ? count(_get_cron_array()) : 0;
        UCP_Helpers::log('DB cleanup run: ' . wp_json_encode($results));
        return $results;
    }
}
