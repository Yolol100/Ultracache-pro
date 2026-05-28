<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Quality_Suite_Release_Logs_Trait {
    public static function rest_release_checklist() {
        return self::action_success(__('Release checklist opgehaald.', 'ultracache-pro'), array('checklist' => self::release_checklist()));
    }

    public static function release_checklist() {
        return array(
            array('label' => 'PHP lint', 'command' => 'find wp-content/plugins/ultracache-pro -name "*.php" -print0 | xargs -0 -n1 php -l'),
            array('label' => 'Plugin Check', 'command' => 'wp plugin check ultracache-pro --checks=all'),
            array('label' => 'Runtime cache test', 'action' => 'UltraCache > Diagnostiek > Cache runtime test uitvoeren'),
            array('label' => 'WooCommerce transaction test', 'manual' => 'cart, checkout, order-pay, account, coupon, payment method, order confirmation'),
            array('label' => 'Role/capability test', 'manual' => 'admin works; editor/subscriber/logged-out cannot run privileged REST actions'),
            array('label' => 'Log package', 'action' => 'Download logpakket after QA and verify errors.jsonl is clean'),
        );
    }

    public static function rest_log_viewer(WP_REST_Request $request) {
        $level = sanitize_key((string) $request->get_param('level'));
        $component = sanitize_key((string) $request->get_param('component'));
        $limit = min(300, max(10, absint($request->get_param('limit') ?: 120)));
        return self::action_success(__('Logs opgehaald.', 'ultracache-pro'), array('logs' => self::recent_file_logs($level, $component, $limit)));
    }

    public static function recent_file_logs($level = '', $component = '', $limit = 120) {
        $files = glob(UCP_CACHE_DIR . 'logs/ucp-*.jsonl');
        rsort($files);
        $rows = array();
        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$lines) {
                continue;
            }
            $lines = array_reverse($lines);
            foreach ($lines as $line) {
                $row = json_decode($line, true);
                if (!is_array($row)) {
                    continue;
                }
                if ($level && (!isset($row['level']) || $row['level'] !== $level)) {
                    continue;
                }
                if ($component && (!isset($row['component']) || $row['component'] !== $component)) {
                    continue;
                }
                $rows[] = $row;
                if (count($rows) >= $limit) {
                    return $rows;
                }
            }
        }
        return $rows;
    }
}
