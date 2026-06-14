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
        self::register_status_routes();
        self::register_settings_routes();
        self::register_preset_routes();
        self::register_diagnostic_routes();
        self::register_action_routes();
    }

    /**
     * Register read-only status and lifecycle routes.
     *
     * @return void
     */
    private static function register_status_routes() {
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
    }

    /**
     * Register settings CRUD, import/export, snapshot and preset routes.
     *
     * @return void
     */
    private static function register_settings_routes() {
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
    }

    /**
     * Register preset-scanning routes.
     *
     * @return void
     */
    private static function register_preset_routes() {
        register_rest_route(self::REST_NAMESPACE, '/scan-preset', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'scan_preset'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
    }

    /**
     * Register diagnostics and support report routes.
     *
     * @return void
     */
    private static function register_diagnostic_routes() {
        $diagnostic_routes = array(
            'jobs'            => 'diagnostic_jobs',
            'logs'            => 'diagnostic_logs',
            'requests'        => 'diagnostic_requests',
            'browser-scan'    => 'browser_scan_latest',
            'asset-snapshot'  => 'asset_manager_snapshot',
            'quality-summary' => 'quality_summary',
        );

        foreach ($diagnostic_routes as $route => $method) {
            register_rest_route(self::REST_NAMESPACE, '/diagnostics/' . $route, array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, $method),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
                'args'                => self::pagination_args(),
            ));
        }

        register_rest_route(self::REST_NAMESPACE, '/support-report', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'support_report'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
    }

    /**
     * Register state-changing maintenance/action routes.
     *
     * @return void
     */
    private static function register_action_routes() {
        $actions = class_exists('UCP_REST_Action_Registry') ? UCP_REST_Action_Registry::route_handlers() : array();

        foreach ($actions as $route => $method) {
            register_rest_route(self::REST_NAMESPACE, '/actions/' . $route, array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => self::guarded_action_callback((string) $route, (string) $method),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
                'args'                => self::action_args($route),
            ));
        }
    }

    /**
     * Wrap an action handler so an unexpected fatal/exception never reaches the
     * browser as a bare 500. The handler keeps full control of its own success
     * and WP_Error responses; this only adds a last-resort safety net that
     * returns a calm, translatable message pointing the user back to Tools.
     *
     * @param string $route  Action route slug (for logging/diagnostics).
     * @param string $method Handler method name on this controller.
     * @return callable
     */
    private static function guarded_action_callback($route, $method) {
        return function ($request = null) use ($route, $method) {
            try {
                return call_user_func(array(__CLASS__, $method), $request);
            } catch (Throwable $e) {
                if (class_exists('UCP_Logger')) {
                    UCP_Logger::log('error', 'rest', 'action_exception', 'REST-actie liep op een onverwachte fout.', array(
                        'route'   => sanitize_key($route),
                        'message' => $e->getMessage(),
                    ));
                }
                return new WP_Error(
                    'ucp_action_failed',
                    __('De actie kon niet worden afgerond. Probeer het opnieuw of controleer Tools voor logs en details.', 'ultracache-pro'),
                    array('status' => 500, 'route' => sanitize_key($route))
                );
            }
        };
    }

    /**
     * Shared pagination arguments for diagnostics routes.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function pagination_args() {
        return array(
            'per_page' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20),
            'paged'    => array('type' => 'integer', 'minimum' => 1, 'default' => 1),
        );
    }

    /**
     * Arguments for action routes.
     *
     * @param string $route Action route slug.
     * @return array<string,array<string,mixed>>
     */
    private static function action_args($route) {
        return class_exists('UCP_REST_Action_Registry') ? UCP_REST_Action_Registry::args($route) : array();
    }

    public static function get_optimization_lifecycle() {
        $lifecycle = class_exists('UCP_Optimization_Status') ? UCP_Optimization_Status::all() : array();
        return rest_ensure_response(array('success' => true, 'lifecycle' => $lifecycle, 'timestamp' => time()));
    }

    public static function permissions_check($request = null) {
        return UCP_REST_Permissions::admin_permission_check($request);
    }

}
