<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin-owned queue table queries use controlled table constants and prepared/sanitized values.

trait UCP_Jobs_Payload_Trait {
    protected static function normalize_job_payload($payload) {
        if (!is_array($payload)) {
            return array();
        }
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::normalize_job_payload($value);
                continue;
            }
            if (class_exists('UCP_Helpers') && in_array((string) $key, array('url', 'href', 'endpoint'), true)) {
                $payload[$key] = esc_url_raw(UCP_Helpers::normalize_url_syntax($value));
            }
        }
        $is_numeric_array = function_exists('wp_is_numeric_array') ? wp_is_numeric_array($payload) : array_keys($payload) === range(0, count($payload) - 1);
        if ($is_numeric_array) {
            return array_values($payload);
        }
        ksort($payload);
        return $payload;
    }

    protected static function encode_job_payload($payload) {
        return wp_json_encode(self::normalize_job_payload(is_array($payload) ? $payload : array()));
    }

    public static function build_job_signature($type, $payload = array(), $queue = 'default') {
        return hash('sha256', sanitize_key($queue) . '|' . sanitize_key($type) . '|' . self::encode_job_payload($payload));
    }
}
