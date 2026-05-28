<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Installer_Lifecycle_Trait {
    public static function maybe_upgrade() {
        $installed = (string) get_option('ucp_db_version', '');
        if ($installed !== UCP_VERSION) {
            self::create_tables();
            UCP_Helpers::ensure_cache_dirs();
            self::cleanup_previous_version_artifacts();
            UCP_Helpers::write_dropin_config();
            UCP_Helpers::write_advanced_cache_stub();
            UCP_Helpers::maybe_write_browser_cache_rules();
            UCP_Options::maybe_apply_performance_migration();
            self::schedule_events();
            update_option('ucp_db_version', UCP_VERSION, false);
            if (class_exists('UCP_Cache')) {
                $cache = UCP_Helpers::new_without_constructor('UCP_Cache');
                $cache->purge_and_preload_after_lifecycle_change('ultracache_upgrade', array('item' => UCP_BASENAME));
            }
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
        UCP_Helpers::ensure_cache_dirs();
        self::cleanup_previous_version_artifacts();
        self::detect_duplicate_plugin_copies();
        UCP_Helpers::maybe_install_own_advanced_cache_automatically();
        UCP_Helpers::maybe_write_browser_cache_rules();
        self::create_tables();
        self::schedule_events();
        UCP_Maintenance::schedule();
        update_option('ucp_db_version', UCP_VERSION, false);
        if (class_exists('UCP_Cache')) {
            $cache = UCP_Helpers::new_without_constructor('UCP_Cache');
            $cache->purge_and_preload_after_lifecycle_change('ultracache_activation', array('item' => UCP_BASENAME));
        }
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
        if (class_exists('UCP_Cache')) {
            $cache = UCP_Helpers::new_without_constructor('UCP_Cache');
            $cache->purge_all();
            UCP_Diagnostics::record('cache', 'Purged full cache during UltraCache deactivation');
        }
        wp_clear_scheduled_hook('ucp_preload_event');
        wp_clear_scheduled_hook(UCP_Jobs::CRON_HOOK);
        if (class_exists('UCP_Health')) {
            wp_clear_scheduled_hook(UCP_Health::CRON_HOOK);
        }
        if (class_exists('UCP_DB_Cleanup')) {
            wp_clear_scheduled_hook(UCP_DB_Cleanup::CRON_HOOK);
        }
        UCP_Helpers::remove_browser_cache_rules();
        UCP_Helpers::remove_own_advanced_cache_stub(true);
        UCP_Maintenance::unschedule();
    }

    /**
     * Remove cache artifacts from previous UltraCache versions and refresh the
     * UltraCache-owned drop-in without touching third-party cache plugins.
     */
    protected static function cleanup_previous_version_artifacts() {
        UCP_Helpers::ensure_cache_dirs();

        $patterns = array(
            UCP_CACHE_DIR . 'pages/*.html',
            UCP_CACHE_DIR . 'used-css/*.css',
            UCP_CACHE_DIR . 'critical-css/*.css',
            UCP_CACHE_DIR . 'css/used-*.css',
            UCP_CACHE_DIR . 'css/critical-*.css',
            UCP_CACHE_DIR . 'css/status-*.json',
            UCP_CACHE_DIR . 'diagnostics/*.json',
            UCP_CACHE_DIR . 'min/*.*',
            UCP_CACHE_DIR . 'self-host/*.*',
        );

        foreach ($patterns as $pattern) {
            UCP_Helpers::safe_glob_delete($pattern);
        }

        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            UCP_Cache_Tags::clear_all();
        } else {
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'meta/*.json');
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'tag-index/*.json');
        }

        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        $source = UCP_PATH . 'advanced-cache.php';
        if (file_exists($target) && is_readable($target) && UCP_Helpers::is_own_advanced_cache(UCP_Helpers::read_file($target))) {
            $source_content = UCP_Helpers::read_file($source);
            if ('' !== trim($source_content)) {
                UCP_Helpers::write_file($target, $source_content);
            }
            UCP_Helpers::write_dropin_config(true);
        } else {
            UCP_Helpers::write_dropin_config(true);
        }
        update_option('ucp_last_upgrade_cleanup_version', UCP_VERSION, false);
    }

    /**
     * Detect old duplicate UltraCache Pro copies during activation.
     *
     * Detection is non-destructive. Actual deactivation or deletion should stay
     * behind an explicit admin action because plugin removal is a high-impact change.
     */
    protected static function detect_duplicate_plugin_copies() {
        if (!is_admin()) {
            return;
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $duplicates = array();

        foreach ($plugins as $basename => $data) {
            if ($basename === UCP_BASENAME) {
                continue;
            }

            if (self::is_duplicate_ultracache_plugin($basename, $data)) {
                $duplicates[] = $basename;
            }
        }

        if (empty($duplicates)) {
            delete_option('ucp_duplicate_plugin_cleanup_candidates');
            delete_option('ucp_duplicate_plugin_cleanup_result');
            return;
        }

        update_option('ucp_duplicate_plugin_cleanup_candidates', array_values($duplicates), false);

        $result = array(
            'version'   => UCP_VERSION,
            'attempted' => array(),
            'deleted'   => array(),
            'failed'    => array(),
            'candidates' => array_values($duplicates),
            'status'    => 'manual_review_required',
        );
        update_option('ucp_duplicate_plugin_cleanup_result', $result, false);
    }

    /**
     * Detect whether another installed plugin is a previous UltraCache Pro copy.
     */
    protected static function is_duplicate_ultracache_plugin($basename, $data) {
        $basename = (string) $basename;
        $folder = dirname($basename);
        $plugin_file = WP_PLUGIN_DIR . '/' . $basename;
        $name = isset($data['Name']) ? trim((string) $data['Name']) : '';
        $text_domain = isset($data['TextDomain']) ? trim((string) $data['TextDomain']) : '';

        if ('UltraCache Pro' === $name || 'ultracache-pro' === $text_domain) {
            return true;
        }

        $known_folders = array(
            'ultracache-pro',
            'ultracache-pro-previous',
            'ultracache-pro-fixed',
            'ultracache-pro-installer-pagespeed-boost-verified',
        );
        if (in_array($folder, $known_folders, true)) {
            return true;
        }

        if (!is_readable($plugin_file)) {
            return false;
        }

        $contents = substr(UCP_Helpers::read_file($plugin_file), 0, 8192);
        if (!is_string($contents)) {
            return false;
        }

        $strong_markers = array(
            "define('UCP_VERSION'",
            'define(\'UCP_VERSION\'',
            'define("UCP_VERSION"',
            'UCP_BASENAME',
            'class UCP_Plugin',
            'Text Domain: ultracache-pro',
        );

        foreach ($strong_markers as $marker) {
            if (false !== strpos($contents, $marker)) {
                return true;
            }
        }

        return false;
    }

}
