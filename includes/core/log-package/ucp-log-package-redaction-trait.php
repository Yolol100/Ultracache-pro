<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log package exports plugin-owned diagnostic tables with fixed SQL and sanitized output.

trait UCP_Log_Package_Redaction_Trait {
    public static function redact($value) {
        if (is_array($value)) {
            $clean = array();
            foreach ($value as $key => $item) {
                $key_string = (string) $key;
                if (preg_match('/(password|passwd|pwd|token|secret|api[_-]?key|license|nonce|cookie|authorization|auth|session|user[_-]?id|order|customer|email|phone|address|payment|cart|checkout)/i', $key_string)) {
                    $clean[$key] = '[redacted]';
                } else {
                    $clean[$key] = self::redact($item);
                }
            }
            return $clean;
        }
        if (is_string($value)) {
            if (preg_match('/(bearer\s+[a-z0-9._\-]+|sk_live_[a-z0-9_]+|sk_test_[a-z0-9_]+)/i', $value)) {
                return '[redacted]';
            }
            $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $value);
            $value = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[redacted-ip]', $value);
            $value = preg_replace_callback("#https?://[^\\s\"'<>]+#i", array(__CLASS__, 'redact_url_callback'), $value);
            if (defined('ABSPATH') && ABSPATH) {
                $value = str_replace(wp_normalize_path(ABSPATH), '[ABSPATH]/', wp_normalize_path($value));
            }
            if (strlen($value) > 5000) {
                return substr($value, 0, 5000) . '...[truncated]';
            }
            return sanitize_textarea_field($value);
        }
        if (is_scalar($value) || null === $value) {
            return $value;
        }
        return '[complex]';
    }

    protected static function redact_url_callback($matches) {
        $url = isset($matches[0]) ? (string) $matches[0] : '';
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '[redacted-url]';
        }
        $path = isset($parts['path']) ? $parts['path'] : '/';
        if (self::is_sensitive_log_url_path($path)) {
            $path = '/[redacted-path]';
        }
        return esc_url_raw($parts['scheme'] . '://' . $parts['host'] . $path . (isset($parts['query']) ? '?[redacted-query]' : ''));
    }

    protected static function is_sensitive_log_url_path($path) {
        return is_string($path) && (bool) preg_match('#/(order-pay|order-received|checkout|cart|my-account|account|payment|customer|session|token|nonce)(/|$)#i', $path);
    }

    protected static function add_redacted_text_file($zip, $name, $file) {
        $content = UCP_Helpers::read_file($file);
        if (!is_string($content)) {
            return;
        }
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $out = '';
        foreach ((array) $lines as $line) {
            if ('' === $line) {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $out .= wp_json_encode(self::redact($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                $out .= (string) self::redact($line) . "\n";
            }
            if (strlen($out) > 1024 * 1024) {
                $out .= '...[truncated]' . "\n";
                break;
            }
        }
        $zip->addFromString($name, $out);
    }
}
