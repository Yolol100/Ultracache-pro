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
            array('label' => __('Cache', 'ultracache-pro'), 'active' => !empty($settings['enable_cache']), 'type' => __('Render-veilig', 'ultracache-pro'), 'why' => __('Maakt pagina’s sneller.', 'ultracache-pro')),
            array('label' => __('Lazy load', 'ultracache-pro'), 'active' => !empty($settings['enable_lazy_images']) || !empty($settings['enable_lazy_iframes']), 'type' => __('Render-veilig', 'ultracache-pro'), 'why' => __('Snellere eerste laadtijd.', 'ultracache-pro')),
            array('label' => __('Used CSS', 'ultracache-pro'), 'active' => !empty($settings['enable_used_css']) || !empty($settings['enable_used_css_delivery']), 'type' => __('Geavanceerd', 'ultracache-pro'), 'why' => __('Minder overbodige CSS.', 'ultracache-pro')),
            array('label' => __('Object cache', 'ultracache-pro'), 'active' => !empty($settings['enable_redis_object_cache']) || !empty($settings['enable_apcu_object_cache']), 'type' => __('Geavanceerd', 'ultracache-pro'), 'why' => __('Vooral nuttig voor grotere sites.', 'ultracache-pro')),
        );
        echo '<p><strong>' . esc_html__('Actieve preset:', 'ultracache-pro') . '</strong> ' . esc_html($preset_label) . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Onderdeel', 'ultracache-pro') . '</th><th>' . esc_html__('Status', 'ultracache-pro') . '</th><th>' . esc_html__('Type', 'ultracache-pro') . '</th><th>' . esc_html__('Waarom', 'ultracache-pro') . '</th></tr></thead><tbody>';
        foreach ($items as $item) {
            $status = $item['active'] ? __('Aan', 'ultracache-pro') : (__('Object cache', 'ultracache-pro') === $item['label'] ? __('Optioneel', 'ultracache-pro') : __('Uit', 'ultracache-pro'));
            echo '<tr><td>' . esc_html($item['label']) . '</td><td>' . esc_html($status) . '</td><td>' . esc_html($item['type']) . '</td><td>' . esc_html($item['why']) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=ultracache-pro')) . '">' . esc_html__('UltraCache openen', 'ultracache-pro') . '</a></p>';
    }
}
