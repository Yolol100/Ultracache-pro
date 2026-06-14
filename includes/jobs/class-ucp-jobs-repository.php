<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Small table gateway for the plugin-owned jobs table.
 */
final class UCP_Jobs_Repository {
    /** @return string Safe raw table name or empty string. */
    public static function table_name() {
        $table = function_exists('ucp_table_name') ? ucp_table_name('jobs') : '';
        return class_exists('UCP_Helpers') && UCP_Helpers::is_safe_table_name($table) ? $table : '';
    }

    /** @return string Safely quoted table name for direct SQL fragments. */
    public static function table_sql() {
        $table = self::table_name();
        return '' !== $table && class_exists('UCP_Helpers') ? UCP_Helpers::quote_table_name($table) : '';
    }
}
