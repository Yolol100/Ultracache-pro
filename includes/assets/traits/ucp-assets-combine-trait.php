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
        $active_group = '';
        $exclusions = apply_filters('ucp_asset_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('css_exclusions', '')));

        foreach ($wp_styles->queue as $handle) {
            if (empty($wp_styles->registered[$handle])) {
                $active_group = '';
                continue;
            }
            $obj = $wp_styles->registered[$handle];
            $src = $obj->src;
            $media = !empty($obj->args) ? $obj->args : 'all';
            if (!$src || !UCP_Helpers::is_local_url($src) || !$this->is_combinable($handle, $src, $exclusions)) {
                $active_group = '';
                continue;
            }
            if (!empty($obj->deps)
                || $wp_styles->get_data($handle, 'conditional')
                || $wp_styles->get_data($handle, 'after')
                || $wp_styles->get_data($handle, 'alt')
                || $wp_styles->get_data($handle, 'title')
                || $wp_styles->get_data($handle, 'rtl')) {
                $active_group = '';
                continue;
            }
            if ($this->has_dependents($handle, 'style')) {
                $active_group = '';
                continue;
            }
            $path = UCP_Helpers::local_path_from_url($this->normalize_asset_url($src));
            if (!$path || !is_file($path) || !is_readable($path)) {
                $active_group = '';
                continue;
            }
            if ('' === $active_group || !isset($groups[$active_group]) || $groups[$active_group]['media'] !== $media) {
                $active_group = 'segment-' . count($groups);
                $groups[$active_group] = array('media' => $media, 'items' => array());
            }
            $groups[$active_group]['items'][$handle] = $path;
        }

        foreach ($groups as $group_data) {
            $media = $group_data['media'];
            $items = $group_data['items'];
            $max_items = max(2, min(100, absint(apply_filters('ucp_css_combine_max_sources', 50, $media))));
            if (count($items) < 2 || count($items) > $max_items) {
                if (count($items) > $max_items) {
                    UCP_Diagnostics::record('assets', 'Skipped CSS combine because the source group exceeds the safety cap.', array('media' => $media, 'handles' => array_keys($items), 'max_sources' => $max_items));
                }
                continue;
            }
            $mtime_parts = array();
            foreach ($items as $mtime_handle => $mtime_path) {
                $mtime_parts[] = $mtime_handle . ':' . (string) @filemtime($mtime_path) . ':' . (string) @filesize($mtime_path);
            }
            $hash = md5(implode('|', $mtime_parts) . '|' . UCP_VERSION . '|combine-safe-v2|' . (int) UCP_Options::get('enable_css_minify'));
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
                $source_contents = array();
                $unsafe_reason = '';
                $source_bytes = 0;
                $max_source_bytes = max(64 * KB_IN_BYTES, min(10 * MB_IN_BYTES, absint(apply_filters('ucp_css_combine_max_source_bytes', 5 * MB_IN_BYTES))));
                $max_total_bytes = max($max_source_bytes, min(40 * MB_IN_BYTES, absint(apply_filters('ucp_css_combine_max_total_bytes', 20 * MB_IN_BYTES))));
                foreach ($items as $handle => $path) {
                    $css = UCP_Helpers::read_file($path, $max_source_bytes);
                    if (!is_string($css) || '' === trim($css)) {
                        $unsafe_reason = 'source_unreadable';
                        break;
                    }
                    $source_bytes += strlen($css);
                    if ($source_bytes > $max_total_bytes) {
                        $unsafe_reason = 'source_group_too_large';
                        break;
                    }
                    $css_for_import_scan = UCP_Helpers::safe_preg_replace('#/\*.*?\*/#s', '', $css);
                    if (is_string($css_for_import_scan) && preg_match('/@import\b/i', $css_for_import_scan)) {
                        $unsafe_reason = 'source_contains_import';
                        break;
                    }
                    $source_contents[$handle] = $css;
                }
                if ('' !== $unsafe_reason || count($source_contents) !== count($items)) {
                    UCP_Diagnostics::record('assets', 'Skipped CSS combine because the source group is not safely relocatable.', array('reason' => $unsafe_reason, 'media' => $media, 'handles' => array_keys($items)));
                    continue;
                }
                $charset = '';
                foreach ($source_contents as $handle => $css) {
                    $path = $items[$handle];
                    $css = UCP_Helpers::safe_preg_replace_callback('/url\((["\']?)(?!data:|https?:|\/)([^)"\']+)\1\)/i', function ($matches) use ($path) {
                        $resolved = $this->rewrite_relative_css_url($path, $matches[2]);
                        return '' !== $resolved ? $resolved : $matches[0];
                    }, $css);
                    $parts = $this->split_css_header_at_rules($css);
                    if ('' === $charset && preg_match('/@charset\s+(["\'][^"\']+["\'])\s*;/i', $parts['header'], $charset_match)) {
                        $charset = '@charset ' . $charset_match[1] . ";\n";
                    }
                    $contents .= "\n" . $parts['body'];
                }
                $contents = $charset . $contents;
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
            $first_handle = array_key_first($items);
            $first_position = array_search($first_handle, $wp_styles->queue, true);
            foreach (array_keys($items) as $handle) {
                if ($this->has_dependents($handle, 'style')) {
                    continue;
                }
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
            $combined_handle = 'ucp-combined-' . md5($hash . $media);
            wp_enqueue_style($combined_handle, UCP_Helpers::file_url_from_path($target), array(), filemtime($target), $media);
            if (false !== $first_position && isset($wp_styles->queue) && is_array($wp_styles->queue)) {
                $wp_styles->queue = array_values(array_filter($wp_styles->queue, static function ($queued_handle) use ($combined_handle) {
                    return $queued_handle !== $combined_handle;
                }));
                array_splice($wp_styles->queue, min((int) $first_position, count($wp_styles->queue)), 0, array($combined_handle));
            }
        }
    }

    private function split_css_header_at_rules($css) {
        $body = (string) $css;
        $header = '';
        $charset_seen = false;

        while (preg_match('/\A\s*(?:\/\*.*?\*\/\s*)*((?:@charset\s+(["\'][^"\']+["\'])|@import\s+(?:url\([^)]+\)|["\'][^"\']+["\'])(?:\s+[^;]+)?)\s*;)/is', $body, $match)) {
            $rule = trim((string) $match[1]);
            $is_charset = 0 === stripos($rule, '@charset');
            if (!$is_charset || !$charset_seen) {
                $header .= $rule . "\n";
            }
            if ($is_charset) {
                $charset_seen = true;
            }
            $body = substr($body, strlen($match[0]));
        }

        return array(
            'header' => $header,
            'body'   => $body,
        );
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
        $active_group = '';
        $exclusions = array_merge(
            apply_filters('ucp_asset_exclusions', apply_filters('ucp_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', '')))),
            UCP_Helpers::normalize_multiline(UCP_Options::get('js_combine_exclusions', ''))
        );

        foreach ($wp_scripts->queue as $handle) {
            if (empty($wp_scripts->registered[$handle])) {
                $active_group = '';
                continue;
            }
            $obj = $wp_scripts->registered[$handle];
            $src = $obj->src;
            $group = (int) $wp_scripts->get_data($handle, 'group');
            if (!$src || !UCP_Helpers::is_local_url($src) || !$this->is_combinable($handle, $src, $exclusions)) {
                $active_group = '';
                continue;
            }
            if (!empty($obj->deps)
                || !empty($obj->textdomain)
                || !empty($obj->translations_path)
                || $wp_scripts->get_data($handle, 'data')
                || $wp_scripts->get_data($handle, 'after')
                || $wp_scripts->get_data($handle, 'before')
                || $wp_scripts->get_data($handle, 'strategy')
                || $wp_scripts->get_data($handle, 'nonce')
                || $wp_scripts->get_data($handle, 'conditional')
                || $wp_scripts->get_data($handle, 'integrity')
                || $wp_scripts->get_data($handle, 'crossorigin')
                || $wp_scripts->get_data($handle, 'referrerpolicy')
                || $wp_scripts->get_data($handle, 'nomodule')) {
                $active_group = '';
                continue;
            }
            if ($wp_scripts->get_data($handle, 'type')) {
                $active_group = '';
                continue;
            }
            if ($this->has_dependents($handle, 'script')) {
                $active_group = '';
                continue;
            }
            $path = UCP_Helpers::local_path_from_url($this->normalize_asset_url($src));
            if (!$path || !is_file($path) || !is_readable($path)) {
                $active_group = '';
                continue;
            }
            if ('' === $active_group || !isset($groups[$active_group]) || $groups[$active_group]['group'] !== $group) {
                $active_group = 'segment-' . count($groups);
                $groups[$active_group] = array('group' => $group, 'items' => array());
            }
            $groups[$active_group]['items'][$handle] = $path;
        }

        foreach ($groups as $group_data) {
            $group = $group_data['group'];
            $items = $group_data['items'];
            $max_items = max(2, min(100, absint(apply_filters('ucp_js_combine_max_sources', 50, $group))));
            if (count($items) < 2 || count($items) > $max_items) {
                if (count($items) > $max_items) {
                    UCP_Diagnostics::record('assets', 'Skipped JS combine because the source group exceeds the safety cap.', array('group' => $group, 'handles' => array_keys($items), 'max_sources' => $max_items));
                }
                continue;
            }
            $mtime_parts = array();
            foreach ($items as $mtime_handle => $mtime_path) {
                $mtime_parts[] = $mtime_handle . ':' . (string) @filemtime($mtime_path) . ':' . (string) @filesize($mtime_path);
            }
            $hash = md5(implode('|', $mtime_parts) . '|' . UCP_VERSION . '|combine-safe-v2|' . (int) UCP_Options::get('enable_js_minify'));
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
                $source_contents = array();
                $source_bytes = 0;
                $max_source_bytes = max(64 * KB_IN_BYTES, min(20 * MB_IN_BYTES, absint(apply_filters('ucp_js_combine_max_source_bytes', 10 * MB_IN_BYTES))));
                $max_total_bytes = max($max_source_bytes, min(60 * MB_IN_BYTES, absint(apply_filters('ucp_js_combine_max_total_bytes', 30 * MB_IN_BYTES))));
                foreach ($items as $handle => $path) {
                    $js = UCP_Helpers::read_file($path, $max_source_bytes);
                    if (!is_string($js) || '' === trim($js)) {
                        $source_contents = array();
                        break;
                    }
                    $source_bytes += strlen($js);
                    if ($source_bytes > $max_total_bytes) {
                        $source_contents = array();
                        break;
                    }
                    $source_contents[$handle] = $js;
                }
                if (count($source_contents) !== count($items)) {
                    UCP_Diagnostics::record('assets', 'Skipped JS combine because one or more sources could not be read safely.', array('group' => $group, 'handles' => array_keys($items)));
                    continue;
                }
                foreach ($source_contents as $js) {
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
            $first_handle = array_key_first($items);
            $first_position = array_search($first_handle, $wp_scripts->queue, true);
            foreach (array_keys($items) as $handle) {
                if ($this->has_dependents($handle, 'script')) {
                    continue;
                }
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            }
            $combined_handle = 'ucp-combined-' . md5($hash . $group);
            wp_enqueue_script($combined_handle, UCP_Helpers::file_url_from_path($target), array(), filemtime($target), $group > 0);
            if (false !== $first_position && isset($wp_scripts->queue) && is_array($wp_scripts->queue)) {
                $wp_scripts->queue = array_values(array_filter($wp_scripts->queue, static function ($queued_handle) use ($combined_handle) {
                    return $queued_handle !== $combined_handle;
                }));
                array_splice($wp_scripts->queue, min((int) $first_position, count($wp_scripts->queue)), 0, array($combined_handle));
            }
        }
    }

}
