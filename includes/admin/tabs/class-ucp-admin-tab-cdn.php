<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Cdn {
    public static function render($admin, $settings) {
        $advanced = $admin->is_advanced_mode($settings);
        $cdn_enabled = !empty($settings['enable_cdn']);
        UCP_Admin_View::template('tabs/cdn.php', get_defined_vars());
    }
}
