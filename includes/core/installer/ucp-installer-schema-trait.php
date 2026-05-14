<?php
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

        $jobs = 'CREATE TABLE ' . UCP_TABLE_JOBS . " (
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
            KEY queue_status (queue, status)
        ) $charset";

        $logs = 'CREATE TABLE ' . UCP_TABLE_LOGS . " (
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
            KEY level_created (level, created_at)
        ) $charset";

        $diagnostics = 'CREATE TABLE ' . UCP_TABLE_DIAGNOSTICS . " (
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
            KEY path_generated (path, generated_at)
        ) $charset";

        dbDelta($jobs);
        dbDelta($logs);
        dbDelta($diagnostics);
    }
}
