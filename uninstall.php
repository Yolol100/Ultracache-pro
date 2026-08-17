<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall intentionally removes plugin-owned custom tables and options.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function ucp_uninstall_safe_preg_replace($pattern, $replacement, $subject, $limit = -1) {
    if (!is_string($subject)) {
        return '';
    }
    $result = @preg_replace($pattern, $replacement, $subject, $limit);
    return null === $result || PREG_NO_ERROR !== preg_last_error() ? $subject : (string) $result;
}

function ucp_safe_table_name($table) {
    return is_string($table) && '' !== $table && (bool) preg_match('/^[A-Za-z0-9_]+$/', $table);
}

function ucp_quote_table_name($table) {
    return '`' . str_replace('`', '``', (string) $table) . '`';
}

function ucp_read_file($path, $max_bytes = 5 * 1024 * 1024) {
    if (!is_string($path) || '' === $path || is_link($path) || !is_file($path) || !is_readable($path)) {
        return '';
    }
    $max_bytes = max(1024, min(20 * 1024 * 1024, absint($max_bytes)));
    $size = filesize($path);
    if (false !== $size && $size > $max_bytes) {
        return '';
    }
    $contents = file_get_contents($path, false, null, 0, $max_bytes + 1);
    return is_string($contents) && strlen($contents) <= $max_bytes ? $contents : '';
}

function ucp_write_file_atomic($path, $content) {
    if (!is_string($path) || '' === $path || !is_string($content) || is_link($path) || !is_file($path)) {
        return false;
    }
    $dir = dirname($path);
    if (!is_dir($dir) || !wp_is_writable($dir) || !wp_is_writable($path)) {
        return false;
    }
    $tmp = $path . '.ucp-tmp-' . wp_generate_password(12, false, false);
    $bytes = file_put_contents($tmp, $content, LOCK_EX);
    if (false === $bytes || $bytes !== strlen($content)) {
        if (is_file($tmp)) {
            wp_delete_file($tmp);
        }
        return false;
    }
    $mode = fileperms($path);
    if (false !== $mode) {
        @chmod($tmp, $mode & 0777);
    }
    if (@rename($tmp, $path)) {
        return true;
    }
    wp_delete_file($tmp);
    return false;
}

function ucp_remove_owned_wp_cache_constant() {
    $root = trailingslashit(ABSPATH) . 'wp-config.php';
    $parent = trailingslashit(dirname(untrailingslashit(ABSPATH))) . 'wp-config.php';
    $parent_settings = trailingslashit(dirname(untrailingslashit(ABSPATH))) . 'wp-settings.php';
    $path = file_exists($root) ? $root : (!file_exists($parent_settings) && file_exists($parent) ? $parent : '');
    if ('' === $path || !is_readable($path) || !wp_is_writable($path)) {
        return;
    }
    $content = ucp_read_file($path);
    $updated = ucp_uninstall_safe_preg_replace('/^\s*define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:true|1)\s*\)\s*;\s*\/\/\s*Added by UltraCache Pro\s*\R?/mi', '', $content, 1);
    if (is_string($updated) && $updated !== $content) {
        ucp_write_file_atomic($path, $updated);
    }
}

function ucp_remove_owned_dropins() {
    $dropins = array(
        WP_CONTENT_DIR . '/advanced-cache.php' => array(
            'UltraCache Pro Drop-in',
        ),
        WP_CONTENT_DIR . '/object-cache.php' => array(
            'UltraCache Pro APCu Object Cache',
            'UltraCache Pro Redis Object Cache',
        ),
    );

    foreach ($dropins as $path => $signatures) {
        $content = ucp_read_file($path);
        if ('' === $content) {
            continue;
        }
        foreach ($signatures as $signature) {
            if (false !== strpos($content, $signature)) {
                wp_delete_file($path);
                break;
            }
        }
    }
}

function ucp_delete_options($options) {
    foreach ((array) $options as $option) {
        if (is_string($option) && preg_match('/^ucp_[a-z0-9_]+$/', $option)) {
            delete_option($option);
        }
    }
}

function ucp_delete_transients($transients) {
    foreach ((array) $transients as $transient) {
        if (is_string($transient) && preg_match('/^ucp_[a-z0-9_]+$/', $transient)) {
            delete_transient($transient);
        }
    }
}

function ucp_delete_option_name_prefixes($prefixes) {
    global $wpdb;
    if (empty($wpdb->options)) {
        return;
    }

    $allowed_prefixes = array(
        '_transient_ucp_',
        '_transient_timeout_ucp_',
        '_site_transient_ucp_',
        '_site_transient_timeout_ucp_',
        '_ucp_lock_ucp_',
        '_ucp_cwv_lock_',
        'ucp_action_lock_',
    );
    foreach ((array) $prefixes as $prefix) {
        if (!is_string($prefix) || !in_array($prefix, $allowed_prefixes, true)) {
            continue;
        }
        $like = $wpdb->esc_like($prefix) . '%';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- clean uninstall removes plugin-owned transient/lock option prefixes only.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
    }
}

function ucp_delete_site_transient_meta_prefixes($prefixes) {
    global $wpdb;
    if (!is_multisite() || empty($wpdb->sitemeta) || !ucp_safe_table_name($wpdb->sitemeta)) {
        return;
    }

    $allowed_prefixes = array(
        '_site_transient_ucp_',
        '_site_transient_timeout_ucp_',
    );
    $table = ucp_quote_table_name($wpdb->sitemeta);
    foreach ((array) $prefixes as $prefix) {
        if (!is_string($prefix) || !in_array($prefix, $allowed_prefixes, true)) {
            continue;
        }
        $like = $wpdb->esc_like($prefix) . '%';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- clean uninstall removes plugin-owned network transient meta prefixes only.
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE meta_key LIKE %s", $like));
    }
}

function ucp_safe_remove_dir($dir) {
    $base = trailingslashit(WP_CONTENT_DIR) . 'cache/ultracache-pro';
    $normalized_dir  = untrailingslashit(wp_normalize_path((string) $dir));
    $normalized_base = untrailingslashit(wp_normalize_path($base));
    if ($normalized_dir !== $normalized_base && 0 !== strpos($normalized_dir, $normalized_base . '/')) {
        return;
    }

    // Remove only the plugin-owned link itself; never traverse its target.
    if (is_link($dir)) {
        wp_delete_file($dir);
        return;
    }
    if (is_link($base)) {
        if ($normalized_dir === $normalized_base) {
            wp_delete_file($base);
        }
        return;
    }

    $real_dir = realpath($dir);
    $real_base = realpath($base);
    if (!$real_dir || !$real_base || ($real_dir !== $real_base && 0 !== strpos($real_dir, trailingslashit($real_base)))) {
        return;
    }

    $items = scandir($real_dir);
    if (is_array($items)) {
        foreach ($items as $name) {
            if ('.' === $name || '..' === $name) {
                continue;
            }
            $item = trailingslashit($real_dir) . $name;
            if (is_link($item)) {
                wp_delete_file($item);
                continue;
            }
            $real_item = realpath($item);
            if (!$real_item || ($real_item !== $real_base && 0 !== strpos($real_item, trailingslashit($real_base)))) {
                continue;
            }
            if (is_dir($real_item)) {
                ucp_safe_remove_dir($real_item);
            } elseif (is_file($real_item)) {
                wp_delete_file($real_item);
            }
        }
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- final empty managed cache directory cleanup during uninstall.
    @rmdir($real_dir);
}


function ucp_safe_remove_local_font_cache() {
    $uploads = function_exists('wp_upload_dir') ? wp_upload_dir(null, false) : array();
    if (empty($uploads['basedir'])) {
        return;
    }

    $uploads_base = realpath($uploads['basedir']);
    $plugin_dir = trailingslashit($uploads['basedir']) . 'ultracache-pro';
    $dir = trailingslashit($plugin_dir) . 'fonts';
    if (!$uploads_base) {
        return;
    }

    // A replaced plugin uploads root is removed as a link only; its target is external.
    if (is_link($plugin_dir)) {
        wp_delete_file($plugin_dir);
        return;
    }

    if (is_link($dir)) {
        wp_delete_file($dir);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- remove the now-empty plugin-owned uploads directory.
        @rmdir($plugin_dir);
        return;
    }

    $uploads_base = trailingslashit(wp_normalize_path($uploads_base));
    $real_dir = realpath($dir);
    if (!$real_dir) {
        return;
    }

    $base = trailingslashit(wp_normalize_path($real_dir));
    $expected_base = trailingslashit($uploads_base . 'ultracache-pro/fonts');
    if ($base !== $expected_base) {
        return;
    }

    $items = glob(trailingslashit($real_dir) . '*');
    if ($items) {
        foreach ($items as $item) {
            if (is_link($item)) {
                wp_delete_file($item);
                continue;
            }
            if (!is_file($item)) {
                continue;
            }
            $real_item = realpath($item);
            $normalized = $real_item ? wp_normalize_path($real_item) : '';
            if ('' !== $normalized && 0 === strpos($normalized, $base)) {
                wp_delete_file($item);
            }
        }
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- final empty plugin-owned upload cache directory cleanup during uninstall.
    @rmdir($real_dir);
    $parent = trailingslashit(wp_normalize_path(dirname($real_dir)));
    if ($parent === trailingslashit($uploads_base . 'ultracache-pro')) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- remove empty plugin-owned parent directory when possible.
        @rmdir(dirname($real_dir));
    }
}

function ucp_uninstall_site() {
    ucp_remove_owned_wp_cache_constant();
    $scheduled_hooks = array(
        'ucp_preload_event',
        'ucp_jobs_event',
        'ucp_health_check_event',
        'ucp_db_cleanup_event',
        'ucp_lifecycle_preload_seed_event',
        'ucp_maintenance_event',
        'ucp_refresh_google_font_cache',
        'ucp_logs_retention_cleanup',
        'ucp_compat_overlay_fetch',
        'ucp_used_css_auto_refresh',
        'ucp_post_update_website_check',
        'ucp_network_activation_batch',
    );
    foreach ($scheduled_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }

    // Runtime drop-ins must not survive plugin removal, even when settings and
    // database records are intentionally retained for a later reinstall.
    ucp_remove_owned_dropins();

    $settings = get_option('ucp_settings', array());
    if (empty($settings['clean_uninstall'])) {
        return;
    }
    ucp_delete_options(array(
        'ucp_settings',
        'ucp_settings_snapshots',
        'ucp_custom_presets',
        'ucp_job_queue',
        'ucp_db_version',
        'ucp_health_snapshot',
        'ucp_runtime_tests_snapshot',
        'ucp_runtime_config_status',
        'ucp_plugin_upgrade_lock',
        'ucp_option_migrations_lock',
        'ucp_option_migrations_version',
        'ucp_runtime_cache_test_report',
        'ucp_debug_mode_until',
        'ucp_testing_mode_started_at',
        'ucp_testing_mode_expires_at',
        'ucp_testing_mode_expired_at',
        'ucp_detected_conflicts',
        'ucp_detected_integrations',
        'ucp_advanced_cache_conflict',
        'ucp_advanced_cache_backup_path',
        'ucp_advanced_cache_auto_status',
        'ucp_advanced_cache_owner',
        'ucp_advanced_cache_replaced_backup',
        'ucp_css_artifact_status',
        'ucp_css_profiles',
        'ucp_css_page_identity_version',
        'ucp_pagespeed_scan_privacy_version',
        'ucp_used_css_last_refresh',
        'ucp_cwv_metrics',
        'ucp_cwv_timeseries',
        'ucp_cwv_legacy_token_until',
        'ucp_pagespeed_browser_scan_latest',
        'ucp_pagespeed_browser_scan_map',
        'ucp_lcp_last_cleanup',
        'ucp_maintenance_cache_cursors',
        'ucp_cache_tags_version',
        'ucp_cache_dirs_ready_version',
        'ucp_private_user_cache_key_version',
        'ucp_cdn_last_result',
        'ucp_cloudflare_last_result',
        'ucp_compat_overlay',
        'ucp_asset_manager_last_snapshot',
        'ucp_delay_js_lifecycle',
        'ucp_duplicate_plugin_cleanup_candidates',
        'ucp_duplicate_plugin_cleanup_result',
        'ucp_exact_transaction_rules_version',
        'ucp_install_profile_version',
        'ucp_jobs_admin_runner_last',
        'ucp_jobs_empty_run_streak',
        'ucp_jobs_last_run_summary',
        'ucp_jobs_runner_lock',
        'ucp_db_cleanup_lock',
        'ucp_last_db_cleanup_at',
        'ucp_last_db_cleanup_results',
        'ucp_last_purge_at',
        'ucp_last_upgrade_cleanup_version',
        'ucp_local_font_preload_candidates',
        'ucp_local_google_fonts_opt_in_version',
        'ucp_onboarding_completed',
        'ucp_onboarding_first_install_pending',
        'ucp_pending_cache_toast',
        'ucp_performance_profile_version',
        'ucp_performance_profile_version_v2',
        'ucp_performance_profile_version_v3',
        'ucp_performance_profile_version_v4',
        'ucp_performance_profile_version_v5',
        'ucp_performance_profile_version_v6',
        'ucp_performance_profile_version_v7',
        'ucp_performance_profile_version_v8',
        'ucp_performance_profile_version_v9',
        'ucp_performance_profile_version_v10',
        'ucp_performance_profile_version_v11',
        'ucp_performance_profile_version_v12',
        'ucp_preload_last_plan',
        'ucp_preload_recent_purge_urls',
        'ucp_preload_safety_version',
        'ucp_preload_url_statuses',
        'ucp_queue_repair_version',
        'ucp_refactor_1124_version',
        'ucp_render_bridge_status',
        'ucp_rest_cache_version',
        'ucp_rocket_style_automation_version',
        'ucp_runtime_writes_logs_version',
        'ucp_safe_autopilot_done',
        'ucp_website_check_report',
        'ucp_post_update_website_check_context',
        'ucp_support_mode_previous_settings',
        'ucp_vpi_map',
        'ucp_fragment_metrics',
        'ucp_fragment_cache_version',
    ));
    ucp_delete_transients(array(
        'ucp_cache_dirs_checked_recently',
        'ucp_compat_list_status',
        'ucp_conflict_snapshot_throttle',
        'ucp_empty_cart_fragments',
        'ucp_testing_mode_expired_notice',
    ));
    ucp_delete_option_name_prefixes(array(
        '_transient_ucp_',
        '_transient_timeout_ucp_',
        '_site_transient_ucp_',
        '_site_transient_timeout_ucp_',
        '_ucp_lock_ucp_',
        '_ucp_cwv_lock_',
        'ucp_action_lock_',
    ));
    ucp_delete_site_transient_meta_prefixes(array(
        '_site_transient_ucp_',
        '_site_transient_timeout_ucp_',
    ));
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'ucp_jobs',
        $wpdb->prefix . 'ucp_logs',
        $wpdb->prefix . 'ucp_diagnostics',
        $wpdb->prefix . 'ucp_cache_events',
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
    // Remove the managed cache tree only through the symlink-aware recursive
    // cleanup below. Direct glob deletion can traverse a replaced child
    // directory symlink and remove files outside the plugin-owned cache root.
    $cache_root = WP_CONTENT_DIR . '/cache/ultracache-pro';
    if (is_dir($cache_root) || is_link($cache_root)) {
        ucp_safe_remove_dir($cache_root);
    }

    ucp_safe_remove_local_font_cache();
}

if (is_multisite()) {
    $number = 100;
    $offset = 0;
    do {
        $site_ids = get_sites(array(
            'fields' => 'ids',
            'number' => $number,
            'offset' => $offset,
            'orderby' => 'id',
            'order' => 'ASC',
        ));
        $site_ids = is_array($site_ids) ? $site_ids : array();
        foreach ($site_ids as $site_id) {
            switch_to_blog((int) $site_id);
            try {
                ucp_uninstall_site();
            } finally {
                restore_current_blog();
            }
        }
        $processed = count($site_ids);
        $offset += $processed;
    } while ($processed === $number);
} else {
    ucp_uninstall_site();
}

if (is_multisite()) {
    $ucp_network_id = function_exists('get_current_network_id') ? (int) get_current_network_id() : 0;
    wp_clear_scheduled_hook('ucp_network_activation_batch', array($ucp_network_id));
    delete_network_option($ucp_network_id, 'ucp_network_activation_state');
    delete_network_option($ucp_network_id, 'ucp_network_activation_lock');
}
