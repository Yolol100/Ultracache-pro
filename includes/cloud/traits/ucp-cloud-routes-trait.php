<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cloud_Routes_Trait {
    public function register_routes() {
        register_rest_route('ultracache-pro/v1', '/cloud/status', array(
            'methods'             => 'GET',
            'permission_callback' => array('UCP_Helpers', 'rest_admin_permission_check'),
            'callback'            => array($this, 'status'),
        ));
    }

    public function status() {
        return rest_ensure_response(array(
            'enabled'   => (bool) UCP_Options::get('enable_cloud'),
            'connected' => self::has_valid_endpoint() && !empty(UCP_Options::get('cloud_api_key')),
            'queue'     => UCP_Jobs::get_summary(),
        ));
    }

    public function handle_manual_sync() {
        UCP_Helpers::require_post_admin_action('ucp_cloud_sync');
        $result = self::push_site_payload();
        wp_safe_redirect(UCP_Admin_Router::url('expert', array('cloud_sync' => ($result ? '1' : '0'))));
        exit;
    }
}
