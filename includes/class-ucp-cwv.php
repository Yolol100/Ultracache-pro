<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned diagnostics/maintenance queries; caching would make these admin metrics stale.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic table identifiers are validated with UCP_Helpers::is_safe_table_name() and quoted before use; values remain prepared.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_CWV {
    const OPTION_KEY = 'ucp_cwv_metrics';
    const MAX_VALUE = 120000;
    const MAX_SAMPLES_PER_METRIC = 500;
    const MAX_DAILY_SAMPLES_PER_METRIC = 1000;
    const MAX_IP_SAMPLES_PER_MINUTE = 20;
    const MAX_SITE_SAMPLES_PER_MINUTE = 120;
    const TOKEN_WINDOW_SECONDS = 86400;
    const LEGACY_TOKEN_UNTIL_OPTION = 'ucp_cwv_legacy_token_until';
    const DEFAULT_PROFILE_MAX_AGE_DAYS = 21;
    const MIN_PROFILE_CONFIDENCE = 85;

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_rum_script'));
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('ultracache-pro/v1', '/cwv', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'record_metric'),
            'permission_callback' => array($this, 'can_record_metric'),
            'args'                => array(
                'metric' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => array($this, 'validate_metric'),
                ),
                'value' => array(
                    'type'              => 'number',
                    'required'          => true,
                    'minimum'           => 0,
                    'maximum'           => self::MAX_VALUE,
                    'sanitize_callback' => array($this, 'sanitize_metric_value'),
                    'validate_callback' => array($this, 'validate_metric_value'),
                ),
                'rating' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'enum'              => array('good', 'needs-improvement', 'poor', 'info'),
                    'sanitize_callback' => 'sanitize_key',
                ),
                'url' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'minLength'         => 1,
                    'maxLength'         => 2048,
                    'sanitize_callback' => array($this, 'sanitize_page_url_param'),
                    'validate_callback' => array($this, 'validate_page_url_param'),
                ),
                'device' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'enum'              => array('mobile', 'desktop', 'all'),
                    'sanitize_callback' => 'sanitize_key',
                ),
                'lcp_url' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'maxLength'         => 2048,
                    'sanitize_callback' => array($this, 'sanitize_lcp_resource_url_param'),
                ),
                'lcp_element_json' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'maxLength'         => 2048,
                    'sanitize_callback' => array($this, 'sanitize_lcp_element_json'),
                ),
                'lcp_imagesrcset' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'maxLength'         => 1200,
                    'sanitize_callback' => array($this, 'sanitize_lcp_srcset_param'),
                ),
                'token' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'minLength'         => 64,
                    'maxLength'         => 80,
                    'pattern'           => '^(?:[0-9]{1,12}\.[a-f0-9]{64}|[a-f0-9]{64})$',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_beacon_token_shape'),
                ),
            ),
        ));
    }

    public function validate_metric($value) {
        if (!is_scalar($value)) {
            return false;
        }
        return in_array(strtoupper(sanitize_key((string) $value)), array('LCP', 'INP', 'CLS', 'FCP', 'TTFB'), true);
    }

    public function sanitize_metric_value($value) {
        if (!is_scalar($value) || !is_numeric($value)) {
            return -1.0;
        }
        $value = (float) $value;
        return is_finite($value) ? $value : -1.0;
    }

    public function validate_metric_value($value) {
        if (!is_scalar($value) || !is_numeric($value)) {
            return false;
        }
        $value = (float) $value;
        return is_finite($value) && $value >= 0 && $value <= self::MAX_VALUE;
    }

    public function can_record_metric($request) {
        if (empty(UCP_Options::get('enable_cwv_monitoring'))) {
            return false;
        }

        $origin_value = $request instanceof WP_REST_Request ? $request->get_header('origin') : '';
        $referer_value = $request instanceof WP_REST_Request ? $request->get_header('referer') : '';
        $origin = is_scalar($origin_value) ? (string) $origin_value : '';
        $referer = is_scalar($referer_value) ? (string) $referer_value : '';

        // CWV beacons originate from cacheable frontend HTML, where embedded tokens must remain usable for the configured fresh and stale cache lifetime.
        // Tokens remain page-bound and rate limited; the accepted daily buckets are bounded by the plugin's cache-retention settings.
        if ('' === trim($origin) && '' === trim($referer)) {
            return false;
        }
        if ('' !== trim($origin) && !$this->is_local_header_url($origin)) {
            return false;
        }
        if ('' !== trim($referer) && !$this->is_local_header_url($referer)) {
            return false;
        }

        $url = $request instanceof WP_REST_Request ? $this->sanitize_page_url_param($request->get_param('url')) : '';
        if ('' === $url) {
            return false;
        }

        $token_value = $request instanceof WP_REST_Request ? $request->get_param('token') : '';
        $token = is_scalar($token_value) ? (string) $token_value : '';
        if (!$this->verify_beacon_token($token, $url)) {
            return false;
        }

        return true;
    }


    public function validate_beacon_token_shape($token) {
        return is_string($token)
            && 1 === preg_match('/^(?:[0-9]{1,12}\.[a-f0-9]{64}|[a-f0-9]{64})$/', $token);
    }

    private function cwv_token($page_url, $bucket = null) {
        $page_url = $this->sanitize_page_url_param($page_url);
        if ('' === $page_url) {
            return '';
        }
        $bucket = null === $bucket ? (int) floor(time() / self::TOKEN_WINDOW_SECONDS) : (int) $bucket;
        $signature = hash_hmac('sha256', 'ucp-cwv|' . $page_url . '|' . $bucket, wp_salt('nonce'));
        return $bucket . '.' . $signature;
    }

    private function legacy_cwv_token($page_url, $bucket) {
        return hash_hmac('sha256', 'ucp-cwv|' . $page_url . '|' . (int) $bucket, wp_salt('nonce'));
    }

    private function token_retention_buckets() {
        $fresh_hours = max(0, absint(UCP_Options::get('cache_lifespan', 10)));
        $stale_hours = !empty(UCP_Options::get('enable_stale_cache', 0))
            ? max(0, absint(UCP_Options::get('stale_cache_lifespan', 24)))
            : 0;
        $retention_seconds = (($fresh_hours + $stale_hours) * HOUR_IN_SECONDS) + self::TOKEN_WINDOW_SECONDS;

        // Settings currently cap fresh + stale retention below 38 days. Keep a
        // defensive hard ceiling so malformed filters/options cannot amplify work.
        return max(2, min(40, (int) ceil($retention_seconds / self::TOKEN_WINDOW_SECONDS)));
    }

    private function verify_beacon_token($token, $page_url) {
        if (!is_scalar($token)) {
            return false;
        }
        $token = sanitize_text_field((string) $token);
        $page_url = $this->sanitize_page_url_param($page_url);
        if (!$this->validate_beacon_token_shape($token) || '' === $page_url) {
            return false;
        }

        $current_bucket = (int) floor(time() / self::TOKEN_WINDOW_SECONDS);
        $retention_buckets = $this->token_retention_buckets();
        if (1 === preg_match('/^([0-9]{1,12})\.([a-f0-9]{64})$/', $token, $matches)) {
            $bucket = (int) $matches[1];
            if ($bucket > $current_bucket || $bucket < ($current_bucket - ($retention_buckets - 1))) {
                return false;
            }
            return hash_equals(
                $this->legacy_cwv_token($page_url, $bucket),
                (string) $matches[2]
            );
        }

        // One-release compatibility window for HTML cached before bucketed tokens
        // were introduced. Fresh installs do not create this option and therefore
        // never pay the legacy multi-bucket verification cost.
        $legacy_until = absint(get_option(self::LEGACY_TOKEN_UNTIL_OPTION, 0));
        if ($legacy_until <= time()) {
            if ($legacy_until > 0) {
                delete_option(self::LEGACY_TOKEN_UNTIL_OPTION);
            }
            return false;
        }
        for ($offset = 0; $offset < $retention_buckets; $offset++) {
            if (hash_equals($this->legacy_cwv_token($page_url, $current_bucket - $offset), $token)) {
                return true;
            }
        }

        return false;
    }

    private function is_local_header_url($url) {
        return UCP_CWV_LCP_Sanitizer::is_local_header_url($url);
    }

    public function enqueue_rum_script() {
        if (is_admin() || empty(UCP_Options::get('enable_cwv_monitoring'))) {
            return;
        }

        $asset = UCP_Helpers::frontend_asset_with_min_fallback('assets/frontend/js/ucp-cwv-monitor');
        if ('' === $asset['url']) {
            return;
        }

        $endpoint    = esc_url_raw(rest_url('ultracache-pro/v1/cwv'));
        $page_url = $this->sanitize_page_url_param(UCP_Helpers::current_full_url());
        if ('' === $page_url) {
            return;
        }
        $sample_rate = min(100, max(1, absint(UCP_Options::get('rum_sample_rate', 10)))) / 100;

        wp_register_script('ucp-cwv-monitor', $asset['url'], array(), $asset['version'], true);
        wp_add_inline_script(
            'ucp-cwv-monitor',
            'window.UCP=window.UCP||{};window.UCP.cwvMonitor=' . UCP_Helpers::safe_inline_json(array(
                'endpoint' => $endpoint,
                'token' => $this->cwv_token($page_url),
                'pageUrl' => $page_url,
                'sampleRate' => $sample_rate,
            ), '{}') . ';window.ucpCwvMonitor=window.UCP.cwvMonitor;',
            'before'
        );
        wp_enqueue_script('ucp-cwv-monitor');
    }

    public function print_rum_script() {
        $this->enqueue_rum_script();
    }

    private function metric_value_is_plausible($metric, $value) {
        $limits = (array) apply_filters('ucp_cwv_metric_max_values', array(
            'LCP'  => 120000.0,
            'INP'  => 60000.0,
            'CLS'  => 10000.0,
            'FCP'  => 120000.0,
            'TTFB' => 120000.0,
        ));
        $maximum = isset($limits[$metric]) && is_numeric($limits[$metric])
            ? max(0.0, (float) $limits[$metric])
            : (float) self::MAX_VALUE;
        return is_finite((float) $value) && (float) $value >= 0.0 && (float) $value <= $maximum;
    }

    public function record_metric($request) {
        $metric_value = $request instanceof WP_REST_Request ? $request->get_param('metric') : null;
        if (!$this->validate_metric($metric_value)) {
            return new WP_REST_Response(array('ok' => false), 400);
        }
        $metric = strtoupper(sanitize_key((string) $metric_value));

        $raw_value = $request instanceof WP_REST_Request ? $request->get_param('value') : null;
        if (!$this->validate_metric_value($raw_value)) {
            return new WP_REST_Response(array('ok' => false), 400);
        }
        $value = (float) $raw_value;
        if (!$this->metric_value_is_plausible($metric, $value)) {
            return new WP_REST_Response(array('ok' => false), 400);
        }

        $rate_limit_response = $this->enforce_rate_limits($metric);
        if ($rate_limit_response instanceof WP_REST_Response) {
            return $rate_limit_response;
        }

        $device_value = $request instanceof WP_REST_Request ? $request->get_param('device') : '';
        $device = is_scalar($device_value) ? sanitize_key((string) $device_value) : '';
        $device = in_array($device, array('mobile', 'desktop', 'all'), true) ? $device : 'all';
        self::record_metric_summary($metric, $value, $device);

        if ('LCP' === $metric) {
            self::store_lcp_hint(array(
                'url' => $this->sanitize_page_url_param($request->get_param('url')),
                'device' => $device,
                'lcp_url' => $this->sanitize_lcp_resource_url_param($request->get_param('lcp_url')),
                'lcp_element_json' => $this->sanitize_lcp_element_json($request->get_param('lcp_element_json')),
                'lcp_imagesrcset' => $this->sanitize_lcp_srcset_param($request->get_param('lcp_imagesrcset')),
                'value_ms' => $value,
            ));
        }

        $response = new WP_REST_Response(array('ok' => true), 202);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('X-Robots-Tag', 'noindex, nofollow');
        $response->header('X-UCP-CWV', 'accepted');
        return $response;
    }


    /**
     * Apply CWV beacon rate limits in the same order as the legacy inline checks.
     *
     * @param string $metric Metric key.
     * @return WP_REST_Response|null Error response when rate-limited, otherwise null.
     */
    private function enforce_rate_limits($metric) {
        return UCP_CWV_Rate_Limiter::enforce($metric);
    }

    /**
     * Update the rolling metric summary stored in the existing CWV option.
     *
     * @param string $metric Metric key.
     * @param float  $value  Metric value.
     * @return void
     */
    private static function record_metric_summary($metric, $value, $device = 'all') {
        UCP_CWV_Metric_Summary::record($metric, $value, $device, UCP_Options::get('rum_sample_rate', 10));
    }

    /**
     * Sanitize a same-origin URL supplied by the browser beacon.
     *
     * CWV and LCP hints are used for preload decisions. Keeping them local avoids
     * turning the public beacon into a third-party URL injection surface.
     *
     * @param mixed $url Raw URL.
     * @return string
     */
    public function sanitize_local_url_param($url) {
        return UCP_CWV_LCP_Sanitizer::sanitize_local_url_param($url);
    }


    /**
     * Sanitize a page URL for profile storage. Query and fragment data are removed.
     *
     * @param mixed $url Raw page URL.
     * @return string
     */
    public function sanitize_page_url_param($url) {
        return UCP_CWV_LCP_Sanitizer::sanitize_page_url($url);
    }

    /**
     * Require a valid same-origin page URL for every public CWV beacon.
     *
     * @param mixed $url Raw page URL.
     * @return bool
     */
    public function validate_page_url_param($url) {
        return '' !== $this->sanitize_page_url_param($url);
    }

    /**
     * Sanitize an LCP resource URL while keeping a valid same-origin resource URL.
     *
     * @param mixed $url Raw resource URL.
     * @return string
     */
    public function sanitize_lcp_resource_url_param($url) {
        $url = UCP_CWV_LCP_Sanitizer::sanitize_local_url_param($url);
        return '' !== $url ? UCP_CWV_LCP_Sanitizer::sanitize_resource_url($url) : '';
    }

    /**
     * Sanitize browser-provided LCP srcset metadata and keep only same-origin candidates.
     *
     * @param mixed $srcset Raw srcset.
     * @return string
     */
    public function sanitize_lcp_srcset_param($srcset) {
        return UCP_CWV_LCP_Sanitizer::sanitize_srcset($srcset);
    }

    /**
     * Sanitize compact LCP element metadata from the browser beacon.
     *
     * @param mixed $json Raw JSON string or array.
     * @return string JSON encoded safe metadata.
     */
    public function sanitize_lcp_element_json($json) {
        return UCP_CWV_LCP_Sanitizer::sanitize_element_json($json);
    }


    /**
     * Persist a sanitized measured LCP hint for one URL/device pair.
     *
     * @param array<string,mixed> $data LCP hint data.
     * @return bool
     */
    public static function store_lcp_hint($data) {
        return UCP_CWV_LCP_Profile_Repository::store($data);
    }

    /**
     * Get an LCP profile safe enough for automatic preload/fetchpriority decisions.
     *
     * @param string $url                  URL to look up.
     * @param string $device               Device bucket.
     * @param bool   $high_confidence_only Require configured confidence threshold.
     * @return array<string,mixed>
     */
    public static function lcp_profile_for_url($url, $device = 'all', $high_confidence_only = true) {
        return UCP_CWV_LCP_Profile_Repository::profile_for_url($url, $device, $high_confidence_only);
    }

    /**
     * Mark one measured LCP profile stale for safe rollback after a bad hint.
     *
     * @param string $url    URL.
     * @param string $device Device bucket.
     * @param string $reason Stale reason.
     * @return int|false
     */
    public static function mark_lcp_profile_stale_for_url($url, $device = 'all', $reason = 'manual_rollback') {
        return UCP_CWV_LCP_Profile_Repository::mark_stale_for_url($url, $device, $reason);
    }

    /**
     * Check profile age/status.
     *
     * @param array $row LCP row.
     * @return bool
     */
    public static function lcp_profile_is_stale($row) {
        return UCP_CWV_LCP_Profile_Repository::is_stale($row);
    }

    /**
     * Mark all measured LCP profiles stale after layout/theme/global changes.
     *
     * @param string $reason Reason.
     * @return int|false
     */
    public static function mark_lcp_profiles_stale($reason = 'global_change') {
        return UCP_CWV_LCP_Profile_Repository::mark_all_stale($reason);
    }

    /**
     * Diagnostic profile summary.
     *
     * @return array<string,mixed>
     */
    public static function lcp_profile_summary() {
        return UCP_CWV_LCP_Profile_Repository::summary();
    }

    /**
     * Get the most recent measured LCP hint for the current URL/device.
     *
     * @param string $url    URL to look up.
     * @param string $device Device bucket.
     * @return array<string,mixed>
     */
    public static function lcp_hint_for_url($url, $device = 'all') {
        return UCP_CWV_LCP_Profile_Repository::hint_for_url($url, $device);
    }

    public static function atf_hints_summary($limit = 20) {
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 20;
        }
        return UCP_CWV_LCP_Profile_Repository::atf_summary($limit);
    }

    public static function summary() {
        return get_option(self::OPTION_KEY, array());
    }

    public static function get_summary() {
        return UCP_CWV_Metric_Summary::get_summary();
    }

    public static function reset_summary() {
        UCP_CWV_Metric_Summary::reset();
    }
}
