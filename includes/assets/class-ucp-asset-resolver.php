<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Asset_Resolver {
    /**
     * Return the best available relative asset path.
     *
     * A minified file is selected only when it is non-empty, smaller and not
     * older than its readable source. Runtime-critical admin bundles deliberately
     * use the readable source to avoid identifier-mangling regressions.
     *
     * @param string $relative Relative path under the plugin root.
     * @return string
     */
    public static function relative($relative) {
        if (!is_scalar($relative) && null !== $relative) {
            $relative = '';
        }
        $source = ltrim((string) $relative, '/');
        if ('' === $source) {
            return '';
        }

        $minified = self::minified_relative($source);
        if ('' === $minified) {
            return $source;
        }

        $source_path = UCP_PATH . $source;
        $minified_path = UCP_PATH . $minified;
        $debug = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;

        if (($debug || self::prefer_readable_runtime_asset($source)) && is_file($source_path)) {
            return $source;
        }

        if (self::is_valid_minified_asset($source_path, $minified_path)) {
            return $minified;
        }

        return is_file($source_path) ? $source : $minified;
    }

    /**
     * Build a content-based asset version.
     *
     * @param string $relative Relative path under the plugin root.
     * @return string
     */
    public static function version($relative) {
        if (!is_scalar($relative) && null !== $relative) {
            $relative = '';
        }
        $path = UCP_PATH . ltrim((string) $relative, '/');
        if (!is_file($path)) {
            return UCP_VERSION;
        }

        $hash = hash_file('sha256', $path);
        if (is_string($hash) && '' !== $hash) {
            return substr($hash, 0, 12);
        }

        $modified = filemtime($path);
        return false !== $modified ? (string) $modified : UCP_VERSION;
    }

    /**
     * @param string $relative Source relative path.
     * @return string
     */
    private static function minified_relative($relative) {
        $dot = strrpos($relative, '.');
        if (false === $dot || 0 === $dot) {
            return '';
        }

        return substr($relative, 0, $dot) . '.min' . substr($relative, $dot);
    }


    /**
     * The React admin interface is runtime-critical and ships readable assets.
     * Prefer those over hand-maintained minified variants so stale CSS or
     * identifier mangling cannot break the complete settings interface.
     *
     * @param string $relative Source relative path.
     * @return bool
     */
    private static function prefer_readable_runtime_asset($relative) {
        return 0 === strpos((string) $relative, 'assets/admin/react/');
    }

    /**
     * @param string $source_path Absolute source path.
     * @param string $minified_path Absolute minified path.
     * @return bool
     */
    private static function is_valid_minified_asset($source_path, $minified_path) {
        if (!is_file($minified_path)) {
            return false;
        }

        $minified_size = filesize($minified_path);
        if (false === $minified_size || $minified_size <= 0) {
            return false;
        }

        if (!is_file($source_path)) {
            return true;
        }

        $source_size = filesize($source_path);
        if (false === $source_size || $minified_size >= $source_size) {
            return false;
        }

        $source_modified = filemtime($source_path);
        $minified_modified = filemtime($minified_path);
        return false === $source_modified || false === $minified_modified || $minified_modified >= $source_modified;
    }
}
