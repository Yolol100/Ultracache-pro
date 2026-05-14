<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Onboarding {
public static function render_onboarding_banner($admin, $settings, $integrations) {
    $step = $admin->current_onboarding_step();
    $steps = $admin->get_onboarding_steps();
    UCP_Admin_View::template('tabs/onboarding.php', get_defined_vars());
}
}
