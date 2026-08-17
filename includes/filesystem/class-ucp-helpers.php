<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- var_export is intentionally used to generate a PHP config array, not for debug logging.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Helpers {
    use UCP_Helpers_Filesystem_Facade_Trait;
    use UCP_Helpers_URL_Facade_Trait;
    use UCP_Helpers_Dropin_Facade_Trait;
    use UCP_Helpers_Minify_Facade_Trait;

    public static function is_safe_table_name($table) {
        return is_string($table) && '' !== $table && 1 === preg_match('/^[A-Za-z0-9_]+$/', $table);
    }

    /**
     * preg_replace_callback that never silently destroys the subject.
     *
     * On very large markup (long inline JSON-LD, page-builder pages, big inline
     * data scripts) PCRE can hit pcre.backtrack_limit / pcre.recursion_limit and
     * return null with a non-zero preg_last_error(). When that happens this helper
     * returns the original subject unchanged instead of letting null cascade
     * through the optimization pipeline (which would drop every optimization on
     * that page and, in the CDN path, could even blank the buffer). The optional
     * by-reference $count is preserved (0 on failure, since nothing was replaced).
     *
     * @param string|array $pattern
     * @param callable     $callback
     * @param string       $subject
     * @param int          $limit
     * @param int|null     $count
     * @return string The replaced subject, or the original subject on PCRE failure.
     */
    public static function safe_preg_replace_callback($pattern, $callback, $subject, $limit = -1, &$count = null) {
        $count = 0;
        if (!is_string($subject)) {
            return $subject;
        }
        if (!is_callable($callback)) {
            return $subject;
        }
        try {
            $result = @preg_replace_callback($pattern, $callback, $subject, $limit, $count);
        } catch (Throwable $exception) {
            $count = 0;
            return $subject;
        }
        if (null === $result || PREG_NO_ERROR !== preg_last_error()) {
            $count = 0;
            return $subject;
        }
        return $result;
    }

    /**
     * preg_replace counterpart of {@see safe_preg_replace_callback()}.
     *
     * Returns the original subject unchanged when PCRE fails, so a backtrack-limit
     * error on large markup degrades to a no-op instead of nulling the document.
     *
     * @param string|array $pattern
     * @param string|array $replacement
     * @param string       $subject
     * @param int          $limit
     * @param int|null     $count
     * @return string The replaced subject, or the original subject on PCRE failure.
     */
    public static function safe_preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null) {
        $count = 0;
        if (!is_string($subject)) {
            return $subject;
        }
        try {
            $result = @preg_replace($pattern, $replacement, $subject, $limit, $count);
        } catch (Throwable $exception) {
            $count = 0;
            return $subject;
        }
        if (null === $result || PREG_NO_ERROR !== preg_last_error()) {
            $count = 0;
            return $subject;
        }
        return $result;
    }

    /**
     * Regex replacement for sanitizers. Failure returns the explicit safe fallback.
     *
     * @param string|array $pattern
     * @param string|array $replacement
     * @param mixed        $subject
     * @param int          $limit
     * @param int|null     $count
     * @param string       $fallback
     * @return string
     */
    public static function sanitize_preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null, $fallback = '') {
        if (!is_scalar($fallback) && null !== $fallback) {
            $fallback = '';
        }
        $count = 0;
        if (!is_string($subject)) {
            return (string) $fallback;
        }
        try {
            $result = @preg_replace($pattern, $replacement, $subject, $limit, $count);
        } catch (Throwable $exception) {
            $count = 0;
            return (string) $fallback;
        }
        if (null === $result || PREG_NO_ERROR !== preg_last_error()) {
            $count = 0;
            return (string) $fallback;
        }
        return (string) $result;
    }

    /**
     * Regex replacement for privacy redaction. Failure never returns the secret-bearing input.
     *
     * @param string|array $pattern
     * @param string|array $replacement
     * @param mixed        $subject
     * @param int          $limit
     * @param int|null     $count
     * @return string
     */
    public static function redact_preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null) {
        return self::sanitize_preg_replace($pattern, $replacement, $subject, $limit, $count, '[redacted]');
    }

    /**
     * Bounded JSON encoder with the same false-on-failure contract as wp_json_encode().
     *
     * @param mixed $value
     * @param int   $flags
     * @param int   $depth
     * @return string|false
     */
    public static function safe_json_encode($value, $flags = 0, $depth = 64) {
        if (!is_scalar($depth) && null !== $depth) {
            $depth = 64;
        }
        $depth = max(1, min(128, absint($depth)));
        try {
            $encoded = wp_json_encode($value, (int) $flags, $depth);
        } catch (Throwable $exception) {
            return false;
        }
        if (!is_string($encoded)) {
            return false;
        }
        $max_bytes = max(KB_IN_BYTES, min(20 * MB_IN_BYTES, absint(apply_filters('ucp_json_max_encoded_bytes', 5 * MB_IN_BYTES, $value))));
        if (strlen($encoded) > $max_bytes) {
            return false;
        }
        return $encoded;
    }

    /**
     * Bounded JSON decoder. Invalid, oversized or over-deep data returns null.
     *
     * @param mixed    $json
     * @param bool|null $associative
     * @param int      $depth
     * @param int      $flags
     * @return mixed
     */
    public static function safe_json_decode($json, $associative = null, $depth = 64, $flags = 0) {
        if (!is_scalar($depth) && null !== $depth) {
            $depth = 64;
        }
        if (!is_string($json)) {
            return null;
        }
        $max_bytes = max(KB_IN_BYTES, min(20 * MB_IN_BYTES, absint(apply_filters('ucp_json_max_decoded_bytes', 5 * MB_IN_BYTES, $json))));
        if (strlen($json) > $max_bytes || false !== strpos($json, "\0")) {
            return null;
        }
        $depth = max(1, min(128, absint($depth)));
        try {
            $decoded = json_decode($json, $associative, $depth, (int) $flags);
        } catch (Throwable $exception) {
            return null;
        }
        return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
    }

    /**
     * Encode JSON with an explicit valid JSON fallback.
     *
     * @param mixed  $value
     * @param string $fallback Valid JSON literal used when encoding fails.
     * @param int    $flags
     * @param int    $depth
     * @return string
     */
    public static function safe_json_encode_or($value, $fallback = 'null', $flags = 0, $depth = 64) {
        if (!is_scalar($depth) && null !== $depth) {
            $depth = 64;
        }
        $fallback = in_array($fallback, array('null', '{}', '[]', '""'), true) ? $fallback : 'null';
        $encoded = self::safe_json_encode($value, $flags, $depth);
        return is_string($encoded) && '' !== $encoded ? $encoded : $fallback;
    }

    /**
     * Encode data for an inline script without permitting HTML delimiter injection.
     *
     * @param mixed  $value
     * @param string $fallback
     * @param int    $flags
     * @param int    $depth
     * @return string
     */
    public static function safe_inline_json($value, $fallback = 'null', $flags = 0, $depth = 64) {
        if (!is_scalar($flags) && null !== $flags) {
            $flags = 0;
        }
        if (!is_scalar($depth) && null !== $depth) {
            $depth = 64;
        }
        $flags |= JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        return self::safe_json_encode_or($value, $fallback, $flags, $depth);
    }

    /**
     * Decode a JSON object or list and return an array fallback for every invalid shape.
     *
     * @param mixed $json
     * @param array $fallback
     * @param int   $depth
     * @param int   $flags
     * @return array
     */
    public static function safe_json_decode_array($json, $fallback = array(), $depth = 64, $flags = 0, $max_items = 5000, $max_scalar_bytes = 1048576) {
        if (!is_scalar($depth) && null !== $depth) {
            $depth = 64;
        }
        $decoded = self::safe_json_decode($json, true, $depth, $flags);
        if (!is_array($decoded)) {
            return is_array($fallback) ? $fallback : array();
        }
        $bounded = self::bounded_data_array($decoded, $max_items, min(8, max(1, absint($depth))), $max_scalar_bytes);
        return is_array($bounded) ? $bounded : (is_array($fallback) ? $fallback : array());
    }

    /**
     * Validate a nested data array without applying request unslashing.
     *
     * @param mixed $value
     * @param int   $max_items
     * @param int   $max_depth
     * @param int   $max_scalar_bytes
     * @param int   $max_key_bytes
     * @return array|false
     */
    public static function bounded_data_array($value, $max_items = 5000, $max_depth = 8, $max_scalar_bytes = 1048576, $max_key_bytes = 256) {
        if (!is_array($value)) {
            return false;
        }
        $max_items = max(1, min(10000, absint($max_items)));
        $max_depth = max(1, min(16, absint($max_depth)));
        $max_scalar_bytes = max(1, min(5 * MB_IN_BYTES, absint($max_scalar_bytes)));
        $max_key_bytes = max(1, min(512, absint($max_key_bytes)));
        $remaining = $max_items;
        $normalized = self::normalize_bounded_input_value($value, 0, $remaining, $max_depth, $max_scalar_bytes, $max_key_bytes);
        return is_array($normalized) ? $normalized : false;
    }

    /**
     * Atomically serialize a value to a plugin-managed JSON file.
     *
     * @param string $path
     * @param mixed  $value
     * @param int    $flags
     * @param int    $depth
     * @return bool
     */
    public static function write_json_file_atomic($path, $value, $flags = 0, $depth = 64) {
        $encoded = self::safe_json_encode($value, $flags, $depth);
        return is_string($encoded) && '' !== $encoded && self::write_file_atomic($path, $encoded);
    }

    /**
     * Append one valid JSON line to a plugin-managed log file.
     *
     * @param string $path
     * @param mixed  $value
     * @param int    $flags
     * @param int    $depth
     * @return bool
     */
    public static function append_json_line($path, $value, $flags = 0, $depth = 64) {
        if (!is_scalar($depth) && null !== $depth) {
            $depth = 64;
        }
        $encoded = self::safe_json_encode($value, $flags, $depth);
        $max_line_bytes = max(KB_IN_BYTES, min(MB_IN_BYTES, absint(apply_filters('ucp_jsonl_max_line_bytes', 256 * KB_IN_BYTES, $path))));
        return is_string($encoded) && '' !== $encoded
            && strlen($encoded) <= $max_line_bytes
            && self::append_file($path, $encoded . "\n");
    }

    /**
     * Return a deterministic, bounded list of regular files under trusted roots.
     *
     * @param string $pattern Glob pattern.
     * @param int    $max_files Maximum returned files.
     * @param array  $allowed_roots Optional trusted roots.
     * @return array
     */
    public static function safe_glob_files($pattern, $max_files = 1000, $allowed_roots = array()) {
        if (!is_scalar($max_files) && null !== $max_files) {
            $max_files = 1000;
        }
        if (!is_string($pattern) || '' === $pattern || strlen($pattern) > 4096 || false !== strpos($pattern, "\0")) {
            return array();
        }
        $max_files = max(1, min(5000, absint($max_files)));
        if (empty($allowed_roots)) {
            $allowed_roots = array();
            if (defined('UCP_CACHE_DIR')) {
                $allowed_roots[] = UCP_CACHE_DIR;
            }
            if (defined('UCP_PATH')) {
                $allowed_roots[] = UCP_PATH;
            }
            if (function_exists('wp_upload_dir')) {
                $uploads = wp_upload_dir();
                if (is_array($uploads) && !empty($uploads['basedir'])) {
                    $allowed_roots[] = $uploads['basedir'];
                }
            }
        }
        $trusted = array();
        foreach ((array) $allowed_roots as $root) {
            $real = is_string($root) ? realpath($root) : false;
            if ($real) {
                $trusted[] = trailingslashit(wp_normalize_path($real));
            }
        }
        if (empty($trusted)) {
            return array();
        }
        $directory = dirname($pattern);
        $mask = basename($pattern);
        if ('' === $mask || preg_match('/[\[\]{}]/', $mask) || preg_match('/[*?]/', $directory)) {
            return array();
        }
        $real_directory = realpath($directory);
        if (!$real_directory || is_link($directory) || !is_dir($real_directory) || !is_readable($real_directory)) {
            return array();
        }
        $normalized_directory = trailingslashit(wp_normalize_path($real_directory));
        $directory_allowed = false;
        foreach ($trusted as $root) {
            if (0 === strpos($normalized_directory, $root)) {
                $directory_allowed = true;
                break;
            }
        }
        if (!$directory_allowed) {
            return array();
        }
        $max_scanned = max($max_files, min(50000, absint(apply_filters('ucp_safe_glob_scan_max_entries', max(5000, $max_files * 10), $pattern))));
        $files = array();
        $scanned = 0;
        try {
            $iterator = new FilesystemIterator($real_directory, FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $entry) {
                ++$scanned;
                if ($scanned > $max_scanned || count($files) >= $max_files) {
                    break;
                }
                $name = $entry->getFilename();
                $matches = function_exists('fnmatch')
                    ? fnmatch($mask, $name)
                    : 1 === preg_match('/^' . str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($mask, '/')) . '$/D', $name);
                if (!$matches || $entry->isLink() || !$entry->isFile()) {
                    continue;
                }
                $real = $entry->getRealPath();
                if (!$real) {
                    continue;
                }
                $normalized = wp_normalize_path($real);
                foreach ($trusted as $root) {
                    if (0 === strpos($normalized, $root)) {
                        $files[] = $normalized;
                        break;
                    }
                }
            }
        } catch (UnexpectedValueException $exception) {
            return array();
        }
        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);
        return array_slice($files, 0, $max_files);
    }

    /**
     * Validate and unslash a bounded nested input array.
     *
     * @param mixed $value
     * @param int   $max_items Total number of nested entries.
     * @param int   $max_depth Maximum nested array depth.
     * @param int   $max_scalar_bytes Maximum bytes for one string value.
     * @param int   $max_key_bytes Maximum bytes for one string key.
     * @return array|false
     */
    public static function bounded_input_array($value, $max_items = 100, $max_depth = 4, $max_scalar_bytes = 8192, $max_key_bytes = 128) {
        if (!is_array($value)) {
            return false;
        }
        $max_items = max(1, min(5000, absint($max_items)));
        $max_depth = max(1, min(8, absint($max_depth)));
        $max_scalar_bytes = max(1, min(MB_IN_BYTES, absint($max_scalar_bytes)));
        $max_key_bytes = max(1, min(256, absint($max_key_bytes)));
        $remaining = $max_items;
        $normalized = self::normalize_bounded_input_value(wp_unslash($value), 0, $remaining, $max_depth, $max_scalar_bytes, $max_key_bytes);
        return is_array($normalized) ? $normalized : false;
    }

    /**
     * @param mixed $value
     * @param int   $depth
     * @param int   $remaining
     * @param int   $max_depth
     * @param int   $max_scalar_bytes
     * @param int   $max_key_bytes
     * @return mixed
     */
    private static function normalize_bounded_input_value($value, $depth, &$remaining, $max_depth, $max_scalar_bytes, $max_key_bytes) {
        if (is_array($value)) {
            if ($depth >= $max_depth) {
                return false;
            }
            $normalized = array();
            foreach ($value as $key => $item) {
                if ($remaining <= 0) {
                    return false;
                }
                --$remaining;
                if (!is_int($key)) {
                    if (!is_string($key) || '' === $key || strlen($key) > $max_key_bytes || false !== strpos($key, "\0")) {
                        return false;
                    }
                }
                $normalized_item = self::normalize_bounded_input_value($item, $depth + 1, $remaining, $max_depth, $max_scalar_bytes, $max_key_bytes);
                if (false === $normalized_item && false !== $item) {
                    return false;
                }
                $normalized[$key] = $normalized_item;
            }
            return $normalized;
        }
        if (null === $value || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value) || strlen($value) > $max_scalar_bytes || false !== strpos($value, "\0")) {
            return false;
        }
        return $value;
    }

    /**
     * Read one bounded array from POST, optionally falling back to GET.
     *
     * @param string $key
     * @param array  $default
     * @param int    $max_items
     * @param int    $max_depth
     * @param int    $max_scalar_bytes
     * @param bool   $allow_get
     * @return array
     */
    public static function request_array($key, $default = array(), $max_items = 100, $max_depth = 4, $max_scalar_bytes = 8192, $allow_get = false) {
        if (!is_scalar($key) && null !== $key) {
            $key = '';
        }
        $key = (string) $key;
        if ('' === $key || 1 !== preg_match('/^[A-Za-z0-9_-]+$/D', $key)) {
            return is_array($default) ? $default : array();
        }
        $value = null;
        if (isset($_POST[$key]) && is_array($_POST[$key])) {
            $value = $_POST[$key];
        } elseif ($allow_get && isset($_GET[$key]) && is_array($_GET[$key])) {
            $value = $_GET[$key];
        }
        if (null === $value) {
            return is_array($default) ? $default : array();
        }
        $normalized = self::bounded_input_array($value, $max_items, $max_depth, $max_scalar_bytes);
        return is_array($normalized) ? $normalized : (is_array($default) ? $default : array());
    }

    /**
     * Return bounded query arguments, or false when the request shape is unsafe.
     *
     * @param int $max_items
     * @param int $max_depth
     * @param int $max_scalar_bytes
     * @return array|false
     */
    public static function query_args($max_items = 100, $max_depth = 4, $max_scalar_bytes = 8192) {
        if (empty($_GET)) {
            return array();
        }
        $normalized = self::bounded_input_array($_GET, $max_items, $max_depth, $max_scalar_bytes);
        if (!is_array($normalized)) {
            return false;
        }
        foreach (array_keys($normalized) as $key) {
            if (!is_string($key) || '' === $key || strlen($key) > 256 || false !== strpos($key, "\0")) {
                return false;
            }
        }
        return $normalized;
    }

    /**
     * Return bounded request cookies, or false when the cookie shape is unsafe.
     *
     * @param int $max_items
     * @param int $max_value_bytes
     * @return array|false
     */
    public static function cookie_map($max_items = 128, $max_value_bytes = 4096) {
        if (empty($_COOKIE)) {
            return array();
        }
        $normalized = self::bounded_input_array($_COOKIE, $max_items, 2, $max_value_bytes, 256);
        if (!is_array($normalized)) {
            return false;
        }
        foreach ($normalized as $key => $value) {
            if (!is_string($key) || '' === $key || strlen($key) > 256 || false !== strpos($key, "\0") || is_array($value)) {
                return false;
            }
        }
        return $normalized;
    }

    /**
     * Read one bounded scalar server value without array-to-string warnings or CRLF injection.
     *
     * @param string $key
     * @param string $default
     * @param int    $max_bytes
     * @return string
     */
    public static function server_value($key, $default = '', $max_bytes = 8192) {
        if (!is_scalar($key) && null !== $key) {
            $key = '';
        }
        if (!is_scalar($default) && null !== $default) {
            $default = '';
        }
        $key = strtoupper((string) $key);
        if (1 !== preg_match('/^[A-Z0-9_]+$/D', $key) || !isset($_SERVER[$key]) || !is_scalar($_SERVER[$key])) {
            return (string) $default;
        }
        $value = (string) wp_unslash($_SERVER[$key]);
        $max_bytes = max(1, min(1048576, absint($max_bytes)));
        if (strlen($value) > $max_bytes || false !== strpos($value, "\0") || false !== strpos($value, "\r") || false !== strpos($value, "\n")) {
            return (string) $default;
        }
        return $value;
    }

    /**
     * Return a canonical request method or an empty string for malformed input.
     *
     * @return string
     */
    public static function request_method() {
        $method = strtoupper(trim(self::server_value('REQUEST_METHOD', '', 16)));
        return in_array($method, array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'), true) ? $method : '';
    }

    /**
     * Read one bounded scalar request value from POST first, optionally falling back to GET.
     *
     * @param string $key
     * @param mixed  $default
     * @param int    $max_bytes
     * @param bool   $allow_get
     * @return mixed
     */
    public static function request_scalar($key, $default = '', $max_bytes = 4096, $allow_get = false) {
        if (!is_scalar($key)) {
            return $default;
        }
        $key = (string) $key;
        if ('' === $key || 1 !== preg_match('/^[A-Za-z0-9_-]+$/D', $key)) {
            return $default;
        }
        $value = null;
        if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
            $value = wp_unslash($_POST[$key]);
        } elseif ($allow_get && isset($_GET[$key]) && is_scalar($_GET[$key])) {
            $value = wp_unslash($_GET[$key]);
        }
        if (null === $value) {
            return $default;
        }
        $value = (string) $value;
        $max_bytes = max(1, min(1048576, absint($max_bytes)));
        if (strlen($value) > $max_bytes || false !== strpos($value, "\0")) {
            return $default;
        }
        return $value;
    }

    public static function quote_table_name($table) {
        if (!is_scalar($table)) {
            return '';
        }
        return '`' . str_replace('`', '``', (string) $table) . '`';
    }


    /**
     * Convert a WordPress local-time MySQL value to a Unix timestamp.
     *
     * @param string $value Local MySQL datetime.
     * @return int
     */
    public static function local_mysql_timestamp($value) {
        if (!is_scalar($value)) {
            return 0;
        }
        $value = trim((string) $value);
        if ('' === $value) {
            return 0;
        }
        if (function_exists('get_gmt_from_date')) {
            $gmt = get_gmt_from_date($value);
            if (is_string($gmt) && '' !== $gmt) {
                $timestamp = strtotime($gmt . ' UTC');
                return false === $timestamp ? 0 : (int) $timestamp;
            }
        }
        $timestamp = strtotime($value);
        return false === $timestamp ? 0 : (int) $timestamp;
    }

    /**
     * Normalize the explicit boolean confirmation values accepted by destructive actions.
     *
     * @param mixed $value Candidate value.
     * @return bool
     */
    public static function is_explicit_confirmation($value) {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            return false;
        }
        return in_array(strtolower(trim((string) $value)), array('1', 'true'), true);
    }

    /**
     * Build one normalized readiness-check record.
     *
     * @return array<string,mixed>
     */
    public static function readiness_check($key, $label, $ok, $weight, $pass, $fix) {
        return array(
            'key' => is_scalar($key) ? sanitize_key((string) $key) : '',
            'label' => $label,
            'ok' => (bool) $ok,
            'weight' => absint($weight),
            'message' => $ok ? $pass : $fix,
            'fix' => $fix,
        );
    }

    public static function new_without_constructor($class_name) {
        $reflector = new ReflectionClass($class_name);
        return $reflector->newInstanceWithoutConstructor();
    }

    /**
     * Whether frontend Testing Mode is enabled.
     *
     * `enable_asset_test_mode` already existed as a narrow asset test flag. It now
     * acts as the compatibility alias for the broader bounded Testing Mode layer.
     * Older installs and exports keep working through `enable_asset_test_mode`.
     *
     * @return bool
     */
    public static function testing_mode_ttl_seconds() {
        $ttl = absint(apply_filters('ucp_testing_mode_ttl_seconds', 4 * HOUR_IN_SECONDS));
        return max(15 * MINUTE_IN_SECONDS, min(DAY_IN_SECONDS, $ttl));
    }

    public static function testing_mode_expires_at() {
        return absint(get_option('ucp_testing_mode_expires_at', 0));
    }

    public static function testing_mode_remaining_seconds() {
        $expires_at = self::testing_mode_expires_at();
        return $expires_at > 0 ? max(0, $expires_at - time()) : 0;
    }

    public static function testing_mode_active() {
        if (!class_exists('UCP_Options')) {
            return false;
        }

        $active = (bool) apply_filters(
            'ucp_testing_mode_active',
            !empty(UCP_Options::get('testing_mode', 0)) || !empty(UCP_Options::get('enable_asset_test_mode', 0))
        );
        if (!$active) {
            return false;
        }

        $expires_at = self::testing_mode_expires_at();
        return 0 === $expires_at || time() < $expires_at;
    }

    /**
     * Whether the current request may see Testing Mode frontend optimizations.
     *
     * @return bool
     */
    public static function current_user_can_preview_testing_mode() {
        return is_user_logged_in() && current_user_can('manage_options');
    }

    /**
     * Central frontend gate for cache/optimization modules.
     *
     * When Testing Mode is disabled, behaviour is unchanged. When enabled,
     * frontend optimizations run only for admins so public visitors keep seeing
     * the production-safe version until the admin disables Testing Mode.
     *
     * @return bool
     */
    public static function frontend_optimizations_allowed() {
        if (!self::testing_mode_active()) {
            return true;
        }

        return (bool) apply_filters(
            'ucp_testing_mode_allow_current_request',
            self::current_user_can_preview_testing_mode()
        );
    }

    /**
     * Shared permission callback for UltraCache Pro admin REST routes.
     *
     * Single source of truth used by every admin REST controller: requires the
     * manage_options capability, and a valid wp_rest nonce for mutating methods.
     * The nonce requirement for mutations can be toggled with the
     * `ucp_rest_require_nonce_for_mutations` filter (default true).
     *
     * @param WP_REST_Request|null $request Current request.
     * @return true|WP_Error
     */
    public static function rest_admin_permission_check($request = null) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('ucp_forbidden', __('Je hebt geen rechten om UltraCache Pro te beheren.', 'ultracache-pro'), array('status' => 403));
        }

        $method_value = ($request instanceof WP_REST_Request) ? $request->get_method() : 'GET';
        $method = is_scalar($method_value) ? strtoupper((string) $method_value) : 'GET';
        $require_nonce = apply_filters('ucp_rest_require_nonce_for_mutations', true, $request);
        if ($require_nonce && !in_array($method, array('GET', 'HEAD', 'OPTIONS'), true)) {
            $nonce_value = ($request instanceof WP_REST_Request) ? $request->get_header('x_wp_nonce') : '';
            if ((!is_scalar($nonce_value) || '' === (string) $nonce_value) && $request instanceof WP_REST_Request) {
                $nonce_value = $request->get_param('_wpnonce');
            }
            $nonce = is_scalar($nonce_value) ? sanitize_text_field(wp_unslash((string) $nonce_value)) : '';
            if ('' === $nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('ucp_rest_nonce_missing', __('Ongeldige of ontbrekende REST-beveiligingstoken.', 'ultracache-pro'), array('status' => 403));
            }
        }

        return true;
    }

    /**
     * Require an authenticated POST request for a state-changing admin action.
     *
     * admin-post.php dispatches both GET and POST requests. Centralizing this
     * guard prevents mutating actions from being triggered by link prefetchers,
     * crawlers, browser history replays, or stale bookmarked URLs.
     *
     * @param string $nonce_action Nonce action name.
     * @param string $message Optional capability error message.
     * @return void
     */
    public static function require_post_admin_action($nonce_action, $message = '') {
        if (!current_user_can('manage_options')) {
            $message = '' !== (string) $message ? (string) $message : __('Je hebt geen rechten om UltraCache Pro te beheren.', 'ultracache-pro');
            wp_die(esc_html($message), '', array('response' => 403));
        }

        $request_method = self::request_method();
        if ('POST' !== $request_method) {
            wp_die(esc_html__('Deze beheeractie vereist een POST-verzoek.', 'ultracache-pro'), '', array('response' => 405));
        }

        check_admin_referer((string) $nonce_action);
    }

    /**
     * Return the best available relative asset path.
     *
     * Production builds use a valid smaller `.min` asset when available and fall
     * back to the readable source when a minified file is missing or only a copy.
     *
     * @param string $relative Relative path under the plugin root.
     * @return string The chosen relative path (with or without `.min`).
     */
    public static function asset_path($relative) {
        return is_scalar($relative) ? UCP_Asset_Resolver::relative((string) $relative) : '';
    }

    /**
     * Service facade for filesystem operations. Prefer this in new code.
     *
     * @return UCP_Filesystem_Service
     */
    public static function filesystem_service() {
        return UCP_Filesystem_Service::instance();
    }

    /**
     * Service facade for URL validation/normalization. Prefer this in new code.
     *
     * @return UCP_URL_Validator
     */
    public static function url_validator() {
        return UCP_URL_Validator::instance();
    }

    /**
     * Service facade for drop-in management. Prefer this in new code.
     *
     * @return UCP_Dropin_Manager
     */
    public static function dropin_manager() {
        return UCP_Dropin_Manager::instance();
    }

    /**
     * Service facade for minification/log helper methods. Prefer this in new code.
     *
     * @return UCP_Minify_Service
     */
    public static function minify_service() {
        return UCP_Minify_Service::instance();
    }


}
