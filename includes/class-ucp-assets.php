<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Assets {
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'apply_global_unloads'), 9998);
        add_action('wp_enqueue_scripts', array($this, 'combine_styles'), 9999);
        add_action('wp_enqueue_scripts', array($this, 'combine_scripts'), 9999);
    }


    public function apply_global_unloads() {
        if (is_admin()) {
            return;
        }
        if (class_exists('UCP_Compat') && UCP_Compat::has_optimization_conflict()) {
            ucp_noop('assets', 'Skipped unloads because another optimization plugin is active.', array(
                'conflicts' => UCP_Compat::detected_conflicts(),
            ));
            return;
        }
        global $wp_scripts, $wp_styles;
        $style_handles = UCP_Helpers::normalize_multiline(UCP_Options::get('disabled_style_handles', ''));
        $script_handles = UCP_Helpers::normalize_multiline(UCP_Options::get('disabled_script_handles', ''));

        $conditional_styles = $this->conditional_handles_for_request(UCP_Options::get('conditional_style_unloads', ''));
        $conditional_scripts = $this->conditional_handles_for_request(UCP_Options::get('conditional_script_unloads', ''));
        if (!empty($conditional_styles)) {
            $style_handles = array_values(array_unique(array_merge($style_handles, $conditional_styles)));
        }
        if (!empty($conditional_scripts)) {
            $script_handles = array_values(array_unique(array_merge($script_handles, $conditional_scripts)));
        }

        $dry_run = (bool) UCP_Options::get('enable_asset_test_mode');
        foreach ($style_handles as $handle) {
            if ($handle && wp_style_is($handle, 'registered') && !$this->has_dependents($handle, 'style')) {
                if (!$dry_run) {
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                }
            }
        }
        foreach ($script_handles as $handle) {
            if ($handle && wp_script_is($handle, 'registered') && !$this->has_dependents($handle, 'script')) {
                if (!$dry_run) {
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                }
            }
        }

        if (!empty($style_handles) || !empty($script_handles)) {
            ucp_noop('assets', $dry_run ? 'Dry-run unload report' : 'Applied global unload handles', array(
                'styles' => $style_handles,
                'scripts' => $script_handles,
                'dry_run' => $dry_run,
            ));
        }
    }

    private function conditional_handles_for_request($raw_rules) {
        $rules = UCP_Helpers::normalize_multiline($raw_rules);
        if (empty($rules)) {
            return array();
        }

        $url = UCP_Helpers::current_full_url();
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $matches = array();

        foreach ($rules as $rule) {
            if (false === strpos($rule, '=>')) {
                continue;
            }
            list($pattern, $handles) = array_map('trim', explode('=>', $rule, 2));
            if ('' === $pattern || '' === $handles) {
                continue;
            }
            if (false === stripos($url, $pattern) && false === stripos($path, $pattern)) {
                continue;
            }
            foreach (preg_split('/[,\s]+/', $handles) as $handle) {
                $handle = sanitize_key($handle);
                if ($handle) {
                    $matches[] = $handle;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    public function combine_styles() {
        if (is_admin() || !UCP_Options::get('enable_css_combine') || UCP_Rule_Engine::has_action('disable_css_optimization')) {
            return;
        }
        if (class_exists('UCP_Compat') && UCP_Compat::has_optimization_conflict()) {
            ucp_noop('assets', 'Skipped CSS combine because another optimization plugin is active.', array(
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
            $hash = md5(implode('|', array_keys($items)) . '|' . UCP_VERSION . '|' . (int) UCP_Options::get('enable_css_minify'));
            $target = UCP_CACHE_DIR . 'assets/combined-' . $hash . '.css';
            if (UCP_Options::get('enable_asset_test_mode')) {
                ucp_noop('assets', 'Dry-run CSS combine report', array('media' => $media, 'handles' => array_keys($items)));
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
                        $file = dirname($path) . '/' . ltrim($matches[2], '/');
                        if (file_exists($file)) {
                            $relative = str_replace(WP_CONTENT_DIR, '', $file);
                            return 'url(' . content_url($relative) . ')';
                        }
                        return $matches[0];
                    }, $css);
                    $contents .= "\n/* {$handle} */\n" . $css;
                }
                if (UCP_Options::get('enable_css_minify')) {
                    $contents = UCP_Helpers::minify_css($contents);
                }
                UCP_Helpers::write_file($target, $contents);
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
            ucp_noop('assets', 'Skipped JS combine because another optimization plugin is active.', array(
                'conflicts' => UCP_Compat::detected_conflicts(),
            ));
            return;
        }
        global $wp_scripts;
        if (!$wp_scripts instanceof WP_Scripts || empty($wp_scripts->queue)) {
            return;
        }

        $groups = array();
        $exclusions = apply_filters('ucp_asset_exclusions', apply_filters('ucp_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', ''))));

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
            if ($wp_scripts->get_data($handle, 'data') || $wp_scripts->get_data($handle, 'after') || $wp_scripts->get_data($handle, 'before') || $wp_scripts->get_data($handle, 'strategy')) {
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
            $hash = md5(implode('|', array_keys($items)) . '|' . UCP_VERSION . '|' . (int) UCP_Options::get('enable_js_minify'));
            $target = UCP_CACHE_DIR . 'assets/combined-' . $hash . '.js';
            if (UCP_Options::get('enable_asset_test_mode')) {
                ucp_noop('assets', 'Dry-run JS combine report', array('group' => $group, 'handles' => array_keys($items)));
                continue;
            }
            if (!file_exists($target)) {
                $contents = '';
                foreach ($items as $handle => $path) {
                    $js = UCP_Helpers::read_file($path);
                    if (!is_string($js) || '' === $js) {
                        continue;
                    }
                    $contents .= "\n/* {$handle} */\n;" . $js;
                }
                if (UCP_Options::get('enable_js_minify')) {
                    $contents = UCP_Helpers::minify_js($contents);
                }
                UCP_Helpers::write_file($target, $contents);
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
            if ('' !== $rule && (false !== strpos($handle, $rule) || false !== strpos($src, $rule))) {
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
