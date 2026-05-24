<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Quality_Suite_Actions_Trait {
    public static function rest_enable_debug_mode() {
        $settings = UCP_Options::get_all();
        $settings['enable_logs'] = 1;
        $settings['enable_diagnostics'] = 1;
        $settings['enable_admin_queue_runner'] = 1;
        $settings['enable_health_checks'] = 1;
        $settings['enable_runtime_debug_headers'] = 1;
        UCP_Options::update($settings);
        update_option(self::DEBUG_UNTIL_OPTION, time() + (30 * MINUTE_IN_SECONDS), false);
        UCP_Logger::log('notice', 'diagnostics', 'debug_mode_enabled', 'Debug/testmodus 30 minuten ingeschakeld.', array('until' => gmdate('c', time() + (30 * MINUTE_IN_SECONDS))));
        return self::action_success(__('Debug/testmodus is 30 minuten ingeschakeld.', 'ultracache-pro'));
    }

    public static function rest_repair_cache_files() {
        $result = class_exists('UCP_Helpers') ? UCP_Helpers::maybe_install_own_advanced_cache_automatically() : array('installed' => false);
        return self::action_success(__('WP_CACHE en drop-in herstelactie uitgevoerd.', 'ultracache-pro'), array('result' => $result));
    }

    protected static function apply_preset_and_reply($preset) {
        $ok = class_exists('UCP_Presets') ? UCP_Presets::apply($preset) : false;
        if (!$ok) {
            return new WP_Error('ucp_preset_failed', __('Preset kon niet worden toegepast.', 'ultracache-pro'), array('status' => 400));
        }
        return self::action_success(__('Preset toegepast.', 'ultracache-pro'), array('preset' => $preset));
    }

    public static function rest_preset_woocommerce_safe() { return self::apply_preset_and_reply('woocommerce'); }

    public static function rest_preset_elementor_safe() { return self::apply_preset_and_reply('builder'); }

    public static function rest_preset_debug_test() { return self::rest_enable_debug_mode(); }

    public static function rest_preset_aggressive() { return self::apply_preset_and_reply('aggressive'); }
}
