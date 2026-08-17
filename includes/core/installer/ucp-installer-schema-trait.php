<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Installer_Schema_Trait {
    protected static function create_tables() {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $jobs = 'CREATE TABLE ' . ucp_table_name('jobs') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_uuid varchar(64) NOT NULL,
            job_signature varchar(64) NULL DEFAULT NULL,
            type varchar(100) NOT NULL,
            queue varchar(50) NOT NULL DEFAULT 'default',
            status varchar(30) NOT NULL DEFAULT 'pending',
            priority smallint(5) unsigned NOT NULL DEFAULT 10,
            attempts smallint(5) unsigned NOT NULL DEFAULT 0,
            max_attempts smallint(5) unsigned NOT NULL DEFAULT 3,
            claim_token varchar(100) NOT NULL DEFAULT '',
            available_at datetime NOT NULL,
            started_at datetime NULL,
            finished_at datetime NULL,
            locked_until datetime NULL,
            last_error text NULL,
            payload longtext NULL,
            result longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY job_uuid (job_uuid),
            UNIQUE KEY job_signature (job_signature),
            KEY status_available (status, available_at),
            KEY queue_status (queue, status),
            KEY status_updated (status, updated_at)
        ) $charset";

        $logs = 'CREATE TABLE ' . ucp_table_name('logs') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            component varchar(80) NOT NULL,
            event varchar(120) NOT NULL,
            message text NOT NULL,
            context longtext NULL,
            request_url text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY component_created (component, created_at),
            KEY level_created (level, created_at),
            KEY created_at (created_at)
        ) $charset";

        $lcp = 'CREATE TABLE ' . ucp_table_name('lcp') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url_hash varchar(64) NOT NULL,
            url text NOT NULL,
            device varchar(20) NOT NULL DEFAULT 'all',
            lcp_element_json longtext NULL,
            lcp_url text NULL,
            lcp_imagesrcset longtext NULL,
            lcp_type varchar(30) NOT NULL DEFAULT 'image',
            lcp_selector text NULL,
            confidence smallint(5) unsigned NOT NULL DEFAULT 0,
            profile_status varchar(30) NOT NULL DEFAULT 'active',
            value_ms double NOT NULL DEFAULT 0,
            sample_count bigint(20) unsigned NOT NULL DEFAULT 0,
            last_measured datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url_device (url_hash, device),
            KEY last_measured (last_measured),
            KEY device_measured (device, last_measured),
            KEY confidence_status (profile_status, confidence)
        ) $charset";

        $cache_events = 'CREATE TABLE ' . ucp_table_name('cache_events') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(20) NOT NULL,
            status varchar(30) NOT NULL DEFAULT '',
            reason varchar(80) NOT NULL DEFAULT '',
            url_hash varchar(64) NOT NULL,
            path varchar(255) NOT NULL DEFAULT '/',
            source varchar(80) NOT NULL DEFAULT '',
            scope varchar(80) NOT NULL DEFAULT '',
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            sample_weight smallint(5) unsigned NOT NULL DEFAULT 1,
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY type_created (event_type, created_at),
            KEY status_created (status, created_at),
            KEY reason_created (reason, created_at),
            KEY created_at (created_at)
        ) $charset";

        $diagnostics = 'CREATE TABLE ' . ucp_table_name('diagnostics') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_hash varchar(64) NOT NULL,
            url text NOT NULL,
            path varchar(255) NOT NULL,
            request_type varchar(40) NOT NULL DEFAULT 'generic',
            cache_decision varchar(80) NOT NULL DEFAULT 'unknown',
            rule_matches longtext NULL,
            module_flags longtext NULL,
            asset_summary longtext NULL,
            notes longtext NULL,
            generated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY request_hash (request_hash),
            KEY path_generated (path, generated_at),
            KEY generated_at (generated_at)
        ) $charset";

        dbDelta($jobs);
        dbDelta($logs);
        dbDelta($diagnostics);
        dbDelta($cache_events);
        dbDelta($lcp);

        foreach (array(
            ucp_table_name('jobs'),
            ucp_table_name('logs'),
            ucp_table_name('diagnostics'),
            ucp_table_name('cache_events'),
            ucp_table_name('lcp'),
        ) as $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact post-dbDelta verification of plugin-owned tables.
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            if ((string) $found !== (string) $table) {
                return false;
            }
        }

        return true;
    }
}
