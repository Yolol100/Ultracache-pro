<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_React_App {
    const SCRIPT_HANDLE = 'ucp-react-admin-app';
    const STYLE_HANDLE  = 'ucp-react-admin-app';

    public static function enabled() {
        return (bool) apply_filters('ucp_use_react_admin', true);
    }

    public static function should_render() {
        $script_path = UCP_PATH . 'assets/admin/react/js/app/ucp-react-admin.js';
        $style_path  = UCP_PATH . 'assets/admin/react/css/ucp-react-admin.css';

        return self::enabled() && file_exists($script_path) && file_exists($style_path);
    }

    public static function enqueue() {
        $script_path = UCP_PATH . 'assets/admin/react/js/app/ucp-react-admin.js';
        $style_path  = UCP_PATH . 'assets/admin/react/css/ucp-react-admin.css';
        $tokens_path = UCP_PATH . 'assets/admin/css/ucp-admin-tokens.css';
        $script_ver  = file_exists($script_path) ? (string) filemtime($script_path) : UCP_VERSION;
        $style_ver   = file_exists($style_path) ? (string) filemtime($style_path) : UCP_VERSION;
        $tokens_ver  = file_exists($tokens_path) ? (string) filemtime($tokens_path) : UCP_VERSION;

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            UCP_URL . 'assets/admin/react/js/app/ucp-react-admin.js',
            array('wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-a11y'),
            $script_ver,
            true
        );

        // Shared design tokens — single source of truth for legacy + React admin.
        if (file_exists($tokens_path)) {
            wp_enqueue_style(
                'ucp-admin-tokens',
                UCP_URL . 'assets/admin/css/ucp-admin-tokens.css',
                array(),
                $tokens_ver
            );
        }

        wp_enqueue_style(
            self::STYLE_HANDLE,
            UCP_URL . 'assets/admin/react/css/ucp-react-admin.css',
            array('wp-components', 'ucp-admin-tokens'),
            $style_ver
        );

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
            )) . ';',
            'before'
        );
    }

    public static function render_root() {
        echo '<div class="wrap ucp-react-admin-wrap"><main id="ucp-admin-root" class="ucp-admin-app" role="main"></main></div>';
    }
}
