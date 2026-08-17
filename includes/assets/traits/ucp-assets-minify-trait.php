<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Assets_Minify_Trait {

    public function maybe_minify_style_tag($tag, $handle, $href, $media) {
        if (is_admin() || !UCP_Options::get('enable_css_minify') || UCP_Options::get('enable_css_combine') || UCP_Rule_Engine::has_action('disable_css_optimization')) {
            return $tag;
        }
        $exclusions = apply_filters('ucp_asset_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('css_exclusions', '')));
        if (!$href || !UCP_Helpers::is_local_url($href) || !$this->is_combinable($handle, $href, $exclusions) || preg_match('/\sintegrity\s*=/i', (string) $tag)) {
            return $tag;
        }
        $min_url = $this->minified_asset_url($href, 'css');
        if (!$min_url) {
            return $tag;
        }
        return str_replace($href, esc_url($min_url), $tag);
    }

    public function maybe_minify_script_tag($tag, $handle, $src) {
        if (is_admin() || !UCP_Options::get('enable_js_minify') || UCP_Options::get('enable_js_combine') || UCP_Options::get('enable_delay_js') || UCP_Rule_Engine::has_action('disable_js_optimization')) {
            return $tag;
        }
        $exclusions = apply_filters('ucp_asset_exclusions', apply_filters('ucp_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', ''))));
        if (!$src || !UCP_Helpers::is_local_url($src) || !$this->is_combinable($handle, $src, $exclusions) || preg_match('/\sintegrity\s*=/i', (string) $tag)) {
            return $tag;
        }
        if (false !== stripos($tag, ' type="module"') || false !== stripos($tag, " type='module'")) {
            return $tag;
        }
        $min_url = $this->minified_asset_url($src, 'js');
        if (!$min_url) {
            return $tag;
        }
        return str_replace($src, esc_url($min_url), $tag);
    }

    private function minified_asset_url($src, $type) {
        $normalized = $this->normalize_asset_url($src);
        $path = UCP_Helpers::local_path_from_url($normalized);
        if (!$path || !is_file($path) || !is_readable($path)) {
            return '';
        }
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== $type) {
            return '';
        }
        if (preg_match('/\.min\.' . preg_quote($type, '/') . '$/i', $path)) {
            return '';
        }

        // Fingerprint the *source* (path + mtime + size + plugin version), not the minified
        // output. This lets us decide the cache filename without reading or minifying the file
        // first. A cache-miss page render runs this for every enqueued local asset, so the old
        // approach paid a full parser+minify pass per asset on every miss just to compute the
        // hash. Keying on the source means an already-minified asset costs a single stat().
        $mtime = (int) @filemtime($path);
        $size  = (int) @filesize($path);
        $max_source_bytes = max(64 * KB_IN_BYTES, min(20 * MB_IN_BYTES, absint(apply_filters('ucp_asset_minify_max_source_bytes', 10 * MB_IN_BYTES, $path, $type))));
        if ($size < 2048 || $size > $max_source_bytes) {
            return '';
        }
        $hash   = substr(hash('sha256', $path . '|' . $mtime . '|' . $size . '|' . UCP_VERSION . '|minify-safe-v2|' . $type), 0, 20);
        $dir    = trailingslashit(UCP_CACHE_DIR) . 'min/';
        $target = $dir . $hash . '.' . $type;
        $skip   = $target . '.skip';

        // Fast paths: a previous render already resolved this exact source fingerprint, either
        // to a usable minified file or to a "not worth it" decision recorded in a .skip marker.
        if (is_file($target)) {
            return UCP_Helpers::file_url_from_path($target);
        }
        if (is_file($skip)) {
            return '';
        }

        $contents = UCP_Helpers::read_file($path, $max_source_bytes);
        if (!is_string($contents) || '' === trim($contents)) {
            return '';
        }
        $original_bytes = strlen($contents);

        if ('css' === $type) {
            $css_for_import_scan = UCP_Helpers::safe_preg_replace('#/\*.*?\*/#s', '', $contents);
            if (is_string($css_for_import_scan) && preg_match('/@import\s+["\'](?!data:|https?:|\/|#)/i', $css_for_import_scan)) {
                $this->mark_minify_skipped($dir, $skip);
                return '';
            }
            $contents = UCP_Helpers::safe_preg_replace_callback('/url\((["\']?)(?!data:|https?:|\/)([^)"\']+)\1\)/i', function ($matches) use ($path) {
                $resolved = $this->rewrite_relative_css_url($path, $matches[2]);
                return '' !== $resolved ? $resolved : $matches[0];
            }, $contents);
            $minified = UCP_Helpers::minify_css($contents);
        } else {
            if ($this->js_minify_candidate_is_risky($contents)) {
                $this->mark_minify_skipped($dir, $skip);
                return '';
            }
            $minified = UCP_Helpers::minify_js($contents);
        }

        $minified = trim((string) $minified);
        $min_bytes = strlen($minified);
        if ('' === $minified || $min_bytes < 20) {
            return '';
        }
        if (($original_bytes - $min_bytes) < 2048 || $min_bytes > (int) floor($original_bytes * 0.90)) {
            // The saving is too small to justify a separate request; remember that so the next
            // cache-miss render does not re-read and re-minify an asset we already rejected.
            $this->mark_minify_skipped($dir, $skip);
            return '';
        }

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        UCP_Helpers::write_file($dir . 'index.html', '');
        $tmp = $target . '.tmp.' . wp_generate_password(8, false, false);
        if (!UCP_Helpers::write_file($tmp, $minified) || !UCP_Helpers::move_file($tmp, $target)) {
            UCP_Helpers::safe_delete_file($tmp);
            return '';
        }
        return UCP_Helpers::file_url_from_path($target);
    }

    /**
     * Persist a tiny negative-cache marker next to where the minified file would live, so a
     * source we judged not worth minifying (saving too small, or JS that needs a full parser)
     * is not re-read and re-minified on every subsequent cache-miss render. The marker shares
     * the source fingerprint, so it is invalidated automatically when the source file changes
     * and is removed whenever the min/ cache directory is purged.
     *
     * @param string $dir       Minified-asset cache directory (trailing-slashed).
     * @param string $skip_path Absolute path of the marker file.
     * @return void
     */
    private function mark_minify_skipped($dir, $skip_path) {
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        UCP_Helpers::write_file($dir . 'index.html', '');
        UCP_Helpers::write_file($skip_path, '');
    }

    /**
     * Resolve a relative url() reference inside a stylesheet to an absolute URL while
     * preserving any ?query or #fragment. The filesystem lookup uses only the path
     * portion: cache-busting suffixes such as url(font.woff2?v=4.7.0) otherwise make the
     * referenced file impossible to locate, which previously left the original relative
     * URL untouched and broke the asset once the stylesheet was relocated to the cache
     * directory. Both the resolved file and the base directories are passed through
     * realpath() so symlinked content directories compare correctly, and references that
     * resolve into wp-includes/ABSPATH are handled too instead of producing a broken URL.
     *
     * @param string $source_path Absolute path of the source stylesheet.
     * @param string $raw_ref     Raw reference captured from url(...).
     * @return string Rewritten "url(...)" string, or '' when it cannot be safely resolved.
     */
    private function rewrite_relative_css_url($source_path, $raw_ref) {
        $raw_ref = (string) $raw_ref;
        $suffix = '';
        $cut = strcspn($raw_ref, '?#');
        if ($cut < strlen($raw_ref)) {
            $suffix = substr($raw_ref, $cut);
            $raw_ref = substr($raw_ref, 0, $cut);
        }
        if ('' === $raw_ref) {
            return '';
        }
        $file = realpath(dirname((string) $source_path) . '/' . ltrim($raw_ref, '/'));
        if (false === $file || !is_file($file)) {
            return '';
        }
        $file = wp_normalize_path($file);
        $content_dir = realpath(WP_CONTENT_DIR);
        if ($content_dir) {
            $content_dir = rtrim(wp_normalize_path($content_dir), '/');
            if (0 === strpos($file, $content_dir . '/')) {
                return 'url(' . content_url(substr($file, strlen($content_dir))) . $suffix . ')';
            }
        }
        $abspath = defined('ABSPATH') ? realpath(ABSPATH) : false;
        if ($abspath) {
            $abspath = rtrim(wp_normalize_path($abspath), '/');
            if (0 === strpos($file, $abspath . '/')) {
                return 'url(' . site_url(substr($file, strlen($abspath))) . $suffix . ')';
            }
        }
        return '';
    }

    /**
     * Keep the experimental JS minifier conservative. It is not a full parser, so
     * skip files that contain syntax frequently misread by lightweight minifiers.
     *
     * @param string $contents JavaScript source.
     * @return bool
     */
    private function js_minify_candidate_is_risky($contents) {
        return UCP_Minify_Service::javascript_is_risky($contents);
    }

    private function matches_asset_exclusion($handle, $src, $rule) {
        $rule = trim((string) $rule);
        if ('' === $rule) {
            return false;
        }
        $src = $this->normalize_asset_url($src);
        $path = (string) wp_parse_url($src, PHP_URL_PATH);
        $host = (string) wp_parse_url($src, PHP_URL_HOST);
        $subjects = array((string) $handle, $src, $path, $host);
        $quoted = preg_quote($rule, '#');
        $quoted = str_replace(array('\\(\\.\\*\\)', '\*'), array('.*', '.*'), $quoted);
        if (@preg_match('#' . $quoted . '#i', '') !== false) {
            foreach ($subjects as $subject) {
                if (preg_match('#' . $quoted . '#i', $subject)) {
                    return true;
                }
            }
        }
        foreach ($subjects as $subject) {
            if ('' !== $subject && false !== stripos($subject, $rule)) {
                return true;
            }
        }
        return false;
    }

    private function normalize_asset_url($src) {
        if (0 === strpos($src, '//')) {
            return (is_ssl() ? 'https:' : 'http:') . $src;
        }
        if (0 === strpos($src, '/')) {
            return home_url($src);
        }
        return $src;
    }

    private function is_combinable($handle, $src, $exclusions) {
        foreach ($exclusions as $rule) {
            if ($this->matches_asset_exclusion($handle, $src, $rule)) {
                return false;
            }
        }
        return true;
    }

    private function has_dependents($handle, $type) {
        $registry = 'script' === $type ? wp_scripts() : wp_styles();
        if (!is_object($registry) || empty($registry->registered) || !is_array($registry->registered)) {
            return false;
        }
        foreach ($registry->registered as $registered) {
            if (!empty($registered->deps) && in_array($handle, (array) $registered->deps, true)) {
                return true;
            }
        }
        return false;
    }

}
