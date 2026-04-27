<?php
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Admin_Router {
    public static function legacy_page_map() {
        return array(
            'ultracache-pro'                   => 'overview',
            'ultracache-pro-cache'             => 'cache',
            'ultracache-pro-file-optimization' => 'optimization',
            'ultracache-pro-media'             => 'optimization',
            'ultracache-pro-preload'           => 'preload',
            'ultracache-pro-assets'            => 'optimization',
            'ultracache-pro-advanced-rules'    => 'expert',
            'ultracache-pro-assets-manager'    => 'optimization',
            'ultracache-pro-asset-manager'     => 'optimization',
            'ultracache-pro-asset-cleanup'     => 'optimization',
            'ultracache-pro-database'          => 'tools',
            'ultracache-pro-cdn'               => 'cdn',
            'ultracache-pro-heartbeat'         => 'expert',
            'ultracache-pro-addons'            => 'expert',
            'ultracache-pro-tools'             => 'tools',
            'ultracache-pro-toolbox'           => 'tools',
            'ultracache-pro-integrations'      => 'expert',
            'ultracache-pro-logs'              => 'tools',
        );
    }

    public static function normalize_tab($tab) {
        $tab = sanitize_key((string) $tab);
        $map = array(
            'addons'         => 'expert',
            'assets'         => 'optimization',
            'database'       => 'tools',
            'heartbeat'      => 'expert',
            'advanced_rules' => 'expert',
            'simple'         => 'overview',
            'media'          => 'optimization',
            'woocommerce'    => 'cache',
            'compatibility'  => 'expert',
        );
        if (isset($map[$tab])) {
            return $map[$tab];
        }
        $allowed = array('overview', 'cache', 'optimization', 'preload', 'cdn', 'tools', 'expert');
        return in_array($tab, $allowed, true) ? $tab : 'overview';
    }

    public static function current_tab() {
        $requested_tab = UCP_Helpers::query_arg_key('tab');
        if ($requested_tab) {
            return self::normalize_tab($requested_tab);
        }
        $page = UCP_Helpers::query_arg_key('page', 'ultracache-pro');
        $map = self::legacy_page_map();
        if (isset($map[$page])) {
            return self::normalize_tab($map[$page]);
        }
        return 'overview';
    }

    public static function admin_page_url($page_slug, $args = array()) {
        $args = wp_parse_args($args, array('page' => $page_slug));
        return add_query_arg($args, admin_url('admin.php'));
    }

    public static function tab_url($tab, $args = array()) {
        return self::admin_page_url('ultracache-pro', wp_parse_args($args, array('tab' => self::normalize_tab($tab))));
    }

    public static function url($tab = 'overview', $args = array()) {
        return self::tab_url($tab, $args);
    }
}
