<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_CDN {
    public static function enabled() {
        return (bool) UCP_Options::get('enable_cdn_purge', 0);
    }

    protected static function provider() {
        $provider = sanitize_key((string) UCP_Options::get('cdn_provider', 'none'));
        return in_array($provider, array('none', 'cloudflare', 'bunny', 'custom_webhook'), true) ? $provider : 'none';
    }

    public static function status() {
        $provider = self::provider();
        $configured = false;
        if ('cloudflare' === $provider) {
            $configured = (bool) UCP_Options::get('cloudflare_zone_id') && (bool) UCP_Options::get('cloudflare_api_token');
        } elseif ('bunny' === $provider) {
            $configured = (bool) UCP_Options::get('bunny_pullzone_id') && (bool) UCP_Options::get('bunny_api_key');
        } elseif ('custom_webhook' === $provider) {
            $configured = (bool) UCP_Options::get('cdn_custom_webhook_url');
        }
        return array('enabled' => self::enabled(), 'provider' => $provider, 'configured' => $configured, 'health' => $configured ? (self::enabled() ? 'connected' : 'partial') : 'not_configured');
    }

    public static function purge_all() {
        if (!self::enabled()) { return false; }
        switch (self::provider()) {
            case 'cloudflare': return self::cloudflare_purge(array('purge_everything' => true));
            case 'bunny': return self::bunny_purge_all();
            case 'custom_webhook': return self::custom_webhook(array('scope' => 'all'));
        }
        return false;
    }

    public static function purge_urls($urls) {
        if (!self::enabled()) { return false; }
        $urls = array_values(array_unique(array_filter(array_map('esc_url_raw', (array) $urls))));
        if (empty($urls)) { return false; }
        switch (self::provider()) {
            case 'cloudflare': return self::cloudflare_purge(array('files' => array_slice($urls, 0, 30)));
            case 'bunny':
                $ok = true;
                foreach (array_slice($urls, 0, 30) as $url) { $ok = self::bunny_purge_url($url) && $ok; }
                return $ok;
            case 'custom_webhook': return self::custom_webhook(array('scope' => 'urls', 'urls' => array_slice($urls, 0, 30)));
        }
        return false;
    }

    protected static function cloudflare_purge($body) {
        $zone_id = sanitize_text_field((string) UCP_Options::get('cloudflare_zone_id', ''));
        $token = (string) UCP_Options::get('cloudflare_api_token', '');
        if ('' === $zone_id || '' === $token) { return false; }
        $response = wp_remote_post('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone_id) . '/purge_cache', array(
            'timeout' => 15,
            'redirection' => 2,
            'headers' => array('Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
        ));
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 300;
    }

    protected static function bunny_purge_all() {
        $pullzone_id = sanitize_text_field((string) UCP_Options::get('bunny_pullzone_id', ''));
        $api_key = (string) UCP_Options::get('bunny_api_key', '');
        if ('' === $pullzone_id || '' === $api_key) { return false; }
        $response = wp_remote_post('https://api.bunny.net/pullzone/' . rawurlencode($pullzone_id) . '/purgeCache', array(
            'timeout' => 15,
            'redirection' => 2,
            'headers' => array('AccessKey' => $api_key),
        ));
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 300;
    }

    protected static function bunny_purge_url($url) {
        $api_key = (string) UCP_Options::get('bunny_api_key', '');
        if ('' === $api_key) { return false; }
        $response = wp_remote_get(add_query_arg('url', rawurlencode($url), 'https://api.bunny.net/purge'), array(
            'timeout' => 15,
            'redirection' => 2,
            'headers' => array('AccessKey' => $api_key),
        ));
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 300;
    }

    protected static function custom_webhook($payload) {
        $url = esc_url_raw((string) UCP_Options::get('cdn_custom_webhook_url', ''));
        if ('' === $url || !wp_http_validate_url($url)) { return false; }
        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'redirection' => 2,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
        ));
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
    }
}