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
        UCP_Helpers::require_post_admin_action('ucp_test_headless_renderer');

        $url = home_url('/');
        $candidate = esc_url_raw(UCP_Helpers::request_scalar('url', '', 2048));
        if ('' !== $candidate) {
            $url = $candidate;
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
            $error = new WP_Error('ucp_render_bridge_inactive', __('Headless renderer staat uit of het endpoint is ongeldig.', 'ultracache-pro'));
            self::record_status('inactive', $error->get_error_message());
            return $error;
        }
        $url = UCP_Helpers::strict_local_url($url ? $url : home_url('/'), home_url('/'));
        if (!$url || !wp_http_validate_url($url)) {
            $error = new WP_Error('ucp_render_bridge_url', __('Niet-lokale of ongeldige test-URL geweigerd.', 'ultracache-pro'));
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
            return new WP_Error('ucp_render_bridge_url', __('Niet-lokale of ongeldige URL geweigerd.', 'ultracache-pro'));
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
            UCP_Logger::log('warning', 'render_bridge', 'request_failed', __('Renderbridge gaf een HTTP-fout.', 'ultracache-pro'), array('url' => $url, 'error' => $response->get_error_message()));
            return $response;
        }

        $data = self::validate_response($response, $url, true);
        if (is_wp_error($data)) {
            self::record_status('error', $data->get_error_message(), array('url' => $url));
            return $data;
        }

        $used_css = !empty($data['used_css']) ? (string) $data['used_css'] : '';
        $critical_css = !empty($data['critical_css']) ? (string) $data['critical_css'] : '';
        $did_work = '' !== $used_css && class_exists('UCP_CSS') && UCP_CSS::persist_artifacts($url, $used_css, $critical_css);
        if (!$did_work) {
            $error = new WP_Error('ucp_render_bridge_persist', __('Render-bridge CSS-artifacts konden niet transactioneel worden opgeslagen.', 'ultracache-pro'));
            self::record_status('error', $error->get_error_message(), array('url' => $url));
            return $error;
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
            self::record_status('ok', __('Headless-render is gereed.', 'ultracache-pro'), array('url' => $url, 'artifact_version' => $artifact_version, 'removable' => count($removable)));
            UCP_Logger::log('info', 'render_bridge', 'render_ok', __('Headless-render is gereed.', 'ultracache-pro'), array('url' => $url, 'removable' => count($removable)));
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
            return new WP_Error('ucp_render_bridge_endpoint', __('Render-endpoint niet geconfigureerd of onveilig.', 'ultracache-pro'));
        }

        $token = trim(str_replace(array("\r", "\n"), '', (string) UCP_Options::get('headless_renderer_token', '')));
        if (strlen($token) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $token)) {
            return new WP_Error('ucp_render_bridge_token', __('Render-token is ongeldig.', 'ultracache-pro'));
        }
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

        $encoded_body = UCP_Helpers::safe_json_encode($body);
        if (!is_string($encoded_body) || '' === $encoded_body) {
            return new WP_Error('ucp_render_bridge_request_json', __('Render-aanvraag kon niet veilig als JSON worden opgebouwd.', 'ultracache-pro'));
        }

        $response = wp_remote_post($endpoint, UCP_Helpers::default_remote_args(array(
            'timeout'             => $timeout > 0 ? absint($timeout) : self::timeout(),
            'limit_response_size' => self::max_response_bytes(),
            'sslverify'           => true,
            'user-agent'          => 'UltraCache Render Bridge/' . (defined('UCP_VERSION') ? UCP_VERSION : 'dev'),
            'headers'             => $headers,
            'body'                => $encoded_body,
        )));

        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('ucp_render_bridge_http', sprintf(__('Render-bridge HTTP %d.', 'ultracache-pro'), $code));
        }
        $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        if ('' !== $content_type && false === strpos($content_type, 'application/json') && false === strpos($content_type, '+json')) {
            return new WP_Error('ucp_render_bridge_content_type', __('Render-bridge gaf geen JSON content-type terug.', 'ultracache-pro'));
        }
        $response_body = UCP_Helpers::bounded_remote_response_body($response, self::max_response_bytes());
        if (false === $response_body) {
            return new WP_Error('ucp_render_bridge_truncated', __('Render-bridge antwoord is te groot of mogelijk afgekapt.', 'ultracache-pro'));
        }
        $data = UCP_Helpers::safe_json_decode($response_body, true);
        if (!is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            return new WP_Error('ucp_render_bridge_json', __('Render-bridge gaf geen geldige JSON terug.', 'ultracache-pro'));
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
            return new WP_Error('ucp_render_bridge_response', __('Renderer-response is geen object.', 'ultracache-pro'));
        }
        if (isset($data['ok'])) {
            if (!is_bool($data['ok'])) {
                return new WP_Error('ucp_render_bridge_ok_type', __('Renderer-veld ok moet een boolean zijn.', 'ultracache-pro'));
            }
            if (false === $data['ok']) {
                $message = isset($data['message']) && is_scalar($data['message'])
                    ? sanitize_text_field((string) $data['message'])
                    : __('Renderer gaf ok=false terug.', 'ultracache-pro');
                return new WP_Error('ucp_render_bridge_not_ok', $message);
            }
        }
        if (!isset($data['contract_version']) || !is_scalar($data['contract_version'])) {
            return new WP_Error('ucp_render_bridge_contract_missing', __('Renderer-contractversie ontbreekt.', 'ultracache-pro'));
        }
        $contract_version = trim((string) $data['contract_version']);
        if ('' === $contract_version) {
            return new WP_Error('ucp_render_bridge_contract_missing', __('Renderer-contractversie ontbreekt.', 'ultracache-pro'));
        }
        if (1 !== preg_match('/^1\.[0-9]+$/', $contract_version)) {
            return new WP_Error('ucp_render_bridge_contract', __('Renderer-contractversie wordt niet ondersteund.', 'ultracache-pro'));
        }
        if (isset($data['url'])) {
            if (!is_scalar($data['url'])) {
                return new WP_Error('ucp_render_bridge_url_mismatch', __('Renderer gaf een andere URL terug dan gevraagd.', 'ultracache-pro'));
            }
            $returned = self::canonical_local_url((string) $data['url']);
            $requested = self::canonical_local_url($url);
            if ('' === $returned || '' === $requested || $returned !== $requested) {
                return new WP_Error('ucp_render_bridge_url_mismatch', __('Renderer gaf een andere URL terug dan gevraagd.', 'ultracache-pro'));
            }
        }

        foreach (array('used_css' => 400000, 'critical_css' => 120000) as $field => $max) {
            if (!isset($data[$field]) || '' === $data[$field]) {
                $data[$field] = '';
                continue;
            }
            if (!is_string($data[$field]) || !self::valid_css($data[$field], $max)) {
                return new WP_Error('ucp_render_bridge_bad_' . $field, sprintf(__('Renderer gaf onveilige of te grote CSS terug: %s.', 'ultracache-pro'), $field));
            }
        }

        if ($require_css && '' === $data['used_css'] && '' === $data['critical_css']) {
            return new WP_Error('ucp_render_bridge_empty_artifacts', __('Renderer gaf geen bruikbare CSS-artifacts terug.', 'ultracache-pro'));
        }

        if (isset($data['safely_removable'])) {
            if (!is_array($data['safely_removable']) || count($data['safely_removable']) > 500) {
                return new WP_Error('ucp_render_bridge_removable_shape', __('safely_removable moet een begrensde lijst zijn.', 'ultracache-pro'));
            }
            $clean_removable = array();
            foreach ($data['safely_removable'] as $href) {
                if (!is_scalar($href) || strlen((string) $href) > 2048) {
                    return new WP_Error('ucp_render_bridge_removable_item', __('safely_removable bevat een ongeldig item.', 'ultracache-pro'));
                }
                $clean = self::normalize_stylesheet_url((string) $href);
                if ('' !== $clean) {
                    $clean_removable[$clean] = $clean;
                }
            }
            $data['safely_removable'] = array_values($clean_removable);
        } else {
            $data['safely_removable'] = array();
        }
        if (isset($data['viewport_images'])) {
            if (!is_array($data['viewport_images']) || count($data['viewport_images']) > 500) {
                return new WP_Error('ucp_render_bridge_vpi_shape', __('viewport_images moet een begrensde lijst zijn.', 'ultracache-pro'));
            }
            $clean_viewport_images = array();
            foreach ($data['viewport_images'] as $viewport_image) {
                if (!is_scalar($viewport_image) || strlen((string) $viewport_image) > 2048) {
                    return new WP_Error('ucp_render_bridge_vpi_item', __('viewport_images bevat een ongeldig item.', 'ultracache-pro'));
                }
                $clean = esc_url_raw(trim((string) $viewport_image), array('http', 'https'));
                $scheme = strtolower((string) wp_parse_url($clean, PHP_URL_SCHEME));
                if ('' === $clean || !in_array($scheme, array('http', 'https'), true)) {
                    return new WP_Error('ucp_render_bridge_vpi_item', __('viewport_images bevat een ongeldig item.', 'ultracache-pro'));
                }
                $clean_viewport_images[$clean] = $clean;
            }
            $data['viewport_images'] = array_values($clean_viewport_images);
        } else {
            $data['viewport_images'] = array();
        }
        $data['contract_version'] = sanitize_text_field($contract_version);
        $renderer = isset($data['renderer']) && is_scalar($data['renderer']) && strlen((string) $data['renderer']) <= 100
            ? sanitize_key((string) $data['renderer'])
            : '';
        $data['renderer'] = '' !== $renderer ? $renderer : 'headless_renderer';
        return $data;
    }

    protected static function result_key($url) {
        // URL paths and query values can be case-sensitive. Normalize only through the
        // configured local origin and preserve the remaining case to avoid cross-page results.
        $canonical = UCP_Helpers::strict_local_url((string) $url);
        if ('' === $canonical) {
            $canonical = 'invalid:' . hash('sha256', (string) $url);
        }
        return self::RESULT_PREFIX . md5($canonical . '|' . (wp_is_mobile() ? 'm' : 'd'));
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

    protected static function canonical_local_url($url) {
        $url = UCP_Helpers::strict_local_url((string) $url, home_url('/'));
        if ('' === $url) {
            return '';
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return '';
        }
        $path = isset($parts['path']) && '' !== $parts['path'] ? $parts['path'] : '/';
        $query = isset($parts['query']) && '' !== $parts['query'] ? '?' . $parts['query'] : '';
        return UCP_Helpers::strict_local_url($path . $query, home_url('/'));
    }

    protected static function normalize_stylesheet_url($href) {
        if (!is_scalar($href)) {
            return '';
        }
        $href = trim((string) $href);
        if ('' === $href || strlen($href) > 2048 || false !== strpos($href, '#')) {
            return '';
        }
        $local = UCP_Helpers::strict_local_url($href, home_url('/'));
        if ('' === $local) {
            return '';
        }
        $path = (string) wp_parse_url($local, PHP_URL_PATH);
        if ('' === $path || !preg_match('/\.css$/i', $path)) {
            return '';
        }
        return esc_url_raw($local);
    }

    protected static function record_status($state, $message = '', $extra = array()) {
        $allowed_extra = array();
        foreach (array('url', 'tested_url', 'renderer', 'response_contract', 'artifact_version', 'removable') as $key) {
            if (!is_array($extra) || !array_key_exists($key, $extra) || (!is_scalar($extra[$key]) && null !== $extra[$key])) {
                continue;
            }
            if ('removable' === $key) {
                $allowed_extra[$key] = absint($extra[$key]);
            } elseif (in_array($key, array('url', 'tested_url'), true)) {
                $allowed_extra[$key] = esc_url_raw((string) $extra[$key]);
            } else {
                $allowed_extra[$key] = sanitize_text_field((string) $extra[$key]);
            }
        }
        $payload = array_merge($allowed_extra, array(
            'state' => sanitize_key((string) $state),
            'message' => sanitize_text_field((string) $message),
            'checked_at' => current_time('mysql', true),
            'endpoint' => self::endpoint(),
            'contract_version' => self::CONTRACT_VERSION,
        ));
        update_option(self::STATUS_OPTION, $payload, false);
    }
}
