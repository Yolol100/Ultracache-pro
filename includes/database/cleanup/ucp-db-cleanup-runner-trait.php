<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DB cleanup uses validated plugin-owned table identifiers.

trait UCP_DB_Cleanup_Runner_Trait {
    public static function selected_operations() {
        $labels = array(
            'db_cleanup_post_revisions'       => __('oude revisies', 'ultracache-pro'),
            'db_cleanup_auto_drafts'          => __('automatische concepten', 'ultracache-pro'),
            'db_cleanup_drafts'               => __('gewone concepten', 'ultracache-pro'),
            'db_cleanup_expired_transients'   => __('verlopen transients', 'ultracache-pro'),
            'db_cleanup_all_transients'       => __('alle transients', 'ultracache-pro'),
            'db_cleanup_spam_comments'        => __('spamreacties', 'ultracache-pro'),
            'db_cleanup_trashed_comments'     => __('reacties in de prullenbak', 'ultracache-pro'),
            'db_cleanup_trashed_posts'        => __('berichten in de prullenbak', 'ultracache-pro'),
            'db_cleanup_wc_sessions'          => __('verlopen WooCommerce-sessies', 'ultracache-pro'),
            'db_cleanup_optimize_tables'      => __('plugin-tabellen optimaliseren', 'ultracache-pro'),
            'db_cleanup_optimize_all_tables'  => __('alle WordPress-tabellen optimaliseren', 'ultracache-pro'),
        );
        $selected = array();
        foreach ($labels as $key => $label) {
            if (UCP_Options::get($key)) {
                $selected[] = array(
                    'key'   => $key,
                    'label' => $label,
                );
            }
        }
        return $selected;
    }

    public function handle_manual_cleanup() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_run_db_cleanup');
        $confirmed_backup = false;
        if (isset($_GET['confirmBackup'])) {
            $confirmed_backup = rest_sanitize_boolean(wp_unslash($_GET['confirmBackup']));
        } elseif (isset($_GET['confirm_backup'])) {
            $confirmed_backup = rest_sanitize_boolean(wp_unslash($_GET['confirm_backup']));
        }
        $confirmed_irreversible = false;
        if (isset($_GET['confirmIrreversible'])) {
            $confirmed_irreversible = rest_sanitize_boolean(wp_unslash($_GET['confirmIrreversible']));
        } elseif (isset($_GET['confirm_irreversible'])) {
            $confirmed_irreversible = rest_sanitize_boolean(wp_unslash($_GET['confirm_irreversible']));
        }
        // Legacy admin-post cleanup requires the same two explicit confirmations as REST.
        if (!$confirmed_backup || !$confirmed_irreversible) {
            wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=database&db_cleanup_confirm=1&db_cleanup_error=confirmation_required'));
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
        $selected = wp_list_pluck(self::selected_operations(), 'key');
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

        if (UCP_Options::get('db_cleanup_drafts')) {
            $ids = get_posts(array('post_status' => 'draft', 'post_type' => 'any', 'fields' => 'ids', 'posts_per_page' => 500, 'no_found_rows' => true));
            $results['drafts_deleted'] = 0;
            foreach ((array) $ids as $post_id) {
                if (wp_delete_post((int) $post_id, true)) {
                    $results['drafts_deleted']++;
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
            $optimized = 0;
            foreach (self::plugin_table_names() as $table) {
                $quoted_table = UCP_Helpers::quote_table_name($table);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table identifier is validated and quoted before use; OPTIMIZE TABLE does not support placeholders for identifiers.
                $wpdb->query("OPTIMIZE TABLE {$quoted_table}");
                $optimized++;
            }
            $results['tables_optimized'] = $optimized;
            $results['tables_optimized_scope'] = 'plugin_tables_only';
        }

        if (UCP_Options::get('db_cleanup_optimize_all_tables')) {
            $results['wordpress_tables_optimized'] = 0;
            $results['wordpress_tables_optimized_scope'] = 'manual_only';
            if ('scheduled_cron' === $source) {
                $results['wordpress_tables_optimized_skipped'] = 'requires_manual_backup_confirmation';
            } else {
                foreach (self::wordpress_table_names() as $table) {
                    $quoted_table = UCP_Helpers::quote_table_name($table);
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- broad table optimization is admin-confirmed; table identifiers are validated and quoted.
                    $wpdb->query("OPTIMIZE TABLE {$quoted_table}");
                    $results['wordpress_tables_optimized']++;
                }
            }
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
        $results['completed_at'] = current_time('mysql', true);
        update_option('ucp_last_db_cleanup_at', $results['completed_at'], false);
        update_option('ucp_last_db_cleanup_results', $results, false);
        UCP_Helpers::log('DB cleanup run: ' . wp_json_encode($results));
        UCP_Logger::log('notice', 'database_cleanup', 'cleanup_completed', 'Database cleanup voltooid.', array('source' => $source, 'user_id' => get_current_user_id(), 'selected' => $selected, 'results' => $results, 'duration_ms' => $results['duration_ms']));
        return $results;
    }
}
