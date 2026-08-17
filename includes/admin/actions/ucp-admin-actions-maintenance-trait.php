<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- Admin actions verify capabilities/nonces before writes; read-only notice parameters are sanitized before display.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Actions_Maintenance_Trait {
    public function check_dropin_owner() {
        UCP_Helpers::require_post_admin_action('ucp_check_dropin_owner');
        UCP_Compat::store_conflict_snapshot();
        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        $owner = '';
        if (file_exists($target) && is_readable($target)) {
            $owner = UCP_Helpers::detect_advanced_cache_owner(UCP_Helpers::read_file_head($target, 64 * KB_IN_BYTES));
            update_option('ucp_advanced_cache_owner', $owner, false);
        }
        wp_safe_redirect($this->admin->tab_url_public('cache', array('dropin_checked' => 1)));
        exit;
    }

    public function fix_server_cache() {
        UCP_Helpers::require_post_admin_action('ucp_fix_server_cache');
        $settings = UCP_Options::get_all();

        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        $has_foreign_dropin = file_exists($target) && is_readable($target) && !UCP_Helpers::is_own_advanced_cache(UCP_Helpers::read_file_head($target, 64 * KB_IN_BYTES));
        $force_takeover = absint(UCP_Helpers::request_scalar('force_takeover', '0', 8));
        if ($has_foreign_dropin && 1 !== $force_takeover) {
            $owner = UCP_Helpers::detect_advanced_cache_owner(UCP_Helpers::read_file_head($target, 64 * KB_IN_BYTES));
            update_option('ucp_advanced_cache_conflict', array(
                'detected_at' => current_time('mysql', true),
                'path'        => $target,
                'owner'       => $owner,
                'backup'      => '',
                'replace_failed' => 0,
            ), false);
            UCP_Logger::log('warning', 'admin', 'dropin_takeover_blocked', __('Bestaande advanced-cache.php behouden; geforceerde overname is niet bevestigd.', 'ultracache-pro'), array('owner' => $owner));
            wp_safe_redirect($this->admin->tab_url_public('cache', array('server_cache_preserved' => 1, 'dropin_owner' => rawurlencode($owner))));
            exit;
        }

        $settings['allow_dropin_takeover'] = $force_takeover ? 1 : 0;
        if (!UCP_Options::update($settings)) {
            wp_safe_redirect($this->admin->tab_url_public('cache', array('server_cache_fixed' => 0, 'settings_write_failed' => 1)));
            exit;
        }
        $result = UCP_Helpers::install_own_advanced_cache_with_backup(true);
        if (!empty($result['installed']) && !empty($result['wp_cache'])) {
            update_option('ucp_advanced_cache_auto_status', array(
                'status' => 'finalizing',
                'attempts' => 0,
                'detected_at' => current_time('mysql', true),
            ), false);
        }
        if ($force_takeover) {
            // Note: treat a foreign drop-in takeover as a one-time admin action instead of leaving future automatic takeovers enabled.
            $settings['allow_dropin_takeover'] = 0;
            if (!UCP_Options::update($settings)) {
                $result['settings_reset_failed'] = 1;
            }
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
        UCP_Helpers::require_post_admin_action('ucp_quick_enable_cache');
        $settings = UCP_Options::get_all();
        $settings['enable_cache'] = 1;
        $settings['enable_woocommerce_rules'] = 1;
        $settings['purge_on_post_update'] = 1;
        $settings['enable_targeted_purge'] = 1;
        if (!UCP_Options::update($settings)) {
            wp_safe_redirect($this->admin->tab_url_public('overview', array('cache_enabled' => 0, 'settings_write_failed' => 1)));
            exit;
        }
        if (class_exists('UCP_Helpers') && (!class_exists('UCP_Compat') || !UCP_Compat::has_active_page_cache_plugin())) {
            $target = WP_CONTENT_DIR . '/advanced-cache.php';
            if (file_exists($target) && is_readable($target) && !UCP_Helpers::is_own_advanced_cache(UCP_Helpers::read_file_head($target, 64 * KB_IN_BYTES))) {
                $owner = UCP_Helpers::detect_advanced_cache_owner(UCP_Helpers::read_file_head($target, 64 * KB_IN_BYTES));
                update_option('ucp_advanced_cache_conflict', array(
                    'detected_at' => current_time('mysql', true),
                    'path'        => $target,
                    'owner'       => $owner,
                    'backup'      => '',
                    'replace_failed' => 0,
                ), false);
                UCP_Logger::log('warning', 'admin', 'quick_enable_dropin_preserved', __('Snel inschakelen heeft de bestaande advanced-cache.php behouden.', 'ultracache-pro'), array('owner' => $owner));
            } else {
                $result = UCP_Helpers::install_own_advanced_cache_with_backup(true);
                if (!empty($result['installed']) && !empty($result['wp_cache'])) {
                    update_option('ucp_advanced_cache_auto_status', array(
                        'status' => 'finalizing',
                        'attempts' => 0,
                        'detected_at' => current_time('mysql', true),
                    ), false);
                }
            }
        }
        UCP_Compat::store_conflict_snapshot();
        wp_safe_redirect($this->admin->tab_url_public('overview', array('cache_enabled' => 1)));
        exit;
    }

    public function run_health_check() {
        UCP_Helpers::require_post_admin_action('ucp_run_health_check');
        UCP_Health::run_checks();
        wp_safe_redirect($this->admin->tab_url_public('tools', array('health' => 1)));
        exit;
    }

    public function apply_quick_exclusions() {
        UCP_Helpers::require_post_admin_action('ucp_apply_quick_exclusions');
        $group = sanitize_key($this->admin_action_scalar('group'));
        $settings = UCP_Options::get_all();
        $path = UCP_PATH . 'compat/quick-exclusions.json';
        $map = is_readable($path) ? UCP_Helpers::safe_json_decode(UCP_Helpers::read_file($path), true) : array();
        if (is_array($map) && isset($map[$group]) && !empty($map[$group]['delay_js'])) {
            $current = UCP_Helpers::normalize_multiline(isset($settings['delay_js_exclusions']) ? $settings['delay_js_exclusions'] : '');
            $extra = array_map('strval', array_filter((array) $map[$group]['delay_js'], 'is_scalar'));
            $settings['delay_js_exclusions'] = implode("\n", array_values(array_unique(array_filter(array_merge($current, $extra), 'strlen'))));
            $saved = UCP_Options::update($settings);
        } else {
            $saved = false;
        }
        wp_safe_redirect($this->admin->tab_url_public('optimization', array('quick_exclusions' => $saved ? 1 : 0)));
        exit;
    }

    public function check_compat_lists() {
        UCP_Helpers::require_post_admin_action('ucp_check_compat_lists');
        $files = UCP_Helpers::safe_glob_files(trailingslashit(UCP_PATH) . 'compat/*.json', 500, array(UCP_PATH));
        $ok = 1;
        $count = 0;
        foreach ((array) $files as $file) {
            $raw = is_readable($file) ? UCP_Helpers::read_file($file) : '';
            UCP_Helpers::safe_json_decode((string) $raw, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $ok = 0;
                break;
            }
            $count++;
        }
        UCP_Logger::log($ok ? 'info' : 'warning', 'compat', 'compat_lists_checked', $ok ? __('Compatibiliteitslijsten zijn geldig.', 'ultracache-pro') : __('Validatie van compatibiliteitslijsten is mislukt.', 'ultracache-pro'), array('files' => $count));
        wp_safe_redirect($this->admin->tab_url_public('tools', array('compat_lists' => $ok ? 1 : 0)));
        exit;
    }

}
