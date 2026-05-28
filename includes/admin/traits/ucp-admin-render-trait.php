<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Render_Trait {
    public function tab_meta($tab) {
        return UCP_Admin_Config::tab_meta($tab);
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }

        if (!class_exists('UCP_Admin_React_App') || !UCP_Admin_React_App::should_render()) {
            wp_die(
                esc_html__('UltraCache Pro admin kan niet laden omdat de React admin assets ontbreken.', 'ultracache-pro'),
                esc_html__('UltraCache Pro', 'ultracache-pro'),
                array('response' => 500)
            );
        }

        UCP_Admin_React_App::render_root();
    }

    protected function tabs() {
        return UCP_Admin_Config::tabs();
    }
}
