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
    const DEFAULT_PROFILE_MAX_AGE_DAYS = 21;
    const MIN_PROFILE_CONFIDENCE = 85;

    public function __construct() {
        add_action('wp_footer', array($this, 'print_rum_script'), 99);
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('ultracache-pro/v1', '/cwv', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'record_metric'),
            'permission_callback' => array($this, 'can_record_metric'),
            'args'                => array(
                'metric' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => array($this, 'validate_metric'),
                ),
                'value' => array(
                    'required'          => true,
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
                    'required'          => false,
                    'maxLength'         => 2048,
                    'sanitize_callback' => array($this, 'sanitize_local_url_param'),
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
                    'sanitize_callback' => array($this, 'sanitize_local_url_param'),
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
                    'maxLength'         => 64,
                    'pattern'           => '^[a-f0-9]{64}$',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_beacon_token_shape'),
                ),
            ),
        ));
    }

    public function validate_metric($value) {
        return in_array(strtoupper(sanitize_key((string) $value)), array('LCP', 'INP', 'CLS', 'FCP', 'TTFB'), true);
    }

    public function sanitize_metric_value($value) {
        return (float) $value;
    }

    public function validate_metric_value($value) {
        $value = (float) $value;
        return $value >= 0 && $value <= self::MAX_VALUE;
    }

    public function can_record_metric($request) {
        if (empty(UCP_Options::get('enable_cwv_monitoring'))) {
            return false;
        }

        $origin = $request instanceof WP_REST_Request ? (string) $request->get_header('origin') : '';
        $referer = $request instanceof WP_REST_Request ? (string) $request->get_header('referer') : '';

        // Note: CWV beacons are sent from cacheable frontend HTML; a WordPress nonce in that HTML expires while the page cache can still be warm.
        // AI-PATCH: keep the public beacon short-lived by accepting only the current/previous daily HMAC bucket.
        // Require at least one browser-supplied same-origin signal and keep the existing per-visitor and daily rate limits in record_metric().
        if ('' === trim($origin) && '' === trim($referer)) {
            return false;
        }
        if ('' !== trim($origin) && !$this->is_local_header_url($origin)) {
            return false;
        }
        if ('' !== trim($referer) && !$this->is_local_header_url($referer)) {
            return false;
        }

        $url = $request instanceof WP_REST_Request ? $this->sanitize_local_url_param($request->get_param('url')) : '';
        if ('' === $url) {
            return false;
        }

        $token = $request instanceof WP_REST_Request ? (string) $request->get_param('token') : '';
        if (!$this->verify_beacon_token($token)) {
            return false;
        }

        return true;
    }


    public function validate_beacon_token_shape($token) {
        return is_string($token) && 1 === preg_match('/^[a-f0-9]{64}$/', $token);
    }

    private function cwv_token($bucket = null) {
        $bucket = null === $bucket ? (int) floor(time() / self::TOKEN_WINDOW_SECONDS) : (int) $bucket;
        return hash_hmac('sha256', 'ucp-cwv|' . home_url('/') . '|' . $bucket, wp_salt('nonce'));
    }

    private function verify_beacon_token($token) {
        $token = sanitize_text_field((string) $token);
        if (!$this->validate_beacon_token_shape($token)) {
            return false;
        }

        $bucket = (int) floor(time() / self::TOKEN_WINDOW_SECONDS);
        return hash_equals($this->cwv_token($bucket), $token) || hash_equals($this->cwv_token($bucket - 1), $token);
    }

    private function is_local_header_url($url) {
        return UCP_CWV_LCP_Sanitizer::is_local_header_url($url);
    }

    private static function default_port_for_scheme($scheme) {
        return UCP_CWV_LCP_Sanitizer::default_port_for_scheme($scheme);
    }

    public function print_rum_script() {
        if (is_admin() || empty(UCP_Options::get('enable_cwv_monitoring'))) {
            return;
        }

        $endpoint = esc_url_raw(rest_url('ultracache-pro/v1/cwv'));
        $token = $this->cwv_token(); ?>
<script id="ucp-cwv-monitor">
(function(){if(!('PerformanceObserver' in window)||!navigator.sendBeacon){return;}var endpoint=<?php echo wp_json_encode($endpoint); ?>;var token=<?php echo wp_json_encode($token); ?>;var sampleRate=0.25;if(Math.random()>sampleRate){return;}function device(){try{return matchMedia('(max-width: 767px)').matches?'mobile':'desktop'}catch(e){return 'all'}}function local(u){try{var x=new URL(u,location.href);return x.origin===location.origin?x.href:''}catch(e){return ''}}function lcpMeta(e){var el=e&&e.element?e.element:null,out={};try{if(el){out.tag=(el.tagName||'').toLowerCase();out.id=el.id||'';out.class=(el.className&&typeof el.className==='string')?el.className.slice(0,240):'';out.selector=out.id?'#'+out.id:(out.tag+(out.class?'.'+out.class.trim().split(/\s+/).slice(0,3).join('.'):''));out.type=(out.tag==='video')?'video-poster':((out.tag==='img'||out.tag==='picture')?'image':'text');}if(e&&e.url){out.url=local(e.url);}if(el&&el.currentSrc){out.url=local(el.currentSrc);out.type='image';}if(el&&el.srcset){out.srcset=String(el.srcset).slice(0,1200);}if(el&&el.sizes){out.sizes=String(el.sizes).slice(0,240);}if(el&&el.poster){out.url=local(el.poster);out.type='video-poster';}if(!out.url&&el){try{var bg=getComputedStyle(el).backgroundImage||'';var m=bg.match(/url\(["']?([^"')]+)["']?\)/);if(m&&m[1]){out.url=local(m[1]);out.background=1;out.type='background-image';}}catch(y){}}}catch(x){}return out}function send(name,value,rating,meta){try{var data=new FormData();data.append('metric',name);data.append('value',String(Math.round(value)));data.append('rating',rating||'');data.append('token',token);data.append('url',location.href.split('#')[0]);data.append('device',device());if(meta&&name==='LCP'){if(meta.url)data.append('lcp_url',meta.url);if(meta.srcset)data.append('lcp_imagesrcset',meta.srcset);data.append('lcp_element_json',JSON.stringify(meta));}navigator.sendBeacon(endpoint,data);}catch(e){}}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){if(e.name==='first-contentful-paint'){send('FCP',e.startTime,'info');}});}).observe({type:'paint',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){var meta=lcpMeta(e);send('LCP',e.startTime,e.startTime<2500?'good':(e.startTime<4000?'needs-improvement':'poor'),meta);});}).observe({type:'largest-contentful-paint',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){if(!e.hadRecentInput){send('CLS',e.value*1000,e.value<0.1?'good':(e.value<0.25?'needs-improvement':'poor'));}});}).observe({type:'layout-shift',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){send('INP',e.duration||0,(e.duration||0)<200?'good':((e.duration||0)<500?'needs-improvement':'poor'));});}).observe({type:'event',buffered:true,durationThreshold:40});}catch(e){}})();
</script><?php
    }

    public function record_metric($request) {
        $metric = strtoupper(sanitize_key($request->get_param('metric')));
        if (!$this->validate_metric($metric)) {
            return new WP_REST_Response(array('ok' => false), 400);
        }

        $value = (float) $request->get_param('value');
        if (!$this->validate_metric_value($value)) {
            return new WP_REST_Response(array('ok' => false), 400);
        }

        $rate_limit_response = $this->enforce_rate_limits($metric);
        if ($rate_limit_response instanceof WP_REST_Response) {
            return $rate_limit_response;
        }

        self::record_metric_summary($metric, $value);

        if ('LCP' === $metric) {
            self::store_lcp_hint(array(
                'url' => $this->sanitize_local_url_param($request->get_param('url')),
                'device' => sanitize_key((string) $request->get_param('device')),
                'lcp_url' => $this->sanitize_local_url_param($request->get_param('lcp_url')),
                'lcp_element_json' => $this->sanitize_lcp_element_json($request->get_param('lcp_element_json')),
                'lcp_imagesrcset' => $this->sanitize_lcp_srcset_param($request->get_param('lcp_imagesrcset')),
                'value_ms' => $value,
            ));
        }

        return new WP_REST_Response(array('ok' => true), 202);
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
    private static function record_metric_summary($metric, $value) {
        UCP_CWV_Metric_Summary::record($metric, $value);
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
     * Sanitize browser-provided LCP srcset metadata and keep only same-origin candidates.
     *
     * @param mixed $srcset Raw srcset.
     * @return string
     */
    public function sanitize_lcp_srcset_param($srcset) {
        return UCP_CWV_LCP_Sanitizer::sanitize_srcset((string) $srcset);
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
     * Sanitize an LCP hint URL for same-origin preload use.
     *
     * @param string $url Raw URL.
     * @return string
     */
    private static function sanitize_lcp_local_url($url) {
        return UCP_CWV_LCP_Sanitizer::sanitize_resource_url($url);
    }

    /**
     * Sanitize the measured page URL. Unlike LCP resources, pages must be same-origin.
     *
     * @param string $url Raw URL.
     * @return string
     */
    private static function sanitize_lcp_local_page_url($url) {
        return UCP_CWV_LCP_Sanitizer::sanitize_page_url($url);
    }

    /**
     * Check whether a URL matches the configured site origin.
     *
     * @param string $url Absolute URL.
     * @return bool
     */
    private static function is_same_origin_url($url) {
        return UCP_CWV_LCP_Sanitizer::is_same_origin_url($url);
    }

    /**
     * Allow same-origin LCP resources and explicitly configured CDN hostnames only.
     * Page URLs still remain same-origin because they are sanitized before lookup/storage.
     *
     * @param string $url Absolute URL.
     * @return bool
     */
    private static function is_lcp_resource_origin_allowed($url) {
        return UCP_CWV_LCP_Sanitizer::is_resource_origin_allowed($url);
    }

    private static function sanitize_lcp_srcset($srcset) {
        return UCP_CWV_LCP_Sanitizer::sanitize_srcset($srcset);
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
     * Normalize LCP profile type.
     *
     * @param string $type Type.
     * @return string
     */
    private static function normalize_lcp_type($type) {
        return UCP_CWV_LCP_Sanitizer::normalize_type($type);
    }

    /**
     * Sanitize a compact selector/element hint.
     *
     * @param string $selector Selector.
     * @return string
     */
    private static function sanitize_lcp_selector($selector) {
        return UCP_CWV_LCP_Sanitizer::sanitize_selector($selector);
    }

    /**
     * Calculate a conservative confidence score for automatic preload/fetchpriority use.
     *
     * @param string $type         LCP type.
     * @param string $lcp_url      Resource URL.
     * @param array  $element      Element metadata.
     * @param float  $value_ms     Measured LCP time.
     * @param int    $sample_count Sample count.
     * @param string $source       Source.
     * @return int
     */
    private static function calculate_lcp_confidence($type, $lcp_url, $element, $value_ms, $sample_count, $source = 'rum') {
        return UCP_CWV_LCP_Profile_Repository::calculate_confidence($type, $lcp_url, $element, $value_ms, $sample_count, $source);
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
     * Keep automatic LCP preloads restricted to resource-like same-origin URLs.
     *
     * @param string $url  Resource URL.
     * @param string $type LCP type.
     * @return bool
     */
    private static function is_lcp_resource_url_safe($url, $type = 'image') {
        return UCP_CWV_LCP_Sanitizer::is_resource_url_safe($url, $type);
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
        return UCP_CWV_LCP_Profile_Repository::atf_summary($limit);
    }

    /**
     * Check whether the LCP table exists without creating it during frontend requests.
     *
     * @param string $table Fully qualified table name from ucp_table_name().
     * @return bool
     */
    private static function lcp_table_exists($table) {
        return UCP_CWV_LCP_Profile_Repository::table_exists($table);
    }

    private function bump_rate_counter($key, $limit, $ttl) {
        return UCP_CWV_Rate_Limiter::bump($key, $limit, $ttl);
    }

    private function site_minute_rate_key() {
        return UCP_CWV_Rate_Limiter::site_minute_rate_key();
    }

    private function ip_minute_rate_key() {
        return UCP_CWV_Rate_Limiter::ip_minute_rate_key();
    }

    private function daily_rate_key($metric) {
        return UCP_CWV_Rate_Limiter::daily_rate_key($metric);
    }

    private function visitor_rate_key($metric) {
        return UCP_CWV_Rate_Limiter::visitor_rate_key($metric);
    }

    public static function summary() {
        return get_option(self::OPTION_KEY, array());
    }
}
