<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin-owned queue table queries use controlled table constants and prepared/sanitized values.

trait UCP_Jobs_Admin_Actions_Trait {
    public function maybe_run_queue_on_admin_shutdown() {
        if (!is_admin() && !(defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        // Running preload/CSS/cloud jobs during admin shutdown makes the settings screen feel slow.
        // Keep normal WP-Cron/manual runners, and allow the previous fallback only when explicitly enabled.
        $allow_admin_runner = (bool) apply_filters('ucp_allow_admin_shutdown_queue_runner', (bool) UCP_Options::get('enable_admin_queue_runner', 0));
        if (!$allow_admin_runner) {
            return;
        }

        if (!self::has_due_jobs(true)) {
            return;
        }
        $last = (int) get_option('ucp_jobs_admin_runner_last', 0);
        if ($last && (time() - $last) < 120) {
            return;
        }
        update_option('ucp_jobs_admin_runner_last', time(), false);
        $this->run_queue();
    }
    public function handle_manual_run() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_run_jobs');
        $this->run_queue_until_idle(true, 5);
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&jobs=1'));
        exit;
    }

    public function handle_seed_jobs() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_seed_jobs');
        self::enqueue_unique('generate_css', array('url' => home_url('/')), 5, 'css');
        self::enqueue_unique('cloud_sync', array(), 10, 'cloud');
        self::enqueue_unique('diagnostics_snapshot', array(), 20, 'health');
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&seeded=1'));
        exit;
    }
}
