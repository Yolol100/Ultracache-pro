<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Canonical implementation service for filesystem operations. */
final class UCP_Filesystem_Service {
    use UCP_Helpers_Filesystem_Trait {
        normalize_managed_path as public;
        is_managed_cache_path as public;
        is_safe_managed_write_target as public;
        root_htaccess_path as public;
        is_root_htaccess_path as public;
        read_root_htaccess as public;
        write_root_htaccess as public;
        delete_atomic_staging_file as public;
    }

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected static function log($message) {
        return UCP_Minify_Service::log($message);
    }

    protected static function wp_config_path() {
        return UCP_Dropin_Manager::wp_config_path();
    }

}
