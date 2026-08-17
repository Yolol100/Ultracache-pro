<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Presets {

    public static function custom_option_key() {
        return 'ucp_custom_presets';
    }

    public static function custom_presets() {
        $stored = get_option(self::custom_option_key(), array());
        if (!is_array($stored)) {
            return array();
        }
        $custom = array();
        foreach ($stored as $key => $preset) {
            if (empty($preset['overrides']) || !is_array($preset['overrides'])) {
                continue;
            }
            $custom[sanitize_key($key)] = array(
                'label' => !empty($preset['label']) ? sanitize_text_field($preset['label']) : __('Maatwerk preset', 'ultracache-pro'),
                'description' => !empty($preset['description']) ? sanitize_text_field($preset['description']) : __('Opgeslagen vanuit de huidige UltraCache-instellingen.', 'ultracache-pro'),
                'overrides' => UCP_Options::validate_import_payload($preset['overrides']),
                'custom' => true,
            );
        }
        return $custom;
    }

    public static function save_custom_preset($name, $settings = null) {
        if (!is_scalar($name) && null !== $name) {
            $name = '';
        }
        $name = trim(sanitize_text_field((string) $name));
        if ('' === $name) {
            return false;
        }
        $key = sanitize_key('custom_' . $name);
        $stored = get_option(self::custom_option_key(), array());
        $stored = is_array($stored) ? $stored : array();
        $stored[$key] = array(
            'label' => $name,
            'description' => __('Opgeslagen maatwerkprofiel.', 'ultracache-pro'),
            'overrides' => UCP_Options::settings_for_export(is_array($settings) ? $settings : UCP_Options::get_all()),
            'created_at' => gmdate('c'),
        );
        $stored = array_slice($stored, -10, 10, true);
        if (!self::persist_custom_presets($stored)) {
            return false;
        }
        return $key;
    }

    public static function delete_custom_preset($key) {
        $key = sanitize_key((string) $key);
        $stored = get_option(self::custom_option_key(), array());
        if (!is_array($stored) || empty($stored[$key])) {
            return false;
        }
        unset($stored[$key]);
        return self::persist_custom_presets($stored);
    }

    public static function pagespeed_auto_overrides() {
        return UCP_Preset_Registry::pagespeed_auto_overrides();
    }

    public static function all() {
        return array_merge(UCP_Preset_Registry::built_in_presets(), self::custom_presets());
    }

    public static function apply($preset_key) {
        if (!is_scalar($preset_key) && null !== $preset_key) {
            $preset_key = '';
        }
        $presets = self::all();
        if (empty($presets[$preset_key])) {
            return false;
        }
        $settings = UCP_Options::get_all();
        $overrides = $presets[$preset_key]['overrides'];
        $settings = array_merge($settings, $overrides);
        if (array_key_exists('ui_mode', $overrides) && !array_key_exists('show_advanced_options', $overrides)) {
            $settings['show_advanced_options'] = 'advanced' === (string) $overrides['ui_mode'] ? 1 : 0;
        }
        $settings['active_preset'] = $preset_key;
        if (!UCP_Options::update($settings)) {
            return false;
        }
        UCP_Logger::log('info', 'presets', 'preset_applied',  __('Preset toegepast.', 'ultracache-pro'), array('preset' => $preset_key));
        return true;
    }

    /**
     * Persist custom presets and distinguish unchanged data from a failed write.
     *
     * @param array<string,array> $stored Preset map.
     * @return bool
     */
    private static function persist_custom_presets($stored) {
        $key = self::custom_option_key();
        if (update_option($key, $stored, false)) {
            return true;
        }
        return get_option($key, null) === $stored;
    }
}
