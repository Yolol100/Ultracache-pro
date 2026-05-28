<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- Admin actions verify capabilities/nonces before writes; read-only notice parameters are sanitized before display.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Actions_Maintenance_Trait {
    public function check_dropin_owner() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_check_dropin_owner');
        UCP_Compat::store_conflict_snapshot();
        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        $owner = '';
        if (file_exists($target) && is_readable($target)) {
            $owner = UCP_Helpers::detect_advanced_cache_owner(UCP_Helpers::read_file($target));
            update_option('ucp_advanced_cache_owner', $owner, false);
        }
        wp_safe_redirect($this->admin->tab_url_public('cache', array('dropin_checked' => 1)));
        exit;
    }

    public function fix_server_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_fix_server_cache');
        $settings = UCP_Options::get_all();
        $settings['allow_wp_config_write'] = 1;
        $settings['allow_dropin_writes'] = 1;

        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        $has_foreign_dropin = file_exists($target) && is_readable($target) && !UCP_Helpers::is_own_advanced_cache(UCP_Helpers::read_file($target));
        $force_takeover = isset($_REQUEST['force_takeover']) ? absint(wp_unslash($_REQUEST['force_takeover'])) : 0;
        if ($has_foreign_dropin && 1 !== $force_takeover) {
            $owner = UCP_Helpers::detect_advanced_cache_owner(UCP_Helpers::read_file($target));
            update_option('ucp_advanced_cache_conflict', array(
                'detected_at' => current_time('mysql', true),
                'path'        => $target,
                'owner'       => $owner,
                'backup'      => '',
                'replace_failed' => 0,
            ), false);
            UCP_Logger::log('warning', 'admin', 'dropin_takeover_blocked', 'Existing advanced-cache.php preserved; force takeover was not confirmed.', array('owner' => $owner));
            wp_safe_redirect($this->admin->tab_url_public('cache', array('server_cache_preserved' => 1, 'dropin_owner' => rawurlencode($owner))));
            exit;
        }

        $settings['allow_dropin_takeover'] = $force_takeover ? 1 : 0;
        UCP_Options::update($settings);
        $result = UCP_Helpers::install_own_advanced_cache_with_backup();
        if ($force_takeover) {
            // Note: treat a foreign drop-in takeover as a one-time admin action instead of leaving future automatic takeovers enabled.
            $settings['allow_dropin_takeover'] = 0;
            UCP_Options::update($settings);
        }
        $args = array();
        if (!empty($result['installed'])) {
            $args['server_cache_fixed'] = 1;
            if (!empty($result['backup'])) {
                $args['dropin_backup'] = 1;
            }
        } elseif (!empty($result['preserved_existing'])) {
            $args['server_cache_preserved'] = 1;
        } else {
            $args['server_cache_fixed'] = 0;
        }
        UCP_Compat::store_conflict_snapshot();
        wp_safe_redirect($this->admin->tab_url_public('cache', $args));
        exit;
    }

    public function quick_enable_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_quick_enable_cache');
        $settings = UCP_Options::get_all();
        $settings['enable_cache'] = 1;
        $settings['enable_woocommerce_rules'] = 1;
        $settings['purge_on_post_update'] = 1;
        $settings['enable_targeted_purge'] = 1;
        $settings['allow_wp_config_write'] = 1;
        $settings['allow_dropin_writes'] = 1;
        UCP_Options::update($settings);
        if (class_exists('UCP_Helpers') && (!class_exists('UCP_Compat') || !UCP_Compat::has_active_page_cache_plugin())) {
            $target = WP_CONTENT_DIR . '/advanced-cache.php';
            if (file_exists($target) && is_readable($target) && !UCP_Helpers::is_own_advanced_cache(UCP_Helpers::read_file($target))) {
                $owner = UCP_Helpers::detect_advanced_cache_owner(UCP_Helpers::read_file($target));
                update_option('ucp_advanced_cache_conflict', array(
                    'detected_at' => current_time('mysql', true),
                    'path'        => $target,
                    'owner'       => $owner,
                    'backup'      => '',
                    'replace_failed' => 0,
                ), false);
                UCP_Logger::log('warning', 'admin', 'quick_enable_dropin_preserved', 'Quick enable preserved an existing advanced-cache.php.', array('owner' => $owner));
            } else {
                UCP_Helpers::install_own_advanced_cache_with_backup();
            }
        }
        UCP_Compat::store_conflict_snapshot();
        wp_safe_redirect($this->admin->tab_url_public('overview', array('cache_enabled' => 1)));
        exit;
    }

    public function run_health_check() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_run_health_check');
        UCP_Health::run_checks();
        wp_safe_redirect($this->admin->tab_url_public('tools', array('health' => 1)));
        exit;
    }

    public function apply_quick_exclusions() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_apply_quick_exclusions');
        $group = isset($_GET['group']) ? sanitize_key(wp_unslash($_GET['group'])) : '';
        $settings = UCP_Options::get_all();
        $path = UCP_PATH . 'compat/quick-exclusions.json';
        $map = is_readable($path) ? json_decode(UCP_Helpers::read_file($path), true) : array();
        if (is_array($map) && isset($map[$group]) && !empty($map[$group]['delay_js'])) {
            $current = UCP_Helpers::normalize_multiline(isset($settings['delay_js_exclusions']) ? $settings['delay_js_exclusions'] : '');
            $extra = array_map('strval', (array) $map[$group]['delay_js']);
            $settings['delay_js_exclusions'] = implode("\n", array_values(array_unique(array_filter(array_merge($current, $extra), 'strlen'))));
            UCP_Options::update($settings);
        }
        wp_safe_redirect($this->admin->tab_url_public('optimization', array('quick_exclusions' => 1)));
        exit;
    }

    public function check_compat_lists() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_check_compat_lists');
        $files = glob(trailingslashit(UCP_PATH) . 'compat/*.json');
        $ok = 1;
        $count = 0;
        foreach ((array) $files as $file) {
            $raw = is_readable($file) ? UCP_Helpers::read_file($file) : '';
            json_decode((string) $raw, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $ok = 0;
                break;
            }
            $count++;
        }
        UCP_Logger::log($ok ? 'info' : 'warning', 'compat', 'compat_lists_checked', $ok ? 'Compatibility lists are valid.' : 'Compatibility list validation failed.', array('files' => $count));
        wp_safe_redirect($this->admin->tab_url_public('tools', array('compat_lists' => $ok ? 1 : 0)));
        exit;
    }

}
