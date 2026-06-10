<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Headless render bridge.
 *
 * This class keeps the heavy browser outside WordPress and only consumes a strict JSON contract.
 * It is intentionally fail-safe: bad, stale or untrusted renderer output is ignored and the local
 * CSS pipeline remains active instead of removing CSS blindly.
 */
class UCP_Render_Bridge {

    /** Transient prefix that records a fresh, trusted render result per URL. */
    const RESULT_PREFIX = 'ucp_render_bridge_';

    /** Option containing the last renderer status/test result. */
    const STATUS_OPTION = 'ucp_render_bridge_status';

    /** Current renderer response contract. */
    const CONTRACT_VERSION = '1.0';

    public function __construct() {
        add_filter('ucp_css_profile_external_result', array($this, 'authorise_profile'), 10, 6);
        add_action('admin_post_ucp_test_headless_renderer', array(__CLASS__, 'handle_admin_test'));
    }

    /**
     * Backward-compatible admin-post renderer test handler.
     *
     * The React admin uses the REST action, but older admin buttons may still call
     * admin-post.php?action=ucp_test_headless_renderer. Keep this route safe instead
     * of leaving it as a fatal undefined callback.
     *
     * @return void
     */
    public static function handle_admin_test() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }

        check_admin_referer('ucp_test_headless_renderer');

        $url = home_url('/');
        if (isset($_REQUEST['url'])) {
            $candidate = esc_url_raw(wp_unslash($_REQUEST['url']));
            if ('' !== $candidate) {
                $url = $candidate;
            }
        }

        $result = self::test_endpoint($url);
        $args = array(
            'ucp_renderer_test' => is_wp_error($result) ? 0 : 1,
        );

        if (is_wp_error($result)) {
            $args['ucp_renderer_message'] = sanitize_text_field($result->get_error_message());
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php?page=ultracache-pro&tab=tools')));
        exit;
    }

    /**
     * Whether the bridge is enabled and configured.
     *
     * @return bool
     */
    public static function is_active() {
        return (bool) UCP_Options::get('enable_headless_renderer') && '' !== self::endpoint();
    }

    /**
     * Resolve the configured, SSRF-validated renderer endpoint.
     *
     * @return string Empty string when not configured or unsafe.
     */
    protected static function endpoint() {
        return UCP_Helpers::validate_public_https_url(UCP_Options::get('headless_renderer_endpoint', ''));
    }

    /**
     * Lightweight status payload for admin/CLI/runtime checks.
     *
     * @return array
     */
    public static function status() {
        $status = get_option(self::STATUS_OPTION, array());
        $status = is_array($status) ? $status : array();
        $status['enabled'] = (bool) UCP_Options::get('enable_headless_renderer');
        $status['endpoint_configured'] = '' !== trim((string) UCP_Options::get('headless_renderer_endpoint', ''));
        $status['endpoint_valid'] = '' !== self::endpoint();
        $status['contract_version'] = self::CONTRACT_VERSION;
        return $status;
    }

    /**
     * Probe the renderer without writing CSS artifacts.
     *
     * @param string $url Optional local URL to render; defaults to home.
     * @return array|WP_Error
     */
    public static function test_endpoint($url = '') {
        if (!self::is_active()) {
            $error = new WP_Error('ucp_render_bridge_inactive', 'Headless renderer staat uit of het endpoint is ongeldig.');
            self::record_status('inactive', $error->get_error_message());
            return $error;
        }
        $url = UCP_Helpers::strict_local_url($url ? $url : home_url('/'), home_url('/'));
        if (!$url || !wp_http_validate_url($url)) {
            $error = new WP_Error('ucp_render_bridge_url', 'Niet-lokale of ongeldige test-URL geweigerd.');
            self::record_status('error', $error->get_error_message());
            return $error;
        }
        $response = self::request_renderer($url, array('action' => 'health_check', 'write_artifacts' => false), 25);
        if (is_wp_error($response)) {
            self::record_status('error', $response->get_error_message());
            return $response;
        }
        $validated = self::validate_response($response, $url, false);
        if (is_wp_error($validated)) {
            self::record_status('error', $validated->get_error_message());
            return $validated;
        }
        self::record_status('ok', 'Renderer bereikbaar en JSON-contract geldig.', array(
            'tested_url' => $url,
            'renderer' => isset($validated['renderer']) ? $validated['renderer'] : 'unknown',
            'response_contract' => isset($validated['contract_version']) ? $validated['contract_version'] : '',
        ));
        return self::status();
    }

    /**
     * Render a single URL through the headless service and persist the artifacts.
     * Called by the `headless_css` job type.
     *
     * @param string $url
     * @return bool|WP_Error
     */
    public static function render($url) {
        if (!self::is_active()) {
            return false;
        }
        $url = UCP_Helpers::strict_local_url($url, home_url('/'));
        if (!$url || !wp_http_validate_url($url)) {
            return new WP_Error('ucp_render_bridge_url', 'Niet-lokale of ongeldige URL geweigerd.');
        }

        $response = self::request_renderer($url, array(
            'action'               => 'render_css',
            'write_artifacts'      => true,
            'want_used_css'        => true,
            'want_critical_css'    => (bool) UCP_Options::get('enable_critical_css'),
            'want_viewport_images' => (bool) UCP_Options::get('enable_viewport_images'),
            'safelist'             => array_values(UCP_Helpers::normalize_multiline(UCP_Options::get('used_css_safelist', ''))),
        ));

        if (is_wp_error($response)) {
            self::record_status('error', $response->get_error_message(), array('url' => $url));
            UCP_Logger::log('warning', 'render_bridge', 'request_failed', 'Render-bridge HTTP-fout.', array('url' => $url, 'error' => $response->get_error_message()));
            return $response;
        }

        $data = self::validate_response($response, $url, true);
        if (is_wp_error($data)) {
            self::record_status('error', $data->get_error_message(), array('url' => $url));
            return $data;
        }

        $did_work = false;
        if (!empty($data['used_css'])) {
            UCP_Helpers::write_file(UCP_Helpers::get_used_css_path($url), (string) $data['used_css']);
            $did_work = true;
        }
        if (!empty($data['critical_css'])) {
            UCP_Helpers::write_file(UCP_Helpers::get_critical_css_path($url), (string) $data['critical_css']);
            $did_work = true;
        }

        $removable = array();
        if (!empty($data['safely_removable']) && is_array($data['safely_removable'])) {
            foreach ($data['safely_removable'] as $href) {
                $clean = self::normalize_stylesheet_url($href);
                if ('' !== $clean) {
                    $removable[] = $clean;
                }
            }
        }

        if ($did_work) {
            $artifact_version = substr(hash('sha256', (string) $data['used_css'] . '|' . (string) $data['critical_css'] . '|' . implode(',', $removable)), 0, 16);
            set_transient(self::result_key($url), array(
                'ready'            => 1,
                'removable'        => array_values(array_unique($removable)),
                'generated'        => time(),
                'contract_version' => self::CONTRACT_VERSION,
                'artifact_version' => $artifact_version,
            ), self::ttl());
            self::record_status('ok', 'Headless render gereed.', array('url' => $url, 'artifact_version' => $artifact_version, 'removable' => count($removable)));
            UCP_Logger::log('info', 'render_bridge', 'render_ok', 'Headless render gereed.', array('url' => $url, 'removable' => count($removable)));
        }

        /**
         * Fires after a headless render completes, exposing the full renderer response.
         *
         * @param string $url
         * @param array  $data
         */
        do_action('ucp_render_result', $url, $data);
        return $did_work;
    }

    /**
     * Feed a trusted, fresh render result into the CSS profile so it may authorise safe removal.
     *
     * @param array  $external Existing external result (from other filters).
     * @param string $url
     * @param string $html
     * @param array  $profile
     * @param string $used_css
     * @param string $critical_css
     * @return array
     */
    public function authorise_profile($external, $url, $html, $profile, $used_css, $critical_css) {
        if (!is_array($external)) {
            $external = array();
        }
        if (!self::is_active()) {
            return $external;
        }
        $result = get_transient(self::result_key($url));
        if (!is_array($result) || empty($result['ready']) || empty($result['contract_version'])) {
            return $external;
        }

        $external['renderer']        = 'headless_chrome_bridge';
        $external['renderer_ready']  = 1;
        $external['renderer_status'] = 'ready';
        $external['artifact_version'] = isset($result['artifact_version']) ? sanitize_key((string) $result['artifact_version']) : '';

        if (!empty($result['removable']) && is_array($result['removable'])) {
            $removable = array();
            foreach ($result['removable'] as $href) {
                $clean = self::normalize_stylesheet_url($href);
                if ('' === $clean) {
                    continue;
                }
                $removable[] = array(
                    'href'           => $clean,
                    'classification' => 'safely_removable_css',
                    'reason'         => 'headless_render_confirmed_unused',
                );
            }
            if (!empty($removable)) {
                $external['safely_removable_css'] = $removable;
            }
        }
        return $external;
    }

    /**
     * Job-queue entry point. Wired into UCP_Jobs run_job() via the 'headless_css' type.
     *
     * @param string $url
     * @return bool|WP_Error
     */
    public static function run_job($url) {
        return self::render($url);
    }

    /**
     * Low-level renderer request wrapper.
     *
     * @param string $url
     * @param array  $payload
     * @param int    $timeout
     * @return array|WP_Error Decoded response array or WP_Error.
     */
    protected static function request_renderer($url, $payload, $timeout = 0) {
        $endpoint = self::endpoint();
        if ('' === $endpoint) {
            return new WP_Error('ucp_render_bridge_endpoint', 'Render-endpoint niet geconfigureerd of onveilig.');
        }

        $token = trim(str_replace(array("\r", "\n"), '', (string) UCP_Options::get('headless_renderer_token', '')));
        $headers = array('Content-Type' => 'application/json', 'Accept' => 'application/json');
        if ('' !== $token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $body = array_merge(array(
            'contract_version' => self::CONTRACT_VERSION,
            'plugin'           => 'ultracache-pro',
            'plugin_version'   => defined('UCP_VERSION') ? UCP_VERSION : 'dev',
            'url'              => esc_url_raw($url),
            'viewport'         => wp_is_mobile() ? 'mobile' : 'desktop',
        ), is_array($payload) ? $payload : array());

        $response = wp_remote_post($endpoint, UCP_Helpers::default_remote_args(array(
            'timeout'             => $timeout > 0 ? absint($timeout) : self::timeout(),
            'limit_response_size' => self::max_response_bytes(),
            'sslverify'           => true,
            'user-agent'          => 'UltraCache Render Bridge/' . (defined('UCP_VERSION') ? UCP_VERSION : 'dev'),
            'headers'             => $headers,
            'body'                => wp_json_encode($body),
        )));

        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('ucp_render_bridge_http', 'Render-bridge HTTP ' . $code);
        }
        $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        if ('' !== $content_type && false === strpos($content_type, 'application/json') && false === strpos($content_type, '+json')) {
            return new WP_Error('ucp_render_bridge_content_type', 'Render-bridge gaf geen JSON content-type terug.');
        }
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            return new WP_Error('ucp_render_bridge_json', 'Render-bridge gaf geen geldige JSON terug.');
        }
        return $data;
    }

    /**
     * Strict response validation. Keeps WordPress fail-safe when the external renderer misbehaves.
     *
     * @param array  $data
     * @param string $url
     * @param bool   $require_css
     * @return array|WP_Error
     */
    protected static function validate_response($data, $url, $require_css) {
        if (!is_array($data)) {
            return new WP_Error('ucp_render_bridge_response', 'Renderer-response is geen object.');
        }
        if (isset($data['ok']) && false === (bool) $data['ok']) {
            return new WP_Error('ucp_render_bridge_not_ok', isset($data['message']) ? sanitize_text_field((string) $data['message']) : 'Renderer gaf ok=false terug.');
        }
        if (isset($data['contract_version']) && '' !== (string) $data['contract_version'] && 0 !== strpos((string) $data['contract_version'], '1.')) {
            return new WP_Error('ucp_render_bridge_contract', 'Renderer-contractversie wordt niet ondersteund.');
        }
        if (isset($data['url'])) {
            $returned = UCP_Helpers::strict_local_url((string) $data['url'], home_url('/'));
            if (!$returned || untrailingslashit($returned) !== untrailingslashit($url)) {
                return new WP_Error('ucp_render_bridge_url_mismatch', 'Renderer gaf een andere URL terug dan gevraagd.');
            }
        }

        foreach (array('used_css' => 400000, 'critical_css' => 120000) as $field => $max) {
            if (!isset($data[$field]) || '' === (string) $data[$field]) {
                $data[$field] = '';
                continue;
            }
            if (!self::valid_css((string) $data[$field], $max)) {
                return new WP_Error('ucp_render_bridge_bad_' . $field, 'Renderer gaf onveilige of te grote CSS terug: ' . $field . '.');
            }
        }

        if ($require_css && '' === $data['used_css'] && '' === $data['critical_css']) {
            return new WP_Error('ucp_render_bridge_empty_artifacts', 'Renderer gaf geen bruikbare CSS-artifacts terug.');
        }

        if (isset($data['safely_removable']) && !is_array($data['safely_removable'])) {
            return new WP_Error('ucp_render_bridge_removable_shape', 'safely_removable moet een lijst zijn.');
        }
        if (isset($data['viewport_images']) && !is_array($data['viewport_images'])) {
            return new WP_Error('ucp_render_bridge_vpi_shape', 'viewport_images moet een lijst zijn.');
        }
        $data['contract_version'] = isset($data['contract_version']) ? sanitize_text_field((string) $data['contract_version']) : self::CONTRACT_VERSION;
        $data['renderer'] = isset($data['renderer']) ? sanitize_key((string) $data['renderer']) : 'headless_renderer';
        return $data;
    }

    protected static function result_key($url) {
        return self::RESULT_PREFIX . md5(strtolower((string) $url) . '|' . (wp_is_mobile() ? 'm' : 'd'));
    }

    protected static function ttl() {
        $days = max(1, absint(UCP_Options::get('used_css_auto_refresh_days', 30)));
        return $days * DAY_IN_SECONDS;
    }

    protected static function timeout() {
        return max(10, min(90, absint(UCP_Options::get('headless_renderer_timeout', 45))));
    }

    protected static function max_response_bytes() {
        return max(262144, min(5242880, absint(UCP_Options::get('headless_renderer_max_response_bytes', 2097152))));
    }

    protected static function valid_css($css, $max_bytes) {
        if (!is_string($css) || '' === trim($css) || strlen($css) > absint($max_bytes)) {
            return false;
        }
        return !preg_match('/<\/?(?:script|style|html|body)\b|<\?(?:php|=)/i', $css);
    }

    protected static function normalize_stylesheet_url($href) {
        $href = esc_url_raw((string) $href);
        if ('' === $href) {
            return '';
        }
        if (0 === strpos($href, '//')) {
            $href = (is_ssl() ? 'https:' : 'http:') . $href;
        }
        if (0 === strpos($href, '/')) {
            $href = home_url($href);
        }
        return esc_url_raw($href);
    }

    protected static function record_status($state, $message = '', $extra = array()) {
        $payload = array_merge(array(
            'state' => sanitize_key((string) $state),
            'message' => sanitize_text_field((string) $message),
            'checked_at' => current_time('mysql', true),
            'endpoint' => self::endpoint(),
            'contract_version' => self::CONTRACT_VERSION,
        ), is_array($extra) ? $extra : array());
        update_option(self::STATUS_OPTION, $payload, false);
    }
}
