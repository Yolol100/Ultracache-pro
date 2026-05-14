<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Assets_Unload_Trait {


        public function apply_global_unloads() {
            if (is_admin()) {
                return;
            }
            if (class_exists('UCP_Compat') && UCP_Compat::has_optimization_conflict()) {
                UCP_Diagnostics::record('assets', 'Skipped unloads because another optimization plugin is active.', array(
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
                UCP_Diagnostics::record('assets', $dry_run ? 'Dry-run unload report' : 'Applied global unload handles', array(
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

}
