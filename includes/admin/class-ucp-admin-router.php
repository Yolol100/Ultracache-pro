<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Admin_Router {
    const PAGE_SLUG = 'ultracache-pro';

    public static function page_slug() {
        return self::PAGE_SLUG;
    }

    public static function hook_suffixes() {
        return array(
            'toplevel_page_' . self::PAGE_SLUG,
            'ultracache_page_' . self::PAGE_SLUG,
        );
    }

    public static function is_plugin_hook_suffix($hook) {
        return in_array((string) $hook, self::hook_suffixes(), true);
    }

    public static function tab_asset_map() {
        return array(
            'overview'       => 'overview',
            'cache'          => 'cache',
            'optimization'   => 'optimization',
            'media'          => 'media',
            'preload'        => 'preload',
            'advanced'       => 'advanced-rules',
            'database'       => 'database',
            'developer'      => 'developer',
            'tools'          => 'tools',
        );
    }

    public static function allowed_tabs() {
        return array_keys(self::tab_asset_map());
    }

    public static function settings_tabs() {
        return array_values(array_diff(self::allowed_tabs(), array('overview')));
    }

    public static function is_settings_tab($tab) {
        return in_array(self::normalize_tab($tab), self::settings_tabs(), true);
    }

    public static function compatibility_page_map() {
        return array(
            self::PAGE_SLUG                       => 'overview',
            'ultracache-pro-cache'               => 'cache',
            'ultracache-pro-file-optimization'   => 'optimization',
            'ultracache-pro-media'               => 'media',
            'ultracache-pro-preload'             => 'preload',
            'ultracache-pro-assets'              => 'advanced',
            'ultracache-pro-advanced-rules'      => 'advanced',
            'ultracache-pro-assets-manager'      => 'advanced',
            'ultracache-pro-asset-manager'       => 'advanced',
            'ultracache-pro-asset-cleanup'       => 'advanced',
            'ultracache-pro-database'            => 'database',
            'ultracache-pro-cdn'                 => 'advanced',
            'ultracache-pro-heartbeat'           => 'developer',
            'ultracache-pro-developer'           => 'developer',
            'ultracache-pro-addons'              => 'tools',
            'ultracache-pro-tools'               => 'tools',
            'ultracache-pro-toolbox'             => 'tools',
            'ultracache-pro-integrations'        => 'tools',
        );
    }

    public static function normalize_tab($tab) {
        $tab = sanitize_key((string) $tab);
        $map = array(
            'expert'         => 'advanced',
            'advanced'       => 'advanced',
            'advanced-rules' => 'advanced',
            'advanced_rules' => 'advanced',
            'assets'         => 'advanced',
            'cdn'            => 'advanced',
            'heartbeat'      => 'developer',
            'diagnostics'    => 'tools',
        );

        if (isset($map[$tab])) {
            return $map[$tab];
        }

        return in_array($tab, self::allowed_tabs(), true) ? $tab : 'overview';
    }

    public static function url($tab = 'overview', $args = array()) {
        return self::tab_url(self::normalize_tab($tab), $args);
    }

    public static function current_tab() {
        $requested_tab = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        if ($requested_tab) {
            return self::normalize_tab($requested_tab);
        }

        $page = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : self::PAGE_SLUG;
        $map = self::compatibility_page_map();
        return isset($map[$page]) ? self::normalize_tab($map[$page]) : 'overview';
    }

    public static function admin_page_url($page_slug, $args = array()) {
        return add_query_arg(wp_parse_args($args, array('page' => $page_slug)), admin_url('admin.php'));
    }

    public static function tab_url($tab, $args = array()) {
        return self::admin_page_url(self::PAGE_SLUG, wp_parse_args($args, array('tab' => self::normalize_tab($tab))));
    }
}
