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
     * Check whether a managed path belongs to the plugin cache tree.
     *
     * @param string $path Absolute path.
     * @return bool
     */
    protected static function is_managed_cache_path($path) {
        $normalized = self::normalize_managed_path($path);
        $cache_dir  = trailingslashit(self::normalize_managed_path(UCP_CACHE_DIR));

        return '' !== $normalized && '' !== $cache_dir && 0 === strpos($normalized, $cache_dir);
    }

    /**
     * Reject symlink escapes below the configured cache root before a managed write.
     *
     * The cache root itself may intentionally be a configured symlink. Descendant
     * symlinks are rejected because file writes would otherwise leave that root.
     * Exact managed files (wp-config.php and advanced-cache.php) may never be links.
     *
     * @param string $path Absolute target path.
     * @return bool
     */
    protected static function is_safe_managed_write_target($path) {
        if (!self::is_managed_write_path($path)) {
            return false;
        }

        if (!self::is_managed_cache_path($path)) {
            return !is_link($path) && (!file_exists($path) || is_file($path));
        }

        $normalized = self::normalize_managed_path($path);
        $cache_dir  = trailingslashit(self::normalize_managed_path(UCP_CACHE_DIR));
        $cache_root = rtrim((string) UCP_CACHE_DIR, '/\\');
        $relative   = ltrim(substr($normalized, strlen($cache_dir)), '/');
        $segments   = '' === $relative ? array() : explode('/', $relative);

        // The final segment is the file target; only walk descendant directories here.
        array_pop($segments);
        $current = $cache_root;
        foreach ($segments as $segment) {
            if ('' === $segment) {
                continue;
            }
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current) || (file_exists($current) && !is_dir($current))) {
                return false;
            }
        }

        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            return false;
        }

        $root_real = realpath($cache_root);
        if (false === $root_real) {
            // The cache root can be created later; the descendant walk above still
            // prevents following an already-present link below the configured root.
            return true;
        }

        $parent = dirname($path);
        while (!file_exists($parent) && !is_link($parent)) {
            $next = dirname($parent);
            if ($next === $parent) {
                return false;
            }
            $parent = $next;
        }
        $normalized_parent = self::normalize_managed_path($parent);
        $normalized_root   = self::normalize_managed_path($cache_root);
        if (is_link($parent) && $normalized_parent !== $normalized_root) {
            return false;
        }

        $parent_real = realpath($parent);
        if (false === $parent_real) {
            return false;
        }

        $root_real   = trailingslashit(wp_normalize_path($root_real));
        $parent_real = trailingslashit(wp_normalize_path($parent_real));

        return $parent_real === $root_real || 0 === strpos($parent_real, $root_real);
    }

    /**
     * Validate a readable regular file below the managed cache root without
     * following descendant symlinks. The cache root itself may be configured as
     * a symlink, matching the managed-write contract.
     *
     * @param string $path Absolute file path.
     * @return bool
     */
    public static function is_safe_managed_cache_file($path) {
        return self::is_managed_cache_path($path)
            && self::is_safe_managed_write_target($path)
            && is_file($path)
            && is_readable($path);
    }

    /**
     * Open a managed cache file without exposing the caller to symlink swaps.
     *
     * The returned handle remains bound to the validated inode even when the
     * pathname is replaced after this method returns.
     *
     * @param string $path Absolute cache file path.
     * @param string $mode Supported non-truncating fopen mode: c or c+.
     * @return resource|false
     */
    public static function open_managed_cache_file($path, $mode = 'c') {
        if (!in_array($mode, array('c', 'c+'), true) || !self::is_safe_managed_write_target($path)) {
            return false;
        }

        $dir = dirname($path);
        if (!is_dir($dir) || !wp_is_writable($dir)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- validated cache-only handle; post-open inode checks close the symlink-swap window.
        $handle = @fopen($path, $mode);
        if (!is_resource($handle)) {
            return false;
        }

        clearstatcache(true, $path);
        $handle_stat = @fstat($handle);
        $path_stat   = @stat($path);
        $same_inode  = is_array($handle_stat) && is_array($path_stat);
        if ($same_inode && isset($handle_stat['dev'], $handle_stat['ino'], $path_stat['dev'], $path_stat['ino'])) {
            $same_inode = (string) $handle_stat['dev'] === (string) $path_stat['dev']
                && (string) $handle_stat['ino'] === (string) $path_stat['ino'];
        }
        $single_link = !is_array($handle_stat) || !isset($handle_stat['nlink']) || (int) $handle_stat['nlink'] <= 1;

        if (
            !$same_inode
            || !$single_link
            || is_link($path)
            || !is_file($path)
            || !self::is_safe_managed_write_target($path)
        ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- close rejected cache handle immediately.
            @fclose($handle);
            return false;
        }

        return $handle;
    }

    /**
     * Delete a file and normalize wp_delete_file() across supported WordPress versions.
     *
     * WordPress versions before 6.7 do not return a result from wp_delete_file(),
     * so deletion success must be confirmed from the filesystem.
     *
     * @param string $file Absolute file path.
     * @return bool
     */
    protected static function delete_file_and_confirm($file) {
        if (!is_string($file) || '' === $file || !function_exists('wp_delete_file')) {
            return false;
        }

        $result = wp_delete_file($file);
        if (is_bool($result)) {
            return $result;
        }

        clearstatcache(true, $file);
        return !file_exists($file) && !is_link($file);
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

        $max_size = max(64 * KB_IN_BYTES, min(5 * MB_IN_BYTES, absint(apply_filters('ucp_root_htaccess_max_read_bytes', MB_IN_BYTES))));
        $size     = filesize($path);
        if (false !== $size && $size > $max_size) {
            self::log('Skipped reading .htaccess: file exceeds UltraCache Pro safety cap.');
            return '';
        }

        return self::read_file($path, $max_size);
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
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- keep the existing .htaccess permissions when replacing atomically.
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
     * Delete a temporary file created by write_file_atomic().
     *
     * Cache-tree temporary files already pass the normal managed-path guard. Drop-in
     * staging files live beside advanced-cache.php and therefore require a narrower
     * filename allow-list instead of broadening the public managed-write paths.
     *
     * @param string $file Temporary or backup path.
     * @return bool
     */
    protected static function delete_atomic_staging_file($file) {
        if (!is_string($file) || '' === $file || false !== strpos($file, "\0")) {
            return false;
        }
        if (!file_exists($file)) {
            return true;
        }
        if (self::safe_delete_file($file)) {
            return true;
        }

        $normalized = self::normalize_managed_path($file);
        $content_dir = self::normalize_managed_path(WP_CONTENT_DIR);
        if (
            '' === $normalized
            || '' === $content_dir
            || self::normalize_managed_path(dirname($file)) !== $content_dir
            || is_link($file)
            || !is_file($file)
        ) {
            return false;
        }

        $basename = basename($normalized);
        if (!preg_match('/^(?:\.ucp-tmp-[A-Za-z0-9]+|\.advanced-cache\.php\.ucp-backup-[A-Za-z0-9]+)$/', $basename)) {
            return false;
        }

        return self::delete_file_and_confirm($file);
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
        if (!is_string($content) || !self::is_safe_managed_write_target($path)) {
            return false;
        }

        $normalized     = self::normalize_managed_path($path);
        $cache_dir      = trailingslashit(self::normalize_managed_path(UCP_CACHE_DIR));
        $advanced_cache = self::normalize_managed_path(WP_CONTENT_DIR . '/advanced-cache.php');
        $is_cache_path  = '' !== $cache_dir && 0 === strpos($normalized, $cache_dir);
        $is_dropin      = '' !== $advanced_cache && $normalized === $advanced_cache;
        if (!$is_cache_path && !$is_dropin) {
            return self::write_file($path, $content);
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir) || !wp_is_writable($dir) || !self::is_safe_managed_write_target($path)) {
            return false;
        }

        if ($is_dropin) {
            $content_real = realpath(WP_CONTENT_DIR);
            $dir_real     = realpath($dir);
            if (false === $content_real || false === $dir_real || wp_normalize_path($content_real) !== wp_normalize_path($dir_real)) {
                return false;
            }
        }

        $suffix = wp_generate_password(12, false, false);
        $tmp    = trailingslashit($dir) . '.ucp-tmp-' . $suffix;
        $backup = $is_dropin ? trailingslashit($dir) . '.advanced-cache.php.ucp-backup-' . $suffix : '';
        $before = '';
        if ($is_dropin && file_exists($path)) {
            if (!is_file($path) || !is_readable($path) || is_link($path)) {
                return false;
            }
            $before = self::read_file($path);
            if (!is_string($before)) {
                return false;
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- verified temporary backup beside the managed drop-in.
            $backup_bytes = file_put_contents($backup, $before, LOCK_EX);
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- another writer may remove the temporary file between the readability check and hashing.
            $backup_hash  = is_readable($backup) ? @hash_file('sha256', $backup) : false;
            if (false === $backup_bytes || $backup_bytes !== strlen($before) || !is_string($backup_hash) || !hash_equals(hash('sha256', $before), $backup_hash)) {
                self::delete_atomic_staging_file($backup);
                return false;
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- validated managed path; atomic temp write prevents partial active files.
        $bytes    = file_put_contents($tmp, $content, LOCK_EX);
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- another writer may move the temporary file between the readability check and hashing.
        $tmp_hash = is_readable($tmp) ? @hash_file('sha256', $tmp) : false;
        if (false === $bytes || $bytes !== strlen($content) || !is_string($tmp_hash) || !hash_equals(hash('sha256', $content), $tmp_hash)) {
            self::delete_atomic_staging_file($tmp);
            self::delete_atomic_staging_file($backup);
            return false;
        }

        $mode = file_exists($path) ? (fileperms($path) & 0777) : (defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- preserve managed target permissions on atomic replacement.
        @chmod($tmp, $mode);

        if ($is_dropin) {
            $current = file_exists($path) && is_readable($path) ? self::read_file($path) : '';
            if (!is_string($current) || !hash_equals(hash('sha256', $before), hash('sha256', $current))) {
                self::delete_atomic_staging_file($tmp);
                self::delete_atomic_staging_file($backup);
                return false;
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic replace inside a validated managed directory.
        if (!@rename($tmp, $path)) {
            self::delete_atomic_staging_file($tmp);
            self::delete_atomic_staging_file($backup);
            return false;
        }

        if ($is_dropin) {
            $installed = is_readable($path) ? self::read_file($path) : '';
            if (!is_string($installed) || !hash_equals(hash('sha256', $content), hash('sha256', $installed))) {
                if ('' !== $before && is_readable($backup)) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- restore the verified previous drop-in after a failed replacement verification.
                    @rename($backup, $path);
                } elseif ('' === $before) {
                    self::safe_delete_file($path);
                }
                self::delete_atomic_staging_file($tmp);
                self::delete_atomic_staging_file($backup);
                return false;
            }
        }

        self::delete_atomic_staging_file($backup);
        return true;
    }

    /**
     * Atomically write a direct child file in an UltraCache-owned uploads cache directory.
     *
     * This is intentionally narrower than write_file_atomic(): the destination
     * directory must resolve below uploads/ultracache-pro, may not be a symlink,
     * and the target must be a direct child of that directory.
     *
     * @param string $path     Destination file path.
     * @param string $content  File contents.
     * @param string $base_dir Validated UltraCache uploads cache directory.
     * @return bool
     */
    public static function write_upload_cache_file_atomic($path, $content, $base_dir) {
        if (!is_string($path) || !is_string($content) || !is_string($base_dir) || false !== strpos($path, "\0")) {
            return false;
        }

        $uploads = wp_upload_dir();
        $base_input = rtrim($base_dir, '/\\');
        if (
            empty($uploads['basedir'])
            || !is_dir($base_input)
            || is_link($base_input)
            || is_link(dirname($base_input))
            || !wp_is_writable($base_input)
        ) {
            return false;
        }

        $uploads_real = realpath((string) $uploads['basedir']);
        $base_real    = realpath($base_input);
        $dir_real     = realpath(dirname($path));
        if (false === $uploads_real || false === $base_real || false === $dir_real) {
            return false;
        }

        $uploads_real = rtrim(wp_normalize_path($uploads_real), '/');
        $base_real    = rtrim(wp_normalize_path($base_real), '/');
        $dir_real     = rtrim(wp_normalize_path($dir_real), '/');
        $plugin_root  = $uploads_real . '/ultracache-pro';
        if ($base_real !== $plugin_root && 0 !== strpos($base_real, $plugin_root . '/')) {
            return false;
        }
        if ($dir_real !== $base_real) {
            return false;
        }

        $normalized_path = wp_normalize_path($path);
        $normalized_base = rtrim(wp_normalize_path($base_input), '/');
        if (dirname($normalized_path) !== $normalized_base || basename($normalized_path) !== basename($path)) {
            return false;
        }
        if (file_exists($path) && !is_file($path) && !is_link($path)) {
            return false;
        }
        if (is_link($path) && !self::delete_file_and_confirm($path)) {
            return false;
        }

        $tmp = $base_real . '/.ucp-tmp-' . wp_generate_password(12, false, false);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- validated direct child of an UltraCache uploads cache directory; LOCK_EX avoids partial writes.
        $bytes = file_put_contents($tmp, $content, LOCK_EX);
        if (false === $bytes || $bytes !== strlen($content)) {
            if (is_file($tmp)) {
                wp_delete_file($tmp);
            }
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic replace inside the validated UltraCache uploads cache directory.
        if (!@rename($tmp, $path)) {
            wp_delete_file($tmp);
            return false;
        }

        return true;
    }

    /**
     * Resolve a frontend asset and prefer its minified variant when present.
     *
     * @param string $relative_base Relative asset path without extension.
     * @param string $extension      Extension without dot.
     * @return array{path:string,url:string,version:string}
     */
    public static function frontend_asset_with_min_fallback($relative_base, $extension = 'js') {
        if (!is_scalar($relative_base)) {
            return array('path' => '', 'url' => '', 'version' => UCP_VERSION);
        }
        $relative_base = ltrim(wp_normalize_path((string) $relative_base), '/');
        $extension     = is_scalar($extension) ? sanitize_key((string) $extension) : 'js';

        if ('' === $extension || !preg_match('#^assets/frontend/js/[a-z0-9._/-]+$#i', $relative_base)) {
            return array(
                'path'    => '',
                'url'     => '',
                'version' => UCP_VERSION,
            );
        }

        $min_relative    = $relative_base . '.min.' . $extension;
        $normal_relative = $relative_base . '.' . $extension;
        $use_debug       = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;

        if ($use_debug && file_exists(UCP_PATH . $normal_relative)) {
            $relative = $normal_relative;
        } elseif (file_exists(UCP_PATH . $min_relative)) {
            $relative = $min_relative;
        } else {
            $relative = $normal_relative;
        }
        $path = UCP_PATH . $relative;

        return array(
            'path'    => $path,
            'url'     => UCP_URL . $relative,
            'version' => file_exists($path) ? (string) filemtime($path) : UCP_VERSION,
        );
    }


    public static function private_dir_htaccess_rules() {
        return "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
    }

    protected static function cache_root_htaccess_rules() {
        return "Options -Indexes\n<FilesMatch \"^(?:dropin-config\\.php|insights-dropin\\.json|server-rules-(?:nginx\\.conf|apache\\.txt))$\">\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n</FilesMatch>\n";
    }

    protected static function cache_root_web_config_rules() {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<configuration><system.webServer><directoryBrowse enabled="false" /><security><requestFiltering><hiddenSegments>'
            . '<add segment="dropin-config.php" /><add segment="insights-dropin.json" /><add segment="server-rules-nginx.conf" /><add segment="server-rules-apache.txt" />'
            . '</hiddenSegments></requestFiltering></security></system.webServer></configuration>' . "\n";
    }

    public static function ensure_cache_dirs($force = false) {
        $version_key = 'ucp_cache_dirs_ready_version';
        $recent_key  = 'ucp_cache_dirs_checked_recently';

        if (!$force && (string) get_option($version_key, '') === (string) UCP_VERSION && get_transient($recent_key)) {
            return true;
        }

        $dirs = array(
            UCP_CACHE_DIR,
            UCP_CACHE_DIR . 'pages/',
            UCP_CACHE_DIR . 'pages-direct/',
            UCP_CACHE_DIR . 'assets/',
            UCP_CACHE_DIR . 'used-css/',
            UCP_CACHE_DIR . 'critical-css/',
            UCP_CACHE_DIR . 'lqip/',
            UCP_CACHE_DIR . 'logs/',
            UCP_CACHE_DIR . 'diagnostics/',
            UCP_CACHE_DIR . 'meta/',
            UCP_CACHE_DIR . 'tag-index/',
        );

        $ready = true;
        foreach ($dirs as $dir) {
            if (!wp_mkdir_p($dir) && !is_dir($dir)) {
                $ready = false;
                continue;
            }
            if (!self::write_placeholder_file($dir . 'index.html', '')) {
                $ready = false;
            }
        }

        if (!self::write_file_atomic(UCP_CACHE_DIR . '.htaccess', self::cache_root_htaccess_rules())) {
            $ready = false;
        }
        if (!self::write_file_atomic(UCP_CACHE_DIR . 'web.config', self::cache_root_web_config_rules())) {
            $ready = false;
        }

        // The PHP/drop-in page cache and internal diagnostics are never intended for direct web access.
        foreach (array(UCP_CACHE_DIR . 'pages/', UCP_CACHE_DIR . 'logs/', UCP_CACHE_DIR . 'diagnostics/', UCP_CACHE_DIR . 'meta/', UCP_CACHE_DIR . 'tag-index/') as $private_dir) {
            if (!is_dir($private_dir)) {
                $ready = false;
                continue;
            }
            if (!self::write_file_atomic($private_dir . '.htaccess', self::private_dir_htaccess_rules())) {
                $ready = false;
            }
            if (!self::write_file_atomic($private_dir . 'web.config', '<?xml version="1.0" encoding="UTF-8"?>' . "\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n")) {
                $ready = false;
            }
        }

        if (!$ready) {
            delete_transient($recent_key);
            delete_option($version_key);
            return false;
        }

        $version_saved = update_option($version_key, UCP_VERSION, false) || (string) get_option($version_key, '') === (string) UCP_VERSION;
        if (!$version_saved) {
            delete_transient($recent_key);
            return false;
        }

        set_transient($recent_key, 1, 6 * HOUR_IN_SECONDS);
        return true;
    }

    public static function invalidate_cache_dirs_check() {
        delete_transient('ucp_cache_dirs_checked_recently');
        delete_option('ucp_cache_dirs_ready_version');
    }

    public static function safe_delete_file($file) {
        if (!$file || !is_string($file) || !self::is_managed_write_path($file)) {
            return false;
        }
        if (is_link($file)) {
            if (!self::is_managed_cache_path($file)) {
                return false;
            }
            return self::delete_file_and_confirm($file);
        }
        if (!self::is_safe_managed_write_target($file)) {
            return false;
        }
        if (!file_exists($file) || !is_file($file)) {
            return false;
        }
        return self::delete_file_and_confirm($file);
    }

    public static function write_placeholder_file($path, $content = '') {
        if (file_exists($path)) {
            return true;
        }
        return self::write_file($path, $content);
    }

    public static function move_file($source, $destination) {
        if (
            !is_string($source)
            || '' === $source
            || !is_file($source)
            || !self::is_safe_managed_write_target($source)
            || !self::is_safe_managed_write_target($destination)
        ) {
            return false;
        }
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        if ($wp_filesystem && self::is_safe_managed_write_target($destination) && $wp_filesystem->move($source, $destination, true)) {
            return true;
        }
        if (!is_readable($source) || !wp_is_writable(dirname($destination))) {
            return false;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- fallback only for plugin-managed cache files if WP_Filesystem cannot move; guarded to avoid runtime warnings.
        return @rename($source, $destination);
    }

    public static function write_file($path, $content) {
        if (!is_string($content) || !self::is_safe_managed_write_target($path)) {
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir) || !wp_is_writable($dir) || !self::is_safe_managed_write_target($path)) {
            return false;
        }

        // Prefer WordPress Filesystem API for managed writes such as
        // wp-config.php, advanced-cache.php and UltraCache cache/config files.
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        if ($wp_filesystem && method_exists($wp_filesystem, 'put_contents')) {
            $mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;
            if (self::is_safe_managed_write_target($path) && $wp_filesystem->put_contents($path, $content, $mode)) {
                return true;
            }
        }

        if (!self::is_safe_managed_write_target($path)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fallback only for plugin-managed files after WP_Filesystem failed and path allow-list passed.
        $bytes = file_put_contents($path, $content, LOCK_EX);

        return false !== $bytes && $bytes === strlen($content);
    }

    public static function append_file($path, $content) {
        if (!is_string($content) || !self::is_managed_cache_path($path) || !self::is_safe_managed_write_target($path)) {
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir) || !wp_is_writable($dir) || !self::is_safe_managed_write_target($path)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- cache-only append with an exclusive lock avoids lost concurrent log writes.
        $bytes = file_put_contents($path, $content, FILE_APPEND | LOCK_EX);

        return false !== $bytes && $bytes === strlen($content);
    }

    public static function read_file($path, $max_bytes = 0) {
        if (!is_string($path) || '' === $path || is_link($path) || !is_file($path) || !is_readable($path)) {
            return '';
        }

        // Cache artifacts are later injected, decoded or streamed by several
        // runtime modules. Never follow a replaced file or descendant directory
        // symlink outside the managed cache root while reading those artifacts.
        // Non-cache reads keep their existing behaviour for uploads, plugin files
        // and explicitly managed WordPress files.
        if (self::is_managed_cache_path($path) && !self::is_safe_managed_cache_file($path)) {
            return '';
        }

        $max_bytes = absint($max_bytes);
        if ($max_bytes <= 0) {
            $max_bytes = absint(apply_filters('ucp_read_file_max_bytes', 50 * MB_IN_BYTES, $path));
        }
        $max_bytes = max(KB_IN_BYTES, min(100 * MB_IN_BYTES, $max_bytes));
        $size = filesize($path);
        if (false !== $size && $size > $max_bytes) {
            return '';
        }
        $contents = file_get_contents($path, false, null, 0, $max_bytes + 1);
        return is_string($contents) && strlen($contents) <= $max_bytes ? $contents : '';
    }

    public static function file_url_from_path($path) {
        $path = self::normalize_managed_path($path);
        $root = rtrim(self::normalize_managed_path(UCP_CACHE_DIR), '/');
        if ('' === $path || '' === $root || ($path !== $root && 0 !== strpos($path, $root . '/'))) {
            return '';
        }
        if (!self::is_safe_managed_cache_file($path)) {
            return '';
        }

        $relative = ltrim(substr($path, strlen($root)), '/');
        return trailingslashit(UCP_CACHE_URL) . $relative;
    }

    public static function safe_glob_delete($pattern) {
        $max_files = max(1, min(5000, absint(apply_filters('ucp_safe_glob_delete_max_files', 2000, $pattern))));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- UCP_Helpers::safe_glob_files() is not part of
        // this trait (or of UCP_Filesystem_Service, which consumes it), so `self::` fatals when this trait is used
        // by UCP_Filesystem_Service. Call the concrete implementation explicitly instead.
        $files = UCP_Helpers::safe_glob_files($pattern, $max_files, array(UCP_CACHE_DIR));
        foreach ($files as $file) {
            self::safe_delete_file($file);
        }
    }


    public static function safe_delete_cache_dir_contents($dir) {
        if (!is_scalar($dir)) {
            return;
        }
        $raw_dir = rtrim((string) $dir, '/\\');
        $dir = trailingslashit(wp_normalize_path($raw_dir));
        $base = trailingslashit(wp_normalize_path(UCP_CACHE_DIR));
        if ('' === $dir || 0 !== strpos($dir, $base)) {
            return;
        }
        $normalized_raw  = self::normalize_managed_path($raw_dir);
        $normalized_root = self::normalize_managed_path(UCP_CACHE_DIR);
        if (is_link($raw_dir)) {
            // Never recurse through a symlink passed as the deletion root. A configured
            // cache-root symlink may be valid for normal reads/writes, but following it
            // during purge/uninstall would turn a replaced link into an arbitrary-tree
            // deletion primitive. Descendant links can be unlinked without traversal.
            if ($normalized_raw !== $normalized_root) {
                self::safe_delete_file($raw_dir);
            }
            return;
        }
        if (!is_dir($raw_dir)) {
            return;
        }

        $root_real = realpath(UCP_CACHE_DIR);
        $dir_real  = realpath($raw_dir);
        if (false === $root_real || false === $dir_real) {
            return;
        }
        $root_real = trailingslashit(wp_normalize_path($root_real));
        $dir_real  = trailingslashit(wp_normalize_path($dir_real));
        if ($dir_real !== $root_real && 0 !== strpos($dir_real, $root_real)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raw_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = wp_normalize_path($item->getPathname());
            if (0 !== strpos($path, $base)) {
                continue;
            }
            if ($item->isLink()) {
                self::safe_delete_file($path);
            } elseif ($item->isDir()) {
                @rmdir($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- cache-only cleanup inside validated cache dir.
            } elseif ($item->isFile()) {
                self::safe_delete_file($path);
            }
        }
    }

}
