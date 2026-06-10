<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_Minify_And_Log_Trait {
    public static function minify_css($content) {
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
                    UCP_Diagnostics::record('assets', 'CSS parser library failed; using built-in fallback.', array('error' => $e->getMessage()));
                }
            }
        }

        $minifier_class = function_exists('ucp_dependency_class') ? ucp_dependency_class('MatthiasMullie\\Minify\\CSS') : '';
        if ($can_use_vendor_css_minifier && '' !== $minifier_class) {
            try {
                $minifier = new $minifier_class($content);
                if (method_exists($minifier, 'setMaxImportSize')) {
                    // Avoid inlining external or local assets into CSS during normal WordPress asset optimization.
                    $minifier->setMaxImportSize(0);
                }
                $minified = $minifier->minify();
                if (is_string($minified) && '' !== trim($minified)) {
                    return trim($minified);
                }
            } catch (Throwable $e) {
                if (class_exists('UCP_Diagnostics')) {
                    UCP_Diagnostics::record('assets', 'CSS minifier library failed; using built-in fallback.', array('error' => $e->getMessage()));
                }
            }
        }

        $content = preg_replace('!/\*[^*]*\*+(?:[^/*][^*]*\*+)*/!s', '', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $content);
        $content = str_replace(';}', '}', $content);
        return trim((string) $content);
    }

    public static function minify_js($content) {
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
                    UCP_Diagnostics::record('assets', 'JS minifier library failed; using built-in fallback.', array('error' => $e->getMessage()));
                }
            }
        }

        $out = '';
        $len = strlen($content);
        $state = 'code';
        $quote = '';
        $escape = false;
        $last_sig = '';

        for ($i = 0; $i < $len; $i++) {
            $ch = $content[$i];
            $next = ($i + 1 < $len) ? $content[$i + 1] : '';

            if ('string' === $state || 'template' === $state) {
                $out .= $ch;
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ('\\' === $ch) {
                    $escape = true;
                    continue;
                }
                if ($ch === $quote) {
                    $state = 'code';
                    $quote = '';
                    $last_sig = $ch;
                }
                continue;
            }

            if ('/' === $ch && '/' === $next) {
                while ($i < $len && !in_array($content[$i], array("\n", "\r"), true)) {
                    $i++;
                }
                $out .= ' ';
                continue;
            }
            if ('/' === $ch && '*' === $next) {
                $i += 2;
                while ($i + 1 < $len && !('*' === $content[$i] && '/' === $content[$i + 1])) {
                    $i++;
                }
                $i++;
                $out .= ' ';
                continue;
            }

            if ('"' === $ch || "'" === $ch || '`' === $ch) {
                $state = ('`' === $ch) ? 'template' : 'string';
                $quote = $ch;
                $out .= $ch;
                continue;
            }

            if (ctype_space($ch)) {
                $prev = '' !== $out ? substr($out, -1) : '';
                $j = $i + 1;
                while ($j < $len && ctype_space($content[$j])) {
                    $j++;
                }
                $n = $j < $len ? $content[$j] : '';
                if (preg_match('/[A-Za-z0-9_$]/', $prev) && preg_match('/[A-Za-z0-9_$]/', $n)) {
                    $out .= ' ';
                }
                continue;
            }

            if (preg_match('/[{}()\[\];,:?+*%=&|!<>~^-]/', $ch)) {
                $out = rtrim($out);
                $out .= $ch;
                $last_sig = $ch;
                continue;
            }

            $out .= $ch;
            if (!ctype_space($ch)) {
                $last_sig = $ch;
            }
        }

        $out = preg_replace('/\s+/', ' ', trim($out));
        return (string) $out;
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
        $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower((string) wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '';

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
        $message = wp_strip_all_tags((string) $message);
        $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $message);
        $message = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[redacted-ip]', $message);
        $message = preg_replace('/\b(?:bearer\s+[a-z0-9._\-]+|sk_live_[a-z0-9_]+|sk_test_[a-z0-9_]+)\b/i', '[redacted-secret]', $message);
        $message = preg_replace('/\b(token|api[_-]?key|secret|password|passwd|pwd|nonce|user[_-]?id|session(?:[_-]?[a-z0-9_]+)?|payment(?:[_-]?[a-z0-9_]+)?|order(?:[_-]?[a-z0-9_]+)?|customer(?:[_-]?[a-z0-9_]+)?|cart(?:[_-]?[a-z0-9_]+)?|checkout(?:[_-]?[a-z0-9_]+)?)=([^\s&]+)/i', '$1=[redacted]', $message);
        $message = preg_replace_callback('#https?://[^\s"\'<>]+#i', array(__CLASS__, 'redact_log_url_callback'), $message);
        $message = sanitize_textarea_field((string) $message);
        if (strlen($message) > 5000) {
            $message = substr($message, 0, 5000) . '...[truncated]';
        }
        return $message;
    }

    public static function redact_log_url($url) {
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
        $url = isset($matches[0]) ? (string) $matches[0] : '';
        return self::redact_log_url($url);
    }

    public static function log($message) {
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . self::redact_log_text($message) . "\n";
        self::append_file(UCP_CACHE_DIR . 'logs/events.log', $line);
    }

    public static function log_throttled($key, $message, $ttl = HOUR_IN_SECONDS) {
        $key = 'ucp_log_throttle_' . md5((string) $key);
        if (get_transient($key)) {
            return;
        }
        set_transient($key, 1, max(60, absint($ttl)));
        self::log($message);
    }

    public static function get_log_tail($lines = 50) {
        $file = UCP_CACHE_DIR . 'logs/events.log';
        if (!file_exists($file)) {
            return array();
        }
        $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($content)) {
            return array();
        }
        $tail = array_slice($content, -1 * absint($lines));
        return array_map(array(__CLASS__, 'redact_log_text'), $tail);
    }
}
