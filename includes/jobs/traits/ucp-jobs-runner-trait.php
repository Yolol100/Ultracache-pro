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
        $started_at = time();
        if (UCP_Options::get('enable_preload_queue')) {
            $limit = max(1, min(20, absint(UCP_Options::get('preload_batch_size', 5))));
            if (class_exists('UCP_Preload') && UCP_Preload::server_load_too_high()) {
                UCP_Logger::log('warning', 'jobs', 'preload_queue_paused_high_load', 'Preload wachtrij gepauzeerd door hoge serverbelasting.', array('limit' => $limit));
                return 0;
            }
        }
        /**
         * Limit the number of jobs in one runner pass. The dashboard uses this to
         * keep the manual "Verwerk taken" action responsive instead of blocking
         * the admin screen on a large preload batch.
         */
        $limit = max(1, absint(apply_filters('ucp_jobs_run_queue_limit', $limit, $force)));
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
                "SELECT id FROM " . $table_sql . " WHERE status IN (%s,%s) AND (locked_until IS NULL OR locked_until < %s) ORDER BY priority ASC, CASE WHEN type IN ('generate_css','remote_css','headless_css') THEN 0 WHEN type = 'diagnostics_snapshot' THEN 1 WHEN type = 'preload_url' THEN 2 ELSE 1 END ASC, id ASC LIMIT %d",
                'pending',
                'retrying',
                current_time('mysql', true),
                $limit
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
            $sql = $wpdb->prepare(
                "SELECT id FROM " . $table_sql . " WHERE status IN (%s,%s) AND available_at <= %s AND (locked_until IS NULL OR locked_until < %s) ORDER BY priority ASC, CASE WHEN type IN ('generate_css','remote_css','headless_css') THEN 0 WHEN type = 'diagnostics_snapshot' THEN 1 WHEN type = 'preload_url' THEN 2 ELSE 1 END ASC, id ASC LIMIT %d",
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
            ), false);
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
        update_option('ucp_jobs_empty_run_streak', 0, false);
        update_option('ucp_jobs_last_run_summary', array(
            'started_at' => gmdate('Y-m-d H:i:s', $started_at),
            'ended_at' => gmdate('Y-m-d H:i:s'),
            'duration' => max(0, time() - $started_at),
            'processed' => $processed,
            'batch_size' => $limit,
            'empty_streak' => 0,
            'forced' => (bool) $force,
        ), false);
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
                return $this->run_generate_css_job($payload);
            case 'remote_css':
                return $this->run_remote_css_job($payload);
            case 'headless_css':
                return $this->run_headless_css_job($payload);
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
            case 'diagnostics_snapshot':
                UCP_Health::run_checks();
                return true;
            default:
                return false;
        }
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
            $message = !empty($status['message']) ? $status['message'] : 'CSS-opbouw mislukt zonder specifieke foutmelding.';
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

    protected function run_attachment_job($payload, $class_name) {
        $attachment_id = isset($payload['attachment_id']) ? (int) $payload['attachment_id'] : 0;
        if ($attachment_id <= 0 || !class_exists($class_name) || !method_exists($class_name, 'run_job')) {
            return false;
        }
        return call_user_func(array($class_name, 'run_job'), $attachment_id);
    }

    protected function run_localize_remote_asset_job($payload) {
        if (!class_exists('UCP_Self_Host_Media')) {
            return false;
        }
        return UCP_Self_Host_Media::run_job(
            isset($payload['url']) ? (string) $payload['url'] : '',
            isset($payload['name']) ? (string) $payload['name'] : ''
        );
    }

    protected function run_preload_url_job($payload) {
        $url = $this->local_job_url($payload);
        if (!$url || !wp_http_validate_url($url)) {
            return false;
        }
        if (class_exists('UCP_Preload')) {
            UCP_Preload::mark_preload_status($url, 'processing', 'queue_request');
        }
        if ($this->preload_url_is_safety_excluded($url)) {
            return true;
        }
        $response = $this->fetch_preload_url($url);
        if (is_wp_error($response)) {
            if (class_exists('UCP_Preload')) {
                UCP_Preload::mark_preload_status($url, 'failed', $response->get_error_message());
            }
            return $response;
        }
        $handled = $this->handle_preload_response($url, $response);
        $this->maybe_fetch_mobile_preload_variant($url);
        return $handled;
    }

    protected function preload_url_is_safety_excluded($url) {
        $safety_reason = class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::bypass_reason($url) : '';
        if ('' === $safety_reason && (!class_exists('UCP_Preload') || !UCP_Preload::is_safety_excluded_url($url))) {
            return false;
        }
        $reason = $safety_reason ? $safety_reason : 'compatibility_preload_safety';
        if (class_exists('UCP_Preload')) {
            UCP_Preload::mark_preload_status($url, 'skipped', $reason);
        }
        UCP_Logger::log('info', 'jobs', 'preload_url_skipped_safety', 'Preload job overgeslagen door centrale safety layer.', array('url' => $url, 'reason' => $reason));
        return true;
    }

    protected function fetch_preload_url($url, $variant = 'desktop') {
        $response = wp_remote_get($url, array(
            'timeout' => max(3, min(8, absint(apply_filters('ucp_preload_request_timeout', 6, $url, $variant, $this->force_current_run)))),
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'user-agent' => 'mobile' === $variant ? $this->mobile_preload_queue_user_agent() : 'UltraCachePro-Preload-Queue/' . UCP_VERSION,
            'sslverify' => apply_filters('https_local_ssl_verify', true),
            'headers' => UCP_Options::get('enable_light_preload_requests') ? array('Range' => 'bytes=0-0') : array(),
        ));
        $delay = min(2000, absint(apply_filters('ucp_preload_delay_ms', UCP_Options::get('preload_delay_ms', 500))));
        if ($delay > 0) {
            usleep($delay * 1000);
        }
        return $response;
    }

    /**
     * Additive mobile-variant warming for queue-based preloads. Uses a mobile User-Agent that
     * matches the same detection regex as the cache key builder, without changing the normal job
     * status for the desktop request.
     *
     * @param string $url
     * @return void
     */
    protected function maybe_fetch_mobile_preload_variant($url) {
        $enabled = UCP_Options::get('enable_cache') && UCP_Options::get('enable_preload') && UCP_Options::get('cache_mobile_separately');
        if (!apply_filters('ucp_preload_mobile_variant', $enabled)) {
            return;
        }
        $response = $this->fetch_preload_url($url, 'mobile');
        if (is_wp_error($response)) {
            UCP_Logger::log('warning', 'jobs', 'preload_mobile_variant_failed', 'Mobiele preload-variant mislukt.', array('url' => esc_url_raw((string) $url), 'error' => $response->get_error_message()));
            return;
        }
        UCP_Logger::log('info', 'jobs', 'preload_mobile_variant_request', 'Mobiele preload-variant opgevraagd.', array(
            'url' => esc_url_raw((string) $url),
            'http_status' => (int) wp_remote_retrieve_response_code($response),
        ));
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

    protected function handle_preload_response($url, $response) {
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
            UCP_Preload::mark_preload_status($url, 'failed', 'http_' . $code, array('http_status' => $code, 'error_message' => 'HTTP ' . $code));
        }
        // Note: een HTTP-foutstatus wordt als mislukte URL gelogd, maar hoeft niet eindeloos opnieuw in de wachtrij te blijven.
        UCP_Logger::log('warning', 'preload', 'preload_url_failed_http_status', 'Preload mislukt door HTTP-foutstatus van de pagina.', array('url' => esc_url_raw($url), 'http_status' => $code));
        return true;
    }
}
