<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_REST_Actions_Trait {
    protected static function action_success($message, $extra = array(), $include_status = true) {
        $payload = array(
            'success'   => true,
            'message'   => $message,
            'timestamp' => time(),
        );
        if ($include_status) {
            $payload['status'] = self::build_status();
        }
        return rest_ensure_response(array_merge($payload, $extra));
    }

    protected static function action_error($code, $message, $status = 500) {
        $clean_message = wp_strip_all_tags((string) $message);
        if (class_exists('UCP_Logger')) {
            UCP_Logger::log('error', 'rest', sanitize_key($code), $clean_message);
        }
        return new WP_Error($code, $clean_message, array('status' => $status, 'details' => array()));
    }

    public static function purge_all() {
        if (!class_exists('UCP_Cache')) {
            return self::action_error('ucp_cache_unavailable', __('Cachemodule is niet beschikbaar.', 'ultracache-pro'));
        }
        try {
            $cache = new UCP_Cache();
            $cache->purge_all();
            update_option('ucp_last_purge_at', current_time('mysql'));
            UCP_Logger::log('info', 'rest', 'cache_purged_all', 'All cache purged from REST admin action.');
            return self::action_success(__('Alle cache is geleegd.', 'ultracache-pro'), array(), false);
        } catch (Throwable $e) {
            return self::action_error('ucp_purge_failed', $e->getMessage());
        }
    }

    public static function purge_page_cache() {
        try {
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html');
            update_option('ucp_last_purge_at', current_time('mysql'));
            UCP_Logger::log('info', 'rest', 'page_cache_purged', 'Page cache purged from REST admin action.');
            return self::action_success(__('Pagina-cache is geleegd.', 'ultracache-pro'), array(), false);
        } catch (Throwable $e) {
            return self::action_error('ucp_page_cache_purge_failed', $e->getMessage());
        }
    }

    public static function purge_url($request = null) {
        try {
            $url = '';
            if ($request instanceof WP_REST_Request) {
                $url = (string) $request->get_param('url');
            }
            if ('' === $url) {
                $url = home_url('/');
            }
            $url = class_exists('UCP_Helpers') ? UCP_Helpers::enforce_local_url($url) : esc_url_raw($url);
            if ('' === $url) {
                return self::action_error('ucp_invalid_purge_url', __('Ongeldige of niet-lokale URL.', 'ultracache-pro'), 400);
            }
            if (!class_exists('UCP_Cache')) {
                return self::action_error('ucp_cache_unavailable', __('Cachemodule is niet beschikbaar.', 'ultracache-pro'));
            }

            $cache = new UCP_Cache();
            $cache->purge_url($url);
            update_option('ucp_last_purge_at', current_time('mysql'));
            UCP_Logger::log('info', 'rest', 'cache_purged_url', 'Single URL purged from REST admin action.', array('url' => esc_url_raw($url)));

            return self::action_success(__('De cache voor deze URL is geleegd.', 'ultracache-pro'), array('url' => esc_url_raw($url)), false);
        } catch (Throwable $e) {
            return self::action_error('ucp_purge_url_failed', $e->getMessage());
        }
    }

    public static function run_preload() {
        try {
            if (class_exists('UCP_Preload')) {
                $preload = new UCP_Preload();
                $queued = method_exists($preload, 'seed_preload_queue') ? $preload->seed_preload_queue() : 0;
                if (!$queued && method_exists('UCP_Preload', 'run_now')) {
                    UCP_Preload::run_now();
                }
                UCP_Logger::log('info', 'rest', 'preload_started', 'Preload started from REST admin action.', array('queued' => absint($queued)));
                return self::action_success(__('Cache opwarmen is gestart.', 'ultracache-pro'), array('queued' => absint($queued)));
            }
            return self::action_error('ucp_preload_unavailable', __('Preloadmodule is niet beschikbaar.', 'ultracache-pro'));
        } catch (Throwable $e) {
            return self::action_error('ucp_preload_failed', $e->getMessage());
        }
    }

    public static function generate_critical_css() {
        try {
            if (class_exists('UCP_Options')) {
                UCP_Options::update(array(
                    'css_delivery_mode'      => 'async',
                    'enable_critical_css'    => 1,
                    'enable_used_css'        => 0,
                    'enable_used_css_delivery' => 0,
                    'enable_css_queue'       => 1,
                    'preload_critical_images' => 1,
                    'lazyload_exclude_leading_images' => 1,
                ));
            }
            $generated = false;
            if (class_exists('UCP_CSS') && method_exists('UCP_CSS', 'generate_for_url')) {
                $generated = (bool) UCP_CSS::generate_for_url(home_url('/'), true);
            }
            if (class_exists('UCP_Cache')) {
                UCP_Cache::clear_all();
            }
            UCP_Logger::log('info', 'rest', 'critical_css_requested', 'Critical CSS generation requested from REST admin action.', array('generated' => (bool) $generated));
            return self::action_success($generated ? __('Kritieke CSS is gegenereerd en CSS-levering is bijgewerkt.', 'ultracache-pro') : __('Kritieke CSS is aangezet. De CSS-wachtrij bouwt de bestanden opnieuw op.', 'ultracache-pro'));
        } catch (Throwable $e) {
            return self::action_error('ucp_critical_css_failed', $e->getMessage());
        }
    }

    public static function generate_used_css() {
        try {
            if (class_exists('UCP_Options')) {
                UCP_Options::update(array(
                    'css_delivery_mode'        => 'remove_unused',
                    'enable_used_css'          => 1,
                    'enable_used_css_delivery' => 1,
                    'enable_critical_css'      => 0,
                    'enable_css_queue'         => 1,
                    'preload_critical_images' => 1,
                    'lazyload_exclude_leading_images' => 1,
                ));
            }
            $generated = false;
            if (class_exists('UCP_CSS') && method_exists('UCP_CSS', 'generate_for_url')) {
                $generated = (bool) UCP_CSS::generate_for_url(home_url('/'), true);
            }
            if (class_exists('UCP_Cache')) {
                UCP_Cache::clear_all();
            }
            UCP_Logger::log('info', 'rest', 'used_css_requested', 'Used CSS generation requested from REST admin action.', array('generated' => (bool) $generated));
            return self::action_success($generated ? __('Used CSS is aangezet en opnieuw gegenereerd voor de homepage.', 'ultracache-pro') : __('Used CSS is aangezet. De CSS-wachtrij bouwt de bestanden opnieuw op.', 'ultracache-pro'));
        } catch (Throwable $e) {
            return self::action_error('ucp_used_css_failed', $e->getMessage());
        }
    }

    public static function clear_minified_css() {
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'assets/minified-*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'assets/combined-*.css');
        UCP_Logger::log('info', 'rest', 'minified_css_cleared', 'Minified CSS cleared from REST admin action.');
        return self::action_success(__('Verkleinde CSS is gewist.', 'ultracache-pro'));
    }

    public static function clear_minified_js() {
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'assets/minified-*.js');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'assets/combined-*.js');
        UCP_Logger::log('info', 'rest', 'minified_js_cleared', 'Minified JavaScript cleared from REST admin action.');
        return self::action_success(__('Verkleinde JavaScript is gewist.', 'ultracache-pro'));
    }

    public static function database_cleanup() {
        try {
            if (!class_exists('UCP_DB_Cleanup')) {
                return self::action_error('ucp_database_cleanup_unavailable', __('Database cleanup is niet beschikbaar.', 'ultracache-pro'));
            }
            $cleanup = new UCP_DB_Cleanup();
            $results = $cleanup->run_cleanup('rest_admin_action');
            UCP_Logger::log('info', 'rest', 'database_cleanup_run', 'Database cleanup run from REST admin action.', array('results' => $results));
            return self::action_success(__('Database opschoning is uitgevoerd.', 'ultracache-pro'), array('results' => $results));
        } catch (Throwable $e) {
            return self::action_error('ucp_database_cleanup_failed', $e->getMessage());
        }
    }

    public static function run_health_check() {
        $checks = class_exists('UCP_Health') ? UCP_Health::run_checks() : array();
        return self::action_success(__('Health check uitgevoerd.', 'ultracache-pro'), array('checks' => $checks));
    }

    protected static function quality_suite_action($method, $missing_code) {
        if (!class_exists('UCP_Quality_Suite') || !method_exists('UCP_Quality_Suite', $method)) {
            return self::action_error($missing_code, __('Quality suite is niet beschikbaar.', 'ultracache-pro'), 404);
        }
        return call_user_func(array('UCP_Quality_Suite', $method));
    }

    public static function runtime_cache_test() {
        return self::quality_suite_action('rest_runtime_cache_test', 'ucp_runtime_cache_test_unavailable');
    }

    public static function detect_conflicts() {
        return self::quality_suite_action('rest_detect_conflicts', 'ucp_conflict_scan_unavailable');
    }

    public static function enable_debug_mode() {
        return self::quality_suite_action('rest_enable_debug_mode', 'ucp_debug_mode_unavailable');
    }

    public static function release_checklist() {
        return self::quality_suite_action('rest_release_checklist', 'ucp_release_checklist_unavailable');
    }

    public static function repair_cache_files() {
        return self::quality_suite_action('rest_repair_cache_files', 'ucp_repair_cache_files_unavailable');
    }

    public static function run_due_jobs() {
        if (!class_exists('UCP_Jobs')) {
            return self::action_error('ucp_jobs_unavailable', __('Jobrunner is niet beschikbaar.', 'ultracache-pro'), 404);
        }

        UCP_Jobs::sync_schedule();
        $runner = new UCP_Jobs();
        $processed = (int) $runner->run_queue_until_idle(true, 5);
        $failed = (int) UCP_Jobs::count_by_status('failed');

        if (0 === $processed && $failed > 0) {
            return self::action_success(
                sprintf(
                    /* translators: %d: number of failed background jobs. */
                    __('0 taken verwerkt. Er staan nog %d mislukte taken; gebruik "Mislukte taken opnieuw proberen" om ze terug in de wachtrij te zetten.', 'ultracache-pro'),
                    $failed
                ),
                array('processed' => 0, 'failed' => $failed)
            );
        }

        return self::action_success(
            sprintf(
                /* translators: %d: number of processed background jobs. */
                _n('%d taak verwerkt.', '%d taken verwerkt.', $processed, 'ultracache-pro'),
                $processed
            ),
            array('processed' => $processed, 'failed' => $failed)
        );
    }
    public static function retry_failed_jobs() {
        global $wpdb;
        if (!defined('UCP_TABLE_JOBS')) {
            return self::action_error('ucp_jobs_unavailable', __('Jobtabel is niet beschikbaar.', 'ultracache-pro'));
        }

        $cleanup = array('skipped' => 0, 'repaired' => 0);
        if (class_exists('UCP_Jobs') && method_exists('UCP_Jobs', 'cleanup_unsafe_preload_jobs')) {
            $cleanup = UCP_Jobs::cleanup_unsafe_preload_jobs();
        }

        if (!is_string(UCP_TABLE_JOBS) || '' === UCP_TABLE_JOBS || !preg_match('/^[A-Za-z0-9_]+$/', UCP_TABLE_JOBS)) {
            return self::action_error('ucp_jobs_invalid_table', __('Jobtabel is ongeldig.', 'ultracache-pro'));
        }
        $jobs_table = '`' . str_replace('`', '``', UCP_TABLE_JOBS) . '`';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- admin-triggered maintenance for validated plugin-owned job table; values are prepared.
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$jobs_table} SET status = %s, attempts = 0, available_at = %s, locked_until = NULL, claim_token = NULL, last_error = NULL, updated_at = %s WHERE status IN ('failed','retrying')",
                'pending',
                current_time('mysql', true),
                current_time('mysql', true)
            )
        );

        $processed = 0;
        if (class_exists('UCP_Jobs')) {
            UCP_Jobs::sync_schedule();
            // after a manual retry request, process multiple forced batches so CSS jobs are not hidden behind preload jobs.
            $runner = new UCP_Jobs();
            $processed = (int) $runner->run_queue_until_idle(true, 5);
        }

        UCP_Logger::log('info', 'rest', 'failed_jobs_retried', 'Failed jobs moved back to the queue from REST admin action.', array('updated' => (int) $updated, 'processed' => $processed));

        return self::action_success(
            sprintf(
                /* translators: 1: number of failed or retrying jobs moved back to retrying now, 2: number of jobs processed immediately. */
                __('%1$d taken teruggezet in de wachtrij. %2$d direct verwerkt.', 'ultracache-pro'),
                (int) $updated,
                $processed
            ),
            array('updated' => (int) $updated, 'processed' => $processed, 'cleanup' => $cleanup)
        );
    }

    public static function support_report() {
        if (!class_exists('UCP_Support_Report')) {
            return self::action_error('ucp_support_report_unavailable', __('Support report is niet beschikbaar.', 'ultracache-pro'), 404);
        }
        return rest_ensure_response(array('success' => true, 'report' => UCP_Support_Report::generate(), 'timestamp' => time()));
    }
}
