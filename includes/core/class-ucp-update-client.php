<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Update_Client {
    private const UPDATE_URI = 'https://github.com/Yolol100/Ultracache-pro';
    private const API_URL = 'https://api.github.com/repos/Yolol100/Ultracache-pro/releases/latest';
    private const PACKAGE_ASSET = 'ultracache-pro.zip';
    private const MANIFEST_ASSET = 'ultracache-pro-release.json';
    private const RELEASE_CACHE_KEY = 'ucp_verified_release_v1';
    private const PACKAGE_CACHE_PREFIX = 'ucp_verified_package_';
    private const MAX_API_BYTES = 1048576;
    private const MAX_MANIFEST_BYTES = 65536;
    private const MAX_PACKAGE_BYTES = 104857600;
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;
    private const PACKAGE_TTL = 2 * DAY_IN_SECONDS;

    private static $bootstrapped = false;

    public static function bootstrap() {
        if (self::$bootstrapped) {
            return;
        }

        add_filter('update_plugins_github.com', array(__CLASS__, 'filter_update'), 10, 4);
        add_filter('upgrader_pre_download', array(__CLASS__, 'verify_download'), 10, 4);
        self::$bootstrapped = true;
    }

    public static function filter_update($update, $plugin_data, $plugin_file, $locales) {
        unset($plugin_data, $locales);

        if (UCP_BASENAME !== (string) $plugin_file) {
            return $update;
        }

        $release = self::release(false);
        if (is_wp_error($release) || !is_array($release)) {
            return false;
        }

        $version = isset($release['version']) ? (string) $release['version'] : '';
        if ('' === $version || !version_compare($version, UCP_VERSION, '>')) {
            return false;
        }

        return array(
            'id'           => self::UPDATE_URI,
            'slug'         => 'ultracache-pro',
            'version'      => $version,
            'url'          => isset($release['release_url']) ? (string) $release['release_url'] : self::UPDATE_URI,
            'package'      => isset($release['package_url']) ? (string) $release['package_url'] : '',
            'tested'       => isset($release['tested_wp']) ? (string) $release['tested_wp'] : '',
            'requires'     => isset($release['requires_wp']) ? (string) $release['requires_wp'] : '',
            'requires_php' => isset($release['requires_php']) ? (string) $release['requires_php'] : '',
            'autoupdate'   => false,
        );
    }

    public static function verify_download($reply, $package, $upgrader, $hook_extra) {
        unset($upgrader);

        if (false !== $reply || !self::is_our_upgrade($hook_extra)) {
            return $reply;
        }

        $cache_key = self::PACKAGE_CACHE_PREFIX . substr(hash('sha256', (string) $package), 0, 24);
        $release = get_site_transient($cache_key);
        if (!is_array($release) || empty($release['package_url'])) {
            $release = self::release(false);
        }
        if (is_wp_error($release)) {
            return $release;
        }

        $expected_url = isset($release['package_url']) ? (string) $release['package_url'] : '';
        $expected_hash = isset($release['package_sha256']) ? (string) $release['package_sha256'] : '';
        if ('' === $expected_url || '' === $expected_hash || !hash_equals($expected_url, (string) $package)) {
            return new WP_Error('ucp_update_package_unverified', __('UltraCache weigert een updatepakket dat niet overeenkomt met het geverifieerde releasemanifest.', 'ultracache-pro'));
        }

        if (!self::valid_release_asset_url($expected_url)) {
            return new WP_Error('ucp_update_package_url', __('UltraCache weigert een updatepakket buiten het toegestane GitHub-releasepad.', 'ultracache-pro'));
        }

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('download_url')) {
            return new WP_Error('ucp_update_download_unavailable', __('WordPress kan het UltraCache-updatepakket niet veilig downloaden.', 'ultracache-pro'));
        }

        $temporary = download_url($expected_url, 300, false);
        if (is_wp_error($temporary)) {
            return $temporary;
        }

        $size = is_file($temporary) ? filesize($temporary) : false;
        $actual_hash = is_file($temporary) ? hash_file('sha256', $temporary) : false;
        if (false === $size || $size < 1024 || $size > self::MAX_PACKAGE_BYTES || !is_string($actual_hash) || !hash_equals($expected_hash, strtolower($actual_hash))) {
            if (is_file($temporary)) {
                wp_delete_file($temporary);
            }
            return new WP_Error('ucp_update_digest_mismatch', __('De SHA-256-controle van het UltraCache-updatepakket is mislukt.', 'ultracache-pro'));
        }

        return $temporary;
    }

    public static function status($force = false) {
        $release = self::release((bool) $force);
        if (is_wp_error($release)) {
            return array(
                'state'   => 'unavailable',
                'current' => UCP_VERSION,
                'message' => sanitize_text_field($release->get_error_message()),
            );
        }

        $latest = isset($release['version']) ? (string) $release['version'] : '';
        return array(
            'state'           => version_compare($latest, UCP_VERSION, '>') ? 'update_available' : (version_compare($latest, UCP_VERSION, '==') ? 'current' : 'source_behind'),
            'current'         => UCP_VERSION,
            'latest'          => $latest,
            'release_url'     => isset($release['release_url']) ? esc_url_raw((string) $release['release_url']) : '',
            'published_at'    => isset($release['published_at']) ? sanitize_text_field((string) $release['published_at']) : '',
            'manifest_digest' => isset($release['manifest_sha256']) ? sanitize_text_field((string) $release['manifest_sha256']) : '',
            'package_digest'  => isset($release['package_sha256']) ? sanitize_text_field((string) $release['package_sha256']) : '',
        );
    }

    public static function clear_cache() {
        delete_site_transient(self::RELEASE_CACHE_KEY);
    }

    private static function release($force) {
        if (!$force) {
            $cached = get_site_transient(self::RELEASE_CACHE_KEY);
            if (is_array($cached) && !empty($cached['version'])) {
                return $cached;
            }
        }

        $response = wp_safe_remote_get(self::API_URL, array(
            'timeout'             => 10,
            'redirection'         => 2,
            'limit_response_size' => self::MAX_API_BYTES,
            'headers'             => array(
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent'           => 'UltraCache-Pro/' . UCP_VERSION,
            ),
        ));
        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        if (200 !== $status || '' === $body || strlen($body) >= self::MAX_API_BYTES) {
            return new WP_Error('ucp_update_release_response', __('De UltraCache-releasebron gaf geen geldige, volledige response.', 'ultracache-pro'));
        }

        $payload = json_decode($body, true);
        if (!is_array($payload) || !empty($payload['draft']) || !empty($payload['prerelease'])) {
            return new WP_Error('ucp_update_release_invalid', __('De nieuwste UltraCache-release is niet gepubliceerd of heeft een ongeldig formaat.', 'ultracache-pro'));
        }

        $version = self::normalize_version(isset($payload['tag_name']) ? $payload['tag_name'] : '');
        $release_url = isset($payload['html_url']) ? esc_url_raw((string) $payload['html_url']) : '';
        if ('' === $version || !self::valid_release_page_url($release_url)) {
            return new WP_Error('ucp_update_release_identity', __('De UltraCache-release-identiteit kon niet veilig worden bevestigd.', 'ultracache-pro'));
        }

        $assets = isset($payload['assets']) && is_array($payload['assets']) ? $payload['assets'] : array();
        $package_asset = self::find_asset($assets, self::PACKAGE_ASSET, self::MAX_PACKAGE_BYTES);
        $manifest_asset = self::find_asset($assets, self::MANIFEST_ASSET, self::MAX_MANIFEST_BYTES);
        if (is_wp_error($package_asset)) {
            return $package_asset;
        }
        if (is_wp_error($manifest_asset)) {
            return $manifest_asset;
        }

        $manifest = self::fetch_manifest($manifest_asset);
        if (is_wp_error($manifest)) {
            return $manifest;
        }

        $package_digest = self::asset_sha256($package_asset);
        $manifest_digest = self::asset_sha256($manifest_asset);
        if (!hash_equals($version, (string) $manifest['version']) || !hash_equals(self::PACKAGE_ASSET, (string) $manifest['package_asset']) || !hash_equals($package_digest, (string) $manifest['package_sha256'])) {
            return new WP_Error('ucp_update_manifest_mismatch', __('Het UltraCache-releasemanifest komt niet overeen met de gepubliceerde release-assets.', 'ultracache-pro'));
        }

        $release = array(
            'version'          => $version,
            'release_url'      => $release_url,
            'published_at'     => isset($payload['published_at']) ? sanitize_text_field((string) $payload['published_at']) : '',
            'package_url'      => (string) $package_asset['browser_download_url'],
            'package_sha256'   => $package_digest,
            'manifest_sha256'  => $manifest_digest,
            'requires_wp'      => (string) $manifest['requires_wp'],
            'tested_wp'        => (string) $manifest['tested_wp'],
            'requires_php'     => (string) $manifest['requires_php'],
        );

        set_site_transient(self::RELEASE_CACHE_KEY, $release, self::CACHE_TTL);
        set_site_transient(self::PACKAGE_CACHE_PREFIX . substr(hash('sha256', $release['package_url']), 0, 24), $release, self::PACKAGE_TTL);
        return $release;
    }

    private static function fetch_manifest($asset) {
        $url = isset($asset['browser_download_url']) ? (string) $asset['browser_download_url'] : '';
        if (!self::valid_release_asset_url($url)) {
            return new WP_Error('ucp_update_manifest_url', __('Het UltraCache-releasemanifest staat niet op een toegestaan GitHub-releasepad.', 'ultracache-pro'));
        }

        $response = wp_safe_remote_get($url, array(
            'timeout'             => 10,
            'redirection'         => 2,
            'limit_response_size' => self::MAX_MANIFEST_BYTES,
            'headers'             => array('User-Agent' => 'UltraCache-Pro/' . UCP_VERSION),
        ));
        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        if (200 !== $status || '' === $body || strlen($body) >= self::MAX_MANIFEST_BYTES) {
            return new WP_Error('ucp_update_manifest_response', __('Het UltraCache-releasemanifest kon niet volledig worden opgehaald.', 'ultracache-pro'));
        }

        $expected_digest = self::asset_sha256($asset);
        $actual_digest = hash('sha256', $body);
        if (!hash_equals($expected_digest, $actual_digest)) {
            return new WP_Error('ucp_update_manifest_digest', __('De SHA-256-controle van het UltraCache-releasemanifest is mislukt.', 'ultracache-pro'));
        }

        $manifest = json_decode($body, true);
        if (!is_array($manifest)) {
            return new WP_Error('ucp_update_manifest_json', __('Het UltraCache-releasemanifest bevat geen geldige JSON.', 'ultracache-pro'));
        }

        $normalized = array(
            'version'        => self::normalize_version(isset($manifest['version']) ? $manifest['version'] : ''),
            'requires_wp'    => self::normalize_version(isset($manifest['requires_wp']) ? $manifest['requires_wp'] : ''),
            'tested_wp'      => self::normalize_version(isset($manifest['tested_wp']) ? $manifest['tested_wp'] : ''),
            'requires_php'   => self::normalize_version(isset($manifest['requires_php']) ? $manifest['requires_php'] : ''),
            'package_asset'  => isset($manifest['package_asset']) && is_scalar($manifest['package_asset']) ? sanitize_file_name((string) $manifest['package_asset']) : '',
            'package_sha256' => isset($manifest['package_sha256']) && is_scalar($manifest['package_sha256']) ? strtolower(trim((string) $manifest['package_sha256'])) : '',
        );

        if (in_array('', $normalized, true) || 1 !== preg_match('/^[a-f0-9]{64}$/', $normalized['package_sha256'])) {
            return new WP_Error('ucp_update_manifest_fields', __('Het UltraCache-releasemanifest mist verplichte of geldige velden.', 'ultracache-pro'));
        }

        return $normalized;
    }

    private static function find_asset($assets, $name, $maximum_size) {
        foreach ($assets as $asset) {
            if (!is_array($asset) || $name !== (isset($asset['name']) ? (string) $asset['name'] : '')) {
                continue;
            }

            $state = isset($asset['state']) ? (string) $asset['state'] : '';
            $size = isset($asset['size']) ? (int) $asset['size'] : 0;
            $url = isset($asset['browser_download_url']) ? (string) $asset['browser_download_url'] : '';
            if ('uploaded' !== $state || $size < 32 || $size > (int) $maximum_size || !self::valid_release_asset_url($url) || '' === self::asset_sha256($asset)) {
                return new WP_Error('ucp_update_asset_invalid', __('Een verplichte UltraCache-releaseasset is onvolledig of niet verifieerbaar.', 'ultracache-pro'));
            }
            return $asset;
        }

        return new WP_Error('ucp_update_asset_missing', __('Een verplichte UltraCache-releaseasset ontbreekt.', 'ultracache-pro'));
    }

    private static function asset_sha256($asset) {
        $digest = isset($asset['digest']) && is_scalar($asset['digest']) ? strtolower(trim((string) $asset['digest'])) : '';
        return 1 === preg_match('/^sha256:([a-f0-9]{64})$/', $digest, $matches) ? $matches[1] : '';
    }

    private static function normalize_version($version) {
        if (!is_scalar($version)) {
            return '';
        }
        $version = ltrim(trim((string) $version), "vV");
        return 1 === preg_match('/^[0-9]+(?:\.[0-9]+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version) ? $version : '';
    }

    private static function valid_release_page_url($url) {
        $parts = wp_parse_url((string) $url);
        return is_array($parts)
            && 'https' === strtolower(isset($parts['scheme']) ? (string) $parts['scheme'] : '')
            && 'github.com' === strtolower(isset($parts['host']) ? (string) $parts['host'] : '')
            && 0 === strpos(isset($parts['path']) ? (string) $parts['path'] : '', '/Yolol100/Ultracache-pro/releases/');
    }

    private static function valid_release_asset_url($url) {
        $parts = wp_parse_url((string) $url);
        return is_array($parts)
            && 'https' === strtolower(isset($parts['scheme']) ? (string) $parts['scheme'] : '')
            && 'github.com' === strtolower(isset($parts['host']) ? (string) $parts['host'] : '')
            && 0 === strpos(isset($parts['path']) ? (string) $parts['path'] : '', '/Yolol100/Ultracache-pro/releases/download/');
    }

    private static function is_our_upgrade($hook_extra) {
        return is_array($hook_extra)
            && isset($hook_extra['plugin'])
            && UCP_BASENAME === (string) $hook_extra['plugin'];
    }
}
