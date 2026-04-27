<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Installer {

    public static function maybe_upgrade() {
        $installed = (string) get_option('ucp_db_version', '');
        if ($installed !== UCP_VERSION) {
            self::create_tables();
            UCP_Helpers::ensure_cache_dirs();
            UCP_Helpers::write_dropin_config();
            UCP_Helpers::write_advanced_cache_stub();
            UCP_Helpers::maybe_write_browser_cache_rules();
            UCP_Options::maybe_apply_performance_migration();
            self::schedule_events();
            update_option('ucp_db_version', UCP_VERSION, false);
        }
    }

    public static function activate() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if (is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network(UCP_BASENAME)) {
            $sites = get_sites(array('fields' => 'ids'));
            foreach ($sites as $site_id) {
                switch_to_blog((int) $site_id);
                self::activate_single_site();
                restore_current_blog();
            }
            return;
        }

        self::activate_single_site();
    }

    protected static function activate_single_site() {
        $created_defaults = UCP_Options::maybe_init_defaults();
        UCP_Options::maybe_apply_performance_migration();
        if ($created_defaults) {
            UCP_Options::maybe_apply_install_profile(true);
        }
        $settings = UCP_Options::get_all();
        // Safe install: do not modify wp-config.php or replace advanced-cache.php
        // during activation. The admin can enable this explicitly from the UI.
        $settings["allow_wp_config_write"] = 0;
        $settings["allow_dropin_writes"] = 0;
        UCP_Options::update($settings);

        UCP_Helpers::ensure_cache_dirs();
        UCP_Helpers::maybe_write_browser_cache_rules();
        self::create_tables();
        self::schedule_events();
        UCP_Maintenance::schedule();
        update_option('ucp_db_version', UCP_VERSION, false);
    }

    public static function deactivate() {
        if (is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network(UCP_BASENAME)) {
            $sites = get_sites(array('fields' => 'ids'));
            foreach ($sites as $site_id) {
                switch_to_blog((int) $site_id);
                self::deactivate_single_site();
                restore_current_blog();
            }
            return;
        }

        self::deactivate_single_site();
    }

    protected static function deactivate_single_site() {
        wp_clear_scheduled_hook('ucp_preload_event');
        wp_clear_scheduled_hook(UCP_Jobs::CRON_HOOK);
        wp_clear_scheduled_hook(UCP_Health::CRON_HOOK);
        UCP_Helpers::remove_browser_cache_rules();
        UCP_Helpers::remove_own_advanced_cache_stub(true, true);
        UCP_Maintenance::unschedule();
    }

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

    protected static function schedule_events() {
        UCP_Jobs::ensure_cron_schedule_registered();
        UCP_Preload::sync_schedule();
        UCP_Jobs::sync_schedule();
        UCP_Health::sync_schedule();
        UCP_Maintenance::schedule();
    }
}
