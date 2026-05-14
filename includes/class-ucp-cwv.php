<?php
if (!defined('ABSPATH')) { exit; }

class UCP_CWV {
    const OPTION_KEY = 'ucp_cwv_metrics';
    const MAX_VALUE = 120000;
    const MAX_SAMPLES_PER_METRIC = 500;
    const MAX_DAILY_SAMPLES_PER_METRIC = 1000;

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
        if (!$this->is_local_header_url($origin) || (!$this->is_local_header_url($referer) && '' !== $referer)) {
            return false;
        }

        $nonce = $request instanceof WP_REST_Request ? (string) $request->get_param('_wpnonce') : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'ucp_cwv')) {
            return false;
        }

        return true;
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
        $nonce = wp_create_nonce('ucp_cwv'); ?>
<script id="ucp-cwv-monitor">
(function(){if(!('PerformanceObserver' in window)||!navigator.sendBeacon){return;}var endpoint=<?php echo wp_json_encode($endpoint); ?>;var nonce=<?php echo wp_json_encode($nonce); ?>;var sampleRate=0.25;if(Math.random()>sampleRate){return;}function send(name,value,rating){try{var data=new FormData();data.append('metric',name);data.append('value',String(Math.round(value)));data.append('rating',rating||'');data.append('_wpnonce',nonce);navigator.sendBeacon(endpoint,data);}catch(e){}}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){if(e.name==='first-contentful-paint'){send('FCP',e.startTime,'info');}});}).observe({type:'paint',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){send('LCP',e.startTime,e.startTime<2500?'good':(e.startTime<4000?'needs-improvement':'poor'));});}).observe({type:'largest-contentful-paint',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){if(!e.hadRecentInput){send('CLS',e.value*1000,e.value<0.1?'good':(e.value<0.25?'needs-improvement':'poor'));}});}).observe({type:'layout-shift',buffered:true});}catch(e){}try{new PerformanceObserver(function(list){list.getEntries().forEach(function(e){send('INP',e.duration||0,(e.duration||0)<200?'good':((e.duration||0)<500?'needs-improvement':'poor'));});}).observe({type:'event',buffered:true,durationThreshold:40});}catch(e){}})();
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

        return new WP_REST_Response(array('ok' => true), 202);
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
