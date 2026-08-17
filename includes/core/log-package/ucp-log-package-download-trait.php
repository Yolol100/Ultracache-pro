<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log package exports plugin-owned diagnostic tables with fixed SQL and sanitized output.

trait UCP_Log_Package_Download_Trait {
    public static function bootstrap() {
        add_action('admin_post_' . self::ACTION_DOWNLOAD, array(__CLASS__, 'handle_download'));
        add_action('ucp_logs_retention_cleanup', array(__CLASS__, 'cleanup_previous_log_files'));
        if (!wp_next_scheduled('ucp_logs_retention_cleanup')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'ucp_logs_retention_cleanup');
        }
    }

    public static function download_url() {
        return add_query_arg(
            array(
                'action'   => self::ACTION_DOWNLOAD,
                '_wpnonce' => wp_create_nonce(self::NONCE_ACTION),
            ),
            admin_url('admin-post.php')
        );
    }

    public static function handle_download() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om UltraCache logs te downloaden.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer(self::NONCE_ACTION);

        if (class_exists('UCP_Logger')) {
            UCP_Logger::log('notice', 'diagnostics', 'log_package_download_requested', __('Downloaden van het logpakket is gestart.', 'ultracache-pro'), array('user_id' => get_current_user_id()));
            UCP_Logger::flush_buffer();
        }

        $tmp = wp_tempnam('ultracache-pro-logs.zip');
        if (!$tmp) {
            wp_die(esc_html__('Kon geen tijdelijk logpakket maken.', 'ultracache-pro'), '', array('response' => 500));
        }
        $zip_path = UCP_Helpers::safe_preg_replace('/\.tmp$/', '', $tmp);
        if ($zip_path === $tmp) {
            $zip_path .= '.zip';
        }
        if (file_exists($tmp)) {
            wp_delete_file($tmp);
        }

        $ok = self::build_zip($zip_path);
        if (!$ok || !is_readable($zip_path)) {
            if (file_exists($zip_path)) {
                wp_delete_file($zip_path);
            }
            wp_die(esc_html__('Kon het logpakket niet bouwen. Controleer of ZipArchive beschikbaar is.', 'ultracache-pro'), '', array('response' => 500));
        }

        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Type: application/zip');
        header('X-Download-Options: noopen');
        header('Content-Disposition: attachment; filename="ultracache-pro-logpakket-' . gmdate('Ymd-His') . '.zip"');
        header('Content-Length: ' . filesize($zip_path));
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams a validated generated ZIP without loading it fully into PHP memory.
        readfile($zip_path);
        wp_delete_file($zip_path);
        exit;
    }

    public static function build_zip($zip_path) {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        UCP_Helpers::ensure_cache_dirs();
        self::protect_log_dir();

        $zip = new ZipArchive();
        if (true !== $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return false;
        }

        self::add_json($zip, 'manifest.json', self::manifest());
        self::add_json($zip, 'system-info.json', self::system_info());
        self::add_json($zip, 'settings-redacted.json', self::redact(UCP_Options::get_all()));
        self::add_json($zip, 'status.json', class_exists('UCP_REST_Admin_Controller') ? UCP_REST_Admin_Controller::build_status() : array());
        self::add_json($zip, 'quality-summary.json', class_exists('UCP_Support_Report') ? UCP_Support_Report::quality_summary() : array());
        self::add_json($zip, 'runtime-tests.json', class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array());
        self::add_json($zip, 'queue-summary.json', self::queue_summary());
        if (class_exists('UCP_Quality_Suite')) {
            self::add_json($zip, 'runtime-cache-test.json', get_option(UCP_Quality_Suite::RUNTIME_OPTION, array()));
            self::add_json($zip, 'conflicts.json', UCP_Quality_Suite::detect_conflicts());
            self::add_json($zip, 'release-checklist.json', UCP_Quality_Suite::release_checklist());
        }
        self::add_jsonl($zip, 'recent-jobs.jsonl', self::recent_jobs(500));
        self::add_jsonl($zip, 'recent-db-logs.jsonl', self::recent_logs(500));
        self::add_jsonl($zip, 'recent-diagnostics.jsonl', self::recent_diagnostics(300));

        foreach (UCP_Helpers::safe_glob_files(UCP_CACHE_DIR . 'logs/ucp-*.jsonl', 500) as $file) {
            if (is_readable($file)) {
                self::add_redacted_text_file($zip, 'file-logs/' . basename($file), $file);
            }
        }
        foreach (UCP_Helpers::safe_glob_files(UCP_CACHE_DIR . 'logs/events*.log', 100) as $previous_log) {
            if (is_readable($previous_log)) {
                self::add_redacted_text_file($zip, 'file-logs/' . basename($previous_log), $previous_log);
            }
        }

        if (!$zip->close() || !is_readable($zip_path) || 0 === (int) filesize($zip_path)) {
            if (file_exists($zip_path)) {
                wp_delete_file($zip_path);
            }
            return false;
        }
        return true;
    }
}
