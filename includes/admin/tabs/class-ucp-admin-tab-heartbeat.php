<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Heartbeat {
    public static function render($admin, $settings) {
        $safe_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_safe_heartbeat'), 'ucp_apply_safe_heartbeat');
        $advanced = $admin->is_advanced_mode($settings);
        $hide_heartbeat_control = !$advanced && !empty($settings['enable_heartbeat_control']);
        UCP_Admin_View::template('tabs/heartbeat.php', get_defined_vars());
    }
}
