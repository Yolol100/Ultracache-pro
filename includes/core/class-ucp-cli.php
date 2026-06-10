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
