<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Media {
    public static function render_media_tab($admin, $settings) {
        $integrations = class_exists('UCP_Integrations') ? UCP_Integrations::detected() : array();
        $has_sensitive_stack = !empty($integrations['commerce']) || !empty($integrations['forms']) || !empty($integrations['consent']);
        $speculative_enabled = !empty($settings['enable_speculative_loading']);
        UCP_Admin_View::template('tabs/media.php', get_defined_vars());
    }
}
