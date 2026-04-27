<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Fonts {
    public function __construct() {
        add_filter('style_loader_tag', array($this, 'localize_google_fonts'), 20, 4);
    }

    public function localize_google_fonts($html, $handle, $href, $media) {
        if (empty(UCP_Options::get('enable_local_google_fonts')) || empty($href)) {
            return $html;
        }
        if (false === strpos($href, 'fonts.googleapis.com')) {
            return $html;
        }
        $local = $this->cached_css_url($href);
        if (!$local) {
            return $html;
        }
        return str_replace(esc_url($href), esc_url($local), $html);
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
        if (!file_exists($file) || filemtime($file) < (time() - WEEK_IN_SECONDS)) {
            $response = wp_remote_get($href, array('timeout' => 8, 'user-agent' => 'Mozilla/5.0 UltraCache Pro'));
            if (is_wp_error($response)) {
                return false;
            }
            $body = wp_remote_retrieve_body($response);
            if (!$body || false !== stripos($body, '<html')) {
                return false;
            }
            $body = $this->localize_font_files($body, $dir, trailingslashit($uploads['baseurl']) . 'ultracache-pro/fonts/');
            file_put_contents($file, $body);
        }
        return trailingslashit($uploads['baseurl']) . 'ultracache-pro/fonts/' . basename($file);
    }

    protected function localize_font_files($css, $dir, $baseurl) {
        if (!preg_match_all('#url\(([^)]+fonts\.gstatic\.com/[^)]+)\)#i', $css, $matches)) {
            return $css;
        }
        foreach ($matches[1] as $raw_url) {
            $font_url = trim($raw_url, '\'" ');
            $path = wp_parse_url($font_url, PHP_URL_PATH);
            $ext = pathinfo((string) $path, PATHINFO_EXTENSION);
            if (!$ext || !in_array(strtolower($ext), array('woff2', 'woff', 'ttf'), true)) {
                $ext = 'woff2';
            }
            $filename = md5($font_url) . '.' . sanitize_key($ext);
            $target = trailingslashit($dir) . $filename;
            if (!file_exists($target)) {
                $response = wp_remote_get($font_url, array('timeout' => 10, 'user-agent' => 'Mozilla/5.0 UltraCache Pro'));
                if (is_wp_error($response)) {
                    continue;
                }
                $body = wp_remote_retrieve_body($response);
                $type = wp_remote_retrieve_header($response, 'content-type');
                if (!$body || false === stripos((string) $type, 'font')) {
                    continue;
                }
                file_put_contents($target, $body);
            }
            $css = str_replace($font_url, esc_url_raw($baseurl . $filename), $css);
        }
        return $css;
    }
}
