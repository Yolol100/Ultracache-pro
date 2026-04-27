<?php
if (!defined('ABSPATH')) {
    exit;
}

interface UCP_Provider_Interface {
    public function get_id();
    public function get_label();
    public function get_required_fields();
    public function validate_credentials($settings);
    public function test_purge($settings);
    public function get_health($settings);
    public function mask_secrets($settings);
}

abstract class UCP_Abstract_Provider implements UCP_Provider_Interface {
    protected function remote_result($response) {
        if (is_wp_error($response)) {
            return array('ok' => false, 'code' => 0, 'message' => $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        return array('ok' => $code > 0 && $code < 400, 'code' => $code, 'message' => wp_remote_retrieve_response_message($response));
    }
    protected function mask($value) {
        $value = (string) $value;
        if ('' === $value) { return ''; }
        return substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4);
    }
}

class UCP_Cloudflare_Provider extends UCP_Abstract_Provider {
    public function get_id() { return 'cloudflare'; }
    public function get_label() { return __('Cloudflare', 'ultracache-pro'); }
    public function get_required_fields() { return array('cloudflare_zone_id', 'cloudflare_api_token'); }
    public function validate_credentials($settings) {
        $zone = sanitize_text_field((string) ($settings['cloudflare_zone_id'] ?? ''));
        $token = (string) ($settings['cloudflare_api_token'] ?? '');
        if (!$zone || !$token) { return array('ok' => false, 'reason' => 'missing_credentials'); }
        $response = wp_safe_remote_get('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone), array('timeout' => 12, 'redirection' => 2, 'headers' => array('Authorization' => 'Bearer ' . $token)));
        return $this->remote_result($response);
    }
    public function test_purge($settings) {
        $zone = sanitize_text_field((string) ($settings['cloudflare_zone_id'] ?? ''));
        $token = (string) ($settings['cloudflare_api_token'] ?? '');
        $response = wp_safe_remote_post('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone) . '/purge_cache', array('timeout' => 12, 'redirection' => 2, 'headers' => array('Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'), 'body' => wp_json_encode(array('files' => array(home_url('/'))))));
        return $this->remote_result($response);
    }
    public function get_health($settings) { $result = $this->validate_credentials($settings); return array('state' => !empty($result['ok']) ? 'connected' : 'failed', 'result' => $result); }
    public function mask_secrets($settings) { if (isset($settings['cloudflare_api_token'])) { $settings['cloudflare_api_token'] = $this->mask($settings['cloudflare_api_token']); } return $settings; }
}

class UCP_Bunny_Provider extends UCP_Abstract_Provider {
    public function get_id() { return 'bunny'; }
    public function get_label() { return __('Bunny CDN', 'ultracache-pro'); }
    public function get_required_fields() { return array('bunny_pullzone_id', 'bunny_api_key'); }
    public function validate_credentials($settings) {
        $zone = sanitize_text_field((string) ($settings['bunny_pullzone_id'] ?? ''));
        $key = (string) ($settings['bunny_api_key'] ?? '');
        if (!$zone || !$key) { return array('ok' => false, 'reason' => 'missing_credentials'); }
        $response = wp_safe_remote_get('https://api.bunny.net/pullzone/' . rawurlencode($zone), array('timeout' => 12, 'redirection' => 2, 'headers' => array('AccessKey' => $key)));
        return $this->remote_result($response);
    }
    public function test_purge($settings) {
        $zone = sanitize_text_field((string) ($settings['bunny_pullzone_id'] ?? ''));
        $key = (string) ($settings['bunny_api_key'] ?? '');
        $response = wp_safe_remote_post('https://api.bunny.net/pullzone/' . rawurlencode($zone) . '/purgeCache', array('timeout' => 12, 'redirection' => 2, 'headers' => array('AccessKey' => $key)));
        return $this->remote_result($response);
    }
    public function get_health($settings) { $result = $this->validate_credentials($settings); return array('state' => !empty($result['ok']) ? 'connected' : 'failed', 'result' => $result); }
    public function mask_secrets($settings) { if (isset($settings['bunny_api_key'])) { $settings['bunny_api_key'] = $this->mask($settings['bunny_api_key']); } return $settings; }
}

class UCP_Custom_Webhook_Provider extends UCP_Abstract_Provider {
    public function get_id() { return 'custom_webhook'; }
    public function get_label() { return __('Custom webhook', 'ultracache-pro'); }
    public function get_required_fields() { return array('cdn_custom_webhook_url'); }
    public function validate_credentials($settings) {
        $url = esc_url_raw((string) ($settings['cdn_custom_webhook_url'] ?? ''));
        if (!$url || !wp_http_validate_url($url) || (0 !== strpos($url, 'https://') && !apply_filters('ucp_allow_insecure_webhook_test', false, $url))) { return array('ok' => false, 'reason' => 'invalid_https_url'); }
        return array('ok' => true, 'code' => 0, 'message' => 'url_valid');
    }
    public function test_purge($settings) {
        $url = esc_url_raw((string) ($settings['cdn_custom_webhook_url'] ?? ''));
        $valid = $this->validate_credentials($settings);
        if (empty($valid['ok'])) { return $valid; }
        $response = wp_safe_remote_post($url, array('timeout' => 8, 'redirection' => 1, 'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode(array('source' => 'ultracache-pro', 'test' => true, 'url' => home_url('/')))));
        return $this->remote_result($response);
    }
    public function get_health($settings) { $result = $this->validate_credentials($settings); return array('state' => !empty($result['ok']) ? 'partial' : 'failed', 'result' => $result); }
    public function mask_secrets($settings) { return $settings; }
}

class UCP_Provider_Manager {
    public static function providers() {
        return array(
            'cloudflare' => new UCP_Cloudflare_Provider(),
            'bunny' => new UCP_Bunny_Provider(),
            'custom_webhook' => new UCP_Custom_Webhook_Provider(),
        );
    }
    public static function get($id = '') {
        $id = $id ? sanitize_key($id) : sanitize_key((string) UCP_Options::get('cdn_provider', 'none'));
        $providers = self::providers();
        return isset($providers[$id]) ? $providers[$id] : null;
    }
    public static function health($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $provider = self::get($settings['cdn_provider'] ?? 'none');
        if (!$provider) { return array('state' => 'not_configured', 'provider' => 'none'); }
        $health = $provider->get_health($settings);
        $health['provider'] = $provider->get_id();
        return $health;
    }
    public static function mask_settings($settings) {
        foreach (self::providers() as $provider) { $settings = $provider->mask_secrets($settings); }
        return $settings;
    }
    public static function detect_edge_headers($headers = null) {
        $headers = is_array($headers) ? $headers : array_change_key_case((array) (function_exists('getallheaders') ? getallheaders() : array()), CASE_LOWER);
        $signals = array();
        foreach (array('x-cache', 'x-fastcgi-cache', 'x-varnish', 'via', 'cf-cache-status') as $header) {
            if (!empty($headers[$header])) { $signals[$header] = sanitize_text_field((string) $headers[$header]); }
        }
        return $signals;
    }
}
