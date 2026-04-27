<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Jobs {
    const CRON_HOOK = 'ucp_jobs_event';

    public function __construct() {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
        add_action(self::CRON_HOOK, array($this, 'run_queue'));
        add_action('admin_post_ucp_run_jobs', array($this, 'handle_manual_run'));
        add_action('admin_post_ucp_seed_jobs', array($this, 'handle_seed_jobs'));
        self::sync_schedule();
    }

    public static function sync_schedule($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $should_run = !empty($settings["enable_preload_queue"]) || !empty($settings["enable_css_queue"]) || !empty($settings["enable_health_checks"]);
        self::ensure_cron_schedule_registered();
        $event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event(self::CRON_HOOK) : false;

        if ($should_run) {
            if ($event && 'ucp_five_minutes' !== $event->schedule) {
                wp_unschedule_event($event->timestamp, self::CRON_HOOK, (array) $event->args);
                $event = false;
            }
            if (!$event && !wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + 180, 'ucp_five_minutes', self::CRON_HOOK);
            }
        }

        if (!$should_run) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    public static function register_schedule($schedules) {
        $schedules['ucp_five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Elke 5 minuten', 'ultracache-pro'),
        );
        return $schedules;
    }

    protected static function runner_lock_key() {
        return 'jobs-runner-lock';
    }


    protected static function runner_lock_option_name() {
        return 'ucp_jobs_runner_lock';
    }

    protected static function normalize_job_payload($payload) {
        if (!is_array($payload)) {
            return array();
        }
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::normalize_job_payload($value);
            }
        }
        if (wp_is_numeric_array($payload)) {
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

    public static function ensure_cron_schedule_registered() {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
        wp_get_schedules();
    }

    protected static function acquire_runner_lock($ttl) {
        global $wpdb;

        $ttl = max(60, absint($ttl));
        $token = wp_generate_password(24, false);
        $lock  = array(
            'token'      => $token,
            'expires_at' => time() + $ttl,
        );
        $option_name = self::runner_lock_option_name();

        if (add_option($option_name, $lock, '', false)) {
            return $token;
        }

        $current = get_option($option_name, array());
        $expires_at = isset($current['expires_at']) ? (int) $current['expires_at'] : 0;
        if ($expires_at >= time()) {
            return false;
        }

        $updated = $wpdb->update(
            $wpdb->options,
            array('option_value' => maybe_serialize($lock)),
            array(
                'option_name'  => $option_name,
                'option_value' => maybe_serialize($current),
            ),
            array('%s'),
            array('%s', '%s')
        );

        return $updated ? $token : false;
    }

    protected static function release_runner_lock($token = '') {
        global $wpdb;

        $option_name = self::runner_lock_option_name();
        $current = get_option($option_name, array());
        if (empty($current['token']) || !hash_equals((string) $current['token'], (string) $token)) {
            return;
        }

        $wpdb->delete(
            $wpdb->options,
            array(
                'option_name'  => $option_name,
                'option_value' => maybe_serialize($current),
            ),
            array('%s', '%s')
        );
        wp_cache_delete($option_name, 'options');
        wp_cache_delete('alloptions', 'options');
    }

    public static function enqueue_unique($type, $payload = array(), $priority = 10, $queue = 'default') {
        return self::enqueue($type, $payload, $priority, $queue, self::build_job_signature($type, $payload, $queue));
    }

    protected static function has_pending_job($type, $payload = array(), $queue = 'default') {
        global $wpdb;
        $payload_json = self::encode_job_payload($payload);
        $sql = $wpdb->prepare(
            "SELECT id FROM " . UCP_TABLE_JOBS . " WHERE type = %s AND queue = %s AND payload = %s AND status IN ('pending','running','retrying') LIMIT 1",
            sanitize_key($type),
            sanitize_key($queue),
            $payload_json
        );
        return (bool) $wpdb->get_var($sql);
    }

    public static function enqueue($type, $payload = array(), $priority = 10, $queue = 'default', $job_signature = null) {
        global $wpdb;
        $job_uuid = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $type = sanitize_key($type);
        $queue = sanitize_key($queue);
        $payload_json = self::encode_job_payload($payload);
        $job_signature = is_string($job_signature) && '' !== $job_signature ? $job_signature : null;
        $inserted = $wpdb->insert(
            UCP_TABLE_JOBS,
            array(
                'job_uuid'      => $job_uuid,
                'job_signature' => $job_signature,
                'type'          => $type,
                'queue'         => $queue,
                'status'        => 'pending',
                'priority'      => absint($priority),
                'attempts'      => 0,
                'max_attempts'  => absint(UCP_Options::get('job_max_attempts', 3)),
                'available_at'  => $now,
                'payload'       => $payload_json,
                'created_at'    => $now,
                'updated_at'    => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        if ($inserted) {
            self::sync_schedule();
            ucp_noop('info', 'jobs', 'job_queued', 'Taak toegevoegd.', array('type' => $type, 'job_uuid' => $job_uuid, 'queue' => $queue));
            return $job_uuid;
        }

        if ($job_signature && false !== strpos((string) $wpdb->last_error, 'job_signature')) {
            return false;
        }

        return false;
    }

    public static function count_by_status($status) {
        global $wpdb;
        $sql = $wpdb->prepare('SELECT COUNT(*) FROM ' . UCP_TABLE_JOBS . ' WHERE status = %s', sanitize_key($status));
        return (int) $wpdb->get_var($sql);
    }

    public static function get_summary() {
        return array(
            'pending'   => self::count_by_status('pending'),
            'running'   => self::count_by_status('running'),
            'retrying'  => self::count_by_status('retrying'),
            'failed'    => self::count_by_status('failed'),
            'success'   => self::count_by_status('success'),
        );
    }

    public static function recent($limit = 25) {
        $result = self::query(
            array(
                'per_page' => max(1, absint($limit)),
                'paged'    => 1,
            )
        );
        return $result['rows'];
    }

    public static function query($args = array()) {
        global $wpdb;
        $defaults = array(
            'status'   => '',
            'queue'    => '',
            'search'   => '',
            'paged'    => 1,
            'per_page' => 20,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $params = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = sanitize_key($args['status']);
        }
        if (!empty($args['queue'])) {
            $where[] = 'queue = %s';
            $params[] = sanitize_key($args['queue']);
        }
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like(wp_unslash($args['search'])) . '%';
            $where[] = '(type LIKE %s OR job_uuid LIKE %s OR last_error LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = 'SELECT COUNT(*) FROM ' . UCP_TABLE_JOBS . ' WHERE ' . $where_sql;
        $prepared_count = !empty($params) ? $wpdb->prepare($count_sql, $params) : $count_sql;
        $total = (int) $wpdb->get_var($prepared_count);

        $per_page = max(1, absint($args['per_page']));
        $paged = max(1, absint($args['paged']));
        $offset = ($paged - 1) * $per_page;

        $rows_sql = 'SELECT * FROM ' . UCP_TABLE_JOBS . ' WHERE ' . $where_sql . ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $rows_params = $params;
        $rows_params[] = $per_page;
        $rows_params[] = $offset;
        $prepared_rows = $wpdb->prepare($rows_sql, $rows_params);
        $rows = $wpdb->get_results($prepared_rows, ARRAY_A);

        return array(
            'rows'      => $rows,
            'total'     => $total,
            'per_page'  => $per_page,
            'paged'     => $paged,
            'max_pages' => max(1, (int) ceil($total / $per_page)),
        );
    }

    public function run_queue() {
        global $wpdb;
        $limit = max(1, absint(UCP_Options::get('job_batch_size', 5)));
        if (UCP_Options::get('enable_preload_queue')) {
            $limit = max($limit, absint(UCP_Options::get('preload_batch_size', 15)));
        }
        $token = wp_generate_password(16, false);
        $ttl = max(60, absint(UCP_Options::get('job_lock_ttl', 300)));
        $runner_token = self::acquire_runner_lock($ttl);
        if (!$runner_token) {
            return;
        }
        $lock_until = gmdate('Y-m-d H:i:s', time() + $ttl);

        $sql = $wpdb->prepare(
            'SELECT id FROM ' . UCP_TABLE_JOBS . ' WHERE status IN (%s,%s) AND available_at <= %s AND (locked_until IS NULL OR locked_until < %s) ORDER BY priority ASC, id ASC LIMIT %d',
            'pending',
            'retrying',
            current_time('mysql', true),
            current_time('mysql', true),
            $limit
        );
        $job_ids = $wpdb->get_col($sql);
        if (empty($job_ids)) {
            self::release_runner_lock($runner_token);
            return;
        }

        try {
            foreach ($job_ids as $job_id) {
                $wpdb->update(
                    UCP_TABLE_JOBS,
                    array(
                        'status' => 'running',
                        'claim_token' => $token,
                        'locked_until' => $lock_until,
                        'started_at' => current_time('mysql', true),
                        'updated_at' => current_time('mysql', true),
                    ),
                    array('id' => $job_id),
                    array('%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );

                $job = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . UCP_TABLE_JOBS . ' WHERE id = %d', $job_id), ARRAY_A);
                if (!$job) {
                    continue;
                }
                $this->process_job($job);
            }
        } finally {
            self::release_runner_lock($runner_token);
        }
    }

    protected function process_job($job) {
        global $wpdb;
        $payload = json_decode((string) $job['payload'], true);
        $payload = is_array($payload) ? $payload : array();
        $success = $this->run_job($job['type'], $payload);
        $attempts = (int) $job['attempts'] + 1;
        $max_attempts = max(1, (int) $job['max_attempts']);

        if ($success) {
            $wpdb->update(
                UCP_TABLE_JOBS,
                array(
                    'status' => 'success',
                    'attempts' => $attempts,
                    'finished_at' => current_time('mysql', true),
                    'updated_at' => current_time('mysql', true),
                    'result' => wp_json_encode(array('ok' => true)),
                    'locked_until' => null,
                    'job_signature' => null,
                ),
                array('id' => $job['id']),
                array('%s', '%d', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            ucp_noop('info', 'jobs', 'job_success', 'Taak klaar.', array('type' => $job['type'], 'job_id' => $job['id']));
            return;
        }

        $next_status = $attempts >= $max_attempts ? 'failed' : 'retrying';
        $delay = min(3600, max(60, pow(2, $attempts) * 60));
        $update_data = array(
            'status' => $next_status,
            'attempts' => $attempts,
            'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
            'updated_at' => current_time('mysql', true),
            'last_error' => 'Taak gaf geen goed resultaat terug.',
            'locked_until' => null,
        );
        $update_format = array('%s', '%d', '%s', '%s', '%s', '%s');
        if ('failed' === $next_status) {
            $update_data['job_signature'] = null;
            $update_format[] = '%s';
        }
        $wpdb->update(
            UCP_TABLE_JOBS,
            $update_data,
            array('id' => $job['id']),
            $update_format,
            array('%d')
        );
        ucp_noop('warning', 'jobs', 'job_retry_scheduled', 'Job rescheduled.', array('type' => $job['type'], 'job_id' => $job['id'], 'status' => $next_status));
    }

    protected function run_job($type, $payload) {
        switch ($type) {
            case 'generate_css':
                return UCP_CSS::generate_for_url(UCP_Helpers::enforce_local_url(isset($payload['url']) ? $payload['url'] : home_url('/')), !empty($payload['force']));
            case 'remote_css':
            case 'cloud_sync':
            case 'cloudflare_purge_url':
            case 'cloudflare_purge_all':
            case 'cloudflare_purge_urls':
                return false;
            case 'preload_url':
                $url = UCP_Helpers::enforce_local_url(isset($payload['url']) ? $payload['url'] : home_url('/'));
                if (!$url || !wp_http_validate_url($url)) {
                    return false;
                }
                $response = wp_remote_get($url, array(
                    'timeout' => 20,
                    'redirection' => 3,
                    'reject_unsafe_urls' => true,
                    'user-agent' => 'UltraCache Preload Queue/' . UCP_VERSION,
                    'sslverify' => apply_filters('https_local_ssl_verify', true),
                ));
                return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
            case 'diagnostics_snapshot':
                UCP_Health::run_checks();
                return true;
            default:
                return false;
        }
    }

    public function handle_manual_run() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_run_jobs');
        $this->run_queue();
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&jobs=1'));
        exit;
    }

    public function handle_seed_jobs() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_seed_jobs');
        self::enqueue_unique('generate_css', array('url' => home_url('/')), 5, 'css');
        self::enqueue_unique('cloud_sync', array(), 10, 'cloud');
        self::enqueue_unique('diagnostics_snapshot', array(), 20, 'health');
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&seeded=1'));
        exit;
    }
}
