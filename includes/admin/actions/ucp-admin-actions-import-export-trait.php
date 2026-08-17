<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- Admin actions verify capabilities/nonces before writes; read-only notice parameters are sanitized before display.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Actions_Import_Export_Trait {
    public function export_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_export_settings');
        $settings = UCP_Options::settings_for_export();
        $encoded = UCP_Helpers::safe_json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            wp_die(esc_html__('De instellingen konden niet veilig als JSON worden opgebouwd.', 'ultracache-pro'), '', array('response' => 500));
        }
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Type: application/json; charset=utf-8');
        header('X-Download-Options: noopen');
        header('Content-Disposition: attachment; filename="ultracache-settings.json"');
        echo $encoded; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated JSON download body.
        exit;
    }

    public function download_support_report() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_download_support_report');
        $report = class_exists('UCP_Support_Report') ? UCP_Support_Report::generate() : array();
        $encoded = UCP_Helpers::safe_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            wp_die(esc_html__('Het supportrapport kon niet veilig als JSON worden opgebouwd.', 'ultracache-pro'), '', array('response' => 500));
        }
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Type: application/json; charset=utf-8');
        header('X-Download-Options: noopen');
        header('Content-Disposition: attachment; filename="ultracache-support-report.json"');
        echo $encoded; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated JSON download body.
        exit;
    }

    public function import_settings() {
        UCP_Helpers::require_post_admin_action('ucp_import_settings');
        $confirmed_backup = absint(UCP_Helpers::request_scalar('ucp_import_confirm_backup', '0', 8));
        if (1 !== $confirmed_backup) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        if (empty($_FILES['ucp_import_file']['tmp_name'])) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $file = isset($_FILES['ucp_import_file']) && is_array($_FILES['ucp_import_file']) ? $_FILES['ucp_import_file'] : array();
        if (!isset($file['error']) || !is_scalar($file['error']) || UPLOAD_ERR_OK !== (int) $file['error']) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        if (empty($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $filename = isset($file['name']) && is_scalar($file['name']) && '' !== (string) $file['name'] ? sanitize_file_name((string) $file['name']) : 'ultracache-settings.json';
        if ('' === $filename || !preg_match('/\.json$/i', $filename)) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $checked = wp_check_filetype_and_ext($file['tmp_name'], $filename, array(
            'json' => 'application/json',
        ));
        if ('json' !== (isset($checked['ext']) ? (string) $checked['ext'] : '')) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        if (isset($file['size']) && (!is_scalar($file['size']) || (int) $file['size'] > 256 * 1024)) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        if (!is_readable($file['tmp_name'])) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $raw = UCP_Helpers::read_file($file['tmp_name'], 256 * 1024 + 1);
        if (!is_string($raw) || '' === trim($raw) || strlen($raw) > 256 * 1024) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $decoded = UCP_Helpers::safe_json_decode_array((string) $raw);
        if (empty($decoded)) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $decoded = UCP_Options::validate_import_payload($decoded);
        $imported = false;
        if (is_array($decoded) && !empty($decoded)) {
            $imported = (bool) UCP_Options::update($this->admin->sanitize($decoded));
            if ($imported) {
                UCP_Logger::log('info', 'admin', 'settings_imported', __('Instellingen zijn geïmporteerd uit JSON.', 'ultracache-pro'), array('keys' => array_keys($decoded)));
            }
        }
        wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => $imported ? 1 : 0)));
        exit;
    }


    public function create_settings_snapshot() {
        UCP_Helpers::require_post_admin_action('ucp_create_settings_snapshot');
        $snapshot_id = UCP_Options::create_settings_snapshot(UCP_Options::get_all(), 'manual');
        wp_safe_redirect($this->admin->tab_url_public('tools', array('snapshot' => $snapshot_id ? 1 : 0)));
        exit;
    }

    public function restore_settings_snapshot() {
        UCP_Helpers::require_post_admin_action('ucp_restore_settings_snapshot');
        $snapshot_id = sanitize_text_field($this->admin_action_scalar('snapshot'));
        $restored = UCP_Options::restore_settings_snapshot($snapshot_id);
        wp_safe_redirect($this->admin->tab_url_public('tools', array('restore_snapshot' => $restored ? 1 : 0)));
        exit;
    }

    public function save_custom_preset() {
        UCP_Helpers::require_post_admin_action('ucp_save_custom_preset');
        $name = sanitize_text_field(UCP_Helpers::request_scalar('ucp_custom_preset_name', '', 100));
        $key = UCP_Presets::save_custom_preset($name, UCP_Options::get_all());
        wp_safe_redirect($this->admin->tab_url_public('overview', array('custom_preset' => $key ? 1 : 0)));
        exit;
    }

    public function delete_custom_preset() {
        UCP_Helpers::require_post_admin_action('ucp_delete_custom_preset');
        $preset = sanitize_key($this->admin_action_scalar('preset'));
        $deleted = UCP_Presets::delete_custom_preset($preset);
        wp_safe_redirect($this->admin->tab_url_public('overview', array('delete_custom_preset' => $deleted ? 1 : 0)));
        exit;
    }

}
