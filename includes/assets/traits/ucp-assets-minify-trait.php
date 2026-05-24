<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Assets_Minify_Trait {


        public function maybe_minify_style_tag($tag, $handle, $href, $media) {
            if (is_admin() || !UCP_Options::get('enable_css_minify') || UCP_Options::get('enable_css_combine') || UCP_Rule_Engine::has_action('disable_css_optimization')) {
                return $tag;
            }
            $exclusions = apply_filters('ucp_asset_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('css_exclusions', '')));
            if (!$href || !UCP_Helpers::is_local_url($href) || !$this->is_combinable($handle, $href, $exclusions)) {
                return $tag;
            }
            $min_url = $this->minified_asset_url($href, 'css');
            if (!$min_url) {
                return $tag;
            }
            return str_replace($href, esc_url($min_url), $tag);
        }


        public function maybe_minify_script_tag($tag, $handle, $src) {
            if (is_admin() || !UCP_Options::get('enable_js_minify') || !UCP_Options::get('allow_experimental_js_minify') || UCP_Options::get('enable_js_combine') || UCP_Options::get('enable_delay_js') || UCP_Rule_Engine::has_action('disable_js_optimization')) {
                return $tag;
            }
            $exclusions = apply_filters('ucp_asset_exclusions', apply_filters('ucp_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', ''))));
            if (!$src || !UCP_Helpers::is_local_url($src) || !$this->is_combinable($handle, $src, $exclusions)) {
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

            $contents = UCP_Helpers::read_file($path);
            if (!is_string($contents) || '' === trim($contents)) {
                return '';
            }

            $original_bytes = strlen($contents);
            if ($original_bytes < 2048 || preg_match('/\.min\.' . preg_quote($type, '/') . '$/i', $path)) {
                return '';
            }

            if ('css' === $type) {
                $contents = preg_replace_callback('/url\((["\']?)(?!data:|https?:|\/)([^)"\']+)\1\)/i', function ($matches) use ($path) {
                    $file = realpath(dirname($path) . '/' . ltrim($matches[2], '/'));
                    if ($file && file_exists($file) && 0 === strpos($file, WP_CONTENT_DIR)) {
                        $relative = str_replace(WP_CONTENT_DIR, '', $file);
                        return 'url(' . content_url($relative) . ')';
                    }
                    return $matches[0];
                }, $contents);
                $minified = UCP_Helpers::minify_css($contents);
            } else {
                if ($this->js_minify_candidate_is_risky($contents)) {
                    return '';
                }
                $minified = UCP_Helpers::minify_js($contents);
            }

            $minified = trim((string) $minified);
            $min_bytes = strlen($minified);
            if ('' === $minified || $min_bytes < 20) {
                return '';
            }
            // FlyingPress-style safety: only serve the generated file when it saves at least 2KB and 10%.
            if (($original_bytes - $min_bytes) < 2048 || $min_bytes > (int) floor($original_bytes * 0.90)) {
                return '';
            }

            $hash = substr(hash('sha256', $minified . '|' . UCP_VERSION . '|' . $type), 0, 20);
            $dir = trailingslashit(UCP_CACHE_DIR) . 'min/';
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            UCP_Helpers::write_file($dir . 'index.html', '');
            $target = $dir . $hash . '.' . $type;
            if (!file_exists($target)) {
                $tmp = $target . '.tmp.' . wp_generate_password(8, false, false);
                if (!UCP_Helpers::write_file($tmp, $minified) || !@rename($tmp, $target)) {
                    @unlink($tmp);
                    return '';
                }
            }
            return UCP_Helpers::file_url_from_path($target);
        }


        /**
         * Keep the experimental JS minifier conservative. It is not a full parser, so
         * skip files that contain syntax frequently misread by lightweight minifiers.
         *
         * @param string $contents JavaScript source.
         * @return bool
         */
        private function js_minify_candidate_is_risky($contents) {
            $contents = (string) $contents;
            if (preg_match('/(^|[=(:,!&|?;{}\[])\s*\/(?![\/*])/', $contents)) {
                return true;
            }
            if (preg_match('/\b(?:import|export)\s+(?:\{|default|from|\*)/m', $contents)) {
                return true;
            }
            if (false !== strpos($contents, 'sourceMappingURL=')) {
                return true;
            }
            return false;
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
