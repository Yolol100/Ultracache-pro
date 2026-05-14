<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_REST_Admin_Controller {
    use UCP_REST_Status_Trait;
    use UCP_REST_Settings_Trait;
    use UCP_REST_Diagnostics_Trait;
    use UCP_REST_Actions_Trait;

    const NAMESPACE = 'ultracache-pro/v1';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes() {
        register_rest_route(self::NAMESPACE, '/status', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_status'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::NAMESPACE, '/settings', array(
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
        register_rest_route(self::NAMESPACE, '/settings/bulk', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'update_settings'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::NAMESPACE, '/settings/export', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'export_settings'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::NAMESPACE, '/settings/import', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'import_settings'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::NAMESPACE, '/scan-preset', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'scan_preset'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));

        $diagnostic_routes = array(
            'jobs'     => 'diagnostic_jobs',
            'logs'     => 'diagnostic_logs',
            'requests' => 'diagnostic_requests',
            'browser-scan' => 'browser_scan_latest',
        );
        foreach ($diagnostic_routes as $route => $method) {
            register_rest_route(self::NAMESPACE, '/diagnostics/' . $route, array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, $method),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
                'args'                => array(
                    'per_page' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20),
                    'paged'    => array('type' => 'integer', 'minimum' => 1, 'default' => 1),
                ),
            ));
        }

        register_rest_route(self::NAMESPACE, '/support-report', array(
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
            register_rest_route(self::NAMESPACE, '/actions/' . $route, array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, $method),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ));
        }
    }

    public static function permissions_check($request = null) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('ucp_forbidden', __('Je hebt geen rechten om UltraCache Pro te beheren.', 'ultracache-pro'), array('status' => 403));
        }

        $method = ($request instanceof WP_REST_Request) ? strtoupper((string) $request->get_method()) : 'GET';
        $require_nonce = apply_filters('ucp_rest_require_nonce_for_mutations', true, $request);
        if ($require_nonce && !in_array($method, array('GET', 'HEAD', 'OPTIONS'), true)) {
            $nonce = ($request instanceof WP_REST_Request) ? (string) $request->get_header('x_wp_nonce') : '';
            if ('' === $nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('ucp_rest_nonce_missing', __('Ongeldige of ontbrekende REST-beveiligingstoken.', 'ultracache-pro'), array('status' => 403));
            }
        }

        return true;
    }

}
