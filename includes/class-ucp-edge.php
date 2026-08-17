<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Edge {
    const CLOUDFLARE_LAST_RESULT_OPTION = 'ucp_cloudflare_last_result';

    public function __construct() {
        add_action('send_headers', array($this, 'send_headers'));
    }

    public function send_headers() {
        if (is_admin() || headers_sent()) {
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
                header($header, false);
                $sent++;
            }
            UCP_Diagnostics::record('edge', 'Preload Link headers sent', array('count' => $sent));
        }
    }

    public static function cloudflare_headers_present() {
        return '' !== UCP_Helpers::server_value('HTTP_CF_CONNECTING_IP', '', 128) || '' !== UCP_Helpers::server_value('HTTP_CF_RAY', '', 128);
    }

    public static function cloudflare_api_configured() {
        $zone_id = strtolower(trim((string) UCP_Options::get('cloudflare_zone_id', '')));
        $token = trim(str_replace(array("\r", "\n"), '', (string) UCP_Options::get('cloudflare_api_token', '')));
        return 1 === preg_match('/^[a-f0-9]{32}$/', $zone_id) && '' !== $token;
    }

    public static function cloudflare_last_result() {
        $result = get_option(self::CLOUDFLARE_LAST_RESULT_OPTION, array());
        return is_array($result) ? $result : array();
    }

    public static function cloudflare_purge_all() {
        if (!self::cloudflare_api_configured()) {
            self::record_cloudflare_result(false, 0, __('De Cloudflare-API is niet ingesteld.', 'ultracache-pro'), array('action' => 'purge_all'));
            return false;
        }
        return self::request('/purge_cache', array('purge_everything' => true));
    }

    public static function cloudflare_purge_url($url) {
        if (!self::cloudflare_api_configured()) {
            self::record_cloudflare_result(false, 0, __('De Cloudflare-API is niet ingesteld.', 'ultracache-pro'), array('action' => 'purge_files', 'fileCount' => 1));
            return false;
        }
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url || !wp_http_validate_url($url)) {
            self::record_cloudflare_result(false, 0, __('Ongeldige lokale purge-URL.', 'ultracache-pro'), array('action' => 'purge_files', 'fileCount' => 0));
            return false;
        }
        return self::request('/purge_cache', array('files' => array($url)));
    }

    public static function cloudflare_purge_urls($urls) {
        if (!self::cloudflare_api_configured()) {
            self::record_cloudflare_result(false, 0, __('De Cloudflare-API is niet ingesteld.', 'ultracache-pro'), array('action' => 'purge_files', 'fileCount' => count((array) $urls)));
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
            self::record_cloudflare_result(false, 0, __('Geen geldige lokale purge-URL’s gevonden.', 'ultracache-pro'), array('action' => 'purge_files', 'fileCount' => 0));
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
            self::record_cloudflare_result(false, 0, __('De Cloudflare-API is niet ingesteld.', 'ultracache-pro'), array('action' => 'purge_tags', 'tagCount' => count((array) $tags)));
            return false;
        }
        $clean = array();
        foreach ((array) $tags as $tag) {
            $tag = UCP_Helpers::sanitize_preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $tag);
            $tag = substr((string) $tag, 0, 60);
            if ('' !== $tag) {
                $clean[] = $tag;
            }
        }
        $clean = array_values(array_unique($clean));
        if (empty($clean)) {
            self::record_cloudflare_result(false, 0, __('Geen geldige purge-tags gevonden.', 'ultracache-pro'), array('action' => 'purge_tags', 'tagCount' => 0));
            return false;
        }
        $ok = true;
        // Cloudflare accepts up to 30 tags per purge call.
        foreach (array_chunk($clean, 30) as $chunk) {
            $ok = self::request('/purge_cache', array('tags' => $chunk)) && $ok;
        }
        return $ok;
    }

    protected static function cloudflare_request_context($path, $body) {
        $body = is_array($body) ? $body : array();
        $context = array(
            'path' => '/' . ltrim((string) $path, '/'),
        );
        if (!empty($body['purge_everything'])) {
            $context['action'] = 'purge_all';
            return $context;
        }
        if (!empty($body['files']) && is_array($body['files'])) {
            $context['action'] = 'purge_files';
            $context['fileCount'] = count($body['files']);
            return $context;
        }
        if (!empty($body['tags']) && is_array($body['tags'])) {
            $context['action'] = 'purge_tags';
            $context['tagCount'] = count($body['tags']);
            return $context;
        }
        $context['action'] = 'unknown';
        return $context;
    }

    protected static function record_cloudflare_result($ok, $code, $message, $context = array()) {
        $result = array(
            'ok' => (bool) $ok,
            'code' => absint($code),
            'message' => wp_strip_all_tags((string) $message),
            'recordedAt' => gmdate('Y-m-d H:i:s'),
        );
        foreach (array('action', 'path') as $key) {
            if (isset($context[$key])) {
                $result[$key] = sanitize_key((string) $context[$key]);
            }
        }
        foreach (array('fileCount', 'tagCount') as $key) {
            if (isset($context[$key])) {
                $result[$key] = absint($context[$key]);
            }
        }
        update_option(self::CLOUDFLARE_LAST_RESULT_OPTION, $result, false);
    }

    protected static function request($path, $body) {
        $context = self::cloudflare_request_context($path, $body);
        $zone_id = strtolower(sanitize_text_field(UCP_Options::get('cloudflare_zone_id')));
        if (1 !== preg_match('/^[a-f0-9]{32}$/', $zone_id)) {
            UCP_Helpers::log(__('Cloudflare-aanvraag is overgeslagen wegens een ongeldige zone-ID.', 'ultracache-pro'));
            self::record_cloudflare_result(false, 0, __('Ongeldige Cloudflare-zone-ID.', 'ultracache-pro'), $context);
            return false;
        }

        $path = '/' . ltrim((string) $path, '/');
        if (!in_array($path, array('/purge_cache'), true)) {
            UCP_Helpers::log(__('Cloudflare-aanvraag is overgeslagen wegens een ongeldig endpointpad.', 'ultracache-pro'));
            self::record_cloudflare_result(false, 0, __('Ongeldig Cloudflare-endpointpad.', 'ultracache-pro'), $context);
            return false;
        }

        $token = (string) UCP_Options::get('cloudflare_api_token');
        // Strip newline characters and trim whitespace from the API token. If it becomes empty, abort.
        $token = trim(str_replace(array("\r", "\n"), '', $token));
        if ('' === $token) {
            self::record_cloudflare_result(false, 0, __('Het Cloudflare-API-token is leeg.', 'ultracache-pro'), $context);
            return false;
        }
        $endpoint = 'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone_id) . $path;
        $encoded_body = UCP_Helpers::safe_json_encode($body);
        if (!is_string($encoded_body) || '' === $encoded_body) {
            self::record_cloudflare_result(false, 0, __('De Cloudflare-payload kon niet veilig als JSON worden opgebouwd.', 'ultracache-pro'), $context);
            return false;
        }
        $response = wp_remote_post($endpoint, array(
            'timeout' => 20,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 65536,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'body' => $encoded_body,
        ));
        if (is_wp_error($response)) {
            UCP_Helpers::log(sprintf(__('Cloudflare-aanvraag is mislukt: %s', 'ultracache-pro'), $response->get_error_message()));
            self::record_cloudflare_result(false, 0, $response->get_error_message(), $context);
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        if (false === UCP_Helpers::bounded_remote_response_body($response, 65536, 0)) {
            self::record_cloudflare_result(false, (int) $code, __('Het Cloudflare-antwoord was te groot of afgekapt.', 'ultracache-pro'), $context);
            return false;
        }
        UCP_Helpers::log(sprintf(__('Cloudflare-aanvraag gaf HTTP-code %d.', 'ultracache-pro'), $code));
        $ok = $code >= 200 && $code < 300;
        self::record_cloudflare_result($ok, $code, $ok ? __('Cloudflare-purge is geaccepteerd.', 'ultracache-pro') : __('Cloudflare-purge is mislukt.', 'ultracache-pro'), $context);
        return $ok;
    }
}
