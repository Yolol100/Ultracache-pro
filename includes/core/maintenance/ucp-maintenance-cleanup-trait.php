<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Scheduled maintenance works on validated plugin-owned custom tables and sanitized retention values.

trait UCP_Maintenance_Schedule_Trait {
    public static function register_schedule($schedules) {
        if (!is_array($schedules)) {
            $schedules = array();
        }
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
        if (class_exists('UCP_Cache_Insights')) {
            UCP_Cache_Insights::cleanup();
        }
        $cache_files_removed = self::cleanup_cache_artifacts();
        UCP_Logger::log('info', 'maintenance', 'maintenance_ran', __('Gepland onderhoud is voltooid.', 'ultracache-pro'), array('cache_files_removed' => $cache_files_removed));
    }

    /**
     * Remove expired page-cache representations and abandoned atomic-write files.
     *
     * Work is deliberately bounded per run so maintenance remains cheap on large sites.
     * A zero page-cache TTL means indefinite retention and therefore only temporary files
     * are eligible for cleanup.
     *
     * @return int Number of files removed.
     */
    public static function cleanup_cache_artifacts() {
        $now = time();
        $removed = self::cleanup_abandoned_cache_temp_files($now - DAY_IN_SECONDS, 500);
        $removed += self::cleanup_legacy_cache_lock_files($now - DAY_IN_SECONDS, 500);
        $ttl = absint(UCP_Options::get('cache_lifespan', 10)) * HOUR_IN_SECONDS;
        if ($ttl <= 0) {
            return $removed;
        }

        if (UCP_Options::get('enable_stale_cache')) {
            $ttl += absint(UCP_Options::get('stale_cache_lifespan', 24)) * HOUR_IN_SECONDS;
        }
        $removed += self::cleanup_expired_flat_page_cache($now, $ttl, 1000);
        $removed += self::cleanup_expired_direct_page_cache($now, $ttl, 1000);
        return $removed;
    }

    /**
     * Clean expired flat page-cache files, including compressed siblings.
     *
     * @param int $now         Current timestamp.
     * @param int $default_ttl Default cache lifetime in seconds.
     * @param int $limit       Maximum files inspected.
     * @return int
     */
    private static function cleanup_expired_flat_page_cache($now, $default_ttl, $limit) {
        $dir = trailingslashit(UCP_CACHE_DIR) . 'pages/';
        if (!is_dir($dir) || !is_readable($dir)) {
            return 0;
        }

        $removed = 0;
        $inspected = 0;
        $position = 0;
        $cursor = self::get_cache_cleanup_cursor('flat_pages');
        $complete = true;
        try {
            $iterator = new DirectoryIterator($dir);
            foreach ($iterator as $item) {
                if ($item->isDot() || !$item->isFile()) {
                    continue;
                }
                if ($position++ < $cursor) {
                    continue;
                }
                $name = $item->getFilename();
                if ('index.html' === $name || !preg_match('/\.html(?:\.(?:gz|br|meta\.json))?$/', $name)) {
                    continue;
                }
                if (++$inspected > $limit) {
                    $complete = false;
                    break;
                }
                $path = $item->getPathname();
                if (preg_match('/\.html\.(?:gz|br|meta\.json)$/', $name)) {
                    $base_path = UCP_Helpers::safe_preg_replace('/\.(?:gz|br|meta\.json)$/', '', $path);
                    if (is_file($base_path)) {
                        continue;
                    }
                    $retention_ttl = self::page_cache_file_retention_ttl($base_path, $default_ttl);
                    if ($retention_ttl > 0 && ($item->getMTime() + $retention_ttl) < $now && UCP_Helpers::safe_delete_file($path)) {
                        $removed++;
                    }
                    continue;
                }
                $retention_ttl = self::page_cache_file_retention_ttl($path, $default_ttl);
                if ($retention_ttl <= 0 || ($item->getMTime() + $retention_ttl) >= $now) {
                    continue;
                }
                foreach (array($path, $path . '.gz', $path . '.br', $path . '.meta.json') as $candidate) {
                    if (is_file($candidate) && UCP_Helpers::safe_delete_file($candidate)) {
                        $removed++;
                    }
                }
            }
        } catch (UnexpectedValueException $e) {
            $complete = false;
        }
        self::set_cache_cleanup_cursor('flat_pages', $complete ? 0 : $position - 1);
        return $removed;
    }

    private static function page_cache_file_retention_ttl($cache_file, $default_ttl) {
        $ttl = max(0, absint($default_ttl));
        $meta_file = (string) $cache_file . '.meta.json';
        if (!is_readable($meta_file)) {
            return $ttl;
        }
        $size = filesize($meta_file);
        if (false === $size || $size <= 0 || $size > 16384) {
            return $ttl;
        }
        $meta = UCP_Helpers::safe_json_decode(UCP_Helpers::read_file($meta_file, 256 * KB_IN_BYTES), true);
        if (!is_array($meta) || !array_key_exists('ttl', $meta)) {
            return $ttl;
        }
        $ttl = min(YEAR_IN_SECONDS, max(0, absint($meta['ttl'])));
        $stale = min(30 * DAY_IN_SECONDS, max(0, absint($meta['stale'] ?? 0)));
        return 0 === $ttl ? 0 : $ttl + $stale;
    }

    /**
     * Clean expired direct-cache representations without removing directory guards.
     *
     * @param int $now         Current timestamp.
     * @param int $default_ttl Default page-cache retention in seconds.
     * @param int $limit       Maximum files inspected.
     * @return int
     */
    private static function cleanup_expired_direct_page_cache($now, $default_ttl, $limit) {
        $dir = trailingslashit(UCP_CACHE_DIR) . 'pages-direct/';
        if (!is_dir($dir) || !is_readable($dir)) {
            return 0;
        }

        $removed = 0;
        $inspected = 0;
        $position = 0;
        $cursor = self::get_cache_cleanup_cursor('direct_pages');
        $complete = true;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                if ($position++ < $cursor) {
                    continue;
                }
                if (++$inspected > $limit) {
                    $complete = false;
                    break;
                }
                $name = $item->getFilename();
                if (!in_array($name, array('index.html', 'index.html.gz', 'index.html.br', 'index.html.meta.json'), true)) {
                    continue;
                }
                if (wp_normalize_path($item->getPath()) === untrailingslashit(wp_normalize_path($dir))) {
                    continue;
                }

                $path = $item->getPathname();
                if ('index.html' !== $name) {
                    $base_path = UCP_Helpers::safe_preg_replace('/\.(?:gz|br|meta\.json)$/', '', $path);
                    if (is_file($base_path)) {
                        continue;
                    }
                    $retention_ttl = self::page_cache_file_retention_ttl($base_path, $default_ttl);
                    if ($retention_ttl > 0 && ($item->getMTime() + $retention_ttl) < $now && UCP_Helpers::safe_delete_file($path)) {
                        $removed++;
                    }
                    continue;
                }

                $retention_ttl = self::page_cache_file_retention_ttl($path, $default_ttl);
                if ($retention_ttl <= 0 || ($item->getMTime() + $retention_ttl) >= $now) {
                    continue;
                }
                foreach (array($path, $path . '.gz', $path . '.br', $path . '.meta.json') as $candidate) {
                    if (is_file($candidate) && UCP_Helpers::safe_delete_file($candidate)) {
                        $removed++;
                    }
                }
            }
        } catch (UnexpectedValueException $e) {
            $complete = false;
        }
        self::set_cache_cleanup_cursor('direct_pages', $complete ? 0 : $position - 1);
        return $removed;
    }

    /**
     * Remove stale per-key lock files from releases before the bounded lock pool.
     * Current pooled locks use one hexadecimal filename character and remain in place.
     *
     * @param int $cutoff Oldest retained modification time.
     * @param int $limit  Maximum legacy lock files inspected.
     * @return int
     */
    private static function cleanup_legacy_cache_lock_files($cutoff, $limit) {
        $dir = trailingslashit(UCP_CACHE_DIR) . 'locks/';
        if (!is_dir($dir) || !is_readable($dir)) {
            return 0;
        }

        $removed = 0;
        $inspected = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $item) {
                if (!$item->isFile() || ++$inspected > $limit) {
                    break;
                }
                $name = $item->getFilename();
                if (1 !== preg_match('/^[a-f0-9]{16}\.lock$/', $name) || $item->getMTime() >= $cutoff) {
                    continue;
                }
                if (UCP_Helpers::safe_delete_file($item->getPathname())) {
                    $removed++;
                }
            }
        } catch (UnexpectedValueException $e) {
            return $removed;
        }
        return $removed;
    }

    /**
     * Remove cache-local temporary files left behind by interrupted atomic writes.
     *
     * @param int $cutoff Oldest retained modification time.
     * @param int $limit  Maximum files inspected.
     * @return int
     */
    private static function cleanup_abandoned_cache_temp_files($cutoff, $limit) {
        $dir = trailingslashit(UCP_CACHE_DIR);
        if (!is_dir($dir) || !is_readable($dir)) {
            return 0;
        }

        $removed = 0;
        $inspected = 0;
        $position = 0;
        $cursor = self::get_cache_cleanup_cursor('temporary_files');
        $complete = true;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                if ($position++ < $cursor) {
                    continue;
                }
                if (++$inspected > $limit) {
                    $complete = false;
                    break;
                }
                $filename = $item->getFilename();
                if (
                    0 !== strpos($filename, '.ucp-tmp-')
                    && !preg_match('/\.tmp\.[A-Za-z0-9]+(?:\.[a-z0-9_-]+)?$/i', $filename)
                ) {
                    continue;
                }
                if ($item->getMTime() < $cutoff && UCP_Helpers::safe_delete_file($item->getPathname())) {
                    $removed++;
                }
            }
        } catch (UnexpectedValueException $e) {
            $complete = false;
        }
        self::set_cache_cleanup_cursor('temporary_files', $complete ? 0 : $position - 1);
        return $removed;
    }

    /**
     * Read one bounded-cleanup cursor.
     *
     * @param string $key Cursor name.
     * @return int
     */
    private static function get_cache_cleanup_cursor($key) {
        $cursors = get_option('ucp_maintenance_cache_cursors', array());
        return is_array($cursors) && isset($cursors[$key]) ? absint($cursors[$key]) : 0;
    }

    /**
     * Persist progress so every cache directory segment eventually receives maintenance.
     *
     * @param string $key      Cursor name.
     * @param int    $position Next directory position.
     * @return void
     */
    private static function set_cache_cleanup_cursor($key, $position) {
        $cursors = get_option('ucp_maintenance_cache_cursors', array());
        $cursors = is_array($cursors) ? $cursors : array();
        $cursors[$key] = max(0, absint($position));
        update_option('ucp_maintenance_cache_cursors', $cursors, false);
    }

    public static function cleanup_logs() {
        global $wpdb;
        $days = max(7, absint(UCP_Options::get('log_retention_days', 30)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        if (UCP_Helpers::is_safe_table_name(ucp_table_name('logs'))) {
            $table = UCP_Helpers::quote_table_name(ucp_table_name('logs'));
            for ($batch = 0; $batch < 10; $batch++) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table identifier is validated and quoted before use; values are prepared.
                $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s LIMIT %d", $cutoff, 1000));
                if (!is_int($deleted) || $deleted < 1000) {
                    break;
                }
            }
        }
    }

    public static function cleanup_diagnostics() {
        global $wpdb;
        $days = max(7, absint(UCP_Options::get('diagnostics_retention_days', 14)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        if (UCP_Helpers::is_safe_table_name(ucp_table_name('diagnostics'))) {
            $table = UCP_Helpers::quote_table_name(ucp_table_name('diagnostics'));
            for ($batch = 0; $batch < 10; $batch++) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table identifier is validated and quoted before use; values are prepared.
                $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE generated_at < %s LIMIT %d", $cutoff, 1000));
                if (!is_int($deleted) || $deleted < 1000) {
                    break;
                }
            }
        }
        foreach (UCP_Helpers::safe_glob_files(UCP_CACHE_DIR . 'diagnostics/*.json', 1000) as $diagnostic_file) {
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
            for ($batch = 0; $batch < 10; $batch++) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table identifier is validated and quoted before use; values are prepared.
                $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE status IN (%s,%s) AND updated_at < %s LIMIT %d", 'success', 'failed', $cutoff, 1000));
                if (!is_int($deleted) || $deleted < 1000) {
                    break;
                }
            }
        }
    }

    public static function handle_manual_run() {
        UCP_Helpers::require_post_admin_action('ucp_run_maintenance');
        self::run();
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&maintenance=1'));
        exit;
    }
}
