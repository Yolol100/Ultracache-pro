<?php
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
    function ucp_dropin_unslash($value) {
        if (is_array($value)) {
            return array_map('ucp_dropin_unslash', $value);
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('ucp_dropin_sanitize_text')) {
    function ucp_dropin_sanitize_text($value) {
        if (!is_scalar($value)) {
            return '';
        }
        $value = (string) $value;
        $value = ucp_dropin_unslash($value);
        $value = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($value) : preg_replace('/<[^>]*>/', '', $value);
        $value = preg_replace('/[\r\n\t\0\x0B]+/', '', $value);
        $value = preg_replace('/[[:cntrl:]]+/', '', $value);
        return trim((string) $value);
    }
}

if (!function_exists('ucp_dropin_sanitize_key')) {
    function ucp_dropin_sanitize_key($key) {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('ucp_dropin_server_value')) {
    function ucp_dropin_server_value($key) {
        if (!isset($_SERVER[$key])) {
            return '';
        }
        return ucp_dropin_sanitize_text(ucp_dropin_unslash($_SERVER[$key]));
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

if ('cli' === PHP_SAPI) {
    return;
}

if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
    return;
}

if ('GET' !== strtoupper(ucp_dropin_server_value('REQUEST_METHOD'))) {
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
if (false !== strpos($cache_control, 'no-cache') || false !== strpos($cache_control, 'no-store') || false !== strpos($cache_control, 'private') || false !== strpos($cache_control, 'max-age=0') || false !== strpos($pragma, 'no-cache')) {
    return;
}

$config_file = WP_CONTENT_DIR . '/cache/ultracache-pro/dropin-config.php';
$config = is_readable($config_file) ? include $config_file : array();
$config = is_array($config) ? $config : array();
$exclude_paths = !empty($config['exclude_paths']) && is_array($config['exclude_paths']) ? $config['exclude_paths'] : array('cart', 'checkout', 'my-account', 'account', 'order-pay', 'order-received', 'add-payment-method', 'wc-api', 'wc-ajax', 'wp-json', 'wp-admin', 'wp-login.php', 'xmlrpc.php', 'customer-logout');
$exclude_cookies = !empty($config['exclude_cookies']) && is_array($config['exclude_cookies']) ? $config['exclude_cookies'] : array(
    'wordpress_logged_in_',
    'wordpress_sec_',
    'wp-postpass_',
    'comment_author_',
    'woocommerce_items_in_cart',
    'wp_woocommerce_session_',
    'woocommerce_cart_hash',
    'pll_language',
    '_icl_current_language',
    'wcml_client_currency',
    'woocommerce_multicurrency_forced_currency',
    'aelia_cs_selected_currency',
    'aelia_customer_country',
    'aelia_customer_state',
    'aelia_tax_exempt',
    'switch_to_olduser_',
    'wordpress_test_cookie',
    'cookie_notice_',
    'cmplz_',
    'complianz_',
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
$safe_cookies = !empty($config['safe_cookies']) && is_array($config['safe_cookies']) ? $config['safe_cookies'] : array('ct_', 'apbct_', 'ct_sfw', 'cleantalk', 'cookiebot', 'cookie_notice_', 'cmplz_', 'complianz_', 'joinchat_', '_ga', '_gid', '_gat', '_fbp', '_fbc');
$block_unknown_cookies = !array_key_exists('block_unknown_cookies', $config) || !empty($config['block_unknown_cookies']);

if (!function_exists('ucp_dropin_sanitize_query_pattern')) {
    function ucp_dropin_sanitize_query_pattern($pattern) {
        $pattern = strtolower((string) ucp_dropin_unslash($pattern));
        return preg_replace('/[^a-z0-9_\-*]/', '', $pattern);
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

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameters are inspected only to decide whether cached HTML may be served.
if (!empty($_GET) && !$cache_query_strings) {
    foreach (array_keys($_GET) as $query_key) {
        if (ucp_dropin_query_key_is_ignored($query_key, $cache_ignore_query_params, $cache_query_string_inclusions)) {
            continue;
        }
        if (!ucp_dropin_query_key_matches($query_key, $cache_query_string_inclusions)) {
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

if (!empty($_COOKIE) && is_array($_COOKIE)) {
    foreach (array_keys($_COOKIE) as $cookie_name) {
        $cookie_name = ucp_dropin_sanitize_key(ucp_dropin_unslash($cookie_name));
        $matched_cookie_rule = false;
        foreach ($exclude_cookies as $cookie_fragment) {
            $cookie_fragment = ucp_dropin_sanitize_key((string) $cookie_fragment);
            if ('' !== $cookie_fragment && false !== strpos($cookie_name, $cookie_fragment)) {
                return;
            }
        }
        foreach ($safe_cookies as $cookie_fragment) {
            $cookie_fragment = ucp_dropin_sanitize_key((string) $cookie_fragment);
            if ('' !== $cookie_fragment && false !== strpos($cookie_name, $cookie_fragment)) {
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

foreach ($exclude_paths as $excluded_fragment) {
    $excluded_fragment = '/' . ltrim((string) $excluded_fragment, '/');
    if ('/' !== $excluded_fragment && false !== strpos($path_only, $excluded_fragment)) {
        return;
    }
}

$path = rtrim($path_only, '/');
$path = '' === $path ? 'home' : trim(str_replace('/', '-', $path), '-');
$query = ucp_dropin_parse_url($uri, PHP_URL_QUERY);
$normalized_query = '';
if ($query) {
    parse_str($query, $query_args);
    if (is_array($query_args)) {
        $normalized_args = array();
        foreach ($query_args as $query_arg_key => $query_arg_value) {
            $query_arg_key = ucp_dropin_sanitize_key((string) $query_arg_key);
            if ('' === $query_arg_key) {
                continue;
            }
            if (ucp_dropin_query_key_is_ignored($query_arg_key, $cache_ignore_query_params, $cache_query_string_inclusions)) {
                continue;
            }
            if (!$cache_query_strings && !ucp_dropin_query_key_matches($query_arg_key, $cache_query_string_inclusions)) {
                continue;
            }
            if (is_array($query_arg_value)) {
                $query_arg_value = array_map('ucp_dropin_sanitize_text', ucp_dropin_unslash($query_arg_value));
                sort($query_arg_value);
            } else {
                $query_arg_value = ucp_dropin_sanitize_text((string) $query_arg_value);
            }
            $normalized_args[$query_arg_key] = $query_arg_value;
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
        $host = strtolower(trim(ucp_dropin_sanitize_text($host)));
        if ('' === $host) {
            return '';
        }
        if (preg_match('/^\[([a-f0-9:]+)\](?::\d+)?$/i', $host, $match)) {
            return '[' . strtolower($match[1]) . ']';
        }
        if (preg_match('/^([^:]+):\d+$/', $host, $match)) {
            $host = $match[1];
        }
        return preg_replace('/[^a-z0-9.-]/', '', $host);
    }
}
$host_header = ucp_dropin_normalize_host(ucp_dropin_server_value('HTTP_HOST'));
$allowed_hosts = !empty($config['allowed_hosts']) && is_array($config['allowed_hosts']) ? $config['allowed_hosts'] : array();
if (!empty($allowed_hosts) && '' !== $host_header) {
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
$is_mobile = $cache_mobile_separately && 1 === preg_match('/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi|iPhone|iPad|iPod/i', $user_agent);
$suffix = 'guest' . ($is_mobile ? '-mobile' : '');
$cache_key = preg_replace('/[^A-Za-z0-9_.-]/', '-', $host_key . '-' . $path . '-' . $suffix . '-' . $query_key);
$cache_file = WP_CONTENT_DIR . '/cache/ultracache-pro/pages/' . $cache_key . '.html';
$ttl = !empty($config['ttl']) ? max(60, (int) $config['ttl']) : 10 * 3600;

if (is_file($cache_file) && is_readable($cache_file) && (filemtime($cache_file) + $ttl) > time()) {
    $ucp_cached_html = file_get_contents($cache_file);
    if (is_string($ucp_cached_html) && '' !== $ucp_cached_html) {
        $file_mtime    = (int) filemtime($cache_file);
        $remaining_ttl = max(0, ($file_mtime + $ttl) - time());
        $etag          = '"' . dechex($file_mtime) . '-' . dechex(strlen($ucp_cached_html)) . '"';
        $last_modified = gmdate('D, d M Y H:i:s', $file_mtime) . ' GMT';

        // 304 Not Modified support (RFC 7232 — accept comma-separated lists and weak validators).
        $if_none_match    = isset($_SERVER['HTTP_IF_NONE_MATCH'])    ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH'])    : '';
        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';
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
                    // Strip optional weak prefix for comparison; advanced-cache emits strong ETags.
                    if (0 === strncmp($candidate, 'W/', 2)) {
                        $candidate = substr($candidate, 2);
                    }
                    if ($candidate === $etag) {
                        $etag_match = true;
                        break;
                    }
                }
            }
        }
        if ($etag_match || ($if_modified_since && strtotime($if_modified_since) >= $file_mtime)) {
            header('X-UltraCache: HIT-304');
            header('ETag: ' . $etag);
            header('Last-Modified: ' . $last_modified);
            header('Cache-Control: public, max-age=' . (int) $remaining_ttl . ', stale-while-revalidate=60, stale-if-error=3600');
            http_response_code(304);
            exit;
        }

        header('X-UltraCache: HIT');
        header('Cache-Control: public, max-age=' . (int) $remaining_ttl . ', stale-while-revalidate=60, stale-if-error=3600');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . $last_modified);
        header('Vary: Accept-Encoding');
        header('X-UltraCache-Age: ' . (int)(time() - $file_mtime));
        header('Content-Type: text/html; charset=UTF-8');

        // Serve pre-compressed variants first; fall back to on-the-fly compression only when needed.
        $accept_encoding = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? (string) $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
        if (false !== strpos($accept_encoding, 'br') && is_file($cache_file . '.br') && is_readable($cache_file . '.br')) {
            $encoded = file_get_contents($cache_file . '.br');
            if (is_string($encoded) && '' !== $encoded) {
                header('Content-Encoding: br');
                header('Content-Length: ' . strlen($encoded));
                echo $encoded; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-compressed cached HTML.
                exit;
            }
        }
        if (false !== strpos($accept_encoding, 'gzip') && is_file($cache_file . '.gz') && is_readable($cache_file . '.gz')) {
            $encoded = file_get_contents($cache_file . '.gz');
            if (is_string($encoded) && '' !== $encoded) {
                header('Content-Encoding: gzip');
                header('Content-Length: ' . strlen($encoded));
                echo $encoded; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-compressed cached HTML.
                exit;
            }
        }
        if (strlen($ucp_cached_html) > 860) {
            if (false !== strpos($accept_encoding, 'br') && function_exists('brotli_compress')) {
                $brotli = @brotli_compress($ucp_cached_html, 4);
                if (false !== $brotli && '' !== $brotli) {
                    header('Content-Encoding: br');
                    header('Content-Length: ' . strlen($brotli));
                    echo $brotli; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- brotli-compressed cached HTML.
                    exit;
                }
            }
            if (false !== strpos($accept_encoding, 'gzip') && function_exists('gzencode')) {
                $gzipped = gzencode($ucp_cached_html, 6);
                if (false !== $gzipped) {
                    header('Content-Encoding: gzip');
                    header('Content-Length: ' . strlen($gzipped));
                    echo $gzipped; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gzipped cached HTML.
                    exit;
                }
            }
        }

        header('Content-Length: ' . strlen($ucp_cached_html));
        echo $ucp_cached_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cached HTML captured from WordPress output buffering.
        exit;
    }
}
