<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// Consolidated from includes/jobs/traits/ucp-jobs-payload-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin-owned queue table queries use controlled table constants and prepared/sanitized values.

trait UCP_Jobs_Payload_Trait {
    protected static function normalize_job_payload($payload) {
        if (!is_array($payload)) {
            return array();
        }
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::normalize_job_payload($value);
                continue;
            }
            if (class_exists('UCP_Helpers') && in_array((string) $key, array('url', 'href', 'endpoint'), true)) {
                $payload[$key] = esc_url_raw(UCP_Helpers::normalize_url_syntax($value));
            }
        }
        $is_numeric_array = function_exists('wp_is_numeric_array') ? wp_is_numeric_array($payload) : array_keys($payload) === range(0, count($payload) - 1);
        if ($is_numeric_array) {
            return array_values($payload);
        }
        ksort($payload);
        return $payload;
    }

    protected static function encode_job_payload($payload) {
        return wp_json_encode(self::normalize_job_payload(is_array($payload) ? $payload : array()));
    }

    public static function build_job_signature($type, $payload = array(), $queue = 'default') {
        return hash('sha256', sanitize_key($queue) . '|' . sanitize_key($type) . '|' . self::encode_job_payload($payload));
    }
}

// Consolidated from includes/jobs/traits/ucp-jobs-admin-actions-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
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

class UCP_Jobs {
    use UCP_Jobs_Schedule_Trait;
    use UCP_Jobs_Payload_Trait;
    use UCP_Jobs_Repository_Trait;
    use UCP_Jobs_Runner_Trait;
    use UCP_Jobs_Admin_Actions_Trait;

    const CRON_HOOK = 'ucp_jobs_event';

    /**
     * Whether the current run was manually forced by an admin action.
     * Used to bypass stale CSS artifact retry locks during explicit retries.
     *
     * @var bool
     */
    protected $force_current_run = false;

    /**
     * @param bool $skip_hook_registration Pass true to construct an instance without
     *                                     registering WordPress hooks. Used by short-lived
     *                                     callers (e.g. REST actions) that only need to
     *                                     invoke the runner without leaking duplicate hooks
     *                                     into the current request.
     */
    public function __construct($skip_hook_registration = false) {
        if ($skip_hook_registration) {
            return;
        }
        add_action(self::CRON_HOOK, array($this, 'run_queue'));
        add_action('admin_post_ucp_run_jobs', array($this, 'handle_manual_run'));
        add_action('admin_post_ucp_seed_jobs', array($this, 'handle_seed_jobs'));
        add_action('shutdown', array($this, 'maybe_run_queue_on_admin_shutdown'), 1000);
        self::sync_schedule();
    }
}
