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

    protected static function settings_validation_notices() {
        if (class_exists('UCP_Admin_Sanitizer') && method_exists('UCP_Admin_Sanitizer', 'get_last_validation_notices')) {
            return UCP_Admin_Sanitizer::get_last_validation_notices();
        }

        return array();
    }

    protected static function accepted_setting_keys() {
        $keys = array_keys(UCP_Options::defaults());
        if (class_exists('UCP_Admin_Sanitizer') && method_exists('UCP_Admin_Sanitizer', 'virtual_control_keys')) {
            $keys = array_merge($keys, UCP_Admin_Sanitizer::virtual_control_keys());
        }
        return array_fill_keys(array_values(array_unique(array_map('sanitize_key', $keys))), true);
    }

    protected static function filter_settings_input($settings) {
        $settings = is_array($settings) ? $settings : array();
        $accepted_map = self::accepted_setting_keys();
        $accepted = array();
        $ignored = array();

        foreach ($settings as $key => $value) {
            $safe_key = sanitize_key((string) $key);
            if ('' !== $safe_key && isset($accepted_map[$safe_key])) {
                $accepted[$safe_key] = $value;
                continue;
            }
            if ('' !== $safe_key) {
                $ignored[] = $safe_key;
            }
        }

        return array(
            'accepted' => $accepted,
            'ignored' => array_values(array_unique($ignored)),
        );
    }

    protected static function ignored_setting_notices($keys) {
        $notices = array();
        foreach (array_slice(self::sanitized_setting_keys($keys), 0, 20) as $key) {
            $notices[] = array(
                'field' => $key,
                'message' => __('Onbekende instelling genegeerd.', 'ultracache-pro'),
            );
        }
        return $notices;
    }

    protected static function sanitized_setting_keys($keys) {
        $safe = array();
        foreach ((array) $keys as $key) {
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }
            if ((class_exists('UCP_Options') && method_exists('UCP_Options', 'is_sensitive_key') && UCP_Options::is_sensitive_key($key)) || preg_match('/(?:secret|token|password|api[_-]?key|license|email)/i', $key)) {
                continue;
            }
            $safe[] = $key;
        }
        return array_values(array_unique($safe));
    }

    protected static function changed_setting_keys($before, $after) {
        $before = is_array($before) ? $before : array();
        $after = is_array($after) ? $after : array();
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $changed = array();
        foreach ($keys as $key) {
            $before_value = array_key_exists($key, $before) ? $before[$key] : null;
            $after_value = array_key_exists($key, $after) ? $after[$key] : null;
            if ($before_value !== $after_value) {
                $changed[] = $key;
            }
        }
        return self::sanitized_setting_keys($changed);
    }

    protected static function newest_snapshot_id($before_ids) {
        return UCP_Options::newest_snapshot_id($before_ids);
    }

    protected static function settings_snapshot_summaries() {
        $current = UCP_Options::get_all();
        $summaries = array();
        foreach (array_slice(UCP_Options::settings_snapshots(), 0, 5) as $snapshot) {
            if (!is_array($snapshot) || empty($snapshot['id'])) {
                continue;
            }
            $snapshot_settings = isset($snapshot['settings']) && is_array($snapshot['settings']) ? $snapshot['settings'] : array();
            $changed = self::changed_setting_keys($snapshot_settings, $current);
            $summaries[] = array(
                'id' => sanitize_text_field((string) $snapshot['id']),
                'createdAt' => sanitize_text_field((string) (isset($snapshot['created_at']) ? $snapshot['created_at'] : '')),
                'context' => sanitize_key((string) (isset($snapshot['context']) ? $snapshot['context'] : 'manual')),
                'changedKeys' => array_slice($changed, 0, 20),
                'changedCount' => count($changed),
            );
        }
        return $summaries;
    }

    protected static function log_settings_exception($event, $exception, $keys = array()) {
        if (!class_exists('UCP_Logger')) {
            return;
        }

        UCP_Logger::log('error', 'admin', sanitize_key($event), __('REST-instellingenactie is mislukt.', 'ultracache-pro'), array(
            'exception' => $exception instanceof Throwable ? get_class($exception) : '',
            'exception_code' => $exception instanceof Throwable ? (string) $exception->getCode() : '',
            'keys' => self::sanitized_setting_keys($keys),
        ));
    }

    protected static function settings_success_message($default_message, $warnings) {
        if (empty($warnings)) {
            return $default_message;
        }

        return sprintf(
            /* translators: %s: default success message. */
            __('%s Eén of meer waarden zijn aangepast of genegeerd. Bekijk de waarschuwingen voor details.', 'ultracache-pro'),
            $default_message
        );
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
        $filtered = self::filter_settings_input($params);
        $accepted = $filtered['accepted'];
        $ignored = $filtered['ignored'];
        if (empty($accepted) && !empty($params)) {
            return new WP_Error('ucp_no_valid_settings', __('Geen geldige UltraCache instellingen ontvangen.', 'ultracache-pro'), array('status' => 400, 'ignoredKeys' => self::sanitized_setting_keys($ignored)));
        }
        try {
            $current = UCP_Options::get_all();
            $snapshot_ids_before = wp_list_pluck(UCP_Options::settings_snapshots(), 'id');
            $merged  = array_merge($current, $accepted);
            $clean    = self::sanitize_settings_payload($merged);
            $warnings = array_merge(self::settings_validation_notices(), self::ignored_setting_notices($ignored));
            if (!UCP_Options::update($clean)) {
                return self::action_error('ucp_settings_persistence_failed', __('Instellingen konden niet blijvend worden opgeslagen.', 'ultracache-pro'), 500);
            }
            if (class_exists('UCP_Helpers')) {
                UCP_Helpers::invalidate_cache_dirs_check();
            }

            $saved_settings = UCP_Options::get_all();
            $changed_keys = self::changed_setting_keys($current, $saved_settings);
            $accepted_keys = self::sanitized_setting_keys(array_keys($accepted));
            $automatic_keys = array_values(array_diff($changed_keys, $accepted_keys));
            $snapshot_id = self::newest_snapshot_id($snapshot_ids_before);

            return rest_ensure_response(array(
                'success'   => true,
                'message'   => self::settings_success_message(__('Instellingen opgeslagen.', 'ultracache-pro'), $warnings),
                'warnings'  => $warnings,
                'warningCount' => count($warnings),
                'updatedKeys' => $accepted_keys,
                'changedKeys' => $changed_keys,
                'automaticAdjustedKeys' => $automatic_keys,
                'snapshotId' => $snapshot_id,
                'snapshotSummaries' => self::settings_snapshot_summaries(),
                'ignoredKeys' => self::sanitized_setting_keys($ignored),
                'settings'  => UCP_Options::redact_sensitive_settings($saved_settings),
                'status'    => self::build_status(),
                'timestamp' => time(),
            ));
        } catch (Throwable $e) {
            self::log_settings_exception('settings_update_failed_rest', $e, array_keys($params));
            return self::action_error('ucp_settings_update_failed', __('Instellingen konden niet worden opgeslagen. Controleer de serverlog voor details.', 'ultracache-pro'), 500);
        }
    }

    public static function export_settings() {
        $settings = UCP_Options::settings_for_export();

        return rest_ensure_response(array(
            'success'   => true,
            'settings'  => $settings,
            'meta'      => array(
                'schema' => 'ultracache-settings-export-v1',
                'pluginVersion' => defined('UCP_VERSION') ? UCP_VERSION : '',
                'exportedAt' => gmdate('c'),
            ),
            'timestamp' => time(),
        ));
    }

    public static function import_settings(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        $payload = is_array($payload) ? $payload : array();
        $confirmed_backup = self::is_explicit_confirmation(isset($payload['confirmBackup']) ? $payload['confirmBackup'] : null) || self::is_explicit_confirmation(isset($payload['confirmed_backup']) ? $payload['confirmed_backup'] : null) || self::is_explicit_confirmation(isset($payload['ucp_import_confirm_backup']) ? $payload['ucp_import_confirm_backup'] : null);
        if (!$confirmed_backup) {
            return new WP_Error('ucp_import_backup_not_confirmed', __('Bevestig eerst dat je een recente export of database-back-up hebt voordat instellingen worden geïmporteerd.', 'ultracache-pro'), array('status' => 400));
        }
        $settings = isset($payload['settings']) ? $payload['settings'] : $payload;
        if (!is_array($settings)) {
            return new WP_Error('ucp_invalid_import', __('Ongeldige import ontvangen.', 'ultracache-pro'), array('status' => 400));
        }
        if (isset($settings['settings']) && is_array($settings['settings'])) {
            $settings = $settings['settings'];
        }
        if (!is_array($settings)) {
            return new WP_Error('ucp_invalid_import', __('Ongeldige import ontvangen.', 'ultracache-pro'), array('status' => 400));
        }
        unset($settings['confirmBackup'], $settings['confirmed_backup'], $settings['ucp_import_confirm_backup']);
        $filtered = self::filter_settings_input($settings);
        $ignored = $filtered['ignored'];
        $settings = $filtered['accepted'];

        if (method_exists('UCP_Options', 'validate_import_payload')) {
            $settings = UCP_Options::validate_import_payload($settings);
        }

        if (!is_array($settings) || empty($settings)) {
            return new WP_Error('ucp_empty_import', __('Geen geldige UltraCache instellingen gevonden.', 'ultracache-pro'), array('status' => 400));
        }

        try {
            $clean    = self::sanitize_settings_payload(array_merge(UCP_Options::get_all(), $settings));
            $warnings = array_merge(self::settings_validation_notices(), self::ignored_setting_notices($ignored));
            if (!UCP_Options::update($clean)) {
                return self::action_error('ucp_settings_import_persistence_failed', __('Instellingen konden niet blijvend worden geïmporteerd.', 'ultracache-pro'), 500);
            }
            if (class_exists('UCP_Helpers')) {
                UCP_Helpers::invalidate_cache_dirs_check();
            }

            if (class_exists('UCP_Logger')) {
                UCP_Logger::log('info', 'admin', 'settings_imported_rest', __('Instellingen zijn geïmporteerd via het React-beheer.', 'ultracache-pro'), array('keys' => array_keys($settings)));
            }

            return rest_ensure_response(array(
                'success'   => true,
                'message'   => self::settings_success_message(__('Instellingen geïmporteerd.', 'ultracache-pro'), $warnings),
                'warnings'  => $warnings,
                'warningCount' => count($warnings),
                'importedKeys' => self::sanitized_setting_keys(array_keys($settings)),
                'ignoredKeys' => self::sanitized_setting_keys($ignored),
                'settings'  => UCP_Options::redact_sensitive_settings(UCP_Options::get_all()),
                'status'    => self::build_status(),
                'timestamp' => time(),
            ));
        } catch (Throwable $e) {
            self::log_settings_exception('settings_import_failed_rest', $e, is_array($settings) ? array_keys($settings) : array());
            return self::action_error('ucp_settings_import_failed', __('Instellingen konden niet worden geïmporteerd. Controleer de serverlog voor details.', 'ultracache-pro'), 500);
        }
    }


    public static function settings_snapshots() {
        return rest_ensure_response(array(
            'success' => true,
            'snapshots' => UCP_Options::settings_snapshots(),
            'summaries' => self::settings_snapshot_summaries(),
            'timestamp' => time(),
        ));
    }

    public static function create_settings_snapshot(WP_REST_Request $request) {
        $id = UCP_Options::create_settings_snapshot(UCP_Options::get_all(), 'manual_rest');
        if (!$id) {
            return self::action_error('ucp_snapshot_persistence_failed', __('Snapshot kon niet worden opgeslagen.', 'ultracache-pro'), 500);
        }
        return rest_ensure_response(array(
            'success' => true,
            'snapshot_id' => $id,
            'snapshots' => UCP_Options::settings_snapshots(),
            'summaries' => self::settings_snapshot_summaries(),
            'timestamp' => time(),
        ));
    }

    public static function restore_settings_snapshot(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $params = is_array($params) ? $params : $request->get_params();
        $id = isset($params['id']) && is_scalar($params['id']) ? sanitize_text_field((string) $params['id']) : '';
        $exists = false;
        foreach (UCP_Options::settings_snapshots() as $snapshot) {
            if (isset($snapshot['id']) && is_scalar($snapshot['id']) && '' !== (string) $snapshot['id'] && hash_equals((string) $snapshot['id'], $id)) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            return new WP_Error('ucp_snapshot_not_found', __('Snapshot niet gevonden.', 'ultracache-pro'), array('status' => 404));
        }
        if (!UCP_Options::restore_settings_snapshot($id)) {
            return self::action_error('ucp_snapshot_restore_failed', __('Snapshot kon niet veilig worden teruggezet.', 'ultracache-pro'), 500);
        }
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Snapshot teruggezet.', 'ultracache-pro'),
            'settings' => UCP_Options::redact_sensitive_settings(UCP_Options::get_all()),
            'status' => self::build_status(),
            'snapshots' => UCP_Options::settings_snapshots(),
            'summaries' => self::settings_snapshot_summaries(),
            'restoredSnapshotId' => $id,
            'timestamp' => time(),
        ));
    }

    public static function save_custom_preset(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $params = is_array($params) ? $params : $request->get_params();
        $name = isset($params['name']) && is_scalar($params['name']) ? sanitize_text_field((string) $params['name']) : '';
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
