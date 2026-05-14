<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Edge {
    public function __construct() {
        add_action('send_headers', array($this, 'send_headers'));
    }

    public function send_headers() {
        if (is_admin()) {
            return;
        }
        if (UCP_Options::get('enable_edge_cache_headers') && !is_user_logged_in()) {
            header('X-UltraCache-Edge: eligible');
            header('Vary: Accept-Encoding, Cookie', false);
            if (UCP_Options::get('enable_cloudflare_apo_mode')) {
                header('X-UltraCache-APO: on');
            }
            UCP_Diagnostics::record('edge', 'Edge cache headers sent', array('apo' => (int) UCP_Options::get('enable_cloudflare_apo_mode')));
        }

        if (UCP_Options::get('enable_early_hints_links')) {
            $candidates = UCP_Helpers::collect_preload_candidates();
            $allowed_as = array('script', 'style', 'font', 'image', 'fetch', 'document');
            $sent = 0;
            foreach ($candidates as $candidate) {
                if ($sent >= 5) {
                    break;
                }
                $href = !empty($candidate['href']) ? esc_url_raw($candidate['href'], array('http', 'https')) : '';
                $as = !empty($candidate['as']) ? sanitize_key($candidate['as']) : 'script';
                if (!$href || preg_match('/[\r\n]/', $href)) {
                    continue;
                }
                if (!in_array($as, $allowed_as, true)) {
                    $as = 'script';
                }
                $header = 'Link: <' . $href . '>; rel=preload; as=' . $as;
                if ('font' === $as) {
                    $header .= '; crossorigin';
                }
                header($header, false);
                $sent++;
            }
            UCP_Diagnostics::record('edge', 'Link preload headers sent', array('count' => $sent));
        }
    }

    public static function cloudflare_headers_present() {
        return !empty($_SERVER['HTTP_CF_CONNECTING_IP']) || !empty($_SERVER['HTTP_CF_RAY']);
    }

    public static function cloudflare_api_configured() {
        return (bool) (UCP_Options::get('cloudflare_zone_id') && UCP_Options::get('cloudflare_api_token'));
    }

    public static function cloudflare_purge_all() {
        if (!self::cloudflare_api_configured()) {
            return false;
        }
        return self::request('/purge_cache', array('purge_everything' => true));
    }

    public static function cloudflare_purge_url($url) {
        if (!self::cloudflare_api_configured()) {
            return false;
        }
        return self::request('/purge_cache', array('files' => array(esc_url_raw($url))));
    }

    public static function cloudflare_purge_urls($urls) {
        if (!self::cloudflare_api_configured()) {
            return false;
        }
        $urls = array_values(array_unique(array_filter(array_map('esc_url_raw', (array) $urls))));
        if (empty($urls)) {
            return false;
        }
        return self::request('/purge_cache', array('files' => $urls));
    }

    protected static function request($path, $body) {
        $zone_id = sanitize_text_field(UCP_Options::get('cloudflare_zone_id'));
        $token = sanitize_text_field(UCP_Options::get('cloudflare_api_token'));
        $endpoint = 'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone_id) . $path;
        $response = wp_remote_post($endpoint, array(
            'timeout' => 20,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
        ));
        if (is_wp_error($response)) {
            UCP_Helpers::log('Cloudflare-aanvraag mislukt: ' . $response->get_error_message());
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        UCP_Helpers::log('Cloudflare-aanvraag code: ' . $code);
        return $code >= 200 && $code < 300;
    }
}
