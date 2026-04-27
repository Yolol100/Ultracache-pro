<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Advanced_Rules {
    public static function render($settings, $rules, $integrations) {
        UCP_Admin_Assets_Controller::render_rules_only($settings, $rules, $integrations);
    }
}
