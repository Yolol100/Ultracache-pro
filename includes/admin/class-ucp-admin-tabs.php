<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tabs {
    public static function render_onboarding_banner($admin, $settings, $integrations) {
        UCP_Admin_Tab_Onboarding::render_onboarding_banner($admin, $settings, $integrations);
    }

    public static function render_overview($admin, $settings, $presets, $integrations, $health, $jobs_summary) {
        UCP_Admin_Tab_Overview::render_overview_tab($admin, $settings, $presets, $integrations, $health, $jobs_summary);
    }

    public static function render_cache($admin, $settings) {
        UCP_Admin_Tab_Cache::render_cache_tab($admin, $settings);
    }

    public static function render_optimization($admin, $settings) {
        UCP_Admin_Tab_Optimization::render_optimization_tab($admin, $settings);
    }

    public static function render_media($admin, $settings) {
        UCP_Admin_Tab_Media::render_media_tab($admin, $settings);
    }

    public static function render_preload($admin, $settings) {
        UCP_Admin_Tab_Preload::render_preload_tab($admin, $settings);
    }

    public static function render_database($admin, $settings, $jobs_summary) {
        UCP_Admin_Tab_Database::render_database_tab($admin, $settings, $jobs_summary);
    }

    public static function render_heartbeat($admin, $settings) {
        UCP_Admin_Tab_Heartbeat::render($admin, $settings);
    }

    public static function render_addons($admin, $settings, $integrations) {
        UCP_Admin_Tab_Addons::render($admin, $settings, $integrations);
    }

    public static function render_tools($admin, $settings) {
        UCP_Admin_Tab_Tools::render($admin, $settings);
    }

    public static function render_expert($admin, $settings, $rules, $integrations) {
        UCP_Admin_Tab_Expert::render($admin, $settings, $rules, $integrations);
    }

    public static function render_assets($settings, $rules, $integrations) {
        UCP_Admin_Tab_Assets::render($settings, $rules, $integrations);
    }

    public static function render_advanced_rules($settings, $rules, $integrations) {
        UCP_Admin_Tab_Advanced_Rules::render($settings, $rules, $integrations);
    }
}
