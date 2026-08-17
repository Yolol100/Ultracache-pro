<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Drop-in runs in global namespace before the plugin bootstrap is available.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The advanced-cache drop-in inspects request keys only to decide whether cached HTML may be served; it does not process form data.
/**
 * UltraCache Pro advanced-cache drop-in.
 * UltraCache Pro Drop-in
 * Early safe file-cache serving before full WordPress bootstrap.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CONTENT_DIR')) {
    return;
}

if (!function_exists('ucp_dropin_unslash')) {
    function ucp_dropin_unslash($value, $depth = 0, &$remaining = null) {
        if (null === $remaining) {
            $remaining = 5000;
        }
        if ($depth > 8 || $remaining < 0) {
            return '';
        }
        if (is_array($value)) {
            $normalized = array();
            foreach ($value as $key => $item) {
                --$remaining;
                if ($remaining < 0) {
                    return array();
                }
                $normalized[$key] = ucp_dropin_unslash($item, $depth + 1, $remaining);
            }
            return $normalized;
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('ucp_dropin_sanitize_preg_replace')) {
    function ucp_dropin_sanitize_preg_replace($pattern, $replacement, $subject, $limit = -1, $fallback = '') {
        if (!is_string($subject)) {
            return (string) $fallback;
        }
        try {
            $result = @preg_replace($pattern, $replacement, $subject, $limit);
        } catch (Throwable $exception) {
            return (string) $fallback;
        }
        return null === $result || PREG_NO_ERROR !== preg_last_error() ? (string) $fallback : (string) $result;
    }
}

if (!function_exists('ucp_dropin_safe_json_decode')) {
    function ucp_dropin_safe_json_decode($json, $associative = null, $depth = 64, $flags = 0) {
        if (!is_string($json) || strlen($json) > 5242880 || false !== strpos($json, "\0")) {
            return null;
        }
        $depth = max(1, min(128, (int) $depth));
        try {
            $decoded = json_decode($json, $associative, $depth, (int) $flags);
        } catch (Throwable $exception) {
            return null;
        }
        return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
    }
}

if (!function_exists('ucp_dropin_safe_json_encode')) {
    function ucp_dropin_safe_json_encode($value, $flags = 0, $depth = 64) {
        $depth = max(1, min(128, (int) $depth));
        try {
            $encoded = json_encode($value, (int) $flags, $depth);
        } catch (Throwable $exception) {
            return false;
        }
        return is_string($encoded) && strlen($encoded) <= 5242880 ? $encoded : false;
    }
}

if (!function_exists('ucp_dropin_restore_locked_contents')) {
    function ucp_dropin_restore_locked_contents($handle, $fallback) {
        if (!is_resource($handle) || !is_string($fallback)) {
            return false;
        }
        rewind($handle);
        $length = strlen($fallback);
        $written = 0;
        while ($written < $length) {
            $bytes = fwrite($handle, substr($fallback, $written));
            if (!is_int($bytes) || $bytes <= 0) {
                return false;
            }
            $written += $bytes;
        }
        return ftruncate($handle, $length) && fflush($handle);
    }
}

if (!function_exists('ucp_dropin_write_locked_contents')) {
    function ucp_dropin_write_locked_contents($handle, $contents, $fallback = '') {
        if (!is_resource($handle) || !is_string($contents)) {
            return false;
        }
        rewind($handle);
        $length = strlen($contents);
        $written = 0;
        while ($written < $length) {
            $bytes = fwrite($handle, substr($contents, $written));
            if (!is_int($bytes) || $bytes <= 0) {
                ucp_dropin_restore_locked_contents($handle, $fallback);
                return false;
            }
            $written += $bytes;
        }
        if (!ftruncate($handle, $length) || !fflush($handle)) {
            ucp_dropin_restore_locked_contents($handle, $fallback);
            return false;
        }
        return true;
    }
}

if (!function_exists('ucp_dropin_sanitize_text')) {
    function ucp_dropin_sanitize_text($value) {
        if (!is_scalar($value)) {
            return '';
        }
        $value = (string) $value;
        $value = ucp_dropin_unslash($value);
        $value = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($value) : strip_tags($value);
        $value = ucp_dropin_sanitize_preg_replace('/[\r\n\t\0\x0B]+/', '', $value);
        $value = ucp_dropin_sanitize_preg_replace('/[[:cntrl:]]+/', '', $value);
        return trim((string) $value);
    }
}

if (!function_exists('ucp_dropin_sanitize_key')) {
    function ucp_dropin_sanitize_key($key) {
        $key = strtolower((string) $key);
        return ucp_dropin_sanitize_preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('ucp_dropin_query_key_is_canonical')) {
    function ucp_dropin_query_key_is_canonical($key) {
        if (!is_scalar($key)) {
            return false;
        }
        $raw_key = (string) $key;
        return '' !== $raw_key && $raw_key === ucp_dropin_sanitize_key($raw_key);
    }
}

if (!function_exists('ucp_dropin_query_value_has_canonical_keys')) {
    function ucp_dropin_query_value_has_canonical_keys($value, $depth = 0, &$remaining = null) {
        if (null === $remaining) {
            $remaining = 100;
        }
        if ($depth > 4 || $remaining < 0) {
            return false;
        }
        if (!is_array($value)) {
            if (!is_scalar($value) && null !== $value) {
                return false;
            }
            $scalar = (string) $value;
            return strlen($scalar) <= 8192 && 0 === preg_match('/[\x00-\x1F\x7F]/', $scalar);
        }
        foreach ($value as $key => $item) {
            --$remaining;
            if ($remaining < 0 || (!is_int($key) && !ucp_dropin_query_key_is_canonical($key))) {
                return false;
            }
            if (!ucp_dropin_query_value_has_canonical_keys($item, $depth + 1, $remaining)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('ucp_dropin_raw_query_keys_are_canonical')) {
    function ucp_dropin_raw_query_keys_are_canonical($query_string) {
        if (!is_scalar($query_string)) {
            return false;
        }
        $query_string = (string) $query_string;
        if (strlen($query_string) > 8192 || false !== strpos($query_string, "\0")) {
            return false;
        }
        $pairs = preg_split('/[&;]/', $query_string);
        if (!is_array($pairs) || count($pairs) > 100) {
            return false;
        }
        foreach ($pairs as $pair) {
            if ('' === $pair) {
                continue;
            }
            $raw_name = explode('=', $pair, 2)[0];
            if ('' === $raw_name || preg_match('/%(?![0-9A-Fa-f]{2})/', $raw_name)) {
                return false;
            }
            $decoded_name = rawurldecode(str_replace('+', ' ', $raw_name));
            if (strlen($decoded_name) > 256 || 1 !== preg_match('/^([a-z0-9_-]+)((?:\[[a-z0-9_-]*\])*)$/D', $decoded_name, $match)) {
                return false;
            }
            if (!ucp_dropin_query_key_is_canonical($match[1])) {
                return false;
            }
            if ('' !== $match[2]) {
                preg_match_all('/\[([^]]*)\]/', $match[2], $segments);
                if (empty($segments[1]) || count($segments[1]) > 4) {
                    return false;
                }
                foreach ($segments[1] as $segment) {
                    if ('' === $segment || ctype_digit($segment)) {
                        continue;
                    }
                    if (!ucp_dropin_query_key_is_canonical($segment)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}

if (!function_exists('ucp_dropin_normalize_page_cache_content_type')) {
    function ucp_dropin_normalize_page_cache_content_type($content_type) {
        if (!is_scalar($content_type)) {
            return '';
        }
        $content_type = trim((string) $content_type);
        if ('' === $content_type || strlen($content_type) > 255 || preg_match('/[\x00-\x1F\x7F]/', $content_type)) {
            return '';
        }
        $token = "[!#$%&'*+.^_`|~0-9A-Za-z-]+";
        $quoted = '"[^"\x00-\x1F\x7F]*"';
        $pattern = '@^' . $token . '/' . $token . '(?:\s*;\s*' . $token . '\s*=\s*(?:' . $token . '|' . $quoted . '))*$@D';
        if (1 !== preg_match($pattern, $content_type)) {
            return '';
        }
        $base = strtolower(trim((string) strtok($content_type, ';')));
        return in_array($base, array('text/html', 'application/xhtml+xml', 'application/rss+xml', 'application/atom+xml', 'application/rdf+xml', 'application/xml', 'text/xml'), true)
            ? $content_type
            : '';
    }
}

if (!function_exists('ucp_dropin_cookie_name_is_valid')) {
    function ucp_dropin_cookie_name_is_valid($cookie_name) {
        return is_scalar($cookie_name)
            && 1 === preg_match('/^[!#$%&\'()*+\-.^_`|~0-9A-Za-z]+$/', (string) $cookie_name);
    }
}

if (!function_exists('ucp_dropin_cookie_header_is_safe')) {
    function ucp_dropin_cookie_header_is_safe($cookie_header, $exclude_cookies, $safe_cookies) {
        if (!is_scalar($cookie_header)) {
            return false;
        }
        $cookie_header = trim((string) $cookie_header);
        if ('' === $cookie_header) {
            return true;
        }
        foreach (explode(';', $cookie_header) as $pair) {
            $parts = explode('=', trim($pair), 2);
            $name = isset($parts[0]) ? trim((string) $parts[0]) : '';
            if (!ucp_dropin_cookie_name_is_valid($name)) {
                return false;
            }
            $normalized_name = strtolower($name);
            foreach ((array) $exclude_cookies as $fragment) {
                $fragment = ucp_dropin_sanitize_key((string) $fragment);
                if ('' !== $fragment && false !== strpos($normalized_name, $fragment)) {
                    return false;
                }
            }
            $safe = false;
            foreach ((array) $safe_cookies as $prefix) {
                $prefix = trim((string) $prefix);
                if (!ucp_dropin_cookie_name_is_valid($prefix)) {
                    continue;
                }
                if (0 === strpos($name, $prefix)) {
                    $safe = true;
                    break;
                }
            }
            if (!$safe) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('ucp_dropin_server_value')) {
    function ucp_dropin_server_value($key, $max_bytes = 8192) {
        if (!isset($_SERVER[$key]) || !is_scalar($_SERVER[$key])) {
            return '';
        }
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated and unslashed below; this drop-in runs before WP helpers are guaranteed.
        $value = ucp_dropin_unslash((string) $_SERVER[$key]);
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $max_bytes = max(1, min(1048576, (int) $max_bytes));
        if (strlen($value) > $max_bytes || false !== strpos($value, "\0") || false !== strpos($value, "\r") || false !== strpos($value, "\n")) {
            return '';
        }
        return ucp_dropin_sanitize_text($value);
    }
}

if (!function_exists('ucp_dropin_request_cache_control_requires_revalidation')) {
    function ucp_dropin_request_cache_control_requires_revalidation($value) {
        foreach (explode(',', strtolower((string) $value)) as $directive) {
            $parts = array_map('trim', explode('=', trim($directive), 2));
            $name = isset($parts[0]) ? $parts[0] : '';
            if (in_array($name, array('no-cache', 'no-store', 'private'), true)) {
                return true;
            }
            if (!in_array($name, array('max-age', 's-maxage'), true)) {
                continue;
            }
            $raw_value = isset($parts[1]) ? trim($parts[1], " \t\n\r\0\x0B\"") : null;
            if (null === $raw_value || 1 !== preg_match('/^-?\d+$/', $raw_value) || (int) $raw_value <= 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('ucp_dropin_cache_header_policy')) {
    function ucp_dropin_cache_header_policy($config) {
        if (isset($config['cache_header_policy']) && is_array($config['cache_header_policy'])) {
            $candidate = $config['cache_header_policy'];
        } else {
            $legacy_vary = array('Accept', 'Accept-Encoding');
            if (!empty($config['vary_cookies']) && is_array($config['vary_cookies'])) {
                array_unshift($legacy_vary, 'Cookie');
            }
            if (!empty($config['cache_mobile_separately'])) {
                $legacy_vary[] = 'User-Agent';
            }
            $candidate = array(
                'edge_enabled' => !empty($config['enable_edge_html_cache']),
                'edge_ttl'     => isset($config['edge_html_cache_ttl']) ? $config['edge_html_cache_ttl'] : 600,
                'edge_stale'   => isset($config['edge_html_cache_stale']) ? $config['edge_html_cache_stale'] : 86400,
                'vary_headers' => implode(', ', array_values(array_unique($legacy_vary))),
            );
        }
        $raw_vary = isset($candidate['vary_headers']) && is_scalar($candidate['vary_headers'])
            ? explode(',', (string) $candidate['vary_headers'])
            : array();
        $allowed_vary = array('accept', 'accept-encoding', 'cookie', 'user-agent');
        $vary_flags = array();
        foreach (array_slice($raw_vary, 0, 8) as $header) {
            $key = strtolower(trim((string) $header));
            if (in_array($key, $allowed_vary, true)) {
                $vary_flags[$key] = true;
            }
        }
        $vary = array();
        if (!empty($vary_flags['cookie'])) {
            $vary[] = 'Cookie';
        }
        $vary[] = 'Accept';
        $vary[] = 'Accept-Encoding';
        if (!empty($vary_flags['user-agent'])) {
            $vary[] = 'User-Agent';
        }
        return array(
            'version'      => 1,
            'edge_enabled' => !empty($candidate['edge_enabled']),
            'edge_ttl'     => min(86400, max(60, isset($candidate['edge_ttl']) ? (int) $candidate['edge_ttl'] : 600)),
            'edge_stale'   => min(604800, max(0, isset($candidate['edge_stale']) ? (int) $candidate['edge_stale'] : 86400)),
            'vary_headers' => implode(', ', array_values(array_unique($vary))),
        );
    }
}

if (!function_exists('ucp_dropin_public_html_cache_control')) {
    function ucp_dropin_public_html_cache_control($config, $remaining_ttl) {
        $policy = ucp_dropin_cache_header_policy($config);
        $value = 'public, max-age=0, must-revalidate';
        if (!empty($policy['edge_enabled'])) {
            $shared_ttl = min(max(0, (int) $remaining_ttl), (int) $policy['edge_ttl']);
            if ($shared_ttl > 0) {
                $value .= ', s-maxage=' . $shared_ttl;
            }
        }
        return $value;
    }
}

if (!function_exists('ucp_dropin_shared_html_cache_control')) {
    function ucp_dropin_shared_html_cache_control($config, $remaining_ttl) {
        $policy = ucp_dropin_cache_header_policy($config);
        if (empty($policy['edge_enabled'])) {
            return '';
        }
        $shared_ttl = min(max(0, (int) $remaining_ttl), (int) $policy['edge_ttl']);
        if ($shared_ttl <= 0) {
            return 'no-store';
        }
        $shared = 'max-age=' . $shared_ttl;
        if ((int) $policy['edge_stale'] > 0) {
            $shared .= ', stale-while-revalidate=' . (int) $policy['edge_stale'] . ', stale-if-error=' . (int) $policy['edge_stale'];
        }
        return $shared;
    }
}

if (!function_exists('ucp_dropin_send_shared_html_cache_headers')) {
    function ucp_dropin_send_shared_html_cache_headers($config, $remaining_ttl) {
        $shared = ucp_dropin_shared_html_cache_control($config, $remaining_ttl);
        if ('' === $shared) {
            return;
        }
        header('CDN-Cache-Control: ' . $shared, true);
        header('Cloudflare-CDN-Cache-Control: ' . $shared, true);
    }
}

if (!function_exists('ucp_dropin_normalize_page_cache_response_headers')) {
    function ucp_dropin_normalize_page_cache_response_headers($headers) {
        if (!is_array($headers)) {
            return array();
        }
        $allowed = array_fill_keys(array(
            'content-language',
            'content-security-policy',
            'content-security-policy-report-only',
            'cross-origin-embedder-policy',
            'cross-origin-opener-policy',
            'cross-origin-resource-policy',
            'document-policy',
            'link',
            'nel',
            'origin-agent-cluster',
            'permissions-policy',
            'referrer-policy',
            'report-to',
            'reporting-endpoints',
            'strict-transport-security',
            'x-content-type-options',
            'x-dns-prefetch-control',
            'x-download-options',
            'x-frame-options',
            'x-permitted-cross-domain-policies',
            'x-robots-tag',
            'x-xss-protection',
        ), true);
        $normalized = array();
        $bytes = 0;
        foreach (array_slice($headers, 0, 64) as $header_line) {
            if (!is_scalar($header_line)) {
                continue;
            }
            $header_line = trim((string) $header_line);
            if ('' === $header_line || strlen($header_line) > 4096 || false !== strpbrk($header_line, "\r\n\0")) {
                continue;
            }
            if (1 !== preg_match('/^([!#$%&\'*+.^_`|~0-9A-Za-z-]+)\s*:\s*(.*)$/', $header_line, $match)) {
                continue;
            }
            $name = strtolower($match[1]);
            $value = trim($match[2]);
            if (!isset($allowed[$name]) || '' === $value || preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value)) {
                continue;
            }
            $line = $match[1] . ': ' . $value;
            $bytes += strlen($line);
            if ($bytes > 8192) {
                break;
            }
            $normalized[] = $line;
        }
        return $normalized;
    }
}

if (!function_exists('ucp_dropin_send_cached_page_response_headers')) {
    function ucp_dropin_send_cached_page_response_headers($headers) {
        foreach (ucp_dropin_normalize_page_cache_response_headers($headers) as $header_line) {
            header($header_line, false);
        }
    }
}

if (!function_exists('ucp_dropin_request_scheme')) {
    function ucp_dropin_request_scheme() {
        $https = strtolower(ucp_dropin_server_value('HTTPS'));
        if (in_array($https, array('on', '1', 'true'), true) || '443' === ucp_dropin_server_value('SERVER_PORT')) {
            return 'https';
        }
        return 'http';
    }
}

if (!function_exists('ucp_dropin_is_light_preload_request')) {
    function ucp_dropin_is_light_preload_request() {
        if ('1' !== ucp_dropin_server_value('HTTP_X_ULTRACACHE_LIGHT_PRELOAD')) {
            return false;
        }
        $user_agent = ucp_dropin_server_value('HTTP_USER_AGENT');
        return false !== strpos($user_agent, 'UltraCachePro-Preloader/')
            || false !== strpos($user_agent, 'UltraCachePro-Preload-Queue/')
            || false !== strpos($user_agent, 'UltraCache-Mobile-Preloader/')
            || false !== strpos($user_agent, 'UltraCache-Mobile-Preload-Queue/');
    }
}

if (!function_exists('ucp_dropin_parse_url')) {
    function ucp_dropin_parse_url($url, $component) {
        if (function_exists('wp_parse_url')) {
            return wp_parse_url($url, $component);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- fallback for very early drop-in execution.
        return parse_url($url, $component);
    }
}

if (!function_exists('ucp_dropin_normalize_cache_path')) {
    function ucp_dropin_normalize_cache_path($path) {
        if (!is_string($path) || '' === $path || false !== strpos($path, "\0")) {
            return '';
        }
        $path = str_replace('\\', '/', $path);
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            return '';
        }
        $prefix = '';
        if (preg_match('/^[A-Za-z]:/', $path, $drive)) {
            $prefix = strtoupper($drive[0]);
            $path = substr($path, 2);
        } elseif (0 === strpos($path, '/')) {
            $prefix = '/';
        }
        $segments = array();
        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                if (empty($segments)) {
                    return '';
                }
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $normalized = implode('/', $segments);
        if ('/' === $prefix) {
            return '/' . $normalized;
        }
        return '' !== $prefix ? $prefix . '/' . $normalized : $normalized;
    }
}

if (!function_exists('ucp_dropin_is_safe_cache_file')) {
    /**
     * Validate a file target below the UltraCache cache root without following
     * descendant symlinks. The cache root itself may intentionally be a symlink.
     *
     * @param string $path       Absolute target path.
     * @param bool   $must_exist Whether the target must be a readable regular file.
     * @return bool
     */
    function ucp_dropin_is_safe_cache_file($path, $must_exist = true) {
        $cache_root = rtrim((string) WP_CONTENT_DIR, '/\\') . '/cache/ultracache-pro';
        $normalized_root = rtrim(ucp_dropin_normalize_cache_path($cache_root), '/');
        $normalized_path = ucp_dropin_normalize_cache_path($path);
        if ('' === $normalized_root || '' === $normalized_path
            || ($normalized_path !== $normalized_root && 0 !== strpos($normalized_path, $normalized_root . '/'))) {
            return false;
        }

        $relative = ltrim(substr($normalized_path, strlen($normalized_root)), '/');
        $segments = '' === $relative ? array() : explode('/', $relative);
        array_pop($segments);
        $current = $cache_root;
        foreach ($segments as $segment) {
            if ('' === $segment) {
                continue;
            }
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current) || (file_exists($current) && !is_dir($current))) {
                return false;
            }
        }

        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            return false;
        }

        $root_real = realpath($cache_root);
        if (false !== $root_real) {
            $parent = dirname($path);
            while (!file_exists($parent) && !is_link($parent)) {
                $next = dirname($parent);
                if ($next === $parent) {
                    return false;
                }
                $parent = $next;
            }
            if (is_link($parent) && ucp_dropin_normalize_cache_path($parent) !== $normalized_root) {
                return false;
            }
            $parent_real = realpath($parent);
            if (false === $parent_real) {
                return false;
            }
            $root_real = rtrim(ucp_dropin_normalize_cache_path($root_real), '/') . '/';
            $parent_real = rtrim(ucp_dropin_normalize_cache_path($parent_real), '/') . '/';
            if ($parent_real !== $root_real && 0 !== strpos($parent_real, $root_real)) {
                return false;
            }
        } elseif ($must_exist) {
            return false;
        }

        return !$must_exist || (is_file($path) && is_readable($path));
    }
}

if (!function_exists('ucp_dropin_open_safe_cache_file')) {
    /**
     * Open a managed cache file and verify the opened inode before returning it.
     *
     * @param string $path Absolute cache file path.
     * @param string $mode Supported non-truncating fopen mode: c or c+.
     * @return resource|false
     */
    function ucp_dropin_open_safe_cache_file($path, $mode = 'c') {
        if (!in_array($mode, array('c', 'c+'), true) || !ucp_dropin_is_safe_cache_file($path, false)) {
            return false;
        }

        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- early cache drop-in uses a validated local handle and verifies its inode before writes.
        $handle = @fopen($path, $mode);
        if (!is_resource($handle)) {
            return false;
        }

        clearstatcache(true, $path);
        $handle_stat = @fstat($handle);
        $path_stat = @stat($path);
        $same_inode = is_array($handle_stat) && is_array($path_stat);
        if ($same_inode && isset($handle_stat['dev'], $handle_stat['ino'], $path_stat['dev'], $path_stat['ino'])) {
            $same_inode = (string) $handle_stat['dev'] === (string) $path_stat['dev']
                && (string) $handle_stat['ino'] === (string) $path_stat['ino'];
        }
        $single_link = !is_array($handle_stat) || !isset($handle_stat['nlink']) || (int) $handle_stat['nlink'] <= 1;

        if (!$same_inode || !$single_link || is_link($path) || !is_file($path) || !ucp_dropin_is_safe_cache_file($path, true)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- close rejected cache handle immediately.
            @fclose($handle);
            return false;
        }

        return $handle;
    }
}

if (!function_exists('ucp_dropin_header_quality')) {
    /**
     * Parse an RFC 9110 qvalue. Invalid q parameters are deliberately treated as q=0.
     *
     * @param array $parameters Header parameters following a media range or content coding.
     * @return float
     */
    function ucp_dropin_header_quality($parameters) {
        $quality = 1.0;
        foreach ((array) $parameters as $parameter) {
            if (!preg_match('/^q\s*=/i', (string) $parameter)) {
                continue;
            }
            if (preg_match('/^q\s*=\s*(0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/i', (string) $parameter, $match)) {
                return (float) $match[1];
            }
            return 0.0;
        }
        return $quality;
    }
}

if (!function_exists('ucp_dropin_encoding_quality')) {
    function ucp_dropin_encoding_quality($header, $encoding) {
        $header = strtolower(trim((string) $header));
        $encoding = strtolower(trim((string) $encoding));
        if ('' === $header || '' === $encoding) {
            return 0.0;
        }

        $exact_quality = null;
        $wildcard_quality = null;
        foreach (explode(',', $header) as $item) {
            $segments = array_map('trim', explode(';', $item));
            $coding = array_shift($segments);
            if ('' === $coding) {
                continue;
            }

            $quality = ucp_dropin_header_quality($segments);
            if ($coding === $encoding) {
                $exact_quality = null === $exact_quality ? $quality : max($exact_quality, $quality);
            } elseif ('*' === $coding) {
                $wildcard_quality = null === $wildcard_quality ? $quality : max($wildcard_quality, $quality);
            }
        }

        return null !== $exact_quality ? $exact_quality : (null !== $wildcard_quality ? $wildcard_quality : 0.0);
    }
}

if (!function_exists('ucp_dropin_identity_quality')) {
    /**
     * Return the quality of the unencoded representation according to RFC 9110 section 12.5.3.
     * Identity is acceptable by default, except when identity;q=0 or a non-overridden *;q=0 excludes it.
     *
     * @param string $header Accept-Encoding header value.
     * @return float
     */
    function ucp_dropin_identity_quality($header) {
        $header = strtolower(trim((string) $header));
        if ('' === $header) {
            return 1.0;
        }

        $identity_quality = null;
        $wildcard_quality = null;
        foreach (explode(',', $header) as $item) {
            $segments = array_map('trim', explode(';', $item));
            $coding = array_shift($segments);
            if ('' === $coding) {
                continue;
            }
            $quality = ucp_dropin_header_quality($segments);
            if ('identity' === $coding) {
                $identity_quality = null === $identity_quality ? $quality : max($identity_quality, $quality);
            } elseif ('*' === $coding) {
                $wildcard_quality = null === $wildcard_quality ? $quality : max($wildcard_quality, $quality);
            }
        }

        if (null !== $identity_quality) {
            return $identity_quality;
        }
        return null !== $wildcard_quality && $wildcard_quality <= 0.0 ? 0.0 : 1.0;
    }
}

if (!function_exists('ucp_dropin_media_quality')) {
    /**
     * Determine whether a concrete response media type is acceptable.
     * Exact media ranges take precedence over type wildcards, which take precedence over the all-types wildcard.
     *
     * @param string $header     Accept header value.
     * @param string $type       Lowercase response media type.
     * @param string $subtype    Lowercase response media subtype.
     * @param array  $parameters Normalized parameters of the concrete representation.
     * @return float
     */
    function ucp_dropin_media_quality($header, $type, $subtype, $parameters = array()) {
        $header = strtolower(trim((string) $header));
        $type = strtolower(trim((string) $type));
        $subtype = strtolower(trim((string) $subtype));
        $parameters = is_array($parameters) ? array_change_key_case($parameters, CASE_LOWER) : array();
        if ('' === $header) {
            return 1.0;
        }
        if ('' === $type || '' === $subtype) {
            return 0.0;
        }

        $best_specificity = -1;
        $best_quality = 0.0;
        foreach (explode(',', $header) as $item) {
            $segments = array_map('trim', explode(';', $item));
            $media_range = array_shift($segments);
            $range_parts = explode('/', (string) $media_range, 2);
            if (2 !== count($range_parts)) {
                continue;
            }
            $range_type = trim($range_parts[0]);
            $range_subtype = trim($range_parts[1]);
            if ('*' === $range_type && '*' === $range_subtype) {
                $specificity = 0;
            } elseif ($type === $range_type && '*' === $range_subtype) {
                $specificity = 1;
            } elseif ($type === $range_type && $subtype === $range_subtype) {
                $specificity = 2;
            } else {
                continue;
            }

            $media_parameter_count = 0;
            $range_matches = true;
            foreach ($segments as $parameter) {
                if (preg_match('/^q\s*=/i', (string) $parameter)) {
                    continue;
                }
                $pair = explode('=', (string) $parameter, 2);
                if (2 !== count($pair)) {
                    $range_matches = false;
                    break;
                }
                $parameter_name = strtolower(trim($pair[0]));
                $parameter_value = strtolower(trim($pair[1], " \t\n\r\0\x0B\""));
                if ('' === $parameter_name
                    || !array_key_exists($parameter_name, $parameters)
                    || strtolower(trim((string) $parameters[$parameter_name], " \t\n\r\0\x0B\"")) !== $parameter_value) {
                    $range_matches = false;
                    break;
                }
                ++$media_parameter_count;
            }
            if (!$range_matches) {
                continue;
            }

            $specificity = ($specificity * 1000) + $media_parameter_count;
            $quality = ucp_dropin_header_quality($segments);
            if ($specificity > $best_specificity) {
                $best_specificity = $specificity;
                $best_quality = $quality;
            } elseif ($specificity === $best_specificity) {
                $best_quality = max($best_quality, $quality);
            }
        }

        return $best_specificity >= 0 ? $best_quality : 0.0;
    }
}

if (!function_exists('ucp_dropin_accepts_encoding')) {
    function ucp_dropin_accepts_encoding($header, $encoding) {
        return ucp_dropin_encoding_quality($header, $encoding) > 0.0;
    }
}

if (!function_exists('ucp_dropin_build_etag')) {
    function ucp_dropin_build_etag($modified, $representation_size, $encoding, $content_hash = '') {
        $encoding = '' !== (string) $encoding ? (string) $encoding : 'identity';
        $content_hash = strtolower((string) $content_hash);
        if (1 === preg_match('/^[a-f0-9]{64}$/', $content_hash)) {
            return 'W/"ucp-' . substr($content_hash, 0, 32) . '-' . $encoding . '"';
        }

        return 'W/"ucp-' . dechex((int) $modified) . '-' . dechex((int) $representation_size) . '-' . $encoding . '"';
    }
}

if (!function_exists('ucp_dropin_status_is_page_cacheable')) {
    function ucp_dropin_status_is_page_cacheable($status_code) {
        if (is_int($status_code)) {
            $status_code = (int) $status_code;
        } elseif (is_string($status_code) && 1 === preg_match('/^[1-9][0-9]{2}$/D', $status_code)) {
            $status_code = (int) $status_code;
        } else {
            return false;
        }
        return in_array($status_code, array(200, 404), true);
    }
}

if (!function_exists('ucp_dropin_status_allows_not_modified')) {
    function ucp_dropin_status_allows_not_modified($status_code) {
        return 200 === (int) $status_code;
    }
}

if (!function_exists('ucp_dropin_record_cache_insight')) {
    function ucp_dropin_record_cache_insight($status, $config) {
        if (empty($config['cache_insights_enabled'])) {
            return;
        }
        $sample_rate = isset($config['cache_insights_sample_rate']) ? (int) $config['cache_insights_sample_rate'] : 1;
        $sample_rate = min(100, max(1, $sample_rate));
        try {
            $roll = random_int(1, 100);
        } catch (Exception $e) {
            $roll = mt_rand(1, 100);
        }
        if ($sample_rate < 100 && $roll > $sample_rate) {
            return;
        }
        $status = strtoupper(ucp_dropin_sanitize_key((string) $status));
        if (!in_array($status, array('HIT', 'HIT-304'), true)) {
            return;
        }
        $path = WP_CONTENT_DIR . '/cache/ultracache-pro/insights-dropin.json';
        $handle = ucp_dropin_open_safe_cache_file($path, 'c+');
        if (!$handle) {
            return;
        }
        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);
            return;
        }
        rewind($handle);
        $raw = stream_get_contents($handle, 65536);
        $data = is_string($raw) && '' !== trim($raw) ? ucp_dropin_safe_json_decode($raw, true) : array();
        $data = is_array($data) ? $data : array();
        $counts = isset($data['status']) && is_array($data['status']) ? $data['status'] : array();
        $weight = max(1, (int) round(100 / $sample_rate));
        $counts[$status] = min(PHP_INT_MAX, max(0, (int) ($counts[$status] ?? 0)) + $weight);
        $encoded = ucp_dropin_safe_json_encode(array(
            'version' => 1,
            'updated_at' => time(),
            'status' => $counts,
        ));
        if (is_string($encoded)) {
            ucp_dropin_write_locked_contents($handle, $encoded, is_string($raw) ? $raw : '');
        }
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

if ('cli' === PHP_SAPI) {
    return;
}

if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
    return;
}

$ucp_dropin_method = strtoupper(ucp_dropin_server_value('REQUEST_METHOD'));
if (!in_array($ucp_dropin_method, array('GET', 'HEAD'), true)) {
    return;
}
$ucp_dropin_is_head = 'HEAD' === $ucp_dropin_method;
if ('' !== ucp_dropin_server_value('HTTP_X_HTTP_METHOD_OVERRIDE') || isset($_GET['_method'])) {
    return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this drop-in only refuses to serve cache when request payloads exist; it does not process form data.
if (!empty($_POST) || !empty($_FILES)) {
    return;
}

$auth_header = '';
foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $auth_key) {
    $candidate = ucp_dropin_server_value($auth_key);
    if ('' !== $candidate) {
        $auth_header = $candidate;
        break;
    }
}

if ('' !== $auth_header || '' !== ucp_dropin_server_value('PHP_AUTH_USER') || '' !== ucp_dropin_server_value('PHP_AUTH_DIGEST')) {
    return;
}

$cache_control = strtolower(ucp_dropin_server_value('HTTP_CACHE_CONTROL'));
$pragma        = strtolower(ucp_dropin_server_value('HTTP_PRAGMA'));
if (ucp_dropin_request_cache_control_requires_revalidation($cache_control) || false !== strpos($pragma, 'no-cache')) {
    return;
}

if ('' !== ucp_dropin_server_value('HTTP_RANGE') || '' !== ucp_dropin_server_value('HTTP_IF_RANGE')) {
    return;
}

$config_file = WP_CONTENT_DIR . '/cache/ultracache-pro/dropin-config.php';
$config = ucp_dropin_is_safe_cache_file($config_file) ? include $config_file : array();
$config = is_array($config) ? $config : array();
if (!empty($config['multisite'])) {
    return;
}
$enable_cache = array_key_exists('enable_cache', $config) ? !empty($config['enable_cache']) : false;
if (!$enable_cache) {
    return;
}

$home_scheme = !empty($config['home_scheme']) ? strtolower((string) $config['home_scheme']) : '';
if (in_array($home_scheme, array('http', 'https'), true) && ucp_dropin_request_scheme() !== $home_scheme) {
    return;
}

$cache_backend = !empty($config['cache_backend']) ? ucp_dropin_sanitize_key((string) $config['cache_backend']) : 'auto';
if (defined('UCP_DISABLE_LITESPEED_BACKEND') && UCP_DISABLE_LITESPEED_BACKEND && 'litespeed' === $cache_backend) {
    $cache_backend = 'disk';
}
if (!in_array($cache_backend, array('auto', 'disk', 'litespeed'), true)) {
    $cache_backend = 'auto';
}
$server_software = strtolower(ucp_dropin_server_value('SERVER_SOFTWARE'));
$is_litespeed_server = false !== strpos($server_software, 'litespeed') || false !== strpos($server_software, 'openlitespeed') || '' !== ucp_dropin_server_value('LSWS_EDITION') || '' !== ucp_dropin_server_value('LITESPEED_CACHE') || '' !== ucp_dropin_server_value('LSCACHE_VERSION');
if ($is_litespeed_server && ('litespeed' === $cache_backend || 'auto' === $cache_backend) && !(defined('UCP_DISABLE_LITESPEED_BACKEND') && UCP_DISABLE_LITESPEED_BACKEND)) {
    // Auto-detected LiteSpeed bridge: stand down so server-level LSCache can own full-page cache.
    return;
}
$exclude_paths = !empty($config['exclude_paths']) && is_array($config['exclude_paths']) ? $config['exclude_paths'] : array('cart', 'checkout', 'winkelwagen', 'afrekenen', 'my-account', 'mijn-account', 'account', 'order-pay', 'order-received', 'add-payment-method', 'wc-api', 'wc-ajax', 'wp-json', 'wp-admin', 'wp-login.php', 'xmlrpc.php', 'customer-logout');
$exclude_cookies = !empty($config['exclude_cookies']) && is_array($config['exclude_cookies']) ? $config['exclude_cookies'] : array(
    'wordpress_logged_in_',
    'wordpress_sec_',
    'wp-postpass_',
    'wp-resetpass_',
    'comment_author_',
    'switch_to_olduser_',
    'wordpress_test_cookie',
    'woocommerce_items_in_cart',
    'wp_woocommerce_session_',
    'woocommerce_cart_hash',
    'woocommerce_recently_viewed',
    'woocommerce_checkout_',
    'woocommerce_pay_',
    'edd_items_in_cart',
    'pll_language',
    '_icl_current_language',
    'wp-wpml_current_language',
    'wpml_browser_redirect_test',
    'trp_language',
    'wp_lang',
    'wcml_client_currency',
    'woocommerce_multicurrency_forced_currency',
    'aelia_cs_selected_currency',
    'aelia_customer_country',
    'aelia_customer_state',
    'aelia_tax_exempt',
    'cookie_notice_',
    'cmplz_',
    'complianz_',
    'cookieyes',
    'cky-',
    'borlabs',
);
$cache_query_strings = !empty($config['cache_query_strings']);
$cache_query_string_inclusions = !empty($config['cache_query_string_inclusions']) && is_array($config['cache_query_string_inclusions']) ? $config['cache_query_string_inclusions'] : array();
$cache_ignore_query_params = !empty($config['cache_ignore_query_params']) && is_array($config['cache_ignore_query_params']) ? $config['cache_ignore_query_params'] : array(
    'utm_*', 'mtm_*', 'pk_*', '_hs*', 'hs_*', 'gad_*',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id',
    'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid', 'srsltid',
    '_ga', '_gl', 'mc_cid', 'mc_eid', 'pk_campaign', 'pk_kwd',
    'mtm_campaign', 'mtm_source', 'mtm_medium', 'yclid', 'dclid', 'twclid',
    'epik', 'igshid', 'scid', 'li_fat_id',
);
$cache_mobile_separately = !array_key_exists('cache_mobile_separately', $config) || !empty($config['cache_mobile_separately']);
$exclude_user_agents = !empty($config['exclude_user_agents']) && is_array($config['exclude_user_agents']) ? $config['exclude_user_agents'] : array();
$safe_cookies = !empty($config['safe_cookies']) && is_array($config['safe_cookies']) ? $config['safe_cookies'] : array('ct_', 'apbct_', 'ct_sfw', 'cleantalk', 'cookiebot', 'cookie_notice_', 'cmplz_', 'complianz_', 'cookieyes', 'cky-', 'borlabs', 'joinchat_', 'wp-settings-', '_ga', '_gid', '_gat', '_gcl_', '_fbp', '_fbc', '_hj', '_clck', '_clsk', '_pk_id', '_pk_ses', '_uetsid', '_uetvid', '_pin_unauth', '_scid', 'li_gc', 'li_mc', 'lidc', 'bcookie', 'bscookie', 'tk_ai', 'tk_qs', '__stripe_mid', '__stripe_sid', '__cf_bm', 'cf_clearance');
$block_unknown_cookies = !empty($config['block_unknown_cookies']);

if (!function_exists('ucp_dropin_cache_path_slug')) {
    // MUST stay byte-for-byte identical to UCP_Helpers::cache_path_slug() so the early drop-in
    // and the PHP fallback build the same page-cache key. See that method for the rationale.
    function ucp_dropin_cache_path_slug($raw_path) {
        $raw = rtrim((string) $raw_path, '/');
        $slug = str_replace('/', '-', $raw);
        $slug = ucp_dropin_sanitize_preg_replace('/[^A-Za-z0-9_.-]/', '-', $slug);
        $slug = ucp_dropin_sanitize_preg_replace('/-+/', '-', (string) $slug);
        $slug = trim((string) $slug, '-');
        return '' === $slug ? 'home' : $slug;
    }
}

if (!function_exists('ucp_dropin_sanitize_query_pattern')) {
    function ucp_dropin_sanitize_query_pattern($pattern) {
        $pattern = strtolower((string) ucp_dropin_unslash($pattern));
        return ucp_dropin_sanitize_preg_replace('/[^a-z0-9_\-*]/', '', $pattern);
    }
}

if (!function_exists('ucp_dropin_query_key_matches')) {
    function ucp_dropin_query_key_matches($key, $patterns) {
        $key = ucp_dropin_sanitize_key((string) $key);
        foreach ((array) $patterns as $pattern) {
            $pattern = ucp_dropin_sanitize_query_pattern($pattern);
            if ('' === $pattern) {
                continue;
            }
            if (substr($pattern, -1) === '*') {
                $prefix = substr($pattern, 0, -1);
                if ('' !== $prefix && 0 === strpos($key, $prefix)) {
                    return true;
                }
            }
            if ($key === $pattern) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('ucp_dropin_query_key_is_ignored')) {
    function ucp_dropin_query_key_is_ignored($key, $ignore_patterns, $include_patterns) {
        $key = ucp_dropin_sanitize_key((string) $key);
        if ('' === $key || ucp_dropin_query_key_matches($key, $include_patterns)) {
            return false;
        }
        return ucp_dropin_query_key_matches($key, $ignore_patterns);
    }
}

if (!function_exists('ucp_dropin_wildcard_match')) {
    /**
     * Match the same simple wildcard syntax as UCP_Helpers::wildcard_match().
     */
    function ucp_dropin_wildcard_match($haystack, $pattern) {
        $haystack = (string) $haystack;
        $pattern = trim((string) $pattern);
        if ('' === $pattern) {
            return false;
        }
        if (false === strpos($pattern, '(.*)') && false === strpos($pattern, '*')) {
            return false !== stripos($haystack, $pattern);
        }
        $regex = preg_quote($pattern, '#');
        $regex = str_replace(array('\\(\\.\\*\\)', '\\*'), '.*', $regex);
        return 1 === @preg_match('#' . $regex . '#i', $haystack);
    }
}

if (!function_exists('ucp_dropin_lowercase_query_tree')) {
    /**
     * Lowercase decoded query keys and scalar values without changing delimiters.
     *
     * @param mixed $value Parsed query value.
     * @return mixed
     */
    function ucp_dropin_lowercase_query_tree($value) {
        if (!is_array($value)) {
            return is_scalar($value) ? strtolower((string) $value) : '';
        }

        $normalized = array();
        foreach ($value as $key => $item) {
            $normalized_key = is_string($key) ? strtolower($key) : $key;
            $normalized[$normalized_key] = ucp_dropin_lowercase_query_tree($item);
        }
        return $normalized;
    }
}

if (!function_exists('ucp_dropin_url_safety_parts')) {
    /**
     * Parse a request target for exact path-segment and query-key matching.
     *
     * @param string $url Absolute URL or request target.
     * @return array
     */
    function ucp_dropin_url_safety_parts($url) {
        $path = ucp_dropin_parse_url((string) $url, PHP_URL_PATH);
        $path = strtolower(rawurldecode(is_string($path) ? $path : ''));
        $path = '/' . trim($path, '/');
        if ('/' !== $path) {
            $path = rtrim($path, '/');
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static function($segment) {
            return '' !== $segment;
        }));

        $query = ucp_dropin_parse_url((string) $url, PHP_URL_QUERY);
        $query = is_string($query) ? $query : '';
        $query_args = array();
        if ('' !== $query) {
            // parse_str() must see the original delimiters. Decoding the complete query
            // first would turn encoded ampersands or equals signs inside a value into
            // new parameters and make cache safety decisions differ from PHP/WordPress.
            parse_str($query, $query_args);
            $query_args = is_array($query_args) ? ucp_dropin_lowercase_query_tree($query_args) : array();
        }
        return array('path' => $path, 'segments' => $segments, 'query' => $query_args);
    }
}

if (!function_exists('ucp_dropin_url_has_exact_token')) {
    function ucp_dropin_url_has_exact_token($parts, $tokens, $check_query_values = false) {
        $tokens = array_values(array_unique(array_map('strtolower', (array) $tokens)));
        foreach ((array) $parts['segments'] as $segment) {
            if (in_array(strtolower((string) $segment), $tokens, true)) {
                return true;
            }
        }
        foreach ((array) $parts['query'] as $key => $value) {
            if (in_array(strtolower((string) $key), $tokens, true)) {
                return true;
            }
            if (!$check_query_values || is_array($value)) {
                continue;
            }
            if (in_array(strtolower(trim((string) $value)), $tokens, true)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('ucp_dropin_matches_configured_url_pattern')) {
    /**
     * Match drop-in URL exclusions with the same exact semantics as the runtime.
     * Known commerce, WordPress and builder tokens are not substring matches.
     * Custom fragments retain their historical substring behavior.
     */
    function ucp_dropin_matches_configured_url_pattern($url, $pattern) {
        $url = (string) $url;
        $pattern = strtolower(rawurldecode(trim((string) $pattern)));
        if ('' === $url || '' === $pattern) {
            return false;
        }
        if (false !== strpos($pattern, '(.*)') || false !== strpos($pattern, '*')) {
            return ucp_dropin_wildcard_match($url, $pattern);
        }

        $parts = ucp_dropin_url_safety_parts($url);
        $query_pattern = $pattern;
        if (false !== strpos($query_pattern, '?')) {
            $query_pattern = substr($query_pattern, strpos($query_pattern, '?') + 1);
        }
        $query_pattern = ltrim($query_pattern, '&?/');
        if (false !== strpos($query_pattern, '=') && false === strpos($query_pattern, '/')) {
            $pair = array_pad(explode('=', $query_pattern, 2), 2, '');
            $query_key = strtolower(trim((string) $pair[0]));
            $expected_value = strtolower(trim((string) $pair[1]));
            if ('' === $query_key || !array_key_exists($query_key, (array) $parts['query'])) {
                return false;
            }
            if ('' === $expected_value) {
                return true;
            }
            $actual_value = $parts['query'][$query_key];
            if (is_array($actual_value)) {
                $actual_values = array_map(static function($value) {
                    return strtolower(trim((string) $value));
                }, $actual_value);
                return in_array($expected_value, $actual_values, true);
            }
            return strtolower(trim((string) $actual_value)) === $expected_value;
        }

        if ('/' === substr($pattern, 0, 1)) {
            $pattern_path = ucp_dropin_parse_url($pattern, PHP_URL_PATH);
            $pattern_path = '/' . trim(is_string($pattern_path) ? $pattern_path : '', '/');
            if ('/' === $pattern_path) {
                return '/' === $parts['path'];
            }
            return $parts['path'] === $pattern_path || 0 === strpos($parts['path'], $pattern_path . '/');
        }

        $exact_tokens = array(
            'cart', 'checkout', 'winkelwagen', 'afrekenen', 'my-account', 'mijn-account',
            'account', 'order-pay', 'order-received', 'add-payment-method', 'customer-logout',
            'wc-ajax', 'wc-api', 'add-to-cart', 'apply_coupon', 'remove_item', 'update_cart',
            '_wpnonce', 'preview', 'elementor-preview', 'elementor_library', 'elementor_ajax',
            'elementor_iframe', 'bricks', 'bricks-run', 'bricks_preview', 'ct_builder',
            'oxygen_iframe', 'oxygen_preview', 'breakdance', 'breakdance_iframe', 'et_fb',
            'et_bfb', 'fl_builder', 'fl_builder_ui', 'vc_editable', 'vcv-action',
            'wpb_vc_js_status', 'customize_changeset_uuid', 'preview_id', 'preview_nonce',
            'uxb_iframe', 'siteorigin_panels_live_editor', 'wp-admin', 'wp-login.php',
            'wp-json', 'xmlrpc.php', 'wp-content', 'uploads', 'author', 'feed', 'search', 'wc',
        );
        $exact_token = trim($pattern, " /?=&\t\n\r\0\x0B");
        if ('' !== $exact_token && in_array($exact_token, $exact_tokens, true)) {
            $query_value_tokens = array(
                'elementor_ajax', 'elementor_library', 'elementor_iframe', 'bricks-run',
                'bricks_preview', 'ct_builder', 'oxygen_iframe', 'oxygen_preview',
                'breakdance_iframe', 'et_fb', 'et_bfb', 'fl_builder', 'fl_builder_ui',
                'vc_editable', 'vcv-action', 'wpb_vc_js_status', 'uxb_iframe',
                'siteorigin_panels_live_editor',
            );
            return ucp_dropin_url_has_exact_token($parts, array($exact_token), in_array($exact_token, $query_value_tokens, true));
        }

        return false !== stripos(rawurldecode($url), $pattern);
    }
}

if (!function_exists('ucp_dropin_normalize_query_value')) {
    /**
     * Recursively normalize query arrays without collapsing associative keys.
     */
    function ucp_dropin_normalize_query_value($value, $depth = 0, &$remaining = null) {
        if (null === $remaining) {
            $remaining = 100;
        }
        if ($depth > 4 || $remaining < 0) {
            return false;
        }
        if (!is_array($value)) {
            if (!is_scalar($value) && null !== $value) {
                return false;
            }
            $value = (string) ucp_dropin_unslash($value);
            if (strlen($value) > 8192 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                return false;
            }
            return $value;
        }
        $normalized = array();
        foreach ($value as $key => $item) {
            --$remaining;
            if ($remaining < 0 || (!is_int($key) && !ucp_dropin_query_key_is_canonical($key))) {
                return false;
            }
            $clean_key = is_int($key) ? $key : (string) $key;
            $normalized_item = ucp_dropin_normalize_query_value($item, $depth + 1, $remaining);
            if (false === $normalized_item) {
                return false;
            }
            $normalized[$clean_key] = $normalized_item;
        }
        if (!empty($normalized)) {
            ksort($normalized);
        }
        return $normalized;
    }
}

$ucp_dropin_raw_query_string = isset($_SERVER['QUERY_STRING']) && is_scalar($_SERVER['QUERY_STRING'])
    ? (string) ucp_dropin_unslash($_SERVER['QUERY_STRING'])
    : '';
if ('' !== $ucp_dropin_raw_query_string && !ucp_dropin_raw_query_keys_are_canonical($ucp_dropin_raw_query_string)) {
    return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameters are inspected only to decide whether cached HTML may be served.
if (!empty($_GET)) {
    if (!is_array($_GET) || count($_GET) > 100) {
        return;
    }
    $ucp_dropin_query_items_remaining = 100;
    foreach ($_GET as $query_key => $query_value) {
        if (!ucp_dropin_query_key_is_canonical($query_key) || !ucp_dropin_query_value_has_canonical_keys($query_value, 0, $ucp_dropin_query_items_remaining)) {
            return;
        }
        if (ucp_dropin_query_key_is_ignored($query_key, $cache_ignore_query_params, $cache_query_string_inclusions)) {
            continue;
        }
        if (!$cache_query_strings || !ucp_dropin_query_key_matches($query_key, $cache_query_string_inclusions)) {
            return;
        }
    }
}

foreach (array('preview', 'preview_id', 'preview_nonce', 'customize_changeset_uuid', 'customize_theme', 'elementor-preview', 'ct_builder', 'bricks', 'breakdance', 'fl_builder', 'oxygen_iframe', 'et_fb', 'vc_editable', 'nonce', '_wpnonce', 'add-to-cart', 'wc-ajax', 'wc-api', 'apply_coupon', 'remove_item', 'undo_item', 'update_cart', 'add-payment-method', 'order-pay', 'customer-logout') as $query_key) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- query key presence only bypasses cache serving.
    if (isset($_GET[$query_key])) {
        return;
    }
}

if ($block_unknown_cookies && isset($_SERVER['HTTP_COOKIE']) && is_scalar($_SERVER['HTTP_COOKIE'])) {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw cookie names must be validated before any normalization can alter them.
    $raw_cookie_header = ucp_dropin_unslash($_SERVER['HTTP_COOKIE']);
    if (strlen((string) $raw_cookie_header) > 16384 || false !== strpos((string) $raw_cookie_header, "\0")) {
        return;
    }
    if ('' !== trim((string) $raw_cookie_header) && !ucp_dropin_cookie_header_is_safe($raw_cookie_header, $exclude_cookies, $safe_cookies)) {
        return;
    }
}

if (!empty($_COOKIE) && is_array($_COOKIE)) {
    if (count($_COOKIE) > 128) {
        return;
    }
    foreach ($_COOKIE as $cookie_name => $cookie_value) {
        if ((!is_scalar($cookie_value) && null !== $cookie_value) || strlen((string) $cookie_value) > 4096 || false !== strpos((string) $cookie_value, "\0")) {
            return;
        }
        $cookie_name = (string) ucp_dropin_unslash($cookie_name);
        if (!ucp_dropin_cookie_name_is_valid($cookie_name)) {
            return;
        }
        $normalized_cookie_name = strtolower($cookie_name);
        $matched_cookie_rule = false;
        foreach ($exclude_cookies as $cookie_fragment) {
            $cookie_fragment = ucp_dropin_sanitize_key((string) $cookie_fragment);
            if ('' !== $cookie_fragment && false !== strpos($normalized_cookie_name, $cookie_fragment)) {
                return;
            }
        }
        foreach ($safe_cookies as $cookie_fragment) {
            $cookie_fragment = trim((string) $cookie_fragment);
            if (ucp_dropin_cookie_name_is_valid($cookie_fragment) && 0 === strpos($cookie_name, $cookie_fragment)) {
                $matched_cookie_rule = true;
                break;
            }
        }
        if ($block_unknown_cookies && !$matched_cookie_rule) {
            return;
        }
    }
}

$uri = ucp_dropin_server_value('REQUEST_URI');
$uri = '' !== $uri ? $uri : '/';
$path_only = ucp_dropin_parse_url($uri, PHP_URL_PATH);
$path_only = is_string($path_only) && '' !== $path_only ? $path_only : '/';
$query_only = ucp_dropin_parse_url($uri, PHP_URL_QUERY);
$policy_path = '/' === $path_only ? '/' : rtrim($path_only, '/') . '/';
$request_target = $policy_path . (is_string($query_only) && '' !== $query_only ? '?' . $query_only : '');

foreach ($exclude_paths as $excluded_fragment) {
    if (ucp_dropin_matches_configured_url_pattern($request_target, $excluded_fragment)) {
        return;
    }
}

$request_user_agent = ucp_dropin_server_value('HTTP_USER_AGENT');
foreach ($exclude_user_agents as $excluded_user_agent) {
    if (ucp_dropin_wildcard_match($request_user_agent, $excluded_user_agent)) {
        return;
    }
}

$path = rtrim($path_only, '/');
$raw_path = '' === $path ? '/' : $path;
$path = ucp_dropin_cache_path_slug($path_only);
$path_hash = substr(md5($raw_path), 0, 8);
$query = ucp_dropin_parse_url($uri, PHP_URL_QUERY);
$normalized_query = '';
if ($query) {
    if (!ucp_dropin_raw_query_keys_are_canonical($query)) {
        return;
    }
    parse_str($query, $query_args);
    if (is_array($query_args)) {
        $normalized_args = array();
        foreach ($query_args as $query_arg_key => $query_arg_value) {
            if (!ucp_dropin_query_key_is_canonical($query_arg_key) || !ucp_dropin_query_value_has_canonical_keys($query_arg_value)) {
                return;
            }
            $query_arg_key = (string) $query_arg_key;
            if (ucp_dropin_query_key_is_ignored($query_arg_key, $cache_ignore_query_params, $cache_query_string_inclusions)) {
                continue;
            }
            if (!$cache_query_strings || !ucp_dropin_query_key_matches($query_arg_key, $cache_query_string_inclusions)) {
                continue;
            }
            $normalized_value = ucp_dropin_normalize_query_value($query_arg_value);
            if (false === $normalized_value) {
                return;
            }
            $normalized_args[$query_arg_key] = $normalized_value;
        }
        if (!empty($normalized_args)) {
            ksort($normalized_args);
            $normalized_query = http_build_query($normalized_args, '', '&', PHP_QUERY_RFC3986);
        }
    }
}
$query_key = '' !== $normalized_query ? md5($normalized_query) : 'noq';

if (!function_exists('ucp_dropin_normalize_host')) {
    function ucp_dropin_normalize_host($host) {
        if (!is_scalar($host)) {
            return '';
        }
        $host = trim((string) $host);
        if ('' === $host || preg_match('~[\x00-\x20\x7f<>{}\\/@?#]~', $host)) {
            return '';
        }
        $host = strtolower($host);
        if (preg_match('/^\[([^]]+)\](?::([0-9]{1,5}))?$/', $host, $match)) {
            if (!filter_var($match[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return '';
            }
            if (isset($match[2]) && ((int) $match[2] < 1 || (int) $match[2] > 65535)) {
                return '';
            }
            return '[' . strtolower($match[1]) . ']';
        }
        if (1 === substr_count($host, ':')) {
            if (!preg_match('/^([^:]+):([0-9]{1,5})$/', $host, $match)
                || (int) $match[2] < 1 || (int) $match[2] > 65535) {
                return '';
            }
            $host = $match[1];
        } elseif (false !== strpos($host, ':')) {
            return '';
        }
        $host = rtrim($host, '.');
        if ('' === $host) {
            return '';
        }
        if (preg_match('/[^\x20-\x7e]/', $host)) {
            if (!function_exists('idn_to_ascii')) {
                return '';
            }
            $flags = defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0;
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
            $ascii = idn_to_ascii($host, $flags, $variant);
            if (!is_string($ascii) || '' === $ascii) {
                return '';
            }
            $host = strtolower($ascii);
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $host;
        }
        if (strlen($host) > 253 || !preg_match('/^[a-z0-9.-]+$/', $host)) {
            return '';
        }
        foreach (explode('.', $host) as $label) {
            if ('' === $label || strlen($label) > 63 || '-' === $label[0] || '-' === substr($label, -1)) {
                return '';
            }
        }
        return $host;
    }
}
$host_header = ucp_dropin_normalize_host(ucp_dropin_server_value('HTTP_HOST'));
$allowed_hosts = !empty($config['allowed_hosts']) && is_array($config['allowed_hosts']) ? $config['allowed_hosts'] : array();
if (!empty($allowed_hosts)) {
    if ('' === $host_header) {
        return;
    }
    $is_allowed = false;
    foreach ($allowed_hosts as $allowed) {
        if (ucp_dropin_normalize_host($allowed) === $host_header) {
            $is_allowed = true;
            break;
        }
    }
    if (!$is_allowed) {
        // Untrusted Host header; do not serve a cached response from a poisoned key.
        return;
    }
}
$host = !empty($config['home_host']) ? ucp_dropin_normalize_host((string) $config['home_host']) : $host_header;
$host_key = $host ? md5(strtolower($host)) : 'nohost';
$user_agent = ucp_dropin_server_value('HTTP_USER_AGENT');
// Mobile regex comes from dropin-config.php (written by UCP_Helpers::mobile_user_agent_regex())
// so the drop-in and the PHP fallback can never disagree. The literal is only a safety net for
// configs written by an older version.
$mobile_regex = !empty($config['mobile_user_agent_regex']) ? (string) $config['mobile_user_agent_regex'] : '/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi|iPhone|iPad|iPod/i';
$is_mobile = $cache_mobile_separately && 1 === @preg_match($mobile_regex, $user_agent);

// Per-currency / per-language cache variation. MUST match UCP_Helpers::cache_vary_suffix()
// byte-for-byte so the early drop-in and the PHP fallback agree on the key.
if (!function_exists('ucp_dropin_vary_suffix')) {
    function ucp_dropin_vary_suffix($vary_cookies) {
        if (empty($vary_cookies) || !is_array($vary_cookies) || empty($_COOKIE) || !is_array($_COOKIE)) {
            return '';
        }
        $pairs = array();
        foreach ($_COOKIE as $name => $value) {
            $raw_name = (string) $name;
            $match_name = ucp_dropin_sanitize_key($raw_name);
            if ('' === $match_name || is_array($value)) {
                continue;
            }
            foreach ($vary_cookies as $fragment) {
                $fragment = ucp_dropin_sanitize_key((string) $fragment);
                if ('' !== $fragment && 0 === strpos($match_name, $fragment)) {
                    $raw_value = (string) ucp_dropin_unslash($value);
                    $name_hash = hash('sha256', $raw_name);
                    $pairs[$name_hash] = $name_hash . '=' . hash('sha256', $raw_value);
                    break;
                }
            }
        }
        if (empty($pairs)) {
            return '';
        }
        ksort($pairs);
        return '-v' . substr(md5(implode('|', $pairs)), 0, 10);
    }
}
$vary_cookies = !empty($config['vary_cookies']) && is_array($config['vary_cookies']) ? $config['vary_cookies'] : array();
$ucp_dropin_header_policy = ucp_dropin_cache_header_policy($config);
$ucp_dropin_vary_header = isset($ucp_dropin_header_policy['vary_headers']) && is_string($ucp_dropin_header_policy['vary_headers'])
    ? $ucp_dropin_header_policy['vary_headers']
    : '';
if ('' === $ucp_dropin_vary_header) {
    $ucp_dropin_vary_headers = array('Accept', 'Accept-Encoding');
    if (!empty($vary_cookies)) {
        array_unshift($ucp_dropin_vary_headers, 'Cookie');
    }
    if ($cache_mobile_separately) {
        $ucp_dropin_vary_headers[] = 'User-Agent';
    }
    $ucp_dropin_vary_header = implode(', ', array_values(array_unique($ucp_dropin_vary_headers)));
}
$suffix = 'guest' . ($is_mobile ? '-mobile' : '') . ucp_dropin_vary_suffix($vary_cookies);
// Every segment is already restricted to [A-Za-z0-9_.-] (md5 hex, the safe slug, or fixed
// literals), exactly like UCP_Helpers::cache_key_for_url(), so no whole-key rewrite is applied.
$cache_key = $host_key . '-' . $path . '-' . $path_hash . '-' . $suffix . '-' . $query_key;
$cache_file = WP_CONTENT_DIR . '/cache/ultracache-pro/pages/' . $cache_key . '.html';
$ttl = array_key_exists('ttl', $config) ? max(0, (int) $config['ttl']) : 10 * 3600;
$cache_meta = array();
$cache_meta_file = $cache_file . '.meta.json';
if (ucp_dropin_is_safe_cache_file($cache_meta_file)) {
    $meta_size = filesize($cache_meta_file);
    if (false !== $meta_size && $meta_size > 0 && $meta_size <= 16384) {
        $meta_raw = file_get_contents($cache_meta_file, false, null, 0, 16385); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- early drop-in runs before WordPress filesystem APIs are available.
        if (!is_string($meta_raw) || strlen($meta_raw) > 16384) {
            $meta_raw = '';
        }
        $decoded_meta = '' !== $meta_raw ? ucp_dropin_safe_json_decode($meta_raw, true) : null;
        if (is_array($decoded_meta)) {
            $cache_meta = $decoded_meta;
            if (array_key_exists('ttl', $cache_meta)) {
                $ttl = min(31536000, max(0, (int) $cache_meta['ttl']));
            }
        }
    }
}


$cached_content_type = 'text/html; charset=UTF-8';
$cached_status = 200;
$content_hash = '';
if (array_key_exists('content_type', $cache_meta)) {
    $cached_content_type = ucp_dropin_normalize_page_cache_content_type($cache_meta['content_type']);
    if ('' === $cached_content_type) {
        return;
    }
}
if (array_key_exists('status_code', $cache_meta)) {
    $cached_status = $cache_meta['status_code'];
    if (!ucp_dropin_status_is_page_cacheable($cached_status)) {
        return;
    }
    $cached_status = (int) $cached_status;
}
if (!empty($cache_meta['content_sha256']) && 1 === preg_match('/^[a-f0-9]{64}$/i', (string) $cache_meta['content_sha256'])) {
    $content_hash = strtolower((string) $cache_meta['content_sha256']);
}
$cached_response_headers = array();
if (!empty($cache_meta['response_headers']) && is_array($cache_meta['response_headers'])) {
    $cached_response_headers = ucp_dropin_normalize_page_cache_response_headers($cache_meta['response_headers']);
}

// The cached representation itself determines which Accept media range must be checked.
// This avoids substring false positives such as text/html;q=0 and honours exact-over-wildcard precedence.
$ucp_dropin_accept = ucp_dropin_server_value('HTTP_ACCEPT');
$ucp_dropin_content_type_segments = array_map('trim', explode(';', strtolower($cached_content_type)));
$ucp_dropin_media_type = (string) array_shift($ucp_dropin_content_type_segments);
$ucp_dropin_media_parts = explode('/', $ucp_dropin_media_type, 2);
$ucp_dropin_media_parameters = array();
foreach ($ucp_dropin_content_type_segments as $ucp_dropin_content_type_parameter) {
    $ucp_dropin_parameter_pair = explode('=', $ucp_dropin_content_type_parameter, 2);
    if (2 === count($ucp_dropin_parameter_pair) && '' !== trim($ucp_dropin_parameter_pair[0])) {
        $ucp_dropin_media_parameters[strtolower(trim($ucp_dropin_parameter_pair[0]))] = trim($ucp_dropin_parameter_pair[1], " \t\n\r\0\x0B\"");
    }
}
if (2 !== count($ucp_dropin_media_parts)
    || ucp_dropin_media_quality($ucp_dropin_accept, $ucp_dropin_media_parts[0], $ucp_dropin_media_parts[1], $ucp_dropin_media_parameters) <= 0.0) {
    return;
}

if (ucp_dropin_is_safe_cache_file($cache_file) && (0 === $ttl || (filemtime($cache_file) + $ttl) > time())) {
    $file_size = filesize($cache_file);
    if (false !== $file_size && $file_size > 0) {
        $file_mtime    = (int) filemtime($cache_file);
        $remaining_ttl = 0 === $ttl ? 31536000 : max(0, ($file_mtime + $ttl) - time());
        $accept_encoding = ucp_dropin_server_value('HTTP_ACCEPT_ENCODING');
        $representation_file = $cache_file;
        $representation_size = $file_size;
        $representation_encoding = '';
        $variant_candidates = array();
        $br_quality = ucp_dropin_encoding_quality($accept_encoding, 'br');
        $gzip_quality = ucp_dropin_encoding_quality($accept_encoding, 'gzip');
        $identity_quality = ucp_dropin_identity_quality($accept_encoding);
        if ($br_quality > 0.0) {
            $variant_candidates['br'] = array('path' => $cache_file . '.br', 'quality' => $br_quality, 'priority' => 2);
        }
        if ($gzip_quality > 0.0) {
            $variant_candidates['gzip'] = array('path' => $cache_file . '.gz', 'quality' => $gzip_quality, 'priority' => 1);
        }
        if ($identity_quality > 0.0) {
            $variant_candidates['identity'] = array('path' => $cache_file, 'quality' => $identity_quality, 'priority' => 0);
        }
        uasort($variant_candidates, function ($left, $right) {
            if ($left['quality'] === $right['quality']) {
                return $right['priority'] <=> $left['priority'];
            }
            return $right['quality'] <=> $left['quality'];
        });
        foreach ($variant_candidates as $encoding => $candidate_data) {
            $candidate = $candidate_data['path'];
            if (!ucp_dropin_is_safe_cache_file($candidate)) {
                continue;
            }
            $candidate_mtime = filemtime($candidate);
            $candidate_size = filesize($candidate);
            if (false === $candidate_mtime || $candidate_mtime < $file_mtime || false === $candidate_size || $candidate_size <= 0) {
                continue;
            }
            $representation_file = $candidate;
            $representation_size = $candidate_size;
            $representation_encoding = 'identity' === $encoding ? '' : $encoding;
            break;
        }
        if ('' === $representation_encoding && $identity_quality <= 0.0) {
            // Identity is forbidden and no acceptable compressed sibling survived validation.
            return;
        }
        $etag          = ucp_dropin_build_etag($file_mtime, $representation_size, $representation_encoding, $content_hash);
        $last_modified = gmdate('D, d M Y H:i:s', $file_mtime) . ' GMT';

        // 304 Not Modified support (RFC 7232 — accept comma-separated lists and weak validators).
        $if_none_match    = ucp_dropin_server_value('HTTP_IF_NONE_MATCH');
        $if_modified_since = ucp_dropin_server_value('HTTP_IF_MODIFIED_SINCE');
        $normalized_etag = 0 === strncmp($etag, 'W/', 2) ? substr($etag, 2) : $etag;
        $etag_match = false;
        if ('' !== $if_none_match) {
            if ('*' === $if_none_match) {
                $etag_match = true;
            } else {
                foreach (explode(',', $if_none_match) as $candidate) {
                    $candidate = trim($candidate);
                    if ('' === $candidate) {
                        continue;
                    }
                    // Strip the optional weak prefix before weak comparison.
                    if (0 === strncmp($candidate, 'W/', 2)) {
                        $candidate = substr($candidate, 2);
                    }
                    if ($candidate === $normalized_etag) {
                        $etag_match = true;
                        break;
                    }
                }
            }
        }
        $not_modified = ucp_dropin_status_allows_not_modified($cached_status) && ('' !== $if_none_match
            ? $etag_match
            : ($if_modified_since && strtotime($if_modified_since) >= $file_mtime));
        if ($not_modified) {
            ucp_dropin_send_cached_page_response_headers($cached_response_headers);
            header('X-UltraCache: HIT-304');
            header('ETag: ' . $etag);
            header('Last-Modified: ' . $last_modified);
            header('Cache-Control: ' . ucp_dropin_public_html_cache_control($config, $remaining_ttl));
            ucp_dropin_send_shared_html_cache_headers($config, $remaining_ttl);
            // RFC 7232 §4.1: a 304 must carry the same Vary the 200 would, so shared/CDN
            // caches key the validated entry on Accept and Accept-Encoding just like the full HIT below.
            header('Vary: ' . $ucp_dropin_vary_header);
            if ('' !== $representation_encoding) {
                header('Content-Encoding: ' . $representation_encoding);
            }
            http_response_code(304);
            ucp_dropin_record_cache_insight('HIT-304', $config);
            exit;
        }

        ucp_dropin_send_cached_page_response_headers($cached_response_headers);
        header('X-UltraCache: HIT');
        header('Cache-Control: ' . ucp_dropin_public_html_cache_control($config, $remaining_ttl));
        ucp_dropin_send_shared_html_cache_headers($config, $remaining_ttl);
        header('ETag: ' . $etag);
        header('Last-Modified: ' . $last_modified);
        header('Vary: ' . $ucp_dropin_vary_header);
        header('X-UltraCache-Age: ' . (int)(time() - $file_mtime));
        if (200 !== $cached_status) {
            http_response_code($cached_status);
        }
        header('Content-Type: ' . $cached_content_type);
        ucp_dropin_record_cache_insight('HIT', $config);

        if (!$ucp_dropin_is_head && ucp_dropin_is_light_preload_request()) {
            header('X-UltraCache-Light-Preload: 1');
            header('Content-Length: 0', true);
            exit;
        }

        // Serve the already selected, generation-coherent representation.
        if ('' !== $representation_encoding) {
            header('Content-Encoding: ' . $representation_encoding);
            header('Content-Length: ' . (int) $representation_size);
            if (!$ucp_dropin_is_head) {
                readfile($representation_file); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions -- pre-compressed cached HTML streamed to the client.
            }
            exit;
        }

        // If no pre-compressed variant exists, serve the identity cache file.
        // This keeps the advanced-cache.php fast path CPU-cheap under traffic spikes.

        header('Content-Length: ' . (int) $file_size);
        if (!$ucp_dropin_is_head) {
            readfile($cache_file); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions -- cached HTML streamed to the client.
        }
        exit;
    }
}
