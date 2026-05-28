<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Dashboard_Widget {
    public static function register() {
        if (!current_user_can('manage_options')) {
            return;
        }
        wp_add_dashboard_widget('ucp_dashboard_widget', __('UltraCache status', 'ultracache-pro'), array(__CLASS__, 'render'));
    }

    public static function render() {
        $settings = class_exists('UCP_Options') ? UCP_Options::get_all() : array();
        $preset = isset($settings['active_preset']) ? (string) $settings['active_preset'] : '';
        $presets = class_exists('UCP_Presets') ? UCP_Presets::all() : array();
        $preset_label = isset($presets[$preset]['label']) ? $presets[$preset]['label'] : __('Handmatig', 'ultracache-pro');
        $items = array(
            __('Page cache', 'ultracache-pro') => !empty($settings['enable_cache']),
            __('Preload', 'ultracache-pro') => !empty($settings['enable_preload']),
            __('CSS minify', 'ultracache-pro') => !empty($settings['enable_css_minify']),
            __('Lazyload media', 'ultracache-pro') => !empty($settings['enable_lazy_images']) || !empty($settings['enable_lazy_iframes']),
        );
        echo '<p><strong>' . esc_html__('Actieve preset:', 'ultracache-pro') . '</strong> ' . esc_html($preset_label) . '</p>';
        echo '<ul>';
        foreach ($items as $label => $active) {
            echo '<li>' . esc_html($active ? '✓' : '–') . ' ' . esc_html($label) . '</li>';
        }
        echo '</ul>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=ultracache-pro')) . '">' . esc_html__('UltraCache openen', 'ultracache-pro') . '</a></p>';
    }
}
