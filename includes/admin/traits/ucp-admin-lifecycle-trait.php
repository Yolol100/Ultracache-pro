<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Lifecycle_Trait {
    public function __construct() {
        $this->actions = new UCP_Admin_Actions($this);
        $this->notices = new UCP_Admin_Notices($this);
        $this->ui = new UCP_Admin_UI();
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'normalize_admin_routes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_enqueue_scripts', array($this->notices, 'enqueue_cache_toast_assets'));
        add_action('admin_notices', array($this->notices, 'hide_third_party_notices'), 1);
        add_action('all_admin_notices', array($this->notices, 'hide_third_party_notices'), 1);
        add_action('admin_footer', array($this->notices, 'render_cache_toast'), 99);
        add_action('admin_post_ucp_export_settings', array($this->actions, 'export_settings'));
        add_action('admin_post_ucp_import_settings', array($this->actions, 'import_settings'));
        add_action('admin_post_ucp_apply_preset', array($this->actions, 'apply_preset'));
        add_action('admin_post_ucp_apply_easy_mode', array($this->actions, 'apply_easy_mode'));
        add_action('admin_post_ucp_toggle_ui_mode', array($this->actions, 'toggle_ui_mode'));
        add_action('admin_post_ucp_complete_onboarding', array($this->actions, 'complete_onboarding'));
        add_action('admin_post_ucp_run_health_check', array($this->actions, 'run_health_check'));
        add_action('admin_post_ucp_apply_auto_compat', array($this->actions, 'apply_auto_compat'));
        add_action('admin_post_ucp_apply_safe_heartbeat', array($this->actions, 'apply_safe_heartbeat'));
        add_action('admin_post_ucp_apply_safe_preload', array($this->actions, 'apply_safe_preload'));
        add_action('admin_post_ucp_apply_safe_html_test', array($this->actions, 'apply_safe_html_test'));
        add_action('admin_post_ucp_fix_server_cache', array($this->actions, 'fix_server_cache'));
        add_action('admin_post_ucp_check_dropin_owner', array($this->actions, 'check_dropin_owner'));
        add_action('admin_post_ucp_quick_enable_cache', array($this->actions, 'quick_enable_cache'));
        add_action('admin_post_ucp_download_support_report', array($this->actions, 'download_support_report'));
        add_action('admin_post_ucp_apply_quick_exclusions', array($this->actions, 'apply_quick_exclusions'));
        add_action('admin_post_ucp_check_compat_lists', array($this->actions, 'check_compat_lists'));
        add_action('admin_post_ucp_clear_used_css', array($this->actions, 'clear_used_css'));
        add_action('admin_post_ucp_clear_minified_css', array($this->actions, 'clear_minified_css'));
        add_action('admin_post_ucp_clear_minified_js', array($this->actions, 'clear_minified_js'));
        add_action('admin_post_ucp_clear_priority_elements', array($this->actions, 'clear_priority_elements'));
        add_action('admin_post_ucp_reset_defaults', array($this->actions, 'reset_defaults'));
        add_action('admin_post_ucp_cleanup_meta_options', array($this->actions, 'cleanup_meta_options'));
        add_action('admin_post_ucp_clear_local_fonts', array($this->actions, 'clear_local_fonts'));
        add_filter('plugin_action_links_' . UCP_BASENAME, array($this->actions, 'plugin_links'));
    }

    public function __call($method, $arguments) {
        $allowed = array('get_onboarding_steps', 'current_onboarding_step', 'metric_card', 'chip', 'status_row', 'checkbox', 'text', 'number', 'textarea', 'select');
        if (in_array($method, $allowed, true) && method_exists($this->ui, $method)) {
            return call_user_func_array(array($this->ui, $method), $arguments);
        }

        if (method_exists($this, $method) && '__call' !== $method) {
            return call_user_func_array(array($this, $method), $arguments);
        }

        throw new BadMethodCallException(sprintf('Method %s does not exist.', esc_html($method)));
    }

    public function ui() {
        return $this->ui;
    }

    public function sanitize($input) {
        return UCP_Admin_Sanitizer::sanitize($input);
    }

    public function render_notices() {
        $this->actions->render_notices();
    }
}
