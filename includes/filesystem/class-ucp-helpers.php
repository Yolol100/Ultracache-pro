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

}
