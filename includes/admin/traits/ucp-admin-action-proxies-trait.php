<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Action_Proxies_Trait {
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
