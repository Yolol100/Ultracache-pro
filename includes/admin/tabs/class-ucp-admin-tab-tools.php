<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Tools {
    public static function job_type_label($type) {
        $labels = array(
            'preload_url' => __('URL opwarmen', 'ultracache-pro'),
            'critical_css' => __('CSS genereren', 'ultracache-pro'),
            'used_css' => __('CSS opschonen', 'ultracache-pro'),
            'maintenance' => __('Onderhoud', 'ultracache-pro'),
            'cache_purge' => __('Cache legen', 'ultracache-pro'),
        );
        if (isset($labels[$type])) {
            return $labels[$type];
        }
        return ucwords(str_replace(array('_', '-'), ' ', (string) $type));
    }

    public static function job_status_label($status) {
        $labels = array(
            'pending' => __('In wachtrij', 'ultracache-pro'),
            'running' => __('Bezig', 'ultracache-pro'),
            'retrying' => __('Opnieuw proberen', 'ultracache-pro'),
            'failed' => __('Mislukt', 'ultracache-pro'),
            'success' => __('Klaar', 'ultracache-pro'),
        );
        return isset($labels[$status]) ? $labels[$status] : ucwords(str_replace(array('_', '-'), ' ', (string) $status));
    }

    public static function render($admin, $settings) {
        $auto_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_auto_compat'), 'ucp_apply_auto_compat');
        $server_fix_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_fix_server_cache'), 'ucp_fix_server_cache');
        $check_dropin_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_check_dropin_owner'), 'ucp_check_dropin_owner');
        $jobs_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_run_jobs'), 'ucp_run_jobs');
        $maintenance_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_run_maintenance'), 'ucp_run_maintenance');
        $compat_lists_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_check_compat_lists'), 'ucp_check_compat_lists');
        $compat_files = glob(trailingslashit(UCP_PATH) . 'compat/*.json');
        $compat_list_count = is_array($compat_files) ? count($compat_files) : 0;
        $jobs_summary = class_exists('UCP_Jobs') ? UCP_Jobs::get_summary() : array('pending' => 0, 'running' => 0, 'retrying' => 0, 'failed' => 0, 'success' => 0);
        $recent_jobs = class_exists('UCP_Jobs') ? array_slice((array) UCP_Jobs::recent(5), 0, 5) : array();
        $runtime = class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array();
        $cwv_summary = class_exists('UCP_CWV') ? UCP_CWV::summary() : array();
        UCP_Admin_View::template('tabs/tools.php', get_defined_vars());
    }
}
