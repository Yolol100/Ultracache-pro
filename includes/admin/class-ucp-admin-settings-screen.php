<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Settings_Screen {
    public static function tabs() {
        return array(
            'overview',
            'cache',
            'optimization',
            'preload',
            'cdn',
            'expert',
            'tools',
        );
    }

    public static function is_settings_tab($tab) {
        return in_array(UCP_Admin_Router::normalize_tab($tab), self::tabs(), true);
    }

    public static function render($admin, $tab, $settings, $context = array()) {
        $tab = UCP_Admin_Router::normalize_tab($tab);
        $context = wp_parse_args($context, array(
            'presets'      => array(),
            'integrations' => array(),
            'health'       => array(),
            'jobs_summary' => array(),
            'rules'        => array(),
        ));

        switch ($tab) {
            case 'cache':
                UCP_Admin_Tabs::render_cache($admin, $settings);
                return;
            case 'optimization':
                UCP_Admin_Tabs::render_optimization($admin, $settings);
                return;
            case 'preload':
                UCP_Admin_Tabs::render_preload($admin, $settings);
                return;
            case 'cdn':
                UCP_Admin_Tab_Experience::render_cdn($admin, $settings);
                return;
            case 'tools':
                UCP_Admin_Tabs::render_tools($admin, $settings);
                return;
            case 'expert':
                UCP_Admin_Tabs::render_expert($admin, $settings, $context['rules'], $context['integrations']);
                return;
            case 'overview':
            default:
                UCP_Admin_Tabs::render_overview($admin, $settings, $context['presets'], $context['integrations'], $context['health'], $context['jobs_summary']);
                return;
        }
    }
}
