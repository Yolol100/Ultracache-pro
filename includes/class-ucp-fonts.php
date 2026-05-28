<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Fonts {
    public function __construct() {
        add_filter('style_loader_tag', array($this, 'localize_google_fonts'), 20, 4);
        add_action('ucp_refresh_google_font_cache', array($this, 'refresh_cached_css'), 10, 1);
    }

    public function localize_google_fonts($html, $handle, $href, $media) {
        if (empty(UCP_Options::get('enable_local_google_fonts')) || empty($href)) {
            return $html;
        }

        $host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
        if ('fonts.googleapis.com' !== $host || !wp_http_validate_url($href)) {
            return $html;
        }

        $local = $this->cached_css_url($href);
        return $local ? str_replace(esc_url($href), esc_url($local), $html) : $html;
    }

    protected function cached_css_url($href) {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir']) || empty($uploads['baseurl'])) {
            return false;
        }

        $dir = trailingslashit($uploads['basedir']) . 'ultracache-pro/fonts';
        if (!wp_mkdir_p($dir)) {
            return false;
        }

        $file = trailingslashit($dir) . md5($href) . '.css';
        if (!file_exists($file)) {
            $this->schedule_refresh($href);
            return false;
        }

        if (filemtime($file) < (time() - WEEK_IN_SECONDS)) {
            $this->schedule_refresh($href);
        }

        return trailingslashit($uploads['baseurl']) . 'ultracache-pro/fonts/' . basename($file);
    }

    public function refresh_cached_css($href) {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir']) || empty($uploads['baseurl'])) {
            return false;
        }

        $href = esc_url_raw((string) $href);
        $host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
        if ('fonts.googleapis.com' !== $host || !wp_http_validate_url($href)) {
            return false;
        }

        $dir = trailingslashit($uploads['basedir']) . 'ultracache-pro/fonts';
        if (!wp_mkdir_p($dir)) {
            return false;
        }

        $file = trailingslashit($dir) . md5($href) . '.css';
        $response = wp_remote_get(
            $href,
            array(
                'timeout'             => 8,
                'redirection'         => 0,
                'reject_unsafe_urls'  => true,
                'user-agent'          => 'Mozilla/5.0 UltraCache Pro',
                'limit_response_size' => 250000,
            )
        );

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $type = wp_remote_retrieve_header($response, 'content-type');
        if ($type && false === stripos((string) $type, 'text/css')) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || '' === $body || strlen($body) > 250000 || false !== stripos($body, '<html') || false !== stripos($body, '<?php')) {
            return false;
        }

        $body = $this->localize_font_files($body, $dir, trailingslashit($uploads['baseurl']) . 'ultracache-pro/fonts/');
        return $this->write_cached_file($file, $body, $dir);
    }

    protected function schedule_refresh($href) {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_single_event')) {
            return;
        }

        $href = esc_url_raw((string) $href);
        if (!$href || wp_next_scheduled('ucp_refresh_google_font_cache', array($href))) {
            return;
        }

        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'ucp_refresh_google_font_cache', array($href));
    }

    protected function write_cached_file($path, $body, $base_dir) {
        $normalized_path = wp_normalize_path((string) $path);
        $normalized_base = trailingslashit(wp_normalize_path((string) $base_dir));
        if (0 !== strpos($normalized_path, $normalized_base)) {
            return false;
        }

        if (!is_string($body) || '' === $body || !wp_is_writable($base_dir)) {
            return false;
        }

        $tmp = $path . '.tmp.' . wp_generate_password(8, false, false);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- cache-only write under wp-content/uploads with path allow-list and LOCK_EX.
        if (false === file_put_contents($tmp, $body, LOCK_EX)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic cache-file move under validated uploads cache path; WP_Filesystem::move() is not atomic.
        if (!@rename($tmp, $path)) {
            wp_delete_file($tmp);
            return false;
        }

        return true;
    }

    protected function localize_font_files($css, $dir, $baseurl) {
        if (!preg_match_all('#url\(([^)]+fonts\.gstatic\.com/[^)]+)\)#i', $css, $matches)) {
            return $css;
        }

        foreach ($matches[1] as $raw_url) {
            $font_url = $this->normalize_font_url($raw_url);
            if (!$font_url) {
                continue;
            }

            $filename = md5($font_url) . '.' . $this->font_file_extension($font_url);
            $target = trailingslashit($dir) . $filename;

            if (!file_exists($target) && !$this->cache_remote_font_file($font_url, $target, $dir)) {
                continue;
            }

            $css = str_replace($font_url, esc_url_raw($baseurl . $filename), $css);
        }

        return $css;
    }

    protected function normalize_font_url($raw_url) {
        $font_url = trim((string) $raw_url, '\'" ');
        $font_host = strtolower((string) wp_parse_url($font_url, PHP_URL_HOST));

        if ('fonts.gstatic.com' !== $font_host || !wp_http_validate_url($font_url)) {
            return false;
        }

        return esc_url_raw($font_url);
    }

    protected function font_file_extension($font_url) {
        $path = wp_parse_url($font_url, PHP_URL_PATH);
        $ext = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

        if (!$ext || !in_array($ext, array('woff2', 'woff', 'ttf'), true)) {
            return 'woff2';
        }

        return sanitize_key($ext);
    }

    protected function cache_remote_font_file($font_url, $target, $dir) {
        $response = wp_remote_get(
            $font_url,
            array(
                'timeout'             => 10,
                'redirection'         => 0,
                'reject_unsafe_urls'  => true,
                'user-agent'          => 'Mozilla/5.0 UltraCache Pro',
                'limit_response_size' => 2000000,
            )
        );

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || '' === $body || strlen($body) > 2000000) {
            return false;
        }

        $type = wp_remote_retrieve_header($response, 'content-type');
        if ($type && false === stripos((string) $type, 'font') && false === stripos((string) $type, 'application/octet-stream')) {
            return false;
        }

        return $this->write_cached_file($target, $body, $dir);
    }
}
