<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
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
            header('Vary: Accept-Encoding', false);
            if (UCP_Options::get('enable_cloudflare_apo_mode')) {
                header('X-UltraCache-APO: on');
            }
            UCP_Diagnostics::record('edge', 'Edge cache headers sent', array('apo' => (int) UCP_Options::get('enable_cloudflare_apo_mode')));
        }

        if (UCP_Options::get('enable_early_hints_links')) {
            $candidates = UCP_Helpers::collect_preload_candidates();
            $this->send_early_hints_if_supported($candidates);
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
                if (!UCP_Helpers::is_local_url($href)) {
                    continue;
                }
                if (!in_array($as, $allowed_as, true)) {
                    $as = 'script';
                }
                $header = 'Link: <' . $href . '>; rel=preload; as=' . $as;
                if ('font' === $as) {
                    $header .= '; crossorigin';
                }
                if (function_exists('headers_sent') && !headers_sent()) {
                    header($header, false);
                }
                $sent++;
            }
            UCP_Diagnostics::record('edge', 'Preload Link headers sent', array('count' => $sent));
        }
    }

    private function send_early_hints_if_supported($candidates) {
        if (headers_sent() || empty($candidates)) {
            return;
        }
        $server = strtolower(isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '');
        $cf = self::cloudflare_headers_present();
        $supports = $cf || false !== strpos($server, 'litespeed') || false !== strpos($server, 'nginx');
        if (!$supports) {
            return;
        }
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? preg_replace('/[^A-Z0-9\.\/]/', '', sanitize_text_field(wp_unslash($_SERVER['SERVER_PROTOCOL']))) : 'HTTP/1.1';
        header($protocol . ' 103 Early Hints', true, 103);
        $sent = 0;
        // Define allowed preload types for 'as' attribute. Unknown types default to 'script'.
        $allowed_as = array('script', 'style', 'font', 'image', 'fetch', 'document');
        foreach ((array) $candidates as $candidate) {
            if ($sent >= 3) {
                break;
            }

            $href = !empty($candidate['href']) ? esc_url_raw($candidate['href'], array('http', 'https')) : '';
            $as = !empty($candidate['as']) ? sanitize_key($candidate['as']) : 'script';
            // Skip invalid or foreign URLs, as well as URLs containing CR/LF characters.
            if (!$href || preg_match('/[\r\n]/', $href) || !UCP_Helpers::is_local_url($href)) {
                continue;
            }
            // Restrict the 'as' value to a safe list of preload types.
            if (!in_array($as, $allowed_as, true)) {
                $as = 'script';
            }
            $header = 'Link: <' . $href . '>; rel=preload; as=' . $as;
            // Fonts require crossorigin set on preload headers.
            if ('font' === $as) {
                $header .= '; crossorigin';
            }
            header($header, false);
            $sent++;
        }

        if (function_exists('flush')) {
            @flush();
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
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url || !wp_http_validate_url($url)) {
            return false;
        }
        return self::request('/purge_cache', array('files' => array($url)));
    }

    public static function cloudflare_purge_urls($urls) {
        if (!self::cloudflare_api_configured()) {
            return false;
        }
        $clean_urls = array();
        foreach ((array) $urls as $url) {
            $url = UCP_Helpers::strict_local_url($url);
            if ($url && wp_http_validate_url($url)) {
                $clean_urls[] = $url;
            }
        }
        $urls = array_values(array_unique($clean_urls));
        if (empty($urls)) {
            return false;
        }
        $ok = true;
        foreach (array_chunk($urls, 30) as $chunk) {
            $ok = self::request('/purge_cache', array('files' => $chunk)) && $ok;
        }
        return $ok;
    }

    public static function cloudflare_purge_tags($tags) {
        if (!self::cloudflare_api_configured()) {
            return false;
        }
        $clean = array();
        foreach ((array) $tags as $tag) {
            $tag = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $tag);
            $tag = substr((string) $tag, 0, 60);
            if ('' !== $tag) {
                $clean[] = $tag;
            }
        }
        $clean = array_values(array_unique($clean));
        if (empty($clean)) {
            return false;
        }
        $ok = true;
        // Cloudflare accepts up to 30 tags per purge call.
        foreach (array_chunk($clean, 30) as $chunk) {
            $ok = self::request('/purge_cache', array('tags' => $chunk)) && $ok;
        }
        return $ok;
    }

    protected static function request($path, $body) {
        $zone_id = sanitize_text_field(UCP_Options::get('cloudflare_zone_id'));
        $token = (string) UCP_Options::get('cloudflare_api_token');
        // Strip newline characters and trim whitespace from the API token. If it becomes empty, abort.
        $token = trim(str_replace(array("\r", "\n"), '', $token));
        if ('' === $token) {
            return false;
        }
        $endpoint = 'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone_id) . $path;
        $response = wp_remote_post($endpoint, array(
            'timeout' => 20,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 65536,
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
