<?php
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
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=ultracache-settings.json');
        $settings = UCP_Options::settings_for_export();
        echo wp_json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function download_support_report() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_download_support_report');
        $report = class_exists('UCP_Support_Report') ? UCP_Support_Report::generate() : array();
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=ultracache-support-report.json');
        echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function import_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_import_settings');
        $confirmed_backup = isset($_POST['ucp_import_confirm_backup']) ? absint(wp_unslash($_POST['ucp_import_confirm_backup'])) : 0;
        if (1 !== $confirmed_backup) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        if (empty($_FILES['ucp_import_file']['tmp_name'])) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $file = wp_unslash($_FILES['ucp_import_file']);
        if (!isset($file['error']) || UPLOAD_ERR_OK !== (int) $file['error']) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        if (empty($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $filename = !empty($file['name']) ? sanitize_file_name($file['name']) : 'ultracache-settings.json';
        if ('' === $filename || !preg_match('/\.(json|txt)$/i', $filename)) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $checked = wp_check_filetype_and_ext($file['tmp_name'], $filename, array(
            'json' => 'application/json',
            'txt'  => 'text/plain',
        ));
        if (empty($checked['ext'])) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        if (!empty($file['size']) && (int) $file['size'] > 256 * 1024) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        if (!is_readable($file['tmp_name'])) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $raw = UCP_Helpers::read_file($file['tmp_name']);
        if (!is_string($raw) || '' === trim($raw) || strlen($raw) > 256 * 1024) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || JSON_ERROR_NONE !== json_last_error()) {
            wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 0)));
            exit;
        }
        $decoded = UCP_Options::validate_import_payload($decoded);
        if (is_array($decoded) && !empty($decoded)) {
            UCP_Options::update($this->admin->sanitize($decoded));
            UCP_Logger::log('info', 'admin', 'settings_imported', 'Settings imported from JSON.', array('keys' => array_keys($decoded)));
        }
        wp_safe_redirect($this->admin->tab_url_public('tools', array('import' => 1)));
        exit;
    }

}
