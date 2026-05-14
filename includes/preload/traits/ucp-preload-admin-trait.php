<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Preload_Admin_Trait {
    public function handle_manual_preload() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_run_preload');

        if (UCP_Options::get('enable_preload_queue') && class_exists('UCP_Jobs')) {
            $queued = $this->seed_preload_queue();
            wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=cache&preload_queued=' . absint($queued)));
            exit;
        }

        $this->run_direct();
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=cache&preloaded=1'));
        exit;
    }
}
