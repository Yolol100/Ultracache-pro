<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Routing_Trait {
    public function get_current_tab() {
        return UCP_Admin_Router::current_tab();
    }

    public function compatibility_page_map() {
        return UCP_Admin_Router::compatibility_page_map();
    }

    public function get_current_page_slug_public() {
        return UCP_Admin_Router::page_slug();
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

    public function admin_page_url($page_slug, $args = array()) {
        return UCP_Admin_Router::admin_page_url($page_slug, $args);
    }

    public function tab_slug($tab) {
        return $this->get_current_page_slug();
    }

    public function advanced_only_tabs() {
        return UCP_Admin_Config::advanced_only_tabs();
    }

    public function visible_tabs($mode = 'simple') {
        return UCP_Admin_Config::visible_tabs($mode);
    }

    public function is_advanced_mode($settings) {
        return !empty($settings['ui_mode']) && 'advanced' === $settings['ui_mode'];
    }

    public function menu() {
        $page_slug = $this->get_current_page_slug();
        add_menu_page(__('UltraCache Pro', 'ultracache-pro'), __('UltraCache', 'ultracache-pro'), 'manage_options', $page_slug, array($this, 'render'), 'dashicons-performance', 58);
        add_submenu_page($page_slug, __('UltraCache Pro', 'ultracache-pro'), __('Overzicht', 'ultracache-pro'), 'manage_options', $page_slug, array($this, 'render'));
    }

    public function register_settings() {
        register_setting('ucp_settings_group', UCP_Options::OPTION_KEY, array('UCP_Admin_Sanitizer', 'sanitize'));
    }

    public function normalize_admin_routes() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        $page = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (!$page || $this->get_current_page_slug() === $page) {
            return;
        }
        $map = $this->compatibility_page_map();
        if (!isset($map[$page])) {
            return;
        }
        wp_safe_redirect($this->tab_url_public($map[$page]));
        exit;
    }
}
