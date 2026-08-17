<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared page/REST cache policy and safe per-request rule evaluation.
 */
final class UCP_Cache_Policy {
    const MAX_RULES = 100;
    const HEADER_POLICY_VERSION = 1;

    /**
     * Export the canonical HTML cache-header contract used by runtime code and
     * the early advanced-cache drop-in.
     *
     * @param array|null $settings Optional settings snapshot.
     * @return array
     */
    public static function export_header_policy($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $policy = array(
            'version'      => self::HEADER_POLICY_VERSION,
            'edge_enabled' => !empty($settings['enable_edge_html_cache']),
            'edge_ttl'     => min(DAY_IN_SECONDS, max(MINUTE_IN_SECONDS, absint($settings['edge_html_cache_ttl'] ?? 600))),
            'edge_stale'   => min(WEEK_IN_SECONDS, max(0, absint($settings['edge_html_cache_stale'] ?? 86400))),
        );

        /**
         * Filters the bounded cache-header policy persisted for the drop-in.
         * Invalid or out-of-range values are normalized after the filter.
         *
         * @param array $policy   Header policy.
         * @param array $settings Settings snapshot.
         */
        $policy = apply_filters('ucp_cache_header_policy', $policy, $settings);
        $policy = self::normalize_header_policy($policy);
        $policy['vary_headers'] = self::html_vary_header(false, $settings);
        return $policy;
    }

    /**
     * Normalize a persisted cache-header contract without accepting arbitrary
     * header text from configuration files.
     *
     * @param mixed $policy Candidate policy.
     * @return array
     */
    public static function normalize_header_policy($policy) {
        $policy = is_array($policy) ? $policy : array();
        $vary = isset($policy['vary_headers']) && is_scalar($policy['vary_headers'])
            ? explode(',', (string) $policy['vary_headers'])
            : array('Accept', 'Accept-Encoding');
        $allowed_vary = array('accept', 'accept-encoding', 'cookie', 'user-agent');
        $vary_flags = array();
        foreach (array_slice($vary, 0, 8) as $header) {
            $key = strtolower(trim((string) $header));
            if (in_array($key, $allowed_vary, true)) {
                $vary_flags[$key] = true;
            }
        }
        $normalized_vary = array();
        if (!empty($vary_flags['cookie'])) {
            $normalized_vary[] = 'Cookie';
        }
        $normalized_vary[] = 'Accept';
        $normalized_vary[] = 'Accept-Encoding';
        if (!empty($vary_flags['user-agent'])) {
            $normalized_vary[] = 'User-Agent';
        }

        return array(
            'version'      => self::HEADER_POLICY_VERSION,
            'edge_enabled' => !empty($policy['edge_enabled']),
            'edge_ttl'     => min(DAY_IN_SECONDS, max(MINUTE_IN_SECONDS, absint(isset($policy['edge_ttl']) && is_scalar($policy['edge_ttl']) ? $policy['edge_ttl'] : 600))),
            'edge_stale'   => min(WEEK_IN_SECONDS, max(0, absint(isset($policy['edge_stale']) && is_scalar($policy['edge_stale']) ? $policy['edge_stale'] : 86400))),
            'vary_headers' => implode(', ', array_values(array_unique($normalized_vary))),
        );
    }

    /**
     * Browser and shared-cache policy for public HTML. Browser freshness stays
     * at zero; an optional edge receives only the bounded remaining lifetime.
     *
     * @param int        $remaining    Remaining origin/disk lifetime.
     * @param bool       $allow_shared Whether the shared cache may store it.
     * @param array|null $policy       Optional normalized policy.
     * @return string
     */
    public static function public_html_cache_control($remaining, $allow_shared = true, $policy = null) {
        $remaining = is_scalar($remaining) ? (int) $remaining : 0;
        $policy = null === $policy ? self::export_header_policy() : self::normalize_header_policy($policy);
        $value = 'public, max-age=0, must-revalidate';
        if ($allow_shared && !empty($policy['edge_enabled'])) {
            $shared_ttl = min(max(0, (int) $remaining), (int) $policy['edge_ttl']);
            if ($shared_ttl > 0) {
                $value .= ', s-maxage=' . $shared_ttl;
            }
        }
        return $value;
    }

    /**
     * Shared-cache-only directive for CDN-Cache-Control style headers.
     * An empty string means the edge feature is disabled; no-store means it is
     * enabled but unsafe for this response.
     *
     * @param int        $remaining    Remaining origin/disk lifetime.
     * @param bool       $allow_shared Whether the shared cache may store it.
     * @param array|null $policy       Optional normalized policy.
     * @return string
     */
    public static function shared_html_cache_control($remaining, $allow_shared = true, $policy = null) {
        $remaining = is_scalar($remaining) ? (int) $remaining : 0;
        $policy = null === $policy ? self::export_header_policy() : self::normalize_header_policy($policy);
        if (empty($policy['edge_enabled'])) {
            return '';
        }
        if (!$allow_shared) {
            return 'no-store';
        }

        $shared_ttl = min(max(0, (int) $remaining), (int) $policy['edge_ttl']);
        if ($shared_ttl <= 0) {
            return 'no-store';
        }

        $shared = 'max-age=' . $shared_ttl;
        $stale = (int) $policy['edge_stale'];
        if ($stale > 0) {
            $shared .= ', stale-while-revalidate=' . $stale . ', stale-if-error=' . $stale;
        }
        return $shared;
    }

    /**
     * Canonical Vary dimensions for page and edge HTML caching.
     *
     * @param bool       $private_user_cache Whether the response is user-private.
     * @param array|null $settings           Optional settings snapshot.
     * @return string
     */
    public static function html_vary_header($private_user_cache = false, $settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $headers = array('Accept', 'Accept-Encoding');
        $vary_cookies = class_exists('UCP_Shopper_Cache')
            ? UCP_Shopper_Cache::vary_cookie_fragments()
            : UCP_Helpers::normalize_multiline($settings['cache_vary_cookies'] ?? '');

        if ($private_user_cache || !empty($vary_cookies)) {
            array_unshift($headers, 'Cookie');
        }
        if (!empty($settings['cache_mobile_separately'])) {
            $headers[] = 'User-Agent';
        }
        return implode(', ', array_values(array_unique($headers)));
    }

    public static function bypass_cookie_fragments() {
        return array(
            'wordpress_logged_in_', 'wordpress_sec_', 'wp-postpass_', 'wp-resetpass_', 'comment_author_',
            'switch_to_olduser_', 'wordpress_test_cookie', 'woocommerce_items_in_cart', 'woocommerce_cart_hash',
            'wp_woocommerce_session_', 'woocommerce_recently_viewed', 'woocommerce_checkout_', 'woocommerce_pay_',
            'edd_items_in_cart', 'pll_language', '_icl_current_language', 'wp-wpml_current_language',
            'wpml_browser_redirect_test', 'trp_language', 'wp_lang', 'wcml_client_currency',
            'woocommerce_multicurrency_forced_currency', 'aelia_cs_selected_currency', 'aelia_customer_country',
            'aelia_customer_state', 'aelia_tax_exempt', 'cookie_notice_', 'cmplz_', 'complianz_', 'cookieyes',
            'cky-', 'borlabs',
        );
    }

    public static function bypass_cookie_text() {
        return implode("\n", self::bypass_cookie_fragments());
    }

    /**
     * Cookie-name prefixes that are known not to personalize shared output.
     * The filter is shared by page cache, REST cache and generated drop-in config.
     */
    public static function safe_request_cookie_prefixes() {
        $prefixes = apply_filters('ucp_cache_safe_request_cookie_fragments', array(
            'ct_', 'apbct_', 'ct_sfw', 'cleantalk', 'cookiebot', 'cookie_notice_',
            'cmplz_', 'complianz_', 'cookieyes', 'cky-', 'borlabs', 'joinchat_',
            'wordpress_test_cookie', 'wp-settings-', 'wp-settings-time-',
            '_ga', '_gid', '_gat', '_gcl_', '_fbp', '_fbc', '_hj', '_clck', '_clsk',
            '_pk_id', '_pk_ses', '_uetsid', '_uetvid', '_pin_unauth', '_scid',
            'li_gc', 'li_mc', 'lidc', 'bcookie', 'bscookie', 'tk_ai', 'tk_qs',
            '__stripe_mid', '__stripe_sid', '__cf_bm', 'cf_clearance',
        ));
        $normalized = array();
        foreach ((array) $prefixes as $prefix) {
            if (!is_scalar($prefix)) {
                continue;
            }
            $prefix = trim((string) $prefix);
            if (!self::cookie_name_is_valid($prefix) || strlen($prefix) > 256) {
                continue;
            }
            $normalized[$prefix] = true;
            if (count($normalized) >= 128) {
                break;
            }
        }
        return array_keys($normalized);
    }

    public static function cookie_name_is_valid($cookie_name) {
        return is_scalar($cookie_name)
            && 1 === preg_match('/^[!#$%&\'()*+\-.^_`|~0-9A-Za-z]+$/', (string) $cookie_name);
    }

    public static function cookie_name_matches_prefixes($cookie_name, $prefixes) {
        if (!self::cookie_name_is_valid($cookie_name)) {
            return false;
        }
        $cookie_name = (string) $cookie_name;
        $checked = 0;
        foreach ((array) $prefixes as $prefix) {
            if (++$checked > 128 || !is_scalar($prefix)) {
                return false;
            }
            $prefix = trim((string) $prefix);
            if (!self::cookie_name_is_valid($prefix) || strlen($prefix) > 256) {
                continue;
            }
            if (0 === strpos($cookie_name, $prefix)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Parse an RFC 9110 qvalue. Invalid q parameters fail closed.
     *
     * @param array<int,string> $parameters Header parameters.
     * @return float
     */
    public static function request_header_quality($parameters) {
        $checked = 0;
        foreach ((array) $parameters as $parameter) {
            if (++$checked > 64 || !is_scalar($parameter)) {
                return 0.0;
            }
            $parameter = (string) $parameter;
            if (strlen($parameter) > 256 || preg_match('/[\x00-\x1F\x7F]/', $parameter)) {
                return 0.0;
            }
            if (!preg_match('/^q\s*=/i', $parameter)) {
                continue;
            }
            if (preg_match('/^q\s*=\s*(0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/i', $parameter, $match)) {
                return (float) $match[1];
            }
            return 0.0;
        }
        return 1.0;
    }

    /**
     * Match a normalized cookie name against cache-policy fragments.
     *
     * @param string            $cookie_name Cookie name.
     * @param array<int,string> $fragments   Fragments.
     * @return bool
     */
    public static function cookie_name_matches_fragments($cookie_name, $fragments) {
        if (!is_scalar($cookie_name)) {
            return false;
        }
        $cookie_name = strtolower(sanitize_key((string) $cookie_name));
        if ('' === $cookie_name) {
            return false;
        }
        $checked = 0;
        foreach ((array) $fragments as $fragment) {
            if (++$checked > 128 || !is_scalar($fragment)) {
                return false;
            }
            $fragment = strtolower(sanitize_key((string) $fragment));
            if ('' !== $fragment && false !== strpos($cookie_name, $fragment)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a raw Vary response header contains an unsupported dimension.
     *
     * @param string $raw_header Raw response header.
     * @return bool
     */
    public static function response_vary_is_unsupported($raw_header) {
        if (!is_scalar($raw_header)) {
            return true;
        }
        $raw_header = (string) $raw_header;
        if (strlen($raw_header) > 8192 || preg_match('/[\x00-\x1F\x7F]/', $raw_header)) {
            return true;
        }
        if (!preg_match('/^vary\s*:\s*(.+)$/i', $raw_header, $match)) {
            return false;
        }

        $supported = apply_filters('ucp_supported_response_vary_headers', array('accept-encoding'));
        $normalized_supported = array();
        foreach ((array) $supported as $header) {
            if (!is_scalar($header)) {
                continue;
            }
            $header = strtolower(trim((string) $header));
            if ('' === $header || strlen($header) > 128 || 1 !== preg_match('/^[a-z0-9-]+$/D', $header)) {
                continue;
            }
            $normalized_supported[$header] = true;
            if (count($normalized_supported) >= 64) {
                break;
            }
        }
        $supported = array_keys($normalized_supported);
        $vary_headers = explode(',', (string) $match[1]);
        if (count($vary_headers) > 64) {
            return true;
        }
        foreach ($vary_headers as $header) {
            $header = strtolower(trim($header));
            if ('*' === $header || ('' !== $header && !in_array($header, $supported, true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Shared anonymous caches may only accept an empty Cookie header or cookie
     * names from the explicit safe-prefix list. Any bypass fragment wins.
     */
    public static function cookie_header_is_safe_for_shared_cache($cookie_header) {
        if (!is_scalar($cookie_header)) {
            return false;
        }
        $cookie_header = trim((string) $cookie_header);
        if ('' === $cookie_header) {
            return true;
        }
        if (strlen($cookie_header) > 16384 || preg_match('/[\x00-\x1F\x7F]/', $cookie_header)) {
            return false;
        }
        $pairs = explode(';', $cookie_header);
        if (count($pairs) > 128) {
            return false;
        }
        foreach ($pairs as $pair) {
            if (strlen($pair) > 4352) {
                return false;
            }
            $parts = explode('=', trim($pair), 2);
            $raw_name = isset($parts[0]) ? trim((string) $parts[0]) : '';
            $raw_value = isset($parts[1]) ? (string) $parts[1] : '';
            if (!self::cookie_name_is_valid($raw_name) || strlen($raw_name) > 256 || strlen($raw_value) > 4096) {
                return false;
            }
            $normalized_name = strtolower($raw_name);
            foreach (self::bypass_cookie_fragments() as $fragment) {
                $fragment = sanitize_key((string) $fragment);
                if ('' !== $fragment && false !== strpos($normalized_name, $fragment)) {
                    return false;
                }
            }
            if (!self::cookie_name_matches_prefixes($raw_name, self::safe_request_cookie_prefixes())) {
                return false;
            }
        }
        return true;
    }

    private static function bounded_header_directives($value, $max_bytes = 8192, $max_directives = 128) {
        if (!is_scalar($value)) {
            return false;
        }
        $value = strtolower((string) $value);
        if (strlen($value) > $max_bytes || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return false;
        }
        $directives = explode(',', $value);
        if (count($directives) > $max_directives) {
            return false;
        }
        return $directives;
    }

    /**
     * Whether Cache-Control forbids serving a response from UltraCache without revalidation.
     * A positive s-maxage explicitly permits shared caching and overrides max-age=0.
     */
    public static function cache_control_disallows_shared_storage($value) {
        $directives = self::bounded_header_directives($value);
        if (false === $directives) {
            return true;
        }

        $has_s_maxage = false;
        $s_maxage = null;
        $max_age = null;
        foreach ($directives as $directive) {
            $parts = array_map('trim', explode('=', trim($directive), 2));
            $name = isset($parts[0]) ? $parts[0] : '';
            $raw_value = isset($parts[1]) ? trim($parts[1], " \t\n\r\0\x0B\"") : null;

            if (in_array($name, array('private', 'no-store', 'no-cache'), true)) {
                return true;
            }
            if (!in_array($name, array('max-age', 's-maxage'), true)) {
                continue;
            }
            if (null === $raw_value || 1 !== preg_match('/^-?\d+$/', $raw_value)) {
                return true;
            }

            $age = (int) $raw_value;
            if ('s-maxage' === $name) {
                $has_s_maxage = true;
                $s_maxage = null === $s_maxage ? $age : min($s_maxage, $age);
            } else {
                $max_age = null === $max_age ? $age : min($max_age, $age);
            }
        }

        if ($has_s_maxage) {
            return null === $s_maxage || $s_maxage <= 0;
        }
        return null !== $max_age && $max_age <= 0;
    }

    /**
     * Whether a response directive forbids storage even when no-cache revalidation
     * is deliberately overridden by a compatibility filter.
     */
    public static function cache_control_forbids_storage_unconditionally($value) {
        $directives = self::bounded_header_directives($value);
        if (false === $directives) {
            return true;
        }
        foreach ($directives as $directive) {
            $parts = array_map('trim', explode('=', trim($directive), 2));
            $name = isset($parts[0]) ? $parts[0] : '';
            if (in_array($name, array('private', 'no-store'), true)) {
                return true;
            }
            if (!in_array($name, array('max-age', 's-maxage'), true)) {
                continue;
            }
            $raw_value = isset($parts[1]) ? trim($parts[1], " \t\n\r\0\x0B\"") : null;
            if (null === $raw_value || 1 !== preg_match('/^-?\d+$/', $raw_value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a request Cache-Control value explicitly requires bypass/revalidation.
     */
    public static function request_cache_control_requires_revalidation($value) {
        $directives = self::bounded_header_directives($value);
        if (false === $directives) {
            return true;
        }
        foreach ($directives as $directive) {
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

    /**
     * UltraCache stores an unencoded canonical body and creates its own variants.
     * A pre-encoded upstream body would otherwise be compressed a second time.
     */
    public static function response_content_encoding_disallows_storage($value) {
        if (!is_scalar($value)) {
            return true;
        }
        $value = strtolower(trim((string) $value));
        return '' !== $value && 'identity' !== $value;
    }

    public static function response_status_is_page_cacheable($status_code) {
        if (is_int($status_code)) {
            $status_code = (int) $status_code;
        } elseif (is_string($status_code) && 1 === preg_match('/^[1-9][0-9]{2}$/D', $status_code)) {
            $status_code = (int) $status_code;
        } else {
            return false;
        }
        return in_array($status_code, array(200, 404), true);
    }

    public static function response_content_type_is_page_cacheable($content_type, $allow_feed = false) {
        if (!is_scalar($content_type)) {
            return false;
        }
        $content_type = (string) $content_type;
        if (strlen($content_type) > 255 || preg_match('/[\x00-\x1F\x7F]/', $content_type)) {
            return false;
        }
        $base = strtolower(trim((string) strtok($content_type, ';')));
        $allowed = array('text/html', 'application/xhtml+xml');
        if ($allow_feed) {
            $allowed = array_merge($allowed, array('application/rss+xml', 'application/atom+xml', 'application/rdf+xml', 'application/xml', 'text/xml'));
        }
        return in_array($base, $allowed, true);
    }

    /**
     * Validate and preserve a page-cache Content-Type value without silently
     * converting malformed or unsupported response metadata to HTML.
     */
    public static function normalize_page_cache_content_type($content_type, $allow_feed = false) {
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
        return self::response_content_type_is_page_cacheable($content_type, $allow_feed) ? $content_type : '';
    }

    public static function response_body_is_complete($body, $content_type) {
        if (!is_scalar($body) || !is_scalar($content_type)) {
            return false;
        }
        $base = strtolower(trim((string) strtok((string) $content_type, ';')));
        if (!in_array($base, array('text/html', 'application/xhtml+xml'), true)) {
            return true;
        }
        $body = (string) $body;
        $looks_like_document = 1 === preg_match('/(?:<!doctype\s+html|<html(?:\s|>))/i', $body);
        return !$looks_like_document || false !== stripos($body, '</html>');
    }

    /**
     * Rule syntax: priority|scope|match|ttl_minutes|stale_minutes|action.
     * Scopes: path, post_type, front_page, feed, status (404 only), rest.
     * Actions: cache or bypass. First matching rule wins after priority sorting.
     */
    public static function parse_rules($raw = null) {
        if (null === $raw) {
            $raw = UCP_Options::get('cache_policy_rules', '');
        }
        $max_bytes = 512 * KB_IN_BYTES;
        $max_lines = 1000;
        if (is_array($raw)) {
            $parts = array();
            $bytes = 0;
            foreach (array_slice($raw, 0, $max_lines) as $item) {
                if (!is_scalar($item)) {
                    continue;
                }
                $item = substr((string) $item, 0, 4096);
                if ($bytes + strlen($item) > $max_bytes) {
                    break;
                }
                $parts[] = $item;
                $bytes += strlen($item) + 1;
            }
            $raw = implode("\n", $parts);
        } elseif (is_scalar($raw)) {
            $raw = (string) $raw;
            if (strlen($raw) > $max_bytes) {
                $raw = substr($raw, 0, $max_bytes);
            }
        } else {
            $raw = '';
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw, $max_lines + 1);
        if (!is_array($lines)) {
            return array();
        }
        $rules = array();
        $order = 0;
        foreach (array_slice($lines, 0, $max_lines) as $line) {
            if (strlen((string) $line) > 4096) {
                $line = substr((string) $line, 0, 4096);
            }
            $line = trim((string) $line);
            if ('' === $line || '#' === substr($line, 0, 1)) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 6) {
                continue;
            }
            $priority = min(999, max(0, absint($parts[0])));
            $scope = sanitize_key($parts[1]);
            $match = sanitize_text_field($parts[2]);
            $ttl = min(43200, max(0, absint($parts[3])));
            $stale = min(10080, max(0, absint($parts[4])));
            $action = sanitize_key($parts[5]);
            if (!in_array($scope, array('path', 'post_type', 'front_page', 'feed', 'status', 'rest'), true)) {
                continue;
            }
            if (!in_array($action, array('cache', 'bypass'), true)) {
                continue;
            }
            if ('status' === $scope && '404' !== $match) {
                continue;
            }
            if ('cache' === $action) {
                $ttl = max(1, $ttl);
            } else {
                $ttl = 0;
                $stale = 0;
            }
            if ('' === $match) {
                $match = '*';
            }
            $rules[] = array(
                'priority' => $priority,
                'scope' => $scope,
                'match' => $match,
                'ttl' => $ttl * MINUTE_IN_SECONDS,
                'stale' => $stale * MINUTE_IN_SECONDS,
                'action' => $action,
                'order' => $order++,
            );
            if (count($rules) >= self::MAX_RULES) {
                break;
            }
        }
        usort($rules, function ($a, $b) {
            return $a['priority'] === $b['priority'] ? $a['order'] <=> $b['order'] : $a['priority'] <=> $b['priority'];
        });
        return $rules;
    }

    public static function normalize_rules_text($raw) {
        $lines = array();
        foreach (self::parse_rules($raw) as $rule) {
            $lines[] = implode('|', array(
                $rule['priority'],
                $rule['scope'],
                $rule['match'],
                (int) floor($rule['ttl'] / MINUTE_IN_SECONDS),
                (int) floor($rule['stale'] / MINUTE_IN_SECONDS),
                $rule['action'],
            ));
        }
        return implode("\n", array_values(array_unique($lines)));
    }

    public static function has_rules($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        return !empty($settings['enable_cache_policy_rules']) && !empty(self::parse_rules(isset($settings['cache_policy_rules']) ? $settings['cache_policy_rules'] : ''));
    }

    public static function default_decision($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $cache_lifespan = isset($settings['cache_lifespan']) && is_scalar($settings['cache_lifespan']) ? $settings['cache_lifespan'] : 10;
        $stale_lifespan = isset($settings['stale_cache_lifespan']) && is_scalar($settings['stale_cache_lifespan']) ? $settings['stale_cache_lifespan'] : 24;
        return array(
            'matched' => false,
            'action' => 'cache',
            'ttl' => absint($cache_lifespan) * HOUR_IN_SECONDS,
            'stale' => !empty($settings['enable_stale_cache']) ? absint($stale_lifespan) * HOUR_IN_SECONDS : 0,
            'scope' => '',
            'match' => '',
            'priority' => null,
        );
    }

    public static function decision_for_current_request($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $context = array(
            'path' => class_exists('UCP_Helpers') ? UCP_Helpers::current_url_path() : '/',
            'post_type' => function_exists('get_post_type') ? (string) get_post_type() : '',
            'front_page' => function_exists('is_front_page') && is_front_page(),
            'feed' => function_exists('is_feed') && is_feed() ? (function_exists('get_query_var') ? (string) get_query_var('feed') : 'feed') : '',
            'status' => function_exists('is_404') && is_404() ? '404' : (string) (function_exists('http_response_code') ? (int) http_response_code() : 200),
            'rest' => '',
        );
        return self::decision_for_context($context, $settings);
    }

    public static function decision_for_rest_route($route, $settings = null) {
        $route = is_scalar($route) ? (string) $route : '';
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        return self::decision_for_context(array(
            'path' => '', 'post_type' => '', 'front_page' => false, 'feed' => '', 'status' => '200',
            'rest' => '/' . ltrim((string) $route, '/'),
        ), $settings);
    }

    public static function decision_for_context($context, $settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $decision = self::default_decision($settings);
        if (empty($settings['enable_cache_policy_rules'])) {
            return $decision;
        }
        foreach (self::parse_rules(isset($settings['cache_policy_rules']) ? $settings['cache_policy_rules'] : '') as $rule) {
            if (!self::rule_matches($rule, (array) $context)) {
                continue;
            }
            return array(
                'matched' => true,
                'action' => $rule['action'],
                'ttl' => $rule['ttl'],
                'stale' => !empty($settings['enable_stale_cache']) ? $rule['stale'] : 0,
                'scope' => $rule['scope'],
                'match' => $rule['match'],
                'priority' => $rule['priority'],
            );
        }
        return $decision;
    }

    private static function rule_matches($rule, $context) {
        $scope = $rule['scope'];
        $match = (string) $rule['match'];
        if ('front_page' === $scope) {
            return !empty($context['front_page']);
        }
        $value = isset($context[$scope]) && is_scalar($context[$scope]) ? (string) $context[$scope] : '';
        if ('' === $value) {
            return false;
        }
        return self::match_value($value, $match, in_array($scope, array('path', 'rest'), true));
    }

    private static function match_value($value, $match, $normalize_path = false) {
        $value = trim((string) $value);
        $match = trim((string) $match);
        if ('*' === $match) {
            return true;
        }
        if ($normalize_path) {
            $value = self::normalize_route_value($value);
            $match = self::normalize_route_value($match, true);
        }
        $match = str_replace('(.*)', '*', $match);
        if (false === strpos($match, '*')) {
            return 0 === strcasecmp($value, $match);
        }
        $pattern = preg_quote($match, '#');
        $pattern = str_replace('\*', '.*', $pattern);
        return 1 === preg_match('#^' . $pattern . '$#iD', $value);
    }

    private static function normalize_route_value($value, $is_pattern = false) {
        $value = '/' . ltrim((string) $value, '/');
        $value = UCP_Helpers::sanitize_preg_replace('#/+#', '/', $value);
        if (!$is_pattern || false === strpos($value, '*')) {
            $value = '/' === $value ? '/' : rtrim($value, '/');
        }
        return (string) $value;
    }
}
