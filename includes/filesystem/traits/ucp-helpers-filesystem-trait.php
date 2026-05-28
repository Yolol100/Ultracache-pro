<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_Filesystem_Trait {
    protected static function normalize_managed_path($path) {
        if (!is_string($path) || '' === $path || false !== strpos($path, "\0")) {
            return '';
        }
        $normalized = wp_normalize_path($path);
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $normalized)) {
            return '';
        }
        $leading_slash = 0 === strpos($normalized, '/') ? '/' : '';
        $segments = array();
        foreach (explode('/', $normalized) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                if (empty($segments)) {
                    return '';
                }
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        return $leading_slash . implode('/', $segments);
    }

    public static function is_managed_write_path($path) {
        $normalized = self::normalize_managed_path($path);
        if ('' === $normalized) {
            return false;
        }

        $cache_dir = trailingslashit(self::normalize_managed_path(UCP_CACHE_DIR));
        if ('' !== $cache_dir && 0 === strpos($normalized, $cache_dir)) {
            return true;
        }

        $exact_files = array(
            self::normalize_managed_path(WP_CONTENT_DIR . '/advanced-cache.php'),
            self::normalize_managed_path(self::wp_config_path()),
        );
        return in_array($normalized, array_filter($exact_files), true);
    }

    public static function ensure_cache_dirs() {
        $dirs = array(
            UCP_CACHE_DIR,
            UCP_CACHE_DIR . 'pages/',
            UCP_CACHE_DIR . 'assets/',
            UCP_CACHE_DIR . 'used-css/',
            UCP_CACHE_DIR . 'critical-css/',
            UCP_CACHE_DIR . 'logs/',
            UCP_CACHE_DIR . 'diagnostics/',
            UCP_CACHE_DIR . 'meta/',
            UCP_CACHE_DIR . 'tag-index/',
        );

        foreach ($dirs as $dir) {
            wp_mkdir_p($dir);
            self::write_placeholder_file($dir . 'index.html', '');
        }
    }

    public static function safe_delete_file($file) {
        if (!$file || !is_string($file) || !self::is_managed_write_path($file)) {
            return false;
        }
        if (!file_exists($file) || !is_file($file)) {
            return false;
        }
        if (function_exists('wp_delete_file')) {
            return (bool) wp_delete_file($file);
        }
        return false;
    }

    public static function write_placeholder_file($path, $content = '') {
        if (file_exists($path)) {
            return true;
        }
        return self::write_file($path, $content);
    }

    public static function move_file($source, $destination) {
        if (!self::is_managed_write_path($destination) || !is_string($source) || '' === $source || !is_file($source)) {
            return false;
        }
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        if ($wp_filesystem && $wp_filesystem->move($source, $destination, true)) {
            return true;
        }
        if (!is_readable($source) || !wp_is_writable(dirname($destination))) {
            return false;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- fallback only for plugin-managed cache files if WP_Filesystem cannot move; guarded to avoid runtime warnings.
        return @rename($source, $destination);
    }

    public static function write_file($path, $content) {
        if (!self::is_managed_write_path($path) || !is_string($content)) {
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir) || !wp_is_writable($dir)) {
            return false;
        }

        // prefer WordPress Filesystem API for managed writes such as
        // wp-config.php, advanced-cache.php and UltraCache cache/config files.
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        if ($wp_filesystem && method_exists($wp_filesystem, 'put_contents')) {
            $mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;
            if ($wp_filesystem->put_contents($path, $content, $mode)) {
                return true;
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fallback only for plugin-managed files after WP_Filesystem failed and path allow-list passed.
        return false !== file_put_contents($path, $content, LOCK_EX);
    }

    public static function append_file($path, $content) {
        if (!self::is_managed_write_path($path) || !is_string($content)) {
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir) || !wp_is_writable($dir)) {
            return false;
        }

        $existing = file_exists($path) ? self::read_file($path) : '';
        return self::write_file($path, $existing . $content);
    }

    public static function read_file($path) {
        if (!is_string($path) || '' === $path || !is_file($path) || !is_readable($path)) {
            return '';
        }
        $contents = file_get_contents($path);
        return is_string($contents) ? $contents : '';
    }

    public static function file_url_from_path($path) {
        if (0 === strpos($path, UCP_CACHE_DIR)) {
            return UCP_CACHE_URL . ltrim(substr($path, strlen(UCP_CACHE_DIR)), '/');
        }
        return '';
    }

    public static function safe_glob_delete($pattern) {
        $files = glob($pattern);
        if (!empty($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    self::safe_delete_file($file);
                }
            }
        }
    }

}
