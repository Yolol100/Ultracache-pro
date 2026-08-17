<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_CDN_Trait {
    public static function get_first_cdn_host() {
        foreach (self::normalize_multiline(UCP_Options::get('cdn_cnames', '')) as $cname) {
            $host = self::normalize_domain_host($cname);
            if ('' !== $host) {
                return $host;
            }
        }
        return '';
    }
    public static function should_skip_cdn_url($url) {
        if (!is_scalar($url)) {
            return true;
        }
        $url = (string) $url;
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $host = (string) wp_parse_url($url, PHP_URL_HOST);
        foreach (self::normalize_multiline(UCP_Options::get('cdn_exclude', '')) as $rule) {
            $rule = trim((string) $rule);
            if ('' === $rule) {
                continue;
            }
            if (self::wildcard_match($url, $rule) || self::wildcard_match($path, $rule) || ('' !== $host && self::wildcard_match($host, $rule))) {
                return true;
            }
        }
        return false;
    }
    public static function cdn_file_type_allows($url) {
        if (!is_scalar($url)) {
            return false;
        }
        $mode = UCP_Options::get('cdn_file_types', 'all');
        $path = (string) wp_parse_url((string) $url, PHP_URL_PATH);
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ('' === $ext) {
            return false;
        }
        $css_js = array('css', 'js');
        $images = array('jpg','jpeg','png','gif','webp','avif','svg','ico');
        $fonts = array('woff','woff2','ttf','otf','eot');
        if ('css_js' === $mode) {
            return in_array($ext, $css_js, true);
        }
        if ('images' === $mode) {
            return in_array($ext, $images, true);
        }
        return in_array($ext, array_merge($css_js, $images, $fonts), true);
    }
    public static function local_path_from_url($url, $allowed_extensions = array('css', 'js')) {
        if (!is_scalar($url)) {
            return '';
        }
        $url = html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5);
        if (!self::is_local_url($url)) {
            return '';
        }
        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $allowed_extensions = array_values(array_unique(array_filter(array_map(
            static function($extension) {
                return is_scalar($extension) ? sanitize_key((string) $extension) : '';
            },
            (array) $allowed_extensions
        ))));
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ('' === $path || '' === $extension || !in_array($extension, $allowed_extensions, true)) {
            return '';
        }

        // map asset URLs by parsed path only. Stylesheet URLs commonly include
        // cache-busting query strings such as ?ver=...; including the query in a filesystem
        // path makes realpath() fail and causes false "Geen lokale CSS-inhoud gevonden" jobs.
        $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
        $content_path = wp_parse_url(content_url('/'), PHP_URL_PATH);
        $includes_path = wp_parse_url(includes_url('/'), PHP_URL_PATH);
        $home_path = '/' . trim((string) $home_path, '/') . '/';
        $content_path = '/' . trim((string) $content_path, '/') . '/';
        $includes_path = '/' . trim((string) $includes_path, '/') . '/';
        $request_path = '/' . ltrim($path, '/');

        $candidate = '';
        if (0 === strpos($request_path, $content_path)) {
            $relative = ltrim(substr($request_path, strlen($content_path)), '/');
            $candidate = WP_CONTENT_DIR . '/' . $relative;
        } elseif (0 === strpos($request_path, $includes_path)) {
            $relative = ltrim(substr($request_path, strlen($includes_path)), '/');
            $candidate = ABSPATH . WPINC . '/' . $relative;
        } elseif ('//' !== $home_path && 0 === strpos($request_path, $home_path)) {
            $relative = ltrim(substr($request_path, strlen($home_path)), '/');
            $candidate = ABSPATH . $relative;
        } else {
            $candidate = ABSPATH . ltrim($request_path, '/');
        }
        if (!$candidate) {
            return '';
        }
        $real = realpath($candidate);
        if (!$real || !is_file($real)) {
            return '';
        }
        $allowed_bases = array_filter(array_map('realpath', array(ABSPATH, WP_CONTENT_DIR, ABSPATH . WPINC)));
        foreach ($allowed_bases as $base) {
            $base = rtrim(wp_normalize_path($base), '/') . '/';
            if (0 === strpos(wp_normalize_path($real), $base)) {
                return $real;
            }
        }
        return '';
    }
    public static function collect_preload_candidates() {
        $candidates = array();
        $fonts = UCP_Options::get('enable_auto_font_preloads')
            ? self::normalize_multiline(UCP_Options::get('preload_fonts', ''))
            : array();
        if (UCP_Options::get('enable_local_google_fonts') && UCP_Options::get('enable_auto_font_preloads')) {
            $auto_fonts = get_option('ucp_local_font_preload_candidates', array());
            if (is_array($auto_fonts)) {
                $fonts = array_merge($fonts, array_slice($auto_fonts, 0, 3));
            }
        }
        $seen_fonts = array();
        foreach ($fonts as $font_url) {
            $font_url = esc_url_raw($font_url, array('http', 'https'));
            if ($font_url && !isset($seen_fonts[$font_url]) && preg_match('/\.(woff2|woff)(\?|$)/i', $font_url) && (self::is_local_url($font_url) || wp_http_validate_url($font_url))) {
                $seen_fonts[$font_url] = true;
                $candidates[] = array('href' => $font_url, 'as' => 'font');
            }
        }
        $critical_url = self::file_url_from_path(self::get_critical_css_path());
        if ($critical_url && file_exists(self::get_critical_css_path())) {
            $candidates[] = array('href' => $critical_url, 'as' => 'style');
        }
        return $candidates;
    }
}
