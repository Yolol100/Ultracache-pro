<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin {
    protected $actions;
    protected $notices;
    protected $ui;

    public function __construct() {
        $this->actions = new UCP_Admin_Actions($this);
        $this->notices = new UCP_Admin_Notices($this);
        $this->ui = new UCP_Admin_UI();
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'normalize_admin_routes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_post_ucp_export_support_bundle', array($this->actions, 'export_support_bundle'));
        add_action('admin_notices', array($this->notices, 'hide_third_party_notices'), 1);
        add_action('all_admin_notices', array($this->notices, 'hide_third_party_notices'), 1);
        add_action('admin_notices', array($this->notices, 'render_admin_notices'), 20);
        add_action('admin_post_ucp_export_settings', array($this->actions, 'export_settings'));
        add_action('admin_post_ucp_import_settings', array($this->actions, 'import_settings'));
        add_action('admin_post_ucp_apply_preset', array($this->actions, 'apply_preset'));
        add_action('admin_post_ucp_apply_easy_mode', array($this->actions, 'apply_easy_mode'));
        add_action('admin_post_ucp_complete_onboarding', array($this->actions, 'complete_onboarding'));
        add_action('admin_post_ucp_run_health_check', array($this->actions, 'run_health_check'));
        add_action('admin_post_ucp_apply_auto_compat', array($this->actions, 'apply_auto_compat'));
        add_action('admin_post_ucp_apply_safe_heartbeat', array($this->actions, 'apply_safe_heartbeat'));
        add_action('admin_post_ucp_apply_safe_preload', array($this->actions, 'apply_safe_preload'));
        add_action('admin_post_ucp_apply_safe_html_test', array($this->actions, 'apply_safe_html_test'));
        add_action('admin_post_ucp_fix_server_cache', array($this->actions, 'fix_server_cache'));
        add_action('admin_post_ucp_check_dropin_owner', array($this->actions, 'check_dropin_owner'));
        add_action('admin_post_ucp_quick_enable_cache', array($this->actions, 'quick_enable_cache'));
        add_action('admin_post_ucp_apply_recommended_settings', array($this->actions, 'apply_recommended_settings'));
        add_action('admin_post_ucp_restore_settings_rollback', array($this->actions, 'restore_settings_rollback'));
        add_action('admin_post_ucp_toggle_ui_mode', array($this->actions, 'toggle_ui_mode'));
        add_action('admin_post_ucp_purge_rest_cache', array($this->actions, 'purge_rest_cache'));
        add_action('admin_post_ucp_purge_fragment_cache', array($this->actions, 'purge_fragment_cache'));
        add_action('admin_post_ucp_start_crawler', array($this->actions, 'start_crawler'));
        add_action('admin_post_ucp_pause_crawler', array($this->actions, 'pause_crawler'));
        add_action('admin_post_ucp_set_serve_mode', array($this->actions, 'set_serve_mode'));
        add_action('admin_post_ucp_apply_apache_rules', array($this->actions, 'apply_apache_rules'));
        add_action('admin_post_ucp_rollback_apache_rules', array($this->actions, 'rollback_apache_rules'));
        add_action('admin_post_ucp_update_compat_rules', array($this->actions, 'update_compat_rules'));
        add_action('admin_post_ucp_rollback_compat_rules', array($this->actions, 'rollback_compat_rules'));
        add_action('admin_post_ucp_provider_test', array($this->actions, 'provider_test'));
        add_action('admin_post_ucp_provider_purge_test', array($this->actions, 'provider_purge_test'));
        add_filter('plugin_action_links_' . UCP_BASENAME, array($this->actions, 'plugin_links'));
    }


    public function get_current_tab() {
        return UCP_Admin_Router::current_tab();
    }

    public function legacy_page_map() {
        return UCP_Admin_Router::legacy_page_map();
    }

    public function get_current_page_slug_public() {
        return 'ultracache-pro';
    }

    public function tab_url_public($tab, $args = array()) {
        return UCP_Admin_Router::tab_url($tab, $args);
    }

    public function get_current_page_slug() {
        return $this->get_current_page_slug_public();
    }

    public function tab_url($tab, $args = array()) {
        return $this->tab_url_public($tab, $args);
    }


    public function ui() {
        return $this->ui;
    }

    public function sanitize($input) {
        return UCP_Admin_Sanitizer::sanitize($input);
    }

    public function render_notices() {
        # Notices are rendered through UCP_Admin_Notices on admin_notices.
        # Keep this method as a no-op to avoid duplicate messages inside the plugin shell.
    }

    public function __call($method, $arguments) {
        $allowed = array('get_onboarding_steps', 'current_onboarding_step', 'metric_card', 'chip', 'status_row', 'checkbox', 'text', 'number', 'textarea', 'select');
        if (in_array($method, $allowed, true) && method_exists($this->ui, $method)) {
            return call_user_func_array(array($this->ui, $method), $arguments);
        }

        if (method_exists($this, $method) && '__call' !== $method) {
            return call_user_func_array(array($this, $method), $arguments);
        }

        throw new BadMethodCallException(sprintf('Method %s does not exist.', $method));
    }


    public function admin_page_url($page_slug, $args = array()) {
        return UCP_Admin_Router::admin_page_url($page_slug, $args);
    }

    public function tab_slug($tab) {
        return 'ultracache-pro';
    }

    public function advanced_only_tabs() {
        return UCP_Admin_Config::advanced_only_tabs();
    }

    public function visible_tabs($mode = 'advanced') {
        return UCP_Admin_Config::visible_tabs($mode);
    }

    public function is_advanced_mode($settings) {
        return !empty($settings['ui_mode']) && 'advanced' === $settings['ui_mode'];
    }

    public function menu() {
        add_menu_page(__('UltraCache Pro', 'ultracache-pro'), __('UltraCache', 'ultracache-pro'), 'manage_options', 'ultracache-pro', array($this, 'render'), 'dashicons-performance', 58);
        add_submenu_page('ultracache-pro', __('UltraCache Pro', 'ultracache-pro'), __('Overview', 'ultracache-pro'), 'manage_options', 'ultracache-pro', array($this, 'render'));
    }

    public function register_settings() {
        register_setting('ucp_settings_group', UCP_Options::OPTION_KEY, array('UCP_Admin_Sanitizer', 'sanitize'));
    }

    public function normalize_admin_routes() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (!$page || 'ultracache-pro' === $page) {
            return;
        }
        $map = $this->legacy_page_map();
        if (!isset($map[$page])) {
            return;
        }
        wp_safe_redirect($this->tab_url_public($map[$page]));
        exit;
    }

    public function enqueue($hook) {
        $allowed_hooks = array(
            'toplevel_page_ultracache-pro',
            'ultracache_page_ultracache-pro',
        );
        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        $style_handles = array(
            'ucp-admin-tokens'     => 'tokens.css',
            'ucp-admin-base'       => 'base.css',
            'ucp-admin-layout'     => 'layout.css',
            'ucp-admin-components' => 'components.css',
            'ucp-admin-forms'      => 'forms.css',
            'ucp-admin-pages'      => 'pages.css',
            'ucp-admin-responsive' => 'responsive.css',
        );
        $style_deps = array();
        foreach ($style_handles as $handle => $file) {
            wp_enqueue_style($handle, UCP_URL . 'assets/admin/css/' . $file, $style_deps, UCP_VERSION);
            $style_deps = array($handle);
        }

        $script_handles = array(
            'ucp-admin'                    => 'ucp-admin.js',
            'ucp-admin-conditional-fields' => 'conditional-fields.js',
            'ucp-admin-dirty-forms'        => 'dirty-forms.js',
            'ucp-admin-rules'              => 'rules.js',
            'ucp-admin-navigation'         => 'navigation.js',
            'ucp-admin-notices'            => 'notices.js',
            'ucp-admin-accordions'         => 'accordions.js',
        );
        $script_deps = array();
        foreach ($script_handles as $handle => $file) {
            wp_enqueue_script($handle, UCP_URL . 'assets/admin/js/' . $file, $script_deps, UCP_VERSION, true);
            $script_deps = array($handle);
        }

        wp_localize_script(
            'ucp-admin',
            'ucpAdmin',
            array(
                'ruleRow' => UCP_Admin_Rules::rule_template_html(),
                'allowedTabs' => array_keys(UCP_Admin_Config::tabs()),
                'messages' => array(
                    'addRule' => __('Rule added.', 'ultracache-pro'),
                    'leaveWithoutSave' => __('You have unsaved changes. Leave without saving?', 'ultracache-pro'),
                    'saved' => __('UltraCache: instellingen opgeslagen.', 'ultracache-pro'),
                ),
            )
        );
    }
    public function tab_meta($tab) {
        return UCP_Admin_Config::tab_meta($tab);
    }




    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }

        $settings = UCP_Options::get_all();
        $tab = $this->get_current_tab();
        $mode = $this->is_advanced_mode($settings) ? 'advanced' : 'simple';

        $health = UCP_Health::latest();
        $jobs_summary = UCP_Jobs::get_summary();
        $integrations = UCP_Integrations::detected();
        $presets = UCP_Presets::all();
        $rules = UCP_Rule_Engine::get_rules();
        $tab_meta = $this->tab_meta($tab);
        $is_settings_tab = UCP_Admin_Settings_Screen::is_settings_tab($tab);

        UCP_Admin_Shell::render_start($this, $mode, $tab, $tab_meta, $this->visible_tabs($mode));
        UCP_Admin_Shell::render_context($this, $mode, $tab, $settings, $integrations, $tab_meta);

        if ($is_settings_tab) {
            UCP_Admin_Submit::open_settings_form($tab);
        }

        echo '<div class="ucp-grid">';
            UCP_Admin_Settings_Screen::render(
                $this,
                $tab,
                $settings,
                array(
                    'presets'      => $presets,
                    'integrations' => $integrations,
                    'health'       => $health,
                    'jobs_summary' => $jobs_summary,
                    'rules'        => $rules,
                )
            );
        echo '</div>';

        if ($is_settings_tab) {
            UCP_Admin_Submit::render_submit_row();
            UCP_Admin_Submit::close_settings_form();
        }

        UCP_Admin_Shell::render_end($this, $tab);
    }

    protected function tabs() {
        return UCP_Admin_Config::tabs();
    }

    public function export_settings() {
        $this->actions->export_settings();
    }

    public function import_settings() {
        $this->actions->import_settings();
    }


    public function apply_easy_mode() {
        $this->actions->apply_easy_mode();
    }

    public function apply_preset() {
        $this->actions->apply_preset();
    }

    public function complete_onboarding() {
        $this->actions->complete_onboarding();
    }

    public function run_health_check() {
        $this->actions->run_health_check();
    }

    public function plugin_links($links) {
        return $this->actions->plugin_links($links);
    }

















}
