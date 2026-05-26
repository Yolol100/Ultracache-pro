<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Assets_Trait {
    /**
     * Return the relative asset path with `.min` inserted when a production
     * variant exists and SCRIPT_DEBUG is not active. Falls back to the
     * unminified path otherwise.
     *
     * Thin wrapper kept for backwards compatibility with the trait's call
     * sites; the implementation lives in UCP_Helpers::asset_path().
     */
    protected function ucp_asset_path($relative) {
        return UCP_Helpers::asset_path($relative);
    }

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

        $tokens_rel = $this->ucp_asset_path('assets/admin/css/ucp-admin-tokens.css');
        $tokens_file = UCP_PATH . $tokens_rel;
        if (file_exists($tokens_file)) {
            wp_enqueue_style(
                'ucp-admin-tokens',
                UCP_URL . $tokens_rel,
                array(),
                (string) filemtime($tokens_file)
            );
        }
        $design_rel = $this->ucp_asset_path('assets/admin/css/ucp-admin-design-system.css');
        $design_system_file = UCP_PATH . $design_rel;
        $design_system_ver  = file_exists($design_system_file) ? (string) filemtime($design_system_file) : UCP_VERSION;
        wp_enqueue_style(
            'ucp-admin-design-system',
            UCP_URL . $design_rel,
            array('ucp-admin-tokens'),
            $design_system_ver
        );

        // Virtual base handle for localized admin compatibility data; functional
        // code lives in the core/tab scripts below.
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
            $core_rel = $this->ucp_asset_path('assets/admin/js/core/' . $core_script . '.js');
            wp_enqueue_script(
                $handle,
                UCP_URL . $core_rel,
                array($last_core_script),
                UCP_VERSION,
                true
            );
            $last_core_script = $handle;
        }

        if (isset($tab_assets[$tab])) {
            $tab_asset = $tab_assets[$tab];
            $tab_js_rel = $this->ucp_asset_path('assets/admin/js/tabs/' . $tab_asset . '.js');
            $tab_js_file = UCP_PATH . $tab_js_rel;
            if (file_exists($tab_js_file)) {
                wp_enqueue_script(
                    'ucp-admin-' . $tab_asset,
                    UCP_URL . $tab_js_rel,
                    array($last_core_script),
                    (string) filemtime($tab_js_file),
                    true
                );
            }
        }
    }
}
