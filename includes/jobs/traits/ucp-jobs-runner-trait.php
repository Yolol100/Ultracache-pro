<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin-owned queue table queries use controlled table constants and prepared/sanitized values.

trait UCP_Jobs_Runner_Trait {
    public function run_queue($force = false) {
        global $wpdb;
        $table = self::jobs_table_name();
        $table_sql = self::jobs_table_sql();
        if ('' === $table || '' === $table_sql) {
            return 0;
        }

        $type_filter = sanitize_key($this->job_type_current_run);
        $limit = max(1, absint(UCP_Options::get('job_batch_size', 5)));
        $started_at = time();
        $started_at_microtime = microtime(true);
        $max_execution_time = max(0, (int) ini_get('max_execution_time'));
        $default_time_budget = $max_execution_time > 0 ? max(5, min(30, $max_execution_time - 5)) : 30;
        $time_budget = (float) apply_filters('ucp_jobs_run_queue_max_seconds', $default_time_budget, $force);
        $time_budget = max(5.0, min(300.0, $time_budget));
        $deadline = $started_at_microtime + $time_budget;
        $memory_limit = function_exists('wp_convert_hr_to_bytes') ? (int) wp_convert_hr_to_bytes((string) ini_get('memory_limit')) : 0;
        $memory_ratio = (float) apply_filters('ucp_jobs_run_queue_memory_ratio', 0.85, $force);
        $memory_ratio = max(0.50, min(0.95, $memory_ratio));
        $memory_budget = $memory_limit > 0 ? (int) floor($memory_limit * $memory_ratio) : 0;
        $preload_queue_enabled = (bool) UCP_Options::get('enable_preload_queue');
        $preload_paused = $preload_queue_enabled
            && !$this->bypass_load_guard_current_run
            && class_exists('UCP_Preload')
            && UCP_Preload::server_load_too_high();

        if ('preload_url' === $type_filter && $preload_queue_enabled) {
            $limit = max(1, min(20, absint(UCP_Options::get('preload_batch_size', 5))));
            if ($preload_paused) {
                UCP_Logger::log('warning', 'jobs', 'preload_queue_paused_high_load', __('Preloadwachtrij is gepauzeerd door hoge serverbelasting.', 'ultracache-pro'), array('limit' => $limit));
                return 0;
            }
        } elseif ($preload_paused && '' === $type_filter) {
            UCP_Logger::log('warning', 'jobs', 'preload_queue_paused_high_load', __('Preloadwachtrij is gepauzeerd door hoge serverbelasting; andere taken worden wel verwerkt.', 'ultracache-pro'), array('limit' => $limit));
        }
        /**
         * Limit the number of jobs in one runner pass. The dashboard uses this to
         * keep the manual "Verwerk taken" action responsive instead of blocking
         * the admin screen on a large preload batch.
         */
        $limit = max(1, min(100, absint(apply_filters('ucp_jobs_run_queue_limit', $limit, $force))));
        $preload_exclusion_sql = ($preload_paused && '' === $type_filter) ? " AND type <> 'preload_url'" : '';
        $token = wp_generate_password(16, false);
        $ttl = max(60, absint(UCP_Options::get('job_lock_ttl', 300)));
        $previous_force_current_run = $this->force_current_run;
        $this->force_current_run = (bool) $force;
        $runner_token = self::acquire_runner_lock($ttl);
        if (!$runner_token) {
            $this->force_current_run = $previous_force_current_run;
            return 0;
        }
        $this->active_runner_lease = array(
            'token'        => (string) $runner_token,
            'ttl'          => $ttl,
            'refreshed_at' => time(),
        );
        $this->active_job_lease = array();
        $this->runner_lease_lost = false;
        add_action('ucp_operation_heartbeat', array($this, 'refresh_active_job_leases'), 5, 0);
        self::rescue_stale_running_jobs();

        if ($force) {
            if ('' !== $type_filter) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
                $sql = $wpdb->prepare(
                    "SELECT id FROM " . $table_sql . " WHERE type = %s AND status IN (%s,%s) AND (locked_until IS NULL OR locked_until < %s) ORDER BY priority ASC, id ASC LIMIT %d",
                    $type_filter,
                    'pending',
                    'retrying',
                    current_time('mysql', true),
                    $limit
                );
            } else {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
                $sql = $wpdb->prepare(
                    "SELECT id FROM " . $table_sql . " WHERE status IN (%s,%s) AND (locked_until IS NULL OR locked_until < %s)" . $preload_exclusion_sql . " ORDER BY priority ASC, CASE WHEN type IN ('generate_css','remote_css','headless_css') THEN 0 WHEN type = 'diagnostics_snapshot' THEN 1 WHEN type = 'preload_url' THEN 2 ELSE 1 END ASC, id ASC LIMIT %d",
                    'pending',
                    'retrying',
                    current_time('mysql', true),
                    $limit
                );
            }
        } else {
            if ('' !== $type_filter) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
                $sql = $wpdb->prepare(
                    "SELECT id FROM " . $table_sql . " WHERE type = %s AND status IN (%s,%s) AND available_at <= %s AND (locked_until IS NULL OR locked_until < %s) ORDER BY priority ASC, id ASC LIMIT %d",
                    $type_filter,
                    'pending',
                    'retrying',
                    current_time('mysql', true),
                    current_time('mysql', true),
                    $limit
                );
            } else {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
                $sql = $wpdb->prepare(
                    "SELECT id FROM " . $table_sql . " WHERE status IN (%s,%s) AND available_at <= %s AND (locked_until IS NULL OR locked_until < %s)" . $preload_exclusion_sql . " ORDER BY priority ASC, CASE WHEN type IN ('generate_css','remote_css','headless_css') THEN 0 WHEN type = 'diagnostics_snapshot' THEN 1 WHEN type = 'preload_url' THEN 2 ELSE 1 END ASC, id ASC LIMIT %d",
                    'pending',
                    'retrying',
                    current_time('mysql', true),
                    current_time('mysql', true),
                    $limit
                );
            }
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $job_ids = $wpdb->get_col($sql);
        if (empty($job_ids)) {
            $empty_runs = (int) get_option('ucp_jobs_empty_run_streak', 0) + 1;
            update_option('ucp_jobs_empty_run_streak', $empty_runs, false);
            update_option('ucp_jobs_last_run_summary', array(
                'started_at' => gmdate('Y-m-d H:i:s', $started_at),
                'ended_at' => gmdate('Y-m-d H:i:s'),
                'duration' => max(0, time() - $started_at),
                'processed' => 0,
                'batch_size' => $limit,
                'empty_streak' => $empty_runs,
                'forced' => (bool) $force,
                'stop_reason' => 'empty',
                'time_budget_seconds' => $time_budget,
                'memory_budget_bytes' => $memory_budget,
            ), false);
            remove_action('ucp_operation_heartbeat', array($this, 'refresh_active_job_leases'), 5);
            $this->active_runner_lease = array();
            $this->active_job_lease = array();
            $this->runner_lease_lost = false;
            self::release_runner_lock($runner_token);
            $this->force_current_run = $previous_force_current_run;
            return 0;
        }
        $processed = 0;
        $stop_reason = '';

        try {
            foreach ($job_ids as $job_id) {
                do_action('ucp_operation_heartbeat');
                if ($this->runner_lease_lost) {
                    $stop_reason = 'runner_lease_lost';
                    break;
                }
                $budget_stop_reason = $this->queue_runner_budget_stop_reason($deadline, $memory_budget);
                if ('' !== $budget_stop_reason) {
                    $stop_reason = $budget_stop_reason;
                    break;
                }
                $claim_now = current_time('mysql', true);
                $lock_until = gmdate('Y-m-d H:i:s', time() + $ttl);
                if ($force) {
                    $claim_sql = $wpdb->prepare(
                        "UPDATE " . $table_sql . " SET status = 'running', claim_token = %s, locked_until = %s, started_at = %s, updated_at = %s WHERE id = %d AND status IN ('pending','retrying') AND (locked_until IS NULL OR locked_until < %s)",
                        $token,
                        $lock_until,
                        $claim_now,
                        $claim_now,
                        $job_id,
                        $claim_now
                    );
                } else {
                    $claim_sql = $wpdb->prepare(
                        "UPDATE " . $table_sql . " SET status = 'running', claim_token = %s, locked_until = %s, started_at = %s, updated_at = %s WHERE id = %d AND status IN ('pending','retrying') AND available_at <= %s AND (locked_until IS NULL OR locked_until < %s)",
                        $token,
                        $lock_until,
                        $claim_now,
                        $claim_now,
                        $job_id,
                        $claim_now,
                        $claim_now
                    );
                }
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic compare-and-set claim against the plugin-owned queue table.
                $claimed = $wpdb->query($claim_sql);
                if (1 !== $claimed) {
                    UCP_Logger::log('warning', 'jobs', 'job_claim_failed', __('Taak kon niet veilig worden geclaimd.', 'ultracache-pro'), array('job_id' => $job_id));
                    continue;
                }
                $job = $wpdb->get_row(
                    $wpdb->prepare(
                        'SELECT * FROM ' . $table_sql . ' WHERE id = %d AND claim_token = %s AND status = %s',
                        $job_id,
                        $token,
                        'running'
                    ),
                    ARRAY_A
                );
                if (!$job) {
                    $released_at = current_time('mysql', true);
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- claim-guarded recovery of a job that could not be read after a successful claim.
                    $released = $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE " . $table_sql . " SET status = 'pending', claim_token = '', locked_until = NULL, started_at = NULL, updated_at = %s WHERE id = %d AND claim_token = %s AND status = 'running'",
                            $released_at,
                            $job_id,
                            $token
                        )
                    );
                    UCP_Logger::log(
                        1 === (int) $released ? 'warning' : 'error',
                        'jobs',
                        1 === (int) $released ? 'job_claim_read_released' : 'job_claim_read_release_failed',
                        1 === (int) $released ? 'Taakclaim vrijgegeven nadat de taak niet kon worden teruggelezen.' : 'Taakclaim kon na een leesfout niet veilig worden vrijgegeven.',
                        array('job_id' => $job_id)
                    );
                    continue;
                }
                $this->active_job_lease = array(
                    'id'           => absint($job['id']),
                    'claim_token'  => (string) $job['claim_token'],
                    'ttl'          => $ttl,
                    'refreshed_at' => time(),
                );
                try {
                    $this->process_job($job);
                } finally {
                    $this->active_job_lease = array();
                }
                do_action('ucp_operation_heartbeat');
                $processed++;
                if ($this->runner_lease_lost) {
                    $stop_reason = 'runner_lease_lost';
                    break;
                }
            }
        } finally {
            remove_action('ucp_operation_heartbeat', array($this, 'refresh_active_job_leases'), 5);
            $this->active_runner_lease = array();
            $this->active_job_lease = array();
            $this->runner_lease_lost = false;
            self::release_runner_lock($runner_token);
            $this->force_current_run = $previous_force_current_run;
        }
        update_option('ucp_jobs_empty_run_streak', 0, false);
        update_option('ucp_jobs_last_run_summary', array(
            'started_at' => gmdate('Y-m-d H:i:s', $started_at),
            'ended_at' => gmdate('Y-m-d H:i:s'),
            'duration' => max(0, time() - $started_at),
            'processed' => $processed,
            'batch_size' => $limit,
            'empty_streak' => 0,
            'forced' => (bool) $force,
            'stop_reason' => $stop_reason,
            'time_budget_seconds' => $time_budget,
            'memory_budget_bytes' => $memory_budget,
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ), false);
        if (self::has_due_jobs(true)) {
            self::sync_schedule();
        }
        return $processed;
    }

    protected function queue_runner_budget_stop_reason($deadline, $memory_budget) {
        if (microtime(true) >= (float) $deadline) {
            return 'time_budget';
        }
        if ((int) $memory_budget > 0 && memory_get_usage(true) >= (int) $memory_budget) {
            return 'memory_budget';
        }
        return '';
    }

    public function refresh_active_job_leases($force = false) {
        $now = time();
        $force = (bool) $force;

        if (!empty($this->active_runner_lease['token'])) {
            $runner_ttl = max(60, absint($this->active_runner_lease['ttl'] ?? 0));
            $runner_interval = max(5, min(60, (int) floor($runner_ttl / 3)));
            $runner_refreshed_at = absint($this->active_runner_lease['refreshed_at'] ?? 0);
            if ($force || ($now - $runner_refreshed_at) >= $runner_interval) {
                if (self::refresh_runner_lock((string) $this->active_runner_lease['token'], $runner_ttl)) {
                    $this->active_runner_lease['refreshed_at'] = $now;
                } else {
                    $this->runner_lease_lost = true;
                }
            }
        }

        if (!empty($this->active_job_lease['id']) && !empty($this->active_job_lease['claim_token'])) {
            $job_ttl = max(60, absint($this->active_job_lease['ttl'] ?? 0));
            $job_interval = max(5, min(60, (int) floor($job_ttl / 3)));
            $job_refreshed_at = absint($this->active_job_lease['refreshed_at'] ?? 0);
            if ($force || ($now - $job_refreshed_at) >= $job_interval) {
                if (!$this->refresh_active_job_claim(
                    absint($this->active_job_lease['id']),
                    (string) $this->active_job_lease['claim_token'],
                    $job_ttl
                )) {
                    throw new RuntimeException('Job lease was lost.');
                }
                $this->active_job_lease['refreshed_at'] = $now;
            }
        }

        return !$this->runner_lease_lost;
    }

    protected function refresh_active_job_claim($job_id, $claim_token, $ttl) {
        global $wpdb;

        $job_id = absint($job_id);
        $claim_token = is_scalar($claim_token) ? (string) $claim_token : '';
        if ($job_id <= 0 || '' === $claim_token) {
            return false;
        }

        $table_sql = self::jobs_table_sql();
        if ('' === $table_sql) {
            return false;
        }

        $locked_until = gmdate('Y-m-d H:i:s', time() + max(60, absint($ttl)));
        $updated_at = current_time('mysql', true);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- claim-token guarded renewal in the plugin-owned queue table.
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE " . $table_sql . " SET locked_until = %s, updated_at = %s WHERE id = %d AND claim_token = %s AND status = 'running'",
                $locked_until,
                $updated_at,
                $job_id,
                $claim_token
            )
        );
        if (1 === (int) $updated) {
            return true;
        }

        $stored = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT claim_token, status, locked_until FROM ' . $table_sql . ' WHERE id = %d',
                $job_id
            ),
            ARRAY_A
        );
        return is_array($stored)
            && isset($stored['claim_token'], $stored['status'], $stored['locked_until'])
            && is_scalar($stored['claim_token'])
            && hash_equals((string) $stored['claim_token'], $claim_token)
            && 'running' === (string) $stored['status']
            && strtotime((string) $stored['locked_until']) >= strtotime($locked_until);
    }

    protected function process_job($job) {
        global $wpdb;
        $table = self::jobs_table_name();
        if ('' === $table) {
            return;
        }

        $payload = UCP_Helpers::safe_json_decode_array((string) $job['payload']);
        $last_error = '';
        $retry_after = 0;
        try {
            do_action('ucp_operation_heartbeat');
            $success = $this->run_job($job['type'], $payload);
            if (is_wp_error($success)) {
                $last_error = $success->get_error_message();
                $error_data = $success->get_error_data();
                if ('preload_url' === $job['type'] && is_array($error_data) && !empty($error_data['retry_after'])) {
                    $retry_after = min(DAY_IN_SECONDS, absint($error_data['retry_after']));
                }
                $success = false;
            }
        } catch (Throwable $e) {
            $success = false;
            $last_error = $e->getMessage();
        }
        $last_error = $this->sanitize_job_error($last_error);
        $attempts = (int) $job['attempts'] + 1;
        $max_attempts = max(1, (int) $job['max_attempts']);

        if ($success) {
            $result_data = array('ok' => true);
            if ('preload_url' === $job['type'] && is_array($success)) {
                $result_data = array(
                    'ok'        => true,
                    'skipped'   => !empty($success['skipped']),
                    'reason'    => !empty($success['reason']) ? sanitize_key((string) $success['reason']) : '',
                    'http_code' => !empty($success['http_code']) ? absint($success['http_code']) : 0,
                );
            }
            $finished_at = current_time('mysql', true);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- claim-guarded queue state transition in the plugin-owned table.
            $updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE " . self::jobs_table_sql() . " SET status = 'success', attempts = %d, finished_at = %s, updated_at = %s, result = %s, locked_until = NULL, claim_token = '', job_signature = NULL WHERE id = %d AND claim_token = %s AND status = 'running'",
                    $attempts,
                    $finished_at,
                    $finished_at,
                    UCP_Helpers::safe_json_encode_or($result_data, '{}'),
                    $job['id'],
                    (string) $job['claim_token']
                )
            );
            if (1 !== (int) $updated) {
                UCP_Logger::log('warning', 'jobs', 'job_completion_stale_claim', __('Verouderde taakclaim kon het actuele taakresultaat niet overschrijven.', 'ultracache-pro'), array('type' => $job['type'], 'job_id' => $job['id']));
                return;
            }
            UCP_Logger::log('info', 'jobs', 'job_success', __('Taak is afgerond.', 'ultracache-pro'), array('type' => $job['type'], 'job_id' => $job['id']));
            return;
        }

        $next_status = $attempts >= $max_attempts ? 'failed' : 'retrying';
        $delay = min(3600, max(60, pow(2, $attempts) * 60));
        if ($retry_after > 0) {
            $delay = min(DAY_IN_SECONDS, max($delay, $retry_after));
        }
        $available_at = gmdate('Y-m-d H:i:s', time() + $delay);
        $updated_at = current_time('mysql', true);
        $error_message = '' !== $last_error ? $last_error : 'Taak gaf geen goed resultaat terug.';
        $result_json = UCP_Helpers::safe_json_encode_or(array('ok' => false, 'final' => 'failed' === $next_status, 'error' => $error_message), '{}');
        $signature_sql = 'failed' === $next_status ? ', job_signature = NULL' : '';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- claim-guarded queue state transition in the plugin-owned table; SQL fragment is fixed by status.
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE " . self::jobs_table_sql() . " SET status = %s, attempts = %d, available_at = %s, updated_at = %s, last_error = %s, result = %s, locked_until = NULL, claim_token = ''" . $signature_sql . " WHERE id = %d AND claim_token = %s AND status = 'running'",
                $next_status,
                $attempts,
                $available_at,
                $updated_at,
                $error_message,
                $result_json,
                $job['id'],
                (string) $job['claim_token']
            )
        );
        if (1 !== (int) $updated) {
            UCP_Logger::log('warning', 'jobs', 'job_completion_stale_claim', __('Verouderde taakclaim kon het actuele taakresultaat niet overschrijven.', 'ultracache-pro'), array('type' => $job['type'], 'job_id' => $job['id']));
            return;
        }
        UCP_Logger::log('warning', 'jobs', 'job_retry_scheduled', __('Taak is opnieuw ingepland.', 'ultracache-pro'), array('type' => $job['type'], 'job_id' => $job['id'], 'status' => $next_status));
    }

    protected function sanitize_job_error($message) {
        $message = (string) $message;
        if (class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'redact_log_text')) {
            $message = UCP_Helpers::redact_log_text($message);
        } else {
            $message = sanitize_textarea_field(wp_strip_all_tags($message));
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($message, 'UTF-8') > 2000) {
                $message = mb_substr($message, 0, 2000, 'UTF-8') . '...[truncated]';
            }
        } elseif (strlen($message) > 2000) {
            $message = substr($message, 0, 2000);
            while ('' !== $message && 1 !== preg_match('//u', $message)) {
                $message = substr($message, 0, -1);
            }
            $message .= '...[truncated]';
        }
        return $message;
    }

    protected function run_job($type, $payload) {
        switch ($type) {
            case 'generate_css':
                return $this->run_generate_css_job($payload);
            case 'remote_css':
                return $this->run_remote_css_job($payload);
            case 'headless_css':
                return $this->run_headless_css_job($payload);
            case 'image_backfill_seed':
                return $this->run_image_backfill_seed_job($payload);
            case 'image_optimize':
                return $this->run_attachment_job($payload, 'UCP_Image_Queue');
            case 'lqip_generate':
                return $this->run_attachment_job($payload, 'UCP_LQIP');
            case 'localize_remote_asset':
                return $this->run_localize_remote_asset_job($payload);
            case 'cloud_sync':
                return UCP_Cloud::push_site_payload();
            case 'cloudflare_purge_url':
                return UCP_Edge::cloudflare_purge_url(isset($payload['url']) ? $payload['url'] : home_url('/'));
            case 'cloudflare_purge_all':
                return UCP_Edge::cloudflare_purge_all();
            case 'cloudflare_purge_urls':
                return UCP_Edge::cloudflare_purge_urls(isset($payload['urls']) ? $payload['urls'] : array());
            case 'preload_url':
                return $this->run_preload_url_job($payload);
            case 'precompress_cache':
                return $this->run_precompress_cache_job($payload);
            case 'diagnostics_snapshot':
                UCP_Health::run_checks();
                return true;
            default:
                return false;
        }
    }

    protected function run_precompress_cache_job($payload) {
        if (!class_exists('UCP_Cache') || !is_array($payload)) {
            return false;
        }
        $relative_path = isset($payload['relative_path']) && is_scalar($payload['relative_path']) ? (string) $payload['relative_path'] : '';
        $content_sha256 = isset($payload['content_sha256']) && is_scalar($payload['content_sha256']) ? (string) $payload['content_sha256'] : '';
        $cache = UCP_Helpers::new_without_constructor('UCP_Cache');
        return $cache instanceof UCP_Cache
            ? $cache->precompress_cache_representation($relative_path, $content_sha256)
            : false;
    }

    protected function local_job_url($payload) {
        return UCP_Helpers::strict_local_url(isset($payload['url']) ? $payload['url'] : home_url('/'), home_url('/'));
    }

    protected function run_generate_css_job($payload) {
        $url = $this->local_job_url($payload);
        if (!$url) {
            return false;
        }
        $ok = UCP_CSS::generate_for_url($url, !empty($payload['force']) || $this->force_current_run);
        if (!$ok) {
            $status = UCP_CSS::artifact_status($url);
            $message = !empty($status['message']) ? $status['message'] : __('CSS-opbouw mislukt zonder specifieke foutmelding.', 'ultracache-pro');
            return new WP_Error('ucp_generate_css_failed', $message);
        }
        return $ok;
    }

    protected function run_remote_css_job($payload) {
        $url = $this->local_job_url($payload);
        return $url ? UCP_Cloud::request_remote_css($url) : false;
    }

    protected function run_headless_css_job($payload) {
        $url = $this->local_job_url($payload);
        if (!$url || !class_exists('UCP_Render_Bridge')) {
            return false;
        }
        return UCP_Render_Bridge::run_job($url);
    }

    protected function run_image_backfill_seed_job($payload) {
        global $wpdb;

        if (!class_exists('UCP_Image_Optimizer') || !UCP_Image_Optimizer::supported_variant_generation_enabled()) {
            return array('ok' => true, 'skipped' => true, 'reason' => 'image_variants_disabled');
        }

        $cursor = isset($payload['cursor']) && is_scalar($payload['cursor']) ? absint($payload['cursor']) : 0;
        $legacy_batch_size = (int) apply_filters('ucp_image_backfill_batch', 500);
        $batch_size = (int) apply_filters('ucp_image_backfill_seed_batch', $legacy_batch_size, $cursor);
        $batch_size = max(25, min(2000, $batch_size));
        $meta_key = (string) UCP_Image_Optimizer::META_KEY;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded cursor query over core attachment tables with a prepared optimizer meta key.
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/jpeg','image/png') AND p.ID > %d AND pm.post_id IS NULL ORDER BY p.ID ASC LIMIT %d",
                $meta_key,
                $cursor,
                $batch_size
            )
        );
        $ids = array_values(array_filter(array_map('absint', (array) $ids)));
        $with_lqip = class_exists('UCP_LQIP') && UCP_LQIP::enabled();
        $queued = 0;

        foreach ($ids as $attachment_id) {
            do_action('ucp_operation_heartbeat');
            if ($this->runner_lease_lost) {
                throw new RuntimeException('Image backfill lease was lost.');
            }
            $image_payload = array('attachment_id' => $attachment_id);
            if (self::enqueue_unique('image_optimize', $image_payload, 30, 'media')
                || self::unique_job_exists('image_optimize', $image_payload, 'media')) {
                $queued++;
            }
            if ($with_lqip) {
                $lqip_queued = (bool) self::enqueue_unique('lqip_generate', $image_payload, 35, 'media')
                    || self::unique_job_exists('lqip_generate', $image_payload, 'media');
                if (!$lqip_queued) {
                    return new WP_Error('ucp_lqip_backfill_enqueue_failed', __('Een onderdeel van de afbeeldingsbackfill kon niet veilig worden ingepland.', 'ultracache-pro'));
                }
            }
        }

        $last_id = empty($ids) ? $cursor : (int) end($ids);
        $continued = false;
        if (count($ids) === $batch_size && $last_id > $cursor) {
            $next_payload = array('cursor' => $last_id);
            $continued = (bool) self::enqueue_unique('image_backfill_seed', $next_payload, 40, 'media')
                || self::unique_job_exists('image_backfill_seed', $next_payload, 'media');
            if (!$continued) {
                return new WP_Error('ucp_image_backfill_continue_failed', __('De volgende afbeeldingsbackfillbatch kon niet veilig worden ingepland.', 'ultracache-pro'));
            }
        }

        return array(
            'ok'        => true,
            'queued'    => $queued,
            'scanned'   => count($ids),
            'cursor'    => $last_id,
            'continued' => $continued,
            'completed' => !$continued,
        );
    }

    protected function run_attachment_job($payload, $class_name) {
        $attachment_id = isset($payload['attachment_id']) && is_scalar($payload['attachment_id'])
            ? absint($payload['attachment_id'])
            : 0;
        if ($attachment_id <= 0 || !class_exists($class_name) || !method_exists($class_name, 'run_job')) {
            return false;
        }
        return call_user_func(array($class_name, 'run_job'), $attachment_id);
    }

    protected function run_localize_remote_asset_job($payload) {
        if (!class_exists('UCP_Self_Host_Media') || !is_array($payload)) {
            return false;
        }
        $url = isset($payload['url']) && is_scalar($payload['url']) ? (string) $payload['url'] : '';
        $name = isset($payload['name']) && is_scalar($payload['name']) ? (string) $payload['name'] : '';
        if ('' === $url || '' === $name) {
            return false;
        }
        return UCP_Self_Host_Media::run_job($url, $name);
    }

    protected function run_preload_url_job($payload) {
        if (!UCP_Options::get('enable_cache') || !UCP_Options::get('enable_preload')) {
            return array('ok' => true, 'skipped' => true, 'reason' => 'preload_disabled', 'http_code' => 0);
        }
        $url = $this->local_job_url($payload);
        if (!$url || !wp_http_validate_url($url)) {
            return false;
        }
        if (class_exists('UCP_Preload')) {
            UCP_Preload::mark_preload_status($url, 'processing', 'queue_request');
        }
        $safety_reason = $this->preload_url_safety_exclusion_reason($url);
        if ('' !== $safety_reason) {
            return array('ok' => true, 'skipped' => true, 'reason' => $safety_reason, 'http_code' => 0);
        }
        $response = $this->fetch_preload_url($url);
        if (is_wp_error($response)) {
            if (class_exists('UCP_Preload')) {
                UCP_Preload::mark_preload_status($url, 'failed', $response->get_error_message());
            }
            return $response;
        }
        $handled = $this->handle_preload_response($url, $response);
        if (!empty($handled['ok']) && empty($handled['skipped'])) {
            $mobile_result = $this->maybe_fetch_mobile_preload_variant($url);
            if (is_wp_error($mobile_result)) {
                return $mobile_result;
            }
        }
        return $handled;
    }

    /**
     * Backward-compatible boolean wrapper for subclasses and integrations.
     *
     * @deprecated 11.6.22 Use preload_url_safety_exclusion_reason() when the reason is needed.
     * @param string $url Candidate preload URL.
     * @return bool
     */
    protected function preload_url_is_safety_excluded($url) {
        return '' !== $this->preload_url_safety_exclusion_reason($url);
    }

    protected function preload_url_safety_exclusion_reason($url) {
        $safety_reason = class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::bypass_reason($url) : '';
        if ('' === $safety_reason && (!class_exists('UCP_Preload') || !UCP_Preload::is_safety_excluded_url($url))) {
            return '';
        }
        $reason = $safety_reason ? $safety_reason : 'compatibility_preload_safety';
        if (class_exists('UCP_Preload')) {
            UCP_Preload::mark_preload_status($url, 'skipped', $reason);
        }
        UCP_Logger::log('info', 'jobs', 'preload_url_skipped_safety', __('Preloadtaak is overgeslagen door de centrale veiligheidslaag.', 'ultracache-pro'), array('url' => $url, 'reason' => $reason));
        return sanitize_key((string) $reason);
    }

    protected function fetch_preload_url($url, $variant = 'desktop') {
        $response = wp_remote_get($url, array(
            'timeout' => max(3, min(8, absint(apply_filters('ucp_preload_request_timeout', 6, $url, $variant, $this->force_current_run)))),
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'user-agent' => 'mobile' === $variant ? $this->mobile_preload_queue_user_agent() : 'UltraCachePro-Preload-Queue/' . UCP_VERSION,
            'sslverify' => apply_filters('https_local_ssl_verify', true),
            // Queue workers only inspect status and headers. Bound abnormal response bodies so
            // a bypassed or failing URL cannot exhaust memory before the next job runs.
            'limit_response_size' => 256 * KB_IN_BYTES,
            'headers' => class_exists('UCP_Preload') ? UCP_Preload::light_request_headers() : array(),
        ));
        $delay = min(2000, absint(apply_filters('ucp_preload_delay_ms', UCP_Options::get('preload_delay_ms', 500))));
        if ($delay > 0) {
            usleep($delay * 1000);
        }
        return $response;
    }

    /**
     * Additive mobile-variant warming for queue-based preloads. Uses a mobile User-Agent that
     * matches the same detection regex as the cache key builder. Retryable mobile failures keep
     * the existing preload job retryable instead of reporting a fully warmed cache variant.
     *
     * @param string $url
     * @return true|WP_Error
     */
    protected function maybe_fetch_mobile_preload_variant($url) {
        $enabled = UCP_Options::get('enable_cache') && UCP_Options::get('enable_preload') && UCP_Options::get('cache_mobile_separately');
        if (!apply_filters('ucp_preload_mobile_variant', $enabled)) {
            return true;
        }
        $response = $this->fetch_preload_url($url, 'mobile');
        if (is_wp_error($response)) {
            UCP_Logger::log('warning', 'jobs', 'preload_mobile_variant_failed', __('Mobiele preloadvariant is mislukt.', 'ultracache-pro'), array('url' => esc_url_raw((string) $url), 'error' => $response->get_error_message()));
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if (self::is_transient_preload_http_status($code)) {
            $retry_after = self::preload_retry_after_seconds($response);
            UCP_Logger::log('warning', 'jobs', 'preload_mobile_variant_failed', __('Mobiele preloadvariant is mislukt.', 'ultracache-pro'), array(
                'url' => esc_url_raw((string) $url),
                'http_status' => $code,
                'retry_after' => $retry_after,
            ));
            return new WP_Error('ucp_preload_mobile_retryable_http_status', 'HTTP ' . $code, array('http_code' => $code, 'retry_after' => $retry_after));
        }
        if (200 !== $code) {
            UCP_Logger::log('warning', 'jobs', 'preload_mobile_variant_failed', __('Mobiele preloadvariant is mislukt.', 'ultracache-pro'), array(
                'url' => esc_url_raw((string) $url),
                'http_status' => $code,
            ));
            return true;
        }

        UCP_Logger::log('info', 'jobs', 'preload_mobile_variant_request', __('Mobiele preloadvariant is opgevraagd.', 'ultracache-pro'), array(
            'url' => esc_url_raw((string) $url),
            'http_status' => $code,
        ));
        return true;
    }

    /**
     * @return string
     */
    protected function mobile_preload_queue_user_agent() {
        return (string) apply_filters(
            'ucp_preload_mobile_user_agent',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1 UltraCache-Mobile-Preload-Queue/' . UCP_VERSION
        );
    }

    protected static function is_transient_preload_http_status($code) {
        return in_array((int) $code, array(429, 500, 502, 503, 504), true);
    }

    protected static function preload_retry_after_seconds($response) {
        $value = trim((string) wp_remote_retrieve_header($response, 'retry-after'));
        if ('' === $value) {
            return 0;
        }
        if (ctype_digit($value)) {
            return min(DAY_IN_SECONDS, absint($value));
        }
        $timestamp = strtotime($value);
        if (false === $timestamp) {
            return 0;
        }
        return min(DAY_IN_SECONDS, max(0, $timestamp - time()));
    }

    protected function handle_preload_response($url, $response) {
        $code = (int) wp_remote_retrieve_response_code($response);
        $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        $location = (string) wp_remote_retrieve_header($response, 'location');
        if (206 === $code) {
            if (class_exists('UCP_Preload')) {
                UCP_Preload::mark_preload_status($url, 'skipped', 'partial_content', array('http_status' => $code));
            }
            return array('ok' => true, 'skipped' => true, 'reason' => 'partial_content', 'http_code' => $code);
        }
        if ($code >= 300 && $code < 400) {
            if (class_exists('UCP_Preload')) {
                UCP_Preload::mark_preload_status($url, 'skipped', 'redirected', array('http_status' => $code, 'location' => esc_url_raw($location)));
            }
            return array('ok' => true, 'skipped' => true, 'reason' => 'redirected', 'http_code' => $code);
        }
        if (200 === $code && ('' === $content_type || false !== strpos($content_type, 'text/html'))) {
            if (class_exists('UCP_Preload')) {
                UCP_Preload::mark_preload_status($url, 'cached', 'http_ok', array('http_status' => $code));
            }
            return array('ok' => true, 'skipped' => false, 'reason' => 'http_ok', 'http_code' => $code);
        }
        if ($code >= 200 && $code < 300 && 200 !== $code) {
            if (class_exists('UCP_Preload')) {
                UCP_Preload::mark_preload_status($url, 'skipped', 'non_cacheable_status', array('http_status' => $code));
            }
            return array('ok' => true, 'skipped' => true, 'reason' => 'non_cacheable_status', 'http_code' => $code);
        }
        if (200 === $code) {
            if (class_exists('UCP_Preload')) {
                UCP_Preload::mark_preload_status($url, 'skipped', 'unsupported_content_type', array('http_status' => $code, 'content_type' => $content_type));
            }
            return array('ok' => true, 'skipped' => true, 'reason' => 'unsupported_content_type', 'http_code' => $code);
        }
        if (self::is_transient_preload_http_status($code)) {
            $retry_after = self::preload_retry_after_seconds($response);
            $extra = array('http_status' => $code, 'error_message' => 'HTTP ' . $code);
            if ($retry_after > 0) {
                $extra['retry_after'] = $retry_after;
            }
            if (class_exists('UCP_Preload')) {
                UCP_Preload::mark_preload_status($url, 'pending', 'retry_http_' . $code, $extra);
            }
            UCP_Logger::log('warning', 'preload', 'preload_url_retryable_http_status', __('Preload is mislukt door een HTTP-foutstatus van de pagina.', 'ultracache-pro'), array('url' => esc_url_raw($url), 'http_status' => $code, 'retry_after' => $retry_after));
            return new WP_Error('ucp_preload_retryable_http_status', 'HTTP ' . $code, array('http_code' => $code, 'retry_after' => $retry_after));
        }
        if (class_exists('UCP_Preload')) {
            UCP_Preload::mark_preload_status($url, 'failed', 'http_' . $code, array('http_status' => $code, 'error_message' => 'HTTP ' . $code));
        }
        // Permanent/non-retryable HTTP responses are recorded as a completed skip so they cannot loop indefinitely.
        UCP_Logger::log('warning', 'preload', 'preload_url_failed_http_status', __('Preload is mislukt door een HTTP-foutstatus van de pagina.', 'ultracache-pro'), array('url' => esc_url_raw($url), 'http_status' => $code));
        return array('ok' => true, 'skipped' => true, 'reason' => 'http_' . $code, 'http_code' => $code);
    }
}
