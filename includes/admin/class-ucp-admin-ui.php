<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_UI {
    public function get_onboarding_steps() {
        return UCP_Admin_Metrics::get_onboarding_steps();
    }

    public function current_onboarding_step() {
        return UCP_Admin_Metrics::current_onboarding_step();
    }

    public function metric_card($label, $value, $description = '', $tone = 'default') {
        UCP_Admin_Metrics::metric_card($label, $value, $description, $tone);
    }

    public function chip($label, $is_positive = false) {
        UCP_Admin_Metrics::chip($label, $is_positive);
    }

    public function status_row($label, $is_ok, $description = '') {
        UCP_Admin_Metrics::status_row($label, $is_ok, $description);
    }

    public function checkbox($key, $label, $settings, $help = '') {
        UCP_Admin_Fields::checkbox($key, $label, $settings, $help);
    }

    public function text($key, $label, $settings, $help = '') {
        UCP_Admin_Fields::text($key, $label, $settings, $help);
    }

    public function secret($key, $label, $settings, $help = '') {
        UCP_Admin_Fields::secret($key, $label, $settings, $help);
    }

    public function number($key, $label, $settings, $min = 0, $max = 999999, $help = '') {
        UCP_Admin_Fields::number($key, $label, $settings, $min, $max, $help);
    }

    public function textarea($key, $label, $settings, $help = '') {
        UCP_Admin_Fields::textarea($key, $label, $settings, $help);
    }

    public function select($key, $label, $settings, $options, $help = '') {
        UCP_Admin_Fields::select($key, $label, $settings, $options, $help);
    }
}
