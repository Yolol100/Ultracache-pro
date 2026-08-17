<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// Consolidated from includes/jobs/traits/ucp-jobs-payload-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin-owned queue table queries use controlled table constants and prepared/sanitized values.

trait UCP_Jobs_Payload_Trait {
    protected static function normalize_job_payload($payload, $depth = 0, &$remaining = null) {
        if (null === $remaining) {
            $remaining = 100;
        }
        if (!is_array($payload) || $depth > 4 || $remaining < 0) {
            return array();
        }
        $normalized = array();
        foreach ($payload as $key => $value) {
            --$remaining;
            if ($remaining < 0) {
                break;
            }
            $clean_key = is_int($key) ? $key : sanitize_key((string) $key);
            if (!is_int($key) && ('' === $clean_key || strlen($clean_key) > 64)) {
                continue;
            }
            if (is_array($value)) {
                $normalized[$clean_key] = self::normalize_job_payload($value, $depth + 1, $remaining);
                continue;
            }
            if (!is_scalar($value) && null !== $value) {
                continue;
            }
            if (is_string($value)) {
                if (strlen($value) > 8192 || false !== strpos($value, "\0")) {
                    continue;
                }
                if (class_exists('UCP_Helpers') && in_array((string) $clean_key, array('url', 'href', 'endpoint'), true)) {
                    $value = esc_url_raw(UCP_Helpers::normalize_url_syntax($value));
                }
            }
            $normalized[$clean_key] = $value;
        }
        $is_numeric_array = function_exists('wp_is_numeric_array') ? wp_is_numeric_array($normalized) : array_keys($normalized) === range(0, count($normalized) - 1);
        if ($is_numeric_array) {
            return array_values($normalized);
        }
        ksort($normalized);
        return $normalized;
    }

    protected static function encode_job_payload($payload) {
        return UCP_Helpers::safe_json_encode_or(self::normalize_job_payload(is_array($payload) ? $payload : array()), '{}');
    }

    public static function build_job_signature($type, $payload = array(), $queue = 'default') {
        if (!is_scalar($type) && null !== $type) {
            $type = '';
        }
        if (!is_scalar($queue) && null !== $queue) {
            $queue = 'default';
        }
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
        UCP_Helpers::require_post_admin_action('ucp_run_jobs');
        $this->run_queue_until_idle(false, 5);
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&jobs=1'));
        exit;
    }

    public function handle_seed_jobs() {
        UCP_Helpers::require_post_admin_action('ucp_seed_jobs');
        $seeded = 0;
        $jobs = array(
            array('generate_css', array('url' => home_url('/')), 5, 'css'),
            array('cloud_sync', array(), 10, 'cloud'),
            array('diagnostics_snapshot', array(), 20, 'health'),
        );
        foreach ($jobs as $job) {
            $queued = self::enqueue_unique($job[0], $job[1], $job[2], $job[3]);
            if ($queued || self::unique_job_exists($job[0], $job[1], $job[3])) {
                $seeded++;
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&seeded=' . $seeded));
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
     * Optional job type filter for an explicit short-lived runner call.
     *
     * @var string
     */
    protected $job_type_current_run = '';

    /**
     * Whether an explicit runner call may bypass the automatic load pause.
     *
     * @var bool
     */
    protected $bypass_load_guard_current_run = false;

    /**
     * Active global runner lease for the current queue pass.
     *
     * @var array<string,mixed>
     */
    protected $active_runner_lease = array();

    /**
     * Active claim lease for the job currently being processed.
     *
     * @var array<string,mixed>
     */
    protected $active_job_lease = array();

    /**
     * Whether the global runner lease was replaced while a claimed job was active.
     *
     * @var bool
     */
    protected $runner_lease_lost = false;

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
