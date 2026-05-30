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

        $limit = max(1, absint(UCP_Options::get('job_batch_size', 5)));
        if (UCP_Options::get('enable_preload_queue')) {
            $limit = max(1, absint(UCP_Options::get('preload_batch_size', 15)));
            if (class_exists('UCP_Preload') && UCP_Preload::server_load_too_high()) {
                UCP_Logger::log('warning', 'jobs', 'preload_queue_paused_high_load', 'Preload wachtrij gepauzeerd door hoge serverbelasting.', array('limit' => $limit));
                return 0;
            }
        }
        $token = wp_generate_password(16, false);
        $ttl = max(60, absint(UCP_Options::get('job_lock_ttl', 300)));
        $previous_force_current_run = $this->force_current_run;
        $this->force_current_run = (bool) $force;
        $runner_token = self::acquire_runner_lock($ttl);
        if (!$runner_token) {
            $this->force_current_run = $previous_force_current_run;
            return 0;
        }
        $lock_until = gmdate('Y-m-d H:i:s', time() + $ttl);
        self::rescue_stale_running_jobs();

        if ($force) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
            $sql = $wpdb->prepare(
                "SELECT id FROM " . $table_sql . " WHERE status IN (%s,%s) AND (locked_until IS NULL OR locked_until < %s) ORDER BY priority ASC, CASE WHEN type IN ('generate_css','remote_css') THEN 0 WHEN type = 'diagnostics_snapshot' THEN 1 WHEN type = 'preload_url' THEN 2 ELSE 1 END ASC, id ASC LIMIT %d",
                'pending',
                'retrying',
                current_time('mysql', true),
                $limit
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
            $sql = $wpdb->prepare(
                "SELECT id FROM " . $table_sql . " WHERE status IN (%s,%s) AND available_at <= %s AND (locked_until IS NULL OR locked_until < %s) ORDER BY priority ASC, CASE WHEN type IN ('generate_css','remote_css') THEN 0 WHEN type = 'diagnostics_snapshot' THEN 1 WHEN type = 'preload_url' THEN 2 ELSE 1 END ASC, id ASC LIMIT %d",
                'pending',
                'retrying',
                current_time('mysql', true),
                current_time('mysql', true),
                $limit
            );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $job_ids = $wpdb->get_col($sql);
        if (empty($job_ids)) {
            self::release_runner_lock($runner_token);
            $this->force_current_run = $previous_force_current_run;
            return 0;
        }
        $processed = 0;

        try {
            foreach ($job_ids as $job_id) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
                $wpdb->update(
                    $table,
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
                $job = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $table_sql . ' WHERE id = %d', $job_id), ARRAY_A);
                if (!$job) {
                    continue;
                }
                $this->process_job($job);
                $processed++;
            }
        } finally {
            self::release_runner_lock($runner_token);
            $this->force_current_run = $previous_force_current_run;
        }
        if (self::has_due_jobs(true)) {
            self::sync_schedule();
        }
        return $processed;
    }

    protected function process_job($job) {
        global $wpdb;
        $table = self::jobs_table_name();
        if ('' === $table) {
            return;
        }

        $payload = json_decode((string) $job['payload'], true);
        $payload = is_array($payload) ? $payload : array();
        $last_error = '';
        try {
            $success = $this->run_job($job['type'], $payload);
            if (is_wp_error($success)) {
                $last_error = $success->get_error_message();
                $success = false;
            }
        } catch (Throwable $e) {
            $success = false;
            $last_error = $e->getMessage();
        }
        $attempts = (int) $job['attempts'] + 1;
        $max_attempts = max(1, (int) $job['max_attempts']);

        if ($success) {
            $wpdb->update(
                $table,
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
            UCP_Logger::log('info', 'jobs', 'job_success', 'Taak klaar.', array('type' => $job['type'], 'job_id' => $job['id']));
            return;
        }

        $next_status = $attempts >= $max_attempts ? 'failed' : 'retrying';
        $delay = min(3600, max(60, pow(2, $attempts) * 60));
        $update_data = array(
            'status' => $next_status,
            'attempts' => $attempts,
            'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
            'updated_at' => current_time('mysql', true),
            'last_error' => '' !== $last_error ? $last_error : 'Taak gaf geen goed resultaat terug.',
            'result' => wp_json_encode(array('ok' => false, 'final' => 'failed' === $next_status, 'error' => '' !== $last_error ? $last_error : 'Taak gaf geen goed resultaat terug.')),
            'locked_until' => null,
        );
        $update_format = array('%s', '%d', '%s', '%s', '%s', '%s', '%s');
        if ('failed' === $next_status) {
            $update_data['job_signature'] = null;
            $update_format[] = '%s';
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $wpdb->update(
            $table,
            $update_data,
            array('id' => $job['id']),
            $update_format,
            array('%d')
        );
        UCP_Logger::log('warning', 'jobs', 'job_retry_scheduled', 'Job rescheduled.', array('type' => $job['type'], 'job_id' => $job['id'], 'status' => $next_status));
    }

    protected function run_job($type, $payload) {
        switch ($type) {
            case 'generate_css':
                $url = UCP_Helpers::strict_local_url(isset($payload['url']) ? $payload['url'] : home_url('/'), home_url('/'));
                if (!$url) {
                    return false;
                }
                $ok = UCP_CSS::generate_for_url($url, !empty($payload['force']) || $this->force_current_run);
                if (!$ok) {
                    $status = UCP_CSS::artifact_status($url);
                    $message = !empty($status['message']) ? $status['message'] : 'CSS-opbouw mislukt zonder specifieke foutmelding.';
                    return new WP_Error('ucp_generate_css_failed', $message);
                }
                return $ok;
            case 'remote_css':
                $url = UCP_Helpers::strict_local_url(isset($payload['url']) ? $payload['url'] : home_url('/'), home_url('/'));
                return $url ? UCP_Cloud::request_remote_css($url) : false;
            case 'cloud_sync':
                return UCP_Cloud::push_site_payload();
            case 'cloudflare_purge_url':
                return UCP_Edge::cloudflare_purge_url(isset($payload['url']) ? $payload['url'] : home_url('/'));
            case 'cloudflare_purge_all':
                return UCP_Edge::cloudflare_purge_all();
            case 'cloudflare_purge_urls':
                return UCP_Edge::cloudflare_purge_urls(isset($payload['urls']) ? $payload['urls'] : array());
            case 'preload_url':
                $url = UCP_Helpers::strict_local_url(isset($payload['url']) ? $payload['url'] : home_url('/'), home_url('/'));
                if (!$url || !wp_http_validate_url($url)) {
                    return false;
                }
                if (class_exists('UCP_Preload')) {
                    UCP_Preload::mark_preload_status($url, 'processing', 'queue_request');
                }
                $safety_reason = class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::bypass_reason($url) : '';
                if ('' !== $safety_reason || (class_exists('UCP_Preload') && UCP_Preload::is_safety_excluded_url($url))) {
                    if (class_exists('UCP_Preload')) {
                        UCP_Preload::mark_preload_status($url, 'skipped', $safety_reason ? $safety_reason : 'compatibility_preload_safety');
                    }
                    UCP_Logger::log('info', 'jobs', 'preload_url_skipped_safety', 'Preload job overgeslagen door centrale safety layer.', array('url' => $url, 'reason' => $safety_reason ? $safety_reason : 'compatibility_preload_safety'));
                    return true;
                }
                $response = wp_remote_get($url, array(
                    'timeout' => 20,
                    'redirection' => 0,
                    'reject_unsafe_urls' => true,
                    'user-agent' => 'UltraCache Preload Queue/' . UCP_VERSION,
                    'sslverify' => apply_filters('https_local_ssl_verify', true),
                    'headers' => UCP_Options::get('enable_light_preload_requests') ? array('Range' => 'bytes=0-0') : array(),
                ));
                $delay = min(2000, absint(apply_filters('ucp_preload_delay_ms', UCP_Options::get('preload_delay_ms', 500))));
                if ($delay > 0) {
                    usleep($delay * 1000);
                }
                if (is_wp_error($response)) {
                    if (class_exists('UCP_Preload')) {
                        UCP_Preload::mark_preload_status($url, 'failed', $response->get_error_message());
                    }
                    return $response;
                }
                $code = (int) wp_remote_retrieve_response_code($response);
                $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
                $location = (string) wp_remote_retrieve_header($response, 'location');
                if ($code >= 300 && $code < 400) {
                    if (class_exists('UCP_Preload')) {
                        UCP_Preload::mark_preload_status($url, 'skipped', 'redirected', array('http_status' => $code, 'location' => esc_url_raw($location)));
                    }
                    return true;
                }
                if ($code >= 200 && $code < 300 && ('' === $content_type || false !== strpos($content_type, 'text/html'))) {
                    if (class_exists('UCP_Preload')) {
                        UCP_Preload::mark_preload_status($url, 'cached', 'http_ok', array('http_status' => $code));
                    }
                    return true;
                }
                if ($code >= 200 && $code < 300) {
                    if (class_exists('UCP_Preload')) {
                        UCP_Preload::mark_preload_status($url, 'skipped', 'unsupported_content_type', array('http_status' => $code, 'content_type' => $content_type));
                    }
                    return true;
                }
                if (class_exists('UCP_Preload')) {
                    UCP_Preload::mark_preload_status($url, 'skipped', 'http_' . $code, array('http_status' => $code));
                }
                // Note: een preload mag de wachtrij niet blijven vervuilen wanneer de pagina zelf een HTTP-foutstatus teruggeeft.
                UCP_Logger::log('warning', 'preload', 'preload_url_skipped_http_status', 'Preload overgeslagen door HTTP-foutstatus van de pagina.', array('url' => esc_url_raw($url), 'http_status' => $code));
                return true;
            case 'diagnostics_snapshot':
                UCP_Health::run_checks();
                return true;
            default:
                return false;
        }
    }
}
