<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Serve_Mode {
    public static function current() {
        $mode = sanitize_key((string) UCP_Options::get('serve_mode', 'safe'));
        return in_array($mode, array('safe', 'fast', 'expert'), true) ? $mode : 'safe';
    }

    public static function environment() {
        $software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field((string) wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '';
        $lower = strtolower($software);
        return array(
            'server_software' => $software,
            'apache_like' => false !== strpos($lower, 'apache') || false !== strpos($lower, 'litespeed'),
            'nginx_like' => false !== strpos($lower, 'nginx'),
            'htaccess_writable' => is_writable(ABSPATH . '.htaccess'),
            'advanced_cache_exists' => file_exists(WP_CONTENT_DIR . '/advanced-cache.php'),
        );
    }

    public static function set_mode($mode) {
        $mode = sanitize_key($mode);
        if (!in_array($mode, array('safe', 'fast', 'expert'), true)) {
            $mode = 'safe';
        }
        UCP_Options::update(array('serve_mode' => $mode));
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('serve_mode_changed', 'success', array('mode' => $mode));
        }
    }
}
