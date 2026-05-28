<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Logger reads plugin-owned custom tables with controlled SQL fragments and prepared values.

class UCP_Logger {
    protected static $buffer = array();

    public static function bootstrap() {
        if (!class_exists('UCP_Options') || !UCP_Options::get('enable_logs')) {
            return;
        }
        add_action('shutdown', array(__CLASS__, 'flush_buffer'), 1000);
    }

    public static function log($level, $component, $event, $message, $context = array()) {
        if (!class_exists('UCP_Options') || !UCP_Options::get('enable_logs')) {
            return;
        }
        $clean_context = class_exists('UCP_Log_Package') ? UCP_Log_Package::redact(is_array($context) ? $context : array()) : (is_array($context) ? $context : array());
        $request_url = esc_url_raw(UCP_Helpers::current_full_url());
        self::$buffer[] = array(
            'level'       => sanitize_key($level),
            'component'   => sanitize_key($component),
            'event'       => sanitize_key($event),
            'message'     => wp_strip_all_tags((string) $message),
            'context'     => $clean_context,
            'request_url' => $request_url,
            'created_at'  => current_time('mysql', true),
        );
        if (class_exists('UCP_Log_Package')) {
            UCP_Log_Package::write_event($level, $component, $event, $message, $clean_context, $request_url);
        }
        UCP_Helpers::log('[' . $component . '] ' . $message);
    }

    public static function flush_buffer() {
        global $wpdb;
        if (!class_exists('UCP_Options') || !UCP_Options::get('enable_logs')) {
            self::$buffer = array();
            return;
        }
        if (empty(self::$buffer)) {
            return;
        }
        foreach (self::$buffer as $row) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
            $wpdb->insert(
                ucp_table_name('logs'),
                array(
                    'level'       => $row['level'],
                    'component'   => $row['component'],
                    'event'       => $row['event'],
                    'message'     => $row['message'],
                    'context'     => wp_json_encode($row['context']),
                    'request_url' => $row['request_url'],
                    'created_at'  => $row['created_at'],
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
        self::$buffer = array();
    }

    public static function recent($limit = 50, $component = '') {
        $result = self::query(
            array(
                'component' => $component,
                'per_page'  => max(1, absint($limit)),
                'paged'     => 1,
            )
        );
        return $result['rows'];
    }

    public static function query($args = array()) {
        global $wpdb;
        $defaults = array(
            'component' => '',
            'level'     => '',
            'search'    => '',
            'paged'     => 1,
            'per_page'  => 20,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $params = array();

        if (!empty($args['component'])) {
            $where[] = 'component = %s';
            $params[] = sanitize_key($args['component']);
        }
        if (!empty($args['level'])) {
            $where[] = 'level = %s';
            $params[] = sanitize_key($args['level']);
        }
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like(wp_unslash($args['search'])) . '%';
            $where[] = '(message LIKE %s OR event LIKE %s OR request_url LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = 'SELECT COUNT(*) FROM ' . ucp_table_name('logs') . ' WHERE ' . $where_sql;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $prepared_count = !empty($params) ? $wpdb->prepare($count_sql, $params) : $count_sql;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is assembled from fixed fragments and prepared values above.
        $total = (int) $wpdb->get_var($prepared_count);

        $per_page = max(1, absint($args['per_page']));
        $paged = max(1, absint($args['paged']));
        $offset = ($paged - 1) * $per_page;

        $rows_sql = 'SELECT * FROM ' . ucp_table_name('logs') . ' WHERE ' . $where_sql . ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $rows_params = $params;
        $rows_params[] = $per_page;
        $rows_params[] = $offset;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $prepared_rows = $wpdb->prepare($rows_sql, $rows_params);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is assembled from fixed fragments and prepared values above.
        $rows = $wpdb->get_results($prepared_rows, ARRAY_A);

        return array(
            'rows'      => $rows,
            'total'     => $total,
            'per_page'  => $per_page,
            'paged'     => $paged,
            'max_pages' => max(1, (int) ceil($total / $per_page)),
        );
    }
}
