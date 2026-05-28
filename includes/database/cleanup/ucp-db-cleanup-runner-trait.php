<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DB cleanup uses validated plugin-owned table identifiers.

trait UCP_DB_Cleanup_Runner_Trait {
    public function handle_manual_cleanup() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_run_db_cleanup');
        $confirmed = isset($_GET['confirm']) ? sanitize_text_field(wp_unslash($_GET['confirm'])) : '';
        if ('yes' !== $confirmed) {
            wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=database&db_cleanup_confirm=1'));
            exit;
        }
        $results = $this->run_cleanup('manual_admin_post');
        $summary = rawurlencode(wp_json_encode($results));
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=database&db_cleanup=' . $summary));
        exit;
    }

    public function run_scheduled_cleanup() {
        if (!UCP_Options::get('enable_db_cleanup')) {
            return;
        }
        $this->run_cleanup('scheduled_cron');
    }

    public function run_cleanup($source = 'manual') {
        global $wpdb;
        $started = microtime(true);
        $selected = array();
        foreach (array('db_cleanup_post_revisions','db_cleanup_auto_drafts','db_cleanup_expired_transients','db_cleanup_all_transients','db_cleanup_spam_comments','db_cleanup_trashed_comments','db_cleanup_trashed_posts','db_cleanup_wc_sessions','db_cleanup_optimize_tables') as $key) {
            if (UCP_Options::get($key)) {
                $selected[] = $key;
            }
        }
        UCP_Logger::log('notice', 'database_cleanup', 'cleanup_started', 'Database cleanup gestart.', array('source' => $source, 'user_id' => get_current_user_id(), 'selected' => $selected, 'dry_run' => false));
        $results = array();

        if (UCP_Options::get('db_cleanup_post_revisions')) {
            $keep = absint(UCP_Options::get('db_keep_post_revisions', 5));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- no user input; core table
            $revision_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' ORDER BY post_parent ASC, post_modified_gmt DESC");
            $deleted = 0;
            if (!empty($revision_ids) && $keep >= 0) {
                $grouped = array();
                foreach ($revision_ids as $revision_id) {
                    $parent = (int) $wpdb->get_var($wpdb->prepare("SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d", $revision_id));
                    $grouped[$parent][] = (int) $revision_id;
                }
                foreach ($grouped as $ids) {
                    if (count($ids) > $keep) {
                        $remove = array_slice($ids, $keep);
                        foreach ($remove as $id) {
                            wp_delete_post_revision($id);
                            $deleted++;
                        }
                    }
                }
            }
            $results['revisions_deleted'] = $deleted;
        }

        if (UCP_Options::get('db_cleanup_auto_drafts')) {
            $ids = get_posts(array('post_status' => 'auto-draft', 'post_type' => 'any', 'fields' => 'ids', 'posts_per_page' => 500, 'no_found_rows' => true));
            $results['auto_drafts_deleted'] = 0;
            foreach ((array) $ids as $post_id) {
                if (wp_delete_post((int) $post_id, true)) {
                    $results['auto_drafts_deleted']++;
                }
            }
        }

        if (UCP_Options::get('db_cleanup_expired_transients')) {
            $timeout_like = $wpdb->esc_like('_transient_timeout_') . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only cleanup of core transient rows with prepared LIKE values.
            $results['expired_transients_deleted'] = (int) $wpdb->query(
                $wpdb->prepare(
                    "DELETE a, b FROM {$wpdb->options} a LEFT JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, %s, %s) WHERE a.option_name LIKE %s AND a.option_value < UNIX_TIMESTAMP()",
                    '_transient_timeout_',
                    '_transient_',
                    $timeout_like
                )
            );
        }

        if (UCP_Options::get('db_cleanup_all_transients')) {
            $transient_like      = $wpdb->esc_like('_transient_') . '%';
            $site_transient_like = $wpdb->esc_like('_site_transient_') . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only cleanup of core transient rows with prepared LIKE values.
            $results['all_transients_deleted'] = (int) $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $transient_like,
                    $site_transient_like
                )
            );
        }

        if (UCP_Options::get('db_cleanup_spam_comments')) {
            $ids = get_comments(array('status' => 'spam', 'fields' => 'ids', 'number' => 500));
            $results['spam_comments_deleted'] = 0;
            foreach ((array) $ids as $comment_id) {
                if (wp_delete_comment((int) $comment_id, true)) {
                    $results['spam_comments_deleted']++;
                }
            }
        }

        if (UCP_Options::get('db_cleanup_trashed_comments')) {
            $ids = get_comments(array('status' => 'trash', 'fields' => 'ids', 'number' => 500));
            $results['trash_comments_deleted'] = 0;
            foreach ((array) $ids as $comment_id) {
                if (wp_delete_comment((int) $comment_id, true)) {
                    $results['trash_comments_deleted']++;
                }
            }
        }

        if (UCP_Options::get('db_cleanup_trashed_posts')) {
            $ids = get_posts(array('post_status' => 'trash', 'post_type' => 'any', 'fields' => 'ids', 'posts_per_page' => 500, 'no_found_rows' => true));
            $results['trash_posts_deleted'] = 0;
            foreach ((array) $ids as $post_id) {
                if (wp_delete_post((int) $post_id, true)) {
                    $results['trash_posts_deleted']++;
                }
            }
        }

        if (UCP_Options::get('db_cleanup_wc_sessions') && class_exists('WooCommerce')) {
            $table = $wpdb->prefix . 'woocommerce_sessions';
            if (self::table_exists($table)) {
                $quoted_table = UCP_Helpers::quote_table_name($table);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table identifier is validated and quoted before use; no value placeholders are needed.
                $results['wc_sessions_deleted'] = (int) $wpdb->query("DELETE FROM {$quoted_table} WHERE session_expiry < UNIX_TIMESTAMP()");
            } else {
                $results['wc_sessions_deleted'] = 0;
            }
        }

        if (UCP_Options::get('db_cleanup_optimize_tables')) {
            $plugin_tables = array_filter(array_unique(array(
                function_exists('ucp_table_name') ? ucp_table_name('jobs') : '',
                function_exists('ucp_table_name') ? ucp_table_name('logs') : '',
                function_exists('ucp_table_name') ? ucp_table_name('diagnostics') : '',
            )));
            $optimized = 0;
            foreach ($plugin_tables as $table) {
                if (!self::table_exists($table)) {
                    continue;
                }
                $quoted_table = UCP_Helpers::quote_table_name($table);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table identifier is validated and quoted before use; OPTIMIZE TABLE does not support placeholders for identifiers.
                $wpdb->query("OPTIMIZE TABLE {$quoted_table}");
                $optimized++;
            }
            $results['tables_optimized'] = $optimized;
            $results['tables_optimized_scope'] = 'plugin_tables_only';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only count with prepared fixed values.
        $results['autoloaded_options_count'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN (%s,%s,%s,%s)",
                'yes',
                'on',
                'auto',
                'auto-on'
            )
        );
        $results['cron_events_count'] = is_array(_get_cron_array()) ? count(_get_cron_array()) : 0;
        $results['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
        $results['source'] = sanitize_key($source);
        UCP_Helpers::log('DB cleanup run: ' . wp_json_encode($results));
        UCP_Logger::log('notice', 'database_cleanup', 'cleanup_completed', 'Database cleanup voltooid.', array('source' => $source, 'user_id' => get_current_user_id(), 'selected' => $selected, 'results' => $results, 'duration_ms' => $results['duration_ms']));
        return $results;
    }
}
