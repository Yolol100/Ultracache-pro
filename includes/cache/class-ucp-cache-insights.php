<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Queries target one plugin-owned table with controlled fragments and prepared values.
final class UCP_Cache_Insights {
    public static function table_name() {
        $table = function_exists('ucp_table_name') ? ucp_table_name('cache_events') : '';
        return class_exists('UCP_Helpers') && UCP_Helpers::is_safe_table_name($table) ? $table : '';
    }

    private static function table_sql() {
        $table = self::table_name();
        return '' !== $table ? UCP_Helpers::quote_table_name($table) : '';
    }

    public static function enabled() {
        return (bool) UCP_Options::get('enable_cache_insights', 1);
    }

    public static function record_request($status, $reason = '', $context = array()) {
        if (!is_scalar($status) && null !== $status) {
            $status = '';
        }
        if (!is_scalar($reason) && null !== $reason) {
            $reason = '';
        }
        if (!self::enabled() || is_admin()) {
            return false;
        }
        $sample_rate = min(100, max(1, absint(UCP_Options::get('cache_insights_sample_rate', 1))));
        if ($sample_rate < 100 && wp_rand(1, 100) > $sample_rate) {
            return false;
        }
        return self::insert(array(
            'event_type' => 'request',
            'status' => sanitize_key((string) $status),
            'reason' => sanitize_key((string) $reason),
            'source' => 'runtime',
            'scope' => '',
            'actor_id' => 0,
            'sample_weight' => max(1, (int) round(100 / $sample_rate)),
            'context' => self::sanitize_context($context),
        ));
    }

    public static function record_purge($source, $scope, $context = array()) {
        if (!is_scalar($source) && null !== $source) {
            $source = '';
        }
        if (!is_scalar($scope) && null !== $scope) {
            $scope = '';
        }
        if (!self::enabled()) {
            return false;
        }
        return self::insert(array(
            'event_type' => 'purge',
            'status' => 'success',
            'reason' => '',
            'source' => sanitize_key((string) $source),
            'scope' => sanitize_key((string) $scope),
            'actor_id' => 0,
            'sample_weight' => 1,
            'context' => self::sanitize_context($context),
        ));
    }

    private static function current_path() {
        $path = class_exists('UCP_Helpers') ? UCP_Helpers::current_url_path() : '/';
        $path = '/' . ltrim((string) $path, '/');
        return substr(sanitize_text_field($path), 0, 255);
    }

    private static function insert($event) {
        global $wpdb;
        $table = self::table_name();
        if ('' === $table || (defined('UCP_VERSION') && UCP_VERSION !== (string) get_option('ucp_db_version', ''))) {
            return false;
        }
        $context = isset($event['context']) && is_array($event['context']) ? $event['context'] : array();
        $path = !empty($context['target_path']) ? '/' . ltrim((string) $context['target_path'], '/') : self::current_path();
        $path = substr(sanitize_text_field($path), 0, 255);
        unset($context['target_path']);
        $url_hash = hash('sha256', strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST)) . '|' . $path);
        return false !== $wpdb->insert($table, array(
            'event_type' => sanitize_key((string) $event['event_type']),
            'status' => sanitize_key((string) $event['status']),
            'reason' => sanitize_key((string) $event['reason']),
            'url_hash' => $url_hash,
            'path' => $path,
            'source' => sanitize_key((string) $event['source']),
            'scope' => sanitize_key((string) $event['scope']),
            'actor_id' => absint($event['actor_id']),
            'sample_weight' => max(1, absint($event['sample_weight'])),
            'context' => UCP_Helpers::safe_json_encode_or($context, '{}'),
            'created_at' => current_time('mysql', true),
        ), array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'));
    }

    private static function sanitize_context($context) {
        $clean = array();
        foreach ((array) $context as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key || in_array($key, array('cookie', 'cookies', 'authorization', 'nonce', 'token'), true)) {
                continue;
            }
            if (is_bool($value)) {
                $clean[$key] = $value;
            } elseif (is_numeric($value)) {
                $clean[$key] = 0 + $value;
            } elseif (is_scalar($value)) {
                $clean[$key] = substr(sanitize_text_field((string) $value), 0, 160);
            }
            if (count($clean) >= 12) {
                break;
            }
        }
        return $clean;
    }

    private static function dropin_counter_path() {
        return defined('UCP_CACHE_DIR') ? trailingslashit(UCP_CACHE_DIR) . 'insights-dropin.json' : '';
    }

    private static function take_dropin_counts() {
        $path = self::dropin_counter_path();
        if ('' === $path || !is_file($path)) {
            return array();
        }
        $handle = UCP_Helpers::open_managed_cache_file($path, 'c+');
        if (!$handle) {
            return array();
        }
        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);
            return array();
        }
        rewind($handle);
        $raw = stream_get_contents($handle, 65536);
        $data = is_string($raw) && '' !== trim($raw) ? UCP_Helpers::safe_json_decode_array($raw) : array();
        $counts = isset($data['status']) && is_array($data['status']) ? $data['status'] : array();
        $reset = self::write_locked_counter_state($handle, array(), is_string($raw) ? $raw : '');
        flock($handle, LOCK_UN);
        fclose($handle);
        if (!$reset) {
            return array();
        }

        $clean = array();
        foreach ($counts as $status => $count) {
            $status = strtoupper(sanitize_key((string) $status));
            if (in_array($status, array('HIT', 'HIT-304'), true) && absint($count) > 0) {
                $clean[$status] = absint($count);
            }
        }
        return $clean;
    }

    private static function restore_dropin_counts($counts) {
        $path = self::dropin_counter_path();
        if ('' === $path || empty($counts)) {
            return;
        }
        $handle = UCP_Helpers::open_managed_cache_file($path, 'c+');
        if (!$handle) {
            return;
        }
        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);
            return;
        }
        rewind($handle);
        $raw = stream_get_contents($handle, 65536);
        $data = is_string($raw) && '' !== trim($raw) ? UCP_Helpers::safe_json_decode_array($raw) : array();
        $current = isset($data['status']) && is_array($data['status']) ? $data['status'] : array();
        foreach ((array) $counts as $status => $count) {
            $status = strtoupper(sanitize_key((string) $status));
            if (!in_array($status, array('HIT', 'HIT-304'), true)) {
                continue;
            }
            $current[$status] = min(PHP_INT_MAX, absint($current[$status] ?? 0) + absint($count));
        }
        self::write_locked_counter_state($handle, $current, is_string($raw) ? $raw : '');
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private static function write_locked_counter_state($handle, $status, $fallback_raw = '') {
        if (!is_resource($handle)) {
            return false;
        }
        $payload = UCP_Helpers::safe_json_encode(array(
            'version'    => 1,
            'updated_at' => time(),
            'status'     => is_array($status) ? $status : array(),
        ));
        if (!is_string($payload) || '' === $payload) {
            return false;
        }
        rewind($handle);
        $length = strlen($payload);
        $written = 0;
        while ($written < $length) {
            $bytes = fwrite($handle, substr($payload, $written));
            if (!is_int($bytes) || $bytes <= 0) {
                self::restore_locked_counter_raw($handle, $fallback_raw);
                return false;
            }
            $written += $bytes;
        }
        if (!ftruncate($handle, $length) || !fflush($handle)) {
            self::restore_locked_counter_raw($handle, $fallback_raw);
            return false;
        }
        return true;
    }

    private static function restore_locked_counter_raw($handle, $raw) {
        if (!is_resource($handle) || !is_string($raw)) {
            return;
        }
        rewind($handle);
        $length = strlen($raw);
        $written = 0;
        while ($written < $length) {
            $bytes = fwrite($handle, substr($raw, $written));
            if (!is_int($bytes) || $bytes <= 0) {
                return;
            }
            $written += $bytes;
        }
        ftruncate($handle, $length);
        fflush($handle);
    }

    public static function import_dropin_counters() {
        if (!self::enabled()) {
            return 0;
        }
        $counts = self::take_dropin_counts();
        if (empty($counts)) {
            return 0;
        }
        $imported = 0;
        $remaining = array();
        foreach ($counts as $status => $count) {
            $left = absint($count);
            while ($left > 0) {
                $chunk = min(65000, $left);
                $saved = self::insert(array(
                    'event_type' => 'request',
                    'status' => $status,
                    'reason' => '',
                    'source' => 'dropin',
                    'scope' => 'aggregate',
                    'actor_id' => 0,
                    'sample_weight' => $chunk,
                    'context' => array('target_path' => '/'),
                ));
                if (!$saved) {
                    $remaining[$status] = isset($remaining[$status]) ? $remaining[$status] + $left : $left;
                    break;
                }
                $imported += $chunk;
                $left -= $chunk;
            }
        }
        if (!empty($remaining)) {
            self::restore_dropin_counts($remaining);
        }
        return $imported;
    }

    public static function summary($days = 7) {
        if (!is_scalar($days) && null !== $days) {
            $days = 7;
        }
        self::import_dropin_counters();
        global $wpdb;
        $days = min(30, max(1, absint($days)));
        $table = self::table_sql();
        if ('' === $table) {
            return self::empty_summary($days);
        }
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $request_rows = $wpdb->get_results($wpdb->prepare(
            'SELECT status, reason, SUM(sample_weight) AS estimated_total FROM ' . $table . " WHERE event_type = 'request' AND created_at >= %s GROUP BY status, reason ORDER BY estimated_total DESC",
            $cutoff
        ), ARRAY_A);
        $purge_rows = $wpdb->get_results($wpdb->prepare(
            'SELECT source, scope, COUNT(*) AS total FROM ' . $table . " WHERE event_type = 'purge' AND created_at >= %s GROUP BY source, scope ORDER BY total DESC",
            $cutoff
        ), ARRAY_A);
        $status = array();
        $reasons = array();
        $estimated = 0;
        foreach ((array) $request_rows as $row) {
            $count = absint($row['estimated_total']);
            $key = strtoupper(sanitize_key((string) $row['status']));
            $status[$key] = isset($status[$key]) ? $status[$key] + $count : $count;
            if (!empty($row['reason'])) {
                $reason = sanitize_key((string) $row['reason']);
                $reasons[$reason] = isset($reasons[$reason]) ? $reasons[$reason] + $count : $count;
            }
            $estimated += $count;
        }
        arsort($reasons);
        $hits = 0;
        foreach ($status as $key => $count) {
            if (false !== strpos($key, 'HIT') || 'STALE' === $key || 'REST-HIT' === $key) {
                $hits += $count;
            }
        }
        $misses = isset($status['MISS']) ? $status['MISS'] : 0;
        $misses += isset($status['REST-MISS']) ? $status['REST-MISS'] : 0;
        $denominator = $hits + $misses;
        return array(
            'enabled' => self::enabled(),
            'days' => $days,
            'estimated_requests' => $estimated,
            'hit_ratio' => $denominator > 0 ? round(($hits / $denominator) * 100, 1) : null,
            'status' => $status,
            'bypass_reasons' => array_slice($reasons, 0, 12, true),
            'purges' => array_values((array) $purge_rows),
            'sample_rate' => min(100, max(1, absint(UCP_Options::get('cache_insights_sample_rate', 1)))),
            'retention_days' => min(30, max(1, absint(UCP_Options::get('cache_insights_retention_days', 7)))),
            'coverage' => 'runtime_and_dropin',
            'direct_server_hits_tracked' => false,
            'direct_server_cache_enabled' => (bool) UCP_Options::get('enable_direct_cache_htaccess', 0),
        );
    }

    public static function recent_purges($limit = 20) {
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 20;
        }
        global $wpdb;
        $limit = min(100, max(1, absint($limit)));
        $table = self::table_sql();
        if ('' === $table) {
            return array();
        }
        $sql = $wpdb->prepare('SELECT id, path, source, scope, context, created_at FROM ' . $table . " WHERE event_type = 'purge' ORDER BY id DESC LIMIT %d", $limit);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        foreach ((array) $rows as &$row) {
            $row['context'] = !empty($row['context']) ? UCP_Helpers::safe_json_decode($row['context'], true) : array();
        }
        return array_values((array) $rows);
    }

    public static function reset() {
        $path = self::dropin_counter_path();
        if ('' !== $path && is_file($path)) {
            UCP_Helpers::safe_delete_file($path);
        }
        global $wpdb;
        $table = self::table_sql();
        if ('' === $table) {
            return false;
        }
        return false !== $wpdb->query('DELETE FROM ' . $table);
    }

    public static function cleanup() {
        self::import_dropin_counters();
        global $wpdb;
        $table = self::table_sql();
        if ('' === $table) {
            return 0;
        }
        $days = min(30, max(1, absint(UCP_Options::get('cache_insights_retention_days', 7))));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $deleted_total = 0;
        for ($batch = 0; $batch < 10; $batch++) {
            $deleted = $wpdb->query($wpdb->prepare('DELETE FROM ' . $table . ' WHERE created_at < %s LIMIT %d', $cutoff, 1000));
            if (!is_int($deleted) || $deleted < 0) {
                break;
            }
            $deleted_total += $deleted;
            if ($deleted < 1000) {
                break;
            }
        }
        return $deleted_total;
    }

    private static function empty_summary($days) {
        return array(
            'enabled' => self::enabled(),
            'days' => $days,
            'estimated_requests' => 0,
            'hit_ratio' => null,
            'status' => array(),
            'bypass_reasons' => array(),
            'purges' => array(),
            'sample_rate' => min(100, max(1, absint(UCP_Options::get('cache_insights_sample_rate', 1)))),
            'retention_days' => min(30, max(1, absint(UCP_Options::get('cache_insights_retention_days', 7)))),
            'coverage' => 'runtime_and_dropin',
            'direct_server_hits_tracked' => false,
            'direct_server_cache_enabled' => (bool) UCP_Options::get('enable_direct_cache_htaccess', 0),
        );
    }
}
