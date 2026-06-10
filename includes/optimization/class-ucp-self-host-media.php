<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Self-host external media: Gravatar avatars and YouTube thumbnails.
 *
 * Comment-heavy and video-embedding sites leak many requests to gravatar.com and i.ytimg.com.
 * This caches those images locally (under uploads/ultracache-pro/remote/) and rewrites the markup
 * to the local copy, eliminating the third-party requests — the local Gravatar / local YouTube
 * placeholder behaviour FlyingPress ships. Fetching is offloaded to the job queue, so the first
 * render keeps the original URL and subsequent renders use the cached copy. Both toggles default OFF.
 */
class UCP_Self_Host_Media {

    const SUBDIR        = 'ultracache-pro/remote/';
    const ALLOWED_HOSTS = array('gravatar.com', 'secure.gravatar.com', '0.gravatar.com', '1.gravatar.com', '2.gravatar.com', 'i.ytimg.com', 'img.youtube.com');

    public function __construct() {
        if (UCP_Options::get('enable_local_gravatar')) {
            add_filter('get_avatar_url', array($this, 'rewrite_gravatar'), 20, 3);
        }
        if (UCP_Options::get('enable_local_youtube_thumbnails')) {
            add_filter('ucp_process_html', array($this, 'rewrite_youtube_thumbnails'), 8);
        }
    }

    /* ----------------------------------------------------------------- Gravatar */

    public function rewrite_gravatar($url, $id_or_email, $args) {
        if (!is_string($url) || false === stripos($url, 'gravatar.com')) {
            return $url;
        }
        $local = self::local_url_for($url, 'avatar');
        return $local ? $local : $url;
    }

    /* ----------------------------------------------------------------- YouTube thumbnails */

    public function rewrite_youtube_thumbnails($html) {
        if (!is_string($html) || '' === trim($html)) {
            return $html;
        }
        if (false === stripos($html, 'ytimg.com') && false === stripos($html, 'img.youtube.com')) {
            return $html;
        }
        return preg_replace_callback('#https?://(?:i\.ytimg\.com|img\.youtube\.com)/[^"\'\s)]+\.(?:jpg|webp)#i', function ($m) {
            $local = self::local_url_for($m[0], 'ythumb');
            return $local ? $local : $m[0];
        }, $html);
    }

    /* ----------------------------------------------------------------- Cache plumbing */

    /**
     * Return the local cached URL for a remote image, scheduling a background fetch on a miss.
     *
     * @param string $remote_url
     * @param string $kind
     * @return string Local URL, or '' when not yet cached.
     */
    protected static function local_url_for($remote_url, $kind) {
        $remote_url = esc_url_raw((string) $remote_url);
        if ('' === $remote_url || !self::host_allowed($remote_url)) {
            return '';
        }
        $ext  = self::extension_for($remote_url);
        $name = $kind . '-' . md5($remote_url) . '.' . $ext;
        $paths = self::cache_paths($name);
        if ('' === $paths['dir']) {
            return '';
        }
        if (is_file($paths['path'])) {
            return $paths['url'];
        }
        // Schedule a one-time background fetch; keep the original URL for now.
        if (class_exists('UCP_Jobs')) {
            UCP_Jobs::enqueue_unique('localize_remote_asset', array('url' => $remote_url, 'name' => $name), 40, 'media');
        }
        return '';
    }

    /**
     * Job-queue entry point (wired into UCP_Jobs run_job() via 'localize_remote_asset').
     *
     * @param string $remote_url
     * @param string $name
     * @return bool
     */
    public static function run_job($remote_url, $name) {
        $remote_url = esc_url_raw((string) $remote_url);
        $name = preg_replace('/[^a-z0-9.\-]/i', '', (string) $name);
        if ('' === $remote_url || '' === $name || !self::host_allowed($remote_url)) {
            return false;
        }
        $paths = self::cache_paths($name);
        if ('' === $paths['dir']) {
            return false;
        }
        if (is_file($paths['path'])) {
            return true;
        }

        $response = wp_remote_get($remote_url, UCP_Helpers::default_remote_args(array(
            'timeout'             => 15,
            'redirection'         => 2,
            'limit_response_size' => 1024 * 1024,
            'user-agent'          => 'UltraCache Media/' . UCP_VERSION,
        )));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return false;
        }
        $type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        if (false === strpos($type, 'image/')) {
            return false;
        }
        $body = wp_remote_retrieve_body($response);
        if ('' === $body) {
            return false;
        }
        if (!is_dir($paths['dir'])) {
            wp_mkdir_p($paths['dir']);
        }
        return false !== UCP_Helpers::write_file($paths['path'], $body);
    }

    protected static function cache_paths($name) {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir']) || empty($uploads['baseurl'])) {
            return array('dir' => '', 'path' => '', 'url' => '');
        }
        $dir = trailingslashit($uploads['basedir']) . self::SUBDIR;
        return array(
            'dir'  => $dir,
            'path' => $dir . $name,
            'url'  => trailingslashit($uploads['baseurl']) . self::SUBDIR . $name,
        );
    }

    protected static function host_allowed($url) {
        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        $host   = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ('https' !== $scheme || '' === $host || !in_array($host, self::ALLOWED_HOSTS, true)) {
            return false;
        }
        return !class_exists('UCP_Helpers') || '' !== UCP_Helpers::validate_public_https_url($url, array('resolve_dns' => false));
    }

    protected static function extension_for($url) {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, array('jpg', 'jpeg', 'png', 'webp', 'gif'), true) ? $ext : 'jpg';
    }
}
