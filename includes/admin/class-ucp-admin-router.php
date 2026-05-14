<?php
if (!defined('ABSPATH')) { exit; }

final class UCP_Admin_Router {
    public static function legacy_page_map() {
        return array(
            'ultracache-pro' => 'overview',
            'ultracache-pro-cache' => 'preload',
            'ultracache-pro-file-optimization' => 'optimization',
            'ultracache-pro-media' => 'media',
            'ultracache-pro-preload' => 'preload',
            'ultracache-pro-assets' => 'advanced_rules',
            'ultracache-pro-advanced-rules' => 'advanced_rules',
            'ultracache-pro-assets-manager' => 'advanced_rules',
            'ultracache-pro-asset-manager' => 'advanced_rules',
            'ultracache-pro-asset-cleanup' => 'advanced_rules',
            'ultracache-pro-database' => 'database',
            'ultracache-pro-cdn' => 'cdn',
            'ultracache-pro-heartbeat' => 'heartbeat',
            'ultracache-pro-addons' => 'tools',
            'ultracache-pro-tools' => 'tools',
            'ultracache-pro-toolbox' => 'tools',
            'ultracache-pro-integrations' => 'tools',
        );
    }
    public static function normalize_tab($tab) {
        $tab = sanitize_key((string) $tab);
        $map = array('cache' => 'preload', 'expert' => 'advanced_rules', 'advanced' => 'advanced_rules', 'advanced-rules' => 'advanced_rules', 'assets' => 'advanced_rules');
        if (isset($map[$tab])) { return $map[$tab]; }
        $allowed = array('overview', 'optimization', 'media', 'preload', 'advanced_rules', 'database', 'cdn', 'heartbeat', 'tools');
        return in_array($tab, $allowed, true) ? $tab : 'overview';
    }
    public static function url($tab = 'overview', $args = array()) { return self::tab_url(self::normalize_tab($tab), $args); }
    public static function current_tab() {
        $requested_tab = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        if ($requested_tab) { return self::normalize_tab($requested_tab); }
        $page = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'ultracache-pro';
        $map = self::legacy_page_map();
        return isset($map[$page]) ? self::normalize_tab($map[$page]) : 'overview';
    }
    public static function admin_page_url($page_slug, $args = array()) { return add_query_arg(wp_parse_args($args, array('page' => $page_slug)), admin_url('admin.php')); }
    public static function tab_url($tab, $args = array()) { return self::admin_page_url('ultracache-pro', wp_parse_args($args, array('tab' => self::normalize_tab($tab)))); }
}
