<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP helper symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Backward-compatible static wrappers for the extracted service implementation. */
trait UCP_Helpers_Filesystem_Facade_Trait {
    protected static function normalize_managed_path($path) {
        return UCP_Filesystem_Service::normalize_managed_path($path);
    }

    public static function is_managed_write_path($path) {
        return UCP_Filesystem_Service::is_managed_write_path($path);
    }

    protected static function is_managed_cache_path($path) {
        return UCP_Filesystem_Service::is_managed_cache_path($path);
    }

    protected static function is_safe_managed_write_target($path) {
        return UCP_Filesystem_Service::is_safe_managed_write_target($path);
    }

    public static function is_safe_managed_cache_file($path) {
        return UCP_Filesystem_Service::is_safe_managed_cache_file($path);
    }

    public static function open_managed_cache_file($path, $mode = 'c') {
        return UCP_Filesystem_Service::open_managed_cache_file($path, $mode);
    }

    protected static function root_htaccess_path() {
        return UCP_Filesystem_Service::root_htaccess_path();
    }

    protected static function is_root_htaccess_path($path) {
        return UCP_Filesystem_Service::is_root_htaccess_path($path);
    }

    protected static function read_root_htaccess() {
        return UCP_Filesystem_Service::read_root_htaccess();
    }

    protected static function write_root_htaccess($content) {
        return UCP_Filesystem_Service::write_root_htaccess($content);
    }

    protected static function delete_atomic_staging_file($file) {
        return UCP_Filesystem_Service::delete_atomic_staging_file($file);
    }

    public static function write_file_atomic($path, $content) {
        return UCP_Filesystem_Service::write_file_atomic($path, $content);
    }

    public static function write_upload_cache_file_atomic($path, $content, $base_dir) {
        return UCP_Filesystem_Service::write_upload_cache_file_atomic($path, $content, $base_dir);
    }

    public static function frontend_asset_with_min_fallback($relative_base, $extension = 'js') {
        return UCP_Filesystem_Service::frontend_asset_with_min_fallback($relative_base, $extension);
    }

    public static function private_dir_htaccess_rules() {
        return UCP_Filesystem_Service::private_dir_htaccess_rules();
    }

    public static function ensure_cache_dirs($force = false) {
        return UCP_Filesystem_Service::ensure_cache_dirs($force);
    }

    public static function invalidate_cache_dirs_check() {
        return UCP_Filesystem_Service::invalidate_cache_dirs_check();
    }

    public static function safe_delete_file($file) {
        return UCP_Filesystem_Service::safe_delete_file($file);
    }

    public static function write_placeholder_file($path, $content = '') {
        return UCP_Filesystem_Service::write_placeholder_file($path, $content);
    }

    public static function move_file($source, $destination) {
        return UCP_Filesystem_Service::move_file($source, $destination);
    }

    public static function write_file($path, $content) {
        return UCP_Filesystem_Service::write_file($path, $content);
    }

    public static function append_file($path, $content) {
        return UCP_Filesystem_Service::append_file($path, $content);
    }

    public static function read_file($path, $max_bytes = 0) {
        return UCP_Filesystem_Service::read_file($path, $max_bytes);
    }

    public static function file_url_from_path($path) {
        return UCP_Filesystem_Service::file_url_from_path($path);
    }

    public static function safe_glob_delete($pattern) {
        return UCP_Filesystem_Service::safe_glob_delete($pattern);
    }

    public static function safe_delete_cache_dir_contents($dir) {
        return UCP_Filesystem_Service::safe_delete_cache_dir_contents($dir);
    }

}
