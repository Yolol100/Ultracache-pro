<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Audit_Log {
    public static function record($action, $result = 'success', $args = array()) {
        $args = is_array($args) ? $args : array();
        $safe = array();
        foreach ($args as $key => $value) {
            $key = sanitize_key((string) $key);
            if (in_array($key, array('token','api_key','apikey','secret','authorization','headers','cookies','password'), true)) {
                continue;
            }
            if ('urls' === $key && is_array($value)) {
                $safe['url_count'] = count($value);
                $safe['urls'] = array_slice(array_map('esc_url_raw', $value), 0, 10);
                continue;
            }
            if (is_scalar($value) || null === $value) {
                $safe[$key] = sanitize_text_field((string) $value);
            }
        }
        if (function_exists('ucp_noop')) {
            ucp_noop('info', 'audit', sanitize_key((string) $action), 'UltraCache audit event.', array_merge($safe, array('result' => sanitize_key((string) $result))));
        }
    }

    public static function recent($limit = 25, $filter = '') {
        global $wpdb;
        if (empty($wpdb) || !defined('UCP_TABLE_LOGS')) {
            return array();
        }
        $limit = max(1, min(100, absint($limit)));
        $where = 'component = %s';
        $params = array('audit');
        if ('failed' === $filter) {
            $where .= ' AND (context LIKE %s OR event LIKE %s)';
            $params[] = '%failed%';
            $params[] = '%failed%';
        }
        $params[] = $limit;
        $sql = $wpdb->prepare('SELECT event, message, context, created_at FROM ' . UCP_TABLE_LOGS . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT %d', $params);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : array();
    }
}
