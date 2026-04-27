<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Support_Bundle {
    public static function build() {
        $settings = class_exists('UCP_Options') ? UCP_Options::get_all() : array();
        return array(
            'generated_at' => current_time('mysql', true),
            'plugin' => array(
                'version' => defined('UCP_VERSION') ? UCP_VERSION : '',
                'wp_cache_defined' => defined('WP_CACHE') && WP_CACHE,
            ),
            'environment' => self::environment(),
            'settings' => self::redact_settings($settings),
            'compatibility' => class_exists('UCP_Compat') ? UCP_Compat::compatibility_center() : array(),
            'safe_takeover' => class_exists('UCP_Compat') ? UCP_Compat::safe_takeover_status() : array(),
            'runtime_tests' => class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array(),
            'dropin' => class_exists('UCP_Compat') ? UCP_Compat::advanced_cache_status() : array(),
            'recent_logs' => self::recent_logs(),
        );
    }

    public static function redact_settings($settings) {
        return self::redact_value($settings, 'settings');
    }

    protected static function redact_value($value, $key = '') {
        $key = strtolower((string) $key);
        if (self::is_secret_key($key)) {
            return is_scalar($value) && '' !== (string) $value ? '[redacted]' : '';
        }
        if (is_array($value)) {
            $clean = array();
            foreach ($value as $item_key => $item_value) {
                $clean[$item_key] = self::redact_value($item_value, (string) $item_key);
            }
            return $clean;
        }
        if (is_string($value)) {
            return self::redact_string($value);
        }
        return $value;
    }

    protected static function is_secret_key($key) {
        if ('' === $key) {
            return false;
        }
        foreach (array('api_key', 'apikey', 'token', 'secret', 'authorization', 'license_key', 'license', 'password', 'webhook_url', 'cloud_api_key', 'cloudflare_api_token', 'bunny_api_key', 'cdn_custom_webhook_url') as $needle) {
            if (false !== strpos($key, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function mask_secret($value) {
        $value = (string) $value;
        if ('' === $value) {
            return '';
        }
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($value, -4);
    }

    protected static function environment() {
        $active_plugins = function_exists('get_option') ? (array) get_option('active_plugins', array()) : array();
        return array(
            'php_version' => PHP_VERSION,
            'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : '',
            'elementor_version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '',
            'active_plugins' => array_values(array_map('sanitize_text_field', $active_plugins)),
            'wp_content_writable' => defined('WP_CONTENT_DIR') ? is_writable(WP_CONTENT_DIR) : false,
            'cache_dir_writable' => defined('UCP_CACHE_DIR') ? is_writable(UCP_CACHE_DIR) : false,
        );
    }

    protected static function recent_logs() {
        global $wpdb;
        if (empty($wpdb) || !defined('UCP_TABLE_LOGS')) {
            return array();
        }
        $table = UCP_TABLE_LOGS;
        $rows = $wpdb->get_results("SELECT level, component, event, message, created_at FROM {$table} ORDER BY id DESC LIMIT 25", ARRAY_A);
        if (!is_array($rows)) {
            return array();
        }
        foreach ($rows as &$row) {
            foreach ($row as $key => $value) {
                if (is_string($value)) {
                    $row[$key] = self::redact_string($value);
                }
            }
        }
        unset($row);
        return $rows;
    }

    protected static function redact_string($value) {
        $value = (string) $value;
        $patterns = array(
            '/(authorization[\\s:=]+)(?:bearer\\s+)?[^\\s,;]+(?:\\s+[^\\s,;]+)?/i' => '$1[redacted]',
            '/(Bearer\\s+)[A-Za-z0-9._\\-]+/i' => '$1[redacted]',
            '/(api[_-]?key|token|secret|license[_-]?key)([\\s:=]+)([^\\s,;]+)/i' => '$1$2[redacted]',
            '/([?&](?:token|api_key|apikey|key|secret|signature)=)[^&\\s]+/i' => '$1[redacted]',
        );
        foreach ($patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value);
        }
        return $value;
    }
}

