<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
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
        UCP_Helpers::log(__('Cloudsynchronisatie is voltooid.', 'ultracache-pro'));
        return true;
    }

    public static function request_remote_css($url) {
        if (!UCP_Options::get('enable_cloud') || !UCP_Options::get('enable_remote_css_render')) {
            return false;
        }
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url || !wp_http_validate_url($url)) {
            UCP_Helpers::log(__('Externe CSS is overgeslagen voor een ongeldige of niet-lokale URL.', 'ultracache-pro'));
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
            UCP_Helpers::log(sprintf(__('Externe CSS is afgewezen wegens een onverwacht inhoudstype: %s', 'ultracache-pro'), $content_type));
            return false;
        }
        $body = UCP_Helpers::bounded_response_body($response['body'], 1024 * 1024);
        if (false === $body) {
            UCP_Helpers::log(__('Externe CSS is afgewezen omdat het antwoord te groot of ongeldig is.', 'ultracache-pro'));
            return false;
        }
        $data = UCP_Helpers::safe_json_decode($body, true);
        if (!is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            UCP_Helpers::log(__('Externe CSS is afgewezen wegens een ongeldige JSON-payload.', 'ultracache-pro'));
            return false;
        }
        $used_css = !empty($data['used_css']) && self::is_valid_css_payload($data['used_css'], 300000) ? (string) $data['used_css'] : '';
        $critical_css = !empty($data['critical_css']) && self::is_valid_css_payload($data['critical_css'], 120000) ? (string) $data['critical_css'] : '';
        if ('' === $used_css || !class_exists('UCP_CSS') || !UCP_CSS::persist_artifacts($url, $used_css, $critical_css)) {
            UCP_Helpers::log(sprintf(__('Externe CSS-render is afgewezen of kon geen consistente artifactset opslaan voor %s', 'ultracache-pro'), $url));
            return false;
        }
        UCP_Helpers::log(sprintf(__('Externe CSS-render is voltooid voor %s', 'ultracache-pro'), $url));
        return true;
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
