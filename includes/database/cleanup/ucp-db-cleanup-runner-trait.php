<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DB cleanup uses validated plugin-owned table identifiers.

trait UCP_DB_Cleanup_Runner_Trait {
    protected static function is_explicit_confirmation_value($value) {
        return UCP_Helpers::is_explicit_confirmation($value);
    }

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
        $request_method = UCP_Helpers::request_method();
        if ('POST' !== $request_method) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 405));
        }
        check_admin_referer('ucp_run_db_cleanup');
        $request_data = UCP_Helpers::bounded_input_array($_POST, 50, 3, 512);
        $request_data = is_array($request_data) ? $request_data : array();
        $confirmed_backup = false;
        if (isset($request_data['confirmBackup'])) {
            $confirmed_backup = self::is_explicit_confirmation_value($request_data['confirmBackup']);
        } elseif (isset($request_data['confirm_backup'])) {
            $confirmed_backup = self::is_explicit_confirmation_value($request_data['confirm_backup']);
        }
        $confirmed_irreversible = false;
        if (isset($request_data['confirmIrreversible'])) {
            $confirmed_irreversible = self::is_explicit_confirmation_value($request_data['confirmIrreversible']);
        } elseif (isset($request_data['confirm_irreversible'])) {
            $confirmed_irreversible = self::is_explicit_confirmation_value($request_data['confirm_irreversible']);
        }
        // Legacy admin-post cleanup requires the same two explicit confirmations as REST.
        if (!$confirmed_backup || !$confirmed_irreversible) {
            wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=database&db_cleanup_confirm=1&db_cleanup_error=confirmation_required'));
            exit;
        }
        if (empty(self::selected_operations())) {
            wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=database&db_cleanup_error=nothing_selected'));
            exit;
        }
        $results = $this->run_cleanup('manual_admin_post');
        $summary = rawurlencode(UCP_Helpers::safe_json_encode_or($results, '{}'));
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=database&db_cleanup=' . $summary));
        exit;
    }

    protected static function cleanup_lock_key() {
        return 'ucp_db_cleanup_lock';
    }

    protected static function acquire_cleanup_lock() {
        global $wpdb;
        $key = self::cleanup_lock_key();
        $now = time();
        $token = wp_generate_password(20, false, false);
        $lock = array(
            'token' => $token,
            'expires' => $now + (15 * MINUTE_IN_SECONDS),
        );

        if (add_option($key, $lock, '', false)) {
            return $token;
        }

        $existing = get_option($key, array());
        $valid_existing = is_array($existing)
            && !empty($existing['token'])
            && is_scalar($existing['token'])
            && isset($existing['expires'])
            && is_numeric($existing['expires']);
        if (!$valid_existing || (int) $existing['expires'] < $now) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic stale or malformed lock takeover for a plugin-owned option.
            $updated = $wpdb->update(
                $wpdb->options,
                array('option_value' => maybe_serialize($lock)),
                array(
                    'option_name'  => $key,
                    'option_value' => maybe_serialize($existing),
                ),
                array('%s'),
                array('%s', '%s')
            );
            if (1 === (int) $updated) {
                wp_cache_delete($key, 'options');
                wp_cache_delete('alloptions', 'options');
                return $token;
            }
        }

        return '';
    }

    protected static function release_cleanup_lock($token) {
        global $wpdb;
        if (!is_scalar($token)) {
            return;
        }
        $token = (string) $token;
        if ('' === $token) {
            return;
        }
        $key = self::cleanup_lock_key();
        $existing = get_option($key, array());
        if (is_array($existing) && !empty($existing['token']) && is_scalar($existing['token']) && hash_equals((string) $existing['token'], $token)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- release only the exact plugin-owned lock value acquired by this process.
            $wpdb->delete(
                $wpdb->options,
                array(
                    'option_name'  => $key,
                    'option_value' => maybe_serialize($existing),
                ),
                array('%s', '%s')
            );
            wp_cache_delete($key, 'options');
            wp_cache_delete('alloptions', 'options');
        }
    }

    public function run_scheduled_cleanup() {
        if (!UCP_Options::get('enable_db_cleanup') || empty(self::selected_operations())) {
            return;
        }
        $this->run_cleanup('scheduled_cron');
    }

    protected function cleanup_post_revisions($results) {
        if (!UCP_Options::get('db_cleanup_post_revisions')) {
            return $results;
        }

        $deleted = 0;
        foreach (self::revision_ids_for_cleanup() as $id) {
            if (wp_delete_post_revision($id)) {
                $deleted++;
            }
        }
        $results['revisions_deleted'] = $deleted;
        return $results;
    }

    protected function cleanup_posts_by_status($results, $option_key, $status, $result_key) {
        if (!UCP_Options::get($option_key)) {
            return $results;
        }

        $ids = self::post_ids_for_status_cleanup($status);
        $results[$result_key] = 0;
        foreach ((array) $ids as $post_id) {
            if (wp_delete_post((int) $post_id, true)) {
                $results[$result_key]++;
            }
        }
        return $results;
    }

    protected function cleanup_expired_transients($results) {
        global $wpdb;
        if (!UCP_Options::get('db_cleanup_expired_transients')) {
            return $results;
        }

        $timeout_pairs = array(array('_transient_timeout_', '_transient_'));
        if (!function_exists('is_multisite') || !is_multisite()) {
            $timeout_pairs[] = array('_site_transient_timeout_', '_site_transient_');
        }
        $results['expired_transients_deleted'] = 0;
        foreach ($timeout_pairs as $timeout_pair) {
            $timeout_like = $wpdb->esc_like($timeout_pair[0]) . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only cleanup of core transient rows with prepared LIKE values.
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE a, b FROM {$wpdb->options} a LEFT JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, %s, %s) WHERE a.option_name LIKE %s AND a.option_value < UNIX_TIMESTAMP()",
                    $timeout_pair[0],
                    $timeout_pair[1],
                    $timeout_like
                )
            );
            if (false === $deleted) {
                $results['expired_transients_failed'] = true;
                continue;
            }
            $results['expired_transients_deleted'] += (int) $deleted;
        }
        return $results;
    }

    protected function cleanup_all_transients($results) {
        global $wpdb;
        if (!UCP_Options::get('db_cleanup_all_transients')) {
            return $results;
        }

        $transient_like = $wpdb->esc_like('_transient_') . '%';
        if (function_exists('is_multisite') && is_multisite()) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- site-local cleanup; network transients remain untouched.
            $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $transient_like));
        } else {
            $site_transient_like = $wpdb->esc_like('_site_transient_') . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- single-site cleanup of core transient rows with prepared LIKE values.
            $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $transient_like, $site_transient_like));
        }
        $results['all_transients_deleted'] = false === $deleted ? 0 : (int) $deleted;
        if (false === $deleted) {
            $results['all_transients_failed'] = true;
        }
        return $results;
    }

    protected function cleanup_comments_by_status($results, $option_key, $status, $result_key) {
        if (!UCP_Options::get($option_key)) {
            return $results;
        }

        $ids = get_comments(array('status' => $status, 'fields' => 'ids', 'number' => 500));
        $results[$result_key] = 0;
        foreach ((array) $ids as $comment_id) {
            if (wp_delete_comment((int) $comment_id, true)) {
                $results[$result_key]++;
            }
        }
        return $results;
    }

    protected function cleanup_woocommerce_sessions($results) {
        global $wpdb;
        if (!UCP_Options::get('db_cleanup_wc_sessions') || !class_exists('WooCommerce')) {
            return $results;
        }

        $table = $wpdb->prefix . 'woocommerce_sessions';
        if (!self::table_exists($table)) {
            $results['wc_sessions_deleted'] = 0;
            return $results;
        }

        $quoted_table = UCP_Helpers::quote_table_name($table);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table identifier is validated and quoted before use; no value placeholders are needed.
        $deleted = $wpdb->query("DELETE FROM {$quoted_table} WHERE session_expiry < UNIX_TIMESTAMP()");
        $results['wc_sessions_deleted'] = false === $deleted ? 0 : (int) $deleted;
        if (false === $deleted) {
            $results['wc_sessions_failed'] = true;
        }
        return $results;
    }

    protected function optimize_plugin_tables($results) {
        global $wpdb;
        if (!UCP_Options::get('db_cleanup_optimize_tables')) {
            return $results;
        }

        $optimized = 0;
        foreach (self::plugin_table_names() as $table) {
            $quoted_table = UCP_Helpers::quote_table_name($table);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table identifier is validated and quoted before use; OPTIMIZE TABLE does not support placeholders for identifiers.
            if (false !== $wpdb->query("OPTIMIZE TABLE {$quoted_table}")) {
                $optimized++;
            } else {
                $results['tables_optimize_failed'][] = $table;
            }
        }
        $results['tables_optimized'] = $optimized;
        $results['tables_optimized_scope'] = 'plugin_tables_only';
        return $results;
    }

    protected function optimize_wordpress_tables($results, $source) {
        global $wpdb;
        if (!UCP_Options::get('db_cleanup_optimize_all_tables')) {
            return $results;
        }

        $results['wordpress_tables_optimized'] = 0;
        $results['wordpress_tables_optimized_scope'] = 'manual_only';
        if ('scheduled_cron' === $source) {
            $results['wordpress_tables_optimized_skipped'] = 'requires_manual_backup_confirmation';
            return $results;
        }

        foreach (self::wordpress_table_names() as $table) {
            $quoted_table = UCP_Helpers::quote_table_name($table);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- broad table optimization is admin-confirmed; table identifiers are validated and quoted.
            if (false !== $wpdb->query("OPTIMIZE TABLE {$quoted_table}")) {
                $results['wordpress_tables_optimized']++;
            } else {
                $results['wordpress_tables_optimize_failed'][] = $table;
            }
        }
        return $results;
    }

    protected function run_selected_cleanup_operations($source) {
        $results = array();
        do_action('ucp_operation_heartbeat');
        $results = $this->cleanup_post_revisions($results);
        do_action('ucp_operation_heartbeat');
        $results = $this->cleanup_posts_by_status($results, 'db_cleanup_auto_drafts', 'auto-draft', 'auto_drafts_deleted');
        do_action('ucp_operation_heartbeat');
        $results = $this->cleanup_posts_by_status($results, 'db_cleanup_drafts', 'draft', 'drafts_deleted');
        do_action('ucp_operation_heartbeat');
        $results = $this->cleanup_expired_transients($results);
        do_action('ucp_operation_heartbeat');
        $results = $this->cleanup_all_transients($results);
        do_action('ucp_operation_heartbeat');
        $results = $this->cleanup_comments_by_status($results, 'db_cleanup_spam_comments', 'spam', 'spam_comments_deleted');
        do_action('ucp_operation_heartbeat');
        $results = $this->cleanup_comments_by_status($results, 'db_cleanup_trashed_comments', 'trash', 'trash_comments_deleted');
        do_action('ucp_operation_heartbeat');
        $results = $this->cleanup_posts_by_status($results, 'db_cleanup_trashed_posts', 'trash', 'trash_posts_deleted');
        do_action('ucp_operation_heartbeat');
        $results = $this->cleanup_woocommerce_sessions($results);
        do_action('ucp_operation_heartbeat');
        $results = $this->optimize_plugin_tables($results);
        do_action('ucp_operation_heartbeat');
        return $this->optimize_wordpress_tables($results, $source);
    }

    protected function finalize_cleanup_results($results, $source, $started, $selected) {
        global $wpdb;
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
        UCP_Helpers::log(sprintf(__('Database-opschoning uitgevoerd: %s', 'ultracache-pro'), UCP_Helpers::safe_json_encode_or($results, '{}')));
        UCP_Logger::log('notice', 'database_cleanup', 'cleanup_completed', __('Database-opschoning is voltooid.', 'ultracache-pro'), array('source' => $source, 'user_id' => get_current_user_id(), 'selected' => $selected, 'results' => $results, 'duration_ms' => $results['duration_ms']));
        return $results;
    }

    public function run_cleanup($source = 'manual') {
        $started = microtime(true);
        $selected = wp_list_pluck(self::selected_operations(), 'key');
        if (empty($selected)) {
            return array(
                'source' => sanitize_key($source),
                'skipped' => 'nothing_selected',
                'completed_at' => current_time('mysql', true),
            );
        }

        $lock_token = self::acquire_cleanup_lock();
        if ('' === $lock_token) {
            return array(
                'source' => sanitize_key($source),
                'skipped' => 'already_running',
                'completed_at' => current_time('mysql', true),
            );
        }

        UCP_Logger::log('notice', 'database_cleanup', 'cleanup_started', __('Database-opschoning is gestart.', 'ultracache-pro'), array('source' => $source, 'user_id' => get_current_user_id(), 'selected' => $selected, 'dry_run' => false));
        try {
            $results = $this->run_selected_cleanup_operations($source);
            return $this->finalize_cleanup_results($results, $source, $started, $selected);
        } finally {
            self::release_cleanup_lock($lock_token);
        }
    }

}
