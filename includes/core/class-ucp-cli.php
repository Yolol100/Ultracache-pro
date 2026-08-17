<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_CLI {
    /**
     * Print a JSON payload or terminate with a clear encoding error.
     *
     * @param mixed $value Payload.
     * @param int   $flags JSON flags.
     * @return void
     */
    private static function output_json($value, $flags = 0) {
        $encoded = UCP_Helpers::safe_json_encode($value, $flags);
        if (!is_string($encoded) || '' === $encoded) {
            \WP_CLI::error(__('De uitvoer kon niet veilig als JSON worden opgebouwd.', 'ultracache-pro'));
        }
        \WP_CLI::line($encoded);
    }

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
        \WP_CLI::log(sprintf(__('Cachemap schrijfbaar: %s', 'ultracache-pro'), !empty($health['cache_dir_writable']) ? __('ja', 'ultracache-pro') : __('nee', 'ultracache-pro')));
        \WP_CLI::log(sprintf(__('Advanced-cache aanwezig: %s', 'ultracache-pro'), !empty($health['advanced_cache']) ? __('ja', 'ultracache-pro') : __('nee', 'ultracache-pro')));
        \WP_CLI::log(sprintf(__('Wachtende taken: %d', 'ultracache-pro'), (int) $health['jobs_pending']));
        \WP_CLI::log(sprintf(__('Mislukte taken: %d', 'ultracache-pro'), (int) $health['jobs_failed']));
        \WP_CLI::log(sprintf(__('Drop-inconfiguratie aanwezig: %s', 'ultracache-pro'), file_exists(UCP_Helpers::dropin_config_path()) ? __('ja', 'ultracache-pro') : __('nee', 'ultracache-pro')));
        $conflicts = UCP_Compat::recommended_disabled_features();
        \WP_CLI::log(sprintf(__('Veilige modus door conflicten: %s', 'ultracache-pro'), !empty($conflicts) ? implode(', ', $conflicts) : __('geen', 'ultracache-pro')));
    }

    public static function purge() {
        if (!class_exists('UCP_Cache') || !method_exists('UCP_Cache', 'clear_all')) {
            \WP_CLI::error(__('De UltraCache-cachemodule is niet beschikbaar.', 'ultracache-pro'));
        }

        try {
            UCP_Cache::clear_all();
        } catch (Throwable $e) {
            \WP_CLI::error(sprintf(__('Wissen van de UltraCache-cache is mislukt: %s', 'ultracache-pro'), $e->getMessage()));
        }

        \WP_CLI::success(__('De UltraCache-cache is gewist.', 'ultracache-pro'));
    }

    public static function preload() {
        if (class_exists('UCP_Preload')) {
            UCP_Preload::run_now();
            \WP_CLI::success(__('De UltraCache-preload is gestart.', 'ultracache-pro'));
            return;
        }
        \WP_CLI::warning(__('De preloadmodule is niet beschikbaar.', 'ultracache-pro'));
    }


    public static function settings_export($args = array()) {
        $settings = UCP_Options::settings_for_export();
        self::output_json($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function settings_import($args = array()) {
        $file = isset($args[0]) ? (string) $args[0] : '';
        if ('' === $file || is_link($file) || !is_file($file) || !is_readable($file)) {
            \WP_CLI::error(__('Geef een leesbaar, regulier JSON-instellingenbestand op.', 'ultracache-pro'));
        }
        $max_bytes = 256 * 1024;
        $size = filesize($file);
        if (false === $size || $size <= 0 || $size > $max_bytes) {
            \WP_CLI::error(__('Het JSON-instellingenbestand is leeg of groter dan 256 KB.', 'ultracache-pro'));
        }
        $raw = UCP_Helpers::read_file($file, $max_bytes + 1);
        if ('' === trim((string) $raw) || strlen((string) $raw) > $max_bytes) {
            \WP_CLI::error(__('Het JSON-instellingenbestand kon niet veilig worden gelezen.', 'ultracache-pro'));
        }
        $decoded = UCP_Helpers::safe_json_decode_array((string) $raw, array(), 32, 0, 1000, 65536);
        if (empty($decoded)) {
            \WP_CLI::error(__('Ongeldig of leeg JSON-instellingenbestand.', 'ultracache-pro'));
        }
        $settings = UCP_Options::validate_import_payload($decoded);
        if (empty($settings)) {
            \WP_CLI::error(__('Geen geldige UltraCache-instellingen gevonden.', 'ultracache-pro'));
        }
        if (!UCP_Options::update($settings)) {
            \WP_CLI::error(__('De UltraCache-instellingen konden niet worden opgeslagen.', 'ultracache-pro'));
        }
        \WP_CLI::success(__('De UltraCache-instellingen zijn geïmporteerd.', 'ultracache-pro'));
    }


    public static function diagnostics($args = array(), $assoc_args = array()) {
        if (!is_array($assoc_args)) {
            $assoc_args = array();
        }
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
            self::output_json($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }
        \WP_CLI::log(__('UltraCache-diagnostiek', 'ultracache-pro'));
        \WP_CLI::log(sprintf(__('Geschatte score: %d/100', 'ultracache-pro'), isset($payload['quality_summary']['score_estimate']) ? (int) $payload['quality_summary']['score_estimate'] : 0));
        \WP_CLI::log(sprintf(__('Aantal conflicten: %d', 'ultracache-pro'), count((array) $payload['conflicts'])));
        \WP_CLI::log(sprintf(__('Runtimetests: %s', 'ultracache-pro'), !empty($payload['runtime_tests']['generated_at']) ? $payload['runtime_tests']['generated_at'] : __('niet uitgevoerd', 'ultracache-pro')));
        if (!empty($payload['quality_summary']['gates'])) {
            foreach ((array) $payload['quality_summary']['gates'] as $gate) {
                \WP_CLI::warning(wp_strip_all_tags((string) $gate));
            }
        } else {
            \WP_CLI::success(__('Geen UltraCache-kwaliteitscontrole blokkeert momenteel de statische diagnosesamenvatting.', 'ultracache-pro'));
        }
    }

    public static function runtime_tests($args = array(), $assoc_args = array()) {
        if (!is_array($assoc_args)) {
            $assoc_args = array();
        }
        if (!class_exists('UCP_Runtime_Tests')) {
            \WP_CLI::error(__('De UltraCache-runtimetests zijn niet beschikbaar.', 'ultracache-pro'));
        }
        $results = UCP_Runtime_Tests::run_all();
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
        if ('json' === $format) {
            self::output_json($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
            \WP_CLI::error(__('Gebruik nginx of apache.', 'ultracache-pro'));
        }
        if (method_exists('UCP_Helpers', 'write_direct_cache_server_rule_exports')) {
            UCP_Helpers::write_direct_cache_server_rule_exports();
        }
        $rules = method_exists('UCP_Helpers', 'direct_cache_server_rules') ? UCP_Helpers::direct_cache_server_rules($server) : array();
        if (empty($rules)) {
            \WP_CLI::error(__('De serverregels voor directe cache zijn niet beschikbaar.', 'ultracache-pro'));
        }
        \WP_CLI::line(implode("\n", $rules));
        if ('nginx' === $server) {
            \WP_CLI::warning(__('Plaats de nginx-configuratie in het server{}-blok vóór de PHP-locatie en laat een serverbeheerder nginx herladen. UltraCache kan nginx niet veilig automatisch aanpassen.', 'ultracache-pro'));
        } elseif (!UCP_Options::get('enable_direct_cache_htaccess')) {
            \WP_CLI::warning(__('Automatisch schrijven naar Apache .htaccess is beschikbaar maar uitgeschakeld. Schakel enable_direct_cache_htaccess pas in na controle op staging.', 'ultracache-pro'));
        }
    }

    public static function renderer_test($args = array(), $assoc_args = array()) {
        if (!class_exists('UCP_Render_Bridge')) {
            \WP_CLI::error(__('De headless-rendererbrug is niet beschikbaar.', 'ultracache-pro'));
        }
        $url = isset($args[0]) ? (string) $args[0] : home_url('/');
        $result = UCP_Render_Bridge::test_endpoint($url);
        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
        if ('json' === $format) {
            self::output_json($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }
        \WP_CLI::success(isset($result['message']) ? (string) $result['message'] : __('De renderertest is geslaagd.', 'ultracache-pro'));
        foreach ((array) $result as $key => $value) {
            if (is_scalar($value)) {
                \WP_CLI::log($key . ': ' . (is_bool($value) ? ($value ? __('ja', 'ultracache-pro') : __('nee', 'ultracache-pro')) : (string) $value));
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
        if (!is_array($assoc_args)) {
            $assoc_args = array();
        }
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
            self::output_json($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }
        \WP_CLI::log(sprintf(__('Hoofdschakelaar: %s', 'ultracache-pro'), $payload['enabled'] ? __('aan', 'ultracache-pro') : __('uit', 'ultracache-pro')));
        \WP_CLI::log(sprintf(__('Frequentie: %s', 'ultracache-pro'), $payload['frequency']));
        \WP_CLI::log(sprintf(__('Te behouden revisies: %d', 'ultracache-pro'), (int) $payload['keep_revisions']));
        \WP_CLI::log(sprintf(__('Volgende planning: %s', 'ultracache-pro'), null === $payload['next_run_utc'] ? __('niet gepland', 'ultracache-pro') : $payload['next_run_utc'] . ' UTC'));
        \WP_CLI::log(__('Selectie per taak:', 'ultracache-pro'));
        foreach ($payload['tasks'] as $key => $on) {
            \WP_CLI::log('  ' . str_pad($key, 32) . ($on ? __('aan', 'ultracache-pro') : __('uit', 'ultracache-pro')));
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
            \WP_CLI::error(__('De UltraCache-module voor databaseopschoning is niet beschikbaar.', 'ultracache-pro'));
        }
        $runner = new UCP_DB_Cleanup();
        $results = $runner->run_cleanup('cli');
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
        if ('json' === $format) {
            self::output_json($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }
        \WP_CLI::success(__('De UltraCache-databaseopschoning is afgerond.', 'ultracache-pro'));
        foreach ((array) $results as $key => $value) {
            if (is_scalar($value)) {
                \WP_CLI::log('  ' . str_pad((string) $key, 36) . (is_bool($value) ? ($value ? __('ja', 'ultracache-pro') : __('nee', 'ultracache-pro')) : (string) $value));
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
            \WP_CLI::error(sprintf(__('Gebruik een van de volgende waarden: %s', 'ultracache-pro'), implode(', ', self::db_cleanup_frequencies())));
        }
        if (!UCP_Options::update(array(
            'db_cleanup_frequency' => $frequency,
            'enable_db_cleanup'    => 'off' === $frequency ? 0 : 1,
        ))) {
            \WP_CLI::error(__('De UltraCache-instellingen voor databaseopschoning konden niet worden opgeslagen.', 'ultracache-pro'));
        }
        if (class_exists('UCP_DB_Cleanup') && method_exists('UCP_DB_Cleanup', 'sync_schedule')) {
            UCP_DB_Cleanup::sync_schedule();
        }
        \WP_CLI::success(sprintf(__('De frequentie voor UltraCache-databaseopschoning is ingesteld op %s.', 'ultracache-pro'), $frequency));
        $next = wp_next_scheduled('ucp_db_cleanup_event');
        if ($next) {
            \WP_CLI::log(sprintf(__('Volgende uitvoering: %s UTC', 'ultracache-pro'), gmdate('Y-m-d H:i:s', (int) $next)));
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
        if (!is_array($assoc_args)) {
            $assoc_args = array();
        }
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
            self::output_json($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }
        \WP_CLI::log(sprintf(__('Hoofdschakelaar (afgeleid): %s', 'ultracache-pro'), $payload['master_enabled'] ? __('aan', 'ultracache-pro') : __('uit', 'ultracache-pro')));
        \WP_CLI::log(sprintf(__('Algemene frequentie: %d s', 'ultracache-pro'), (int) $payload['common_frequency_seconds']));
        foreach ($payload['areas'] as $area => $state) {
            \WP_CLI::log(sprintf(__('  %1$-9s gedrag=%2$-7s frequentie=%3$d s', 'ultracache-pro'), $area, $state['behavior'], $state['frequency']));
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
            \WP_CLI::error(sprintf(__('Gebied moet een van deze waarden zijn: %s', 'ultracache-pro'), implode(', ', $allowed['areas'])));
        }
        if (!in_array($behavior, $allowed['behaviors'], true)) {
            \WP_CLI::error(sprintf(__('Gedrag moet een van deze waarden zijn: %s', 'ultracache-pro'), implode(', ', $allowed['behaviors'])));
        }
        if (!UCP_Options::update(array('heartbeat_' . $area . '_behavior' => $behavior))) {
            \WP_CLI::error(__('Het heartbeatgedrag kon niet worden opgeslagen.', 'ultracache-pro'));
        }
        \WP_CLI::success(sprintf(__('Heartbeatgedrag voor %1$s is ingesteld op %2$s.', 'ultracache-pro'), $area, $behavior));
    }

    /**
     * `wp ultracache heartbeat frequency <frontend|editor|backend|all> <seconds>` — set the heartbeat interval. Values are clamped to WP-safe range 15..300.
     *
     * @param array<int,string> $args
     * @return void
     */
    public static function heartbeat_frequency($args = array()) {
        if (!is_array($args)) {
            $args = is_scalar($args) ? array($args) : array();
        }
        $args = array_values(array_filter($args, 'is_scalar'));
        $target = isset($args[0]) ? sanitize_key((string) $args[0]) : '';
        $seconds = isset($args[1]) ? absint($args[1]) : 0;
        $opts = self::heartbeat_options();
        $allowed_targets = array_merge(array('all'), $opts['areas']);
        if (!in_array($target, $allowed_targets, true)) {
            \WP_CLI::error(sprintf(__('Doel moet een van deze waarden zijn: %s', 'ultracache-pro'), implode(', ', $allowed_targets)));
        }
        if ($seconds < 15 || $seconds > 300) {
            \WP_CLI::error(__('Het aantal seconden moet tussen 15 en 300 liggen.', 'ultracache-pro'));
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
        if (!UCP_Options::update($update)) {
            \WP_CLI::error(__('De heartbeatfrequentie kon niet worden opgeslagen.', 'ultracache-pro'));
        }
        \WP_CLI::success(sprintf(__('Heartbeatfrequentie voor %1$s is ingesteld op %2$d s.', 'ultracache-pro'), $target, $seconds));
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
        if (!is_array($assoc_args)) {
            $assoc_args = array();
        }
        if (!class_exists('UCP_CWV_Timeseries')) {
            \WP_CLI::error(__('De UltraCache-opslag voor CWV-tijdreeksen is niet beschikbaar.', 'ultracache-pro'));
        }
        $days   = isset($assoc_args['days']) ? absint($assoc_args['days']) : (int) UCP_Options::get('cwv_timeseries_retention_days', 7);
        $days   = max(1, min(30, $days));
        $metric = isset($assoc_args['metric']) ? sanitize_key((string) $assoc_args['metric']) : null;
        $device = isset($assoc_args['device']) ? sanitize_key((string) $assoc_args['device']) : null;
        $series = UCP_CWV_Timeseries::get_series($metric, $device, $days);
        $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';

        if ('json' === $format) {
            self::output_json(array(
                'days'        => $days,
                'metricFilter' => $metric,
                'deviceFilter' => $device,
                'bucketCount' => UCP_CWV_Timeseries::bucket_count(),
                'series'      => $series,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        \WP_CLI::log(sprintf(__('CWV-tijdreeks — venster: %1$d dagen, totaal opgeslagen tijdvakken: %2$d', 'ultracache-pro'), $days, UCP_CWV_Timeseries::bucket_count()));
        if (empty($series)) {
            \WP_CLI::warning(__('Geen metingen in het gekozen venster. Schakel enable_cwv_monitoring in en verzamel eerst verkeer.', 'ultracache-pro'));
            return;
        }
        foreach ($series as $m => $devs) {
            foreach ($devs as $d => $buckets) {
                \WP_CLI::log(sprintf(__('%1$s/%2$s (%3$d tijdvakken)', 'ultracache-pro'), $m, $d, count($buckets)));
                foreach ($buckets as $bk) {
                    \WP_CLI::log(sprintf(__('  %1$s  n=%2$-5d gem=%3$-8s max=%4$s', 'ultracache-pro'),
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
            \WP_CLI::error(__('De UltraCache-opslag voor CWV-tijdreeksen is niet beschikbaar.', 'ultracache-pro'));
        }
        UCP_CWV_Timeseries::clear();
        \WP_CLI::success(__('De UltraCache-CWV-tijdreeks is gewist.', 'ultracache-pro'));
    }

    public static function conflicts() {
        $conflicts = UCP_Compat::detected_conflicts();
        if (empty($conflicts)) {
            \WP_CLI::success(__('Geen bekende UltraCache-conflicten gedetecteerd.', 'ultracache-pro'));
            return;
        }
        foreach ($conflicts as $conflict) {
            \WP_CLI::log($conflict['type'] . ': ' . $conflict['label']);
        }
        \WP_CLI::warning(__('Los de bovenstaande punten op voordat je alle optimalisatielagen inschakelt.', 'ultracache-pro'));
    }
}
