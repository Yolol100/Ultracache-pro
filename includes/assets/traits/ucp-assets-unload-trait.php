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

        $advanced = $this->advanced_asset_rules_for_request();
        if (!empty($advanced['disable']['style'])) {
            $style_handles = array_values(array_unique(array_merge($style_handles, $advanced['disable']['style'])));
        }
        if (!empty($advanced['disable']['script'])) {
            $script_handles = array_values(array_unique(array_merge($script_handles, $advanced['disable']['script'])));
        }
        if (!empty($advanced['keep']['style'])) {
            $style_handles = $this->remove_kept_handles($style_handles, $advanced['keep']['style']);
        }
        if (!empty($advanced['keep']['script'])) {
            $script_handles = $this->remove_kept_handles($script_handles, $advanced['keep']['script']);
        }

        $test_mode = (bool) UCP_Options::get('enable_asset_test_mode');
        if ($test_mode && !current_user_can('manage_options')) {
            return;
        }
        $applied = array('styles' => array(), 'scripts' => array(), 'skipped' => array());

        foreach ($style_handles as $handle) {
            $handle = sanitize_key($handle);
            if (!$handle) {
                continue;
            }
            if (wp_style_is($handle, 'registered') && !$this->has_dependents($handle, 'style')) {
                $applied['styles'][] = $handle;
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            } elseif (wp_style_is($handle, 'registered')) {
                $applied['skipped'][] = 'style:' . $handle . ':has_dependents';
            }
        }
        foreach ($script_handles as $handle) {
            $handle = sanitize_key($handle);
            if (!$handle) {
                continue;
            }
            if (wp_script_is($handle, 'registered') && !$this->has_dependents($handle, 'script')) {
                $applied['scripts'][] = $handle;
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            } elseif (wp_script_is($handle, 'registered')) {
                $applied['skipped'][] = 'script:' . $handle . ':has_dependents';
            }
        }

        if (!empty($style_handles) || !empty($script_handles) || !empty($advanced['matched_rules'])) {
            UCP_Diagnostics::record('assets', $test_mode ? 'Applied asset manager rules in admin test mode' : 'Applied asset manager rules', array(
                'styles' => $style_handles,
                'scripts' => $script_handles,
                'advanced_rules' => $advanced['matched_rules'],
                'test_mode' => $test_mode,
                'result' => $applied,
            ));
        }
    }

    public function capture_frontend_asset_snapshot() {
        if (is_admin() || !UCP_Options::get('enable_asset_manager_snapshot') || !current_user_can('manage_options')) {
            return;
        }

        global $wp_scripts, $wp_styles;
        $snapshot = array(
            'captured_at' => time(),
            'url' => UCP_Helpers::current_full_url(),
            'context' => $this->asset_manager_request_context(),
            'styles' => $this->snapshot_registry($wp_styles instanceof WP_Styles ? $wp_styles : null, 'style'),
            'scripts' => $this->snapshot_registry($wp_scripts instanceof WP_Scripts ? $wp_scripts : null, 'script'),
        );
        update_option('ucp_asset_manager_last_snapshot', $snapshot, false);
    }

    private function snapshot_registry($registry, $kind) {
        if (!$registry || empty($registry->registered)) {
            return array();
        }

        $queue = isset($registry->queue) && is_array($registry->queue) ? $registry->queue : array();
        $done = isset($registry->done) && is_array($registry->done) ? $registry->done : array();
        $reverse = array();
        foreach ($registry->registered as $registered_handle => $registered_obj) {
            if (empty($registered_obj->deps)) {
                continue;
            }
            foreach ((array) $registered_obj->deps as $dependency_handle) {
                $dependency_handle = sanitize_key($dependency_handle);
                if ($dependency_handle) {
                    $reverse[$dependency_handle][] = sanitize_key($registered_handle);
                }
            }
        }
        $items = array();
        foreach ($registry->registered as $handle => $obj) {
            if (!in_array($handle, $queue, true) && !in_array($handle, $done, true)) {
                continue;
            }
            $items[] = array(
                'handle' => sanitize_key($handle),
                'kind' => $kind,
                'src' => isset($obj->src) ? esc_url_raw((string) $obj->src) : '',
                'deps' => !empty($obj->deps) ? array_values(array_map('sanitize_key', (array) $obj->deps)) : array(),
                'dependents' => isset($reverse[sanitize_key($handle)]) ? array_values(array_unique($reverse[sanitize_key($handle)])) : array(),
                'queued' => in_array($handle, $queue, true),
                'done' => in_array($handle, $done, true),
            );
        }
        return array_slice($items, 0, 250);
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

    private function advanced_asset_rules_for_request() {
        $result = array(
            'disable' => array('style' => array(), 'script' => array()),
            'keep' => array('style' => array(), 'script' => array()),
            'matched_rules' => array(),
        );
        if (UCP_Options::get('enable_asset_test_mode') && !current_user_can('manage_options')) {
            return $result;
        }

        $rules = UCP_Helpers::normalize_multiline(UCP_Options::get('advanced_asset_rules', ''));
        if (empty($rules)) {
            return $result;
        }
        foreach ($rules as $line) {
            $rule = $this->parse_advanced_asset_rule($line);
            if (empty($rule) || !$this->asset_rule_matches_request($rule)) {
                continue;
            }
            $result[$rule['action']][$rule['kind']][] = $rule['handle'];
            $result['matched_rules'][] = $rule['kind'] . ':' . $rule['handle'] . ':' . $rule['action'] . ':' . $rule['scope'];
        }
        foreach (array('disable', 'keep') as $action) {
            foreach (array('style', 'script') as $kind) {
                $result[$action][$kind] = array_values(array_unique(array_filter($result[$action][$kind])));
            }
        }
        return $result;
    }

    private function parse_advanced_asset_rule($line) {
        $parts = array_map('trim', explode('|', (string) $line));
        if (count($parts) < 3) {
            return array();
        }
        $kind = sanitize_key($parts[0]);
        $handle = sanitize_key($parts[1]);
        $action = sanitize_key($parts[2]);
        $scope = isset($parts[3]) ? sanitize_key($parts[3]) : 'all';
        $value = isset($parts[4]) ? trim((string) $parts[4]) : '';

        if (!in_array($kind, array('style', 'script'), true) || '' === $handle || !in_array($action, array('disable', 'keep'), true)) {
            return array();
        }
        if (!in_array($scope, array('all', 'url_contains', 'path_contains', 'post_type', 'archive', 'device', 'logged_in', 'logged_out', 'regex', 'front_page', 'singular', '404'), true)) {
            $scope = 'all';
        }
        return array(
            'kind' => $kind,
            'handle' => $handle,
            'action' => $action,
            'scope' => $scope,
            'value' => $value,
        );
    }

    private function asset_rule_matches_request($rule) {
        $url = UCP_Helpers::current_full_url();
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $value = isset($rule['value']) ? (string) $rule['value'] : '';

        switch ($rule['scope']) {
            case 'all':
                return true;
            case 'url_contains':
                return '' !== $value && false !== stripos($url, $value);
            case 'path_contains':
                return '' !== $value && false !== stripos($path, $value);
            case 'post_type':
                return '' !== $value && is_singular($value);
            case 'archive':
                return is_archive() && ('' === $value || is_post_type_archive($value) || is_tax($value) || is_category($value) || is_tag($value));
            case 'device':
                if ('mobile' === strtolower($value)) {
                    return wp_is_mobile();
                }
                if ('desktop' === strtolower($value)) {
                    return !wp_is_mobile();
                }
                return false;
            case 'logged_in':
                return is_user_logged_in();
            case 'logged_out':
                return !is_user_logged_in();
            case 'regex':
                return class_exists('UCP_Helpers') && UCP_Helpers::safe_regex_match($value, $url);
            case 'front_page':
                return is_front_page();
            case 'singular':
                return is_singular();
            case '404':
                return is_404();
        }
        return false;
    }

    private function remove_kept_handles($handles, $kept) {
        if (empty($handles) || empty($kept)) {
            return $handles;
        }
        return array_values(array_filter($handles, function ($handle) use ($kept) {
            return !in_array($handle, $kept, true);
        }));
    }

    private function asset_manager_request_context() {
        $post_type = '';
        if (is_singular()) {
            $post_type = get_post_type();
        } elseif (is_post_type_archive()) {
            $post_type = (string) get_query_var('post_type');
        }
        return array(
            'device' => wp_is_mobile() ? 'mobile' : 'desktop',
            'logged_in' => is_user_logged_in(),
            'front_page' => is_front_page(),
            'singular' => is_singular(),
            'archive' => is_archive(),
            '404' => is_404(),
            'post_type' => is_string($post_type) ? $post_type : '',
        );
    }

}
