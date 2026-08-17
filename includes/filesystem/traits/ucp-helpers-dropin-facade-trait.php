<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP helper symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Backward-compatible static wrappers for the extracted service implementation. */
trait UCP_Helpers_Dropin_Facade_Trait {
    public static function wp_config_path() {
        return UCP_Dropin_Manager::wp_config_path();
    }

    public static function can_manage_wp_config() {
        return UCP_Dropin_Manager::can_manage_wp_config();
    }

    public static function wp_cache_owner_marker() {
        return UCP_Dropin_Manager::wp_cache_owner_marker();
    }

    public static function has_own_wp_cache_constant($content = null) {
        return UCP_Dropin_Manager::has_own_wp_cache_constant($content);
    }

    public static function ensure_wp_cache_constant($force = false) {
        return UCP_Dropin_Manager::ensure_wp_cache_constant($force);
    }

    public static function remove_own_wp_cache_constant($force = false) {
        return UCP_Dropin_Manager::remove_own_wp_cache_constant($force);
    }

    public static function has_valid_wp_cache_constant() {
        return UCP_Dropin_Manager::has_valid_wp_cache_constant();
    }

    public static function detect_advanced_cache_owner($content = null) {
        return UCP_Dropin_Manager::detect_advanced_cache_owner($content);
    }

    public static function advanced_cache_signature() {
        return UCP_Dropin_Manager::advanced_cache_signature();
    }

    public static function is_own_advanced_cache($content = null) {
        return UCP_Dropin_Manager::is_own_advanced_cache($content);
    }

    public static function backup_existing_advanced_cache() {
        return UCP_Dropin_Manager::backup_existing_advanced_cache();
    }

    public static function install_own_advanced_cache_with_backup($force_writes = false) {
        return UCP_Dropin_Manager::install_own_advanced_cache_with_backup($force_writes);
    }

    public static function maybe_install_own_advanced_cache_automatically($fresh_install = false) {
        return UCP_Dropin_Manager::maybe_install_own_advanced_cache_automatically($fresh_install);
    }

    public static function maybe_verify_advanced_cache_setup() {
        return UCP_Dropin_Manager::maybe_verify_advanced_cache_setup();
    }

    public static function verify_advanced_cache_setup() {
        return UCP_Dropin_Manager::verify_advanced_cache_setup();
    }

    public static function dropin_config_path() {
        return UCP_Dropin_Manager::dropin_config_path();
    }

    public static function write_dropin_config($force = false) {
        return UCP_Dropin_Manager::write_dropin_config($force);
    }

    public static function remove_dropin_config() {
        return UCP_Dropin_Manager::remove_dropin_config();
    }

    public static function write_advanced_cache_stub($force = false, $allow_takeover = false) {
        return UCP_Dropin_Manager::write_advanced_cache_stub($force, $allow_takeover);
    }

    public static function remove_own_advanced_cache_stub($force = false) {
        return UCP_Dropin_Manager::remove_own_advanced_cache_stub($force);
    }

    protected static function ensure_insert_with_markers() {
        return UCP_Dropin_Manager::ensure_insert_with_markers();
    }

    public static function maybe_write_browser_cache_rules() {
        return UCP_Dropin_Manager::maybe_write_browser_cache_rules();
    }

    public static function remove_browser_cache_rules() {
        return UCP_Dropin_Manager::remove_browser_cache_rules();
    }

    public static function write_direct_cache_server_rule_exports() {
        return UCP_Dropin_Manager::write_direct_cache_server_rule_exports();
    }

    public static function maybe_write_direct_cache_rules() {
        return UCP_Dropin_Manager::maybe_write_direct_cache_rules();
    }

    public static function remove_direct_cache_rules() {
        return UCP_Dropin_Manager::remove_direct_cache_rules();
    }

    protected static function direct_cache_marker_block($rules) {
        return UCP_Dropin_Manager::direct_cache_marker_block($rules);
    }

    protected static function remove_direct_cache_marker_block($content) {
        return UCP_Dropin_Manager::remove_direct_cache_marker_block($content);
    }

    protected static function normalize_htaccess_content($content) {
        return UCP_Dropin_Manager::normalize_htaccess_content($content);
    }

    public static function browser_cache_rules() {
        return UCP_Dropin_Manager::browser_cache_rules();
    }

}
