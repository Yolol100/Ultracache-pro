<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multi-provider CDN purge routing.
 *
 * UltraCache already rewrites assets to a CNAME (optimizer CDN hints) and can purge Cloudflare via
 * UCP_Edge. This layer routes the plugin's own purge events (`ucp_cache_purged_all|url|urls`) to the
 * configured provider, adding Bunny.net and a generic purge webhook so full-site / pull-zone CDNs
 * stay in sync when UltraCache flushes; the cross-provider edge consistency QUIC.cloud and
 * FlyingCDN give out of the box. Default provider: none.
 */
class UCP_CDN {
    const LAST_RESULT_OPTION = 'ucp_cdn_last_result';

    public function __construct() {
        if ('none' === self::provider()) {
            return;
        }
        add_action('ucp_cache_purged_all', array($this, 'on_purge_all'));
        add_action('ucp_cache_purged_url', array($this, 'on_purge_url'), 10, 1);
        add_action('ucp_cache_purged_urls', array($this, 'on_purge_urls'), 10, 1);
    }

    /**
     * Active CDN provider.
     *
     * @return string none|cloudflare|bunny|generic
     */
    public static function provider() {
        $p = sanitize_key((string) UCP_Options::get('cdn_provider', 'none'));
        return in_array($p, array('cloudflare', 'bunny', 'generic'), true) ? $p : 'none';
    }

    public static function cdn_last_result() {
        $result = get_option(self::LAST_RESULT_OPTION, array());
        return is_array($result) ? $result : array();
    }

    public function on_purge_all() {
        switch (self::provider()) {
            case 'cloudflare':
                if (class_exists('UCP_Edge')) {
                    UCP_Edge::cloudflare_purge_all();
                }
                break;
            case 'bunny':
                self::bunny_purge_all();
                break;
            case 'generic':
                self::generic_purge('all', array());
                break;
        }
    }

    public function on_purge_url($url) {
        $url = self::normalize_purge_url($url);
        if ('' === $url) {
            return;
        }
        $this->on_purge_urls(array($url));
    }

    public function on_purge_urls($urls) {
        $clean = array();
        foreach (array_slice((array) $urls, 0, 100) as $url) {
            $url = self::normalize_purge_url($url);
            if ('' !== $url) {
                $clean[] = $url;
            }
        }
        if (empty($clean)) {
            return;
        }
        $clean = array_values(array_unique($clean));

        switch (self::provider()) {
            case 'cloudflare':
                if (class_exists('UCP_Edge')) {
                    UCP_Edge::cloudflare_purge_urls($clean);
                }
                break;
            case 'bunny':
                foreach ($clean as $url) {
                    self::bunny_purge_url($url);
                }
                break;
            case 'generic':
                self::generic_purge('urls', $clean);
                break;
        }
    }

    /* ----------------------------------------------------------------- Bunny.net */

    protected static function bunny_key() {
        return trim(str_replace(array("\r", "\n"), '', (string) UCP_Options::get('bunny_api_key', '')));
    }

    protected static function bunny_purge_url($url) {
        $url = self::normalize_purge_url($url);
        if ('' === $url) {
            self::record_cdn_result('bunny', false, 0, __('Ongeldige lokale purge-URL.', 'ultracache-pro'), array('action' => 'purge_urls', 'urlCount' => 0));
            return false;
        }
        $key = self::bunny_key();
        if ('' === $key) {
            self::record_cdn_result('bunny', false, 0, __('De Bunny-API-sleutel is leeg.', 'ultracache-pro'), array('action' => 'purge_urls', 'urlCount' => 1));
            return false;
        }
        $endpoint = add_query_arg(array('url' => $url, 'async' => 'true'), 'https://api.bunny.net/purge');
        return self::remote('GET', $endpoint, null, array('AccessKey' => $key), 'bunny_purge_url', array('provider' => 'bunny', 'action' => 'purge_urls', 'urlCount' => 1));
    }

    protected static function bunny_purge_all() {
        $key  = self::bunny_key();
        $zone = UCP_Helpers::sanitize_preg_replace('/[^0-9]/', '', (string) UCP_Options::get('bunny_pull_zone_id', ''));
        if ('' === $key || '' === $zone) {
            self::record_cdn_result('bunny', false, 0, __('De Bunny-API-sleutel of pullzone-ID ontbreekt.', 'ultracache-pro'), array('action' => 'purge_all'));
            return false;
        }
        $endpoint = 'https://api.bunny.net/pullzone/' . $zone . '/purgeCache';
        return self::remote('POST', $endpoint, '', array('AccessKey' => $key), 'bunny_purge_all', array('provider' => 'bunny', 'action' => 'purge_all'));
    }

    /* ----------------------------------------------------------------- Generic webhook */

    protected static function generic_purge($scope, $urls) {
        $webhook = UCP_Helpers::validate_public_https_url(UCP_Options::get('cdn_purge_webhook', ''));
        if ('' === $webhook) {
            self::record_cdn_result('generic', false, 0, __('De algemene CDN-webhook is niet ingesteld of onveilig.', 'ultracache-pro'), array('action' => sanitize_key($scope), 'urlCount' => count((array) $urls)));
            return false;
        }
        $clean_urls = array();
        foreach ((array) $urls as $url) {
            $url = self::normalize_purge_url($url);
            if ('' !== $url) {
                $clean_urls[] = $url;
            }
        }
        $clean_urls = array_values(array_unique($clean_urls));
        $headers = array('Content-Type' => 'application/json');
        $token = trim((string) UCP_Options::get('cdn_purge_webhook_token', ''));
        if ('' !== $token) {
            $headers['Authorization'] = 'Bearer ' . str_replace(array("\r", "\n"), '', $token);
        }
        $body = UCP_Helpers::safe_json_encode(array('action' => sanitize_key($scope), 'urls' => $clean_urls, 'site' => home_url('/')));
        if (!is_string($body) || '' === $body) {
            self::record_cdn_result('generic', false, 0, __('De CDN-purge-payload kon niet veilig als JSON worden opgebouwd.', 'ultracache-pro'), array('action' => sanitize_key($scope), 'urlCount' => count($clean_urls)));
            return false;
        }
        return self::remote('POST', $webhook, $body, $headers, 'generic_purge', array('provider' => 'generic', 'action' => sanitize_key($scope), 'urlCount' => count($clean_urls)));
    }

    /* ----------------------------------------------------------------- Shared HTTP */

    protected static function normalize_purge_url($url) {
        if (class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'strict_local_url')) {
            $url = UCP_Helpers::strict_local_url($url);
        } else {
            $url = esc_url_raw((string) $url);
        }
        return $url && wp_http_validate_url($url) ? esc_url_raw($url) : '';
    }

    protected static function record_cdn_result($provider, $ok, $code, $message, $context = array()) {
        $result = array(
            'provider' => sanitize_key((string) $provider),
            'ok' => (bool) $ok,
            'code' => absint($code),
            'message' => wp_strip_all_tags((string) $message),
            'recordedAt' => gmdate('Y-m-d H:i:s'),
        );
        foreach (array('action', 'endpointHost') as $key) {
            if (isset($context[$key])) {
                $result[$key] = sanitize_text_field((string) $context[$key]);
            }
        }
        if (isset($context['urlCount'])) {
            $result['urlCount'] = absint($context['urlCount']);
        }
        update_option(self::LAST_RESULT_OPTION, $result, false);
    }

    protected static function remote($method, $endpoint, $body, $headers, $context, $result_context = array()) {
        $method = strtoupper((string) $method);
        if (!in_array($method, array('GET', 'POST', 'DELETE'), true)) {
            return false;
        }
        if (null !== $body && (!is_string($body) || strlen($body) > 256 * KB_IN_BYTES)) {
            return false;
        }
        $headers = is_array($headers) ? array_slice($headers, 0, 30, true) : array();
        $result_context = is_array($result_context) ? array_slice($result_context, 0, 20, true) : array();
        $provider = !empty($result_context['provider']) ? sanitize_key((string) $result_context['provider']) : self::provider();
        $endpoint_host = wp_parse_url($endpoint, PHP_URL_HOST);
        if ($endpoint_host) {
            $result_context['endpointHost'] = strtolower((string) $endpoint_host);
        }
        $safe_endpoint = class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'validate_public_https_url')
            ? UCP_Helpers::validate_public_https_url($endpoint, array('resolve_dns' => false))
            : (wp_http_validate_url($endpoint) ? esc_url_raw($endpoint) : '');
        if ('' === $safe_endpoint) {
            self::record_cdn_result($provider, false, 0, __('Het CDN-purge-endpoint is onveilig.', 'ultracache-pro'), $result_context);
            return false;
        }
        $args = UCP_Helpers::default_remote_args(array(
            'method'     => $method,
            'user-agent' => 'UltraCache CDN/' . UCP_VERSION,
            'headers'             => $headers,
            'limit_response_size' => 64 * 1024,
        ));
        if (null !== $body) {
            $args['body'] = $body;
        }
        $response = ('GET' === $method) ? wp_remote_get($safe_endpoint, $args) : wp_remote_request($safe_endpoint, $args);
        if (is_wp_error($response)) {
            if (class_exists('UCP_Logger')) {
                UCP_Logger::log('warning', 'cdn', $context . '_failed', __('CDN-purge gaf een HTTP-fout.', 'ultracache-pro'), array('error' => $response->get_error_message()));
            }
            self::record_cdn_result($provider, false, 0, $response->get_error_message(), $result_context);
            return false;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if (false === UCP_Helpers::bounded_remote_response_body($response, 64 * KB_IN_BYTES, 0)) {
            self::record_cdn_result($provider, false, $code, __('Het CDN-antwoord was te groot of afgekapt.', 'ultracache-pro'), $result_context);
            return false;
        }
        $ok = ($code >= 200 && $code < 300);
        if (!$ok && class_exists('UCP_Logger')) {
            UCP_Logger::log('warning', 'cdn', $context . '_http', sprintf(__('CDN-purge HTTP %d.', 'ultracache-pro'), $code), array('code' => $code));
        }
        self::record_cdn_result($provider, $ok, $code, $ok ? __('CDN-purge is geaccepteerd.', 'ultracache-pro') : __('CDN-purge is mislukt.', 'ultracache-pro'), $result_context);
        return $ok;
    }
}
