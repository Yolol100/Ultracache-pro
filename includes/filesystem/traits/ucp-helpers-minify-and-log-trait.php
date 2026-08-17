<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_Minify_And_Log_Trait {
    public static function minify_css($content) {
        if (!is_scalar($content)) {
            return '';
        }
        $content = (string) $content;
        if ('' === trim($content)) {
            return '';
        }
        $parser_class = function_exists('ucp_dependency_class') ? ucp_dependency_class('Sabberworm\\CSS\\Parser') : '';
        $can_use_vendor_css_minifier = true;
        if ('' !== $parser_class) {
            try {
                $parser = new $parser_class($content);
                $parser->parse();
            } catch (Throwable $e) {
                $can_use_vendor_css_minifier = false;
                if (class_exists('UCP_Diagnostics')) {
                    UCP_Diagnostics::record('assets', 'CSS parser library failed; original CSS preserved.', array('exception' => get_class($e)));
                }
            }
        }

        $minifier_class = function_exists('ucp_dependency_class') ? ucp_dependency_class('MatthiasMullie\\Minify\\CSS') : '';
        if ($can_use_vendor_css_minifier && '' !== $minifier_class) {
            try {
                $minifier = new $minifier_class($content);
                if (method_exists($minifier, 'setMaxImportSize')) {
                    $minifier->setMaxImportSize(0);
                }
                $minified = $minifier->minify();
                if (is_string($minified) && '' !== trim($minified)) {
                    return trim($minified);
                }
            } catch (Throwable $e) {
                if (class_exists('UCP_Diagnostics')) {
                    UCP_Diagnostics::record('assets', 'CSS minifier library failed; original CSS preserved.', array('exception' => get_class($e)));
                }
            }
        }

        // CSS strings, data URLs and calc() operators are grammar-sensitive.
        return trim($content);
    }

    public static function minify_js($content) {
        if (!is_scalar($content)) {
            return '';
        }
        $content = (string) $content;
        if ('' === trim($content)) {
            return '';
        }
        $minifier_class = function_exists('ucp_dependency_class') ? ucp_dependency_class('MatthiasMullie\\Minify\\JS') : '';
        if ('' !== $minifier_class) {
            try {
                $minifier = new $minifier_class($content);
                $minified = $minifier->minify();
                if (is_string($minified) && '' !== trim($minified)) {
                    return trim($minified);
                }
            } catch (Throwable $e) {
                if (class_exists('UCP_Diagnostics')) {
                    UCP_Diagnostics::record('assets', 'JS minifier library failed; original JavaScript preserved.', array('exception' => get_class($e)));
                }
            }
        }

        // JavaScript cannot be safely minified with whitespace heuristics because
        // regex literals, ASI and adjacent unary operators are grammar-sensitive.
        return trim($content);
    }

    public static function get_used_css_path($url = '') {
        return UCP_CACHE_DIR . 'used-css/' . self::css_artifact_key_for_url($url) . '.css';
    }

    public static function get_critical_css_path($url = '') {
        return UCP_CACHE_DIR . 'critical-css/' . self::css_artifact_key_for_url($url) . '.css';
    }

    /**
     * Cache key for used/critical CSS artifacts. Defaults to the per-URL key
     * (unchanged behaviour). When template grouping is enabled via the
     * 'ucp_css_artifact_template_grouping' filter, singular posts of the same
     * post type + page template share one artifact (fewer generations, faster
     * warmup, less disk). Grouping can under-cache a page that uses blocks absent
     * from the template's first-generated artifact, so validate on staging before
     * enabling. Page-cache keying is deliberately not affected.
     */
    public static function css_artifact_key_for_url($url = '') {
        $per_url = self::cache_key_for_url($url);
        // 'template' scope shares one Used/Critical CSS artifact across singular posts of the same
        // post type + page template (the QUIC.cloud "CCSS per post type" model): far fewer
        // generations, faster warmup, less disk. The filter still allows per-call overrides.
        $grouping = 'template' === UCP_Options::get('css_artifact_scope', 'url');
        if (!apply_filters('ucp_css_artifact_template_grouping', $grouping, $url)) {
            return $per_url;
        }
        if (!$url) {
            $url = self::current_full_url();
        }
        $url = self::enforce_local_url($url);
        $post_id = function_exists('url_to_postid') ? (int) url_to_postid($url) : 0;
        if ($post_id <= 0) {
            // Archives, front page, search, 404, taxonomy: keep per-URL keying.
            return $per_url;
        }
        $post_type = (string) get_post_type($post_id);
        $template = (string) get_post_meta($post_id, '_wp_page_template', true);
        if ('' === $template) {
            $template = 'default';
        }
        $signature = 'tpl-' . $post_type . '-' . substr(md5($template), 0, 8) . '-' . self::user_state_suffix();
        return sanitize_file_name((string) apply_filters('ucp_css_artifact_key', $signature, $url, $post_id));
    }

    public static function has_persistent_object_cache() {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    public static function is_likely_cache_server_present() {
        $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']))) : '';

        // Trust only server/runtime-owned signals here. Inbound HTTP_* headers are
        // visitor-controlled and must not create false cache-conflict warnings or
        // influence LiteSpeed/backend decisions.
        return false !== strpos($server, 'litespeed')
            || false !== strpos($server, 'openlitespeed')
            || !empty($_SERVER['LSWS_EDITION'])
            || !empty($_SERVER['LITESPEED_CACHE'])
            || !empty($_SERVER['LSCACHE_VERSION']);
    }

    /**
     * Redact sensitive data before writing legacy plain-text logs.
     *
     * The event log is readable from the admin diagnostics surface and can be
     * exported in support packages. Keep operational context, but do not store
     * query strings, e-mail addresses, IPs, tokens or payment/order markers in
     * the plain-text file.
     */
    public static function redact_log_text($message) {
        if (!is_scalar($message)) {
            return '';
        }
        $message = wp_strip_all_tags((string) $message);
        $message = UCP_Helpers::redact_preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $message);
        $message = UCP_Helpers::redact_preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[redacted-ip]', $message);
        $message = UCP_Helpers::safe_preg_replace_callback(
            '/(?<![a-f0-9:])\[?(?=[a-f0-9:]*:)[a-f0-9:]{2,45}\]?(?![a-f0-9:])/i',
            static function($matches) {
                $candidate = trim((string) ($matches[0] ?? ''), '[]');
                return filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[redacted-ip]' : (string) ($matches[0] ?? '');
            },
            $message
        );
        $message = UCP_Helpers::redact_preg_replace('/\b(?:bearer\s+[a-z0-9._\-]+|sk_live_[a-z0-9_]+|sk_test_[a-z0-9_]+)\b/i', '[redacted-secret]', $message);
        $message = UCP_Helpers::redact_preg_replace('/\b(token|api[_-]?key|secret|password|passwd|pwd|nonce|user[_-]?id|session(?:[_-]?[a-z0-9_]+)?|payment(?:[_-]?[a-z0-9_]+)?|order(?:[_-]?[a-z0-9_]+)?|customer(?:[_-]?[a-z0-9_]+)?|cart(?:[_-]?[a-z0-9_]+)?|checkout(?:[_-]?[a-z0-9_]+)?)(\s*(?:=|:)\s*|\s+)([^\s&]+)/i', '$1$2[redacted]', $message);
        $message = UCP_Helpers::safe_preg_replace_callback('#https?://[^\s"\'<>]+#i', array(__CLASS__, 'redact_log_url_callback'), $message);
        $message = sanitize_textarea_field((string) $message);
        if (strlen($message) > 5000) {
            $message = substr($message, 0, 5000) . '...[truncated]';
        }
        return $message;
    }

    public static function redact_log_url($url) {
        if (!is_scalar($url)) {
            return '';
        }
        $url = esc_url_raw((string) $url);
        if ('' === $url) {
            return '';
        }
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $path = isset($parts['path']) ? $parts['path'] : '/';
        if (self::is_sensitive_log_url_path($path)) {
            $path = '/[redacted-path]';
        }
        return esc_url_raw($parts['scheme'] . '://' . $parts['host'] . $path . (!empty($parts['query']) ? '?[redacted-query]' : ''));
    }

    protected static function is_sensitive_log_url_path($path) {
        return is_string($path) && (bool) preg_match('#/(order-pay|order-received|checkout|cart|my-account|account|payment|customer|session|token|nonce)(/|$)#i', $path);
    }

    protected static function redact_log_url_callback($matches) {
        if (!is_array($matches)) {
            $matches = is_scalar($matches) ? array($matches) : array();
        }
        $matches = array_filter($matches, 'is_scalar');
        $url = isset($matches[0]) ? (string) $matches[0] : '';
        return self::redact_log_url($url);
    }

    public static function log($message) {
        if (!class_exists('UCP_Options') || !UCP_Options::get('enable_logs')) {
            return false;
        }

        $file = UCP_CACHE_DIR . 'logs/events.log';
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . self::redact_log_text($message) . "\n";
        if (!self::append_file($file, $line)) {
            return false;
        }

        self::rotate_legacy_log_if_needed($file);
        return true;
    }

    protected static function rotate_legacy_log_if_needed($file) {
        if (!is_scalar($file) && null !== $file) {
            $file = '';
        }
        $max_bytes = max(64 * KB_IN_BYTES, min(100 * MB_IN_BYTES, absint(apply_filters('ucp_log_file_max_bytes', 5 * MB_IN_BYTES))));
        if (!is_file($file) || (int) filesize($file) <= $max_bytes) {
            return;
        }

        $suffix = wp_generate_password(8, false, false);
        $rotated = dirname($file) . '/events-' . gmdate('Ymd-His') . '-' . $suffix . '.log';
        self::move_file($file, $rotated);
    }

    public static function log_throttled($key, $message, $ttl = HOUR_IN_SECONDS) {
        if (!class_exists('UCP_Options') || !UCP_Options::get('enable_logs')) {
            return false;
        }

        $key = 'ucp_log_throttle_' . md5((string) $key);
        if (get_transient($key)) {
            return false;
        }
        set_transient($key, 1, max(60, absint($ttl)));
        return self::log($message);
    }

    /**
     * Read a bounded prefix from a local file without loading the complete file.
     *
     * @param string $file      File path.
     * @param int    $max_bytes Maximum bytes to read.
     * @return string
     */
    public static function read_file_head($file, $max_bytes = 65536) {
        $file = is_scalar($file) ? (string) $file : '';
        if ('' === $file || !is_file($file) || !is_readable($file)) {
            return '';
        }
        $max_bytes = max(1024, min(4 * MB_IN_BYTES, absint($max_bytes)));
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- bounded local read avoids loading an arbitrary drop-in fully.
        $handle = fopen($file, 'rb');
        if (false === $handle) {
            return '';
        }
        $content = fread($handle, $max_bytes);
        fclose($handle);
        return is_string($content) ? $content : '';
    }

    /**
     * Read a bounded number of lines from the end of a local plugin-owned file.
     *
     * @param string $file     File path.
     * @param int    $lines    Maximum number of lines.
     * @param int    $max_read Maximum bytes to read from the end of the file.
     * @return string[]
     */
    public static function read_file_tail_lines($file, $lines = 50, $max_read = 0) {
        $file = is_scalar($file) ? (string) $file : '';
        if (
            '' === $file
            || !is_file($file)
            || !is_readable($file)
            || !self::is_safe_managed_write_target($file)
        ) {
            return array();
        }

        $lines = max(1, min(20000, absint($lines)));
        $size = (int) filesize($file);
        if ($size <= 0) {
            return array();
        }

        $max_read = absint($max_read);
        if ($max_read <= 0) {
            $max_read = max(64 * KB_IN_BYTES, min(4 * MB_IN_BYTES, $lines * 8 * KB_IN_BYTES));
        }
        $max_read = max(64 * KB_IN_BYTES, min(8 * MB_IN_BYTES, $max_read));
        $position = $size;
        $read = 0;
        $buffer = '';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- bounded reverse-read of a validated plugin-owned file avoids loading it fully.
        $handle = fopen($file, 'rb');
        if (false === $handle) {
            return array();
        }

        while ($position > 0 && $read < $max_read && substr_count($buffer, "\n") <= $lines) {
            $chunk_size = min(8 * KB_IN_BYTES, $position, $max_read - $read);
            $position -= $chunk_size;
            if (0 !== fseek($handle, $position)) {
                break;
            }
            $chunk = fread($handle, $chunk_size);
            if (!is_string($chunk) || '' === $chunk) {
                break;
            }
            $buffer = $chunk . $buffer;
            $read += strlen($chunk);
        }
        fclose($handle);

        if ($position > 0) {
            $first_newline = strpos($buffer, "\n");
            $buffer = false === $first_newline ? '' : substr($buffer, $first_newline + 1);
        }
        $content = preg_split('/\r\n|\r|\n/', $buffer, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($content)) {
            return array();
        }
        return array_slice($content, -1 * $lines);
    }

    public static function get_log_tail($lines = 50) {
        if (!is_scalar($lines) && null !== $lines) {
            $lines = 50;
        }
        $file = UCP_CACHE_DIR . 'logs/events.log';
        $content = self::read_file_tail_lines($file, max(1, min(500, absint($lines))));
        return array_map(array(__CLASS__, 'redact_log_text'), $content);
    }
}
