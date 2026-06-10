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

    /**
     * Return the canonical root .htaccess target path.
     *
     * @return string
     */
    protected static function root_htaccess_path() {
        return trailingslashit(ABSPATH) . '.htaccess';
    }

    /**
     * Validate that a path is the site root .htaccess file.
     *
     * This intentionally stays separate from is_managed_write_path(): root .htaccess
     * writes need a very narrow, explicit path check and must not broaden the global
     * file-write allow-list used for cache files, wp-config.php and advanced-cache.php.
     *
     * @param string $path Absolute file path.
     * @return bool
     */
    protected static function is_root_htaccess_path($path) {
        if (!is_string($path) || '' === $path || false !== strpos($path, "\0")) {
            return false;
        }

        $target = self::normalize_managed_path(self::root_htaccess_path());
        $path   = self::normalize_managed_path($path);

        return '' !== $target && $path === $target;
    }

    /**
     * Read the root .htaccess file with a conservative size cap.
     *
     * @return string
     */
    protected static function read_root_htaccess() {
        $path = self::root_htaccess_path();
        if (!self::is_root_htaccess_path($path) || !is_file($path) || !is_readable($path) || is_link($path)) {
            return '';
        }

        $max_size = (int) apply_filters('ucp_root_htaccess_max_read_bytes', 1048576);
        $size     = filesize($path);
        if (false !== $size && $max_size > 0 && $size > $max_size) {
            self::log('Skipped reading .htaccess: file exceeds UltraCache Pro safety cap.');
            return '';
        }

        return self::read_file($path);
    }

    /**
     * Write the root .htaccess file after strict root-path validation.
     *
     * Used only by marker-managed .htaccess writers. Do not route this through
     * write_file(), because .htaccess must remain outside the generic managed-file
     * allow-list.
     *
     * @param string $content Full .htaccess contents.
     * @return bool
     */
    protected static function write_root_htaccess($content) {
        $path = self::root_htaccess_path();
        if (!self::is_root_htaccess_path($path) || !is_string($content) || false !== strpos($content, "\0")) {
            return false;
        }
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            self::log('Skipped writing .htaccess: target is not a regular file.');
            return false;
        }
        if (!wp_is_writable(ABSPATH) || (file_exists($path) && !wp_is_writable($path))) {
            self::log('Skipped writing .htaccess: root directory or file is not writable.');
            return false;
        }

        $root_real = realpath(ABSPATH);
        $dir_real  = realpath(dirname($path));
        if (false === $root_real || false === $dir_real || wp_normalize_path($root_real) !== wp_normalize_path($dir_real)) {
            self::log('Skipped writing .htaccess: root path validation failed.');
            return false;
        }

        $content = str_replace(array("\r\n", "\r"), "\n", $content);
        $content = rtrim($content, "\n") . "\n";

        $tmp = trailingslashit(ABSPATH) . '.htaccess.ucp-tmp-' . wp_generate_password(12, false, false);

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- validated root .htaccess only; LOCK_EX avoids partial concurrent writes.
        $bytes = file_put_contents($tmp, $content, LOCK_EX);
        if (false === $bytes || $bytes !== strlen($content)) {
            if (is_file($tmp)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temporary file created by this method in the validated site root.
                @unlink($tmp);
            }
            self::log('Failed writing temporary .htaccess file.');
            return false;
        }

        $mode = file_exists($path) ? fileperms($path) & 0777 : (defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.chmod_chmod -- keep the existing .htaccess permissions when replacing atomically.
        @chmod($tmp, $mode);

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic replace for validated root .htaccess marker management.
        if (@rename($tmp, $path)) {
            return true;
        }

        if (is_file($tmp)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- cleanup temporary file created by this method.
            @unlink($tmp);
        }
        self::log('Failed replacing .htaccess atomically.');
        return false;
    }

    /**
     * Atomically write a managed plugin/cache file.
     *
     * Cache artifacts are written with local atomic file operations to avoid loading
     * WP_Filesystem on every cache miss. Non-cache managed files fall back to write_file().
     *
     * @param string $path    Destination path.
     * @param string $content File contents.
     * @return bool
     */
    public static function write_file_atomic($path, $content) {
        if (!self::is_managed_write_path($path) || !is_string($content)) {
            return false;
        }

        $normalized = self::normalize_managed_path($path);
        $cache_dir  = trailingslashit(self::normalize_managed_path(UCP_CACHE_DIR));
        if ('' === $cache_dir || 0 !== strpos($normalized, $cache_dir)) {
            return self::write_file($path, $content);
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir) || !wp_is_writable($dir)) {
            return false;
        }

        $tmp = trailingslashit($dir) . '.ucp-tmp-' . wp_generate_password(12, false, false);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- cache-dir-only atomic write after managed path validation.
        $bytes = file_put_contents($tmp, $content, LOCK_EX);
        if (false === $bytes || $bytes !== strlen($content)) {
            self::safe_delete_file($tmp);
            return false;
        }

        $mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;
        // phpcs:ignore WordPress.WP.AlternativeFunctions.chmod_chmod -- cache artifact permissions after atomic temp write.
        @chmod($tmp, $mode);

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic replace for plugin-managed cache artifacts.
        if (@rename($tmp, $path)) {
            return true;
        }

        self::safe_delete_file($tmp);
        return false;
    }

    /**
     * Resolve a frontend asset and prefer its minified variant when present.
     *
     * @param string $relative_base Relative asset path without extension.
     * @param string $extension      Extension without dot.
     * @return array{path:string,url:string,version:string}
     */
    public static function frontend_asset_with_min_fallback($relative_base, $extension = 'js') {
        $relative_base = ltrim(wp_normalize_path((string) $relative_base), '/');
        $extension     = sanitize_key((string) $extension);

        if ('' === $extension || !preg_match('#^assets/frontend/js/[a-z0-9._/-]+$#i', $relative_base)) {
            return array(
                'path'    => '',
                'url'     => '',
                'version' => UCP_VERSION,
            );
        }

        $min_relative    = $relative_base . '.min.' . $extension;
        $normal_relative = $relative_base . '.' . $extension;
        $relative        = file_exists(UCP_PATH . $min_relative) ? $min_relative : $normal_relative;
        $path            = UCP_PATH . $relative;

        return array(
            'path'    => $path,
            'url'     => UCP_URL . $relative,
            'version' => file_exists($path) ? (string) filemtime($path) : UCP_VERSION,
        );
    }


    public static function private_dir_htaccess_rules() {
        return "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
    }

    public static function ensure_cache_dirs($force = false) {
        $version_key = 'ucp_cache_dirs_ready_version';
        $recent_key  = 'ucp_cache_dirs_checked_recently';

        if (!$force && (string) get_option($version_key, '') === (string) UCP_VERSION && get_transient($recent_key)) {
            return;
        }

        $dirs = array(
            UCP_CACHE_DIR,
            UCP_CACHE_DIR . 'pages/',
            UCP_CACHE_DIR . 'pages-direct/',
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

        // deny direct web access to diagnostic/meta/log internals while leaving public cache/assets directories readable.
        foreach (array(UCP_CACHE_DIR . 'logs/', UCP_CACHE_DIR . 'diagnostics/', UCP_CACHE_DIR . 'meta/', UCP_CACHE_DIR . 'tag-index/') as $private_dir) {
            self::write_placeholder_file($private_dir . '.htaccess', self::private_dir_htaccess_rules());
            self::write_placeholder_file($private_dir . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n");
        }

        update_option($version_key, UCP_VERSION, false);
        set_transient($recent_key, 1, 6 * HOUR_IN_SECONDS);
    }

    public static function invalidate_cache_dirs_check() {
        delete_transient('ucp_cache_dirs_checked_recently');
        delete_option('ucp_cache_dirs_ready_version');
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


    public static function safe_delete_cache_dir_contents($dir) {
        $dir = trailingslashit(wp_normalize_path((string) $dir));
        $base = trailingslashit(wp_normalize_path(UCP_CACHE_DIR));
        if ('' === $dir || 0 !== strpos($dir, $base) || !is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = wp_normalize_path($item->getPathname());
            if (0 !== strpos($path, $base)) {
                continue;
            }
            if ($item->isDir()) {
                @rmdir($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- cache-only cleanup inside validated cache dir.
            } elseif ($item->isFile()) {
                self::safe_delete_file($path);
            }
        }
    }

}
