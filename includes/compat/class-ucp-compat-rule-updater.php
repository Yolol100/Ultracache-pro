<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Compat_Rule_Updater {
    const MAX_BYTES = 262144;

    public static function update_from_remote($endpoint = '') {
        if (!UCP_Options::get('compat_remote_updates_enabled', 0)) {
            return array('ok' => false, 'reason' => 'remote_updates_disabled');
        }
        $endpoint = $endpoint ? esc_url_raw($endpoint) : esc_url_raw((string) UCP_Options::get('compat_remote_endpoint', ''));
        $endpoint = apply_filters('ucp_compat_rules_endpoint', $endpoint);
        if (!$endpoint || 0 !== strpos($endpoint, 'https://')) {
            return array('ok' => false, 'reason' => 'https_required');
        }
        $response = wp_safe_remote_get($endpoint, array('timeout' => 12, 'redirection' => 2, 'limit_response_size' => self::MAX_BYTES));
        if (is_wp_error($response)) {
            return array('ok' => false, 'reason' => $response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code >= 400 || !is_string($body) || strlen($body) > self::MAX_BYTES) {
            return array('ok' => false, 'reason' => 'invalid_response');
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return array('ok' => false, 'reason' => 'invalid_json');
        }
        $rules = isset($decoded['rules']) && is_array($decoded['rules']) ? $decoded['rules'] : $decoded;
        $normalized = array();
        foreach ($rules as $rule) {
            $clean = UCP_Compat_Rules::normalize_rule($rule);
            if ($clean) {
                $clean['source'] = 'remote';
                $clean['provenance'] = 'remote';
                $normalized[] = $clean;
            }
        }
        update_option(UCP_Compat_Rules::REMOTE_OPTION, $normalized, false);
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('compat_rules_remote_update', 'success', array('count' => count($normalized)));
        }
        return array('ok' => true, 'count' => count($normalized));
    }
}
