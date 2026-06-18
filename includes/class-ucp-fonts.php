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

        $scheme = strtolower((string) wp_parse_url($href, PHP_URL_SCHEME));
        $host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
        if ('https' !== $scheme || 'fonts.googleapis.com' !== $host || !wp_http_validate_url($href)) {
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
        $this->ensure_font_cache_index($dir);

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
        $scheme = strtolower((string) wp_parse_url($href, PHP_URL_SCHEME));
        $host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
        if ('https' !== $scheme || 'fonts.googleapis.com' !== $host || !wp_http_validate_url($href)) {
            return false;
        }

        $dir = trailingslashit($uploads['basedir']) . 'ultracache-pro/fonts';
        if (!wp_mkdir_p($dir)) {
            return false;
        }
        $this->ensure_font_cache_index($dir);

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
        $body = $this->maybe_apply_unicode_ranges($body);
        $written = $this->write_cached_file($file, $body, $dir);
        if ($written) {
            $this->store_font_preload_candidates($body);
        }
        return $written;
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

    protected function ensure_font_cache_index($dir) {
        $index = trailingslashit((string) $dir) . 'index.html';
        if (file_exists($index) || !wp_is_writable($dir)) {
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- static directory index in the plugin-owned public font cache.
        file_put_contents($index, '', LOCK_EX);
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


    /**
     * Lightweight local-font subsetting aid: keep Google's own unicode-range declarations and add
     * a safe Latin range to cached @font-face blocks that do not declare one. This does not modify
     * binary font files, but it lets browsers skip non-matching faces in split CSS.
     *
     * @param string $css Cached Google Fonts CSS.
     * @return string
     */
    protected function maybe_apply_unicode_ranges($css) {
        if (empty(UCP_Options::get('enable_font_unicode_ranges')) || !is_string($css) || false === stripos($css, '@font-face')) {
            return $css;
        }

        $range = $this->font_unicode_range_value();
        if ('' === $range) {
            return $css;
        }

        $updated = preg_replace_callback('/@font-face\s*\{[^}]*\}/i', function ($matches) use ($range) {
            $block = (string) $matches[0];
            if (false !== stripos($block, 'unicode-range')) {
                return $block;
            }
            return rtrim($block, '}') . '  unicode-range: ' . $range . ";
}";
        }, $css);

        return is_string($updated) ? $updated : $css;
    }

    /**
     * @return string
     */
    protected function font_unicode_range_value() {
        $mode = sanitize_key((string) UCP_Options::get('font_unicode_ranges', 'latin'));
        if ('latin-ext' === $mode) {
            return 'U+0100-024F, U+1E00-1EFF';
        }
        if ('latin-plus-ext' === $mode) {
            return 'U+0000-00FF, U+0100-024F, U+1E00-1EFF, U+20AC';
        }
        return 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD';
    }

    protected function store_font_preload_candidates($css) {
        if (!is_string($css) || '' === $css || !preg_match_all('#url\(([^)]+\.(?:woff2|woff))(?:\?[^)]*)?\)#i', $css, $matches)) {
            return;
        }

        $existing = get_option('ucp_local_font_preload_candidates', array());
        $existing = is_array($existing) ? $existing : array();
        $candidates = array();
        foreach (array_merge($existing, $matches[1]) as $raw_url) {
            $font_url = trim((string) $raw_url, '\"\' ');
            $font_url = esc_url_raw($font_url, array('http', 'https'));
            if (!$font_url || !UCP_Helpers::is_local_url($font_url)) {
                continue;
            }
            if (!preg_match('/\.(woff2|woff)(\?|$)/i', $font_url)) {
                continue;
            }
            $candidates[$font_url] = $font_url;
            if (count($candidates) >= 6) {
                break;
            }
        }

        update_option('ucp_local_font_preload_candidates', array_values($candidates), false);
    }

    protected function normalize_font_url($raw_url) {
        $font_url = trim((string) $raw_url, '\'" ');
        $font_scheme = strtolower((string) wp_parse_url($font_url, PHP_URL_SCHEME));
        $font_host = strtolower((string) wp_parse_url($font_url, PHP_URL_HOST));

        if ('https' !== $font_scheme || 'fonts.gstatic.com' !== $font_host || !wp_http_validate_url($font_url)) {
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
