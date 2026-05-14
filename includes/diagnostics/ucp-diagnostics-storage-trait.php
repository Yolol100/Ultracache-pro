<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Diagnostics_Storage_Trait {
    public static function persist() {
        global $wpdb;
        $entries = property_exists(__CLASS__, 'entries') ? self::$entries : array();
        if (empty($entries) || is_admin() || !UCP_Options::get('enable_diagnostics')) {
            return;
        }
        $url = UCP_Helpers::current_full_url();
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = $path ? $path : '/';
        $request_type = UCP_Helpers::current_request_category();
        $rules = UCP_Rule_Engine::evaluate_request($url, $request_type);
        $cache_decision = UCP_Rule_Engine::has_action('disable_cache', $url, $request_type) ? 'bypass_by_rule' : 'eligible';
        $module_flags = array(
            'delay_js'     => (bool) UCP_Options::get('enable_delay_js'),
            'used_css'     => (bool) UCP_Options::get('enable_used_css'),
            'critical_css' => (bool) UCP_Options::get('enable_critical_css'),
            'speculation'  => (bool) UCP_Options::get('enable_speculative_loading'),
            'edge_headers' => (bool) UCP_Options::get('enable_edge_cache_headers'),
        );
        $asset_summary = array(
            'css_exclusions'   => UCP_Helpers::normalize_multiline(UCP_Options::get('css_exclusions', '')),
            'js_exclusions'    => apply_filters('ucp_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', ''))),
            'delay_exclusions' => UCP_Helpers::normalize_multiline(UCP_Options::get('delay_js_exclusions', '')),
        );

        $payload = array(
            'generated_at'   => gmdate('c'),
            'url'            => esc_url_raw($url),
            'cache_key'      => UCP_Helpers::cache_key_for_url($url),
            'entries'        => $entries,
            'cache_decision' => $cache_decision,
            'request_type'   => $request_type,
            'rules'          => $rules,
            'module_flags'   => $module_flags,
            'asset_summary'  => $asset_summary,
        );

        UCP_Helpers::write_file(self::get_file($url), wp_json_encode($payload, JSON_PRETTY_PRINT));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $wpdb->insert(
            UCP_TABLE_DIAGNOSTICS,
            array(
                'request_hash'   => md5($url . '|' . gmdate('Y-m-d H:i')),
                'url'            => esc_url_raw($url),
                'path'           => sanitize_text_field($path),
                'request_type'   => sanitize_key($request_type),
                'cache_decision' => sanitize_key($cache_decision),
                'rule_matches'   => wp_json_encode($rules),
                'module_flags'   => wp_json_encode($module_flags),
                'asset_summary'  => wp_json_encode($asset_summary),
                'notes'          => wp_json_encode($entries),
                'generated_at'   => current_time('mysql', true),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    public static function get_file($url = '') {
        return UCP_CACHE_DIR . 'diagnostics/' . UCP_Helpers::cache_key_for_url($url) . '.json';
    }

    public static function read($url = '') {
        $file = self::get_file($url);
        if (!is_readable($file)) {
            return array();
        }
        $decoded = json_decode(UCP_Helpers::read_file($file), true);
        return is_array($decoded) ? $decoded : array();
    }
}
