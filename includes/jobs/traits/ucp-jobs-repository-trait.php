<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin-owned queue table queries use controlled table constants and prepared/sanitized values.

trait UCP_Jobs_Repository_Trait {
    public static function enqueue_unique($type, $payload = array(), $priority = 10, $queue = 'default') {
        return self::enqueue($type, $payload, $priority, $queue, self::build_job_signature($type, $payload, $queue));
    }

    protected static function has_pending_job($type, $payload = array(), $queue = 'default') {
        global $wpdb;
        $payload_json = self::encode_job_payload($payload);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
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

        $job_uuid      = wp_generate_uuid4();
        $now           = current_time('mysql', true);
        $type          = sanitize_key($type);
        $queue         = sanitize_key($queue);
        $payload       = self::prepare_payload_for_type($type, is_array($payload) ? $payload : array());

        if (false === $payload) {
            return false;
        }

        $payload_json  = self::encode_job_payload($payload);
        $job_signature = is_string($job_signature) && '' !== $job_signature ? self::build_job_signature($type, $payload, $queue) : null;

        if ($job_signature) {
            // Avoid noisy duplicate-key database errors during activation/preload when the same job is queued twice.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table name is controlled by a constant and values are prepared.
            $existing_job_id = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT id FROM ' . UCP_TABLE_JOBS . ' WHERE job_signature = %s LIMIT 1',
                    $job_signature
                )
            );

            if ($existing_job_id) {
                return false;
            }
        }

        $suppress_errors = $wpdb->suppress_errors(true);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table insert with sanitized values.
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

        $last_error = (string) $wpdb->last_error;
        $wpdb->suppress_errors($suppress_errors);

        if ($inserted) {
            self::sync_schedule();
            UCP_Logger::log('info', 'jobs', 'job_queued', 'Taak toegevoegd.', array('type' => $type, 'job_uuid' => $job_uuid, 'queue' => $queue));
            return $job_uuid;
        }

        if ($job_signature && false !== strpos($last_error, 'job_signature')) {
            return false;
        }

        return false;
    }
    protected static function prepare_payload_for_type($type, $payload) {
        if (!is_array($payload)) {
            $payload = array();
        }

        if (in_array($type, array('preload_url', 'generate_css', 'remote_css'), true)) {
            $url = isset($payload['url']) ? $payload['url'] : home_url('/');
            $url = class_exists('UCP_Helpers') ? UCP_Helpers::enforce_local_url($url) : esc_url_raw($url);
            if (!$url || !wp_http_validate_url($url)) {
                return false;
            }
            if ('preload_url' === $type && class_exists('UCP_Preload') && UCP_Preload::is_safety_excluded_url($url)) {
                if (class_exists('UCP_Logger')) {
                    UCP_Logger::log('info', 'jobs', 'preload_job_not_queued_safety', 'Preload job niet toegevoegd door safety filter.', array('url' => $url));
                }
                return false;
            }
            $payload['url'] = $url;
        }

        if ('cloudflare_purge_url' === $type && isset($payload['url']) && class_exists('UCP_Helpers')) {
            $payload['url'] = UCP_Helpers::enforce_local_url($payload['url']);
        }

        if ('cloudflare_purge_urls' === $type && isset($payload['urls']) && is_array($payload['urls']) && class_exists('UCP_Helpers')) {
            $payload['urls'] = array_values(array_filter(array_map(array('UCP_Helpers', 'enforce_local_url'), $payload['urls'])));
        }

        return $payload;
    }

    public static function cleanup_unsafe_preload_jobs($limit = 500) {
        global $wpdb;

        if (!defined('UCP_TABLE_JOBS')) {
            return array('skipped' => 0, 'repaired' => 0);
        }

        $limit = max(1, min(1000, absint($limit)));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled constants and prepared values.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, payload, status, queue, type FROM " . UCP_TABLE_JOBS . " WHERE type = %s AND status IN ('pending','retrying','failed') ORDER BY id DESC LIMIT %d",
                'preload_url',
                $limit
            ),
            ARRAY_A
        );

        $skipped = 0;
        $repaired = 0;
        foreach ((array) $rows as $row) {
            $payload = json_decode((string) $row['payload'], true);
            $payload = is_array($payload) ? $payload : array();
            $raw_url = isset($payload['url']) ? $payload['url'] : '';
            $url = class_exists('UCP_Helpers') ? UCP_Helpers::enforce_local_url($raw_url) : esc_url_raw($raw_url);

            if (!$url || !wp_http_validate_url($url) || (class_exists('UCP_Preload') && UCP_Preload::is_safety_excluded_url($url))) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned maintenance update for skipped preload jobs.
                $wpdb->update(
                    UCP_TABLE_JOBS,
                    array(
                        'status'        => 'success',
                        'result'        => wp_json_encode(array('ok' => true, 'skipped' => true, 'reason' => 'preload_safety_cleanup')),
                        'last_error'    => null,
                        'locked_until'  => null,
                        'claim_token'   => null,
                        'job_signature' => null,
                        'updated_at'    => current_time('mysql', true),
                    ),
                    array('id' => absint($row['id'])),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
                $skipped++;
                continue;
            }

            if ($url !== $raw_url) {
                $payload['url'] = $url;
                $payload_json = self::encode_job_payload($payload);
                $signature = self::build_job_signature('preload_url', $payload, isset($row['queue']) ? $row['queue'] : 'default');
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned maintenance update for repaired preload jobs.
                $wpdb->update(
                    UCP_TABLE_JOBS,
                    array(
                        'payload'       => $payload_json,
                        'job_signature' => $signature,
                        'status'        => 'pending',
                        'attempts'      => 0,
                        'available_at'  => current_time('mysql', true),
                        'last_error'    => null,
                        'locked_until'  => null,
                        'claim_token'   => null,
                        'updated_at'    => current_time('mysql', true),
                    ),
                    array('id' => absint($row['id'])),
                    array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
                $repaired++;
            }
        }

        if (($skipped || $repaired) && class_exists('UCP_Logger')) {
            UCP_Logger::log('info', 'jobs', 'preload_jobs_cleaned', 'Onveilige of malforme preloadtaken opgeschoond.', array('skipped' => $skipped, 'repaired' => $repaired));
        }

        return array('skipped' => $skipped, 'repaired' => $repaired);
    }

    public static function count_by_status($status) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table names/placeholders are controlled by constants and prepared values.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $sql = $wpdb->prepare('SELECT COUNT(*) FROM ' . UCP_TABLE_JOBS . ' WHERE status = %s', sanitize_key($status));
        return (int) $wpdb->get_var($sql);
    }

    public static function count_due_jobs() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM " . UCP_TABLE_JOBS . " WHERE status IN ('pending','retrying') AND available_at <= %s AND (locked_until IS NULL OR locked_until < %s)",
            current_time('mysql', true),
            current_time('mysql', true)
        );
        return (int) $wpdb->get_var($sql);
    }

    public static function count_due_jobs_by_type($type) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM " . UCP_TABLE_JOBS . " WHERE type = %s AND status IN ('pending','retrying') AND available_at <= %s AND (locked_until IS NULL OR locked_until < %s)",
            sanitize_key($type),
            current_time('mysql', true),
            current_time('mysql', true)
        );
        return (int) $wpdb->get_var($sql);
    }

    public static function count_retrying_with_zero_attempts() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $sql = "SELECT COUNT(*) FROM " . UCP_TABLE_JOBS . " WHERE status = 'retrying' AND attempts = 0";
        return (int) $wpdb->get_var($sql);
    }

    public function run_queue_until_idle($force = false, $max_batches = 5) {
        $max_batches = max(1, min(10, absint($max_batches)));
        $processed = 0;
        for ($i = 0; $i < $max_batches; $i++) {
            $batch_processed = (int) $this->run_queue($force);
            $processed += $batch_processed;
            if ($batch_processed <= 0) {
                break;
            }
            if ($force) {
                if (!self::has_due_jobs(false)) {
                    break;
                }
            } elseif (!self::has_due_jobs(true)) {
                break;
            }
        }
        return $processed;
    }

    public static function has_due_jobs($due_only = true) {
        global $wpdb;
        if ($due_only) {
            return self::count_due_jobs() > 0;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $sql = "SELECT COUNT(*) FROM " . UCP_TABLE_JOBS . " WHERE status IN ('pending','retrying')";
        return (int) $wpdb->get_var($sql) > 0;
    }

    public static function get_runner_status() {
        $next = wp_next_scheduled(self::CRON_HOOK);
        return array(
            'due'          => self::count_due_jobs(),
            'nextCron'     => $next ? gmdate('Y-m-d H:i:s', (int) $next) : '',
            'cronDisabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'hook'         => self::CRON_HOOK,
            'schedule'     => $next ? 'ucp_one_minute' : '',
        );
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
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $prepared_count = !empty($params) ? $wpdb->prepare($count_sql, $params) : $count_sql;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is assembled from fixed fragments and prepared values above.
        $total = (int) $wpdb->get_var($prepared_count);

        $per_page = max(1, absint($args['per_page']));
        $paged = max(1, absint($args['paged']));
        $offset = ($paged - 1) * $per_page;

        $rows_sql = 'SELECT * FROM ' . UCP_TABLE_JOBS . ' WHERE ' . $where_sql . ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $rows_params = $params;
        $rows_params[] = $per_page;
        $rows_params[] = $offset;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $prepared_rows = $wpdb->prepare($rows_sql, $rows_params);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is assembled from fixed fragments and prepared values above.
        $rows = $wpdb->get_results($prepared_rows, ARRAY_A);

        return array(
            'rows'      => $rows,
            'total'     => $total,
            'per_page'  => $per_page,
            'paged'     => $paged,
            'max_pages' => max(1, (int) ceil($total / $per_page)),
        );
    }
}
