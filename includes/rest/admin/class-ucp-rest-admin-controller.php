<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_REST_Admin_Controller {
    use UCP_REST_Status_Trait;
    use UCP_REST_Settings_Trait;
    use UCP_REST_Diagnostics_Trait;
    use UCP_REST_Actions_Trait;

    const REST_NAMESPACE = 'ultracache-pro/v1';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/status', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_status'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::REST_NAMESPACE, '/optimization-lifecycle', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_optimization_lifecycle'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'get_settings'),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'update_settings'),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/bulk', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'update_settings'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/export', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'export_settings'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/import', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'import_settings'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));

        register_rest_route(self::REST_NAMESPACE, '/settings/snapshots', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'settings_snapshots'),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'create_settings_snapshot'),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/snapshots/restore', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'restore_settings_snapshot'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/custom-preset', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'save_custom_preset'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));

        register_rest_route(self::REST_NAMESPACE, '/scan-preset', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'scan_preset'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));

        $diagnostic_routes = array(
            'jobs'     => 'diagnostic_jobs',
            'logs'     => 'diagnostic_logs',
            'requests' => 'diagnostic_requests',
            'browser-scan' => 'browser_scan_latest',
            'asset-snapshot' => 'asset_manager_snapshot',
        );
        foreach ($diagnostic_routes as $route => $method) {
            register_rest_route(self::REST_NAMESPACE, '/diagnostics/' . $route, array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, $method),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
                'args'                => array(
                    'per_page' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20),
                    'paged'    => array('type' => 'integer', 'minimum' => 1, 'default' => 1),
                ),
            ));
        }

        register_rest_route(self::REST_NAMESPACE, '/support-report', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'support_report'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));

        $actions = array(
            'purge-all'          => 'purge_all',
            'purge-page-cache'   => 'purge_page_cache',
            'purge-url'          => 'purge_url',
            'preload'            => 'run_preload',
            'critical-css'       => 'generate_critical_css',
            'used-css'           => 'generate_used_css',
            'clear-minified-css' => 'clear_minified_css',
            'clear-minified-js'  => 'clear_minified_js',
            'database-cleanup'   => 'database_cleanup',
            'health-check'       => 'run_health_check',
            'runtime-cache-test' => 'runtime_cache_test',
            'detect-conflicts'   => 'detect_conflicts',
            'enable-debug-mode'  => 'enable_debug_mode',
            'release-checklist'  => 'release_checklist',
            'repair-cache-files' => 'repair_cache_files',
            'retry-failed-jobs' => 'retry_failed_jobs',
            'run-due-jobs'      => 'run_due_jobs',
            'browser-scan'      => 'browser_scan_save',
        );
        foreach ($actions as $route => $method) {
            $args = array();
            if ('purge-url' === $route) {
                $args['url'] = array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'esc_url_raw',
                    'validate_callback' => array('UCP_Helpers', 'validate_local_url_arg'),
                );
            }
            register_rest_route(self::REST_NAMESPACE, '/actions/' . $route, array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, $method),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
                'args'                => $args,
            ));
        }
    }

    public static function get_optimization_lifecycle() {
        $lifecycle = class_exists('UCP_Optimization_Status') ? UCP_Optimization_Status::all() : array();
        return rest_ensure_response(array('success' => true, 'lifecycle' => $lifecycle, 'timestamp' => time()));
    }

    public static function permissions_check($request = null) {
        return UCP_Helpers::rest_admin_permission_check($request);
    }

}
