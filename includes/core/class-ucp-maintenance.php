<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Maintenance {
    const CRON_HOOK = 'ucp_maintenance_event';

    public static function bootstrap() {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'run'));
        add_action('admin_post_ucp_run_maintenance', array(__CLASS__, 'handle_manual_run'));
        add_action('admin_init', array(__CLASS__, 'maybe_register_privacy_content'));
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_privacy_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_privacy_eraser'));
    }

    public static function register_schedule($schedules) {
        if (!isset($schedules['daily'])) {
            $schedules['daily'] = array(
                'interval' => DAY_IN_SECONDS,
                'display'  => __('Elke dag', 'ultracache-pro'),
            );
        }
        return $schedules;
    }

    public static function schedule() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'daily', self::CRON_HOOK);
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function run() {
        self::cleanup_logs();
        self::cleanup_diagnostics();
        self::cleanup_jobs();
        ucp_noop('info', 'maintenance', 'maintenance_ran', 'Scheduled maintenance completed.');
    }

    public static function cleanup_logs() {
        global $wpdb;
        $days = max(7, absint(UCP_Options::get('log_retention_days', 30)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . UCP_TABLE_LOGS . ' WHERE created_at < %s', $cutoff));
    }

    public static function cleanup_diagnostics() {
        global $wpdb;
        $days = max(7, absint(UCP_Options::get('diagnostics_retention_days', 14)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . UCP_TABLE_DIAGNOSTICS . ' WHERE generated_at < %s', $cutoff));
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'diagnostics/*.json');
    }

    public static function cleanup_jobs() {
        global $wpdb;
        $days = max(7, absint(UCP_Options::get('job_retention_days', 14)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . UCP_TABLE_JOBS . ' WHERE status IN (%s,%s) AND updated_at < %s', 'success', 'failed', $cutoff));
    }

    public static function handle_manual_run() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_run_maintenance');
        self::run();
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&maintenance=1'));
        exit;
    }

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

        $log_rows = $wpdb->get_results($wpdb->prepare('SELECT id, level, component, event, message, context, request_url, created_at FROM ' . UCP_TABLE_LOGS . ' ORDER BY id ASC LIMIT %d OFFSET %d', $limit, $offset), ARRAY_A);
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

        $diag_rows = $wpdb->get_results($wpdb->prepare('SELECT id, url, path, notes, asset_summary, generated_at FROM ' . UCP_TABLE_DIAGNOSTICS . ' ORDER BY id ASC LIMIT %d OFFSET %d', $limit, $offset), ARRAY_A);
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

        $log_rows = $wpdb->get_results($wpdb->prepare('SELECT id, message, context, request_url FROM ' . UCP_TABLE_LOGS . ' ORDER BY id ASC LIMIT %d OFFSET %d', $limit, $offset), ARRAY_A);
        foreach ((array) $log_rows as $row) {
            if (!self::email_like_match($row['message'], $email_address) && !self::email_like_match($row['context'], $email_address) && !self::email_like_match($row['request_url'], $email_address)) {
                continue;
            }
            $updated = $wpdb->update(
                UCP_TABLE_LOGS,
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

        $diag_rows = $wpdb->get_results($wpdb->prepare('SELECT id, url, notes, asset_summary FROM ' . UCP_TABLE_DIAGNOSTICS . ' ORDER BY id ASC LIMIT %d OFFSET %d', $limit, $offset), ARRAY_A);
        foreach ((array) $diag_rows as $row) {
            if (!self::email_like_match($row['url'], $email_address) && !self::email_like_match($row['notes'], $email_address) && !self::email_like_match($row['asset_summary'], $email_address)) {
                continue;
            }
            $updated = $wpdb->update(
                UCP_TABLE_DIAGNOSTICS,
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
