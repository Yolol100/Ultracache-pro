<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall intentionally removes plugin-owned custom tables and options.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function ucp_safe_table_name($table) {
    return is_string($table) && '' !== $table && (bool) preg_match('/^[A-Za-z0-9_]+$/', $table);
}

function ucp_quote_table_name($table) {
    return '`' . str_replace('`', '``', (string) $table) . '`';
}


function ucp_read_file($path) {
    if (!is_string($path) || '' === $path || !is_file($path) || !is_readable($path)) {
        return '';
    }
    $contents = file_get_contents($path);
    return is_string($contents) ? $contents : '';
}

function ucp_safe_remove_dir($dir) {
    $base = trailingslashit(WP_CONTENT_DIR) . 'cache/ultracache-pro';
    $real_dir = realpath($dir);
    $real_base = realpath($base);
    if (!$real_dir || !$real_base || ($real_dir !== $real_base && 0 !== strpos($real_dir, trailingslashit($real_base)))) {
        return;
    }

    $items = glob(trailingslashit($real_dir) . '*');
    if ($items) {
        foreach ($items as $item) {
            if (is_dir($item)) {
                ucp_safe_remove_dir($item);
            } elseif (is_file($item)) {
                wp_delete_file($item);
            }
        }
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- final empty managed cache directory cleanup during uninstall.
    @rmdir($real_dir);
}

function ucp_uninstall_site() {
    $scheduled_hooks = array(
        'ucp_preload_event',
        'ucp_jobs_event',
        'ucp_health_check_event',
        'ucp_db_cleanup_event',
        'ucp_lifecycle_preload_seed_event',
        'ucp_maintenance_event',
        'ucp_refresh_google_font_cache',
        'ucp_logs_retention_cleanup',
    );
    foreach ($scheduled_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }

    $settings = get_option('ucp_settings', array());
    if (empty($settings['clean_uninstall'])) {
        return;
    }
    delete_option('ucp_settings');
    delete_option('ucp_job_queue');
    delete_option('ucp_db_version');
    delete_option('ucp_health_snapshot');
    delete_option('ucp_runtime_tests_snapshot');
    delete_option('ucp_detected_conflicts');
    delete_option('ucp_advanced_cache_conflict');
    delete_option('ucp_advanced_cache_backup_path');
    delete_option('ucp_css_artifact_status');
    delete_option('ucp_cwv_metrics');
    delete_option('ucp_pagespeed_browser_scan_latest');
    delete_option('ucp_lcp_last_cleanup');
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'ucp_jobs',
        $wpdb->prefix . 'ucp_logs',
        $wpdb->prefix . 'ucp_diagnostics',
        $wpdb->prefix . 'ucp_lcp',
    );
    foreach ($tables as $table) {
        if (!ucp_safe_table_name($table)) {
            continue;
        }
        $quoted_table = ucp_quote_table_name($table);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- uninstall removes plugin-owned tables with validated and quoted identifiers.
        $wpdb->query("DROP TABLE IF EXISTS {$quoted_table}");
    }
    $dirs = array(
        WP_CONTENT_DIR . '/cache/ultracache-pro/pages/*.html',
        WP_CONTENT_DIR . '/cache/ultracache-pro/assets/*.*',
        WP_CONTENT_DIR . '/cache/ultracache-pro/min/*.*',
        WP_CONTENT_DIR . '/cache/ultracache-pro/self-host/*.*',
        WP_CONTENT_DIR . '/cache/ultracache-pro/used-css/*.css',
        WP_CONTENT_DIR . '/cache/ultracache-pro/critical-css/*.css',
        WP_CONTENT_DIR . '/cache/ultracache-pro/diagnostics/*.json',
        WP_CONTENT_DIR . '/cache/ultracache-pro/logs/*.*',
    );
    foreach ($dirs as $pattern) {
        $files = glob($pattern);
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== 'index.html') {
                    wp_delete_file($file);
                }
            }
        }
    }
    $dropin_config = WP_CONTENT_DIR . '/cache/ultracache-pro/dropin-config.php';
    if (file_exists($dropin_config) && is_file($dropin_config)) {
        wp_delete_file($dropin_config);
    }
    $cache_root = WP_CONTENT_DIR . '/cache/ultracache-pro';
    if (is_dir($cache_root)) {
        ucp_safe_remove_dir($cache_root);
    }
    $advanced = WP_CONTENT_DIR . '/advanced-cache.php';
    if (is_file($advanced) && is_readable($advanced)) {
        $content = ucp_read_file($advanced);
        if (is_string($content) && false !== strpos($content, 'UltraCache Pro Drop-in')) {
            wp_delete_file($advanced);
        }
    }
}

if (is_multisite()) {
    $sites = get_sites(array('fields' => 'ids'));
    foreach ($sites as $site_id) {
        switch_to_blog((int) $site_id);
        ucp_uninstall_site();
        restore_current_blog();
    }
} else {
    ucp_uninstall_site();
}
