<?php
if (!defined('ABSPATH')) { exit; }

class UCP_Admin_Tabs {
    public static function render_onboarding_banner($admin, $settings, $integrations) { UCP_Admin_Tab_Onboarding::render_onboarding_banner($admin, $settings, $integrations); }
    public static function render_overview($admin, $settings, $presets, $integrations, $health, $jobs_summary) { UCP_Admin_Tab_Overview::render_overview_tab($admin, $settings, $presets, $integrations, $health, $jobs_summary); }
    public static function render_optimization($admin, $settings) { UCP_Admin_Tab_Optimization::render_optimization_tab($admin, $settings); }
    public static function render_media($admin, $settings) { UCP_Admin_Tab_Media::render_media_tab($admin, $settings); }
    public static function render_preload($admin, $settings) {
        $safe_preload_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_safe_preload'), 'ucp_apply_safe_preload');
        $preload_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_and_preload'), 'ucp_purge_and_preload');
        $jobs_summary = class_exists('UCP_Jobs') ? UCP_Jobs::get_summary() : array('pending' => 0, 'running' => 0, 'retrying' => 0, 'failed' => 0);
        UCP_Admin_View::template('tabs/preload.php', get_defined_vars());
    }
    public static function render_advanced_rules($admin, $settings, $rules, $integrations) {
        $server_fix_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_fix_server_cache'), 'ucp_fix_server_cache');
        $check_dropin_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_check_dropin_owner'), 'ucp_check_dropin_owner');
        $object_cache_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_check_object_cache'), 'ucp_check_object_cache');
        UCP_Admin_View::template('tabs/advanced-rules.php', get_defined_vars());
    }
    public static function render_database($admin, $settings, $jobs_summary) { UCP_Admin_Tab_Database::render_database_tab($admin, $settings, $jobs_summary); }
    public static function render_cdn($admin, $settings) { UCP_Admin_Tab_Cdn::render($admin, $settings); }
    public static function render_heartbeat($admin, $settings) { UCP_Admin_Tab_Heartbeat::render($admin, $settings); }
    public static function render_tools($admin, $settings) { UCP_Admin_Tab_Tools::render($admin, $settings); }
}
