<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Compat_Rules {
    const REMOTE_OPTION = 'ucp_remote_compat_rules';

    public static function bundled() {
        $file = UCP_PATH . 'includes/compat/rules/default-rules.php';
        $rules = file_exists($file) ? include $file : array();
        return is_array($rules) ? $rules : array();
    }

    public static function remote() {
        $rules = get_option(self::REMOTE_OPTION, array());
        return is_array($rules) ? $rules : array();
    }

    public static function all() {
        $rules = self::bundled();
        if (UCP_Options::get('compat_remote_updates_enabled', 0)) {
            $rules = array_merge($rules, self::remote());
        }
        return array_values(array_filter(array_map(array(__CLASS__, 'normalize_rule'), $rules)));
    }

    public static function active_matches() {
        $matches = array();
        $active = function_exists('get_option') ? (array) get_option('active_plugins', array()) : array();
        foreach (self::all() as $rule) {
            if (empty($rule['enabled'])) { continue; }
            if (self::matches_signature($rule['signature'], $active)) {
                $matches[] = $rule;
            }
        }
        return $matches;
    }

    protected static function matches_signature($signature, $active_plugins) {
        $signature = is_array($signature) ? $signature : array();
        if (!empty($signature['plugin']) && in_array($signature['plugin'], $active_plugins, true)) { return true; }
        if (!empty($signature['plugins']) && is_array($signature['plugins'])) {
            foreach ($signature['plugins'] as $plugin) {
                if (in_array($plugin, $active_plugins, true)) { return true; }
            }
        }
        if (!empty($signature['class']) && class_exists($signature['class'])) { return true; }
        if (!empty($signature['function']) && function_exists($signature['function'])) { return true; }
        return false;
    }

    public static function normalize_rule($rule) {
        if (!is_array($rule) || empty($rule['id'])) { return null; }
        return array(
            'id' => sanitize_key($rule['id']),
            'version' => sanitize_text_field(isset($rule['version']) ? $rule['version'] : '1.0.0'),
            'signature' => isset($rule['signature']) && is_array($rule['signature']) ? $rule['signature'] : array(),
            'applies_to' => array_values(array_filter(array_map('sanitize_key', (array) ($rule['applies_to'] ?? array())))),
            'affected_feature' => sanitize_key(isset($rule['affected_feature']) ? $rule['affected_feature'] : 'general'),
            'exclusions' => array_values(array_filter(array_map('sanitize_text_field', (array) ($rule['exclusions'] ?? array())))),
            'risk_tags' => array_values(array_filter(array_map('sanitize_key', (array) ($rule['risk_tags'] ?? array())))),
            'message' => sanitize_text_field(isset($rule['message']) ? $rule['message'] : ''),
            'source' => sanitize_key(isset($rule['source']) ? $rule['source'] : 'bundled'),
            'changelog' => sanitize_text_field(isset($rule['changelog']) ? $rule['changelog'] : ''),
            'enabled' => !empty($rule['enabled']),
            'provenance' => sanitize_key(isset($rule['provenance']) ? $rule['provenance'] : 'bundled'),
        );
    }

    public static function dry_run() {
        $matches = self::active_matches();
        $changes = array();
        foreach ($matches as $rule) {
            $changes[] = array(
                'rule' => $rule['id'],
                'feature' => $rule['affected_feature'],
                'exclusions' => $rule['exclusions'],
                'risk_tags' => $rule['risk_tags'],
                'message' => $rule['message'],
            );
        }
        return $changes;
    }

    public static function rollback_remote() {
        delete_option(self::REMOTE_OPTION);
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('compat_rules_rollback', 'success');
        }
    }
}
