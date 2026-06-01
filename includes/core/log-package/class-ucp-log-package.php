<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log package exports plugin-owned diagnostic tables with fixed SQL and sanitized output.

// Consolidated from includes/core/log-package/ucp-log-package-writer-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log package exports plugin-owned diagnostic tables with fixed SQL and sanitized output.

trait UCP_Log_Package_Writer_Trait {
    public static function write_event($level, $component, $event, $message, $context = array(), $request_url = '') {
        UCP_Helpers::ensure_cache_dirs();
        self::protect_log_dir();
        $entry = array(
            'time'        => current_time('mysql', true),
            'iso_time'    => gmdate('c'),
            'level'       => sanitize_key($level),
            'component'   => sanitize_key($component),
            'event'       => sanitize_key($event),
            'message'     => method_exists('UCP_Helpers', 'redact_log_text') ? UCP_Helpers::redact_log_text($message) : wp_strip_all_tags((string) $message),
            'context'     => self::redact(is_array($context) ? $context : array()),
            'request_url' => method_exists('UCP_Helpers', 'redact_log_url') ? UCP_Helpers::redact_log_url($request_url ? $request_url : UCP_Helpers::current_full_url()) : esc_url_raw($request_url ? $request_url : UCP_Helpers::current_full_url()),
            'user_id'     => get_current_user_id() ? '[redacted]' : 0,
            'request'     => array(
                'method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
                'ajax'   => function_exists('wp_doing_ajax') && wp_doing_ajax(),
                'cron'   => function_exists('wp_doing_cron') && wp_doing_cron(),
                'cli'    => defined('WP_CLI') && WP_CLI,
            ),
        );
        self::append_jsonl(self::log_file('activity'), $entry);
        if (in_array($entry['level'], array('warning', 'error', 'critical', 'alert', 'emergency'), true)) {
            self::append_jsonl(self::log_file('errors'), $entry);
        }
        if (in_array($entry['component'], array('cache', 'page_cache', 'asset_optimizer', 'css_generator', 'fonts', 'runtime'), true)) {
            self::append_jsonl(self::log_file('runtime'), $entry);
        }
    }

    public static function log_file($stream) {
        $stream = sanitize_key($stream);
        if (!in_array($stream, array('activity', 'errors', 'runtime'), true)) {
            $stream = 'activity';
        }
        return UCP_CACHE_DIR . 'logs/ucp-' . $stream . '-' . gmdate('Y-m-d') . '.jsonl';
    }

    protected static function append_jsonl($file, $entry) {
        UCP_Helpers::append_file($file, wp_json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        self::rotate_file_if_needed($file);
    }

    protected static function rotate_file_if_needed($file) {
        $max_bytes = (int) apply_filters('ucp_log_file_max_bytes', 5 * MB_IN_BYTES);
        if (is_file($file) && filesize($file) > $max_bytes) {
            $rotated = dirname($file) . '/' . basename($file, '.jsonl') . '-' . gmdate('His') . '.jsonl';
            UCP_Helpers::move_file($file, $rotated);
        }
    }

    public static function protect_log_dir() {
        $dir = UCP_CACHE_DIR . 'logs/';
        wp_mkdir_p($dir);
        UCP_Helpers::write_placeholder_file($dir . 'index.html', '');
        UCP_Helpers::write_placeholder_file($dir . '.htaccess', UCP_Helpers::private_dir_htaccess_rules());
        UCP_Helpers::write_placeholder_file($dir . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n");
    }

    public static function cleanup_previous_log_files() {
        $days = max(1, absint(UCP_Options::get('log_retention_days', 30)));
        $cutoff = time() - ($days * DAY_IN_SECONDS);
        foreach (glob(UCP_CACHE_DIR . 'logs/*') as $file) {
            if (is_file($file) && preg_match('/\.(jsonl|log)$/', $file) && filemtime($file) < $cutoff) {
                UCP_Helpers::safe_delete_file($file);
            }
        }
    }
}

// Consolidated from includes/core/log-package/ucp-log-package-redaction-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log package exports plugin-owned diagnostic tables with fixed SQL and sanitized output.

trait UCP_Log_Package_Redaction_Trait {
    public static function redact($value) {
        if (is_array($value)) {
            $clean = array();
            foreach ($value as $key => $item) {
                $key_string = (string) $key;
                if (preg_match('/(password|passwd|pwd|token|secret|api[_-]?key|license|nonce|cookie|authorization|auth|session|user[_-]?id|order|customer|email|phone|address|payment|cart|checkout)/i', $key_string)) {
                    $clean[$key] = '[redacted]';
                } else {
                    $clean[$key] = self::redact($item);
                }
            }
            return $clean;
        }
        if (is_string($value)) {
            if (preg_match('/(bearer\s+[a-z0-9._\-]+|sk_live_[a-z0-9_]+|sk_test_[a-z0-9_]+)/i', $value)) {
                return '[redacted]';
            }
            $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $value);
            $value = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[redacted-ip]', $value);
            $value = preg_replace_callback("#https?://[^\\s\"'<>]+#i", array(__CLASS__, 'redact_url_callback'), $value);
            if (defined('ABSPATH') && ABSPATH) {
                $value = str_replace(wp_normalize_path(ABSPATH), '[ABSPATH]/', wp_normalize_path($value));
            }
            if (strlen($value) > 5000) {
                return substr($value, 0, 5000) . '...[truncated]';
            }
            return sanitize_textarea_field($value);
        }
        if (is_scalar($value) || null === $value) {
            return $value;
        }
        return '[complex]';
    }

    protected static function redact_url_callback($matches) {
        $url = isset($matches[0]) ? (string) $matches[0] : '';
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '[redacted-url]';
        }
        $path = isset($parts['path']) ? $parts['path'] : '/';
        if (self::is_sensitive_log_url_path($path)) {
            $path = '/[redacted-path]';
        }
        return esc_url_raw($parts['scheme'] . '://' . $parts['host'] . $path . (isset($parts['query']) ? '?[redacted-query]' : ''));
    }

    protected static function is_sensitive_log_url_path($path) {
        return is_string($path) && (bool) preg_match('#/(order-pay|order-received|checkout|cart|my-account|account|payment|customer|session|token|nonce)(/|$)#i', $path);
    }

    protected static function add_redacted_text_file($zip, $name, $file) {
        $content = UCP_Helpers::read_file($file);
        if (!is_string($content)) {
            return;
        }
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $out = '';
        foreach ((array) $lines as $line) {
            if ('' === $line) {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $out .= wp_json_encode(self::redact($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                $out .= (string) self::redact($line) . "\n";
            }
            if (strlen($out) > 1024 * 1024) {
                $out .= '...[truncated]' . "\n";
                break;
            }
        }
        $zip->addFromString($name, $out);
    }
}

// Consolidated from includes/core/log-package/ucp-log-package-data-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log package exports plugin-owned diagnostic tables with fixed SQL and sanitized output.

trait UCP_Log_Package_Data_Trait {
    protected static function manifest() {
        return array(
            'generated_at' => gmdate('c'),
            'plugin'       => 'UltraCache Pro',
            'version'      => defined('UCP_VERSION') ? UCP_VERSION : '',
            'format'       => 'jsonl-and-json',
            'contents'     => array('system-info', 'settings-redacted', 'status', 'quality-summary', 'runtime-tests', 'queue-summary', 'runtime-cache-test', 'conflicts', 'release-checklist', 'recent-jobs', 'recent-db-logs', 'recent-diagnostics', 'file-logs'),
        );
    }

    protected static function system_info() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $theme = wp_get_theme();
        return array(
            'site_url'       => self::redact(home_url('/')),
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => PHP_VERSION,
            'plugin_version' => defined('UCP_VERSION') ? UCP_VERSION : '',
            'environment'    => function_exists('wp_get_environment_type') ? wp_get_environment_type() : '',
            'is_multisite'   => is_multisite(),
            'wp_debug'       => defined('WP_DEBUG') && WP_DEBUG,
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'wp_cache'       => UCP_Helpers::has_valid_wp_cache_constant(),
            'dropin_config'  => class_exists('UCP_Helpers') ? file_exists(UCP_Helpers::dropin_config_path()) : false,
            'object_cache'   => wp_using_ext_object_cache(),
            'theme'          => array('name' => $theme->get('Name'), 'template' => $theme->get_template(), 'stylesheet' => $theme->get_stylesheet()),
            'active_plugins' => (array) get_option('active_plugins', array()),
        );
    }

    protected static function queue_summary() {
        return class_exists('UCP_Jobs') ? array('counts' => UCP_Jobs::get_summary(), 'runner' => UCP_Jobs::get_runner_status()) : array();
    }

    protected static function recent_jobs($limit) {
        return UCP_Jobs::query(array('per_page' => $limit, 'paged' => 1))['rows'];
    }

    protected static function recent_logs($limit) {
        return class_exists('UCP_Logger') ? UCP_Logger::query(array('per_page' => $limit, 'paged' => 1))['rows'] : array();
    }

    protected static function recent_diagnostics($limit) {
        return class_exists('UCP_Diagnostics') ? UCP_Diagnostics::query(array('per_page' => $limit, 'paged' => 1))['rows'] : array();
    }

    protected static function add_json($zip, $name, $data) {
        $zip->addFromString($name, wp_json_encode(self::redact($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected static function add_jsonl($zip, $name, $rows) {
        $out = '';
        foreach ((array) $rows as $row) {
            $out .= wp_json_encode(self::redact($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }
        $zip->addFromString($name, $out);
    }
}

class UCP_Log_Package {
    use UCP_Log_Package_Download_Trait;
    use UCP_Log_Package_Writer_Trait;
    use UCP_Log_Package_Redaction_Trait;
    use UCP_Log_Package_Data_Trait;

    const ACTION_DOWNLOAD = 'ucp_download_log_package';
    const NONCE_ACTION = 'ucp_download_log_package';
}
