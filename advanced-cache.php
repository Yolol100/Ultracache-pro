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
    'wordpress_test_cookie',
    'cookie_notice_',
    'cmplz_',
    'complianz_',
);
$cache_query_strings = !empty($config['cache_query_strings']);
$cache_query_string_inclusions = !empty($config['cache_query_string_inclusions']) && is_array($config['cache_query_string_inclusions']) ? $config['cache_query_string_inclusions'] : array();
$cache_mobile_separately = !array_key_exists('cache_mobile_separately', $config) || !empty($config['cache_mobile_separately']);

if (!function_exists('ucp_dropin_query_key_matches')) {
    function ucp_dropin_query_key_matches($key, $patterns) {
        $key = ucp_dropin_sanitize_key((string) $key);
        foreach ((array) $patterns as $pattern) {
            $pattern = ucp_dropin_sanitize_key((string) $pattern);
            if ('' === $pattern) {
                continue;
            }
            if (substr($pattern, -1) === '*' && 0 === strpos($key, substr($pattern, 0, -1))) {
                return true;
            }
            if ($key === $pattern) {
                return true;
            }
        }
        return false;
    }
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameters are inspected only to decide whether cached HTML may be served.
if (!empty($_GET) && !$cache_query_strings) {
    foreach (array_keys($_GET) as $query_key) {
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
        foreach ($exclude_cookies as $cookie_fragment) {
            $cookie_fragment = (string) $cookie_fragment;
            if ('' !== $cookie_fragment && false !== strpos($cookie_name, $cookie_fragment)) {
                return;
            }
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
$host = !empty($config['home_host']) ? ucp_dropin_sanitize_text((string) $config['home_host']) : ucp_dropin_server_value('HTTP_HOST');
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
        header('X-UltraCache: HIT');
        header('Cache-Control: public, max-age=' . (int) $ttl);
        header('Content-Type: text/html; charset=UTF-8');
        echo $ucp_cached_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cached HTML generated by WordPress output buffering.
        exit;
    }
}
