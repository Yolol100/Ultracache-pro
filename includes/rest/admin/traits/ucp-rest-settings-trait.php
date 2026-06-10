<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_REST_Settings_Trait {
    public static function get_settings() {
        return rest_ensure_response(array(
            'success'   => true,
            'settings'  => UCP_Options::redact_sensitive_settings(UCP_Options::get_all()),
            'defaults'  => UCP_Options::redact_sensitive_settings(UCP_Options::defaults()),
            'timestamp' => time(),
        ));
    }

    protected static function sanitize_settings_payload($settings) {
        $settings = is_array($settings) ? $settings : array();
        if (class_exists('UCP_Admin_Sanitizer') && method_exists('UCP_Admin_Sanitizer', 'sanitize')) {
            return UCP_Admin_Sanitizer::sanitize($settings);
        }

        $allowed = array_intersect_key($settings, UCP_Options::defaults());
        return UCP_Options::normalize($allowed, UCP_Options::get_all());
    }

    public static function update_settings(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_params();
        }
        if (!is_array($params)) {
            return new WP_Error('ucp_invalid_settings', __('Ongeldige instellingen ontvangen.', 'ultracache-pro'), array('status' => 400));
        }
        unset($params['_locale'], $params['_wpnonce']);
        try {
            $current = UCP_Options::get_all();
            $merged  = array_merge($current, $params);
            $clean   = self::sanitize_settings_payload($merged);
            UCP_Options::update($clean);
            if (class_exists('UCP_Helpers')) {
                UCP_Helpers::invalidate_cache_dirs_check();
            }

            return rest_ensure_response(array(
                'success'   => true,
                'message'   => __('Instellingen opgeslagen.', 'ultracache-pro'),
                'settings'  => UCP_Options::redact_sensitive_settings(UCP_Options::get_all()),
                'status'    => self::build_status(),
                'timestamp' => time(),
            ));
        } catch (Throwable $e) {
            return self::action_error('ucp_settings_update_failed', __('Instellingen konden niet worden opgeslagen. Controleer de serverlog voor details.', 'ultracache-pro'), 500);
        }
    }

    public static function export_settings() {
        $settings = UCP_Options::settings_for_export();

        return rest_ensure_response(array(
            'success'   => true,
            'settings'  => $settings,
            'timestamp' => time(),
        ));
    }

    public static function import_settings(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        $payload = is_array($payload) ? $payload : array();
        $confirmed_backup = !empty($payload['confirmBackup']) || !empty($payload['confirmed_backup']) || !empty($payload['ucp_import_confirm_backup']);
        if (!$confirmed_backup) {
            return new WP_Error('ucp_import_backup_not_confirmed', __('Bevestig eerst dat je een recente export of database-back-up hebt voordat instellingen worden geïmporteerd.', 'ultracache-pro'), array('status' => 400));
        }
        $settings = isset($payload['settings']) ? $payload['settings'] : $payload;
        unset($settings['confirmBackup'], $settings['confirmed_backup'], $settings['ucp_import_confirm_backup']);

        if (!is_array($settings)) {
            return new WP_Error('ucp_invalid_import', __('Ongeldige import ontvangen.', 'ultracache-pro'), array('status' => 400));
        }

        if (method_exists('UCP_Options', 'validate_import_payload')) {
            $settings = UCP_Options::validate_import_payload($settings);
        }

        if (!is_array($settings) || empty($settings)) {
            return new WP_Error('ucp_empty_import', __('Geen geldige UltraCache instellingen gevonden.', 'ultracache-pro'), array('status' => 400));
        }

        try {
            $clean = self::sanitize_settings_payload(array_merge(UCP_Options::get_all(), $settings));
            UCP_Options::update($clean);
            if (class_exists('UCP_Helpers')) {
                UCP_Helpers::invalidate_cache_dirs_check();
            }

            if (class_exists('UCP_Logger')) {
                UCP_Logger::log('info', 'admin', 'settings_imported_rest', 'Settings imported through React admin.', array('keys' => array_keys($settings)));
            }

            return rest_ensure_response(array(
                'success'   => true,
                'message'   => __('Instellingen geïmporteerd.', 'ultracache-pro'),
                'settings'  => UCP_Options::redact_sensitive_settings(UCP_Options::get_all()),
                'status'    => self::build_status(),
                'timestamp' => time(),
            ));
        } catch (Throwable $e) {
            return self::action_error('ucp_settings_import_failed', __('Instellingen konden niet worden geïmporteerd. Controleer de serverlog voor details.', 'ultracache-pro'), 500);
        }
    }


    public static function settings_snapshots() {
        return rest_ensure_response(array(
            'success' => true,
            'snapshots' => UCP_Options::settings_snapshots(),
            'timestamp' => time(),
        ));
    }

    public static function create_settings_snapshot(WP_REST_Request $request) {
        $id = UCP_Options::create_settings_snapshot(UCP_Options::get_all(), 'manual_rest');
        return rest_ensure_response(array(
            'success' => (bool) $id,
            'snapshot_id' => $id,
            'snapshots' => UCP_Options::settings_snapshots(),
            'timestamp' => time(),
        ));
    }

    public static function restore_settings_snapshot(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $params = is_array($params) ? $params : $request->get_params();
        $id = isset($params['id']) ? sanitize_text_field($params['id']) : '';
        $restored = UCP_Options::restore_settings_snapshot($id);
        if (!$restored) {
            return new WP_Error('ucp_snapshot_not_found', __('Snapshot niet gevonden.', 'ultracache-pro'), array('status' => 404));
        }
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Snapshot teruggezet.', 'ultracache-pro'),
            'settings' => UCP_Options::redact_sensitive_settings(UCP_Options::get_all()),
            'status' => self::build_status(),
            'snapshots' => UCP_Options::settings_snapshots(),
            'timestamp' => time(),
        ));
    }

    public static function save_custom_preset(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $params = is_array($params) ? $params : $request->get_params();
        $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';
        $key = UCP_Presets::save_custom_preset($name, UCP_Options::get_all());
        if (!$key) {
            return new WP_Error('ucp_custom_preset_invalid', __('Geef een geldige naam op voor het maatwerkprofiel.', 'ultracache-pro'), array('status' => 400));
        }
        return rest_ensure_response(array(
            'success' => true,
            'preset' => $key,
            'presets' => UCP_Presets::custom_presets(),
            'timestamp' => time(),
        ));
    }

}
