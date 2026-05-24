<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- Admin actions verify capabilities/nonces before writes; read-only notice parameters are sanitized before display.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Actions_Presets_Trait {
    public function apply_easy_mode() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_apply_easy_mode');
        $mode = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : 'safe_on';
        if ('safe_off' === $mode) {
            UCP_Presets::apply('safe_off');
            wp_safe_redirect($this->admin->tab_url_public('overview', array('easy_mode' => 'off')));
            exit;
        }
        UCP_Presets::apply('balanced');
        wp_safe_redirect($this->admin->tab_url_public('overview', array('easy_mode' => 'on')));
        exit;
    }

    public function toggle_ui_mode() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_toggle_ui_mode');
        $mode = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : 'simple';
        $settings = UCP_Options::get_all();
        $settings['ui_mode'] = 'advanced' === $mode ? 'advanced' : 'simple';
        UCP_Options::update($settings);
        UCP_Logger::log('info', 'admin', 'ui_mode_changed', 'Adminmodus gewijzigd.', array('ui_mode' => $settings['ui_mode']));
        wp_safe_redirect($this->admin->tab_url_public('overview', array('ui_mode' => $settings['ui_mode'])));
        exit;
    }

    public function apply_preset() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_apply_preset');
        $preset = isset($_GET['preset']) ? sanitize_key(wp_unslash($_GET['preset'])) : '';
        UCP_Presets::apply($preset);
        wp_safe_redirect($this->admin->tab_url_public('overview', array('preset' => $preset ? $preset : '1')));
        exit;
    }

    public function complete_onboarding() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_complete_onboarding');
        $site_type = isset($_POST['site_type']) ? sanitize_key(wp_unslash($_POST['site_type'])) : 'general';
        $goal = isset($_POST['onboarding_goal']) ? sanitize_key(wp_unslash($_POST['onboarding_goal'])) : UCP_Options::get('onboarding_goal', 'safe');
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
        foreach (array('enable_woocommerce_rules', 'enable_admin_bar', 'enable_cloudflare_apo_mode', 'enable_early_hints_links', 'enable_cloud') as $flag) {
            if (isset($_POST[$flag])) {
                $settings[$flag] = 1;
            }
        }
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
            $settings['enable_js_minify'] = 0;
            $settings['allow_experimental_js_minify'] = 0;
        } else {
            $settings['enable_speculative_loading'] = 0;
            $settings['enable_delay_js'] = 0;
        }
        $step = isset($_POST['setup_step']) ? absint(wp_unslash($_POST['setup_step'])) : 0;
        UCP_Options::update($settings);
        UCP_Runtime_Tests::run_all();
        UCP_Logger::log('info', 'admin', 'onboarding_completed', 'Onboarding completed.', array('site_type' => $site_type, 'ui_mode' => 'simple', 'goal' => $settings['onboarding_goal']));
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&onboarding=1&setup_step=' . min(4, $step + 1)));
        exit;
    }

    public function apply_auto_compat() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
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
        wp_safe_redirect($this->admin->tab_url_public('tools', array('auto_compat' => 1)));
        exit;
    }

    public function apply_safe_html_test() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_apply_safe_html_test');
        if (class_exists('UCP_Integrations')) {
            UCP_Integrations::autodetect();
        }
        $settings = UCP_Options::get_all();
        $detected = class_exists('UCP_Integrations') ? UCP_Integrations::detected() : array();
        $settings['remove_html_comments'] = 1;
        $settings['enable_html_minify'] = 0;
        $settings['enable_html_test_mode'] = 0;
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
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_apply_safe_heartbeat');
        $settings = UCP_Options::get_all();
        $settings['enable_heartbeat_control'] = 1;
        $settings['heartbeat_frontend_behavior'] = 'reduce';
        $settings['heartbeat_editor_behavior'] = 'reduce';
        $settings['heartbeat_backend_behavior'] = 'reduce';
        $settings['heartbeat_frontend_frequency'] = 60;
        $settings['heartbeat_editor_frequency'] = 30;
        $settings['heartbeat_backend_frequency'] = 60;
        $settings['heartbeat_frequency'] = 60;
        UCP_Options::update($settings);
        wp_safe_redirect($this->admin->tab_url_public('heartbeat', array('heartbeat_defaults' => 1)));
        exit;
    }

    public function apply_safe_preload() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
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

}
