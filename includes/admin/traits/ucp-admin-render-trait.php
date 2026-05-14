<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Render_Trait {
    public function tab_meta($tab) {
        return UCP_Admin_Config::tab_meta($tab);
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }

        if (class_exists('UCP_Admin_React_App') && UCP_Admin_React_App::should_render()) {
            UCP_Admin_React_App::render_root();
            return;
        }

        $settings = UCP_Options::get_all();
        $tab = $this->get_current_tab();
        $mode = !empty($settings['ui_mode']) && 'advanced' === $settings['ui_mode'] ? 'advanced' : 'simple';
        $visible_tabs = $this->visible_tabs($mode);

        $health = UCP_Health::latest();
        $jobs_summary = UCP_Jobs::get_summary();
        $integrations = UCP_Integrations::detected();
        $presets = UCP_Presets::all();
        $rules = UCP_Rule_Engine::get_rules();

        $tab_meta = $this->tab_meta($tab);
        $is_settings_tab = UCP_Admin_Settings_Screen::is_settings_tab($tab);

        UCP_Admin_Shell::render_start($this, $mode, $tab, $tab_meta, $visible_tabs);
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
}
