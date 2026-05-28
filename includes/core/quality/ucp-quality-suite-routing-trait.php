<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Quality_Suite_Routing_Trait {
    public static function bootstrap() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_filter('site_status_tests', array(__CLASS__, 'register_site_health_tests'));
        add_filter('ucp_preload_urls', array(__CLASS__, 'filter_safe_preload_urls'), 20);
        add_action('init', array(__CLASS__, 'expire_debug_mode'), 20);
    }

    public static function register_routes() {
        $routes = array(
            'log-viewer'              => 'rest_log_viewer',
            'preset-woocommerce-safe' => 'rest_preset_woocommerce_safe',
            'preset-elementor-safe'   => 'rest_preset_elementor_safe',
            'preset-debug-test'       => 'rest_preset_debug_test',
            'preset-aggressive'       => 'rest_preset_aggressive',
        );
        foreach ($routes as $route => $method) {
            register_rest_route('ultracache-pro/v1', '/actions/' . $route, array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, $method),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ));
        }
    }

    public static function permissions_check($request = null) {
        return UCP_Helpers::rest_admin_permission_check($request);
    }

    protected static function action_success($message, $data = array()) {
        $status = class_exists('UCP_REST_Admin_Controller') ? UCP_REST_Admin_Controller::build_status() : array();
        return rest_ensure_response(array_merge(array('success' => true, 'message' => $message, 'status' => $status), $data));
    }
}
