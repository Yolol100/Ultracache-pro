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
 * stay in sync when UltraCache flushes — the cross-provider edge consistency QUIC.cloud and
 * FlyingCDN give out of the box. Default provider: none.
 */
class UCP_CDN {

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
        $url = esc_url_raw((string) $url);
        if ('' === $url) {
            return;
        }
        $this->on_purge_urls(array($url));
    }

    public function on_purge_urls($urls) {
        $clean = array();
        foreach ((array) $urls as $url) {
            $url = esc_url_raw((string) $url);
            if ('' !== $url && wp_http_validate_url($url)) {
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
        $key = self::bunny_key();
        if ('' === $key) {
            return false;
        }
        $endpoint = add_query_arg(array('url' => $url, 'async' => 'true'), 'https://api.bunny.net/purge');
        return self::remote('GET', $endpoint, null, array('AccessKey' => $key), 'bunny_purge_url');
    }

    protected static function bunny_purge_all() {
        $key  = self::bunny_key();
        $zone = preg_replace('/[^0-9]/', '', (string) UCP_Options::get('bunny_pull_zone_id', ''));
        if ('' === $key || '' === $zone) {
            return false;
        }
        $endpoint = 'https://api.bunny.net/pullzone/' . $zone . '/purgeCache';
        return self::remote('POST', $endpoint, '', array('AccessKey' => $key), 'bunny_purge_all');
    }

    /* ----------------------------------------------------------------- Generic webhook */

    protected static function generic_purge($scope, $urls) {
        $webhook = UCP_Helpers::validate_public_https_url(UCP_Options::get('cdn_purge_webhook', ''));
        if ('' === $webhook) {
            return false;
        }
        $headers = array('Content-Type' => 'application/json');
        $token = trim((string) UCP_Options::get('cdn_purge_webhook_token', ''));
        if ('' !== $token) {
            $headers['Authorization'] = 'Bearer ' . str_replace(array("\r", "\n"), '', $token);
        }
        $body = wp_json_encode(array('action' => sanitize_key($scope), 'urls' => array_values((array) $urls), 'site' => home_url('/')));
        return self::remote('POST', $webhook, $body, $headers, 'generic_purge');
    }

    /* ----------------------------------------------------------------- Shared HTTP */

    protected static function remote($method, $endpoint, $body, $headers, $context) {
        $args = UCP_Helpers::default_remote_args(array(
            'method'     => $method,
            'user-agent' => 'UltraCache CDN/' . UCP_VERSION,
            'headers'    => $headers,
        ));
        if (null !== $body) {
            $args['body'] = $body;
        }
        $response = ('GET' === $method) ? wp_remote_get($endpoint, $args) : wp_remote_request($endpoint, $args);
        if (is_wp_error($response)) {
            if (class_exists('UCP_Logger')) {
                UCP_Logger::log('warning', 'cdn', $context . '_failed', 'CDN-purge HTTP-fout.', array('error' => $response->get_error_message()));
            }
            return false;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $ok = ($code >= 200 && $code < 300);
        if (!$ok && class_exists('UCP_Logger')) {
            UCP_Logger::log('warning', 'cdn', $context . '_http', 'CDN-purge HTTP ' . $code, array('code' => $code));
        }
        return $ok;
    }
}
