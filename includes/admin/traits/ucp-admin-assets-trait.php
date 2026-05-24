<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Assets_Trait {
    public function enqueue($hook) {
        if (!UCP_Admin_Router::is_plugin_hook_suffix($hook)) {
            return;
        }

        if (class_exists("UCP_Admin_React_App") && UCP_Admin_React_App::should_render()) {
            UCP_Admin_React_App::enqueue();
            return;
        }
        $tab = $this->get_current_tab();
        $tab_assets = UCP_Admin_Router::tab_asset_map();
        // The file preserves the previous core/tab cascade while removing the fragmented
        // CSS loading path.
        $tokens_file = UCP_PATH . 'assets/admin/css/ucp-admin-tokens.css';
        if (file_exists($tokens_file)) {
            wp_enqueue_style(
                'ucp-admin-tokens',
                UCP_URL . 'assets/admin/css/ucp-admin-tokens.css',
                array(),
                (string) filemtime($tokens_file)
            );
        }
        $design_system_file = UCP_PATH . 'assets/admin/css/ucp-admin-design-system.css';
        $design_system_ver  = file_exists($design_system_file) ? (string) filemtime($design_system_file) : UCP_VERSION;
        wp_enqueue_style(
            'ucp-admin-design-system',
            UCP_URL . 'assets/admin/css/ucp-admin-design-system.css',
            array('ucp-admin-tokens'),
            $design_system_ver
        );

        // Register a virtual base handle for localized admin compatibility data. The previous admin.js
        // file only contained a comment; functional code lives in core/tab scripts below.
        wp_register_script('ucp-admin', false, array(), UCP_VERSION, true);
        wp_enqueue_script('ucp-admin');
        wp_localize_script(
            'ucp-admin',
            'ucpAdmin',
            array(
                'ruleRow' => UCP_Admin_Rules::rule_template_html(),
                'messages' => array(
                    'addRule' => __('Regel toegevoegd.', 'ultracache-pro'),
                    'leaveWithoutSave' => __('Je hebt iets aangepast. Wil je weggaan zonder op te slaan?', 'ultracache-pro'),
                    'saved' => __('UltraCache: instellingen opgeslagen.', 'ultracache-pro'),
                ),
            )
        );

        $core_scripts = array('context', 'notices', 'conditional-fields', 'forms', 'navigation', 'disclosures');
        $last_core_script = 'ucp-admin';
        foreach ($core_scripts as $core_script) {
            $handle = 'ucp-admin-core-' . $core_script;
            wp_enqueue_script(
                $handle,
                UCP_URL . 'assets/admin/js/core/' . $core_script . '.js',
                array($last_core_script),
                UCP_VERSION,
                true
            );
            $last_core_script = $handle;
        }

        if (isset($tab_assets[$tab])) {
            $tab_asset = $tab_assets[$tab];
            $tab_js_file = UCP_PATH . 'assets/admin/js/tabs/' . $tab_asset . '.js';
            if (file_exists($tab_js_file)) {
                wp_enqueue_script(
                    'ucp-admin-' . $tab_asset,
                    UCP_URL . 'assets/admin/js/tabs/' . $tab_asset . '.js',
                    array($last_core_script),
                    (string) filemtime($tab_js_file),
                    true
                );
            }
        }
    }
}
