<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin-owned queue table queries use controlled table constants and prepared/sanitized values.

trait UCP_Jobs_Repository_Trait {
    protected static function jobs_table_name() {
        return class_exists('UCP_Jobs_Repository') ? UCP_Jobs_Repository::table_name() : '';
    }

    protected static function jobs_table_sql() {
        return class_exists('UCP_Jobs_Repository') ? UCP_Jobs_Repository::table_sql() : '';
    }

    public static function enqueue_unique($type, $payload = array(), $priority = 10, $queue = 'default') {
        return self::enqueue($type, $payload, $priority, $queue, self::build_job_signature($type, $payload, $queue));
    }

    /**
     * Check whether an equivalent active unique job already exists.
     *
     * This is intentionally separate from enqueue_unique(), whose historical false
     * return value can mean either an existing job or an insertion failure.
     *
     * @param string $type    Job type.
     * @param array  $payload Job payload.
     * @param string $queue   Queue name.
     * @return bool
     */
    public static function unique_job_exists($type, $payload = array(), $queue = 'default') {
        if (!is_scalar($type) && null !== $type) {
            $type = '';
        }
        if (!is_scalar($queue) && null !== $queue) {
            $queue = 'default';
        }
        global $wpdb;

        $table = self::jobs_table_name();
        $type = substr(sanitize_key($type), 0, 64);
        $queue = substr(sanitize_key($queue), 0, 64);
        if ('' === $table || '' === $type || '' === $queue) {
            return false;
        }

        $payload = self::prepare_payload_for_type($type, is_array($payload) ? $payload : array());
        if (false === $payload) {
            return false;
        }
        $signature = self::build_job_signature($type, $payload, $queue);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned queue table and prepared signature lookup.
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM ' . self::jobs_table_sql() . ' WHERE job_signature = %s LIMIT 1',
                $signature
            )
        );
    }

    public static function active_job_type_exists($type, $queue = 'default') {
        if (!is_scalar($type) && null !== $type) {
            $type = '';
        }
        if (!is_scalar($queue) && null !== $queue) {
            $queue = 'default';
        }
        global $wpdb;

        $type = substr(sanitize_key($type), 0, 64);
        $queue = substr(sanitize_key($queue), 0, 64);
        $table_sql = self::jobs_table_sql();
        if ('' === $type || '' === $queue || '' === $table_sql) {
            return false;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded existence query on the plugin-owned jobs table.
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM " . $table_sql . " WHERE type = %s AND queue = %s AND status IN ('pending','retrying','running') LIMIT 1",
                $type,
                $queue
            )
        );
    }

    public static function enqueue($type, $payload = array(), $priority = 10, $queue = 'default', $job_signature = null) {
        global $wpdb;

        $table = self::jobs_table_name();
        if ('' === $table) {
            return false;
        }

        $job_uuid      = wp_generate_uuid4();
        $now           = current_time('mysql', true);
        $type          = substr(sanitize_key($type), 0, 64);
        $queue         = substr(sanitize_key($queue), 0, 64);
        if ('' === $type || '' === $queue) {
            return false;
        }
        $payload       = self::prepare_payload_for_type($type, is_array($payload) ? $payload : array());

        if (false === $payload) {
            return false;
        }

        $payload_json  = self::encode_job_payload($payload);
        if (!is_string($payload_json) || '' === $payload_json) {
            return false;
        }
        $job_signature = is_string($job_signature) && '' !== $job_signature ? self::build_job_signature($type, $payload, $queue) : null;

        if ($job_signature) {
            // Avoid noisy duplicate-key database errors during activation/preload when the same job is queued twice.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table name is controlled by a constant and values are prepared.
            $existing_job_id = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT id FROM ' . self::jobs_table_sql() . ' WHERE job_signature = %s LIMIT 1',
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
            $table,
            array(
                'job_uuid'      => $job_uuid,
                'job_signature' => $job_signature,
                'type'          => $type,
                'queue'         => $queue,
                'status'        => 'pending',
                'priority'      => absint($priority),
                'attempts'      => 0,
                'max_attempts'  => min(20, max(1, absint(UCP_Options::get('job_max_attempts', 3)))),
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
            UCP_Logger::log('info', 'jobs', 'job_queued', __('Taak toegevoegd.', 'ultracache-pro'), array('type' => $type, 'job_uuid' => $job_uuid, 'queue' => $queue));
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
            $url = UCP_Helpers::strict_local_url($url, home_url('/'));
            if (!$url || !wp_http_validate_url($url)) {
                return false;
            }
            if ('preload_url' === $type && class_exists('UCP_Preload') && UCP_Preload::is_safety_excluded_url($url)) {
                if (class_exists('UCP_Logger')) {
                    UCP_Logger::log('info', 'jobs', 'preload_job_not_queued_safety', __('Preloadtaak is niet toegevoegd door het veiligheidsfilter.', 'ultracache-pro'), array('url' => $url));
                }
                return false;
            }
            $payload['url'] = $url;
        }

        if ('image_backfill_seed' === $type) {
            $payload = array(
                'cursor' => isset($payload['cursor']) && is_scalar($payload['cursor']) ? absint($payload['cursor']) : 0,
            );
        }

        if ('precompress_cache' === $type) {
            $relative_path = isset($payload['relative_path']) && is_scalar($payload['relative_path'])
                ? ltrim(wp_normalize_path((string) $payload['relative_path']), '/')
                : '';
            $content_sha256 = isset($payload['content_sha256']) && is_scalar($payload['content_sha256'])
                ? strtolower(trim((string) $payload['content_sha256']))
                : '';
            if ('' === $relative_path
                || strlen($relative_path) > 500
                || false !== strpos($relative_path, "\0")
                || in_array('..', explode('/', $relative_path), true)
                || 1 !== preg_match('/\.(?:html?|xhtml)$/i', $relative_path)
                || 1 !== preg_match('/^[a-f0-9]{64}$/D', $content_sha256)) {
                return false;
            }
            $payload = array(
                'relative_path'  => $relative_path,
                'content_sha256' => $content_sha256,
            );
        }

        if ('cloudflare_purge_url' === $type && isset($payload['url']) && class_exists('UCP_Helpers')) {
            $url = UCP_Helpers::strict_local_url($payload['url']);
            if (!$url || !wp_http_validate_url($url)) {
                return false;
            }
            $payload['url'] = $url;
        }

        if ('cloudflare_purge_urls' === $type && isset($payload['urls']) && is_array($payload['urls']) && class_exists('UCP_Helpers')) {
            $urls = array();
            foreach (array_slice($payload['urls'], 0, 100) as $raw_url) {
                $url = UCP_Helpers::strict_local_url($raw_url);
                if ($url && wp_http_validate_url($url)) {
                    $urls[] = $url;
                }
            }
            $payload['urls'] = array_values(array_unique($urls));
            if (empty($payload['urls'])) {
                return false;
            }
        }

        return $payload;
    }

    public static function cleanup_unsafe_preload_jobs($limit = 500) {
        global $wpdb;
        $table = self::jobs_table_name();
        $table_sql = self::jobs_table_sql();

        if ('' === $table || '' === $table_sql) {
            return array('ok' => false, 'skipped' => 0, 'repaired' => 0, 'reason' => 'table_unavailable');
        }

        $limit = max(1, min(1000, absint($limit)));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled constants and prepared values.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, payload, status, queue, type, last_error FROM " . $table_sql . " WHERE type IN ('preload_url','generate_css','remote_css') AND status IN ('pending','retrying','failed') ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if (null === $rows && !empty($wpdb->last_error)) {
            return array('ok' => false, 'skipped' => 0, 'repaired' => 0, 'reason' => 'query_failed');
        }

        $skipped = 0;
        $repaired = 0;
        $incomplete = false;
        foreach ((array) $rows as $row) {
            $payload = UCP_Helpers::safe_json_decode_array((string) $row['payload']);
            $payload = is_array($payload) ? $payload : array();
            $raw_url = isset($payload['url']) ? $payload['url'] : '';
            $url = UCP_Helpers::strict_local_url($raw_url);

            $type = isset($row['type']) ? sanitize_key($row['type']) : 'preload_url';
            $row_status = isset($row['status']) ? sanitize_key($row['status']) : '';
            $is_unsafe_preload = 'preload_url' === $type && class_exists('UCP_Preload') && UCP_Preload::is_safety_excluded_url($url);
            if (!$url || !wp_http_validate_url($url) || $is_unsafe_preload) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned maintenance update for skipped preload jobs.
                $updated = $wpdb->update(
                    $table,
                    array(
                        'status'        => 'success',
                        'result'        => UCP_Helpers::safe_json_encode_or(array('ok' => true, 'skipped' => true, 'reason' => $is_unsafe_preload ? 'preload_safety_cleanup' : 'invalid_url_cleanup'), '{}'),
                        'last_error'    => null,
                        'locked_until'  => null,
                        'claim_token'   => '',
                        'job_signature' => null,
                        'updated_at'    => current_time('mysql', true),
                    ),
                    array('id' => absint($row['id']), 'status' => $row_status),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                    array('%d', '%s')
                );
                if (1 === (int) $updated) {
                    $skipped++;
                } elseif (false === $updated || !empty($wpdb->last_error)) {
                    $incomplete = true;
                }
                continue;
            }

            if ($url !== $raw_url) {
                $payload['url'] = $url;
                $payload_json = self::encode_job_payload($payload);
                $signature = self::build_job_signature($type, $payload, isset($row['queue']) ? $row['queue'] : 'default');
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned maintenance update for repaired preload jobs.
                $updated = $wpdb->update(
                    $table,
                    array(
                        'payload'       => $payload_json,
                        'job_signature' => $signature,
                        'status'        => 'pending',
                        'attempts'      => 0,
                        'available_at'  => current_time('mysql', true),
                        'last_error'    => null,
                        'locked_until'  => null,
                        'claim_token'   => '',
                        'updated_at'    => current_time('mysql', true),
                    ),
                    array('id' => absint($row['id']), 'status' => $row_status),
                    array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'),
                    array('%d', '%s')
                );
                if (1 === (int) $updated) {
                    $repaired++;
                } elseif (false === $updated || !empty($wpdb->last_error)) {
                    $incomplete = true;
                }
                continue;
            }

            $last_error = isset($row['last_error']) ? (string) $row['last_error'] : '';
            $preload_http_code = 0;
            if (preg_match('/HTTP\s*(\d{3})/i', $last_error, $preload_http_match)) {
                $preload_http_code = absint($preload_http_match[1]);
            }
            if ('preload_url' === $type && in_array($row_status, array('failed', 'retrying'), true) && ($preload_http_code || preg_match('/geen goed resultaat/i', $last_error))) {
                if ($preload_http_code && self::is_transient_preload_http_status($preload_http_code)) {
                    continue;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned maintenance update for non-actionable preload responses.
                $updated = $wpdb->update(
                    $table,
                    array(
                        'status'        => 'success',
                        'result'        => UCP_Helpers::safe_json_encode_or(array('ok' => true, 'skipped' => true, 'reason' => 'preload_http_cleanup'), '{}'),
                        'last_error'    => null,
                        'locked_until'  => null,
                        'claim_token'   => '',
                        'job_signature' => null,
                        'updated_at'    => current_time('mysql', true),
                    ),
                    array('id' => absint($row['id']), 'status' => $row_status),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                    array('%d', '%s')
                );
                if (1 === (int) $updated) {
                    $skipped++;
                } elseif (false === $updated || !empty($wpdb->last_error)) {
                    $incomplete = true;
                }
                continue;
            }

            if (in_array($type, array('generate_css', 'remote_css'), true) && 'failed' === $row_status && preg_match('/HTTP\s*\d{3}/i', $last_error)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned maintenance update for URL job retry.
                $updated = $wpdb->update(
                    $table,
                    array(
                        'status'       => 'pending',
                        'attempts'     => 0,
                        'available_at' => current_time('mysql', true),
                        'last_error'   => null,
                        'locked_until' => null,
                        'claim_token'  => '',
                        'updated_at'   => current_time('mysql', true),
                    ),
                    array('id' => absint($row['id']), 'status' => $row_status),
                    array('%s', '%d', '%s', '%s', '%s', '%s', '%s'),
                    array('%d', '%s')
                );
                if (1 === (int) $updated) {
                    $repaired++;
                } elseif (false === $updated || !empty($wpdb->last_error)) {
                    $incomplete = true;
                }
            }
        }

        if (($skipped || $repaired) && class_exists('UCP_Logger')) {
            UCP_Logger::log('info', 'jobs', 'url_jobs_cleaned', __('Onveilige of ongeldige URL-taken zijn opgeschoond.', 'ultracache-pro'), array('skipped' => $skipped, 'repaired' => $repaired));
        }

        return array('ok' => !$incomplete, 'skipped' => $skipped, 'repaired' => $repaired);
    }

    public static function rescue_stale_running_jobs($limit = 100) {
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 100;
        }
        global $wpdb;
        $table = self::jobs_table_name();
        $table_sql = self::jobs_table_sql();
        if ('' === $table || '' === $table_sql) {
            return 0;
        }

        $limit = max(1, min(500, absint($limit)));
        $now = current_time('mysql', true);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned queue recovery with controlled table name and prepared limit.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, attempts, max_attempts, claim_token, locked_until FROM " . $table_sql . " WHERE status = 'running' AND locked_until IS NOT NULL AND locked_until < %s ORDER BY id ASC LIMIT %d",
                $now,
                $limit
            ),
            ARRAY_A
        );
        $rescued = 0;
        foreach ((array) $rows as $row) {
            $attempts = isset($row['attempts']) ? absint($row['attempts']) : 0;
            $max_attempts = max(1, isset($row['max_attempts']) ? absint($row['max_attempts']) : 3);
            $next_status = $attempts >= $max_attempts ? 'failed' : 'retrying';
            $signature_sql = 'failed' === $next_status ? ', job_signature = NULL' : '';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-set recovery for the exact expired claim in the plugin-owned queue table.
            $updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE " . $table_sql . " SET status = %s, available_at = %s, last_error = %s, claim_token = '', locked_until = NULL, updated_at = %s" . $signature_sql . " WHERE id = %d AND status = 'running' AND claim_token = %s AND locked_until = %s AND locked_until < %s",
                    $next_status,
                    $now,
                    'Stale running lock automatisch vrijgegeven.',
                    $now,
                    absint($row['id']),
                    (string) $row['claim_token'],
                    (string) $row['locked_until'],
                    $now
                )
            );
            if (1 === (int) $updated) {
                $rescued++;
            }
        }
        if ($rescued && class_exists('UCP_Logger')) {
            UCP_Logger::log('warning', 'jobs', 'stale_running_jobs_rescued', __('Vastgelopen actieve taken zijn vrijgegeven.', 'ultracache-pro'), array('count' => $rescued));
        }
        return $rescued;
    }

    public static function dead_letter_summary($limit = 20) {
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 20;
        }
        global $wpdb;
        $limit = max(1, min(100, absint($limit)));
        $summary = array('total' => 0, 'by_type' => array(), 'recent' => array());
        $table_sql = self::jobs_table_sql();
        if ('' === $table_sql) {
            return $summary;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned queue diagnostics.
        $summary['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . $table_sql . " WHERE status = 'failed'");
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned queue diagnostics.
        $rows = $wpdb->get_results("SELECT type, COUNT(*) AS total FROM " . $table_sql . " WHERE status = 'failed' GROUP BY type ORDER BY total DESC", ARRAY_A);
        foreach ((array) $rows as $row) {
            $summary['by_type'][sanitize_key((string) $row['type'])] = absint($row['total']);
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned queue diagnostics.
        $recent_sql = $wpdb->prepare("SELECT id, type, queue, attempts, max_attempts, last_error, updated_at FROM " . $table_sql . " WHERE status = 'failed' ORDER BY updated_at DESC LIMIT %d", $limit);
        $summary['recent'] = $wpdb->get_results($recent_sql, ARRAY_A);
        return $summary;
    }

    protected static function count_jobs_where($where, $args = array()) {
        global $wpdb;
        $table_sql = self::jobs_table_sql();
        if ('' === $table_sql) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM ' . $table_sql . ' WHERE ' . (string) $where;
        if (!empty($args)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL fragment is internal; values are prepared.
            $sql = $wpdb->prepare($sql, $args);
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        return (int) $wpdb->get_var($sql);
    }

    public static function count_by_status($status) {
        if (!is_scalar($status) && null !== $status) {
            $status = '';
        }
        return self::count_jobs_where('status = %s', array(sanitize_key($status)));
    }

    public static function count_by_status_and_type($status, $type) {
        if (!is_scalar($status) && null !== $status) {
            $status = '';
        }
        if (!is_scalar($type) && null !== $type) {
            $type = '';
        }
        return self::count_jobs_where(
            'status = %s AND type = %s',
            array(sanitize_key($status), sanitize_key($type))
        );
    }

    public static function count_due_jobs() {
        $now = current_time('mysql', true);
        return self::count_jobs_where(
            "status IN ('pending','retrying') AND available_at <= %s AND (locked_until IS NULL OR locked_until < %s)",
            array($now, $now)
        );
    }

    public static function count_due_jobs_by_type($type) {
        if (!is_scalar($type) && null !== $type) {
            $type = '';
        }
        $now = current_time('mysql', true);
        return self::count_jobs_where(
            "type = %s AND status IN ('pending','retrying') AND available_at <= %s AND (locked_until IS NULL OR locked_until < %s)",
            array(sanitize_key($type), $now, $now)
        );
    }

    public static function count_retrying_with_zero_attempts() {
        return self::count_jobs_where("status = 'retrying' AND attempts = 0");
    }

    public static function count_stale_running_jobs() {
        $now = current_time('mysql', true);
        return self::count_jobs_where(
            "status = 'running' AND locked_until IS NOT NULL AND locked_until < %s",
            array($now)
        );
    }

    public static function normalize_summary($summary) {
        $summary = is_array($summary) ? $summary : array();
        $pending = isset($summary['pending']) ? absint($summary['pending']) : 0;
        $running = isset($summary['running']) ? absint($summary['running']) : 0;
        $retrying = isset($summary['retrying']) ? absint($summary['retrying']) : 0;
        $failed = isset($summary['failed']) ? absint($summary['failed']) : 0;
        $stale_running = isset($summary['staleRunning']) ? absint($summary['staleRunning']) : 0;

        $summary['pending'] = $pending;
        $summary['running'] = $running;
        $summary['retrying'] = $retrying;
        $summary['failed'] = $failed;
        $summary['success'] = isset($summary['success']) ? absint($summary['success']) : 0;
        $summary['staleRunning'] = $stale_running;
        $summary['totalOpen'] = isset($summary['totalOpen']) ? absint($summary['totalOpen']) : $pending + $running + $retrying;
        $summary['needsAttention'] = !empty($summary['needsAttention']) || $failed > 0 || $stale_running > 0;

        return $summary;
    }

    public function run_queue_type_until_idle($type, $force = false, $max_batches = 5, $bypass_load_guard = false) {
        $previous_type = $this->job_type_current_run;
        $previous_bypass = $this->bypass_load_guard_current_run;
        $this->job_type_current_run = sanitize_key($type);
        $this->bypass_load_guard_current_run = (bool) $bypass_load_guard;
        try {
            return $this->run_queue_until_idle($force, $max_batches);
        } finally {
            $this->job_type_current_run = $previous_type;
            $this->bypass_load_guard_current_run = $previous_bypass;
        }
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
        $table_sql = self::jobs_table_sql();
        if ('' === $table_sql) {
            return false;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $sql = "SELECT COUNT(*) FROM " . $table_sql . " WHERE status IN ('pending','retrying')";
        return (int) $wpdb->get_var($sql) > 0;
    }

    public static function get_runner_status() {
        $next = wp_next_scheduled(self::CRON_HOOK);
        $last = get_option('ucp_jobs_last_run_summary', array());
        $last = is_array($last) ? $last : array();
        $due = self::count_due_jobs();
        $stale_running = self::count_stale_running_jobs();
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        return array(
            'due'          => $due,
            'nextCron'     => $next ? gmdate('Y-m-d H:i:s', (int) $next) : '',
            'cronDisabled' => $cron_disabled,
            'hook'         => self::CRON_HOOK,
            'schedule'     => $next ? 'ucp_one_minute' : '',
            'staleRunning' => $stale_running,
            'hasBlockedRunner' => $stale_running > 0 || ($cron_disabled && $due > 0),
            'lastRun'      => isset($last['ended_at']) ? sanitize_text_field((string) $last['ended_at']) : '',
            'lastDuration' => isset($last['duration']) ? absint($last['duration']) : 0,
            'lastProcessed'=> isset($last['processed']) ? absint($last['processed']) : 0,
            'batchSize'    => isset($last['batch_size']) ? absint($last['batch_size']) : max(1, absint(UCP_Options::get('job_batch_size', 5))),
            'emptyStreak'  => isset($last['empty_streak']) ? absint($last['empty_streak']) : absint(get_option('ucp_jobs_empty_run_streak', 0)),
        );
    }

    public static function get_type_summary($type) {
        if (!is_scalar($type) && null !== $type) {
            $type = '';
        }
        $type = sanitize_key($type);
        $pending = self::count_by_status_and_type('pending', $type);
        $running = self::count_by_status_and_type('running', $type);
        $retrying = self::count_by_status_and_type('retrying', $type);
        $failed = self::count_by_status_and_type('failed', $type);
        return self::normalize_summary(array(
            'pending' => $pending,
            'running' => $running,
            'retrying' => $retrying,
            'failed' => $failed,
            'success' => self::count_by_status_and_type('success', $type),
            'runner' => array(
                'due' => self::count_due_jobs_by_type($type),
            ),
        ));
    }

    public static function get_summary() {
        $pending = self::count_by_status('pending');
        $running = self::count_by_status('running');
        $retrying = self::count_by_status('retrying');
        $failed = self::count_by_status('failed');
        $stale_running = self::count_stale_running_jobs();
        return self::normalize_summary(array(
            'pending'     => $pending,
            'running'     => $running,
            'retrying'    => $retrying,
            'failed'      => $failed,
            'success'     => self::count_by_status('success'),
            'staleRunning'=> $stale_running,
            'dead_letter' => self::dead_letter_summary(5),
        ));
    }

    public static function recent($limit = 25) {
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 25;
        }
        $result = self::query(
            array(
                'per_page' => max(1, absint($limit)),
                'paged'    => 1,
            )
        );
        return $result['rows'];
    }

    public static function retry_job($job_id, $type = 'preload_url') {
        if (!is_scalar($job_id) && null !== $job_id) {
            $job_id = 0;
        }
        if (!is_scalar($type) && null !== $type) {
            $type = 'preload_url';
        }
        global $wpdb;
        $table = self::jobs_table_name();
        if ('' === $table) {
            return false;
        }
        $job_id = absint($job_id);
        $type = sanitize_key($type);
        if (!$job_id || '' === $type) {
            return false;
        }
        $now = current_time('mysql', true);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned queue state transition with fixed conditions.
        $updated = $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::jobs_table_sql() . " SET status = 'pending', attempts = 0, available_at = %s, result = NULL, last_error = NULL, locked_until = NULL, claim_token = '', updated_at = %s WHERE id = %d AND type = %s AND status = 'failed'",
            $now,
            $now,
            $job_id,
            $type
        ));
        if (1 === (int) $updated) {
            self::sync_schedule();
            return true;
        }
        return false;
    }

    public static function cancel_job($job_id, $type = 'preload_url') {
        if (!is_scalar($job_id) && null !== $job_id) {
            $job_id = 0;
        }
        if (!is_scalar($type) && null !== $type) {
            $type = 'preload_url';
        }
        global $wpdb;
        $table = self::jobs_table_name();
        if ('' === $table) {
            return false;
        }
        $job_id = absint($job_id);
        $type = sanitize_key($type);
        if (!$job_id || '' === $type) {
            return false;
        }
        $now = current_time('mysql', true);
        $result = UCP_Helpers::safe_json_encode_or(array('ok' => true, 'skipped' => true, 'reason' => 'cancelled_by_admin'), '{}');
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned queue state transition with fixed conditions.
        $updated = $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::jobs_table_sql() . " SET status = 'success', result = %s, last_error = NULL, job_signature = NULL, locked_until = NULL, claim_token = '', updated_at = %s WHERE id = %d AND type = %s AND status IN ('pending','retrying')",
            $result,
            $now,
            $job_id,
            $type
        ));
        return 1 === (int) $updated;
    }

    protected static function normalize_query_row($row) {
        $row = is_array($row) ? $row : array();
        $payload = !empty($row['payload']) ? UCP_Helpers::safe_json_decode_array((string) $row['payload']) : array();
        $result = !empty($row['result']) ? UCP_Helpers::safe_json_decode_array((string) $row['result']) : array();
        $payload = is_array($payload) ? $payload : array();
        $result = is_array($result) ? $result : array();
        $url = !empty($payload['url']) && class_exists('UCP_Helpers') ? UCP_Helpers::strict_local_url($payload['url']) : '';
        unset($row['payload'], $row['result'], $row['claim_token'], $row['job_signature']);
        $row['id'] = isset($row['id']) ? absint($row['id']) : 0;
        $row['priority'] = isset($row['priority']) ? absint($row['priority']) : 0;
        $row['attempts'] = isset($row['attempts']) ? absint($row['attempts']) : 0;
        $row['max_attempts'] = isset($row['max_attempts']) ? absint($row['max_attempts']) : 0;
        $row['url'] = $url ? esc_url_raw($url) : '';
        $row['path'] = $url ? sanitize_text_field((string) wp_parse_url($url, PHP_URL_PATH)) : '';
        $source = !empty($payload['source']) ? $payload['source'] : (!empty($row['queue']) ? $row['queue'] : '');
        $row['source'] = is_scalar($source) ? sanitize_key((string) $source) : '';
        $http_code = !empty($result['http_code']) ? $result['http_code'] : (!empty($result['status']) ? $result['status'] : 0);
        $row['http_code'] = is_scalar($http_code) ? absint($http_code) : 0;
        $reason = !empty($result['reason']) ? $result['reason'] : '';
        $row['result_reason'] = is_scalar($reason) ? sanitize_key((string) $reason) : '';
        $row['cancelled'] = 'cancelled_by_admin' === $row['result_reason'];
        $row['last_error'] = !empty($row['last_error']) && is_scalar($row['last_error'])
            ? substr(sanitize_text_field((string) $row['last_error']), 0, 300)
            : '';
        return $row;
    }

    public static function query($args = array()) {
        global $wpdb;
        $defaults = array(
            'status'   => '',
            'queue'    => '',
            'type'     => '',
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
        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $params[] = sanitize_key($args['type']);
        }
        if (!empty($args['search']) && (is_scalar($args['search']) || null === $args['search'])) {
            $search = substr(sanitize_text_field(wp_unslash((string) $args['search'])), 0, 200);
            if ('' !== $search) {
                $like = '%' . $wpdb->esc_like($search) . '%';
                $where[] = '(type LIKE %s OR job_uuid LIKE %s OR last_error LIKE %s)';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        $table_sql = self::jobs_table_sql();
        if ('' === $table_sql) {
            return array(
                'rows'      => array(),
                'total'     => 0,
                'per_page'  => max(1, absint($args['per_page'])),
                'paged'     => max(1, absint($args['paged'])),
                'max_pages' => 1,
            );
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = 'SELECT COUNT(*) FROM ' . $table_sql . ' WHERE ' . $where_sql;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $prepared_count = !empty($params) ? $wpdb->prepare($count_sql, $params) : $count_sql;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is assembled from fixed fragments and prepared values above.
        $total = (int) $wpdb->get_var($prepared_count);

        $per_page = max(1, absint($args['per_page']));
        $paged = max(1, absint($args['paged']));
        $offset = ($paged - 1) * $per_page;

        $rows_sql = 'SELECT * FROM ' . $table_sql . ' WHERE ' . $where_sql . ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $rows_params = $params;
        $rows_params[] = $per_page;
        $rows_params[] = $offset;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $prepared_rows = $wpdb->prepare($rows_sql, $rows_params);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is assembled from fixed fragments and prepared values above.
        $rows = $wpdb->get_results($prepared_rows, ARRAY_A);
        $rows = array_map(array(__CLASS__, 'normalize_query_row'), (array) $rows);

        return array(
            'rows'      => $rows,
            'total'     => $total,
            'per_page'  => $per_page,
            'paged'     => $paged,
            'max_pages' => max(1, (int) ceil($total / $per_page)),
        );
    }
}
