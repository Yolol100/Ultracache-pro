<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_URL_Core_Trait {
    public static function normalize_multiline($value) {
        $max_lines = 2000;
        $max_line_bytes = 4096;
        $max_total_bytes = 2 * MB_IN_BYTES;

        if (is_array($value)) {
            if (count($value) > $max_lines) {
                $value = array_slice($value, 0, $max_lines);
            }
            $lines = array();
            $input_bytes = 0;
            foreach ($value as $line) {
                if (!is_scalar($line)) {
                    continue;
                }
                $line = (string) $line;
                if (strlen($line) > $max_line_bytes) {
                    $line = substr($line, 0, $max_line_bytes);
                }
                if ($input_bytes + strlen($line) > $max_total_bytes) {
                    $remaining = $max_total_bytes - $input_bytes;
                    if ($remaining <= 0) {
                        break;
                    }
                    $line = substr($line, 0, $remaining);
                }
                $lines[] = $line;
                $input_bytes += strlen($line);
                if ($input_bytes >= $max_total_bytes) {
                    break;
                }
            }
        } elseif (is_scalar($value)) {
            $raw = (string) $value;
            if (strlen($raw) > $max_total_bytes) {
                $raw = substr($raw, 0, $max_total_bytes);
            }
            $lines = preg_split('/\r\n|\r|\n/', $raw, $max_lines + 1);
            if (is_array($lines) && count($lines) > $max_lines) {
                $lines = array_slice($lines, 0, $max_lines);
            }
        } else {
            return array();
        }

        $normalized = array();
        $normalized_bytes = 0;
        foreach ((array) $lines as $line) {
            $line = trim(str_replace("\0", '', (string) $line));
            if ('' === $line) {
                continue;
            }
            if (strlen($line) > $max_line_bytes) {
                $line = substr($line, 0, $max_line_bytes);
            }
            if (isset($normalized[$line])) {
                continue;
            }
            if ($normalized_bytes + strlen($line) > $max_total_bytes) {
                $remaining = $max_total_bytes - $normalized_bytes;
                if ($remaining <= 0) {
                    break;
                }
                $line = substr($line, 0, $remaining);
                if ('' === $line || isset($normalized[$line])) {
                    break;
                }
            }
            $normalized[$line] = true;
            $normalized_bytes += strlen($line);
            if (count($normalized) >= $max_lines || $normalized_bytes >= $max_total_bytes) {
                break;
            }
        }

        return array_keys($normalized);
    }
    /**
     * Validate an outbound URL for SSRF-safe https requests to a third-party service.
     *
     * Single source of truth used by every module that talks to an external endpoint
     * (cloud, headless renderer, CDN providers, compat overlay). Requires https, a public
     * host (no localhost/.local/.test/.invalid, no embedded credentials) and, for hostnames,
     * DNS resolution that does not land on a private/reserved network.
     *
     * @param string $url
     * @param array  $opts {
     *     @type bool $resolve_dns Whether to DNS-resolve hostnames (default true).
     * }
     * @return string The validated URL, or '' when unsafe/invalid.
     */
    public static function validate_public_https_url($url, $opts = array()) {
        if (!is_scalar($url)) {
            return '';
        }
        $opts = is_array($opts) ? $opts : array();
        $url = esc_url_raw((string) $url);
        if ('' === $url || !wp_http_validate_url($url)) {
            return '';
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return '';
        }
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        $host   = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        if ('https' !== $scheme || '' === $host || !empty($parts['user']) || !empty($parts['pass'])) {
            return '';
        }

        // A trailing DNS root dot is semantically irrelevant but can hide
        // localhost or a reserved suffix from a literal string comparison.
        $host_for_checks = rtrim($host, '.');
        if ('' === $host_for_checks || false !== strpos($host_for_checks, '%')) {
            return '';
        }

        // parse_url() keeps brackets around IPv6 URL literals. Normalize them
        // before loopback/private checks so DNS-less validation cannot treat
        // addresses such as [::1] as ordinary hostnames.
        $ip_host = ('[' === substr($host_for_checks, 0, 1) && ']' === substr($host_for_checks, -1))
            ? substr($host_for_checks, 1, -1)
            : $host_for_checks;
        if (in_array($ip_host, array('localhost', '127.0.0.1', '::1'), true)) {
            return '';
        }
        foreach (array('.local', '.test', '.invalid') as $suffix) {
            if ($host_for_checks === ltrim($suffix, '.') || (function_exists('str_ends_with') ? str_ends_with($host_for_checks, $suffix) : (substr($host_for_checks, -strlen($suffix)) === $suffix))) {
                return '';
            }
        }
        if (filter_var($ip_host, FILTER_VALIDATE_IP)) {
            return self::is_public_ip_address($ip_host) ? $url : '';
        }

        // URL parsers and operating-system resolvers may interpret decimal,
        // octal, hexadecimal or shortened IPv4 forms as an IP address even
        // though FILTER_VALIDATE_IP does not. Reject numeric-looking hosts
        // rather than letting DNS-less settings validation approve them.
        if (preg_match('/^(?:(?:0x[0-9a-f]+|[0-9]+)(?:\.|$))+$/i', $ip_host)) {
            return '';
        }

        $resolve = !isset($opts['resolve_dns']) || !empty($opts['resolve_dns']);
        if ($resolve && !self::host_resolves_to_public_ip($host_for_checks)) {
            return '';
        }
        return $url;
    }
    /**
     * Determine whether an IP literal is globally routable enough for an outbound request.
     *
     * PHP's NO_PRIV_RANGE/NO_RES_RANGE flags do not reject every documentation,
     * benchmark or special-use IPv6 range on all supported PHP builds, so keep a
     * small explicit deny-list in addition to the native checks.
     *
     * @param string $ip IPv4 or IPv6 literal without URL brackets.
     * @return bool
     */
    protected static function is_public_ip_address($ip) {
        $ip = strtolower(trim((string) $ip, "[] \t\n\r\0\x0B"));
        if ('' === $ip || false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        $is_ipv6 = false !== strpos($ip, ':');
        // Public HTTPS endpoints should use globally routable unicast addresses.
        // This also rejects IPv4-translation, multicast, legacy site-local and
        // currently reserved IPv6 space that native PHP filters can allow.
        if ($is_ipv6 && !self::ip_address_in_cidr($ip, '2000::/3')) {
            return false;
        }

        $blocked_cidrs = $is_ipv6
            ? array('2001::/32', '2001:2::/48', '2001:10::/28', '2001:20::/28', '2001:30::/28', '2001:db8::/32', '2002::/16', '3fff::/20')
            : array('0.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4');
        foreach ($blocked_cidrs as $cidr) {
            if (self::ip_address_in_cidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Binary-safe CIDR membership check for IPv4 and IPv6 literals.
     *
     * @param string $ip   Candidate address.
     * @param string $cidr Network in address/prefix form.
     * @return bool
     */
    protected static function ip_address_in_cidr($ip, $cidr) {
        $parts = explode('/', (string) $cidr, 2);
        if (2 !== count($parts)) {
            return false;
        }
        $address = @inet_pton((string) $ip);
        $network = @inet_pton((string) $parts[0]);
        if (false === $address || false === $network || strlen($address) !== strlen($network)) {
            return false;
        }
        $bits = (int) $parts[1];
        $max_bits = 8 * strlen($address);
        if ($bits < 0 || $bits > $max_bits) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $remaining = $bits % 8;
        if ($bytes > 0 && substr($address, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }
        if (0 === $remaining) {
            return true;
        }
        $mask = (0xFF << (8 - $remaining)) & 0xFF;
        return (ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }

    /**
     * Resolve a hostname and confirm none of its A/AAAA records point to a private/reserved IP.
     *
     * @param string $host
     * @return bool
     */
    public static function host_resolves_to_public_ip($host) {
        if (!is_scalar($host)) {
            return false;
        }
        $host = strtolower(trim((string) $host));
        if ('' === $host) {
            return false;
        }
        $records = function_exists('dns_get_record') ? @dns_get_record($host, DNS_A + DNS_AAAA) : false;
        if (empty($records) || !is_array($records)) {
            $ip = @gethostbyname($host);
            $records = ($ip && $ip !== $host) ? array(array('ip' => $ip)) : array();
        }
        if (empty($records)) {
            return false;
        }
        $has_valid_ip = false;
        foreach ($records as $record) {
            $ip = '';
            if (!empty($record['ip'])) {
                $ip = (string) $record['ip'];
            } elseif (!empty($record['ipv6'])) {
                $ip = (string) $record['ipv6'];
            }
            if ('' === $ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            $has_valid_ip = true;
            if (!self::is_public_ip_address($ip)) {
                return false;
            }
        }
        return $has_valid_ip;
    }
    /**
     * Common wp_remote_* argument defaults for outbound requests, merged with overrides.
     *
     * @param array $overrides
     * @return array
     */
    public static function default_remote_args($overrides = array()) {
        $defaults = array(
            'timeout'            => 20,
            'redirection'        => 0,
            'reject_unsafe_urls' => true,
            'sslverify'          => true,
            'user-agent'         => 'UltraCache/' . (defined('UCP_VERSION') ? UCP_VERSION : 'dev'),
        );
        $args = array_merge($defaults, is_array($overrides) ? $overrides : array());
        $args['timeout'] = max(1, min(120, absint(isset($args['timeout']) ? $args['timeout'] : $defaults['timeout'])));
        $args['redirection'] = max(0, min(3, absint(isset($args['redirection']) ? $args['redirection'] : 0)));
        $args['reject_unsafe_urls'] = true;
        $args['sslverify'] = true;
        unset($args['stream'], $args['filename']);
        $user_agent = isset($args['user-agent']) && is_scalar($args['user-agent']) ? (string) $args['user-agent'] : $defaults['user-agent'];
        $sanitized_user_agent = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $user_agent);
        $user_agent = is_string($sanitized_user_agent) ? trim($sanitized_user_agent) : $defaults['user-agent'];
        $args['user-agent'] = '' !== $user_agent ? substr($user_agent, 0, 256) : $defaults['user-agent'];
        if (array_key_exists('limit_response_size', $args)) {
            $args['limit_response_size'] = max(KB_IN_BYTES, min(10 * MB_IN_BYTES, absint($args['limit_response_size'])));
        }
        return $args;
    }

    /**
     * Validate a response body against the same byte cap used by limit_response_size.
     *
     * A body exactly equal to the cap is rejected because the HTTP transport may
     * have truncated it at that boundary.
     *
     * @param mixed $body      Candidate response body.
     * @param int   $max_bytes Exclusive upper byte limit.
     * @param int   $min_bytes Inclusive lower byte limit.
     * @return string|false Valid body, or false when outside the bounds.
     */
    public static function bounded_response_body($body, $max_bytes, $min_bytes = 1) {
        if (!is_scalar($max_bytes) && null !== $max_bytes) {
            $max_bytes = 0;
        }
        if (!is_scalar($min_bytes) && null !== $min_bytes) {
            $min_bytes = 1;
        }
        $max_bytes = absint($max_bytes);
        $min_bytes = max(0, absint($min_bytes));
        if (!is_string($body) || $max_bytes < 1 || $min_bytes >= $max_bytes) {
            return false;
        }

        $length = strlen($body);
        if ($length < $min_bytes || $length >= $max_bytes) {
            return false;
        }

        return $body;
    }

    /**
     * Retrieve and validate a WordPress HTTP API response body.
     *
     * @param array|WP_Error $response  WordPress HTTP API response.
     * @param int            $max_bytes Exclusive upper byte limit.
     * @param int            $min_bytes Inclusive lower byte limit.
     * @return string|false Valid body, or false when unavailable or out of bounds.
     */
    public static function bounded_remote_response_body($response, $max_bytes, $min_bytes = 1) {
        if (!is_scalar($max_bytes) && null !== $max_bytes) {
            $max_bytes = 0;
        }
        if (!is_scalar($min_bytes) && null !== $min_bytes) {
            $min_bytes = 1;
        }
        if (is_wp_error($response)) {
            return false;
        }

        $body = self::bounded_response_body(wp_remote_retrieve_body($response), $max_bytes, $min_bytes);
        if (false === $body) {
            return false;
        }

        $declared = wp_remote_retrieve_header($response, 'content-length');
        $declared = is_scalar($declared) ? absint($declared) : 0;
        if ($declared >= absint($max_bytes)) {
            return false;
        }

        $encoding = strtolower(trim((string) wp_remote_retrieve_header($response, 'content-encoding')));
        if (0 === $declared || '' !== $encoding) {
            return $body;
        }

        return $declared === strlen($body) ? $body : false;
    }
    /**
     * Scheme-relative uploads base URL (e.g. //site.tld/wp-content/uploads), or '' when unknown.
     *
     * @return string
     */
    public static function uploads_baseurl_relative() {
        $uploads = wp_upload_dir();
        if (empty($uploads['baseurl'])) {
            return '';
        }
        return UCP_Helpers::sanitize_preg_replace('#^https?:#i', '', (string) $uploads['baseurl']);
    }
    /**
     * Map an uploads-relative URL to its on-disk realpath inside the uploads dir.
     * Returns '' for anything outside uploads or with a traversal attempt.
     *
     * @param string $url
     * @return string
     */
    public static function uploads_url_to_path($url) {
        if (!is_scalar($url)) {
            return '';
        }
        $url = (string) $url;
        if ('' === $url) {
            return '';
        }
        $uploads = wp_upload_dir();
        if (empty($uploads['baseurl']) || empty($uploads['basedir'])) {
            return '';
        }
        $baseurl   = rtrim((string) UCP_Helpers::sanitize_preg_replace('#^https?:#i', '', (string) $uploads['baseurl']), '/');
        $candidate = (string) UCP_Helpers::sanitize_preg_replace('#^https?:#i', '', $url);
        // Query strings and fragments are not part of the filesystem path. Media URLs
        // commonly carry cache-busting query parameters, while rawurldecode() is needed
        // to resolve ordinary encoded filenames such as "photo%20one.jpg".
        $candidate = (string) UCP_Helpers::sanitize_preg_replace('/[?#].*$/', '', $candidate);
        if ('' === $baseurl || ($candidate !== $baseurl && 0 !== strpos($candidate, $baseurl . '/'))) {
            return '';
        }
        $relative = rawurldecode(ltrim(substr($candidate, strlen($baseurl)), '/'));
        if (false !== strpos($relative, "\0") || false !== strpos($relative, '\\') || self::contains_parent_path_segment($relative)) {
            return '';
        }
        $path      = trailingslashit($uploads['basedir']) . $relative;
        $real      = realpath($path);
        $base_real = realpath($uploads['basedir']);
        if (!$real || !$base_real) {
            return '';
        }
        $real      = wp_normalize_path($real);
        $base_real = trailingslashit(wp_normalize_path($base_real));
        if (0 !== strpos($real, $base_real)) {
            return '';
        }
        return $real;
    }
    /**
     * Map an on-disk uploads path back to its public URL, or '' when outside uploads.
     *
     * @param string $path
     * @return string
     */
    public static function uploads_path_to_url($path) {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir']) || empty($uploads['baseurl']) || !is_string($path) || '' === $path) {
            return '';
        }

        $base_real = realpath((string) $uploads['basedir']);
        $path_real = realpath($path);
        if (false === $base_real || false === $path_real || !is_file($path_real)) {
            return '';
        }

        $base_real = trailingslashit(wp_normalize_path($base_real));
        $path_real = wp_normalize_path($path_real);
        if (0 !== strpos($path_real, $base_real)) {
            return '';
        }

        $relative = ltrim(substr($path_real, strlen($base_real)), '/');
        if ('' === $relative || self::contains_parent_path_segment($relative)) {
            return '';
        }

        return trailingslashit((string) $uploads['baseurl']) . str_replace('%2F', '/', rawurlencode($relative));
    }

    /**
     * Check for an actual parent-directory segment without rejecting valid
     * filenames that merely contain two adjacent dots.
     *
     * @param string $relative Uploads-relative path.
     * @return bool
     */
    private static function contains_parent_path_segment($relative) {
        $segments = preg_split('#/+#', str_replace('\\', '/', (string) $relative));
        return is_array($segments) && in_array('..', $segments, true);
    }
    public static function validate_local_url_arg($value) {
        if (!is_scalar($value)) {
            return false;
        }
        if ('' === (string) $value) {
            return true;
        }
        return '' !== self::strict_local_url((string) $value);
    }
    public static function wildcard_match($haystack, $pattern) {
        if (!is_scalar($haystack) || !is_scalar($pattern)) {
            return false;
        }
        $haystack = (string) $haystack;
        $pattern = trim((string) $pattern);
        if ('' === $pattern || strlen($pattern) > 256 || strlen($haystack) > 16384 || false !== strpos($pattern, "\0")) {
            return false;
        }
        if (false === strpos($pattern, '(.*)') && false === strpos($pattern, '*')) {
            return false !== stripos($haystack, $pattern);
        }
        $regex = preg_quote($pattern, '#');
        $regex = str_replace(array('\\(\\.\\*\\)', '\\*'), '.*', $regex);
        return 1 === preg_match('#' . $regex . '#i', $haystack);
    }
    public static function safe_regex_match($pattern, $subject, $max_length = 180) {
        if (!is_scalar($max_length) && null !== $max_length) {
            $max_length = 180;
        }
        if (!is_scalar($pattern) || !is_scalar($subject)) {
            return false;
        }
        $pattern = trim((string) $pattern);
        $subject = (string) $subject;
        $max_length = max(16, min(1000, absint($max_length)));
        if ('' === $pattern || strlen($pattern) > $max_length || strlen($subject) > 16384 || false !== strpos($pattern, "\0")) {
            return false;
        }
        if (preg_match('/\([^)]*[+*][^)]*\)\s*(?:[+*]|\{)/', $pattern)) {
            return false;
        }
        $regex = '#' . str_replace('#', '\#', $pattern) . '#i';
        $matched = @preg_match($regex, $subject);
        return 1 === $matched && PREG_NO_ERROR === preg_last_error();
    }
    public static function current_url_path() {
        $uri = method_exists('UCP_Helpers', 'server_value') ? UCP_Helpers::server_value('REQUEST_URI', '/', 8192) : '/';
        $uri = esc_url_raw($uri);
        $path = wp_parse_url($uri, PHP_URL_PATH);
        return is_string($path) && '' !== $path ? trailingslashit($path) : '/';
    }
    public static function current_full_url() {
        $uri = method_exists('UCP_Helpers', 'server_value') ? UCP_Helpers::server_value('REQUEST_URI', '/', 8192) : '/';
        $uri = self::normalize_url_syntax(esc_url_raw($uri));
        $parts = wp_parse_url($uri);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return self::enforce_local_url(home_url($path . $query));
    }
    public static function normalize_domain_host($value) {
        if (!is_scalar($value)) {
            return '';
        }
        $value = strtolower(trim((string) $value));
        if ('' === $value || strlen($value) > 512 || false !== strpos($value, "\0")) {
            return '';
        }
        $value = UCP_Helpers::sanitize_preg_replace('#^https?://#i', '', $value);
        $value = UCP_Helpers::sanitize_preg_replace('/[\?#].*$/', '', $value);
        $value = UCP_Helpers::sanitize_preg_replace('#/.*$#', '', $value);
        $value = UCP_Helpers::sanitize_preg_replace('/:\d+$/', '', $value);
        $value = trim($value, '.: /');
        if ('' === $value || strlen($value) > 253 || !preg_match('/^[a-z0-9.-]+$/D', $value) || false !== strpos($value, '..')) {
            return '';
        }
        foreach (explode('.', $value) as $label) {
            if ('' === $label || strlen($label) > 63 || '-' === $label[0] || '-' === substr($label, -1)) {
                return '';
            }
        }
        return $value;
    }
    public static function normalize_url_syntax($url) {
        if (!is_scalar($url)) {
            return '';
        }
        $url = trim((string) $url);
        if ('' === $url || strlen($url) > 8192 || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '';
        }

        $url = UCP_Helpers::sanitize_preg_replace('#^(https?):/{1,}(?!/)#i', '$1://', $url);
        $url = UCP_Helpers::sanitize_preg_replace('#^(https?):/{3,}#i', '$1://', $url);

        return $url;
    }
    public static function is_local_url($url) {
        $url = self::normalize_url_syntax($url);
        // Reject URLs that specify a non-HTTP/HTTPS scheme. Relative URLs have no scheme and are allowed.
        $parts = wp_parse_url($url);
        if (false === $parts) {
            return false;
        }
        $parts  = is_array($parts) ? $parts : array();
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        if ($scheme && !in_array(strtolower((string) $scheme), array('http', 'https'), true)) {
            return false;
        }
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        // An empty host means the URL is relative.
        if ('' === $host) {
            return true;
        }

        $home_parts = wp_parse_url(home_url('/'));
        if (!is_array($home_parts) || empty($home_parts['host']) || empty($home_parts['scheme'])) {
            return false;
        }

        $home_scheme = strtolower((string) $home_parts['scheme']);
        $scheme      = '' !== $scheme ? $scheme : $home_scheme;
        $port        = isset($parts['port']) ? absint($parts['port']) : ('https' === $scheme ? 443 : 80);
        $home_port   = isset($home_parts['port']) ? absint($home_parts['port']) : ('https' === $home_scheme ? 443 : 80);

        return $host === strtolower((string) $home_parts['host']) && $scheme === $home_scheme && $port === $home_port;
    }
    public static function strict_local_url($url, $default = '') {
        if (!is_scalar($url) && null !== $url) {
            return '';
        }
        if (!is_scalar($default) && null !== $default) {
            $default = '';
        }
        $raw = trim((string) $url);
        if ('' === $raw) {
            $raw = (string) $default;
        }
        $raw = self::normalize_url_syntax($raw);
        if ('' === $raw) {
            return '';
        }

        // Note: strict URL validation must reject foreign/unsafe schemes instead of silently converting them to local paths.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $raw) && !preg_match('#^https?://#i', $raw)) {
            return '';
        }

        if (0 === strpos($raw, '//')) {
            $scheme = wp_parse_url(home_url('/'), PHP_URL_SCHEME);
            $raw = ($scheme ? $scheme : 'https') . ':' . $raw;
        }

        $parts = wp_parse_url($raw);
        if (!is_array($parts)) {
            return '';
        }

        if (empty($parts['host'])) {
            $path = isset($parts['path']) ? (string) $parts['path'] : '/';
            $query = isset($parts['query']) ? '?' . (string) $parts['query'] : '';
            if ('' === $path) {
                $path = '/';
            }
            $raw = home_url('/' . ltrim($path, '/') . $query);
        }

        $raw = esc_url_raw($raw);
        if (!$raw || !self::is_local_url($raw) || !wp_http_validate_url($raw)) {
            return '';
        }

        return self::enforce_local_url($raw);
    }
    public static function normalize_local_url_list($urls) {
        $clean = array();
        $max_urls = 1000;
        foreach ((array) $urls as $url) {
            if (!is_scalar($url)) {
                continue;
            }
            $url = self::strict_local_url($url);
            if ($url && wp_http_validate_url($url)) {
                $clean[$url] = true;
                if (count($clean) >= $max_urls) {
                    break;
                }
            }
        }
        return array_keys($clean);
    }
    public static function enforce_local_url($url) {
        if (!is_scalar($url) && null !== $url) {
            return home_url('/');
        }
        $url = esc_url_raw(self::normalize_url_syntax($url));
        if (!$url) {
            return home_url('/');
        }
        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        if (!self::is_local_url($url)) {
            return home_url($path . $query);
        }
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return home_url($path . $query);
        }

        // Always use the configured WordPress origin. This preserves custom ports
        // and prevents same-host input URLs from selecting a different origin.
        $home_parts = wp_parse_url(home_url('/'));
        if (!is_array($home_parts) || empty($home_parts['scheme']) || empty($home_parts['host'])) {
            return home_url($path . $query);
        }
        $host = (string) $home_parts['host'];
        if (false !== strpos($host, ':') && '[' !== substr($host, 0, 1)) {
            $host = '[' . $host . ']';
        }
        $port = !empty($home_parts['port']) ? ':' . absint($home_parts['port']) : '';

        return $home_parts['scheme'] . '://' . $host . $port . $path . $query;
    }
    public static function normalize_url($url) {
        return self::enforce_local_url($url);
    }
}
