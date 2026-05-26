<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cloud_Routes_Trait {
    public function register_routes() {
        register_rest_route('ultracache-pro/v1', '/cloud/status', array(
            'methods' => 'GET',
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => array($this, 'status'),
        ));
    }

    public function status() {
        return rest_ensure_response(array(
            'enabled'   => (bool) UCP_Options::get('enable_cloud'),
            'connected' => self::has_valid_endpoint() && !empty(UCP_Options::get('cloud_api_key')),
            'queue'     => UCP_Jobs::get_summary(),
        ));
    }

    public function handle_manual_sync() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_cloud_sync');
        $result = self::push_site_payload();
        wp_safe_redirect(UCP_Admin_Router::url('expert', array('cloud_sync' => ($result ? '1' : '0'))));
        exit;
    }
}

trait UCP_Cloud_CSS_Trait {
    public static function push_site_payload() {
        if (!UCP_Options::get('enable_cloud')) {
            return false;
        }
        $endpoint = self::get_validated_endpoint();
        if (!$endpoint) {
            return false;
        }
        $payload = array(
            'action' => 'site_sync',
            'site_id' => sanitize_text_field(UCP_Options::get('cloud_site_id', '')),
            'home_url' => home_url('/'),
            'plugin_version' => UCP_VERSION,
            'used_css_enabled' => (bool) UCP_Options::get('cloud_pull_used_css'),
            'critical_css_enabled' => (bool) UCP_Options::get('cloud_pull_critical_css'),
            'queue_summary' => UCP_Jobs::get_summary(),
        );
        $response = self::post($endpoint, $payload);
        if (!$response) {
            return false;
        }
        UCP_Helpers::log('Cloud sync OK');
        return true;
    }

    public static function request_remote_css($url) {
        if (!UCP_Options::get('enable_cloud') || !UCP_Options::get('enable_remote_css_render')) {
            return false;
        }
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url || !wp_http_validate_url($url)) {
            UCP_Helpers::log('Remote CSS overgeslagen voor ongeldige of niet-lokale URL.');
            return false;
        }
        $endpoint = self::get_validated_endpoint();
        if (!$endpoint) {
            return false;
        }
        $response = self::post($endpoint, array(
            'action' => 'render_css',
            'site_id' => sanitize_text_field(UCP_Options::get('cloud_site_id', '')),
            'url' => esc_url_raw($url),
            'want_used_css' => (bool) UCP_Options::get('cloud_pull_used_css'),
            'want_critical_css' => (bool) UCP_Options::get('cloud_pull_critical_css'),
        ));
        if (!$response || empty($response['body'])) {
            return false;
        }
        $content_type = isset($response['content_type']) ? strtolower((string) $response['content_type']) : '';
        if ('' !== $content_type && false === strpos($content_type, 'application/json') && false === strpos($content_type, 'text/json')) {
            UCP_Helpers::log('Remote CSS rejected: unexpected content type ' . $content_type);
            return false;
        }
        if (!is_string($response['body']) || strlen($response['body']) > 1024 * 1024) {
            UCP_Helpers::log('Remote CSS rejected: response too large or invalid.');
            return false;
        }
        $data = json_decode($response['body'], true);
        if (!is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            UCP_Helpers::log('Remote CSS rejected: invalid JSON payload.');
            return false;
        }
        $did_work = false;
        if (!empty($data['used_css']) && self::is_valid_css_payload($data['used_css'], 300000)) {
            UCP_Helpers::write_file(UCP_Helpers::get_used_css_path($url), $data['used_css']);
            $did_work = true;
        }
        if (!empty($data['critical_css']) && self::is_valid_css_payload($data['critical_css'], 120000)) {
            UCP_Helpers::write_file(UCP_Helpers::get_critical_css_path($url), $data['critical_css']);
            $did_work = true;
        }
        if ($did_work) {
            UCP_Helpers::log('Remote CSS render OK for ' . $url);
        }
        return $did_work;
    }


    protected static function is_valid_css_payload($css, $max_bytes = 300000) {
        if (!is_string($css) || '' === trim($css)) {
            return false;
        }
        if (strlen($css) > absint($max_bytes)) {
            return false;
        }
        if (preg_match('/<\/?(?:script|style|html|body)|<\?(?:php|=)/i', $css)) {
            return false;
        }
        return true;
    }
}

trait UCP_Cloud_Endpoint_Trait {
    protected static function has_valid_endpoint() {
        return (bool) self::get_validated_endpoint();
    }

    protected static function get_validated_endpoint() {
        $endpoint = esc_url_raw(UCP_Options::get('cloud_endpoint', ''));
        if (!$endpoint || !wp_http_validate_url($endpoint)) {
            return false;
        }
        $parts = wp_parse_url($endpoint);
        if (!is_array($parts)) {
            return false;
        }
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        if ('https' !== $scheme || '' === $host) {
            return false;
        }
        if (!empty($parts['user']) || !empty($parts['pass'])) {
            return false;
        }
        foreach (array('.local', '.test', '.invalid') as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                return false;
            }
        }
        if (in_array($host, array('localhost', '127.0.0.1', '::1'), true)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (false === filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        } elseif (!self::host_resolves_to_public_ip($host)) {
            return false;
        }
        return $endpoint;
    }

    protected static function host_resolves_to_public_ip($host) {
        $host = strtolower(trim((string) $host));
        if ('' === $host) {
            return false;
        }

        // validate DNS results to reduce SSRF risk for hostnames that resolve to private/reserved networks.
        $records = function_exists('dns_get_record') ? dns_get_record($host, DNS_A + DNS_AAAA) : false;
        if (empty($records) || !is_array($records)) {
            $ip = gethostbyname($host);
            $records = ($ip && $ip !== $host) ? array(array('ip' => $ip)) : array();
        }

        if (empty($records)) {
            return false;
        }

        foreach ($records as $record) {
            $ip = '';
            if (!empty($record['ip'])) {
                $ip = (string) $record['ip'];
            } elseif (!empty($record['ipv6'])) {
                $ip = (string) $record['ipv6'];
            }
            if ('' === $ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}

trait UCP_Cloud_HTTP_Trait {
    protected static function post($endpoint, $payload) {
        $endpoint = esc_url_raw((string) $endpoint);
        $validated_endpoint = self::get_validated_endpoint();
        if (!$validated_endpoint || $endpoint !== $validated_endpoint) {
            UCP_Helpers::log('Cloud-aanvraag overgeslagen: ongeldig of gewijzigd endpoint.');
            return false;
        }

        $response = wp_remote_post($endpoint, array(
            'timeout' => 25,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'user-agent' => 'UltraCache Cloud/' . UCP_VERSION,
            'headers' => array(
                'Authorization' => 'Bearer ' . (string) UCP_Options::get('cloud_api_key', ''),
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
        ));
        if (is_wp_error($response)) {
            UCP_Helpers::log('Cloud-aanvraag mislukt: ' . $response->get_error_message());
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            UCP_Helpers::log('Cloud-aanvraag HTTP ' . $code);
            return false;
        }
        return array(
            'code' => $code,
            'body' => wp_remote_retrieve_body($response),
            'content_type' => (string) wp_remote_retrieve_header($response, 'content-type'),
        );
    }
}

class UCP_Cloud {
    use UCP_Cloud_Routes_Trait;
    use UCP_Cloud_CSS_Trait;
    use UCP_Cloud_Endpoint_Trait;
    use UCP_Cloud_HTTP_Trait;

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_post_ucp_cloud_sync', array($this, 'handle_manual_sync'));
    }
}
