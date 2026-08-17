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
 * to the local copy, eliminating repeat third-party requests. Fetching is offloaded to the job queue,
 * so the first render keeps the original URL and subsequent renders use the cached copy. Both
 * toggles default OFF.
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
        return UCP_Helpers::safe_preg_replace_callback('#https?://(?:i\.ytimg\.com|img\.youtube\.com)/[^"\'\s)]+\.(?:jpg|webp)#i', function ($m) {
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
        if (self::is_safe_cached_file($paths['path'], $paths['dir'])) {
            if (!self::cached_file_is_fresh($paths['path'], $paths['dir'], $kind, $remote_url) && class_exists('UCP_Jobs')) {
                UCP_Jobs::enqueue_unique('localize_remote_asset', array('url' => $remote_url, 'name' => $name), 40, 'media');
            }
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
        $name = UCP_Helpers::sanitize_preg_replace('/[^a-z0-9.\-]/i', '', (string) $name);
        if ('' === $remote_url || '' === $name || !self::host_allowed($remote_url)) {
            return false;
        }
        if (1 !== preg_match('/^(avatar|ythumb)-[a-f0-9]{32}\.(?:jpe?g|png|webp|gif)$/', $name)) {
            return false;
        }
        $expected_hash = md5($remote_url);
        if (false === strpos($name, '-' . $expected_hash . '.')) {
            return false;
        }
        $paths = self::cache_paths($name);
        if ('' === $paths['dir']) {
            return false;
        }
        $kind = 0 === strpos($name, 'avatar-') ? 'avatar' : 'ythumb';
        if (self::cached_file_is_fresh($paths['path'], $paths['dir'], $kind, $remote_url)) {
            return true;
        }
        if (is_link($paths['path'])) {
            wp_delete_file($paths['path']);
        }

        $max_bytes = 1024 * 1024;
        $response = wp_remote_get($remote_url, UCP_Helpers::default_remote_args(array(
            'timeout'             => 15,
            'redirection'         => 0,
            'limit_response_size' => $max_bytes,
            'user-agent'          => 'UltraCache Media/' . UCP_VERSION,
        )));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return false;
        }
        $type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        if (false === strpos($type, 'image/')) {
            return false;
        }
        $body = UCP_Helpers::bounded_remote_response_body($response, $max_bytes);
        if (false === $body) {
            return false;
        }
        $image_info = function_exists('getimagesizefromstring') ? @getimagesizefromstring($body) : false;
        $image_mime = is_array($image_info) && !empty($image_info['mime']) ? strtolower((string) $image_info['mime']) : '';
        if (!in_array($image_mime, array('image/jpeg', 'image/png', 'image/gif', 'image/webp'), true)
            || !self::mime_matches_filename($image_mime, $name)) {
            return false;
        }
        if (!is_dir($paths['dir'])) {
            wp_mkdir_p($paths['dir']);
        }
        self::ensure_cache_index($paths['dir']);
        return self::write_cached_file($paths['path'], $body, $paths['dir']);
    }

    protected static function cache_paths($name) {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir']) || empty($uploads['baseurl'])) {
            return array('dir' => '', 'path' => '', 'url' => '');
        }

        $uploads_dir = rtrim((string) $uploads['basedir'], '/\\');
        $plugin_dir  = trailingslashit($uploads_dir) . 'ultracache-pro';
        $dir         = trailingslashit($plugin_dir) . 'remote/';
        if (
            '' === $uploads_dir
            || is_link($plugin_dir)
            || is_link(rtrim($dir, '/'))
            || (file_exists($plugin_dir) && !is_dir($plugin_dir))
            || (file_exists($dir) && !is_dir($dir))
            || (is_dir($dir) && !self::is_safe_cache_directory($dir))
        ) {
            return array('dir' => '', 'path' => '', 'url' => '');
        }

        return array(
            'dir'  => $dir,
            'path' => $dir . $name,
            'url'  => trailingslashit($uploads['baseurl']) . self::SUBDIR . $name,
        );
    }

    protected static function is_safe_cache_directory($dir) {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir']) || !is_dir($dir) || is_link(rtrim((string) $dir, '/\\'))) {
            return false;
        }

        $uploads_real = realpath((string) $uploads['basedir']);
        $dir_real     = realpath($dir);
        if (false === $uploads_real || false === $dir_real) {
            return false;
        }

        $expected = trailingslashit(wp_normalize_path($uploads_real)) . 'ultracache-pro/remote';
        return rtrim(wp_normalize_path($dir_real), '/') === $expected;
    }

    protected static function is_safe_cached_file($path, $dir) {
        if (!self::is_safe_cache_directory($dir) || is_link($path) || !is_file($path) || !is_readable($path)) {
            return false;
        }
        $file_real = realpath($path);
        $dir_real  = realpath($dir);
        return false !== $file_real
            && false !== $dir_real
            && dirname(wp_normalize_path($file_real)) === rtrim(wp_normalize_path($dir_real), '/');
    }

    protected static function cached_file_is_fresh($path, $dir, $kind, $remote_url) {
        if (!self::is_safe_cached_file($path, $dir)) {
            return false;
        }
        $max_age = absint(apply_filters('ucp_self_host_media_max_age', WEEK_IN_SECONDS, $kind, $remote_url));
        $max_age = max(HOUR_IN_SECONDS, min(30 * DAY_IN_SECONDS, $max_age));
        $modified = filemtime($path);
        return false !== $modified && $modified >= time() - $max_age;
    }

    protected static function ensure_cache_index($dir) {
        $index = trailingslashit((string) $dir) . 'index.html';
        if (file_exists($index) || !wp_is_writable($dir)) {
            return;
        }
        self::write_cached_file($index, '', $dir);
    }

    protected static function write_cached_file($path, $body, $base_dir) {
        return UCP_Helpers::write_upload_cache_file_atomic($path, $body, $base_dir);
    }

    protected static function host_allowed($url) {
        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        $host   = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ('https' !== $scheme || '' === $host || !in_array($host, self::ALLOWED_HOSTS, true)) {
            return false;
        }
        return !class_exists('UCP_Helpers') || '' !== UCP_Helpers::validate_public_https_url($url, array('resolve_dns' => false));
    }

    /**
     * Ensure detected image bytes agree with the public filename extension.
     * Browsers and intermediaries may otherwise apply the wrong content type.
     *
     * @param string $mime Detected image MIME type.
     * @param string $name Local cache filename.
     * @return bool
     */
    protected static function mime_matches_filename($mime, $name) {
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        $expected = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        );
        return isset($expected[$extension]) && $expected[$extension] === strtolower((string) $mime);
    }

    protected static function extension_for($url) {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, array('jpg', 'jpeg', 'png', 'webp', 'gif'), true) ? $ext : 'jpg';
    }
}
