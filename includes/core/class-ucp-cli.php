<?php
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
    }

    public static function status() {
        $health = UCP_Health::latest();
        \WP_CLI::log('Cache dir writable: ' . (!empty($health['cache_dir_writable']) ? 'yes' : 'no'));
        \WP_CLI::log('Advanced cache present: ' . (!empty($health['advanced_cache']) ? 'yes' : 'no'));
        \WP_CLI::log('Pending jobs: ' . (int) $health['jobs_pending']);
        \WP_CLI::log('Failed jobs: ' . (int) $health['jobs_failed']);
        \WP_CLI::log('Drop-in config present: ' . (file_exists(UCP_Helpers::dropin_config_path()) ? 'yes' : 'no'));
        $conflicts = class_exists('UCP_Compat') ? UCP_Compat::recommended_disabled_features() : array();
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

    public static function conflicts() {
        $conflicts = class_exists('UCP_Compat') ? UCP_Compat::detected_conflicts() : array();
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
