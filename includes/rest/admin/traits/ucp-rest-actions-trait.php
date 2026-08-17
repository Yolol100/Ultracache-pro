<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic table identifiers are validated with UCP_Helpers::is_safe_table_name() and quoted before use; values remain prepared.
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
        $extra = is_array($extra) ? $extra : array();
        unset($extra['success'], $extra['message'], $extra['timestamp']);
        if ($include_status) {
            unset($extra['status']);
        }
        return rest_ensure_response(array_merge($extra, $payload));
    }

    protected static function action_error($code, $message, $status = 500) {
        $code = sanitize_key((string) $code);
        if ('' === $code) {
            $code = 'ucp_action_error';
        }
        $clean_message = sanitize_text_field(wp_strip_all_tags((string) $message));
        if ('' === $clean_message) {
            $clean_message = __('De actie kon niet worden afgerond.', 'ultracache-pro');
        }
        $status = absint($status);
        if ($status < 400 || $status > 599) {
            $status = 500;
        }
        if (class_exists('UCP_Logger')) {
            UCP_Logger::log('error', 'rest', $code, $clean_message);
        }
        return new WP_Error($code, $clean_message, array('status' => $status, 'details' => array()));
    }

    protected static function action_exception_error($code, $message, $exception, $status = 500) {
        if (class_exists('UCP_Logger')) {
            UCP_Logger::log('error', 'rest', sanitize_key($code), __('REST-actie is mislukt door een onverwachte uitzondering.', 'ultracache-pro'), array(
                'exception' => $exception instanceof Throwable ? get_class($exception) : '',
                'exception_code' => $exception instanceof Throwable ? (string) $exception->getCode() : '',
            ));
        }
        return self::action_error($code, $message, $status);
    }

    public static function purge_all() {
        if (!class_exists('UCP_Cache')) {
            return self::action_error('ucp_cache_unavailable', __('Cachemodule is niet beschikbaar.', 'ultracache-pro'));
        }
        try {
            $cache = UCP_Helpers::new_without_constructor('UCP_Cache');
            $cache->purge_all();
            update_option('ucp_last_purge_at', current_time('mysql'), false);
            UCP_Logger::log('info', 'rest', 'cache_purged_all', __('Alle cache is geleegd via een REST-beheeractie.', 'ultracache-pro'));
            return self::action_success(__('Alle cache is geleegd.', 'ultracache-pro'), array(), false);
        } catch (Throwable $e) {
            return self::action_exception_error('ucp_purge_failed', __('De cache kon niet volledig worden geleegd.', 'ultracache-pro'), $e);
        }
    }

    public static function purge_page_cache() {
        try {
            if (!class_exists('UCP_Cache') || !method_exists('UCP_Cache', 'clear_page_cache_files')) {
                return self::action_error('ucp_cache_unavailable', __('Cachemodule is niet beschikbaar.', 'ultracache-pro'));
            }
            UCP_Cache::clear_page_cache_files();
            update_option('ucp_last_purge_at', current_time('mysql'), false);
            UCP_Logger::log('info', 'rest', 'page_cache_purged', __('Paginacache is geleegd via een REST-beheeractie.', 'ultracache-pro'));
            return self::action_success(__('Pagina-cache is geleegd.', 'ultracache-pro'), array(), false);
        } catch (Throwable $e) {
            return self::action_exception_error('ucp_page_cache_purge_failed', __('De pagina-cache kon niet volledig worden geleegd.', 'ultracache-pro'), $e);
        }
    }

    public static function purge_url($request = null) {
        try {
            $url = '';
            if ($request instanceof WP_REST_Request) {
                $url_value = $request->get_param('url');
                if (null !== $url_value && !is_scalar($url_value)) {
                    return self::action_error('ucp_invalid_purge_url', __('Ongeldige of niet-lokale URL.', 'ultracache-pro'), 400);
                }
                $url = is_scalar($url_value) ? (string) $url_value : '';
            }
            if ('' === $url) {
                $url = home_url('/');
            }
            $url = UCP_Helpers::strict_local_url($url);
            if ('' === $url) {
                return self::action_error('ucp_invalid_purge_url', __('Ongeldige of niet-lokale URL.', 'ultracache-pro'), 400);
            }
            if (!class_exists('UCP_Cache')) {
                return self::action_error('ucp_cache_unavailable', __('Cachemodule is niet beschikbaar.', 'ultracache-pro'));
            }

            $cache = UCP_Helpers::new_without_constructor('UCP_Cache');
            $cache->purge_url($url);
            update_option('ucp_last_purge_at', current_time('mysql'), false);
            $preload_queued = false;
            if (UCP_Options::get('enable_preload') && UCP_Options::get('enable_preload_queue') && class_exists('UCP_Jobs') && (!class_exists('UCP_Preload') || !UCP_Preload::is_safety_excluded_url($url))) {
                $payload = array('url' => $url);
                $preload_queued = (bool) UCP_Jobs::enqueue_unique('preload_url', $payload, 5, 'preload');
                if (!$preload_queued && method_exists('UCP_Jobs', 'unique_job_exists')) {
                    $preload_queued = (bool) UCP_Jobs::unique_job_exists('preload_url', $payload, 'preload');
                }
            }
            UCP_Logger::log('info', 'rest', 'cache_purged_url', __('Eén URL is geleegd via een REST-beheeractie.', 'ultracache-pro'), array('url' => esc_url_raw($url), 'preload_queued' => $preload_queued ? 1 : 0));

            return self::action_success(__('De cache voor deze URL is geleegd.', 'ultracache-pro'), array('url' => esc_url_raw($url), 'preload_queued' => $preload_queued), false);
        } catch (Throwable $e) {
            return self::action_exception_error('ucp_purge_url_failed', __('De cache voor deze URL kon niet worden geleegd.', 'ultracache-pro'), $e);
        }
    }

    public static function run_preload() {
        try {
            if (class_exists('UCP_Preload')) {
                $queued = 0;
                if (UCP_Options::get('enable_preload_queue') && class_exists('UCP_Jobs')) {
                    $preload = UCP_Helpers::new_without_constructor('UCP_Preload');
                    $queued = method_exists($preload, 'seed_preload_queue') ? $preload->seed_preload_queue() : 0;
                } elseif (method_exists('UCP_Preload', 'run_now')) {
                    UCP_Preload::run_now();
                }
                UCP_Logger::log('info', 'rest', 'preload_started', __('Preload is gestart via een REST-beheeractie.', 'ultracache-pro'), array('queued' => absint($queued), 'mode' => UCP_Options::get('enable_preload_queue') ? 'queue' : 'direct'));
                return self::action_success(__('Cache opwarmen is gestart.', 'ultracache-pro'), array('queued' => absint($queued)));
            }
            return self::action_error('ucp_preload_unavailable', __('Preloadmodule is niet beschikbaar.', 'ultracache-pro'));
        } catch (Throwable $e) {
            return self::action_exception_error('ucp_preload_failed', __('Cache opwarmen kon niet worden gestart.', 'ultracache-pro'), $e);
        }
    }

    public static function generate_critical_css() {
        try {
            if (class_exists('UCP_Options') && !UCP_Options::update(array(
                'css_delivery_mode'      => 'async',
                'enable_critical_css'    => 1,
                'enable_used_css'        => 0,
                'enable_used_css_delivery' => 0,
                'enable_css_queue'       => 1,
                'preload_critical_images' => 1,
                'lazyload_exclude_leading_images' => 1,
            ))) {
                return self::action_error('ucp_critical_css_settings_failed', __('De CSS-instellingen konden niet blijvend worden opgeslagen.', 'ultracache-pro'));
            }
            if (class_exists('UCP_Cache')) {
                UCP_Cache::clear_all();
            }
            do_action('ucp_operation_heartbeat');
            $generated = false;
            if (class_exists('UCP_CSS') && method_exists('UCP_CSS', 'generate_for_url')) {
                $generated = (bool) UCP_CSS::generate_for_url(home_url('/'), true);
            }
            do_action('ucp_operation_heartbeat');
            UCP_Logger::log('info', 'rest', 'critical_css_requested', __('Genereren van kritieke CSS is aangevraagd via een REST-beheeractie.', 'ultracache-pro'), array('generated' => (bool) $generated));
            return self::action_success($generated ? __('Kritieke CSS is gegenereerd en CSS-levering is bijgewerkt.', 'ultracache-pro') : __('Kritieke CSS is aangezet. De CSS-wachtrij bouwt de bestanden opnieuw op.', 'ultracache-pro'));
        } catch (Throwable $e) {
            return self::action_exception_error('ucp_critical_css_failed', __('Kritieke CSS kon niet worden gegenereerd.', 'ultracache-pro'), $e);
        }
    }

    public static function generate_used_css() {
        try {
            if (class_exists('UCP_Options') && !UCP_Options::update(array(
                'css_delivery_mode'        => 'remove_unused',
                'enable_used_css'          => 1,
                'enable_used_css_delivery' => 1,
                'enable_critical_css'      => 0,
                'enable_css_queue'         => 1,
                'preload_critical_images' => 1,
                'lazyload_exclude_leading_images' => 1,
            ))) {
                return self::action_error('ucp_used_css_settings_failed', __('De CSS-instellingen konden niet blijvend worden opgeslagen.', 'ultracache-pro'));
            }
            if (class_exists('UCP_Cache')) {
                UCP_Cache::clear_all();
            }
            do_action('ucp_operation_heartbeat');
            $generated = false;
            if (class_exists('UCP_CSS') && method_exists('UCP_CSS', 'generate_for_url')) {
                $generated = (bool) UCP_CSS::generate_for_url(home_url('/'), true);
            }
            do_action('ucp_operation_heartbeat');
            UCP_Logger::log('info', 'rest', 'used_css_requested', __('Genereren van gebruikte CSS is aangevraagd via een REST-beheeractie.', 'ultracache-pro'), array('generated' => (bool) $generated));
            return self::action_success($generated ? __('Used CSS is aangezet en opnieuw gegenereerd voor de homepage.', 'ultracache-pro') : __('Used CSS is aangezet. De CSS-wachtrij bouwt de bestanden opnieuw op.', 'ultracache-pro'));
        } catch (Throwable $e) {
            return self::action_exception_error('ucp_used_css_failed', __('Used CSS kon niet worden gegenereerd.', 'ultracache-pro'), $e);
        }
    }

    protected static function clear_css_delivery_artifacts() {
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'used-css/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'used-css-served/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'critical-css/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'css/status-*.json');
    }

    protected static function clear_minified_css_artifacts() {
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'assets/minified-*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'assets/combined-*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'min/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'min/*.css.skip');
    }

    public static function clear_used_css() {
        try {
            self::clear_css_delivery_artifacts();
            UCP_Logger::log('info', 'rest', 'used_css_artifacts_cleared', __('Gebruikte CSS- en kritieke-CSS-bestanden zijn gewist via een REST-beheeractie.', 'ultracache-pro'));
            return self::action_success(__('Gebruikte CSS en kritieke CSS zijn gewist.', 'ultracache-pro'));
        } catch (Throwable $e) {
            return self::action_exception_error('ucp_used_css_clear_failed', __('Used CSS kon niet volledig worden gewist.', 'ultracache-pro'), $e);
        }
    }

    public static function clear_minified_css() {
        self::clear_minified_css_artifacts();
        UCP_Logger::log('info', 'rest', 'minified_css_cleared', __('Verkleinde CSS is gewist via een REST-beheeractie.', 'ultracache-pro'));
        return self::action_success(__('Verkleinde CSS is gewist.', 'ultracache-pro'));
    }

    public static function refresh_css() {
        try {
            self::clear_minified_css_artifacts();
            self::clear_css_delivery_artifacts();
            do_action('ucp_operation_heartbeat');

            if (class_exists('UCP_Cache') && method_exists('UCP_Cache', 'clear_page_cache_files')) {
                UCP_Cache::clear_page_cache_files();
            }

            $mode = class_exists('UCP_Options') ? (string) UCP_Options::get('css_delivery_mode', 'none') : 'none';
            $advanced_css_enabled = 'none' !== $mode
                || (class_exists('UCP_Options') && (
                    UCP_Options::get('enable_used_css')
                    || UCP_Options::get('enable_used_css_delivery')
                    || UCP_Options::get('enable_critical_css')
                    || UCP_Options::get('enable_local_critical_css')
                ));
            $generated = false;

            if ($advanced_css_enabled && class_exists('UCP_CSS') && method_exists('UCP_CSS', 'generate_for_url')) {
                $generated = (bool) UCP_CSS::generate_for_url(home_url('/'), true);
            }
            do_action('ucp_operation_heartbeat');

            UCP_Logger::log('info', 'rest', 'css_refreshed', __('CSS-bestanden zijn vernieuwd zonder de ingestelde leveringsmodus te wijzigen.', 'ultracache-pro'), array(
                'mode' => sanitize_key($mode),
                'advanced_css_enabled' => $advanced_css_enabled ? 1 : 0,
                'generated' => $generated ? 1 : 0,
            ));

            if (!$advanced_css_enabled) {
                $message = __('CSS-cache is gewist. Verkleinde CSS wordt bij volgende aanvragen opnieuw opgebouwd.', 'ultracache-pro');
            } elseif ($generated) {
                $message = __('CSS-bestanden zijn vernieuwd met behoud van de gekozen leveringsmethode.', 'ultracache-pro');
            } else {
                $message = __('CSS-bestanden zijn gewist. De actieve CSS-opbouw kan op de achtergrond verdergaan.', 'ultracache-pro');
            }

            return self::action_success($message, array(
                'mode' => $mode,
                'generated' => $generated,
            ));
        } catch (Throwable $e) {
            return self::action_exception_error('ucp_css_refresh_failed', __('CSS-bestanden konden niet volledig worden vernieuwd.', 'ultracache-pro'), $e);
        }
    }

    public static function clear_minified_js() {
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'assets/minified-*.js');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'assets/combined-*.js');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'js/combined-*.js');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'min/*.js');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'min/*.js.skip');
        UCP_Logger::log('info', 'rest', 'minified_js_cleared', __('Verkleinde JavaScript is gewist via een REST-beheeractie.', 'ultracache-pro'));
        return self::action_success(__('Verkleinde JavaScript is gewist.', 'ultracache-pro'));
    }

    public static function clear_priority_elements() {
        try {
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'meta/*.json');
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'css/status-*.json');
            delete_option('ucp_vpi_map');
            UCP_Logger::log('info', 'rest', 'priority_elements_cleared', __('Metadata van prioriteitselementen is gewist via de beheerbalk.', 'ultracache-pro'));
            return self::action_success(__('Priority elements zijn geleegd.', 'ultracache-pro'), array(), false);
        } catch (Throwable $e) {
            return self::action_exception_error('ucp_priority_elements_clear_failed', __('Priority-elementgegevens konden niet volledig worden gewist.', 'ultracache-pro'), $e);
        }
    }

    public static function database_cleanup($request = null) {
        try {
            if ($request instanceof WP_REST_Request) {
                $confirmed_backup = self::is_explicit_confirmation($request->get_param('confirmBackup')) || self::is_explicit_confirmation($request->get_param('confirmed_backup')) || self::is_explicit_confirmation($request->get_param('ucp_confirm_backup'));
                $confirmed_irreversible = self::is_explicit_confirmation($request->get_param('confirmIrreversible')) || self::is_explicit_confirmation($request->get_param('confirmed_irreversible')) || self::is_explicit_confirmation($request->get_param('ucp_confirm_irreversible'));
                if (!$confirmed_backup) {
                    return self::action_error('ucp_database_cleanup_backup_not_confirmed', __('Bevestig eerst dat er een recente database-back-up is voordat database cleanup wordt uitgevoerd.', 'ultracache-pro'), 400);
                }
                if (!$confirmed_irreversible) {
                    return self::action_error('ucp_database_cleanup_irreversible_not_confirmed', __('Bevestig eerst dat je begrijpt dat database cleanup niet kan worden teruggedraaid.', 'ultracache-pro'), 400);
                }
            }
            if (!class_exists('UCP_DB_Cleanup')) {
                return self::action_error('ucp_database_cleanup_unavailable', __('Database cleanup is niet beschikbaar.', 'ultracache-pro'));
            }
            if (empty(UCP_DB_Cleanup::selected_operations())) {
                return self::action_error('ucp_database_cleanup_nothing_selected', __('Selecteer eerst minimaal één database-opschoonactie.', 'ultracache-pro'), 400);
            }
            $cleanup = new UCP_DB_Cleanup();
            $results = $cleanup->run_cleanup('rest_admin_action');
            if (!empty($results['skipped']) && 'already_running' === $results['skipped']) {
                return self::action_error('ucp_database_cleanup_already_running', __('Er draait al een database-opschoning. Wacht tot die is afgerond.', 'ultracache-pro'), 409);
            }
            if (!empty($results['skipped'])) {
                return self::action_error('ucp_database_cleanup_skipped', __('Database-opschoning is niet uitgevoerd.', 'ultracache-pro'), 400);
            }
            $failed = false;
            foreach ($results as $key => $value) {
                if ((substr((string) $key, -7) === '_failed' && !empty($value)) || (false !== strpos((string) $key, '_optimize_failed') && !empty($value))) {
                    $failed = true;
                    break;
                }
            }
            if ($failed) {
                UCP_Logger::log('error', 'rest', 'database_cleanup_partial_failure', __('Database-opschoning is voltooid met een of meer mislukte bewerkingen.', 'ultracache-pro'), array('results' => $results));
                return self::action_error('ucp_database_cleanup_partial_failure', __('Database-opschoning is gedeeltelijk mislukt. Bekijk de technische details en serverlog.', 'ultracache-pro'));
            }
            UCP_Logger::log('info', 'rest', 'database_cleanup_run', __('Database-opschoning is uitgevoerd via een REST-beheeractie.', 'ultracache-pro'), array('results' => $results));
            return self::action_success(__('Database opschoning is uitgevoerd.', 'ultracache-pro'), array('results' => $results));
        } catch (Throwable $e) {
            return self::action_exception_error('ucp_database_cleanup_failed', __('Database-opschoning kon niet worden afgerond.', 'ultracache-pro'), $e);
        }
    }

    public static function renderer_test($request = null) {
        if (!class_exists('UCP_Render_Bridge')) {
            return self::action_error('ucp_renderer_unavailable', __('Headless renderer bridge is niet beschikbaar.', 'ultracache-pro'), 404);
        }
        $url = home_url('/');
        if ($request instanceof WP_REST_Request) {
            $url_value = $request->get_param('url');
            if (null !== $url_value && !is_scalar($url_value)) {
                return self::action_error('ucp_renderer_url_invalid', __('Geef een geldige URL op voor de renderer-test.', 'ultracache-pro'), 400);
            }
            $candidate = is_scalar($url_value) ? (string) $url_value : '';
            if ('' !== $candidate) {
                $url = $candidate;
            }
        }
        do_action('ucp_operation_heartbeat');
        $result = UCP_Render_Bridge::test_endpoint($url);
        do_action('ucp_operation_heartbeat');
        if (is_wp_error($result)) {
            return self::action_error('ucp_renderer_test_failed', $result->get_error_message(), 400);
        }
        return self::action_success(__('Headless renderer test geslaagd.', 'ultracache-pro'), array('renderer' => $result));
    }

    public static function auto_configure_object_cache($request = null) {
        $confirmed = $request instanceof WP_REST_Request && self::is_explicit_confirmation($request->get_param('confirmed'));
        if (!$confirmed) {
            return self::action_error('ucp_object_cache_confirmation_required', __('Bevestig eerst dat UltraCache de veilige object-cachebackend mag instellen.', 'ultracache-pro'), 400);
        }
        if (!class_exists('UCP_Object_Cache')) {
            return self::action_error('ucp_object_cache_unavailable', __('Object-cacheconfiguratie is niet beschikbaar.', 'ultracache-pro'), 404);
        }
        $result = UCP_Object_Cache::configure_automatically();
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $error_status = is_array($error_data) && isset($error_data['status']) ? (int) $error_data['status'] : 400;
            return self::action_error($result->get_error_code(), $result->get_error_message(), $error_status);
        }
        return self::action_success($result['message'], array(
            'backend' => $result['backend'],
            'changed' => !empty($result['changed']),
            'objectCache' => $result['status'],
        ));
    }

    public static function refresh_object_cache_status() {
        if (!class_exists('UCP_Object_Cache')) {
            return self::action_error('ucp_object_cache_unavailable', __('Object-cachestatus is niet beschikbaar.', 'ultracache-pro'), 404);
        }
        UCP_Object_Cache::invalidate_probe_cache();
        $status = UCP_Object_Cache::status(true);
        return self::action_success(__('Object-cachestatus opnieuw gecontroleerd.', 'ultracache-pro'), array('objectCache' => $status));
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

    public static function website_check() {
        return self::quality_suite_action('rest_website_check', 'ucp_website_check_unavailable');
    }

    public static function detect_conflicts() {
        return self::quality_suite_action('rest_detect_conflicts', 'ucp_conflict_scan_unavailable');
    }

    public static function enable_debug_mode() {
        return self::quality_suite_action('rest_enable_debug_mode', 'ucp_debug_mode_unavailable');
    }

    public static function disable_debug_mode() {
        return self::quality_suite_action('rest_disable_support_mode', 'ucp_debug_mode_unavailable');
    }

    public static function apply_conflict_resolution($request = null) {
        if (!class_exists('UCP_Quality_Suite') || !method_exists('UCP_Quality_Suite', 'rest_apply_conflict_resolution')) {
            return self::action_error('ucp_conflict_resolution_unavailable', __('Conflictoplosser is niet beschikbaar.', 'ultracache-pro'), 404);
        }
        return UCP_Quality_Suite::rest_apply_conflict_resolution($request);
    }

    public static function release_checklist() {
        return self::quality_suite_action('rest_release_checklist', 'ucp_release_checklist_unavailable');
    }

    public static function repair_cache_files() {
        return self::quality_suite_action('rest_repair_cache_files', 'ucp_repair_cache_files_unavailable');
    }

    public static function dashboard_preload_timeout() {
        return 5;
    }

    public static function dashboard_job_limit() {
        return 5;
    }

    public static function run_due_jobs($request = null) {
        if (!class_exists('UCP_Jobs')) {
            return self::action_error('ucp_jobs_unavailable', __('Jobrunner is niet beschikbaar.', 'ultracache-pro'), 404);
        }

        $max_batches = 2;
        $dashboard_run = false;
        $job_type = '';
        if ($request instanceof WP_REST_Request) {
            $max_batches_value = $request->get_param('maxBatches');
            $dashboard_value = $request->get_param('dashboard');
            $job_type_value = $request->get_param('jobType');
            foreach (array($max_batches_value, $dashboard_value, $job_type_value) as $request_value) {
                if (null !== $request_value && !is_scalar($request_value)) {
                    return self::action_error('ucp_jobs_request_invalid', __('Ongeldige parameters voor de taakverwerking.', 'ultracache-pro'), 400);
                }
            }
            $requested_batches = is_scalar($max_batches_value) && is_numeric($max_batches_value) ? absint($max_batches_value) : 0;
            if ($requested_batches > 0) {
                $max_batches = $requested_batches;
            }
            $dashboard_run = self::is_explicit_confirmation($dashboard_value);
            $job_type = is_scalar($job_type_value) ? sanitize_key((string) $job_type_value) : '';
        }
        $max_batches = max(1, min(5, $max_batches));
        if (!in_array($job_type, array('', 'preload_url'), true)) {
            $job_type = '';
        }
        if ($dashboard_run) {
            add_filter('ucp_jobs_run_queue_limit', array(__CLASS__, 'dashboard_job_limit'), 10, 2);
            add_filter('ucp_preload_request_timeout', array(__CLASS__, 'dashboard_preload_timeout'), 10, 4);
        }

        UCP_Jobs::sync_schedule();
        $runner = new UCP_Jobs(true);
        try {
            $processed = '' !== $job_type && method_exists($runner, 'run_queue_type_until_idle')
                ? (int) $runner->run_queue_type_until_idle($job_type, false, $max_batches, $dashboard_run)
                : (int) $runner->run_queue_until_idle(false, $max_batches);
        } finally {
            if ($dashboard_run) {
                remove_filter('ucp_jobs_run_queue_limit', array(__CLASS__, 'dashboard_job_limit'), 10);
                remove_filter('ucp_preload_request_timeout', array(__CLASS__, 'dashboard_preload_timeout'), 10);
            }
        }
        $failed = '' !== $job_type && method_exists('UCP_Jobs', 'count_by_status_and_type')
            ? (int) UCP_Jobs::count_by_status_and_type('failed', $job_type)
            : (int) UCP_Jobs::count_by_status('failed');

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
    public static function retry_job($request = null) {
        $job_id_value = $request instanceof WP_REST_Request ? $request->get_param('jobId') : null;
        $job_id = is_scalar($job_id_value) && is_numeric($job_id_value) ? absint($job_id_value) : 0;
        if (!$job_id || !class_exists('UCP_Jobs') || !method_exists('UCP_Jobs', 'retry_job')) {
            return self::action_error('ucp_job_retry_invalid', __('De preloadtaak kon niet opnieuw worden gestart.', 'ultracache-pro'), 400);
        }
        if (!UCP_Jobs::retry_job($job_id, 'preload_url')) {
            return self::action_error('ucp_job_retry_not_allowed', __('Alleen mislukte preloadtaken kunnen opnieuw worden gestart.', 'ultracache-pro'), 409);
        }
        UCP_Jobs::sync_schedule();
        if (class_exists('UCP_Logger')) {
            UCP_Logger::log('info', 'rest', 'preload_job_retried', __('Preloadtaak is opnieuw ingepland.', 'ultracache-pro'), array('job_id' => $job_id));
        }
        return self::action_success(__('Preloadtaak staat opnieuw in de wachtrij.', 'ultracache-pro'), array('jobId' => $job_id));
    }

    public static function cancel_job($request = null) {
        $job_id_value = $request instanceof WP_REST_Request ? $request->get_param('jobId') : null;
        $job_id = is_scalar($job_id_value) && is_numeric($job_id_value) ? absint($job_id_value) : 0;
        if (!$job_id || !class_exists('UCP_Jobs') || !method_exists('UCP_Jobs', 'cancel_job')) {
            return self::action_error('ucp_job_cancel_invalid', __('De preloadtaak kon niet worden geannuleerd.', 'ultracache-pro'), 400);
        }
        if (!UCP_Jobs::cancel_job($job_id, 'preload_url')) {
            return self::action_error('ucp_job_cancel_not_allowed', __('Alleen wachtende preloadtaken kunnen worden geannuleerd.', 'ultracache-pro'), 409);
        }
        if (class_exists('UCP_Logger')) {
            UCP_Logger::log('info', 'rest', 'preload_job_cancelled', __('Preloadtaak is geannuleerd.', 'ultracache-pro'), array('job_id' => $job_id));
        }
        return self::action_success(__('Preloadtaak is geannuleerd.', 'ultracache-pro'), array('jobId' => $job_id));
    }

    public static function reset_cache_insights() {
        if (!class_exists('UCP_Cache_Insights')) {
            return self::action_error('ucp_cache_insights_unavailable', __('Cache-inzicht is niet beschikbaar.', 'ultracache-pro'), 404);
        }
        if (!UCP_Cache_Insights::reset()) {
            return self::action_error('ucp_cache_insights_reset_failed', __('Cache-inzicht kon niet worden gewist.', 'ultracache-pro'));
        }
        return self::action_success(__('Cache-inzicht en purgegeschiedenis zijn gewist.', 'ultracache-pro'));
    }

    public static function retry_failed_jobs() {
        global $wpdb;
        if (
            !function_exists('ucp_table_name')
            || !class_exists('UCP_Jobs')
            || !method_exists('UCP_Jobs', 'sync_schedule')
            || !method_exists('UCP_Jobs', 'cleanup_unsafe_preload_jobs')
        ) {
            return self::action_error('ucp_jobs_unavailable', __('Jobtabel of jobrunner is niet beschikbaar.', 'ultracache-pro'));
        }

        $raw_jobs_table = ucp_table_name('jobs');
        if (!UCP_Helpers::is_safe_table_name($raw_jobs_table)) {
            return self::action_error('ucp_jobs_invalid_table', __('Jobtabel is ongeldig.', 'ultracache-pro'));
        }
        $jobs_table = UCP_Helpers::quote_table_name($raw_jobs_table);
        $now = current_time('mysql', true);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned jobs table identifier is validated and values are prepared.
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$jobs_table} SET status = %s, attempts = 0, available_at = %s, locked_until = NULL, claim_token = '', last_error = NULL, updated_at = %s WHERE type = %s AND status IN ('failed','retrying')",
                'pending',
                $now,
                $now,
                'preload_url'
            )
        );

        if (false === $updated) {
            return self::action_error('ucp_preload_jobs_retry_failed', __('Mislukte preloadtaken konden niet worden teruggezet in de wachtrij.', 'ultracache-pro'));
        }

        UCP_Jobs::sync_schedule();
        UCP_Logger::log('info', 'rest', 'failed_preload_jobs_requeued', __('Mislukte preloadtaken zijn via een REST-beheeractie teruggezet in de wachtrij.', 'ultracache-pro'), array('updated' => (int) $updated));

        return self::action_success(
            sprintf(
                /* translators: %d: number of failed or retrying preload jobs moved back to the queue. */
                _n('%d preloadtaak teruggezet in de wachtrij.', '%d preloadtaken teruggezet in de wachtrij.', (int) $updated, 'ultracache-pro'),
                (int) $updated
            ),
            array(
                'updated' => (int) $updated,
                'processed' => 0,
                'cleanup' => array('skipped' => 0, 'repaired' => 0),
            )
        );
    }

    public static function clear_cwv_fielddata() {
        if (!class_exists('UCP_CWV') || !method_exists('UCP_CWV', 'reset_summary')) {
            return self::action_error('ucp_cwv_unavailable', __('CWV-monitoring is niet beschikbaar.', 'ultracache-pro'), 404);
        }

        UCP_CWV::reset_summary();
        if (class_exists('UCP_CWV_Timeseries') && method_exists('UCP_CWV_Timeseries', 'clear')) {
            UCP_CWV_Timeseries::clear();
        }
        if (class_exists('UCP_Logger')) {
            UCP_Logger::log('info', 'rest', 'cwv_data_reset', __('CWV-veldgegevens zijn hersteld via een REST-beheeractie.', 'ultracache-pro'));
        }

        return self::action_success(__('CWV-samenvatting en tijdreeks zijn gewist.', 'ultracache-pro'));
    }

    /**
     * Backward-compatible method name for older admin bundles.
     */
    public static function clear_rum() {
        return self::clear_cwv_fielddata();
    }

    public static function support_report() {
        if (!class_exists('UCP_Support_Report')) {
            return self::action_error('ucp_support_report_unavailable', __('Ondersteuningsrapport is niet beschikbaar.', 'ultracache-pro'), 404);
        }
        return rest_ensure_response(array('success' => true, 'report' => UCP_Support_Report::generate(), 'timestamp' => time()));
    }
}
