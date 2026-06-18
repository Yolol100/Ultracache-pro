<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_React_App {
    const SCRIPT_HANDLE = 'ucp-react-admin-app';
    const STYLE_HANDLE  = 'ucp-react-admin-app';

    public static function enabled() {
        return true;
    }

    /**
     * Prefer the `.min` variant of an asset when SCRIPT_DEBUG is off and the
     * file exists on disk; otherwise fall back to the unminified source.
     *
     * Thin wrapper kept for backwards compatibility with the class's own
     * call sites; the implementation lives in UCP_Helpers::asset_path().
     */
    protected static function asset_path($relative) {
        return UCP_Helpers::asset_path($relative);
    }

    /**
     * Return modular admin stylesheet assets in load order.
     *
     * The legacy monolithic CSS remains as a fallback for custom/private builds,
     * but release builds can load smaller sectioned files while preserving the
     * same cascade order through chained style dependencies.
     *
     * @return array<int,string>
     */
    protected static function style_asset_relatives() {
        $modules = glob(UCP_PATH . 'assets/admin/react/css/modules/ucp-react-admin-*.css');
        if (!empty($modules) && is_array($modules)) {
            sort($modules, SORT_NATURAL);
            $assets = array();
            $seen = array();
            foreach ($modules as $module) {
                $relative = str_replace(wp_normalize_path(UCP_PATH), '', wp_normalize_path((string) $module));
                $source_relative = preg_replace('/\.min\.css$/', '.css', (string) $relative);
                if (!is_string($source_relative) || '' === $source_relative || isset($seen[$source_relative])) {
                    continue;
                }
                $seen[$source_relative] = true;
                $assets[] = self::asset_path($source_relative);
            }
            if (!empty($assets)) {
                return apply_filters('ucp_react_admin_style_assets', $assets);
            }
        }

        return apply_filters('ucp_react_admin_style_assets', array(self::asset_path('assets/admin/react/css/ucp-react-admin.css')));
    }

    public static function should_render() {
        $script_path = UCP_PATH . self::asset_path('assets/admin/react/js/app/ucp-react-admin.js');
        $style_assets = self::style_asset_relatives();
        $style_path  = !empty($style_assets[0]) ? UCP_PATH . $style_assets[0] : '';

        return self::enabled() && file_exists($script_path) && file_exists($style_path);
    }

    public static function enqueue() {
        $script_rel  = self::asset_path('assets/admin/react/js/app/ucp-react-admin.js');
        $style_rels  = self::style_asset_relatives();
        $tokens_rel  = self::asset_path('assets/admin/css/ucp-admin-tokens.css');
        $script_path = UCP_PATH . $script_rel;
        $tokens_path = UCP_PATH . $tokens_rel;
        $script_ver  = file_exists($script_path) ? (string) filemtime($script_path) : UCP_VERSION;
        $tokens_ver  = file_exists($tokens_path) ? (string) filemtime($tokens_path) : UCP_VERSION;

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            UCP_URL . $script_rel,
            array('wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-a11y'),
            $script_ver,
            true
        );

        // Shared design tokens — single source of truth for legacy + React admin.
        if (file_exists($tokens_path)) {
            wp_enqueue_style(
                'ucp-admin-tokens',
                UCP_URL . $tokens_rel,
                array(),
                $tokens_ver
            );
        }

        $style_dependency = 'ucp-admin-tokens';
        $style_count = count($style_rels);
        foreach ($style_rels as $index => $style_rel) {
            $handle = ($index === $style_count - 1) ? self::STYLE_HANDLE : self::STYLE_HANDLE . '-' . ($index + 1);
            $style_path = UCP_PATH . $style_rel;
            wp_enqueue_style(
                $handle,
                UCP_URL . $style_rel,
                array('wp-components', $style_dependency),
                file_exists($style_path) ? (string) filemtime($style_path) : UCP_VERSION
            );
            $style_dependency = $handle;
        }

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::SCRIPT_HANDLE, 'ultracache-pro', UCP_PATH . 'languages');
        }

        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            'window.UCP_ADMIN_CONFIG = ' . wp_json_encode(array(
                'restUrl'    => esc_url_raw(rest_url('ultracache-pro/v1/')),
                'homeUrl'    => esc_url_raw(home_url('/')),
                'nonce'      => wp_create_nonce('wp_rest'),
                'version'    => UCP_VERSION,
                'pluginName' => __('UltraCache Pro', 'ultracache-pro'),
                'caps'       => array('manageOptions' => current_user_can('manage_options')),
                'logDownloadUrl' => class_exists('UCP_Log_Package') ? UCP_Log_Package::download_url() : '',
                'managedSettingKeys' => class_exists('UCP_Options') && method_exists('UCP_Options', 'automatic_managed_keys') ? UCP_Options::automatic_managed_keys() : array(),
            )) . ';',
            'before'
        );
    }

    public static function render_root() {
        echo '<div class="wrap ucp-react-admin-wrap"><main id="ucp-admin-root" class="ucp-admin-app" role="main"></main></div>';
    }
}
