<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Table_Names {
    /**
     * Resolve a plugin-owned table using the current blog prefix.
     *
     * @param string $name Logical table name.
     * @return string
     */
    public static function get($name) {
        global $wpdb;

        $map = array(
            'jobs' => 'ucp_jobs',
            'logs' => 'ucp_logs',
            'diagnostics' => 'ucp_diagnostics',
            'lcp' => 'ucp_lcp',
            'cache_events' => 'ucp_cache_events',
        );
        $key = is_scalar($name) ? sanitize_key((string) $name) : '';

        return isset($map[$key]) ? $wpdb->prefix . $map[$key] : '';
    }
}
