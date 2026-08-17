<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// Consolidated from includes/diagnostics/ucp-diagnostics-record-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
trait UCP_Diagnostics_Record_Trait {
    public static function bootstrap() {
        static $fallback_booted = false;

        $booted = property_exists(__CLASS__, 'booted') ? self::$booted : $fallback_booted;
        if ($booted || !class_exists('UCP_Options') || !UCP_Options::get('enable_diagnostics')) {
            return;
        }

        if (property_exists(__CLASS__, 'booted')) {
            self::$booted = true;
        } else {
            $fallback_booted = true;
        }

        add_action('shutdown', array(__CLASS__, 'persist'), 999);
    }

    public static function record($component, $message, $context = array()) {
        if (!is_scalar($component) && null !== $component) {
            $component = '';
        }
        if (!is_scalar($message) && null !== $message) {
            $message = '';
        }
        if (!class_exists('UCP_Options') || !UCP_Options::get('enable_diagnostics')) {
            return;
        }

        $entry = array(
            'time'      => gmdate('c'),
            'component' => sanitize_key((string) $component),
            'message'   => sanitize_text_field((string) $message),
            'context'   => self::sanitize_context($context),
        );

        if (property_exists(__CLASS__, 'entries')) {
            self::$entries[] = $entry;
        }
    }

    /**
     * Redact sensitive or order-related diagnostic context keys before storage.
     * Keep operational messages useful without storing secrets, customer data,
     * payment details, cart/session identifiers or concrete WooCommerce order IDs.
     */
    protected static function is_sensitive_context_key($key) {
        return (bool) preg_match('/(password|passwd|pwd|token|secret|api[_-]?key|license|nonce|cookie|authorization|auth|session|order|customer|email|phone|address|payment|cart|checkout)/i', (string) $key);
    }

    protected static function sanitize_context($context) {
        if (!is_array($context)) {
            return array();
        }
        $clean = array();
        foreach ($context as $key => $value) {
            if (count($clean) >= 32) {
                break;
            }
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }
            if (self::is_sensitive_context_key($key)) {
                $clean[$key] = '[redacted]';
                continue;
            }
            if (is_scalar($value) || null === $value) {
                if (is_string($value) && preg_match('#^https?://#i', $value) && method_exists('UCP_Helpers', 'redact_log_url')) {
                    $clean[$key] = UCP_Helpers::redact_log_url($value);
                } else {
                    $clean[$key] = is_string($value) ? substr(sanitize_text_field($value), 0, 512) : $value;
                }
            } elseif (is_array($value)) {
                $clean[$key] = array_map(function($item) {
                    return is_scalar($item) ? substr(sanitize_text_field((string) $item), 0, 256) : '[complex]';
                }, array_slice($value, 0, 20, true));
            } else {
                $clean[$key] = '[complex]';
            }
        }
        return $clean;
    }
}

// Consolidated from includes/diagnostics/ucp-diagnostics-storage-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
trait UCP_Diagnostics_Storage_Trait {
    public static function persist() {
        global $wpdb;
        $entries = property_exists(__CLASS__, 'entries') ? self::$entries : array();
        if (empty($entries) || is_admin() || !UCP_Options::get('enable_diagnostics')) {
            return;
        }
        $raw_url = UCP_Helpers::current_full_url();
        $url = method_exists('UCP_Helpers', 'redact_log_url') ? UCP_Helpers::redact_log_url($raw_url) : esc_url_raw($raw_url);
        $url = UCP_Helpers::sanitize_preg_replace('/\?.*$/', '', (string) $url);
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = $path ? $path : '/';
        $request_type = UCP_Helpers::current_request_category();
        $rules = UCP_Rule_Engine::evaluate_request($url, $request_type);
        $cache_decision = UCP_Rule_Engine::has_action('disable_cache', $url, $request_type) ? 'bypass_by_rule' : 'eligible';
        $module_flags = array(
            'delay_js'     => (bool) UCP_Options::get('enable_delay_js'),
            'used_css'     => (bool) UCP_Options::get('enable_used_css'),
            'critical_css' => (bool) UCP_Options::get('enable_critical_css'),
            'speculation'  => (bool) UCP_Options::get('enable_speculative_loading'),
            'edge_headers' => (bool) UCP_Options::get('enable_edge_cache_headers'),
        );
        $asset_summary = array(
            'css_exclusions'   => UCP_Helpers::normalize_multiline(UCP_Options::get('css_exclusions', '')),
            'js_exclusions'    => apply_filters('ucp_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', ''))),
            'delay_exclusions' => UCP_Helpers::normalize_multiline(UCP_Options::get('delay_js_exclusions', '')),
        );

        $entries = array_slice(array_values(array_filter($entries, 'is_array')), -100);
        $payload = array(
            'generated_at'   => gmdate('c'),
            'url'            => esc_url_raw($url),
            'cache_key'      => UCP_Helpers::cache_key_for_url($url),
            'entries'        => $entries,
            'cache_decision' => $cache_decision,
            'request_type'   => $request_type,
            'rules'          => $rules,
            'module_flags'   => $module_flags,
            'asset_summary'  => $asset_summary,
        );

        UCP_Helpers::write_json_file_atomic(self::get_file($url), $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $wpdb->insert(
            ucp_table_name('diagnostics'),
            array(
                'request_hash'   => md5($url . '|' . gmdate('Y-m-d H:i')),
                'url'            => esc_url_raw($url),
                'path'           => sanitize_text_field($path),
                'request_type'   => sanitize_key($request_type),
                'cache_decision' => sanitize_key($cache_decision),
                'rule_matches'   => UCP_Helpers::safe_json_encode_or($rules, '[]'),
                'module_flags'   => UCP_Helpers::safe_json_encode_or($module_flags, '{}'),
                'asset_summary'  => UCP_Helpers::safe_json_encode_or($asset_summary, '{}'),
                'notes'          => UCP_Helpers::safe_json_encode_or($entries, '[]'),
                'generated_at'   => current_time('mysql', true),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    public static function get_file($url = '') {
        return UCP_CACHE_DIR . 'diagnostics/' . UCP_Helpers::cache_key_for_url($url) . '.json';
    }

    public static function read($url = '') {
        $file = self::get_file($url);
        if (!is_readable($file)) {
            return array();
        }
        return UCP_Helpers::safe_json_decode_array(UCP_Helpers::read_file($file, 2 * MB_IN_BYTES));
    }
}

// Consolidated from includes/diagnostics/ucp-diagnostics-query-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Diagnostics queries use plugin-owned tables and prepared values.

trait UCP_Diagnostics_Query_Trait {
    public static function latest_files() {
        $files = UCP_Helpers::safe_glob_files(UCP_CACHE_DIR . 'diagnostics/*.json', 500);
        if (empty($files)) {
            return array();
        }
        usort($files, function($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });
        return array_slice($files, 0, 20);
    }

    public static function recent_rows($limit = 20) {
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 20;
        }
        $result = self::query(
            array(
                'per_page' => max(1, absint($limit)),
                'paged'    => 1,
            )
        );
        return $result['rows'];
    }

    public static function query($args = array()) {
        global $wpdb;
        $defaults = array(
            'request_type'   => '',
            'cache_decision' => '',
            'search'         => '',
            'paged'          => 1,
            'per_page'       => 20,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $params = array();

        if (!empty($args['request_type'])) {
            $where[] = 'request_type = %s';
            $params[] = sanitize_key($args['request_type']);
        }
        if (!empty($args['cache_decision'])) {
            $where[] = 'cache_decision = %s';
            $params[] = sanitize_key($args['cache_decision']);
        }
        if (!empty($args['search']) && (is_scalar($args['search']) || null === $args['search'])) {
            $search = substr(sanitize_text_field(wp_unslash((string) $args['search'])), 0, 200);
            if ('' !== $search) {
                $like = '%' . $wpdb->esc_like($search) . '%';
                $where[] = '(url LIKE %s OR path LIKE %s OR notes LIKE %s)';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = 'SELECT COUNT(*) FROM ' . ucp_table_name('diagnostics') . ' WHERE ' . $where_sql;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $prepared_count = !empty($params) ? $wpdb->prepare($count_sql, $params) : $count_sql;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is assembled from fixed fragments and prepared values above.
        $total = (int) $wpdb->get_var($prepared_count);

        $per_page = min(100, max(1, absint($args['per_page'])));
        $paged = max(1, absint($args['paged']));
        $offset = ($paged - 1) * $per_page;

        $rows_sql = 'SELECT * FROM ' . ucp_table_name('diagnostics') . ' WHERE ' . $where_sql . ' ORDER BY id DESC LIMIT %d OFFSET %d';
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

class UCP_Diagnostics {
    use UCP_Diagnostics_Record_Trait;
    use UCP_Diagnostics_Storage_Trait;
    use UCP_Diagnostics_Query_Trait;

    /**
     * Whether diagnostics shutdown persistence has been registered.
     *
     * @var bool
     */
    protected static $booted = false;

    protected static $entries = array();
}
