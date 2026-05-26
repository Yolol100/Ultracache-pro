<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- var_export is intentionally used to generate a PHP config array, not for debug logging.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Helpers {
    use UCP_Helpers_Filesystem_Trait;
    use UCP_Helpers_URL_Trait;
    use UCP_Helpers_Dropin_Trait;
    use UCP_Helpers_Minify_And_Log_Trait;

    public static function is_safe_table_name($table) {
        return is_string($table) && '' !== $table && 1 === preg_match('/^[A-Za-z0-9_]+$/', $table);
    }

    public static function quote_table_name($table) {
        return '`' . str_replace('`', '``', (string) $table) . '`';
    }

    /**
     * Return the relative asset path with `.min` inserted when a production
     * variant exists and SCRIPT_DEBUG is not active. Falls back to the
     * unminified path otherwise. Relative to UCP_PATH.
     *
     * @param string $relative Relative path under the plugin root.
     * @return string The chosen relative path (with or without `.min`).
     */
    public static function asset_path($relative) {
        $rel = ltrim((string) $relative, '/');
        $use_debug = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        if (!$use_debug) {
            $dot = strrpos($rel, '.');
            if (false !== $dot) {
                $min_rel = substr($rel, 0, $dot) . '.min' . substr($rel, $dot);
                if (file_exists(UCP_PATH . $min_rel)) {
                    return $min_rel;
                }
            }
        }
        return $rel;
    }

}
