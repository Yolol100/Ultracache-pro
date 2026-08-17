<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Canonical implementation service for minification, artifact paths and diagnostic logging. */
final class UCP_Minify_Service {
    use UCP_Helpers_Minify_And_Log_Trait {
        is_sensitive_log_url_path as public;
        redact_log_url_callback as public;
        rotate_legacy_log_if_needed as public;
    }

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * Whether the lightweight JavaScript minifier should skip the source.
     *
     * @param string $contents JavaScript source.
     * @return bool
     */
    public static function javascript_is_risky($contents) {
        if (!is_scalar($contents)) {
            return true;
        }
        $contents = (string) $contents;
        if (preg_match('/(^|[=(:,!&|?;{}\[])\s*\/(?![\/*])/', $contents)) {
            return true;
        }
        if (preg_match('/\b(?:import|export)\s+(?:\{|default|from|\*)/m', $contents)) {
            return true;
        }
        return false !== strpos($contents, 'sourceMappingURL=');
    }

    protected static function append_file($path, $content) {
        return UCP_Filesystem_Service::append_file($path, $content);
    }

    protected static function cache_key_for_url($url = '') {
        return UCP_URL_Validator::cache_key_for_url($url);
    }

    protected static function current_full_url() {
        return UCP_URL_Validator::current_full_url();
    }

    protected static function enforce_local_url($url) {
        return UCP_URL_Validator::enforce_local_url($url);
    }

    protected static function is_safe_managed_write_target($path) {
        return UCP_Filesystem_Service::is_safe_managed_write_target($path);
    }

    protected static function move_file($source, $destination) {
        return UCP_Filesystem_Service::move_file($source, $destination);
    }

    protected static function user_state_suffix() {
        return UCP_URL_Validator::user_state_suffix();
    }

}
