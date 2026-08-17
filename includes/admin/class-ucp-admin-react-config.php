<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Admin_React_Config {
    /**
     * Build the localized admin application configuration.
     *
     * @return array<string,mixed>
     */
    public static function data() {
        return array(
            'restUrl' => esc_url_raw(rest_url('ultracache-pro/v1/')),
            'homeUrl' => esc_url_raw(home_url('/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => UCP_VERSION,
            'pluginName' => __('UltraCache Pro', 'ultracache-pro'),
            'caps' => array(
                'manageOptions' => current_user_can('manage_options'),
            ),
            'logDownloadUrl' => class_exists('UCP_Log_Package') ? UCP_Log_Package::download_url() : '',
            'objectCacheUrl' => class_exists('UCP_Admin_Object_Cache_Page')
                ? admin_url('admin.php?page=' . UCP_Admin_Object_Cache_Page::MENU_SLUG)
                : admin_url('admin.php?page=ultracache-object-cache'),
            'managedSettingKeys' => class_exists('UCP_Options') && method_exists('UCP_Options', 'automatic_managed_keys')
                ? UCP_Options::automatic_managed_keys()
                : array(),
            'protectedRuleDefaults' => class_exists('UCP_Options') && method_exists('UCP_Options', 'protected_rule_defaults')
                ? UCP_Options::protected_rule_defaults()
                : array(),
        );
    }
}
