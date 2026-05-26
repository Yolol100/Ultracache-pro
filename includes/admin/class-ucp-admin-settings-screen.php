<?php
if (!defined('ABSPATH')) { exit; }

class UCP_Admin_Settings_Screen {
    public static function tabs() { return UCP_Admin_Router::allowed_tabs(); }
    public static function is_settings_tab($tab) { return UCP_Admin_Router::is_settings_tab($tab); }
    public static function render($admin, $tab, $settings, $context = array()) {
        switch ($tab) {
            case 'optimization': UCP_Admin_Tabs::render_optimization($admin, $settings); return;
            case 'media': UCP_Admin_Tabs::render_media($admin, $settings); return;
            case 'preload': UCP_Admin_Tabs::render_preload($admin, $settings); return;
            case 'advanced_rules': UCP_Admin_Tabs::render_advanced_rules($admin, $settings, $context['rules'], $context['integrations']); return;
            case 'database': UCP_Admin_Tabs::render_database($admin, $settings, $context['jobs_summary']); return;
            case 'cdn': UCP_Admin_Tabs::render_cdn($admin, $settings); return;
            case 'heartbeat': UCP_Admin_Tabs::render_heartbeat($admin, $settings); return;
            case 'developer': UCP_Admin_Tabs::render_developer($admin, $settings); return;
            case 'tools': UCP_Admin_Tabs::render_tools($admin, $settings); return;
            case 'overview': default: UCP_Admin_Tabs::render_overview($admin, $settings, $context['presets'], $context['integrations'], $context['health'], $context['jobs_summary']); return;
        }
    }
}
