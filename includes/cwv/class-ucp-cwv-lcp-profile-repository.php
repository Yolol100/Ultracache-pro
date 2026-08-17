<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned LCP profile storage and diagnostics; caching would make admin metrics stale.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic table identifiers are validated with UCP_Helpers::is_safe_table_name() and quoted before use; values remain prepared.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for measured LCP profile reads/writes.
 *
 * This keeps database persistence, profile staleness and summary queries outside
 * the public CWV REST controller while preserving the existing UCP_CWV API.
 */
final class UCP_CWV_LCP_Profile_Repository {
    /**
     * Persist a sanitized measured LCP hint for one URL/device pair.
     *
     * @param array<string,mixed> $data LCP hint data.
     * @return bool
     */
    public static function store($data) {
        global $wpdb;

        if (!is_array($data) || !function_exists('ucp_table_name') || !isset($wpdb) || !is_object($wpdb)) {
            return false;
        }

        $table = ucp_table_name('lcp');
        if ('' === $table || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table) || !self::table_exists($table)) {
            return false;
        }
        $table_sql = UCP_Helpers::quote_table_name($table);

        $url = isset($data['url']) ? UCP_CWV_LCP_Sanitizer::sanitize_page_url($data['url']) : '';
        $lcp_url = isset($data['lcp_url']) ? UCP_CWV_LCP_Sanitizer::sanitize_resource_url($data['lcp_url']) : '';
        if ('' === $url) {
            return false;
        }

        $device = self::normalize_device(isset($data['device']) ? $data['device'] : 'all');

        $element_json = isset($data['lcp_element_json']) && is_scalar($data['lcp_element_json']) ? (string) $data['lcp_element_json'] : '';
        $element = UCP_Helpers::safe_json_decode($element_json, true);
        $safe_element = UCP_CWV_LCP_Sanitizer::sanitize_element_array(is_array($element) ? $element : array());

        $lcp_type = UCP_CWV_LCP_Sanitizer::normalize_type(isset($data['lcp_type']) ? $data['lcp_type'] : (isset($safe_element['type']) ? $safe_element['type'] : ''));
        if ('' === $lcp_type) {
            $lcp_type = !empty($safe_element['background']) ? 'background-image' : ('' !== $lcp_url ? 'image' : 'text');
        }
        if ('' === $lcp_url && 'text' !== $lcp_type) {
            return false;
        }

        $srcset = isset($data['lcp_imagesrcset']) ? UCP_CWV_LCP_Sanitizer::sanitize_srcset($data['lcp_imagesrcset']) : '';
        $value_ms = isset($data['value_ms']) && is_scalar($data['value_ms']) && is_numeric($data['value_ms'])
            ? max(0, min((float) $data['value_ms'], (float) UCP_CWV::MAX_VALUE))
            : 0;
        $url_hash = hash('sha256', $url);
        $now = current_time('mysql');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned LCP table identifier is validated before quoting.
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, sample_count FROM {$table_sql} WHERE url_hash = %s AND device = %s LIMIT 1",
                $url_hash,
                $device
            ),
            ARRAY_A
        );

        $sample_count = is_array($existing) && isset($existing['sample_count']) ? absint($existing['sample_count']) + 1 : 1;
        $selector = UCP_CWV_LCP_Sanitizer::sanitize_selector(isset($data['lcp_selector']) ? $data['lcp_selector'] : (isset($safe_element['selector']) ? $safe_element['selector'] : ''));
        $source = isset($data['source']) && is_scalar($data['source']) ? sanitize_key((string) $data['source']) : 'rum';
        $confidence = self::calculate_confidence($lcp_type, $lcp_url, $safe_element, $value_ms, $sample_count, $source);

        $payload = array(
            'url'              => $url,
            'lcp_element_json' => $safe_element ? UCP_Helpers::safe_json_encode($safe_element) : '',
            'lcp_url'          => $lcp_url,
            'lcp_imagesrcset'  => $srcset,
            'lcp_type'         => $lcp_type,
            'lcp_selector'     => $selector,
            'confidence'       => $confidence,
            'value_ms'         => $value_ms,
            'last_measured'    => $now,
            'updated_at'       => $now,
            'profile_status'   => 'active',
        );

        if (is_array($existing) && !empty($existing['id'])) {
            $payload['sample_count'] = $sample_count;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned LCP table write.
            return false !== $wpdb->update(
                $table,
                $payload,
                array('id' => absint($existing['id'])),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%d'),
                array('%d')
            );
        }

        $payload['url_hash'] = $url_hash;
        $payload['device'] = $device;
        $payload['sample_count'] = 1;
        $payload['created_at'] = $now;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned LCP table write.
        return false !== $wpdb->insert(
            $table,
            $payload,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * Get an LCP profile safe enough for automatic preload/fetchpriority decisions.
     *
     * @param string $url                  URL to look up.
     * @param string $device               Device bucket.
     * @param bool   $high_confidence_only Require configured confidence threshold.
     * @return array<string,mixed>
     */
    public static function profile_for_url($url, $device = 'all', $high_confidence_only = true) {
        $row = self::hint_for_url($url, $device);
        if (!is_array($row) || empty($row)) {
            return array();
        }
        if (self::is_stale($row)) {
            return array();
        }
        if ($high_confidence_only) {
            $min = class_exists('UCP_Options') ? absint(UCP_Options::get('lcp_profile_min_confidence', UCP_CWV::MIN_PROFILE_CONFIDENCE)) : UCP_CWV::MIN_PROFILE_CONFIDENCE;
            if (absint($row['confidence'] ?? 0) < max(1, min(100, $min))) {
                return array();
            }
        }
        if (empty($row['lcp_url']) && 'text' !== ($row['lcp_type'] ?? '')) {
            return array();
        }
        if (!empty($row['lcp_url']) && !UCP_CWV_LCP_Sanitizer::is_resource_url_safe((string) $row['lcp_url'], isset($row['lcp_type']) ? (string) $row['lcp_type'] : 'image')) {
            return array();
        }
        return $row;
    }

    /**
     * Mark one measured LCP profile stale for safe rollback after a bad hint.
     *
     * @param string $url    URL.
     * @param string $device Device bucket.
     * @param string $reason Stale reason, reserved for future storage.
     * @return int|false
     */
    public static function mark_stale_for_url($url, $device = 'all', $reason = 'manual_rollback') {
        global $wpdb;
        unset($reason);

        if (!function_exists('ucp_table_name') || !isset($wpdb) || !is_object($wpdb)) {
            return false;
        }
        $table = ucp_table_name('lcp');
        if ('' === $table || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table) || !self::table_exists($table)) {
            return false;
        }
        $url = UCP_CWV_LCP_Sanitizer::sanitize_page_url($url);
        if ('' === $url) {
            return false;
        }
        $device = self::normalize_device($device);
        $where = array('url_hash' => hash('sha256', $url));
        $where_format = array('%s');
        if ('all' !== $device) {
            $where['device'] = $device;
            $where_format[] = '%s';
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned LCP rollback write.
        return $wpdb->update(
            $table,
            array('profile_status' => 'stale', 'updated_at' => current_time('mysql')),
            $where,
            array('%s', '%s'),
            $where_format
        );
    }

    /**
     * Check profile age/status.
     *
     * @param array $row LCP row.
     * @return bool
     */
    public static function is_stale($row) {
        if (!is_array($row)) {
            return true;
        }
        if (isset($row['profile_status'])) {
            if (!is_scalar($row['profile_status']) || 'active' !== sanitize_key((string) $row['profile_status'])) {
                return true;
            }
        }
        $last = isset($row['last_measured']) && is_scalar($row['last_measured']) ? self::local_mysql_timestamp((string) $row['last_measured']) : 0;
        if ($last <= 0) {
            return true;
        }
        $days = class_exists('UCP_Options') ? absint(UCP_Options::get('lcp_profile_max_age_days', UCP_CWV::DEFAULT_PROFILE_MAX_AGE_DAYS)) : UCP_CWV::DEFAULT_PROFILE_MAX_AGE_DAYS;
        return ($last + (max(1, $days) * DAY_IN_SECONDS)) < time();
    }


    /**
     * Convert a WordPress local-time MySQL value to a Unix timestamp.
     *
     * @param string $value Local MySQL datetime.
     * @return int
     */
    private static function local_mysql_timestamp($value) {
        return UCP_Helpers::local_mysql_timestamp($value);
    }

    /**
     * Mark all measured LCP profiles stale after layout/theme/global changes.
     *
     * @param string $reason Reason, reserved for future storage.
     * @return int|false
     */
    public static function mark_all_stale($reason = 'global_change') {
        global $wpdb;
        unset($reason);

        if (!function_exists('ucp_table_name') || !isset($wpdb) || !is_object($wpdb)) {
            return false;
        }
        $table = ucp_table_name('lcp');
        if ('' === $table || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table) || !self::table_exists($table)) {
            return false;
        }
        $now = current_time('mysql');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned LCP table write.
        return $wpdb->update(
            $table,
            array('profile_status' => 'stale', 'updated_at' => $now),
            array('profile_status' => 'active'),
            array('%s', '%s'),
            array('%s')
        );
    }

    /**
     * Diagnostic profile summary.
     *
     * @return array<string,mixed>
     */
    public static function summary() {
        global $wpdb;
        $out = array('total' => 0, 'active' => 0, 'high_confidence' => 0, 'stale' => 0, 'recent' => array());
        if (!function_exists('ucp_table_name') || !isset($wpdb) || !is_object($wpdb)) {
            return $out;
        }
        $table = ucp_table_name('lcp');
        if ('' === $table || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table) || !self::table_exists($table)) {
            return $out;
        }
        $table_sql = UCP_Helpers::quote_table_name($table);
        $min = class_exists('UCP_Options') ? absint(UCP_Options::get('lcp_profile_min_confidence', UCP_CWV::MIN_PROFILE_CONFIDENCE)) : UCP_CWV::MIN_PROFILE_CONFIDENCE;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned diagnostics with validated table identifier.
        $out['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_sql}");
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned diagnostics with validated table identifier.
        $out['active'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_sql} WHERE profile_status = 'active'");
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned diagnostics with validated table identifier.
        $out['high_confidence'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_sql} WHERE profile_status = 'active' AND confidence >= %d", $min));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned diagnostics with validated table identifier.
        $out['stale'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_sql} WHERE profile_status <> 'active'");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned diagnostics with validated table identifier.
        $sql = $wpdb->prepare("SELECT url, device, lcp_type, lcp_url, lcp_selector, confidence, profile_status, last_measured FROM {$table_sql} ORDER BY last_measured DESC LIMIT %d", 20);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is prepared above.
        $out['recent'] = $wpdb->get_results($sql, ARRAY_A);
        return $out;
    }

    /**
     * Get the most recent measured LCP hint for the current URL/device.
     *
     * @param string $url    URL to look up.
     * @param string $device Device bucket.
     * @return array<string,mixed>
     */
    public static function hint_for_url($url, $device = 'all') {
        global $wpdb;

        if (!function_exists('ucp_table_name') || !isset($wpdb) || !is_object($wpdb)) {
            return array();
        }

        $table = ucp_table_name('lcp');
        if ('' === $table || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table) || !self::table_exists($table)) {
            return array();
        }
        $table_sql = UCP_Helpers::quote_table_name($table);

        $url = UCP_CWV_LCP_Sanitizer::sanitize_page_url($url);
        if ('' === $url) {
            return array();
        }

        $device = self::normalize_device($device);

        $url_hash = hash('sha256', $url);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned LCP table identifier is validated before quoting.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_sql} WHERE url_hash = %s AND device = %s ORDER BY last_measured DESC LIMIT 1",
                $url_hash,
                $device
            ),
            ARRAY_A
        );

        if (!is_array($row) && 'all' !== $device) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned LCP table identifier is validated before quoting.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_sql} WHERE url_hash = %s AND device = %s ORDER BY last_measured DESC LIMIT 1",
                    $url_hash,
                    'all'
                ),
                ARRAY_A
            );
        }

        return is_array($row) ? $row : array();
    }

    /**
     * Diagnostic ATF/LCP hint summary.
     *
     * @param int $limit Maximum recent rows.
     * @return array<string,mixed>
     */
    public static function atf_summary($limit = 20) {
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 20;
        }
        global $wpdb;
        $limit = max(1, min(100, absint($limit)));
        $out = array('total' => 0, 'recent' => array());
        if (!function_exists('ucp_table_name') || !isset($wpdb) || !is_object($wpdb)) {
            return $out;
        }
        $table = ucp_table_name('lcp');
        if ('' === $table || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table) || !self::table_exists($table)) {
            return $out;
        }
        $table_sql = UCP_Helpers::quote_table_name($table);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- plugin-owned LCP diagnostics with validated table identifier.
        $out['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_sql}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned LCP diagnostics with validated table identifier.
        $sql = $wpdb->prepare("SELECT url, device, lcp_url, value_ms, sample_count, last_measured FROM {$table_sql} ORDER BY last_measured DESC LIMIT %d", $limit);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is prepared above with a validated table identifier and integer LIMIT placeholder.
        $out['recent'] = $wpdb->get_results($sql, ARRAY_A);
        return $out;
    }

    /**
     * Check whether the LCP table exists without creating it during frontend requests.
     *
     * @param string $table Fully qualified table name from ucp_table_name().
     * @return bool
     */
    public static function table_exists($table) {
        global $wpdb;
        if (!is_string($table) || '' === $table || !isset($wpdb) || !is_object($wpdb) || !class_exists('UCP_Helpers') || !UCP_Helpers::is_safe_table_name($table)) {
            return false;
        }

        $cache_key = 'ucp_lcp_table_exists_' . md5($table);
        $cached = wp_cache_get($cache_key, 'ultracache-pro');
        if (is_bool($cached)) {
            return $cached;
        }

        $like = $wpdb->esc_like($table);
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        $ok = is_string($exists) && strtolower($exists) === strtolower($table);
        wp_cache_set($cache_key, $ok, 'ultracache-pro', MINUTE_IN_SECONDS);
        return $ok;
    }

    /**
     * Calculate a conservative confidence score for automatic preload/fetchpriority use.
     *
     * @param string $type         LCP type.
     * @param string $lcp_url      Resource URL.
     * @param array  $element      Element metadata.
     * @param float  $value_ms     Measured LCP time.
     * @param int    $sample_count Sample count.
     * @param string $source       Source.
     * @return int
     */
    public static function calculate_confidence($type, $lcp_url, $element, $value_ms, $sample_count, $source = 'rum') {
        $type = is_scalar($type) ? (string) $type : '';
        $lcp_url = is_scalar($lcp_url) ? (string) $lcp_url : '';
        $element = is_array($element) ? $element : array();
        $value_ms = is_scalar($value_ms) && is_numeric($value_ms) ? (float) $value_ms : 0.0;
        $source = is_scalar($source) ? sanitize_key((string) $source) : 'rum';
        $score = 35;
        if (in_array($type, array('image', 'background-image', 'video-poster'), true) && '' !== $lcp_url) {
            $score += 25;
        }
        if (!empty($element['selector']) || !empty($element['id'])) {
            $score += 15;
        }
        if (!empty($element['background']) && 'background-image' === $type) {
            $score += 10;
        }
        if ((float) $value_ms > 0) {
            $score += 5;
        }
        if (absint($sample_count) >= 3) {
            $score += 10;
        } elseif (absint($sample_count) >= 2) {
            $score += 5;
        }
        if ('browser_scan' === $source) {
            $score = max($score, 92);
        } else {
            // Public RUM is advisory only. Keep it below the configured automatic-use threshold
            // so visitor samples cannot independently promote preload/fetchpriority candidates.
            $automatic_threshold = class_exists('UCP_Options')
                ? absint(UCP_Options::get('lcp_profile_min_confidence', UCP_CWV::MIN_PROFILE_CONFIDENCE))
                : UCP_CWV::MIN_PROFILE_CONFIDENCE;
            $score = min($score, max(0, min(99, $automatic_threshold - 1)));
        }
        if ('text' === $type) {
            $score = min($score, 75);
        }
        return max(0, min(100, absint($score)));
    }

    /**
     * @param string $device Device bucket.
     * @return string
     */
    private static function normalize_device($device) {
        $device = is_scalar($device) ? sanitize_key((string) $device) : 'all';
        if (!in_array($device, array('mobile', 'desktop', 'tablet', 'all'), true)) {
            $device = 'all';
        }
        return $device;
    }
}
