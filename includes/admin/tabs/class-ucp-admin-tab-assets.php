<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Assets {
    public static function render($settings, $rules, $integrations) {
        UCP_Admin_Assets_Controller::render($settings, $rules, $integrations);
    }
}
