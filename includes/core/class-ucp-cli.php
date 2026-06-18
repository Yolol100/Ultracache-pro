<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_CLI {
    public static function bootstrap() {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }
        \WP_CLI::add_command('ultracache status', array(__CLASS__, 'status'));
        \WP_CLI::add_command('ultracache purge', array(__CLASS__, 'purge'));
        \WP_CLI::add_command('ultracache preload', array(__CLASS__, 'preload'));
        \WP_CLI::add_command('ultracache conflicts', array(__CLASS__, 'conflicts'));
        \WP_CLI::add_command('ultracache settings export', array(__CLASS__, 'settings_export'));
        \WP_CLI::add_command('ultracache settings import', array(__CLASS__, 'settings_import'));
        \WP_CLI::add_command('ultracache diagnostics', array(__CLASS__, 'diagnostics'));
        \WP_CLI::add_command('ultracache runtime-tests', array(__CLASS__, 'runtime_tests'));
        \WP_CLI::add_command('ultracache server-rules', array(__CLASS__, 'server_rules'));
        \WP_CLI::add_command('ultracache renderer-test', array(__CLASS__, 'renderer_test'));
        \WP_CLI::add_command('ultracache db-cleanup status', array(__CLASS__, 'db_cleanup_status'));
        \WP_CLI::add_command('ultracache db-cleanup run', array(__CLASS__, 'db_cleanup_run'));
        \WP_CLI::add_command('ultracache db-cleanup schedule', array(__CLASS__, 'db_cleanup_schedule'));
        \WP_CLI::add_command('ultracache heartbeat status', array(__CLASS__, 'heartbeat_status'));
        \WP_CLI::add_command('ultracache heartbeat set', array(__CLASS__, 'heartbeat_set'));
        \WP_CLI::add_command('ultracache heartbeat frequency', array(__CLASS__, 'heartbeat_frequency'));
        \WP_CLI::add_command('ultracache cwv timeseries', array(__CLASS__, 'cwv_timeseries'));
        \WP_CLI::add_command('ultracache cwv clear-timeseries', array(__CLASS__, 'cwv_clear_timeseries'));
    }

    public static function status() {
        $health = UCP_Health::latest();
        \WP_CLI::log('Cache dir writable: ' . (!empty($health['cache_dir_writable']) ? 'yes' : 'no'));
        \WP_CLI::log('Advanced cache present: ' . (!empty($health['advanced_cache']) ? 'yes' : 'no'));
        \WP_CLI::log('Pending jobs: ' . (int) $health['jobs_pending']);
        \WP_CLI::log('Failed jobs: ' . (int) $health['jobs_failed']);
        \WP_CLI::log('Drop-in config present: ' . (file_exists(UCP_Helpers::dropin_config_path()) ? 'yes' : 'no'));
        $conflicts = UCP_Compat::recommended_disabled_features();
        \WP_CLI::log('Conflict-derived safe mode: ' . (!empty($conflicts) ? implode(', ', $conflicts) : 'none'));
    }

    public static function purge() {
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'assets/*.*');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'used-css/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'critical-css/*.css');
        \WP_CLI::success('UltraCache cache cleared.');
    }

    public static function preload() {
        if (class_exists('UCP_Preload')) {
            UCP_Preload::run_now();
            \WP_CLI::success('UltraCache preload started.');
            return;
        }
        \WP_CLI::warning('Preload module unavailable.');
    }


    public static function settings_export($args = array()) {
        $settings = UCP_Options::settings_for_export();
        \WP_CLI::line(wp_json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function settings_import($args = array()) {
        $file = isset($args[0]) ? (string) $args[0] : '';
        if ('' === $file || !is_file($file) || !is_readable($file)) {
            \WP_CLI::error('Please provide a readable JSON settings file.');
        }
        $raw = UCP_Helpers::read_file($file);
        $decoded = json_decode((string) $raw, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            \WP_CLI::error('Invalid JSON settings file.');
        }
        $settings = UCP_Options::validate_import_payload($decoded);
        if (empty($settings)) {
            \WP_CLI::error('No valid UltraCache settings found.');
        }
        UCP_Options::update($settings);
        \WP_CLI::success('UltraCache settings imported.');
    }


    public static function diagnostics($args = array(), $assoc_args = array()) {
        if (class_exists('UCP_Health')) {
            UCP_Health::run_checks();
        }
        $payload = array(
            'health' => class_exists('UCP_Health') ? UCP_Health::latest() : array(),
            'runtime_tests' => class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array(),
            'conflicts' => class_exists('UCP_Compat') ? UCP_Compat::detected_conflicts() : array(),
            'quality_summary' => class_exists('UCP_Support_Report') ? UCP_Support_Report::quality_summary() : array(),
        );
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }
        \WP_CLI::log('UltraCache diagnostics');
        \WP_CLI::log('Score estimate: ' . (isset($payload['quality_summary']['score_estimate']) ? (int) $payload['quality_summary']['score_estimate'] : 0) . '/100');
        \WP_CLI::log('Conflict count: ' . count((array) $payload['conflicts']));
        \WP_CLI::log('Runtime tests: ' . (!empty($payload['runtime_tests']['generated_at']) ? $payload['runtime_tests']['generated_at'] : 'not run'));
        if (!empty($payload['quality_summary']['gates'])) {
            foreach ((array) $payload['quality_summary']['gates'] as $gate) {
                \WP_CLI::warning(wp_strip_all_tags((string) $gate));
            }
        } else {
            \WP_CLI::success('No UltraCache quality gates currently blocking the static diagnostic summary.');
        }
    }

    public static function runtime_tests($args = array(), $assoc_args = array()) {
        if (!class_exists('UCP_Runtime_Tests')) {
            \WP_CLI::error('UltraCache runtime tests are unavailable.');
        }
        $results = UCP_Runtime_Tests::run_all();
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }
        foreach ($results as $key => $result) {
            if (!is_array($result) || empty($result['status'])) {
                continue;
            }
            $line = $key . ': ' . $result['status'];
            if ('pass' === $result['status']) {
                \WP_CLI::success($line);
            } elseif ('warning' === $result['status']) {
                \WP_CLI::warning($line);
            } else {
                \WP_CLI::log($line);
            }
        }
    }

    public static function server_rules($args = array(), $assoc_args = array()) {
        $server = isset($args[0]) ? sanitize_key((string) $args[0]) : 'nginx';
        if (!in_array($server, array('nginx', 'apache', 'htaccess'), true)) {
            \WP_CLI::error('Use nginx or apache.');
        }
        if (method_exists('UCP_Helpers', 'write_direct_cache_server_rule_exports')) {
            UCP_Helpers::write_direct_cache_server_rule_exports();
        }
        $rules = method_exists('UCP_Helpers', 'direct_cache_server_rules') ? UCP_Helpers::direct_cache_server_rules($server) : array();
        if (empty($rules)) {
            \WP_CLI::error('Direct-cache server rules are unavailable.');
        }
        \WP_CLI::line(implode("\n", $rules));
        if ('nginx' === $server) {
            \WP_CLI::warning('Nginx config must be placed in the server{} block before the PHP location and reloaded by a server admin. UltraCache cannot safely edit nginx automatically.');
        } elseif (!UCP_Options::get('enable_direct_cache_htaccess')) {
            \WP_CLI::warning('Apache .htaccess auto-write is available but disabled. Enable enable_direct_cache_htaccess after staging verification.');
        }
    }

    public static function renderer_test($args = array(), $assoc_args = array()) {
        if (!class_exists('UCP_Render_Bridge')) {
            \WP_CLI::error('Headless renderer bridge is unavailable.');
        }
        $url = isset($args[0]) ? (string) $args[0] : home_url('/');
        $result = UCP_Render_Bridge::test_endpoint($url);
        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }
        \WP_CLI::success(isset($result['message']) ? (string) $result['message'] : 'Renderer test passed.');
        foreach ((array) $result as $key => $value) {
            if (is_scalar($value)) {
                \WP_CLI::log($key . ': ' . (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value));
            }
        }
    }

    /**
     * Allowed db_cleanup_frequency values, mirrored from UCP_DB_Cleanup::sync_schedule().
     *
     * @return array<int,string>
     */
    private static function db_cleanup_frequencies() {
        return array('off', 'daily', 'weekly', 'monthly');
    }

    /**
     * Per-task cleanup option keys, mirrored from UCP_DB_Cleanup_Runner_Trait::selected_operations().
     *
     * @return array<int,string>
     */
    private static function db_cleanup_task_keys() {
        return array(
            'db_cleanup_post_revisions',
            'db_cleanup_auto_drafts',
            'db_cleanup_drafts',
            'db_cleanup_expired_transients',
            'db_cleanup_all_transients',
            'db_cleanup_spam_comments',
            'db_cleanup_trashed_comments',
            'db_cleanup_trashed_posts',
            'db_cleanup_wc_sessions',
            'db_cleanup_optimize_tables',
            'db_cleanup_optimize_all_tables',
        );
    }

    /**
     * `wp ultracache db-cleanup status` — show toggle state, schedule, next cron run, per-task selection and revisions-keep threshold.
     *
     * @param array<int,string> $args
     * @param array<string,mixed> $assoc_args
     * @return void
     */
    public static function db_cleanup_status($args = array(), $assoc_args = array()) {
        $payload = array(
            'enabled'           => (bool) UCP_Options::get('enable_db_cleanup'),
            'frequency'         => (string) UCP_Options::get('db_cleanup_frequency'),
            'keep_revisions'    => (int) UCP_Options::get('db_keep_post_revisions', 5),
            'next_run_utc'      => null,
            'tasks'             => array(),
        );
        $next = wp_next_scheduled('ucp_db_cleanup_event');
        if ($next) {
            $payload['next_run_utc'] = gmdate('Y-m-d H:i:s', (int) $next);
        }
        foreach (self::db_cleanup_task_keys() as $key) {
            $payload['tasks'][$key] = (bool) UCP_Options::get($key);
        }
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }
        \WP_CLI::log('Master toggle:    ' . ($payload['enabled'] ? 'on' : 'off'));
        \WP_CLI::log('Frequency:        ' . $payload['frequency']);
        \WP_CLI::log('Keep revisions:   ' . $payload['keep_revisions']);
        \WP_CLI::log('Next scheduled:   ' . (null === $payload['next_run_utc'] ? 'not scheduled' : $payload['next_run_utc'] . ' UTC'));
        \WP_CLI::log('Per-task selection:');
        foreach ($payload['tasks'] as $key => $on) {
            \WP_CLI::log('  ' . str_pad($key, 32) . ($on ? 'on' : 'off'));
        }
    }

    /**
     * `wp ultracache db-cleanup run` — execute the cleanup now using the current per-task selection.
     *
     * @param array<int,string> $args
     * @param array<string,mixed> $assoc_args
     * @return void
     */
    public static function db_cleanup_run($args = array(), $assoc_args = array()) {
        if (!class_exists('UCP_DB_Cleanup')) {
            \WP_CLI::error('UltraCache database cleanup module unavailable.');
        }
        $runner = new UCP_DB_Cleanup();
        $results = $runner->run_cleanup('cli');
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }
        \WP_CLI::success('UltraCache database cleanup finished.');
        foreach ((array) $results as $key => $value) {
            if (is_scalar($value)) {
                \WP_CLI::log('  ' . str_pad((string) $key, 36) . (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value));
            }
        }
    }

    /**
     * `wp ultracache db-cleanup schedule <off|daily|weekly|monthly>` — change the cron frequency. Auto-toggles enable_db_cleanup.
     *
     * @param array<int,string> $args
     * @return void
     */
    public static function db_cleanup_schedule($args = array()) {
        $frequency = isset($args[0]) ? sanitize_key((string) $args[0]) : '';
        if (!in_array($frequency, self::db_cleanup_frequencies(), true)) {
            \WP_CLI::error('Use one of: ' . implode(', ', self::db_cleanup_frequencies()));
        }
        UCP_Options::update(array(
            'db_cleanup_frequency' => $frequency,
            'enable_db_cleanup'    => 'off' === $frequency ? 0 : 1,
        ));
        if (class_exists('UCP_DB_Cleanup') && method_exists('UCP_DB_Cleanup', 'sync_schedule')) {
            UCP_DB_Cleanup::sync_schedule();
        }
        \WP_CLI::success('UltraCache db-cleanup frequency set to ' . $frequency . '.');
        $next = wp_next_scheduled('ucp_db_cleanup_event');
        if ($next) {
            \WP_CLI::log('Next run: ' . gmdate('Y-m-d H:i:s', (int) $next) . ' UTC');
        }
    }

    /**
     * Allowed heartbeat areas + behavior values, mirrored from the sanitizer.
     *
     * @return array<string,array<int,string>>
     */
    private static function heartbeat_options() {
        return array(
            'areas'      => array('frontend', 'editor', 'backend'),
            'behaviors'  => array('keep', 'reduce', 'disable'),
        );
    }

    /**
     * `wp ultracache heartbeat status` — show per-area behavior, per-area frequency and the derived master toggle.
     *
     * @param array<int,string> $args
     * @param array<string,mixed> $assoc_args
     * @return void
     */
    public static function heartbeat_status($args = array(), $assoc_args = array()) {
        $opts = self::heartbeat_options();
        $payload = array(
            'master_enabled' => (bool) UCP_Options::get('enable_heartbeat_control'),
            'common_frequency_seconds' => (int) UCP_Options::get('heartbeat_frequency', 60),
            'areas' => array(),
        );
        foreach ($opts['areas'] as $area) {
            $payload['areas'][$area] = array(
                'behavior'  => (string) UCP_Options::get('heartbeat_' . $area . '_behavior', 'reduce'),
                'frequency' => (int) UCP_Options::get('heartbeat_' . $area . '_frequency', 60),
            );
        }
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }
        \WP_CLI::log('Master toggle (derived): ' . ($payload['master_enabled'] ? 'on' : 'off'));
        \WP_CLI::log('Common frequency:        ' . $payload['common_frequency_seconds'] . 's');
        foreach ($payload['areas'] as $area => $state) {
            \WP_CLI::log(sprintf('  %-9s behavior=%-7s frequency=%ds', $area, $state['behavior'], $state['frequency']));
        }
    }

    /**
     * `wp ultracache heartbeat set <frontend|editor|backend> <keep|reduce|disable>` — change one area's behavior. Master toggle is auto-derived by the sanitizer.
     *
     * @param array<int,string> $args
     * @return void
     */
    public static function heartbeat_set($args = array()) {
        $area = isset($args[0]) ? sanitize_key((string) $args[0]) : '';
        $behavior = isset($args[1]) ? sanitize_key((string) $args[1]) : '';
        $allowed = self::heartbeat_options();
        if (!in_array($area, $allowed['areas'], true)) {
            \WP_CLI::error('Area must be one of: ' . implode(', ', $allowed['areas']));
        }
        if (!in_array($behavior, $allowed['behaviors'], true)) {
            \WP_CLI::error('Behavior must be one of: ' . implode(', ', $allowed['behaviors']));
        }
        UCP_Options::update(array('heartbeat_' . $area . '_behavior' => $behavior));
        \WP_CLI::success(sprintf('Heartbeat %s behavior set to %s.', $area, $behavior));
    }

    /**
     * `wp ultracache heartbeat frequency <frontend|editor|backend|all> <seconds>` — set the heartbeat interval. Values are clamped to WP-safe range 15..300.
     *
     * @param array<int,string> $args
     * @return void
     */
    public static function heartbeat_frequency($args = array()) {
        $target = isset($args[0]) ? sanitize_key((string) $args[0]) : '';
        $seconds = isset($args[1]) ? absint($args[1]) : 0;
        $opts = self::heartbeat_options();
        $allowed_targets = array_merge(array('all'), $opts['areas']);
        if (!in_array($target, $allowed_targets, true)) {
            \WP_CLI::error('Target must be one of: ' . implode(', ', $allowed_targets));
        }
        if ($seconds < 15 || $seconds > 300) {
            \WP_CLI::error('Seconds must be between 15 and 300.');
        }
        $update = array();
        if ('all' === $target) {
            $update['heartbeat_frequency'] = $seconds;
            foreach ($opts['areas'] as $area) {
                $update['heartbeat_' . $area . '_frequency'] = $seconds;
            }
        } else {
            $update['heartbeat_' . $target . '_frequency'] = $seconds;
        }
        UCP_Options::update($update);
        \WP_CLI::success(sprintf('Heartbeat %s frequency set to %ds.', $target, $seconds));
    }

    /**
     * `wp ultracache cwv timeseries [--days=N] [--metric=lcp|inp|cls|fcp|ttfb] [--device=mobile|desktop|all] [--format=table|json]`
     *  — print hourly aggregates of the RUM/CWV data for charting and trend reviews.
     *
     * @param array<int,string> $args
     * @param array<string,mixed> $assoc_args
     * @return void
     */
    public static function cwv_timeseries($args = array(), $assoc_args = array()) {
        if (!class_exists('UCP_CWV_Timeseries')) {
            \WP_CLI::error('UltraCache CWV time-series storage unavailable.');
        }
        $days   = isset($assoc_args['days']) ? absint($assoc_args['days']) : (int) get_option('cwv_timeseries_retention_days', 7);
        $days   = max(1, min(30, $days));
        $metric = isset($assoc_args['metric']) ? sanitize_key((string) $assoc_args['metric']) : null;
        $device = isset($assoc_args['device']) ? sanitize_key((string) $assoc_args['device']) : null;
        $series = UCP_CWV_Timeseries::get_series($metric, $device, $days);
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';

        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode(array(
                'days'        => $days,
                'metricFilter' => $metric,
                'deviceFilter' => $device,
                'bucketCount' => UCP_CWV_Timeseries::bucket_count(),
                'series'      => $series,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }

        \WP_CLI::log(sprintf('CWV time-series — window: %d days, total stored buckets: %d', $days, UCP_CWV_Timeseries::bucket_count()));
        if (empty($series)) {
            \WP_CLI::warning('No samples in the requested window. Enable enable_cwv_monitoring and collect some traffic first.');
            return;
        }
        foreach ($series as $m => $devs) {
            foreach ($devs as $d => $buckets) {
                \WP_CLI::log(sprintf('%s/%s (%d buckets)', $m, $d, count($buckets)));
                foreach ($buckets as $bk) {
                    \WP_CLI::log(sprintf('  %s  n=%-5d avg=%-8s max=%s',
                        gmdate('Y-m-d H:00', (int) $bk['hour']),
                        (int) $bk['n'],
                        (string) $bk['avg'],
                        (string) $bk['max']
                    ));
                }
            }
        }
    }

    /**
     * `wp ultracache cwv clear-timeseries` — drop the time-series option. Does not touch the aggregate summary or LCP profile repo.
     *
     * @return void
     */
    public static function cwv_clear_timeseries() {
        if (!class_exists('UCP_CWV_Timeseries')) {
            \WP_CLI::error('UltraCache CWV time-series storage unavailable.');
        }
        UCP_CWV_Timeseries::clear();
        \WP_CLI::success('UltraCache CWV time-series cleared.');
    }

    public static function conflicts() {
        $conflicts = UCP_Compat::detected_conflicts();
        if (empty($conflicts)) {
            \WP_CLI::success('No known UltraCache conflicts detected.');
            return;
        }
        foreach ($conflicts as $conflict) {
            \WP_CLI::log($conflict['type'] . ': ' . $conflict['label']);
        }
        \WP_CLI::warning('Resolve the items above before enabling every optimization layer.');
    }
}
