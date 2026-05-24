<?php
if (!defined('ABSPATH')) { exit; }

class UCP_CWV {
    const OPTION_KEY = 'ucp_cwv_metrics';
    const MAX_VALUE = 120000;
    const MAX_SAMPLES_PER_METRIC = 500;
    const MAX_DAILY_SAMPLES_PER_METRIC = 1000;
    const TOKEN_WINDOW_SECONDS = 604800;

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
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_key',
                ),
                'url' => array(
                    'required'          => false,
                    'sanitize_callback' => array($this, 'sanitize_local_url_param'),
                ),
                'device' => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_key',
                ),
                'lcp_url' => array(
                    'required'          => false,
                    'sanitize_callback' => array($this, 'sanitize_local_url_param'),
                ),
                'lcp_element_json' => array(
                    'required'          => false,
                    'sanitize_callback' => array($this, 'sanitize_lcp_element_json'),
                ),
                'lcp_imagesrcset' => array(
                    'required'          => false,
                    'sanitize_callback' => array($this, 'sanitize_lcp_srcset_param'),
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

        // AI-PATCH: CWV beacons are sent from cacheable frontend HTML; a WordPress nonce in that HTML expires while the page cache can still be warm.
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

    private function cwv_token($bucket = null) {
        $bucket = null === $bucket ? (int) floor(time() / self::TOKEN_WINDOW_SECONDS) : (int) $bucket;
        return hash_hmac('sha256', 'ucp-cwv|' . home_url('/') . '|' . $bucket, wp_salt('nonce'));
    }

    private function verify_beacon_token($token) {
        $token = sanitize_text_field((string) $token);
        if ('' === $token || 64 !== strlen($token)) {
            return false;
        }

        $bucket = (int) floor(time() / self::TOKEN_WINDOW_SECONDS);
        return hash_equals($this->cwv_token($bucket), $token) || hash_equals($this->cwv_token($bucket - 1), $token);
    }

    private function is_local_header_url($url) {
        $url = trim((string) $url);
        if ('' === $url) {
            return true;
        }

        $url_host = wp_parse_url($url, PHP_URL_HOST);
        $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        return $url_host && $home_host && strtolower((string) $url_host) === strtolower((string) $home_host);
    }

    public function print_rum_script() {
        if (is_admin() || empty(UCP_Options::get('enable_cwv_monitoring'))) { return; }
        $endpoint = esc_url_raw(rest_url('ultracache-pro/v1/cwv'));
        $token = $this->cwv_token(); ?>
<script id="ucp-cwv-monitor">
(function(){if(!('PerformanceObserver' in window)||!navigator.sendBeacon){return;}var endpoint=<?php echo wp_json_encode($endpoint); ?>;var token=<?php echo wp_json_encode($token); ?>;var sampleRate=0.25;if(Math.random()>sampleRate){return;}function device(){try{return matchMedia('(max-width: 767px)').matches?'mobile':'desktop'}catch(e){return 'all'}}function local(u){try{var x=new URL(u,location.href);return x.origin===location.origin?x.href:''}catch(e){return ''}}function lcpMeta(e){var el=e&&e.element?e.element:null,out={};try{if(el){out.tag=(el.tagName||'').toLowerCase();out.id=el.id||'';out.class=(el.className&&typeof el.className==='string')?el.className.slice(0,240):'';out.selector=out.id?'#'+out.id:(out.tag+(out.class?'.'+out.class.trim().split(/\s+/).slice(0,3).join('.'):''));}if(e&&e.url){out.url=local(e.url);}if(el&&el.currentSrc){out.url=local(el.currentSrc);}if(el&&el.srcset){out.srcset=String(el.srcset).slice(0,1200);}}catch(x){}return out}function send(name,value,rating,meta){try{var data=new FormData();data.append('metric',name);data.append('value',String(Math.round(value)));data.append('rating',rating||'');data.append('token',token);data.append('url',location.href.split('#')[0]);data.append('device',device());if(meta&&name==='LCP'){if(meta.url)data.append('lcp_url',meta.url);if(meta.srcset)data.append('lcp_imagesrcset',meta.srcset);data.append('lcp_element_json',JSON.stringify(meta));}navigator.sendBeacon(endpoint,data);}catch(e){}}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){if(e.name==='first-contentful-paint'){send('FCP',e.startTime,'info');}});}).observe({type:'paint',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){var meta=lcpMeta(e);send('LCP',e.startTime,e.startTime<2500?'good':(e.startTime<4000?'needs-improvement':'poor'),meta);});}).observe({type:'largest-contentful-paint',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){if(!e.hadRecentInput){send('CLS',e.value*1000,e.value<0.1?'good':(e.value<0.25?'needs-improvement':'poor'));}});}).observe({type:'layout-shift',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){send('INP',e.duration||0,(e.duration||0)<200?'good':((e.duration||0)<500?'needs-improvement':'poor'));});}).observe({type:'event',buffered:true,durationThreshold:40});}catch(e){}})();
</script><?php
    }

    public function record_metric($request) {
        $metric = strtoupper(sanitize_key($request->get_param('metric')));
        if (!$this->validate_metric($metric)) { return new WP_REST_Response(array('ok' => false), 400); }

        $value = (float) $request->get_param('value');
        if (!$this->validate_metric_value($value)) { return new WP_REST_Response(array('ok' => false), 400); }

        $visitor_key = $this->visitor_rate_key($metric);
        if (get_transient($visitor_key)) {
            return new WP_REST_Response(array('ok' => false, 'reason' => 'rate_limited'), 429);
        }

        $daily_key = $this->daily_rate_key($metric);
        $daily_count = absint(get_transient($daily_key));
        if ($daily_count >= self::MAX_DAILY_SAMPLES_PER_METRIC) {
            return new WP_REST_Response(array('ok' => false, 'reason' => 'daily_limit_reached'), 429);
        }

        set_transient($visitor_key, 1, MINUTE_IN_SECONDS);
        set_transient($daily_key, $daily_count + 1, DAY_IN_SECONDS);

        $data = get_option(self::OPTION_KEY, array());
        if (!is_array($data)) {
            $data = array();
        }
        if (empty($data[$metric]) || !is_array($data[$metric])) {
            $data[$metric] = array('count' => 0, 'sum' => 0, 'max' => 0, 'last' => 0, 'sample_rate' => 0.25);
        }

        $previous_count = absint($data[$metric]['count']);
        $previous_sum = (float) $data[$metric]['sum'];
        if ($previous_count >= self::MAX_SAMPLES_PER_METRIC) {
            $previous_average = $previous_count > 0 ? $previous_sum / $previous_count : 0;
            $data[$metric]['count'] = self::MAX_SAMPLES_PER_METRIC;
            $data[$metric]['sum'] = ($previous_average * (self::MAX_SAMPLES_PER_METRIC - 1)) + $value;
        } else {
            $data[$metric]['count'] = $previous_count + 1;
            $data[$metric]['sum'] = $previous_sum + $value;
        }
        $data[$metric]['max'] = max((float) $data[$metric]['max'], $value);
        $data[$metric]['last'] = time();
        $data[$metric]['sample_rate'] = 0.25;
        update_option(self::OPTION_KEY, $data, false);

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
     * Sanitize a same-origin URL supplied by the browser beacon.
     *
     * CWV and LCP hints are used for preload decisions. Keeping them local avoids
     * turning the public beacon into a third-party URL injection surface.
     *
     * @param mixed $url Raw URL.
     * @return string
     */
    public function sanitize_local_url_param($url) {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        $absolute = class_exists('UCP_Helpers') ? UCP_Helpers::strict_local_url($url) : esc_url_raw($url);
        if ('' === $absolute || !$this->is_local_header_url($absolute)) {
            return '';
        }

        return $absolute;
    }

    /**
     * Sanitize browser-provided LCP srcset metadata and keep only same-origin candidates.
     *
     * @param mixed $srcset Raw srcset.
     * @return string
     */
    public function sanitize_lcp_srcset_param($srcset) {
        return self::sanitize_lcp_srcset((string) $srcset);
    }

    /**
     * Sanitize compact LCP element metadata from the browser beacon.
     *
     * @param mixed $json Raw JSON string or array.
     * @return string JSON encoded safe metadata.
     */
    public function sanitize_lcp_element_json($json) {
        if (is_array($json)) {
            $decoded = $json;
        } else {
            $decoded = json_decode((string) $json, true);
        }
        if (!is_array($decoded)) {
            return '';
        }

        $allowed = array();
        foreach (array('tag', 'id', 'class', 'selector') as $key) {
            if (isset($decoded[$key])) {
                $allowed[$key] = substr(sanitize_text_field((string) $decoded[$key]), 0, 240);
            }
        }
        if (isset($decoded['url'])) {
            $allowed['url'] = $this->sanitize_local_url_param($decoded['url']);
        }
        if (isset($decoded['srcset'])) {
            $allowed['srcset'] = self::sanitize_lcp_srcset((string) $decoded['srcset']);
        }
        if (!empty($decoded['background'])) {
            $allowed['background'] = 1;
        }

        return $allowed ? wp_json_encode($allowed) : '';
    }


    /**
     * Sanitize an LCP hint URL for same-origin preload use.
     *
     * @param string $url Raw URL.
     * @return string
     */
    private static function sanitize_lcp_local_url($url) {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        if (class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'normalize_url_syntax')) {
            $url = UCP_Helpers::normalize_url_syntax($url);
        }

        $absolute = wp_parse_url($url, PHP_URL_HOST) ? $url : home_url('/' . ltrim($url, '/'));
        $absolute = esc_url_raw($absolute);
        if ('' === $absolute) {
            return '';
        }

        $url_host = wp_parse_url($absolute, PHP_URL_HOST);
        $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (!$url_host || !$home_host || strtolower((string) $url_host) !== strtolower((string) $home_host)) {
            return '';
        }

        return $absolute;
    }

    /**
     * Keep only same-origin candidates in browser-provided srcset metadata.
     *
     * @param string $srcset Raw srcset.
     * @return string
     */
    private static function sanitize_lcp_srcset($srcset) {
        $srcset = substr(sanitize_textarea_field((string) $srcset), 0, 1200);
        if ('' === trim($srcset)) {
            return '';
        }

        $safe = array();
        foreach (explode(',', $srcset) as $candidate) {
            $candidate = trim($candidate);
            if ('' === $candidate) {
                continue;
            }
            $parts = preg_split('/\s+/', $candidate, 2);
            $url = isset($parts[0]) ? self::sanitize_lcp_local_url($parts[0]) : '';
            if ('' === $url) {
                continue;
            }
            $descriptor = isset($parts[1]) ? preg_replace('/[^0-9\.wx\s-]/i', '', (string) $parts[1]) : '';
            $safe[] = trim($url . ('' !== trim((string) $descriptor) ? ' ' . trim((string) $descriptor) : ''));
        }

        return substr(implode(', ', $safe), 0, 1200);
    }

    /**
     * Persist a sanitized measured LCP hint for one URL/device pair.
     *
     * @param array<string,mixed> $data LCP hint data.
     * @return bool
     */
    public static function store_lcp_hint($data) {
        global $wpdb;

        if (!function_exists('ucp_table_name') || !isset($wpdb) || !is_object($wpdb)) {
            return false;
        }

        $table = ucp_table_name('lcp');
        if ('' === $table || !self::lcp_table_exists($table)) {
            return false;
        }

        $url = isset($data['url']) ? self::sanitize_lcp_local_url((string) $data['url']) : '';
        $lcp_url = isset($data['lcp_url']) ? self::sanitize_lcp_local_url((string) $data['lcp_url']) : '';
        if ('' === $url || '' === $lcp_url) {
            return false;
        }

        $device = isset($data['device']) ? sanitize_key((string) $data['device']) : 'all';
        if (!in_array($device, array('mobile', 'desktop', 'tablet', 'all'), true)) {
            $device = 'all';
        }

        $element_json = isset($data['lcp_element_json']) ? (string) $data['lcp_element_json'] : '';
        $element = json_decode($element_json, true);
        if (!is_array($element)) {
            $element = array();
        }
        $safe_element = array();
        foreach (array('tag', 'id', 'class', 'selector') as $key) {
            if (isset($element[$key])) {
                $safe_element[$key] = substr(sanitize_text_field((string) $element[$key]), 0, 240);
            }
        }
        if (isset($element['url'])) {
            $safe_element['url'] = self::sanitize_lcp_local_url((string) $element['url']);
        }
        if (!empty($element['background'])) {
            $safe_element['background'] = 1;
        }

        $srcset = isset($data['lcp_imagesrcset']) ? self::sanitize_lcp_srcset((string) $data['lcp_imagesrcset']) : '';
        $value_ms = isset($data['value_ms']) ? max(0, min((float) $data['value_ms'], (float) self::MAX_VALUE)) : 0;
        $url_hash = hash('sha256', $url);
        $now = current_time('mysql');

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, sample_count FROM {$table} WHERE url_hash = %s AND device = %s LIMIT 1",
                $url_hash,
                $device
            ),
            ARRAY_A
        );

        $payload = array(
            'url'              => $url,
            'lcp_element_json' => $safe_element ? wp_json_encode($safe_element) : '',
            'lcp_url'          => $lcp_url,
            'lcp_imagesrcset'  => $srcset,
            'value_ms'         => $value_ms,
            'last_measured'    => $now,
            'updated_at'       => $now,
        );

        if (is_array($existing) && !empty($existing['id'])) {
            $payload['sample_count'] = absint($existing['sample_count']) + 1;
            return false !== $wpdb->update(
                $table,
                $payload,
                array('id' => absint($existing['id'])),
                array('%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d'),
                array('%d')
            );
        }

        $payload['url_hash'] = $url_hash;
        $payload['device'] = $device;
        $payload['sample_count'] = 1;
        $payload['created_at'] = $now;

        return false !== $wpdb->insert(
            $table,
            $payload,
            array('%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * Get the most recent measured LCP hint for the current URL/device.
     *
     * @param string $url URL to look up.
     * @param string $device Device bucket.
     * @return array<string,mixed>
     */
    public static function lcp_hint_for_url($url, $device = 'all') {
        global $wpdb;

        if (!function_exists('ucp_table_name') || !isset($wpdb) || !is_object($wpdb)) {
            return array();
        }

        $table = ucp_table_name('lcp');
        if ('' === $table || !self::lcp_table_exists($table)) {
            return array();
        }

        $url = self::sanitize_lcp_local_url((string) $url);
        if ('' === $url) {
            return array();
        }

        $device = sanitize_key((string) $device);
        if (!in_array($device, array('mobile', 'desktop', 'tablet', 'all'), true)) {
            $device = 'all';
        }

        $url_hash = hash('sha256', $url);
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE url_hash = %s AND device = %s ORDER BY last_measured DESC LIMIT 1",
                $url_hash,
                $device
            ),
            ARRAY_A
        );

        if (!is_array($row) && 'all' !== $device) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE url_hash = %s AND device = %s ORDER BY last_measured DESC LIMIT 1",
                    $url_hash,
                    'all'
                ),
                ARRAY_A
            );
        }

        return is_array($row) ? $row : array();
    }



    public static function atf_hints_summary($limit = 20) {
        global $wpdb;
        $limit = max(1, min(100, absint($limit)));
        $out = array('total' => 0, 'recent' => array());
        if (!function_exists('ucp_table_name') || !isset($wpdb) || !is_object($wpdb)) {
            return $out;
        }
        $table = ucp_table_name('lcp');
        if ('' === $table || !self::lcp_table_exists($table)) {
            return $out;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned LCP diagnostics.
        $out['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned LCP diagnostics.
        $sql = $wpdb->prepare("SELECT url, device, lcp_url, value_ms, sample_count, last_measured FROM {$table} ORDER BY last_measured DESC LIMIT %d", $limit);
        $out['recent'] = $wpdb->get_results($sql, ARRAY_A);
        return $out;
    }

    /**
     * Check whether the LCP table exists without creating it during frontend requests.
     *
     * @param string $table Fully qualified table name from ucp_table_name().
     * @return bool
     */
    private static function lcp_table_exists($table) {
        global $wpdb;
        $table = (string) $table;
        if ('' === $table || !isset($wpdb) || !is_object($wpdb)) {
            return false;
        }

        $cache_key = 'ucp_lcp_table_exists_' . md5($table);
        $cached = wp_cache_get($cache_key, 'ultracache-pro');
        if (is_bool($cached)) {
            return $cached;
        }

        $like = $wpdb->esc_like($table);
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        $ok = is_string($exists) && strtolower($exists) === strtolower($table);
        wp_cache_set($cache_key, $ok, 'ultracache-pro', MINUTE_IN_SECONDS);
        return $ok;
    }

    private function daily_rate_key($metric) {
        return 'ucp_cwv_daily_' . gmdate('Ymd') . '_' . sanitize_key((string) $metric);
    }

    private function visitor_rate_key($metric) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        return 'ucp_cwv_rate_' . wp_hash($metric . '|' . $ip . '|' . substr($agent, 0, 120));
    }

    public static function summary() { return get_option(self::OPTION_KEY, array()); }
}
