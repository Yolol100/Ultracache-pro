<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_Minify_And_Log_Trait {
    public static function minify_css($content) {
        $content = (string) $content;
        if ('' === trim($content)) {
            return '';
        }
        if (class_exists('MatthiasMullie\Minify\CSS')) {
            try {
                $minifier = new MatthiasMullie\Minify\CSS($content);
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
        if (class_exists('MatthiasMullie\Minify\JS')) {
            try {
                $minifier = new MatthiasMullie\Minify\JS($content);
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
        return UCP_CACHE_DIR . 'used-css/' . self::cache_key_for_url($url) . '.css';
    }

    public static function get_critical_css_path($url = '') {
        return UCP_CACHE_DIR . 'critical-css/' . self::cache_key_for_url($url) . '.css';
    }

    public static function has_persistent_object_cache() {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    public static function is_likely_cache_server_present() {
        return !empty($_SERVER['LITESPEED_CACHE']) || !empty($_SERVER['HTTP_X_LITE_SPEED_CACHE']) || !empty($_SERVER['HTTP_X_VARNISH']) || !empty($_SERVER['HTTP_X_CACHE']);
    }

    public static function log($message) {
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . "\n";
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
        return array_slice($content, -1 * absint($lines));
    }
}
