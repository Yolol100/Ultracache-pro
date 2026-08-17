<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Installer_Lifecycle_Trait {
    /**
     * Return the option used as the cross-request upgrade mutex.
     *
     * @return string
     */
    protected static function upgrade_lock_key() {
        return 'ucp_plugin_upgrade_lock';
    }

    /**
     * Return the bounded lease duration for schema and filesystem upgrades.
     *
     * @return int
     */
    protected static function upgrade_lock_ttl() {
        $ttl = (int) apply_filters('ucp_upgrade_lock_ttl', 30 * MINUTE_IN_SECONDS);
        return max(5 * MINUTE_IN_SECONDS, min(HOUR_IN_SECONDS, $ttl));
    }

    protected static function network_activation_state_key() {
        return 'ucp_network_activation_state';
    }

    protected static function network_activation_lock_key() {
        return 'ucp_network_activation_lock';
    }

    protected static function network_activation_lock_ttl() {
        $ttl = (int) apply_filters('ucp_network_activation_lock_ttl', 30 * MINUTE_IN_SECONDS);
        return max(10 * MINUTE_IN_SECONDS, min(HOUR_IN_SECONDS, $ttl));
    }

    /**
     * Acquire an atomic, expiring upgrade lock.
     *
     * @return string Lock token, or an empty string when another request owns it.
     */
    protected static function acquire_upgrade_lock() {
        global $wpdb;

        $key = self::upgrade_lock_key();
        $now = time();
        $token = wp_generate_password(24, false, false);
        $lock = array(
            'token'   => $token,
            'expires' => $now + self::upgrade_lock_ttl(),
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
        if ($valid_existing && (int) $existing['expires'] >= $now) {
            return '';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-swap takeover of a stale plugin-owned upgrade lock.
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
        if (1 !== (int) $updated) {
            return '';
        }

        wp_cache_delete($key, 'options');
        wp_cache_delete('alloptions', 'options');
        return $token;
    }

    /**
     * Renew the exact upgrade lease held by this process.
     *
     * @param string $token Upgrade lock token.
     * @return bool
     */
    protected static function refresh_upgrade_lock($token) {
        global $wpdb;

        if (!is_scalar($token) || '' === (string) $token) {
            return false;
        }
        $key = self::upgrade_lock_key();
        $existing = get_option($key, array());
        if (!is_array($existing)
            || empty($existing['token'])
            || !is_scalar($existing['token'])
            || !hash_equals((string) $existing['token'], (string) $token)) {
            return false;
        }

        $next = $existing;
        $next['expires'] = time() + self::upgrade_lock_ttl();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-swap renewal of the exact plugin-owned upgrade lease.
        $updated = $wpdb->update(
            $wpdb->options,
            array('option_value' => maybe_serialize($next)),
            array('option_name' => $key, 'option_value' => maybe_serialize($existing)),
            array('%s'),
            array('%s', '%s')
        );
        if (1 === (int) $updated) {
            wp_cache_delete($key, 'options');
            wp_cache_delete('alloptions', 'options');
            return true;
        }

        $stored = get_option($key, array());
        return is_array($stored)
            && !empty($stored['token'])
            && is_scalar($stored['token'])
            && hash_equals((string) $stored['token'], (string) $token)
            && isset($stored['expires'])
            && (int) $stored['expires'] >= (int) $next['expires'];
    }

    /**
     * Stop the current migration immediately when its lease was replaced.
     *
     * @param string $token Upgrade lock token.
     * @param string $phase Migration phase for diagnostics.
     * @return void
     * @throws RuntimeException When the lease cannot be renewed.
     */
    protected static function assert_upgrade_lock($token, $phase) {
        if (!self::refresh_upgrade_lock($token)) {
            throw new RuntimeException('Upgrade lease was lost during ' . sanitize_key($phase) . '.');
        }
    }

    /**
     * Release only the exact upgrade lock acquired by this process.
     *
     * @param string $token Upgrade lock token.
     * @return void
     */
    protected static function release_upgrade_lock($token) {
        global $wpdb;

        if (!is_scalar($token) || '' === (string) $token) {
            return;
        }

        $key = self::upgrade_lock_key();
        $existing = get_option($key, array());
        if (!is_array($existing)
            || empty($existing['token'])
            || !is_scalar($existing['token'])
            || !hash_equals((string) $existing['token'], (string) $token)) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- release only the exact serialized plugin-owned lock value.
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

    /**
     * Persist the current runtime integration state separately from schema state.
     *
     * @param array<int,string> $issues Runtime integration issue codes.
     * @return bool
     */
    protected static function persist_runtime_config_status($issues) {
        $issues = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $issues))));
        $status = array(
            'version'    => UCP_VERSION,
            'status'     => empty($issues) ? 'ready' : 'degraded',
            'issues'     => $issues,
            'checked_at' => time(),
        );
        $saved = update_option('ucp_runtime_config_status', $status, false);
        return $saved || $status === get_option('ucp_runtime_config_status', array());
    }

    /**
     * Upgrade the plugin exactly once across concurrent requests.
     *
     * @return bool True when already current or upgraded successfully.
     */
    public static function maybe_upgrade() {
        $installed = (string) get_option('ucp_db_version', '');
        if (UCP_VERSION === $installed) {
            return true;
        }

        $token = self::acquire_upgrade_lock();
        if ('' === $token) {
            return false;
        }

        try {
            // A competing request may have completed while this request acquired the lock.
            $installed = (string) get_option('ucp_db_version', '');
            if (UCP_VERSION === $installed) {
                return true;
            }

            if (!self::create_tables()) {
                throw new RuntimeException('Database schema verification failed.');
            }
            self::assert_upgrade_lock($token, 'schema');

            // Filesystem and webserver integration can be unavailable on read-only or
            // externally managed hosts. Keep the database migration atomic, but record
            // a separate degraded runtime state instead of retrying schema work forever.
            $runtime_issues = array();
            if (!UCP_Helpers::ensure_cache_dirs(true)) {
                $runtime_issues[] = 'cache_directories';
            } elseif (!self::cleanup_previous_version_artifacts()) {
                $runtime_issues[] = 'cache_configuration';
            }
            self::assert_upgrade_lock($token, 'cache_files');

            $dropin_result = UCP_Helpers::maybe_install_own_advanced_cache_automatically(false);
            $dropin_blocked = is_array($dropin_result) && !empty($dropin_result['blocked']);
            $dropin_required = UCP_Options::get('enable_cache') && UCP_Options::get('allow_dropin_writes');
            if ($dropin_required && !$dropin_blocked && (empty($dropin_result['installed']) || empty($dropin_result['wp_cache']))) {
                $runtime_issues[] = 'page_cache_dropin';
            }
            self::assert_upgrade_lock($token, 'dropin');

            if (!UCP_Helpers::maybe_write_browser_cache_rules()) {
                $runtime_issues[] = 'browser_cache_rules';
            }
            self::assert_upgrade_lock($token, 'browser_rules');

            UCP_Options::maybe_apply_performance_migration();
            UCP_Options::maybe_upgrade_exact_transaction_rules_v1();
            self::assert_upgrade_lock($token, 'option_migrations');

            if ('' !== $installed && version_compare($installed, '11.6.48', '<')) {
                $fresh_hours = max(0, absint(UCP_Options::get('cache_lifespan', 10)));
                $stale_hours = UCP_Options::get('enable_stale_cache', 0)
                    ? max(0, absint(UCP_Options::get('stale_cache_lifespan', 24)))
                    : 0;
                $legacy_until = time() + min(40 * DAY_IN_SECONDS, (($fresh_hours + $stale_hours) * HOUR_IN_SECONDS) + DAY_IN_SECONDS);
                $legacy_saved = update_option('ucp_cwv_legacy_token_until', $legacy_until, false);
                if (!$legacy_saved && $legacy_until !== (int) get_option('ucp_cwv_legacy_token_until', 0)) {
                    throw new RuntimeException('CWV token compatibility window could not be committed.');
                }
            }

            self::schedule_events();
            self::assert_upgrade_lock($token, 'scheduling');

            self::persist_runtime_config_status($runtime_issues);
            if (!empty($runtime_issues) && class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('upgrade', 'Database upgrade completed with runtime configuration issues.', array(
                    'issues' => array_values(array_unique($runtime_issues)),
                ));
            }

            self::assert_upgrade_lock($token, 'finalization');
            if (class_exists('UCP_Cache')) {
                $cache = UCP_Helpers::new_without_constructor('UCP_Cache');
                $cache->purge_and_preload_after_lifecycle_change('ultracache_upgrade', array('item' => UCP_BASENAME));
            }
            self::assert_upgrade_lock($token, 'cache_invalidation');

            // Commit the installed version only after every required phase succeeded.
            // A failed or replaced worker must leave the old version in place so the
            // next request can resume the idempotent migration safely.
            $version_saved = update_option('ucp_db_version', UCP_VERSION, false);
            if (!$version_saved && UCP_VERSION !== (string) get_option('ucp_db_version', '')) {
                throw new RuntimeException('The installed plugin version could not be committed.');
            }

            return true;
        } catch (Throwable $e) {
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('upgrade', 'UltraCache upgrade failed safely.', array(
                    'exception' => sanitize_key(get_class($e)),
                    'from'      => sanitize_text_field($installed),
                    'to'        => UCP_VERSION,
                ));
            }
            return false;
        } finally {
            self::release_upgrade_lock($token);
        }
    }

    public static function activate($network_wide = false) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if (is_multisite() && (bool) $network_wide) {
            $network_id = function_exists('get_current_network_id') ? (int) get_current_network_id() : 0;
            if (!self::schedule_network_activation($network_id) && class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('upgrade', 'UltraCache network activation could not be scheduled.', array(
                    'network_id' => $network_id,
                    'to'         => UCP_VERSION,
                ));
            }
            return;
        }

        self::activate_single_site();
    }

    public static function activate_current_site() {
        return self::activate_single_site();
    }

    public static function deactivate_current_site() {
        return self::deactivate_single_site();
    }

    /**
     * Initialize a resumable, cursor-based multisite activation job.
     *
     * @param int $network_id Network ID.
     * @return bool
     */
    protected static function schedule_network_activation($network_id = 0) {
        $network_id = $network_id > 0 ? (int) $network_id : (function_exists('get_current_network_id') ? (int) get_current_network_id() : 0);
        $token = self::acquire_network_activation_lock($network_id);
        if ('' === $token) {
            $existing = get_network_option($network_id, self::network_activation_state_key(), array());
            if (is_array($existing)
                && UCP_VERSION === (string) ($existing['target_version'] ?? '')
                && in_array((string) ($existing['status'] ?? ''), array('pending', 'running', 'failed'), true)
                && absint($existing['attempts'] ?? 0) < 5) {
                return self::schedule_network_activation_event($network_id, 5);
            }
            return false;
        }

        try {
            $existing = get_network_option($network_id, self::network_activation_state_key(), array());
            if (is_array($existing)
                && UCP_VERSION === (string) ($existing['target_version'] ?? '')
                && in_array((string) ($existing['status'] ?? ''), array('pending', 'running', 'failed'), true)
                && absint($existing['attempts'] ?? 0) < 5) {
                return self::schedule_network_activation_event($network_id, 5);
            }

            $state = array(
                'target_version' => UCP_VERSION,
                'cursor'         => 0,
                'status'         => 'pending',
                'attempts'       => 0,
                'updated_at'     => time(),
            );
            $saved = update_network_option($network_id, self::network_activation_state_key(), $state);
            if (!$saved && $state !== get_network_option($network_id, self::network_activation_state_key(), array())) {
                return false;
            }
            return self::schedule_network_activation_event($network_id, 5);
        } finally {
            self::release_network_activation_lock($network_id, $token);
        }
    }

    protected static function schedule_network_activation_event($network_id, $delay = 5) {
        $args = array((int) $network_id);
        if (!wp_next_scheduled('ucp_network_activation_batch', $args)) {
            return (bool) wp_schedule_single_event(time() + max(1, absint($delay)), 'ucp_network_activation_batch', $args);
        }
        return true;
    }

    public static function ensure_network_activation_schedule() {
        if (!is_multisite() || (function_exists('is_main_site') && !is_main_site())) {
            return;
        }

        $network_id = function_exists('get_current_network_id') ? (int) get_current_network_id() : 0;
        $state = get_network_option($network_id, self::network_activation_state_key(), array());
        if (!is_array($state) || UCP_VERSION !== (string) ($state['target_version'] ?? '')) {
            return;
        }

        $status = sanitize_key((string) ($state['status'] ?? ''));
        $attempts = absint($state['attempts'] ?? 0);
        if (!in_array($status, array('pending', 'running', 'failed'), true) || $attempts >= 5) {
            return;
        }

        self::schedule_network_activation_event($network_id, 5);
    }

    protected static function delete_network_option_cache($network_id, $key) {
        wp_cache_delete((int) $network_id . ':' . (string) $key, 'site-options');
    }

    protected static function compare_and_swap_network_activation_state($network_id, $expected, $next) {
        global $wpdb;

        if (!is_array($expected) || !is_array($next)) {
            return false;
        }
        $key = self::network_activation_state_key();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact-value compare-and-swap for plugin-owned resumable network activation state.
        $updated = $wpdb->update(
            $wpdb->sitemeta,
            array('meta_value' => maybe_serialize($next)),
            array('site_id' => (int) $network_id, 'meta_key' => $key, 'meta_value' => maybe_serialize($expected)),
            array('%s'),
            array('%d', '%s', '%s')
        );
        self::delete_network_option_cache($network_id, $key);
        if (1 === (int) $updated) {
            return true;
        }
        return $next === get_network_option($network_id, $key, array());
    }

    protected static function acquire_network_activation_lock($network_id) {
        global $wpdb;

        $key = self::network_activation_lock_key();
        $now = time();
        $token = wp_generate_password(24, false, false);
        $lock = array('token' => $token, 'expires' => $now + self::network_activation_lock_ttl());
        if (add_network_option($network_id, $key, $lock)) {
            return $token;
        }

        $current = get_network_option($network_id, $key, array());
        $valid = is_array($current) && !empty($current['token']) && is_scalar($current['token']) && isset($current['expires']) && is_numeric($current['expires']);
        if ($valid && (int) $current['expires'] >= $now) {
            return '';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic takeover of a stale plugin-owned network activation lock.
        $updated = $wpdb->update(
            $wpdb->sitemeta,
            array('meta_value' => maybe_serialize($lock)),
            array('site_id' => (int) $network_id, 'meta_key' => $key, 'meta_value' => maybe_serialize($current)),
            array('%s'),
            array('%d', '%s', '%s')
        );
        if (1 !== (int) $updated) {
            return '';
        }
        self::delete_network_option_cache($network_id, $key);
        return $token;
    }

    protected static function refresh_network_activation_lock($network_id, $token) {
        global $wpdb;

        $key = self::network_activation_lock_key();
        $current = get_network_option($network_id, $key, array());
        if (!is_array($current) || empty($current['token']) || !is_scalar($current['token']) || !hash_equals((string) $current['token'], (string) $token)) {
            return false;
        }
        $next = $current;
        $next['expires'] = time() + self::network_activation_lock_ttl();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-swap renewal of the exact network activation lease.
        $updated = $wpdb->update(
            $wpdb->sitemeta,
            array('meta_value' => maybe_serialize($next)),
            array('site_id' => (int) $network_id, 'meta_key' => $key, 'meta_value' => maybe_serialize($current)),
            array('%s'),
            array('%d', '%s', '%s')
        );
        if (1 === (int) $updated) {
            self::delete_network_option_cache($network_id, $key);
            return true;
        }
        $stored = get_network_option($network_id, $key, array());
        return is_array($stored) && !empty($stored['token']) && is_scalar($stored['token']) && hash_equals((string) $stored['token'], (string) $token) && isset($stored['expires']) && (int) $stored['expires'] >= (int) $next['expires'];
    }

    protected static function release_network_activation_lock($network_id, $token) {
        global $wpdb;

        $key = self::network_activation_lock_key();
        $current = get_network_option($network_id, $key, array());
        if (!is_array($current) || empty($current['token']) || !is_scalar($current['token']) || !hash_equals((string) $current['token'], (string) $token)) {
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact-value deletion of a plugin-owned network activation lock.
        $wpdb->delete(
            $wpdb->sitemeta,
            array('site_id' => (int) $network_id, 'meta_key' => $key, 'meta_value' => maybe_serialize($current)),
            array('%d', '%s', '%s')
        );
        self::delete_network_option_cache($network_id, $key);
    }

    /**
     * Process one bounded activation batch and persist its cursor.
     *
     * @param int $network_id Network ID.
     * @return bool
     */
    public static function process_network_activation_batch($network_id = 0) {
        global $wpdb;

        if (!is_multisite()) {
            return false;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $network_id = $network_id > 0 ? (int) $network_id : (function_exists('get_current_network_id') ? (int) get_current_network_id() : 0);
        $token = self::acquire_network_activation_lock($network_id);
        if ('' === $token) {
            self::schedule_network_activation_event($network_id, MINUTE_IN_SECONDS);
            return false;
        }

        try {
            $state = get_network_option($network_id, self::network_activation_state_key(), array());
            if (!is_array($state) || UCP_VERSION !== (string) ($state['target_version'] ?? '')) {
                return false;
            }
            if ('completed' === (string) ($state['status'] ?? '')) {
                return true;
            }

            $cursor = max(0, absint($state['cursor'] ?? 0));
            $batch_size = (int) apply_filters('ucp_network_activation_batch_size', 50, $network_id);
            $batch_size = max(1, min(200, $batch_size));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cursor-based read from the core blogs table.
            $site_ids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = %d AND blog_id > %d ORDER BY blog_id ASC LIMIT %d", $network_id, $cursor, $batch_size));
            $site_ids = array_values(array_filter(array_map('absint', (array) $site_ids)));

            foreach ($site_ids as $site_id) {
                if (!self::refresh_network_activation_lock($network_id, $token)) {
                    throw new RuntimeException('Network activation lease was lost.');
                }
                switch_to_blog($site_id);
                try {
                    if (!self::activate_single_site()) {
                        throw new RuntimeException('Site activation failed.');
                    }
                } finally {
                    restore_current_blog();
                }
                if (!self::refresh_network_activation_lock($network_id, $token)) {
                    throw new RuntimeException('Network activation lease was lost after site activation.');
                }

                $next = $state;
                $next['cursor'] = $site_id;
                $next['status'] = 'running';
                $next['attempts'] = 0;
                $next['updated_at'] = time();
                if (!self::compare_and_swap_network_activation_state($network_id, $state, $next)) {
                    throw new RuntimeException('Network activation state changed before the cursor commit.');
                }
                $state = $next;
            }

            if (count($site_ids) < $batch_size) {
                if (!self::refresh_network_activation_lock($network_id, $token)) {
                    throw new RuntimeException('Network activation lease was lost before completion.');
                }
                $next = $state;
                $next['status'] = 'completed';
                $next['completed_at'] = time();
                $next['updated_at'] = time();
                if (!self::compare_and_swap_network_activation_state($network_id, $state, $next)) {
                    throw new RuntimeException('Network activation state changed before completion.');
                }
                wp_clear_scheduled_hook('ucp_network_activation_batch', array($network_id));
                return true;
            }

            if (!self::refresh_network_activation_lock($network_id, $token)) {
                throw new RuntimeException('Network activation lease was lost before rescheduling.');
            }
            $next = $state;
            $next['status'] = 'pending';
            $next['updated_at'] = time();
            if (!self::compare_and_swap_network_activation_state($network_id, $state, $next)) {
                throw new RuntimeException('Network activation state changed before rescheduling.');
            }
            if (!self::schedule_network_activation_event($network_id, 5)) {
                throw new RuntimeException('Network activation continuation could not be scheduled.');
            }
            return true;
        } catch (Throwable $e) {
            if (!self::refresh_network_activation_lock($network_id, $token)) {
                return false;
            }

            $state = get_network_option($network_id, self::network_activation_state_key(), array());
            if (!is_array($state)
                || UCP_VERSION !== (string) ($state['target_version'] ?? '')
                || 'completed' === (string) ($state['status'] ?? '')) {
                return false;
            }
            $failed = $state;
            $failed['status'] = 'failed';
            $failed['attempts'] = absint($state['attempts'] ?? 0) + 1;
            $failed['last_error'] = sanitize_key(get_class($e));
            $failed['updated_at'] = time();
            if (!self::compare_and_swap_network_activation_state($network_id, $state, $failed)) {
                return false;
            }
            if ($failed['attempts'] < 5) {
                self::schedule_network_activation_event($network_id, MINUTE_IN_SECONDS);
            }
            return false;
        } finally {
            self::release_network_activation_lock($network_id, $token);
        }
    }

    /**
     * Execute a lifecycle callback for every site without relying on get_sites()' default limit.
     *
     * @param callable $callback Site-local lifecycle callback.
     * @return void
     */
    protected static function for_each_network_site($callback) {
        $number = 100;
        $offset = 0;

        do {
            $site_ids = get_sites(array(
                'fields' => 'ids',
                'number' => $number,
                'offset' => $offset,
                'orderby' => 'id',
                'order' => 'ASC',
            ));
            $site_ids = is_array($site_ids) ? $site_ids : array();

            foreach ($site_ids as $site_id) {
                switch_to_blog((int) $site_id);
                try {
                    call_user_func($callback);
                } finally {
                    restore_current_blog();
                }
            }

            $processed = count($site_ids);
            $offset += $processed;
        } while ($processed === $number);
    }

    protected static function activate_single_site() {
        $token = self::acquire_upgrade_lock();
        if ('' === $token) {
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('upgrade', 'UltraCache activation deferred because another lifecycle operation owns the site lock.', array('to' => UCP_VERSION));
            }
            return false;
        }

        try {
            return self::activate_single_site_unlocked($token);
        } catch (Throwable $e) {
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('upgrade', 'UltraCache activation failed safely.', array(
                    'exception' => sanitize_key(get_class($e)),
                    'to'        => UCP_VERSION,
                ));
            }
            return false;
        } finally {
            self::release_upgrade_lock($token);
        }
    }

    /**
     * Run one site activation while the caller owns the site lifecycle lease.
     *
     * @param string $token Upgrade lock token.
     * @return bool
     */
    protected static function activate_single_site_unlocked($token) {
        if (!self::create_tables()) {
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('upgrade', 'UltraCache activation stopped because the database schema could not be verified.', array(
                    'to' => UCP_VERSION,
                ));
            }
            return false;
        }
        self::assert_upgrade_lock($token, 'activation_schema');

        $created_defaults = UCP_Options::maybe_init_defaults();
        UCP_Options::maybe_apply_performance_migration();
        UCP_Options::maybe_upgrade_exact_transaction_rules_v1();
        self::assert_upgrade_lock($token, 'activation_options');
        if ($created_defaults) {
            UCP_Options::maybe_apply_install_profile(true);
            if (class_exists('UCP_Onboarding_Wizard')) {
                UCP_Onboarding_Wizard::mark_fresh_install_pending();
            }
        }

        $runtime_issues = array();
        if (!UCP_Helpers::ensure_cache_dirs(true)) {
            $runtime_issues[] = 'cache_directories';
        } elseif (!self::cleanup_previous_version_artifacts()) {
            $runtime_issues[] = 'cache_configuration';
        }

        self::assert_upgrade_lock($token, 'activation_cache_directories');
        self::detect_duplicate_plugin_copies();
        $dropin_result = UCP_Helpers::maybe_install_own_advanced_cache_automatically($created_defaults);
        $dropin_blocked = is_array($dropin_result) && !empty($dropin_result['blocked']);
        $dropin_required = UCP_Options::get('enable_cache') && UCP_Options::get('allow_dropin_writes');
        if ($dropin_required && !$dropin_blocked && (empty($dropin_result['installed']) || empty($dropin_result['wp_cache']))) {
            $runtime_issues[] = 'page_cache_dropin';
        }

        if (class_exists('UCP_Object_Cache')) {
            $object_cache_restore = UCP_Object_Cache::restore_configured_dropin();
            if (is_wp_error($object_cache_restore)) {
                $runtime_issues[] = 'object_cache_dropin';
                if (class_exists('UCP_Diagnostics')) {
                    UCP_Diagnostics::record('object_cache', 'Object-cache drop-in was not restored during activation.', array(
                        'error_code' => sanitize_key($object_cache_restore->get_error_code()),
                    ));
                }
            }
        }
        if (!UCP_Helpers::maybe_write_browser_cache_rules()) {
            $runtime_issues[] = 'browser_cache_rules';
        }
        self::assert_upgrade_lock($token, 'activation_runtime_files');

        self::schedule_events();
        self::assert_upgrade_lock($token, 'activation_scheduling');

        self::persist_runtime_config_status($runtime_issues);
        if (!empty($runtime_issues) && class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('upgrade', 'UltraCache activated with runtime configuration issues.', array(
                'issues' => array_values(array_unique($runtime_issues)),
            ));
        }
        if (class_exists('UCP_Cache')) {
            $cache = UCP_Helpers::new_without_constructor('UCP_Cache');
            $cache->purge_and_preload_after_lifecycle_change('ultracache_activation', array('item' => UCP_BASENAME));
        }
        self::assert_upgrade_lock($token, 'activation_cache_invalidation');

        $version_saved = update_option('ucp_db_version', UCP_VERSION, false);
        if (!$version_saved && UCP_VERSION !== (string) get_option('ucp_db_version', '')) {
            throw new RuntimeException('The activated plugin version could not be committed.');
        }
        return true;
    }


    public static function deactivate($network_wide = false) {
        if (is_multisite() && (bool) $network_wide) {
            $network_id = function_exists('get_current_network_id') ? (int) get_current_network_id() : 0;
            wp_clear_scheduled_hook('ucp_network_activation_batch', array($network_id));
            delete_network_option($network_id, self::network_activation_state_key());
            delete_network_option($network_id, self::network_activation_lock_key());
            self::for_each_network_site(array(__CLASS__, 'deactivate_current_site'));
            return;
        }

        self::deactivate_single_site();
    }

    protected static function deactivate_single_site() {
        if (class_exists('UCP_Cache')) {
            $cache = UCP_Helpers::new_without_constructor('UCP_Cache');
            $cache->purge_all();
            UCP_Diagnostics::record('cache', 'Purged full cache during UltraCache deactivation');
        }
        wp_clear_scheduled_hook('ucp_preload_event');
        wp_clear_scheduled_hook('ucp_lifecycle_preload_seed_event');
        wp_clear_scheduled_hook('ucp_refresh_google_font_cache');
        wp_clear_scheduled_hook('ucp_logs_retention_cleanup');
        wp_clear_scheduled_hook(UCP_Jobs::CRON_HOOK);
        if (class_exists('UCP_Health')) {
            wp_clear_scheduled_hook(UCP_Health::CRON_HOOK);
        }
        if (class_exists('UCP_DB_Cleanup')) {
            wp_clear_scheduled_hook(UCP_DB_Cleanup::CRON_HOOK);
        }
        if (class_exists('UCP_Compat_Updater')) {
            wp_clear_scheduled_hook(UCP_Compat_Updater::FETCH_HOOK);
            wp_clear_scheduled_hook(UCP_Compat_Updater::REGEN_HOOK);
        }
        if (class_exists('UCP_Quality_Suite')) {
            wp_clear_scheduled_hook(UCP_Quality_Suite::POST_UPDATE_CHECK_HOOK);
        }
        UCP_Helpers::remove_browser_cache_rules();
        if (method_exists('UCP_Helpers', 'remove_direct_cache_rules')) {
            UCP_Helpers::remove_direct_cache_rules();
        }
        UCP_Helpers::remove_own_advanced_cache_stub(true);
        UCP_Helpers::remove_own_wp_cache_constant(true);
        if (class_exists('UCP_Object_Cache') && method_exists('UCP_Object_Cache', 'remove_owned_dropin')) {
            UCP_Object_Cache::remove_owned_dropin();
        }
        UCP_Maintenance::unschedule();
    }

    /**
     * Remove cache artifacts from previous UltraCache versions and refresh the
     * UltraCache-owned drop-in without touching third-party cache plugins.
     */
    protected static function cleanup_previous_version_artifacts() {
        if (!UCP_Helpers::ensure_cache_dirs(true)) {
            return false;
        }

        $patterns = array(
            UCP_CACHE_DIR . 'pages/*.html',
            UCP_CACHE_DIR . 'pages/*.html.gz',
            UCP_CACHE_DIR . 'pages/*.html.br',
            UCP_CACHE_DIR . 'used-css/*.css',
            UCP_CACHE_DIR . 'used-css-served/*.css',
            UCP_CACHE_DIR . 'critical-css/*.css',
            UCP_CACHE_DIR . 'css/used-*.css',
            UCP_CACHE_DIR . 'css/critical-*.css',
            UCP_CACHE_DIR . 'css/status-*.json',
            UCP_CACHE_DIR . 'diagnostics/*.json',
            UCP_CACHE_DIR . 'min/*.*',
            UCP_CACHE_DIR . 'assets/*.*',
            UCP_CACHE_DIR . 'js/*.*',
            UCP_CACHE_DIR . 'self-host/*.*',
        );

        foreach ($patterns as $pattern) {
            UCP_Helpers::safe_glob_delete($pattern);
        }
        UCP_Helpers::safe_delete_cache_dir_contents(UCP_CACHE_DIR . 'pages-direct/');

        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            UCP_Cache_Tags::clear_all();
        } else {
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'meta/*.json');
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'tag-index/*.json');
        }

        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        if (file_exists($target) && is_readable($target) && UCP_Helpers::is_own_advanced_cache(UCP_Helpers::read_file_head($target, 64 * KB_IN_BYTES))) {
            $configuration_ready = UCP_Helpers::write_advanced_cache_stub(true);
        } else {
            $configuration_ready = UCP_Helpers::write_dropin_config(true);
        }
        if (!$configuration_ready) {
            return false;
        }

        $version_saved = update_option('ucp_last_upgrade_cleanup_version', UCP_VERSION, false);
        return $version_saved || UCP_VERSION === (string) get_option('ucp_last_upgrade_cleanup_version', '');
    }

    /**
     * Detect old duplicate UltraCache Pro copies during activation.
     *
     * Detection is non-destructive. Actual deactivation or deletion should stay
     * behind an explicit admin action because plugin removal is a high-impact change.
     */
    protected static function detect_duplicate_plugin_copies() {
        if (!is_admin()) {
            return;
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $duplicates = array();

        foreach ($plugins as $basename => $data) {
            if ($basename === UCP_BASENAME) {
                continue;
            }

            if (self::is_duplicate_ultracache_plugin($basename, $data)) {
                $duplicates[] = $basename;
            }
        }

        if (empty($duplicates)) {
            delete_option('ucp_duplicate_plugin_cleanup_candidates');
            delete_option('ucp_duplicate_plugin_cleanup_result');
            return;
        }

        update_option('ucp_duplicate_plugin_cleanup_candidates', array_values($duplicates), false);

        $result = array(
            'version'   => UCP_VERSION,
            'attempted' => array(),
            'deleted'   => array(),
            'failed'    => array(),
            'candidates' => array_values($duplicates),
            'status'    => 'manual_review_required',
        );
        update_option('ucp_duplicate_plugin_cleanup_result', $result, false);
    }

    /**
     * Detect whether another installed plugin is a previous UltraCache Pro copy.
     */
    protected static function is_duplicate_ultracache_plugin($basename, $data) {
        if (!is_scalar($basename)) {
            return false;
        }
        $basename = (string) $basename;
        $data = is_array($data) ? $data : array();
        $folder = dirname($basename);
        $plugin_file = WP_PLUGIN_DIR . '/' . $basename;
        $name = isset($data['Name']) && is_scalar($data['Name']) ? trim((string) $data['Name']) : '';
        $text_domain = isset($data['TextDomain']) && is_scalar($data['TextDomain']) ? trim((string) $data['TextDomain']) : '';

        if ('UltraCache Pro' === $name || 'ultracache-pro' === $text_domain) {
            return true;
        }

        $known_folders = array(
            'ultracache-pro',
            'ultracache-pro-previous',
            'ultracache-pro-fixed',
            'ultracache-pro-installer-pagespeed-boost-verified',
        );
        if (in_array($folder, $known_folders, true)) {
            return true;
        }

        if (!is_readable($plugin_file)) {
            return false;
        }

        $contents = UCP_Helpers::read_file($plugin_file, 8192);
        if (!is_string($contents)) {
            return false;
        }

        $strong_markers = array(
            "define('UCP_VERSION'",
            'define(\'UCP_VERSION\'',
            'define("UCP_VERSION"',
            'UCP_BASENAME',
            'class UCP_Plugin',
            'Text Domain: ultracache-pro',
        );

        foreach ($strong_markers as $marker) {
            if (false !== strpos($contents, $marker)) {
                return true;
            }
        }

        return false;
    }

}
