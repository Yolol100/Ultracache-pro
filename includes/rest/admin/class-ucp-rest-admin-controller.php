<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_REST_Admin_Controller {
    use UCP_REST_Status_Trait;
    use UCP_REST_Settings_Trait;
    use UCP_REST_Diagnostics_Trait;
    use UCP_REST_Actions_Trait;

    const REST_NAMESPACE = 'ultracache-pro/v1';

    /**
     * Lease context for the currently running guarded REST mutation.
     *
     * @var array<string,mixed>
     */
    private static $active_action_lease = array();

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes() {
        self::register_status_routes();
        self::register_settings_routes();
        self::register_preset_routes();
        self::register_diagnostic_routes();
        self::register_action_routes();
    }

    /**
     * Register read-only status and lifecycle routes.
     *
     * @return void
     */
    private static function register_status_routes() {
        register_rest_route(self::REST_NAMESPACE, '/status', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_status'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::REST_NAMESPACE, '/optimization-lifecycle', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_optimization_lifecycle'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
    }

    /**
     * Register settings CRUD, import/export, snapshot and preset routes.
     *
     * @return void
     */
    private static function register_settings_routes() {
        register_rest_route(self::REST_NAMESPACE, '/settings', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'get_settings'),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'update_settings'),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/bulk', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'update_settings'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/export', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'export_settings'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/import', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'import_settings'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/snapshots', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'settings_snapshots'),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'create_settings_snapshot'),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/snapshots/restore', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'restore_settings_snapshot'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
            'args'                => array(
                'id' => array(
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 1,
                    'maxLength' => 80,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        register_rest_route(self::REST_NAMESPACE, '/settings/custom-preset', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'save_custom_preset'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
            'args'                => array(
                'name' => array(
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 1,
                    'maxLength' => 80,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Register preset-scanning routes.
     *
     * @return void
     */
    private static function register_preset_routes() {
        register_rest_route(self::REST_NAMESPACE, '/scan-preset', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'scan_preset'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
    }

    /**
     * Register diagnostics and support report routes.
     *
     * @return void
     */
    private static function register_diagnostic_routes() {
        $diagnostic_routes = array(
            'jobs'                   => array('method' => 'diagnostic_jobs', 'args' => self::pagination_args()),
            'logs'                   => array('method' => 'diagnostic_logs', 'args' => self::pagination_args()),
            'requests'               => array('method' => 'diagnostic_requests', 'args' => self::pagination_args()),
            'browser-scan'           => array('method' => 'browser_scan_latest', 'args' => array()),
            'asset-snapshot'         => array('method' => 'asset_manager_snapshot', 'args' => self::pagination_args()),
            'quality-summary'        => array('method' => 'quality_summary', 'args' => array()),
            'cache-insights'         => array('method' => 'cache_insights', 'args' => self::cache_insights_args()),
            'preload-queue'          => array('method' => 'preload_queue', 'args' => self::preload_queue_args()),
            'compatibility-profiles' => array('method' => 'compatibility_profiles', 'args' => array()),
            'fragments'              => array('method' => 'fragment_platform', 'args' => array()),
        );

        foreach ($diagnostic_routes as $route => $definition) {
            register_rest_route(self::REST_NAMESPACE, '/diagnostics/' . $route, array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, $definition['method']),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
                'args'                => $definition['args'],
            ));
        }

        register_rest_route(self::REST_NAMESPACE, '/support-report', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'support_report'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
    }

    /**
     * Register state-changing maintenance/action routes.
     *
     * @return void
     */
    private static function register_action_routes() {
        $actions = class_exists('UCP_REST_Action_Registry') ? UCP_REST_Action_Registry::route_handlers() : array();

        foreach ($actions as $route => $method) {
            if (!is_string($route) || !is_string($method) || !method_exists(__CLASS__, $method)) {
                continue;
            }
            register_rest_route(self::REST_NAMESPACE, '/actions/' . $route, array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => self::guarded_action_callback((string) $route, (string) $method),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
                'args'                => self::action_args($route),
            ));
        }
    }

    /**
     * Normalize action input for a stable duplicate-request fingerprint.
     *
     * @param mixed $value Request value.
     * @return mixed
     */
    private static function normalize_action_payload($value) {
        if (!is_array($value)) {
            return is_scalar($value) || null === $value ? $value : '';
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize_action_payload($item);
        }
        return $value;
    }

    /**
     * Build a user- and payload-bound action fingerprint.
     *
     * @param string                $route   Route slug.
     * @param WP_REST_Request|null  $request Request object.
     * @return string
     */
    private static function action_fingerprint($route, $request) {
        $params = $request instanceof WP_REST_Request ? $request->get_params() : array();
        $params = is_array($params) ? $params : array();
        unset($params['_wpnonce'], $params['idempotency_key']);
        return hash('sha256', get_current_user_id() . '|' . sanitize_key($route) . '|' . UCP_Helpers::safe_json_encode_or(self::normalize_action_payload($params), '{}'));
    }

    /**
     * Build a replay key only from a valid client-provided idempotency token.
     * The semantic fingerprint remains responsible for the in-progress mutex,
     * while this key allows the same timed-out request to retrieve its result.
     *
     * @param string               $route                Route slug.
     * @param WP_REST_Request|null $request              Request object.
     * @param string               $semantic_fingerprint Payload-bound action fingerprint.
     * @return string Empty when no valid token was supplied.
     */
    private static function action_idempotency_fingerprint($route, $request, $semantic_fingerprint = '') {
        if (!$request instanceof WP_REST_Request) {
            return '';
        }
        $raw = $request->get_header('X-UCP-Idempotency-Key');
        $key = is_scalar($raw) ? trim((string) $raw) : '';
        if (1 !== preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $key)) {
            return '';
        }
        $semantic_fingerprint = 1 === preg_match('/^[a-f0-9]{64}$/D', (string) $semantic_fingerprint)
            ? (string) $semantic_fingerprint
            : self::action_fingerprint($route, $request);
        return hash('sha256', get_current_user_id() . '|' . sanitize_key($route) . '|' . $key . '|' . $semantic_fingerprint);
    }

    private static function action_lock_option_name($fingerprint) {
        return 'ucp_action_lock_' . substr((string) $fingerprint, 0, 40);
    }

    private static function action_result_transient_name($fingerprint) {
        return 'ucp_action_result_' . substr((string) $fingerprint, 0, 40);
    }

    /**
     * Acquire a cross-request action mutex using compare-and-swap takeover.
     *
     * @param string $fingerprint Action fingerprint.
     * @param int    $ttl         Lock lifetime.
     * @return string Lock token or empty string.
     */
    private static function acquire_action_lock($fingerprint, $ttl) {
        global $wpdb;

        $key = self::action_lock_option_name($fingerprint);
        $now = time();
        $token = wp_generate_password(24, false, false);
        $lock = array('token' => $token, 'expires' => $now + max(60, absint($ttl)));
        if (add_option($key, $lock, '', false)) {
            return $token;
        }

        $current = get_option($key, array());
        $valid = is_array($current)
            && !empty($current['token'])
            && is_scalar($current['token'])
            && isset($current['expires'])
            && is_numeric($current['expires']);
        if ($valid && (int) $current['expires'] >= $now) {
            return '';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic takeover of a stale plugin-owned REST action lock.
        $updated = $wpdb->update(
            $wpdb->options,
            array('option_value' => maybe_serialize($lock)),
            array('option_name' => $key, 'option_value' => maybe_serialize($current)),
            array('%s'),
            array('%s', '%s')
        );
        if (1 !== (int) $updated) {
            return '';
        }
        wp_cache_delete($key, 'options');
        wp_cache_delete('alloptions', 'options');
        return $token;
    }

    /**
     * Extend an action lease only when this request still owns the exact token.
     *
     * @param string $fingerprint Action fingerprint.
     * @param string $token       Lock token.
     * @param int    $ttl         New lease duration.
     * @return bool
     */
    private static function refresh_action_lock($fingerprint, $token, $ttl) {
        global $wpdb;

        if (!is_scalar($token) || '' === (string) $token) {
            return false;
        }

        $key = self::action_lock_option_name($fingerprint);
        $current = get_option($key, array());
        if (!is_array($current)
            || empty($current['token'])
            || !is_scalar($current['token'])
            || !hash_equals((string) $current['token'], (string) $token)) {
            return false;
        }

        $next = $current;
        $next['expires'] = time() + max(MINUTE_IN_SECONDS, absint($ttl));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-swap renewal of the exact plugin-owned REST lease.
        $updated = $wpdb->update(
            $wpdb->options,
            array('option_value' => maybe_serialize($next)),
            array('option_name' => $key, 'option_value' => maybe_serialize($current)),
            array('%s'),
            array('%s', '%s')
        );
        if (1 === (int) $updated) {
            wp_cache_delete($key, 'options');
            wp_cache_delete('alloptions', 'options');
            return true;
        }

        $stored = get_option($key, array());
        return is_array($stored)
            && !empty($stored['token'])
            && is_scalar($stored['token'])
            && hash_equals((string) $stored['token'], (string) $token)
            && isset($stored['expires'])
            && (int) $stored['expires'] >= (int) $next['expires'];
    }

    /**
     * Renew the active REST lease from a long-running batch or loop.
     *
     * Long-running services can call do_action('ucp_operation_heartbeat')
     * between bounded units of work. Losing the lease aborts the mutation so
     * a stale worker cannot continue beside a replacement worker.
     *
     * @param bool $force Ignore the normal renewal interval.
     * @return bool
     * @throws RuntimeException When the current request no longer owns the lease.
     */
    public static function refresh_active_action_lease($force = false) {
        if (empty(self::$active_action_lease)) {
            return true;
        }

        $now = microtime(true);
        $ttl = max(MINUTE_IN_SECONDS, absint(self::$active_action_lease['ttl'] ?? 0));
        $interval = max(5, min(60, (int) floor($ttl / 3)));
        $last = isset(self::$active_action_lease['refreshed_at']) ? (float) self::$active_action_lease['refreshed_at'] : 0.0;
        if (!$force && ($now - $last) < $interval) {
            return true;
        }

        $renewed = self::refresh_action_lock(
            (string) self::$active_action_lease['fingerprint'],
            (string) self::$active_action_lease['token'],
            $ttl
        );
        if (!$renewed) {
            throw new RuntimeException('REST action lease was lost.');
        }

        self::$active_action_lease['refreshed_at'] = $now;
        return true;
    }

    private static function release_action_lock($fingerprint, $token) {
        global $wpdb;

        if (!is_scalar($token) || '' === (string) $token) {
            return;
        }
        $key = self::action_lock_option_name($fingerprint);
        $current = get_option($key, array());
        if (!is_array($current)
            || empty($current['token'])
            || !is_scalar($current['token'])
            || !hash_equals((string) $current['token'], (string) $token)) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact-value deletion of a plugin-owned REST action lock.
        $wpdb->delete(
            $wpdb->options,
            array('option_name' => $key, 'option_value' => maybe_serialize($current)),
            array('%s', '%s')
        );
        wp_cache_delete($key, 'options');
        wp_cache_delete('alloptions', 'options');
    }

    /**
     * Rebuild a recently completed successful response for a duplicate retry.
     *
     * @param string $fingerprint Action fingerprint.
     * @return WP_REST_Response|null
     */
    private static function replay_action_response($fingerprint) {
        $cached = get_transient(self::action_result_transient_name($fingerprint));
        if (!is_array($cached) || !array_key_exists('data', $cached) || empty($cached['status'])) {
            return null;
        }
        $response = new WP_REST_Response($cached['data'], absint($cached['status']));
        $response->header('X-UCP-Idempotent-Replay', '1');
        return $response;
    }

    /**
     * Cache a bounded successful response briefly so a browser timeout cannot
     * immediately execute the same mutation again.
     *
     * @param string $fingerprint Action fingerprint.
     * @param mixed  $result      Handler result.
     * @return void
     */
    private static function remember_action_response($fingerprint, $result) {
        if (is_wp_error($result)) {
            return;
        }
        $response = rest_ensure_response($result);
        if (!($response instanceof WP_REST_Response)) {
            return;
        }
        $status = absint($response->get_status());
        if ($status < 200 || $status >= 300) {
            return;
        }
        $record = array('data' => $response->get_data(), 'status' => $status);
        $serialized = maybe_serialize($record);
        if (!is_string($serialized) || strlen($serialized) > (512 * KB_IN_BYTES)) {
            return;
        }
        set_transient(self::action_result_transient_name($fingerprint), $record, 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Wrap an action handler so an unexpected fatal/exception never reaches the
     * browser as a bare 500. The handler keeps full control of its own success
     * and WP_Error responses; this only adds a last-resort safety net that
     * returns a calm, translatable message pointing the user back to Tools.
     *
     * @param string $route  Action route slug (for logging/diagnostics).
     * @param string $method Handler method name on this controller.
     * @return callable
     */
    private static function guarded_action_callback($route, $method) {
        return function ($request = null) use ($route, $method) {
            if (!method_exists(__CLASS__, $method)) {
                return new WP_Error('ucp_action_unavailable', __('De gevraagde actie is niet beschikbaar.', 'ultracache-pro'), array('status' => 404));
            }

            $fingerprint = self::action_fingerprint($route, $request);
            $idempotency_fingerprint = self::action_idempotency_fingerprint($route, $request, $fingerprint);
            if ('' !== $idempotency_fingerprint) {
                $replayed = self::replay_action_response($idempotency_fingerprint);
                if ($replayed instanceof WP_REST_Response) {
                    return $replayed;
                }
            }

            $long_routes = array('runtime-cache-test', 'website-check', 'preload', 'refresh-css', 'used-css', 'critical-css', 'run-due-jobs', 'renderer-test', 'repair-cache-files', 'database-cleanup');
            $default_lock_ttl = in_array($route, $long_routes, true) ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
            $lock_ttl = (int) apply_filters('ucp_rest_action_lock_ttl', $default_lock_ttl, $route, $request);
            $lock_ttl = max(MINUTE_IN_SECONDS, min(HOUR_IN_SECONDS, $lock_ttl));
            $lock_token = self::acquire_action_lock($fingerprint, $lock_ttl);
            if ('' === $lock_token) {
                return new WP_Error(
                    'ucp_action_in_progress',
                    __('Deze actie wordt al uitgevoerd. Controleer de status voordat u haar opnieuw start.', 'ultracache-pro'),
                    array('status' => 409, 'route' => sanitize_key($route))
                );
            }

            self::$active_action_lease = array(
                'fingerprint' => $fingerprint,
                'token'       => $lock_token,
                'ttl'         => $lock_ttl,
                'refreshed_at' => 0.0,
            );
            add_action('ucp_operation_heartbeat', array(__CLASS__, 'refresh_active_action_lease'));

            try {
                self::refresh_active_action_lease(true);
                $result = call_user_func(array(__CLASS__, $method), $request);
                self::refresh_active_action_lease(true);
                if ('' !== $idempotency_fingerprint) {
                    self::remember_action_response($idempotency_fingerprint, $result);
                }
                return $result;
            } catch (Throwable $e) {
                if (class_exists('UCP_Logger')) {
                    UCP_Logger::log('error', 'rest', 'action_exception', __('REST-actie liep tegen een onverwachte fout aan.', 'ultracache-pro'), array(
                        'route' => sanitize_key($route),
                        'exception' => get_class($e),
                        'exception_code' => (string) $e->getCode(),
                    ));
                }
                return new WP_Error(
                    'ucp_action_failed',
                    __('De actie kon niet worden afgerond. Probeer het opnieuw of controleer Tools voor logs en details.', 'ultracache-pro'),
                    array('status' => 500, 'route' => sanitize_key($route))
                );
            } finally {
                remove_action('ucp_operation_heartbeat', array(__CLASS__, 'refresh_active_action_lease'));
                self::$active_action_lease = array();
                self::release_action_lock($fingerprint, $lock_token);
            }
        };
    }

    /**
     * Shared pagination arguments for diagnostics routes.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function pagination_args() {
        return array(
            'per_page' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20),
            'paged'    => array('type' => 'integer', 'minimum' => 1, 'default' => 1),
        );
    }

    private static function cache_insights_args() {
        return array_merge(self::pagination_args(), array(
            'days' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 7),
        ));
    }

    private static function preload_queue_args() {
        return array_merge(self::pagination_args(), array(
            'status' => array(
                'type' => 'string',
                'enum' => array('', 'pending', 'running', 'retrying', 'failed', 'success'),
                'default' => '',
                'sanitize_callback' => 'sanitize_key',
            ),
        ));
    }

    /**
     * Arguments for action routes.
     *
     * @param string $route Action route slug.
     * @return array<string,array<string,mixed>>
     */
    private static function action_args($route) {
        return class_exists('UCP_REST_Action_Registry') ? UCP_REST_Action_Registry::args($route) : array();
    }

    private static function scalar_request_param($request, $name, $default = '') {
        if (!$request instanceof WP_REST_Request) {
            return $default;
        }
        $value = $request->get_param($name);
        return is_scalar($value) ? $value : $default;
    }

    private static function is_explicit_confirmation($value) {
        return UCP_Helpers::is_explicit_confirmation($value);
    }

    public static function get_optimization_lifecycle() {
        $lifecycle = class_exists('UCP_Optimization_Status') ? UCP_Optimization_Status::all() : array();
        return rest_ensure_response(array('success' => true, 'lifecycle' => $lifecycle, 'timestamp' => time()));
    }

    public static function permissions_check($request = null) {
        return UCP_REST_Permissions::admin_permission_check($request);
    }

}
