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


    public static function clear_cache() {
        $dir = self::font_cache_directory(false);
        $deleted = 0;
        if ($dir) {
            foreach (UCP_Helpers::safe_glob_files(trailingslashit($dir) . '*', 500, array($dir)) as $file) {
                if (!is_file($file)) {
                    continue;
                }
                wp_delete_file($file);
                clearstatcache(true, $file);
                if (!file_exists($file) && !is_link($file)) {
                    $deleted++;
                }
            }
        }
        delete_option('ucp_local_font_preload_candidates');
        wp_clear_scheduled_hook('ucp_refresh_google_font_cache');
        return $deleted;
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
        $context = $this->font_cache_context();
        if (!$context) {
            return false;
        }

        $dir = $context['dir'];
        $file = trailingslashit($dir) . md5($href) . '.css';
        if (!self::is_safe_cached_file($file, $dir)) {
            if (is_link($file)) {
                wp_delete_file($file);
            }
            if (!$this->refresh_cached_css($href) || !self::is_safe_cached_file($file, $dir)) {
                $this->schedule_refresh($href);
                return false;
            }
        }

        if (filemtime($file) < (time() - WEEK_IN_SECONDS)) {
            $this->schedule_refresh($href);
        }

        return $context['baseurl'] . basename($file);
    }

    public function refresh_cached_css($href) {
        $context = $this->font_cache_context();
        if (!$context) {
            return false;
        }

        $href = esc_url_raw((string) $href);
        $scheme = strtolower((string) wp_parse_url($href, PHP_URL_SCHEME));
        $host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
        if ('https' !== $scheme || 'fonts.googleapis.com' !== $host || !wp_http_validate_url($href)) {
            return false;
        }

        $dir = $context['dir'];
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

        $type = strtolower(trim((string) wp_remote_retrieve_header($response, 'content-type')));
        $type = trim((string) strtok($type, ';'));
        if ('text/css' !== $type) {
            return false;
        }

        $body = UCP_Helpers::bounded_remote_response_body($response, 250000);
        if (false === $body || '' === trim($body) || false !== stripos($body, '<html') || false !== stripos($body, '<?php')) {
            return false;
        }

        $body = $this->localize_font_files($body, $dir, $context['baseurl']);
        $body = $this->maybe_apply_unicode_ranges($body);
        $written = $this->write_cached_file($file, $body, $dir);
        if ($written) {
            $this->store_font_preload_candidates($body);
        }
        return $written;
    }

    /**
     * Prepare the shared local-font cache directory and URL.
     *
     * @return array{dir:string,baseurl:string}|false
     */
    protected function font_cache_context() {
        $uploads = wp_upload_dir();
        if (empty($uploads['baseurl'])) {
            return false;
        }

        $dir = self::font_cache_directory(true);
        if (!$dir) {
            return false;
        }
        $this->ensure_font_cache_index($dir);

        return array(
            'dir'     => $dir,
            'baseurl' => trailingslashit($uploads['baseurl']) . 'ultracache-pro/fonts/',
        );
    }

    /**
     * Return the canonical local-font cache directory without following a
     * replaced plugin-owned child directory symlink outside uploads.
     *
     * The uploads root itself may be a legitimate configured symlink. Only the
     * plugin-owned `ultracache-pro` and `fonts` descendants are required to be
     * real directories under that canonical uploads root.
     *
     * @param bool $create Whether the directory may be created when missing.
     * @return string|false
     */
    protected static function font_cache_directory($create = false) {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir']) || !is_string($uploads['basedir'])) {
            return false;
        }

        $uploads_dir = rtrim((string) $uploads['basedir'], '/\\');
        $plugin_dir = trailingslashit($uploads_dir) . 'ultracache-pro';
        $dir = trailingslashit($plugin_dir) . 'fonts';
        if (
            '' === $uploads_dir
            || is_link($plugin_dir)
            || is_link($dir)
            || (file_exists($plugin_dir) && !is_dir($plugin_dir))
            || (file_exists($dir) && !is_dir($dir))
        ) {
            return false;
        }

        if (!is_dir($dir) && (!$create || !wp_mkdir_p($dir))) {
            return false;
        }

        $uploads_real = realpath($uploads_dir);
        $dir_real = realpath($dir);
        if (false === $uploads_real || false === $dir_real) {
            return false;
        }

        $expected = trailingslashit(wp_normalize_path($uploads_real)) . 'ultracache-pro/fonts';
        $dir_real = wp_normalize_path($dir_real);
        if ($dir_real !== $expected) {
            return false;
        }

        return $dir_real;
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
        if (self::is_safe_cached_file($index, $dir)) {
            return;
        }
        if (is_link($index)) {
            wp_delete_file($index);
        }
        if (!wp_is_writable($dir)) {
            return;
        }

        $this->write_cached_file($index, '', $dir);
    }

    protected static function is_safe_cached_file($path, $dir) {
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            return false;
        }
        $file_real = realpath($path);
        $dir_real  = realpath($dir);
        return false !== $file_real
            && false !== $dir_real
            && dirname(wp_normalize_path($file_real)) === rtrim(wp_normalize_path($dir_real), '/');
    }

    protected function write_cached_file($path, $body, $base_dir) {
        return UCP_Helpers::write_upload_cache_file_atomic($path, $body, $base_dir);
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

            if (!self::is_safe_cached_file($target, $dir)) {
                if (is_link($target)) {
                    wp_delete_file($target);
                }
                if (!$this->cache_remote_font_file($font_url, $target, $dir) || !self::is_safe_cached_file($target, $dir)) {
                    continue;
                }
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

        $updated = UCP_Helpers::safe_preg_replace_callback('/@font-face\s*\{[^}]*\}/i', function ($matches) use ($range) {
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
        $font_url = $this->normalize_font_url($font_url);
        if (!$font_url) {
            return false;
        }

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

        $body = UCP_Helpers::bounded_remote_response_body($response, 2000000);
        if (false === $body || '' === $body) {
            return false;
        }

        $type = strtolower(trim((string) wp_remote_retrieve_header($response, 'content-type')));
        $type = trim((string) strtok($type, ';'));
        if ('' === $type || (false === strpos($type, 'font') && 'application/octet-stream' !== $type)) {
            return false;
        }

        $extension = strtolower((string) pathinfo((string) $target, PATHINFO_EXTENSION));
        if (!$this->font_body_matches_extension($body, $extension)) {
            return false;
        }

        return $this->write_cached_file($target, $body, $dir);
    }

    protected function font_body_matches_extension($body, $extension) {
        if (!is_string($body) || strlen($body) < 4) {
            return false;
        }

        $signature = substr($body, 0, 4);
        if ('woff2' === $extension) {
            return 'wOF2' === $signature;
        }
        if ('woff' === $extension) {
            return 'wOFF' === $signature;
        }
        if ('ttf' === $extension) {
            return in_array($signature, array("\x00\x01\x00\x00", 'true', 'typ1', 'OTTO'), true);
        }
        return false;
    }
}
