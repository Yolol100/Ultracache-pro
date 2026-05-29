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
                    'type'              => 'string',
                    'required'          => false,
                    'maxLength'         => 2048,
                    'sanitize_callback' => array($this, 'sanitize_lcp_element_json'),
                ),
                'lcp_imagesrcset' => array(
                    'required'          => false,
                    'sanitize_callback' => array($this, 'sanitize_lcp_srcset_param'),
                ),
                'token' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
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
        return '' === $url || self::is_same_origin_url($url);
    }

    private static function default_port_for_scheme($scheme) {
        if ('https' === $scheme) {
            return 443;
        }

        if ('http' === $scheme) {
            return 80;
        }

        return 0;
    }

    public function print_rum_script() {
        if (is_admin() || empty(UCP_Options::get('enable_cwv_monitoring'))) {
            return;
        }

        $endpoint = esc_url_raw(rest_url('ultracache-pro/v1/cwv'));
        $token = $this->cwv_token(); ?>
<script id="ucp-cwv-monitor">
(function(){if(!('PerformanceObserver' in window)||!navigator.sendBeacon){return;}var endpoint=<?php echo wp_json_encode($endpoint); ?>;var token=<?php echo wp_json_encode($token); ?>;var sampleRate=0.25;if(Math.random()>sampleRate){return;}function device(){try{return matchMedia('(max-width: 767px)').matches?'mobile':'desktop'}catch(e){return 'all'}}function local(u){try{var x=new URL(u,location.href);return x.origin===location.origin?x.href:''}catch(e){return ''}}function lcpMeta(e){var el=e&&e.element?e.element:null,out={};try{if(el){out.tag=(el.tagName||'').toLowerCase();out.id=el.id||'';out.class=(el.className&&typeof el.className==='string')?el.className.slice(0,240):'';out.selector=out.id?'#'+out.id:(out.tag+(out.class?'.'+out.class.trim().split(/\s+/).slice(0,3).join('.'):''));}if(e&&e.url){out.url=local(e.url);}if(el&&el.currentSrc){out.url=local(el.currentSrc);}if(el&&el.srcset){out.srcset=String(el.srcset).slice(0,1200);}if(el&&el.sizes){out.sizes=String(el.sizes).slice(0,240);}if(!out.url&&el){try{var bg=getComputedStyle(el).backgroundImage||'';var m=bg.match(/url\(["']?([^"')]+)["']?\)/);if(m&&m[1]){out.url=local(m[1]);out.background=1;}}catch(y){}}}catch(x){}return out}function send(name,value,rating,meta){try{var data=new FormData();data.append('metric',name);data.append('value',String(Math.round(value)));data.append('rating',rating||'');data.append('token',token);data.append('url',location.href.split('#')[0]);data.append('device',device());if(meta&&name==='LCP'){if(meta.url)data.append('lcp_url',meta.url);if(meta.srcset)data.append('lcp_imagesrcset',meta.srcset);data.append('lcp_element_json',JSON.stringify(meta));}navigator.sendBeacon(endpoint,data);}catch(e){}}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){if(e.name==='first-contentful-paint'){send('FCP',e.startTime,'info');}});}).observe({type:'paint',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){var meta=lcpMeta(e);send('LCP',e.startTime,e.startTime<2500?'good':(e.startTime<4000?'needs-improvement':'poor'),meta);});}).observe({type:'largest-contentful-paint',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){if(!e.hadRecentInput){send('CLS',e.value*1000,e.value<0.1?'good':(e.value<0.25?'needs-improvement':'poor'));}});}).observe({type:'layout-shift',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){send('INP',e.duration||0,(e.duration||0)<200?'good':((e.duration||0)<500?'needs-improvement':'poor'));});}).observe({type:'event',buffered:true,durationThreshold:40});}catch(e){}})();
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

        if (!$this->bump_rate_counter($this->visitor_rate_key($metric), 1, MINUTE_IN_SECONDS)) {
            return new WP_REST_Response(array('ok' => false, 'reason' => 'rate_limited'), 429);
        }

        if (!$this->bump_rate_counter($this->daily_rate_key($metric), self::MAX_DAILY_SAMPLES_PER_METRIC, DAY_IN_SECONDS)) {
            return new WP_REST_Response(array('ok' => false, 'reason' => 'daily_limit_reached'), 429);
        }

        if (!$this->bump_rate_counter($this->ip_minute_rate_key(), self::MAX_IP_SAMPLES_PER_MINUTE, MINUTE_IN_SECONDS)) {
            return new WP_REST_Response(array('ok' => false, 'reason' => 'ip_rate_limited'), 429);
        }

        if (!$this->bump_rate_counter($this->site_minute_rate_key(), self::MAX_SITE_SAMPLES_PER_MINUTE, MINUTE_IN_SECONDS)) {
            return new WP_REST_Response(array('ok' => false, 'reason' => 'site_rate_limited'), 429);
        }

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
        if ('' === $url || strlen($url) > 2048) {
            return '';
        }

        $absolute = UCP_Helpers::strict_local_url($url);
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
        $raw = is_array($json) ? wp_json_encode($json) : (string) $json;

        if (!is_string($raw) || '' === $raw || strlen($raw) > 2048) {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }

        $allowed = array();
        foreach (array('tag', 'id', 'class', 'selector', 'sizes') as $key) {
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

        if (!self::is_same_origin_url($absolute)) {
            return '';
        }

        return $absolute;
    }

    /**
     * Check whether a URL matches the configured site origin.
     *
     * @param string $url Absolute URL.
     * @return bool
     */
    private static function is_same_origin_url($url) {
        $url_parts = wp_parse_url($url);
        $home_parts = wp_parse_url(home_url('/'));

        if (!is_array($url_parts) || !is_array($home_parts)) {
            return false;
        }

        $url_host = isset($url_parts['host']) ? strtolower((string) $url_parts['host']) : '';
        $home_host = isset($home_parts['host']) ? strtolower((string) $home_parts['host']) : '';
        $url_scheme = isset($url_parts['scheme']) ? strtolower((string) $url_parts['scheme']) : '';
        $home_scheme = isset($home_parts['scheme']) ? strtolower((string) $home_parts['scheme']) : '';
        $url_port = isset($url_parts['port']) ? absint($url_parts['port']) : self::default_port_for_scheme($url_scheme);
        $home_port = isset($home_parts['port']) ? absint($home_parts['port']) : self::default_port_for_scheme($home_scheme);

        return '' !== $url_host
            && '' !== $home_host
            && '' !== $url_scheme
            && '' !== $home_scheme
            && $url_host === $home_host
            && $url_scheme === $home_scheme
            && $url_port === $home_port;
    }

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
        if ('' === $table || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table) || !self::lcp_table_exists($table)) {
            return false;
        }
        $table_sql = UCP_Helpers::quote_table_name($table);

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
        foreach (array('tag', 'id', 'class', 'selector', 'sizes') as $key) {
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

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned LCP table identifier is validated before quoting.
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, sample_count FROM {$table_sql} WHERE url_hash = %s AND device = %s LIMIT 1",
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
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned LCP table write.
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned LCP table write.
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
        if ('' === $table || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table) || !self::lcp_table_exists($table)) {
            return array();
        }
        $table_sql = UCP_Helpers::quote_table_name($table);

        $url = self::sanitize_lcp_local_url((string) $url);
        if ('' === $url) {
            return array();
        }

        $device = sanitize_key((string) $device);
        if (!in_array($device, array('mobile', 'desktop', 'tablet', 'all'), true)) {
            $device = 'all';
        }

        $url_hash = hash('sha256', $url);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned LCP table identifier is validated before quoting.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_sql} WHERE url_hash = %s AND device = %s ORDER BY last_measured DESC LIMIT 1",
                $url_hash,
                $device
            ),
            ARRAY_A
        );

        if (!is_array($row) && 'all' !== $device) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned LCP table identifier is validated before quoting.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_sql} WHERE url_hash = %s AND device = %s ORDER BY last_measured DESC LIMIT 1",
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
        if ('' === $table || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table) || !self::lcp_table_exists($table)) {
            return $out;
        }
        $table_sql = UCP_Helpers::quote_table_name($table);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned LCP diagnostics with validated table identifier.
        $out['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_sql}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned LCP diagnostics with validated table identifier.
        $sql = $wpdb->prepare("SELECT url, device, lcp_url, value_ms, sample_count, last_measured FROM {$table_sql} ORDER BY last_measured DESC LIMIT %d", $limit);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is prepared above with a validated table identifier and integer LIMIT placeholder.
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
        if ('' === $table || !isset($wpdb) || !is_object($wpdb) || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table)) {
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

    private function bump_rate_counter($key, $limit, $ttl) {
        $key = sanitize_key((string) $key);
        $limit = max(1, absint($limit));
        $ttl = max(1, absint($ttl));

        if ('' === $key) {
            return false;
        }

        $lock_key = '_ucp_lock_' . $key;
        $now = time();
        $locked = add_option($lock_key, $now, '', 'no');

        if (!$locked) {
            $created = absint(get_option($lock_key));
            if ($created && $created < ($now - 5)) {
                delete_option($lock_key);
                $locked = add_option($lock_key, $now, '', 'no');
            }
        }

        if (!$locked) {
            return false;
        }

        try {
            $count = absint(get_transient($key));
            if ($count >= $limit) {
                return false;
            }

            if (!set_transient($key, $count + 1, $ttl)) {
                return false;
            }

            return true;
        } finally {
            delete_option($lock_key);
        }
    }

    private function site_minute_rate_key() {
        return 'ucp_cwv_site_' . gmdate('YmdHi');
    }

    private function ip_minute_rate_key() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        return 'ucp_cwv_ip_' . wp_hash($ip . '|' . gmdate('YmdHi'));
    }

    private function daily_rate_key($metric) {
        return 'ucp_cwv_daily_' . gmdate('Ymd') . '_' . sanitize_key((string) $metric);
    }

    private function visitor_rate_key($metric) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        return 'ucp_cwv_rate_' . wp_hash($metric . '|' . $ip . '|' . substr($agent, 0, 120));
    }

    public static function summary() {
        return get_option(self::OPTION_KEY, array());
    }
}
