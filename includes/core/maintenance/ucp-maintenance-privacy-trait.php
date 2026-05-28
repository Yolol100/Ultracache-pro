<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Scheduled maintenance works on validated plugin-owned custom tables and sanitized retention values.

trait UCP_Maintenance_Privacy_Trait {
    public static function maybe_register_privacy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        wp_add_privacy_policy_content(
            __('UltraCache Pro', 'ultracache-pro'),
            wp_kses_post('<p>' . esc_html__('UltraCache Pro kan logs en controles bewaren voor beheerders. Deze gegevens blijven lokaal in WordPress voor controle en hulp bij problemen.', 'ultracache-pro') . '</p>')
        );
    }


    public static function register_privacy_exporter($exporters) {
        $exporters['ultracache-pro'] = array(
            'exporter_friendly_name' => __('UltraCache Pro gegevens', 'ultracache-pro'),
            'callback'               => array(__CLASS__, 'privacy_exporter'),
        );
        return $exporters;
    }

    public static function register_privacy_eraser($erasers) {
        $erasers['ultracache-pro'] = array(
            'eraser_friendly_name' => __('UltraCache Pro gegevens', 'ultracache-pro'),
            'callback'             => array(__CLASS__, 'privacy_eraser'),
        );
        return $erasers;
    }

    protected static function email_like_match($value, $email_address) {
        if (!is_scalar($value) || '' === (string) $value) {
            return false;
        }
        return false !== stripos((string) $value, (string) $email_address);
    }

    protected static function redact_email_from_text($value, $email_address) {
        if (!is_scalar($value) || '' === (string) $value) {
            return $value;
        }
        return str_ireplace((string) $email_address, '[redacted-email]', (string) $value);
    }

    public static function privacy_exporter($email_address, $page = 1) {
        global $wpdb;

        $email_address = sanitize_email($email_address);
        $page = max(1, absint($page));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $items = array();
        $found = 0;
        $log_rows = array();
        $diag_rows = array();

        if (function_exists('ucp_table_name') && UCP_Helpers::is_safe_table_name(ucp_table_name('logs'))) {
            $logs_table = UCP_Helpers::quote_table_name(ucp_table_name('logs'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table identifier is validated and quoted before use; values are prepared.
            $log_rows = $wpdb->get_results($wpdb->prepare("SELECT id, level, component, event, message, context, request_url, created_at FROM {$logs_table} ORDER BY id ASC LIMIT %d OFFSET %d", $limit, $offset), ARRAY_A);
        }
        foreach ((array) $log_rows as $row) {
            if (!self::email_like_match($row['message'], $email_address) && !self::email_like_match($row['context'], $email_address) && !self::email_like_match($row['request_url'], $email_address)) {
                continue;
            }
            $found++;
            $items[] = array(
                'group_id'    => 'ultracache-pro-logs',
                'group_label' => __('UltraCache Pro logs', 'ultracache-pro'),
                'item_id'     => 'ucp-log-' . absint($row['id']),
                'data'        => array(
                    array('name' => __('Niveau', 'ultracache-pro'), 'value' => $row['level']),
                    array('name' => __('Onderdeel', 'ultracache-pro'), 'value' => $row['component']),
                    array('name' => __('Gebeurtenis', 'ultracache-pro'), 'value' => $row['event']),
                    array('name' => __('Bericht', 'ultracache-pro'), 'value' => $row['message']),
                    array('name' => __('Context', 'ultracache-pro'), 'value' => (string) $row['context']),
                    array('name' => __('URL', 'ultracache-pro'), 'value' => (string) $row['request_url']),
                    array('name' => __('Aangemaakt', 'ultracache-pro'), 'value' => $row['created_at']),
                ),
            );
        }

        if (function_exists('ucp_table_name') && UCP_Helpers::is_safe_table_name(ucp_table_name('diagnostics'))) {
            $diagnostics_table = UCP_Helpers::quote_table_name(ucp_table_name('diagnostics'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table identifier is validated and quoted before use; values are prepared.
            $diag_rows = $wpdb->get_results($wpdb->prepare("SELECT id, url, path, notes, asset_summary, generated_at FROM {$diagnostics_table} ORDER BY id ASC LIMIT %d OFFSET %d", $limit, $offset), ARRAY_A);
        }
        foreach ((array) $diag_rows as $row) {
            if (!self::email_like_match($row['url'], $email_address) && !self::email_like_match($row['notes'], $email_address) && !self::email_like_match($row['asset_summary'], $email_address)) {
                continue;
            }
            $found++;
            $items[] = array(
                'group_id'    => 'ultracache-pro-diagnostics',
                'group_label' => __('UltraCache Pro controles', 'ultracache-pro'),
                'item_id'     => 'ucp-diagnostic-' . absint($row['id']),
                'data'        => array(
                    array('name' => __('URL', 'ultracache-pro'), 'value' => $row['url']),
                    array('name' => __('Pad', 'ultracache-pro'), 'value' => $row['path']),
                    array('name' => __('Notities', 'ultracache-pro'), 'value' => (string) $row['notes']),
                    array('name' => __('Assets', 'ultracache-pro'), 'value' => (string) $row['asset_summary']),
                    array('name' => __('Gegenereerd', 'ultracache-pro'), 'value' => $row['generated_at']),
                ),
            );
        }

        $done = count($log_rows) < $limit && count($diag_rows) < $limit;

        return array(
            'data' => $items,
            'done' => $done,
        );
    }

    public static function privacy_eraser($email_address, $page = 1) {
        global $wpdb;

        $email_address = sanitize_email($email_address);
        $page = max(1, absint($page));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $items_removed = false;
        $items_retained = false;
        $log_rows = array();
        $diag_rows = array();

        if (function_exists('ucp_table_name') && UCP_Helpers::is_safe_table_name(ucp_table_name('logs'))) {
            $logs_table = UCP_Helpers::quote_table_name(ucp_table_name('logs'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table identifier is validated and quoted before use; values are prepared.
            $log_rows = $wpdb->get_results($wpdb->prepare("SELECT id, message, context, request_url FROM {$logs_table} ORDER BY id ASC LIMIT %d OFFSET %d", $limit, $offset), ARRAY_A);
        }
        foreach ((array) $log_rows as $row) {
            if (!self::email_like_match($row['message'], $email_address) && !self::email_like_match($row['context'], $email_address) && !self::email_like_match($row['request_url'], $email_address)) {
                continue;
            }
            $updated = $wpdb->update(
                ucp_table_name('logs'),
                array(
                    'message'     => self::redact_email_from_text($row['message'], $email_address),
                    'context'     => self::redact_email_from_text($row['context'], $email_address),
                    'request_url' => self::redact_email_from_text($row['request_url'], $email_address),
                ),
                array('id' => absint($row['id'])),
                array('%s', '%s', '%s'),
                array('%d')
            );
            if (false !== $updated) {
                $items_removed = true;
            } else {
                $items_retained = true;
            }
        }

        if (function_exists('ucp_table_name') && UCP_Helpers::is_safe_table_name(ucp_table_name('diagnostics'))) {
            $diagnostics_table = UCP_Helpers::quote_table_name(ucp_table_name('diagnostics'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table identifier is validated and quoted before use; values are prepared.
            $diag_rows = $wpdb->get_results($wpdb->prepare("SELECT id, url, notes, asset_summary FROM {$diagnostics_table} ORDER BY id ASC LIMIT %d OFFSET %d", $limit, $offset), ARRAY_A);
        }
        foreach ((array) $diag_rows as $row) {
            if (!self::email_like_match($row['url'], $email_address) && !self::email_like_match($row['notes'], $email_address) && !self::email_like_match($row['asset_summary'], $email_address)) {
                continue;
            }
            $updated = $wpdb->update(
                ucp_table_name('diagnostics'),
                array(
                    'url'           => self::redact_email_from_text($row['url'], $email_address),
                    'notes'         => self::redact_email_from_text($row['notes'], $email_address),
                    'asset_summary' => self::redact_email_from_text($row['asset_summary'], $email_address),
                ),
                array('id' => absint($row['id'])),
                array('%s', '%s', '%s'),
                array('%d')
            );
            if (false !== $updated) {
                $items_removed = true;
            } else {
                $items_retained = true;
            }
        }

        $done = count($log_rows) < $limit && count($diag_rows) < $limit;

        return array(
            'items_removed'  => $items_removed,
            'items_retained' => $items_retained,
            'messages'       => array(),
            'done'           => $done,
        );
    }
}
