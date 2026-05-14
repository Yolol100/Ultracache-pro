<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Overview {
    public static function status_class($ok, $warn = false) {
        if ($ok) {
            return 'is-good';
        }
        if ($warn) {
            return 'is-warn';
        }
        return 'is-neutral';
    }

    public static function render_overview_tab($admin, $settings, $presets, $integrations, $health, $jobs_summary) {
        $advanced_cache_file = WP_CONTENT_DIR . '/advanced-cache.php';
        $dropin_exists = file_exists($advanced_cache_file);
        $dropin_owner = get_option('ucp_advanced_cache_owner', '');
        if ($dropin_exists && '' === $dropin_owner && class_exists('UCP_Helpers') && is_readable($advanced_cache_file)) {
            $dropin_owner = UCP_Helpers::detect_advanced_cache_owner(UCP_Helpers::read_file($advanced_cache_file));
        }
        $is_ultracache_dropin = $dropin_exists && false !== stripos((string) $dropin_owner, 'UltraCache');
        $wp_cache_on = defined('WP_CACHE') && WP_CACHE;
        $conflicts = class_exists('UCP_Compat') ? UCP_Compat::detected_conflicts() : array();
        $conflict_count = is_array($conflicts) ? count($conflicts) : 0;
        $quick_enable_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_quick_enable_cache'), 'ucp_quick_enable_cache');
        $purge_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all');
        $preload_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_and_preload'), 'ucp_purge_and_preload');
        UCP_Admin_View::template('tabs/overview.php', get_defined_vars());
    }
}
