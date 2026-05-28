<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log package exports plugin-owned diagnostic tables with fixed SQL and sanitized output.

trait UCP_Log_Package_Data_Trait {
    protected static function manifest() {
        return array(
            'generated_at' => gmdate('c'),
            'plugin'       => 'UltraCache Pro',
            'version'      => defined('UCP_VERSION') ? UCP_VERSION : '',
            'format'       => 'jsonl-and-json',
            'contents'     => array('system-info', 'settings-redacted', 'status', 'queue-summary', 'runtime-cache-test', 'conflicts', 'release-checklist', 'recent-jobs', 'recent-db-logs', 'recent-diagnostics', 'file-logs'),
        );
    }

    protected static function system_info() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $theme = wp_get_theme();
        return array(
            'site_url'       => self::redact(home_url('/')),
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => PHP_VERSION,
            'plugin_version' => defined('UCP_VERSION') ? UCP_VERSION : '',
            'environment'    => function_exists('wp_get_environment_type') ? wp_get_environment_type() : '',
            'is_multisite'   => is_multisite(),
            'wp_debug'       => defined('WP_DEBUG') && WP_DEBUG,
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'wp_cache'       => UCP_Helpers::has_valid_wp_cache_constant(),
            'dropin_config'  => class_exists('UCP_Helpers') ? file_exists(UCP_Helpers::dropin_config_path()) : false,
            'object_cache'   => wp_using_ext_object_cache(),
            'theme'          => array('name' => $theme->get('Name'), 'template' => $theme->get_template(), 'stylesheet' => $theme->get_stylesheet()),
            'active_plugins' => (array) get_option('active_plugins', array()),
        );
    }

    protected static function queue_summary() {
        return class_exists('UCP_Jobs') ? array('counts' => UCP_Jobs::get_summary(), 'runner' => UCP_Jobs::get_runner_status()) : array();
    }

    protected static function recent_jobs($limit) {
        return UCP_Jobs::query(array('per_page' => $limit, 'paged' => 1))['rows'];
    }

    protected static function recent_logs($limit) {
        return class_exists('UCP_Logger') ? UCP_Logger::query(array('per_page' => $limit, 'paged' => 1))['rows'] : array();
    }

    protected static function recent_diagnostics($limit) {
        return class_exists('UCP_Diagnostics') ? UCP_Diagnostics::query(array('per_page' => $limit, 'paged' => 1))['rows'] : array();
    }

    protected static function add_json($zip, $name, $data) {
        $zip->addFromString($name, wp_json_encode(self::redact($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected static function add_jsonl($zip, $name, $rows) {
        $out = '';
        foreach ((array) $rows as $row) {
            $out .= wp_json_encode(self::redact($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }
        $zip->addFromString($name, $out);
    }
}
