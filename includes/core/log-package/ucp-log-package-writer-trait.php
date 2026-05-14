<?php
if (!defined('ABSPATH')) {
    exit;
}

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
            'message'     => wp_strip_all_tags((string) $message),
            'context'     => self::redact(is_array($context) ? $context : array()),
            'request_url' => esc_url_raw($request_url ? $request_url : UCP_Helpers::current_full_url()),
            'user_id'     => get_current_user_id(),
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
        UCP_Helpers::write_placeholder_file($dir . '.htaccess', "Deny from all\n");
        UCP_Helpers::write_placeholder_file($dir . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n");
    }

    public static function cleanup_old_log_files() {
        $days = max(1, absint(UCP_Options::get('log_retention_days', 30)));
        $cutoff = time() - ($days * DAY_IN_SECONDS);
        foreach (glob(UCP_CACHE_DIR . 'logs/*') as $file) {
            if (is_file($file) && preg_match('/\.(jsonl|log)$/', $file) && filemtime($file) < $cutoff) {
                UCP_Helpers::safe_delete_file($file);
            }
        }
    }
}
