<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Assets_Combine_Trait {

        public function combine_styles() {
            if (is_admin() || !UCP_Options::get('enable_css_combine') || UCP_Rule_Engine::has_action('disable_css_optimization')) {
                return;
            }
            if (class_exists('UCP_Compat') && UCP_Compat::has_optimization_conflict()) {
                UCP_Diagnostics::record('assets', 'Skipped CSS combine because another optimization plugin is active.', array(
                    'conflicts' => UCP_Compat::detected_conflicts(),
                ));
                return;
            }
            global $wp_styles;
            if (!$wp_styles instanceof WP_Styles || empty($wp_styles->queue)) {
                return;
            }

            $groups = array();
            $exclusions = apply_filters('ucp_asset_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('css_exclusions', '')));

            foreach ($wp_styles->queue as $handle) {
                if (empty($wp_styles->registered[$handle])) {
                    continue;
                }
                $obj = $wp_styles->registered[$handle];
                $src = $obj->src;
                $media = !empty($obj->args) ? $obj->args : 'all';
                if (!$src || !UCP_Helpers::is_local_url($src) || !$this->is_combinable($handle, $src, $exclusions)) {
                    continue;
                }
                if ($wp_styles->get_data($handle, 'conditional') || $wp_styles->get_data($handle, 'after') || $wp_styles->get_data($handle, 'alt')) {
                    continue;
                }
                if ($this->has_dependents($handle, 'style')) {
                    continue;
                }
                $path = UCP_Helpers::local_path_from_url($this->normalize_asset_url($src));
                if (!$path || !file_exists($path)) {
                    continue;
                }
                $groups[$media][$handle] = $path;
            }

            foreach ($groups as $media => $items) {
                if (count($items) < 2) {
                    continue;
                }
                $mtime_parts = array();
                foreach ($items as $mtime_handle => $mtime_path) {
                    $mtime_parts[] = $mtime_handle . ':' . (string) @filemtime($mtime_path) . ':' . (string) @filesize($mtime_path);
                }
                $hash = md5(implode('|', $mtime_parts) . '|' . UCP_VERSION . '|' . (int) UCP_Options::get('enable_css_minify'));
                $target_dir = trailingslashit(UCP_CACHE_DIR) . 'assets/';
                if (!is_dir($target_dir)) {
                    wp_mkdir_p($target_dir);
                }
                UCP_Helpers::write_file($target_dir . 'index.html', '');
                $target = $target_dir . 'combined-' . $hash . '.css';
                if (UCP_Options::get('enable_asset_test_mode')) {
                    UCP_Diagnostics::record('assets', 'Dry-run CSS combine report', array('media' => $media, 'handles' => array_keys($items)));
                    continue;
                }
                if (!file_exists($target)) {
                    $contents = '';
                    foreach ($items as $handle => $path) {
                        $css = UCP_Helpers::read_file($path);
                        if (!is_string($css) || '' === $css) {
                            continue;
                        }
                        $css = preg_replace_callback('/url\((["\']?)(?!data:|https?:|\/)([^)"\']+)\1\)/i', function ($matches) use ($path) {
                            $resolved = $this->rewrite_relative_css_url($path, $matches[2]);
                            return '' !== $resolved ? $resolved : $matches[0];
                        }, $css);
                        $contents .= "\n" . $css;
                    }
                    if (UCP_Options::get('enable_css_minify')) {
                        $contents = UCP_Helpers::minify_css($contents);
                    }
                    if ('' === trim((string) $contents) || !UCP_Helpers::write_file($target, $contents)) {
                        UCP_Diagnostics::record('assets', 'Skipped CSS combine because the combined file could not be written.', array('media' => $media, 'handles' => array_keys($items)));
                        continue;
                    }
                }
                if (!is_file($target) || !is_readable($target)) {
                    UCP_Diagnostics::record('assets', 'Skipped CSS combine because the combined file is unavailable after write.', array('media' => $media));
                    continue;
                }
                foreach (array_keys($items) as $handle) {
                    if ($this->has_dependents($handle, 'style')) {
                        continue;
                    }
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                }
                wp_enqueue_style('ucp-combined-' . md5($hash . $media), UCP_Helpers::file_url_from_path($target), array(), filemtime($target), $media);
            }
        }


        public function combine_scripts() {
            if (is_admin() || !UCP_Options::get('enable_js_combine') || UCP_Rule_Engine::has_action('disable_js_optimization')) {
                return;
            }
            if (class_exists('UCP_Compat') && UCP_Compat::has_optimization_conflict()) {
                UCP_Diagnostics::record('assets', 'Skipped JS combine because another optimization plugin is active.', array(
                    'conflicts' => UCP_Compat::detected_conflicts(),
                ));
                return;
            }
            global $wp_scripts;
            if (!$wp_scripts instanceof WP_Scripts || empty($wp_scripts->queue)) {
                return;
            }

            $groups = array();
            $exclusions = array_merge(
                apply_filters('ucp_asset_exclusions', apply_filters('ucp_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', '')))),
                UCP_Helpers::normalize_multiline(UCP_Options::get('js_combine_exclusions', ''))
            );

            foreach ($wp_scripts->queue as $handle) {
                if (empty($wp_scripts->registered[$handle])) {
                    continue;
                }
                $obj = $wp_scripts->registered[$handle];
                $src = $obj->src;
                $group = (int) $wp_scripts->get_data($handle, 'group');
                if (!$src || !UCP_Helpers::is_local_url($src) || !$this->is_combinable($handle, $src, $exclusions)) {
                    continue;
                }
                if ($wp_scripts->get_data($handle, 'data') || $wp_scripts->get_data($handle, 'after') || $wp_scripts->get_data($handle, 'before') || $wp_scripts->get_data($handle, 'strategy') || $wp_scripts->get_data($handle, 'nonce')) {
                    continue;
                }
                if ($wp_scripts->get_data($handle, 'type') === 'module') {
                    continue;
                }
                if ($this->has_dependents($handle, 'script')) {
                    continue;
                }
                $path = UCP_Helpers::local_path_from_url($this->normalize_asset_url($src));
                if (!$path || !file_exists($path)) {
                    continue;
                }
                $groups[$group][$handle] = $path;
            }

            foreach ($groups as $group => $items) {
                if (count($items) < 2) {
                    continue;
                }
                $mtime_parts = array();
                foreach ($items as $mtime_handle => $mtime_path) {
                    $mtime_parts[] = $mtime_handle . ':' . (string) @filemtime($mtime_path) . ':' . (string) @filesize($mtime_path);
                }
                $hash = md5(implode('|', $mtime_parts) . '|' . UCP_VERSION . '|' . (int) UCP_Options::get('enable_js_minify'));
                $target_dir = trailingslashit(UCP_CACHE_DIR) . 'js/';
                if (!is_dir($target_dir)) {
                    wp_mkdir_p($target_dir);
                }
                UCP_Helpers::write_file($target_dir . 'index.html', '');
                $target = $target_dir . 'combined-' . $hash . '.js';
                if (UCP_Options::get('enable_asset_test_mode')) {
                    UCP_Diagnostics::record('assets', 'Dry-run JS combine report', array('group' => $group, 'handles' => array_keys($items)));
                    continue;
                }
                if (!file_exists($target)) {
                    $contents = '';
                    foreach ($items as $handle => $path) {
                        $js = UCP_Helpers::read_file($path);
                        if (!is_string($js) || '' === $js) {
                            continue;
                        }
                        $contents .= "\n;" . $js . "\n;";
                    }
                    if (UCP_Options::get('enable_js_minify')) {
                        if ($this->js_minify_candidate_is_risky($contents)) {
                            UCP_Diagnostics::record('assets', 'Skipped JS minify for combined file because the source needs a full JavaScript parser.', array('group' => $group, 'handles' => array_keys($items)));
                        } else {
                            $contents = UCP_Helpers::minify_js($contents);
                        }
                    }
                    if ('' === trim((string) $contents) || !UCP_Helpers::write_file($target, $contents)) {
                        UCP_Diagnostics::record('assets', 'Skipped JS combine because the combined file could not be written.', array('group' => $group, 'handles' => array_keys($items)));
                        continue;
                    }
                }
                if (!is_file($target) || !is_readable($target)) {
                    UCP_Diagnostics::record('assets', 'Skipped JS combine because the combined file is unavailable after write.', array('group' => $group));
                    continue;
                }
                foreach (array_keys($items) as $handle) {
                    if ($this->has_dependents($handle, 'script')) {
                        continue;
                    }
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                }
                wp_enqueue_script('ucp-combined-' . md5($hash . $group), UCP_Helpers::file_url_from_path($target), array(), filemtime($target), $group > 0);
            }
        }

}
