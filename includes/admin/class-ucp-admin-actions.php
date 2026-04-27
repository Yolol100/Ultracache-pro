<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Actions {
    protected $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function export_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_export_settings');
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=ultracache-settings.json');
        $settings = UCP_Options::get_all();
        if (class_exists('UCP_Support_Bundle')) {
            $settings = UCP_Support_Bundle::redact_settings($settings);
        }
        echo wp_json_encode($settings, JSON_PRETTY_PRINT);
        exit;
    }

    public function export_support_bundle() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_export_support_bundle');
        $bundle = class_exists('UCP_Support_Bundle') ? UCP_Support_Bundle::build() : array('error' => 'support_bundle_unavailable');
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=ultracache-support-bundle.json');
        echo wp_json_encode($bundle, JSON_PRETTY_PRINT);
        exit;
    }

    public function import_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_import_settings');
        if (empty($_FILES['ucp_import_file']['tmp_name'])) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $file = wp_unslash($_FILES['ucp_import_file']);
        if (!isset($file['error']) || UPLOAD_ERR_OK !== (int) $file['error']) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        if (empty($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $filename = !empty($file['name']) ? sanitize_file_name($file['name']) : 'ultracache-settings.json';
        if ('' === $filename || !preg_match('/\.(json|txt)$/i', $filename)) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $checked = wp_check_filetype_and_ext($file['tmp_name'], $filename, array(
            'json' => 'application/json',
            'txt'  => 'text/plain',
        ));
        if (empty($checked['ext'])) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        if (!empty($file['size']) && (int) $file['size'] > 256 * 1024) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $raw = file_get_contents($file['tmp_name']);
        if (!is_string($raw) || '' === trim($raw) || strlen($raw) > 256 * 1024) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || JSON_ERROR_NONE !== json_last_error()) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $decoded = UCP_Options::validate_import_payload($decoded);
        if (is_array($decoded) && !empty($decoded)) {
            UCP_Options::update($this->admin->sanitize($decoded));
            ucp_noop('info', 'admin', 'settings_imported', 'Settings imported from JSON.');
        }
        wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 1)));
        exit;
    }

    public function apply_easy_mode() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_apply_easy_mode');
        $mode = UCP_Helpers::query_arg_key('mode', 'safe_on');
        if ('safe_off' === $mode) {
            UCP_Presets::apply('safe_off');
            wp_safe_redirect($this->admin->tab_url_public('overview', array('easy_mode' => 'off')));
            exit;
        }
        UCP_Presets::apply('balanced');
        wp_safe_redirect($this->admin->tab_url_public('overview', array('easy_mode' => 'on')));
        exit;
    }

    public function apply_preset() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_apply_preset');
        $preset = UCP_Helpers::query_arg_key('preset');
        UCP_Presets::apply($preset);
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&preset=1'));
        exit;
    }

    public function complete_onboarding() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_complete_onboarding');
        $site_type = UCP_Helpers::post_arg_key('site_type', 'general');
        $goal = UCP_Helpers::post_arg_key('onboarding_goal', UCP_Options::get('onboarding_goal', 'safe'));
        $map = array(
            'woocommerce' => 'woocommerce',
            'builder'     => 'builder',
            'edge'        => 'edge',
            'general'     => 'balanced',
        );
        $preset = isset($map[$site_type]) ? $map[$site_type] : 'balanced';
        UCP_Presets::apply($preset);
        $settings = UCP_Options::get_all();
        $settings['onboarding_completed'] = 1;
        $settings['onboarding_site_type'] = $site_type;
        $settings['onboarding_goal'] = in_array($goal, array('safe', 'balanced', 'aggressive'), true) ? $goal : 'safe';
$settings['ui_mode'] = 'simple';
        if ('balanced' === $settings['onboarding_goal']) {
            $settings['enable_speculative_loading'] = 0;
            $settings['enable_delay_js'] = 0;
            $settings['enable_lazy_images'] = 1;
            $settings['enable_lazy_iframes'] = 1;
        } elseif ('aggressive' === $settings['onboarding_goal']) {
            $settings['enable_speculative_loading'] = 0;
            $settings['enable_delay_js'] = 0;
            $settings['enable_lazy_images'] = 1;
            $settings['enable_lazy_iframes'] = 1;
            $settings['enable_used_css'] = 0;
            $settings['enable_css_queue'] = 0;
            $settings['enable_remote_css_render'] = 0;
            $settings['enable_html_minify'] = 0;
            $settings['remove_html_comments'] = 1;
            $settings['enable_css_minify'] = 1;
            $settings['enable_js_minify'] = 1;
        } else {
            $settings['enable_speculative_loading'] = 0;
            $settings['enable_delay_js'] = 0;
        }
        $step = UCP_Helpers::post_arg_int('setup_step');
        UCP_Options::update($settings);
        UCP_Runtime_Tests::run_all();
        ucp_noop('info', 'admin', 'onboarding_completed', 'Onboarding completed.', array('site_type' => $site_type, 'ui_mode' => 'simple', 'goal' => $settings['onboarding_goal']));
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&onboarding=1&setup_step=' . min(4, $step + 1)));
        exit;
    }


    public function apply_auto_compat() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_apply_auto_compat');

        if (class_exists('UCP_Integrations')) {
            UCP_Integrations::autodetect();
        }

        $settings = UCP_Options::get_all();
        $detected = class_exists('UCP_Integrations') ? UCP_Integrations::detected() : array();
        $conflicts = class_exists('UCP_Compat') ? UCP_Compat::detected_conflicts() : array();
        $manual_js_flags = array(
            'defer_all_js' => isset($settings['defer_all_js']) ? (int) !empty($settings['defer_all_js']) : 0,
            'enable_delay_js' => isset($settings['enable_delay_js']) ? (int) !empty($settings['enable_delay_js']) : 0,
            'enable_defer_js_fallback' => isset($settings['enable_defer_js_fallback']) ? (int) !empty($settings['enable_defer_js_fallback']) : 0,
            'enable_native_script_strategy' => isset($settings['enable_native_script_strategy']) ? (int) !empty($settings['enable_native_script_strategy']) : 0,
            'enable_js_combine' => isset($settings['enable_js_combine']) ? (int) !empty($settings['enable_js_combine']) : 0,
            'enable_css_combine' => isset($settings['enable_css_combine']) ? (int) !empty($settings['enable_css_combine']) : 0,
        );

        if (class_exists('UCP_Integrations')) {
            $settings = UCP_Integrations::apply_autopilot_v2_settings($settings, $detected, $conflicts);
        }

        $settings = array_merge($settings, $manual_js_flags);
        UCP_Options::update($settings);
        wp_safe_redirect($this->admin->tab_url_public('compatibility', array('auto_compat' => 1)));
        exit;
    }


    public function apply_safe_html_test() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_apply_safe_html_test');
        if (class_exists('UCP_Integrations')) {
            UCP_Integrations::autodetect();
        }
        $settings = UCP_Options::get_all();
        $detected = class_exists('UCP_Integrations') ? UCP_Integrations::detected() : array();
        $settings['remove_html_comments'] = 1;
        $settings['enable_html_minify'] = 1;
        $settings['enable_html_test_mode'] = 1;
        $settings['html_exclude_urls'] = trim((string) $settings['html_exclude_urls'] . "
/cart
/checkout
/my-account
/wp-json/
?preview=true
?elementor-preview=");
        $settings['html_exclude_templates'] = trim((string) $settings['html_exclude_templates'] . "
canvas
full-width");
        if (!empty($detected['builder']) || !empty($detected['seo']) || !empty($detected['yoast']) || !empty($detected['rank_math']) || !empty($detected['aioseo']) || !empty($detected['seopress']) || !empty($detected['seo_framework']) || !empty($detected['slim_seo']) || !empty($detected['squirrly_seo']) || !empty($detected['consent']) || !empty($detected['acf']) || !empty($detected['forms']) || !empty($detected['analytics'])) {
            $settings['enable_html_minify'] = 0;
        }
        UCP_Options::update($settings);
        wp_safe_redirect($this->admin->tab_url_public('optimization', array('html_test_defaults' => 1)));
        exit;
    }

    public function apply_safe_heartbeat() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_apply_safe_heartbeat');
        $settings = UCP_Options::get_all();
        $settings['enable_heartbeat_control'] = 1;
        $settings['heartbeat_frontend_frequency'] = 60;
        $settings['heartbeat_editor_frequency'] = 30;
        $settings['heartbeat_backend_frequency'] = 60;
        $settings['heartbeat_frequency'] = 60;
        UCP_Options::update($settings);
        wp_safe_redirect($this->admin->tab_url_public('expert', array('heartbeat_defaults' => 1)));
        exit;
    }

    public function apply_safe_preload() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_apply_safe_preload');
        $settings = UCP_Options::get_all();
        $settings['enable_preload'] = 1;
        $settings['enable_preload_queue'] = 1;
        $settings['preload_homepage'] = 1;
        $settings['preload_sitemaps'] = 1;
        $settings['preload_batch_size'] = 15;
        $settings['preload_max_urls'] = 250;
        $settings['preload_delay_ms'] = 500;
        UCP_Options::update($settings);
        wp_safe_redirect($this->admin->tab_url_public('preload', array('preload_defaults' => 1)));
        exit;
    }


    public function check_dropin_owner() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_check_dropin_owner');
        if (class_exists('UCP_Compat')) {
            UCP_Compat::store_conflict_snapshot();
        }
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
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_fix_server_cache');

        UCP_Options::update(array(
            'allow_wp_config_write' => 1,
            'allow_dropin_writes'   => 1,
        ));

        $result = UCP_Helpers::install_own_advanced_cache_with_backup();
        $args = array();

        if (!empty($result['installed'])) {
            $args['server_cache_fixed'] = 1;
            $args['cache_enabled'] = 1;

            $settings = UCP_Options::get_all();
            $settings['enable_cache'] = 1;
            $settings['enable_preload'] = 1;
            $settings['enable_preload_queue'] = 1;
            $settings['allow_wp_config_write'] = 1;
            $settings['allow_dropin_writes'] = 1;
            $settings['confirm_page_cache_takeover'] = 1;
            $settings['auto_advanced_cache_takeover'] = 1;
            UCP_Options::update($settings);

            if (!empty($result['backup'])) {
                $args['dropin_backup'] = 1;
            }
        } elseif (!empty($result['preserved_existing'])) {
            $args['server_cache_preserved'] = 1;
        } else {
            $args['server_cache_failed'] = 1;
        }

        if (empty($result['wp_cache']) && !UCP_Helpers::has_valid_wp_cache_constant()) {
            $args['wp_cache_failed'] = 1;
            if (!UCP_Helpers::can_manage_wp_config()) {
                $args['wp_config_unwritable'] = 1;
            }
        }

        if (class_exists('UCP_Compat')) {
            UCP_Compat::store_conflict_snapshot();
        }

        $redirect_tab = UCP_Helpers::query_arg_key('redirect_tab', 'overview');
        wp_safe_redirect($this->admin->tab_url_public($redirect_tab, $args));
        exit;
    }

    public function quick_enable_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_quick_enable_cache');
        $settings = UCP_Options::get_all();
        UCP_Options::backup_current_settings('quick_enable_cache');
        $takeover = class_exists('UCP_Compat') ? UCP_Compat::safe_takeover_status() : array('can_auto_enable' => false, 'status' => 'uncertain');
        update_option('ucp_safe_takeover_status', $takeover, false);
        $settings = UCP_Options::recommended_safe_settings($settings);
        if (empty($takeover['can_auto_enable']) && empty($settings['confirm_page_cache_takeover'])) {
            $settings['enable_cache'] = 0;
            $settings['enable_preload'] = 0;
            $settings['enable_preload_queue'] = 0;
            $settings['allow_wp_config_write'] = 0;
            $settings['allow_dropin_writes'] = 0;
            UCP_Options::update($settings);
            wp_safe_redirect($this->admin->tab_url_public('overview', array('cache_takeover_blocked' => 1)));
            exit;
        }
        $settings['enable_cache'] = 1;
        $settings['enable_preload'] = 1;
        $settings['enable_preload_queue'] = 1;
        $settings['allow_wp_config_write'] = !empty($settings['confirm_page_cache_takeover']) ? 1 : 0;
        $settings['allow_dropin_writes'] = !empty($settings['confirm_page_cache_takeover']) ? 1 : 0;
        UCP_Options::update($settings);
        if (!empty($settings['confirm_page_cache_takeover']) && class_exists('UCP_Helpers')) {
            UCP_Helpers::install_own_advanced_cache_with_backup(true);
        }
        if (class_exists('UCP_Compat')) {
            UCP_Compat::store_conflict_snapshot();
        }
        if (class_exists('UCP_Runtime_Tests')) {
            UCP_Runtime_Tests::run_all();
        }
        ucp_noop('info', 'admin', 'quick_enable_cache', 'Safe cache enable flow completed.', array('takeover_status' => isset($takeover['status']) ? $takeover['status'] : 'unknown'));
        wp_safe_redirect($this->admin->tab_url_public('overview', array('cache_enabled' => 1)));
        exit;
    }

    public function apply_recommended_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_apply_recommended_settings');
        UCP_Options::backup_current_settings('recommended_settings');
        $settings = UCP_Options::recommended_safe_settings(UCP_Options::get_all());
        $takeover = class_exists('UCP_Compat') ? UCP_Compat::safe_takeover_status() : array('can_auto_enable' => false, 'status' => 'uncertain');
        update_option('ucp_safe_takeover_status', $takeover, false);
        if (!empty($takeover['can_auto_enable'])) {
            $settings['enable_cache'] = 1;
            $settings['enable_preload'] = 1;
            $settings['enable_preload_queue'] = 1;
        } else {
            $settings['enable_cache'] = 0;
            $settings['enable_preload'] = 0;
            $settings['enable_preload_queue'] = 0;
        }
        UCP_Options::update($settings);
        if (class_exists('UCP_Runtime_Tests')) {
            UCP_Runtime_Tests::run_all();
        }
        if (class_exists('UCP_Compat')) {
            UCP_Compat::store_conflict_snapshot();
        }
        ucp_noop('info', 'admin', 'recommended_settings_applied', 'Recommended safe settings applied.', array('takeover_status' => isset($takeover['status']) ? $takeover['status'] : 'unknown'));
        wp_safe_redirect($this->admin->tab_url_public('overview', array('recommended_applied' => 1)));
        exit;
    }

    public function restore_settings_rollback() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_restore_settings_rollback');
        $restored = UCP_Options::restore_rollback();
        wp_safe_redirect($this->admin->tab_url_public('overview', array($restored ? 'rollback_restored' : 'rollback_missing' => 1)));
        exit;
    }

    public function toggle_ui_mode() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_toggle_ui_mode');
        $settings = UCP_Options::get_all();
        $settings['ui_mode'] = (!empty($settings['ui_mode']) && 'advanced' === $settings['ui_mode']) ? 'simple' : 'advanced';
        UCP_Options::update($settings);
        wp_safe_redirect($this->admin->tab_url_public('overview', array('mode_changed' => 1)));
        exit;
    }
    public function run_health_check() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_run_health_check');
        UCP_Health::run_checks();
        wp_safe_redirect($this->admin->tab_url_public('tools', array('health' => 1)));
        exit;
    }

    public function purge_rest_cache() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_purge_rest_cache');
        if (class_exists('UCP_REST_Cache')) { UCP_REST_Cache::purge_all(); }
        wp_safe_redirect($this->admin->tab_url_public('expert', array('rest_purged' => 1)));
        exit;
    }

    public function purge_fragment_cache() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_purge_fragment_cache');
        if (class_exists('UCP_Fragment_Cache')) { UCP_Fragment_Cache::purge_all(); }
        wp_safe_redirect($this->admin->tab_url_public('expert', array('fragments_purged' => 1)));
        exit;
    }

    public function start_crawler() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_start_crawler');
        $mode = UCP_Helpers::query_arg_key('mode', UCP_Options::get('crawler_mode', 'sitemap'));
        if (class_exists('UCP_Crawler')) { UCP_Crawler::start($mode); }
        wp_safe_redirect($this->admin->tab_url_public('preload', array('crawler_started' => 1)));
        exit;
    }

    public function pause_crawler() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_pause_crawler');
        if (class_exists('UCP_Crawler')) { UCP_Crawler::pause(); }
        wp_safe_redirect($this->admin->tab_url_public('preload', array('crawler_paused' => 1)));
        exit;
    }

    public function set_serve_mode() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_set_serve_mode');
        $mode = UCP_Helpers::query_arg_key('mode', 'safe');
        if (class_exists('UCP_Serve_Mode')) { UCP_Serve_Mode::set_mode($mode); }
        wp_safe_redirect($this->admin->tab_url_public('expert', array('serve_mode_changed' => 1)));
        exit;
    }

    public function apply_apache_rules() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_apply_apache_rules');
        $result = class_exists('UCP_Apache_Rules') ? UCP_Apache_Rules::apply() : array('ok' => false);
        wp_safe_redirect($this->admin->tab_url_public('expert', array(!empty($result['ok']) ? 'apache_applied' : 'apache_failed' => 1)));
        exit;
    }

    public function rollback_apache_rules() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_rollback_apache_rules');
        $result = class_exists('UCP_Apache_Rules') ? UCP_Apache_Rules::rollback() : array('ok' => false);
        wp_safe_redirect($this->admin->tab_url_public('expert', array(!empty($result['ok']) ? 'apache_rollback' : 'apache_failed' => 1)));
        exit;
    }

    public function update_compat_rules() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_update_compat_rules');
        $result = class_exists('UCP_Compat_Rule_Updater') ? UCP_Compat_Rule_Updater::update_from_remote() : array('ok' => false);
        wp_safe_redirect($this->admin->tab_url_public('compatibility', array(!empty($result['ok']) ? 'compat_rules_updated' : 'compat_rules_failed' => 1)));
        exit;
    }

    public function rollback_compat_rules() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_rollback_compat_rules');
        if (class_exists('UCP_Compat_Rules')) { UCP_Compat_Rules::rollback_remote(); }
        wp_safe_redirect($this->admin->tab_url_public('compatibility', array('compat_rules_rollback' => 1)));
        exit;
    }

    public function provider_test() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_provider_test');
        $settings = UCP_Options::get_all();
        $provider = class_exists('UCP_Provider_Manager') ? UCP_Provider_Manager::get() : null;
        $result = $provider ? $provider->validate_credentials($settings) : array('ok' => false, 'reason' => 'provider_missing');
        if (class_exists('UCP_Audit_Log')) { UCP_Audit_Log::record('provider_credential_test', !empty($result['ok']) ? 'success' : 'failed', array('provider' => isset($settings['cdn_provider']) ? $settings['cdn_provider'] : 'none', 'code' => isset($result['code']) ? $result['code'] : 0)); }
        wp_safe_redirect($this->admin->tab_url_public('cdn', array(!empty($result['ok']) ? 'provider_ok' : 'provider_failed' => 1)));
        exit;
    }

    public function provider_purge_test() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Geen toegang.', 'ultracache-pro')); }
        check_admin_referer('ucp_provider_purge_test');
        $settings = UCP_Options::get_all();
        $provider = class_exists('UCP_Provider_Manager') ? UCP_Provider_Manager::get() : null;
        $result = $provider ? $provider->test_purge($settings) : array('ok' => false, 'reason' => 'provider_missing');
        if (class_exists('UCP_Audit_Log')) { UCP_Audit_Log::record('provider_purge_test', !empty($result['ok']) ? 'success' : 'failed', array('provider' => isset($settings['cdn_provider']) ? $settings['cdn_provider'] : 'none', 'code' => isset($result['code']) ? $result['code'] : 0)); }
        wp_safe_redirect($this->admin->tab_url_public('cdn', array(!empty($result['ok']) ? 'provider_purge_ok' : 'provider_purge_failed' => 1)));
        exit;
    }

    public function plugin_links($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=ultracache-pro')) . '">' . esc_html__('Instellingen', 'ultracache-pro') . '</a>');
        return $links;
    }

    public function render_notices() {
        $map = array(
            'preset'     => __('Instellingen aangezet.', 'ultracache-pro'),
            'seeded'     => __('Testtaken toegevoegd.', 'ultracache-pro'),
            'jobs'       => __('Taken gestart.', 'ultracache-pro'),
            'import'     => __('Bestand verwerkt.', 'ultracache-pro'),
            'health'     => __('Controle bijgewerkt.', 'ultracache-pro'),
            'onboarding' => __('Eerste hulp is klaar.', 'ultracache-pro'),
            'maintenance' => __('Onderhoud is klaar.', 'ultracache-pro'),
            'purged'     => __('Cache is geleegd.', 'ultracache-pro'),
            'preloaded'  => __('Cache is opgewarmd.', 'ultracache-pro'),
            'preload_queued' => __("Pagina's zijn toegevoegd om op te warmen.", 'ultracache-pro'),
            'cache_enabled' => __('Cache is ingeschakeld.', 'ultracache-pro'),
            'recommended_applied' => __('Aanbevolen veilige instellingen zijn toegepast.', 'ultracache-pro'),
            'rollback_restored' => __('Instellingen zijn teruggezet naar de vorige versie.', 'ultracache-pro'),
            'cache_takeover_blocked' => __('Page-cache is niet automatisch overgenomen. Controleer het Compatibiliteit centrum.', 'ultracache-pro'),
            'mode_changed' => __('Weergavemodus is aangepast.', 'ultracache-pro'),
        );
        foreach ($map as $query_key => $message) {
            if (isset($_GET[$query_key])) {
                echo '<div class="notice notice-success is-dismissible ucp-notice"><p>' . esc_html($message) . '</p></div>';
                break;
            }
        }
    }
}
